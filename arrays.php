<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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

$stat_report_array = array(
	0  => "Statistics Reports",
	99 => "Summary",
	5  => "UDP/TCP Destination Port",
	6  => "UDP/TCP Source Port",
	7  => "UDP/TCP Port",
	8  => "Destination IP",
	9  => "Source IP",
	10 => "Source/Destination IP",
	11 => "Source or Destination IP",
	12 => "IP Protocol",
	17 => "Input Interface",
	18 => "Output Interface",
	23 => "Input/Output Interface",
	19 => "Source AS",
	20 => "Destination AS",
	21 => "Source/Destination AS",
	22 => "IP ToS",
	24 => "Source Prefix",
	25 => "Destination Prefix",
	26 => "Source/Destination Prefix"
	);

$print_report_array = array(
	 0  => "Print Reports",
	 1  => "Flow Times",
	 4  => "AS Numbers",
	 5  => "132 Columns",
	 9  => "1 Line with Tags",
	 10 => "AS Aggregation",
	 11 => "Protocol Port Aggregation",
	 12 => "Source Prefix Aggregation",
	 13 => "Destination Prefix Aggregation",
	 14 => "Prefix Aggregation",
	 24 => "Full (Catalyst)"
	);

$flow_select_array = array(
	  1 => "Any Part in Specified Time Span",
	  2 => "End Time in Specified Time Span",
	  3 => "Start Time in Specified Time Span",
	  4 => "Entirely in Specified Time Span"
	);

$ip_protocols_array = array(
	'' => '',
	6 => 'TCP',
	17 => 'UDP',

	1 => 'ICMP',
	2 => 'IGMP',
	3 => 'GGP',
	4 => 'IPENCAP',
	5 => 'ST',
	8 => 'EGP',
	12 => 'PUP',
	20 => 'HMP',
	22 => 'XNS-IDP',
	27 => 'RDP',
	29 => 'ISO-TP4',
	36 => 'XTP',
	37 => 'DDP',
	39 => 'IDPR-CMTP',
	47 => 'GRE',
	50 => 'IPSEC-ESP',
	51 => 'IPSEC-AH',
	73 => 'RSPF',
	81 => 'VMTP',
	89 => 'OSPF',
	94 => 'IPIP',
	98 => 'ENCAP',
	);

$resolve_addresses_array = array (
	  'Y' => "Yes",
	  'N' => "No"
	);

$devices_arr = db_fetch_assoc("SELECT folder, name FROM plugin_flowview_devices ORDER BY name");
$devices = array();
if (!empty($devices_arr)) {
	foreach ($devices_arr as $d) {
		$devices[$d['folder']] = $d['name'];
	}
}

$queries_arr = db_fetch_assoc("SELECT id, name FROM plugin_flowview_queries ORDER BY name");
$queries = array('' => '');
if (!empty($queries_arr)) {
	foreach ($queries_arr as $d) {
		$queries[$d['id']] = $d['name'];
	}
}

$query_newname_field = array("friendly_name" => '',
		'name' => 'queryname',
		"method" => "textbox",
		"max_length" => 255,		"default" => '',
		"description" => '',
		"value" => (isset($_POST['queryname']) ? $_POST['queryname'] : ''));

$query_name_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 0,
		"description" => '',
		"value" => (isset($_REQUEST['query']) ? $_REQUEST['query'] : 0),
		"array" => $queries);

$device_name_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 0,
		"description" => '',
		"value" => (isset($_POST['device_name']) ? $_POST['device_name'] : 0),
		"array" => $devices);

$ip_protocol_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 0,
		"description" => '',
		"value" => (isset($_POST['protocols']) ? $_POST['protocols'] : ''),
		"array" => $ip_protocols_array);

$stat_report_field = array("friendly_name" => '',
		'name' => 'stat_report',
		"method" => "drop_array",
		"default" => 10,
		"description" => '',
		"value" => (isset($_POST['stat_report']) ? $_POST['stat_report'] : 10),
		"array" => $stat_report_array);

$flow_select_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 1,
		"description" => '',
		"value" => (isset($_POST['flow_select']) ? $_POST['flow_select'] : 1),
		"array" => $flow_select_array);

$print_report_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 0,
		"description" => '',
		"value" => (isset($_POST['print_report']) ? $_POST['print_report'] : 0),
		"array" => $print_report_array);

$resolve_addresses_field = array("friendly_name" => '',
		"method" => "drop_array",
		"default" => 'Y',
		"description" => '',
		"value" => (isset($_POST['resolve_addresses']) ? $_POST['resolve_addresses'] : 'Y'),
		"array" => $resolve_addresses_array);

$stat_columns_array = array(
	5  => array(1, 2, '', '', 'Port', 'Flows', 'Bytes', 'Packets'),
	6  => array(1, 2, '', '', 'Port', 'Flows', 'Bytes', 'Packets'),
	7  => array(1, 2, '', '', 'Port', 'Flows', 'Bytes', 'Packets'),
	8  => array(1, 2, 0, '',  'Destination IP', 'Flows', 'Bytes', 'Packets'),
	9  => array(1, 2, 0, '',  'Source IP', 'Flows', 'Bytes', 'Packets'),
	10 => array(1, 3, '0,1', '', 'Source IP', 'Destination IP', 'Flows', 'Bytes', 'Packets'),
	11 => array(1, 2, 0, '',  'Source/Desination IP', 'Flows', 'Bytes', 'Packets'),
	12 => array(1, 2, '', '0', 'Protocol', 'Flows', 'Bytes', 'Packets'),
	17 => array(1, 2, '', '', 'Input Interface', 'Flows', 'Bytes', 'Packets'),
	18 => array(1, 2, '', '', 'Output Interface', 'Flows', 'Bytes', 'Packets'),
	19 => array(1, 2, '', '', 'Source AS', 'Flows', 'Bytes', 'Packets'),
	20 => array(1, 2, '', '', 'Destination AS', 'Flows', 'Bytes', 'Packets'),
	21 => array(1, 3, '', '', 'Source AS', 'Destination AS', 'Flows', 'Bytes', 'Packets'),
	22 => array(1, 2, '', '', 'TOS', 'Flows', 'Bytes', 'Packets'),
	23 => array(1, 3, '', '', 'Input Interface', 'Output Interface', 'Flows', 'Bytes', 'Packets'),
	24 => array(1, 2, '', '', 'Source Prefix', 'Flows', 'Bytes', 'Packets'),
	25 => array(1, 2, '', '', 'Destination Prefix', 'Flows', 'Bytes', 'Packets'),
	26 => array(1, 3, '', '', 'Source Prefix', 'Destination Prefix', 'Flows', 'Bytes', 'Packets'),
	);

$print_columns_array = array(
	4  => array(1, 5, '', '2', 'Source IP', 'Destination IP', 'Protocol', 'Source AS', 'Destination AS', 'Bytes', 'Packets'),
	5  => array(1, 11, '3,6', '8', 'Start Time', 'End Time', 'Source Interface', 'Source IP', 'Source Port', 'Destination Interface', 'Destination IP', 'Dest Port', 'Protocol', 'Flags', 'Packets', 'Bytes'),
	);


