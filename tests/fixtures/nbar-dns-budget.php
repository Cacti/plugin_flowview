<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
// Run only as a child process of the isolated unit test suite.
if (PHP_SAPI !== 'cli' || count($argv) !== 3) {
	exit(1);
}
require(dirname(__DIR__) . '/bootstrap-unit.php');
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

class Net_DNS2_Resolver {
	public $timeout;
	public static int $queries = 0;
	public function __construct(array $options) {
	}
	public function query(string $ip, string $type): object {
		self::$queries++;

		return (object) ['answer' => []];
	}
}
flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_dns.php');
$GLOBALS['fv_nbar_preferences'] = ['dns' => '1'];
fv_nbar_dns('192.0.2.249');
usleep(1100000);
print json_encode(['name' => fv_nbar_dns('192.0.2.250'), 'queries' => Net_DNS2_Resolver::$queries]);
