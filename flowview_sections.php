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
 * Read scalar report-navigation fields through Cacti request helpers.
 *
 * @return array
 */
function fv_section_request_params() {
	$params = [];
	foreach (['action', 'section', 'report', 'query', 'device_id', 'listener', 'mode', 'family', 'predefined_timespan', 'period', 'date1', 'date2', 'context', 'sortfield', 'cutofflines', 'cutoffoctets', 'exclude', 'ex_addr', 'graph_type', 'graph_height', 'domains', 'headercontent'] as $key) {
		$value = get_nfilter_request_var($key, null);
		if (is_scalar($value)) {
			$params[$key] = $value;
		}
	}
	return $params;
}
/**
 * Restore a report section with a fresh half-hour time window.
 *
 * @param string $section Report section identifier.
 *
 * @return array
 */
function fv_section_params($section) {
	$p = json_decode((string) read_user_setting('flowview_section_' . $section, '', true), true);
	if (!is_array($p)) {
		$p = [];
	}
	$keys = ['report', 'query', 'device_id', 'listener', 'mode', 'family', 'predefined_timespan', 'period', 'date1', 'date2', 'context', 'sortfield', 'cutofflines', 'cutoffoctets', 'exclude', 'ex_addr', 'graph_type', 'graph_height', 'domains'];
	$p = array_intersect_key($p, array_flip($keys));
	foreach ($p as $k => $v) {
		if (!is_scalar($v)) {
			unset($p[$k]);
		}
	}
	$p['predefined_timespan'] = 1; // Re-enter reports with a fresh half-hour window.
	if ((int) ($p['predefined_timespan'] ?? 0) > 0) {
		unset($p['date1'],$p['date2']);
		$c = json_decode($p['context'] ?? '', true);
		if (is_array($c)) {
			unset($c['date1'],$c['date2']);
			$p['context'] = json_encode($c);
		}
	}
	$p['action'] = 'view';
	$p['section'] = $section;
	if ($section === 'nbar') {
		$p['report'] = 'nbar';
	} elseif (($p['report'] ?? '') === 'nbar') {
		unset($p['report']);
	}
	return $p;
}
/**
 * Build a local URL restoring one report section.
 *
 * @param string $section Report section identifier.
 *
 * @return string
 */
function fv_section_url($section) {
	return 'flowview.php?' . http_build_query(fv_section_params($section));
}
/**
 * Persist account-specific section navigation without drilldown addresses.
 *
 * @param string $section Report section identifier.
 * @param array $params Scalar navigation fields.
 *
 * @return void
 */
function fv_section_save($section, $params) {
	unset($params['app'],$params['internal'],$params['external'],$params['section']);
	$value = json_encode($params);
	if (read_user_setting('flowview_last_section', '', true) !== $section) {
		set_user_setting('flowview_last_section', $section);
	}
	if (read_user_setting('flowview_section_' . $section, '', true) !== $value) {
		set_user_setting('flowview_section_' . $section, $value);
	}
}
/**
 * Render theme-aware navigation between Flow Reports and Cisco Apps.
 *
 * @param string $selected Currently selected report section.
 *
 * @return void
 */
function fv_section_navigation($selected) {
	global $config;
	echo '<link rel="stylesheet" href="' . plugin_flowview_escape_attr($config['url_path'] . 'plugins/flowview/css/nbar.css') . '">';
	echo '<nav id="fv_section_tabs" class="tabs" aria-label="' . plugin_flowview_escape_attr(__('FlowView sections', 'flowview')) . '" style="display:flex;flex-wrap:wrap;gap:6px;margin:8px 0 0;border:0;background:none"><ul style="display:flex;flex-wrap:wrap;gap:6px;list-style:none;margin:0;padding:0;border:0">';
	foreach (['flows' => __('Flow Reports', 'flowview'), 'nbar' => __('Cisco Apps (NBAR)', 'flowview')] as $key => $label) {
		echo '<li><a class="' . ($key === $selected ? 'selected' : '') . '"' . ($key === $selected ? ' aria-current="page"' : '') . ' href="' . htmlspecialchars(fv_section_url($key), ENT_QUOTES, 'UTF-8') . '">' . html_escape($label) . '</a></li>';
	}
	echo '</ul></nav>';
}
$section_entry = array_diff_key(fv_section_request_params(), array_flip(['headercontent']));
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (!$section_entry || (cacti_sizeof($section_entry) === 1 && ($section_entry['action'] ?? '') === 'view'))) {
	$last = read_user_setting('flowview_last_section', '', true);
	if (in_array($last, ['flows', 'nbar'], true)) {
		$restore = fv_section_url($last);
		if (get_nfilter_request_var('header') === 'false') {
			$restore .= '&header=false';
		}
		if (isset_request_var('headercontent')) {
			$restore .= '&headercontent=true';
		}
		header('Location: ' . $restore);
		exit;
	}
}
if (get_nfilter_request_var('section') === 'nbar') {
	set_request_var('action', 'view');
	set_request_var('report', 'nbar');
}
