#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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

chdir(__DIR__ . '/../../');
include('./include/cli_check.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/lib/time.php');
include_once($config['base_path'] . '/plugins/flowview/database.php');
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

flowview_connect();

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

$debug     = false;
$maint     = false;
$force     = false;
$scheduled = false;
$report_id = false;

$shortopts = 'VvHh';
$longopts = array(
	'scheduled::',
	'report-id::',
	'debug',
	'force',
	'version',
	'help',
);

$options = getopt($shortopts, $longopts);

foreach($options as $arg => $value) {
	switch($arg) {
		case 'force':
			$force = true;

			break;
		case 'debug':
			$debug = true;

			break;
		case 'scheduled':
			$scheduled = true;

			break;
		case 'report-id':
			$report_id = $value;

			break;
		case 'version':
			display_version();
			exit(0);

			break;
		case 'help':
			display_help();
			exit(0);

			break;
		default:
			print 'ERROR: Invalid option ' . $arg . PHP_EOL;
			exit(1);

			break;
	}
}

if ($scheduled == true) {
	/* silently end if the registered process is still running, or process table missing */
	if (!$force) {
		if (!register_process_start('flowsched', 'master', 0, 1200)) {
			exit(0);
		}
	}

	$start = microtime(true);
	$run = 0;

	$concurrent_processes = read_config_option('reports_concurrent');
	if (empty($concurrent_processes)) {
		set_config_option('reports_concurrent', 1);
		$concurrent_processes = 1;
	}

	$reports = db_fetch_assoc_prepared('SELECT *
		FROM reports_queued
		WHERE status = ?
		AND source = ?',
		array('pending', 'flowview'));

	while(true) {
		$running = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM processes
			WHERE taskname = ?
			AND tasktype LIKE ?',
			array('flowsched', 'child%'));

		if ($running < $concurrent_processes) {
			$report = db_fetch_row_prepared('SELECT *
				FROM reports_queued
				WHERE source = ?
				AND status = ?
				LIMIT 1',
				array('flowview', 'pending'));

			if (cacti_sizeof($report)) {
				reports_run($report['id']);

				$run++;
			} else {
				break;
			}

			sleep(1);
		}
	}

	$end = microtime(true);

	cacti_log(sprintf('FLOWVIEW REPORT STATS: Time:%0.2f Reports:%s', $end - $start, $run), false, 'SYSTEM');

	if (!$force) {
		unregister_process('flowsched', 'master', 0);
	}
} else {
	if ($report_id === false) {
		cacti_log('FATAL: Flowview Schedule Report ID not provided', true, 'FLOWVIEW');
		exit(1);
	}

	$report = db_fetch_row_prepared('SELECT rq.*
		FROM reports_queued AS rq
		WHERE rq.source = ?
		AND rq.id = ?',
		array('flowview', $report_id));

	if (cacti_sizeof($report)) {
		$id = $report['source_id'];

		/* silently end if the registered process is still running, or process table missing */
		if (!$force) {
			if (!register_process_start('flowsched', "child_$id", 0, 1200)) {
				exit(0);
			}
		}

		$t = time();
		$r = intval($t / 60) * 60;
		$start = microtime(true);

		$schedule = flowview_db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_schedules
			WHERE id = ?',
			array($id));

		if (cacti_sizeof($schedule)) {
			flowview_db_execute_prepared('UPDATE plugin_flowview_schedules
				SET lastsent = ?, start = ?
				WHERE id = ?',
				array($r, date('Y-m-d H:i:s'), $id));

			plugin_flowview_run_schedule($id, $report_id);

			$end = microtime(true);

			$cacti_stats = sprintf('Time:%01.4f Schedule:%s', round($end-$start,2), $id);

			cacti_log('FLOWVIEW SCHEDULE STATS: ' . $cacti_stats , true, 'SYSTEM');
		}

		if (!$force) {
			unregister_process('flowsched', "child_$id", 0);
		}
	} else {
		cacti_log("WARNING: Unable to find Report ID $report_id", false, 'REPORTS');
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti FlowView Schedule Poller, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'usage: schedule_run.php --schedule=ID [--debug]' . PHP_EOL . PHP_EOL;

	print 'Runs the Selected Report and Sends to Receivers.' . PHP_EOL . PHP_EOL;

	print 'Required:' . PHP_EOL;
	print '    --schedule=ID The Schedule to Run.' . PHP_EOL . PHP_EOL;
	print 'Options:' . PHP_EOL;
	print '    --force Force running even if another is running.' . PHP_EOL . PHP_EOL;
	print '    --debug Provide some debug output during collection.' . PHP_EOL . PHP_EOL;
}

