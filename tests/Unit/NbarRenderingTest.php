<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_chart.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_feed_status.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_preferences.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_dns.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn']  = 'render-test-connection';
	$GLOBALS['lid']           = 1;
	$GLOBALS['combined_lids'] = [1, 2, 3];
});

it('escapes router names and reports freshness separately for three selected captures', function () {
	foreach ([1, 2, 3] as $unused) {
		plugin_test_queue_slices([fv_nbar_data_table(time())]);
	}
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => time()]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => time() - 600]);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	ob_start();
	fv_nbar_feed_status([
		['id' => 1, 'name' => '<router>'], ['id' => 2, 'name' => 'Second'],
		['id' => 3, 'name' => 'Third'], ['id'    => 4, 'name' => 'Excluded'],
	], [1, 2, 3]);
	$html = ob_get_clean();
	expect($html)->toContain('&lt;router&gt;: Receiving', 'Second: No recent NBAR', 'Third: No recent NBAR', 'No retained NBAR records');
	expect($html)->not->toContain('<router>', 'Excluded');
	expect(substr_count($html, 'fa-check-circle'))->toBe(1);
	expect(substr_count($html, 'fa-times-circle'))->toBe(2);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(6);
});

it('renders an explicit empty status when no capture is selected', function () {
	ob_start();
	fv_nbar_feed_status([['id' => 1, 'name' => 'Router']], []);
	expect(ob_get_clean())->toContain('No captures selected');
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('does not mark future-dated records as a receiving feed', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => time() + 600]);
	ob_start();
	fv_nbar_feed_status([['id' => 1, 'name' => 'Router']], [1]);
	expect(ob_get_clean())->toContain('No recent NBAR');
});

it('shows the top five application totals plus Other without exposing application markup', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [
		['name' => '<script>alert(1)</script>', 'bytes' => 2000000000],
		['name' => 'Two', 'bytes' => 4000000], ['name'  => 'Three', 'bytes' => 3000000],
		['name' => 'Four', 'bytes' => 2000000], ['name' => 'Five', 'bytes' => 1000000],
		['name' => 'Six', 'bytes' => 500000], ['name'   => 'Zero', 'bytes' => 0],
	]);
	ob_start();
	fv_nbar_chart('d.listener_id=?', [1, 1800000000, 1800001800], 'all');
	$html = ob_get_clean();
	expect($html)->toContain('&lt;script&gt;', '2.00 GB', 'Other', '0.50 MB', 'conic-gradient');
	expect($html)->not->toContain('<script>', '>Six<');
	expect(substr_count($html, '<li>'))->toBe(6);
});

it('uses the host and IP version filters in the chart query', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	ob_start();
	fv_nbar_chart('d.listener_id=?', [1, 1800000000, 1800001800], '6', '2001:db8::10');
	expect(ob_get_clean())->toContain('No application data in this period');
	$call = end($GLOBALS['__test_db_calls']);
	expect($call['sql'])->toContain('LENGTH(d.src)=16', 'd.src=INET6_ATON(?) OR d.dst=INET6_ATON(?)');
	expect($call['params'])->toBe([1, 1800000000, 1800001800, '2001:db8::10', '2001:db8::10']);
});

it('renders date cells with escaped labels and explicit unavailable observations', function () {
	ob_start();
	$html = fv_nbar_seen_cells(['first_seen' => 1800000000, 'last_seen' => null]);
	ob_end_clean();
	expect($html)->toContain('data-text="1800000000"', 'minute precision', 'Unavailable');
	expect(substr_count($html, '<td '))->toBe(2);
});

it('escapes names, prefixes and dynamic settings in the Options markup', function () {
	$GLOBALS['fv_nbar_preferences']         = ['prefixes' => ['192.0.2.0/24'], 'listeners' => [1, 2], 'data_unit' => 'Auto', 'rate_unit' => 'Mbps', 'dns' => '0'];
	$GLOBALS['fv_nbar_preferences_message'] = '<failure>';
	ob_start();
	fv_nbar_preferences_render([['id' => 1, 'name' => '<router>'], ['id' => 2, 'name' => 'Second']], '', '', '', '');
	$html = ob_get_clean();
	expect($html)->toContain('&lt;router&gt;', '&lt;failure&gt;', 'Internal Networks', '192.0.2.0/24', 'window.fvNbarDisplay=', '"selection":"{count} of 3 selected"');
	expect($html)->not->toContain('<router>', '<failure>');
	expect(substr_count($html, ' checked'))->toBe(2);
});

it('labels conversation directions, observation dates and the application drilldown', function () {
	$GLOBALS['__test_translations']['flowview']['Top 100 conversations by combined data volume. Each row groups an Internal Host, External Host and application across both directions. Uses only the selected LAN captures to avoid counting WAN copies. Local and multicast traffic is excluded. DNS names identify servers, not the exact website requested. History retention: %d days.'] = '<localized> retention %d days.';
	$GLOBALS['fv_nbar_preferences']                                                                                                                                                                                                                                                                                                                                                           = ['prefixes' => ['192.0.2.0/24'], 'dns' => '0'];
	$nbar_preferences                                                                                                                                                                                                                                                                                                                                                                         = $GLOBALS['fv_nbar_preferences'];
	$listener_scope                                                                                                                                                                                                                                                                                                                                                                           = 'd.listener_id=?';
	$lid                                                                                                                                                                                                                                                                                                                                                                                      = 1;
	$combined_lids                                                                                                                                                                                                                                                                                                                                                                            = [];
	$start                                                                                                                                                                                                                                                                                                                                                                                    = 1800000000;
	$end                                                                                                                                                                                                                                                                                                                                                                                      = 1800001800;
	$seconds                                                                                                                                                                                                                                                                                                                                                                                  = 1800;
	$family                                                                                                                                                                                                                                                                                                                                                                                   = '4';
	$aid                                                                                                                                                                                                                                                                                                                                                                                      = '0d000001';
	$host                                                                                                                                                                                                                                                                                                                                                                                     = '192.0.2.10';
	$base                                                                                                                                                                                                                                                                                                                                                                                     = 'flowview.php?report=nbar';
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['app_id' => $aid, 'name' => '<app>', 'internal' => $host, 'external' => '198.51.100.20', 'download' => 2000000, 'upload' => 10000, 'first_seen' => $start, 'last_seen' => $end - 60]]);
	ob_start();
	include(dirname(__DIR__, 2) . '/nbar_conversations.php');
	$html = ob_get_clean();
	expect($html)->toContain('Internal Host', 'External Host', '&lt;app&gt;', 'parent_app=0d000001', 'mode=connections', 'Back to top applications', 'First seen', 'Last seen');
	expect($html)->toContain('&lt;localized&gt; retention ' . fv_nbar_retention_days() . ' days.')->not->toContain('<localized>', 'Top 100 conversations by combined data volume.');
	expect($html)->not->toContain('<app>');
	$call = end($GLOBALS['__test_db_calls']);
	expect($call['params'])->toBe([1, $start, $end, $aid, $host, $host]);
	expect($call['sql'])->toContain('NOT', 'LENGTH(d.src)=4');
});

it('explains why conversation classification needs internal networks', function () {
	$nbar_preferences = ['prefixes' => []];
	ob_start();
	include(dirname(__DIR__, 2) . '/nbar_conversations.php');
	expect(ob_get_clean())->toContain('Configure your site networks');
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('shows an empty conversation table when no matching records exist', function () {
	$GLOBALS['fv_nbar_preferences'] = ['prefixes' => ['2001:db8::/64'], 'dns' => '0'];
	$nbar_preferences               = $GLOBALS['fv_nbar_preferences'];
	$listener_scope                 = 'd.listener_id=?';
	$lid                            = 1;
	$combined_lids                  = [];
	$start                          = 1800000000;
	$end                            = 1800001800;
	$seconds                        = 1800;
	$family                         = 'all';
	$aid                            = '';
	$host                           = '';
	$base                           = 'flowview.php?report=nbar';
	ob_start();
	include(dirname(__DIR__, 2) . '/nbar_conversations.php');
	expect(ob_get_clean())->toContain('No conversations for this period');
});

it('groups chart rows by the complete fallback label for missing names', function () {
	ob_start();
	fv_nbar_chart('d.listener_id=?', [1, 1800000000, 1800001800], 'all');
	ob_end_clean();
	$queries = array_column($GLOBALS['__test_db_calls'], 'sql');
	$sql     = implode(' ', $queries);
	expect($sql)->toContain("GROUP BY COALESCE(NULLIF(n.name,''),CONCAT('Application ',d.app_id)) ORDER BY bytes DESC");
	expect($sql)->not->toContain('GROUP BY name ORDER BY');
});
