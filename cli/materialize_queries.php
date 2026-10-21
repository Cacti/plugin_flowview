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

/* signals are handled as they arrive, not when the script next ticks */
if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
}

flowview_connect();

/* another materialization may not start until this one ends or this many seconds pass */
const MATERIALIZE_RUNTIME_LIMIT = 28800;

$debug     = false;
$force     = false;
$dry_run   = false;
$status    = false;
$worker    = false;

/* what the signal handler has to clean up: the filters registered, and the worker being run */
$materialize_registered = [];
$materialize_child      = null;
$query_ids = [];
$partitions = [];
$nat       = false;
$start     = false;
$end       = false;

$shortopts = 'VvHh';
$longopts  = [
	'query-id:',
	'partitions:',
	'nat:',
	'start:',
	'end:',
	'debug',
	'force',
	'dry-run',
	'status',
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
		case 'partitions':
			$partitions = [];

			foreach (explode(',', (string) $value) as $table) {
				if (flowview_partition_sort_key($table) === '') {
					print 'ERROR: --partitions must be a comma separated list of raw partition tables' . PHP_EOL;
					exit(1);
				}

				$partitions[$table] = $table;
			}

			$partitions = array_values($partitions);

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
		case 'status':
			$status = true;

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

if ($status) {
	materialize_show_status();

	exit(0);
}

/* a worker materializes exactly one query and NAT mode for the range its parent chose */
if ($worker) {
	if (cacti_sizeof($query_ids) != 1 || $nat === false || $start === false || $end === false) {
		print 'ERROR: --worker requires one --query-id, --nat, --start and --end' . PHP_EOL;
		exit(1);
	}

	materialize_install_signal_handlers();

	exit(materialize_run_worker($query_ids[0], $nat, $start, $end));
}

/* the filters to materialize, all of them unless some were asked for */
if (!cacti_sizeof($query_ids)) {
	$query_ids = array_map('intval', array_column(flowview_db_fetch_assoc('SELECT id
		FROM plugin_flowview_queries
		ORDER BY id'), 'id'));
}

if (!cacti_sizeof($query_ids)) {
	print 'NOTE: There are no FlowView queries to materialize.' . PHP_EOL;
	exit(0);
}

/**
 * Each filter is registered in the process table as its own materialize
 * process, with the filter id as the task id, so the filters can be
 * materialized separately and what is being done can be seen there.  A filter
 * that already has one is left to it.
 */
if (!$force && !$dry_run) {
	flowview_purge_dead_materializations();

	$skipped = [];

	foreach ($query_ids as $query_id) {
		if (register_process_start('flowview', 'materialize', $query_id, MATERIALIZE_RUNTIME_LIMIT)) {
			$materialize_registered[$query_id] = $query_id;
		} else {
			$skipped[] = $query_id;
		}
	}

	if (cacti_sizeof($skipped)) {
		materialize_say(sprintf('NOTE: Query(s) %s are already being materialized, so they are skipped.  To override use --force', implode(', ', $skipped)));
	}

	if (!cacti_sizeof($materialize_registered)) {
		exit(0);
	}

	register_shutdown_function('materialize_release');

	$query_ids = array_values($materialize_registered);
}

materialize_install_signal_handlers();

$threads = read_config_option('flowview_parallel_threads');

if (empty($threads) || $threads == 1) {
	print 'ERROR: Materialization requires the Parallel Query.  Set Max Concurrent Threads to 2 or more under the FlowView Settings.' . PHP_EOL;
	exit(1);
}

/* the live partition is still receiving flows, so it is never materialized */
$tables = flowview_get_cold_partitions(flowview_get_partition_tables());

/* only the partitions asked for, that still exist */
if (cacti_sizeof($partitions)) {
	$tables = array_values(array_intersect($tables, $partitions));
}

$cold = $tables;

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

materialize_say(sprintf('FlowView Materialization: Partitions:%d From:%s To:%s Queries:%d NAT:%s',
	cacti_sizeof($tables),
	date('Y-m-d H:i:s', flowview_table_name_to_time(reset($tables), 'start_time')),
	date('Y-m-d H:i:s', flowview_table_name_to_time(end($tables), 'end_time')),
	cacti_sizeof($queries),
	implode('/', $modes)));

/**
 * Any alter that is due has to be done before the parallel query starts.  A
 * query waits for an alter running on the partition it scans, and a long one
 * ends the query at the Maximum Run Time.
 */
materialize_prepare_partitions($cold, $modes, $dry_run);

/* partitions that are still accurate keep their cache, everything else is rebuilt below */
if (!$dry_run) {
	$live = flowview_remove_live_partition_cache();

	if ($live > 0) {
		materialize_say(sprintf('Removed %d cached result(s) of the live partition, as it is still receiving flows', $live));
	}

	$cache = flowview_invalidate_stale_shard_cache(cacti_sizeof($partitions) ? $tables : []);

	materialize_say(sprintf('Cached Partitions Still Accurate:%d, Stale Partitions Removed:%d', $cache['kept'], $cache['removed']));
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

		$status = materialize_run_command($command);

		if ($status != 0) {
			$failed++;

			materialize_say(sprintf('ERROR: Query:%d [%s] NAT:%s failed to materialize', $query['id'], $query['name'], $mode));
		}
	}
}

$time = microtime(true) - $began;

materialize_say(sprintf('FlowView Materialization Complete: Time:%0.2f Runs:%d Failed:%d', $time, $total, $failed));

if ($failed > 0) {
	print 'NOTE: Partitions that finished materializing are kept, so run the materializer again to continue.' . PHP_EOL;
}

if (!$dry_run) {
	cacti_log(sprintf('FLOWVIEW MATERIALIZE STATS: Time:%0.2f Runs:%d Failed:%d', $time, $total, $failed), false, 'SYSTEM');
}

exit($failed > 0 ? 1 : 0);

/**
 * Shows the parallel queries that are running and the Filter each one is for.
 * The process table only has the id of the request, so the Filter is found
 * from the cache key of the request.
 *
 * @return void
 */
function materialize_show_status(): void {
	$materializing = flowview_get_materializing_filters();

	if (cacti_sizeof($materializing)) {
		print sprintf('Materializing Query(s): %s', implode(', ', $materializing)) . PHP_EOL;
	} else {
		print 'No Query is being materialized.' . PHP_EOL;
	}

	$running = flowview_get_running_requests();

	if (!cacti_sizeof($running)) {
		print 'No parallel queries are running.' . PHP_EOL;

		return;
	}

	$names   = [];
	$md5sums = [];

	foreach (flowview_db_fetch_assoc('SELECT id, name FROM plugin_flowview_queries ORDER BY id') as $filter) {
		$names[(int) $filter['id']]   = $filter['name'];
		$md5sums[(int) $filter['id']] = flowview_get_filter_md5sums((int) $filter['id']);
	}

	$requests = array_column(flowview_db_fetch_assoc_prepared('SELECT id, status, total_shards, finished_shards, cached_shards
		FROM parallel_database_query
		WHERE id IN (' . implode(', ', array_fill(0, cacti_sizeof($running), '?')) . ')',
		array_keys($running)), null, 'id');

	print sprintf('%d parallel queries are running:', cacti_sizeof($running)) . PHP_EOL;

	foreach ($running as $id => $md5sum) {
		$for = [];

		foreach (flowview_find_request_filters($md5sums, $md5sum) as $match) {
			$mode  = $match['nat'] && $match['non_nat'] ? 'on/off' : ($match['nat'] ? 'on' : 'off');
			$for[] = sprintf('Query:%d [%s] NAT:%s', $match['id'], $names[$match['id']], $mode);
		}

		if (isset($requests[$id])) {
			$progress = sprintf('Shards:%d of %d, Already Materialized:%d, State:%s', $requests[$id]['finished_shards'], $requests[$id]['total_shards'], $requests[$id]['cached_shards'], $requests[$id]['status']);
		} else {
			$progress = 'The request no longer exists, its processes are still registered';
		}

		print sprintf('Request:%d %s  %s', $id, cacti_sizeof($for) ? implode(', ', $for) : 'No Filter matches it, it may be an ad hoc report or a Filter that has changed', $progress) . PHP_EOL;
	}
}

/**
 * Waits until no ALTER TABLE is running on any of the partitions.  A query
 * waits for an alter on the partition it scans, so the parallel query must
 * not start while one is running.
 *
 * @param array $tables The partitions to wait for
 *
 * @return void
 */
function materialize_wait_for_alters(array $tables): void {
	$last_report = 0;

	while (cacti_sizeof($running = flowview_get_running_partition_alters($tables))) {
		if (time() - $last_report >= 30) {
			$last_report = time();

			arsort($running);

			materialize_say(sprintf('Waiting for an ALTER TABLE to finish on %d partition(s), the longest %s for %d seconds', cacti_sizeof($running), array_key_first($running), reset($running)));
		}

		sleep(5);
	}
}

/**
 * Gets the partitions ready to be scanned.  It waits for an alter that is
 * already running on one of them, for instance from the schema upgrade, and
 * then does the alters that are still due itself, so nothing is altered while
 * the parallel query runs.  The partition still receiving flows is left alone.
 *
 * A partition is looked at again just before it is altered.  If another
 * process started an alter on it in the meantime, this waits for it and then
 * only alters what is still due, so a partition is never rebuilt twice.
 *
 * @param array $cold    The partitions to prepare
 * @param array $modes   The NAT modes being materialized, on and off
 * @param bool  $dry_run Only say what would be done
 *
 * @return void
 */
function materialize_prepare_partitions(array $cold, array $modes, bool $dry_run): void {
	if (!cacti_sizeof($cold)) {
		return;
	}

	$raw_engine = get_set_default_fast_engine();

	if (!$dry_run) {
		materialize_wait_for_alters($cold);
	}

	$pending = flowview_get_pending_partition_alters($cold, $raw_engine);

	if (cacti_sizeof($pending)) {
		materialize_say(sprintf('%s %d partition(s) that still need an ALTER TABLE', $dry_run ? 'DRY RUN: Would alter' : 'Altering', cacti_sizeof($pending)));

		$i = 0;

		foreach ($pending as $table => $plan) {
			$i++;

			if (!$dry_run && cacti_sizeof(flowview_get_running_partition_alters([$table]))) {
				materialize_wait_for_alters([$table]);

				$again = flowview_get_pending_partition_alters([$table], $raw_engine);

				if (!isset($again[$table])) {
					materialize_say(sprintf('Table %s (%d of %d) was altered by another process', $table, $i, cacti_sizeof($pending)));

					continue;
				}

				$plan = $again[$table];
			}

			materialize_say(sprintf('%s Table %s (%d of %d): %s', $dry_run ? 'DRY RUN: Would alter' : 'Altering', $table, $i, cacti_sizeof($pending), trim($plan['alter'])));

			if (!$dry_run) {
				$began = microtime(true);

				flowview_apply_partition_alter($table, $plan, false);

				materialize_say(sprintf('Altered Table %s in %0.2f seconds', $table, microtime(true) - $began));
			}
		}
	}

	$no_nat = in_array('on', $modes, true) ? flowview_get_partitions_without_nat_columns($cold) : [];

	if (cacti_sizeof($no_nat)) {
		materialize_say(sprintf('NOTE: %d partition(s) have no NAT columns, so they have no NAT data.  Run flowview_upgrade_nat_columns.php before materializing if they should.', cacti_sizeof($no_nat)));
	}
}

/**
 * Makes SIGTERM and SIGINT end the script through materialize_sig_handler(),
 * so what it registered is cleaned up, as the flow collector does.
 *
 * @return void
 */
function materialize_install_signal_handlers(): void {
	if (function_exists('pcntl_signal')) {
		pcntl_signal(SIGTERM, 'materialize_sig_handler');
		pcntl_signal(SIGINT, 'materialize_sig_handler');
	}
}

/**
 * Cleans up on SIGTERM and SIGINT and exits.  The script that runs the
 * materialization stops the worker it started, and removes its registration
 * from the process table.  A worker cancels the parallel queries it started,
 * which stops their runner and shard workers and removes their registrations.
 *
 * @param int $signo
 *
 * @return void
 */
function materialize_sig_handler($signo): void {
	global $worker;

	static $handling = false;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			if ($handling) {
				return;
			}

			$handling = true;

			if ($worker) {
				cacti_log('WARNING: FlowView Materialization worker is shutting down by signal!', false, 'FLOWVIEW');

				flowview_cancel_requests_in_flight();

				/* the shutdown function must not report this as a failure to finish */
				$GLOBALS['flowview_error_reported'] = true;
			} else {
				cacti_log('WARNING: FlowView Materialization is shutting down by signal!', false, 'FLOWVIEW');

				materialize_stop_child();
				materialize_release();
			}

			exit(1);
		default:
			/* ignore all other signals */
	}
}

/**
 * Removes the registration of each filter of this materialization from the
 * process table.  It does nothing the second time it is called.
 *
 * @return void
 */
function materialize_release(): void {
	global $materialize_registered;

	foreach ($materialize_registered as $query_id) {
		unregister_process('flowview', 'materialize', $query_id, (int) getmypid());
	}

	$materialize_registered = [];
}

/**
 * Says something, on the screen, and also in the Cacti log when the output is
 * not a terminal.  A materialization started from the Filters page, or by the
 * poller, has nowhere to show it otherwise.
 *
 * @param string $text
 *
 * @return void
 */
function materialize_say(string $text): void {
	print $text . PHP_EOL;

	if (!function_exists('posix_isatty') || !posix_isatty(STDOUT)) {
		cacti_log($text, false, 'FLOWVIEW');
	}
}

/**
 * Runs a worker and waits for it, with its output shown as it is written.
 * The worker is kept so a signal can stop it.
 *
 * @param string $command The command to run
 *
 * @return int The exit status, 0 on success
 */
function materialize_run_command(string $command): int {
	global $materialize_child;

	$process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);

	if (!is_resource($process)) {
		return 1;
	}

	$materialize_child = $process;
	$status            = 1;

	while (true) {
		$info = proc_get_status($process);

		if (!$info['running']) {
			/* the exit status can only be read the first time it is asked for */
			$status = $info['exitcode'] >= 0 ? $info['exitcode'] : 1;

			break;
		}

		usleep(100000);
	}

	$materialize_child = null;

	proc_close($process);

	return $status;
}

/**
 * Stops the worker being run, giving it time to cancel its queries first.
 *
 * @return void
 */
function materialize_stop_child(): void {
	global $materialize_child;

	if (!is_resource($materialize_child)) {
		return;
	}

	proc_terminate($materialize_child, SIGTERM);

	$deadline = microtime(true) + 15;

	while (microtime(true) < $deadline) {
		$info = proc_get_status($materialize_child);

		if (!$info['running']) {
			return;
		}

		usleep(100000);
	}

	proc_terminate($materialize_child, SIGKILL);
}

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

	/* the parallel query ends the process on a timeout, after it has said why */
	register_shutdown_function(function () use (&$finished) {
		if (!$finished) {
			if (empty($GLOBALS['flowview_error_reported'])) {
				print 'ERROR: The materialization ended before it finished.  See the Cacti log for details.' . PHP_EOL;
			}

			exit(1);
		}
	});

	set_request_var('usenat', $nat == 'on' ? 'true' : 'false');

	/* run the shards, even if the same query was run before, as it is the cached shards that are wanted */
	$GLOBALS['flowview_fresh_requests'] = true;
	$GLOBALS['flowview_requests_made']  = [];

	materialize_say(sprintf('Materializing Query:%d NAT:%s', $query_id, $nat));

	$began = microtime(true);

	$data = load_data_for_filter($query_id, $start, $end);

	$finished = true;

	if ($data === false) {
		materialize_say(sprintf('ERROR: Query:%d NAT:%s did not return a report.  See the Cacti log for details.', $query_id, $nat));

		return 1;
	}

	/* only the requests of this worker, as the workers of other filters can run at the same time */
	$made = array_map('intval', array_values($GLOBALS['flowview_requests_made']));

	$stats = cacti_sizeof($made) ? flowview_db_fetch_row_prepared('SELECT COUNT(*) AS requests,
		COALESCE(SUM(total_shards), 0) AS shards,
		COALESCE(SUM(cached_shards), 0) AS cached
		FROM parallel_database_query
		WHERE id IN (' . implode(', ', array_fill(0, cacti_sizeof($made), '?')) . ')',
		$made) : [];

	if (cacti_sizeof($stats) && $stats['requests'] > 0) {
		materialize_say(sprintf('Query:%d NAT:%s Partitions:%d AlreadyMaterialized:%d Materialized:%d Time:%0.2f',
			$query_id,
			$nat,
			$stats['shards'],
			$stats['cached'],
			$stats['shards'] - $stats['cached'],
			microtime(true) - $began));
	} else {
		materialize_say(sprintf('Query:%d NAT:%s Already Materialized and Accurate Time:%0.2f', $query_id, $nat, microtime(true) - $began));
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

	print PHP_EOL . 'usage: materialize_queries.php [--query-id=ID[,ID...]] [--partitions=TABLE[,TABLE...]] [--nat=on|off] [--dry-run] [--status] [--force] [--debug]' . PHP_EOL . PHP_EOL;

	print 'Materializes each FlowView query into the parallel query cache for every' . PHP_EOL;
	print 'flow partition that has rotated, once with NAT on and once with NAT off.' . PHP_EOL;
	print 'The partition still receiving flows is never cached.  Partitions that are' . PHP_EOL;
	print 'already materialized and still accurate are skipped.' . PHP_EOL;
	print 'Each Filter is registered in the process table as materialize, with its id as the task id,' . PHP_EOL;
	print 'so Filters can be materialized separately.  A Filter that is already being materialized' . PHP_EOL;
	print 'is skipped.  Registrations last up to 8 hours.' . PHP_EOL . PHP_EOL;

	print 'Options:' . PHP_EOL;
	print '    --query-id=ID       Only materialize this query, or a comma separated list of them.' . PHP_EOL;
	print '    --partitions=TABLE  Only materialize these partition tables, as the poller does for the' . PHP_EOL;
	print '                        partitions that have rotated.' . PHP_EOL;
	print '    --nat=on|off        Only materialize this NAT mode.' . PHP_EOL;
	print '    --dry-run           List what would be materialized and exit.' . PHP_EOL;
	print '    --status            List the parallel queries that are running and the Filter each is for.' . PHP_EOL;
	print '    --force             Run even if a Filter is already being materialized.' . PHP_EOL;
	print '    --debug             Provide some debug output during the run.' . PHP_EOL . PHP_EOL;
}
