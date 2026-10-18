<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
beforeEach(function () {
	plugin_test_reset();
	global $config;
	$this->oldBase = $config['base_path'];
	$this->root = sys_get_temp_dir() . '/flowview-batch-' . bin2hex(random_bytes(8));
	mkdir($this->root . '/lib', 0700, true);
	file_put_contents($this->root . '/lib/poller.php', '<?php');
	$config['base_path'] = $this->root;
});
afterEach(function () {
	global $config;
	$config['base_path'] = $this->oldBase;
	unlink($this->root . '/lib/poller.php');
	rmdir($this->root . '/lib');
	rmdir($this->root);
});
it('attempts a failed occurrence once and still dispatches the healthy row behind it', function () {
	for ($i = 0;$i < 5;$i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51], ['id' => 52]]);
	foreach ([51, 52] as $id) {
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => $id, 'source' => 'flowview', 'source_id' => $id - 44, 'run_command' => 'fixture', 'run_timeout' => 60]);
	}
	plugin_test_queue_db_result('exec_with_timeout', 75);
	plugin_test_queue_db_result('exec_with_timeout', 0);
	expect(flowview_reports_run_pending())->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$exec = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'exec_with_timeout'));
	expect($exec)->toHaveCount(2)->and($exec[0]['params'][0])->toContain('--queue-id=51')->and($exec[1]['params'][0])->toContain('--queue-id=52');
	$deletes = array_values(array_filter($calls, fn ($c) => str_starts_with($c['sql'], 'DELETE FROM reports_queued')));
	expect($deletes)->toHaveCount(1)->and($deletes[0]['params'])->toBe([52, 'flowview']);
	$query = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared'))[0];
	expect($query['sql'])->toContain('ORDER BY start_time,scheduled_time,id LIMIT 100');
});
it('stops the batch when schema readiness is lost instead of starting another child', function () {
	foreach ([true, true, true, false] as $ready) {
		plugin_test_queue_db_result('db_fetch_row', $ready ? ['Field' => 'enabled'] : []);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51], ['id' => 52]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
	plugin_test_queue_db_result('exec_with_timeout', 75);
	expect(flowview_reports_run_pending())->toBe(0);
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'))->toHaveCount(1);
});
it('does not dispatch beyond capacity or after a failed process count', function ($running) {
	for ($i = 0;$i < 2;$i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51]]);
	plugin_test_queue_db_result('db_fetch_cell_prepared', $running);
	expect(flowview_reports_run_pending(2))->toBe(0);
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('exec_with_timeout');
})->with([2, false]);
it('does not read a queue before schema readiness and does not ignore failed queue reads', function () {
	expect(flowview_reports_run_pending())->toBe(0);
	plugin_test_reset();
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(flowview_reports_run_pending(1, 100000))->toBe(0);
	$query = end($GLOBALS['__test_db_calls']);
	expect($query['sql'])->toContain('LIMIT 1000');
});
