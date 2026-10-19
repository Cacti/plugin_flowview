<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeAll(function () {
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_partition.php');
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'nbar-partition-test';
});

it('names hourly slices by default and daily slices when the scheme is daily', function () {
	$time = gmmktime(5, 30, 0, 3, 1, 2026);
	expect(fv_nbar_data_table($time))->toBe('plugin_flowview_nbar_data_202605905');
	$GLOBALS['__test_config']['flowview_partition'] = '1';
	expect(fv_nbar_data_table($time))->toBe('plugin_flowview_nbar_data_202605905');
	$GLOBALS['__test_config']['flowview_partition'] = '0';
	expect(fv_nbar_data_table($time))->toBe('plugin_flowview_nbar_data_2026059');
	expect(fv_nbar_data_table(gmmktime(23, 59, 59, 12, 31, 2024)))->toBe('plugin_flowview_nbar_data_2024365');
	expect(fv_nbar_data_table(gmmktime(0, 0, 0, 1, 1, 2026)))->toBe('plugin_flowview_nbar_data_2026000');
});

it('names slices by UTC so the repeated daylight saving hour cannot share a table', function () {
	$old = date_default_timezone_get();

	try {
		foreach (['Australia/Adelaide', 'America/New_York', 'UTC'] as $zone) {
			date_default_timezone_set($zone);
			expect(fv_nbar_data_table(gmmktime(5, 30, 0, 3, 1, 2026)))->toBe('plugin_flowview_nbar_data_202605905');
		}
		date_default_timezone_set('America/New_York');
		$first  = gmmktime(5, 30, 0, 11, 1, 2026);
		$second = $first + 3600;
		expect(fv_nbar_data_table($first))->not->toBe(fv_nbar_data_table($second));
	} finally {
		date_default_timezone_set($old);
	}
});

it('reads the time range of a slice from its name and rejects names that are not slices', function () {
	$hour = gmmktime(5, 0, 0, 3, 1, 2026);
	expect(fv_nbar_data_bounds('plugin_flowview_nbar_data_202605905'))->toBe([$hour, $hour + 3600]);
	$day = gmmktime(0, 0, 0, 3, 1, 2026);
	expect(fv_nbar_data_bounds('plugin_flowview_nbar_data_2026059'))->toBe([$day, $day + 86400]);
	expect(fv_nbar_data_bounds('plugin_flowview_nbar_data_2024365'))->toBe([gmmktime(0, 0, 0, 12, 31, 2024), gmmktime(0, 0, 0, 12, 31, 2024) + 86400]);

	foreach (['plugin_flowview_nbar_data', 'plugin_flowview_nbar_data_x', 'plugin_flowview_nbar_data_2026366', 'plugin_flowview_nbar_data_202605924', 'plugin_flowview_nbar_data_2026059123', 'plugin_flowview_nbar_data_2026059' . "\n", 'plugin_flowview_nbar_hours', 'plugin_flowview_raw_202605905'] as $name) {
		expect(fv_nbar_data_bounds($name))->toBeNull();
	}
});

it('lists the slices oldest first and ignores unrelated tables', function () {
	$rows = array_map(fn ($name) => ['Tables_in_cacti (plugin\_flowview\_nbar\_data\_%)' => $name], [
		'plugin_flowview_nbar_data_202605907',
		'plugin_flowview_nbar_data_2026058',
		'plugin_flowview_nbar_data_garbage',
		'plugin_flowview_nbar_data_202605905',
	]);
	plugin_test_queue_db_result('db_fetch_assoc', $rows);
	$tables = fv_nbar_data_tables();
	expect(array_column($tables, 'table'))->toBe(['plugin_flowview_nbar_data_2026058', 'plugin_flowview_nbar_data_202605905', 'plugin_flowview_nbar_data_202605907']);
	expect($tables[0])->toBe(['table' => 'plugin_flowview_nbar_data_2026058', 'start' => gmmktime(0, 0, 0, 2, 28, 2026), 'end' => gmmktime(0, 0, 0, 3, 1, 2026)]);
	expect($GLOBALS['__test_db_calls'][0]['sql'])->toBe("SHOW TABLES LIKE 'plugin\\_flowview\\_nbar\\_data\\_%'");
	expect($GLOBALS['__test_db_calls'][0]['conn'])->toBe('nbar-partition-test');
});

it('lists no slices when the table list cannot be read', function () {
	plugin_test_queue_db_result('db_fetch_assoc', false);
	expect(fv_nbar_data_tables())->toBe([]);
	expect(fv_nbar_data_tables())->toBe([]);
});

/**
 * The slice list fv_nbar_data_tables() would return for hourly slices starting at the given UTC hours of 2026-03-01.
 *
 * @param list<int> $hours UTC hours.
 *
 * @return list<array{table: string, start: int, end: int}>
 */
function flowview_test_hourly_slices(array $hours): array {
	$tables = [];

	foreach ($hours as $hour) {
		$start    = gmmktime($hour, 0, 0, 3, 1, 2026);
		$tables[] = ['table' => fv_nbar_data_table($start), 'start' => $start, 'end' => $start + 3600];
	}

	return $tables;
}

it('selects only the slices that overlap a range, with the range end exclusive', function () {
	$tables = flowview_test_hourly_slices([4, 5, 6, 7]);
	$h5     = gmmktime(5, 0, 0, 3, 1, 2026);
	expect(fv_nbar_data_slices($h5, $h5 + 3600, $tables))->toBe(['plugin_flowview_nbar_data_202605905']);
	expect(fv_nbar_data_slices($h5 + 1, $h5 + 3601, $tables))->toBe(['plugin_flowview_nbar_data_202605905', 'plugin_flowview_nbar_data_202605906']);
	expect(fv_nbar_data_slices($h5 - 7200, $h5 - 3600, $tables))->toBe([]);
	expect(fv_nbar_data_slices($h5 + 86400, $h5 + 90000, $tables))->toBe([]);
	expect($GLOBALS['__test_db_calls'])->toBe([]);
});

it('lists the slices itself when none are supplied', function () {
	$name = fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026));
	plugin_test_queue_slices([$name]);
	expect(fv_nbar_data_slices(gmmktime(5, 0, 0, 3, 1, 2026), gmmktime(6, 0, 0, 3, 1, 2026)))->toBe([$name]);
	expect($GLOBALS['__test_db_calls'])->toHaveCount(1);
});

it('reads a daily slice for an hourly range that falls inside it, so a scheme change keeps old data readable', function () {
	$daily = 'plugin_flowview_nbar_data_2026059';
	plugin_test_queue_slices([$daily, fv_nbar_data_table(gmmktime(0, 0, 0, 3, 2, 2026))]);
	$from = gmmktime(5, 0, 0, 3, 1, 2026);
	expect(fv_nbar_data_slices($from, $from + 3600))->toBe([$daily]);
});

it('builds one filtered select per overlapping slice so no slice is read whole', function () {
	$tables = flowview_test_hourly_slices([4, 5, 6]);
	$from   = gmmktime(4, 30, 0, 3, 1, 2026);
	$to     = gmmktime(5, 30, 0, 3, 1, 2026);
	$parts  = fv_nbar_data_parts('a,b', "listener_id=3 AND minute>=$from AND minute<$to", $from, $to, $tables);
	expect($parts)->toBe([
		"SELECT a,b FROM `plugin_flowview_nbar_data_202605904` WHERE listener_id=3 AND minute>=$from AND minute<$to",
		"SELECT a,b FROM `plugin_flowview_nbar_data_202605905` WHERE listener_id=3 AND minute>=$from AND minute<$to",
	]);
	$source = fv_nbar_data_source('a,b', "listener_id=3 AND minute>=$from AND minute<$to", $from, $to, $tables);
	expect($source)->toBe('(' . implode(' UNION ALL ', $parts) . ')');
});

it('uses a single select without a union when one slice covers the range', function () {
	$tables = flowview_test_hourly_slices([5]);
	$from   = gmmktime(5, 0, 0, 3, 1, 2026);
	$source = fv_nbar_data_source(fv_nbar_data_columns(), 'minute>=1', $from, $from + 3600, $tables);
	expect($source)->toStartWith('(SELECT listener_id,minute,')->not->toContain('UNION ALL');
	expect($source)->toContain('minute first_seen,minute last_seen,router FROM `plugin_flowview_nbar_data_202605905` WHERE minute>=1');
});

it('returns an empty typed relation when no slice overlaps the range', function () {
	$columns = fv_nbar_data_columns();
	$from    = gmmktime(5, 0, 0, 3, 1, 2026);

	foreach ([[], flowview_test_hourly_slices([9])] as $tables) {
		$source = fv_nbar_data_source($columns, 'listener_id=3', $from, $from + 3600, $tables);
		expect($source)->toContain('WHERE 1=0', 'fv_nbar_empty', 'CAST(0 AS UNSIGNED) listener_id', 'CAST(\'\' AS CHAR(45)) router');
		expect($source)->toStartWith('(SELECT ' . $columns . ' FROM (SELECT CAST');
		expect($source)->not->toContain('listener_id=3');
	}
});

it('exposes the raw columns the summaries expect including observation dates', function () {
	expect(fv_nbar_data_columns())->toBe('listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router');
});

it('creates a slice once and remembers it for a minute', function () {
	$name = 'plugin_flowview_nbar_data_202605905';
	plugin_test_queue_db_result('db_fetch_cell', $name);
	expect(fv_nbar_data_ensure($name))->toBeTrue();
	$first = count($GLOBALS['__test_db_calls']);
	expect($first)->toBeGreaterThan(0);
	expect(fv_nbar_data_ensure($name))->toBeTrue();
	expect($GLOBALS['__test_db_calls'])->toHaveCount($first);

	$GLOBALS['fv_nbar_data_known'][$name] = time() - 61;
	plugin_test_queue_db_result('db_fetch_cell', $name);
	expect(fv_nbar_data_ensure($name))->toBeTrue();
	expect(count($GLOBALS['__test_db_calls']))->toBeGreaterThan($first);
});

it('does not remember a slice that could not be created', function () {
	$name = 'plugin_flowview_nbar_data_202605905';
	plugin_test_queue_db_result('db_fetch_cell', '');
	expect(fv_nbar_data_ensure($name))->toBeFalse();
	expect($GLOBALS['fv_nbar_data_known'] ?? [])->not->toHaveKey($name);
	plugin_test_queue_db_result('db_fetch_cell', $name);
	expect(fv_nbar_data_ensure($name))->toBeTrue();
});

it('drops only the slices that ended before the retention cutoff', function () {
	$cutoff = gmmktime(6, 0, 0, 3, 1, 2026);
	plugin_test_queue_slices([
		fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026)),
		fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026)),
		fv_nbar_data_table(gmmktime(6, 0, 0, 3, 1, 2026)),
		fv_nbar_data_table(gmmktime(7, 0, 0, 3, 1, 2026)),
	]);
	$GLOBALS['fv_nbar_data_known']['plugin_flowview_nbar_data_202605904'] = time();
	expect(fv_nbar_data_drop_expired($cutoff))->toBe(2);
	$drops = array_column(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute'), 'sql');
	expect($drops)->toBe(['DROP TABLE IF EXISTS `plugin_flowview_nbar_data_202605904`', 'DROP TABLE IF EXISTS `plugin_flowview_nbar_data_202605905`']);
	expect($GLOBALS['fv_nbar_data_known'])->not->toHaveKey('plugin_flowview_nbar_data_202605904');
});

it('keeps a daily slice until the whole day is outside retention', function () {
	plugin_test_queue_slices(['plugin_flowview_nbar_data_2026059']);
	expect(fv_nbar_data_drop_expired(gmmktime(23, 0, 0, 3, 1, 2026)))->toBe(0);
	plugin_test_queue_slices(['plugin_flowview_nbar_data_2026059']);
	expect(fv_nbar_data_drop_expired(gmmktime(0, 0, 0, 3, 2, 2026)))->toBe(1);
});

it('does not count a slice whose drop failed', function () {
	plugin_test_queue_slices([fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026))]);
	plugin_test_queue_db_result('db_execute', false);
	expect(fv_nbar_data_drop_expired(gmmktime(9, 0, 0, 3, 1, 2026)))->toBe(0);
});

it('finds the oldest record of a capture by scanning slices oldest first and skipping empty ones', function () {
	plugin_test_queue_slices([fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026)), fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026)), fv_nbar_data_table(gmmktime(6, 0, 0, 3, 1, 2026))]);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => 1772341260]);
	expect(fv_nbar_data_edge(3))->toBe(['minute' => 1772341260]);
	$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));
	expect($reads)->toHaveCount(2);
	expect($reads[0]['sql'])->toBe('SELECT minute FROM `plugin_flowview_nbar_data_202605904` WHERE listener_id=? ORDER BY minute LIMIT 1');
	expect($reads[1]['sql'])->toContain('`plugin_flowview_nbar_data_202605905`');
	expect($reads[1]['params'])->toBe([3]);
	expect($reads[1]['conn'])->toBe('nbar-partition-test');
});

it('finds the newest record of a capture by scanning slices newest first', function () {
	plugin_test_queue_slices([fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026)), fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026))]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => 1772341260]);
	expect(fv_nbar_data_edge(3, true))->toBe(['minute' => 1772341260]);
	$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));
	expect($reads)->toHaveCount(1);
	expect($reads[0]['sql'])->toBe('SELECT minute FROM `plugin_flowview_nbar_data_202605905` WHERE listener_id=? ORDER BY minute DESC LIMIT 1');
});

it('finds the true oldest record when a daily slice overlaps an earlier hourly slice', function () {
	$daily  = 'plugin_flowview_nbar_data_2026059';
	$hourly = fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026));
	plugin_test_queue_slices([$daily, $hourly]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(6, 30, 0, 3, 1, 2026)]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(5, 10, 0, 3, 1, 2026)]);
	expect(fv_nbar_data_edge(3))->toBe(['minute' => gmmktime(5, 10, 0, 3, 1, 2026)]);
});

it('keeps the earlier record when an overlapping slice only holds later ones', function () {
	$daily  = 'plugin_flowview_nbar_data_2026059';
	$hourly = fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026));
	plugin_test_queue_slices([$daily, $hourly]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(5, 5, 0, 3, 1, 2026)]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(5, 40, 0, 3, 1, 2026)]);
	expect(fv_nbar_data_edge(3))->toBe(['minute' => gmmktime(5, 5, 0, 3, 1, 2026)]);
});

it('finds the true newest record when a daily slice overlaps a later hourly slice', function () {
	$daily  = 'plugin_flowview_nbar_data_2026059';
	$hourly = fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026));
	plugin_test_queue_slices([$daily, $hourly]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(5, 30, 0, 3, 1, 2026)]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(5, 50, 0, 3, 1, 2026)]);
	expect(fv_nbar_data_edge(3, true))->toBe(['minute' => gmmktime(5, 50, 0, 3, 1, 2026)]);
	$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));
	expect($reads)->toHaveCount(2);
});

it('stops scanning for the newest record once no remaining slice can be newer', function () {
	$daily  = 'plugin_flowview_nbar_data_2026059';
	$hourly = fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026));
	plugin_test_queue_slices([$daily, $hourly]);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['minute' => gmmktime(8, 0, 0, 3, 1, 2026)]);
	expect(fv_nbar_data_edge(3, true))->toBe(['minute' => gmmktime(8, 0, 0, 3, 1, 2026)]);
	$reads = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));
	expect($reads)->toHaveCount(1);
	expect($reads[0]['sql'])->toContain('`' . $daily . '`');
});

it('reports no record when the capture has none and no slice exists', function () {
	expect(fv_nbar_data_edge(3))->toBe([]);
	plugin_test_queue_slices([fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026))]);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	expect(fv_nbar_data_edge(3))->toBe([]);
});

it('reports a database failure instead of an empty capture', function () {
	plugin_test_queue_slices([fv_nbar_data_table(gmmktime(4, 0, 0, 3, 1, 2026)), fv_nbar_data_table(gmmktime(5, 0, 0, 3, 1, 2026))]);
	plugin_test_queue_db_result('db_fetch_row_prepared', []);
	plugin_test_queue_db_result('db_fetch_row_prepared', false);
	expect(fv_nbar_data_edge(3))->toBeFalse();
});
