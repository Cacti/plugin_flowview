<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
	0  => __('Select a Report', 'flowview'),
	99 => __('Summary', 'flowview'),

	2  => __('Source Root Domain', 'flowview'),
	3  => __('Destination Root Domain', 'flowview'),
	4  => __('Source/Destination Root Domain', 'flowview'),

	5  => __('UDP/TCP Destination Port', 'flowview'),
	6  => __('UDP/TCP Source Port', 'flowview'),
	7  => __('UDP/TCP Port', 'flowview'),

	9  => __('Source IP', 'flowview'),
	8  => __('Destination IP', 'flowview'),
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
	0  => __('Select a Report', 'flowview'),
	1  => __('Flow Times', 'flowview'),
	4  => __('AS Numbers', 'flowview'),
	5  => __('132 Columns', 'flowview'),
	6  => __('Show IP Accounting Emulation', 'flowview')
);

$flow_select_array = array(
	1 => __('Any Part in Range', 'flowview'),
	2 => __('End Time in Range', 'flowview'),
	3 => __('Start Time in Range', 'flowview'),
	4 => __('Entirely in Range', 'flowview')
);

$ip_protocols_array = array(
	0  => __('All', 'flowview'),
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

$stat_columns_array = array(
	2  => array(
		__('Source Domain', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	3  => array(
		__('Destination Domain', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	4 => array(
		__('Source Domain', 'flowview'),
		__('Destination Domain', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	5  => array(
		__('Port', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	6  => array(
		__('Port', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	7  => array(
		__('Port', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	8  => array(
		__('Destination IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	9  => array(
		__('Source IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	10 => array(
		__('Source IP', 'flowview'),
		__('Dest IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	11 => array(
		__('Source IP', 'flowview'),
		__('Destination IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	12 => array(
		__('Protocol', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	17 => array(
		__('Input IF', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	18 => array(
		__('Output IF', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	19 => array(
		__('Source AS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	20 => array(
		__('Destination AS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	21 => array(
		__('Source AS', 'flowview'),
		__('Destination AS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	22 => array(
		__('TOS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	23 => array(
		__('Input IF', 'flowview'),
		__('Output IF', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	24 => array(
		__('Source Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	25 => array(
		__('Destination Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	26 => array(
		__('Source Prefix', 'flowview'),
		__('Destingation Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	)
);

$print_columns_array = array(
	1  => array(
		__('Source IF', 'flowview'),
		__('Source IP', 'flowview'),
		__('Destination IF', 'flowview'),
		__('Destination IP', 'flowview'),
		__('Protocol', 'flowview'),
		__('Source Port', 'flowview'),
		__('Destination Port', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview'),
		__('Start Time', 'flowview'),
		__('End Time', 'flowview'),
		__('Active', 'flowview'),
		__('B/Pk', 'flowview'),
		__('Ts', 'flowview'),
		__('Fl', 'flowview')
	),
	4  => array(
		__('Source IP', 'flowview'),
		__('Destination IP', 'flowview'),
		__('Protocol', 'flowview'),
		__('Source AS', 'flowview'),
		__('Destination AS', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	5  => array(
		__('Start Time', 'flowview'),
		__('End Time', 'flowview'),
		__('Source IF', 'flowview'),
		__('Source IP', 'flowview'),
		__('Source Port', 'flowview'),
		__('Destination IF', 'flowview'),
		__('Destination IP', 'flowview'),
		__('Destination Port', 'flowview'),
		__('Protocol', 'flowview'),
		__('Flags', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview')
	),
	6  => array(
		__('Source', 'flowview'),
		__('Destination', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview')
	)
);

$devices = array_rekey(
	db_fetch_assoc('SELECT id, name
		FROM plugin_flowview_devices
		ORDER BY name'),
	'id', 'name'
);

$filter_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'collapsible' => true,
		'friendly_name' => __('General Filters', 'flowview'),
	),
	'name' => array(
		'friendly_name' => __('Filter', 'flowview'),
		'description' => __('The Saved Filter to display.', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:name|',
		'default' => __('New Filter', 'flowview'),
		'size' => 50,
		'max_length' => 64
	),
	'device_id' => array(
		'friendly_name' => __('Listener', 'flowview'),
		'description' => __('The Listener to use for the Filter.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:device_id|',
		'array' => $devices,
		'default' => '0',
		'none_value' => __('All', 'flowview'),
	),
	'predefined_timespan' => array(
		'friendly_name' => __('Presets', 'flowview'),
		'description' => __('If this Filter is based upon a pre-defined Timespan, select it here.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:timespan|',
		'array' => $graph_timespans,
		'default' => '0',
	),
	'spacer1' => array(
		'method' => 'spacer',
		'collapsible' => true,
		'friendly_name' => __('Detailed Filter Criteria', 'flowview'),
	),
	'rtype' => array(
		'friendly_name' => __('Report Type', 'flowview'),
		'description' => __('The Report Type to use by default for this Filter when creating a Report.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:rtype|',
		'array' => array(0 => __('Statistical', 'flowview'), 1 => __('Printed', 'flowview')),
		'default' => '0'
	),
	'statistics' => array(
		'friendly_name' => __('Statistical Report', 'flowview'),
		'description' => __('The Display Report Type to use by default for this Filter when creating a Report.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:statistics|',
		'array' => $stat_report_array,
		'default' => '10'
	),
	'printed' => array(
		'friendly_name' => __('Printed Report', 'flowview'),
		'description' => __('The Printed Report Type to use by default for this Filter when creating a Printed Report.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:printed|',
		'array' => $print_report_array,
		'default' => '0'
	),
	'includeif' => array(
		'friendly_name' => __('Range Rules', 'flowview'),
		'description' => __('Constrain the Filter Data by these time filter rules.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:includeif|',
		'default' => '1',
		'array' => $flow_select_array
	),
	'resolve' => array(
		'friendly_name' => __('Resolve IP\'s', 'flowview'),
		'description' => __('Resolve IP Addresses to Domain Names.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:resolve|',
		'default' => 'Y',
		'array' => array(
			'Y' => __('Yes', 'flowview'),
			'N' => __('No', 'flowview')
		)
	),
	'sortfield' => array(
		'friendly_name' => __('Sort Field', 'flowview'),
		'description' => __('The default Sort Field for the Filter.  This setting will be applied for any Scheduled Reports.', 'flowview'),
		'value' => '|arg1:sortfield|',
		'method' => 'drop_array',
		'default' => '10',
		'array' => array()
	),
	'cutofflines' => array(
		'friendly_name' => __('Maximum Rows', 'flowview'),
		'description' => __('The Maximum Rows to provide in the Filter.  This setting will be applied for any Scheduled Reports.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:cutofflines|',
		'default' => '20',
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
	),
	'cutoffoctets' => array(
		'friendly_name' => __('Minimum Bytes', 'flowview'),
		'description' => __('The Minimum Total Bytes to consider for the Filter.  Any flow totals that are less than this many bytes will be ignored.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:cutoffoctets|',
		'default' => '0',
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
	),
	'spacer2' => array(
		'method' => 'spacer',
		'collapsible' => true,
		'friendly_name' => __('Protocol Filters', 'flowview'),
	),
	'protocols' => array(
		'friendly_name' => __('Protocols', 'flowview'),
		'description' => __('Select the Specific Protocol for the Filter.', 'flowview'),
		'method' => 'drop_multi',
		'value' => '|arg1:protocols|',
		'default' => '0',
		'array' => $ip_protocols_array
	),
	'tcpflags' => array(
		'friendly_name' => __('TCP Flags', 'flowview'),
		'description' => __('The TCP Flags to search for in the Filter.  This can be a comma delimited list of TCP Flags', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:tcpflags|',
		'max_length' => '20',
		'size' => '14'
	),
	'tosfields' => array(
		'friendly_name' => __('TOS Fields', 'flowview'),
		'description' => __('The TOS Fields to search for in the Filter.  This can be a comma delimited list of TOS Fields', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:tosfields|',
		'max_length' => '20',
		'size' => '14'
	),
	'sourceip' => array(
		'friendly_name' => __('Source IP', 'flowview'),
		'description' => __('Filter on the select Source IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format (eg. 192.168.1.0/24).', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:sourceip|',
		'max_length' => '20',
		'size' => '14'
	),
	'sourceport' => array(
		'friendly_name' => __('Source Ports', 'flowview'),
		'description' => __('Filter on the select Source Ports for in the Filter.  This can be a comma delimited list of Source Ports.', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:sourceport|',
		'max_length' => '20',
		'size' => '14'
	),
	'sourceinterface' => array(
		'friendly_name' => __('Source Interface', 'flowview'),
		'description' => __('Filter on the select Source Interface for in the Filter.  This can be a comma delimited list of Source Interfaces', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:sourceinterface|',
		'max_length' => '20',
		'size' => '14'
	),
	'sourceas' => array(
		'friendly_name' => __('Source AS', 'flowview'),
		'description' => __('Filter on the select Destination AS for in the Filter.  This can be a comma delimited list of Source AS\'s', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destas|',
		'max_length' => '20',
		'size' => '14'
	),
	'destip' => array(
		'friendly_name' => __('Dest IP', 'flowview'),
		'description' => __('Filter on the select Destination IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format (eg. 192.168.1.0/24).', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destip|',
		'max_length' => '20',
		'size' => '14'
	),
	'destport' => array(
		'friendly_name' => __('Dest Ports', 'flowview'),
		'description' => __('Filter on the select Destination Ports for in the Filter.  This can be a comma delimited list of Destimation Ports.', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destport|',
		'max_length' => '20',
		'size' => '14'
	),
	'destinterface' => array(
		'friendly_name' => __('Dest Interface', 'flowview'),
		'description' => __('Filter on the select Destination Interface for in the Filter.  This can be a comma delimited list of Destimation Interfaces.', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destinterface|',
		'max_length' => '20',
		'size' => '14'
	),
	'destas' => array(
		'friendly_name' => __('Dest AS', 'flowview'),
		'description' => __('Filter on the select Destination AS for in the Filter.  This can be a comma delimited list of Destimation AS\'s', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destas|',
		'max_length' => '20',
		'size' => '14'
	),
	'id' => array(
		'method' => 'hidden',
		'value' => '|arg1:id|'
	)
);


