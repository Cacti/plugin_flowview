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

// Personal report preferences; classification only, never router configuration.
if (!isset($config['base_path'])) {
	http_response_code(403);
	exit;
}
/**
 * Validate and normalize up to 32 internal network prefixes.
 *
 * @param string $text Network prefixes supplied by the account.
 *
 * @return array|false
 */
function fv_nbar_cidrs($text) {
	$out = [];
	foreach (preg_split('/[\s,]+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) as $cidr) {
		$parts = explode('/', $cidr);
		if (cacti_sizeof($parts) !== 2 || !ctype_digit($parts[1]) || !filter_var($parts[0], FILTER_VALIDATE_IP)) {
			return false;
		}
		$packed = inet_pton($parts[0]);
		$bits = (int) $parts[1];
		if ($bits < 0 || $bits > strlen($packed) * 8) {
			return false;
		}
		$out[] = inet_ntop($packed) . '/' . $bits;
		if (cacti_sizeof($out) > 32) {
			return false;
		}
	}
	return array_values(array_unique($out));
}
/**
 * Load account preferences and validate a protected settings submission.
 *
 * @param array $devices Available capture definitions.
 *
 * @return array
 */
function fv_nbar_preferences_init($devices) {
	$defaults = ['prefixes' => [], 'listeners' => [], 'data_unit' => 'GB', 'rate_unit' => 'Mbps', 'dns' => '1'];
	$saved = json_decode((string) read_user_setting('flowview_nbar_preferences', ''), true);
	$p = array_replace($defaults, is_array($saved) ? $saved : ($_SESSION['fv_nbar_preferences'] ?? []));
	if (!is_array($p['prefixes']) || !fv_nbar_cidrs(implode("\n", $p['prefixes']))) {
		$p['prefixes'] = $defaults['prefixes'];
	}
	$p['listeners'] = is_array($p['listeners']) ? array_values(array_intersect(array_map('intval', $p['listeners']), array_map('intval', array_column($devices, 'id')))) : $defaults['listeners'];
	if (!$p['listeners']) {
		$p['listeners'] = $defaults['listeners'];
	}
	$message = '';
	$GLOBALS['fv_nbar_preferences_saved'] = false;
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && get_nfilter_request_var('prefs_apply') === '1') {
		if (!function_exists('csrf_check') || !csrf_check(false)) {
			http_response_code(403);
			exit;
		}
		$candidate = $p;
		$prefixes = is_string(get_nfilter_request_var('local_prefixes', null)) ? fv_nbar_cidrs(get_nfilter_request_var('local_prefixes')) : false;
		$ids = is_array(get_nfilter_request_var('site_listeners', null)) ? get_nfilter_request_var('site_listeners') : [];
		$ids = array_filter($ids, 'is_scalar');
		$ids = array_values(array_intersect(array_map('intval', $ids), array_map('intval', array_column($devices, 'id'))));
		if ($prefixes === false) {
			$message = __('Settings were not saved. Enter valid IPv4/IPv6 networks, or leave the list blank.', 'flowview');
		} elseif (cacti_sizeof(array_unique($ids)) > 3 || (isset_request_var('combine_apply') && cacti_sizeof(array_unique($ids)) === 1)) {
			$message = __('Settings were not saved. Choose two or three captures to combine, or clear all selections to save Internal Networks only.', 'flowview');
		} else {
			$candidate['prefixes'] = $prefixes;
			$candidate['listeners'] = array_values(array_unique($ids));
			foreach (['data_unit' => ['Auto', 'MB', 'GB'], 'rate_unit' => ['Auto', 'Kbps', 'Mbps'], 'dns' => ['0', '1']] as $key => $allowed) {
				if (is_string(get_nfilter_request_var($key, null)) && in_array(get_nfilter_request_var($key), $allowed, true)) {
					$candidate[$key] = get_nfilter_request_var($key);
				}
			}
			$_SESSION['fv_nbar_preferences'] = $p = $candidate;
			$GLOBALS['fv_nbar_preferences_saved'] = true;
			set_user_setting('flowview_nbar_preferences', json_encode($p));
			$message = '';
		}
	}
	$GLOBALS['fv_nbar_preferences'] = $p;
	$GLOBALS['fv_nbar_preferences_message'] = $message;
	return $p;
}
/**
 * Build the internal-network predicate for a permitted address column.
 *
 * @param string $f Permitted address-column name.
 *
 * @return string
 */
function fv_nbar_local_sql($f) {
	if (!in_array($f, ['d.src', 'd.dst'], true)) {
		throw new InvalidArgumentException('Invalid address field');
	}
	$clauses = [];
	foreach ($GLOBALS['fv_nbar_preferences']['prefixes'] as $cidr) {
		[$ip,$bits] = explode('/', $cidr);
		$packed = inet_pton($ip);
		$bits = (int) $bits;
		$whole = intdiv($bits, 8);
		$rest = $bits % 8;
		$checks = ['LENGTH(' . $f . ')=' . strlen($packed)];
		if ($whole) {
			$checks[] = "LEFT(HEX($f)," . ($whole * 2) . ")='" . strtoupper(bin2hex(substr($packed, 0, $whole))) . "'";
		}
		if ($rest) {
			$mask = (255 << (8 - $rest)) & 255;
			$value = ord($packed[$whole]) & $mask;
			$checks[] = "(ORD(SUBSTRING($f," . ($whole + 1) . ",1)) & $mask)=$value";
		}
		$clauses[] = '(' . implode(' AND ', $checks) . ')';
	}
	return $clauses ? '(' . implode(' OR ', $clauses) . ')' : '(0)';
}
/**
 * Render protected capture options, networks and display preferences.
 *
 * @param array $devices Available capture definitions.
 * @param string $base Report base URL.
 * @param string $mode Current report mode.
 * @param string $aid Current application identifier.
 * @param string $parent_app Parent application drilldown identifier.
 *
 * @return void
 */
function fv_nbar_preferences_render($devices, $base, $mode, $aid, $parent_app) {
	global $config;
	$p = $GLOBALS['fv_nbar_preferences'];
	echo '<div id="nbarPreferences"><input type="hidden" name="prefs_apply" value="1">';
	if ($GLOBALS['fv_nbar_preferences_message'] !== '') {
		echo '<p role="status">' . fv_nbar_h($GLOBALS['fv_nbar_preferences_message']) . '</p>';
	}
	echo '<div class="nbarSettingsRow">';
	foreach (['data_unit' => [__('Data units', 'flowview'), ['Auto', 'MB', 'GB']], 'rate_unit' => [__('Bandwidth units', 'flowview'), ['Auto', 'Kbps', 'Mbps']], 'dns' => [__('DNS names', 'flowview'), ['1' => __('Show', 'flowview'), '0' => __('Hide', 'flowview')]]] as $key => $definition) {
		echo '<div class="nbarSetting"><label for="nbar_' . $key . '">' . html_escape($definition[0]) . '</label><select id="nbar_' . $key . '" name="' . $key . '">';
		foreach ($definition[1] as $v => $label) {
			if ($key !== 'dns') {
				$v = $label;
			}
			echo '<option value="' . fv_nbar_h($v) . '"' . ((string) $p[$key] === (string) $v ? ' selected' : '') . '>' . html_escape($label === 'Auto' ? __('Auto', 'flowview') : $label) . '</option>';
		}
		echo '</select></div>';
	}
	echo '</div><dialog id="nbarCombineDialog" class="ui-widget ui-widget-content ui-corner-all" aria-labelledby="nbarCombineTitle"><div class="nbarCombineHeader ui-widget-header"><strong id="nbarCombineTitle">' . html_escape(__('Options', 'flowview')) . '</strong><button type="button" id="nbarCombineClose" class="ui-button ui-corner-all ui-widget">' . html_escape(__('Close', 'flowview')) . '</button></div><div class="nbarCombineBody"><h3>' . html_escape(__('Combine Options', 'flowview')) . '</h3><p id="nbarRouterSelectionCount"></p><div>';
	foreach ($devices as $d) {
		echo '<label style="display:block;white-space:nowrap"><input type="checkbox" name="site_listeners[]" value="' . (int) $d['id'] . '"' . (in_array((int) $d['id'], $p['listeners'], true) ? ' checked' : '') . '> ' . fv_nbar_h($d['name']) . '</label>';
	}
	echo '<small>' . html_escape(__('Choose two or three captures to combine, normally one LAN capture per router. Leave all captures unticked to save Internal Networks only. Overlapping LAN and WAN captures are added together and can count traffic twice.', 'flowview')) . '</small></div>';
	echo '<div style="margin-top:20px"><h3><label for="nbarPrefixes">' . html_escape(__('Internal Networks', 'flowview')) . '</label></h3><textarea id="nbarPrefixes" name="local_prefixes" rows="5" cols="44">' . fv_nbar_h(implode("\n", $p['prefixes'])) . '</textarea><p>' . html_escape(__('One internal IPv4 or IPv6 network per line. Used to identify internal hosts and upload/download direction in Conversations. Update delegated IPv6 prefixes when they change, and keep old prefixes for retained history. Exported flows do not automatically identify your site networks.', 'flowview')) . '</p><button type="submit" id="nbarCombineSave" name="combine_apply" value="1" class="ui-button ui-corner-all ui-widget">' . html_escape(__('Save', 'flowview')) . '</button> <button type="button" id="nbarCombineCancel" class="ui-button ui-corner-all ui-widget">' . html_escape(__('Cancel', 'flowview')) . '</button></div></div></dialog></div>';
	echo '<script ' . plugin_flowview_csp_nonce() . '>window.fvNbarDisplay=' . json_encode(['data' => $p['data_unit'], 'rate' => $p['rate_unit'], 'dns' => $p['dns'], 'labels' => ['selection' => __('{count} of 3 selected', 'flowview'), 'single' => __(' — select another capture or untick all to save', 'flowview'), 'off' => __(' — combining off', 'flowview'), 'rate' => __('Average rate across the selected timespan.', 'flowview'), 'data' => __('Decimal units: 1 GB = 1,000 MB.', 'flowview')]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';</script>';
	echo '<script src="' . plugin_flowview_escape_attr($config['url_path'] . 'plugins/flowview/js/nbar-preferences.js') . '"></script>';

}

/** Exclude multicast endpoints from internal/external conversations. */
function fv_nbar_unicast_sql(string $field): string {
	if (!in_array($field, ['d.src', 'd.dst'], true)) {
		throw new InvalidArgumentException('Invalid address field');
	}
	return "NOT ((LENGTH($field)=4 AND (ORD(SUBSTRING($field,1,1)) & 240)=224) OR (LENGTH($field)=16 AND ORD(SUBSTRING($field,1,1))=255))";
}
