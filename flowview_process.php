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
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

flowview_connect();

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

$last = read_config_option('flowview_last_change');
if (empty($last)) {
	$last = $t - read_config_option('poller_interval');
	set_config_option('flowview_last_change', $last);
} else {
	set_config_option('flowview_last_change', $t);
}

// Maintenance is at midnight
if (date('z', $last) != date('z', time())) {
	$maint = true;
}

$queued_reports = array_rekey(
	db_fetch_assoc('SELECT rq.source_id
 		FROM reports_queued AS rq
   		WHERE rq.source = "flowview"'),
	'source_id', 'source_id'
);

if (cacti_sizeof($queued_reports)) {
	$sql_where = ' AND fs.id NOT IN (' . implode(', ', $queued_reports) . ')';
} else {
	$sql_where = '';
}

$schedules = flowview_db_fetch_assoc("SELECT fs.*
	FROM plugin_flowview_schedules AS fs
	WHERE enabled = 'on'
	$sql_where
	AND ($t - sendinterval > lastsent)");

$php = read_config_option('path_php_binary');

if (cacti_sizeof($schedules)) {
	foreach ($schedules as $s) {
		$command   = "$php {$config['base_path']}/plugins/flowview/run_schedule.php";
		$name      = $s['title'];
		$source    = 'flowview';
		$source_id = $s['id'];

		$notification = array();

		if ($s['email'] != '') {
			$notification['email']['to_email'] = $s['email'];
		}

		if ($s['notification_list'] > 0) {
			$notification['notification_list']['id'] = $s['notification_list'];
		}

		reports_queue($name, 1, $source, $source_id, $command, $notification);
	}
}

/* run all scheduled reports */
exec_background($php, $config['base_path'] . '/plugins/flowview/run_schedule.php --scheduled');

$total = flowview_db_fetch_cell('SELECT COUNT(*) FROM plugin_flowview_devices');

/* determine how many records were inserted */
$last_sequence = read_config_option('flowview_last_sequence');
$last_table    = read_config_option('flowview_last_table');

$tables      = get_tables_range($last, $t);
$records     = 0;
$sequence    = 0;
$nlast_table = '';

foreach($tables as $table) {
	if (empty($last_sequence)) {
		$data = flowview_db_fetch_row_prepared("SELECT COUNT(*) AS totals, MAX(sequence) AS sequence
			FROM $table
			WHERE end_time >= ?",
			array(date('Y-m-d H:i:s', $last)));

		$nlast_table = $table;
	} elseif ($last_table == $table) {
		$data = flowview_db_fetch_row_prepared("SELECT COUNT(*) AS totals, MAX(sequence) AS sequence
			FROM $table
			WHERE sequence > ?",
			array($last_sequence));

		$nlast_table = $table;
	} else {
		$data = flowview_db_fetch_row("SELECT COUNT(*) AS totals, MAX(sequence) AS sequence
			FROM $table");

		$nlast_table = $table;
	}

	if (cacti_sizeof($data)) {
		$sequence = intval($data['sequence']);
		$records += $data['totals'];
	}

	if ($sequence == '') {
		$sequence = 0;
	}
}

$raw_engine  = get_set_default_fast_engine();

$last_tables = flowview_db_fetch_assoc('SELECT TABLE_NAME, ENGINE
	FROM information_schema.TABLES
	WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"
	ORDER BY TABLE_NAME DESC
	LIMIT 1, 3');

if (cacti_sizeof($last_tables)) {
	foreach($last_tables as $table) {
		if ($table['ENGINE'] != $raw_engine) {
			flowview_db_execute("ALTER TABLE {$table['TABLE_NAME']} ENGINE=$raw_engine");
		}
	}
}

set_config_option('flowview_last_sequence', $sequence);
set_config_option('flowview_last_table', $nlast_table);

/* prune expired query results */
parallel_database_query_expire();

if ($maint) {
	flowview_debug('Performing Table Maintenance');

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

	flowview_debug('Removing partitioned tables with suffix less than ' . $remove_lessthan);

	$tables = flowview_db_fetch_assoc("SELECT TABLE_NAME
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_NAME LIKE 'plugin_flowview_raw_%'
		ORDER BY TABLE_NAME");

	$dropped = 0;

	if (cacti_sizeof($tables)) {
		foreach($tables as $t) {
			$date_part = str_replace('plugin_flowview_raw_', '', $t['TABLE_NAME']);

			if ($date_part <  $remove_lessthan) {
				$dropped++;
				flowview_debug("Removing partitioned table 'plugin_flowview_raw_" . $date_part . "'");
				flowview_db_execute('DROP TABLE plugin_flowview_raw_' . $date_part);
			}
		}
	}

	flowview_debug('Total number of partition tables dropped is ' . $dropped);

	$cache = flowview_db_fetch_assoc('SELECT * FROM parallel_database_query_shard_cache');

	$dropped = 0;

	if (cacti_sizeof($cache)) {
		foreach($cache as $entry) {
			if ($entry['map_partition'] == '') {
				if (!flowview_db_table_exists($entry['map_table'])) {
					flowview_db_execute_prepared('DELETE FROM parallel_database_query_shard_cache
						WHERE md5sum = ?
						AND map_table = ?
						AND map_partition = ?',
						array($entry['md5sum'], $entry['map_table'], $entry['map_partition']));

					$dropped++;
				}
			}
		}
	}

	flowview_debug('Total number of cached shards dropped is ' . $dropped);

	db_execute_prepared('DELETE FROM reports_log
		WHERE send_time < FROM_UNIXTIME(UNIX_TIMESTAMP() - (? * 86400))
		AND source = "flowview"',
		array($retention_days));

	/* download a fresh copy of the radb.db.gz and load it */
	flowview_check_databases();
}

$end = microtime(true);

$cacti_stats = sprintf(
	'Time:%0.2f ' .
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


