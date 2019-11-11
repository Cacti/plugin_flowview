<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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
	 6  => __('Show IP Accounting Emulation', 'flowview')
);

$flow_select_array = array(
	  1 => __('Any Part in Range', 'flowview'),
	  2 => __('End Time in Range', 'flowview'),
	  3 => __('Start Time in Range', 'flowview'),
	  4 => __('Entirely in Range', 'flowview')
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

$stat_columns_array = array(
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
		__('Dest IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	9  => array(
		__('Src IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	10 => array(
		__('Src IP', 'flowview'),
		__('Dest IP', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	11 => array(
		__('Src/Dest IP', 'flowview'),
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
		__('Src AS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	20 => array(
		__('Dest AS', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	21 => array(
		__('Src AS', 'flowview'),
		__('Dest AS', 'flowview'),
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
		__('Src Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	25 => array(
		__('Dest Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	26 => array(
		__('Src Prefix', 'flowview'),
		__('Dest Prefix', 'flowview'),
		__('Flows', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	)
);

$print_columns_array = array(
	1  => array(2, 8, '1,3', '1', '4', '1', '5,6',
		__('Src IF', 'flowview'),
		__('Src IP', 'flowview'),
		__('Dest IF', 'flowview'),
		__('Dest IP', 'flowview'),
		__('Protocol', 'flowview'),
		__('Src Port', 'flowview'),
		__('Dest Port', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview'),
		__('Start Time', 'flowview'),
		__('End Time', 'flowview'),
		__('Active', 'flowview'),
		__('B/Pk', 'flowview'),
		__('Ts', 'flowview'),
		__('Fl', 'flowview')
	),
	4  => array(1, 5, '', '0', '2', '0', '',
		__('Src IP', 'flowview'),
		__('Dest IP', 'flowview'),
		__('Protocol', 'flowview'),
		__('Src AS', 'flowview'),
		__('Dest AS', 'flowview'),
		__('Bytes', 'flowview'),
		__('Packets', 'flowview')
	),
	5  => array(1, 11, '3,6', '0', '8', '0', '4,7',
		__('Start Time', 'flowview'),
		__('End Time', 'flowview'),
		__('Src IF', 'flowview'),
		__('Src IP', 'flowview'),
		__('Src Port', 'flowview'),
		__('Dest IF', 'flowview'),
		__('Dest IP', 'flowview'),
		__('Dest Port', 'flowview'),
		__('Protocol', 'flowview'),
		__('Flags', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview')
	),
	6  => array(1, 11, '3,6', '0', '8', '0', '4,7',
		__('Source', 'flowview'),
		__('Destination', 'flowview'),
		__('Packets', 'flowview'),
		__('Bytes', 'flowview')
	)
);

$print_array = array(
	1 => array(
		'clines' => 2,
		'ports_hex' => true,
		'if_hex' => true,
		'db_columns' => 'src_if, src_addr, src_domain, src_rdomain, dst_if, dst_addr, dst_domain, dst_rdomain, protocol, src_port, src_rport, dst_port, dst_rport, packets, bytes, start_time, end_time, active, bytes_ppacket, Ts, Fl',
		'spec' => array(
			array(
				'column' => 'SIf',
				'description' => __('Src IF', 'flowview'),
			),
			array(
				'column' => 'SrcIPaddress',
				'description' => __('Src IP', 'flowview'),
			),
			array(
				'column' => 'DIf',
				'description' => __('Dest IF', 'flowview'),
			),
			array(
				'column' => 'DstIPaddress',
				'description' => __('Dest IP', 'flowview'),
			),
			array(
				'column' => 'Pr',
				'description' => __('Protocol', 'flowview'),
			),
			array(
				'column' => 'SrcP',
				'description' => __('Src Port', 'flowview'),
			),
			array(
				'column' => 'DstP',
				'description' => __('Dest Port', 'flowview'),
			),
			array(
				'column' => 'Pkts',
				'description' => __('Packets', 'flowview'),
			),
			array(
				'column' => 'Octets',
				'description' => __('Bytes', 'flowview'),
			),
			array(
				'column' => 'StartTime',
				'description' => __('Start Time', 'flowview'),
			),
			array(
				'column' => 'EndTime',
				'description' => __('End Time', 'flowview'),
			),
			array(
				'column' => 'Active',
				'description' => __('Active', 'flowview'),
			),
			array(
				'column' => 'B/Pk',
				'description' => __('B/Pk', 'flowview'),
			),
			array(
				'column' => 'Ts',
				'description' => __('Ts', 'flowview'),
			),
			array(
				'column' => 'Fl',
				'description' => __('Fl', 'flowview')
			)
		)
	),
	4  => array(
		'clines' => 1,
		'db_columns' => 'src_mask, src_addr, src_domain, src_rdomain, dst_mask, dst_addr, dst_domain, dst_rdomain, protocol, src_as, dst_as, bytes, packets',
		'spec' => array(
			array(
				'column' => 'srcIP',
				'description' => __('Src IP', 'flowview'),
			),
			array(
				'column' => 'dstIP',
				'description' => __('Dest IP', 'flowview'),
			),
			array(
				'column' => 'prot',
				'description' => __('Protocol', 'flowview'),
			),
			array(
				'column' => 'srcAS',
				'description' => __('Src AS', 'flowview'),
			),
			array(
				'column' => 'dstAS',
				'description' => __('Dest AS', 'flowview'),
			),
			array(
				'column' => 'octets',
				'description' => __('Bytes', 'flowview'),
			),
			array(
				'column' => 'packets',
				'description' => __('Packets', 'flowview')
			)
		)
	),
	5 => array(
		'clines' => 1,
		'ports_hex' => false,
		'if_hex' => false,
		'db_columns' => 'start_time, end_time, src_if, src_addr, src_domain, src_rdomain, src_port, src_rport, dst_if, dst_addr, dst_domain, dst_rdomain, dst_port, dst_rport, protocol, flags, packets, bytes',
		'spec' => array(
			array(
				'column' => 'Start',
				'description' => __('Start Time', 'flowview'),
			),
			array(
				'column' => 'End' ,
				'description'=> __('End Time', 'flowview'),
			),
			array(
				'column' => 'Sif',
				'description' => __('Src IF', 'flowview'),
			),
			array(
				'column' => 'SrcIPaddress',
				'description' => __('Src IP', 'flowview'),
			),
			array(
				'column' => 'SrcP',
				'description' => __('Src Port', 'flowview'),
			),
			array(
				'column' => 'DIf',
				'description' => __('Dest IF', 'flowview'),
			),
			array(
				'column' => 'DstIPaddress',
				'description' => __('Dest IP', 'flowview'),
			),
			array(
				'column' => 'DstP',
				'description' => __('Dest Port', 'flowview'),
			),
			array(
				'column' => 'P',
				'description' => __('Protocol', 'flowview'),
			),
			array(
				'column' => 'Fl',
				'description' => __('Flags', 'flowview'),
			),
			array(
				'column' => 'Pkts',
				'description' => __('Packets', 'flowview'),
			),
			array(
				'column' => 'Octets',
				'description' => __('Bytes', 'flowview')
			)
		)
	),
	6 => array(
		'clines' => 1,
		'db_columns' => 'src_addr, src_domain, src_rdomain, dst_addr, dst_domain, dst_rdomain, packets, bytes',
		'spec' => array(
			array(
				'column' => 'Source',
				'description' => __('Source', 'flowview'),
			),
			array(
				'column' => 'Destination',
				'description' => __('Destination', 'flowview'),
			),
			array(
				'column' => 'Packets',
				'description' => __('Packets', 'flowview'),
			),
			array(
				'column' => 'Bytes',
				'description' => __('Bytes', 'flowview')
			)
		)
	)
);

$filter_edit = array(
	'spacer0' => array(
		'method' => 'spacer',
		'collapsible' => true,
		'friendly_name' => __('General Filters', 'flowview'),
	),
	'query' => array(
		'friendly_name' => __('Filter', 'flowview'),
		'description' => __('The Saved Filter to display.', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:query|',
		'default' => __('New Filter', 'flowview'),
		'size' => 50,
		'max_length' => 64
	),
	'device' => array(
		'friendly_name' => __('Listener', 'flowview'),
		'description' => __('The Listener to use for the Filter.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:device|',
		'array' => $devices,
		'default' => '0',
		'none_value' => __('None', 'flowview'),
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
	'statistics' => array(
		'friendly_name' => __('Report Type', 'flowview'),
		'description' => __('The Report Type to use by default for this Filter when creating a Report.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:statistics|',
		'array' => $stat_report_array,
		'default' => '10',
		'none_value' => __('None', 'flowview'),
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
		'friendly_name' => __('Protocol', 'flowview'),
		'description' => __('Select the Specific Protocol for the Filter.', 'flowview'),
		'method' => 'drop_array',
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
		'description' => __('Filter on the select Source IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format.', 'flowview'),
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
	'destas' => array(
		'friendly_name' => __('Dest AS', 'flowview'),
		'description' => __('Filter on the select Destination AS for in the Filter.  This can be a comma delimited list of Source AS\'s', 'flowview'),
		'method' => 'textbox',
		'value' => '|arg1:destas|',
		'max_length' => '20',
		'size' => '14'
	),
	'destip' => array(
		'friendly_name' => __('Dest IP', 'flowview'),
		'description' => __('Filter on the select Destination IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format.', 'flowview'),
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
);

