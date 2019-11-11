<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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
include_once('./plugins/flowview/functions.php');

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

$debug = false;

$shortopts = 'VvHh';
$longopts = array(
	'debug',
	'version',
	'help',
);

$options = getopt($shortopts, $longopts);

foreach($options as $arg => $value) {
	switch($arg) {
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
			print "ERROR: Invalid options" . PHP_EOL;
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

$schedules = db_fetch_assoc("SELECT *
	FROM plugin_flowview_schedules
	WHERE enabled = 'on'
	AND ($t - sendinterval > lastsent)");

if (count($schedules)) {
	foreach ($schedules as $s) {
		db_execute_prepared("UPDATE plugin_flowview_schedules
			SET lastsent = ?
			WHERE id = ?",
			array($r, $s['id']));

		plugin_flowview_run_schedule($s['id']);
	}
}

$total = db_fetch_cell('SELECT COUNT(*)
	FROM plugin_flowview_devices');

$listeners = db_fetch_assoc('SELECT *
	FROM plugin_flowview_devices
	WHERE cmethod = 1');

$initial_import_completed = read_config_option('flowview_legacy_import_completed');
if (cacti_sizeof($listeners)) {
	if ($initial_import_completed == 'true') {
		$last_date      = read_config_option('flowview_last_import');
		$flow_directory = read_config_option('path_flows_dir');
		set_config_option('flowview_last_import', time());

		if (file_exists($flow_directory)) {
			foreach($listeners as $l) {
				$dir_iterator = new RecursiveDirectoryIterator($flow_directory . '/' . $l['folder']);
				$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

				foreach($iterator as $file) {
					if (strpos($file, 'ft-') !== false && filemtime($file) >= $last_date) {
						$rfile = str_replace(rtrim($flow_directory, '/') . '/', '', $file);

						$parts = explode('/', $rfile);

						$listener_id = $l['id'];

						$fstart = microtime(true);
						debug("Processing file: $rfile");
						flowview_load_flow_file_into_database($file, $listener_id);
						$fend = microtime(true);
						debug('File: ' . $rfile . ', Total time ' . round($fend - $fstart, 2));
					}
                }
            }
		} else {
			print 'ERROR: Flow directory does not exist.' . PHP_EOL;
			exit(1);
		}
	} else {
		cacti_log('WARNING: Legacy flows must be imported into MySQL before current flow data can be imported', false, 'FLOWVIEW');
		exit(1);
	}
}

$tables = get_tables_range($last);
$records = 0;

foreach($tables as $table) {
	$records += db_fetch_cell_prepared("SELECT COUNT(*)
		FROM $table
		WHERE end_time >= ?",
		array(date('Y-m-d H:i:s', $last)));
}

$end = microtime(true);

$cacti_stats = sprintf(
	'time:%01.4f ' .
	'listeners:%s ' .
	'legacy:%s ' .
	'newrecs:%s ' .
	'schedules:%s',
	round($end-$start,2),
	sizeof($total),
	sizeof($listeners),
	$records,
	sizeof($schedules)
);

set_config_option('flowview_stats', $cacti_stats);

/* log to the logfile */
cacti_log('FLOWVIEW STATS: ' . $cacti_stats , TRUE, 'SYSTEM');

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

	print PHP_EOL . "usage: flowview_process.php [--debug]" . PHP_EOL . PHP_EOL;

	print "Cacti poller processes reports and imports latest legacy data into" . PHP_EOL;
	print "the Cacti database." . PHP_EOL . PHP_EOL;

	print "Options:" . PHP_EOL;
	print "    --debug Provide some debug output during collection." . PHP_EOL . PHP_EOL;
}

