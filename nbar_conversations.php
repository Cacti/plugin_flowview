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

// Conversation direction uses configured site networks and selected listeners.
if (empty($nbar_preferences['prefixes'])) {
	echo '<p class="textInfo nbarReportText">' . html_escape(__('Configure your site networks in Application settings to label internal and external devices. Top applications and Connections work directly with exported IPv4 and IPv6 addresses.', 'flowview')) . '</p>';
	return;
}
$where = $listener_scope . ' AND d.minute>=? AND d.minute<?';
$args = $lid === 0 ? [$start, $end] : [$lid, $start, $end];
if ($family !== 'all') {
	$where .= ' AND LENGTH(d.src)=' . ($family === '6' ? 16 : 4);
}
if ($aid !== '') {
	$where .= ' AND d.app_id=?';
	$args[] = $aid;
}
if ($host !== '') {
	$where .= ' AND (d.src=INET6_ATON(?) OR d.dst=INET6_ATON(?))';
	array_push($args, $host, $host);
}
$sl = fv_nbar_local_sql('d.src');
$dl = fv_nbar_local_sql('d.dst');
$where .= " AND (($sl AND NOT $dl) OR ($dl AND NOT $sl))";
$fields = "d.app_id,n.name,INET6_NTOA(IF($sl,d.src,d.dst)) internal,INET6_NTOA(IF($sl,d.dst,d.src)) external,SUM(IF($sl,d.bytes,0)) upload,SUM(IF($dl,d.bytes,0)) download,SUM(d.bytes) total,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen";
$nbar_source = fv_nbar_summary_source('pairs', $lid === 0 ? $combined_lids : [$lid], $start, $end);
$sql = "SELECT $fields FROM $nbar_source d JOIN plugin_flowview_devices l ON l.id=d.listener_id LEFT JOIN plugin_flowview_nbar_names n ON n.router=COALESCE(NULLIF(d.router,SPACE(0)),l.allowfrom) AND n.app_id=d.app_id WHERE $where GROUP BY d.app_id,n.name,internal,external ORDER BY total DESC LIMIT 100";
$rows = fv_nbar_query_cached($sql, $args);
if (!is_array($rows)) {
	echo '<p class="textInfo nbarReportText">' . fv_nbar_h(__('Unable to load conversations. Please try again.', 'flowview')) . '</p>';
	return;
}
$convbase = $base . '&mode=conversations' . ($aid !== '' ? '&app=' . $aid : '');
if ($aid !== '') {
	echo '<p class="textInfo nbarReportText"><a class="ui-button ui-corner-all ui-widget" href="' . fv_nbar_h($base . '&mode=apps') . '">' . html_escape(__('← Back to top applications', 'flowview')) . '</a></p>';
}
echo '<p class="textInfo nbarReportText">' . html_escape(__('Internal Host ↔ External Host. Click a device to see its individual connections. Bandwidth is averaged over the selected timespan.', 'flowview')) . '</p>';
echo '<div class="flowview-nbar-table-wrap"><table id="nbarConversationTable" class="cactiTable" style="width:100%"><thead><tr class="tableHeader">';
foreach ([__('Internal Host', 'flowview'), __('External Host', 'flowview'), __('Application', 'flowview'), __('Download (GB)', 'flowview'), __('Upload (GB)', 'flowview'), __('Download (Avg Mbps)', 'flowview'), __('Upload (Avg Mbps)', 'flowview'), __('First seen', 'flowview'), __('Last seen', 'flowview')] as $index => $h) {
	$unit = [3 => 'data', 4 => 'data', 5 => 'rate', 6 => 'rate'][$index] ?? '';
	echo '<th data-nbar-unit="' . $unit . '">' . fv_nbar_h($h) . '</th>';
}
echo '</tr></thead><tbody>';
$i = 0;
foreach ($rows as $r) {
	$link = $base . '&parent_app=' . rawurlencode($aid) . '&mode=connections&app=' . $r['app_id'] . '&internal=' . rawurlencode($r['internal']) . '&external=' . rawurlencode($r['external']);
	echo '<tr class="' . ($i++ % 2 ? 'even' : 'odd') . '">';
	foreach (['internal', 'external'] as $f) {
		$dns = fv_nbar_dns($r[$f]);
		echo '<td><a href="' . fv_nbar_h($link) . '">' . fv_nbar_h($r[$f]) . '</a><br><small>' . fv_nbar_h($dns) . '</small></td>';
	}
	echo '<td>' . fv_nbar_h($r['name'] ?? __('Awaiting name (%s)', $r['app_id'], 'flowview')) . '</td>';
	foreach ([$r['download'] / 1e9, $r['upload'] / 1e9, $r['download'] * 8 / $seconds / 1e6, $r['upload'] * 8 / $seconds / 1e6] as $j => $v) {
		$precision = $j < 2 ? 4 : 3;
		$threshold = pow(10, -$precision);
		echo '<td class="right" data-text="' . $v . '">' . ($v > 0 && $v < $threshold ? '&lt;' . number_format($threshold, $precision) : number_format($v, $precision)) . '</td>';
	}
	echo fv_nbar_seen_cells($r) . '</tr>';
}
if (!$rows) {
	echo '<tr><td colspan="9">' . html_escape(__('No conversations for this period. Allow 1–2 minutes for exports, or choose a longer timespan.', 'flowview')) . '</td></tr>';
}
echo '</tbody></table></div><details class="textInfo nbarReportText"><summary>' . html_escape(__('Report information', 'flowview')) . '</summary><p class="textInfo nbarReportText">' . html_escape(__('First seen and Last seen show the earliest and latest FlowView observations within the selected period, at minute precision. They do not identify continuous activity or exact flow start/end times. Unavailable means the retained summary has no complete observation dates.', 'flowview')) . '</p><p class="textInfo nbarReportText">Top 100 conversations by combined data volume. Each row groups an Internal Host, External Host and application across both directions. Uses only the selected LAN captures to avoid counting WAN copies. Local and multicast traffic is excluded. DNS names identify servers, not the exact website requested. History retention: ' . fv_nbar_retention_days() . ' days.</p></details>';

echo '<script ' . plugin_flowview_csp_nonce() . '>flowviewNbarInitTable(' . json_encode([
	'table' => '#nbarConversationTable',
	'sortKey' => 'flowview_nbar_conversation_sort',
	'conversations' => true,
	'resizeLabel' => __('Drag to resize column', 'flowview')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';
