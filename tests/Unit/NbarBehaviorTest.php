<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_collector.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_connections.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_preferences.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_timespan.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'nbar-test-connection';
});

it('adds and compares byte totals without floating point rounding', function () {
	expect(fv_nbar_decimal_add('18446744073709551615', '99'))->toBe('18446744073709551714');
	expect(fv_nbar_decimal_add('000', '0'))->toBe('0');
	expect(fv_nbar_decimal_compare('099', '100'))->toBeLessThan(0);
	expect(fv_nbar_decimal_compare('000123', '123'))->toBe(0);
});

it('clips requested dates to retention without changing a valid selection', function () {
	$now = 1800000000;
	expect(fv_nbar_bound_dates($now - 7200, $now - 60, $now, 7))->toBe([$now - 7200, $now - 60, false]);
	expect(fv_nbar_bound_dates($now - 9 * 86400, $now + 60, $now, 7))->toBe([$now - 7 * 86400, $now, true]);
	expect(fv_nbar_bound_dates($now - 9 * 86400, $now - 8 * 86400, $now, 7))->toBe([$now - 1800, $now, true]);
});

it('uses local calendar days across daylight saving changes', function () {
	$old = date_default_timezone_get();

	try {
		date_default_timezone_set('Australia/Adelaide');
		[$from, $to] = fv_nbar_daily_bounds(strtotime('2026-10-04 12:00'));
		expect(date('Y-m-d H:i', $from))->toBe('2026-10-04 00:00');
		expect(date('Y-m-d H:i', $to))->toBe('2026-10-05 00:00');
		expect($to - $from)->toBe(23 * 3600);
	} finally {
		date_default_timezone_set($old);
	}
});

it('validates internal IPv4 and IPv6 prefixes and rejects invalid masks', function () {
	expect(fv_nbar_cidrs("192.0.2.0/24\n2001:db8::/64"))->toBe(['192.0.2.0/24', '2001:db8::/64']);
	expect(fv_nbar_cidrs('192.0.2.0/33'))->toBeFalse();
	expect(fv_nbar_cidrs('2001:db8::/129'))->toBeFalse();
	expect(fv_nbar_cidrs('example.com/24'))->toBeFalse();
	expect(fv_nbar_cidrs(''))->toBe([]);
});

it('decodes template-defined IPv4 and IPv6 application records using the dedicated connection', function () {
	$GLOBALS['listener_id'] = 3;
	$now                    = time();
	$slice                  = fv_nbar_data_table((int) (floor($now / 60) * 60));
	plugin_test_queue_db_result('db_fetch_cell', $slice);

	foreach ([4, 16] as $length) {
		$src         = inet_pton($length === 4 ? '192.0.2.10' : '2001:db8::10');
		$dst         = inet_pton($length === 4 ? '198.51.100.20' : '2001:db8:1::20');
		$fields      = [95 => 4, $length === 4 ? 8 : 27 => $length, $length === 4 ? 12 : 28 => $length, 7 => 2, 11 => 2, 4 => 1, 1 => 4, 2 => 4];
		$definitions = '';

		foreach ($fields as $id => $size) {
			$definitions .= pack('nn', $id, $size);
		}
		$template = pack('nnnn', 0, 8 + strlen($definitions), 400 + $length, count($fields)) . $definitions;
		$record   = hex2bin('0d000001') . $src . $dst . pack('nnCNN', 50000, 443, 6, 123456, 120);
		$data     = pack('nn', 400 + $length, 4 + strlen($record)) . $record;
		$packet   = pack('nnNNNN', 9, 2, 100000, $now, 1, 100 + $length) . $template . $data;
		fv_nbar_packet($packet, '192.0.2.1');
		$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared' && str_contains($c['sql'], 'INSERT INTO `' . $slice . '`')));
		$call  = end($calls);
		expect($call['conn'])->toBe('nbar-test-connection');
		expect($call['params'][2])->toBe('0d000001');
		expect($call['params'][3])->toBe(bin2hex($src));
		expect($call['params'][4])->toBe(bin2hex($dst));
		expect(array_slice($call['params'], 5))->toBe([50000, 443, 6, 123456, 120, '192.0.2.1']);
	}
});

it('ignores malformed or unsupported packets without writing data', function () {
	fv_nbar_packet('short', '192.0.2.1');
	fv_nbar_packet(pack('nnNNNN', 10, 0, 0, time(), 0, 0), '192.0.2.1');
	fv_nbar_packet(pack('nnNNNN', 9, 1, 0, time(), 0, 0) . pack('nn', 0, 65535), '192.0.2.1');
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('saves two or three combined captures without depending on a success message', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST                  = ['prefs_apply' => '1', 'combine_apply' => '1', 'local_prefixes' => '192.0.2.0/24', 'site_listeners' => ['1', '2']];
	$devices                   = [['id' => 1], ['id' => 2], ['id' => 3]];
	$saved                     = fv_nbar_preferences_init($devices);
	expect($GLOBALS['fv_nbar_preferences_saved'])->toBeTrue();
	expect($saved['listeners'])->toBe([1, 2]);
	expect($GLOBALS['fv_nbar_preferences_message'])->toBe('');
	$_REQUEST['site_listeners'] = ['1', '2', '3'];
	expect(fv_nbar_preferences_init($devices)['listeners'])->toBe([1, 2, 3]);
	$_SERVER['REQUEST_METHOD'] = 'GET';
});

it('rejects a single combined capture but permits saving networks with none selected', function () {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_REQUEST                  = ['prefs_apply' => '1', 'combine_apply' => '1', 'local_prefixes' => '2001:db8::/64', 'site_listeners' => ['1']];
	$devices                   = [['id' => 1], ['id' => 2]];
	fv_nbar_preferences_init($devices);
	expect($GLOBALS['fv_nbar_preferences_saved'])->toBeFalse();
	$_REQUEST['site_listeners'] = [];
	$saved                      = fv_nbar_preferences_init($devices);
	expect($GLOBALS['fv_nbar_preferences_saved'])->toBeTrue();
	expect($saved['listeners'])->toBe([]);
	expect($saved['prefixes'])->toBe(['2001:db8::/64']);
	$_SERVER['REQUEST_METHOD'] = 'GET';
});

it('rolls back a failed raw batch before publishing a summary revision', function () {
	plugin_test_queue_db_result('db_execute_prepared', false);
	expect(fv_nbar_summary_store([['INSERT fixture VALUES (?)', [123]]], 1, [1800000000 => true]))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('ROLLBACK');
	expect(implode(' ', $sql))->not->toContain('revision=revision+1');
});

it('escapes quotes and markup in attributes on Cacti 1.2', function () {
	expect(plugin_flowview_escape_attr('"<host>&'))->toBe('&quot;&lt;host&gt;&amp;');
});

it('decodes unsigned 64-bit exporter counters without rounding', function () {
	expect(fv_nbar_uint(hex2bin('7fffffffffffffff')))->toBe(PHP_INT_MAX);
	expect(fv_nbar_uint(hex2bin('8000000000000000')))->toBe('9223372036854775808');
	expect(fv_nbar_uint(hex2bin('ffffffffffffffff')))->toBe('18446744073709551615');
	expect(fv_nbar_uint(hex2bin('00000000000000ff')))->toBe(255);
});

it('passes full-width byte counters to the database as exact decimal strings', function () {
	$GLOBALS['listener_id'] = 3;
	$now                    = time();
	$slice                  = fv_nbar_data_table((int) (floor($now / 60) * 60));
	plugin_test_queue_db_result('db_fetch_cell', $slice);
	$fields      = [95 => 4, 8 => 4, 12 => 4, 1 => 8];
	$definitions = '';

	foreach ($fields as $id => $size) {
		$definitions .= pack('nn', $id, $size);
	}
	$template = pack('nnnn', 0, 8 + strlen($definitions), 777, count($fields)) . $definitions;
	$record   = hex2bin('0d000001') . inet_pton('192.0.2.10') . inet_pton('198.51.100.20') . hex2bin('ffffffffffffffff');
	$data     = pack('nn', 777, 4 + strlen($record)) . $record;
	fv_nbar_packet(pack('nnNNNN', 9, 2, 100000, $now, 1, 777) . $template . $data, '192.0.2.1');
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared' && str_contains($c['sql'], 'INSERT INTO `' . $slice . '`')));
	expect($calls)->toHaveCount(1);
	expect($calls[0]['params'][8])->toBe('18446744073709551615');
});

it('uses a single grouped fallback before probing a sparse history across every segment', function () {
	foreach (range(1, 3) as $unused) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	}

	for ($segment = 0; $segment < 4; $segment++) {
		$rows = [];

		for ($i = 0; $i < 257; $i++) {
			$rows[] = ['app_id' => '0d000001', 'src_hex' => 'C000020A', 'dst_hex' => strtoupper(bin2hex(inet_pton('198.18.0.' . ($segment + 1)))), 'sport' => $i, 'dport' => 443, 'protocol' => 6, 'bytes' => '100', 'packets' => '1', 'records' => '1', 'first_seen' => 1800000000, 'last_seen' => 1800000000];
		}
		plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	expect(fv_nbar_connections_direct([1], 1799971200, 1799985600, '1=1', [], 1))->toBe([]);
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared'));
	expect($calls)->toHaveCount(8);
	expect(end($calls)['sql'])->toContain('UNION ALL');
});
