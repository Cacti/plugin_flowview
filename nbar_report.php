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

// Included only through FlowView's authenticated entry point.
if (!isset($config) || !function_exists('flowview_connect')) {
	exit;
}

require_once($config['base_path'] . '/lib/time.php');
require_once(__DIR__ . '/includes/nbar_common.php');
require_once(__DIR__ . '/includes/nbar_dns.php');
require_once(__DIR__ . '/includes/nbar_preferences.php');
require_once(__DIR__ . '/includes/nbar_timespan.php');
require_once(__DIR__ . '/includes/nbar_feed_status.php');
require_once(__DIR__ . '/includes/nbar_query.php');
require_once(__DIR__ . '/includes/nbar_chart.php');
require_once(__DIR__ . '/includes/nbar_count.php');
require_once(__DIR__ . '/includes/nbar_connections.php');

// arrays.php populates report variables in this scope, so it is included on each request.
include(__DIR__ . '/includes/arrays.php');

/**
 * Report arrays supplied by Cacti core and includes/arrays.php.
 *
 * @var array<int, string>               $graph_timespans
 * @var array<int, array<string, mixed>> $stat_report_array
 * @var array<int, array<string, mixed>> $print_report_array
 */
if ((int) read_config_option('flowview_nbar_schema_version') < 2) {
	plugin_flowview_check_upgrade();
	print '<p class="textInfo nbarReportText">' . html_escape(__('NBAR history is being upgraded. Refresh this report shortly.', 'flowview')) . '</p>';
	bottom_footer();
	exit;
}

// Report controls use GET links or a protected POST form.
$nbar_request = [];

foreach (['listener', 'period', 'family', 'mode', 'app', 'host', 'page', 'parent_app', 'context', 'predefined_timespan', 'date1', 'date2', 'lock_range', 'internal', 'external'] as $key) {
	$value = get_nfilter_request_var($key, null);

	if (is_scalar($value)) {
		$nbar_request[$key] = (string) $value;
	}
}

$devices          = flowview_db_fetch_assoc("SELECT id,name FROM plugin_flowview_devices WHERE enabled='on' ORDER BY id");
$nbar_preferences = fv_nbar_preferences_init($devices);
$lid              = (int) ($nbar_request['listener'] ?? ($devices[0]['id'] ?? 0));
$valid            = array_column($devices, 'id');

if ($lid !== 0 && !in_array($lid, $valid, true)) {
	$lid = (int) ($valid[0] ?? 0);
}
$seconds = (int) ($nbar_request['period'] ?? 300);

if (!in_array($seconds, [300, 900, 3600, 86400, 604800], true)) {
	$seconds = 300;
}
$family = (string) ($nbar_request['family'] ?? 'all');

if (!in_array($family, ['all', '4', '6'], true)) {
	$family = 'all';
}
$mode = (string) ($nbar_request['mode'] ?? 'conversations');

if (!in_array($mode, ['apps', 'conversations', 'connections'], true)) {
	$mode = 'conversations';
}
$combined_lids = array_values(array_intersect($nbar_preferences['listeners'], array_map('intval', $valid)));

if (!empty($GLOBALS['fv_nbar_preferences_saved']) && isset_request_var('combine_apply') && cacti_sizeof($combined_lids) >= 2) {
	$lid = 0;
}

if ($lid === 0 && !$combined_lids) {
	$lid = (int) ($valid[0] ?? 0);
}
// Capture ids start at 1, so 0 matches nothing when no capture device exists and IN () is never built.
$listener_scope = $lid === 0 ? 'd.listener_id IN (' . implode(',', $combined_lids ?: [0]) . ')' : 'd.listener_id=?';
$aid            = (string) ($nbar_request['app'] ?? '');

if (!preg_match('/^[a-f0-9]{2,32}$/D', $aid)) {
	$aid = '';
}
$host = (string) ($nbar_request['host'] ?? '');

if (!filter_var($host, FILTER_VALIDATE_IP)) {
	$host = '';
}
$page       = max(1, min(1000000, (int) ($nbar_request['page'] ?? 1)));
$parent_app = (string) ($nbar_request['parent_app'] ?? '');

if (!preg_match('/^[a-f0-9]{2,32}$/D', $parent_app)) {
	$parent_app = '';
}
$end              = (int) (floor(time() / 60) * 60) - 60;
$start            = $end - $seconds;
$timespan_context = json_decode((string) ($nbar_request['context'] ?? ''), true);
$timespan         = (int) ($nbar_request['predefined_timespan'] ?? ($timespan_context['predefined_timespan'] ?? 0));
$custom_start     = strtotime((string) ($nbar_request['date1'] ?? ''));
$custom_end       = strtotime((string) ($nbar_request['date2'] ?? ''));
$has_dates        = $custom_start && $custom_end && $custom_end > $custom_start;
$custom           = $has_dates;

if (!isset($nbar_request['predefined_timespan']) && !isset($timespan_context['predefined_timespan']) && !$custom) {
	$legacy = [3600 => GT_LAST_HOUR, 86400 => GT_LAST_DAY, 604800 => GT_LAST_WEEK];

	if (isset($nbar_request['period'])) {
		$timespan = $legacy[$seconds] ?? 0;
	} else {
		$timespan = GT_LAST_HALF_HOUR;
	}
}

if ($timespan > 0 && $timespan !== 1000 && !isset($graph_timespans[$timespan])) {
	$timespan = GT_LAST_HOUR;
}
$nbar_now            = (int) (floor(time() / 60) * 60);
$nbar_retention_days = fv_nbar_retention_days();
$nbar_timespans      = fv_nbar_timespan_options($graph_timespans, $nbar_now, $nbar_retention_days, read_user_setting('first_weekdayid'));
$nbar_range_notice   = '';

if ($timespan > 0 && !isset($nbar_timespans[$timespan])) {
	$timespan          = GT_LAST_HALF_HOUR;
	$nbar_range_notice = __('That period is outside the %s-day NBAR retention setting. Showing the last half hour.', $nbar_retention_days, 'flowview');
	unset($nbar_request['lock_range']);
}

if ($timespan === 1000) {
	$end    = $nbar_now;
	$start  = $end - $nbar_retention_days * 86400;
	$custom = false;
} elseif ($timespan > 0) {
	$nbar_span = [];
	get_timespan($nbar_span, $nbar_now, $timespan, read_user_setting('first_weekdayid'));
	$start  = strtotime($nbar_span['current_value_date1']);
	$end    = strtotime($nbar_span['current_value_date2']);
	$custom = false;
} else {
	if ($custom) {
		$start = $custom_start;
		$end   = $custom_end;
	} else {
		$end   = (int) (floor(time() / 60) * 60);
		$start = $end - $seconds;
	}
	$timespan = 0;
	$custom   = true;
}

if ($timespan === 0 && $has_dates) {
	$start = $custom_start;
	$end   = $custom_end;
}

if (!empty($nbar_request['lock_range']) && $has_dates) {
	$start = $custom_start;
	$end   = $custom_end;
}
[$start, $end, $nbar_dates_adjusted] = fv_nbar_bound_dates($start, $end, $nbar_now, $nbar_retention_days);

if ($nbar_dates_adjusted) {
	$timespan          = 0;
	$custom            = true;
	$nbar_range_notice = __('Dates adjusted to the %s-day NBAR retention window.', $nbar_retention_days, 'flowview');
}
// The primary key starts with capture/minute, so each lookup reads its first record.
$nbar_earliest   = null;
$nbar_data_error = false;

foreach ($lid === 0 ? $combined_lids : [$lid] as $nbar_capture_id) {
	$nbar_first = fv_nbar_data_edge($nbar_capture_id);

	if ($nbar_first === false) {
		$nbar_data_error = true;
	} elseif (isset($nbar_first['minute'])) {
		$nbar_first_minute = max($nbar_now - $nbar_retention_days * 86400, (int) $nbar_first['minute']);
		$nbar_earliest     = $nbar_earliest === null ? $nbar_first_minute : min($nbar_earliest, $nbar_first_minute);
	}
}
$seconds = max(1, $end - $start);
$context = json_decode((string) ($nbar_request['context'] ?? ''), true);

if (!is_array($context)) {
	$context = [];
}
$allowed = ['query', 'report', 'predefined_timespan', 'ex_addr', 'sortfield', 'cutofflines', 'cutoffoctets', 'exclude', 'graph_type', 'graph_height', 'date1', 'date2', 'domains'];
$context = array_intersect_key($context, array_flip($allowed));

foreach ($context as $key => $value) {
	if (!is_scalar($value)) {
		unset($context[$key]);
	}
}

if (!preg_match('/^[sp][0-9]+$/', (string) ($context['report'] ?? ''))) {
	$context['report'] = 's10';
}
$report_key = (int) substr($context['report'], 1);

if (!isset(($context['report'][0] === 's' ? $stat_report_array : $print_report_array)[$report_key])) {
	$context['report'] = 's10';
}
$context_json                  = json_encode($context, JSON_THROW_ON_ERROR);
$base                          = 'flowview.php?' . http_build_query(['action' => 'view', 'report' => 'nbar', 'listener' => $lid, 'predefined_timespan' => $timespan, 'lock_range' => 1, 'family' => $family, 'date1' => date('Y-m-d H:i', $start), 'date2' => date('Y-m-d H:i', $end), 'context' => $context_json, 'host' => $host]);
$return                        = $context;
$return['action']              = 'view';
$return['device_id']           = $lid;
$return['date1']               = date('Y-m-d H:i', $start);
$return['date2']               = date('Y-m-d H:i', $end);
$return['predefined_timespan'] = $timespan;
$return['section']             = 'flows';
$return_url                    = 'flowview.php?' . http_build_query($return);
parse_str((string) parse_url($base, PHP_URL_QUERY), $section_params);
$section_params['mode'] = $mode;
fv_section_save('nbar', $section_params);
fv_section_navigation('nbar');
html_start_box(__('Application Report: Cisco Apps Recognition (NBAR)', 'flowview'), '100%', false, 3, 'center', '');
print '<tr class="even"><td style="padding:4px 8px">';
fv_nbar_feed_status($devices, array_map('intval', $lid === 0 ? $combined_lids : [$lid]));
print '</td></tr>';
print '<tr class="even"><td><div class="nbarReportBar"><form id="nbar_filter" method="post" action="flowview.php">';
print '<input type="hidden" name="__csrf_magic" value="' . fv_nbar_h(csrf_get_tokens()) . '">';

foreach (['action' => 'view', 'report' => 'nbar', 'context' => $context_json, 'app' => $aid, 'parent_app' => $parent_app] as $key => $value) {
	print '<input type="hidden" name="' . $key . '" value="' . fv_nbar_h($value) . '">';
}

foreach (['internal', 'external'] as $f) {
	if ($mode === 'connections' && filter_var($nbar_request[$f] ?? '', FILTER_VALIDATE_IP)) {
		print '<input type="hidden" name="' . $f . '" value="' . fv_nbar_h($nbar_request[$f] ?? '') . '">';
	}
}
print '<table class="filterTable"><tr><td>' . html_escape(__('Router / capture', 'flowview')) . '</td><td><select name="listener">';

if (cacti_sizeof($combined_lids) > 1) {
	print '<option value="0"' . ($lid === 0 ? ' selected' : '') . '>' . html_escape(__('Combined routers', 'flowview')) . '</option>';
}

foreach ($devices as $d) {
	print '<option value="' . (int) $d['id'] . '"' . ($lid === (int) $d['id'] ? ' selected' : '') . '>' . fv_nbar_h($d['name']) . '</option>';
}
print '</select></td><td><button class="ui-button ui-corner-all ui-widget" type="submit">' . html_escape(__('Go', 'flowview')) . '</button></td><td><a class="ui-button ui-corner-all ui-widget" href="' . fv_nbar_h('flowview.php?action=view&report=nbar&listener=' . $lid . '&context=' . rawurlencode($context_json)) . '">' . html_escape(__('Clear', 'flowview')) . '</a></td><td><button type="button" id="nbarCombineOptions" class="ui-button ui-corner-all ui-widget">' . html_escape(__('Options', 'flowview')) . '</button></td><td><button type="button" id="nbarSetupLink" class="ui-button ui-corner-all ui-widget">' . html_escape(__('Setup guide', 'flowview')) . '</button></td></tr></table>';
print '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:0 8px"><table class="filterTable" style="width:auto;margin:0"><tr><td>' . html_escape(__('IP version', 'flowview')) . '</td><td><select name="family">';

foreach (['all' => __('IPv4 and IPv6', 'flowview'), '4' => 'IPv4', '6' => 'IPv6'] as $v => $t) {
	print '<option value="' . $v . '"' . ((string) $v === $family ? ' selected' : '') . '>' . $t . '</option>';
}
print '</select></td><td>' . html_escape(__('Show', 'flowview')) . '</td><td><select name="mode"><option value="conversations"' . ($mode === 'conversations' ? ' selected' : '') . '>' . html_escape(__('Conversations', 'flowview')) . '</option><option value="apps"' . ($mode === 'apps' ? ' selected' : '') . '>' . html_escape(__('Top applications', 'flowview')) . '</option><option value="connections"' . ($mode === 'connections' ? ' selected' : '') . '>' . html_escape(__('Connections', 'flowview')) . '</option></select></td></tr></table>';
print '<table class="filterTable" style="width:auto;margin:0"><tr><td>' . html_escape(__('Filter by IP', 'flowview')) . '</td><td><input type="text" name="host" size="30" placeholder="' . plugin_flowview_escape_attr(__('Any IPv4 or IPv6 address', 'flowview')) . '" title="' . plugin_flowview_escape_attr(__('Show traffic where this IP is the source or destination. Leave blank to show all.', 'flowview')) . '" value="' . fv_nbar_h($host) . '"></td></tr></table></div>';
print '<table class="filterTable"><tr><td>' . html_escape(__('Timespan', 'flowview')) . '</td><td><select name="predefined_timespan" id="nbar_period"><option value="0"' . ($custom ? ' selected' : '') . '>' . html_escape(__('Custom', 'flowview')) . '</option>';

foreach ($nbar_timespans as $v => $t) {
	print '<option value="' . $v . '"' . ($v === $timespan ? ' selected' : '') . '>' . fv_nbar_h($t) . '</option>';
}
print '</select></td><td>' . html_escape(__('From', 'flowview')) . '</td><td><input type="text" name="date1" id="nbar_date1" size="16" value="' . fv_nbar_h(date('Y-m-d H:i', $start)) . '"></td><td>' . html_escape(__('To', 'flowview')) . '</td><td><input type="text" name="date2" id="nbar_date2" size="16" value="' . fv_nbar_h(date('Y-m-d H:i', $end)) . '"></td></tr></table>';

if ($nbar_range_notice !== '') {
	print '<p role="status">' . fv_nbar_h($nbar_range_notice) . '</p>';
}
fv_nbar_preferences_render($devices, $base, $mode, $aid, $parent_app);
print '</form>';

if ($nbar_data_error) {
	print '<p class="alert">' . fv_nbar_h(__('Unable to read the NBAR records. Please try again.', 'flowview')) . '</p>';
} else {
	fv_nbar_chart($listener_scope, $lid === 0 ? [$start, $end] : [$lid, $start, $end], $family, $host);
}
print '</div></td></tr>';
html_end_box();
include(__DIR__ . '/nbar_setup_guide.php');

print '<script src="' . plugin_flowview_escape_attr($config['url_path'] . 'plugins/flowview/js/nbar-report.js') . '"></script>';
print '<script src="' . $config['url_path'] . 'include/js/jquery.tablesorter.widgets.js"></script>';
print '<script src="' . plugin_flowview_escape_attr($config['url_path'] . 'plugins/flowview/js/nbar-tables.js') . '"></script>';

if ($nbar_data_error) {
	bottom_footer();

	return;
}

if ($mode === 'conversations') {
	include(__DIR__ . '/nbar_conversations.php');
	bottom_footer();
	exit;
}

if ($mode === 'connections' && $aid !== '' && !filter_var($nbar_request['internal'] ?? '', FILTER_VALIDATE_IP)) {
	print '<p class="textInfo nbarReportText"><a class="ui-button ui-corner-all ui-widget" href="' . fv_nbar_h($base . '&mode=apps') . '">' . html_escape(__('← Back to top applications', 'flowview')) . '</a></p>';
}
$where = $listener_scope . ' AND d.minute>=? AND d.minute<?';
$args  = $lid === 0 ? [$start, $end] : [$lid, $start, $end];

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

$internal = (string) ($nbar_request['internal'] ?? '');
$external = (string) ($nbar_request['external'] ?? '');

if ($mode === 'connections' && filter_var($internal, FILTER_VALIDATE_IP) && filter_var($external, FILTER_VALIDATE_IP)) {
	$where .= ' AND ((d.src=INET6_ATON(?) AND d.dst=INET6_ATON(?)) OR (d.src=INET6_ATON(?) AND d.dst=INET6_ATON(?)))';
	array_push($args, $internal, $external, $external, $internal);
	print '<p class="textInfo nbarReportText"><a class="ui-button ui-corner-all ui-widget" href="' . fv_nbar_h($base . '&mode=conversations' . ($parent_app !== '' ? '&app=' . $parent_app : '')) . '">' . html_escape(__('← Back to conversations', 'flowview')) . '</a> &nbsp; ' . fv_nbar_h($internal) . ' &harr; ' . fv_nbar_h($external) . '</p>';
}
$fields = 'd.app_id,n.name,SUM(d.bytes) bytes,SUM(d.packets) packets,SUM(d.flows) records,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen';
$group  = 'd.app_id,n.name';

if ($mode === 'connections') {
	$fields .= ',INET6_NTOA(d.src) src,INET6_NTOA(d.dst) dst,d.sport,d.dport,d.protocol';
	$group .= ',d.src,d.dst,d.sport,d.dport,d.protocol';
}
$nbar_source = null;

if ($mode === 'apps') {
	try {
		$nbar_source = fv_nbar_summary_source($host === '' ? 'apps' : 'pairs', $lid === 0 ? $combined_lids : [$lid], $start, $end);
	} catch (RuntimeException $e) {
		cacti_log('NBAR applications: ' . $e->getMessage(), false, 'FLOWVIEW');
		print '<p class="alert">' . fv_nbar_h(__('Unable to load applications. Please try again.', 'flowview')) . '</p>';
		bottom_footer();

		return;
	}
}

if ($mode === 'apps') {
	// Resolve names after reducing raw records to one row per capture/application.
	$sql = 'SELECT d.app_id,n.name,SUM(d.bytes) bytes,SUM(d.packets) packets,SUM(d.flows) records,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen FROM (SELECT d.listener_id,d.app_id,d.router,SUM(d.bytes) bytes,SUM(d.packets) packets,SUM(d.flows) flows,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen FROM ' . $nbar_source . ' d WHERE ' . $where . ' GROUP BY d.listener_id,d.app_id,d.router) d JOIN plugin_flowview_devices l ON l.id=d.listener_id LEFT JOIN plugin_flowview_nbar_names n ON n.router=COALESCE(NULLIF(d.router,SPACE(0)),l.allowfrom) AND n.app_id=d.app_id GROUP BY d.app_id,n.name ORDER BY bytes DESC LIMIT 100';
}

if ($mode === 'connections') {
	try {
		$nbar_count_key = fv_nbar_count_context('', $where, $args, $end, $lid === 0 ? $combined_lids : [$lid], $start);
		$rows           = fv_nbar_connections_cached($lid === 0 ? $combined_lids : [$lid], $start, $end, $where, $args, $page);
	} catch (Throwable $e) {
		cacti_log('NBAR connection page: ' . $e->getMessage(), false, 'FLOWVIEW');
		print '<p class="alert">' . fv_nbar_h(__('Unable to load connections. Try a shorter timespan or filter by IP.', 'flowview')) . '</p>';
		bottom_footer();

		return;
	}
} else {
	$rows = fv_nbar_query_cached($sql, $args);

	if (!is_array($rows)) {
		print '<p class="alert">' . fv_nbar_h(__('Unable to load applications. Please try again.', 'flowview')) . '</p>';
		bottom_footer();

		return;
	}
}
$has_next = $mode === 'connections' && cacti_sizeof($rows) > 100;

if ($has_next) {
	$rows = array_slice($rows, 0, 100);
}
$nbar_pagination = '';

if ($mode === 'connections') {
	$page_params     = ['mode' => 'connections', 'app' => $aid, 'parent_app' => $parent_app, 'host' => $host, 'internal' => $internal, 'external' => $external];
	$nbar_page_key   = hash('sha256', $base . '&' . http_build_query($page_params));
	$nbar_pagination = '<div class="nbarPagination textInfo nbarReportText" style="display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:8px 4px">';
	$nbar_page_total = '<span class="nbarPageTotal" data-count-key="' . fv_nbar_h($nbar_count_key) . '" aria-live="polite">' . fv_nbar_h(__('Counting total pages…', 'flowview')) . '</span>';
	$nbar_pagination .= '<label>' . fv_nbar_h(__('Page', 'flowview')) . ' <input class="nbarPageNumber" type="number" required min="1" max="1000000" value="' . $page . '" style="width:100px"></label><button type="button" class="nbarPageJump ui-button ui-corner-all ui-widget" data-url="' . fv_nbar_h($base . '&' . http_build_query($page_params)) . '">' . __('Go', 'flowview') . '</button>' . $nbar_page_total . '<span>' . fv_nbar_h(__('Up to 100 connections per page, ordered by data volume.', 'flowview')) . '</span>';

	if ($page > 1) {
		$nbar_pagination .= '<a class="nbarPageLink ui-button ui-corner-all ui-widget" href="' . fv_nbar_h($base . '&' . http_build_query($page_params + ['page' => $page - 1])) . '">' . __('Previous', 'flowview') . '</a>';
	}

	if ($has_next && $page < 1000000) {
		$nbar_pagination .= '<a class="nbarPageLink ui-button ui-corner-all ui-widget" href="' . fv_nbar_h($base . '&' . http_build_query($page_params + ['page' => $page + 1])) . '">' . __('Next', 'flowview') . '</a>';
	}
	$nbar_pagination .= '</div>';
	print $nbar_pagination;
}

if ($mode === 'connections' && $aid !== '') {
	$selected_name = $rows[0]['name'] ?? __('Application %s', $aid, 'flowview');
	print '<p class="textInfo nbarReportText"><strong>' . fv_nbar_h(strtoupper($selected_name)) . ' &mdash; Connections</strong></p>';
}
print '<div class="flowview-nbar-table-wrap"><table class="cactiTable" id="nbarTable" ><thead><tr class="tableHeader"><th>' . html_escape(__('Application', 'flowview')) . '</th>';

if ($mode === 'connections') {
	print '<th>' . html_escape(__('Source IP', 'flowview')) . '</th><th data-nbar-unit="dns" title="' . plugin_flowview_escape_attr(__('Reverse DNS hostname', 'flowview')) . '">' . html_escape(__('Source DNS', 'flowview')) . '</th><th>' . html_escape(__('Source port', 'flowview')) . '</th><th>' . html_escape(__('Destination IP', 'flowview')) . '</th><th data-nbar-unit="dns" title="' . plugin_flowview_escape_attr(__('Reverse DNS hostname', 'flowview')) . '">' . html_escape(__('Destination DNS', 'flowview')) . '</th><th>' . html_escape(__('Destination port', 'flowview')) . '</th><th>' . html_escape(__('Protocol', 'flowview')) . '</th>';
}
print '<th data-nbar-unit="data">' . html_escape(__('Data (GB)', 'flowview')) . '</th><th data-nbar-unit="rate">' . html_escape(__('Bandwidth (Avg Mbps)', 'flowview')) . '</th><th>' . html_escape(__('Packets', 'flowview')) . '</th><th>' . html_escape(__('Records', 'flowview')) . '</th><th title="' . plugin_flowview_escape_attr(__('First FlowView observation in the selected period', 'flowview')) . '">' . html_escape(__('First seen', 'flowview')) . '</th><th title="' . plugin_flowview_escape_attr(__('Last FlowView observation in the selected period', 'flowview')) . '">' . html_escape(__('Last seen', 'flowview')) . '</th></tr></thead><tbody>';
$nbar_row = 0;

foreach ($rows as $r) {
	$name = $r['name'] ?? __('Awaiting name (%s)', $r['app_id'], 'flowview');
	print '<tr class="' . (($nbar_row++ % 2) ? 'even' : 'odd') . '"><td>';

	if ($mode === 'connections') {
		print fv_nbar_h($name);
	} else {
		print '<a href="' . fv_nbar_h($base . '&mode=conversations&app=' . $r['app_id']) . '">' . fv_nbar_h($name) . '</a>';
	}
	print '</td>';

	if ($mode === 'connections') {
		foreach (['src', 'sport', 'dst', 'dport'] as $f) {
			print '<td>' . fv_nbar_h($r[$f]) . '</td>';

			if ($f === 'src' || $f === 'dst') {
				print '<td>' . fv_nbar_h(fv_nbar_dns($r[$f])) . '</td>';
			}
		}
		print '<td>' . fv_nbar_h([6 => 'TCP', 17 => 'UDP', 1 => 'ICMP', 58 => 'ICMPv6'][$r['protocol']] ?? $r['protocol']) . '</td>';
	}

	print '<td class="right" data-text="' . ($r['bytes'] / 1e9) . '">' . ($r['bytes'] > 0 && $r['bytes'] < 100000 ? '&lt;0.0001' : number_format($r['bytes'] / 1e9, 4)) . '</td><td class="right" data-text="' . ($r['bytes'] * 8 / $seconds / 1e6) . '">' . ($r['bytes'] > 0 && $r['bytes'] * 8 / $seconds / 1e6 < 0.001 ? '&lt;0.001' : number_format($r['bytes'] * 8 / $seconds / 1e6, 3)) . '</td><td class="right" data-text="' . $r['packets'] . '">' . number_format($r['packets']) . '</td><td class="right" data-text="' . $r['records'] . '">' . number_format($r['records']) . '</td>' . fv_nbar_seen_cells($r) . '</tr>';
}

if (!$rows) {
	print '<tr><td colspan="14">' . html_escape(__('No application records in this view yet. Try a longer period or another traffic view.', 'flowview')) . '</td></tr>';
}
print '</tbody></table></div>';
print $nbar_pagination;

if ($mode === 'connections') {
	print '<script ' . plugin_flowview_csp_nonce() . '>$(function(){var key=' . json_encode($nbar_count_key) . ',tries=0,format=' . json_encode(__('of {pages} pages ({connections} connections)', 'flowview'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ',unavailable=' . json_encode(__('Total pages unavailable', 'flowview'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';
 function labels(){return $(".nbarPageTotal").filter(function(){return this.dataset.countKey===key;});}
 function countPages(){if(!labels().length)return;$.ajax({url:"flowview.php",data:{action:"nbar_count",key:key},dataType:"json",timeout:100000,global:false}).done(function(r){var el=labels();if(!el.length)return;if(r.status==="ready"){el.text(format.replace("{pages}",r.pages.toLocaleString()).replace("{connections}",r.total.toLocaleString()));el.closest(".nbarPagination").find(".nbarPageNumber").attr("max",Math.max(1,r.pages));}else if(r.status==="busy"&&++tries<36){setTimeout(countPages,5000);}else{el.text(unavailable);}}).fail(function(){labels().text(unavailable);});}countPages();});</script>';
}

print '<details class="textInfo nbarReportText"><summary>' . html_escape(__('Report information', 'flowview')) . '</summary><p class="textInfo nbarReportText">' . html_escape(__('First seen and Last seen show the earliest and latest FlowView observations within the selected period, at minute precision. They do not identify continuous activity or exact flow start/end times. Unavailable means the retained summary has no complete observation dates.', 'flowview')) . '</p><p class="textInfo nbarReportText">' . html_escape(__('Results are ordered by data volume; Connections shows up to 100 per page. Column sorting applies to the displayed page. NBAR history uses the configured retention setting. Daily summaries are reused across report selections; partial days use hourly summaries, and partial or changed hours use raw records. Connections pages are read from shared daily history, with nearby pages briefly cached for navigation. Average Mbps covers the selected period; allow 1–2 minutes for exports. Capture visibility depends on the device exporter and observation point. NAT can hide internal IPv4 addresses on a WAN capture. Select one observation point per traffic path to avoid duplicate totals. Cisco NBAR may classify encrypted traffic as SSL, QUIC or Unknown. History starts when export is enabled.', 'flowview')) . '</p></details>';
print '<script ' . plugin_flowview_csp_nonce() . '>flowviewNbarInitTable(' . json_encode([
	'table'         => '#nbarTable',
	'sortKey'       => 'flowview-nbar-sort-' . ($nbar_page_key ?? 'apps'),
	'conversations' => false,
	'resizeLabel'   => __('Drag to resize column', 'flowview')
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');</script>';
bottom_footer();
