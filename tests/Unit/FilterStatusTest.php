<?php

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'filter-database';
});

afterEach(function () {
	$GLOBALS['flowview_cnn'] = false;
});

it('displays enabled, disabled and unavailable states without missing-key warnings', function () {
	expect(flowview_filter_status(['enabled' => 'on']))->toBe(['class' => 'deviceUp', 'label' => 'Enabled'])
		->and(flowview_filter_status(['enabled' => '']))->toBe(['class' => 'deviceDisabled', 'label' => 'Disabled'])
		->and(flowview_filter_status([]))->toBe(['class' => 'deviceDisabled', 'label' => 'Unavailable']);
});

it('does not alter a missing filter table', function () {
	expect(flowview_filter_status_upgrade())->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute');
});

it('leaves an existing enabled column intact', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	expect(flowview_filter_status_upgrade())->toBeTrue();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute');
});

it('repairs legacy filters as enabled through the configured database', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	expect(flowview_filter_status_upgrade())->toBeTrue();
	$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_execute'));
	expect($writes)->toHaveCount(1)
		->and($writes[0]['sql'])->toBe("ALTER TABLE plugin_flowview_queries ADD COLUMN enabled char(2) NOT NULL DEFAULT 'on' AFTER name")
		->and($writes[0]['conn'])->toBe('filter-database');
});

it('rechecks concurrent upgrades and reports actual failures', function ($exists) {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', []);
	plugin_test_queue_db_result('db_fetch_row', $exists ? ['Field' => 'enabled'] : []);
	plugin_test_queue_db_result('db_execute', false);
	expect(flowview_filter_status_upgrade())->toBe($exists);
})->with([true, false]);

it('does not run a schedule when the state schema is unavailable', function () {
	expect(plugin_flowview_run_schedule(1, 1))->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_fetch_row_prepared');
});

it('does not prepare scheduled reports for missing or disabled filters', function ($query) {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', $query);
	expect(plugin_flowview_run_schedule(1, 1))->toBeTrue();
	$reads = array_filter($GLOBALS['__test_db_calls'], fn ($call) => $call['fn'] === 'db_fetch_row_prepared');
	expect($reads)->toHaveCount(2);
})->with([[[]], [['enabled' => '']], [['name' => 'legacy']]]);

it('continues preparing an enabled scheduled filter', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 7]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['enabled' => 'on', 'timespan' => 1]);
	plugin_test_queue_db_result('get_timespan', new RuntimeException('enabled report reached time selection'));
	expect(fn () => plugin_flowview_run_schedule(1, 1))->toThrow(RuntimeException::class, 'enabled report reached time selection');
});

it('preserves an existing disabled schedule filter with an explicit label', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', [['id' => 2, 'name' => 'Active', 'enabled' => 'on'], ['id' => 7, 'name' => 'Saved', 'enabled' => '']]);
	expect(flowview_schedule_filters(3))->toBe([2 => 'Active', 7 => 'Saved (Disabled)']);
	$call = $GLOBALS['__test_db_calls'][0];
	expect($call['params'])->toBe([3])->and($call['sql'])->toContain('SELECT query_id');
});
it('handles unavailable schedule choices without replacing the association', function () {
	plugin_test_queue_db_result('db_fetch_assoc_prepared', false);
	expect(flowview_schedule_filters(3))->toBe([]);
});

it('resets a queued time-selection exception between tests', function () {
	plugin_test_queue_db_result('get_timespan', new RuntimeException('fixture only'));
	plugin_test_reset();
	$span = [];
	get_timespan($span, 2000, 1, 0);
	expect($span)->toBe(['begin_now' => 200, 'end_now' => 2000]);
});

it('distinguishes prepared records, an empty period and a failed query', function ($data, $success) {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 3, 'title' => 'Fixture', 'format_file' => 'default']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['enabled' => 'on', 'timespan' => 1, 'name' => 'Fixture']);
	plugin_test_queue_db_result('get_timespan', ['begin_now' => 0, 'end_now' => 2000]);
	set_request_var('timespan', 'custom');
	set_request_var('query', 3);
	set_request_var('date1', date('Y-m-d H:i:s', 100));
	set_request_var('date2', date('Y-m-d H:i:s', 2000));
	$key = get_flowview_session_key(3, 100, 2000);
	$_SESSION['sess_flowdata'][$key] = ['timeout' => time() + 60, 'data' => $data];
	$GLOBALS['__test_cacti_log'] = [];
	expect(plugin_flowview_run_schedule(7, 51))->toBe($success);
	$formatted = in_array('reports_load_format_file', array_column($GLOBALS['__test_db_calls'], 'fn'), true);
	expect($formatted)->toBe(!empty($data['data']));
	if ($data === false || ($data['data'] ?? null) === false) {
		expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('retry');
	} elseif (empty($data['data'])) {
		expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('Returned not Data');
	}
})->with([
	[['table' => 'Fixture', 'data' => [['bytes' => 42]]], true],
	[['table' => 'Fixture', 'data' => []], true],
	[false, false],
	[['table' => 'Fixture', 'data' => false], false],
]);

it('retries failed filter lookups while consuming genuinely absent or disabled filters', function ($filter, $success) {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 3]);
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	expect(plugin_flowview_run_schedule(7, 51))->toBe($success);
})->with([[false, false], [[], true], [['enabled' => ''], true]]);

it('distinguishes uncached empty query execution from failure in scheduled preparation', function ($rows, $success, $catalog = 'normal', $details = 'normal', $threads = 1, $parallelResult = '[]') {
	$start = strtotime('2024-01-01 00:00:00 UTC');
	$end = $start + 3600;
	$query = ['id' => 3, 'name' => 'Fixture', 'enabled' => 'on', 'timespan' => 1, 'statistics' => 9, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 100];
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row', $details === 'normal' ? ['min_date' => date('Y-m-d H:i:s', $start), 'max_date' => date('Y-m-d H:i:s', $end), 'table_partition' => ''] : $details);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['query_id' => 3, 'title' => 'Fixture', 'format_file' => 'default']);
	plugin_test_queue_db_result('db_fetch_row_prepared', $query);
	plugin_test_queue_db_result('db_fetch_row_prepared', $query);
	plugin_test_queue_db_result('get_timespan', ['begin_now' => $start, 'end_now' => $end]);
	// arrays.php reads three choice lists each time; no result-session cache is seeded.
	for ($i = 0; $i < 6; $i++) {
		plugin_test_queue_db_result('db_fetch_assoc', []);
	}
	plugin_test_queue_db_result('db_fetch_assoc', $catalog === 'normal' ? [['table' => 'plugin_flowview_raw_202400000']] : $catalog);
	plugin_test_queue_db_result('db_fetch_assoc_prepared', $rows);
	$GLOBALS['__test_config']['flowview_partition'] = 1;
	$GLOBALS['__test_config']['flowview_parallel_threads'] = $threads;
	if ($threads > 1) {
		$GLOBALS['debug'] = false;
		$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 5;
		foreach (['Fixture', 71, 0, $parallelResult, ''] as $value) {
			plugin_test_queue_db_result('db_fetch_cell_prepared', $value);
		}
		plugin_test_queue_db_result('db_fetch_cell', 0);
	}
	expect(plugin_flowview_run_schedule(7, 51))->toBe($success);
	$queries = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_assoc_prepared'));
	if ($threads > 1) {
		$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_cell_prepared' && str_contains($c['sql'], 'SELECT results')));
		expect($reads)->toHaveCount(1)->and($reads[0]['params'])->toBe([71]);
	} elseif ($catalog === 'normal' && $details === 'normal') {
		expect($queries)->toHaveCount(1)->and($queries[0]['sql'])->toContain('FROM plugin_flowview_raw_202400000');
	} else {
		expect($queries)->toHaveCount(0);
		expect($_SESSION['sess_flowview_table_details'] ?? [])->not->toHaveKey('plugin_flowview_raw_202400000');
	}
	expect($_SESSION)->not->toHaveKey('sess_flowdata');
})->with([
	[[], true], [false, false],
	[[], false, false], [[], true, []],
	[[], false, 'normal', false], [[], false, 'normal', []],
	[[], true, 'normal', ['min_date' => null, 'max_date' => null, 'table_partition' => '']],
	[[], true, 'normal', 'normal', 2, '[]'],
	[[], false, 'normal', 'normal', 2, false],
	[[], false, 'normal', 'normal', 2, 'null'],
	[[], false, 'normal', 'normal', 2, 'invalid-json'],
]);

it('distinguishes failed and absent schedule lookups before reading a filter', function ($schedule, $success) {
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', $schedule);
	expect(plugin_flowview_run_schedule(7, 51))->toBe($success);
	$reads = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared');
	expect($reads)->toHaveCount(1);
})->with([[false, false], [[], true]]);
it('fails an uncached query cleanly when its own filter lookup fails or is absent', function ($filter) {
	plugin_test_queue_db_result('db_fetch_row_prepared', $filter);
	expect(run_flow_query(false, 3, 1704067200, 1704070800))->toBeFalse();
})->with([[false], [[]]]);

it('preserves two-request reduce lookup failures and cleans up both requests', function ($reduce, $success, $metadata = 'normal') {
	$GLOBALS['debug'] = false;
	$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 5;
	$query = ['sql_query' => 'SELECT SUM(bytes) bytes', 'sql_where' => '', 'sql_groupby' => '', 'sql_having' => '', 'sql_order' => '', 'sql_limit' => '', 'sql_params' => []];
	foreach ([0, 0, $reduce === 'valid' ? json_encode($query) : $reduce, '', ''] as $value) {
		plugin_test_queue_db_result('db_fetch_cell_prepared', $value);
	}
	plugin_test_queue_db_result('db_fetch_cell', 0);
	plugin_test_queue_db_result('db_fetch_assoc', $metadata === 'normal' ? [
		['map_table' => 'fixture_map_71', 'map_create' => 'CREATE TABLE fixture_map_71 (bytes bigint)', 'results' => '[]'],
		['map_table' => 'fixture_map_72', 'map_create' => 'CREATE TABLE fixture_map_72 (bytes bigint)', 'results' => '[]'],
	] : $metadata);
	expect(parallel_database_query_run([71, 72]))->toBe($success ? [] : false);
	$cleanup = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE FROM parallel_database_query_shard')));
	expect(array_column($cleanup, 'params'))->toBe([[71], [72]]);
})->with([[false, false], ['', false], ['null', false], ['bad-json', false], ['valid', true], ['valid', false, false], ['valid', false, []], ['{}', false]]);

it('returns a failed result after cleaning up timed out parallel requests', function () {
	$GLOBALS['debug'] = false;
	$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 0;
	foreach ([0, 0, '', ''] as $value) {
		plugin_test_queue_db_result('db_fetch_cell_prepared', $value);
	}
	expect(parallel_database_query_run([71, 72]))->toBeFalse();
	$cleanup = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE FROM parallel_database_query_shard')));
	expect(array_column($cleanup, 'params'))->toBe([[71], [72]]);
});
