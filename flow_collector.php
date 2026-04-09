#!/usr/bin/env php
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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

ini_set('output_buffering', 'Off');
ini_set('max_runtime', '-1');
ini_set('memory_limit', '-1');

set_time_limit(0);
ob_implicit_flush();

chdir(__DIR__ . '/../../');
include('./include/cli_check.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
chdir(__DIR__);

ini_set('max_execution_time', '-1');

flowview_connect();

/* include arrays after flowview_connect() */
include_once($config['base_path'] . '/plugins/flowview/arrays.php');

$debug     = false;
$force     = false;
$reload    = true;
$taskname  = '';

$shortopts = 'VvHh';
$longopts = [
	'listener-id::',
	'debug',
	'force',
	'version',
	'help',
];

$options = getopt($shortopts, $longopts);

foreach($options as $arg => $value) {
	switch($arg) {
		case 'listener-id':
			if ($value > 0) {
				$listener_id = $value;
			} else {
				print "FATAL: Option 'listener-id' is not numeric" . PHP_EOL;
				exit(1);
			}

			break;
		case 'debug':
			$debug = true;

			break;
		case 'force':
			$force = true;

			break;
		case 'version':
			display_version();

			break;
		case 'help':
			display_help();

			break;
	}
}

if (empty($listener_id)) {
	print "FATAL: You must provide a --listener-id" . PHP_EOL;
	exit(1);
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
	pcntl_signal(SIGHUP, 'sig_handler');
}

$templates  = [];
$tlengths   = [];
$tsupported = [];
$start     = 0;

$pacmap = [
	'unsigned8'            => 'C',
	'unsigned16'           => 'n',
	'unsigned32'           => 'N',
	'unsigned64'           => 'J',
	'float64'              => '',
	'boolean'              => '',
	'ipv4Address'          => 'C4',
	'ipv6Address'          => 'n8',
	'octetArray'           => '',
	'string'               => '',
	'macAddress'           => 'C6',
	'dateTimeSeconds'      => '',
	'dateTimeMilliseconds' => 'J',
	'dateTimeMicroseconds' => '',
	'dateTimeNanoseconds'  => '',
	'basicList'            => '',
	'subTemplateList'      => '',
	'subTemplateMultiList' => '',
];

/**
 * The specification for allfields can be found here:
 * - https://www.iana.org/assignments/ipfix/ipfix.xhtml
 *
 * Slight alterations for V9 can be extracted from the Cisco specification here:
 * - https://www.cisco.com/en/US/technologies/tk648/tk362/technologies_white_paper09186a00800a3db9.html
 *
 * Important Note:
 * --------------------------------------------------------------------------------------------------
 * Though the specification says fields like octetDeltaCount are 64bit, it can actually be
 * either 64bit or 32bit depending on the hardware vendors choice with V9.
 * So, as a corrective action, for all numeric types besides float64, we will so a custom mapping
 * when using V9.  See the function get_unpack_syntax() for more details.
 */
$allfields = array(
	1   => ['name' => 'octetDeltaCount',                       'pack' => 'unsigned64'],
	2   => ['name' => 'packetDeltaCount',                      'pack' => 'unsigned64'],
	3   => ['name' => 'deltaFlowCount',                        'pack' => 'unsigned64'],
	4   => ['name' => 'protocolIdentifier',                    'pack' => 'unsigned8'],
	5   => ['name' => 'ipClassOfService',                      'pack' => 'unsigned8'],
	6   => ['name' => 'tcpControlBits',                        'pack' => 'unsigned16'],
	7   => ['name' => 'sourceTransportPort',                   'pack' => 'unsigned16'],
	8   => ['name' => 'sourceIPv4Address',                     'pack' => 'ipv4Address'],
	9   => ['name' => 'sourceIPv4PrefixLength',                'pack' => 'unsigned8'],
	10  => ['name' => 'ingressInterface',                      'pack' => 'unsigned32'],
	11  => ['name' => 'destinationTransportPort',              'pack' => 'unsigned16'],
	12  => ['name' => 'destinationIPv4Address',                'pack' => 'ipv4Address'],
	13  => ['name' => 'destinationIPv4PrefixLength',           'pack' => 'unsigned8'],
	14  => ['name' => 'egressInterface',                       'pack' => 'unsigned32'],
	15  => ['name' => 'ipNextHopIPv4Address',                  'pack' => 'ipv4Address'],
	16  => ['name' => 'bgpSourceAsNumber',                     'pack' => 'unsigned32'],
	17  => ['name' => 'bgpDestinationAsNumber',                'pack' => 'unsigned32'],
	18  => ['name' => 'bgpNexthopIPv4Address',                 'pack' => 'ipv4Address'],
	19  => ['name' => 'postMCastPacketDeltaCount',             'pack' => 'unsigned64'],
	20  => ['name' => 'postMCastOctetDeltaCount',              'pack' => 'unsigned64'],
	21  => ['name' => 'flowEndSysUpTime',                      'pack' => 'unsigned32'],
	22  => ['name' => 'flowStartSysUpTime',                    'pack' => 'unsigned32'],
	23  => ['name' => 'postOctetDeltaCount',                   'pack' => 'unsigned64'],
	24  => ['name' => 'postPacketDeltaCount',                  'pack' => 'unsigned64'],
	25  => ['name' => 'minimumIpTotalLength',                  'pack' => 'unsigned64'],
	26  => ['name' => 'maximumIpTotalLength',                  'pack' => 'unsigned64'],
	27  => ['name' => 'sourceIPv6Address',                     'pack' => 'ipv6Address'],
	28  => ['name' => 'destinationIPv6Address',                'pack' => 'ipv6Address'],
	29  => ['name' => 'sourceIPv6PrefixLength',                'pack' => 'unsigned8'],
	30  => ['name' => 'destinationIPv6PrefixLength',           'pack' => 'unsigned8'],
	31  => ['name' => 'flowLabelIPv6',                         'pack' => 'unsigned32'],
	32  => ['name' => 'icmpTypeCodeIPv4',                      'pack' => 'unsigned16'],
	33  => ['name' => 'igmpType',                              'pack' => 'unsigned8'],
	34  => ['name' => 'samplingInterval',                      'pack' => 'unsigned32'],
	35  => ['name' => 'samplingAlgorithm',                     'pack' => 'unsigned8'],
	36  => ['name' => 'flowActiveTimeout',                     'pack' => 'unsigned16'],
	37  => ['name' => 'flowIdleTimeout',                       'pack' => 'unsigned16'],
	38  => ['name' => 'engineType',                            'pack' => 'unsigned8'],
	39  => ['name' => 'engineId',                              'pack' => 'unsigned8'],
	40  => ['name' => 'exportedOctetTotalCount',               'pack' => 'unsigned64'],
	41  => ['name' => 'exportedMessageTotalCount',             'pack' => 'unsigned64'],
	42  => ['name' => 'exportedFlowRecordTotalCount',          'pack' => 'unsigned64'],
	43  => ['name' => 'ipv4RouterSc',                          'pack' => 'ipv4Address'],
	44  => ['name' => 'sourceIPv4Prefix',                      'pack' => 'ipv4Address'],
	45  => ['name' => 'destinationIPv4Prefix',                 'pack' => 'ipv4Address'],
	46  => ['name' => 'mplsTopLabelType',                      'pack' => 'unsigned8'],
	47  => ['name' => 'mplsTopLabelIPv4Address',               'pack' => 'ipv4Address'],
	48  => ['name' => 'samplerId',                             'pack' => 'unsigned8'],
	49  => ['name' => 'samplerMode',                           'pack' => 'unsigned8'],
	50  => ['name' => 'samplerRandomInterval',                 'pack' => 'unsigned32'],
	51  => ['name' => 'classId',                               'pack' => 'unsigned8'],
	52  => ['name' => 'minimumTTL',                            'pack' => 'unsigned8'],
	53  => ['name' => 'maximumTTL',                            'pack' => 'unsigned8'],
	54  => ['name' => 'fragmentIdentification',                'pack' => 'unsigned32'],
	55  => ['name' => 'postIpClassOfService',                  'pack' => 'unsigned8'],
	56  => ['name' => 'sourceMacAddress',                      'pack' => 'macAddress'],
	57  => ['name' => 'postDestinationMacAddress',             'pack' => 'macAddress'],
	58  => ['name' => 'vlanId',                                'pack' => 'unsigned16'],
	59  => ['name' => 'postVlanId',                            'pack' => 'unsigned16'],
	60  => ['name' => 'ipVersion',                             'pack' => 'unsigned8'],
	61  => ['name' => 'flowDirection',                         'pack' => 'unsigned8'],
	62  => ['name' => 'ipNextHopIPv6Address',                  'pack' => 'ipv6Address'],
	63  => ['name' => 'bgpNexthopIPv6Address',                 'pack' => 'ipv6Address'],
	64  => ['name' => 'ipv6ExtensionHeaders',                  'pack' => 'unsigned32'],

	65  => ['name' => 'vendorReserved',                        'pack' => 'string'],
	66  => ['name' => 'vendorReserved',                        'pack' => 'string'],
	67  => ['name' => 'vendorReserved',                        'pack' => 'string'],
	68  => ['name' => 'vendorReserved',                        'pack' => 'string'],
	69  => ['name' => 'vendorReserved',                        'pack' => 'string'],

	70  => ['name' => 'mplsTopLabelStackSection',              'pack' => 'octetArray'],
	71  => ['name' => 'mplsLabelStackSection2',                'pack' => 'octetArray'],
	72  => ['name' => 'mplsLabelStackSection3',                'pack' => 'octetArray'],
	73  => ['name' => 'mplsLabelStackSection4',                'pack' => 'octetArray'],
	74  => ['name' => 'mplsLabelStackSection5',                'pack' => 'octetArray'],
	75  => ['name' => 'mplsLabelStackSection6',                'pack' => 'octetArray'],
	76  => ['name' => 'mplsLabelStackSection7',                'pack' => 'octetArray'],
	77  => ['name' => 'mplsLabelStackSection8',                'pack' => 'octetArray'],
	78  => ['name' => 'mplsLabelStackSection9',                'pack' => 'octetArray'],
	79  => ['name' => 'mplsLabelStackSection10',               'pack' => 'octetArray'],
	80  => ['name' => 'destinationMacAddress',                 'pack' => 'macAddress'],
	81  => ['name' => 'postSourceMacAddress',                  'pack' => 'macAddress'],
	82  => ['name' => 'interfaceName',                         'pack' => 'string'],
	83  => ['name' => 'interfaceDescription',                  'pack' => 'string'],
	84  => ['name' => 'samplerName',                           'pack' => 'string'],
	85  => ['name' => 'octetTotalCount',                       'pack' => 'unsigned64'],
	86  => ['name' => 'packetTotalCount',                      'pack' => 'unsigned64'],
	87  => ['name' => 'flagsAndSamplerId',                     'pack' => 'unsigned32'],
	88  => ['name' => 'fragmentOffset',                        'pack' => 'unsigned16'],
	89  => ['name' => 'forwardingStatus',                      'pack' => 'unsigned8'],
	90  => ['name' => 'mplsVpnRouteDistinguisher',             'pack' => 'octetArray'],
	91  => ['name' => 'mplsTopLabelPrefixLength',              'pack' => 'unsigned8'],
	92  => ['name' => 'srcTrafficIndex',                       'pack' => 'unsigned32'],
	93  => ['name' => 'dstTrafficIndex',                       'pack' => 'unsigned32'],
	94  => ['name' => 'applicationDescription',                'pack' => 'string'],
	95  => ['name' => 'applicationId',                         'pack' => 'octetArray'],
	96  => ['name' => 'applicationName',                       'pack' => 'unsigned8'],
	97  => ['name' => 'Assigned For V9 Compatibility',         'pack' => ''],
	98  => ['name' => 'postIpDiffServCodePoint',               'pack' => 'unsigned8'],
	99  => ['name' => 'multicastReplicationFactor',            'pack' => 'unsigned32'],
	100 => ['name' => 'className',                             'pack' => 'string'],
	101 => ['name' => 'classificationEngineId',                'pack' => 'unsigned8'],
	102 => ['name' => 'layer2packetSectionOffset',             'pack' => 'unsigned16'],
	103 => ['name' => 'layer2packetSectionSize',               'pack' => 'unsigned16'],
	104 => ['name' => 'layer2packetSectionData',               'pack' => 'octetArray'],

	128 => ['name' => 'bgpNextAdjacentAsNumber',               'pack' => 'unsigned32'],
	129 => ['name' => 'bgpPrevAdjacentAsNumber',               'pack' => 'unsigned32'],
	130 => ['name' => 'exporterIPv4Address',                   'pack' => 'ipv4Address'],
	131 => ['name' => 'exporterIPv6Address',                   'pack' => 'ipv6Address'],
	132 => ['name' => 'droppedOctetDeltaCount',                'pack' => 'unsigne64'],
	133 => ['name' => 'droppedPacketDeltaCount',               'pack' => 'unsigne64'],
	134 => ['name' => 'droppedOctetTotalCount',                'pack' => 'unsigne64'],
	135 => ['name' => 'droppedPacketTotalCount',               'pack' => 'unsigne64'],
	136 => ['name' => 'flowEndReason',                         'pack' => 'unsigned8'],
	137 => ['name' => 'commonPropertiesId',                    'pack' => 'unsigned64'],
	138 => ['name' => 'observationPointId',                    'pack' => 'unsigned64'],
	139 => ['name' => 'icmpTypeCodeIPv6',                      'pack' => 'unsigned16'],
	140 => ['name' => 'mplsTopLabelIPv6Address',               'pack' => 'ipv6Address'],
	141 => ['name' => 'lineCardId',                            'pack' => 'unsigned32'],
	142 => ['name' => 'portId',                                'pack' => 'unsigned32'],
	143 => ['name' => 'meteringProcessId',                     'pack' => 'unsigned32'],
	144 => ['name' => 'exportingProcessId',                    'pack' => 'unsigned32'],
	145 => ['name' => 'templateId',                            'pack' => 'unsigned16'],
	146 => ['name' => 'wlanChannelId',                         'pack' => 'unsigned8'],
	147 => ['name' => 'wlanSSID',                              'pack' => 'string'],
	148 => ['name' => 'flowId',                                'pack' => 'unsigned64'],
	149 => ['name' => 'observationDomainId',                   'pack' => 'unsigned32'],
	150 => ['name' => 'flowStartSeconds',                      'pack' => 'dateTimeSeconds'],
	151 => ['name' => 'flowEndSeconds',                        'pack' => 'dateTimeSeconds'],
	152 => ['name' => 'flowStartMilliseconds',                 'pack' => 'dateTimeMilliseconds'],
	153 => ['name' => 'flowEndMilliseconds',                   'pack' => 'dateTimeMilliseconds'],
	154 => ['name' => 'flowStartMicroseconds',                 'pack' => 'dateTimeMicroseconds'],
	155 => ['name' => 'flowEndMicroseconds',                   'pack' => 'dateTimeMicroseconds'],
	156 => ['name' => 'flowStartNanoseconds',                  'pack' => 'dateTimeNanoSeconds'],
	157 => ['name' => 'flowEndNanoseconds',                    'pack' => 'dateTimeNanoSeconds'],
	158 => ['name' => 'flowStartDeltaMicroseconds',            'pack' => 'unsigned32'],
	159 => ['name' => 'flowEndDeltaMicroseconds',              'pack' => 'unsigned32'],
	160 => ['name' => 'systemInitTimeMilliseconds',            'pack' => 'dateTimeMilliseconds'],
	161 => ['name' => 'flowDurationMilliseconds',              'pack' => 'unsigned32'],
	162 => ['name' => 'flowDurationMicroseconds',              'pack' => 'unsigned32'],
	163 => ['name' => 'observedFlowTotalCount',                'pack' => 'unsigned64'],
	164 => ['name' => 'ignoredPacketTotalCount',               'pack' => 'unsigned64'],
	165 => ['name' => 'ignoredOctetTotalCount',                'pack' => 'unsigned64'],
	166 => ['name' => 'notSentFlowTotalCount',                 'pack' => 'unsigned64'],
	167 => ['name' => 'notSentPacketTotalCount',               'pack' => 'unsigned64'],
	168 => ['name' => 'notSentOctetTotalCount',                'pack' => 'unsigned64'],
	169 => ['name' => 'destinationIPv6Prefix',                 'pack' => 'ipv6Address'],
	170 => ['name' => 'sourceIPv6Prefix',                      'pack' => 'ipv6Address'],
	171 => ['name' => 'postOctetTotalCount',                   'pack' => 'unsigned64'],
	172 => ['name' => 'postPacketTotalCount',                  'pack' => 'unsigned64'],
	173 => ['name' => 'flowKeyIndicator',                      'pack' => 'unsigned64'],
	174 => ['name' => 'postMCastPacketTotalCount',             'pack' => 'unsigned64'],
	175 => ['name' => 'postMCastOctetTotalCount',              'pack' => 'unsigned64'],
	176 => ['name' => 'icmpTypeIPv4',                          'pack' => 'unsigned8'],
	177 => ['name' => 'icmpCodeIPv4',                          'pack' => 'unsigned8'],
	178 => ['name' => 'icmpTypeIPv6',                          'pack' => 'unsigned8'],
	179 => ['name' => 'icmpCodeIPv6',                          'pack' => 'unsigned8'],
	180 => ['name' => 'udpSourcePort',                         'pack' => 'unsigned16'],
	181 => ['name' => 'udpDestinationPort',                    'pack' => 'unsigned16'],
	182 => ['name' => 'tcpSourcePort',                         'pack' => 'unsigned16'],
	183 => ['name' => 'tcpDestinationPort',                    'pack' => 'unsigned16'],
	184 => ['name' => 'tcpSequenceNumber',                     'pack' => 'unsigned32'],
	185 => ['name' => 'tcpAcknowledgementNumber',              'pack' => 'unsigned32'],
	186 => ['name' => 'tcpWindowSize',                         'pack' => 'unsigned16'],
	187 => ['name' => 'tcpUrgentPointer',                      'pack' => 'unsigned16'],
	188 => ['name' => 'tcpHeaderLength',                       'pack' => 'unsigned8'],
	189 => ['name' => 'ipHeaderLength',                        'pack' => 'unsigned8'],
	190 => ['name' => 'totalLengthIPv4',                       'pack' => 'unsigned16'],
	191 => ['name' => 'payloadLengthIPv6',                     'pack' => 'unsigned16'],
	192 => ['name' => 'ipTTL',                                 'pack' => 'unsigned8'],
	193 => ['name' => 'nextHeaderIPv6',                        'pack' => 'unsigned8'],
	194 => ['name' => 'mplsPayloadLength',                     'pack' => 'unsigned32'],
	195 => ['name' => 'ipDiffServCodePoint',                   'pack' => 'unsigned8'],
	196 => ['name' => 'ipPrecedence',                          'pack' => 'unsigned8'],
	197 => ['name' => 'fragmentFlags',                         'pack' => 'unsigned8'],
	198 => ['name' => 'octetDeltaSumOfSquares',                'pack' => 'unsigned64'],
	199 => ['name' => 'octetTotalSumOfSquares',                'pack' => 'unsigned64'],
	200 => ['name' => 'mplsTopLabelTTL',                       'pack' => 'unsigned8'],
	201 => ['name' => 'mplsLabelStackLength',                  'pack' => 'unsigned32'],
	202 => ['name' => 'mplsLabelStackDepth',                   'pack' => 'unsigned32'],
	203 => ['name' => 'mplsTopLabelExp',                       'pack' => 'unsigned8'],
	204 => ['name' => 'ipPayloadLength',                       'pack' => 'unsigned32'],
	205 => ['name' => 'udpMessageLength',                      'pack' => 'unsigned16'],
	206 => ['name' => 'isMulticast',                           'pack' => 'unsigned8'],
	207 => ['name' => 'ipv4IHL',                               'pack' => 'unsigned8'],
	208 => ['name' => 'ipv4Options',                           'pack' => 'unsigned32'],
	209 => ['name' => 'tcpOptions',                            'pack' => 'unsigned64'],
	210 => ['name' => 'paddingOctets',                         'pack' => 'octetArray'],
	211 => ['name' => 'collectorIPv4Address',                  'pack' => 'ipv4Address'],
	212 => ['name' => 'collectorIPv6Address',                  'pack' => 'ipv6Address'],
	213 => ['name' => 'exportInterface',                       'pack' => 'unsigned32'],
	214 => ['name' => 'exportProtocolVersion',                 'pack' => 'unsigned8'],
	215 => ['name' => 'exportTransportProtocol',               'pack' => 'unsigned8'],
	216 => ['name' => 'collectorTransportPort',                'pack' => 'unsigned16'],
	217 => ['name' => 'exporterTransportPort',                 'pack' => 'unsigned16'],
	218 => ['name' => 'tcpSynTotalCount',                      'pack' => 'unsigned64'],
	219 => ['name' => 'tcpFinTotalCount',                      'pack' => 'unsigned64'],
	220 => ['name' => 'tcpRstTotalCount',                      'pack' => 'unsigned64'],
	221 => ['name' => 'tcpPshTotalCount',                      'pack' => 'unsigned64'],
	222 => ['name' => 'tcpAckTotalCount',                      'pack' => 'unsigned64'],
	223 => ['name' => 'tcpUrgTotalCount',                      'pack' => 'unsigned64'],
	224 => ['name' => 'ipTotalLength',                         'pack' => 'unsigned64'],
	225 => ['name' => 'postNATSourceIPv4Address',              'pack' => 'ipv4Address'],
	226 => ['name' => 'postNATDestinationIPv4Address',         'pack' => 'ipv4Address'],
	227 => ['name' => 'postNAPTSourceTransportPort',           'pack' => 'unsigned16'],
	228 => ['name' => 'postNAPTDestinationTransportPort',      'pack' => 'unsigned16'],
	229 => ['name' => 'natOriginatingAddressRealm',            'pack' => 'unsigned8'],
	230 => ['name' => 'natEvent',                              'pack' => 'unsigned8'],
	231 => ['name' => 'initiatorOctets',                       'pack' => 'unsigned64'],
	232 => ['name' => 'responderOctets',                       'pack' => 'unsigned64'],
	233 => ['name' => 'firewallEvent',                         'pack' => 'unsigned8'],
	234 => ['name' => 'ingressVRFID',                          'pack' => 'unsigned32'],
	235 => ['name' => 'egressVRFID',                           'pack' => 'unsigned32'],
	236 => ['name' => 'VRFname',                               'pack' => 'string'],
	237 => ['name' => 'postMplsTopLabelExp',                   'pack' => 'unsigned8'],
	238 => ['name' => 'tcpWindowScale',                        'pack' => 'unsigned16'],
	239 => ['name' => 'biflowDirection',                       'pack' => 'unsigned8'],
	240 => ['name' => 'ethernetHeaderLength',                  'pack' => 'unsigned8'],
	241 => ['name' => 'ethernetPayloadLength',                 'pack' => 'unsigned16'],
	242 => ['name' => 'ethernetTotalLength',                   'pack' => 'unsigned16'],
	243 => ['name' => 'dot1qVlanId',                           'pack' => 'unsigned16'],
	244 => ['name' => 'dot1qPriority',                         'pack' => 'unsigned8'],
	245 => ['name' => 'dot1qCustomerVlanId',                   'pack' => 'unsigned16'],
	246 => ['name' => 'dot1qCustomerPriority',                 'pack' => 'unsigned8'],
	247 => ['name' => 'metroEvcId',                            'pack' => 'string'],
	248 => ['name' => 'metroEvcType',                          'pack' => 'unsigned8'],
	249 => ['name' => 'pseudoWireId',                          'pack' => 'unsigned32'],
	250 => ['name' => 'pseudoWireType',                        'pack' => 'unsigned16'],
	251 => ['name' => 'pseudoWireControlWord',                 'pack' => 'unsigned32'],
	252 => ['name' => 'ingressPhysicalInterface',              'pack' => 'unsigned32'],
	253 => ['name' => 'egressPhysicalInterface',               'pack' => 'unsigned32'],
	254 => ['name' => 'postDot1qVlanId',                       'pack' => 'unsigned16'],
	255 => ['name' => 'postDot1qCustomerVlanId',               'pack' => 'unsigned16'],
	256 => ['name' => 'ethernetType',                          'pack' => 'unsigned16'],
	257 => ['name' => 'postIpPrecedence',                      'pack' => 'unsigned8'],
	258 => ['name' => 'collectionTimeMilliseconds',            'pack' => 'dateTimeMilliseconds'],
	259 => ['name' => 'exportSctpStreamId',                    'pack' => 'unsigned16'],
	260 => ['name' => 'maxExportSeconds',                      'pack' => 'dateTimeSeconds'],
	261 => ['name' => 'maxFlowEndSeconds',                     'pack' => 'dateTimeSeconds'],
	262 => ['name' => 'messageMD5Checksum',                    'pack' => 'octetArray'],
	263 => ['name' => 'messageScope',                          'pack' => 'unsigned8'],
	264 => ['name' => 'minExportSeconds',                      'pack' => 'dateTimeSeconds'],
	265 => ['name' => 'minFlowStartSeconds',                   'pack' => 'dateTimeSeconds'],
	266 => ['name' => 'opaqueOctets',                          'pack' => 'octetArray'],
	267 => ['name' => 'sessionScope',                          'pack' => 'unsigned8'],
	268 => ['name' => 'maxFlowEndMicroseconds',                'pack' => 'dateTimeMicroseconds'],
	269 => ['name' => 'maxFlowEndMilliseconds',                'pack' => 'dateTimeMilliseconds'],
	270 => ['name' => 'maxFlowEndNanoseconds',                 'pack' => 'dateTimeNanoseconds'],
	271 => ['name' => 'minFlowStartMicroseconds',              'pack' => 'dateTimeMicroseconds'],
	272 => ['name' => 'minFlowStartMilliseconds',              'pack' => 'dateTimeMilliseconds'],
	273 => ['name' => 'minFlowStartNanoseconds',               'pack' => 'dateTimeNanoseconds'],
	274 => ['name' => 'collectorCertificate',                  'pack' => 'octetArray'],
	275 => ['name' => 'exporterCertificate',                   'pack' => 'octetArray'],
	276 => ['name' => 'dataRecordsReliability',                'pack' => 'boolean'],
	277 => ['name' => 'observationPointType',                  'pack' => 'unsigned8'],
	278 => ['name' => 'newConnectionDeltaCount',               'pack' => 'unsigned32'],
	279 => ['name' => 'connectionSumDurationSeconds',          'pack' => 'unsigned64'],
	280 => ['name' => 'connectionTransactionId',               'pack' => 'unsigned64'],
	281 => ['name' => 'postNATSourceIPv6Address',              'pack' => 'ipv6Address'],
	282 => ['name' => 'postNATDestinationIPv6Address',         'pack' => 'ipv6Address'],
	283 => ['name' => 'natPoolId',                             'pack' => 'unsigned32'],
	284 => ['name' => 'natPoolName',                           'pack' => 'string'],
	285 => ['name' => 'anonymizationFlags',                    'pack' => 'unsigned16'],
	286 => ['name' => 'anonymizationTechnique',                'pack' => 'unsigned16'],
	287 => ['name' => 'informationElementIndex',               'pack' => 'unsigned16'],
	288 => ['name' => 'p2pTechnology',                         'pack' => 'string'],
	289 => ['name' => 'tunnelTechnology',                      'pack' => 'string'],
	290 => ['name' => 'encryptedTechnology',                   'pack' => 'string'],
	291 => ['name' => 'basicList',                             'pack' => 'basicList'],
	292 => ['name' => 'subTemplateList',                       'pack' => 'subTemplateList'],
	293 => ['name' => 'subTemplateMultiList',                  'pack' => 'subTemplateMultiList'],
	294 => ['name' => 'bgpValidityState',                      'pack' => 'unsigned8'],
	295 => ['name' => 'IPSecSPI',                              'pack' => 'unsigned32'],
	296 => ['name' => 'greKey',                                'pack' => 'unsigned32'],
	297 => ['name' => 'natType',                               'pack' => 'unsigned8'],
	298 => ['name' => 'initiatorPackets',                      'pack' => 'unsigned64'],
	299 => ['name' => 'responderPackets',                      'pack' => 'unsigned64'],
	300 => ['name' => 'observationDomainName',                 'pack' => 'string'],
	301 => ['name' => 'selectionSequenceId',                   'pack' => 'unsigned64'],
	302 => ['name' => 'selectorId',                            'pack' => 'unsigned64'],
	303 => ['name' => 'informationElementId',                  'pack' => 'unsigned16'],
	304 => ['name' => 'selectorAlgorithm',                     'pack' => 'unsigned16'],
	305 => ['name' => 'samplingPacketInterval',                'pack' => 'unsigned32'],
	306 => ['name' => 'samplingPacketSpace',                   'pack' => 'unsigned32'],
	307 => ['name' => 'samplingTimeInterval',                  'pack' => 'unsigned32'],
	308 => ['name' => 'samplingTimeSpace',                     'pack' => 'unsigned32'],
	309 => ['name' => 'samplingSize',                          'pack' => 'unsigned32'],
	310 => ['name' => 'samplingPopulation',                    'pack' => 'unsigned32'],
	311 => ['name' => 'samplingProbability',                   'pack' => 'float64'],
	312 => ['name' => 'dataLinkFrameSize',                     'pack' => 'unsigned16'],
	313 => ['name' => 'ipHeaderPacketSection',                 'pack' => 'octetArray'],
	314 => ['name' => 'ipPayloadPacketSection',                'pack' => 'octetArray'],
	315 => ['name' => 'dataLinkFrameSection',                  'pack' => 'octetArray'],
	316 => ['name' => 'mplsLabelStackSection',                 'pack' => 'octetArray'],
	317 => ['name' => 'mplsPayloadPacketSection',              'pack' => 'octetArray'],
	318 => ['name' => 'selectorIdTotalPktsObserved',           'pack' => 'unsigned64'],
	319 => ['name' => 'selectorIdTotalPktsSelected',           'pack' => 'unsigned64'],
	320 => ['name' => 'absoluteError',                         'pack' => 'float64'],
	321 => ['name' => 'relativeError',                         'pack' => 'float64'],
	322 => ['name' => 'observationTimeSeconds',                'pack' => 'dateTimeSeconds'],
	323 => ['name' => 'observationTimeMilliseconds',           'pack' => 'dateTimeMilliseconds'],
	324 => ['name' => 'observationTimeMicroseconds',           'pack' => 'dateTimeMicroseconds'],
	325 => ['name' => 'observationTimeNanoseconds',            'pack' => 'dateTimeNanoseconds'],
	326 => ['name' => 'digestHashValue',                       'pack' => 'unsigned64'],
	327 => ['name' => 'hashIPPayloadOffset',                   'pack' => 'unsigned64'],
	328 => ['name' => 'hashIPPayloadSize',                     'pack' => 'unsigned64'],
	329 => ['name' => 'hashOutputRangeMin',                    'pack' => 'unsigned64'],
	330 => ['name' => 'hashOutputRangeMax',                    'pack' => 'unsigned64'],
	331 => ['name' => 'hashSelectedRangeMin',                  'pack' => 'unsigned64'],
	332 => ['name' => 'hashSelectedRangeMax',                  'pack' => 'unsigned64'],
	333 => ['name' => 'hashDigestOutput',                      'pack' => 'boolean'],
	334 => ['name' => 'hashInitialiserValue',                  'pack' => 'unsigned64'],
	335 => ['name' => 'selectorName',                          'pack' => 'string'],
	336 => ['name' => 'upperCILimit',                          'pack' => 'float64'],
	337 => ['name' => 'lowerCILimit',                          'pack' => 'float64'],
	338 => ['name' => 'confidenceLevel',                       'pack' => 'float64'],
	339 => ['name' => 'informationElementDataType',            'pack' => 'unsigned8'],
	340 => ['name' => 'informationElementDescription',         'pack' => 'string'],
	341 => ['name' => 'informationElementName',                'pack' => 'string'],
	342 => ['name' => 'informationElementRangeBegin',          'pack' => 'unsigned64'],
	343 => ['name' => 'informationElementRangeEnd',            'pack' => 'unsigned64'],
	344 => ['name' => 'informationElementSemantics',           'pack' => 'unsigned8'],
	345 => ['name' => 'informationElementUnits',               'pack' => 'unsigned16'],
	346 => ['name' => 'privateEnterpriseNumber',               'pack' => 'unsigned32'],
	347 => ['name' => 'virtualStationInterfaceId',             'pack' => 'octetArray'],
	348 => ['name' => 'virtualStationInterfaceName',           'pack' => 'string'],
	349 => ['name' => 'virtualStationUUID',                    'pack' => 'octetArray'],
	350 => ['name' => 'virtualStationName',                    'pack' => 'string'],
	351 => ['name' => 'layer2SegmentId',                       'pack' => 'unsigned64'],
	352 => ['name' => 'layer2OctetDeltaCount',                 'pack' => 'unsigned64'],
	353 => ['name' => 'layer2OctetTotalCount',                 'pack' => 'unsigned64'],
	354 => ['name' => 'ingressUnicastPacketTotalCount',        'pack' => 'unsigned64'],
	355 => ['name' => 'ingressMulticastPacketTotalCount',      'pack' => 'unsigned64'],
	356 => ['name' => 'ingressBroadcastPacketTotalCount',      'pack' => 'unsigned64'],
	357 => ['name' => 'egressUnicastPacketTotalCount',         'pack' => 'unsigned64'],
	358 => ['name' => 'egressBroadcastPacketTotalCount',       'pack' => 'unsigned64'],
	359 => ['name' => 'monitoringIntervalStartMilliSeconds',   'pack' => 'dateTimeMilliseconds'],
	360 => ['name' => 'monitoringIntervalEndMilliSeconds',     'pack' => 'dateTimeMilliseconds'],
	361 => ['name' => 'portRangeStart',                        'pack' => 'unsigned16'],
	362 => ['name' => 'portRangeEnd',                          'pack' => 'unsigned16'],
	363 => ['name' => 'portRangeStepSize',                     'pack' => 'unsigned16'],
	364 => ['name' => 'portRangeNumPorts',                     'pack' => 'unsigned16'],
	365 => ['name' => 'staMacAddress',                         'pack' => 'macAddress'],
	366 => ['name' => 'staIPv4Address',                        'pack' => 'ipv4Address'],
	367 => ['name' => 'wtpMacAddress',                         'pack' => 'macAddress'],
	368 => ['name' => 'ingressInterfaceType',                  'pack' => 'unsigned32'],
	369 => ['name' => 'egressInterfaceType',                   'pack' => 'unsigned32'],
	370 => ['name' => 'rtpSequenceNumber',                     'pack' => 'unsigned16'],
	371 => ['name' => 'userName',                              'pack' => 'string'],
	372 => ['name' => 'applicationCategoryName',               'pack' => 'string'],
	373 => ['name' => 'applicationSubCategoryName',            'pack' => 'string'],
	374 => ['name' => 'applicationGroupName',                  'pack' => 'string'],
	375 => ['name' => 'originalFlowsPresent',                  'pack' => 'unsigned64'],
	376 => ['name' => 'originalFlowsInitiated',                'pack' => 'unsigned64'],
	377 => ['name' => 'originalFlowsCompleted',                'pack' => 'unsigned64'],
	378 => ['name' => 'distinctCountOfSourceIPAddress',        'pack' => 'unsigned64'],
	379 => ['name' => 'distinctCountOfDestinationIPAddress',   'pack' => 'unsigned64'],
	380 => ['name' => 'distinctCountOfSourceIPv4Address',      'pack' => 'unsigned32'],
	381 => ['name' => 'distinctCountOfDestinationIPv4Address', 'pack' => 'unsigned32'],
	382 => ['name' => 'distinctCountOfSourceIPv6Address',      'pack' => 'unsigned64'],
	383 => ['name' => 'distinctCountOfDestinationIPv6Address', 'pack' => 'unsigned64'],
	384 => ['name' => 'valueDistributionMethod',               'pack' => 'unsigned8'],
	385 => ['name' => 'rfc3550JitterMilliseconds',             'pack' => 'unsigned32'],
	386 => ['name' => 'rfc3550JitterMicroseconds',             'pack' => 'unsigned32'],
	387 => ['name' => 'rfc3550JitterNanoseconds',              'pack' => 'unsigned32'],
	388 => ['name' => 'dot1qDEI',                              'pack' => 'boolean'],
	389 => ['name' => 'dot1qCustomerDEI',                      'pack' => 'boolean'],
	390 => ['name' => 'flowSelectorAlgorithm',                 'pack' => 'unsigned16'],
	391 => ['name' => 'flowSelectedOctetDeltaCount',           'pack' => 'unsigned64'],
	392 => ['name' => 'flowSelectedPacketDeltaCount',          'pack' => 'unsigned64'],
	393 => ['name' => 'flowSelectedFlowDeltaCount',            'pack' => 'unsigned64'],
	394 => ['name' => 'selectorIDTotalFlowsObserved',          'pack' => 'unsigned64'],
	395 => ['name' => 'selectorIDTotalFlowsSelected',          'pack' => 'unsigned64'],
	396 => ['name' => 'samplingFlowInterval',                  'pack' => 'unsigned64'],
	397 => ['name' => 'samplingFlowSpacing',                   'pack' => 'unsigned64'],
	398 => ['name' => 'flowSamplingTimeInterval',              'pack' => 'unsigned64'],
	399 => ['name' => 'flowSamplingTimeSpacing',               'pack' => 'unsigned64'],
	400 => ['name' => 'hashFlowDomain',                        'pack' => 'unsigned16'],
	401 => ['name' => 'transportOctetDeltaCount',              'pack' => 'unsigned64'],
	402 => ['name' => 'transportPacketDeltaCount',             'pack' => 'unsigned64'],
	403 => ['name' => 'originalExporterIPv4Address',           'pack' => 'ipv4Address'],
	404 => ['name' => 'originalExporterIPv6Address',           'pack' => 'ipv6Address'],
	405 => ['name' => 'originalObservationDomainId',           'pack' => 'unsigned32'],
	406 => ['name' => 'intermediateProcessId',                 'pack' => 'unsigned32'],
	407 => ['name' => 'ignoredDataRecordTotalCount',           'pack' => 'unsigned64'],
	408 => ['name' => 'dataLinkFrameType',                     'pack' => 'unsigned16'],
	409 => ['name' => 'sectionOffset',                         'pack' => 'unsigned16'],
	410 => ['name' => 'sectionExportedOctets',                 'pack' => 'unsigned16'],
	411 => ['name' => 'dot1qServiceInstanceTag',               'pack' => 'octetArray'],
	412 => ['name' => 'dot1qServiceInstanceId',                'pack' => 'unsigned32'],
	413 => ['name' => 'dot1qServiceInstancePriority',          'pack' => 'unsigned8'],
	414 => ['name' => 'dot1qCustomerSourceMacAddress',         'pack' => 'macAddress'],
	415 => ['name' => 'dot1qCustomerDestinationMacAddress',    'pack' => 'macAddress'],
	416 => ['name' => 'deprecated',                            'pack' => 'unsigned64'],
	417 => ['name' => 'postLayer2OctetDeltaCount',             'pack' => 'unsigned64'],
	418 => ['name' => 'postMCastLayer2OctetDeltaCount',        'pack' => 'unsigned64'],
	419 => ['name' => 'deprecated',                            'pack' => 'unsigned64'],
	420 => ['name' => 'postLayer2OctetTotalCount',             'pack' => 'unsigned64'],
	421 => ['name' => 'postMCastLayer2OctetTotalCount',        'pack' => 'unsigned64'],
	422 => ['name' => 'minimumLayer2TotalLength',              'pack' => 'unsigned64'],
	423 => ['name' => 'maximumLayer2TotalLength',              'pack' => 'unsigned64'],
	424 => ['name' => 'droppedLayer2OctetDeltaCount',          'pack' => 'unsigned64'],
	425 => ['name' => 'droppedLayer2OctetTotalCount',          'pack' => 'unsigned64'],
	426 => ['name' => 'ignoredLayer2OctetTotalCount',          'pack' => 'unsigned64'],
	427 => ['name' => 'notSentLayer2OctetTotalCount',          'pack' => 'unsigned64'],
	428 => ['name' => 'layer2OctetDeltaSumOfSquares',          'pack' => 'unsigned64'],
	429 => ['name' => 'layer2OctetTotalSumOfSquares',          'pack' => 'unsigned64'],
	430 => ['name' => 'layer2FrameDeltaCount',                 'pack' => 'unsigned64'],
	431 => ['name' => 'layer2FrameTotalCount',                 'pack' => 'unsigned64'],
	432 => ['name' => 'pseudoWireDestinationIPv4Address',      'pack' => 'ipv4Address'],
	433 => ['name' => 'ignoredLayer2FrameTotalCount',          'pack' => 'unsigned64'],
	434 => ['name' => 'mibObjectValueInteger',                 'pack' => 'signed32'],
	435 => ['name' => 'mibObjectValueOctetString',             'pack' => 'octetArray'],
	436 => ['name' => 'mibObjectValueOID',                     'pack' => 'octetArray'],
	437 => ['name' => 'mibObjectValueBits',                    'pack' => 'octetArray'],
	438 => ['name' => 'mibObjectValueIPAddress',               'pack' => 'ipv4Address'],
	439 => ['name' => 'mibObjectValueCounter',                 'pack' => 'unsigned64'],
	440 => ['name' => 'mibObjectValueGauge',                   'pack' => 'unsigned32'],
	441 => ['name' => 'mibObjectValueTimeTicks',               'pack' => 'unsigned32'],
	442 => ['name' => 'mibObjectValueUnsigned',                'pack' => 'unsigned32'],
	443 => ['name' => 'mibObjectValueTable',                   'pack' => 'subTemplateList'],
	444 => ['name' => 'mibObjectValueRow',                     'pack' => 'subTemplateList'],
	445 => ['name' => 'mibObjectIdentifier',                   'pack' => 'octetArray'],
	446 => ['name' => 'mibSubIdentifier',                      'pack' => 'unsigned32'],
	447 => ['name' => 'mibIndexIndicator',                     'pack' => 'unsigned64'],
	448 => ['name' => 'mibCaptureTimeSemantics',               'pack' => 'unsigned8'],
	449 => ['name' => 'mibContextEngineID',                    'pack' => 'octetArray'],
	450 => ['name' => 'mibContextName',                        'pack' => 'string'],
	451 => ['name' => 'mibObjectName',                         'pack' => 'string'],
	452 => ['name' => 'mibObjectDescription',                  'pack' => 'string'],
	453 => ['name' => 'mibObjectSyntax',                       'pack' => 'string'],
	454 => ['name' => 'mibModuleName',                         'pack' => 'string'],
	455 => ['name' => 'mobileIMSI',                            'pack' => 'string'],
	456 => ['name' => 'mobileMSISDN',                          'pack' => 'string'],
	457 => ['name' => 'httpStatusCode',                        'pack' => 'unsigned16'],
	458 => ['name' => 'sourceTransportPortsLimit',             'pack' => 'unsigned16'],
	459 => ['name' => 'httpRequestMethod',                     'pack' => 'string'],
	460 => ['name' => 'httpRequestHost',                       'pack' => 'string'],
	461 => ['name' => 'httpRequestTarget',                     'pack' => 'string'],
	462 => ['name' => 'httpMessageVersion',                    'pack' => 'string'],
	463 => ['name' => 'natInstanceID',                         'pack' => 'unsigned32'],
	464 => ['name' => 'internalAddressRealm',                  'pack' => 'octetArray'],
	465 => ['name' => 'externalAddressRealm',                  'pack' => 'octetArray'],
	466 => ['name' => 'natQuotaExceededEvent',                 'pack' => 'unsigned32'],
	467 => ['name' => 'natThresholdEvent',                     'pack' => 'unsigned32'],
	468 => ['name' => 'httpUserAgent',                         'pack' => 'string'],
	469 => ['name' => 'httpContentType',                       'pack' => 'string'],
	470 => ['name' => 'httpReasonPhrase',                      'pack' => 'string'],
	471 => ['name' => 'maxSessionEntries',                     'pack' => 'unsigned32'],
	472 => ['name' => 'maxBIBEntries',                         'pack' => 'unsigned32'],
	473 => ['name' => 'maxEntriesPerUser',                     'pack' => 'unsigned32'],
	474 => ['name' => 'maxSubscribers',                        'pack' => 'unsigned32'],
	475 => ['name' => 'maxFragmentsPendingReassembly',         'pack' => 'unsigned32'],
	476 => ['name' => 'addressPoolHighThreshold',              'pack' => 'unsigned32'],
	477 => ['name' => 'addressPoolLowThreshold',               'pack' => 'unsigned32'],
	478 => ['name' => 'addressPortMappingHighThreshold',       'pack' => 'unsigned32'],
	479 => ['name' => 'addressPortMappingLowThreshold',        'pack' => 'unsigned32'],
	480 => ['name' => 'addressPortMappingPerUserHighThreshold','pack' => 'unsigned32'],
	481 => ['name' => 'globalAddressMappingHighThreshold',     'pack' => 'unsigned32'],
	482 => ['name' => 'vpnIdentifier',                         'pack' => 'octetArray'],
	483 => ['name' => 'bgpCommunity',                          'pack' => 'unsigned32'],
	484 => ['name' => 'bgpSourceCommunityList',                'pack' => 'basicList'],
	485 => ['name' => 'bgpDestinationCommunityList',           'pack' => 'basicList'],
	486 => ['name' => 'bgpExtendedCommunity',                  'pack' => 'octetArray'],
	487 => ['name' => 'bgpSourceExtendedCommunityList',        'pack' => 'basicList'],
	488 => ['name' => 'bgpDestinationExtendedCommunityList',   'pack' => 'basicList'],
	489 => ['name' => 'bgpLargeCommunity',                     'pack' => 'octetArray'],
	490 => ['name' => 'bgpSourceLargeCommunityList',           'pack' => 'basicList'],
	491 => ['name' => 'bgpDestinationLargeCommunityList',      'pack' => 'basicList'],
	492 => ['name' => 'srhFlagsIPv6',                          'pack' => 'unsigned8'],
	493 => ['name' => 'srhTagIPv6',                            'pack' => 'unsigned16'],
	494 => ['name' => 'srhSegmentIPv6',                        'pack' => 'ipv6Address'],
	495 => ['name' => 'srhActiveSegmentIPv6',                  'pack' => 'ipv6Address'],
	496 => ['name' => 'srhSegmentIPv6BasicList',               'pack' => 'basicList'],
	497 => ['name' => 'srhSegmentIPv6ListSection',             'pack' => 'octetArray'],
	498 => ['name' => 'srhSegmentsIPv6Left',                   'pack' => 'unsigned8'],
	499 => ['name' => 'srhIPv6Section',                        'pack' => 'octetArray'],
	500 => ['name' => 'srhIPv6ActiveSegmentType',              'pack' => 'unsigned8'],
	501 => ['name' => 'srhSegmentIPv6LocatorLength',           'pack' => 'unsigned8'],
	502 => ['name' => 'srhSegmentIPv6EndpointBehavior',        'pack' => 'unsigned16'],
	503 => ['name' => 'transportChecksum',                     'pack' => 'unsigned16'],
	504 => ['name' => 'icmpHeaderPacketSection',               'pack' => 'octetArray'],
	505 => ['name' => 'gtpuFlags',                             'pack' => 'unsigned8'],
	506 => ['name' => 'gtpuMsgType',                           'pack' => 'unsigned8'],
	507 => ['name' => 'gtpuTEid',                              'pack' => 'unsigned32'],
	508 => ['name' => 'gtpuSequenceNum',                       'pack' => 'unsigned16'],
	509 => ['name' => 'gtpuQFI',                               'pack' => 'unsigned8'],
	510 => ['name' => 'gtpuPduType',                           'pack' => 'unsigned8'],
	511 => ['name' => 'bgpSourceAsPathList',                   'pack' => 'basicList'],
	512 => ['name' => 'bgpDestinationAsPathList',              'pack' => 'basicList'],
);

$partition = read_config_option('flowview_partition');

$listener  = flowview_db_fetch_row_prepared('SELECT *
	FROM plugin_flowview_devices
	WHERE id = ?',
	[$listener_id]);

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_device_streams` (
	device_id int(11) unsigned NOT NULL default '0',
	ex_addr varchar(46) NOT NULL default '',
	name varchar(64) NOT NULL default '',
	version varchar(5) NOT NULL default '',
	last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (device_id, ex_addr))
	ENGINE=InnoDB,
	ROW_FORMAT=DYNAMIC,
	COMMENT='Plugin Flowview - List of Streams coming into each of the listeners'");

flowview_db_execute('DELETE FROM plugin_flowview_device_streams WHERE ex_addr LIKE "%:%"');

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_device_templates` (
	device_id int(11) unsigned NOT NULL default '0',
	ex_addr varchar(46) NOT NULL default '',
	template_id int(11) unsigned NOT NULL default '0',
	column_spec blob default '',
	last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (device_id, ex_addr, template_id))
	ENGINE=InnoDB,
	ROW_FORMAT=DYNAMIC,
	COMMENT='Plugin Flowview - List of Stream Templates coming into each of the listeners'");

flowview_db_execute('DELETE FROM plugin_flowview_device_templates WHERE ex_addr LIKE "%:%"');

if (cacti_sizeof($listener)) {
	/**
	 * Register the master process
	 */
	$taskname = 'child_' . $listener['id'];

	if (!$force && !register_process_start('flowview', $taskname, $config['poller_id'], 315360000)) {
		debug("FATAL: Process already running.  Shutting down");
		exit(0);
	}

	cacti_log("WARNING: Flowview Listener child_$listener_id is starting up", false, 'FLOWVIEW');

	$previous_version    = -1;
	$refresh_seconds     = 300;
	$tmpl_refreshed      = []; // We update the templates every $refresh_seconds per peer

	flowview_db_execute_prepared("UPDATE `plugin_flowview_devices`
		SET last_updated = NOW()
		WHERE id = ?",
		[$listener['id']]);

	while (true) {
		if ($reload) {
			$listener  = flowview_db_fetch_row_prepared('SELECT *
				FROM plugin_flowview_devices
				WHERE id = ?',
				[$listener_id]);

			$reload = false;
		}

		$protocol = strtolower($listener['protocol']);

		/**
		 * This was for legacy stream_socket_server() which is lacking quite
		 * a bit of functionality.  So, we will use the native socket
		 * calls for now.
		 */

		if ($protocol == 'udp') {
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		} else {
			$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_set_nonblock($socket);
		}

		if (is_resource($socket) || $socket !== false) {
			socket_bind($socket, $listener['bind_address'], $listener['port']);

			if ($protocol == 'tcp') {
				socket_listen($socket, 1024);
			}

			$sndbuf = socket_get_option($socket, SOL_SOCKET, SO_SNDBUF);
			$rcvbuf = socket_get_option($socket, SOL_SOCKET, SO_RCVBUF);

			debug(sprintf("The Send buffer is:    %s KBytes\n", $sndbuf/1024));
			debug(sprintf("The Receive buffer is: %s KBytesm\n", $rcvbuf/1024));
		} else {
			cacti_log("FATAL: Flowview Listener unable to open port! Error: $errstr ($errno)", false, 'FLOWVIEW');

			if (!$force) {
				unregister_process('flowview', $taskname, $config['poller_id'], getmypid());
			}

			exit(1);
		}

		while (true) {
			$p = '';

			if ($protocol == 'tcp') {
				$connection = socket_accept($socket);

				if (!$connection) {
					usleep(100);
					continue;
				}
			}

			$status = socket_recvfrom($socket, $p, 8192, 0, $peer, $listener['port']);

			if ($peer === null || $status === false) {
				sleep(1);
				continue;
			}

			debug('-----------------------------------------------');
			debug("The buffer is " . strlen($p) . ", The peer is " . $peer);

			$ex_addr = get_peer_address($peer);

			if (!is_valid_peer($ex_addr, $listener['allowfrom'])) {
				cacti_log("WARNING: Flowview Received a packet from an unregistered peer $ex_addr", false, 'FLOWVIEW');
				continue;
			}

			if (!isset($tmpl_refreshed[$ex_addr])) {
				$tmpl_refreshed[$ex_addr] = false;
			}

			if ($start > 0) {
				$end = microtime(true);
				debug('-----------------------------------------------');
				debug(sprintf('Flow: Sleep Time: %0.2f', $end - $start));
			}

			$start = microtime(true);

			if ($p !== false && !$reload) {
				$version = unpack('n', substr($p, 0, 2));

				update_stream_stats($listener['id'], $ex_addr, $version[1], $tmpl_refreshed, $templates, $refresh_seconds);

				debug("Flow: Packet from: $peer v" . $version[1] . " - Len: " . strlen($p));

				database_check_connect();

				if ($version[1] == 5) {
					process_fv5($p, $ex_addr);

					$previous_version = 5;
				} elseif ($version[1] == 9) {
					process_fv9($p, $ex_addr);

					$previous_version = 9;
				} elseif ($version[1] == 10) {
					process_fv10($p, $ex_addr);

					$previous_version = 10;
				} else {
					$previous_version = -1;
				}

				$end = microtime(true);

				debug(sprintf('Flow: Cycle Time: %0.2f', $end - $start));

				$start = microtime(true);
			} else {
				socket_close($socket);

				break;
			}

			if (!$tmpl_refreshed[$ex_addr] && isset($templates[$ex_addr]) && cacti_sizeof($templates[$ex_addr])) {
				foreach($templates[$ex_addr] AS $template_id => $t) {
					flowview_db_execute_prepared("INSERT INTO `plugin_flowview_device_templates`
						(device_id, ex_addr, template_id, column_spec) VALUES (?, ?, ?, ?)
						ON DUPLICATE KEY UPDATE column_spec=VALUES(column_spec), last_updated=NOW()",
						array($listener['id'], $ex_addr, $template_id, json_encode($t)));
				}

				$tmpl_refreshed[$ex_addr] = true;
			}
		}
	}
}

exit(0);

function update_stream_stats($listener_id, $ex_addr, $version, &$tmpl_refreshed, &$templates, $refresh_seconds) {
	global $config;

	static $stream_refreshed; // We update the heartbeat every $refresh_seconds per peer
	static $sstart;           // Array of ex_addr start times
	static $ssend;            // Array of ex_addr end times
	static $lversion;         // Array of ex_addr end times

	if (!isset($sstart[$ex_addr])) {
		$sstart[$ex_addr] = time();
	}

	if (!isset($stream_refreshed[$ex_addr])) {
		$stream_refreshed[$ex_addr] = false;
	}

	if (!isset($lversion[$ex_addr]) || (isset($lversion[$ex_addr]) && $version != $lversion[$ex_addr])) {
		debug('Flow: Detecting version initialization/change to v' . $version);

		$templates                  = [];
		$stream_refreshed[$ex_addr] = false;
		$tmpl_refreshed[$ex_addr]   = false;
	}

	if (!$stream_refreshed[$ex_addr] || $ssend[$ex_addr] - $sstart[$ex_addr] > $refresh_seconds) {
		cacti_log(sprintf('Updating Listener:%s for ex_addr:%s', $listener_id, $ex_addr), false, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);

		$sstart[$ex_addr] = time();

		if ($version == 5) {
			$db_version = 'v5';
		} elseif ($version == 9) {
			$db_version = 'v9';
		} elseif ($version == 10) {
			$db_version = 'IPFIX';
		}

		if (!isset($db_version)) {
			if ((is_string($version) && strlen($version) > 0) || is_numeric($version)) {
				$db_version = substr($version, 0, 5);
			} else {
				$db_version = 'N/A';
			}
		}

		$update_time = date('Y-m-d H:i:s');

		$name = gethostbyaddr($ex_addr);

		if ($name == $ex_addr) {
			$name = sprintf('Stream [%s]', $ex_addr);
		}

		flowview_db_execute_prepared("INSERT INTO `plugin_flowview_device_streams`
			(device_id, ex_addr, name, version, last_updated) VALUES (?, ?, IF(name = '', ?, name), ?, ?)
			ON DUPLICATE KEY UPDATE
				version = VALUES(version),
				name = VALUES(name),
				last_updated = VALUES(last_updated)",
			[$listener_id, $ex_addr, $name, $db_version, $update_time]);

		flowview_db_execute_prepared("DELETE FROM `plugin_flowview_device_streams`
			WHERE device_id = ? AND last_updated < FROM_UNIXTIME(UNIX_TIMESTAMP()-86400)",
			[$listener_id]);

		heartbeat_process('flowview', 'child_' . $listener_id, $config['poller_id']);

		flowview_db_execute_prepared("UPDATE `plugin_flowview_devices`
			SET last_updated = NOW()
			WHERE id = ?",
			[$listener_id]);
	}

	$lversion[$ex_addr]         = $version;
	$ssend[$ex_addr]            = time();
	$stream_refreshed[$ex_addr] = true;
}

function get_peer_address($peer) {
	$parts = explode(':', $peer);

	/* remove the port part */
	array_pop($parts);

	if (cacti_sizeof($parts) > 1) {
		return implode(':', $parts);
	} elseif (isset($parts[0])) {
		return $parts[0];
	} else {
		return $peer;
	}
}

function is_valid_peer($peer, $range) {
	if (strpos($range, ',') !== false) {
		$ip_addresses = explode(',', $range);
		$ip_addresses = array_map('trim', $ip_addresses);

		foreach($ip_addresses as $ip) {
			if ($peer == $ip) {
				return true;
			}
		}

		return false;
	} elseif ($range == '0.0.0.0') {
		return true;
	} elseif ($peer == $range) {
		return true;
	} else {
		if (strpos($range, '/' ) === false) {
			$range .= '/32';
		}

		list($range, $netmask) = explode('/', $range, 2);

		$range_decimal    = ip2long($range);
		$ip_decimal       = ip2long($peer);
		$wildcard_decimal = pow(2, (32 - $netmask)) - 1;
		$netmask_decimal  = ~ $wildcard_decimal;
		return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
	}

	return false;
}

function database_check_connect() {
	global $config;

	flowview_determine_config();

	include($config['include_path'] . '/config.php');

	$connection_good = flowview_db_fetch_cell('SELECT 1');

	if (empty($connection_good)) {
		$cnn_id = flowview_get_connection(false);

		flowview_db_close($cnn_id);

		while(true) {
			$cnn_id = flowview_db_connect_real(
				$database_hostname,
				$database_username,
				$database_password,
				$database_default,
				$database_type,
				$database_port,
				$database_retries,
				$database_ssl,
				$database_ssl_key,
				$database_ssl_cert,
				$database_ssl_ca
			);

			if (!is_object($cnn_id)) {
				sleep(1);
			} else {
				break;
			}
		}
	}
}

function process_fv5($p, $ex_addr) {
	global $listener_id;

	flowview_connect();

	/* process header */
	$header_len  = 24;
	$header = unpack('nversion/ncount/Nsysuptime/Nunix_secs/Nunix_nsecs/Nflow_sequence/Cengine_type/Cengine_id/nsample_int', substr($p, 0, 24));

	/* prepare to process records */
	$records     = $header['count'];
	$flows       = 1;
	$flowrec_len = 48;
	$flowtime    = $header['unix_secs'];
	$sql         = [];

	debug('Flow: Processing v5 Data, Records: ' . $records);

	for ($i = 0; $i < $records; $i++) {
		$flowrec = substr($p, $header_len + ($i * $flowrec_len), $flowrec_len);

		$data = unpack('C4src_addr/C4dst_addr/C4nexthop/nsrc_if/ndst_if/NdPkts/NdOctets/NFirst/NLast/nsrc_port/ndst_port/Cblank/Cflags/Cprotocol/Ctos/nsrc_as/ndst_as/Csrc_prefix/Cdst_prefix', $flowrec);

		$flowtime    = $header['unix_secs'] + ($header['unix_nsecs'] / 1000000000);
		$time        = time();
		$delta_start = $delta_end = 0;
		$sysuptime   = $header['sysuptime'];
		$flowtimeus  = ($header['unix_nsecs'] / 1000);

		$src_addr = $data['src_addr1'] . '.' . $data['src_addr2'] . '.' . $data['src_addr3'] . '.' . $data['src_addr4'];
		$dst_addr = $data['dst_addr1'] . '.' . $data['dst_addr2'] . '.' . $data['dst_addr3'] . '.' . $data['dst_addr4'];
		$nexthop  = $data['nexthop1']  . '.' . $data['nexthop2']  . '.' . $data['nexthop3']  . '.' . $data['nexthop4'];
		$ex_addr  = $ex_addr;

		$delta_start = ($data['First'] - $sysuptime) / 1000000;
		$delta_end   = ($data['Last']  - $sysuptime) / 1000000;

		$start_date = date('Y-m-d H:i:s', intval($flowtime + $delta_start)) . '.' . abs(intval($flowtimeus + ($delta_start * 1000000)));
		$end_date   = date('Y-m-d H:i:s', intval($flowtime + $delta_end))   . '.' . abs(intval($flowtimeus + ($delta_end * 1000000)));

		//cacti_log("Time:$time, FlowTime:{$flowtime}, Start Time:{$data['First']}, SysUptime:{$sysuptime}, DeltaTime:$delta_start");
		//cacti_log("Time:$time, FlowTime:{$flowtime}, End Time:{$data['Last']}, SysUptime:{$sysuptime}, DeltaTime:$delta_end");

		$sql_prefix = get_sql_prefix($flowtime);

		$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
		$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

		$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
		$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

		$src_rport  = flowview_translate_port($data['src_port'], false, false);
		$dst_rport  = flowview_translate_port($data['dst_port'], false, false);

		if ($data['dPkts'] > 0) {
			$pps = round($data['dOctets'] / $data['dPkts'], 3);
		} else {
			$pps = 0;
		}

		$sql[] = '(' .
			$listener_id                    . ', ' .
			'0'                             . ', ' .
			db_qstr($header['engine_type']) . ', ' .
			db_qstr($header['engine_id'])   . ', ' .
			db_qstr($header['sample_int'])  . ', ' .
			db_qstr($ex_addr)               . ', ' .
			db_qstr($header['sysuptime'])   . ', ' .

			'INET6_ATON(' . db_qstr($src_addr) . ')' . ', ' .

			db_qstr($src_domain)            . ', ' .
			db_qstr($src_rdomain)           . ', ' .
			db_qstr($data['src_as'])        . ', ' .
			db_qstr($data['src_if'])        . ', ' .
			db_qstr($data['src_prefix'])    . ', ' .
			db_qstr($data['src_port'])      . ', ' .
			db_qstr($src_rport)             . ', ' .

			'INET6_ATON(' . db_qstr($dst_addr) . ')' . ', ' .

			db_qstr($dst_domain)            . ', ' .
			db_qstr($dst_rdomain)           . ', ' .
			db_qstr($data['dst_as'])        . ', ' .
			db_qstr($data['dst_if'])        . ', ' .
			db_qstr($data['dst_prefix'])    . ', ' .
			db_qstr($data['dst_port'])      . ', ' .
			db_qstr($dst_rport)             . ', ' .

			db_qstr($nexthop)               . ', ' .
			db_qstr($data['protocol'])      . ', ' .
			db_qstr($start_date)            . ', ' .
			db_qstr($end_date)              . ', ' .

			$flows                          . ', ' .

			db_qstr($data['dPkts'])         . ', ' .
			db_qstr($data['dOctets'])       . ', ' .

			$pps                            . ', ' .

			db_qstr($data['tos'])           . ', ' .
			db_qstr($data['flags'])         . ')';
	}

	if (cacti_sizeof($sql)) {
		debug('Flow: Writing Records: ' . cacti_sizeof($sql));
		flowview_db_execute($sql_prefix . implode(' ,', $sql));
	}
}

function debug($string) {
	global $debug;

	if ($debug) {
		print trim($string) . PHP_EOL;
	}
}

function get_unpack_syntax(&$field, $version) {
	global $pacmap, $allfields;

	if (isset($allfields[$field['field_id']])) {
		$prepac = $allfields[$field['field_id']]['pack'];
	} else {
		$prepac = 'string';
	}

	$field['pack'] = $prepac;
	$length        = $field['length'];
	$id            = $field['field_id'];
	$name          = $field['name'];

	$set = false;

	/**
	 * Some numeric data varies in width using the same specification
	 * so, for numeric data, check the width and adjust.
	 */
	switch($prepac) {
		case 'unsigned16':
			if ($length == 2) {
				$set = true;

				$field['unpack'] = 'n';
			} elseif ($length == 1) {
				$set = true;

				$field['unpack'] = 'C';
			}

			break;
		case 'unsigned32':
			if ($length == 4) {
				$set = true;

				$field['unpack'] = 'N';
			} elseif ($length == 2) {
				$set = true;

				$field['unpack'] = 'n';
			}

			break;
		case 'unsigned64':
			if ($length == 8) {
				$set = true;

				$field['unpack'] = 'J';
			} elseif ($length == 4) {
				$set = true;

				$field['unpack'] = 'N';
			} elseif ($length == 2) {
				$set = true;

				$field['unpack'] = 'n';
			} elseif ($length == 1) {
				$set = true;

				$field['unpack'] = 'C';
			}

			break;
	}

	if (!$set) {
		if (isset($pacmap[$prepac]) && $pacmap[$prepac] != '') {
			$field['pack']   = $prepac;
			$field['unpack'] = $pacmap[$prepac];
		} else {
			$field['pack']   = $prepac;
			$field['unpack'] = 'C' . $field['length'];
		}
	}

	debug("Flow: Name: $name, Id: $id, Length: $length, Type: $prepac, Unpack: {$field['unpack']}");
}

function process_fv9($p, $ex_addr) {
	global $templates, $tlengths, $allfields, $pacmap, $listener_id;

	flowview_connect();

	if (!isset($templates[$ex_addr])) {
		$templates[$ex_addr] = [];
	}

	/* process header */
	$header_len = 20;
	$header     = unpack('nversion/ncount/Nsysuptime/Nunix_seconds/Nseq_num/Nsource_id', substr($p, 0, $header_len));

	/* prepare to process records */
	$records    = $header['count'];
	$i          = $header_len;
	$j          = 0;
	$flowtime   = $header['unix_seconds'];
	$sysuptime  = $header['sysuptime'];
	$flow_data  = false;
	$sql        = [];
	$sql_prefix = get_sql_prefix($flowtime);

	debug('Flow: Processing v9 Data, Records: ' . $records);

	while ($j < $records) {
		$header = substr($p, $i, 4);
		$header = unpack('nflowset_id/nflowset_length', $header);
		$h      = $i + 4;
		$fslen  = $header['flowset_length'];
		$fsid   = $header['flowset_id'];

		// Template Set
		if ($fsid == 0) {
			debug('===============================================');
			debug('Flow: Template Sets Found');

			$k = 4;

			if ($fslen > 0) {
				debug('Flow: Template Set Length: ' . $fslen);

				while ($k < $fslen) {
					$theader = substr($p, $h, 4);
					$theader = unpack('ntemplate_id/nfieldcount', $theader);
					$tid     = $theader['template_id'];
					$fcount  = $theader['fieldcount'];
					$h += 4;
					$k += 4;
					$tlength = 0;

					debug('===============================================');
					debug("Flow: Template Id: $tid with $fcount fields");

					$templates[$ex_addr][$tid] = [];

					for ($a = 0; $a < $fcount; $a++) {
						$field = substr($p, $h, 4);
						$field = unpack('nfield_id/nfield_len', $field);
						$tf    = [];

						$tf['field_id'] = $field['field_id'];
						$tf['length']   = $field['field_len'];
						$tlength       += $tf['length'];

						if (($field['field_id'] & 32768)) {
							$tf['field_id']   = $field['field_id'] & ~32768;
							$tf['enterprise'] = 1;

							$entnum = substr($p, $h, 4);
							$entnum = unpack('Nentnum', $entnum);

							$tf['enterprise_number'] = $entnum['entnum'];

							$h += 4;
							$k += 4;
						} else {
							$tf['enterprise'] = 0;
						}

						if (isset($allfields[$tf['field_id']])) {
							$tf['name'] = $allfields[$tf['field_id']]['name'];
							get_unpack_syntax($tf, 9);
						} else {
							cacti_log('ERROR: Unknown field id ' . $tf['field_id'] . ' has length ' . $tf['length'], false, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);

							$tf['name']   = 'Unknown';
							$tf['unpack'] = 'C' . $tf['length'];
						}

						$templates[$ex_addr][$tid][] = $tf;
						$h += 4;
						$k += 4;
						$j++;
					}

					if (!flowview_template_supported($templates[$ex_addr][$tid], $tid)) {
						$tsupported[$ex_addr][$tid] = false;

						flowview_db_execute_prepared('UPDATE plugin_flowview_device_templates
							SET supported = 0
							WHERE device_id = ? AND ex_addr = ? AND template_id = ?',
							[$listener_id, $ex_addr, $tid]);
					} else {
						$tsupported[$ex_addr][$tid] = true;

						flowview_db_execute_prepared('UPDATE plugin_flowview_device_templates
							SET supported = 1
							WHERE device_id = ? AND ex_addr = ? AND template_id = ?',
							[$listener_id, $ex_addr, $tid]);
					}

					debug("Flow: Template Captured, Template:$tid Size:$tlength");

					$tlengths[$ex_addr][$tid] = $tlength;
				}

				debug('Flow: Templates Captured');
			} else {
				debug('Flow: Bad Template Records');
			}

			$i += $fslen;
		} elseif ($fsid == 1) {
			// Option Set
			debug('Flow: Options Found');

			$i += $fslen;
			$j++;
		} elseif ($fsid > 255) {
			// Flow Data Set
			if (cacti_sizeof($templates[$ex_addr])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;
			$k   = 4;

			if (isset($templates[$ex_addr][$tid])) {
				debug('Flow: Template Found: ' . $tid);

				while ($k < $fslen) {
					$data = [];

					foreach ($templates[$ex_addr][$tid] as $t) {
						$id = $t['field_id'];

						$field = substr($p, $h, $t['length']);

						$field = unpack($t['unpack'], $field);

						if ($t['pack'] == 'ipv4Address') {
							$field = implode('.', $field);
						} elseif ($t['pack'] == 'ipv6Address') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('0000' . dechex($v), -4);
							}

							$field = strtoupper($ofield);
						} elseif ($t['pack'] == 'macAddress') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('00' . dechex($v), -2);
							}

							$field = strtoupper($ofield);
						} elseif ($t['field_id'] == 95) {
							$field = '';
						} elseif (substr($t['unpack'], 0, 1) == 'C') {
							$field = implode('', $field);
						} elseif (cacti_count($field) > 1) {
							$c = 0;
							$d = 1;

							for ($b = cacti_count($field); $b > 0; $b--) {
								$c += $field[$b] * $d;
								$d = $d * 256;
							}

							$field = $c;
						} elseif (isset($field[1])) {
							$field = $field[1];
						}

						$h += $t['length'];
						$k += $t['length'];
						$data[$id] = $field;
					}

					$remaining = $fslen - $k;

					debug(sprintf("Flow: Processing Record: %s, Remaining: %s", $j+1, $remaining));

					$result = false;

					if (cacti_sizeof($data)) {
						$result = process_v9_v10($data, $ex_addr, $flowtime, $fsid, $sysuptime);
					}

					if ($result !== false) {
						$sql[] = $result;
					} else {
						debug("Flow: ERROR: Bad Record with FSId:$fsid");
					}

					$j++;

					/**
					 * version 9 flows can include padding check for a length
					 * less than a total template and if found finish up.
					 */
					if ($remaining < $tlengths[$ex_addr][$tid]) {
						$k = $fslen;
					}
				}
			} else {
				$j = $records;
				debug('Flow: Template Not Found, Skipping');
			}

			$i += $fslen;
		} else {
			debug("Flow: ERROR: Bad Record with FSId:$fsid");

			$i += $fslen;
			$j++;
		}
	}

	if (cacti_sizeof($sql)) {
		debug('Flow: Writing Records: ' . cacti_sizeof($sql));
		flowview_db_execute($sql_prefix . implode(', ', $sql));
	}
}

function get_sql_prefix($flowtime) {
	global $partition;
	static $last_table = '';

	flowview_connect();

	$flowtime = intval($flowtime);

	if ($partition == 0) {
		$suffix = date('Y', $flowtime) . substr('000' . date('z', $flowtime), -3);
	} else {
		$suffix = date('Y', $flowtime) . substr('000' . date('z', $flowtime), -3) . date('H', $flowtime);
	}

	$table  = 'plugin_flowview_raw_' . $suffix;

	if ($table != $last_table) {
		if (!flowview_db_table_exists($table)) {
			create_raw_partition($table);
		}
	}

	$last_table = $table;

	return 'INSERT INTO ' . $table . ' (listener_id, template_id, engine_type, engine_id, sampling_interval, ex_addr, sysuptime, src_addr, src_domain, src_rdomain, src_as, src_if, src_prefix, src_port, src_rport, dst_addr, dst_domain, dst_rdomain, dst_as, dst_if, dst_prefix, dst_port, dst_rport, nexthop, protocol, start_time, end_time, flows, packets, bytes, bytes_ppacket, tos, flags) VALUES ';
}

function process_fv10($p, $ex_addr) {
	global $listener_id, $templates, $tlengths, $allfields, $pacmap;

	flowview_connect();

	if (!isset($templates[$ex_addr])) {
		$templates[$ex_addr] = [];
	}

	/* process header */
	$header_len = 16;
	$header     = unpack('nversion/ncount/Nexporttime/Nseq_num/Ndomainid', substr($p, 0, $header_len));

	/* prepare to process records */
	$count      = $header['count'];
	$i          = $header_len;
	$flowtime   = $header['exporttime'];
	$sql        = [];
	$sql_prefix = get_sql_prefix($flowtime);

	debug('Flow: Processing v10/IPFIX Data, Bytes: ' . $count);

	while ($i < $count) {
		$header = substr($p, $i, 4);
		$header = unpack('nflowset_id/nflowset_length', $header);

		$h      = $i + 4;
		$fsid   = $header['flowset_id'];
		$fslen  = $header['flowset_length'];

		// Template Set
		if ($fsid == 2) {
			debug('===============================================');
			debug('Flow: Template Sets Found');

			$k = 4;

			if ($fslen > 0) {
				debug('Flow: Template Set Length: ' . $fslen);

				while ($k < $fslen) {
					$theader = substr($p, $h, 4);
					$theader = unpack('ntemplate_id/nfieldcount', $theader);
					$tid     = $theader['template_id'];
					$fcount  = $theader['fieldcount'];
					$h += 4;
					$k += 4;
					$tlength = 0;

					debug('===============================================');
					debug("Flow: Template Id: $tid with $fcount fields");

					$templates[$ex_addr][$tid] = [];

					for ($a = 0; $a < $fcount; $a++) {
						$field = substr($p, $h, 4);
						$field = unpack('nfield_id/nfield_len', $field);
						$tf    = [];

						$tf['field_id'] = $field['field_id'];
						$tf['length']   = $field['field_len'];
						$tlength       += $tf['length'];

						if (($field['field_id'] & 32768)) {
							$tf['field_id']   = $field['field_id'] & ~32768;
							$tf['enterprise'] = 1;

							$entnum = substr($p, $h, 4);
							$entnum = unpack('Nentnum', $entnum);

							$tf['enterprise_number'] = $entnum['entnum'];

							$h += 4;
							$k += 4;
						} else {
							$tf['enterprise'] = 0;
						}

						if (isset($allfields[$tf['field_id']])) {
							$tf['name'] = $allfields[$tf['field_id']]['name'];
							get_unpack_syntax($tf, 10);
						} else {
							cacti_log('ERROR: Unknown field id ' . $tf['field_id'] . ' has length ' . $tf['length'], false, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);

							$tf['name']   = 'Unknown';
							$tf['unpack'] = 'C' . $tf['length'];
						}

						$templates[$ex_addr][$tid][] = $tf;
						$h += 4;
						$k += 4;
					}

					if (!flowview_template_supported($templates[$ex_addr][$tid], $tid)) {
						$tsupported[$ex_addr][$tid] = false;

						flowview_db_execute_prepared('UPDATE plugin_flowview_device_templates
							SET supported = 0
							WHERE device_id = ? AND ex_addr = ? AND template_id = ?',
							[$listener_id, $ex_addr, $tid]);
					} else {
						$tsupported[$ex_addr][$tid] = true;

						flowview_db_execute_prepared('UPDATE plugin_flowview_device_templates
							SET supported = 1
							WHERE device_id = ? AND ex_addr = ? AND template_id = ?',
							[$listener_id, $ex_addr, $tid]);
					}

					debug("Flow: Template Captured, Template:$tid Size: $tlength");

					$tlengths[$ex_addr][$tid] = $tlength;
				}
			} else {
				debug('Flow: Bad Template Records');
			}

			//print_r($templates);

			$i += $fslen;
		} elseif ($fsid == 3) {
			// Option Set
			debug('Flow: Options Found');

			$i += $fslen;
		} elseif ($fsid > 255) {
			// Data Set
			if (cacti_sizeof($templates[$ex_addr])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;
			$k   = 4;
			$j   = 0;

			if (isset($templates[$ex_addr][$tid])) {
				debug('Flow: Template Found: ' . $tid);

				while ($k < $fslen) {
					$data = [];

					foreach ($templates[$ex_addr][$tid] as $t) {
						$id    = $t['field_id'];

						$field = substr($p, $h, $t['length']);
						$field = unpack($t['unpack'], $field);

						if ($t['pack'] == 'ipv4Address') {
							$field = implode('.', $field);
						} elseif ($t['pack'] == 'ipv6Address') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('0000' . dechex($v), -4);
							}

							$field = strtoupper($ofield);
						} elseif ($t['pack'] == 'macAddress') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('00' . dechex($v), -2);
							}

							$field = strtoupper($ofield);
						} elseif (cacti_count($field) > 1) {
							$c = 0;
							$d = 1;

							for ($b = cacti_count($field); $b > 0; $b--) {
								$c += $field[$b] * $d;
								$d = $d * 256;
							}

							$field = $c;
						} elseif (isset($field[1])) {
							$field = $field[1];
						}

						$h += $t['length'];
						$k += $t['length'];
						$data[$id] = $field;
					}

					$remaining = $fslen - $k;

					debug(sprintf("Flow: Processing Record: %s, Remaining: %s", $j+1, $remaining));

					$result = false;

					if (cacti_sizeof($data)) {
						$result = process_v9_v10($data, $ex_addr, $flowtime, $fsid);
					}

					if ($result !== false) {
						$sql[] = $result;
					} else {
						debug("Flow: ERROR: Bad Record with FSId:$fsid");
					}

					$j++;

					/**
					 * version 9 flows can include padding check for a length
					 * less than a total template and if found finish up.
					 */
					if ($remaining < $tlengths[$ex_addr][$tid]) {
						$k = $fslen;
					}
				}
			} else {
				$i = $count;
				debug('Flow: Template Not Found, Skipping');
			}

			$i += $fslen;
		} else {
			debug("Flow: ERROR: Bad Record with FSId:$fsid");

			$i += $fslen;
		}
	}

	if (cacti_sizeof($sql)) {
		debug('Flow: Writing Records: ' . cacti_sizeof($sql));
		flowview_db_execute($sql_prefix . implode(', ', $sql));
	}
}

function flowview_template_supported($template, $tid) {
	global $required_fields_v4, $required_fields_v6;

	static $logged_ipv4_errors = false;
	static $logged_ipv6_errors = false;

	$fieldspec = ['field_id', 'name', 'pack', 'unpack'];
	$columns   = [];

	foreach($template as $index => $field) {
		$columns[$field['field_id']] = true;
	}

	if (isset($columns['12'])) {
		foreach($required_fields_v4 as $columnName => $field_id) {
			if (!isset($columns[$field_id]) && !$logged_ipv4_errors) {
				cacti_log('Column with field id ' . $field_id . ' does not exist for ipv4 flow template.');

				$logged_ipv4_errors = true;

				return false;
			}
		}
	} elseif (isset($columns['28'])) {
		foreach($required_fields_v6 as $columnName => $field_id) {
			if (!isset($columns[$field_id]) && !$logged_ipv6_errors) {
				cacti_log('Column with field id ' . $field_id . ' does not exist for ipv6 flow template.');

				$logged_ipv6_errors = true;

				return false;
			}
		}
	} else {
		return false;
	}

	return true;
}

function process_v9_v10($data, $ex_addr, $flowtime, $fsid, $sysuptime = 0) {
	global $listener_id, $partition, $flow_fields;

	$flows = 1;

	if (isset($data[$flow_fields['src_addr_ipv6']])) {
		$src_addr = $data[$flow_fields['src_addr_ipv6']];

		if (isset($data[$flow_fields['src_prefix_ipv6']])) {
			$src_prefix = $data[$flow_fields['src_prefix_ipv6']];
		} else {
			$src_prefix = 0;
		}
	} elseif (isset($data[$flow_fields['src_addr']])) {
		$src_addr = $data[$flow_fields['src_addr']];

		if (isset($data[$flow_fields['src_prefix']])) {
			$src_prefix = $data[$flow_fields['src_prefix']];
		} else {
			$src_prefix = 0;
		}
	} else {
		cacti_log('The Source Address is not set', false, 'FLOWVIEW');
		return false;
	}

	if (isset($data[$flow_fields['dst_addr_ipv6']])) {
		$dst_addr = $data[$flow_fields['dst_addr_ipv6']];

		if (isset($data[$flow_fields['dst_prefix_ipv6']])) {
			$dst_prefix = $data[$flow_fields['dst_prefix_ipv6']];
		} else {
			$dst_prefix = 0;
		}
	} elseif (isset($data[$flow_fields['dst_addr']])) {
		$dst_addr = $data[$flow_fields['dst_addr']];

		if (isset($data[$flow_fields['dst_prefix']])) {
			$dst_prefix = $data[$flow_fields['dst_prefix']];
		} else {
			$dst_prefix = 0;
		}
	} else {
		cacti_log('The Destination Address is not set', false, 'FLOWVIEW');
		return false;
	}

	if (isset($data[$flow_fields['nexthop_ipv6']])) {
		$nexthop = $data[$flow_fields['nexthop_ipv6']];
	} elseif (isset($data[$flow_fields['nexthop']])) {
		$nexthop = $data[$flow_fields['nexthop']];
	} else {
		$nexthop = '';
	}

	if (isset($data[$flow_fields['sysuptime']]) && abs($data[$flow_fields['end_time']] - $data[$flow_fields['sysuptime']]) < 3) {
		$delta_start = $delta_end = 0;
		$sysuptime   = $data[$flow_fields['sysuptime']];
		$time = time();

		if (isset($data[$flow_fields['start_time']])) {
			$delta_start = ($data[$flow_fields['start_time']] - $sysuptime) / 1000000;
			//cacti_log("Time:$time, FlowTime:{$flowtime}, Start Time:{$data[$flow_fields['start_time']]}, SysUptime:{$sysuptime}, DeltaTime:$delta_start");
		}

		if (isset($data[$flow_fields['end_time']])) {
			$delta_end   = ($data[$flow_fields['end_time']] - $sysuptime) / 1000000;
			//cacti_log("Time:$time, FlowTime:{$flowtime}, End Time:{$data[$flow_fields['end_time']]}, SysUptime:{$sysuptime}, DeltaTime:$delta_end");
		}

		$start_date = date('Y-m-d H:i:s', intval($flowtime + $delta_start)) . '.' . abs(intval($delta_start*1000000));
		$end_date   = date('Y-m-d H:i:s', intval($flowtime + $delta_end))   . '.' . abs(intval($delta_end*1000000));

		//cacti_log(sprintf("CurDate:%s, StartDate:%s, EndDate:%s", date('Y-m-d H:i:s'), $start_date, $end_date));
	} elseif ($sysuptime > 0) {
		$delta_start = $delta_end = 0;
		$time   = time();

		if (isset($data[$flow_fields['start_time']])) {
			$delta_start = ($data[$flow_fields['start_time']] - $sysuptime) / 1000000;
			//cacti_log("Time:$time, FlowTime:{$flowtime}, Start Time:{$data[$flow_fields['start_time']]}, SysUptime:{$sysuptime}, DeltaTime:$delta_start");
		}

		if (isset($data[$flow_fields['end_time']])) {
			$delta_end   = ($data[$flow_fields['end_time']] - $sysuptime) / 1000000;
			//cacti_log("Time:$time, FlowTime:{$flowtime}, End Time:{$data[$flow_fields['end_time']]}, SysUptime:{$sysuptime}, DeltaTime:$delta_end");
		}

		$start_date = date('Y-m-d H:i:s', intval($flowtime + $delta_start)) . '.' . abs(intval($delta_start*1000000));
		$end_date   = date('Y-m-d H:i:s', intval($flowtime + $delta_end))   . '.' . abs(intval($delta_end*1000000));
		//cacti_log(sprintf("CurDate:%s, StartDate:%s, EndDate:%s", date('Y-m-d H:i:s'), $start_date, $end_date));
	} else {
		if (isset($data[$flow_fields['start_time']]) && isset($data[$flow_fields['end_time']])) {
			$delta_micro = intval(($data[$flow_fields['end_time']] - $data[$flow_fields['start_time']]) / 1000000);
			$delta_sec   = floor($data[$flow_fields['end_time']] - $data[$flow_fields['start_time']]);
		} else {
			$delta_micro = $delta_sec = 0;
		}

		$start_date = date('Y-m-d H:i:s', intval($flowtime - $delta_sec)) . '.' . abs(substr("{$delta_micro}000000", 0, 6));
		$end_date   = date('Y-m-d H:i:s', intval($flowtime)) . '.' . '000000';
	}

	$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
	$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

	$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
	$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

	if (isset($data[$flow_fields['src_port']])) {
		$src_rport = flowview_translate_port($data[$flow_fields['src_port']], false, false);
	} else {
		$src_rport = 0;
	}

	if (isset($data[$flow_fields['dst_port']])) {
		$dst_rport = flowview_translate_port($data[$flow_fields['dst_port']], false, false);
	} else {
		$dst_rport = 0;
	}

	if (isset($data[$flow_fields['dPkts']]) && $data[$flow_fields['dPkts']] > 0) {
		$pps = round($data[$flow_fields['dOctets']] / $data[$flow_fields['dPkts']], 3);
	} else {
		$pps = 0;
	}

	$sql = '(' .
		$listener_id                                        . ', ' .
		$fsid                                               . ', ' .
		check_set($data, $flow_fields['engine_type'])       . ', ' .
		check_set($data, $flow_fields['engine_id'])         . ', ' .
		check_set($data, $flow_fields['sampling_interval']) . ', ' .
		db_qstr($ex_addr)                                   . ', ' .
		$sysuptime                                          . ', ' .

		'INET6_ATON(' . db_qstr($src_addr) . ')'            . ', ' .
		db_qstr($src_domain)                                . ', ' .
		db_qstr($src_rdomain)                               . ', ' .
		check_set($data, $flow_fields['src_as'])            . ', ' .
		check_set($data, $flow_fields['src_if'])            . ', ' .
		$src_prefix                                         . ', ' .
		check_set($data, $flow_fields['src_port'])          . ', ' .
		db_qstr($src_rport)                                 . ', ' .

		'INET6_ATON(' . db_qstr($dst_addr) . ')'            . ', ' .
		db_qstr($dst_domain)                                . ', ' .
		db_qstr($dst_rdomain)                               . ', ' .
		check_set($data, $flow_fields['dst_as'])            . ', ' .
		check_set($data, $flow_fields['dst_if'])            . ', ' .
		$dst_prefix                                         . ', ' .
		check_set($data, $flow_fields['dst_port'])          . ', ' .
		db_qstr($dst_rport)                                 . ', ' .

		db_qstr($nexthop)                                   . ', ' .
		check_set($data, $flow_fields['protocol'])          . ', ' .
		db_qstr($start_date)                                . ', ' .
		db_qstr($end_date)                                  . ', ' .

		$flows                                              . ', ' .
		check_set($data, $flow_fields['dPkts'])             . ', ' .
		check_set($data, $flow_fields['dOctets'])           . ', ' .
		$pps                                                . ', ' .
		check_set($data, $flow_fields['tos'])               . ', ' .
		check_set($data, $flow_fields['flags'])             . ')';

	return $sql;
}

function check_set(&$data, $index, $quote = false) {
	if (isset($data[$index])) {
		if ($quote) {
			return db_qstr($data[$index]);
		} else {
			return $data[$index];
		}
	} else {
		if ($quote) {
			return db_qstr('');
		} else {
			return 0;
		}
	}
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param  (int) $signo - the signal that was thrown by the interface.
 *
 * @return (void)
 */
function sig_handler($signo) {
	global $taskname, $force, $config, $reload, $flowview_sighup_settings;

	switch ($signo) {
		case SIGHUP:
			cacti_log('NOTE: Flow Collector request received to reload is running configuration.', false, 'FLOWVIEW');

			$reload = true;

			foreach($flowview_sighup_settings as $setting) {
				$rsetting = read_config_option($setting, true);
			}

			break;
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: Flowview Listener $taskname is shutting down by signal!", false, 'FLOWVIEW');

			if (!$force) {
				unregister_process('flowview', $taskname, $config['poller_id'], getmypid());
			}

			exit(1);
			break;
		default:
			/* ignore all other signals */
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Flow Capture Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/**
 * display_help - displays help information
 */
function display_help() {
	display_version();

	print PHP_EOL . "usage: flow_collector.php --listener-id=ID [--debug]" . PHP_EOL . PHP_EOL;

	print "Cacti utility receiving flow data over a socket." . PHP_EOL . PHP_EOL;

	print "Options:" . PHP_EOL;
	print "    --listener-id=ID  The listener-id to collect for." . PHP_EOL;
	print "    --debug           Provide some debug output during collection." . PHP_EOL . PHP_EOL;
}

