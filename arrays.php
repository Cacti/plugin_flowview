<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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
	'' => __('Select One'),
	6  => __('TCP'),
	17 => __('UDP'),
	1  => __('ICMP'),
	2  => __('IGMP'),
	3  => __('GGP'),
	4  => __('IPENCAP'),
	5  => __('ST'),
	7  => __('CBT'),
	8  => __('EGP'),
	9  => __('IGP'),
	10 => __('BBN-RCC-MON'),
	11 => __('NVP-II'),
	12 => __('PUP'),
	13 => __('ARGUS'),
	14 => __('EMCON'),
	15 => __('XNET'),
	16 => __('CHAOS'),
	18 => __('MUX'),
	19 => __('DCN-MEAS'),
	20 => __('HMP'),
	21 => __('PRM'),
	22 => __('XNS-IDP'),
	23 => __('TRUNK-1'),
	24 => __('TRUNK-2'),
	25 => __('LEAF-1'),
	26 => __('LEAF-2'),
	27 => __('RDP'),
	28 => __('IRTP'),
	29 => __('ISO-TP4'),
	30 => __('NETBLT'),
	31 => __('MFE-NSP'),
	32 => __('MERIT-INP'),
	33 => __('DCCP'),
	34 => __('3PC'),
	35 => __('IDPR'),
	36 => __('XTP'),
	37 => __('DDP'),
	38 => __('IDPR-CMTP'),
	39 => __('TP++'),
	40 => __('IL'),
	41 => __('IPv6'),
	42 => __('SDRP'),
	43 => __('IPv6-Route'),
	44 => __('IPv6-Frag'),
	45 => __('IDRP'),
	46 => __('RSVP'),
	47 => __('GRE'),
	48 => __('DSR'),
	49 => __('BNA'),
	50 => __('IPSEC-ESP'),
	51 => __('IPSEC-AH'),
	58 => __('IPv6-ICMP'),
	59 => __('IPv6-NoNxt'),
	60 => __('IPv6-Opts'),
	73 => __('RSPF'),
	81 => __('VMTP'),
	88 => __('EIGRP'),
	89 => __('OSPF'),
	92 => __('MTP'),
	94 => __('IPIP'),
	98 => __('ENCAP'),
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
	5  => array(1, 2, '-1',  '-1', '0',  __('Port'), __('Flows'), __('Bytes'), __('Packets')),
	6  => array(1, 2, '-1',  '-1', '0',  __('Port'), __('Flows'), __('Bytes'), __('Packets')),
	7  => array(1, 2, '-1',  '-1', '0',  __('Port'), __('Flows'), __('Bytes'), __('Packets')),
	8  => array(1, 2, 0,     '-1', '-1', __('Dest IP'), __('Flows'), __('Bytes'), __('Packets')),
	9  => array(1, 2, 0,     '-1', '-1', __('Src IP'), __('Flows'), __('Bytes'), __('Packets')),
	10 => array(1, 3, '0,1', '-1', '-1', __('Src IP'), __('Dest IP'), __('Flows'), __('Bytes'), __('Packets')),
	11 => array(1, 2, 0,     '-1', '-1', __('Src/Dest IP'), __('Flows'), __('Bytes'), __('Packets')),
	12 => array(1, 2, '-1',  '0',  '-1', __('Protocol'), __('Flows'), __('Bytes'), __('Packets')),
	17 => array(1, 2, '-1',  '-1', '-1', __('Input IF'), __('Flows'), __('Bytes'), __('Packets')),
	18 => array(1, 2, '-1',  '-1', '-1', __('Output IF'), __('Flows'), __('Bytes'), __('Packets')),
	19 => array(1, 2, '-1',  '-1', '-1', __('Src AS'), __('Flows'), __('Bytes'), __('Packets')),
	20 => array(1, 2, '-1',  '-1', '-1', __('Dest AS'), __('Flows'), __('Bytes'), __('Packets')),
	21 => array(1, 3, '-1',  '-1', '-1', __('Src AS'), __('Dest AS'), __('Flows'), __('Bytes'), __('Packets')),
	22 => array(1, 2, '-1',  '-1', '-1', __('TOS'), __('Flows'), __('Bytes'), __('Packets')),
	23 => array(1, 3, '-1',  '-1', '-1', __('Input IF'), __('Output IF'), __('Flows'), __('Bytes'), __('Packets')),
	24 => array(1, 2, '0',   '-1', '-1', __('Src Prefix'), __('Flows'), __('Bytes'), __('Packets')),
	25 => array(1, 2, '0',   '-1', '-1', __('Dest Prefix'), __('Flows'), __('Bytes'), __('Packets')),
	26 => array(1, 3, '0,1', '-1', '-1', __('Src Prefix'), __('Dest Prefix'), __('Flows'), __('Bytes'), __('Packets')),
);

$print_columns_array = array(
	1  => array(2, 8, '1,3', '1', '4', '1', '5,6', __('Src IF'), __('Src IP'), __('Dest IF'), __('Dest IP'), __('Protocol'), __('Src Port'), __('Dest Port'), __('Packets'), __('Bytes'), __('Start Time'), __('End Time'), __('Active'), __('B/Pk'), __('Ts'), __('Fl')),
	4  => array(1, 5, '', '0', '2', '0', '', __('Src IP'), __('Dest IP'), __('Protocol'), __('Src AS'), __('Dest AS'), __('Bytes'), __('Packets')),
	5  => array(1, 11, '3,6', '0', '8', '0', '4,7', __('Start Time'), __('End Time'), __('Src IF'), __('Src IP'), __('Src Port'), __('Dest IF'), __('Dest IP'), __('Dest Port'), __('Protocol'), __('Flags'), __('Packets'), __('Bytes')),
);


