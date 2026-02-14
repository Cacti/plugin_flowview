#!/usr/bin/env php
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');

chdir(__DIR__ . '/../../');
require('./include/cli_check.php');
require_once($config['base_path'] . '/lib/poller.php');

flowview_connect();

/* get the boost polling cycle */
$max_run_duration = read_config_option('flowview_query_max_runtime');

if (empty($max_run_duration)) {
	set_config_option('flowview_query_max_runtime', 60);
	$max_run_duration = 60;
}

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug    = false;
$query_id = false;
$shard_id = false;

global $shard_id, $query_id;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

				break;
			case '--query-id':
				$query_id = $value;

				break;
			case '--shard-id':
				$shard_id = $value;

				break;
			case '--version':
			case '-V':
			case '-v':

				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':

				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;

				display_help();
				exit(1);
		}
	}
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* take time and log performance data */
$start = microtime(true);
$start_time = time();

db_debug(sprintf('Variables Processed for Query ID:%s and Shard ID:%s', $query_id, $shard_id));

/* let's give this script lot of time to run for ever */
ini_set('max_execution_time', '0');

if ($shard_id === false) {
	db_debug('About to start parent');

	/* we will warn if the process is taking extra long */
	if (!register_process_start('flowview', "db_query_{$query_id}", 0, 300)) {
		exit(0);
	}

	$current_time  = time();

	$stats = parallel_database_parent_runner($query_id);

	unregister_process('flowview', "db_query_{$query_id}", 0);

	$end = microtime(true);

	$lstats = sprintf('PARALLEL STATS: Time:%0.3f Threads:%d Shards:%d Cached:%d TotalSize:%0.2fGB CachedSize:%0.2fGB',
		$end - $start,
		$stats['threads'],
		$stats['shards'],
		$stats['cached'],
		$stats['total_size'],
		$stats['cached_size']
	);

	cacti_log($lstats, false, 'FLOWVIEW');
} else {
	/* we will warn if the process is taking extra long */
	if (!register_process_start('flowview', "db_shard_{$query_id}", $shard_id, 300)) {
		exit(0);
	}

	parallel_database_child_runner($query_id, $shard_id);

	unregister_process('flowview', "db_shard_{$query_id}", $shard_id);

	exit(0);
}

function sig_handler($signo) {
	global $shard_id, $query_id, $config, $current_lock;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Flowview Database Parallel Poller terminated by user', false, 'BOOST');

			if ($shard_id) {
				unregister_process('flowview', "db_shard{$query_id}", $shard_id, getmypid());
			} else {
				unregister_process('flowview', "db_query{$query_id}", 0, getmypid());
			}

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

function flowview_launch_workers($query_id, $threads, $running) {
	global $config, $debug;

	$php_binary = read_config_option('path_php_binary');
	$launching  = $threads - $running;
	$pending    = flowview_db_fetch_cell_prepared('SELECT COUNT(*)
		FROM parallel_database_query_shard
		WHERE query_id = ?
		AND status = ?',
		array($query_id, 'pending'));

	$redirect   = '';

	if ($pending > 0 && $launching > 0) {
		db_debug("About to launch $launching processes.");

		while($launching > 0 && $pending > 0) {
			$shard_id = flowview_db_fetch_cell_prepared('SELECT shard_id
				FROM parallel_database_query_shard
				WHERE query_id = ?
				AND status = ?
				LIMIT 1',
				array($query_id, 'pending'));

			if ($shard_id >= 0 && $shard_id != '') {
				flowview_db_execute_prepared('UPDATE parallel_database_query_shard
					SET status = ?
					WHERE query_id = ?
					AND shard_id = ?',
					array('running', $query_id, $shard_id));

				db_debug('Launching FlowView Database Shard Process ' . $shard_id);

				//cacti_log('NOTE: Launching FlowView Database Shard Process ' . $shard_id, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

				exec_background($php_binary, $config['base_path'] . "/plugins/flowview/flowview_runner.php --query-id=$query_id --shard-id=$shard_id" . ($debug ? ' --debug':''), $redirect);
			} else {
				break;
			}

			$launching--;
			$pending--;
		}
	}

	usleep(5000);
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_version();
	print "Cacti Boost RRD Update Poller, Version $version " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 */
function display_help () {
	display_version();

	print PHP_EOL;
	print 'usage: flowview_runner.php --query-id=N [--shard-id=N] [--debug]' . PHP_EOL . PHP_EOL;
	print 'FlowView Parallel Database Runner Script.  Provided a Database Query ID and a properly' . PHP_EOL;
	print 'registered query.  Run all the shards in parallel and then consolidate the results' . PHP_EOL;
	print 'for return to the user.' . PHP_EOL . PHP_EOL;
	print 'Optional:' . PHP_EOL;
	print '    --debug   - Display verbose output during execution' . PHP_EOL . PHP_EOL;
}

