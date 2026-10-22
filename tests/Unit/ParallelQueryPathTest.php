<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The paths a report takes through run_flow_query() and the Parallel Query:
 * planning a Filter's cache keys, running a report through the parallel
 * query, the request and its shards, a query that times out, a shard worker
 * sharing a scan of the live partition, and the bookkeeping around them.
 * The database is the unit bootstrap's fake, whose results are queued in
 * the order each kind of call is made.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/functions.php';

	if (!function_exists('cacti_session_close')) { function cacti_session_close() {} }
	if (!function_exists('db_qstr'))             { function db_qstr($value) { return "'" . addslashes((string) $value) . "'"; } }
	if (!function_exists('number_format_i18n'))  { function number_format_i18n($number, $decimals = 0) { return number_format($number, $decimals); } }

	if (!function_exists('raise_message_javascript')) {
		function raise_message_javascript($title, $message, $level) {
			$GLOBALS['__test_javascript_messages'][] = array($title, $message, $level);
		}
	}

	if (!function_exists('unregister_process')) {
		function unregister_process($tasktype, $taskname, $taskid = 0, $pid = 0) {
			$GLOBALS['__test_unregistered'][] = array($tasktype, $taskname, $taskid);
		}
	}

	if (!function_exists('array_rekey')) {
		function array_rekey($array, $key, $value) {
			$ret = array();

			foreach ((array) $array as $item) {
				$ret[$item[$key]] = is_array($value) ? array_intersect_key($item, array_flip($value)) : $item[$value];
			}

			return $ret;
		}
	}
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['__test_cacti_log']           = array();
	$GLOBALS['__test_unregistered']        = array();
	$GLOBALS['flowview_requests_in_flight'] = array();
	unset($GLOBALS['flowview_fresh_requests'], $GLOBALS['flowview_partition_allowlist'], $GLOBALS['flowview_error_reported']);
});

function plugin_test_query_filter(array $override = array()): array {
	return array_merge(array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	), $override);
}

function plugin_test_db_calls(string $fn, string $like): array {
	return array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) use ($fn, $like) {
		return $call['fn'] == $fn && strpos($call['sql'], $like) !== false;
	}));
}

/* the partitions of a report, read after includes/arrays.php by run_flow_query() and get_tables_for_query() */
function plugin_test_queue_partitions(array $tables): void {
	foreach ($tables as $table) {
		$_SESSION['sess_flowview_table_details'][$table] = array('min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00', 'table_partition' => '');
	}

	$list = array_map(fn ($table) => array('table' => $table), $tables);

	foreach (range(1, 6) as $unused) {
		plugin_test_queue_db_result('db_fetch_assoc', array());
	}

	/* the partitions, their engines, then the newest partition */
	plugin_test_queue_db_result('db_fetch_assoc', $list);
	plugin_test_queue_db_result('db_fetch_assoc', array());
	plugin_test_queue_db_result('db_fetch_assoc', $list);
}

/* runs the packet size summary through the parallel query, with each size seen $packets times */
function plugin_test_run_packet_summary(int $packets): array {
	$names = array(
		'p0To32', 'p32To64', 'p64To96', 'p96To128', 'p128To160', 'p160To192', 'p192To224', 'p224To256', 'p256To288',
		'p288To320', 'p320To352', 'p352To384', 'p384To416', 'p416To448', 'p448To480', 'p480To512', 'p512To544',
		'p544To576', 'p576To1024', 'p1024To1536', 'p1536To2048', 'p2048To2560', 'p2560To3072', 'p3072To3568',
		'p3568To4096', 'p4096To4608', 'p4608ToInfin',
	);

	$GLOBALS['__test_config']['flowview_parallel_threads']      = 4;
	$GLOBALS['__test_config']['flowview_parallel_runlimit']     = 60;
	$GLOBALS['__test_config']['flowview_parallel_time_to_live'] = 21600;

	plugin_test_queue_partitions(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003'));
	plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 99)));

	/* the title, no earlier request, nothing pending, the result, and the map table */
	plugin_test_queue_db_result('db_fetch_cell_prepared', 'Top Talkers');
	plugin_test_queue_db_result('db_fetch_cell_prepared', '');
	plugin_test_queue_db_result('db_fetch_cell_prepared', '0');
	plugin_test_queue_db_result('db_fetch_cell_prepared', json_encode(array(array_fill_keys($names, $packets))));
	plugin_test_queue_db_result('db_fetch_cell_prepared', '');

	/* nothing is still running */
	plugin_test_queue_db_result('db_fetch_cell', '0');

	$output = run_flow_query(false, 4, strtotime('2024-01-01 00:00:00'), strtotime('2024-01-05 00:00:00'));

	return array($output, plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard'));
}

describe('planning the cache keys of a Filter', function () {
	it('has a cache key for each map query of a report that has two', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 11)));

		$plan = flowview_plan_filter(4, false);

		expect($plan)->toHaveCount(2)
			->and($plan[0])->not->toBe($plan[1]);
	});

	it('plans a printed report', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 0, 'printed' => 1, 'sortfield' => 'start_time')));

		expect(flowview_plan_filter(4, false))->toHaveCount(1);
	});

	it('takes a printed report from the request for a Filter that has none', function () {
		$_REQUEST['report'] = 'p1';

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 0)));

		expect(flowview_plan_filter(4, false))->toHaveCount(1);
	});

	it('has no plan for a report that does not exist', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 98)));

		expect(flowview_plan_filter(4, false))->toBe(array());
	});

	it('follows the NAT mode of the request when none is forced', function () {
		$plans = array();

		foreach (array('true', 'false') as $nat) {
			$_REQUEST['usenat'] = $nat;

			plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter());

			$plan = array();
			run_flow_query(false, 4, 1704067200, 1704153600, true, null, $plan);
			$plans[$nat] = $plan;
		}

		expect($plans['true'])->toHaveCount(1)
			->and($plans['true'])->not->toBe($plans['false']);
	});
});

describe('running a report', function () {
	it('says when a Filter has no report, and has nothing to show', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 0)));

		expect(run_flow_query(false, 4, 1704067200, 1704153600))->toBeFalse()
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('does not include either a Statistical or Printed Report');
	});

	it('runs the packet size summary through the parallel query and shows it as a table', function () {
		list($output, $shards) = plugin_test_run_packet_summary(4);

		expect($output)->toBeArray()
			->and($output['table'])->toContain('cactiTable')
			->and(substr_count($output['table'], '3.70 %'))->toBe(27)
			->and($shards)->toHaveCount(3)
			->and($shards[0]['params'][2])->toBe('1')
			->and($shards[2]['params'][4])->toBe(FLOWVIEW_LIVE_SHARD_MARKER);
	});

	it('shows the packet size summary as not applicable when no packets were counted', function () {
		list($output) = plugin_test_run_packet_summary(0);

		expect(substr_count($output['table'], 'N/A'))->toBe(27);
	});

	it('runs a report as one query over the partitions without the parallel query', function () {
		$GLOBALS['__test_config']['flowview_parallel_threads'] = 1;

		plugin_test_queue_partitions(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'));
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter());

		$output = run_flow_query(false, 4, strtotime('2024-01-01 00:00:00'), strtotime('2024-01-05 00:00:00'));

		$query = plugin_test_db_calls('db_fetch_assoc_prepared', 'UNION ALL');

		expect($output)->toBeFalse()
			->and($query)->toHaveCount(1)
			->and($query[0]['sql'])->toContain('FROM plugin_flowview_raw_2024001')
			->and($query[0]['sql'])->toContain('FROM plugin_flowview_raw_2024002')
			->and(plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard'))->toBeEmpty();
	});

	it('says a report it does not know is a bug, and has nothing to show', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 98)));
		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter(array('statistics' => 0, 'printed' => 98)));

		expect(run_flow_query(false, 4, 1704067200, 1704153600))->toBeFalse()
			->and(run_flow_query(false, 4, 1704067200, 1704153600))->toBeFalse()
			->and(array_column($GLOBALS['__test_messages'], 0))->toBe(array('bad_report', 'bad_report'))
			->and($GLOBALS['__test_messages'][0][1])->toContain('Statistical Report 98')
			->and($GLOBALS['__test_messages'][1][1])->toContain('Printed Report 98')
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('contains an invalid Statistical or Printed Report');
	});

	it('has nothing to show for a Filter that is gone', function () {
		$_REQUEST['cutofflines'] = 20;

		expect(run_flow_query(false, 99, 1704067200, 1704153600))->toBeFalse();
	});

	it('plans the exporter of the Filter, and the same cache whatever the sort order of the request', function () {
		$plan_for = function (array $filter, array $request) {
			$_REQUEST = $request;

			plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_query_filter($filter));

			return flowview_plan_filter(4, false);
		};

		$plain = $plan_for(array(), array());

		expect($plan_for(array('ex_addr' => '10.0.0.5'), array()))->not->toBe($plain)
			->and($plan_for(array(), array('sortfield' => 'packets')))->toBe($plain);
	});
});

describe('a parallel query request', function () {
	$stru = function () {
		$inner = array(
			'sql_query' => 'SELECT src_addr, SUM(bytes) AS bytes', 'sql_where' => '', 'sql_range' => '(`start_time` BETWEEN ? AND ?)',
			'sql_groupby' => 'GROUP BY src_addr', 'sql_having' => '', 'sql_order' => '', 'sql_limit' => '',
			'sql_params' => array(), 'sql_range_params' => array('2024-01-01 00:00:00', '2024-01-05 00:00:00'),
			'sql_start_time' => strtotime('2024-01-01 00:00:00'), 'sql_end_time' => strtotime('2024-01-05 00:00:00'),
		);

		$outer = array('sql_query' => 'SELECT INET6_NTOA(src_addr) AS src_addr, SUM(bytes) AS bytes', 'sql_where' => '', 'sql_groupby' => 'GROUP BY src_addr',
			'sql_having' => '', 'sql_order' => '', 'sql_limit' => 'LIMIT 20', 'sql_params' => array());

		return array($inner, $outer);
	};

	$tables = array(
		'plugin_flowview_raw_2024001' => array('table_name' => 'plugin_flowview_raw_2024001', 'table_partition' => '', 'min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'),
		'plugin_flowview_raw_2024002' => array('table_name' => 'plugin_flowview_raw_2024002', 'table_partition' => '', 'min_date' => '2024-01-03 00:00:00', 'max_date' => '2024-01-03 23:00:00'),
	);

	it('replaces a request a query that timed out left behind', function () use ($stru, $tables) {
		list($inner, $outer) = $stru();

		plugin_test_queue_db_result('db_fetch_cell_prepared', '77');
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'scheduled', 'created' => date('Y-m-d H:i:s', time() - 600)));

		parallel_database_query_request($tables, $inner, $outer);

		$removed = plugin_test_db_calls('db_execute_prepared', 'DELETE FROM parallel_database_query');

		expect(array_filter($removed, fn ($call) => $call['params'] == array('77')))->not->toBeEmpty()
			->and(plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard'))->toHaveCount(2);
	});

	it('runs the shards again for a materialization instead of reusing a finished request', function () use ($stru, $tables) {
		list($inner, $outer) = $stru();

		$GLOBALS['flowview_fresh_requests'] = true;

		plugin_test_queue_db_result('db_fetch_cell_prepared', '78');
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'complete', 'created' => date('Y-m-d H:i:s')));
		plugin_test_queue_db_result('db_fetch_cell_prepared', 'complete');

		parallel_database_query_request($tables, $inner, $outer);

		expect(plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard'))->toHaveCount(2)
			->and($GLOBALS['flowview_requests_made'])->toHaveKey(0);
	});

	it('marks the shard of the live partition so its scan is shared', function () use ($stru, $tables) {
		list($inner, $outer) = $stru();

		plugin_test_queue_db_result('db_fetch_assoc', array());
		plugin_test_queue_db_result('db_fetch_assoc', array(array('table' => 'plugin_flowview_raw_2024001'), array('table' => 'plugin_flowview_raw_2024002')));

		parallel_database_query_request($tables, $inner, $outer);

		$shards = plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard');

		expect($shards[0]['params'][2])->toBe('1')
			->and($shards[0]['params'][4])->toBe('')
			->and($shards[1]['params'][2])->toBe('0')
			->and($shards[1]['params'][4])->toBe(FLOWVIEW_LIVE_SHARD_MARKER);
	});

	it('says whether a request is still running', function () {
		plugin_test_queue_db_result('db_fetch_cell_prepared', '2');
		plugin_test_queue_db_result('db_fetch_cell_prepared', '0');

		expect(parallel_database_query_is_running(5))->toBeTrue()
			->and(parallel_database_query_is_running(5))->toBeFalse();
	});

	it('has nothing from the requests of a report with two map queries when there is nothing to reduce', function () {
		$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 60;

		plugin_test_queue_db_result('db_fetch_cell_prepared', '0');
		plugin_test_queue_db_result('db_fetch_cell_prepared', '0');
		plugin_test_queue_db_result('db_fetch_cell', '0');

		expect(parallel_database_query_run(array(5, 6)))->toBe(array())
			->and(plugin_test_db_calls('db_fetch_cell_prepared', 'SELECT reduce_query'))->toHaveCount(1)
			->and(plugin_test_db_calls('db_fetch_assoc', 'SELECT map_table, map_create, results'))->toHaveCount(1);
	});

	it('drops the table a request reduced its shards into when it is cleaned up', function () {
		plugin_test_queue_db_result('db_fetch_cell_prepared', 'parallel_database_query_map_5');

		parallel_database_query_cleanup(5, true);

		expect(plugin_test_db_calls('db_execute_prepared', 'DROP TABLE IF EXISTS parallel_database_query_map_5'))->toHaveCount(1);
	});

	it('logs the progress of a shard only when debugging', function () {
		global $debug;

		$saved = $debug;

		$debug = false;
		db_debug('Query 5 and Shard 2 Complete');

		$debug = true;
		db_debug('Query 6 and Shard 1 Complete');

		$debug = $saved;

		expect($GLOBALS['__test_cacti_log'])->toHaveCount(1)
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('Query 6 and Shard 1 Complete');
	});

	it('removes the requests that have expired', function () {
		$GLOBALS['__test_config']['flowview_parallel_time_to_live'] = 21600;

		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('id' => 7)));

		parallel_database_query_expire();

		expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('Expiring Table for Parallel Query Request 7')
			->and(plugin_test_db_calls('db_execute_prepared', 'DELETE FROM parallel_database_query_shard_cache'))->not->toBeEmpty();
	});
});

describe('the summary of a report', function () {
	it('shows the table of the report, or nothing', function () {
		$report = array('table' => '<table class="cactiTable"></table>');
		$none   = array();

		ob_start();
		flowview_show_summary($report);
		flowview_show_summary($none);

		expect(ob_get_clean())->toBe('<table class="cactiTable"></table>');
	});
});

describe('a parallel query that does not finish in time', function () {
	it('says so in the browser of a web page, and logs it', function () {
		$GLOBALS['__test_javascript_messages'] = array();

		ob_start();
		flowview_report_parallel_timeout(30, true);
		$printed = ob_get_clean();

		expect($printed)->toBe('')
			->and($GLOBALS['__test_javascript_messages'])->toHaveCount(1)
			->and($GLOBALS['__test_javascript_messages'][0][0])->toBe('parallel_query_timeout')
			->and($GLOBALS['__test_javascript_messages'][0][2])->toBe(MESSAGE_LEVEL_ERROR)
			->and(isset($GLOBALS['flowview_error_reported']))->toBeFalse()
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('Maximum Run Time of 30 seconds');
	});
	it('says so in words from a script, cleans up, and ends with a failure', function () {
		$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 0;
		$GLOBALS['flowview_requests_in_flight'] = array(5 => 5);

		ob_start();

		try {
			parallel_database_query_run(array(5));
			$status = null;
		} catch (FlowviewTestExit $e) {
			$status = $e->getCode();
		}

		$printed = ob_get_clean();

		expect($status)->toBe(1)
			->and($printed)->toContain('did not finish within the Maximum Run Time')
			->and($printed)->not->toContain('<script')
			->and($GLOBALS['flowview_error_reported'])->toBeTrue()
			->and($GLOBALS['flowview_requests_in_flight'])->toBe(array());

		unset($GLOBALS['flowview_error_reported']);
	});
});

describe('cancelling a parallel query', function () {
	it('kills its runner and shard workers and removes them', function () {
		if (!function_exists('posix_kill')) {
			$this->markTestSkipped('The posix extension is not available.');
		}

		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('id' => 9, 'pid' => 4999999, 'taskname' => 'db_shard_5')));

		parallel_database_query_cancel(5);

		expect(plugin_test_db_calls('db_execute_prepared', 'DELETE FROM processes'))->toHaveCount(1)
			->and(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('Killing FlowView Query Process with Task Name:db_shard_5');
	});
});

describe('a shard worker on the live partition', function () {
	$query = array('id' => 5, 'map_table' => 'parallel_database_query_map_5', 'md5sum_tables' => 'abc');
	$shard = array('shard_id' => 2, 'full_scan' => 0, 'map_table' => 'plugin_flowview_raw_2024002', 'map_partition' => 'live', 'map_query' => 'SELECT 1', 'map_params' => '[]');

	it('runs the scan once and keeps it for the other panels', function () use ($query, $shard) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $query);
		plugin_test_queue_db_result('db_fetch_row_prepared', $shard);
		plugin_test_queue_db_result('db_fetch_cell_prepared', '1');
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('src_addr' => '10.0.0.1', 'bytes' => 5)));

		parallel_database_child_runner(5, 2);

		expect(plugin_test_db_calls('db_execute_prepared', 'REPLACE INTO parallel_database_query_shard_cache'))->toHaveCount(1)
			->and(plugin_test_db_calls('db_fetch_cell_prepared', 'RELEASE_LOCK'))->toHaveCount(1)
			->and(plugin_test_db_calls('db_execute', 'INSERT INTO parallel_database_query_map_5'))->toHaveCount(1)
			->and($GLOBALS['__test_unregistered'])->toBe(array(array('flowview', 'db_shard_5', 2)));
	});

	it('reuses the scan another worker just ran', function () use ($query, $shard) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $query);
		plugin_test_queue_db_result('db_fetch_row_prepared', $shard);
		plugin_test_queue_db_result('db_fetch_cell_prepared', '1');
		plugin_test_queue_db_result('db_fetch_row_prepared', array('results' => json_encode(array(array('src_addr' => '10.0.0.1', 'bytes' => 5)))));

		parallel_database_child_runner(5, 2);

		expect(plugin_test_db_calls('db_fetch_assoc_prepared', 'SELECT 1'))->toBeEmpty()
			->and(plugin_test_db_calls('db_execute_prepared', 'REPLACE INTO parallel_database_query_shard_cache'))->toBeEmpty()
			->and(plugin_test_db_calls('db_execute', 'INSERT INTO parallel_database_query_map_5'))->toHaveCount(1);
	});

	it('serves a cold partition from its cached shard', function () use ($query, $shard) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $query);
		plugin_test_queue_db_result('db_fetch_row_prepared', array_merge($shard, array('full_scan' => 1, 'map_partition' => '')));
		plugin_test_queue_db_result('db_fetch_row_prepared', array('results' => json_encode(array(array('src_addr' => '10.0.0.1', 'bytes' => 5)))));

		parallel_database_child_runner(5, 2);

		expect(plugin_test_db_calls('db_execute_prepared', 'SET cached_shards = cached_shards + 1'))->toHaveCount(1)
			->and(plugin_test_db_calls('db_fetch_cell_prepared', 'GET_LOCK'))->toBeEmpty();
	});

	it('scans a whole cold partition and caches it for the next report', function () use ($query, $shard) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $query);
		plugin_test_queue_db_result('db_fetch_row_prepared', array_merge($shard, array('full_scan' => 1, 'map_partition' => '')));
		plugin_test_queue_db_result('db_fetch_row_prepared', array());
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('src_addr' => '10.0.0.1', 'bytes' => 5)));

		parallel_database_child_runner(5, 2);

		$cached = plugin_test_db_calls('db_execute_prepared', 'INSERT INTO parallel_database_query_shard_cache');

		expect($cached)->toHaveCount(1)
			->and($cached[0]['params'][3])->toBe(date('Y-m-d 00:00:00'))
			->and(plugin_test_db_calls('db_fetch_assoc_prepared', 'SELECT 1'))->toHaveCount(1);
	});
});

describe('the rest of the partition bookkeeping', function () {
	it('reads every partition when it is asked about many, and notes the old key column', function () {
		$tables = array_map(fn ($i) => sprintf('plugin_flowview_raw_2024%03d', $i), range(1, 101));

		plugin_test_queue_db_result('db_fetch_assoc', array(array('TABLE_NAME' => $tables[0], 'TABLE_COLLATION' => 'latin1_swedish_ci', 'ENGINE' => 'Aria')));
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TABLE_NAME' => $tables[0], 'COLUMN_NAME' => 'keycol', 'COLUMN_TYPE' => 'int(11)'),
			array('TABLE_NAME' => 'plugin_flowview_raw_2099001', 'COLUMN_NAME' => 'template_id', 'COLUMN_TYPE' => 'int(11)'),
		));

		$pending = flowview_get_pending_partition_alters($tables, 'Aria');

		expect($GLOBALS['__test_db_calls'][0]['sql'])->toContain('TABLE_NAME LIKE "plugin_flowview_raw_%"')
			->and($pending[$tables[0]]['alter'])->toContain('DROP KEY keycol');
	});

	it('records the newest partition when nothing rotated but it is not the one recorded', function () {
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024009';

		plugin_test_queue_db_result('db_fetch_assoc', array(array('table' => 'plugin_flowview_raw_2024001'), array('table' => 'plugin_flowview_raw_2024003')));

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024003');
	});

	it('keeps the record when there is no Filter to materialize the rotated partitions for', function () {
		$GLOBALS['__test_config']['flowview_parallel_threads']   = 4;
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024001';

		plugin_test_queue_db_result('db_fetch_assoc', array(array('table' => 'plugin_flowview_raw_2024001'), array('table' => 'plugin_flowview_raw_2024003')));

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024001');
	});
});
