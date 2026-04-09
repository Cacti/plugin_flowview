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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include('./include/cli_check.php');
include_once('./plugins/flowview/functions.php');
include_once('./plugins/flowview/setup.php');
include_once('./plugins/flowview/database.php');

flowview_connect();

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$proceed = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--proceed':
				$proceed = true;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

if ($proceed == false) {
	print "WARNING: This utility is meant for development purposes only.  It will kill and cleanup parallel queries." . PHP_EOL;
	print "Use the --proceed option if you wish to do so.". PHP_EOL;
	exit(1);
}

print "NOTE: Check for Lingering Processes" . PHP_EOL;

$processes = db_fetch_assoc_prepared('SELECT * FROM processes
	WHERE tasktype = ?
	AND taskname LIKE "db_%"
	ORDER BY taskname',
	['flowview']);

if (cacti_sizeof($processes)) {
	foreach($processes as $p) {
		print "WARNING: Cleaning Process with Task Name:{$p['taskname']} and PID:{$p['pid']}" . PHP_EOL;

		posix_kill($p['pid'], SIGINT);

		db_execute_prepared('DELETE FROM processes WHERE id = ?', [$p['id']]);
	}
}

print "NOTE: Check for Lingering Map Tables" . PHP_EOL;

$parallel_tables = flowview_db_fetch_assoc('SELECT TABLE_NAME, TABLE_COLLATION
	FROM information_schema.TABLES
	WHERE TABLE_NAME LIKE "parallel_database_query_map%"
	ORDER BY TABLE_NAME DESC');

if (cacti_sizeof($parallel_tables)) {
	foreach($parallel_tables as $t) {
		print "WARNING: Dropping Table {$t['TABLE_NAME']}" . PHP_EOL;
		flowview_db_execute("DROP TABLE {$t['TABLE_NAME']}");
	}
}

print "NOTE: Purging Parallel Query Cache and Orphaned Shards" . PHP_EOL;
flowview_db_execute_prepared('TRUNCATE TABLE parallel_database_query');
flowview_db_execute_prepared('TRUNCATE TABLE parallel_database_query_shard');
flowview_db_execute_prepared('TRUNCATE TABLE parallel_database_query_shard_cache');

exit(0);

/*  display_version - displays version information */
function display_version() {
	$info    = plugin_flowview_version();
	$version = $info['version'];

	print "Cacti Flowview Cleanup Parallel Queries, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: flowview_cleanup.php [--proceed]' . PHP_EOL . PHP_EOL;
	print 'A command line version of the Cacti Flowview parallel query cleanup.  You must execute' . PHP_EOL;
	print 'this command as a super user to execute the cleanup.' . PHP_EOL;
	print 'You must use the --proceed option.' . PHP_EOL . PHP_EOL;
	print 'This tool is to be used by developers who wish to reset their database.' . PHP_EOL;
}
