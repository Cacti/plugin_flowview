<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_connections.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'paging-test';
	$this->start             = (int) floor((time() - 86400) / 3600) * 3600;
	$this->tuple             = ['app_id' => '0d000001', 'src_hex' => bin2hex(inet_pton('2001:db8::10')), 'dst_hex' => bin2hex(inet_pton('2001:db8::20')), 'sport' => 1000, 'dport' => 443, 'protocol' => 6, 'bytes' => '9007199254740993', 'packets' => '5', 'records' => '1', 'first_seen' => $this->start, 'last_seen' => $this->start + 60];
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['hour' => $this->start], ['hour' => $this->start + 3600]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['listener_id' => 1, 'app_id' => '0d000001', 'name_id' => 1, 'name' => 'youtube']]);
});

it('totals every candidate across all segments without rounding large counters', function () {
	$a    = $this->tuple;
	$b    = array_replace($a, ['sport' => 2000, 'bytes' => '10']);
	$late = array_replace($a, ['bytes' => '2', 'first_seen' => $this->start + 3600, 'last_seen' => $this->start + 3660]);

	foreach ([[$a, $b], [$late], [$a, $b], [$late]] as $rows) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	}
	$rows = fv_nbar_connections_direct([1], $this->start, $this->start + 7200, 'd.app_id=?', ['0d000001'], 1);
	expect($rows)->toHaveCount(2);
	expect($rows[0]['bytes'])->toBe('9007199254740995');
	expect($rows[0]['packets'])->toBe('10');
	expect($rows[0]['records'])->toBe('2');
	expect($rows[0]['name'])->toBe('youtube');
	expect($rows[0]['src'])->toBe('2001:db8::10');
	expect($rows[0]['dst'])->toBe('2001:db8::20');
	expect($rows[0]['first_seen'])->toBe($this->start);
	expect($rows[0]['last_seen'])->toBe($this->start + 3660);
	expect($rows[0])->not->toHaveKey('src_hex')->not->toHaveKey('_key');
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('COMMIT');
	$lookups = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => str_contains($call['sql'], 'UNHEX(?)')));
	expect($lookups)->toHaveCount(2);
	expect($lookups[0]['params'][0])->toBe('0d000001');
});

it('uses deterministic tuple ordering for equal-volume connections and preserves missing dates', function () {
	$a = array_replace($this->tuple, ['bytes' => '10']);
	$b = array_replace($a, ['sport' => 999, 'first_seen' => null, 'last_seen' => null]);

	foreach ([[$a, $b], [], [$a, $b], []] as $rows) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	}
	$rows = fv_nbar_connections_direct([1], $this->start, $this->start + 7200, '1=1', [], 1);
	expect(array_column($rows, 'sport'))->toBe([999, 1000]);
	expect($rows[0]['first_seen'])->toBeNull();
	expect($rows[0]['last_seen'])->toBeNull();
});

it('rolls back if segment reads or candidate total lookups fail', function ($stage) {
	if ($stage === 'candidate') {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	} else {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', [$this->tuple]);
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
		plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	}
	expect(fn () => fv_nbar_connections_direct([1], $this->start, $this->start + 7200, '1=1', [], 1))->toThrow(RuntimeException::class);
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('ROLLBACK');
})->with(['candidate', 'lookup']);

it('rejects invalid capture selections before opening a snapshot', function ($captures) {
	expect(fn () => fv_nbar_connections_direct($captures, $this->start, $this->start + 7200, '1=1', [], 1))->toThrow(InvalidArgumentException::class);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
})->with([[[]], [[1, 2, 3, 4]]]);

it('uses a complete daily checkpoint between raw partial-day boundaries', function () {
	plugin_test_reset();
	$day = strtotime('-2 days midnight');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['day' => $day, 'covered_to' => $day + 86400, 'timezone' => date_default_timezone_get(), 'revision' => '10']]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 24, 'ready' => 24, 'rev' => '10']);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	$plan = fv_nbar_connection_plan([2], $day - 1800, $day + 88200, time());
	expect($plan)->toHaveCount(3);
	expect(array_column($plan, 'daily'))->toBe([false, true, false]);
	expect($plan[1]['source'])->toContain('plugin_flowview_nbar_daily_connections', 'listener_id=2', 'hour=' . $day);
	expect($plan[0]['source'])->toContain('minute>=' . ($day - 1800), 'minute<' . $day);
	expect($plan[2]['source'])->toContain('minute>=' . ($day + 86400), 'minute<' . ($day + 88200));
});

it('falls back to raw hours when a daily checkpoint is stale or uses another timezone', function ($reason) {
	plugin_test_reset();
	$day = strtotime('-2 days midnight');
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['day' => $day, 'covered_to' => $day + 86400, 'timezone' => $reason === 'timezone' ? 'Other/Zone' : date_default_timezone_get(), 'revision' => '10']]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 24, 'ready' => 24, 'rev' => '11']);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	$plan = fv_nbar_connection_plan([2], $day, $day + 86400, time());
	expect($plan)->toHaveCount(24);

	foreach ($plan as $part) {
		expect($part['daily'])->toBeFalse();
		expect($part['source'])->toContain('plugin_flowview_nbar_data');
	}
})->with(['timezone', 'revision']);

it('rejects failed planning metadata instead of returning an empty report', function ($stage) {
	plugin_test_reset();

	if ($stage === 'hour') {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(fn () => fv_nbar_connection_plan([1], $this->start, $this->start + 7200, time()))->toThrow(RuntimeException::class);
})->with(['day', 'hour']);

it('keeps exporter name ranks ahead of endpoints when candidate pages contain ties', function () {
	plugin_test_reset();
	$names  = [];
	$ranks  = [3, 1, 0, 2];
	$labels = ['Zulu', 'Alpha', null, 'Bravo'];

	foreach ([0, 1, 3] as $i) {
		$names[] = ['listener_id' => 1, 'router' => '192.0.2.' . (201 + $i), 'app_id' => '0d000001', 'name_id' => $ranks[$i], 'name' => $labels[$i]];
	}
	$tuples = [];

	for ($i = 0; $i < 256; $i++) {
		$tuples[] = array_replace($this->tuple, ['router' => '192.0.2.' . (201 + $i % 4), 'sport' => 1000 + intdiv($i, 4), 'bytes' => $i < 101 ? '10' : '1']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['hour' => $this->start]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $names);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', array_merge($tuples, [array_replace($this->tuple, ['sport' => 9000, 'bytes' => '1'])]));
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $tuples);
	$rows     = fv_nbar_connections_direct([1], $this->start, $this->start + 3600, '1=1', [], 1);
	$expected = array_slice($tuples, 0, 101);
	usort($expected, function ($a, $b) use ($ranks) {
		$ai = (int) substr($a['router'], -3) - 201;
		$bi = (int) substr($b['router'], -3) - 201;

		return ($ranks[$ai] <=> $ranks[$bi]) ?: ($a['sport'] <=> $b['sport']);
	});
	expect(array_column($rows, 'router'))->toBe(array_column($expected, 'router'));
	expect(array_column($rows, 'sport'))->toBe(array_column($expected, 'sport'));
	expect($rows[0]['name'])->toBeNull()->and($rows[100]['name'])->toBe('Zulu');

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		expect($call['sql'])->not->toContain('OFFSET');
	}
});

it('does not add an unrequested tuple returned by a candidate lookup', function () {
	$unexpected = array_replace($this->tuple, ['sport' => 9999, 'bytes' => '9999999999999999999']);

	foreach ([[$this->tuple], [], [$this->tuple, $unexpected], []] as $rows) {
		plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	}
	$rows = fv_nbar_connections_direct([1], $this->start, $this->start + 7200, '1=1', [], 1);
	expect($rows)->toHaveCount(1)->and($rows[0]['sport'])->toBe($this->tuple['sport'])->and($rows[0]['bytes'])->toBe($this->tuple['bytes']);
});
