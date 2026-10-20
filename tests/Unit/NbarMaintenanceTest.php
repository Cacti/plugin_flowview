<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_connections.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'maintenance-test-connection';
});

it('publishes raw data and revision invalidation in one transaction', function () {
	expect(fv_nbar_summary_store([['INSERT fixture VALUES (?)', [42]]], 3, [1800000000 => true, 1800003600 => true]))->toBeTrue();
	$calls = $GLOBALS['__test_db_calls'];
	expect(array_column($calls, 'sql')[0])->toBe('START TRANSACTION');
	expect($calls[1]['params'])->toBe([42]);
	expect($calls[2]['sql'])->toContain('(3,1800000000,1,-1)', '(3,1800003600,1,-1)', 'revision=revision+1');
	expect(end($calls)['sql'])->toBe('COMMIT');

	foreach ($calls as $call) {
		expect($call['conn'])->toBe('maintenance-test-connection');
	}
});

it('does not publish revisions when transaction creation fails', function () {
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_summary_store([['INSERT fixture VALUES (?)', [42]]], 3, [1800000000 => true]))->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('rolls back when invalidation fails after the raw write', function () {
	plugin_test_queue_db_result('db_execute', true);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_summary_store([['INSERT fixture VALUES (?)', [42]]], 3, [1800000000 => true]))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('ROLLBACK');
	expect($sql)->not->toContain('COMMIT');
});

it('writes every slice in one transaction and rolls back when any slice fails', function () {
	$statements = [['INSERT slice_a VALUES (?)', [1]], ['INSERT slice_b VALUES (?)', [2]]];
	expect(fv_nbar_summary_store($statements, 3, [1800000000 => true]))->toBeTrue();
	$prepared = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
	expect(array_column($prepared, 'sql'))->toBe(['INSERT slice_a VALUES (?)', 'INSERT slice_b VALUES (?)']);

	plugin_test_reset();
	plugin_test_queue_db_result('db_execute_prepared', true);
	plugin_test_queue_db_result('db_execute_prepared', false);
	expect(fv_nbar_summary_store($statements, 3, [1800000000 => true]))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('ROLLBACK');
	expect(implode(' ', $sql))->not->toContain('revision=revision+1');
});

it('rebuilds all hourly tables and publishes only a matching revision', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeTrue();
	$calls = $GLOBALS['__test_db_calls'];
	$sql   = array_column($calls, 'sql');
	expect($sql[count($sql) - 2])->toBe('COMMIT');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
	$publish = array_values(array_filter($calls, fn ($c) => str_starts_with($c['sql'], 'UPDATE plugin_flowview_nbar_hours')));
	expect($publish[0]['params'])->toBe(['123', '123', 3, 1800000000]);
});

it('rolls back each failed hourly mutation without committing partial totals', function ($function, $failure) {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);

	// db_execute runs the timeout, isolation level and START TRANSACTION before the three aggregates.
	$passes = $function === 'db_execute' ? 3 + $failure : $failure;

	for ($i = 0; $i < $passes; $i++) {
		plugin_test_queue_db_result($function, true);
	}
	plugin_test_queue_db_result($function, false);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[count($sql) - 2])->toBe('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
	expect($sql)->not->toContain('COMMIT');
})->with([
	['db_execute_prepared', 0], ['db_execute_prepared', 1], ['db_execute_prepared', 2], ['db_execute_prepared', 3],
	['db_execute', 0], ['db_execute', 1], ['db_execute', 2],
]);

it('rejects an hourly build whose commit fails', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);

	// The timeout, isolation level, START TRANSACTION and three aggregates precede the COMMIT.
	for ($i = 0; $i < 6; $i++) {
		plugin_test_queue_db_result('db_execute', true);
	}
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[count($sql) - 2])->toBe('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
});

it('rolls back an hourly build when the raw slices cannot be listed instead of publishing an empty hour', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_assoc', false);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[count($sql) - 2])->toBe('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
	expect(implode(' ', $sql))->not->toContain('DELETE FROM plugin_flowview_nbar_')->not->toContain('built=?');
});

it('stores the selection criteria so the count rebuilds its source when it runs', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_count.php');
	$_SESSION['sess_user_id'] = 1;
	plugin_test_queue_slices([]);
	$key = fv_nbar_count_context('', 'd.listener_id=?', [1], 1800001800, [1], 1800000000);
	expect($_SESSION['fv_nbar_counts'][$key]['scope'])->toBe(['where' => 'd.listener_id=?', 'listeners' => [1], 'start' => 1800000000, 'end' => 1800001800]);
	// A slice that appears later changes the SQL text but must not change the cache key.
	plugin_test_queue_slices([fv_nbar_data_table(1800000000)]);
	expect(fv_nbar_count_context('', 'd.listener_id=?', [1], 1800001800, [1], 1800000000))->toBe($key);
	expect($_SESSION['fv_nbar_counts'])->toHaveCount(1);
	$other = fv_nbar_count_context('(SELECT fixture)', 'd.listener_id=?', [1], 1800001800);
	expect($_SESSION['fv_nbar_counts'][$other])->not->toHaveKey('scope');
});

it('aggregates an hour from only the slices that cover it', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_slices([fv_nbar_data_table(1799996400), fv_nbar_data_table(1800000000), fv_nbar_data_table(1800003600)]);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeTrue();
	$inserts = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'INSERT INTO plugin_flowview_nbar_')));
	expect($inserts)->toHaveCount(3);

	foreach ($inserts as $insert) {
		expect($insert['sql'])->toContain(fv_nbar_data_table(1800000000));
		expect($insert['sql'])->not->toContain(fv_nbar_data_table(1799996400));
		expect($insert['sql'])->not->toContain(fv_nbar_data_table(1800003600));
		expect($insert['sql'])->toContain('listener_id=3 AND minute>=1800000000 AND minute<1800003600');
	}
});

it('rejects hourly builds with missing or changed revisions', function ($latest) {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => '123']);
	plugin_test_queue_db_result('db_fetch_row_prepared', $latest);
	expect(fv_nbar_summary_build(3, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[count($sql) - 2])->toBe('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
	expect(implode(' ', $sql))->not->toContain('UPDATE plugin_flowview_nbar_hours SET built');
})->with([[['revision' => '124']], [[]]]);

it('rejects a build with no initial hourly state', function () {
	expect(fv_nbar_summary_build(3, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[count($sql) - 2])->toBe('ROLLBACK');
	expect(end($sql))->toBe('SET SESSION max_statement_time=0');
});

it('skips hourly maintenance while another worker holds its lock', function () {
	expect(fv_nbar_summary_refresh())->toBe(0);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('requires all overlapping hours to be ready before using a daily revision', function ($state, $expected) {
	plugin_test_queue_db_result('db_fetch_row_prepared', $state);
	expect(fv_nbar_daily_signature(3, 1800000000, 1800007200))->toBe($expected);
})->with([
	[['n' => 2, 'ready' => 2, 'rev' => '18446744073709551616'], '18446744073709551616'],
	[['n' => 1, 'ready' => 1, 'rev' => '1'], null],
	[['n' => 2, 'ready' => 1, 'rev' => '1'], null],
	[[], null],
]);

it('fails closed for untrusted daily signature text', function () {
	$sql = fv_nbar_daily_guard(3, 1800000000, 1800007200, '1 OR 1=1');
	expect($sql)->toContain('revision=-1');
	expect($sql)->not->toContain('OR 1=1');
});

it('keeps observation dates unknown when any contributing row lacks dates', function () {
	$total = ['first_seen' => null, 'last_seen' => null, '_seen_missing' => false];
	fv_nbar_seen_merge($total, ['first_seen' => 20, 'last_seen' => 30]);
	fv_nbar_seen_merge($total, ['first_seen' => 10, 'last_seen' => 25]);
	expect($total['first_seen'])->toBe(10);
	expect($total['last_seen'])->toBe(30);
	fv_nbar_seen_merge($total, ['first_seen' => null, 'last_seen' => 50]);
	expect($total['_seen_missing'])->toBeTrue();
});

it('does not start date backfill without both scoped locks', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_cell', 'REPEATABLE-READ');
	plugin_test_queue_db_result('db_fetch_cell', 0);
	expect(fv_nbar_seen_backfill())->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('RELEASE_LOCK', 'REPEATABLE READ');
	expect($sql)->not->toContain('UPDATE plugin_flowview_nbar_');
});

it('expires all summary levels with bounded database deletions and drops raw slices', function () {
	fv_nbar_cleanup();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect($sql[0])->toStartWith('SHOW TABLES');
	$deletes = array_slice($sql, 1);
	expect($deletes)->toHaveCount(8);

	foreach ($deletes as $statement) {
		expect($statement)->toStartWith('DELETE FROM plugin_flowview_nbar_');
		expect($statement)->toContain(' LIMIT ');
		expect($statement)->not->toContain('plugin_flowview_nbar_data');
	}
});

it('rejects unknown summary types before constructing SQL', function () {
	expect(fn () => fv_nbar_hourly_source('untrusted', [1], 0, 3600))->toThrow(InvalidArgumentException::class);
	expect(fn () => fv_nbar_summary_source('untrusted', [1], 0, 3600))->toThrow(InvalidArgumentException::class);
});

it('keeps summary and raw branches mutually exclusive for complete hours', function () {
	foreach (['apps', 'pairs', 'connections'] as $kind) {
		plugin_test_queue_slices([fv_nbar_data_table(1800000000), fv_nbar_data_table(1800003600), fv_nbar_data_table(1800007200), fv_nbar_data_table(1800010800), fv_nbar_data_table(1800090000)]);
		$sql = fv_nbar_hourly_source($kind, [3], 1800000010, 1800010810, 1800100000);
		expect($sql)->toContain('UNION ALL', 'NOT (', 'listener_id=3');
		expect($sql)->toContain('minute>=1800000010', 'minute<1800010810');
		expect($sql)->toContain('`' . fv_nbar_data_table(1800000000) . '`', '`' . fv_nbar_data_table(1800003600) . '`', '`' . fv_nbar_data_table(1800010800) . '`');
		expect($sql)->not->toContain(fv_nbar_data_table(1800090000));
	}
});

it('restores the database timeout after building or rolling back a daily block', function ($fail) {
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 1, 'ready' => 1, 'rev' => '42']);
	plugin_test_queue_db_result('db_fetch_cell', '7.5');

	if ($fail) {
		plugin_test_queue_db_result('db_execute', true);
		plugin_test_queue_db_result('db_execute', true);
		plugin_test_queue_db_result('db_execute', false);
	}
	expect(fv_nbar_daily_build(3, 1800000000, 1800003600))->toBe($fail ? 0 : 1);
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('SET SESSION max_statement_time=7.5');
	expect($sql)->toContain($fail ? 'ROLLBACK' : 'COMMIT');
})->with([false, true]);

it('caches exact-count contexts separately by account and filter', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_count.php');
	$_SESSION['sess_user_id'] = 1;
	$a                        = fv_nbar_count_context('(SELECT fixture)', 'd.listener_id=?', [1], time());
	$b                        = fv_nbar_count_context('(SELECT fixture)', 'd.listener_id=?', [2], time() - 3600);
	$_SESSION['sess_user_id'] = 2;
	$c                        = fv_nbar_count_context('(SELECT fixture)', 'd.listener_id=?', [1], time());
	expect($a)->not->toBe($b)->not->toBe($c);
	expect($_SESSION['fv_nbar_counts'][$a]['ttl'])->toBe(60);
	expect($_SESSION['fv_nbar_counts'][$b]['ttl'])->toBe(600);
	expect($_SESSION['fv_nbar_counts'][$a]['sql'])->toContain('COUNT(DISTINCT', 'd.listener_id=?');
	expect($_SESSION['fv_nbar_counts'][$a]['args'])->toBe([1]);
	expect(fv_nbar_count_context('(SELECT fixture)', 'd.listener_id=?', [1], time()))->toBe($c);
});

it('expires old count contexts and bounds the account cache', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_count.php');
	$_SESSION['fv_nbar_counts'] = ['expired' => ['expires' => time() - 1]];

	foreach (range(1, 12) as $i) {
		$_SESSION['fv_nbar_counts']['context' . $i] = ['expires' => time() + 3600];
	}
	$key = fv_nbar_count_context('(SELECT fixture)', '1=1', [], time());
	expect($_SESSION['fv_nbar_counts'])->toHaveCount(12);
	expect($_SESSION['fv_nbar_counts'])->not->toHaveKey('expired')->not->toHaveKey('context1');
	expect($_SESSION['fv_nbar_counts'])->toHaveKey($key);
});

it('uses summary tuple fields for counts without aggregating traffic counters', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_count.php');
	plugin_test_queue_slices([fv_nbar_data_table(1800000000)]);
	$key = fv_nbar_count_context('', 'd.listener_id=? AND d.minute>=? AND d.minute<?', [1, 1800000000, 1800001800], 1800001800, [1], 1800000000);
	$sql = $_SESSION['fv_nbar_counts'][$key]['sql'];
	expect($sql)->toContain('COUNT(DISTINCT', fv_nbar_data_table(1800000000), 'd.listener_id=?');
	expect($sql)->not->toContain('SUM(d.bytes)');
});
