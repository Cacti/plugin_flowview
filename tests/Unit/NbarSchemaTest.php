<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

/**
 * Run flowview_db_create_nbar_tables() against the stubbed database, reporting every table as present.
 *
 * @return array<string, string> The CREATE statement issued for each table.
 */
function flowview_test_create_nbar_tables(): array {
	foreach (flowview_test_nbar_tables() as $table) {
		plugin_test_queue_db_result('db_fetch_cell', $table);
	}

	expect(flowview_db_create_nbar_tables())->toBeTrue();

	$created = [];

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if ($call['fn'] === 'db_execute' && str_starts_with($call['sql'], 'CREATE TABLE')) {
			preg_match('/`(plugin_flowview_nbar_[a-z_]+)`/', $call['sql'], $match);
			$created[$match[1]] = $call['sql'];
		}
	}

	return $created;
}

/**
 * The NBAR tables, in creation order.
 *
 * @return list<string>
 */
function flowview_test_nbar_tables(): array {
	return array_map(
		fn ($suffix) => 'plugin_flowview_nbar_' . $suffix,
		['names', 'data', 'hours', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections', 'days', 'seen_upgrade']
	);
}

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'nbar-schema-test';
});

it('creates every NBAR table with one standard CREATE TABLE IF NOT EXISTS on the flow connection', function () {
	$created = flowview_test_create_nbar_tables();

	expect(array_keys($created))->toBe(flowview_test_nbar_tables());

	foreach ($GLOBALS['__test_db_calls'] as $call) {
		if ($call['fn'] === 'db_execute') {
			expect($call['conn'])->toBe('nbar-schema-test');
		}
	}

	foreach ($created as $table => $sql) {
		expect($sql)->toStartWith('CREATE TABLE IF NOT EXISTS `' . $table . '`')
			->toContain('PRIMARY KEY (', 'ENGINE=InnoDB', "COMMENT='Plugin Flowview - NBAR");
	}
});

it('creates the final schema directly with no follow-up ALTER, ownership or column upgrade calls', function () {
	flowview_test_create_nbar_tables();

	$sql = implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql'));
	expect($sql)->not->toContain('ALTER TABLE', 'plugin_db_changes', 'INSERT IGNORE');
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_add_column');
});

it('keeps the exporter identity as the last primary key column of every raw and summary table', function ($suffix) {
	$sql = flowview_test_create_nbar_tables()['plugin_flowview_nbar_' . $suffix];

	expect($sql)->toMatch('/PRIMARY KEY \([^)]*,`router`\)/')
		->toContain("`router` varchar(45) NOT NULL DEFAULT ''");
})->with(['data', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections']);

it('includes the observation dates and indexes that earlier builds added with ALTER TABLE', function () {
	$created = flowview_test_create_nbar_tables();

	foreach (['apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections'] as $suffix) {
		expect($created['plugin_flowview_nbar_' . $suffix])->toContain('`first_seen` int(10) unsigned DEFAULT NULL', '`last_seen` int(10) unsigned DEFAULT NULL');
	}
	expect($created['plugin_flowview_nbar_hours'])->toContain('`connection_built` bigint(20) DEFAULT -1');
	expect($created['plugin_flowview_nbar_daily_connections'])->toContain('KEY `capture_day_bytes` (`listener_id`,`hour`,`bytes`)');
	expect($created['plugin_flowview_nbar_seen_upgrade'])->toContain('`phase` tinyint(3) unsigned NOT NULL DEFAULT 0', '`app_cursor` varchar(32) DEFAULT NULL');
});

it('stops and reports failure as soon as a table cannot be verified', function () {
	plugin_test_queue_db_result('db_fetch_cell', 'plugin_flowview_nbar_names');
	plugin_test_queue_db_result('db_fetch_cell', '');

	expect(flowview_db_create_nbar_tables())->toBeFalse();

	$creates = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_execute' && str_starts_with($call['sql'], 'CREATE TABLE'));
	expect($creates)->toHaveCount(2);
});
