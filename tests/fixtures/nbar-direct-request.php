<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Run only as a child process of the isolated unit test suite.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
	exit(1);
}
$scenario  = $argv[1];
$directory = $argv[2];
$driver    = function_exists('pcov\\start') ? 'pcov' : (function_exists('xdebug_start_code_coverage') ? 'xdebug' : '');

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
	file_put_contents($directory . '/result.json', json_encode(['session' => $_SESSION ?? [], 'calls' => $GLOBALS['__test_db_calls'] ?? [], 'coverage' => $coverage, 'status' => http_response_code()]));
});
$files = ['guide' => 'nbar_setup_guide.php', 'report' => 'nbar_report.php'];

if (!isset($files[$scenario])) {
	exit(1);
}

require(dirname(__DIR__, 2) . '/' . $files[$scenario]);
