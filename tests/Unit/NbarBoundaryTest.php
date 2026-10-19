<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_preferences.php');
});

beforeEach(function () {
	plugin_test_reset();
});

it('guards complete daily summaries and raw boundaries against double counting', function ($kind) {
	$day = strtotime('-2 days midnight');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['day' => $day, 'covered_to' => $day + 86400, 'timezone' => date_default_timezone_get(), 'revision' => '42']]);
	$sql = fv_nbar_summary_source($kind, [1], $day - 1800, $day + 88200, time());
	expect($sql)->toContain('plugin_flowview_nbar_daily_' . $kind, 'fallback_before', 'fallback_after', 'fallback_day', 'NOT (EXISTS', 'revision=42');
	expect($sql)->toContain('minute>=' . ($day - 1800), 'minute<' . ($day + 88200));

	if ($kind === 'apps') {
		expect($sql)->toContain('REPEAT(CHAR(0),family)');
	} elseif ($kind === 'pairs') {
		expect($sql)->toContain('0 sport,0 dport,0 protocol');
	} else {
		expect($sql)->toContain('src,dst,sport,dport,protocol');
	}
})->with(['apps', 'pairs', 'connections']);

it('ignores incompatible or overlapping daily checkpoints', function () {
	$day = strtotime('-2 days midnight');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [
		['day' => $day, 'covered_to' => $day + 86400, 'timezone' => 'Other/Zone', 'revision' => '1'],
		['day' => $day - 86400, 'covered_to' => $day, 'timezone' => date_default_timezone_get(), 'revision' => '1'],
		['day' => $day, 'covered_to' => time() + 3600, 'timezone' => date_default_timezone_get(), 'revision' => '1']
	]);
	$sql = fv_nbar_summary_source('apps', [1], $day, $day + 86400, time());
	expect($sql)->not->toContain('plugin_flowview_nbar_daily_apps');
	expect($sql)->toContain('fallback_after', 'minute>=' . $day);
});

it('uses a raw source when no capture scope exists', function () {
	expect(fv_nbar_summary_source('apps', [], 0, 3600))->toContain('plugin_flowview_nbar_data', 'minute first_seen');
	expect(fv_nbar_hourly_source('apps', [], 0, 3600))->toContain('plugin_flowview_nbar_data', 'minute last_seen');
});

it('builds exact partial-byte internal-prefix predicates for both address families', function () {
	$GLOBALS['fv_nbar_preferences'] = ['prefixes' => ['192.0.2.128/25', '2001:db8::/65']];
	$sql                            = fv_nbar_local_sql('d.src');
	expect($sql)->toContain('LENGTH(d.src)=4', 'LENGTH(d.src)=16', '(ORD(SUBSTRING(d.src,4,1)) & 128)=128', '(ORD(SUBSTRING(d.src,9,1)) & 128)=0');
	expect(fn () => fv_nbar_local_sql('d.bytes'))->toThrow(InvalidArgumentException::class);
});

it('rejects oversized network lists and invalid network submissions without saving', function () {
	$prefixes = implode("\n", array_map(fn ($i) => '192.0.2.' . $i . '/32', range(1, 33)));
	expect(fv_nbar_cidrs($prefixes))->toBeFalse();
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST                  = ['prefs_apply' => '1', 'local_prefixes' => 'not-a-network', 'site_listeners' => []];
	fv_nbar_preferences_init([]);
	expect($GLOBALS['fv_nbar_preferences_saved'])->toBeFalse();
	expect($GLOBALS['fv_nbar_preferences_message'])->toContain('Enter valid IPv4/IPv6 networks');
});

it('saves only permitted display options from settings submissions', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST                  = ['prefs_apply' => '1', 'local_prefixes' => '', 'site_listeners' => [], 'data_unit' => 'Auto', 'rate_unit' => 'Kbps', 'dns' => '0'];
	$saved                     = fv_nbar_preferences_init([]);
	expect($saved['data_unit'])->toBe('Auto');
	expect($saved['rate_unit'])->toBe('Kbps');
	expect($saved['dns'])->toBe('0');
	expect($GLOBALS['fv_nbar_preferences_saved'])->toBeTrue();
});
