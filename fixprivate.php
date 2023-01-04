<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include_once('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
include_once($config['base_path'] . '/lib/time.php');

$tables = db_fetch_assoc('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_NAME LIKE "plugin_flowview_raw%"');

if (cacti_sizeof($tables)) {
	foreach($tables as $table) {
		print "Checking Table: " . $table['TABLE_NAME'] . PHP_EOL;

		$src_ips = db_fetch_assoc('SELECT DISTINCT INET6_NTOA(src_addr) AS src_addr
			FROM ' . $table['TABLE_NAME'] . '
			WHERE INET6_NTOA(src_addr) LIKE "192.%"
			AND INET6_NTOA(src_addr) NOT LIKE "192.168.%"');

		if (cacti_sizeof($src_ips)) {
			print "There are " . cacti_sizeof($src_ips) . " Source DNS records to fix" . PHP_EOL;

			foreach($src_ips as $ip) {
				$dns = flowview_get_dns_from_ip($ip['src_addr']);
				$parts = array_reverse(explode('.', $dns));
				$rdns = $parts[1] . '.' . $parts[0];

				print "Repair: " . $ip['src_addr'] . ", To: " . $dns . ", RDNS: " . $rdns . PHP_EOL;

				$sql = 'UPDATE ' . $table['TABLE_NAME'] . ' SET src_domain = ' . db_qstr($dns) . ', src_rdomain = ' . db_qstr($rdns) . ' WHERE INET6_NTOA(src_addr) = ' . db_qstr($ip['src_addr']);
				db_execute($sql);
				//print $sql . PHP_EOL;
			}
		}

		$dst_ips = db_fetch_assoc('SELECT DISTINCT INET6_NTOA(dst_addr) AS dst_addr
			FROM ' . $table['TABLE_NAME'] . '
			WHERE INET6_NTOA(dst_addr) LIKE "192.%"
			AND INET6_NTOA(dst_addr) NOT LIKE "192.168.%"');

		if (cacti_sizeof($dst_ips)) {
			print "There are " . cacti_sizeof($src_ips) . " Destination DNS records to fix" . PHP_EOL;

			foreach($dst_ips as $ip) {
				$dns = flowview_get_dns_from_ip($ip['dst_addr']);
				$parts = array_reverse(explode('.', $dns));
				$rdns = $parts[1] . '.' . $parts[0];

				print "Repair: " . $ip['dst_addr'] . ", To: " . $dns . ", RDNS: " . $rdns . PHP_EOL;

				$sql = 'UPDATE ' . $table['TABLE_NAME'] . ' SET dst_domain = ' . db_qstr($dns) . ', dst_rdomain = ' . db_qstr($rdns) . ' WHERE INET6_NTOA(dst_addr) = ' . db_qstr($ip['dst_addr']);
				db_execute($sql);
				//print $sql . PHP_EOL;
			}
		}
	}
}


