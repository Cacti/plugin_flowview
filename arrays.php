<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2010 The Cacti Group                                 |
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
	6  => 'TCP',
	17 => 'UDP',
	1  => 'ICMP',
	2  => 'IGMP',
	3  => 'GGP',
	4  => 'IPENCAP',
	5  => 'ST',
	7  => 'CBT',
	8  => 'EGP',
	9  => 'IGP',
	10 => 'BBN-RCC-MON',
	11 => 'NVP-II',
	12 => 'PUP',
	13 => 'ARGUS',
	14 => 'EMCON',
	15 => 'XNET',
	16 => 'CHAOS',
	18 => 'MUX',
	19 => 'DCN-MEAS',
	20 => 'HMP',
	21 => 'PRM',
	22 => 'XNS-IDP',
	23 => 'TRUNK-1',
	24 => 'TRUNK-2',
	25 => 'LEAF-1',
	26 => 'LEAF-2',
	27 => 'RDP',
	28 => 'IRTP',
	29 => 'ISO-TP4',
	30 => 'NETBLT',
	31 => 'MFE-NSP',
	32 => 'MERIT-INP',
	33 => 'DCCP',
	34 => '3PC',
	35 => 'IDPR',
	36 => 'XTP',
	37 => 'DDP',
	38 => 'IDPR-CMTP',
	39 => 'TP++',
	40 => 'IL',
	41 => 'IPv6',
	42 => 'SDRP',
	43 => 'IPv6-Route',
	44 => 'IPv6-Frag',
	45 => 'IDRP',
	46 => 'RSVP',
	47 => 'GRE',
	48 => 'DSR',
	49 => 'BNA',
	50 => 'IPSEC-ESP',
	51 => 'IPSEC-AH',
	58 => 'IPv6-ICMP',
	59 => 'IPv6-NoNxt',
	60 => 'IPv6-Opts',
	73 => 'RSPF',
	81 => 'VMTP',
	88 => 'EIGRP',
	89 => 'OSPF',
	92 => 'MTP',
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

$query_newname_field = array("friendly_name" => '',
	'name' => 'queryname',
	"method" => "textbox",
	"max_length" => 255,
	"default" => '',
	"description" => '',
	"value" => (isset($_POST['queryname']) ? $_POST['queryname'] : '')
);

$query_name_field = array("friendly_name" => '',
	"method" => "drop_sql",
	"default" => 0,
	"description" => '',
	"value" => (isset($_REQUEST['query']) ? $_REQUEST['query'] : 0),
	"none_value" => "None",
	"sql" => "SELECT id, name FROM plugin_flowview_queries ORDER BY name"
);

$device_name_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 0,
	"description" => '',
	"value" => (isset($_POST['device_name']) ? $_POST['device_name'] : 0),
	"none_value" => "None",
	"array" => $devices
);

$cutoff_lines_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 20,
	"description" => '',
	"value" => (isset($_POST['cutoff_lines']) ? $_POST['cutoff_lines'] : 0),
	"array" => array(
		'999999' => 'All',
		'5'  => 'Top 5', 
		'10'  => 'Top 10', 
		'20'  => 'Top 20', 
		'30'  => 'Top 30', 
		'40'  => 'Top 40', 
		'50'  => 'Top 50', 
		'100' => 'Top 100', 
		'200' => 'Top 200')
);

$cutoff_octets_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 0,
	"description" => '',
	"value" => (isset($_POST['cutoff_octets']) ? $_POST['cutoff_octets']:''),
	"array" => array(
		'0'         => 'No Limit', 
		'1024'      => '1K   Bytes', 
		'10240'     => '10K  Bytes',
		'20480'     => '20K  Bytes',
		'102400'    => '100K Bytes',
		'512000'    => '500K Bytes',
		'1024000'   => '1M   Bytes',
		'10240000'  => '10M  Bytes',
		'20480000'  => '20M  Bytes',
		'51200000'  => '50M  Bytes',
		'102400000' => '100M Bytes',
		'204800000' => '200M Bytes',
		'512000000' => '500M Bytes',
		'1024000000'=> '1G   Bytes')
);

$ip_protocol_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 0,
	"description" => '',
	"value" => (isset($_POST['protocols']) ? $_POST['protocols'] : ''),
	"array" => $ip_protocols_array
);

$stat_report_field = array("friendly_name" => '',
	'name' => 'stat_report',
	"method" => "drop_array",
	"default" => 10,
	"description" => '',
	"value" => (isset($_POST['stat_report']) ? $_POST['stat_report'] : 10),
	"array" => $stat_report_array
);

$flow_select_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 1,
	"description" => '',
	"value" => (isset($_POST['flow_select']) ? $_POST['flow_select'] : 1),
	"array" => $flow_select_array
);

$print_report_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 0,
	"description" => '',
	"value" => (isset($_POST['print_report']) ? $_POST['print_report'] : 0),
	"array" => $print_report_array
);

$resolve_addresses_field = array("friendly_name" => '',
	"method" => "drop_array",
	"default" => 'Y',
	"description" => '',
	"value" => (isset($_POST['resolve_addresses']) ? $_POST['resolve_addresses'] : 'Y'),
	"array" => $resolve_addresses_array
);

$stat_columns_array = array(
	5  => array(1, 2, '-1',  '-1', '0', 'Port', 'Flows', 'Bytes', 'Packets'),
	6  => array(1, 2, '-1',  '-1', '0', 'Port', 'Flows', 'Bytes', 'Packets'),
	7  => array(1, 2, '-1',  '-1', '0', 'Port', 'Flows', 'Bytes', 'Packets'),
	8  => array(1, 2, 0,     '-1', '-1', 'Dest IP', 'Flows', 'Bytes', 'Packets'),
	9  => array(1, 2, 0,     '-1', '-1', 'Src IP', 'Flows', 'Bytes', 'Packets'),
	10 => array(1, 3, '0,1', '-1', '-1', 'Src IP', 'Dest IP', 'Flows', 'Bytes', 'Packets'),
	11 => array(1, 2, 0,     '-1', '-1', 'Src/Dest IP', 'Flows', 'Bytes', 'Packets'),
	12 => array(1, 2, '-1',  '0',  '-1', 'Protocol', 'Flows', 'Bytes', 'Packets'),
	17 => array(1, 2, '-1',  '-1', '-1', 'Input IF', 'Flows', 'Bytes', 'Packets'),
	18 => array(1, 2, '-1',  '-1', '-1', 'Output IF', 'Flows', 'Bytes', 'Packets'),
	19 => array(1, 2, '-1',  '-1', '-1', 'Src AS', 'Flows', 'Bytes', 'Packets'),
	20 => array(1, 2, '-1',  '-1', '-1', 'Dest AS', 'Flows', 'Bytes', 'Packets'),
	21 => array(1, 3, '-1',  '-1', '-1', 'Src AS', 'Dest AS', 'Flows', 'Bytes', 'Packets'),
	22 => array(1, 2, '-1',  '-1', '-1', 'TOS', 'Flows', 'Bytes', 'Packets'),
	23 => array(1, 3, '-1',  '-1', '-1', 'Input IF', 'Output IF', 'Flows', 'Bytes', 'Packets'),
	24 => array(1, 2, '0',   '-1', '-1', 'Src Prefix', 'Flows', 'Bytes', 'Packets'),
	25 => array(1, 2, '0',   '-1', '-1', 'Dest Prefix', 'Flows', 'Bytes', 'Packets'),
	26 => array(1, 3, '0,1', '-1', '-1', 'Src Prefix', 'Dest Prefix', 'Flows', 'Bytes', 'Packets'),
);

$print_columns_array = array(
	1  => array(2, 8, '1,3', '1', '4', '1', '5,6', 'Src IF', 'Src IP', 'Dest IF', 'Dest IP', 'Protocol', 'Src Port', 'Dest Port', 'Packets', 'Bytes', 'Start Time', 'End Time', 'Active', 'B/Pk', 'Ts', 'Fl'),
	4  => array(1, 5, '', '0', '2', '0', '', 'Src IP', 'Dest IP', 'Protocol', 'Src AS', 'Dest AS', 'Bytes', 'Packets'),
	5  => array(1, 11, '3,6', '0', '8', '0', '4,7', 'Start Time', 'End Time', 'Src IF', 'Src IP', 'Src Port', 'Dest IF', 'Dest IP', 'Dest Port', 'Protocol', 'Flags', 'Packets', 'Bytes'),
);


