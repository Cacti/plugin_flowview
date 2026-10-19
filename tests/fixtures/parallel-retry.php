<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Persist request/shard state while using the real request, run and cleanup helpers.
// Only MySQL map-table DDL and information_schema reads are adapted to SQLite.
$config = ['base_path' => $argv[2]];
$debug = false;
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE parallel_database_query (id INTEGER PRIMARY KEY AUTOINCREMENT, md5sum TEXT, md5sum_tables TEXT, user_id INTEGER, total_shards INTEGER, map_query TEXT, map_range TEXT, map_range_params TEXT, reduce_query TEXT, created TEXT, time_to_live INTEGER, map_table TEXT, map_create TEXT, status TEXT DEFAULT 'pending', results TEXT)");
$db->exec('CREATE TABLE parallel_database_query_shard (query_id INTEGER, shard_id INTEGER, full_scan INTEGER, map_table TEXT, map_partition TEXT, map_query TEXT, map_params TEXT)');
$phase = 0;
$launches = [];
const MESSAGE_LEVEL_ERROR = 1;
const POLLER_VERBOSITY_MEDIUM = 2;
function __($text, ...$args) { return $text; }
function cacti_sizeof($value) { return is_array($value) ? count($value) : 0; }
function cacti_log(...$args) {}
function raise_message_javascript(...$args) {}
function read_config_option($name) {
	return ['path_php_binary' => PHP_BINARY, 'flowview_parallel_runlimit' => $GLOBALS['phase'] ? 5 : 0, 'flowview_parallel_time_to_live' => 21600, 'flowview_engine' => 'Aria'][$name] ?? '';
}
function array_rekey($rows, $key, $value) {
	$result = [];
	foreach ($rows as $row) {
		$result[$row[$key]] = is_array($value) ? array_intersect_key($row, array_flip($value)) : $row[$value];
	}
	return $result;
}
function db_fetch_assoc_prepared($sql, $params = [], ...$args) {
	if (str_contains($sql, 'FROM processes')) {
		return [];
	}
	if (str_contains($sql, 'INFORMATION_SCHEMA.COLUMNS')) {
		return [['COLUMN_NAME' => 'bytes', 'DATA_TYPE' => 'bigint', 'COLUMN_TYPE' => 'bigint', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => '']];
	}
	$stmt = $GLOBALS['db']->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function db_fetch_assoc($sql, ...$args) {
	return str_contains($sql, 'information_schema.ENGINES') ? [['ENGINE' => 'Aria']] : db_fetch_assoc_prepared($sql);
}
function db_fetch_cell_prepared($sql, $params = [], ...$args) {
	$rows = db_fetch_assoc_prepared($sql, $params);
	return $rows ? reset($rows[0]) : false;
}
function db_fetch_cell($sql, ...$args) { return db_fetch_cell_prepared($sql); }
function db_execute_prepared($sql, $params = [], ...$args) {
	$stmt = $GLOBALS['db']->prepare($sql);
	return $stmt->execute($params);
}
function db_execute($sql, ...$args) {
	$sql = preg_replace('/\) ENGINE=.*$/s', ')', $sql);
	return db_execute_prepared($sql);
}
function sql_save($row, $table, ...$args) {
	unset($row['id']);
	$sql = 'INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')';
	db_execute_prepared($sql, array_values($row));
	return (int) $GLOBALS['db']->lastInsertId();
}
function exec_background($php, $command, ...$args) {
	preg_match('/--query-id=(\d+)/', $command, $match);
	$id = (int) $match[1];
	$GLOBALS['launches'][] = $id;
	if ($GLOBALS['phase']) {
		db_execute_prepared('UPDATE parallel_database_query SET status=?,results=? WHERE id=?', ['complete', '[]', $id]);
	}
}
require $argv[1] . '/includes/database.php';
require $argv[1] . '/includes/functions.php';
$tables = ['fixture_raw' => ['min_date' => '2024-01-01 00:00:00', 'max_date' => '2024-01-01 01:00:00', 'table_name' => 'fixture_raw', 'table_partition' => '']];
$inner = ['sql_query' => 'SELECT SUM(bytes) bytes', 'sql_where' => '', 'sql_range' => 'start_time >= ? AND start_time < ?', 'sql_range_params' => [1704067200, 1704070800], 'sql_params' => [], 'sql_start_time' => 1704067200, 'sql_end_time' => 1704070800];
$outer = ['sql_query' => 'SELECT SUM(bytes) bytes', 'sql_where' => '', 'sql_groupby' => '', 'sql_having' => '', 'sql_order' => '', 'sql_limit' => '', 'sql_params' => []];
$first = parallel_database_query_request($tables, $inner, $outer);
$timeout = parallel_database_query_run([$first]);
$remaining = db_fetch_cell('SELECT COUNT(*) FROM parallel_database_query');
$remainingShards = db_fetch_cell('SELECT COUNT(*) FROM parallel_database_query_shard');
$phase = 1;
$retry = parallel_database_query_request($tables, $inner, $outer);
$retryShards = db_fetch_cell_prepared('SELECT COUNT(*) FROM parallel_database_query_shard WHERE query_id=?', [$retry]);
$result = parallel_database_query_run([$retry]);
$cached = parallel_database_query_request($tables, $inner, $outer);
$cachedResult = parallel_database_query_run([$cached]);
echo json_encode(compact('first', 'timeout', 'remaining', 'remainingShards', 'retry', 'retryShards', 'result', 'cached', 'cachedResult', 'launches'));
