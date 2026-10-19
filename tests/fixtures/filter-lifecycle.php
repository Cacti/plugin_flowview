<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Minimal core boundary for subprocess tests of the actual plugin entrypoints.
$config = ['base_path' => dirname(__DIR__), 'poller_id' => 1];
$local_db_cnn_id = 'fixture';
$mode = getenv('FLOWVIEW_FIXTURE_MODE');
$ready = $mode === 'ready';
$calls = [];
const MESSAGE_LEVEL_INFO = 1;
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
	return $name === 'path_php_binary' ? PHP_BINARY : '';
}
function cacti_log(...$args) {
}
function raise_message(...$args) {
}
function db_fetch_cell_prepared(...$args) {
	return 'fixture-version';
}
function db_fetch_cell(...$args) {
	return str_contains($args[0], 'SELECT version') ? 'fixture-version' : 1;
}
function db_fetch_row($sql, ...$args) {
	$GLOBALS['calls'][] = ['read', $sql];
	return $GLOBALS['ready'] ? ['Field' => 'enabled'] : [];
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
	file_put_contents(getenv('FLOWVIEW_FIXTURE_RESULT'), json_encode(['calls' => $GLOBALS['calls'], 'status' => http_response_code()]));
});
