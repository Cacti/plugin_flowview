<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'backfill-test';
	foreach ([1, 'REPEATABLE-READ', 1, '7.5'] as $value) {
		plugin_test_queue_db_result('db_fetch_cell', $value);
	}
});

it('backfills hourly dates only when the retained counters match', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 0, 'listener_id' => 3, 'period' => 1800000000, 'phase' => 0]]);
	foreach (range(1, 3) as $i) {
		plugin_test_queue_db_result('db_fetch_row', ['Collation' => 'utf8mb4_unicode_ci']);
	}
	expect(fv_nbar_seen_backfill())->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$sql = implode(' ', array_column($calls, 'sql'));
	expect($sql)->toContain('s.bytes=r.bytes AND s.packets=r.packets AND s.flows=r.flows', 'SET done=1', 'max_statement_time=7.5', 'REPEATABLE READ');
	$updates = array_filter($calls, fn ($call) => str_starts_with($call['sql'], 'UPDATE plugin_flowview_nbar_') && $call['fn'] === 'db_execute');
	expect($updates)->toHaveCount(3);
	foreach ($updates as $call) {
		expect($call['sql'])->toContain('minute>=1800000000 AND minute<1800003600');
		expect($call['conn'])->toBe('backfill-test');
	}
});

it('resumes daily connection dates from the saved application cursor', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 1, 'listener_id' => 2, 'period' => 1800000000, 'phase' => 2, 'app_cursor' => 'previous']]);
	plugin_test_queue_db_result('db_fetch_cell_prepared', 1800086400);
	plugin_test_queue_db_result('db_fetch_row', ['Collation' => 'utf8mb4_unicode_ci']);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['app_id' => "app'quoted"], ['app_id' => 'second']]);
	expect(fv_nbar_seen_backfill())->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$cursor = array_values(array_filter($calls, fn ($call) => str_contains($call['sql'], 'SET app_cursor=?')));
	expect($cursor)->toHaveCount(2);
	expect($cursor[0]['params'])->toBe(["app'quoted", 1, 2, 1800000000]);
	$sql = implode(' ', array_column($calls, 'sql'));
	expect($sql)->toContain('UPDATE plugin_flowview_nbar_daily_connections', 'CONVERT(0x' . bin2hex("app'quoted"), 'SET phase=?', 'SET done=1');
	expect($sql)->not->toContain("app'quoted", 'UPDATE plugin_flowview_nbar_daily_apps');
});

it('leaves failed date jobs pending and restores database session settings', function ($failure) {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 0, 'listener_id' => 1, 'period' => 1800000000, 'phase' => 0]]);
	plugin_test_queue_db_result('db_fetch_row', ['Collation' => $failure === 'collation' ? 'unsafe;SQL' : 'utf8mb4_unicode_ci']);
	if ($failure === 'write') {
		foreach ([true, true, false] as $value) {
			plugin_test_queue_db_result('db_execute', $value);
		}
	}
	expect(fv_nbar_seen_backfill())->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('SET done=1', 'unsafe;SQL');
	expect($sql)->toContain('max_statement_time=7.5', 'REPEATABLE READ', 'RELEASE_LOCK');
})->with(['collation', 'write']);

it('does not begin a job when the backfill budget is exhausted', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 0, 'listener_id' => 1, 'period' => 1800000000, 'phase' => 0]]);
	expect(fv_nbar_seen_backfill(0))->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('SHOW FULL COLUMNS', 'SET done=1');
	expect($sql)->toContain('RELEASE_LOCK');
});

it('keeps a completed date job pending when its checkpoint cannot be saved', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 0, 'listener_id' => 1, 'period' => 1800000000, 'phase' => 0]]);
	foreach (range(1, 3) as $i) {
		plugin_test_queue_db_result('db_fetch_row', ['Collation' => 'utf8mb4_unicode_ci']);
	}
	plugin_test_queue_db_result('db_execute_prepared', false);
	expect(fv_nbar_seen_backfill())->toBe(0);
	expect(implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql')))->toContain('RELEASE_LOCK', 'SET done=1');
});

it('leaves a job pending if its application work exhausts the budget', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [['kind' => 0, 'listener_id' => 1, 'period' => 1800000000, 'phase' => 0]]);
	plugin_test_queue_db_result('db_fetch_row', static function () { usleep(1100000); return ['Collation' => 'utf8mb4_unicode_ci']; });
	expect(fv_nbar_seen_backfill(1))->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('SET done=1');
	expect($sql)->toContain('RELEASE_LOCK');
});
