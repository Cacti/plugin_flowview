<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Compatibility name for callers on Cacti versions without native reporting.
 * False means safe to retry; null means delivery requires manual reconciliation.
 */
function reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, &$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = [], $headers = []): ?bool {
	return flowview_reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, $raw_data, $oput_raw, $oput_html, $oput_text, $attachments, $headers);
}

/**
 * Pre-1.3 Cacti compatibility shim for reports_queue(): inserts a new
 * pending row into the reports_queued table, recording who requested
 * it, and raises/logs a scheduling confirmation or error message.
 * Called from this plugin's report-scheduling code on Cacti versions
 * lacking the native reports_queue() function.
 * queued.
 * report (e.g. 'flowview').
 * report.
 * store (JSON-encoded).
 *
 * @param mixed $name
 * @param mixed $request_type
 * @param mixed $source
 * @param mixed $source_id
 * @param mixed $command
 * @param mixed $notification
 *
 * @return void
 */
function reports_queue($name, $request_type, $source, $source_id, $command, $notification): void {
	if (isset($_SESSION['sess_user_id'])) {
		$requested_id = $_SESSION['sess_user_id'];
		$requested_by = db_fetch_cell_prepared('SELECT username
			FROM user_auth
			WHERE id = ?',
			[$requested_id]);

		if ($requested_by == '') {
			$requested_by = 'unknown';
		}
	} else {
		$requested_id = -1;
		$requested_by = 'system';
	}

	$save = [];

	$save['id']             = 0;
	$save['name']           = $name;
	$save['request_type']   = $request_type;
	$save['source']         = $source;
	$save['source_id']      = $source_id;
	$save['status']         = 'pending';
	$save['run_command']    = $command;
	$save['scheduled_time'] = date('Y-m-d H:i:s');
	$save['notification']   = json_encode($notification);
	$save['requested_by']   = $requested_by;
	$save['requested_id']   = $requested_id;

	$id = sql_save($save, 'reports_queued');

	if ($id > 0) {
		if ($requested_id > 0) {
			raise_message('report_scheduled', __esc("The Report '%s' from source %s with id %s is scheduled to run!", $name, $source, $source_id, 'flowview'), MESSAGE_LEVEL_INFO);
		} else {
			cacti_log(sprintf("The Report '%s' from source %s with id %s is scheduled to run!", $name, $source, $source_id), false, 'REPORTS');
		}
	} else {
		if ($requested_id > 0) {
			raise_message('report_not_scheduled', __("The Report '%s' from source %s with id %s was not scheduled to run due to an error!", $name, $source, $source_id, 'flowview'), MESSAGE_LEVEL_ERROR);
		} else {
			cacti_log(sprintf("FATAL: The Report '%s' from source %s with id %s was not scheduled to run due to an error!", $name, $source, $source_id), false, 'REPORTS');
		}
	}
}

/**
 * Pre-1.3 Cacti compatibility shim for reports_run(): marks a queued
 * report as running (recording its start time) and launches its
 * generation. Called from this plugin's report-running code on Cacti
 * versions lacking the native reports_run() function.
 * otherwise no explicit value is returned after
 * launching generation.
 * @global array $config Cacti global configuration array; used to
 * include the poller library.
 *
 * @param mixed $id
 *
 * @return bool
 */
function reports_run($id): bool {
	global $config;

	$report = db_fetch_row_prepared('SELECT *
		FROM reports_queued
		WHERE id = ?',
		[$id]);

	if (cacti_sizeof($report)) {
		if ($report['source'] === 'flowview') {
			return flowview_reports_run((int) $id, $report);
		}
		require_once($config['base_path'] . '/lib/poller.php');
		db_execute_prepared('UPDATE reports_queued
			SET status = ?, start_time = ?
			WHERE id = ?',
			array('running', date('Y-m-d H:i:s'), $id));
	} else {
		return false;
	}

	$start = microtime(true);

	$return_code = 1;
	$output      = [];
	$command     = $report['run_command'] . " --report-id=$id";
	$timeout     = $report['run_timeout'];

	cacti_log("The report:$id has command was:$command");

	exec_with_timeout($command, $output, $return_code, $timeout);

	$end  = microtime(true);

	$stats = sprintf("FLOWVIEW REPORT STATS: Time:%0.2f Report:'%s' Source:%s SourceID:%s", $end-$start, $report['name'], $report['source'], $report['source_id']);

	cacti_log($stats, false, 'SYSTEM');

	db_execute_prepared('DELETE FROM reports_queued WHERE id = ?', [$id]);
	return true;
}

