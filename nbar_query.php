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

if (!function_exists('flowview_db_fetch_assoc_prepared')) {
	exit;
}
/**
 * Fetch a bounded report result with a short account-session cache.
 *
 * @param string $sql Trusted SQL statement.
 * @param array $args Prepared statement values.
 *
 * @return array|false
 */
function fv_nbar_query_cached($sql, $args) {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return flowview_db_fetch_assoc_prepared($sql, $args);
	}
	// Fetch five adjacent pages together; retain one extra row for Next.
	$slice = null;
	if (preg_match('/ LIMIT 101 OFFSET ([0-9]+)(?=\)|$)/', $sql, $match)) {
		$offset = (int) $match[1];
		$block = (int) (floor($offset / 500) * 500);
		$slice = $offset - $block;
		$sql = preg_replace('/ LIMIT 101 OFFSET [0-9]+(?=\)|$)/', ' LIMIT 501 OFFSET ' . $block, $sql);
	}
	$now = time();
	$key = hash('sha256', serialize([$_SESSION['sess_user_id'] ?? 0, $sql, $args]));
	$cache = $_SESSION['fv_nbar_query_cache'] ?? [];
	foreach ($cache as $id => $entry) {
		if ($entry['until'] <= $now) {
			unset($cache[$id]);
		}
	}
	if (isset($cache[$key])) {
		$_SESSION['fv_nbar_query_cache'] = $cache;
		return $slice === null ? $cache[$key]['rows'] : array_slice($cache[$key]['rows'], $slice, 101);
	}
	$rows = flowview_db_fetch_assoc_prepared($sql, $args);
	// Cache successful, compact result sets only; never retain query failures.
	if (is_array($rows) && strlen(serialize($rows)) <= 524288) {
		while (cacti_sizeof($cache) >= 12) {
			array_shift($cache);
		}
		$cache[$key] = ['until' => time() + ($slice === null ? 30 : 120), 'rows' => $rows];
	}
	$_SESSION['fv_nbar_query_cache'] = $cache;
	return $slice === null || !is_array($rows) ? $rows : array_slice($rows, $slice, 101);
}

/**
 * Build an exact grouped connection-page query over a trusted source.
 *
 * @param string $source Trusted summary or raw SQL source.
 * @param string $where Validated filter predicate.
 * @param int $page One-based connection page.
 *
 * @return string
 */
function fv_nbar_connections_sql($source, $where, $page) {
	// Use compact name ranks while grouping; resolve display names after paging.
	$map = '(SELECT router,app_id,DENSE_RANK() OVER (ORDER BY name) name_id FROM plugin_flowview_nbar_names)';
	$group = 'd.app_id,n.name_id,d.src,d.dst,d.sport,d.dport,d.protocol';
	$inner = 'SELECT d.app_id,n.name_id,SUM(d.bytes) bytes,SUM(d.packets) packets,SUM(d.flows) records,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen,d.src,d.dst,d.sport,d.dport,d.protocol FROM ' . $source . ' d JOIN plugin_flowview_devices l ON l.id=d.listener_id LEFT JOIN ' . $map . ' n ON n.router=l.allowfrom AND n.app_id=d.app_id WHERE ' . $where . ' GROUP BY ' . $group . ' ORDER BY bytes DESC,' . $group . ' LIMIT 101 OFFSET ' . (($page - 1) * 100);
	return 'SELECT d.app_id,n.name,d.bytes,d.packets,d.records,d.first_seen,d.last_seen,INET6_NTOA(d.src) src,INET6_NTOA(d.dst) dst,d.sport,d.dport,d.protocol FROM (' . $inner . ') d LEFT JOIN (SELECT name,DENSE_RANK() OVER (ORDER BY name) name_id FROM plugin_flowview_nbar_names GROUP BY name) n ON n.name_id=d.name_id ORDER BY d.bytes DESC,d.app_id,n.name,d.src,d.dst,d.sport,d.dport,d.protocol';
}
