<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Exercise real SQL ordering and persisted retries, independent of MySQL DDL.
$config = ['base_path' => $argv[2]];
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE reports_queued (id INTEGER PRIMARY KEY, source TEXT, source_id INTEGER, status TEXT, start_time TEXT, scheduled_time TEXT, run_command TEXT, run_timeout INTEGER)');
for ($id = 1; $id <= 4; $id++) {
	$db->exec("INSERT INTO reports_queued VALUES ($id, 'flowview', $id, 'pending', '0000-00-00 00:00:00', '2026-01-01 00:00:00', 'fixture', 1)");
}
$attempts = [];
function db_fetch_row(...$args) {
	return ['Field' => 'enabled'];
}
function db_fetch_assoc_prepared($sql, $params = [], ...$args) {
	if (str_contains($sql, 'FROM processes')) {
	return [];
	}
	$stmt = $GLOBALS['db']->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function db_fetch_row_prepared($sql, $params = [], ...$args) {
	return db_fetch_assoc_prepared($sql, $params)[0] ?? [];
}
function db_execute_prepared($sql, $params = [], ...$args) {
	$stmt = $GLOBALS['db']->prepare($sql);
	return $stmt->execute($params);
}
function exec_with_timeout($command, &$output, &$status, $timeout) {
	preg_match('/--queue-id=(\d+)/', $command, $match);
	$id = (int) $match[1];
	$GLOBALS['attempts'][] = $id;
	$status = $id <= 2 ? 75 : 0;
	return '';
}
function read_config_option(...$args) {
	return '';
}
function cacti_log(...$args) {
}
require $argv[1] . '/includes/database.php';
require $argv[1] . '/includes/functions.php';
$first = flowview_reports_run_pending(1, 2);
$afterFirst = db_fetch_assoc_prepared('SELECT id,status FROM reports_queued ORDER BY id');
$second = flowview_reports_run_pending(1, 2);
$remaining = db_fetch_assoc_prepared('SELECT id,status FROM reports_queued ORDER BY id');
$third = flowview_reports_run_pending(1, 2);
echo json_encode(compact('first', 'second', 'third', 'attempts', 'afterFirst', 'remaining'));
