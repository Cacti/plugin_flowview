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

	// A flow can start before the window of its partition, so the start has a day of room.
	expect($range)->toBe(array(
		strtotime('2024-01-02 00:00:00 UTC') - 86400,
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

it('tells the user to start a materialization with the Materialize View action', function () {
	expect(flowview_rematerialize_recommendation())->toContain('materialized again')
		->and(flowview_rematerialize_recommendation())->toContain('Materialize View');
});

describe('warning before a Filter is saved with a change to what is cached', function () {
	beforeEach(function () {
		plugin_test_reset();

		/* the dialog uses the CSP nonce of setup.php */
		require_once __DIR__ . '/../../setup.php';
	});

	it('does not warn for a new Filter, as it has nothing cached', function () {
		expect(flowview_filter_form_is_material(array('id' => '', 'sourceip' => '10.0.0.1')))->toBeFalse()
			->and($GLOBALS['__test_db_calls'])->toBe(array());
	});

	it('warns when the change alters what is cached', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('id' => 4, 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0'));

		expect(flowview_filter_form_is_material(array('id' => '4', 'sourceip' => '10.0.0.2', 'statistics' => '10', 'printed' => '0')))->toBeTrue();
	});

	it('does not warn for a change of how it is shown', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('id' => 4, 'name' => 'Old', 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0', 'graph_type' => 'bar'));

		expect(flowview_filter_form_is_material(array('id' => '4', 'name' => 'New', 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0', 'graph_type' => 'pie')))->toBeFalse();
	});

	it('answers the form with JSON, without saving anything', function () {
		$_REQUEST = array('id' => '4', 'name' => 'Top', 'sourceip' => '10.0.0.2', 'statistics' => '10', 'printed' => '0');

		plugin_test_queue_db_result('db_fetch_row_prepared', array('id' => 4, 'sourceip' => '10.0.0.1', 'statistics' => '10', 'printed' => '0'));

		ob_start();
		flowview_check_filter_save();
		$output = ob_get_clean();

		expect(json_decode($output, true))->toBe(array('material' => true))
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['fn'], 'db_execute') === 0;
			}))->toBeEmpty();
	});

	it('says the save succeeded and to materialize again, once, in a dialog', function () {
		$_SESSION['flowview_rematerialize_saved'] = 4;

		ob_start();
		flowview_show_saved_dialog();
		$first = ob_get_clean();

		ob_start();
		flowview_show_saved_dialog();
		$second = ob_get_clean();

		expect($first)->toContain("id='flowview_saved_dialog'")
			->and($first)->toContain('Save Succeeded')
			->and($first)->toContain('Materialize View')
			->and($first)->toContain("\$('#flowview_saved_dialog').dialog(")
			->and($first)->toContain('modal: false')
			->and($second)->toBe('')
			->and(isset($_SESSION['flowview_rematerialize_saved']))->toBeFalse();
	});

	it('shows nothing when nothing was saved with a change to what is cached', function () {
		ob_start();
		flowview_show_saved_dialog();

		expect(ob_get_clean())->toBe('');
	});

	it('warns from the Save button with Cancel and Continue, and no longer raises a warning message', function () {
		$source = plugin_test_read_source('includes/functions.php');

		expect($source)->toContain("id='flowview_save_warning'")
			->and($source)->toContain("\$('#chk #submit').off('click.flowview').on('click.flowview'")
			->and($source)->toContain("{ name: 'action', value: 'check_material' }")
			->and($source)->toContain("\$('#flowview_save_warning').dialog(")
			->and($source)->toContain('sessionMessageCancel')
			->and($source)->toContain('sessionMessageContinue')
			->and($source)->not->toContain("raise_message('flowview_rematerialize'")
			->and(plugin_test_read_source('flowview_filters.php'))->toContain("case 'check_material':");
	});
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

	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 4)));

	try {
		$started = flowview_launch_materialize(array('3', 5, '7; rm -rf /', 4, 0, 5));
	} finally {
		$GLOBALS['config']['base_path'] = $original_base_path;
	}

	expect($started)->toBe(array(3, 5, 7))
		->and($GLOBALS['__test_exec'])->toBe(array(
			array('/usr/bin/php', "'/var/www/cacti/plugins/flowview/cli/materialize_queries.php' --query-id=3,5,7"),
		))
		->and($GLOBALS['__test_db_calls'][0]['params'])->toBe(array('flowview', 'materialize'));
});

it('does not start a materialization for filters that are already being materialized', function () {
	$GLOBALS['__test_exec'] = array();

	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 3)));

	expect(flowview_launch_materialize(array(3)))->toBe(array())
		->and($GLOBALS['__test_exec'])->toBe(array());
});

it('materializes every filter that is not already being materialized when none are given', function () {
	$GLOBALS['__test_exec']                      = array();
	$original_base_path                          = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path']              = '/var/www/cacti';
	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';

	plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 1), array('id' => 4), array('id' => 9)));
	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 4)));

	try {
		$started = flowview_launch_materialize();
	} finally {
		$GLOBALS['config']['base_path'] = $original_base_path;
	}

	expect($started)->toBe(array(1, 9))
		->and($GLOBALS['__test_exec'])->toBe(array(
			array('/usr/bin/php', "'/var/www/cacti/plugins/flowview/cli/materialize_queries.php' --query-id=1,9"),
		));
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

it('finds the partitions of a filter that are cached with and without NAT', function () {
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

	// Each mode plans the filter (a row and the name) and then looks up its cache rows.
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	plugin_test_queue_db_result('db_fetch_cell_prepared', 'Top Talkers');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
		array('map_table' => 'plugin_flowview_raw_2024001'),
		array('map_table' => 'plugin_flowview_raw_2024002'),
	));
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	plugin_test_queue_db_result('db_fetch_cell_prepared', 'Top Talkers');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
		array('map_table' => 'plugin_flowview_raw_2024001'),
	));

	expect(flowview_get_cached_shard_tables(4))->toBe(array(
		'nat'     => array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'),
		'non_nat' => array('plugin_flowview_raw_2024001'),
	));

	$lookups = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return strpos($call['sql'] ?? '', 'FROM parallel_database_query_shard_cache') !== false;
	}));

	expect($lookups)->toHaveCount(2)
		->and($lookups[0]['params'])->toHaveCount(2)
		->and($lookups[0]['params'][1])->toBe(1)
		->and($lookups[0]['params'][0])->not->toBe($lookups[1]['params'][0]);
});

it('finds nothing cached for a filter that cannot be planned', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', array());
	plugin_test_queue_db_result('db_fetch_row_prepared', array());

	expect(flowview_get_cached_shard_tables(99))->toBe(array('nat' => array(), 'non_nat' => array()));
});

it('lists only genuine raw partition tables, oldest first', function () {
	plugin_test_queue_db_result('db_fetch_assoc', array(
		array('table' => 'plugin_flowview_raw_202400105'),
		array('table' => 'plugin_flowview_raw_2024001'),
		array('table' => 'plugin_flowview_raw_abc'),
		array('table' => 'plugin_flowview_rawX2024001'),
		array('table' => 'plugin_flowview_raw_2024001_bak'),
		array('table' => 'plugin_flowview_raw_2023364'),
	));

	expect(flowview_get_partition_tables())->toBe(array(
		'plugin_flowview_raw_2023364',
		'plugin_flowview_raw_2024001',
		'plugin_flowview_raw_202400105',
	));
});

it('lists no partition tables when none exist', function () {
	expect(flowview_get_partition_tables())->toBe(array());
});

it('keys daily and hourly partitions so they sort in time order', function () {
	expect(flowview_partition_sort_key('plugin_flowview_raw_2024001'))->toBe('202400100')
		->and(flowview_partition_sort_key('plugin_flowview_raw_202400105'))->toBe('202400105')
		->and(flowview_partition_sort_key('plugin_flowview_raw_abc'))->toBe('')
		->and(flowview_partition_sort_key('plugin_flowview_queries'))->toBe('');
});

it('treats every partition but the newest as cold', function ($tables, $expected) {
	expect(flowview_get_cold_partitions($tables))->toBe($expected);
})->with(array(
	'none'            => array(array(), array()),
	'only the hot one' => array(array('plugin_flowview_raw_2024003'), array()),
	'several'         => array(
		array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003'),
		array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'),
	),
	'hourly'          => array(
		array('plugin_flowview_raw_202400105', 'plugin_flowview_raw_202400106'),
		array('plugin_flowview_raw_202400105'),
	),
));

it('finds the partitions that rotated since the previous newest one', function ($previous, $expected) {
	$tables = array(
		'plugin_flowview_raw_2024001',
		'plugin_flowview_raw_2024002',
		'plugin_flowview_raw_2024003',
		'plugin_flowview_raw_2024004',
	);

	expect(flowview_get_rotated_partitions($tables, $previous))->toBe($expected);
})->with(array(
	'nothing recorded'      => array('', array()),
	'not a partition'       => array('whatever', array()),
	'newest is unchanged'   => array('plugin_flowview_raw_2024004', array()),
	'one rotated'           => array('plugin_flowview_raw_2024003', array('plugin_flowview_raw_2024003')),
	'several rotated'       => array('plugin_flowview_raw_2024002', array('plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003')),
	'previous is gone'      => array('plugin_flowview_raw_2024002_old', array()),
	'recorded is ahead'     => array('plugin_flowview_raw_2024009', array()),
));

it('reports the automatic materialization status of a filter', function ($nat, $non_nat, $running, $expected) {
	$cold = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002');

	expect(flowview_materialization_status($nat, $non_nat, $cold, $running))->toBe($expected);
})->with(array(
	'both complete'         => array(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), false, 'current'),
	'complete while running' => array(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), true, 'current'),
	'hot one cached too'    => array(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003'), array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), false, 'current'),
	'nat missing one'       => array(array('plugin_flowview_raw_2024001'), array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), false, 'required'),
	'non nat missing'       => array(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002'), array(), false, 'required'),
	'incomplete and running' => array(array(), array(), true, 'running'),
	'nothing cached'        => array(array(), array(), false, 'required'),
));

it('is up to date when there are no cold partitions to materialize', function () {
	expect(flowview_materialization_status(array(), array(), array(), false))->toBe('current');
});

it('is required when the live partition is cached but an older one is not, and names it', function () {
	$tables = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003', 'plugin_flowview_raw_2024004');
	$cold   = flowview_get_cold_partitions($tables);

	// every partition but the oldest is cached, and that includes the live one
	$cached = array('plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003', 'plugin_flowview_raw_2024004');

	expect(flowview_materialization_status($cached, $cached, $cold, false))->toBe('required')
		->and(flowview_get_missing_partitions($cached, $cached, $cold))->toBe(array(
			'nat'     => array('plugin_flowview_raw_2024001'),
			'non_nat' => array('plugin_flowview_raw_2024001'),
		));
});

it('is up to date when every older partition is cached and only the live one is not', function () {
	$tables = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003');
	$cold   = flowview_get_cold_partitions($tables);
	$cached = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002');

	expect(flowview_materialization_status($cached, $cached, $cold, false))->toBe('current')
		->and(flowview_get_missing_partitions($cached, $cached, $cold))->toBe(array('nat' => array(), 'non_nat' => array()));
});

it('describes the partitions that are missing in a tip', function ($tables, $expected) {
	expect(flowview_describe_partitions($tables))->toBe($expected);
})->with(array(
	'none'      => array(array(), '0'),
	'one'       => array(array('plugin_flowview_raw_2024001'), '1 (plugin_flowview_raw_2024001)'),
	'three'     => array(array('a', 'b', 'c'), '3 (a, b, c)'),
	'too many'  => array(array('a', 'b', 'c', 'd', 'e'), '5 (a, b, c, ...)'),
));

it('says which partitions are missing in the tip of a pill that is not up to date', function () {
	$missing = array('nat' => array('plugin_flowview_raw_2024001'), 'non_nat' => array());

	expect(flowview_get_colored_materialization_status('required', $missing))
		->toContain("deviceDown' title='Not materialized yet, with NAT: 1 (plugin_flowview_raw_2024001), without NAT: 0.'")
		->and(flowview_get_colored_materialization_status('running', $missing))->toContain('deviceRecovering\' title=')
		->and(flowview_get_colored_materialization_status('current', $missing))->not->toContain('title=');
});

it('shows the status pills in the form of the device status', function ($status, $class, $label) {
	expect(flowview_get_colored_materialization_status($status))->toBe("<span class='deviceStatus " . $class . "'>" . $label . '</span>');
})->with(array(
	'current'  => array('current', 'deviceUp', 'Up to date'),
	'running'  => array('running', 'deviceRecovering', 'Running'),
	'required' => array('required', 'deviceDown', 'Required'),
	'unknown'  => array('anything', 'deviceDown', 'Required'),
));

it('words the parallel query timeout for the log and a script, not the browser', function () {
	$message = flowview_parallel_timeout_message(300);

	expect($message)->toStartWith('ERROR: ')
		->and($message)->toContain('300 seconds')
		->and($message)->toContain('Maximum Run Time')
		->and($message)->not->toContain('<script')
		->and($message)->not->toContain('DOMPurify');
});

it('starts a materialization for only the partitions it is given', function () {
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

	$GLOBALS['__test_exec']                      = array();
	$original_base_path                          = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path']              = '/var/www/cacti';
	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';

	plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 1), array('id' => 2)));

	try {
		$started = flowview_launch_materialize(array(), array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024001; rm', 'plugin_flowview_raw_2024002'));
	} finally {
		$GLOBALS['config']['base_path'] = $original_base_path;
	}

	expect($started)->toBe(array(1, 2))
		->and($GLOBALS['__test_exec'])->toBe(array(
			array('/usr/bin/php', "'/var/www/cacti/plugins/flowview/cli/materialize_queries.php' --query-id=1,2 --partitions=plugin_flowview_raw_2024001,plugin_flowview_raw_2024002"),
		));
});

it('only checks the cache of the partitions it is limited to', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
		array('md5sum' => 'aaa', 'map_table' => 'plugin_flowview_raw_2024001', 'map_partition' => '', 'min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'),
	));
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', array('min_date' => '2024-01-02 00:00:00', 'max_date' => '2024-01-02 23:00:00'));

	expect(flowview_invalidate_stale_shard_cache(array('plugin_flowview_raw_2024001')))->toBe(array('kept' => 1, 'removed' => 0));

	$lookups = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] == 'db_fetch_assoc_prepared' && strpos($call['sql'], 'map_table IN (?)') !== false;
	}));

	expect($lookups)->toHaveCount(1)
		->and($lookups[0]['params'])->toBe(array('plugin_flowview_raw_2024001'));
});

describe('materializing the partitions that rotated', function () {
	beforeEach(function () {
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

		$GLOBALS['__test_exec']                          = array();
		$GLOBALS['__test_config']['flowview_parallel_threads'] = 4;
		$GLOBALS['__test_config']['path_php_binary']     = '/usr/bin/php';

		$this->tables = array(
			array('table' => 'plugin_flowview_raw_2024001'),
			array('table' => 'plugin_flowview_raw_2024002'),
			array('table' => 'plugin_flowview_raw_2024003'),
		);
	});

	it('only records the newest partition on the first cycle', function () {
		plugin_test_queue_db_result('db_fetch_assoc', $this->tables);

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024003')
			->and($GLOBALS['__test_exec'])->toBe(array());
	});

	it('does nothing when no partition has rotated', function () {
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024003';
		plugin_test_queue_db_result('db_fetch_assoc', $this->tables);

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024003')
			->and($GLOBALS['__test_exec'])->toBe(array());
	});

	it('starts a materialization for the partitions that rotated and records the newest', function () {
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024002';
		plugin_test_queue_db_result('db_fetch_assoc', $this->tables);
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 1), array('id' => 2)));

		$original_base_path             = $GLOBALS['config']['base_path'];
		$GLOBALS['config']['base_path'] = '/var/www/cacti';

		try {
			$started = flowview_materialize_rotated_partitions();
		} finally {
			$GLOBALS['config']['base_path'] = $original_base_path;
		}

		expect($started)->toBe(array('plugin_flowview_raw_2024002'))
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024003')
			->and($GLOBALS['__test_exec'])->toBe(array(
				array('/usr/bin/php', "'/var/www/cacti/plugins/flowview/cli/materialize_queries.php' --query-id=1,2 --partitions=plugin_flowview_raw_2024002"),
			));
	});

	it('leaves the record alone while a materialization is running so it is caught up later', function () {
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024001';
		plugin_test_queue_db_result('db_fetch_assoc', $this->tables);
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 3)));

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024001')
			->and($GLOBALS['__test_exec'])->toBe(array());
	});

	it('leaves the record alone when the parallel query is not enabled', function () {
		$GLOBALS['__test_config']['flowview_parallel_threads']   = 1;
		$GLOBALS['__test_config']['flowview_last_hot_partition'] = 'plugin_flowview_raw_2024001';
		plugin_test_queue_db_result('db_fetch_assoc', $this->tables);

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config']['flowview_last_hot_partition'])->toBe('plugin_flowview_raw_2024001')
			->and($GLOBALS['__test_exec'])->toBe(array());
	});

	it('does nothing without any partitions', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array());

		expect(flowview_materialize_rotated_partitions())->toBe(array())
			->and($GLOBALS['__test_config'])->not->toHaveKey('flowview_last_hot_partition');
	});
});

describe('preparing the partitions before they are scanned', function () {
	$current = array(
		'collation'       => 'latin1_swedish_ci',
		'engine'          => 'Aria',
		'has_keycol'      => false,
		'has_template_id' => true,
		'indexes'         => array('template_id', 'start_time', 'end_time', 'ex_addr'),
		'ex_addr_type'    => 'varchar(46)',
	);

	it('needs no alter for a partition that is already current', function () use ($current) {
		expect(flowview_get_partition_alter($current, 'Aria'))->toBe(array('alter' => '', 'ex_change' => false));
	});

	it('alters only the engine of a partition that has rotated but kept the live engine', function ($raw_engine, $expected) use ($current) {
		$info           = $current;
		$info['engine'] = 'InnoDB';

		expect(flowview_get_partition_alter($info, $raw_engine))->toBe(array('alter' => $expected, 'ex_change' => false));
	})->with(array(
		'aria'   => array('Aria', ' ENGINE=Aria ROW_FORMAT=page'),
		'myisam' => array('MyISAM', ' ENGINE=MyISAM ROW_FORMAT=Dynamic'),
	));

	it('plans every alter a partition from an old version needs, as the schema upgrade always did', function () {
		$info = array(
			'collation'       => 'utf8mb4_general_ci',
			'engine'          => 'InnoDB',
			'has_keycol'      => true,
			'has_template_id' => false,
			'indexes'         => array(),
			'ex_addr_type'    => 'varbinary(16)',
		);

		expect(flowview_get_partition_alter($info, 'Aria'))->toBe(array(
			'alter'     => 'CONVERT TO CHARACTER SET latin1, DROP KEY keycol, ADD COLUMN template_id int(11) unsigned NOT NULL default "0" AFTER listener_id, ' .
				'ADD INDEX template_id (template_id), ADD INDEX start_time (start_time), ADD INDEX end_time (end_time), ' .
				'MODIFY COLUMN ex_addr VARCHAR(46) NOT NULL default "", ADD INDEX ex_addr (ex_addr), ENGINE=Aria ROW_FORMAT=page',
			'ex_change' => true,
		));
	});

	it('leaves ex_addr alone when it is a varchar in any case or is missing', function ($type) use ($current) {
		$info                 = $current;
		$info['ex_addr_type'] = $type;

		expect(flowview_get_partition_alter($info, 'Aria'))->toBe(array('alter' => '', 'ex_change' => false));
	})->with(array('VARCHAR(46)', 'varchar(20)', null));

	it('adds only the index a partition is missing', function () use ($current) {
		$info            = $current;
		$info['indexes'] = array('template_id', 'start_time', 'ex_addr');

		expect(flowview_get_partition_alter($info, 'Aria')['alter'])->toBe('ADD INDEX end_time (end_time)');
	});

	it('alters a partition and rewrites ex_addr afterwards when it was converted', function () {
		flowview_apply_partition_alter('plugin_flowview_raw_2024001', array('alter' => 'MODIFY COLUMN ex_addr VARCHAR(46) NOT NULL default ""', 'ex_change' => true));

		$sql = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute';
		}), 'sql');

		expect(array_values($sql))->toBe(array(
			'ALTER TABLE plugin_flowview_raw_2024001 MODIFY COLUMN ex_addr VARCHAR(46) NOT NULL default ""',
			'UPDATE plugin_flowview_raw_2024001 SET ex_addr = SUBSTRING_INDEX(ex_addr, ":", 1)',
		));
	});

	it('does not rewrite ex_addr when it was not converted', function () {
		flowview_apply_partition_alter('plugin_flowview_raw_2024001', array('alter' => 'ADD INDEX end_time (end_time)', 'ex_change' => false));

		$sql = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute';
		}), 'sql');

		expect(array_values($sql))->toBe(array('ALTER TABLE plugin_flowview_raw_2024001 ADD INDEX end_time (end_time)'));
	});

	it('logs an alter and shows it too, as the schema upgrade always did', function () {
		$GLOBALS['__test_cacti_log']       = array();
		$GLOBALS['__test_cacti_log_print'] = array();

		flowview_apply_partition_alter('plugin_flowview_raw_2024001', array('alter' => 'ADD INDEX end_time (end_time)', 'ex_change' => false));

		expect($GLOBALS['__test_cacti_log'])->toBe(array('Altering Table plugin_flowview_raw_2024001 Using this alter ADD INDEX end_time (end_time).'))
			->and($GLOBALS['__test_cacti_log_print'])->toBe(array(true));
	});

	it('only logs an alter when the caller reports it itself, so it is not said twice', function () {
		$GLOBALS['__test_cacti_log']       = array();
		$GLOBALS['__test_cacti_log_print'] = array();

		flowview_apply_partition_alter('plugin_flowview_raw_2024001', array('alter' => ' ENGINE=Aria ROW_FORMAT=page', 'ex_change' => false), false);

		expect($GLOBALS['__test_cacti_log'])->toBe(array('Altering Table plugin_flowview_raw_2024001 Using this alter ENGINE=Aria ROW_FORMAT=page.'))
			->and($GLOBALS['__test_cacti_log_print'])->toBe(array(false));
	});

	it('finds the partitions that still need an alter in a single pass', function () {
		$table = function ($name, $engine) {
			return array('TABLE_NAME' => $name, 'TABLE_COLLATION' => 'latin1_swedish_ci', 'ENGINE' => $engine);
		};

		$columns = function ($name) {
			return array(
				array('TABLE_NAME' => $name, 'COLUMN_NAME' => 'template_id', 'COLUMN_TYPE' => 'int(11) unsigned'),
				array('TABLE_NAME' => $name, 'COLUMN_NAME' => 'ex_addr', 'COLUMN_TYPE' => 'varchar(46)'),
			);
		};

		$indexes = function ($name, $names) {
			return array_map(function ($index) use ($name) {
				return array('TABLE_NAME' => $name, 'INDEX_NAME' => $index);
			}, $names);
		};

		$all = array('template_id', 'start_time', 'end_time', 'ex_addr');

		plugin_test_queue_db_result('db_fetch_assoc', array(
			$table('plugin_flowview_raw_2024001', 'Aria'),
			$table('plugin_flowview_raw_2024002', 'InnoDB'),
			$table('plugin_flowview_raw_2024003', 'Aria'),
		));
		plugin_test_queue_db_result('db_fetch_assoc', array_merge(
			$columns('plugin_flowview_raw_2024001'),
			$columns('plugin_flowview_raw_2024002'),
			$columns('plugin_flowview_raw_2024003')
		));
		plugin_test_queue_db_result('db_fetch_assoc', array_merge(
			$indexes('plugin_flowview_raw_2024001', $all),
			$indexes('plugin_flowview_raw_2024002', $all),
			$indexes('plugin_flowview_raw_2024003', array('template_id', 'start_time', 'ex_addr'))
		));

		$pending = flowview_get_pending_partition_alters(
			array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003', 'plugin_flowview_raw_2024009'),
			'Aria'
		);

		expect(array_keys($pending))->toBe(array('plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003'))
			->and($pending['plugin_flowview_raw_2024002']['alter'])->toBe(' ENGINE=Aria ROW_FORMAT=page')
			->and($pending['plugin_flowview_raw_2024003']['alter'])->toBe('ADD INDEX end_time (end_time)')
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return $call['fn'] == 'db_fetch_assoc';
			}))->toHaveCount(3);
	});

	it('looks at nothing when there are no partitions', function () {
		expect(flowview_get_pending_partition_alters(array(), 'Aria'))->toBe(array())
			->and($GLOBALS['__test_db_calls'])->toBe(array());
	});

	it('finds the alters running on the partitions it needs', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TIME' => 120, 'INFO' => 'ALTER TABLE plugin_flowview_raw_2024001 ADD INDEX end_time (end_time)'),
			array('TIME' => 5, 'INFO' => 'alter table `plugin_flowview_raw_2024002` ENGINE=Aria'),
			array('TIME' => 300, 'INFO' => 'ALTER TABLE `flowview`.`plugin_flowview_raw_2024001` ENGINE=Aria'),
			array('TIME' => 9, 'INFO' => 'ALTER TABLE plugin_flowview_raw_2024099 ENGINE=Aria'),
			array('TIME' => 3, 'INFO' => 'ALTER TABLE plugin_flowview_arin_information MODIFY COLUMN comments VARCHAR(512)'),
			array('TIME' => 1, 'INFO' => null),
		));

		expect(flowview_get_running_partition_alters(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002')))->toBe(array(
			'plugin_flowview_raw_2024001' => 300,
			'plugin_flowview_raw_2024002' => 5,
		));
	});

	it('finds no running alters when there are none', function () {
		expect(flowview_get_running_partition_alters(array('plugin_flowview_raw_2024001')))->toBe(array());
	});

	it('converts the engine of a partition when no alter is running on it', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array());

		expect(flowview_convert_partition_engine('plugin_flowview_raw_202629214', 'Aria'))->toBeTrue();

		$sql = array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute';
		}), 'sql');

		expect(array_values($sql))->toBe(array('ALTER TABLE plugin_flowview_raw_202629214 ENGINE=Aria'));
	});

	it('does not start the same engine conversion again while one is running', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TIME' => 1605, 'INFO' => 'ALTER TABLE plugin_flowview_raw_202629214 ENGINE=Aria'),
		));

		expect(flowview_convert_partition_engine('plugin_flowview_raw_202629214', 'Aria'))->toBeFalse();

		$writes = array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return strpos($call['fn'], 'db_execute') === 0;
		});

		expect($writes)->toBeEmpty();
	});

	it('still converts a partition when the alter running is on another one', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TIME' => 12, 'INFO' => 'ALTER TABLE plugin_flowview_raw_202629213 ENGINE=Aria'),
		));

		expect(flowview_convert_partition_engine('plugin_flowview_raw_202629214', 'Aria'))->toBeTrue();
	});

	it('finds the partitions that have no NAT columns', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TABLE_NAME' => 'plugin_flowview_raw_2024001'),
			array('TABLE_NAME' => 'plugin_flowview_raw_2024003'),
		));

		expect(flowview_get_partitions_without_nat_columns(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003')))
			->toBe(array('plugin_flowview_raw_2024002'));
	});
});

describe('finding a materialization that is still running', function () {
	beforeEach(function () {
		if (!function_exists('cacti_process_still_running')) {
			function cacti_process_still_running($pid) {
				return in_array($pid, $GLOBALS['__test_live_pids'], true);
			}
		}

		$GLOBALS['__test_live_pids'] = array();
	});

	it('finds the runner and shard workers of a request by the names they register', function () {
		if (!function_exists('posix_kill')) {
			$this->markTestSkipped('The posix extension is not available.');
		}

		// A pid that cannot be a live process, so nothing is signalled.
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array());
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			array('pid' => 4999999, 'taskname' => 'db_query_991'),
			array('pid' => 4999998, 'taskname' => 'db_shard_991'),
		));

		parallel_database_query_cleanup(991);

		$lookup = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_fetch_assoc_prepared' && strpos($call['sql'], 'FROM processes') !== false;
		}));

		$removed = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && strpos($call['sql'], 'DELETE FROM processes') === 0;
		}));

		expect($lookup)->toHaveCount(1)
			->and($lookup[0]['params'])->toBe(array('flowview', 'db_query_991', 'db_shard_991'))
			->and(array_column($removed, 'params'))->toBe(array(array(4999999), array(4999998)));
	});

	it('finds the running requests from the process table', function () {
		$GLOBALS['__test_live_pids'] = array(222);

		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			// old, and the process is gone
			array('pid' => 111, 'taskname' => 'db_query_990', 'age' => 5000),
			// old, but the process is alive
			array('pid' => 222, 'taskname' => 'db_shard_991', 'age' => 5000),
			array('pid' => 223, 'taskname' => 'db_shard_991', 'age' => 5000),
			// recent, so it is trusted even though the process cannot be seen
			array('pid' => 333, 'taskname' => 'db_shard_992', 'age' => 10),
			array('pid' => 444, 'taskname' => 'db_shard_abc', 'age' => 10),
		));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			array('id' => 991, 'md5sum_tables' => 'aaaa'),
			array('id' => 992, 'md5sum_tables' => 'bbbb'),
		));

		expect(flowview_get_running_requests())->toBe(array(991 => 'aaaa', 992 => 'bbbb'));

		$lookup = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return strpos($call['sql'], 'FROM parallel_database_query') !== false;
		}));

		// it is not limited to the requests of one user
		expect($lookup[0]['sql'])->not->toContain('user_id')
			->and($lookup[0]['params'])->toBe(array(991, 992));
	});

	it('keeps a running request whose row is gone, without a cache key', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('pid' => 111, 'taskname' => 'db_query_991', 'age' => 5)));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array());

		expect(flowview_get_running_requests())->toBe(array(991 => ''));
	});

	it('ignores the rows left behind by processes that are gone', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			array('pid' => 111, 'taskname' => 'db_query_991', 'age' => 9000),
			array('pid' => 222, 'taskname' => 'db_shard_991', 'age' => 9000),
		));

		expect(flowview_get_running_requests())->toBe(array())
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['sql'], 'FROM parallel_database_query') !== false;
			}))->toBeEmpty();
	});

	it('finds nothing without any runner processes', function () {
		expect(flowview_get_running_requests())->toBe(array())
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['sql'], 'FROM parallel_database_query') !== false;
			}))->toBeEmpty();
	});

	it('looks for the runner and shard worker names in the process table', function () {
		flowview_get_running_requests();

		$lookup = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return strpos($call['sql'], 'FROM processes') !== false;
		}));

		expect($lookup)->toHaveCount(1)
			->and($lookup[0]['params'])->toBe(array('flowview', 'db\_query\_%', 'db\_shard\_%'));
	});

	it('is running while a filter is registered as being materialized', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 3)));

		expect(flowview_materialization_running())->toBeTrue()
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['sql'], 'FROM processes') !== false && ($call['params'][1] ?? '') === 'db\_query\_%';
			}))->toBeEmpty();
	});

	it('is still running after the script that started it has ended', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array());
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('pid' => 222, 'taskname' => 'db_shard_991', 'age' => 12)));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('id' => 991, 'md5sum_tables' => 'aaaa')));

		expect(flowview_materialization_running())->toBeTrue();
	});

	it('is not running when nothing is registered and no runner is registered', function () {
		expect(flowview_materialization_running())->toBeFalse();
	});
});

describe('showing the queries of a filter that are running', function () {
	$filter = array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	);

	beforeEach(function () {
		if (!function_exists('array_rekey')) {
			function array_rekey($array, $key, $value) {
				return array();
			}
		}

		if (!function_exists('number_format_i18n')) {
			function number_format_i18n($number, $decimals = 0) {
				return number_format($number, $decimals);
			}
		}
	});

	it('works out the cache keys of a filter for each NAT mode', function () use ($filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		$md5sums = flowview_get_filter_md5sums(4);

		expect($md5sums['nat'])->toHaveCount(1)
			->and($md5sums['non_nat'])->toHaveCount(1)
			->and($md5sums['nat'][0])->toMatch('/^[0-9a-f]{32}$/')
			->and($md5sums['nat'][0])->not->toBe($md5sums['non_nat'][0]);
	});

	it('has no cache keys for a filter that cannot be planned', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array());
		plugin_test_queue_db_result('db_fetch_row_prepared', array());

		expect(flowview_get_filter_md5sums(99))->toBe(array('nat' => array(), 'non_nat' => array()));
	});

	it('uses the cache keys it is given instead of planning the filter again', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('map_table' => 'plugin_flowview_raw_2024001')));

		$cached = flowview_get_cached_shard_tables(4, array('nat' => array('aaaa'), 'non_nat' => array()));

		expect($cached)->toBe(array('nat' => array('plugin_flowview_raw_2024001'), 'non_nat' => array()))
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return $call['fn'] == 'db_fetch_row_prepared';
			}))->toBeEmpty();
	});

	it('counts the running requests that are queries of a filter', function ($running, $expected) {
		$md5sums = array('nat' => array('aaaa'), 'non_nat' => array('bbbb', 'cccc'));

		expect(flowview_count_running_queries($md5sums, $running))->toBe($expected);
	})->with(array(
		'none running'        => array(array(), 0),
		'one with nat'        => array(array(991 => 'aaaa'), 1),
		'both modes'          => array(array(991 => 'aaaa', 992 => 'bbbb'), 2),
		'two of the report'   => array(array(991 => 'bbbb', 992 => 'cccc'), 2),
		'someone elses'       => array(array(991 => 'zzzz'), 0),
		'mixed'               => array(array(991 => 'aaaa', 992 => 'zzzz', 993 => 'cccc'), 2),
	));

	it('counts nothing for a filter that has no cache keys', function () {
		expect(flowview_count_running_queries(array('nat' => array(), 'non_nat' => array()), array(991 => 'aaaa')))->toBe(0);
	});

	it('counts the running queries of a filter with NAT and without', function () {
		$md5sums = array('nat' => array('aaaa'), 'non_nat' => array('bbbb'));

		expect(flowview_count_running_queries_by_mode($md5sums, array(991 => 'aaaa', 992 => 'bbbb', 993 => 'bbbb', 994 => 'zzzz')))
			->toBe(array('nat' => 1, 'non_nat' => 2));
	});

	it('finds the filter a running request belongs to, by its cache key', function () {
		$filters = array(
			1  => array('nat' => array('aaaa'), 'non_nat' => array('bbbb')),
			15 => array('nat' => array('cccc'), 'non_nat' => array('cccc', 'dddd')),
			16 => array('nat' => array(), 'non_nat' => array()),
		);

		expect(flowview_find_request_filters($filters, 'aaaa'))->toBe(array(array('id' => 1, 'nat' => true, 'non_nat' => false)))
			->and(flowview_find_request_filters($filters, 'bbbb'))->toBe(array(array('id' => 1, 'nat' => false, 'non_nat' => true)))
			->and(flowview_find_request_filters($filters, 'cccc'))->toBe(array(array('id' => 15, 'nat' => true, 'non_nat' => true)))
			->and(flowview_find_request_filters($filters, 'zzzz'))->toBe(array())
			->and(flowview_find_request_filters($filters, ''))->toBe(array());
	});

	it('says how many running queries are with NAT and without in the tip', function () {
		$pill = flowview_get_colored_running_queries(3, array('nat' => 1, 'non_nat' => 2));

		expect($pill)->toContain('Running: 3')
			->and($pill)->toContain('With NAT: 1, without NAT: 2.');
	});

	it('shows the number of running queries in a pill like a disabled device', function () {
		$pill = flowview_get_colored_running_queries(1234);

		expect($pill)->toStartWith("<span class='deviceStatus deviceDisabled'")
			->and($pill)->toContain('Running: 1,234')
			->and($pill)->toContain('title=');
	});

	it('shows no pill when nothing is running', function ($count) {
		expect(flowview_get_colored_running_queries($count))->toBe('');
	})->with(array(0, -1));
});

describe('keeping the live partition out of the cache', function () {
	it('scans the whole of a partition only when the range covers all of it', function ($start, $end, $live, $expected) {
		// the partition holds flows from 1000 to 2000
		expect(flowview_is_full_scan($start, $end, 1000, 2000, $live))->toBe($expected);
	})->with(array(
		'covers it'                  => array(500, 3000, false, true),
		'starts after its first flow' => array(1500, 3000, false, false),
		'ends before its last flow'  => array(500, 1500, false, false),
		'starts at its first flow'   => array(1000, 3000, false, false),
		'ends at its last flow'      => array(500, 2000, false, false),
		'covers it but is live'      => array(500, 3000, true, false),
	));

	it('finds the live partition as the newest one', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('table' => 'plugin_flowview_raw_202400105'),
			array('table' => 'plugin_flowview_raw_202400107'),
			array('table' => 'plugin_flowview_raw_202400106'),
			array('table' => 'plugin_flowview_raw_abc'),
		));

		expect(flowview_get_live_partition())->toBe('plugin_flowview_raw_202400107');
	});

	it('has no live partition when there are no partitions', function () {
		expect(flowview_get_live_partition())->toBe('');
	});

	it('removes what was cached for the live partition', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('table' => 'plugin_flowview_raw_2024001'),
			array('table' => 'plugin_flowview_raw_2024002'),
		));
		plugin_test_queue_db_result('db_fetch_cell_prepared', 3);

		expect(flowview_remove_live_partition_cache())->toBe(3);

		$deleted = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && strpos($call['sql'], 'DELETE FROM parallel_database_query_shard_cache') === 0;
		}));

		expect($deleted)->toHaveCount(1)
			->and($deleted[0]['params'])->toBe(array('plugin_flowview_raw_2024002'));
	});

	it('removes nothing when the live partition has nothing cached', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(array('table' => 'plugin_flowview_raw_2024001')));
		plugin_test_queue_db_result('db_fetch_cell_prepared', 0);

		expect(flowview_remove_live_partition_cache())->toBe(0)
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['fn'], 'db_execute') === 0;
			}))->toBeEmpty();
	});

	it('does nothing without any partitions', function () {
		expect(flowview_remove_live_partition_cache())->toBe(0)
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['sql'] ?? '', 'parallel_database_query_shard_cache') !== false;
			}))->toBeEmpty();
	});

	it('keeps the short lived live shard results out of the removal and the staleness checks', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(array('table' => 'plugin_flowview_raw_2024001')));
		plugin_test_queue_db_result('db_fetch_cell_prepared', 1);

		flowview_remove_live_partition_cache();
		flowview_invalidate_stale_shard_cache();
		flowview_invalidate_stale_shard_cache(array('plugin_flowview_raw_2024001'));

		$selects = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return strpos($call['sql'] ?? '', 'FROM parallel_database_query_shard_cache') !== false;
		}));

		expect($selects)->not->toBeEmpty();

		foreach ($selects as $call) {
			expect($call['sql'])->toContain("map_partition != 'live'");
		}
	});
});

describe('sharing one scan of the live partition', function () {
	beforeEach(function () {
		plugin_test_reset();
	});

	$query = array('md5sum_tables' => 'abc');
	$shard = array('map_table' => 'plugin_flowview_raw_2024001', 'map_params' => '["x"]');

	it('derives one key for the same map query, table and range', function () use ($query, $shard) {
		$key = flowview_live_shard_key($query, $shard);

		expect($key)->toHaveLength(32)
			->and(flowview_live_shard_key($query, $shard))->toBe($key)
			->and(flowview_live_shard_key(array('md5sum_tables' => 'def'), $shard))->not->toBe($key)
			->and(flowview_live_shard_key($query, array_merge($shard, array('map_params' => '["y"]'))))->not->toBe($key)
			->and(flowview_live_shard_key($query, array_merge($shard, array('map_table' => 'plugin_flowview_raw_2024002'))))->not->toBe($key)
			->and($key)->not->toBe('abc');
	});

	it('takes and releases a named lock on that key', function () {
		plugin_test_queue_db_result('db_fetch_cell_prepared', '1');
		plugin_test_queue_db_result('db_fetch_cell_prepared', '1');

		expect(flowview_live_shard_lock('k1'))->toBeTrue();

		flowview_live_shard_unlock('k1');

		$calls = array_values($GLOBALS['__test_db_calls']);

		expect($calls[0]['sql'])->toContain('GET_LOCK')
			->and($calls[0]['params'])->toBe(array('flowview_live_k1', FLOWVIEW_LIVE_SHARD_LOCK_WAIT))
			->and($calls[1]['sql'])->toContain('RELEASE_LOCK')
			->and($calls[1]['params'])->toBe(array('flowview_live_k1'));
	});

	it('reports a lock that was not obtained in time', function () {
		plugin_test_queue_db_result('db_fetch_cell_prepared', '0');

		expect(flowview_live_shard_lock('k1'))->toBeFalse();
	});

	it('reuses a recent live result', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('results' => json_encode(array(array('bytes' => 5)))));

		expect(flowview_live_shard_get('k1', 'plugin_flowview_raw_2024001'))->toBe(array(array('bytes' => 5)));

		$call = $GLOBALS['__test_db_calls'][0];

		expect($call['params'])->toBe(array('k1', 'plugin_flowview_raw_2024001', 'live', FLOWVIEW_LIVE_SHARD_TTL))
			->and($call['sql'])->toContain('date_created >= DATE_SUB(NOW(), INTERVAL ? SECOND)');
	});

	it('reuses an empty live result', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('results' => '[]'));

		expect(flowview_live_shard_get('k1', 'plugin_flowview_raw_2024001'))->toBe(array());
	});

	it('has nothing to reuse without a recent live result', function () {
		expect(flowview_live_shard_get('k1', 'plugin_flowview_raw_2024001'))->toBeNull();
	});

	it('has nothing to reuse when the stored result is unreadable', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('results' => 'not json'));

		expect(flowview_live_shard_get('k1', 'plugin_flowview_raw_2024001'))->toBeNull();
	});

	it('keeps a live result under the live marker', function () {
		flowview_live_shard_save('k1', 'plugin_flowview_raw_2024001', array(array('bytes' => 5)));

		$call = $GLOBALS['__test_db_calls'][0];

		expect($call['sql'])->toStartWith('REPLACE INTO parallel_database_query_shard_cache')
			->and($call['params'])->toBe(array('k1', 'plugin_flowview_raw_2024001', 'live', json_encode(array(array('bytes' => 5)))));
	});

	it('removes only expired live results', function () {
		flowview_live_shard_expire();

		$call = $GLOBALS['__test_db_calls'][0];

		expect($call['sql'])->toContain('map_partition = ?')
			->and($call['sql'])->toContain('date_created < DATE_SUB(NOW(), INTERVAL ? SECOND)')
			->and($call['params'])->toBe(array('live', FLOWVIEW_LIVE_SHARD_TTL));
	});

	it('expires them with the poller clean up of parallel queries', function () {
		parallel_database_query_expire();

		$sql = array_column($GLOBALS['__test_db_calls'], 'sql');

		expect(array_filter($sql, fn ($s) => strpos($s, "DELETE FROM parallel_database_query_shard_cache") === 0))->toHaveCount(1);
	});
});

describe('converting the partitions that have rotated', function () {
	beforeEach(function () {
		plugin_test_reset();

		$this->partitions = function (array $engines) {
			plugin_test_queue_db_result('db_fetch_assoc', array_map(function ($table) {
				return array('table' => $table);
			}, array_keys($engines)));
		};

		$this->alters = function () {
			return array_values(array_column(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return $call['fn'] == 'db_execute';
			}), 'sql'));
		};
	});

	it('converts every cold partition that is not on the fast engine, newest first, and never the live one', function () {
		$engines = array(
			'plugin_flowview_raw_202629214' => 'InnoDB',
			'plugin_flowview_raw_202629215' => 'Aria',
			'plugin_flowview_raw_202629216' => 'InnoDB',
			'plugin_flowview_raw_202629217' => 'InnoDB',
		);

		($this->partitions)($engines);
		plugin_test_queue_db_result('db_fetch_assoc', array());
		plugin_test_queue_db_result('db_fetch_assoc', array_map(function ($table, $engine) {
			return array('TABLE_NAME' => $table, 'ENGINE' => $engine);
		}, array_keys($engines), $engines));

		expect(flowview_convert_cold_partitions('Aria'))->toBe(array('plugin_flowview_raw_202629216', 'plugin_flowview_raw_202629214'))
			->and(($this->alters)())->toBe(array(
				'ALTER TABLE plugin_flowview_raw_202629216 ENGINE=Aria',
				'ALTER TABLE plugin_flowview_raw_202629214 ENGINE=Aria',
			));
	});

	it('catches up on partitions that were missed for more than a few hours', function () {
		$engines = array();

		foreach (range(10, 20) as $hour) {
			$engines['plugin_flowview_raw_2026292' . $hour] = 'InnoDB';
		}

		($this->partitions)($engines);
		plugin_test_queue_db_result('db_fetch_assoc', array());
		plugin_test_queue_db_result('db_fetch_assoc', array_map(function ($table, $engine) {
			return array('TABLE_NAME' => $table, 'ENGINE' => $engine);
		}, array_keys($engines), $engines));

		expect(flowview_convert_cold_partitions('Aria', 100))->toHaveCount(10);
	});

	it('converts only as many as it is allowed to in one go', function () {
		$engines = array(
			'plugin_flowview_raw_202629214' => 'InnoDB',
			'plugin_flowview_raw_202629215' => 'InnoDB',
			'plugin_flowview_raw_202629216' => 'InnoDB',
			'plugin_flowview_raw_202629217' => 'InnoDB',
			'plugin_flowview_raw_202629218' => 'InnoDB',
		);

		($this->partitions)($engines);
		plugin_test_queue_db_result('db_fetch_assoc', array());
		plugin_test_queue_db_result('db_fetch_assoc', array_map(function ($table, $engine) {
			return array('TABLE_NAME' => $table, 'ENGINE' => $engine);
		}, array_keys($engines), $engines));

		expect(flowview_convert_cold_partitions('Aria', 2))->toBe(array('plugin_flowview_raw_202629217', 'plugin_flowview_raw_202629216'));
	});

	it('does nothing while an alter is running on any cold partition', function () {
		($this->partitions)(array(
			'plugin_flowview_raw_202629214' => 'InnoDB',
			'plugin_flowview_raw_202629215' => 'InnoDB',
			'plugin_flowview_raw_202629216' => 'InnoDB',
		));
		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('TIME' => 14, 'INFO' => 'ALTER TABLE plugin_flowview_raw_202629214 ENGINE=Aria ROW_FORMAT=page'),
		));

		expect(flowview_convert_cold_partitions('Aria'))->toBe(array())
			->and(($this->alters)())->toBe(array());
	});

	it('does nothing when the cold partitions are all on the fast engine', function () {
		$engines = array(
			'plugin_flowview_raw_202629214' => 'Aria',
			'plugin_flowview_raw_202629215' => 'Aria',
			'plugin_flowview_raw_202629216' => 'InnoDB',
		);

		($this->partitions)($engines);
		plugin_test_queue_db_result('db_fetch_assoc', array());
		plugin_test_queue_db_result('db_fetch_assoc', array_map(function ($table, $engine) {
			return array('TABLE_NAME' => $table, 'ENGINE' => $engine);
		}, array_keys($engines), $engines));

		expect(flowview_convert_cold_partitions('Aria'))->toBe(array())
			->and(($this->alters)())->toBe(array());
	});

	it('does nothing when there is no cold partition', function () {
		($this->partitions)(array('plugin_flowview_raw_202629216' => 'InnoDB'));

		expect(flowview_convert_cold_partitions('Aria'))->toBe(array());
	});
});

describe('showing only the filters being materialized as running', function () {
	beforeEach(function () {
		plugin_test_reset();

		if (!function_exists('cacti_process_still_running')) {
			function cacti_process_still_running($pid) {
				return in_array($pid, $GLOBALS['__test_live_pids'], true);
			}
		}

		$GLOBALS['__test_live_pids'] = array();
	});

	it('finds the filters being materialized from their task ids', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			array('taskid' => 3),
			array('taskid' => '7'),
			array('taskid' => 3),
		));

		expect(flowview_get_materializing_filters())->toBe(array(3, 7));

		$lookup = $GLOBALS['__test_db_calls'][0];

		expect($lookup['params'])->toBe(array('flowview', 'materialize'))
			->and($lookup['sql'])->toContain('taskid > 0');
	});

	it('finds no filter being materialized when none is registered', function () {
		expect(flowview_get_materializing_filters())->toBe(array());
	});

	it('removes the registrations of a materialization whose process is gone', function () {
		$GLOBALS['__test_live_pids'] = array(222);

		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(
			array('id' => 10, 'pid' => 111),
			array('id' => 11, 'pid' => 222),
			array('id' => 12, 'pid' => '333'),
		));

		expect(flowview_purge_dead_materializations())->toBe(2);

		$deleted = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && strpos($call['sql'], 'DELETE FROM processes') === 0;
		}));

		expect(array_column($deleted, 'params'))->toBe(array(array(10), array(12)));
	});

	it('is not running for a filter that is not registered, while another is', function () {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('taskid' => 3)));

		expect(in_array(1, flowview_get_materializing_filters(), true))->toBeFalse();
	});
});

describe('keeping the report of a filter fixed and pruning what no filter caches', function () {
	$filter = array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	);

	beforeEach(function () {
		plugin_test_reset();
	});

	/* queue the lookups of one filter for each NAT mode, and return its cache keys */
	$keys_of = function (array $filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		$keys = flowview_get_filter_md5sums((int) $filter['id']);

		plugin_test_reset();

		return $keys;
	};

	it('names the report of a filter the way the report selector does', function ($filter, $expected) {
		expect(flowview_filter_report_key($filter))->toBe($expected);
	})->with(array(
		'statistical' => array(array('statistics' => 10, 'printed' => 0), 's10'),
		'printed'     => array(array('statistics' => '0', 'printed' => '2'), 'p2'),
		'none'        => array(array('statistics' => 0, 'printed' => 0), ''),
		'missing'     => array(array(), ''),
	));

	it('does not let the request change the report of a filter', function () use ($filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		$plain = run_flow_query(false, 4, 1704067200, 1704153600, true, true);

		$_REQUEST['report'] = 's11';

		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		expect(run_flow_query(false, 4, 1704067200, 1704153600, true, true))->toBe($plain);
	});

	it('still lets the request pick the report of a filter that has none', function () use ($filter) {
		$none = array_merge($filter, array('statistics' => 0, 'printed' => 0));

		plugin_test_queue_db_result('db_fetch_row_prepared', $none);

		expect(run_flow_query(false, 4, 1704067200, 1704153600, true, true))->toBeFalse();

		$_REQUEST['report'] = 's10';

		plugin_test_queue_db_result('db_fetch_row_prepared', $none);

		expect(run_flow_query(false, 4, 1704067200, 1704153600, true, true))->toBeArray()->toHaveCount(1);
	});

	it('knows the cache keys of every filter', function () use ($filter, $keys_of) {
		$keys = $keys_of($filter);

		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('id' => 4, 'statistics' => 10, 'printed' => 0),
			array('id' => 5, 'statistics' => 10, 'printed' => 0),
		));

		foreach (range(1, 4) as $unused) {
			plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		}

		expect(flowview_get_filter_shard_keys())->toBe(array(
			$keys['nat'][0] => true,
			$keys['non_nat'][0] => true,
		));
	});

	it('does not know the cache keys when a filter that has a report cannot be planned', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 10, 'printed' => 0)));

		expect(flowview_get_filter_shard_keys())->toBeNull();
	});

	it('knows the cache keys when only a filter that has no report cannot be planned', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 0, 'printed' => 0)));

		expect(flowview_get_filter_shard_keys())->toBe(array());
	});

	it('tells the cached shards of the filters from the others', function () use ($filter, $keys_of) {
		$keys = $keys_of($filter);

		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('md5sum' => $keys['nat'][0], 'shards' => 40),
			array('md5sum' => $keys['non_nat'][0], 'shards' => 38),
			array('md5sum' => 'old1', 'shards' => 12),
			array('md5sum' => 'old2', 'shards' => 3),
		));
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 10, 'printed' => 0)));
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		expect(flowview_get_shard_cache_summary())->toBe(array(
			'matching' => array('shards' => 78),
			'other'    => array('shards' => 15, 'md5sums' => array('old1', 'old2')),
		));

		$lookup = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return strpos($call['sql'], 'FROM parallel_database_query_shard_cache') !== false;
		}));

		expect($lookup[0]['sql'])->toContain("map_partition != 'live'");
	});

	it('removes only the cached shards that match no filter', function () use ($filter, $keys_of) {
		$keys = $keys_of($filter);

		plugin_test_queue_db_result('db_fetch_assoc', array(
			array('md5sum' => $keys['nat'][0], 'shards' => 40),
			array('md5sum' => 'old1', 'shards' => 12),
			array('md5sum' => 'old2', 'shards' => 3),
		));
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 10, 'printed' => 0)));
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		expect(flowview_prune_shard_cache())->toBe(15);

		$deleted = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared';
		}));

		expect($deleted)->toHaveCount(2)
			->and($deleted[0]['params'])->toBe(array('old1'))
			->and($deleted[1]['params'])->toBe(array('old2'))
			->and($deleted[0]['sql'])->toContain('DELETE FROM parallel_database_query_shard_cache')
			->and($deleted[0]['sql'])->toContain("map_partition != 'live'");
	});

	it('removes nothing when there is nothing to prune', function () use ($filter, $keys_of) {
		$keys = $keys_of($filter);

		plugin_test_queue_db_result('db_fetch_assoc', array(array('md5sum' => $keys['nat'][0], 'shards' => 40)));
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 10, 'printed' => 0)));
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);

		expect(flowview_prune_shard_cache())->toBe(0)
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['fn'], 'db_execute') === 0;
			}))->toBeEmpty();
	});

	it('removes nothing when the cache keys of the filters are not known', function () {
		plugin_test_queue_db_result('db_fetch_assoc', array(array('md5sum' => 'old1', 'shards' => 12)));
		plugin_test_queue_db_result('db_fetch_assoc', array(array('id' => 4, 'statistics' => 10, 'printed' => 0)));

		expect(flowview_prune_shard_cache())->toBeNull()
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['fn'], 'db_execute') === 0;
			}))->toBeEmpty();
	});
});

describe('stopping the processes of a parallel query', function () {
	beforeEach(function () {
		plugin_test_reset();
		$GLOBALS['flowview_requests_in_flight'] = array();
	});

	it('names signals without needing the pcntl constants', function () {
		expect(flowview_signal_number('SIGTERM'))->toBe(15)
			->and(flowview_signal_number('SIGKILL'))->toBe(9)
			->and(flowview_signal_number('SIGINT'))->toBe(2)
			->and(flowview_signal_number('SIGNOTREAL'))->toBe(15);
	});

	it('does not signal something that is not a process', function () {
		if (!function_exists('posix_kill')) {
			$this->markTestSkipped('The posix extension is not available.');
		}

		expect(flowview_signal_process(0))->toBeFalse()
			->and(flowview_signal_process(-5))->toBeFalse()
			->and(flowview_signal_process(4999999))->toBeFalse();
	});

	it('does not use a signal constant that the web server does not have in the clean up', function () {
		$source = plugin_test_read_source('includes/functions.php');

		expect($source)->not->toMatch('/posix_kill\([^)]*\bSIG[A-Z]+\b/');
	});

	it('remembers a request until it is cleaned up', function () {
		flowview_track_request(991);
		flowview_track_request(992);

		parallel_database_query_cleanup(991);

		expect($GLOBALS['flowview_requests_in_flight'])->toBe(array(992 => 992));
	});

	it('cancels the requests that are not finished, removing their rows', function () {
		flowview_track_request(991);
		flowview_track_request(992);

		expect(flowview_cancel_requests_in_flight())->toBe(array(991, 992))
			->and($GLOBALS['flowview_requests_in_flight'])->toBe(array());

		$removed = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && preg_match('/^DELETE FROM parallel_database_query\s+WHERE id = \?/', $call['sql']) === 1;
		}));

		expect(array_column($removed, 'params'))->toBe(array(array(991), array(992)));
	});

	it('cancels nothing when no request is in flight', function () {
		expect(flowview_cancel_requests_in_flight())->toBe(array());
	});

	it('removes a finished request so the same query is run again', function () {
		plugin_test_queue_db_result('db_fetch_cell_prepared', 'complete');

		expect(flowview_discard_completed_request(991))->toBeTrue();

		$removed = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && preg_match('/^DELETE FROM parallel_database_query\s+WHERE id = \?/', $call['sql']) === 1;
		}));

		expect(array_column($removed, 'params'))->toBe(array(array(991)));
	});

	it('leaves a request that is still being run, as another process is using it', function ($status) {
		plugin_test_queue_db_result('db_fetch_cell_prepared', $status);

		expect(flowview_discard_completed_request(991))->toBeFalse()
			->and(array_filter($GLOBALS['__test_db_calls'], function ($call) {
				return strpos($call['fn'], 'db_execute') === 0;
			}))->toBeEmpty();
	})->with(array('pending', 'scheduled', 'running', ''));

	it('prunes from a button with a confirmation dialog and a post, not from the actions', function () {
		$source = plugin_test_read_source('flowview_filters.php');

		expect($source)->not->toContain("6 => __('Prune Cached Shards'")
			->and($source)->not->toContain("drp_action') == '6'")
			->and($source)->not->toContain('function confirm_prune_shards')
			->and($source)->toContain("id='prune_dialog'")
			->and($source)->toContain("\$('#prune_dialog').dialog(")
			->and($source)->toMatch("/\\\$\('#prune_dialog'\)\.dialog\(\{\s*modal: false,/")
			->and($source)->toContain("\$.post('flowview_filters.php', data)")
			->and($source)->toMatch("/function prune_shards\(\): void \{.*?REQUEST_METHOD'\] \?\? 'GET'\) !== 'POST'/s")
			->and($source)->toMatch("/id='clear'[^\n]*\n\s*<button type='button' id='prune'/");
	});

	it('still opens the Filter editor from the Filters page', function () {
		expect(plugin_test_read_source('flowview_filters.php'))->toMatch("/case 'edit':\s*\n\s*if \(!isset_request_var\('embed'\)\)/");
	});

	it('waits two seconds after starting a materialization before the page is shown again', function () {
		expect(plugin_test_read_source('flowview_filters.php'))->toMatch('/\$started = flowview_launch_materialize\(\$selected_items\);.*?if \(cacti_sizeof\(\$started\)\) \{\s*sleep\(2\);/s');
	});

	it('runs the shards of a materialization instead of reusing an earlier request', function () {
		expect(plugin_test_read_source('cli/materialize_queries.php'))->toContain("\$GLOBALS['flowview_fresh_requests'] = true")
			->and(plugin_test_read_source('includes/functions.php'))->toContain("!empty(\$GLOBALS['flowview_fresh_requests']) && flowview_discard_completed_request");
	});
});

describe('materializing a part of the partitions at a time', function () {
	$filter = array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => 0, 'ex_addr' => '', 'template_id' => -1,
		'statistics' => 10, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 20, 'cutoffoctets' => 0,
		'includeif' => 0, 'usenat' => '', 'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
	);

	beforeEach(function () {
		plugin_test_reset();

		if (!function_exists('cacti_process_still_running')) {
			function cacti_process_still_running($pid) {
				return in_array($pid, $GLOBALS['__test_live_pids'], true);
			}
		}

		$GLOBALS['__test_live_pids'] = array();
	});

	it('finds the partitions a filter has not cached with NAT', function () use ($filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('map_table' => 'plugin_flowview_raw_2024001'), array('map_table' => 'plugin_flowview_raw_2024003')));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('map_table' => 'plugin_flowview_raw_2024002')));

		$cold = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003', 'plugin_flowview_raw_2024004');

		expect(flowview_get_uncached_partitions(4, 'on', $cold))->toBe(array('plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024004'));
	});

	it('finds the partitions a filter has not cached without NAT', function () use ($filter) {
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('map_table' => 'plugin_flowview_raw_2024001'), array('map_table' => 'plugin_flowview_raw_2024003')));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('map_table' => 'plugin_flowview_raw_2024002')));

		$cold = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002', 'plugin_flowview_raw_2024003');

		expect(flowview_get_uncached_partitions(4, 'off', $cold))->toBe(array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024003'));
	});

	it('runs a filter for everything when its cache keys are not known', function () {
		$cold = array('plugin_flowview_raw_2024001', 'plugin_flowview_raw_2024002');

		expect(flowview_get_uncached_partitions(99, 'on', $cold))->toBe($cold);
	});

	it('sizes the batches by the threads, and halves one that did not finish', function ($current, $timed_out, $threads, $expected) {
		expect(flowview_materialize_batch_size($current, $timed_out, $threads))->toBe($expected);
	})->with(array(
		'first with the default threads' => array(0, false, 4, 100),
		'first with few threads'         => array(0, false, 1, 40),
		'first with many threads'        => array(0, false, 20, 500),
		'that finished'                  => array(100, false, 4, 100),
		'that did not finish'            => array(100, true, 4, 50),
		'that did not finish again'      => array(9, true, 4, 4),
		'at the least'                   => array(4, true, 4, 4),
	));

	it('does not remove a request that is finished, or that does not exist', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'complete', 'created' => date('Y-m-d H:i:s', time() - 9999)));

		expect(flowview_discard_abandoned_request(991))->toBeFalse()
			->and(flowview_discard_abandoned_request(992))->toBeFalse();
	});

	it('does not remove a request that was only just made, as its runner has not registered yet', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'scheduled', 'created' => date('Y-m-d H:i:s', time() - 5)));

		expect(flowview_discard_abandoned_request(991))->toBeFalse();
	});

	it('does not remove a request that a runner is working on', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'scheduled', 'created' => date('Y-m-d H:i:s', time() - 600)));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('pid' => 222, 'taskname' => 'db_query_991', 'age' => 5)));
		plugin_test_queue_db_result('db_fetch_assoc_prepared', array(array('id' => 991, 'md5sum_tables' => 'aaaa')));

		expect(flowview_discard_abandoned_request(991))->toBeFalse();
	});

	it('removes a request that was left behind by a query that timed out', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('status' => 'scheduled', 'created' => date('Y-m-d H:i:s', time() - 600)));

		expect(flowview_discard_abandoned_request(991))->toBeTrue();

		$removed = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && preg_match('/^DELETE FROM parallel_database_query\s+WHERE id = \?/', $call['sql']) === 1;
		}));

		expect(array_column($removed, 'params'))->toBe(array(array(991)));
	});

	it('runs a part of the partitions at a time in the materializer', function () {
		$source = plugin_test_read_source('cli/materialize_queries.php');

		expect($source)->toContain('function materialize_query_mode(')
			->and($source)->toContain('flowview_materialize_batch_size(')
			->and($source)->toContain('flowview_get_uncached_partitions(');
	});
});
