#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

chdir(dirname(__FILE__) . '/../../');
include('./include/cli_check.php');
include_once('./lib/time.php');
include_once('./plugins/flowview/functions.php');

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

$debug = false;
$maint = false;
$force = false;

$shortopts = 'VvHh';
$longopts = array(
	'schedule::',
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
		case 'schedule':
			$id = $value;

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

/* silently end if the registered process is still running, or process table missing */
if (!$force) {
	if (!register_process_start('flowsched', $id, 0, 1200)) {
		exit(0);
	}
}

$t = time();
$r = intval($t / 60) * 60;
$start = microtime(true);

$schedule = db_fetch_row_prepared('SELECT *
	FROM plugin_flowview_schedules
	WHERE id = ?',
	array($id));

if (cacti_sizeof($schedule)) {
	db_execute_prepared('UPDATE plugin_flowview_schedules
		SET lastsent = ?
		WHERE id = ?',
		array($r, $id));

	plugin_flowview_run_schedule($id);

	$end = microtime(true);

	$cacti_stats = sprintf('Time:%01.4f Schedule:%s', round($end-$start,2), $id);

	cacti_log('FLOWVIEW SCHEDULE STATS: ' . $cacti_stats , true, 'SYSTEM');
}

if (!$force) {
	unregister_process('flowsched', $id, 0);
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

