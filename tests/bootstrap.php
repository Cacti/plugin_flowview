<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Test bootstrap: stub Cacti framework functions so plugin code
 * can be loaded in isolation without the full Cacti application.
 */

$GLOBALS['__test_db_calls'] = array();
$GLOBALS['__test_db_results'] = array();
$GLOBALS['__test_config'] = array();
$GLOBALS['__test_messages'] = array();
$GLOBALS['__test_pdo'] = null;
$GLOBALS['__test_integration_db'] = false;

if (!defined('CACTI_VERSION')) {
	define('CACTI_VERSION', '1.3.0');
}

if (!function_exists('plugin_test_reset')) {
	function plugin_test_reset() {
		$GLOBALS['__test_db_calls'] = array();
		$GLOBALS['__test_db_results'] = array();
		$GLOBALS['__test_config'] = array();
		$GLOBALS['__test_messages'] = array();
		$GLOBALS['__test_pdo'] = null;
		$GLOBALS['__test_integration_db'] = false;
		$_GET = array();
		$_POST = array();
		$_REQUEST = array();
		$_SESSION = array();
	}
}

if (!function_exists('plugin_test_use_database')) {
	function plugin_test_use_database($enabled) {
		$GLOBALS['__test_integration_db'] = (bool) $enabled;
		if (!$enabled) {
			$GLOBALS['__test_pdo'] = null;
		}
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

if (!function_exists('plugin_test_pdo')) {
	function plugin_test_pdo() {
		if (!$GLOBALS['__test_integration_db']) {
			return null;
		}

		if ($GLOBALS['__test_pdo'] instanceof PDO) {
			return $GLOBALS['__test_pdo'];
		}

		$dsn = getenv('FLOWVIEW_TEST_DB_DSN');
		if ($dsn === false || $dsn === '') {
			return null;
		}

		$GLOBALS['__test_pdo'] = new PDO(
			$dsn,
			getenv('FLOWVIEW_TEST_DB_USER') ?: 'root',
			getenv('FLOWVIEW_TEST_DB_PASSWORD') ?: '',
			array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
		);

		return $GLOBALS['__test_pdo'];
	}
}

if (!function_exists('plugin_test_db_query')) {
	function plugin_test_db_query($sql, $params = array()) {
		$pdo = plugin_test_pdo();
		if (!$pdo) {
			return null;
		}
		$stmt = $pdo->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}
}

if (!function_exists('db_execute')) {
	function db_execute($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		if (plugin_test_pdo()) {
			plugin_test_db_query($sql);
		}
		return true;
	}
}

if (!function_exists('db_execute_prepared')) {
	function db_execute_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_execute_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		if (plugin_test_pdo()) {
			plugin_test_db_query($sql, $params);
		}
		return true;
	}
}

if (!function_exists('db_fetch_assoc')) {
	function db_fetch_assoc($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		if (plugin_test_pdo()) {
			return plugin_test_db_query($sql)->fetchAll(PDO::FETCH_ASSOC);
		}
		return plugin_test_db_result('db_fetch_assoc', array());
	}
}

if (!function_exists('db_fetch_assoc_prepared')) {
	function db_fetch_assoc_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_assoc_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		if (plugin_test_pdo()) {
			return plugin_test_db_query($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
		}
		return plugin_test_db_result('db_fetch_assoc_prepared', array());
	}
}

if (!function_exists('db_fetch_row')) {
	function db_fetch_row($sql, $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		if (plugin_test_pdo()) {
			$row = plugin_test_db_query($sql)->fetch(PDO::FETCH_ASSOC);
			return $row === false ? array() : $row;
		}
		return plugin_test_db_result('db_fetch_row', array());
	}
}

if (!function_exists('db_fetch_row_prepared')) {
	function db_fetch_row_prepared($sql, $params = array(), $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_row_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		if (plugin_test_pdo()) {
			$row = plugin_test_db_query($sql, $params)->fetch(PDO::FETCH_ASSOC);
			return $row === false ? array() : $row;
		}
		return plugin_test_db_result('db_fetch_row_prepared', array());
	}
}

if (!function_exists('db_fetch_cell')) {
	function db_fetch_cell($sql, $column = '', $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell', 'sql' => $sql, 'params' => array(), 'conn' => $conn);
		if (plugin_test_pdo()) {
			$value = plugin_test_db_query($sql)->fetchColumn();
			return $value === false ? '' : $value;
		}
		return plugin_test_db_result('db_fetch_cell', '');
	}
}

if (!function_exists('db_fetch_cell_prepared')) {
	function db_fetch_cell_prepared($sql, $params = array(), $column = '', $log = true, $conn = false) {
		$GLOBALS['__test_db_calls'][] = array('fn' => 'db_fetch_cell_prepared', 'sql' => $sql, 'params' => $params, 'conn' => $conn);
		if (plugin_test_pdo()) {
			$value = plugin_test_db_query($sql, $params)->fetchColumn();
			return $value === false ? '' : $value;
		}
		return plugin_test_db_result('db_fetch_cell_prepared', '');
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
		$ip = inet_pton($parts[0]);
		$bits = (int) $parts[1];
		$max = strlen($ip) * 8;
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
	$test_root = realpath(__DIR__ . '/..');
	define('CACTI_PATH_BASE', $test_root !== false ? $test_root : dirname(__DIR__));
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

require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/database.php';
