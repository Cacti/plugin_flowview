<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_connections.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_chart.php');
	$_REQUEST = ['report' => 'nbar'];
	flowview_test_load(dirname(__DIR__, 2) . '/flowview_sections.php');
});

beforeEach(function () {
	plugin_test_reset();
});

it('compares identical connection tuples consistently', function () {
	$row = ['bytes' => '9007199254740993', '_key' => ['0d000001', 1, 'C0000201', 'C6336401', 1000, 443, 6]];
	expect(fv_nbar_connection_compare($row, $row))->toBe(0);
	expect(fv_nbar_connection_compare($row, array_replace($row, ['bytes' => '9007199254740994'])))->toBeGreaterThan(0);
});

it('normalizes saved navigation fields without restoring NBAR into Flow Reports', function () {
	$GLOBALS['__test_user_settings']['flowview_section_flows'] = json_encode(['report' => 'nbar', 'query' => ['bad'], 'mode' => 'apps']);
	$params = fv_section_params('flows');
	expect($params)->not->toHaveKey('report')->not->toHaveKey('query');
	expect($params['section'])->toBe('flows');
});

it('does not draw a chart slice for a zero-byte application', function () {
	$GLOBALS['lid'] = 1;
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['name' => 'Empty app', 'bytes' => 0]]);
	ob_start();
	fv_nbar_chart('d.listener_id=?', [1, time() - 1200, time()], 'all');
	$html = ob_get_clean();
	expect($html)->not->toContain('Empty app');
});

it('stops hourly schema setup on creation or column-upgrade failure', function ($stage) {
	if ($stage === 'column') {
		foreach (['hours', 'apps', 'pairs', 'connections'] as $kind) {
			plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_' . $kind);
		}
		plugin_test_queue_db_result('db_add_column', false);
	}
	expect(fv_nbar_summary_schema())->toBeFalse();
})->with(['create', 'column']);

it('stops daily schema setup on table or index failures', function ($stage) {
	if ($stage === 'index') {
		foreach (['apps', 'pairs', 'connections'] as $kind) {
			plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_daily_' . $kind);
		}
		foreach ([true, true, true, false] as $result) {
			plugin_test_queue_db_result('db_execute', $result);
		}
	}
	expect(fv_nbar_daily_schema())->toBeFalse();
})->with(['create', 'index']);

it('does not complete observation schema setup after failed checkpoint writes', function ($stage) {
	foreach (range(1, 12) as $unused) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'present']);
	}
	if ($stage === 'column') {
		plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_seen_upgrade');
		plugin_test_queue_db_result('db_add_column', false);
	} elseif ($stage === 'seed') {
		plugin_test_queue_db_result('db_fetch_cell', '');
		plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_seen_upgrade');
		plugin_test_queue_db_result('db_execute', true);
		plugin_test_queue_db_result('db_execute', false);
	}
	expect(fv_nbar_seen_schema())->toBeFalse();
})->with(['create', 'seed', 'column']);

it('does not build summaries when transaction creation or commit fails', function ($stage) {
	if ($stage === 'start') {
		plugin_test_queue_db_result('db_execute', false);
		expect(fv_nbar_summary_build(1, 1800000000))->toBeFalse();
		plugin_test_queue_db_result('db_execute', false);
		expect(fv_nbar_daily_build(1, 1800000000, 1800003600))->toBe(0);
	} else {
		plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 4]);
		plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 4]);
		foreach ([true, true, true, false] as $result) {
			plugin_test_queue_db_result('db_execute', $result);
		}
		expect(fv_nbar_summary_build(1, 1800000000))->toBeFalse();
		expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('ROLLBACK');
	}
})->with(['start', 'commit']);

it('does not turn failed name or deep-page queries into an empty report', function ($stage) {
	foreach (range(1, 2) as $unused) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	}
	if ($stage === 'page') {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(fn () => fv_nbar_connections_direct([1], 1800000000, 1800001800, '1=1', [], $stage === 'page' ? 500 : 1))->toThrow(RuntimeException::class);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('ROLLBACK');
})->with(['names', 'page']);

it('rejects a nonpositive capture identifier inside the snapshot planner', function () {
	expect(fn () => fv_nbar_connections_direct([0], 1800000000, 1800001800, '1=1', [], 1))->toThrow(InvalidArgumentException::class);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toContain('ROLLBACK');
});
