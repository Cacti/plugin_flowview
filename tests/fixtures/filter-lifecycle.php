<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Minimal core boundary for subprocess tests of the actual plugin entrypoints.
$config = ['base_path' => dirname(__DIR__), 'poller_id' => 1];
$local_db_cnn_id = 'fixture';
$mode = getenv('FLOWVIEW_FIXTURE_MODE');
$ready = in_array($mode, ['ready', 'late', 'child-disabled', 'report-read-failure', 'schedule-read-failure', 'filter-read-failure', 'parallel-timeout'], true);
$metadataReads = 0;
$calls = [];
const MESSAGE_LEVEL_INFO = 1;
const MESSAGE_LEVEL_ERROR = 2;
function __(string $text, ...$args) {
	return $text;
}
function html_escape($text) {
	return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function cacti_sizeof($value) {
	return is_array($value) ? count($value) : 0;
}
function read_config_option($name) {
	return ['path_php_binary' => PHP_BINARY, 'flowview_parallel_threads' => 2, 'flowview_partition' => 1, 'flowview_parallel_time_to_live' => 21600, 'flowview_parallel_runlimit' => 0][$name] ?? '';
}
function cacti_log(...$args) {
}
function raise_message(...$args) {
}
function db_fetch_cell_prepared($sql, ...$args) {
	if (str_contains($sql, 'SELECT name')) {
		return 'Fixture';
	}
	if (str_contains($sql, 'SELECT id')) {
		return 71;
	}
	return str_contains($sql, 'parallel_database_query') ? '' : 'fixture-version';
}
function db_fetch_cell(...$args) {
	return str_contains($args[0], 'SELECT version') ? 'fixture-version' : 1;
}
function db_fetch_row($sql, ...$args) {
	$GLOBALS['calls'][] = ['read', $sql];
	if (str_contains($sql, 'MIN(start_time)')) {
		return ['min_date' => '2024-01-01 00:00:00', 'max_date' => '2024-01-01 01:00:00', 'table_partition' => ''];
	}
	$GLOBALS['metadataReads']++;
	return $GLOBALS['ready'] && ($GLOBALS['mode'] !== 'late' || $GLOBALS['metadataReads'] === 1) ? ['Field' => 'enabled'] : [];
}
function db_execute($sql, ...$args) {
	$GLOBALS['calls'][] = ['ddl', $sql];
	$GLOBALS['ready'] = $GLOBALS['mode'] !== 'failed';
	return $GLOBALS['ready'];
}
function exec_background(...$args) {
	$GLOBALS['calls'][] = ['background', $args];
}
function register_process_start(...$args) {
	$GLOBALS['calls'][] = ['register', $args];
	return $GLOBALS['mode'] !== 'busy';
}
function unregister_process(...$args) {
	$GLOBALS['calls'][] = ['unregister', $args];
}
function set_default_action() {
	$GLOBALS['calls'][] = ['page-ready'];
	exit;
}
register_shutdown_function(function () {
	file_put_contents(getenv('FLOWVIEW_FIXTURE_RESULT'), json_encode(['calls' => $GLOBALS['calls'], 'status' => http_response_code(), 'repair_loaded' => function_exists('flowview_filter_status_upgrade')]));
});
function db_fetch_row_prepared($sql, $params = [], ...$args) {
	$GLOBALS['calls'][] = ['row', $sql, $params];
	if (($GLOBALS['mode'] === 'report-read-failure' && str_contains($sql, 'reports_queued'))
		|| ($GLOBALS['mode'] === 'schedule-read-failure' && str_contains($sql, 'plugin_flowview_schedules'))
		|| ($GLOBALS['mode'] === 'filter-read-failure' && str_contains($sql, 'plugin_flowview_queries'))) {
		return false;
	}
	if (str_contains($sql, 'reports_queued')) {
		return ['id' => 51, 'source' => 'flowview', 'source_id' => 7];
	}
	if (str_contains($sql, 'plugin_flowview_schedules')) {
		return ['id' => 7, 'query_id' => 3, 'title' => 'Fixture', 'format_file' => 'default'];
	}
	return $GLOBALS['mode'] === 'parallel-timeout'
		? ['id' => 3, 'name' => 'Fixture', 'enabled' => 'on', 'timespan' => 1, 'statistics' => 9, 'printed' => 0, 'sortfield' => 'bytes', 'cutofflines' => 100]
		: ['enabled' => ''];
}
function db_execute_prepared($sql, $params = [], ...$args) {
	$GLOBALS['calls'][] = ['write', $sql, $params];
	return true;
}

function db_fetch_assoc($sql, ...$args) {
	return str_contains($sql, 'information_schema') ? [['table' => 'plugin_flowview_raw_202400000']] : [];
}
function db_fetch_assoc_prepared(...$args) {
	return [];
}
function get_timespan(&$span, ...$args) {
	$span = ['begin_now' => 1704067200, 'end_now' => 1704070800];
}
function read_user_setting(...$args) { return ''; }
function isset_request_var(...$args) { return false; }
function get_request_var(...$args) { return null; }
function get_nfilter_request_var(...$args) { return null; }
function get_filter_request_var(...$args) { return null; }
function cacti_session_close() {}
function raise_message_javascript(...$args) {}
function array_rekey($rows, $key, $value) {
	return array_column($rows, $value, $key);
}

function __esc($text, ...$args) { return html_escape($text); }
