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
	0  => __('Select a Statistic Report', 'flowview'),
	99 => __('Summary', 'flowview'),
	5  => __('UDP/TCP Destination Port', 'flowview'),
	6  => __('UDP/TCP Source Port', 'flowview'),
	7  => __('UDP/TCP Port', 'flowview'),
	8  => __('Destination IP', 'flowview'),
	9  => __('Source IP', 'flowview'),
	10 => __('Source/Destination IP', 'flowview'),
	11 => __('Source or Destination IP', 'flowview'),
	12 => __('IP Protocol', 'flowview'),
	17 => __('Input Interface', 'flowview'),
	18 => __('Output Interface', 'flowview'),
	23 => __('Input/Output Interface', 'flowview'),
	19 => __('Source AS', 'flowview'),
	20 => __('Destination AS', 'flowview'),
	21 => __('Source/Destination AS', 'flowview'),
	22 => __('IP ToS', 'flowview'),
	24 => __('Source Prefix', 'flowview'),
	25 => __('Destination Prefix', 'flowview'),
	26 => __('Source/Destination Prefix', 'flowview')
);

$print_report_array = array(
	 0  => __('Select a Print Report', 'flowview'),
	 1  => __('Flow Times', 'flowview'),
	 4  => __('AS Numbers', 'flowview'),
	 5  => __('132 Columns', 'flowview'),
	 9  => __('1 Line with Tags', 'flowview'),
	 10 => __('AS Aggregation', 'flowview'),
	 11 => __('Protocol Port Aggregation', 'flowview'),
	 12 => __('Source Prefix Aggregation', 'flowview'),
	 13 => __('Destination Prefix Aggregation', 'flowview'),
	 14 => __('Prefix Aggregation', 'flowview'),
	 24 => __('Full (Catalyst)', 'flowview')
);

$flow_select_array = array(
	  1 => __('Any Part in Specified Time Span', 'flowview'),
	  2 => __('End Time in Specified Time Span', 'flowview'),
	  3 => __('Start Time in Specified Time Span', 'flowview'),
	  4 => __('Entirely in Specified Time Span', 'flowview')
);

$ip_protocols_array = array(
	'' => __('Select One', 'flowview'),
	6  => __('TCP', 'flowview'),
	17 => __('UDP', 'flowview'),
	1  => __('ICMP', 'flowview'),
	2  => __('IGMP', 'flowview'),
	3  => __('GGP', 'flowview'),
	4  => __('IPENCAP', 'flowview'),
	5  => __('ST', 'flowview'),
	7  => __('CBT', 'flowview'),
	8  => __('EGP', 'flowview'),
	9  => __('IGP', 'flowview'),
	10 => __('BBN-RCC-MON', 'flowview'),
	11 => __('NVP-II', 'flowview'),
	12 => __('PUP', 'flowview'),
	13 => __('ARGUS', 'flowview'),
	14 => __('EMCON', 'flowview'),
	15 => __('XNET', 'flowview'),
	16 => __('CHAOS', 'flowview'),
	18 => __('MUX', 'flowview'),
	19 => __('DCN-MEAS', 'flowview'),
	20 => __('HMP', 'flowview'),
	21 => __('PRM', 'flowview'),
	22 => __('XNS-IDP', 'flowview'),
	23 => __('TRUNK-1', 'flowview'),
	24 => __('TRUNK-2', 'flowview'),
	25 => __('LEAF-1', 'flowview'),
	26 => __('LEAF-2', 'flowview'),
	27 => __('RDP', 'flowview'),
	28 => __('IRTP', 'flowview'),
	29 => __('ISO-TP4', 'flowview'),
	30 => __('NETBLT', 'flowview'),
	31 => __('MFE-NSP', 'flowview'),
	32 => __('MERIT-INP', 'flowview'),
	33 => __('DCCP', 'flowview'),
	34 => __('3PC', 'flowview'),
	35 => __('IDPR', 'flowview'),
	36 => __('XTP', 'flowview'),
	37 => __('DDP', 'flowview'),
	38 => __('IDPR-CMTP', 'flowview'),
	39 => __('TP++', 'flowview'),
	40 => __('IL', 'flowview'),
	41 => __('IPv6', 'flowview'),
	42 => __('SDRP', 'flowview'),
	43 => __('IPv6-Route', 'flowview'),
	44 => __('IPv6-Frag', 'flowview'),
	45 => __('IDRP', 'flowview'),
	46 => __('RSVP', 'flowview'),
	47 => __('GRE', 'flowview'),
	48 => __('DSR', 'flowview'),
	49 => __('BNA', 'flowview'),
	50 => __('IPSEC-ESP', 'flowview'),
	51 => __('IPSEC-AH', 'flowview'),
	58 => __('IPv6-ICMP', 'flowview'),
	59 => __('IPv6-NoNxt', 'flowview'),
	60 => __('IPv6-Opts', 'flowview'),
	73 => __('RSPF', 'flowview'),
	81 => __('VMTP', 'flowview'),
	88 => __('EIGRP', 'flowview'),
	89 => __('OSPF', 'flowview'),
	92 => __('MTP', 'flowview'),
	94 => __('IPIP', 'flowview'),
	98 => __('ENCAP', 'flowview'),
);

$resolve_addresses_array = array (
	'Y' => __('Yes', 'flowview'),
	'N' => __('No', 'flowview')
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
	'none_value' => __('None', 'flowview'),
	'on_change' => 'applyFilter()',
	'sql' => 'SELECT id, name FROM plugin_flowview_queries ORDER BY name'
);

$device_name_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('device_name') ? get_nfilter_request_var('device_name') : $ddevice),
	'none_value' => __('None', 'flowview'),
	'array' => $devices
);

$cutoff_lines_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 20,
	'description' => '',
	'value' => (isset_request_var('cutoff_lines') ? get_nfilter_request_var('cutoff_lines') : 0),
	'array' => array(
		'999999' => __('All', 'flowview'),
		'5'   => __('Top %d', 5, 'flowview'),
		'10'  => __('Top %d', 10, 'flowview'),
		'20'  => __('Top %d', 20, 'flowview'),
		'30'  => __('Top %d', 30, 'flowview'),
		'40'  => __('Top %d', 40, 'flowview'),
		'50'  => __('Top %d', 50, 'flowview'),
		'100' => __('Top %d', 100, 'flowview'),
		'200' => __('Top %d', 200, 'flowview')
	)
);

$cutoff_octets_field = array(
	'friendly_name' => '',
	'method' => 'drop_array',
	'default' => 0,
	'description' => '',
	'value' => (isset_request_var('cutoff_octets') ? get_nfilter_request_var('cutoff_octets'):''),
	'array' => array(
		'0'         => __('No Limit', 'flowview'),
		'1024'      => __('%s Bytes', '1K', 'flowview'),
		'10240'     => __('%s Bytes', '10K', 'flowview'),
		'20480'     => __('%s Bytes', '20K', 'flowview'),
		'102400'    => __('%s Bytes', '100K', 'flowview'),
		'512000'    => __('%s Bytes', '500K', 'flowview'),
		'1024000'   => __('%s Bytes', '1M', 'flowview'),
		'10240000'  => __('%s Bytes', '10M', 'flowview'),
		'20480000'  => __('%s Bytes', '20M', 'flowview'),
		'51200000'  => __('%s Bytes', '50M', 'flowview'),
		'102400000' => __('%s Bytes', '100M', 'flowview'),
		'204800000' => __('%s Bytes', '200M', 'flowview'),
		'512000000' => __('%s Bytes', '500M', 'flowview'),
		'1024000000'=> __('%s Bytes', '1G', 'flowview')
	)
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
	5  => array(1, 2, '-1',  '-1', '0',  __('Port', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	6  => array(1, 2, '-1',  '-1', '0',  __('Port', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	7  => array(1, 2, '-1',  '-1', '0',  __('Port', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	8  => array(1, 2, 0,     '-1', '-1', __('Dest IP', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	9  => array(1, 2, 0,     '-1', '-1', __('Src IP', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	10 => array(1, 3, '0,1', '-1', '-1', __('Src IP', 'flowview'), __('Dest IP', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	11 => array(1, 2, 0,     '-1', '-1', __('Src/Dest IP', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	12 => array(1, 2, '-1',  '0',  '-1', __('Protocol', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	17 => array(1, 2, '-1',  '-1', '-1', __('Input IF', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	18 => array(1, 2, '-1',  '-1', '-1', __('Output IF', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	19 => array(1, 2, '-1',  '-1', '-1', __('Src AS', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	20 => array(1, 2, '-1',  '-1', '-1', __('Dest AS', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	21 => array(1, 3, '-1',  '-1', '-1', __('Src AS', 'flowview'), __('Dest AS', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	22 => array(1, 2, '-1',  '-1', '-1', __('TOS', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	23 => array(1, 3, '-1',  '-1', '-1', __('Input IF', 'flowview'), __('Output IF', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	24 => array(1, 2, '0',   '-1', '-1', __('Src Prefix', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	25 => array(1, 2, '0',   '-1', '-1', __('Dest Prefix', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	26 => array(1, 3, '0,1', '-1', '-1', __('Src Prefix', 'flowview'), __('Dest Prefix', 'flowview'), __('Flows', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
);

$print_columns_array = array(
	1  => array(2, 8, '1,3', '1', '4', '1', '5,6', __('Src IF', 'flowview'), __('Src IP', 'flowview'), __('Dest IF', 'flowview'), __('Dest IP', 'flowview'), __('Protocol', 'flowview'), __('Src Port', 'flowview'), __('Dest Port', 'flowview'), __('Packets', 'flowview'), __('Bytes', 'flowview'), __('Start Time', 'flowview'), __('End Time', 'flowview'), __('Active', 'flowview'), __('B/Pk', 'flowview'), __('Ts', 'flowview'), __('Fl', 'flowview')),
	4  => array(1, 5, '', '0', '2', '0', '', __('Src IP', 'flowview'), __('Dest IP', 'flowview'), __('Protocol', 'flowview'), __('Src AS', 'flowview'), __('Dest AS', 'flowview'), __('Bytes', 'flowview'), __('Packets', 'flowview')),
	5  => array(1, 11, '3,6', '0', '8', '0', '4,7', __('Start Time', 'flowview'), __('End Time', 'flowview'), __('Src IF', 'flowview'), __('Src IP', 'flowview'), __('Src Port', 'flowview'), __('Dest IF', 'flowview'), __('Dest IP', 'flowview'), __('Dest Port', 'flowview'), __('Protocol', 'flowview'), __('Flags', 'flowview'), __('Packets', 'flowview'), __('Bytes', 'flowview')),
);


