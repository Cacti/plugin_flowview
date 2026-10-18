<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/nbar_common.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'nbar-schema-test';
});

it('defines each NBAR table through the connection-aware schema contract', function ($suffix) {
	$definition = fv_nbar_schema_definition($suffix);
	expect($definition['type'])->toBe('InnoDB');
	$columns = array_column($definition['columns'], null, 'name');
	foreach (explode('`,`', $definition['primary']) as $key) {
		expect($columns)->toHaveKey($key);
		expect($columns[$key]['NULL'])->toBeFalse();
	}
	foreach ($definition['keys'] as $index) {
		foreach ($index['columns'] as $key) {
			expect($columns)->toHaveKey($key);
		}
	}
	plugin_test_queue_db_result('db_fetch_assoc', []);
	plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_' . $suffix);
	expect(fv_nbar_schema_table($suffix))->toBeTrue();
	$calls = $GLOBALS['__test_db_calls'];
	$create = array_values(array_filter($calls, fn ($call) => str_starts_with($call['sql'], 'CREATE TABLE')));
	expect($create)->toHaveCount(1);
	expect($create[0]['conn'])->toBe('nbar-schema-test');
	$ownership = array_values(array_filter($calls, fn ($call) => str_contains($call['sql'], 'REPLACE INTO plugin_db_changes')));
	expect($ownership)->toHaveCount(1);
	expect($ownership[0]['params'])->toBe(['flowview', 'plugin_flowview_nbar_' . $suffix]);
})->with(['names', 'data', 'hours', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections', 'days', 'seen_upgrade']);

it('rejects unknown schema names and detects failed table creation', function () {
	foreach (['other', 'daily_names', 'apps; DROP TABLE fixture'] as $suffix) {
		expect(fn () => fv_nbar_schema_table($suffix))->toThrow(InvalidArgumentException::class);
	}
	expect($GLOBALS['__test_db_calls'])->toBe([]);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_schema_table('apps'))->toBeFalse();
	expect(implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql')))->not->toContain('REPLACE INTO plugin_db_changes');
});

it('does not recreate or reclaim an existing table', function () {
	$table = 'plugin_flowview_nbar_data';
	plugin_test_queue_db_result('db_fetch_assoc', [['Tables_in_flow' => $table]]);
	plugin_test_queue_db_result('db_fetch_cell', $table);
	expect(fv_nbar_schema_table('data'))->toBeTrue();
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('CREATE TABLE', 'REPLACE INTO plugin_db_changes');
});


it('installs the complete schema and upgrades missing columns on the flow connection', function () {
	foreach (['names', 'data', 'hours', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections', 'days'] as $suffix) {
		plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_' . $suffix);
	}
	plugin_test_queue_db_result('db_fetch_cell', '');
	plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_seen_upgrade');
	expect(fv_nbar_schema_install())->toBeTrue();
	$alter = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_add_column'));
	expect($alter)->toHaveCount(15);
	foreach ($alter as $call) {
		expect($call['conn'])->toBe('nbar-schema-test');
	}
	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->toContain('SELECT 0,listener_id,hour', 'SELECT 1,listener_id,day');
	expect($alter[0]['params']['name'])->toBe('connection_built');
	expect($alter[14]['params']['name'])->toBe('app_cursor');
});

it('stops schema installation and migration when a database operation fails', function () {
	expect(fv_nbar_schema_install())->toBeFalse();
	plugin_test_reset();
	plugin_test_queue_db_result('db_add_column', false);
	expect(fv_nbar_seen_schema())->toBeFalse();
	$alter = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_add_column'));
	expect($alter)->toHaveCount(1);
});
