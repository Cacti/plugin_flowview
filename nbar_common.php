<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/includes/nbar_database.php';
require_once __DIR__ . '/nbar_seen.php';
// Shared NBAR schema and retention helpers.
if (!isset($GLOBALS['config']) || !function_exists('flowview_connect')) {
	exit;
}
require_once __DIR__ . '/nbar_summary.php';

/**
 * Escape report text and quoted HTML attributes.
 *
 * @param mixed $s Input text or network-order bytes.
 *
 * @return string
 */
function fv_nbar_h($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Install application-name, raw-record and summary schemas.
 *
 * @return bool
 */
function fv_nbar_schema_install() {
	return fv_nbar_schema_table('names') && fv_nbar_schema_table('data') && fv_nbar_summary_schema() && fv_nbar_exporter_schema();
}
/**
 * Read the validated history-retention setting with a short cache.
 *
 * @return int
 */
function fv_nbar_retention_days() {
	static $days = 7,$checked = 0;
	if (time() - $checked >= 60) {
		$value = (int) read_config_option('flowview_nbar_retention_days', true);
		$days = in_array($value, [1, 3, 7, 14, 30], true) ? $value : 7;
		$checked = time();
	}
	return $days;
}
/**
 * Expire raw records and summaries in bounded database batches.
 *
 * @return void
 */
function fv_nbar_cleanup() {
	fv_nbar_daily_cleanup();
	$cutoff = (int) (floor((time() - fv_nbar_retention_days() * 86400) / 3600) * 3600);
	foreach (['apps', 'pairs', 'connections', 'hours'] as $table) {
		flowview_db_execute("DELETE FROM plugin_flowview_nbar_$table WHERE hour<$cutoff LIMIT 50000");
	}
	flowview_db_execute('DELETE FROM plugin_flowview_nbar_data WHERE minute < ' . (time() - fv_nbar_retention_days() * 86400) . ' LIMIT 50000');
}

/** Establish a verified DML deadline; MySQL SELECT timeouts do not bound INSERT SELECT. */
function fv_nbar_worker_timeout(int $seconds): ?float {
	$old = flowview_db_fetch_cell('SELECT @@session.max_statement_time', '', false);
	if (!is_numeric($old) || (float) $old < 0) {
		return null;
	}
	if (!flowview_db_execute('SET SESSION max_statement_time=' . $seconds, false)) {
		return null;
	}
	return (float) $old;
}
