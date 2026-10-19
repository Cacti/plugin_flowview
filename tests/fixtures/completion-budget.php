<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Persistent deletion failures must not consume the executable-work budget.
$config = ['base_path' => $argv[2]];
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE reports_queued (id INTEGER PRIMARY KEY, source TEXT, source_id INTEGER, status TEXT, start_time TEXT, scheduled_time TEXT, run_command TEXT, run_timeout INTEGER)');
for ($id = 1; $id <= 101; $id++) {
	$stmt = $db->prepare("INSERT INTO reports_queued VALUES (?, 'flowview', ?, ?, '', '', 'fixture', 1)");
	$stmt->execute([$id, $id, $id === 101 ? 'pending' : 'complete']);
}
$attempts = [];
$deletes = [];
function db_fetch_row(...$args) { return ['Field' => 'enabled']; }
function db_fetch_assoc_prepared($sql, $params = [], ...$args) {
	if (str_contains($sql, 'FROM processes')) { return []; }
	$stmt = $GLOBALS['db']->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function db_fetch_row_prepared($sql, $params = [], ...$args) { return db_fetch_assoc_prepared($sql, $params)[0] ?? []; }
function db_execute_prepared($sql, $params = [], ...$args) {
	if (str_starts_with($sql, 'DELETE FROM reports_queued')) {
		$GLOBALS['deletes'][] = $params[0];
		if ($params[0] <= 100) { return false; }
	}
	$stmt = $GLOBALS['db']->prepare($sql);
	$result = $stmt->execute($params);
	$GLOBALS['affected'] = $stmt->rowCount();
	return $result;
}
function db_affected_rows(...$args) { return $GLOBALS['affected'] ?? 0; }
function exec_with_timeout($command, &$output, &$status, $timeout) {
	preg_match('/--queue-id=(\d+)/', $command, $match);
	$id = (int) $match[1];
	$GLOBALS['attempts'][] = $id;
	db_execute_prepared('UPDATE reports_queued SET status=? WHERE id=?', ['complete', $id]);
	$status = 0;
}
function read_config_option(...$args) { return ''; }
function cacti_log(...$args) {}
require $argv[1] . '/includes/database.php';
require $argv[1] . '/includes/functions.php';
$done = flowview_reports_run_pending(1, 100);
$remaining = db_fetch_assoc_prepared('SELECT id,status FROM reports_queued ORDER BY id');
echo json_encode(compact('done', 'attempts', 'deletes', 'remaining'));
