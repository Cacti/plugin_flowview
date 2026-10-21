<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Real Cacti process helper + persisted queue, with a deliberately late worker.
$config = ['base_path' => $argv[1]];
$dbPath = $argv[3] . '/queue.sqlite';
$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE reports_queued (id INTEGER PRIMARY KEY, source TEXT, source_id INTEGER, status TEXT, start_time TEXT, scheduled_time TEXT, run_command TEXT, run_timeout INTEGER)');
$worker = $argv[3] . '/late.php';
$delivery = $argv[3] . '/deliveries.txt';
file_put_contents($worker, '<?php fwrite(STDERR,"nonfatal warning\n"); usleep(1800000); $db=new PDO(' . var_export('sqlite:' . $dbPath, true) . '); file_put_contents(' . var_export($delivery, true) . ',"delivered\n",FILE_APPEND); $db->exec("UPDATE reports_queued SET status=\'complete\' WHERE id=51 AND status=\'running\'");');
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($worker);
$stmt = $db->prepare("INSERT INTO reports_queued VALUES (51,'flowview',7,'pending','','',?,1)");
$stmt->execute([$command]);
$writes = [];
$logs = [];
function db_fetch_row($sql, ...$args) { return ['Field' => 'enabled']; }
function db_fetch_assoc_prepared($sql, $params = [], ...$args) {
	if (str_contains($sql, 'FROM processes')) { return []; }
	$stmt = $GLOBALS['db']->prepare($sql);
	$stmt->execute($params);
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
function db_fetch_row_prepared($sql, $params = [], ...$args) { return db_fetch_assoc_prepared($sql, $params)[0] ?? []; }
function db_execute_prepared($sql, $params = [], ...$args) {
	$GLOBALS['writes'][] = [$sql, $params];
	$stmt = $GLOBALS['db']->prepare($sql);
	$result = $stmt->execute($params);
	$GLOBALS['affected'] = $stmt->rowCount();
	return $result;
}
function db_affected_rows(...$args) { return $GLOBALS['affected'] ?? 0; }
function cacti_sizeof($rows) { return is_array($rows) ? count($rows) : 0; }
function read_config_option(...$args) { return ''; }
function cacti_log($text, ...$args) { $GLOBALS['logs'][] = $text; }
require $argv[1] . '/lib/poller.php';
require $argv[2] . '/includes/database.php';
require $argv[2] . '/includes/functions.php';
$initial = $argv[4] === 'native' ? flowview_reports_run(51) : reports_run(51);
$deadline = microtime(true) + 5;
while (!is_file($delivery) && microtime(true) < $deadline) { usleep(50000); }
// The delivery marker precedes the durable outcome; wait for that write too.
while ((db_fetch_row_prepared('SELECT status FROM reports_queued WHERE id=51')['status'] ?? '') === 'running' && microtime(true) < $deadline) { usleep(50000); }
$late = flowview_reports_run_pending();
$remaining = db_fetch_assoc_prepared('SELECT * FROM reports_queued');
$deliveries = is_file($delivery) ? file($delivery) : [];
echo json_encode(compact('initial', 'late', 'remaining', 'deliveries', 'writes', 'logs'));
