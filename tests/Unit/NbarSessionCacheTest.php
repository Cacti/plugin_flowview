<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_query.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_connections.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'session-cache-test';
	$this->oldSessionPath = session_save_path();
	$this->oldSessionCookies = ini_get('session.use_cookies');
	$this->oldSessionLimiter = session_cache_limiter();
	session_save_path(sys_get_temp_dir());
	ini_set('session.use_cookies', '0');
	session_cache_limiter('');
	session_start();
	session_regenerate_id(true);
});

afterEach(function () {
	session_destroy();
	session_save_path($this->oldSessionPath);
	ini_set('session.use_cookies', $this->oldSessionCookies);
	session_cache_limiter($this->oldSessionLimiter);
});

it('reuses adjacent page blocks while slicing only the requested rows', function () {
	$rows = array_map(fn ($i) => ['id' => $i], range(0, 500));
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	expect(fv_nbar_query_cached('SELECT fixture LIMIT 101 OFFSET 200', [1]))->toBe(array_slice($rows, 200, 101));
	expect(fv_nbar_query_cached('SELECT fixture LIMIT 101 OFFSET 300', [1]))->toBe(array_slice($rows, 300, 101));
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toBe('SELECT fixture LIMIT 501 OFFSET 0');
});

it('does not share cached results between accounts or different filters', function () {
	$_SESSION['sess_user_id'] = 1;
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['bytes' => 10]]);
	expect(fv_nbar_query_cached('SELECT fixture', [1]))->toBe([['bytes' => 10]]);
	$_SESSION['sess_user_id'] = 2;
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['bytes' => 20]]);
	expect(fv_nbar_query_cached('SELECT fixture', [1]))->toBe([['bytes' => 20]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['bytes' => 30]]);
	expect(fv_nbar_query_cached('SELECT fixture', [2]))->toBe([['bytes' => 30]]);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(3);
});

it('expires stale cache entries and evicts the oldest compact result', function () {
	$_SESSION['fv_nbar_query_cache'] = ['expired' => ['until' => time() - 1, 'rows' => []]];
	foreach (range(1, 12) as $i) {
		$_SESSION['fv_nbar_query_cache']['item' . $i] = ['until' => time() + 100, 'rows' => []];
	}
	fv_nbar_query_cached('SELECT fresh', []);
	expect($_SESSION['fv_nbar_query_cache'])->toHaveCount(12);
	expect($_SESSION['fv_nbar_query_cache'])->not->toHaveKey('expired')->not->toHaveKey('item1');
});

it('does not retain failed or oversized query results', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(fv_nbar_query_cached('SELECT failure LIMIT 101 OFFSET 0', []))->toBeFalse();
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['large' => str_repeat('x', 600000)]]);
	$result = fv_nbar_query_cached('SELECT large', []);
	expect(strlen($result[0]['large']))->toBe(600000);
	expect($_SESSION['fv_nbar_query_cache'])->toBe([]);
});


it('reuses connection pages only for the same account and page', function () {
	$_SESSION['sess_user_id'] = 1;
	expect(fv_nbar_connections_cached([1], 100, 100, '1=1', [], 1))->toBe([]);
	$calls = count($GLOBALS['__test_db_calls']);
	expect(fv_nbar_connections_cached([1], 100, 100, '1=1', [], 1))->toBe([]);
	expect($GLOBALS['__test_db_calls'])->toHaveCount($calls);
	fv_nbar_connections_cached([1], 100, 100, '1=1', [], 2);
	expect(count($GLOBALS['__test_db_calls']))->toBeGreaterThan($calls);
	$calls = count($GLOBALS['__test_db_calls']);
	$_SESSION['sess_user_id'] = 2;
	fv_nbar_connections_cached([1], 100, 100, '1=1', [], 1);
	expect(count($GLOBALS['__test_db_calls']))->toBeGreaterThan($calls);
	expect($_SESSION['fv_nbar_connection_pages'])->toHaveCount(3);
});

it('expires old connection pages and retains at most three requested pages', function () {
	$_SESSION['fv_nbar_connection_pages'] = ['expired' => ['until' => time() - 1, 'rows' => []]];
	foreach (range(1, 3) as $i) {
		$_SESSION['fv_nbar_connection_pages']['page' . $i] = ['until' => time() + 100, 'rows' => []];
	}
	fv_nbar_connections_cached([1], 100, 100, '1=1', [], 1);
	expect($_SESSION['fv_nbar_connection_pages'])->toHaveCount(3);
	expect($_SESSION['fv_nbar_connection_pages'])->not->toHaveKey('expired')->not->toHaveKey('page1');
	$entry = end($_SESSION['fv_nbar_connection_pages']);
	expect($entry['until'])->toBeGreaterThanOrEqual(time() + 119);
});

it('keeps recent connection page caching short and never caches a failed snapshot', function () {
	$now = time();
	fv_nbar_connections_cached([1], $now, $now, '1=1', [], 1);
	$entry = end($_SESSION['fv_nbar_connection_pages']);
	expect($entry['until'])->toBeLessThanOrEqual(time() + 30);
	$_SESSION['fv_nbar_connection_pages'] = [];
	plugin_test_queue_db_result('db_execute', false);
	expect(fn () => fv_nbar_connections_cached([1], $now, $now, '1=1', [], 2))->toThrow(RuntimeException::class);
	expect($_SESSION['fv_nbar_connection_pages'])->toBe([]);
});
