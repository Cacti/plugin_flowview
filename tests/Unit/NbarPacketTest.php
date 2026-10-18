<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_collector.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['listener_id'] = 1;
	$this->header = pack('nnNNNN', 9, 2, 100000, time(), 1, 902);
});

it('learns an application name from a v9 options template', function () {
	$definitions = pack('nnnnnn', 1, 4, 95, 4, 96, 32);
	$template = pack('nnnnn', 1, 10 + strlen($definitions), 901, 4, 8) . $definitions;
	$record = inet_pton('192.0.2.1') . hex2bin('0d000001') . str_pad('youtube', 32, chr(0));
	$data = pack('nn', 901, 4 + strlen($record)) . $record;
	fv_nbar_packet($this->header . $template . $data, '192.0.2.91');
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
	expect($calls)->toHaveCount(1);
	expect($calls[0]['sql'])->toContain('INSERT INTO plugin_flowview_nbar_names');
	expect(array_slice($calls[0]['params'], 0, 3))->toBe(['192.0.2.91', '0d000001', 'youtube']);
});

it('ignores malformed template definitions without writing records', function ($definition) {
	fv_nbar_packet($this->header . $definition, '192.0.2.92');
	expect($GLOBALS['__test_db_calls'])->toBe([]);
})->with([pack('nnnn', 0, 8, 12, 1), pack('nnnn', 0, 8, 902, 1), pack('nnnnn', 1, 10, 903, 1, 0)]);

it('uses exporter uptime to date records and rejects expired observations', function ($expired) {
	$fields = [95 => 4, 8 => 4, 12 => 4, 1 => 4, 21 => 4];
	$definitions = '';
	foreach ($fields as $id => $size) {
		$definitions .= pack('nn', $id, $size);
	}
	$template = pack('nnnn', 0, 8 + strlen($definitions), 904, count($fields)) . $definitions;
	$record = hex2bin('0d000001') . inet_pton('192.0.2.10') . inet_pton('198.51.100.20') . pack('NN', 1000, 95000);
	$data = pack('nn', 904, 4 + strlen($record)) . $record;
	$now = time();
	fv_nbar_packet(pack('nnNNNN', 9, 2, 100000, $expired ? $now - 8 * 86400 : $now, 1, 904) . $template . $data, '192.0.2.94');
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
	if ($expired) {
		expect($calls)->toBe([]);
	} else {
		expect($calls)->toHaveCount(1);
		expect($calls[0]['params'][1])->toBe((int) (floor(($now - 5) / 60) * 60));
	}
})->with([false, true]);

it('ignores incomplete records and reports a failed record transaction', function ($reason) {
	$fields = $reason === 'application' ? [8 => 4, 12 => 4, 1 => 4] : [95 => 4, 8 => $reason === 'address' ? 1 : 4, 12 => 4, 1 => 4];
	$definitions = '';
	$record = '';
	foreach ($fields as $id => $size) {
		$definitions .= pack('nn', $id, $size);
		$record .= str_repeat(chr(1), $size);
	}
	$template = pack('nnnn', 0, 8 + strlen($definitions), 905, count($fields)) . $definitions;
	$data = pack('nn', 905, 4 + strlen($record)) . $record;
	if ($reason === 'transaction') {
		plugin_test_queue_db_result('db_execute', false);
	}
	fv_nbar_packet($this->header . $template . $data, '192.0.2.95');
	if ($reason === 'transaction') {
		expect(end($GLOBALS['__test_cacti_log']))->toBe('WARNING: NBAR record transaction failed');
	} else {
		expect($GLOBALS['__test_db_calls'])->toBe([]);
	}
})->with(['application', 'address', 'transaction']);

it('invalidates cached templates containing any zero-length field and recovers on a valid refresh', function ($options, $position) {
	$tid = 960 + $position + ($options ? 10 : 0);
	$router = '192.0.2.96';
	$fields = $options ? [[1, 4], [95, 4], [96, 32]] : [[95, 4], [8, 4], [12, 4], [1, 4]];
	$record = $options ? inet_pton('192.0.2.1') . hex2bin('0d000001') . str_pad('youtube', 32, chr(0)) : hex2bin('0d000001') . inet_pton('192.0.2.10') . inet_pton('198.51.100.20') . pack('N', 1000);
	$encode = static function ($fields) use ($options, $tid) {
		$definitions = implode('', array_map(fn ($f) => pack('nn', ...$f), $fields));
		return ($options ? pack('nnnnn', 1, 10 + strlen($definitions), $tid, 4, strlen($definitions) - 4) : pack('nnnn', 0, 8 + strlen($definitions), $tid, count($fields))) . $definitions;
	};
	$data = pack('nn', $tid, 4 + strlen($record)) . $record;
	fv_nbar_packet($this->header . $encode($fields) . $data, $router);
	expect($GLOBALS['__test_db_calls'])->not->toBe([]);
	plugin_test_reset();
	$invalid = $fields;
	array_splice($invalid, $position, 0, [[999, 0]]);
	fv_nbar_packet($this->header . $encode($invalid) . $data, $router);
	fv_nbar_packet($this->header . $data, $router);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
	fv_nbar_packet($this->header . $encode($fields) . $data, $router);
	expect($GLOBALS['__test_db_calls'])->not->toBe([]);
})->with([[false, 0], [false, 2], [false, 4], [true, 0], [true, 2], [true, 3]]);

it('rejects a large zero-length template before repeatedly parsing its data', function () {
	$definitions = str_repeat(pack('nn', 999, 0), 1999) . pack('nn', 95, 1);
	$template = pack('nnnn', 0, 8 + strlen($definitions), 990, 2000) . $definitions;
	$data = pack('nn', 990, 56004) . str_repeat('x', 56000);
	fv_nbar_packet($this->header . $template . $data, '192.0.2.99');
	fv_nbar_packet($this->header . $data,'192.0.2.99');
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});
