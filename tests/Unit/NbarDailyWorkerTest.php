<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'daily-worker-test';
});

it('appends the next hour to a valid daily checkpoint without resetting totals', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['timezone' => date_default_timezone_get(), 'covered_to' => 1800003600, 'revision' => '3']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 1, 'ready' => 1, 'rev' => '3']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 2, 'ready' => 2, 'rev' => '4']);
	plugin_test_queue_db_result('db_fetch_cell', '6.25');
	expect(fv_nbar_daily_build(2, 1800000000, 1800007200))->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$sql   = implode(' ', array_column($calls, 'sql'));
	expect($sql)->not->toContain('DELETE FROM');
	expect($sql)->toContain('minute>=1800003600 AND d.minute<1800007200', 'bytes=bytes+VALUES(bytes)', 'SET SESSION max_statement_time=6.25');
	$checkpoint = array_values(array_filter($calls, fn ($c) => str_contains($c['sql'], 'INSERT INTO plugin_flowview_nbar_days')));
	expect(array_slice($checkpoint[0]['params'], 0, 4))->toBe([2, 1800000000, 1800007200, '4']);
});

it('does not rebuild a completed daily checkpoint', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['timezone' => date_default_timezone_get(), 'covered_to' => 1800003600, 'revision' => '3']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 1, 'ready' => 1, 'rev' => '3']);
	expect(fv_nbar_daily_build(2, 1800000000, 1800003600))->toBe(0);
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('ROLLBACK');
	expect(implode(' ', $sql))->not->toContain('INSERT INTO', 'DELETE FROM');
});

it('does not publish a day when any daily mutation fails', function ($stage) {
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 1, 'ready' => 1, 'rev' => '7']);
	plugin_test_queue_db_result('db_fetch_cell', '4.5');

	if ($stage === 'reset') {
		plugin_test_queue_db_result('db_execute_prepared', false);
	} elseif ($stage === 'aggregate') {
		foreach ([true, true, false] as $value) {
			plugin_test_queue_db_result('db_execute', $value);
		}
	} elseif ($stage === 'checkpoint') {
		foreach ([true, true, true, false] as $value) {
			plugin_test_queue_db_result('db_execute_prepared', $value);
		}
	} else {
		foreach ([true, true, true, true, true, false] as $value) {
			plugin_test_queue_db_result('db_execute', $value);
		}
	}
	expect(fv_nbar_daily_build(1, 1800000000, 1800003600))->toBe(0);
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql)->toContain('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=4.5');
})->with(['reset', 'aggregate', 'checkpoint', 'commit']);

it('skips daily maintenance when another worker owns the lock', function () {
	plugin_test_queue_db_result('db_fetch_cell', 0);
	expect(fv_nbar_daily_refresh())->toBe(0);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('plans retained calendar days and seeds only empty hours before the first record', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	$first = strtotime('yesterday') + 3 * 3600;
	plugin_test_queue_db_result('db_fetch_assoc', [['listener_id' => 2, 'first_hour' => $first], ['listener_id' => 3, 'first_hour' => $first]]);
	plugin_test_queue_db_result('db_fetch_cell_prepared', $first);
	plugin_test_queue_db_result('db_fetch_cell_prepared', false);
	expect(fv_nbar_daily_refresh())->toBe(0);
	$calls = $GLOBALS['__test_db_calls'];
	$seeds = array_values(array_filter($calls, fn ($c) => str_contains($c['sql'], 'INSERT IGNORE INTO plugin_flowview_nbar_hours')));
	expect($seeds)->toHaveCount(3);
	expect($seeds[0]['params'])->toBe([2, strtotime('yesterday')]);
	$sql = implode(' ', array_column($calls, 'sql'));
	expect($sql)->toContain('START TRANSACTION WITH CONSISTENT SNAPSHOT', 'RELEASE_LOCK');
	expect($sql)->not->toContain('INSERT INTO plugin_flowview_nbar_days');
});

it('seeds and publishes closed hours before handing off to the daily worker', function () {
	$hour = (int) floor((time() - 7200) / 3600) * 3600;
	plugin_test_queue_db_result('db_fetch_row', ['acquired' => 1]);
	plugin_test_queue_db_result('db_fetch_assoc', [['id' => 1], ['id' => 2]]);
	plugin_test_queue_db_result('db_fetch_assoc', [['listener_id' => 1, 'hour' => $hour, 'revision' => 4]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => $hour]);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 4]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 4]);
	expect(fv_nbar_summary_refresh())->toBe(1);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('INSERT IGNORE INTO plugin_flowview_nbar_hours', 'SET built=?,connection_built=?', 'RELEASE_LOCK', 'flowview_nbar_daily:');
});

it('releases the hourly worker lock when checkpoint seeding fails', function () {
	plugin_test_queue_db_result('db_fetch_row', ['acquired' => 1]);
	plugin_test_queue_db_result('db_fetch_assoc', [['id' => 1]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => time() - 7200]);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_summary_refresh())->toBe(0);
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toContain('RELEASE_LOCK');
	expect(implode(' ', $sql))->not->toContain('START TRANSACTION');
});

it('stops the hourly work queue when its time budget expires', function () {
	plugin_test_queue_db_result('db_fetch_row', ['acquired' => 1]);
	plugin_test_queue_db_result('db_fetch_assoc', static function () {
		usleep(1100000);

		return [];
	});
	plugin_test_queue_db_result('db_fetch_assoc', [['listener_id' => 1, 'hour' => time() - 7200, 'revision' => 4]]);
	expect(fv_nbar_summary_refresh(1))->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('RELEASE_LOCK');
	expect($sql)->not->toContain('START TRANSACTION');
});

it('stops daily work after the worker deadline while releasing its lock', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_assoc', static function () {
		usleep(1100000);

		return [['listener_id' => 1, 'first_hour' => time() - 7200]];
	});
	plugin_test_queue_db_result('db_fetch_cell_prepared', time() - 7200);
	expect(fv_nbar_daily_refresh(1))->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('RELEASE_LOCK');
	expect($sql)->not->toContain('START TRANSACTION');
});

it('does not materialize a calendar day that begins before the retention cutoff', function () {
	$cutoff = time() - fv_nbar_retention_days() * 86400;
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_assoc', [['listener_id' => 2, 'first_hour' => $cutoff - 86400]]);
	plugin_test_queue_db_result('db_fetch_cell_prepared', $cutoff - 86400);
	expect(fv_nbar_daily_refresh())->toBe(0);
	$lookups = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => str_starts_with($call['sql'], 'SELECT * FROM plugin_flowview_nbar_days')));
	expect($lookups)->not->toBe([]);

	foreach ($lookups as $lookup) {
		expect($lookup['params'][1])->toBeGreaterThanOrEqual($cutoff);
	}
});
