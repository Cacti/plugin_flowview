<?php
beforeEach(function () { plugin_test_reset(); });
it('keeps a pending occurrence untouched before a pre-1.3 runner claims it', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview']);
	expect(reports_run(51))->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute_prepared', 'exec_with_timeout');
	expect($GLOBALS['__test_saved_rows'])->toBe([]);
});
it('preserves an already claimed occurrence under a new pending queue id', function () {
	$original = ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'status' => 'running', 'start_time' => '2026-10-18 00:00:00', 'scheduled_time' => '2026-10-17 23:00:00', 'run_command' => 'php run_schedule.php', 'notification' => '{}'];
	plugin_test_queue_db_result('sql_save', 72);
	expect(flowview_retry_queued_report($original))->toBeTrue();
	$saved = $GLOBALS['__test_saved_rows'][0];
	expect($saved['table'])->toBe('reports_queued')->and($saved['row']['id'])->toBe(0)->and($saved['row']['status'])->toBe('pending');
	expect($saved['row'])->not->toHaveKey('start_time');
	foreach (['source_id', 'scheduled_time', 'run_command', 'notification'] as $key) {
		expect($saved['row'][$key])->toBe($original[$key]);
	}
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});
it('does not duplicate a row which is already pending', function () {
	expect(flowview_retry_queued_report(['id' => 51, 'source' => 'flowview', 'status' => 'pending']))->toBeTrue();
	expect($GLOBALS['__test_saved_rows'])->toBe([]);
});
it('rejects unrelated or unidentified queued rows', function ($row) {
	expect(flowview_retry_queued_report($row))->toBeFalse();
	expect($GLOBALS['__test_saved_rows'])->toBe([]);
})->with([[[]], [['id' => 51, 'source' => 'other']]]);
it('reports a failed preservation instead of claiming a successful retry', function () {
	plugin_test_queue_db_result('sql_save', false);
	expect(flowview_retry_queued_report(['id' => 51, 'source' => 'flowview', 'status' => 'running']))->toBeFalse();
});
it('continues normal pre-1.3 queue processing once the schema is ready', function () {
	global $config;
	$old = $config['base_path'];
	$root = sys_get_temp_dir() . '/flowview-shim-' . bin2hex(random_bytes(8));
	mkdir($root . '/lib', 0700, true);
	file_put_contents($root . '/lib/poller.php', '<?php // Shared core doubles are supplied by the test bootstrap.');
	try {
		$config['base_path'] = $root;
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'name' => 'Fixture', 'source_id' => 7, 'run_command' => 'fixture-command', 'run_timeout' => 60]);
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
		expect(reports_run(51))->toBeTrue();
		$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
		expect($sql)->toContain('UPDATE reports_queued', 'DELETE FROM reports_queued');
	} finally {
		$config['base_path'] = $old;
		unlink($root . '/lib/poller.php');
		rmdir($root . '/lib');
		rmdir($root);
	}
});
