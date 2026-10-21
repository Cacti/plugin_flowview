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
	file_put_contents($directory . '/result.json', json_encode(['session' => $_SESSION ?? [], 'calls' => $GLOBALS['__test_db_calls'], 'coverage' => $coverage, 'status' => http_response_code(), 'footer' => $GLOBALS['__test_footer_rendered'] ?? false, 'redirect' => $GLOBALS['restore'] ?? null]));
});
flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
$constants = file_get_contents($config['base_path'] . '/include/global_constants.php');
preg_match_all("/define\('((?:GT_|GTS_)[A-Z0-9_]+)', ([0-9]+)\);/", $constants, $matches, PREG_SET_ORDER);

foreach ($matches as $match) {
	if (!defined($match[1])) {
		define($match[1], (int) $match[2]);
	}
}
/**
 * Supply a test token without a real account or authentication cookie.
 *
 * @return string
 */
function csrf_get_tokens(): string {
	return 'unit-csrf-token';
}
/**
 * Render the minimum Cacti report-box markup needed by this fixture.
 *
 * @param mixed ...$args Cacti box options.
 *
 * @return void
 */
function html_start_box(mixed ...$args): void {
	print '<table>';
}
/**
 * Close the fixture report-box markup.
 *
 * @param mixed ...$args Cacti box options.
 *
 * @return void
 */
function html_end_box(mixed ...$args): void {
	print '</table>';
}
/**
 * Record completion without loading Cacti's real page footer.
 *
 * @return void
 */
function bottom_footer(): void {
	$GLOBALS['__test_footer_rendered'] = true;
}
$GLOBALS['__test_config']['flowview_nbar_schema_version']     = '2';
$GLOBALS['__test_user_settings']['flowview_nbar_preferences'] = json_encode(['prefixes' => ['192.0.2.0/24'], 'listeners' => [], 'dns' => '0']);
$graph_timespans                                              = [GT_LAST_HALF_HOUR => 'Last Half Hour', GT_LAST_HOUR => 'Last Hour', GT_LAST_DAY => 'Last Day', GT_LAST_YEAR => 'Last Year'];
$_REQUEST                                                     = ['action' => 'view', 'report' => 'nbar', 'mode' => 'apps', 'listener' => '1'];
$_SERVER['REQUEST_METHOD']                                    = 'GET';

if (str_starts_with($scenario, 'nav-redirect')) {
	$_REQUEST = in_array($scenario, ['nav-redirect-header', 'nav-redirect-fragment-header'], true) ? ['headercontent' => 'true'] : [];

	if (str_contains($scenario, 'fragment')) {
		$_REQUEST += ['action' => 'view', 'header' => 'false'];
	}
	$GLOBALS['__test_user_settings']['flowview_last_section'] = $scenario === 'nav-redirect-fragment-flows' ? 'flows' : 'nbar';
} elseif ($scenario === 'nav-select') {
	$_REQUEST = ['section' => 'nbar', 'mode' => 'apps'];
} elseif ($scenario === 'combine') {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST += ['prefs_apply' => '1', 'combine_apply' => '1', 'local_prefixes' => '192.0.2.0/24', 'site_listeners' => ['1', '2']];
} elseif ($scenario === 'invalid-mode') {
	$_REQUEST['mode'] = 'invalid';
} elseif ($scenario === 'no-combined') {
	$_REQUEST['listener'] = '0';
} elseif ($scenario === 'unknown-context') {
	$_REQUEST['context'] = json_encode(['report' => 's99999', 'sortfield' => ['bad']]);
} elseif ($scenario === 'invalid') {
	$_REQUEST += ['period' => '7', 'family' => 'bad', 'app' => 'injected', 'host' => 'not-an-ip', 'context' => 'false'];
	$_REQUEST['listener'] = '999';
} elseif ($scenario === 'retention') {
	$_REQUEST['predefined_timespan'] = (string) GT_LAST_YEAR;
} elseif ($scenario === 'fallback-span') {
	$_REQUEST['predefined_timespan'] = '9999';
} elseif ($scenario === 'all-history') {
	$_REQUEST['predefined_timespan'] = '1000';
} elseif ($scenario === 'clamped') {
	$_REQUEST += ['predefined_timespan' => '0', 'date1' => date('Y-m-d H:i', time() - 10 * 86400), 'date2' => date('Y-m-d H:i', time() + 3600)];
} elseif ($scenario === 'legacy') {
	$_REQUEST['period'] = '3600';
} elseif ($scenario === 'drilldown') {
	$_REQUEST = array_replace($_REQUEST, ['mode' => 'connections', 'app' => '0d000001', 'parent_app' => '0d000001', 'internal' => '192.0.2.10', 'external' => '198.51.100.20', 'context' => json_encode(['report' => 'bad', 'nested' => ['bad']])]);
} elseif ($scenario === 'conversations') {
	$_REQUEST['mode'] = 'conversations';
} elseif ($scenario === 'apps-failure') {
	$_REQUEST['mode'] = 'apps';
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
} elseif ($scenario === 'conversation-failure') {
	$_REQUEST['mode'] = 'conversations';
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
} elseif ($scenario === 'failure') {
	$_REQUEST['mode'] = 'connections';
	plugin_test_queue_db_result('db_execute', false);
} elseif ($scenario === 'upgrade') {
	$GLOBALS['__test_config']['flowview_nbar_schema_version'] = '0';
	$_SERVER['PHP_SELF']                                      = 'fixture.php';
} elseif ($scenario === 'more-pages') {
	$_REQUEST['mode'] = 'connections';
	$from             = (int) floor(time() / 3600) * 3600 - 3600;
	$_REQUEST += ['predefined_timespan' => '0', 'date1' => date('Y-m-d H:i', $from), 'date2' => date('Y-m-d H:i', $from + 1200), 'lock_range' => '1'];

	foreach (range(1, 4) as $unused) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	}
	$rows = [];

	foreach (range(1, 101) as $i) {
		$rows[] = ['app_id' => '0d000001', 'src_hex' => bin2hex(inet_pton('192.0.2.' . $i)), 'dst_hex' => bin2hex(inet_pton('198.51.100.20')), 'sport' => $i, 'dport' => 443, 'protocol' => 6, 'bytes' => '10', 'packets' => '1', 'records' => '1', 'first_seen' => $from, 'last_seen' => $from + 60];
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
} elseif ($scenario === 'csrf') {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST += ['prefs_apply' => '1', 'local_prefixes' => ''];
	$GLOBALS['__test_csrf_valid'] = false;
}

foreach (range(1, 3) as $unused) {
	plugin_test_queue_db_result('db_fetch_assoc', []);
}
plugin_test_queue_db_result('db_fetch_assoc', $scenario === 'combine' ? [['id' => 1, 'name' => 'Fixture router'], ['id' => 2, 'name' => 'Second']] : [['id' => 1, 'name' => 'Fixture router']]);
require(dirname(__DIR__, 2) . '/flowview_sections.php');
require(dirname(__DIR__, 2) . '/nbar_report.php');
