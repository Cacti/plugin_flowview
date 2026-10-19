<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

// Regression coverage for the PHPStan level 8 and cacti_sizeof() fixes in pre-existing code.

beforeEach(function () {
	plugin_test_reset();
	date_default_timezone_set('UTC');
	$GLOBALS['__test_db_calls'] = [];
});

it('maps table column definitions and tolerates a failed column lookup', function () {
	plugin_test_queue_db_result('db_fetch_assoc', [
		['Field' => 'id', 'Type' => 'int(11)', 'Null' => 'NO', 'Default' => null, 'Extra' => 'auto_increment'],
		['Field' => 'name', 'Type' => 'varchar(64)', 'Null' => 'YES', 'Default' => '', 'Extra' => ''],
	]);

	expect(flowview_db_get_table_column_types('plugin_flowview_devices'))->toBe([
		'id'   => ['type' => 'int(11)', 'null' => 'NO', 'default' => null, 'extra' => 'auto_increment'],
		'name' => ['type' => 'varchar(64)', 'null' => 'YES', 'default' => '', 'extra' => ''],
	]);

	plugin_test_queue_db_result('db_fetch_assoc', false);

	expect(flowview_db_get_table_column_types('plugin_flowview_devices'))->toBe([]);
});

it('raises the parallel query timeout at error level with an empty message body', function () {
	if (!function_exists('raise_message_javascript')) {
		function raise_message_javascript(string $title, string $header, string $message, int $level = 0): void {
			throw new RuntimeException(json_encode([$title, $header, $message, $level]));
		}
	}
	$GLOBALS['__test_config']['flowview_parallel_runlimit'] = 0;

	try {
		parallel_database_query_run([]);
		$raised = null;
	} catch (RuntimeException $e) {
		$raised = json_decode($e->getMessage(), true);
	}

	expect($raised)->toBe([
		'parallel_query_timeout',
		__('The Parallel Query Timed Out.  Please contact your administrator', 'flowview'),
		'',
		MESSAGE_LEVEL_ERROR,
	]);
});

it('passes the report format loader a boolean report tag', function () {
	global $config;

	$constants = file_get_contents($config['base_path'] . '/include/global_constants.php');
	preg_match_all("/define\\('((?:GT_|GTS_)[A-Z0-9_]+)', ([0-9]+)\\);/", $constants, $matches, PREG_SET_ORDER);

	foreach ($matches as $match) {
		if (!defined($match[1])) {
			define($match[1], (int) $match[2]);
		}
	}

	if (!defined('DEFAULT_TIMESPAN')) {
		define('DEFAULT_TIMESPAN', 1800);
	}
	require_once($config['base_path'] . '/lib/time.php');

	if (!function_exists('reports_load_format_file')) {
		function reports_load_format_file($format_file, &$output, &$report_tag_included, &$theme): bool {
			$GLOBALS['__test_report_tag_argument'] = $report_tag_included;

			throw new RuntimeException('format loader reached');
		}
	}
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 1, 'title' => 'Nightly', 'query_id' => 0, 'format_file' => '']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 1, 'name' => 'Top talkers', 'timespan' => GT_LAST_HOUR]);

	expect(fn () => plugin_flowview_run_schedule(1, 1))->toThrow(RuntimeException::class, 'format loader reached');
	expect($GLOBALS['__test_report_tag_argument'])->toBeFalse();
});

it('returns no data for a saved query that does not exist and no report was requested', function () {
	if (!function_exists('cacti_session_close')) {
		function cacti_session_close(): void {
		}
	}
	$_REQUEST = ['cutofflines' => '999999'];

	expect(run_flow_query(false, 1, 1000, 2000))->toBeFalse();
});
