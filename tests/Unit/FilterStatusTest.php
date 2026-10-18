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
	plugin_test_queue_db_result('db_column_exists', true);
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
	plugin_test_queue_db_result('db_column_exists', false);
	plugin_test_queue_db_result('db_column_exists', $exists);
	plugin_test_queue_db_result('db_execute', false);
	expect(flowview_filter_status_upgrade())->toBe($exists);
})->with([true, false]);

it('does not run a schedule when the state schema is unavailable', function () {
	plugin_flowview_run_schedule(1, 1);
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_fetch_row_prepared');
});

it('does not prepare scheduled reports for missing or disabled filters', function ($query) {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_column_exists', true);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', $query);
	plugin_flowview_run_schedule(1, 1);
	$reads = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_fetch_row_prepared');
	expect($reads)->toHaveCount(2);
})->with([[[]], [['enabled' => '']], [['name' => 'legacy']]]);

// Stop at time selection so the positive path cannot send a report in unit tests.
if (!function_exists('read_user_setting')) {
	function read_user_setting($name) { return false; }
}
if (!function_exists('get_timespan')) {
	function get_timespan(&$span, $now, $timespan, $weekday) {
		throw new RuntimeException('enabled report reached time selection');
	}
}

it('continues preparing an enabled scheduled filter', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_column_exists', true);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['enabled' => 'on', 'timespan' => 1]);
	expect(fn () => plugin_flowview_run_schedule(1, 1))->toThrow(RuntimeException::class, 'enabled report reached time selection');
});
