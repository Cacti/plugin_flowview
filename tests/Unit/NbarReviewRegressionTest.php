<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
beforeEach(function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_count.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_connections.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_query.php');
	$GLOBALS['flowview_cnn'] = 'review-database';
});
it('selects an exact count deadline for the actual flow database', function ($version, $fragment) {
	plugin_test_queue_db_result('db_fetch_cell', $version);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['total' => 17]);
	expect(fv_nbar_count_query('SELECT COUNT(*) total FROM fixture', [3]))->toBe(['total' => 17]);
	$call = end($GLOBALS['__test_db_calls']);
	expect($call['sql'])->toContain($fragment)->and($call['params'])->toBe([3])->and($call['conn'])->toBe('review-database');
})->with([['11.8.9-MariaDB', 'SET STATEMENT max_statement_time=90 FOR SELECT'], ['8.0.44', 'SELECT /*+ MAX_EXECUTION_TIME(90000) */']]);
it('does not execute an unbounded count when the backend is unknown', function () {
	expect(fv_nbar_count_query('SELECT 1', []))->toBeFalse();
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});
it('defers DML when a deadline is unsupported or cannot be set', function ($value) {
	plugin_test_queue_db_result('db_fetch_cell', $value);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_worker_timeout(20))->toBeNull();
})->with([false, '-1', '4.5']);
it('does not mutate daily data without a verified statement deadline', function () {
	plugin_test_queue_db_result('db_fetch_cell', false);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['n' => 1, 'ready' => 1, 'rev' => '1']);
	expect(fv_nbar_daily_build(1, 1800000000, 1800003600))->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('ROLLBACK')->not->toContain('INSERT INTO', 'DELETE FROM');
});
it('retains exporter identity in all raw and summary primary keys', function () {
	foreach (['data', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections'] as $suffix) {
		$definition = fv_nbar_schema_definition($suffix);
		expect($definition['primary'])->toEndWith('router');
		expect(end($definition['columns'])['name'])->toBe('router');
	}
});
it('repairs legacy exporter keys and leaves already upgraded tables intact', function () {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'router']);
	expect(fv_nbar_exporter_schema())->toBeTrue();
	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_execute'));
	expect($writes)->toHaveCount(6)->and($writes[0]['sql'])->toContain('ADD COLUMN router', 'DROP PRIMARY KEY', 'router`)');
});
it('does not publish a failed exporter upgrade', function () {
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_exporter_schema())->toBeFalse();
});
it('does not merge matching connection tuples from distinct exporters', function () {
	$row = ['app_id' => 'a1', 'src_hex' => 'C0000201', 'dst_hex' => 'C6336401', 'sport' => 10, 'dport' => 443, 'protocol' => 6, 'router' => '192.0.2.1'];
	$names = [1 => ['192.0.2.1:a1' => ['name_id' => 1], '192.0.2.2:a1' => ['name_id' => 2]]];
	$key = fv_nbar_connection_key($row, $names, 1);
	$row['router'] = '192.0.2.2';
	expect($key)->not->toBe(fv_nbar_connection_key($row, $names, 1));
});
it('retries checkpoint seeds after a previous schema creation succeeded', function () {
	foreach (range(1, 14) as $unused) {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'present']);
	}
	plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_seen_upgrade');
	expect(fv_nbar_seen_schema())->toBeTrue();
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('SELECT 0,listener_id,hour', 'SELECT 1,listener_id,day')->not->toContain('CREATE TABLE');
});
it('renders a chart failure without aborting the report', function () {
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_chart.php');
	$GLOBALS['lid'] = 1;
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	ob_start();
	fv_nbar_chart('d.listener_id=?', [1, 100, 200], 'all');
	expect(ob_get_clean())->toContain('Unable to load application chart');
});
it('does not change session isolation when its original value is unavailable', function () {
	foreach ([1, false, false, 1] as $value) {
		plugin_test_queue_db_result('db_fetch_cell', $value);
	}
	expect(fv_nbar_seen_backfill())->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('transaction_isolation', 'tx_isolation', 'RELEASE_LOCK')->not->toContain('SET SESSION');
});
it('defers date backfill when DML limits are unsupported', function () {
	foreach ([1, 'REPEATABLE-READ', 1, false] as $value) {
		plugin_test_queue_db_result('db_fetch_cell', $value);
	}
	expect(fv_nbar_seen_backfill())->toBe(0);
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('READ COMMITTED', 'UPDATE plugin_flowview');
});
