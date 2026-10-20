<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Run only as a child process of the isolated unit test suite.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
	exit(1);
}
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
	file_put_contents($directory . '/result.json', json_encode(['session' => $_SESSION ?? [], 'calls' => $GLOBALS['__test_db_calls'] ?? [], 'coverage' => $coverage, 'status' => http_response_code()]));
});
$files = ['database' => 'includes/nbar_database.php', 'common' => 'nbar_common.php', 'connections' => 'nbar_connections.php', 'query' => 'nbar_query.php', 'daily' => 'nbar_daily.php', 'seen' => 'nbar_seen.php', 'summary' => 'nbar_summary.php', 'timespan' => 'nbar_timespan.php', 'preferences' => 'nbar_preferences.php', 'guide' => 'nbar_setup_guide.php', 'report' => 'nbar_report.php', 'collector' => 'nbar_collector.php'];
if (!isset($files[$scenario])) {
	exit(1);
}
if ($scenario === 'common') {
	/**
	 * Supply only the bootstrap symbol required to reach the common guard.
	 *
	 * @return void
	 */
	function flowview_connect() {
	}
	/**
	 * Supply only the bootstrap symbol required to load schema definitions.
	 *
	 * @return void
	 */
	function flowview_db_table_create() {
	}
	$GLOBALS['config'] = [];
	require dirname(__DIR__, 2) . '/nbar_seen.php';
	unset($GLOBALS['config']);
}
require dirname(__DIR__, 2) . '/' . $files[$scenario];
