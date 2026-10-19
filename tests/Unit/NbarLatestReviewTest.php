<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
beforeEach(function () {
	plugin_test_reset();
	global $config;
	$constants = file_get_contents($config['base_path'] . '/include/global_constants.php');
	preg_match_all("/define\\('((?:GT_|GTS_)[A-Z0-9_]+)', ([0-9]+)\\);/", $constants, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		if (!defined($match[1])) {
			define($match[1], (int) $match[2]);
		}
	}

	if (!defined('DEFAULT_TIMESPAN')) {
		define('DEFAULT_TIMESPAN', 1800);
	}
	require_once($config['base_path'] . '/lib/time.php');
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_timespan.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_preferences.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_feed_status.php');
});
it('handles all NBAR timespans without replacing legacy sessions', function ($preset) {
	$_SESSION = ['sess_fv_7' => ['report' => 7, 'predefined_timespan' => 4]];
	$_REQUEST = ['report' => 'nbar', 'predefined_timespan' => $preset, 'date1' => '2026-10-10 00:00', 'date2' => '2026-10-10 01:00'];
	$session  = $_SESSION;
	ob_start();
	expect(fv_nbar_timespan_request())->toBeTrue();
	$payload = json_decode(ob_get_clean(), true);
	expect($payload)->toHaveKeys(['begin_now', 'end_now']);
	expect($_SESSION)->toBe($session);
})->with([0, 1, 4, 1000, 'invalid']);
it('leaves legacy timespan dispatch to its original handler', function () {
	$_REQUEST = ['report' => '7'];
	expect(fv_nbar_timespan_request())->toBeFalse();
});
it('distinguishes a status read failure from an empty feed and keeps other captures visible', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', false);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => time()]);
	ob_start();
	fv_nbar_feed_status([['id' => 1, 'name' => 'Broken'], ['id' => 2, 'name' => 'Healthy']], [1, 2]);
	$html = ob_get_clean();
	expect($html)->toContain('Broken: Unable to load NBAR status. Please try again.', 'Healthy: Receiving')->not->toContain('No retained NBAR records', 'No recent NBAR');
});
it('excludes multicast in both endpoint predicates', function () {
	foreach (['d.src', 'd.dst'] as $field) {
		$sql = fv_nbar_unicast_sql($field);
		expect($sql)->toContain("LENGTH($field)=4", '& 240)=224', "LENGTH($field)=16", '=255');
	}
	expect(fn () => fv_nbar_unicast_sql('unsafe'))->toThrow(InvalidArgumentException::class);
});
it('drains more than one batch fairly across retention tables', function () {
	foreach ([50000, 50000, 1, 1] as $count) {
		plugin_test_queue_db_result('db_affected_rows', $count);
	}
	fv_nbar_cleanup_batches([['DELETE fixtureA LIMIT 50000', 50000], ['DELETE fixtureB LIMIT 50000', 50000]], microtime(true) + 1);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toBe(['DELETE fixtureA LIMIT 50000', 'DELETE fixtureB LIMIT 50000', 'DELETE fixtureA LIMIT 50000', 'DELETE fixtureB LIMIT 50000']);
});
it('stops cleanup at its deadline and drops failed batches', function () {
	fv_nbar_cleanup_batches([['expired', 1]], microtime(true) - 1);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
	plugin_test_queue_db_result('db_execute', false);
	fv_nbar_cleanup_batches([['failed', 1]], microtime(true) + 1);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toBe(['failed']);
});
it('checks the shared deadline between cleanup statements', function () {
	plugin_test_queue_db_result('db_execute', function () {
		usleep(3000);

		return true;
	});
	fv_nbar_cleanup_batches([['first', 1], ['second', 1]], microtime(true) + 0.001);
	expect(array_column($GLOBALS['__test_db_calls'], 'sql'))->toBe(['first']);
});
it('drains the daily retention tables through the shared worker', function () {
	fv_nbar_daily_cleanup();
	expect($GLOBALS['__test_db_calls'])->toHaveCount(4);
});
