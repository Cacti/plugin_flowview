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

// Uses FlowView's DNS cache and a bounded resolver budget.
/**
 * Resolve a host through persistent and bounded per-account DNS caches.
 *
 * @param string $ip Endpoint IP address.
 *
 * @return string
 */
function fv_nbar_dns(string $ip): string {
	global $config;

	if (($GLOBALS['fv_nbar_preferences']['dns'] ?? '1') === '0') {
		return '';
	}
	static $names = [], $resolver = null, $started = null;

	if (array_key_exists($ip, $names)) {
		return $names[$ip];
	}
	$cache = flowview_db_fetch_row_prepared('SELECT host FROM plugin_flowview_dnscache WHERE ip = ? AND source IN (?,?,?)', [$ip, 'DNS', 'Local DNS', 'Specified DNS']);

	if (!empty($cache['host'])) {
		return $names[$ip] = ($cache['host'] === $ip ? __('No reverse DNS', 'flowview') : $cache['host']);
	}
	$negative = $_SESSION['fv_nbar_dns_negative'] ?? [];

	if (isset($negative[$ip]) && $negative[$ip]['until'] > time()) {
		return $names[$ip] = $negative[$ip]['name'];
	}

	if ($started === null) {
		$started = microtime(true);
	}

	if (microtime(true) - $started > 1) {
		return $names[$ip] = __('Not resolved', 'flowview');
	}

	if ($resolver === null) {
		set_include_path($config['base_path'] . '/plugins/flowview' . PATH_SEPARATOR . get_include_path());
		require_once($config['base_path'] . '/plugins/flowview/Net/DNS2.php');
		$servers           = array_filter([read_config_option('settings_dns_primary'), read_config_option('settings_dns_secondary')]);
		$resolver          = new Net_DNS2_Resolver($servers ? ['nameservers' => array_values($servers)] : []);
		$resolver->timeout = 1;
	}

	try {
		$response = $resolver->query($ip, 'PTR');

		foreach ($response->answer as $answer) {
			if (!empty($answer->ptrdname)) {
				$name = rtrim($answer->ptrdname, '.');
				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache (ip,host,source,arin_verified,arin_id,time) VALUES (?,?,?,0,0,?) ON DUPLICATE KEY UPDATE host=VALUES(host),source=VALUES(source),time=VALUES(time)', [$ip, $name, 'DNS', time()]);

				return $names[$ip] = $name;
			}
		}

		return $names[$ip] = fv_nbar_dns_negative($ip, __('No reverse DNS', 'flowview'), 300);
	} catch (Exception $e) {
		return $names[$ip] = fv_nbar_dns_negative($ip, $e->getCode() === 3 ? __('No reverse DNS', 'flowview') : __('Not resolved', 'flowview'), $e->getCode() === 3 ? 300 : 30);
	}
}

/**
 * Cache a negative DNS result with bounded account-session storage.
 *
 * @param string $ip   Endpoint IP address.
 * @param string $name Cached DNS result label.
 * @param int    $ttl  Cache lifetime in seconds.
 *
 * @return string
 */
function fv_nbar_dns_negative(string $ip, string $name, int $ttl): string {
	$cache = $_SESSION['fv_nbar_dns_negative'] ?? [];

	foreach ($cache as $key => $entry) {
		if ($entry['until'] <= time()) {
			unset($cache[$key]);
		}
	}
	$cache[$ip] = ['name' => $name, 'until' => time() + $ttl];

	if (cacti_sizeof($cache) > 512) {
		$cache = array_slice($cache, -512, null, true);
	}
	$_SESSION['fv_nbar_dns_negative'] = $cache;

	return $name;
}
