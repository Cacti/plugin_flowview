<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Run only as a child process of the isolated unit test suite.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
	exit(1);
}
require dirname(__DIR__) . '/bootstrap-unit.php';
$scenario = $argv[1];
$directory = $argv[2];
$driver = function_exists('pcov\\start') ? 'pcov' : (function_exists('xdebug_start_code_coverage') ? 'xdebug' : '');
if ($driver === 'pcov') {
	\pcov\start();
} elseif ($driver === 'xdebug') {
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}
register_shutdown_function(static function () use ($directory, $driver) {
	$coverage = [];
	if ($driver === 'pcov') {
		\pcov\stop();
		$coverage = \pcov\collect();
	} elseif ($driver === 'xdebug') {
		$coverage = xdebug_get_code_coverage();
		xdebug_stop_code_coverage();
	}
	file_put_contents($directory . '/result.json', json_encode(['session' => $_SESSION ?? [], 'calls' => $GLOBALS['__test_db_calls'], 'coverage' => $coverage]));
});
flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
flowview_test_load(dirname(__DIR__, 2) . '/nbar_count.php');
$GLOBALS['flowview_cnn'] = 'count-endpoint-test';
ini_set('session.use_cookies', '0');
session_cache_limiter('');
session_save_path($directory);
session_id('flowview-count-test');
session_start();
$key = str_repeat('a', 64);
$_REQUEST = ['key' => $key];
$entry = ['expires' => time() + 3600, 'sql' => 'SELECT COUNT(*) total FROM fixture WHERE listener_id=?', 'args' => [3], 'ttl' => 600];
$_SESSION['fv_nbar_counts'] = [$key => $entry];
if ($scenario === 'malformed') {
	$_REQUEST['key'] = ['nested'];
} elseif ($scenario === 'missing') {
	$_SESSION['fv_nbar_counts'] = [];
} elseif ($scenario === 'expired') {
	$_SESSION['fv_nbar_counts'][$key]['expires'] = time() - 1;
} elseif ($scenario === 'retry') {
	$_SESSION['fv_nbar_counts'][$key]['failed_until'] = time() + 60;
} elseif ($scenario === 'cached') {
	$_SESSION['fv_nbar_counts'][$key] += ['total' => 12345, 'until' => time() + 60];
} elseif ($scenario === 'busy') {
	plugin_test_queue_db_result('db_fetch_cell', 0);
} else {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row_prepared', str_contains($scenario, 'failed') ? false : ['total' => 12345]);
	if (str_starts_with($scenario, 'background')) {
		function fastcgi_finish_request() {
			$GLOBALS['__test_response_finished'] = true;
			return true;
		}
	}
}
fv_nbar_count_request();
