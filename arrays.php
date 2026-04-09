<?php

declare(strict_types=1);
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
	7  => __('Source/Destination IPs and Ports', 'flowview'),
	4  => __('AS Numbers', 'flowview'),
	5  => __('132 Columns', 'flowview'),
	6  => __('Show IP Accounting Emulation', 'flowview'),
);

$flow_select_array = array(
	1 => __('Any Part in Range (slow)', 'flowview'),
	2 => __('End Time in Range (fast)', 'flowview'),
	3 => __('Start Time in Range (fast)', 'flowview'),
	4 => __('Entirely in Range (slow)', 'flowview')
);

$cutoff_lines = array(
	'999999' => __('Return All Lines', 'flowview'),
	'5'      => __('Top %d', 5, 'flowview'),
	'10'     => __('Top %d', 10, 'flowview'),
	'15'     => __('Top %d', 15, 'flowview'),
	'20'     => __('Top %d', 20, 'flowview'),
	'30'     => __('Top %d', 30, 'flowview'),
	'40'     => __('Top %d', 40, 'flowview'),
	'50'     => __('Top %d', 50, 'flowview'),
	'100'    => __('Top %d', 100, 'flowview'),
	'200'    => __('Top %d', 200, 'flowview')
);

$cutoff_octets = array(
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
);

$ip_protocols_array = array(
	0  => __('All', 'flowview'),
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
	98 => 'ENCAP'
);

$required_fields_v4 = array(
	'octetDeltaCount'             => 1,
	'packetDeltaCount'            => 2,
	'protocolIdentifier'          => 4,
	'sourceTransportPort'         => 7,
	'sourceIPv4Address'           => 8,
	'destinationTransportPort'    => 11,
	'destinationIPv4Address'      => 12,
);

$required_fields_v6 = array(
	'octetDeltaCount'             => 1,
	'packetDeltaCount'            => 2,
	'protocolIdentifier'          => 4,
	'ipClassOfService'            => 5,
	'tcpControlBits'              => 6,
	'sourceTransportPort'         => 7,
	'ingressInterface'            => 10,
	'destinationTransportPort'    => 11,
	'egressInterface'             => 14,
	'flowEndSysUpTime'            => 21,
	'flowStartSysUpTime'          => 22,
	'sourceIPv6Address'           => 27,
	'destinationIPv6Address'      => 28,
	'sourceIPv6PrefixLength'      => 29,
	'destinationIPv6PrefixLength' => 30,
	'samplingInterval'            => 34,
	'ipVersion'                   => 60,
	'ipNextHopIPv6Address'        => 62,
);

$flow_fields = array(
	'engine_type'       => 38,
	'engine_id'         => 39,
	'sampling_interval' => 34,
	'ipVersion'         => 60,
	'sysuptime'         => 160,

	'src_addr'          => 8,
	'src_addr_ipv6'     => 27,
	'src_prefix'        => 9,
	'src_prefix_ipv6'   => 29,
	'src_if'            => 10,
	'src_as'            => 16,

	'dst_addr'          => 12,
	'dst_addr_ipv6'     => 28,
	'dst_prefix'        => 13,
	'dst_prefix_ipv6'   => 30,
	'dst_if'            => 14,
	'dst_as'            => 17,

	'nexthop'           => 15,
	'nexthop_ipv6'      => 62,

	'dPkts'             => 2,
	'dOctets'           => 1,
	'src_port'          => 7,
	'dst_port'          => 11,

	'protocol'          => 4,
	'tos'               => 5,
	'flags'             => 6,
	'start_time'        => 22,
	'end_time'          => 21
);

$flow_fieldids = array(
	1 => array(
		'column' => 'dOctets',
		'name'   => 'octetDeltaCount'
	),
	2 => array(
		'column' => 'dPkts',
		'name'   => 'packetDeltaCount'
	),
	4 => array(
		'column' => 'protocol',
		'name'   => 'protocolIdentifier'
	),
	5 => array(
		'column' => 'tos',
		'name'   => 'ipClassOfService'
	),
	6 => array(
		'column' => 'flags',
		'name'   => 'tcpControlBits'
	),
	7 => array(
		'column' => 'src_port',
		'name'   => 'sourceTransportPort'
	),
	8 => array(
		'column' => 'src_addr',
		'name'   => 'sourceIPv4Address'
	),
	9 => array(
		'column' => 'src_prefix',
		'name'   => 'sourceIPv4PrefixLength'
	),
	10 => array(
		'column' => 'src_if',
		'name'   => 'ingressInterface'
	),
	11 => array(
		'column' => 'dst_port',
		'name'   => 'destinationTransportPort'
	),
	12 => array(
		'column' => 'dst_addr',
		'name'   => 'destinationIPv4Address'
	),
	13 => array(
		'column' => 'dst_prefix',
		'name'   => 'destinationIPv4PrefixLength'
	),
	14 => array(
		'column' => 'dst_if',
		'name'   => 'egressInterface'
	),
	15 => array(
		'column' => 'nexthop',
		'name'   => 'ipNextHopIPv4Address'
	),
	16 => array(
		'column' => 'src_as',
		'name'   => 'bgpSourceAsNumber'
	),
	17 => array(
		'column' => 'dst_as',
		'name'   => 'bgpDestinationAsNumber'
	),
	21 => array(
		'column' => 'end_time',
		'name'   => 'flowEndSysUpTime'
	),
	22 => array(
		'column' => 'start_time',
		'name'   => 'flowStartSysUpTime'
	),
	27 => array(
		'column' => 'src_addr',
		'name'   => 'sourceIPv6Address'
	),
	28 => array(
		'column' => 'dst_addr',
		'name'   => 'destinationIPv6Address'
	),
	29 => array(
		'column' => 'src_prefix',
		'name'   => 'sourceIPv6PrefixLength'
	),
	30 => array(
		'column' => 'dst_prefix',
		'name'   => 'destinationIPv6PrefixLength'
	),
	34 => array(
		'column' => 'sampling_interval',
		'name'   => 'samplingInterval'
	),
	38 => array(
		'column' => 'engine_type',
		'name'   => 'engineType'
	),
	39 => array(
		'column' => 'engine_id',
		'name'   => 'engineId'
	),
	60 => array(
		'column' => 'ipVersion',
		'name'   => 'ipVersion'
	),
	62 => array(
		'column' => 'nexthop',
		'name'   => 'ipNextHopIPv6Address'
	),
	160 => array(
		'column' => 'sysuptime',
		'name'   => 'systemInitTimeMilliseconds'
	)
);

$stat_columns_array = array(
	2  => array(
		'src_rdomain' => __('Source Domain', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	3  => array(
		'dst_rdomain' => __('Destination Domain', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	4 => array(
		'src_rdomain' => __('Source Domain', 'flowview'),
		'src_rdomain' => __('Destination Domain', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	5  => array(
		'dst_port'    => __('Port', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	6  => array(
		'src_port'    => __('Port', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	7  => array(
		'src_port'    => __('Source Port', 'flowview'),
		'dst_port'    => __('Destination Port', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	8  => array(
		'dst_addr'    => __('Destination IP', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	9  => array(
		'src_addr'    => __('Source IP', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	10 => array(
		'src_addr'    => __('Source IP', 'flowview'),
		'dst_addr'    => __('Dest IP', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	11 => array(
		'src_addr'    => __('IP Address', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	12 => array(
		'protocol'    => __('Protocol', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	17 => array(
		'src_if'      => __('Source IF', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	18 => array(
		'dst_if'      => __('Destination IF', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	19 => array(
		'src_as'      => __('Source AS', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	20 => array(
		'dst_as'      => __('Destination AS', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	21 => array(
		'src_as'      => __('Source AS', 'flowview'),
		'dst_as'      => __('Destination AS', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	22 => array(
		'tos'         =>__('TOS', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	23 => array(
		'src_if'      => __('Source IF', 'flowview'),
		'dst_if'      => __('Destination IF', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	24 => array(
		'src_prefix'  => __('Source Prefix', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	25 => array(
		'dst_prefix'  => __('Destination Prefix', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	26 => array(
		'src_prefix'  => __('Source Prefix', 'flowview'),
		'dst_prefix'  => __('Destingation Prefix', 'flowview'),
		'flows'       => __('Flows', 'flowview'),
		'bytes'       => __('Bytes', 'flowview'),
		'packets'     => __('Packets', 'flowview')
	),
	99 => array(
		'-1'          => __('N/A', 'flowview')
	)
);

$print_columns_array = array(
	1  => array(
		'src_if'     => __('Source IF', 'flowview'),
		'src_addr'   => __('Source IP', 'flowview'),
		'dst_if'     => __('Destination IF', 'flowview'),
		'dst_addr'   => __('Destination IP', 'flowview'),
		'protocol'   => __('Protocol', 'flowview'),
		'src_port'   => __('Source Port', 'flowview'),
		'dst_port'   => __('Destination Port', 'flowview'),
		'packets'    => __('Packets', 'flowview'),
		'bytes'      => __('Bytes', 'flowview'),
		'start_time' => __('Start Time', 'flowview'),
		'end_time'   => __('End Time', 'flowview'),
		'active'     => __('Active', 'flowview'),
		'bpp'        => __('B/Pk', 'flowview'),
		'tos'        => __('Ts', 'flowview'),
		'flags'      => __('Fl', 'flowview')
	),
	4  => array(
		'src_addr'   => __('Source IP', 'flowview'),
		'dst_addr'   => __('Destination IP', 'flowview'),
		'protocol'   => __('Protocol', 'flowview'),
		'src_as'     => __('Source AS', 'flowview'),
		'dst_as'     => __('Destination AS', 'flowview'),
		'bytes'      => __('Bytes', 'flowview'),
		'packets'    => __('Packets', 'flowview')
	),
	5  => array(
		'start_time' => __('Start Time', 'flowview'),
		'end_time'   => __('End Time', 'flowview'),
		'src_if'     => __('Source IF', 'flowview'),
		'src_addr'   => __('Source IP', 'flowview'),
		'src_port'   => __('Source Port', 'flowview'),
		'dst_if'     => __('Destination IF', 'flowview'),
		'dst_addr'   => __('Destination IP', 'flowview'),
		'dst_port'   => __('Destination Port', 'flowview'),
		'protocol'   => __('Protocol', 'flowview'),
		'flags'      => __('Flags', 'flowview'),
		'packets'    => __('Packets', 'flowview'),
		'bytes'      => __('Bytes', 'flowview')
	),
	6  => array(
		'src_addr'   => __('Source', 'flowview'),
		'dst_addr'   => __('Destination', 'flowview'),
		'packets'    => __('Packets', 'flowview'),
		'bytes'      => __('Bytes', 'flowview')
	),
	7  => array(
		'src_addr'   => __('Source', 'flowview'),
		'dst_addr'   => __('Destination', 'flowview'),
		'src_port'   => __('Source Port', 'flowview'),
		'dst_port'   => __('Destination Port', 'flowview'),
		'flows'      => __('Flows', 'flowview'),
		'packets'    => __('Packets', 'flowview'),
		'bytes'      => __('Bytes', 'flowview')
	)
);

$graph_heights = array(
	300 => __('%d Pixels', 300),
	350 => __('%d Pixels', 350),
	400 => __('%d Pixels', 400),
	450 => __('%d Pixels', 450),
	500 => __('%d Pixels', 500),
	550 => __('%d Pixels', 550),
	600 => __('%d Pixels', 600),
	650 => __('%d Pixels', 650),
	700 => __('%d Pixels', 700)
);

$devices = array_rekey(
	flowview_db_fetch_assoc('SELECT id, name
		FROM plugin_flowview_devices
		ORDER BY name'),
	'id', 'name'
);

$templates = array_rekey(
	flowview_db_fetch_assoc('SELECT -1 AS id, "' . __esc('All', 'flowview') . '" AS name UNION
		SELECT DISTINCT template_id AS id, template_id AS name
		FROM plugin_flowview_device_templates
		ORDER BY id'),
	'id', 'name'
);

$ex_addrs = array_rekey(
	flowview_db_fetch_assoc('SELECT -1 AS id, "' . __esc('All', 'flowview') . '" AS name UNION
		SELECT DISTINCT ex_addr AS id, name
		FROM plugin_flowview_device_streams
		ORDER BY id'),
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
	'template_id' => array(
		'friendly_name' => __('Flow Template ID', 'flowview'),
		'description' => __('The Flow Template ID for v9 and IPFIX Flows only.  Note that Template ID\'s may differ from manufacturer to manufacturer.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:template_id|',
		'array' => $templates,
		'default' => '-1',
	),
	'ex_addr' => array(
		'friendly_name' => __('Stream Address', 'flowview'),
		'description' => __('The Stream IP Address or Hostname from the list of registered streams.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:ex_addr|',
		'array' => $ex_addrs,
		'default' => '-1',
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
		'default' => '2',
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
		'array' => $cutoff_lines
	),
	'cutoffoctets' => array(
		'friendly_name' => __('Minimum Bytes', 'flowview'),
		'description' => __('The Minimum Total Bytes to consider for the Filter.  Any flow totals that are less than this many bytes will be ignored.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:cutoffoctets|',
		'default' => '0',
		'array' => $cutoff_octets
	),
	'spacer2' => array(
		'method' => 'spacer',
		'collapsible' => true,
		'friendly_name' => __('Charting Options', 'flowview'),
	),
	'graph_type' => array(
		'friendly_name' => __('Graph Type', 'flowview'),
		'description' => __('The Graph Type to display by default.  They include Bar, Pie, and Treemap.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:graph_type|',
		'default' => 'bar',
		'array' => array(
			'bar'     => __('Bar Chart', 'flowview'),
			'pie'     => __('Pie Chart', 'fowview'),
			'treemap' => __('Treemap Chart', 'flowview')
		)
	),
	'graph_height' => array(
		'friendly_name' => __('Graph Height', 'flowview'),
		'description' => __('The Graph Height to use by default.', 'flowview'),
		'method' => 'drop_array',
		'value' => '|arg1:graph_height|',
		'default' => 'bar',
		'array' => $graph_heights
	),
	'panel_table' => array(
		'friendly_name' => __('Table Panel', 'flowview'),
		'description' => __('Should the Table Panel be displayed by default.', 'flowview'),
		'method' => 'checkbox',
		'value' => '|arg1:panel_table|',
		'default' => ''
	),
	'panel_bytes' => array(
		'friendly_name' => __('Bytes Panel', 'flowview'),
		'description' => __('Should the Bytes Panel be displayed by default.', 'flowview'),
		'method' => 'checkbox',
		'value' => '|arg1:panel_bytes|',
		'default' => ''
	),
	'panel_packets' => array(
		'friendly_name' => __('Packets Panel', 'flowview'),
		'description' => __('Should the Packets Panel be displayed by default.', 'flowview'),
		'method' => 'checkbox',
		'value' => '|arg1:panel_packets|',
		'default' => ''
	),
	'panel_flows' => array(
		'friendly_name' => __('Flows Panel', 'flowview'),
		'description' => __('Should the Flows Panel be displayed by default.', 'flowview'),
		'method' => 'checkbox',
		'value' => '|arg1:panel_flows|',
		'default' => ''
	),
	'spacer3' => array(
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
		'method' => 'textarea',
		'value' => '|arg1:sourceip|',
		'textarea_rows' => '2',
		'textarea_cols' => '80'
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
		'value' => '|arg1:sourceas|',
		'max_length' => '20',
		'size' => '14'
	),
	'destip' => array(
		'friendly_name' => __('Dest IP', 'flowview'),
		'description' => __('Filter on the select Destination IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format (eg. 192.168.1.0/24).', 'flowview'),
		'method' => 'textarea',
		'value' => '|arg1:destip|',
		'textarea_rows' => '2',
		'textarea_cols' => '80'
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
	'return' => array(
		'method' => 'hidden',
		'value' => get_request_var('return')
	),
	'id' => array(
		'method' => 'hidden',
		'value' => '|arg1:id|'
	)
);

$db_tabs = array(
	'dns_cache'    => array(
		'name' => __esc('DNS Cache', 'flowview'),
		'function' => 'view_dns_cache',
	),
	'route'        => array(
		'name' => __esc('CIDR/Routes', 'flowview'),
		'function' => 'view_routes',
	),
	'aut_num'      => array(
		'name' => __esc('Autonomous Numbers', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'aut_num, as_name, source, mnt_by, descr, created, last_modified',
		'search'   => 'aut_num, as_name, descr, mnt_by, admin_c, tech_c, remarks',
		'rowid'    => 'aut_num, source'
	),
	'as_block'     => array(
		'name' => __esc('AS Blocks', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'as_block, type, source, mnt_by, descr, created, last_modified',
		'search'   => 'as_block, type, descr, remarks, org, admin_c, tech_c, mnt_by, mnt_lower',
		'rowid'    => 'as_block, source'
	),
	'as_set'       => array(
		'name' => __esc('AS Sets', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'as_set, source, mnt_by, descr, members, created, last_modified',
		'search'   => 'as_set, descr, mnt_by, members, tech_c, admin_c',
		'rowid'    => 'as_set, source'
	),
	'route_set'    => array(
		'name' => __esc('Route Sets', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'route_set, source, mnt_by, descr, created, last_modified',
		'search'   => 'route_set, descr, tech_c, admin_c, members, mnt_by',
		'rowid'    => 'route_set, source'
	),
	'domain'       => array(
		'name' => __esc('Domains', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'domain, source, mnt_by, descr, created, last_modified',
		'search'   => 'domain, descr, nserver, mnt_by, admin_c, tech_c, zone_c, remarks',
		'rowid'    => 'domain, source'
	),
	'filter_set'   => array(
		'name' => __esc('Filter Sets', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'filter_set, source, mnt_by, descr, created, last_modified',
		'search'   => 'filter_set, descr, tech_c, filter, admin_c, mnt_by, remarks, notify',
		'rowid'    => 'filter_set, source'
	),
	'peering_set'  => array(
		'name' => __esc('Peering Sets', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'peering_set, source, mnt_by, descr, created, last_modified',
		'search'   => 'peering_set, descr, mnt_by, remarks, admin_c, tech_c, peering',
		'rowid'    => 'peering_set, source'
	),
	'inetnum'      => array(
		'name' => __esc('Network', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'inetnum, source, mnt_by, status, netname, descr, created, last_modified',
		'search'   => 'inetnum, netname, status, mnt_by, descr, admin_c, tech_c, remarks, mnt_lower',
		'rowid'    => 'inetnum, source'
	),
	'inet_rtr'     => array(
		'name' => __esc('Internet Routers', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'inet_rtr, local_as, source, mnt_by, descr, ifaddr, created, last_modified',
		'search'   => 'inet_rtr, local_ad, descr, mnt_by, ifaddr, admin_c, tech_c, alias',
		'rowid'    => 'inet_rtr, source'
	),
	'irt'          => array(
		'name' => __esc('Incident Response Teams', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'irt, source, mnt_by, address, created, last_modified',
		'search'   => 'irt, address, remarks, admin_c, tech_c, mnt_by',
		'rowid'    => 'irt, source'
	),
	'mntner'       => array(
		'name' => __esc('Auth Agents', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'mntner, source, mnt_by, descr, created, last_modified',
		'search'   => 'mntner, descr, admin_c, tech_c, mnt_nfy, mnt_by, remarks',
		'rowid'    => 'mntner, source'
	),
	'organisation' => array(
		'name' => __esc('Organizations', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'organisation, source, org_name, org_type, country, created, last_modified',
		'search'   => 'organisation, org_name, org_type, country, remarks, mnt_ref, mnt_by',
		'rowid'    => 'organisation, source'
	),
	'role'         => array(
		'name' => __esc('Roles', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'role, nic_hdl, source, mnt_by, created, last_modified',
		'search'   => 'role, nic_hdl, mnt_by, remarks, tech_c, admin_c',
		'rowid'    => 'role, source, nic_hdl, address'
	),
	'person'       => array(
		'name' => __esc('Persons', 'flowview'),
		'function' => 'view_db_table',
		'filter'   => '',
		'columns'  => 'person, nic_hdl, source, mnt_by, created, last_modified',
		'search'   => 'person, nic_hdl, mnt_by, remarks, e_mail, address',
		'rowid'    => 'person, source, nic_hdl'
	)
);

//		'rtr_set',
//		'rtr_set' => array(
//			'display' => __esc('', 'flowview'),
//			'align'   => 'left',
//			'order'   => 'ASC'
//		),

