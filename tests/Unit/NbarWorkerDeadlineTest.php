<?php
beforeEach(function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_common.php');
});
it('launches maintenance without running aggregation in the poller process', function () {
	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';
	expect(fv_nbar_launch_maintenance())->toBeTrue();
	$calls = $GLOBALS['__test_db_calls'];
	expect($calls)->toHaveCount(1)->and($calls[0]['fn'])->toBe('exec_background')->and($calls[0]['params'][0])->toContain('--nbar-worker=supervise');
});
it('does not launch maintenance with a missing PHP binary', function () {
	expect(fv_nbar_launch_maintenance())->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});
it('supervises a shell-free child and always releases its process registration', function ($status) {
	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';
	plugin_test_queue_db_result('exec_with_timeout', $status);
	fv_nbar_supervise_maintenance();
	$calls = $GLOBALS['__test_db_calls'];
	expect(array_column($calls, 'fn'))->toBe(['register_process_start', 'exec_with_timeout', 'unregister_process']);
	expect($calls[1]['params'][1])->toBe(90)->and($calls[1]['params'][0][0])->toBe('/usr/bin/php')->and($calls[1]['params'][0][3])->toBe('--nbar-worker=build');
})->with([0, 124]);
it('does not start another child while maintenance is registered', function () {
	plugin_test_queue_db_result('register_process_start', false);
	fv_nbar_supervise_maintenance();
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});
it('defers unsupported hourly mutations outside a supervised child', function () {
	plugin_test_queue_db_result('db_fetch_cell', false);
	expect(fv_nbar_summary_build(1, 1800000000))->toBeFalse();
	expect(implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql')))->not->toContain('START TRANSACTION', 'INSERT INTO');
});
it('preserves MySQL hourly aggregation inside a supervised child', function () {
	plugin_test_queue_db_result('db_fetch_cell', false);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 1]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['revision' => 1]);
	expect(fv_nbar_summary_build(1, 1800000000, true))->toBeTrue();
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('INSERT INTO plugin_flowview_nbar_apps', 'COMMIT')->not->toContain('SET SESSION max_statement_time');
});
it('restores an established hourly deadline after a failed transaction setup', function () {
	plugin_test_queue_db_result('db_fetch_cell', '7.5');
	plugin_test_queue_db_result('db_execute', true);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_summary_build(1, 1800000000))->toBeFalse();
	$sql = array_column($GLOBALS['__test_db_calls'], 'sql');
	expect(end($sql))->toBe('SET SESSION max_statement_time=7.5');
});
it('uses identical tie ordering before and after SQL pagination', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_query.php');
	$sql = fv_nbar_connections_sql('fixture', '1=1', 2);
	expect($sql)->toContain('ORDER BY bytes DESC,d.app_id,COALESCE(n.name_id,0),d.src,d.dst,d.sport,d.dport,d.protocol,d.router LIMIT 101 OFFSET 100');
	expect($sql)->toContain('ORDER BY d.bytes DESC,d.app_id,d.name_id,d.src,d.dst,d.sport,d.dport,d.protocol,d.router');
});
