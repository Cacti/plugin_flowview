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
 * Pre-1.3 Cacti compatibility shim for reports_log_and_notify():
 * records a completed queued report's result and notification
 * settings into the reports_log table and sends its notification
 * (attachments/email) via the report's stored delivery settings.
 * Called from this plugin's report-running code on Cacti versions
 * lacking the native reports_log_and_notify() function.
 *
 * @param int    $id           The reports_queued id being completed.
 * @param float  $start_time   The Unix timestamp the run started at.
 * @param string $report_type  The rendered report format (e.g.
 *                            'html').
 * @param string $source       The plugin/source name generating the
 *                            report (e.g. 'flowview').
 * @param int    $source_id    The source's own report/schedule id.
 * @param string $subject      The notification email subject.
 * @param array  $raw_data     Reference, the report's raw result data.
 * @param mixed  $oput_raw     Reference, the raw rendered output.
 * @param string $oput_html    Reference, the HTML-rendered output body.
 * @param string $oput_text    Reference, the plain-text output body
 *                            (defaulted to '' if null).
 * @param array  $attachments  Optional list of file attachments to
 *                            include.
 * @param array|false $headers Optional extra email headers to include.
 *
 * @return void
 */
function reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, &$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = [], $headers = false) {
	$report = db_fetch_row_prepared('SELECT *
		FROM reports_queued
		WHERE id = ?',
		[$id]);

	if ($oput_text == null) {
		$oput_text = '';
	}

	$fromemail = read_config_option('settings_from_email');
	if ($fromemail == '') {
		$fromemail = 'cacti@cacti.net';
	}

	$fromname = read_config_option('settings_from_name');
	if ($fromname == '') {
		$fromname = __('Cacti %s', ucfirst($source), 'flowview');
	}

	$from[0] = $fromemail;
	$from[1] = $fromname;

	if (cacti_sizeof($report)) {
		if ($report['notification'] != '') {
			$notifications = json_decode($report['notification'], true);

			foreach($notifications as $type => $data) {
				switch($type) {
					case 'email':
						if (!isset($data['to_email'])) {
							cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Missing 'to_email' attribute in request", $report['name']), false, 'REPORTS');
							break;
						} else {
							$to_email = $data['to_email'];
						}

						if (isset($data['cc_email'])) {
							$cc_email = $data['cc_email'];
						} else {
							$cc_email = '';
						}

						if (isset($data['bcc_email'])) {
							$bcc_email = $data['bcc_email'];
						} else {
							$bcc_email = '';
						}

						if (isset($data['reply_to'])) {
							$reply_to = $data['reply_to'];
						} else {
							$reply_to = '';
						}

						mailer($from, $to_email, $cc_email, $bcc_email, $reply_to, $subject, $oput_html, $oput_text, $attachments, $headers);

						break;
					case 'notification_list':
						if (!isset($data['id'])) {
							cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Missing notification list 'id' attribute in request", $report['name']), false, 'REPORTS');
							break;
						} else {
							$list = db_fetch_row_prepared('SELECT *
								FROM plugin_notify_list
								WHERE id = ?',
								[$data['id']]);

							if (cacti_sizeof($list)) {
								/* process the format file */
								$report_tag  = '';
								$theme       = 'modern';
								$output_html = '';
								$format      = $list['format_file'] != '' ? $list['format_file']:'default';

								$format_ok = reports_load_format_file($format, $output, $report_tag, $theme);

								flowview_debug('Format File Loaded, Format is ' . ($format_ok ? 'Ok':'Not Ok') . ', Report Tag is ' . $report_tag);

								if ($format_ok) {
									if ($report_tag) {
										$oput_html = str_replace('<REPORT>', $oput_raw, $output);
									} else {
										$oput_html = $output . PHP_EOL . $oput_raw;
									}
								} else {
									$oput_html = $oput_raw;
								}

								$to_email   = $list['emails'];
								$cc_emails  = isset($list['cc_emails']) ? $list['cc_emails']:'';
								$bcc_emails = $list['bcc_emails'];
								$reply_to   = isset($list['reply_to'])  ? $list['reply_to']:'';

								mailer($from, $to_email, $cc_emails, $bcc_emails, $reply_to, $subject, $oput_html, $oput_text, $attachments, $headers);
							} else {
								cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Unable to locate notification list '%s'", $report['name'], $id), false, 'REPORTS');
							}
						}

						break;
					default:
						cacti_log(sprintf("WARNING: Email Report '%s' not sent!  Unknown notification type '%s' attribute in request", $report['name'], $type), false, 'REPORTS');
						break;
				}
			}
		}

		$end_time = microtime(true);

		$save = [];

		$save['id']                 = 0;
		$save['name']               = $report['name'];
		$save['source']             = $source;
		$save['source_id']          = $source_id;
		$save['report_output_type'] = $report_type;
		$save['report_raw_data']    = json_encode($raw_data);
		$save['report_raw_output']  = $oput_raw;
		$save['report_html_output'] = $oput_html;
		$save['report_txt_output']  = $oput_text;
		$save['send_type']          = $report['request_type'];
		$save['send_time']          = date('Y-m-d H:i:s');
		$save['run_time']           = $end_time - $start_time;
		$save['sent_by']            = $report['requested_by'];
		$save['sent_id']            = $report['requested_id'];
		$save['notification']       = $report['notification'];

		sql_save($save, 'reports_log');
	}
}

/**
 * Pre-1.3 Cacti compatibility shim for reports_queue(): inserts a new
 * pending row into the reports_queued table, recording who requested
 * it, and raises/logs a scheduling confirmation or error message.
 * Called from this plugin's report-scheduling code on Cacti versions
 * lacking the native reports_queue() function.
 *
 * @param string $name          The report's display name.
 * @param string $request_type  The type of request/report being
 *                              queued.
 * @param string $source        The plugin/source name generating the
 *                              report (e.g. 'flowview').
 * @param int    $source_id     The source's own report/schedule id.
 * @param string $command       The CLI command to run to generate the
 *                              report.
 * @param mixed  $notification  The notification/delivery settings to
 *                              store (JSON-encoded).
 *
 * @return void
 */
function reports_queue($name, $request_type, $source, $source_id, $command, $notification) {
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
 *
 * @param int $id The reports_queued id to run.
 *
 * @return void
 *
 * @global array $config Cacti global configuration array; used to
 *                       include the poller library.
 */
function reports_run($id) {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	$report = db_fetch_row_prepared('SELECT *
		FROM reports_queued
		WHERE id = ?',
		[$id]);

	if (cacti_sizeof($report)) {
		db_execute_prepared('UPDATE reports_queued
			SET status = ?, start_time = ?
			WHERE id = ?',
			array('running', date('Y-m-d H:i:s'), $id));
	} else {
		return false;
	}

	$start = microtime(true);

	$return_code = 0;
	$output      = [];
	$command     = $report['run_command'] . " --report-id=$id";
	$timeout     = $report['run_timeout'];

	cacti_log("The report:$id has command was:$command");

	$last_line = exec_with_timeout($command, $output, $return_code, $timeout);

	$end  = microtime(true);

	$stats = sprintf("FLOWVIEW REPORT STATS: Time:0.2f Report:'%s' Source:%s SourceID:%s", $end-$start, $report['name'], $report['source'], $report['source_id']);

	cacti_log($stats, false, 'SYSTEM');

	db_execute_prepared('DELETE FROM reports_queued WHERE id = ?', [$id]);
}

