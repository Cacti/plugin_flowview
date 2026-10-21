<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Use Cacti's actual process helper, loading it before the guarded test doubles.
require_once $argv[1] . '/lib/poller.php';
require $argv[2] . '/tests/bootstrap-unit.php';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($argv[3] . '/warning.php');
$output = [];
$status = 1;
$helperResult = exec_with_timeout($command, $output, $status, 5);
plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'name' => 'Fixture', 'source_id' => 7, 'run_command' => $command, 'run_timeout' => 5]);
plugin_test_queue_db_result('report_outcome', ['status' => 'complete']);
$success = $argv[4] === 'native' ? flowview_reports_run(51) : reports_run(51);
$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
echo json_encode(compact('helperResult', 'status', 'success', 'writes'));
