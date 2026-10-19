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

// Exact connection counts, requested separately from page rendering.
/**
 * Count distinct connection tuples without regrouping traffic counters.
 *
 * @param string $source Trusted summary or raw SQL source.
 * @param string $where Validated filter predicate.
 *
 * @return string
 */
function fv_nbar_count_sql(string $source, string $where): string {
	$map = '(SELECT router,app_id,DENSE_RANK() OVER (ORDER BY name) name_id FROM plugin_flowview_nbar_names)';
	return 'SELECT COUNT(DISTINCT d.router,d.app_id,COALESCE(n.name_id,0),d.src,d.dst,d.sport,d.dport,d.protocol) total FROM ' . $source . ' d JOIN plugin_flowview_devices l ON l.id=d.listener_id LEFT JOIN ' . $map . ' n ON n.router=COALESCE(NULLIF(d.router,SPACE(0)),l.allowfrom) AND n.app_id=d.app_id WHERE ' . $where;
}
/**
 * Store a bounded account-scoped context for an asynchronous exact count.
 *
 * @param string $source Trusted summary or raw SQL source.
 * @param string $where Validated filter predicate.
 * @param array $args Prepared statement values.
 * @param int $end Exclusive range end in Unix seconds.
 * @param array|null $listeners Selected capture identifiers.
 * @param int|null $start Inclusive range start in Unix seconds.
 *
 * @return string
 */
function fv_nbar_count_context(string $source, string $where, array $args, int $end, ?array $listeners = null, ?int $start = null): string {
	// Guards are evaluated in the count statement, after any intervening refresh.
	if ($listeners !== null && $start !== null) {
		$source = fv_nbar_summary_source('connections', $listeners, (int) $start, (int) $end);
	}
	$sql = fv_nbar_count_sql($source, $where);
	$key = hash('sha256', serialize([$_SESSION['sess_user_id'] ?? 0, $sql, $args]));
	$cache = $_SESSION['fv_nbar_counts'] ?? [];
	foreach ($cache as $k => $entry) {
		if ($entry['expires'] < time()) {
			unset($cache[$k]);
		}
	}
	if (!isset($cache[$key])) {
		while (cacti_sizeof($cache) >= 12) {
			array_shift($cache);
		}
		$cache[$key] = ['sql' => $sql, 'args' => $args, 'expires' => time() + 3600, 'ttl' => $end >= time() - 120 ? 60 : 600];
	}
	$_SESSION['fv_nbar_counts'] = $cache;
	return $key;
}
/**
 * Send a JSON count response and terminate the HTTP request.
 *
 * @param array $payload JSON response fields.
 *
 * @return never
 */
function fv_nbar_count_response(array $payload): never {
	header('Content-Type: application/json');
	header('Cache-Control: no-store');
	echo json_encode($payload);
	exit;
}
/**
 * Handle an authenticated count request without blocking account navigation.
 *
 * @return never
 */
function fv_nbar_count_request(): never {
	$key = get_nfilter_request_var('key');
	if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) {
		fv_nbar_count_response(['status' => 'expired']);
	}
	$entry = $_SESSION['fv_nbar_counts'][$key] ?? null;
	if (!$entry || $entry['expires'] < time()) {
		fv_nbar_count_response(['status' => 'expired']);
	}
	if (($entry['failed_until'] ?? 0) > time()) {
		fv_nbar_count_response(['status' => 'unavailable']);
	}
	if (isset($entry['total']) && $entry['until'] > time()) {
		fv_nbar_count_response(['status' => 'ready', 'pages' => (int) ceil($entry['total'] / 100), 'total' => $entry['total']]);
	}
	// Release the account session lock so navigation remains available during counting.
	session_write_close();
	$locked = flowview_db_fetch_cell("SELECT GET_LOCK(CONCAT('flowview_nbar_page_count:',LEFT(SHA2(DATABASE(),256),16)),0)");
	if ((int) $locked !== 1) {
		fv_nbar_count_response(['status' => 'busy']);
	}
	// Return promptly; subsequent polls read the completed session cache.
	// PHP-FPM can finish this bounded calculation after closing the response.
	ini_set('session.use_cookies', '0');
	session_cache_limiter('');
	$background = function_exists('fastcgi_finish_request');
	if ($background) {
		header('Content-Type: application/json');
		header('Cache-Control: no-store');
		echo json_encode(['status' => 'busy']);
		fastcgi_finish_request();
	}
	$row = null;
	try {
		set_time_limit(100);
		$row = fv_nbar_count_query($entry['sql'], $entry['args']);
	} finally {
		flowview_db_fetch_cell("SELECT RELEASE_LOCK(CONCAT('flowview_nbar_page_count:',LEFT(SHA2(DATABASE(),256),16)))");
	}
	if (!is_array($row) || !isset($row['total'])) {
		session_start();
		if (isset($_SESSION['fv_nbar_counts'][$key])) {
			$_SESSION['fv_nbar_counts'][$key]['failed_until'] = time() + 60;
		}
		session_write_close();
		if ($background) {
			exit;
		}
		fv_nbar_count_response(['status' => 'unavailable']);
	}
	$total = (int) $row['total'];
	session_start();
	if (isset($_SESSION['fv_nbar_counts'][$key])) {
		$_SESSION['fv_nbar_counts'][$key]['total'] = $total;
		$_SESSION['fv_nbar_counts'][$key]['until'] = time() + $entry['ttl'];
	}
	session_write_close();
	if ($background) {
		exit;
	}
	fv_nbar_count_response(['status' => 'ready', 'pages' => (int) ceil($total / 100), 'total' => $total]);
}

/** Bound a SELECT on the actual FlowView server rather than Cacti's server. */
function fv_nbar_count_query(string $sql, array $args): array|false|null {
	$version = flowview_db_fetch_cell('SELECT VERSION()');
	if (is_string($version) && stripos($version, 'MariaDB') !== false) {
		return flowview_db_fetch_row_prepared('SET STATEMENT max_statement_time=90 FOR ' . $sql, $args);
	}
	if (is_string($version) && preg_match('/^8[.]/', $version)) {
		return flowview_db_fetch_row_prepared(preg_replace('/^SELECT /', 'SELECT /*+ MAX_EXECUTION_TIME(90000) */ ', $sql), $args);
	}
	return false;
}
