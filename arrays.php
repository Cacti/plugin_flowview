<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2016 The Cacti Group                                 |
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
	0  => __('Statistics Reports'),
	99 => __('Summary'),
	5  => __('UDP/TCP Destination Port'),
	6  => __('UDP/TCP Source Port'),
	7  => __('UDP/TCP Port'),
	8  => __('Destination IP'),
	9  => __('Source IP'),
	10 => __('Source/Destination IP'),
	11 => __('Source or Destination IP'),
	12 => __('IP Protocol'),
	17 => __('Input Interface'),
	18 => __('Output Interface'),
	23 => __('Input/Output Interface'),
	19 => __('Source AS'),
	20 => __('Destination AS'),
	21 => __('Source/Destination AS'),
	22 => __('IP ToS'),
	24 => __('Source Prefix'),
	25 => __('Destination Prefix'),
	26 => __('Source/Destination Prefix')
);

$print_report_array = array(
	 0  => __('Print Reports'),
	 1  => __('Flow Times'),
	 4  => __('AS Numbers'),
	 5  => __('132 Columns'),
	 9  => __('1 Line with Tags'),
	 10 => __('AS Aggregation'),
	 11 => __('Protocol Port Aggregation'),
	 12 => __('Source Prefix Aggregation'),
	 13 => __('Destination Prefix Aggregation'),
	 14 => __('Prefix Aggregation'),
	 24 => __('Full (Catalyst)')
);

$flow_select_array = array(
	  1 => __('Any Part in Specified Time Span'),
	  2 => __('End Time in Specified Time Span'),
	  3 => __('Start Time in Specified Time Span'),
	  4 => __('Entirely in Specified Time Span')
);

$ip_protocols_array = array(
	'' => 'Select One',
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
	'Y' => __('Yes'),
	'N' => __('No')
);

$devices_arr = db_fetch_assoc('SELECT folder, name FROM plugin_flowview_devices ORDER BY name');
$devices = array();
if (!empty($devices_arr)) {
	$ddevice = $devices_arr[0]['folder'];
	foreach ($devices_arr as $d) {
		$devices[$d['folder']] = $d['name'];
	}
}else{
	$ddevice = 0;
}

$query_newname_field = array(
	'friendly_name' => '',
	'name' => 'queryname',
	'method' => 'textbox',
	'max_length' => 255,
	'default' => '',
	'description' => '',
	'value' => (isset_request_var('queryname') ? get_nfilter_request_var('queryname') : '')
);

$query_name_field = array(
	'friendly_name' => '',
	'method' => 'drop_sql',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('query') ? get_filter_request_var('query') : 0),
	'none_value' => __('None'),
	'on_change' => 'applyFilter()',
	'sql' => 'SELECT id, name FROM plugin_flowview_queries ORDER BY name'
);


$device_name_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('device_name') ? get_nfilter_request_var('device_name') : $ddevice),
	'none_value' => __('None'),
	'array' => $devices
);

$cutoff_lines_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 20,
	'description' => '',
	'value' => (isset_request_var('cutoff_lines') ? get_nfilter_request_var('cutoff_lines') : 0),
	'array' => array(
		'999999' => __('All'),
		'5'   => __('Top %d', 5), 
		'10'  => __('Top %d', 10), 
		'20'  => __('Top %d', 20), 
		'30'  => __('Top %d', 30), 
		'40'  => __('Top %d', 40), 
		'50'  => __('Top %d', 50), 
		'100' => __('Top %d', 100), 
		'200' => __('Top %d', 200))
);

$cutoff_octets_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('cutoff_octets') ? get_nfilter_request_var('cutoff_octets'):''),
	'array' => array(
		'0'         => __('No Limit'), 
		'1024'      => __('%s Bytes', '1K'), 
		'10240'     => __('%s Bytes', '10K'),
		'20480'     => __('%s Bytes', '20K'),
		'102400'    => __('%s Bytes', '100K'),
		'512000'    => __('%s Bytes', '500K'),
		'1024000'   => __('%s Bytes', '1M'),
		'10240000'  => __('%s Bytes', '10M'),
		'20480000'  => __('%s Bytes', '20M'),
		'51200000'  => __('%s Bytes', '50M'),
		'102400000' => __('%s Bytes', '100M'),
		'204800000' => __('%s Bytes', '200M'),
		'512000000' => __('%s Bytes', '500M'),
		'1024000000'=> __('%s Bytes', '1G'))
);

$ip_protocol_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('protocols') ? get_nfilter_request_var('protocols') : ''),
	'array' => $ip_protocols_array
);

$stat_report_field = array(
	'friendly_name' => '',
	'name' => 'stat_report',
	'method' => 'drop_array',
	'default' => 10,
	'description' => '',
	'value' => (isset_request_var('stat_report') ? get_nfilter_request_var('stat_report') : 10),
	'array' => $stat_report_array
);

$flow_select_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 1,
	'description' => '',
	'value' => (isset_request_var('flow_select') ? get_nfilter_request_var('flow_select') : 1),
	'array' => $flow_select_array
);

$print_report_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('print_report') ? get_nfilter_request_var('print_report') : 0),
	'array' => $print_report_array
);

$resolve_addresses_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 'Y',
	'description' => '',
	'value' => (isset_request_var('resolve_addresses') ? get_nfilter_request_var('resolve_addresses') : 'Y'),
	'array' => $resolve_addresses_array
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


