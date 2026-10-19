<?php

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'filter-database';
});

afterEach(function () {
	$GLOBALS['flowview_cnn'] = false;
});

it('displays enabled, disabled and unavailable states without missing-key warnings', function () {
	expect(flowview_filter_status(['enabled' => 'on']))->toBe(['class' => 'deviceUp', 'label' => 'Enabled'])
		->and(flowview_filter_status(['enabled' => '']))->toBe(['class' => 'deviceDisabled', 'label' => 'Disabled'])
		->and(flowview_filter_status([]))->toBe(['class' => 'deviceDisabled', 'label' => 'Unavailable']);
});

it('does not alter a missing filter table', function () {
	expect(flowview_filter_status_upgrade())->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute');
});

it('leaves an existing enabled column intact', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	expect(flowview_filter_status_upgrade())->toBeTrue();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute');
});

it('repairs legacy filters as enabled through the configured database', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	expect(flowview_filter_status_upgrade())->toBeTrue();
	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_execute'));
	expect($writes)->toHaveCount(1)
		->and($writes[0]['sql'])->toBe("ALTER TABLE plugin_flowview_queries ADD COLUMN enabled char(2) NOT NULL DEFAULT 'on' AFTER name")
		->and($writes[0]['conn'])->toBe('filter-database');
});

it('rechecks concurrent upgrades and reports actual failures', function ($exists) {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', []);
	plugin_test_queue_db_result('db_fetch_row', $exists ? ['Field' => 'enabled'] : []);
	plugin_test_queue_db_result('db_execute', false);
	expect(flowview_filter_status_upgrade())->toBe($exists);
})->with([true, false]);

it('does not run a schedule when the state schema is unavailable', function () {
	expect(plugin_flowview_run_schedule(1, 1))->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_fetch_row_prepared');
});

it('does not prepare scheduled reports for missing or disabled filters', function ($query) {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', $query);
	expect(plugin_flowview_run_schedule(1, 1))->toBeTrue();
	$reads = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_fetch_row_prepared');
	expect($reads)->toHaveCount(2);
})->with([[[]], [['enabled' => '']], [['name' => 'legacy']]]);

it('continues preparing an enabled scheduled filter', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['enabled' => 'on', 'timespan' => 1]);
	plugin_test_queue_db_result('get_timespan', new RuntimeException('enabled report reached time selection'));
	expect(fn () => plugin_flowview_run_schedule(1, 1))->toThrow(RuntimeException::class, 'enabled report reached time selection');
});

it('preserves an existing disabled schedule filter with an explicit label', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 2, 'name' => 'Active', 'enabled' => 'on'], ['id' => 7, 'name' => 'Saved', 'enabled' => '']]);
	expect(flowview_schedule_filters(3))->toBe([2 => 'Active', 7 => 'Saved (Disabled)']);
	$call = $GLOBALS['__test_db_calls'][0];
	expect($call['params'])->toBe([3])->and($call['sql'])->toContain('SELECT query_id');
});
it('handles unavailable schedule choices without replacing the association', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(flowview_schedule_filters(3))->toBe([]);
});

it('resets a queued time-selection exception between tests', function () {
	plugin_test_queue_db_result('get_timespan', new RuntimeException('fixture only'));
	plugin_test_reset();
	$span = [];
	get_timespan($span, 2000, 1, 0);
	expect($span)->toBe(['begin_now' => 200, 'end_now' => 2000]);
});

it('returns success after preparing an enabled schedule', function () {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 3, 'title' => 'Fixture', 'format_file' => 'default']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['enabled' => 'on', 'timespan' => 1, 'name' => 'Fixture']);
	plugin_test_queue_db_result('get_timespan', ['begin_now' => 0, 'end_now' => 2000]);
	set_request_var('timespan', 'custom');
	set_request_var('query', 3);
	set_request_var('date1', date('Y-m-d H:i:s', 100));
	set_request_var('date2', date('Y-m-d H:i:s', 2000));
	$key = get_flowview_session_key(3, 100, 2000);
	$_SESSION['sess_flowdata'][$key] = ['timeout' => time() + 60, 'data' => ['table' => 'Fixture', 'data' => [['bytes' => 42]]]];
	expect(plugin_flowview_run_schedule(7, 51))->toBeTrue();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->toContain('reports_load_format_file');
});
