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

/**
 * Render the top application totals for the current report filters.
 *
 * @param string $scope Trusted capture predicate.
 * @param array $args Prepared statement values.
 * @param string $family IP family selection.
 * @param string $host Optional endpoint IP filter.
 *
 * @return void
 */
function fv_nbar_chart($scope, $args, $family, $host = '') {
	$where = $scope . ' AND d.minute>=? AND d.minute<?';
	if ($family !== 'all') {
		$where .= ' AND LENGTH(d.src)=' . ($family === '6' ? 16 : 4);
	}
	global $lid, $combined_lids;
	$source = fv_nbar_summary_source($host === '' ? 'apps' : 'pairs', $lid === 0 ? $combined_lids : [$lid], $args[cacti_sizeof($args) - 2], $args[cacti_sizeof($args) - 1]);
	if ($host !== '' && filter_var($host, FILTER_VALIDATE_IP)) {
		$where .= ' AND (d.src=INET6_ATON(?) OR d.dst=INET6_ATON(?))';
		array_push($args, $host, $host);
	}
	$rows = fv_nbar_query_cached(
		"SELECT COALESCE(NULLIF(n.name,''),CONCAT('Application ',d.app_id)) name,SUM(d.bytes) bytes FROM (SELECT d.listener_id,d.app_id,d.router,SUM(d.bytes) bytes FROM " . $source . " d WHERE " . $where . ' GROUP BY d.listener_id,d.app_id,d.router) d JOIN plugin_flowview_devices l ON l.id=d.listener_id LEFT JOIN plugin_flowview_nbar_names n ON n.router=COALESCE(NULLIF(d.router,SPACE(0)),l.allowfrom) AND n.app_id=d.app_id GROUP BY COALESCE(NULLIF(n.name,\'\'),CONCAT(\'Application \',d.app_id)) ORDER BY bytes DESC',
		$args
	);
	if (!is_array($rows)) {
		echo '<aside class="nbarChart"><p>' . fv_nbar_h(__('Unable to load application chart. Please try again.', 'flowview')) . '</p></aside>';
		return;
	}
	$total = array_sum(array_column($rows, 'bytes'));
	$items = array_slice($rows, 0, 5);
	$other = $total - array_sum(array_column($items, 'bytes'));
	if ($other > 0) {
		$items[] = ['name' => __('Other', 'flowview'), 'bytes' => $other];
	}
	$colors = ['#4c9bd6', '#73ae53', '#e2a545', '#a780c5', '#de7970', '#91979d'];
	$stops = [];
	$legend = '';
	$position = 0;
	foreach ($items as $i => $item) {
		$bytes = (float) $item['bytes'];
		if ($bytes <= 0) {
			continue;
		}
		$next = min(100, $position + $bytes / $total * 100);
		$stops[] = $colors[$i] . ' ' . round($position, 5) . '% ' . round($next, 5) . '%';
		$position = $next;
		$value = $bytes >= 1000000000 ? number_format($bytes / 1000000000, 2) . ' GB' : number_format($bytes / 1000000, 2) . ' MB';
		$legend .= '<li><span class="nbarChartKey" style="background:' . $colors[$i] . '"></span><span class="nbarChartName">' . fv_nbar_h($item['name']) . '</span><span>' . $value . '</span></li>';
	}
	echo '<aside class="nbarChart" aria-label="' . fv_nbar_h(__('Top 5 applications by data', 'flowview')) . '"><strong>' . __('Top 5 applications', 'flowview') . '</strong>';
	if ($total > 0) {
		echo '<div class="nbarChartBody"><div class="nbarChartPie" role="img" aria-label="' . fv_nbar_h(__('Application data share for the selected period', 'flowview')) . '" style="background:conic-gradient(' . implode(',', $stops) . ')"></div><ul>' . $legend . '</ul></div>';
	} else {
		echo '<p>' . __('No application data in this period', 'flowview') . '</p>';
	}
	echo '</aside>';
}
