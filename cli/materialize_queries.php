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

chdir(__DIR__ . '/../../../');
require('./include/cli_check.php');

global $config;
require_once($config['base_path'] . '/lib/poller.php');
require_once($config['base_path'] . '/lib/time.php');
require_once($config['base_path'] . '/plugins/flowview/includes/database.php');
require_once($config['base_path'] . '/plugins/flowview/setup.php');
require_once($config['base_path'] . '/plugins/flowview/includes/functions.php');

ini_set('max_execution_time', 0);
ini_set('memory_limit', '-1');

flowview_connect();

/* another materialization may not start until this one ends or this many seconds pass */
const MATERIALIZE_RUNTIME_LIMIT = 28800;

$debug    = false;
$force    = false;
$dry_run  = false;
$worker   = false;
$query_ids = [];
$nat      = false;
$start    = false;
$end      = false;

$shortopts = 'VvHh';
$longopts  = [
	'query-id:',
	'nat:',
	'start:',
	'end:',
	'debug',
	'force',
	'dry-run',
	'worker',
	'version',
	'help',
];

$options = getopt($shortopts, $longopts);

foreach ($options as $arg => $value) {
	$value = is_array($value) ? end($value) : $value;

	switch ($arg) {
		case 'query-id':
			$query_ids = [];

			foreach (explode(',', (string) $value) as $id) {
				if (!ctype_digit($id) || $id < 1) {
					print 'ERROR: --query-id must be a positive integer or a comma separated list of them' . PHP_EOL;
					exit(1);
				}

				$query_ids[(int) $id] = (int) $id;
			}

			$query_ids = array_values($query_ids);

			break;
		case 'nat':
			$nat = strtolower((string) $value);

			if ($nat != 'on' && $nat != 'off') {
				print 'ERROR: --nat must be either on or off' . PHP_EOL;
				exit(1);
			}

			break;
		case 'start':
			$start = ctype_digit((string) $value) ? (int) $value : false;

			break;
		case 'end':
			$end = ctype_digit((string) $value) ? (int) $value : false;

			break;
		case 'debug':
			$debug = true;

			break;
		case 'force':
			$force = true;

			break;
		case 'dry-run':
			$dry_run = true;

			break;
		case 'worker':
			$worker = true;

			break;
		case 'V':
		case 'v':
		case 'version':
			display_version();
			exit(0);
		case 'H':
		case 'h':
		case 'help':
			display_help();
			exit(0);
		default:
			print 'ERROR: Invalid option ' . $arg . PHP_EOL;
			display_help();
			exit(1);
	}
}

/* a worker materializes exactly one query and NAT mode for the range its parent chose */
if ($worker) {
	if (cacti_sizeof($query_ids) != 1 || $nat === false || $start === false || $end === false) {
		print 'ERROR: --worker requires one --query-id, --nat, --start and --end' . PHP_EOL;
		exit(1);
	}

	exit(materialize_run_worker($query_ids[0], $nat, $start, $end));
}

if (!$force && !$dry_run) {
	if (!register_process_start('flowview', 'materialize', 0, MATERIALIZE_RUNTIME_LIMIT)) {
		print 'NOTE: A materialization is already running.  To override use --force' . PHP_EOL;
		exit(0);
	}

	register_shutdown_function(function () {
		unregister_process('flowview', 'materialize', 0);
	});
}

$threads = read_config_option('flowview_parallel_threads');

if (empty($threads) || $threads == 1) {
	print 'ERROR: Materialization requires the Parallel Query.  Set Max Concurrent Threads to 2 or more under the FlowView Settings.' . PHP_EOL;
	exit(1);
}

$tables = flowview_get_partition_tables();

$range = flowview_get_materialize_range($tables);

if ($range === false) {
	print 'NOTE: There are no flow partitions to materialize.' . PHP_EOL;
	exit(0);
}

list($start, $end) = $range;

if (cacti_sizeof($query_ids)) {
	$queries = flowview_db_fetch_assoc_prepared('SELECT id, name
		FROM plugin_flowview_queries
		WHERE id IN (' . implode(', ', array_fill(0, cacti_sizeof($query_ids), '?')) . ')
		ORDER BY id',
		$query_ids);
} else {
	$queries = flowview_db_fetch_assoc('SELECT id, name
		FROM plugin_flowview_queries
		ORDER BY id');
}

if (!cacti_sizeof($queries)) {
	print 'NOTE: There are no FlowView queries to materialize.' . PHP_EOL;
	exit(0);
}

$modes = $nat !== false ? [$nat] : ['on', 'off'];

print sprintf('FlowView Materialization: Partitions:%d From:%s To:%s Queries:%d NAT:%s',
	cacti_sizeof($tables),
	date('Y-m-d H:i:s', $start + 1),
	date('Y-m-d H:i:s', $end - 1),
	cacti_sizeof($queries),
	implode('/', $modes)) . PHP_EOL;

/* partitions that are still accurate keep their cache, everything else is rebuilt below */
if (!$dry_run) {
	$cache = flowview_invalidate_stale_shard_cache();

	print sprintf('Cached Partitions Still Accurate:%d, Stale Partitions Removed:%d', $cache['kept'], $cache['removed']) . PHP_EOL;
}

$began  = microtime(true);
$total  = 0;
$failed = 0;

foreach ($queries as $query) {
	foreach ($modes as $mode) {
		$total++;

		if ($dry_run) {
			print sprintf('DRY RUN: Would materialize Query:%d [%s] NAT:%s', $query['id'], $query['name'], $mode) . PHP_EOL;

			continue;
		}

		/* a separate process keeps one query hitting the run limit from ending the rest */
		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) .
			' --worker' .
			' --query-id=' . (int) $query['id'] .
			' --nat=' . $mode .
			' --start=' . $start .
			' --end=' . $end .
			($debug ? ' --debug' : '');

		passthru($command, $status);

		if ($status != 0) {
			$failed++;

			print sprintf('ERROR: Query:%d [%s] NAT:%s failed to materialize', $query['id'], $query['name'], $mode) . PHP_EOL;
		}
	}
}

$time = microtime(true) - $began;

print sprintf('FlowView Materialization Complete: Time:%0.2f Runs:%d Failed:%d', $time, $total, $failed) . PHP_EOL;

if (!$dry_run) {
	cacti_log(sprintf('FLOWVIEW MATERIALIZE STATS: Time:%0.2f Runs:%d Failed:%d', $time, $total, $failed), false, 'SYSTEM');
}

exit($failed > 0 ? 1 : 0);

/**
 * Materializes one query for one NAT mode across the given range.  The query
 * runs through the normal parallel query path, so each partition that is
 * already cached and still accurate is reused instead of being scanned again.
 *
 * @param int    $query_id The FlowView query to run
 * @param string $nat      Either on or off
 * @param int    $start    The Unix start time of the range
 * @param int    $end      The Unix end time of the range
 *
 * @return int The exit status, 0 on success
 */
function materialize_run_worker(int $query_id, string $nat, int $start, int $end): int {
	$finished = false;

	/* the run limit ends the process without returning, so report it as a failure */
	register_shutdown_function(function () use (&$finished) {
		if (!$finished) {
			print 'ERROR: The materialization did not finish.  Check the Maximum Run Time under the FlowView Settings.' . PHP_EOL;
			exit(1);
		}
	});

	set_request_var('usenat', $nat == 'on' ? 'true' : 'false');

	$last_request = (int) flowview_db_fetch_cell('SELECT COALESCE(MAX(id), 0)
		FROM parallel_database_query');

	$began = microtime(true);

	$data = load_data_for_filter($query_id, $start, $end);

	$finished = true;

	if ($data === false) {
		print sprintf('ERROR: Query:%d NAT:%s did not return a report.  See the Cacti log for details.', $query_id, $nat) . PHP_EOL;

		return 1;
	}

	$stats = flowview_db_fetch_row_prepared('SELECT COUNT(*) AS requests,
		COALESCE(SUM(total_shards), 0) AS shards,
		COALESCE(SUM(cached_shards), 0) AS cached
		FROM parallel_database_query
		WHERE user_id = 0
		AND id > ?',
		[$last_request]);

	if (cacti_sizeof($stats) && $stats['requests'] > 0) {
		print sprintf('Query:%d NAT:%s Partitions:%d AlreadyMaterialized:%d Materialized:%d Time:%0.2f',
			$query_id,
			$nat,
			$stats['shards'],
			$stats['cached'],
			$stats['shards'] - $stats['cached'],
			microtime(true) - $began) . PHP_EOL;
	} else {
		print sprintf('Query:%d NAT:%s Already Materialized and Accurate Time:%0.2f', $query_id, $nat, microtime(true) - $began) . PHP_EOL;
	}

	return 0;
}

/**
 * Prints this script's version and copyright banner. Called from
 * display_help() and when invoked with '--version'.
 *
 * @return void
 */
function display_version(): void {
	$version = get_cacti_cli_version();
	print "Cacti FlowView Query Materializer, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * Prints the version banner followed by this script's command-line
 * usage summary. Called when invoked with '--help' or with invalid
 * arguments.
 *
 * @return void
 */
function display_help(): void {
	display_version();

	print PHP_EOL . 'usage: materialize_queries.php [--query-id=ID[,ID...]] [--nat=on|off] [--dry-run] [--force] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Materializes each FlowView query into the parallel query cache for the' . PHP_EOL;
	print 'entire range of flow partitions, once with NAT on and once with NAT off.' . PHP_EOL;
	print 'Partitions that are already materialized and still accurate are skipped.' . PHP_EOL;
	print 'Only one run may be active at a time, for up to 8 hours.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '    --query-id=ID Only materialize this query, or a comma separated list of them.' . PHP_EOL;
	print '    --nat=on|off  Only materialize this NAT mode.' . PHP_EOL;
	print '    --dry-run     List what would be materialized and exit.' . PHP_EOL;
	print '    --force       Run even if another materialization is running.' . PHP_EOL;
	print '    --debug       Provide some debug output during the run.' . PHP_EOL . PHP_EOL;
}
