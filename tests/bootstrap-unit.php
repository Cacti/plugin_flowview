<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Test bootstrap.
 *
 * FlowView's sources expect to be included by Cacti, which has already
 * defined the db_*, request-variable, and logging helpers as plain global
 * functions. Nothing here talks to a database or a network: each Cacti
 * function is declared as a stub that records the call in
 * $GLOBALS['__test_db_calls'] and hands back a safe (optionally queued)
 * default via plugin_test_queue_db_result()/plugin_test_db_result().
 *
 * The CI workflow checks out a pinned Cacti release next to this plugin so
 * Pest runs against Cacti's own Composer-managed vendor tree (Pest/PHPUnit)
 * instead of a vendor tree local to this plugin. The version check below
 * makes sure that checkout actually matches what tests/.cacti-version
 * expects before any plugin source is loaded.
 *
 * Guarding every declaration with function_exists() keeps this file usable
 * if a future integration suite loads real Cacti first.
 */

$cacti_root = dirname(__DIR__, 3);
$autoload   = $cacti_root . '/include/vendor/autoload.php';
$version    = $cacti_root . '/include/cacti_version';
$expected   = __DIR__ . '/.cacti-version';

if (!is_readable($autoload)) {
	throw new RuntimeException("Cacti Composer autoloader is not readable: $autoload");
}

if (!is_readable($version)) {
	throw new RuntimeException("Cacti version file is not readable: $version");
}

if (!is_readable($expected)) {
	throw new RuntimeException("Expected Cacti version file is not readable: $expected");
}

$cacti_version    = trim((string) file_get_contents($version));
$expected_version = trim((string) file_get_contents($expected));

if ($cacti_version === '') {
	throw new RuntimeException("Cacti version file is empty: $version");
}

if ($expected_version === '') {
	throw new RuntimeException("Expected Cacti version file is empty: $expected");
}

// The CI workflow tracks a moving branch (1.2.x or develop) rather than a pinned release, so any actual version is accepted.
if (!in_array($expected_version, array('1.2.x', 'develop'), true) && $cacti_version !== $expected_version) {
	throw new RuntimeException("Expected Cacti $expected_version, found $cacti_version in $version");
}

require_once $autoload;

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', $cacti_version);
}

/*
 * base_path has to point at the Cacti root two levels above this plugin:
 * flowview's source files build include paths from it at runtime.
 */
$GLOBALS['config'] = array(
	'base_path'       => $cacti_root,
	'url_path'        => '/cacti/',
	'cacti_version'   => $cacti_version,
	'cacti_server_os' => 'unix',
);

$GLOBALS['__test_db_calls']   = array();
$GLOBALS['__test_db_results'] = array();
$GLOBALS['__test_config']     = array();
$GLOBALS['__test_messages']   = array();

if (!function_exists('plugin_test_reset')) {
	function plugin_test_reset() {
		$GLOBALS['__test_db_calls']   = array();
		$GLOBALS['__test_db_results'] = array();
		$GLOBALS['__test_config']     = array();
		$GLOBALS['__test_messages']   = array();
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		$_SESSION = array();
	}
}

if (!function_exists('plugin_test_queue_db_result')) {
	function plugin_test_queue_db_result($function, $result) {
		if (!isset($GLOBALS['__test_db_results'][$function])) {
			$GLOBALS['__test_db_results'][$function] = array();
		}
		$GLOBALS['__test_db_results'][$function][] = $result;
	}
}

if (!function_exists('plugin_test_db_result')) {
	function plugin_test_db_result($function, $default) {
		if (!empty($GLOBALS['__test_db_results'][$function])) {
			return array_shift($GLOBALS['__test_db_results'][$function]);
		}
		return $default;
	}
}

if (!function_exists('db_execute')) {
	function db_execute($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		return plugin_test_db_result('db_fetch_assoc', array());
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		return plugin_test_db_result('db_fetch_assoc_prepared', array());
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		return plugin_test_db_result('db_fetch_row', array());
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		return plugin_test_db_result('db_fetch_row_prepared', array());
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $column = '', $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		return plugin_test_db_result('db_fetch_cell', '');
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = array(), $column = '', $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		return plugin_test_db_result('db_fetch_cell_prepared', '');
	}
}

// $replacement mirrors core's real reconnect: false means the connection was
// still alive and $conn is left untouched; anything else is queued via
// plugin_test_queue_db_result('db_check_reconnect', ...) and written back.
if (!function_exists('db_check_reconnect')) {
	function db_check_reconnect(&$conn, $log = true) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_check_reconnect', 'conn' => $conn, 'log' => $log);

		$replacement = plugin_test_db_result('db_check_reconnect', false);

		if ($replacement !== false) {
			$conn = $replacement;
		}

		return true;
	}
}

if (!function_exists('db_index_exists')) {
	function db_index_exists($table, $index) {
		return false;
	}
}

if (!function_exists('db_column_exists')) {
	function db_column_exists($table, $column) {
		return false;
	}
}

if (!function_exists('api_plugin_db_add_column')) {
	function api_plugin_db_add_column($plugin, $table, $data) {
		return true;
	}
}

if (!function_exists('api_plugin_db_table_create')) {
	function api_plugin_db_table_create($plugin, $table, $data) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'api_plugin_db_table_create', 'sql' => $table, 'params' => $data);
		return true;
	}
}

$GLOBALS['__test_registered_hooks'] = array();

if (!function_exists('api_plugin_register_hook')) {
	function api_plugin_register_hook($plugin, $hook, $function, $file, $subtype = '') {
		$GLOBALS['__test_registered_hooks'][] = array(
			'name'     => $plugin,
			'hook'     => $hook,
			'function' => $function,
			'file'     => $file,
		);

		return true;
	}
}

$GLOBALS['__test_registered_realms'] = array();

if (!function_exists('api_plugin_register_realm')) {
	function api_plugin_register_realm($plugin, $file, $description, $enabled) {
		$GLOBALS['__test_registered_realms'][] = array(
			'name'        => $plugin,
			'file'        => $file,
			'description' => $description,
			'enabled'     => $enabled,
		);

		return true;
	}
}

if (!function_exists('read_config_option')) {
	function read_config_option($name, $force = false) {
		return isset($GLOBALS['__test_config'][$name]) ? $GLOBALS['__test_config'][$name] : '';
	}
}

if (!function_exists('set_config_option')) {
	function set_config_option($name, $value) {
		$GLOBALS['__test_config'][$name] = $value;
	}
}

if (!function_exists('html_escape')) {
	function html_escape($string) {
		return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('__')) {
	function __($text, $domain = '') {
		return $text;
	}
}

if (!function_exists('__esc')) {
	function __esc($text, ...$args) {
		if (!empty($args)) {
			array_pop($args);
		}
		if (!empty($args)) {
			$text = vsprintf($text, $args);
		}
		return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
}

if (!function_exists('cacti_log')) {
	function cacti_log($message, $also_print = false, $log_type = '', $level = 0) {
	}
}

if (!function_exists('cacti_sizeof')) {
	function cacti_sizeof($array) {
		return is_array($array) ? count($array) : 0;
	}
}

if (!function_exists('is_realm_allowed')) {
	function is_realm_allowed($realm) {
		return true;
	}
}

if (!function_exists('raise_message')) {
	function raise_message($id, $text = '', $level = 0) {
		$GLOBALS['__test_messages'][] = array($id, $text, $level);
	}
}

if (!function_exists('get_request_var')) {
	function get_request_var($name) {
		return isset($_REQUEST[$name]) ? $_REQUEST[$name] : '';
	}
}

if (!function_exists('get_nfilter_request_var')) {
	function get_nfilter_request_var($name) {
		return get_request_var($name);
	}
}

if (!function_exists('get_filter_request_var')) {
	function get_filter_request_var($name) {
		return get_request_var($name);
	}
}

if (!function_exists('isset_request_var')) {
	function isset_request_var($name) {
		return isset($_REQUEST[$name]);
	}
}

if (!function_exists('set_request_var')) {
	function set_request_var($name, $value) {
		$_REQUEST[$name] = $value;
	}
}

if (!function_exists('is_ipaddress')) {
	function is_ipaddress($value) {
		return filter_var($value, FILTER_VALIDATE_IP) !== false;
	}
}

if (!function_exists('cacti_pton')) {
	function cacti_pton($cidr) {
		$parts = explode('/', $cidr, 2);
		if (count($parts) !== 2 || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
			return array();
		}
		$ip   = inet_pton($parts[0]);
		$bits = (int) $parts[1];
		$max  = strlen($ip) * 8;
		if ($bits < 0 || $bits > $max) {
			return array();
		}
		$mask = str_repeat("\xff", intdiv($bits, 8));
		if ($bits % 8) {
			$mask .= chr((0xff << (8 - ($bits % 8))) & 0xff);
		}
		$mask = str_pad($mask, strlen($ip), "\0");
		return array('ip' => $ip, 'subnet' => $mask);
	}
}

if (!function_exists('db_format_index_create')) {
	function db_format_index_create($columns) {
		return implode(', ', array_map(function ($column) {
			return '`' . $column . '`';
		}, (array) $columns));
	}
}

if (!function_exists('db_get_global_variable')) {
	function db_get_global_variable($name) {
		return 'Barracuda';
	}
}

if (!function_exists('form_input_validate')) {
	function form_input_validate($value, $name, $regex, $optional, $error) {
		return $value;
	}
}

if (!function_exists('is_error_message')) {
	function is_error_message() {
		return false;
	}
}

if (!function_exists('sql_save')) {
	function sql_save($array, $table, $key = 'id') {
		return isset($array['id']) ? $array['id'] : 1;
	}
}

if (!defined('CACTI_PATH_BASE')) {
	define('CACTI_PATH_BASE', $GLOBALS['config']['base_path']);
}

if (!defined('POLLER_VERBOSITY_LOW')) {
	define('POLLER_VERBOSITY_LOW', 2);
}

if (!defined('POLLER_VERBOSITY_MEDIUM')) {
	define('POLLER_VERBOSITY_MEDIUM', 3);
}

if (!defined('POLLER_VERBOSITY_DEBUG')) {
	define('POLLER_VERBOSITY_DEBUG', 5);
}

if (!defined('POLLER_VERBOSITY_NONE')) {
	define('POLLER_VERBOSITY_NONE', 6);
}

if (!defined('MESSAGE_LEVEL_ERROR')) {
	define('MESSAGE_LEVEL_ERROR', 1);
}

if (!defined('MESSAGE_LEVEL_INFO')) {
	define('MESSAGE_LEVEL_INFO', 0);
}

if (!function_exists('plugin_test_read_source')) {
	function plugin_test_read_source($relative_file) {
		$path = realpath(__DIR__ . '/../' . $relative_file);
		if ($path === false) {
			throw new RuntimeException("Unable to resolve required file: {$relative_file}");
		}

		$contents = file_get_contents($path);
		if ($contents === false) {
			throw new RuntimeException("Unable to read required file: {$relative_file}");
		}

		return $contents;
	}
}

/**
 * Load a plugin source file at global scope.
 *
 * Some plugin files define data as file-scope variables that the rest of
 * the plugin reads as globals, and they read $config while doing so.
 * Requiring them from inside a method would make both halves of that
 * method-local, so the require happens here and any variable the file
 * introduced is published to $GLOBALS.
 *
 * @param string $path Absolute path to the file.
 *
 * @return void
 */
function flowview_test_load($path) {
	global $config;

	$__before = get_defined_vars();

	require_once $path;

	foreach (get_defined_vars() as $__name => $__value) {
		if (!array_key_exists($__name, $__before) && strncmp($__name, '__', 2) !== 0) {
			$GLOBALS[$__name] = $__value;
		}
	}
}

require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/database.php';
