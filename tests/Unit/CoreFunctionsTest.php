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
