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
	plugin_test_queue_db_result('report_outcome', ['status' => 'retry']);
	plugin_test_queue_db_result('exec_with_timeout', 0);
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	expect(flowview_reports_run_pending())->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$exec = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'exec_with_timeout'));
	expect($exec)->toHaveCount(2)->and($exec[0]['params'][0])->toContain('--queue-id=51')->and($exec[1]['params'][0])->toContain('--queue-id=52');
	$deletes = array_values(array_filter($calls, fn ($c) => str_starts_with($c['sql'], 'DELETE FROM reports_queued')));
	expect($deletes)->toHaveCount(1)->and($deletes[0]['params'])->toBe([52, 'flowview']);
	$query = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared' && !str_starts_with($c['sql'], 'SELECT id,source_id')))[0];
	expect($query['sql'])->toContain('ORDER BY start_time,scheduled_time,id LIMIT 100');
});
it('stops the batch when schema readiness is lost instead of starting another child', function () {
	foreach ([true, true, true, false] as $ready) {
		plugin_test_queue_db_result('db_fetch_row', $ready ? ['Field' => 'enabled'] : []);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51], ['id' => 52]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
	plugin_test_queue_db_result('exec_with_timeout', 75);
	plugin_test_queue_db_result('report_outcome', ['status' => 'retry']);
	expect(flowview_reports_run_pending())->toBe(0);
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'))->toHaveCount(1);
});
it('does not dispatch beyond capacity or after a failed process count', function ($running) {
	for ($i = 0;$i < 2;$i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $running === false ? false : array_fill(0, $running, ['taskname' => 'child_7', 'taskid' => 0, 'pid' => getmypid()]));
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

it('reclaims a failed childs dead registration before dispatching the healthy occurrence', function () {
	if (!function_exists('posix_kill')) {
		$this->markTestSkipped('POSIX liveness is unavailable');
	}
	for ($i = 0;$i < 5;$i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51], ['id' => 52]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', []);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['taskname' => 'child_7', 'taskid' => 0, 'pid' => 2147483647]]);
	foreach ([51, 52] as $id) {
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => $id, 'source' => 'flowview', 'source_id' => $id - 44, 'run_command' => 'fixture', 'run_timeout' => 60]);
	}
	plugin_test_queue_db_result('exec_with_timeout', 75);
	plugin_test_queue_db_result('report_outcome', ['status' => 'retry']);
	plugin_test_queue_db_result('exec_with_timeout', 0);
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	expect(flowview_reports_run_pending())->toBe(1);
	$calls = $GLOBALS['__test_db_calls'];
	$deletes = array_values(array_filter($calls, fn ($c) => str_starts_with($c['sql'], 'DELETE FROM processes')));
	expect($deletes)->toHaveCount(1)->and($deletes[0]['params'])->toBe(['flowsched', 'child_7', 0, 2147483647]);
	expect(array_filter($calls, fn ($c) => $c['fn'] === 'exec_with_timeout'))->toHaveCount(2);
});
it('keeps live and uncertain child registrations and removes only confirmed dead ones', function () {
	if (!function_exists('posix_kill')) {
		$this->markTestSkipped('POSIX liveness is unavailable');
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [
		['taskname' => 'child_7', 'taskid' => 0, 'pid' => 2147483647],
		['taskname' => 'child_8', 'taskid' => 0, 'pid' => getmypid()],
		['taskname' => 'child_9', 'taskid' => 0, 'pid' => 0],
	]);
	expect(flowview_running_schedule_children())->toBe(2);
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE')))->toHaveCount(1);
	expect(flowview_schedule_pid_alive(getmypid()))->toBeTrue()->and(flowview_schedule_pid_alive(0))->toBeNull();
});
it('does not grant capacity when removal of a dead registration fails', function () {
	if (!function_exists('posix_kill')) {
		$this->markTestSkipped('POSIX liveness is unavailable');
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['taskname' => 'child_7', 'taskid' => 0, 'pid' => 2147483647]]);
	plugin_test_queue_db_result('db_execute_prepared', false);
	$GLOBALS['__test_cacti_log'] = [];
	expect(flowview_running_schedule_children())->toBe(1);
	expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('Unable to remove a dead FlowView child registration');
});

it('keeps a capacity reservation when a concurrent replacement makes dead-PID deletion affect no rows', function () {
	if (!function_exists('posix_kill')) {
		$this->markTestSkipped('POSIX liveness is unavailable');
	}
	for ($i = 0;$i < 2;$i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 52]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['taskname' => 'child_7', 'taskid' => 0, 'pid' => 2147483647]]);
	plugin_test_queue_db_result('db_affected_rows', 0);
	expect(flowview_reports_run_pending(1))->toBe(0);
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('exec_with_timeout');
	$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE FROM processes')));
	expect($deletes)->toHaveCount(1)->and($deletes[0]['params'])->toBe(['flowsched', 'child_7', 0, 2147483647]);
});

it('does not launch or reset an occurrence when another dispatcher already owns its claim', function () {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
	plugin_test_queue_db_result('db_affected_rows', 0);
	expect(flowview_reports_run(51))->toBeFalse();
	$calls = $GLOBALS['__test_db_calls'];
	expect(array_column($calls, 'fn'))->not->toContain('exec_with_timeout');
	$writes = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'db_execute_prepared'));
	expect($writes)->toHaveCount(1)->and($writes[0]['sql'])->toContain('AND status=?')
		->and($writes[0]['params'][4])->toBe('pending');
});

it('retries successful completion cleanup without rerunning or requeuing the delivered report', function ($firstDelete, $retryDelete) {
	$GLOBALS['__test_cacti_log'] = [];
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
	plugin_test_queue_db_result('exec_with_timeout', 0);
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	foreach ([true, $firstDelete, $retryDelete] as $result) {
		plugin_test_queue_db_result('db_execute_prepared', $result);
	}
	expect(flowview_reports_run(51))->toBe($firstDelete || $retryDelete);
	$calls = $GLOBALS['__test_db_calls'];
	$exec = array_filter($calls, fn ($c) => $c['fn'] === 'exec_with_timeout');
	$writes = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'db_execute_prepared'));
	expect($exec)->toHaveCount(1)->and($writes)->toHaveCount($firstDelete ? 2 : 3);
	foreach (array_slice($writes, 1) as $write) {
		expect($write['sql'])->toBe('DELETE FROM reports_queued WHERE id=? AND source=?')
			->and($write['params'])->toBe([51, 'flowview']);
	}
	$log = implode(' ', $GLOBALS['__test_cacti_log']);
	if (!$firstDelete && !$retryDelete) {
		expect($log)->toContain('queue repair is required', '51', '7', 'already completed');
	} else {
		expect($log)->not->toContain('queue repair is required');
	}
})->with([[true, true], [false, true], [false, false]]);

it('does not requeue an unresolved timeout or crash and reconciles only explicit child outcomes', function ($outcome, $success, $reset, $status) {
	$GLOBALS['__test_cacti_log'] = [];
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 1]);
	plugin_test_queue_db_result('report_outcome', $outcome);
	plugin_test_queue_db_result('exec_with_timeout', $status);
	plugin_test_queue_db_result('exec_with_timeout_result', false);
	expect(flowview_reports_run(51))->toBe($success);
	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
	expect($writes)->toHaveCount(($success || $reset) ? 2 : 1);
	if ($reset) {
		expect($writes[1]['params'])->toBe(['pending', 51, 'flowview', 'retry']);
	} elseif ($success) {
		expect($writes[1]['sql'])->toStartWith('DELETE FROM reports_queued');
	} else {
		expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('unresolved child outcome', '51');
	}
})->with([[['status' => 'running'], false, false], [false, false, false], [[], false, false], [['status' => 'complete'], true, false], [['status' => 'retry'], false, true]])->with([0, 75, 1, -1, 255]);

it('reconciles late complete and retry markers without losing queue ownership', function ($state, $reset, $claim, $success, $launches) {
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'status' => $state, 'run_command' => 'fixture', 'run_timeout' => 1]);
	foreach ([$reset, $claim] as $value) {
		plugin_test_queue_db_result('db_execute_prepared', $value);
	}
	expect(flowview_reports_run(51))->toBe($success);
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'))->toHaveCount($launches);
})->with([['complete', true, true, true, 0], ['retry', false, true, false, 0], ['retry', true, false, false, 0], ['retry', true, true, true, 1]]);

it('cleans late completed reports even when execution capacity is full', function () {
	for ($i = 0; $i < 3; $i++) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	}
	plugin_test_queue_db_result('completed_reports', [['id' => 51, 'source_id' => 7]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'status' => 'complete']);
	expect(flowview_reports_run_pending())->toBe(1);
	$exec = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout');
	expect($exec)->toHaveCount(0);
	$reads = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared' && str_contains($c['sql'], 'FROM processes'));
	expect($reads)->toHaveCount(0);
});

it('preserves legacy dispatch for other report sources', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'other-plugin', 'source_id' => 7, 'name' => 'Other report', 'run_command' => 'fixture', 'run_timeout' => 60]);
	expect(reports_run(51))->toBeTrue();
	$exec = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'));
	expect($exec)->toHaveCount(1)->and($exec[0]['params'])->toBe(['fixture --report-id=51', 60]);
});

it('uses the scheduler default when a report timeout is unset', function () {
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 0]);
	expect(flowview_reports_run(51))->toBeTrue();
	$exec = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'));
	expect($exec[0]['params'][1])->toBe(300);
});

it('continues executable work after a cleanup snapshot read fails', function () {
	$GLOBALS['__test_cacti_log'] = [];
	for ($i = 0; $i < 3; $i++) { plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']); }
	plugin_test_queue_db_result('completed_reports', false);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 51]]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 1]);
	plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
	expect(flowview_reports_run_pending())->toBe(1);
	expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('Unable to read completed FlowView occurrences');
});

it('retains its completed cleanup count when the executable snapshot read fails', function () {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('completed_reports', [['id' => 51, 'source_id' => 7]]);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(flowview_reports_run_pending())->toBe(1);
	expect(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'exec_with_timeout'))->toHaveCount(0);
});
