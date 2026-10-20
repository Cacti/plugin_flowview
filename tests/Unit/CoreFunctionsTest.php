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

it('flags only changes that alter what a filter materializes', function ($old, $new, $expected) {
	expect(flowview_filter_change_is_material($old, $new))->toBe($expected);
})->with(array(
	'new filter'                   => array(
		array(),
		array('sourceip' => '10.0.0.1', 'statistics' => 10),
		false,
	),
	'nothing changed'              => array(
		array('device_id' => '3', 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0'),
		array('device_id' => '3', 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0'),
		false,
	),
	'display and time settings'    => array(
		array('name' => 'Top Talkers', 'timespan' => '3', 'graph_type' => 'bar', 'cutofflines' => '20', 'usenat' => '', 'statistics' => '10'),
		array('name' => 'Renamed', 'timespan' => '5', 'graph_type' => 'pie', 'cutofflines' => '50', 'usenat' => 'on', 'statistics' => '10'),
		false,
	),
	'source address changed'       => array(
		array('sourceip' => '10.0.0.1'),
		array('sourceip' => '10.0.0.2'),
		true,
	),
	'protocol added'               => array(
		array('protocols' => ''),
		array('protocols' => '6, 17'),
		true,
	),
	'report type changed'          => array(
		array('statistics' => '10', 'printed' => '0'),
		array('statistics' => '11', 'printed' => '0'),
		true,
	),
	'device restricted'            => array(
		array('device_id' => '0'),
		array('device_id' => '3'),
		true,
	),
	'unrestricted saved differently' => array(
		array('device_id' => '0', 'ex_addr' => '-1', 'protocols' => '0', 'template_id' => ''),
		array('device_id' => '', 'ex_addr' => '', 'protocols' => '', 'template_id' => '0'),
		false,
	),
	'sort field on printed report' => array(
		array('printed' => '2', 'statistics' => '0', 'sortfield' => 'bytes'),
		array('printed' => '2', 'statistics' => '0', 'sortfield' => 'packets'),
		true,
	),
	'sort field on statistics report' => array(
		array('printed' => '0', 'statistics' => '10', 'sortfield' => 'bytes'),
		array('printed' => '0', 'statistics' => '10', 'sortfield' => 'packets'),
		false,
	),
	'partial save only compares what it carries' => array(
		array('device_id' => '3', 'sourceip' => '10.0.0.1', 'statistics' => '10'),
		array('device_id' => '3'),
		false,
	),
));

it('recommends a forced re-materialization pointing at the Materialize View action', function () {
	expect(flowview_rematerialize_recommendation())->toContain('re-materialization')
		->and(flowview_rematerialize_recommendation())->toContain('Materialize View');
});

it('starts the materialization in the background for the given queries', function () {
	if (!function_exists('exec_background')) {
		function exec_background($filename, $args = '', $redirect_args = '') {
			$GLOBALS['__test_exec'][] = array($filename, $args);
		}
	}

	if (!function_exists('cacti_escapeshellarg')) {
		function cacti_escapeshellarg($string) {
			return escapeshellarg($string);
		}
	}

	$GLOBALS['__test_exec']             = array();
	$original_base_path                = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path']     = '/var/www/cacti';
	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';

	plugin_test_queue_db_result('db_fetch_cell_prepared', 0);

	try {
		$started = flowview_launch_materialize(array('3', 5, '7; rm -rf /'));
	} finally {
		$GLOBALS['config']['base_path'] = $original_base_path;
	}

	expect($started)->toBeTrue()
		->and($GLOBALS['__test_exec'])->toBe(array(
			array('/usr/bin/php', "'/var/www/cacti/plugins/flowview/cli/materialize_queries.php' --query-id=3,5,7"),
		))
		->and($GLOBALS['__test_db_calls'][0]['params'])->toBe(array('flowview', 'materialize', 0));
});

it('does not start another materialization while one is registered', function () {
	$GLOBALS['__test_exec'] = array();

	plugin_test_queue_db_result('db_fetch_cell_prepared', 1);

	expect(flowview_launch_materialize(array(3)))->toBeFalse()
		->and($GLOBALS['__test_exec'])->toBe(array());
});

it('keys the cached shards on the map query without the time range', function () {
	$inner = array(
		'sql_query'        => 'SELECT src_addr, SUM(bytes) AS bytes',
		'sql_where'        => 'WHERE protocol IN (?)',
		'sql_range'        => '(`start_time` BETWEEN ? AND ?)',
		'sql_groupby'      => 'GROUP BY src_addr',
		'sql_params'       => array(6),
		'sql_range_params' => array('2024-01-01 00:00:00', '2024-01-02 00:00:00'),
		'sql_start_time'   => 1704067200,
		'sql_end_time'     => 1704153600,
	);

	$later = $inner;
	$later['sql_range_params'] = array('2024-02-01 00:00:00', '2024-02-02 00:00:00');
	$later['sql_start_time']   = 1706745600;
	$later['sql_end_time']     = 1706832000;

	$other = $inner;
	$other['sql_where'] = 'WHERE protocol IN (?, ?)';

	expect(parallel_database_query_table_md5($inner))->toBe(md5(parallel_database_query_map_query($inner)))
		->and(parallel_database_query_table_md5($later))->toBe(parallel_database_query_table_md5($inner))
		->and(parallel_database_query_table_md5($other))->not->toBe(parallel_database_query_table_md5($inner))
		->and(parallel_database_query_map_query($inner))->not->toContain('sql_range');
});

it('plans the cache keys of a filter for each NAT mode without running it', function () {
	if (!function_exists('array_rekey')) {
		function array_rekey($array, $key, $value) {
			return array();
		}
	}

	$filter = array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	);

	$plan = function ($nat) use ($filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		return run_flow_query(false, 4, 1704067200, 1704153600, true, $nat);
	};

	$nat     = $plan(true);
	$non_nat = $plan(false);

	expect($nat)->toBeArray()->toHaveCount(1)
		->and($nat[0])->toMatch('/^[0-9a-f]{32}$/')
		->and($non_nat[0])->toMatch('/^[0-9a-f]{32}$/')
		->and($nat[0])->not->toBe($non_nat[0])
		->and($plan(true))->toBe($nat);

	$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['fn'], 'db_execute') === 0;
	});

	expect($writes)->toBeEmpty();
});

it('counts the partitions of a filter that are cached with and without NAT', function () {
	if (!function_exists('array_rekey')) {
		function array_rekey($array, $key, $value) {
			return array();
		}
	}

	$filter = array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	);

	// Each mode plans the filter (a row and the name) and then counts its cache rows.
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	plugin_test_queue_db_result('db_fetch_cell_prepared', 'Top Talkers');
	plugin_test_queue_db_result('db_fetch_cell_prepared', '12');
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	plugin_test_queue_db_result('db_fetch_cell_prepared', 'Top Talkers');
	plugin_test_queue_db_result('db_fetch_cell_prepared', '7');

	expect(flowview_count_cached_shards(4))->toBe(array('nat' => 12, 'non_nat' => 7));

	$counts = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'] ?? '', 'FROM parallel_database_query_shard_cache') !== false;
	}));

	expect($counts)->toHaveCount(2)
		->and($counts[0]['params'])->toHaveCount(2)
		->and($counts[0]['params'][1])->toBe(1)
		->and($counts[0]['params'][0])->not->toBe($counts[1]['params'][0]);
});

it('counts nothing for a filter that cannot be planned', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', array());
	plugin_test_queue_db_result('db_fetch_row_prepared', array());

	expect(flowview_count_cached_shards(99))->toBe(array('nat' => 0, 'non_nat' => 0));
});
