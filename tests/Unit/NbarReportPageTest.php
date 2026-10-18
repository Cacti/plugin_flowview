<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

it('renders combined IPv6 reports with protected controls and escaped rows', function ($view) {
	global $config, $graph_timespans, $lid, $combined_lids;
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	// Import only time constants; the unit harness already defines poller constants.
	$constants = file_get_contents($config['base_path'] . '/include/global_constants.php');
	preg_match_all("/define\('((?:GT_|GTS_)[A-Z0-9_]+)', ([0-9]+)\);/", $constants, $matches, PREG_SET_ORDER);
	foreach ($matches as $match) {
		if (!defined($match[1])) {
			define($match[1], (int) $match[2]);
		}
	}
	if (!function_exists('csrf_get_tokens')) {
		function csrf_get_tokens() {
			return 'unit-csrf-token';
		}
	}
	if (!function_exists('html_start_box')) {
		function html_start_box(...$args) {
			echo '<table>';
		}
	}
	if (!function_exists('html_end_box')) {
		function html_end_box(...$args) {
			echo '</table>';
		}
	}
	if (!function_exists('bottom_footer')) {
		function bottom_footer() {
			$GLOBALS['__test_footer_rendered'] = true;
		}
	}
	$GLOBALS['__test_config']['flowview_nbar_schema_version'] = '2';
	$GLOBALS['__test_user_settings']['flowview_nbar_preferences'] = json_encode(['listeners' => [1, 2, 3], 'prefixes' => ['2001:db8::/64'], 'dns' => '0']);
	$graph_timespans = [GT_LAST_HALF_HOUR => 'Last Half Hour', GT_LAST_HOUR => 'Last Hour', GT_LAST_DAY => 'Last Day', GT_LAST_WEEK => 'Last Week'];
	$now = (int) floor(time() / 60) * 60;
	$_REQUEST = ['mode' => $view, 'page' => '500', 'listener' => '0', 'family' => '6', 'host' => '2001:db8::10', 'app' => '0d000001', 'predefined_timespan' => '0', 'date1' => date('Y-m-d H:i', $now - 1200), 'date2' => date('Y-m-d H:i', $now), 'lock_range' => '1', 'context' => json_encode(['report' => 's10', 'unexpected' => ['nested']])];
	$_SERVER['REQUEST_METHOD'] = 'GET';
	for ($i = 0; $i < 3; $i++) {
		plugin_test_queue_db_result('db_fetch_assoc', []);
	}
	plugin_test_queue_db_result('db_fetch_assoc', [['id' => 1, 'name' => '<router>'], ['id' => 2, 'name' => 'Second'], ['id' => 3, 'name' => 'Third']]);
	for ($i = 0; $i < 6; $i++) {
		plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => $now - 60]);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['name' => '<app>', 'bytes' => 1000000]]);
	if ($view === 'connections') {
		for ($i = 0; $i < 7; $i++) {
			plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
		}
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['src' => '2001:db8::10', 'dst' => '2001:db8::20', 'sport' => 12345, 'dport' => 443, 'protocol' => 6, 'app_id' => '0d000001', 'name' => '<app>', 'bytes' => 1000000, 'packets' => 20, 'records' => 2, 'first_seen' => $now - 600, 'last_seen' => $now - 60]]);
	ob_start();
	require_once dirname(__DIR__, 2) . '/flowview_sections.php';
	require dirname(__DIR__, 2) . '/nbar_report.php';
	$html = ob_get_clean();
	expect($html)->toContain('unit-csrf-token', 'method="post"', '&lt;router&gt;', '&lt;app&gt;', '2001:db8::10', 'First seen', 'Last seen', 'Setup guide', 'flowviewNbarInitTable');
	expect($html)->not->toContain('<app>', '<router>', 'unexpected', '<scriptnonce');
	expect($lid)->toBe(0);
	expect($combined_lids)->toBe([1, 2, 3]);
	expect($GLOBALS['__test_footer_rendered'])->toBeTrue();
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('LENGTH(d.src)=16', 'd.app_id=?', 'INET6_ATON(?)');
	if ($view === 'connections') {
		expect($html)->toContain('Source IP', 'Destination IP', 'TCP', 'Counting total pages', 'Previous', 'nbarPageNumber', 'Back to top applications');
		expect($html)->not->toContain('All conversations');
	}
})->with(['apps', 'connections']);
