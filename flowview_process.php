<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
include_once('./lib/poller.php');
include_once('./plugins/flowview/functions.php');

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

$debug = false;
$maint = false;

$shortopts = 'VvHh';
$longopts = array(
	'maint',
	'debug',
	'version',
	'help',
);

$options = getopt($shortopts, $longopts);

foreach($options as $arg => $value) {
	switch($arg) {
		case 'maint':
			$maint = true;

			break;
		case 'debug':
			$debug = true;

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
			print 'ERROR: Invalid options' . PHP_EOL;
			exit(1);

			break;
	}
}

$templates = array();

$t = time();
$r = intval($t / 60) * 60;
$start = microtime(true);

$last  = read_config_option('flowview_last_change');
if (empty($last)) {
	$last = time() - read_config_option('poller_interval');
	set_config_option('flowview_last_change', $last);
} else {
	set_config_option('flowview_last_change', time());
}

// Maintenance is at midnight
if (date('z', $last) != date('z', time())) {
	$maint = true;
}

$schedules = db_fetch_assoc("SELECT *
	FROM plugin_flowview_schedules
	WHERE enabled = 'on'
	AND ($t - sendinterval > lastsent)");

if (count($schedules)) {
	$php = read_config_option('path_php_binary');
	foreach ($schedules as $s) {
		debug('Running Schedule ' . $s['id']);
		exec_background($php, ' -q ' . $config['base_path'] . '/plugins/flowview/run_schedule.php --schedule=' . $s['id']);
	}
}

$total = db_fetch_cell('SELECT COUNT(*)
	FROM plugin_flowview_devices');

$tables = get_tables_range($last);
$records = 0;

foreach($tables as $table) {
	$records += db_fetch_cell_prepared("SELECT COUNT(*)
		FROM $table
		WHERE end_time >= ?",
		array(date('Y-m-d H:i:s', $last)));
}

if ($maint) {
	debug('Performing Table Maintenance');

	// 0 - Daily, 1 - Hourly
	$partition_mode = read_config_option('flowview_partition');

	$retention_days = read_config_option('flowview_retention');
	$today_day      = date('z');
	$today_year     = date('Y');

	if ($today_day - $retention_days < 0) {
		$retention_year = $today_year - 1;
		$min_day        = 365 + $today_day - $retention_days;

		if ($partition_mode == 0) {
			$min_day = substr('000' . $min_day, -3);
		} else {
			$min_day = substr('000' . $min_day . '00', -5);
		}
	} else {
		$retention_year = $today_year;
		$min_day        = $today_day - $retention_days;

		if ($partition_mode == 0) {
			$min_day = substr('000' . $min_day, -3);
		} else {
			$min_day = substr('000' . $min_day . '00', -5);
		}
	}

	$remove_lessthan = $retention_year . $min_day;

	debug('Removing partitioned tables with suffix less than ' . $remove_lessthan);

	$tables = db_fetch_assoc("SELECT TABLE_NAME
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_NAME LIKE 'plugin_flowview_raw_%'
		ORDER BY TABLE_NAME");

	$dropped = 0;

	if (cacti_sizeof($tables)) {
		foreach($tables as $t) {
			$date_part = str_replace('plugin_flowview_raw_', '', $t['TABLE_NAME']);

			if ($date_part <  $remove_lessthan) {
				$dropped++;
				debug("Removing partitioned table 'plugin_flowview_raw_" . $date_part . "'");
				db_execute('DROP TABLE plugin_flowview_raw_' . $date_part);
			}
		}
	}

	debug('Total number of partition tables dropped is ' . $dropped);
}

$end = microtime(true);

$cacti_stats = sprintf(
	'Time:%01.4f ' .
	'Listeners:%s ' .
	'Newrecs:%s ' .
	'Schedules:%s',
	round($end-$start,2),
	$total,
	$records,
	cacti_sizeof($schedules)
);

set_config_option('flowview_stats', $cacti_stats);

/* log to the logfile */
cacti_log('FLOWVIEW STATS: ' . $cacti_stats , true, 'SYSTEM');

function debug($string) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Flow Poller, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . 'usage: flowview_process.php [--debug]' . PHP_EOL . PHP_EOL;

	print 'Cacti poller processes reports and imports latest legacy data into' . PHP_EOL;
	print 'the Cacti database.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '    --maint Force table maintenance immediately.' . PHP_EOL . PHP_EOL;
	print '    --debug Provide some debug output during collection.' . PHP_EOL . PHP_EOL;
}

