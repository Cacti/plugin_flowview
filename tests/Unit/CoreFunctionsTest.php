<?php

beforeEach(function () {
	plugin_test_reset();
	date_default_timezone_set('UTC');
});

afterEach(function () {
	date_default_timezone_set('UTC');
});

it('normalizes whitespace and tokenizes flow output', function () {
	expect(removeWhiteSpace("  alpha\t beta   gamma  "))->toBe(' alpha beta gamma ')
		->and(flowview_explode('  alpha   beta  gamma '))->toBe(array('alpha', 'beta', 'gamma'))
		->and(flowview_explode('   '))->toBe(array());
});

it('maps type-of-service bits to their precedence and service labels', function ($tos, $expected) {
	expect(parse_tos($tos))->toBe($expected);
})->with(array(
	array(0, 'Routine'),
	array(16, 'Low Delay, Routine'),
	array(40, 'Throughput, Priority'),
	array(224, 'Net Control'),
));

it('formats display helpers deterministically', function () {
	expect(display_domain(''))->toBe('unresolved')
		->and(display_domain('example.net'))->toBe('example.net')
		->and(get_column_alignment('Bytes'))->toBe('right')
		->and(get_column_alignment('Source'))->toBe('left')
		->and(flowview_altrow(0))->toBe('even')
		->and(flowview_altrow(3))->toBe('odd');
});

it('serializes only report parameters', function () {
	$_POST = array(
		'__csrf_magic' => 'secret',
		'domains' => 'ignored',
		'table' => 'ignored',
		'view' => 'ignored',
		'bytes' => 'ignored',
		'packets' => 'ignored',
		'flows' => 'ignored',
		'sourceip' => '192.0.2.1',
		'protocols' => array('6', '17'),
	);

	expect(json_decode(get_json_params(), true))->toBe(array(
		'sourceip' => '192.0.2.1',
		'protocols' => array('6', '17'),
	));
});

it('derives domains without rewriting IP addresses', function () {
	expect(flowview_get_rdomain_from_domain('host.eu.example.net'))->toBe('example.net')
		->and(flowview_get_rdomain_from_domain('localhost'))->toBe('')
		->and(flowview_get_domain('host.eu.example.net', true))->toBe('example.net')
		->and(flowview_get_domain('192.0.2.5', true))->toBe('192.0.2.5')
		->and(flowview_get_domain('host.eu.example.net'))->toBe('host.eu.example.net');
});

it('chooses chart scale boundaries', function ($value, $expected) {
	expect(flowview_autoscale($value))->toBe($expected);
})->with(array(
	array(999, array(1, '')),
	array(1000, array(1000, 'K')),
	array(1000000, array(1000000, 'M')),
	array(1000000000, array(1000000000, 'G')),
	array(1000000000000, array(1000000000000, 'P')),
));

it('produces stable cache keys and expires only stale sessions', function () {
	$key = get_flowview_session_key(7, 100, 200);
	expect($key)->toMatch('/^7_[a-f0-9]{32}$/');

	$_SESSION['sess_flowdata'] = array(
		'fresh' => array('timeout' => time() + 60),
		'stale' => array('timeout' => time() - 1),
	);
	purge_flowview_sessions();

	expect($_SESSION['sess_flowdata'])->toHaveKey('fresh')
		->and($_SESSION['sess_flowdata'])->not->toHaveKey('stale');
});

it('converts daily partition names into exact UTC bounds', function () {
	expect(flowview_table_name_to_time('plugin_flowview_raw_2024000', 'start_time'))->toBe(strtotime('2024-01-01 00:00:00 UTC'))
		->and(flowview_table_name_to_time('plugin_flowview_raw_2024000', 'end_time'))->toBe(strtotime('2024-01-02 00:00:00 UTC'))
		->and(flowview_table_name_to_time('invalid_table', 'start_time'))->toBeFalse();
});

it('converts hourly partition names into one-hour bounds', function () {
	expect(flowview_table_name_to_time('plugin_flowview_raw_202416412', 'start_time'))->toBe(strtotime('2024-06-13 12:00:00 UTC'))
		->and(flowview_table_name_to_time('plugin_flowview_raw_202416412', 'end_time'))->toBe(strtotime('2024-06-13 13:00:00 UTC'));
});

it('uses local calendar boundaries across daylight-saving changes', function ($timezone, $table, $expectedStart, $expectedEnd) {
	date_default_timezone_set($timezone);

	expect(flowview_table_name_to_time($table, 'start_time'))->toBe(strtotime($expectedStart))
		->and(flowview_table_name_to_time($table, 'end_time'))->toBe(strtotime($expectedEnd));
})->with(array(
	array('America/New_York', 'plugin_flowview_raw_202406901', '2024-03-10 01:00:00', '2024-03-10 03:00:00'),
	array('America/New_York', 'plugin_flowview_raw_202430701', '2024-11-03 01:00:00 EDT', '2024-11-03 02:00:00 EST'),
	array('Europe/Berlin', 'plugin_flowview_raw_202409001', '2024-03-31 01:00:00', '2024-03-31 03:00:00'),
));

it('declares load_data_for_filter start/end as int|false rather than bool', function () {
	// Regression guard: a bool hint silently coerced the nonzero Unix timestamps
	// plugin_flowview_run_schedule() passes into true, so scheduled reports queried
	// epoch second 1. This exact change passed syntax and static-analysis checks.
	$types = array();
	foreach ((new ReflectionFunction('load_data_for_filter'))->getParameters() as $param) {
		$types[$param->getName()] = (string) $param->getType();
	}

	expect($types['start'])->toBe('int|false')
		->and($types['end'])->toBe('int|false');
});

it('carries nonzero epoch timestamps through load_data_for_filter to the query path', function () {
	// Drive the request-var path so $session is true, then confirm run_flow_query()
	// recomputes the session cache key from the real timestamps (not a coerced 1)
	// by returning the entry seeded under that exact key.
	$_REQUEST['query'] = 7;
	$_REQUEST['date1'] = '2024-01-01 00:00:00';
	$_REQUEST['date2'] = '2024-01-02 00:00:00';

	$key      = get_flowview_session_key(7, strtotime('2024-01-01 00:00:00'), strtotime('2024-01-02 00:00:00'));
	$sentinel = array('id' => 7, 'table' => 'sentinel-rows');

	$_SESSION['sess_flowdata'] = array(
		$key => array('data' => $sentinel, 'timeout' => time() + 60),
	);

	expect(load_data_for_filter())->toBe($sentinel);
});

it('formats nonzero epoch bounds into the flow date range filter', function () {
	// get_date_filter() is run_flow_query()'s consumer of the timestamps; a coerced
	// value of 1 would render 1970-01-01 00:00:01 instead of the real window.
	$params = array();
	$sql    = get_date_filter('', $params, strtotime('2024-01-01 00:00:00 UTC'), strtotime('2024-01-02 00:00:00 UTC'), 3);

	expect($sql)->toBe('(`start_time` BETWEEN ? AND ?)')
		->and($params)->toBe(array('2024-01-01 00:00:00', '2024-01-02 00:00:00'));
});

it('shifts the date range backward and forward by the selected interval', function ($direction, $shift, $date1, $date2, $expected1, $expected2) {
	if (!defined('GTS_1_DAY')) {
		define('GTS_1_DAY', 7);
	}

	$GLOBALS['graph_timeshifts_vals'] = array(7 => '1 Day', 11 => '1 Week', 13 => '1 Month');

	$_REQUEST['direction']            = $direction;
	$_REQUEST['predefined_timeshift'] = $shift;
	$_REQUEST['date1']                = $date1;
	$_REQUEST['date2']                = $date2;

	ob_start();
	flowview_timeshift();
	$span = json_decode(ob_get_clean(), true);

	expect($span['current_value_date1'])->toBe($expected1)
		->and($span['current_value_date2'])->toBe($expected2);
})->with(array(
	'left one week'            => array('left',  11, '2026-10-13 12:02', '2026-10-20 12:02', '2026-10-06 12:02', '2026-10-13 12:02'),
	'right one week'           => array('right', 11, '2026-10-13 12:02', '2026-10-20 12:02', '2026-10-20 12:02', '2026-10-27 12:02'),
	'right one month'          => array('right', 13, '2026-09-01 00:00', '2026-09-30 23:59', '2026-10-01 00:00', '2026-10-30 23:59'),
	'unknown interval is 1day' => array('left',  99, '2026-10-13 12:02', '2026-10-20 12:02', '2026-10-12 12:02', '2026-10-19 12:02'),
));

it('returns an empty range when the time shift dates cannot be parsed', function () {
	$_REQUEST['direction']            = 'left';
	$_REQUEST['predefined_timeshift'] = 7;
	$_REQUEST['date1']                = 'not a date';
	$_REQUEST['date2']                = '';

	ob_start();
	flowview_timeshift();

	expect(json_decode(ob_get_clean(), true))->toBe(array());
});

it('spans every partition when materializing and pads the bounds so shards are full scans', function () {
	$range = flowview_get_materialize_range(array(
		'plugin_flowview_raw_2024003',
		'not_a_partition_table',
		'plugin_flowview_raw_2024001',
		'plugin_flowview_raw_202400105',
	));

	expect($range)->toBe(array(
		strtotime('2024-01-02 00:00:00 UTC') - 1,
		strtotime('2024-01-05 00:00:00 UTC') + 1,
	));
});

it('has no materialization range without a valid partition table', function ($tables) {
	expect(flowview_get_materialize_range($tables))->toBeFalse();
})->with(array(
	'no tables'        => array(array()),
	'not partitions'   => array(array('plugin_flowview_queries', 'plugin_flowview_raw_abc')),
));

it('treats a cached partition shard as current only while the partition bounds match', function ($cached, $current, $expected) {
	expect(flowview_shard_cache_is_current($cached, $current))->toBe($expected);
})->with(array(
	'identical bounds'          => array(
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:59:59'),
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:59:59'),
		true,
	),
	'microsecond precision'     => array(
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:59:59'),
		array('min_date' => '2026-10-20 12:00:00.400000', 'max_date' => '2026-10-20 12:59:59.900000'),
		true,
	),
	'partition has more data'   => array(
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:30:00'),
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:45:00'),
		false,
	),
	'partition starts earlier'  => array(
		array('min_date' => '2026-10-20 12:05:00', 'max_date' => '2026-10-20 12:30:00'),
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:30:00'),
		false,
	),
	'empty partition'           => array(
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:30:00'),
		array('min_date' => null, 'max_date' => null),
		false,
	),
	'unset cached bounds'       => array(
		array('min_date' => '0000-00-00', 'max_date' => '0000-00-00'),
		array('min_date' => '2026-10-20 12:00:00', 'max_date' => '2026-10-20 12:30:00'),
		false,
	),
));

it('keeps accurate cached partitions and removes ones that changed or were dropped', function () {
	plugin_test_queue_db_result('db_fetch_assoc', array(
		array('md5sum' => 'aaa', 'map_table' => 'plugin_flowview_raw_2024001', 'map_partition' => '', 'min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'),
		array('md5sum' => 'bbb', 'map_table' => 'plugin_flowview_raw_2024001', 'map_partition' => '', 'min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'),
		array('md5sum' => 'aaa', 'map_table' => 'plugin_flowview_raw_2024002', 'map_partition' => '', 'min_date' => '2024-01-03 00:00:00', 'max_date' => '2024-01-03 12:00:00'),
		array('md5sum' => 'aaa', 'map_table' => 'plugin_flowview_raw_2024009', 'map_partition' => '', 'min_date' => '2024-01-10 00:00:00', 'max_date' => '2024-01-10 23:00:00'),
	));

	// Each distinct partition table is checked once: it exists, then its current bounds.
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', array('min_date' => '2024-01-02 00:00:00.120000', 'max_date' => '2024-01-02 23:00:00.480000'));
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', array('min_date' => '2024-01-03 00:00:00', 'max_date' => '2024-01-03 20:00:00'));
	plugin_test_queue_db_result('db_fetch_cell', 0);

	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('id' => 9)));

	expect(flowview_invalidate_stale_shard_cache())->toBe(array('kept' => 2, 'removed' => 2));

	$deleted = array();
	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if ($call['fn'] == 'db_execute_prepared' && strpos($call['sql'], 'DELETE FROM parallel_database_query_shard_cache') === 0) {
			$deleted[] = $call['params'];
		}
	}

	expect($deleted)->toBe(array(
		array('aaa', 'plugin_flowview_raw_2024002', ''),
		array('aaa', 'plugin_flowview_raw_2024009', ''),
	));

	$requests = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] == 'db_execute_prepared' && preg_match('/^DELETE FROM parallel_database_query\s+WHERE id = \?/', $call['sql']) === 1;
	}));

	expect($requests)->toHaveCount(1)
		->and($requests[0]['params'])->toBe(array(9));
});

it('leaves finished requests alone when every cached partition is still accurate', function () {
	plugin_test_queue_db_result('db_fetch_assoc', array(
		array('md5sum' => 'aaa', 'map_table' => 'plugin_flowview_raw_2024001', 'map_partition' => '', 'min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'),
	));
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', array('min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'));

	expect(flowview_invalidate_stale_shard_cache())->toBe(array('kept' => 1, 'removed' => 0));

	$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['fn'], 'db_execute') === 0;
	});

	expect($writes)->toBeEmpty();
});
