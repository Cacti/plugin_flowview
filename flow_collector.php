#!/usr/bin/env php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

chdir(__DIR__ . '/../../');
include('./include/cli_check.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

ini_set('max_execution_time', '-1');

flowview_connect();

$debug     = false;
$taskname  = '';

$shortopts = 'VvHh';
$longopts = array(
	'listener-id::',
	'debug',
	'version',
	'help',
);

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
		case 'version':
			display_version();

			break;
		case 'help':
			display_help();

			break;
	}
}

/* install signal handlers for UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

$templates = array();
$tlenghs   = array();
$start     = 0;

$pacmap = array(
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
);

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
	1   => array('name' => 'octetDeltaCount',                       'pack' => 'unsigned64'),
	2   => array('name' => 'packetDeltaCount',                      'pack' => 'unsigned64'),
	3   => array('name' => 'deltaFlowCount',                        'pack' => 'unsigned64'),
	4   => array('name' => 'protocolIdentifier',                    'pack' => 'unsigned8'),
	5   => array('name' => 'ipClassOfService',                      'pack' => 'unsigned8'),
	6   => array('name' => 'tcpControlBits',                        'pack' => 'unsigned16'),
	7   => array('name' => 'sourceTransportPort',                   'pack' => 'unsigned16'),
	8   => array('name' => 'sourceIPv4Address',                     'pack' => 'ipv4Address'),
	9   => array('name' => 'sourceIPv4PrefixLength',                'pack' => 'unsigned8'),
	10  => array('name' => 'ingressInterface',                      'pack' => 'unsigned32'),
	11  => array('name' => 'destinationTransportPort',              'pack' => 'unsigned16'),
	12  => array('name' => 'destinationIPv4Address',                'pack' => 'ipv4Address'),
	13  => array('name' => 'destinationIPv4PrefixLength',           'pack' => 'unsigned8'),
	14  => array('name' => 'egressInterface',                       'pack' => 'unsigned32'),
	15  => array('name' => 'ipNextHopIPv4Address',                  'pack' => 'ipv4Address'),
	16  => array('name' => 'bgpSourceAsNumber',                     'pack' => 'unsigned32'),
	17  => array('name' => 'bgpDestinationAsNumber',                'pack' => 'unsigned32'),
	18  => array('name' => 'bgpNexthopIPv4Address',                 'pack' => 'ipv4Address'),
	19  => array('name' => 'postMCastPacketDeltaCount',             'pack' => 'unsigned64'),
	20  => array('name' => 'postMCastOctetDeltaCount',              'pack' => 'unsigned64'),
	21  => array('name' => 'flowEndSysUpTime',                      'pack' => 'unsigned32'),
	22  => array('name' => 'flowStartSysUpTime',                    'pack' => 'unsigned32'),
	23  => array('name' => 'postOctetDeltaCount',                   'pack' => 'unsigned64'),
	24  => array('name' => 'postPacketDeltaCount',                  'pack' => 'unsigned64'),
	25  => array('name' => 'minimumIpTotalLength',                  'pack' => 'unsigned64'),
	26  => array('name' => 'maximumIpTotalLength',                  'pack' => 'unsigned64'),
	27  => array('name' => 'sourceIPv6Address',                     'pack' => 'ipv6Address'),
	28  => array('name' => 'destinationIPv6Address',                'pack' => 'ipv6Address'),
	29  => array('name' => 'sourceIPv6PrefixLength',                'pack' => 'unsigned8'),
	30  => array('name' => 'destinationIPv6PrefixLength',           'pack' => 'unsigned8'),
	31  => array('name' => 'flowLabelIPv6',                         'pack' => 'unsigned32'),
	32  => array('name' => 'icmpTypeCodeIPv4',                      'pack' => 'unsigned16'),
	33  => array('name' => 'igmpType',                              'pack' => 'unsigned8'),
	34  => array('name' => 'samplingInterval',                      'pack' => 'unsigned32'),
	35  => array('name' => 'samplingAlgorithm',                     'pack' => 'unsigned8'),
	36  => array('name' => 'flowActiveTimeout',                     'pack' => 'unsigned16'),
	37  => array('name' => 'flowIdleTimeout',                       'pack' => 'unsigned16'),
	38  => array('name' => 'engineType',                            'pack' => 'unsigned8'),
	39  => array('name' => 'engineId',                              'pack' => 'unsigned8'),
	40  => array('name' => 'exportedOctetTotalCount',               'pack' => 'unsigned64'),
	41  => array('name' => 'exportedMessageTotalCount',             'pack' => 'unsigned64'),
	42  => array('name' => 'exportedFlowRecordTotalCount',          'pack' => 'unsigned64'),
	43  => array('name' => 'ipv4RouterSc',                          'pack' => 'ipv4Address'),
	44  => array('name' => 'sourceIPv4Prefix',                      'pack' => 'ipv4Address'),
	45  => array('name' => 'destinationIPv4Prefix',                 'pack' => 'ipv4Address'),
	46  => array('name' => 'mplsTopLabelType',                      'pack' => 'unsigned8'),
	47  => array('name' => 'mplsTopLabelIPv4Address',               'pack' => 'ipv4Address'),
	48  => array('name' => 'samplerId',                             'pack' => 'unsigned8'),
	49  => array('name' => 'samplerMode',                           'pack' => 'unsigned8'),
	50  => array('name' => 'samplerRandomInterval',                 'pack' => 'unsigned32'),
	51  => array('name' => 'classId',                               'pack' => 'unsigned8'),
	52  => array('name' => 'minimumTTL',                            'pack' => 'unsigned8'),
	53  => array('name' => 'maximumTTL',                            'pack' => 'unsigned8'),
	54  => array('name' => 'fragmentIdentification',                'pack' => 'unsigned32'),
	55  => array('name' => 'postIpClassOfService',                  'pack' => 'unsigned8'),
	56  => array('name' => 'sourceMacAddress',                      'pack' => 'macAddress'),
	57  => array('name' => 'postDestinationMacAddress',             'pack' => 'macAddress'),
	58  => array('name' => 'vlanId',                                'pack' => 'unsigned16'),
	59  => array('name' => 'postVlanId',                            'pack' => 'unsigned16'),
	60  => array('name' => 'ipVersion',                             'pack' => 'unsigned8'),
	61  => array('name' => 'flowDirection',                         'pack' => 'unsigned8'),
	62  => array('name' => 'ipNextHopIPv6Address',                  'pack' => 'ipv6Address'),
	63  => array('name' => 'bgpNexthopIPv6Address',                 'pack' => 'ipv6Address'),
	64  => array('name' => 'ipv6ExtensionHeaders',                  'pack' => 'unsigned32'),

	65  => array('name' => 'vendorReserved',                        'pack' => 'string'),
	66  => array('name' => 'vendorReserved',                        'pack' => 'string'),
	67  => array('name' => 'vendorReserved',                        'pack' => 'string'),
	68  => array('name' => 'vendorReserved',                        'pack' => 'string'),
	69  => array('name' => 'vendorReserved',                        'pack' => 'string'),

	70  => array('name' => 'mplsTopLabelStackSection',              'pack' => 'octetArray'),
	71  => array('name' => 'mplsLabelStackSection2',                'pack' => 'octetArray'),
	72  => array('name' => 'mplsLabelStackSection3',                'pack' => 'octetArray'),
	73  => array('name' => 'mplsLabelStackSection4',                'pack' => 'octetArray'),
	74  => array('name' => 'mplsLabelStackSection5',                'pack' => 'octetArray'),
	75  => array('name' => 'mplsLabelStackSection6',                'pack' => 'octetArray'),
	76  => array('name' => 'mplsLabelStackSection7',                'pack' => 'octetArray'),
	77  => array('name' => 'mplsLabelStackSection8',                'pack' => 'octetArray'),
	78  => array('name' => 'mplsLabelStackSection9',                'pack' => 'octetArray'),
	79  => array('name' => 'mplsLabelStackSection10',               'pack' => 'octetArray'),
	80  => array('name' => 'destinationMacAddress',                 'pack' => 'macAddress'),
	81  => array('name' => 'postSourceMacAddress',                  'pack' => 'macAddress'),
	82  => array('name' => 'interfaceName',                         'pack' => 'string'),
	83  => array('name' => 'interfaceDescription',                  'pack' => 'string'),
	84  => array('name' => 'samplerName',                           'pack' => 'string'),
	85  => array('name' => 'octetTotalCount',                       'pack' => 'unsigned64'),
	86  => array('name' => 'packetTotalCount',                      'pack' => 'unsigned64'),
	87  => array('name' => 'flagsAndSamplerId',                     'pack' => 'unsigned32'),
	88  => array('name' => 'fragmentOffset',                        'pack' => 'unsigned16'),
	89  => array('name' => 'forwardingStatus',                      'pack' => 'unsigned8'),
	90  => array('name' => 'mplsVpnRouteDistinguisher',             'pack' => 'octetArray'),
	91  => array('name' => 'mplsTopLabelPrefixLength',              'pack' => 'unsigned8'),
	92  => array('name' => 'srcTrafficIndex',                       'pack' => 'unsigned32'),
	93  => array('name' => 'dstTrafficIndex',                       'pack' => 'unsigned32'),
	94  => array('name' => 'applicationDescription',                'pack' => 'string'),
	95  => array('name' => 'applicationId',                         'pack' => 'octetArray'),
	96  => array('name' => 'applicationName',                       'pack' => 'unsigned8'),
	97  => array('name' => 'Assigned For V9 Compatibility',         'pack' => ''),
	98  => array('name' => 'postIpDiffServCodePoint',               'pack' => 'unsigned8'),
	99  => array('name' => 'multicastReplicationFactor',            'pack' => 'unsigned32'),
	100 => array('name' => 'className',                             'pack' => 'string'),
	101 => array('name' => 'classificationEngineId',                'pack' => 'unsigned8'),
	102 => array('name' => 'layer2packetSectionOffset',             'pack' => 'unsigned16'),
	103 => array('name' => 'layer2packetSectionSize',               'pack' => 'unsigned16'),
	104 => array('name' => 'layer2packetSectionData',               'pack' => 'octetArray'),

	128 => array('name' => 'bgpNextAdjacentAsNumber',               'pack' => 'unsigned32'),
	129 => array('name' => 'bgpPrevAdjacentAsNumber',               'pack' => 'unsigned32'),
	130 => array('name' => 'exporterIPv4Address',                   'pack' => 'ipv4Address'),
	131 => array('name' => 'exporterIPv6Address',                   'pack' => 'ipv6Address'),
	132 => array('name' => 'droppedOctetDeltaCount',                'pack' => 'unsigne64'),
	133 => array('name' => 'droppedPacketDeltaCount',               'pack' => 'unsigne64'),
	134 => array('name' => 'droppedOctetTotalCount',                'pack' => 'unsigne64'),
	135 => array('name' => 'droppedPacketTotalCount',               'pack' => 'unsigne64'),
	136 => array('name' => 'flowEndReason',                         'pack' => 'unsigned8'),
	137 => array('name' => 'commonPropertiesId',                    'pack' => 'unsigned64'),
	138 => array('name' => 'observationPointId',                    'pack' => 'unsigned64'),
	139 => array('name' => 'icmpTypeCodeIPv6',                      'pack' => 'unsigned16'),
	140 => array('name' => 'mplsTopLabelIPv6Address',               'pack' => 'ipv6Address'),
	141 => array('name' => 'lineCardId',                            'pack' => 'unsigned32'),
	142 => array('name' => 'portId',                                'pack' => 'unsigned32'),
	143 => array('name' => 'meteringProcessId',                     'pack' => 'unsigned32'),
	144 => array('name' => 'exportingProcessId',                    'pack' => 'unsigned32'),
	145 => array('name' => 'templateId',                            'pack' => 'unsigned16'),
	146 => array('name' => 'wlanChannelId',                         'pack' => 'unsigned8'),
	147 => array('name' => 'wlanSSID',                              'pack' => 'string'),
	148 => array('name' => 'flowId',                                'pack' => 'unsigned64'),
	149 => array('name' => 'observationDomainId',                   'pack' => 'unsigned32'),
	150 => array('name' => 'flowStartSeconds',                      'pack' => 'dateTimeSeconds'),
	151 => array('name' => 'flowEndSeconds',                        'pack' => 'dateTimeSeconds'),
	152 => array('name' => 'flowStartMilliseconds',                 'pack' => 'dateTimeMilliseconds'),
	153 => array('name' => 'flowEndMilliseconds',                   'pack' => 'dateTimeMilliseconds'),
	154 => array('name' => 'flowStartMicroseconds',                 'pack' => 'dateTimeMicroseconds'),
	155 => array('name' => 'flowEndMicroseconds',                   'pack' => 'dateTimeMicroseconds'),
	156 => array('name' => 'flowStartNanoseconds',                  'pack' => 'dateTimeNanoSeconds'),
	157 => array('name' => 'flowEndNanoseconds',                    'pack' => 'dateTimeNanoSeconds'),
	158 => array('name' => 'flowStartDeltaMicroseconds',            'pack' => 'unsigned32'),
	159 => array('name' => 'flowEndDeltaMicroseconds',              'pack' => 'unsigned32'),
	160 => array('name' => 'systemInitTimeMilliseconds',            'pack' => 'dateTimeMilliseconds'),
	161 => array('name' => 'flowDurationMilliseconds',              'pack' => 'unsigned32'),
	162 => array('name' => 'flowDurationMicroseconds',              'pack' => 'unsigned32'),
	163 => array('name' => 'observedFlowTotalCount',                'pack' => 'unsigned64'),
	164 => array('name' => 'ignoredPacketTotalCount',               'pack' => 'unsigned64'),
	165 => array('name' => 'ignoredOctetTotalCount',                'pack' => 'unsigned64'),
	166 => array('name' => 'notSentFlowTotalCount',                 'pack' => 'unsigned64'),
	167 => array('name' => 'notSentPacketTotalCount',               'pack' => 'unsigned64'),
	168 => array('name' => 'notSentOctetTotalCount',                'pack' => 'unsigned64'),
	169 => array('name' => 'destinationIPv6Prefix',                 'pack' => 'ipv6Address'),
	170 => array('name' => 'sourceIPv6Prefix',                      'pack' => 'ipv6Address'),
	171 => array('name' => 'postOctetTotalCount',                   'pack' => 'unsigned64'),
	172 => array('name' => 'postPacketTotalCount',                  'pack' => 'unsigned64'),
	173 => array('name' => 'flowKeyIndicator',                      'pack' => 'unsigned64'),
	174 => array('name' => 'postMCastPacketTotalCount',             'pack' => 'unsigned64'),
	175 => array('name' => 'postMCastOctetTotalCount',              'pack' => 'unsigned64'),
	176 => array('name' => 'icmpTypeIPv4',                          'pack' => 'unsigned8'),
	177 => array('name' => 'icmpCodeIPv4',                          'pack' => 'unsigned8'),
	178 => array('name' => 'icmpTypeIPv6',                          'pack' => 'unsigned8'),
	179 => array('name' => 'icmpCodeIPv6',                          'pack' => 'unsigned8'),
	180 => array('name' => 'udpSourcePort',                         'pack' => 'unsigned16'),
	181 => array('name' => 'udpDestinationPort',                    'pack' => 'unsigned16'),
	182 => array('name' => 'tcpSourcePort',                         'pack' => 'unsigned16'),
	183 => array('name' => 'tcpDestinationPort',                    'pack' => 'unsigned16'),
	184 => array('name' => 'tcpSequenceNumber',                     'pack' => 'unsigned32'),
	185 => array('name' => 'tcpAcknowledgementNumber',              'pack' => 'unsigned32'),
	186 => array('name' => 'tcpWindowSize',                         'pack' => 'unsigned16'),
	187 => array('name' => 'tcpUrgentPointer',                      'pack' => 'unsigned16'),
	188 => array('name' => 'tcpHeaderLength',                       'pack' => 'unsigned8'),
	189 => array('name' => 'ipHeaderLength',                        'pack' => 'unsigned8'),
	190 => array('name' => 'totalLengthIPv4',                       'pack' => 'unsigned16'),
	191 => array('name' => 'payloadLengthIPv6',                     'pack' => 'unsigned16'),
	192 => array('name' => 'ipTTL',                                 'pack' => 'unsigned8'),
	193 => array('name' => 'nextHeaderIPv6',                        'pack' => 'unsigned8'),
	194 => array('name' => 'mplsPayloadLength',                     'pack' => 'unsigned32'),
	195 => array('name' => 'ipDiffServCodePoint',                   'pack' => 'unsigned8'),
	196 => array('name' => 'ipPrecedence',                          'pack' => 'unsigned8'),
	197 => array('name' => 'fragmentFlags',                         'pack' => 'unsigned8'),
	198 => array('name' => 'octetDeltaSumOfSquares',                'pack' => 'unsigned64'),
	199 => array('name' => 'octetTotalSumOfSquares',                'pack' => 'unsigned64'),
	200 => array('name' => 'mplsTopLabelTTL',                       'pack' => 'unsigned8'),
	201 => array('name' => 'mplsLabelStackLength',                  'pack' => 'unsigned32'),
	202 => array('name' => 'mplsLabelStackDepth',                   'pack' => 'unsigned32'),
	203 => array('name' => 'mplsTopLabelExp',                       'pack' => 'unsigned8'),
	204 => array('name' => 'ipPayloadLength',                       'pack' => 'unsigned32'),
	205 => array('name' => 'udpMessageLength',                      'pack' => 'unsigned16'),
	206 => array('name' => 'isMulticast',                           'pack' => 'unsigned8'),
	207 => array('name' => 'ipv4IHL',                               'pack' => 'unsigned8'),
	208 => array('name' => 'ipv4Options',                           'pack' => 'unsigned32'),
	209 => array('name' => 'tcpOptions',                            'pack' => 'unsigned64'),
	210 => array('name' => 'paddingOctets',                         'pack' => 'octetArray'),
	211 => array('name' => 'collectorIPv4Address',                  'pack' => 'ipv4Address'),
	212 => array('name' => 'collectorIPv6Address',                  'pack' => 'ipv6Address'),
	213 => array('name' => 'exportInterface',                       'pack' => 'unsigned32'),
	214 => array('name' => 'exportProtocolVersion',                 'pack' => 'unsigned8'),
	215 => array('name' => 'exportTransportProtocol',               'pack' => 'unsigned8'),
	216 => array('name' => 'collectorTransportPort',                'pack' => 'unsigned16'),
	217 => array('name' => 'exporterTransportPort',                 'pack' => 'unsigned16'),
	218 => array('name' => 'tcpSynTotalCount',                      'pack' => 'unsigned64'),
	219 => array('name' => 'tcpFinTotalCount',                      'pack' => 'unsigned64'),
	220 => array('name' => 'tcpRstTotalCount',                      'pack' => 'unsigned64'),
	221 => array('name' => 'tcpPshTotalCount',                      'pack' => 'unsigned64'),
	222 => array('name' => 'tcpAckTotalCount',                      'pack' => 'unsigned64'),
	223 => array('name' => 'tcpUrgTotalCount',                      'pack' => 'unsigned64'),
	224 => array('name' => 'ipTotalLength',                         'pack' => 'unsigned64'),
	225 => array('name' => 'postNATSourceIPv4Address',              'pack' => 'ipv4Address'),
	226 => array('name' => 'postNATDestinationIPv4Address',         'pack' => 'ipv4Address'),
	227 => array('name' => 'postNAPTSourceTransportPort',           'pack' => 'unsigned16'),
	228 => array('name' => 'postNAPTDestinationTransportPort',      'pack' => 'unsigned16'),
	229 => array('name' => 'natOriginatingAddressRealm',            'pack' => 'unsigned8'),
	230 => array('name' => 'natEvent',                              'pack' => 'unsigned8'),
	231 => array('name' => 'initiatorOctets',                       'pack' => 'unsigned64'),
	232 => array('name' => 'responderOctets',                       'pack' => 'unsigned64'),
	233 => array('name' => 'firewallEvent',                         'pack' => 'unsigned8'),
	234 => array('name' => 'ingressVRFID',                          'pack' => 'unsigned32'),
	235 => array('name' => 'egressVRFID',                           'pack' => 'unsigned32'),
	236 => array('name' => 'VRFname',                               'pack' => 'string'),
	237 => array('name' => 'postMplsTopLabelExp',                   'pack' => 'unsigned8'),
	238 => array('name' => 'tcpWindowScale',                        'pack' => 'unsigned16'),
	239 => array('name' => 'biflowDirection',                       'pack' => 'unsigned8'),
	240 => array('name' => 'ethernetHeaderLength',                  'pack' => 'unsigned8'),
	241 => array('name' => 'ethernetPayloadLength',                 'pack' => 'unsigned16'),
	242 => array('name' => 'ethernetTotalLength',                   'pack' => 'unsigned16'),
	243 => array('name' => 'dot1qVlanId',                           'pack' => 'unsigned16'),
	244 => array('name' => 'dot1qPriority',                         'pack' => 'unsigned8'),
	245 => array('name' => 'dot1qCustomerVlanId',                   'pack' => 'unsigned16'),
	246 => array('name' => 'dot1qCustomerPriority',                 'pack' => 'unsigned8'),
	247 => array('name' => 'metroEvcId',                            'pack' => 'string'),
	248 => array('name' => 'metroEvcType',                          'pack' => 'unsigned8'),
	249 => array('name' => 'pseudoWireId',                          'pack' => 'unsigned32'),
	250 => array('name' => 'pseudoWireType',                        'pack' => 'unsigned16'),
	251 => array('name' => 'pseudoWireControlWord',                 'pack' => 'unsigned32'),
	252 => array('name' => 'ingressPhysicalInterface',              'pack' => 'unsigned32'),
	253 => array('name' => 'egressPhysicalInterface',               'pack' => 'unsigned32'),
	254 => array('name' => 'postDot1qVlanId',                       'pack' => 'unsigned16'),
	255 => array('name' => 'postDot1qCustomerVlanId',               'pack' => 'unsigned16'),
	256 => array('name' => 'ethernetType',                          'pack' => 'unsigned16'),
	257 => array('name' => 'postIpPrecedence',                      'pack' => 'unsigned8'),
	258 => array('name' => 'collectionTimeMilliseconds',            'pack' => 'dateTimeMilliseconds'),
	259 => array('name' => 'exportSctpStreamId',                    'pack' => 'unsigned16'),
	260 => array('name' => 'maxExportSeconds',                      'pack' => 'dateTimeSeconds'),
	261 => array('name' => 'maxFlowEndSeconds',                     'pack' => 'dateTimeSeconds'),
	262 => array('name' => 'messageMD5Checksum',                    'pack' => 'octetArray'),
	263 => array('name' => 'messageScope',                          'pack' => 'unsigned8'),
	264 => array('name' => 'minExportSeconds',                      'pack' => 'dateTimeSeconds'),
	265 => array('name' => 'minFlowStartSeconds',                   'pack' => 'dateTimeSeconds'),
	266 => array('name' => 'opaqueOctets',                          'pack' => 'octetArray'),
	267 => array('name' => 'sessionScope',                          'pack' => 'unsigned8'),
	268 => array('name' => 'maxFlowEndMicroseconds',                'pack' => 'dateTimeMicroseconds'),
	269 => array('name' => 'maxFlowEndMilliseconds',                'pack' => 'dateTimeMilliseconds'),
	270 => array('name' => 'maxFlowEndNanoseconds',                 'pack' => 'dateTimeNanoseconds'),
	271 => array('name' => 'minFlowStartMicroseconds',              'pack' => 'dateTimeMicroseconds'),
	272 => array('name' => 'minFlowStartMilliseconds',              'pack' => 'dateTimeMilliseconds'),
	273 => array('name' => 'minFlowStartNanoseconds',               'pack' => 'dateTimeNanoseconds'),
	274 => array('name' => 'collectorCertificate',                  'pack' => 'octetArray'),
	275 => array('name' => 'exporterCertificate',                   'pack' => 'octetArray'),
	276 => array('name' => 'dataRecordsReliability',                'pack' => 'boolean'),
	277 => array('name' => 'observationPointType',                  'pack' => 'unsigned8'),
	278 => array('name' => 'newConnectionDeltaCount',               'pack' => 'unsigned32'),
	279 => array('name' => 'connectionSumDurationSeconds',          'pack' => 'unsigned64'),
	280 => array('name' => 'connectionTransactionId',               'pack' => 'unsigned64'),
	281 => array('name' => 'postNATSourceIPv6Address',              'pack' => 'ipv6Address'),
	282 => array('name' => 'postNATDestinationIPv6Address',         'pack' => 'ipv6Address'),
	283 => array('name' => 'natPoolId',                             'pack' => 'unsigned32'),
	284 => array('name' => 'natPoolName',                           'pack' => 'string'),
	285 => array('name' => 'anonymizationFlags',                    'pack' => 'unsigned16'),
	286 => array('name' => 'anonymizationTechnique',                'pack' => 'unsigned16'),
	287 => array('name' => 'informationElementIndex',               'pack' => 'unsigned16'),
	288 => array('name' => 'p2pTechnology',                         'pack' => 'string'),
	289 => array('name' => 'tunnelTechnology',                      'pack' => 'string'),
	290 => array('name' => 'encryptedTechnology',                   'pack' => 'string'),
	291 => array('name' => 'basicList',                             'pack' => 'basicList'),
	292 => array('name' => 'subTemplateList',                       'pack' => 'subTemplateList'),
	293 => array('name' => 'subTemplateMultiList',                  'pack' => 'subTemplateMultiList'),
	294 => array('name' => 'bgpValidityState',                      'pack' => 'unsigned8'),
	295 => array('name' => 'IPSecSPI',                              'pack' => 'unsigned32'),
	296 => array('name' => 'greKey',                                'pack' => 'unsigned32'),
	297 => array('name' => 'natType',                               'pack' => 'unsigned8'),
	298 => array('name' => 'initiatorPackets',                      'pack' => 'unsigned64'),
	299 => array('name' => 'responderPackets',                      'pack' => 'unsigned64'),
	300 => array('name' => 'observationDomainName',                 'pack' => 'string'),
	301 => array('name' => 'selectionSequenceId',                   'pack' => 'unsigned64'),
	302 => array('name' => 'selectorId',                            'pack' => 'unsigned64'),
	303 => array('name' => 'informationElementId',                  'pack' => 'unsigned16'),
	304 => array('name' => 'selectorAlgorithm',                     'pack' => 'unsigned16'),
	305 => array('name' => 'samplingPacketInterval',                'pack' => 'unsigned32'),
	306 => array('name' => 'samplingPacketSpace',                   'pack' => 'unsigned32'),
	307 => array('name' => 'samplingTimeInterval',                  'pack' => 'unsigned32'),
	308 => array('name' => 'samplingTimeSpace',                     'pack' => 'unsigned32'),
	309 => array('name' => 'samplingSize',                          'pack' => 'unsigned32'),
	310 => array('name' => 'samplingPopulation',                    'pack' => 'unsigned32'),
	311 => array('name' => 'samplingProbability',                   'pack' => 'float64'),
	312 => array('name' => 'dataLinkFrameSize',                     'pack' => 'unsigned16'),
	313 => array('name' => 'ipHeaderPacketSection',                 'pack' => 'octetArray'),
	314 => array('name' => 'ipPayloadPacketSection',                'pack' => 'octetArray'),
	315 => array('name' => 'dataLinkFrameSection',                  'pack' => 'octetArray'),
	316 => array('name' => 'mplsLabelStackSection',                 'pack' => 'octetArray'),
	317 => array('name' => 'mplsPayloadPacketSection',              'pack' => 'octetArray'),
	318 => array('name' => 'selectorIdTotalPktsObserved',           'pack' => 'unsigned64'),
	319 => array('name' => 'selectorIdTotalPktsSelected',           'pack' => 'unsigned64'),
	320 => array('name' => 'absoluteError',                         'pack' => 'float64'),
	321 => array('name' => 'relativeError',                         'pack' => 'float64'),
	322 => array('name' => 'observationTimeSeconds',                'pack' => 'dateTimeSeconds'),
	323 => array('name' => 'observationTimeMilliseconds',           'pack' => 'dateTimeMilliseconds'),
	324 => array('name' => 'observationTimeMicroseconds',           'pack' => 'dateTimeMicroseconds'),
	325 => array('name' => 'observationTimeNanoseconds',            'pack' => 'dateTimeNanoseconds'),
	326 => array('name' => 'digestHashValue',                       'pack' => 'unsigned64'),
	327 => array('name' => 'hashIPPayloadOffset',                   'pack' => 'unsigned64'),
	328 => array('name' => 'hashIPPayloadSize',                     'pack' => 'unsigned64'),
	329 => array('name' => 'hashOutputRangeMin',                    'pack' => 'unsigned64'),
	330 => array('name' => 'hashOutputRangeMax',                    'pack' => 'unsigned64'),
	331 => array('name' => 'hashSelectedRangeMin',                  'pack' => 'unsigned64'),
	332 => array('name' => 'hashSelectedRangeMax',                  'pack' => 'unsigned64'),
	333 => array('name' => 'hashDigestOutput',                      'pack' => 'boolean'),
	334 => array('name' => 'hashInitialiserValue',                  'pack' => 'unsigned64'),
	335 => array('name' => 'selectorName',                          'pack' => 'string'),
	336 => array('name' => 'upperCILimit',                          'pack' => 'float64'),
	337 => array('name' => 'lowerCILimit',                          'pack' => 'float64'),
	338 => array('name' => 'confidenceLevel',                       'pack' => 'float64'),
	339 => array('name' => 'informationElementDataType',            'pack' => 'unsigned8'),
	340 => array('name' => 'informationElementDescription',         'pack' => 'string'),
	341 => array('name' => 'informationElementName',                'pack' => 'string'),
	342 => array('name' => 'informationElementRangeBegin',          'pack' => 'unsigned64'),
	343 => array('name' => 'informationElementRangeEnd',            'pack' => 'unsigned64'),
	344 => array('name' => 'informationElementSemantics',           'pack' => 'unsigned8'),
	345 => array('name' => 'informationElementUnits',               'pack' => 'unsigned16'),
	346 => array('name' => 'privateEnterpriseNumber',               'pack' => 'unsigned32'),
	347 => array('name' => 'virtualStationInterfaceId',             'pack' => 'octetArray'),
	348 => array('name' => 'virtualStationInterfaceName',           'pack' => 'string'),
	349 => array('name' => 'virtualStationUUID',                    'pack' => 'octetArray'),
	350 => array('name' => 'virtualStationName',                    'pack' => 'string'),
	351 => array('name' => 'layer2SegmentId',                       'pack' => 'unsigned64'),
	352 => array('name' => 'layer2OctetDeltaCount',                 'pack' => 'unsigned64'),
	353 => array('name' => 'layer2OctetTotalCount',                 'pack' => 'unsigned64'),
	354 => array('name' => 'ingressUnicastPacketTotalCount',        'pack' => 'unsigned64'),
	355 => array('name' => 'ingressMulticastPacketTotalCount',      'pack' => 'unsigned64'),
	356 => array('name' => 'ingressBroadcastPacketTotalCount',      'pack' => 'unsigned64'),
	357 => array('name' => 'egressUnicastPacketTotalCount',         'pack' => 'unsigned64'),
	358 => array('name' => 'egressBroadcastPacketTotalCount',       'pack' => 'unsigned64'),
	359 => array('name' => 'monitoringIntervalStartMilliSeconds',   'pack' => 'dateTimeMilliseconds'),
	360 => array('name' => 'monitoringIntervalEndMilliSeconds',     'pack' => 'dateTimeMilliseconds'),
	361 => array('name' => 'portRangeStart',                        'pack' => 'unsigned16'),
	362 => array('name' => 'portRangeEnd',                          'pack' => 'unsigned16'),
	363 => array('name' => 'portRangeStepSize',                     'pack' => 'unsigned16'),
	364 => array('name' => 'portRangeNumPorts',                     'pack' => 'unsigned16'),
	365 => array('name' => 'staMacAddress',                         'pack' => 'macAddress'),
	366 => array('name' => 'staIPv4Address',                        'pack' => 'ipv4Address'),
	367 => array('name' => 'wtpMacAddress',                         'pack' => 'macAddress'),
	368 => array('name' => 'ingressInterfaceType',                  'pack' => 'unsigned32'),
	369 => array('name' => 'egressInterfaceType',                   'pack' => 'unsigned32'),
	370 => array('name' => 'rtpSequenceNumber',                     'pack' => 'unsigned16'),
	371 => array('name' => 'userName',                              'pack' => 'string'),
	372 => array('name' => 'applicationCategoryName',               'pack' => 'string'),
	373 => array('name' => 'applicationSubCategoryName',            'pack' => 'string'),
	374 => array('name' => 'applicationGroupName',                  'pack' => 'string'),
	375 => array('name' => 'originalFlowsPresent',                  'pack' => 'unsigned64'),
	376 => array('name' => 'originalFlowsInitiated',                'pack' => 'unsigned64'),
	377 => array('name' => 'originalFlowsCompleted',                'pack' => 'unsigned64'),
	378 => array('name' => 'distinctCountOfSourceIPAddress',        'pack' => 'unsigned64'),
	379 => array('name' => 'distinctCountOfDestinationIPAddress',   'pack' => 'unsigned64'),
	380 => array('name' => 'distinctCountOfSourceIPv4Address',      'pack' => 'unsigned32'),
	381 => array('name' => 'distinctCountOfDestinationIPv4Address', 'pack' => 'unsigned32'),
	382 => array('name' => 'distinctCountOfSourceIPv6Address',      'pack' => 'unsigned64'),
	383 => array('name' => 'distinctCountOfDestinationIPv6Address', 'pack' => 'unsigned64'),
	384 => array('name' => 'valueDistributionMethod',               'pack' => 'unsigned8'),
	385 => array('name' => 'rfc3550JitterMilliseconds',             'pack' => 'unsigned32'),
	386 => array('name' => 'rfc3550JitterMicroseconds',             'pack' => 'unsigned32'),
	387 => array('name' => 'rfc3550JitterNanoseconds',              'pack' => 'unsigned32'),
	388 => array('name' => 'dot1qDEI',                              'pack' => 'boolean'),
	389 => array('name' => 'dot1qCustomerDEI',                      'pack' => 'boolean'),
	390 => array('name' => 'flowSelectorAlgorithm',                 'pack' => 'unsigned16'),
	391 => array('name' => 'flowSelectedOctetDeltaCount',           'pack' => 'unsigned64'),
	392 => array('name' => 'flowSelectedPacketDeltaCount',          'pack' => 'unsigned64'),
	393 => array('name' => 'flowSelectedFlowDeltaCount',            'pack' => 'unsigned64'),
	394 => array('name' => 'selectorIDTotalFlowsObserved',          'pack' => 'unsigned64'),
	395 => array('name' => 'selectorIDTotalFlowsSelected',          'pack' => 'unsigned64'),
	396 => array('name' => 'samplingFlowInterval',                  'pack' => 'unsigned64'),
	397 => array('name' => 'samplingFlowSpacing',                   'pack' => 'unsigned64'),
	398 => array('name' => 'flowSamplingTimeInterval',              'pack' => 'unsigned64'),
	399 => array('name' => 'flowSamplingTimeSpacing',               'pack' => 'unsigned64'),
	400 => array('name' => 'hashFlowDomain',                        'pack' => 'unsigned16'),
	401 => array('name' => 'transportOctetDeltaCount',              'pack' => 'unsigned64'),
	402 => array('name' => 'transportPacketDeltaCount',             'pack' => 'unsigned64'),
	403 => array('name' => 'originalExporterIPv4Address',           'pack' => 'ipv4Address'),
	404 => array('name' => 'originalExporterIPv6Address',           'pack' => 'ipv6Address'),
	405 => array('name' => 'originalObservationDomainId',           'pack' => 'unsigned32'),
	406 => array('name' => 'intermediateProcessId',                 'pack' => 'unsigned32'),
	407 => array('name' => 'ignoredDataRecordTotalCount',           'pack' => 'unsigned64'),
	408 => array('name' => 'dataLinkFrameType',                     'pack' => 'unsigned16'),
	409 => array('name' => 'sectionOffset',                         'pack' => 'unsigned16'),
	410 => array('name' => 'sectionExportedOctets',                 'pack' => 'unsigned16'),
	411 => array('name' => 'dot1qServiceInstanceTag',               'pack' => 'octetArray'),
	412 => array('name' => 'dot1qServiceInstanceId',                'pack' => 'unsigned32'),
	413 => array('name' => 'dot1qServiceInstancePriority',          'pack' => 'unsigned8'),
	414 => array('name' => 'dot1qCustomerSourceMacAddress',         'pack' => 'macAddress'),
	415 => array('name' => 'dot1qCustomerDestinationMacAddress',    'pack' => 'macAddress'),
	416 => array('name' => 'deprecated',                            'pack' => 'unsigned64'),
	417 => array('name' => 'postLayer2OctetDeltaCount',             'pack' => 'unsigned64'),
	418 => array('name' => 'postMCastLayer2OctetDeltaCount',        'pack' => 'unsigned64'),
	419 => array('name' => 'deprecated',                            'pack' => 'unsigned64'),
	420 => array('name' => 'postLayer2OctetTotalCount',             'pack' => 'unsigned64'),
	421 => array('name' => 'postMCastLayer2OctetTotalCount',        'pack' => 'unsigned64'),
	422 => array('name' => 'minimumLayer2TotalLength',              'pack' => 'unsigned64'),
	423 => array('name' => 'maximumLayer2TotalLength',              'pack' => 'unsigned64'),
	424 => array('name' => 'droppedLayer2OctetDeltaCount',          'pack' => 'unsigned64'),
	425 => array('name' => 'droppedLayer2OctetTotalCount',          'pack' => 'unsigned64'),
	426 => array('name' => 'ignoredLayer2OctetTotalCount',          'pack' => 'unsigned64'),
	427 => array('name' => 'notSentLayer2OctetTotalCount',          'pack' => 'unsigned64'),
	428 => array('name' => 'layer2OctetDeltaSumOfSquares',          'pack' => 'unsigned64'),
	429 => array('name' => 'layer2OctetTotalSumOfSquares',          'pack' => 'unsigned64'),
	430 => array('name' => 'layer2FrameDeltaCount',                 'pack' => 'unsigned64'),
	431 => array('name' => 'layer2FrameTotalCount',                 'pack' => 'unsigned64'),
	432 => array('name' => 'pseudoWireDestinationIPv4Address',      'pack' => 'ipv4Address'),
	433 => array('name' => 'ignoredLayer2FrameTotalCount',          'pack' => 'unsigned64'),
	434 => array('name' => 'mibObjectValueInteger',                 'pack' => 'signed32'),
	435 => array('name' => 'mibObjectValueOctetString',             'pack' => 'octetArray'),
	436 => array('name' => 'mibObjectValueOID',                     'pack' => 'octetArray'),
	437 => array('name' => 'mibObjectValueBits',                    'pack' => 'octetArray'),
	438 => array('name' => 'mibObjectValueIPAddress',               'pack' => 'ipv4Address'),
	439 => array('name' => 'mibObjectValueCounter',                 'pack' => 'unsigned64'),
	440 => array('name' => 'mibObjectValueGauge',                   'pack' => 'unsigned32'),
	441 => array('name' => 'mibObjectValueTimeTicks',               'pack' => 'unsigned32'),
	442 => array('name' => 'mibObjectValueUnsigned',                'pack' => 'unsigned32'),
	443 => array('name' => 'mibObjectValueTable',                   'pack' => 'subTemplateList'),
	444 => array('name' => 'mibObjectValueRow',                     'pack' => 'subTemplateList'),
	445 => array('name' => 'mibObjectIdentifier',                   'pack' => 'octetArray'),
	446 => array('name' => 'mibSubIdentifier',                      'pack' => 'unsigned32'),
	447 => array('name' => 'mibIndexIndicator',                     'pack' => 'unsigned64'),
	448 => array('name' => 'mibCaptureTimeSemantics',               'pack' => 'unsigned8'),
	449 => array('name' => 'mibContextEngineID',                    'pack' => 'octetArray'),
	450 => array('name' => 'mibContextName',                        'pack' => 'string'),
	451 => array('name' => 'mibObjectName',                         'pack' => 'string'),
	452 => array('name' => 'mibObjectDescription',                  'pack' => 'string'),
	453 => array('name' => 'mibObjectSyntax',                       'pack' => 'string'),
	454 => array('name' => 'mibModuleName',                         'pack' => 'string'),
	455 => array('name' => 'mobileIMSI',                            'pack' => 'string'),
	456 => array('name' => 'mobileMSISDN',                          'pack' => 'string'),
	457 => array('name' => 'httpStatusCode',                        'pack' => 'unsigned16'),
	458 => array('name' => 'sourceTransportPortsLimit',             'pack' => 'unsigned16'),
	459 => array('name' => 'httpRequestMethod',                     'pack' => 'string'),
	460 => array('name' => 'httpRequestHost',                       'pack' => 'string'),
	461 => array('name' => 'httpRequestTarget',                     'pack' => 'string'),
	462 => array('name' => 'httpMessageVersion',                    'pack' => 'string'),
	463 => array('name' => 'natInstanceID',                         'pack' => 'unsigned32'),
	464 => array('name' => 'internalAddressRealm',                  'pack' => 'octetArray'),
	465 => array('name' => 'externalAddressRealm',                  'pack' => 'octetArray'),
	466 => array('name' => 'natQuotaExceededEvent',                 'pack' => 'unsigned32'),
	467 => array('name' => 'natThresholdEvent',                     'pack' => 'unsigned32'),
	468 => array('name' => 'httpUserAgent',                         'pack' => 'string'),
	469 => array('name' => 'httpContentType',                       'pack' => 'string'),
	470 => array('name' => 'httpReasonPhrase',                      'pack' => 'string'),
	471 => array('name' => 'maxSessionEntries',                     'pack' => 'unsigned32'),
	472 => array('name' => 'maxBIBEntries',                         'pack' => 'unsigned32'),
	473 => array('name' => 'maxEntriesPerUser',                     'pack' => 'unsigned32'),
	474 => array('name' => 'maxSubscribers',                        'pack' => 'unsigned32'),
	475 => array('name' => 'maxFragmentsPendingReassembly',         'pack' => 'unsigned32'),
	476 => array('name' => 'addressPoolHighThreshold',              'pack' => 'unsigned32'),
	477 => array('name' => 'addressPoolLowThreshold',               'pack' => 'unsigned32'),
	478 => array('name' => 'addressPortMappingHighThreshold',       'pack' => 'unsigned32'),
	479 => array('name' => 'addressPortMappingLowThreshold',        'pack' => 'unsigned32'),
	480 => array('name' => 'addressPortMappingPerUserHighThreshold','pack' => 'unsigned32'),
	481 => array('name' => 'globalAddressMappingHighThreshold',     'pack' => 'unsigned32'),
	482 => array('name' => 'vpnIdentifier',                         'pack' => 'octetArray'),
	483 => array('name' => 'bgpCommunity',                          'pack' => 'unsigned32'),
	484 => array('name' => 'bgpSourceCommunityList',                'pack' => 'basicList'),
	485 => array('name' => 'bgpDestinationCommunityList',           'pack' => 'basicList'),
	486 => array('name' => 'bgpExtendedCommunity',                  'pack' => 'octetArray'),
	487 => array('name' => 'bgpSourceExtendedCommunityList',        'pack' => 'basicList'),
	488 => array('name' => 'bgpDestinationExtendedCommunityList',   'pack' => 'basicList'),
	489 => array('name' => 'bgpLargeCommunity',                     'pack' => 'octetArray'),
	490 => array('name' => 'bgpSourceLargeCommunityList',           'pack' => 'basicList'),
	491 => array('name' => 'bgpDestinationLargeCommunityList',      'pack' => 'basicList'),
	492 => array('name' => 'srhFlagsIPv6',                          'pack' => 'unsigned8'),
	493 => array('name' => 'srhTagIPv6',                            'pack' => 'unsigned16'),
	494 => array('name' => 'srhSegmentIPv6',                        'pack' => 'ipv6Address'),
	495 => array('name' => 'srhActiveSegmentIPv6',                  'pack' => 'ipv6Address'),
	496 => array('name' => 'srhSegmentIPv6BasicList',               'pack' => 'basicList'),
	497 => array('name' => 'srhSegmentIPv6ListSection',             'pack' => 'octetArray'),
	498 => array('name' => 'srhSegmentsIPv6Left',                   'pack' => 'unsigned8'),
	499 => array('name' => 'srhIPv6Section',                        'pack' => 'octetArray'),
	500 => array('name' => 'srhIPv6ActiveSegmentType',              'pack' => 'unsigned8'),
	501 => array('name' => 'srhSegmentIPv6LocatorLength',           'pack' => 'unsigned8'),
	502 => array('name' => 'srhSegmentIPv6EndpointBehavior',        'pack' => 'unsigned16'),
	503 => array('name' => 'transportChecksum',                     'pack' => 'unsigned16'),
	504 => array('name' => 'icmpHeaderPacketSection',               'pack' => 'octetArray'),
	505 => array('name' => 'gtpuFlags',                             'pack' => 'unsigned8'),
	506 => array('name' => 'gtpuMsgType',                           'pack' => 'unsigned8'),
	507 => array('name' => 'gtpuTEid',                              'pack' => 'unsigned32'),
	508 => array('name' => 'gtpuSequenceNum',                       'pack' => 'unsigned16'),
	509 => array('name' => 'gtpuQFI',                               'pack' => 'unsigned8'),
	510 => array('name' => 'gtpuPduType',                           'pack' => 'unsigned8'),
	511 => array('name' => 'bgpSourceAsPathList',                   'pack' => 'basicList'),
	512 => array('name' => 'bgpDestinationAsPathList',              'pack' => 'basicList'),
);

$partition = read_config_option('flowview_partition');

$listener  = flowview_db_fetch_row_prepared('SELECT *
	FROM plugin_flowview_device
	WHERE id = ?',
	array($listener_id));

flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_device_streams` (
	device_id int(11) unsigned NOT NULL default '0',
	ext_addr varchar(32) NOT NULL default '',
	name varchar(64) NOT NULL default '',
	version varchar(5) NOT NULL default '',
	last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (device_id, ext_addr))
	ENGINE=InnoDB,
	ROW_FORMAT=DYNAMIC,
	COMMENT='Plugin Flowview - List of Streams coming into each of the listeners'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_device_templates` (
	device_id int(11) unsigned NOT NULL default '0',
	ext_addr varchar(32) NOT NULL default '',
	template_id int(11) unsigned NOT NULL default '0',
	column_spec blob default '',
	last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (device_id, ext_addr, template_id))
	ENGINE=InnoDB,
	ROW_FORMAT=DYNAMIC,
	COMMENT='Plugin Flowview - List of Stream Templates coming into each of the listeners'");

if (cacti_sizeof($listener)) {
	/**
	 * Register the master process
	 */
	$taskname = 'child_' . $listener['id'];

	// 50 Years by default
	if (!register_process_start('flowview', $taskname, $config['poller_id'], 10 * 157680000)) {
		exit(0);
	}

	$previous_version    = -1;
	$refresh_seconds     = 300;
	$stream_refreshed    = array(); // We update the heartbeat every $refresh_seconds per peer
	$tmpl_refreshed      = array(); // We update the templates every $refresh_seconds per peer
	$lversion            = array(); // Track version changes
	$sstart              = time();

	flowview_db_execute_prepared("UPDATE `" . $flowviewdb_default . "`.`plugin_flowview_device`
		SET last_updated = NOW
		WHERE id = ?",
		array($listener['id']));

	while (true) {
		$socket = stream_socket_server('udp://0.0.0.0:' . $listener['port'], $errno, $errstr, STREAM_SERVER_BIND);

		if (!$socket) {
		    die("$errstr ($errno)");
		}

		while (true) {
			$p = stream_socket_recvfrom($socket, 1500, 0, $peer);

			if (!isset($stream_refreshed[$peer])) {
				$stream_refreshed[$peer] = false;
			}

			if (!isset($tmpl_refreshed[$peer])) {
				$tmpl_refreshed[$peer] = false;
			}

			if ($start > 0) {
				$end = microtime(true);
				debug('-----------------------------------------------');
				debug(sprintf('Flow: Sleep Time: %0.2f', $end - $start));
			}

			$start = microtime(true);

			if ($p !== false ) {
				$version = unpack('n', substr($p, 0, 2));

				if (isset($lversion[$peer]) && $version[1] != $lversion[$peer]) {
					debug('Flow: Detecting version change to v' . $version[1]);

					$templates        = array();
					$stream_refreshed[$peer] = false;
					$tmpl_refreshed[$peer]   = false;
					$lversion[$peer]         = $version[1];
				}

				if (!$stream_refreshed[$peer]) {
					update_flowview_streams($listener['id'], $peer, $version[1]);
					$stream_refreshed[$peer] = true;
				}

				debug("Flow: Packet from: $peer v" . $version[1] . " - Len: " . strlen($p));

				database_check_connect();

				if ($version[1] == 5) {
					process_fv5($p, $peer);

					$previous_version = 5;
				} elseif ($version[1] == 9) {
					process_fv9($p, $peer);

					$previous_version = 9;
				} elseif ($version[1] == 10) {
					process_fv10($p, $peer);

					$previous_version = 10;
				}

				$end = microtime(true);

				debug(sprintf('Flow: Cycle Time: %0.2f', $end - $start));

				$start = microtime(true);
			} else {
				break;
			}

			if (!$tmpl_refreshed[$peer] && isset($templates[$peer]) && cacti_sizeof($templates[$peer])) {
				foreach($templates[$peer] AS $template_id => $t) {
					flowview_db_execute_prepared("INSERT INTO `" . $flowviewdb_default . "`.`plugin_flowview_device_templates`
						(device_id, ext_addr, template_id, column_spec) VALUES (?, ?, ?, ?)
						ON DUPLICATE KEY UPDATE column_spec=VALUES(column_spec), last_updated=NOW()",
						array($listener['id'], $peer, $template_id, json_encode($t)));
				}

				$tmpl_refreshed[$peer] = true;
			}

			$send = time();

			if ($send - $sstart > $refresh_seconds) {
				$sstart = time();
				$stream_refreshed[$peer] = false;

				heartbeat_process('flowview', 'client_' . $listener['id'], $config['poller_id']);

				flowview_db_execute_prepared("UPDATE INTO `" . $flowviewdb_default . "`.`plugin_flowview_device`
					SET last_updated = NOW
					WHERE device_id = ?",
					array($listener['id']));
			}
		}
	}
}

exit(0);

function update_flowview_streams($listener_id, $peer, $version) {
	global $flowviewdb_default;

	if ($version == 5) {
		$db_version = 'v5';
	} elseif ($version == 9) {
		$db_version = 'v9';
	} elseif ($version == 10) {
		$db_version = 'IPFIX';
	}

	flowview_db_execute_prepared("INSERT INTO `" . $flowviewdb_default . "`.`plugin_flowview_device_streams`
		(device_id, ext_addr, version) VALUES (?, ?, ?)
		ON DUPLICATE KEY UPDATE version=VALUES(version),
			version=VALUES(version),
			last_updated=NOW()",
		array($listener_id, $peer, $db_version));

	flowview_db_execute_prepared("DELETE FROM `" . $flowviewdb_default . "`.`plugin_flowview_device_streams`
		WHERE device_id = ? AND last_updated < FROM_UNIXTIME(UNIX_TIMESTAMP()-86400)",
		array($listener_id));
}

function database_check_connect() {
	global $config;

	flowview_determine_config();

	include($config['include_path'] . '/config.php');

	$connection_good = flowview_db_fetch_cell('SELECT 1');

	if (empty($connection_good)) {
		flowview_db_close();

		while(true) {
			$db_conn = flowview_db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_retries, $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca);

			if (!is_object($db_conn)) {
				sleep(1);
			} else {
				break;
			}
		}
	}
}

function process_fv5($p, $peer) {
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
	$sql         = array();

	debug('Flow: Processing v5 Data, Records: ' . $records);

	for ($i = 0; $i < $records; $i++) {
		$flowrec = substr($p, $header_len + ($i * $flowrec_len), $flowrec_len);

		$data = unpack('C4src_addr/C4dst_addr/C4nexthop/nsrc_if/ndst_if/NdPkts/NdOctets/NFirst/NLast/nsrc_port/ndst_port/Cblank/Cflags/Cprotocol/Ctos/nsrc_as/ndst_as/Csrc_prefix/Cdst_prefix', $flowrec);

		$flowtime = $header['unix_secs'] + ($header['unix_nsecs'] / 1000000);

		$src_addr = $data['src_addr1'] . '.' . $data['src_addr2'] . '.' . $data['src_addr3'] . '.' . $data['src_addr4'];
		$dst_addr = $data['dst_addr1'] . '.' . $data['dst_addr2'] . '.' . $data['dst_addr3'] . '.' . $data['dst_addr4'];
		$nexthop  = $data['nexthop1']  . '.' . $data['nexthop2']  . '.' . $data['nexthop3']  . '.' . $data['nexthop4'];
		$ex_addr  = $peer;

		$rstime = ($data['First'] - $header['sysuptime']) / 1000;
		$rsmsec = substr($data['First'] - $header['sysuptime'], -3);
		$retime = ($data['Last'] - $header['sysuptime']) / 1000;
		$remsec = substr($data['Last'] - $header['sysuptime'], -3);

		$start_date = date('Y-m-d H:i:s', intval($flowtime + $rstime)) . '.' . $rsmsec;
		$end_date   = date('Y-m-d H:i:s', intval($flowtime + $retime)) . '.' . $remsec;

		//debug("Flow: Case 0 SysUptime:{$header['sysuptime']}, StartTime:{$data['First']}, StartDate:{$start_date}, EndTime:{$data['Last']}, EndDate:{$end_date}");

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
			db_qstr($header['engine_type']) . ', ' .
			db_qstr($header['engine_id'])   . ', ' .
			db_qstr($header['sample_int'])  . ', ' .
			db_qstr($ex_addr)               . ', ' .
			db_qstr($header['sysuptime'])   . ', ' .

			'INET6_ATON("' . db_qstr($src_addr) . '")' . ', ' .

			db_qstr($src_domain)            . ', ' .
			db_qstr($src_rdomain)           . ', ' .
			db_qstr($data['src_as'])        . ', ' .
			db_qstr($data['src_if'])        . ', ' .
			db_qstr($data['src_prefix'])    . ', ' .
			db_qstr($data['src_port'])      . ', ' .
			db_qstr($src_rport)             . ', ' .

			'INET6_ATON("' . db_qstr($dst_addr) . '")' . ', ' .

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

function process_fv9($p, $peer) {
	global $templates, $tlengths, $allfields, $pacmap;

	flowview_connect();

	if (!isset($templates[$peer])) {
		$templates[$peer] = array();
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
	$sql        = array();
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

					$templates[$peer][$tid] = array();

					for ($a = 0; $a < $fcount; $a++) {
						$field = substr($p, $h, 4);
						$field = unpack('nfield_id/nfield_len', $field);
						$tf    = array();

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

						$templates[$peer][$tid][] = $tf;
						$h += 4;
						$k += 4;
						$j++;
					}

					debug("Flow: Template Captured, Template:$tid Size: $tlength");

					$tlengths[$peer][$tid] = $tlength;
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
			if (cacti_sizeof($templates[$peer])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;
			$k   = 4;

			if (isset($templates[$peer][$tid])) {
				debug('Flow: Template Found: ' . $tid);

				while ($k < $fslen) {
					$data = array();

					foreach ($templates[$peer][$tid] as $t) {
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
						$result = process_v9_v10($data, $peer, $flowtime, $sysuptime);
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
					if ($remaining < $tlengths[$peer][$tid]) {
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

	return 'INSERT IGNORE INTO ' . $table . ' (listener_id, engine_type, engine_id, sampling_interval, ex_addr, sysuptime, src_addr, src_domain, src_rdomain, src_as, src_if, src_prefix, src_port, src_rport, dst_addr, dst_domain, dst_rdomain, dst_as, dst_if, dst_prefix, dst_port, dst_rport, nexthop, protocol, start_time, end_time, flows, packets, bytes, bytes_ppacket, tos, flags) VALUES ';
}

function process_fv10($p, $peer) {
	global $templates, $tlengths, $allfields, $pacmap;

	flowview_connect();

	if (!isset($templates[$peer])) {
		$templates[$peer] = array();
	}

	/* process header */
	$header_len = 16;
	$header     = unpack('nversion/ncount/Nexporttime/Nseq_num/Ndomainid', substr($p, 0, $header_len));

	/* prepare to process records */
	$count      = $header['count'];
	$i          = $header_len;
	$flowtime   = $header['exporttime'];
	$sql        = array();
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

					$templates[$peer][$tid] = array();

					for ($a = 0; $a < $fcount; $a++) {
						$field = substr($p, $h, 4);
						$field = unpack('nfield_id/nfield_len', $field);
						$tf    = array();

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

						$templates[$peer][$tid][] = $tf;
						$h += 4;
						$k += 4;
					}

					debug("Flow: Template Captured, Template:$tid Size: $tlength");

					$tlengths[$peer][$tid] = $tlength;
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
			if (cacti_sizeof($templates[$peer])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;
			$k   = 4;
			$j   = 0;

			if (isset($templates[$peer][$tid])) {
				debug('Flow: Template Found: ' . $tid);

				while ($k < $fslen) {
					$data = array();

					foreach ($templates[$peer][$tid] as $t) {
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
						$result = process_v9_v10($data, $peer, $flowtime);
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
					if ($remaining < $tlengths[$peer][$tid]) {
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

function process_v9_v10($data, $peer, $flowtime, $sysuptime = 0) {
	global $listener_id, $partition;

	$fieldname = array(
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
		'dst_if'            => 10,
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

	$flows = 1;

	if (isset($data[$fieldname['src_addr_ipv6']])) {
		$src_addr   = $data[$fieldname['src_addr_ipv6']];

		if (isset($data[$fieldname['src_prefix_ipv6']])) {
			$src_prefix = $data[$fieldname['src_prefix_ipv6']];
		} else {
			$src_prefix = 0;
		}
	} elseif (isset($data[$fieldname['src_addr']])) {
		$src_addr   = $data[$fieldname['src_addr']];

		if (isset($data[$fieldname['src_prefix']])) {
			$src_prefix = $data[$fieldname['src_prefix']];
		} else {
			$src_prefix = 0;
		}
	} else {
		cacti_log('The Source Address is not set', false, 'FLOWVIEW');
		return false;
	}

	if (isset($data[$fieldname['dst_addr_ipv6']])) {
		$dst_addr = $data[$fieldname['dst_addr_ipv6']];

		if (isset($data[$fieldname['dst_prefix_ipv6']])) {
			$dst_prefix = $data[$fieldname['dst_prefix_ipv6']];
		} else {
			$dst_prefix = 0;
		}
	} elseif (isset($data[$fieldname['dst_addr']])) {
		$dst_addr = $data[$fieldname['dst_addr']];

		if (isset($data[$fieldname['dst_prefix']])) {
			$dst_prefix = $data[$fieldname['dst_prefix']];
		} else {
			$dst_prefix = 0;
		}
	} else {
		cacti_log('The Destination Address is not set', false, 'FLOWVIEW');
		return false;
	}

	if (isset($data[$fieldname['nexthop_ipv6']])) {
		$nexthop = $data[$fieldname['nexthop_ipv6']];
	} elseif (isset($data[$fieldname['nexthop']])) {
		$nexthop = $data[$fieldname['nexthop']];
	} else {
		$nexthop = '';
	}

	if (isset($data[$fieldname['sysuptime']]) && abs($data[$fieldname['end_time']] - $data[$fieldname['sysuptime']]) < 3) {
		$rstime = ($data[$fieldname['start_time']] - $data[$fieldname['sysuptime']]) / 1000;
		$rsmsec = substr($data[$fieldname['start_time']] - $data[$fieldname['sysuptime']], -3);
		$retime = ($data[$fieldname['end_time']] - $data[$fieldname['sysuptime']]) / 1000;
		$remsec = substr($data[$fieldname['end_time']] - $data[$fieldname['sysuptime']], -3);

		$start_time = date('Y-m-d H:i:s.v', intval($flowtime + $rstime)) . '.' . $rsmsec;
		$end_time   = date('Y-m-d H:i:s.v', intval($flowtime + $retime)) . '.' . $remsec;
		$sysuptime = $data[$fieldname['sysuptime']];

		//debug("Flow: Case 3 SysUptime:{$sysuptime}, StartTime:{$data[$fieldname['start_time']]}, StartDate:{$start_date}, EndTime:{$data[$fieldname['end_time']]}, EndDate:{$end_date}");
	} elseif ($sysuptime > 0) {
		$rsmsec = $rstime = $remsec = $retime = 0;

		if (isset($data[$fieldname['start_time']])) {
			$rstime = ($data[$fieldname['start_time']] - $sysuptime) / 1000;
			$rsmsec = substr('000' . ($data[$fieldname['start_time']] - $sysuptime), -3);
		}

		if (isset($data[$fieldname['end_time']])) {
			$retime = ($data[$fieldname['end_time']] - $sysuptime) / 1000;
			$remsec = substr('000' . ($data[$fieldname['end_time']] - $sysuptime), -3);
		}

		$start_date = date('Y-m-d H:i:s', intval($flowtime + $rstime)) . '.' . $rsmsec;
		$end_date   = date('Y-m-d H:i:s', intval($flowtime + $retime)) . '.' . $remsec;

		//debug("Flow: Case 2 SysUptime:{$sysuptime}, StartTime:{$data[$fieldname['start_time']]}, StartDate:{$start_date}, EndTime:{$data[$fieldname['end_time']]}, EndDate:{$end_date}");
	} else {
		if (isset($data[$fieldname['start_time']]) && isset($data[$fieldname['end_time']])) {
			$delta_milli = intval(($data[$fieldname['end_time']] - $data[$fieldname['start_time']]) / 1000);
			$delta_sec   = floor($data[$fieldname['end_time']] - $data[$fieldname['start_time']]);
		} else {
			$delta_milli = $delta_sec = 0;
		}

		$start_date = date('Y-m-d H:i:s', intval($flowtime - $delta_sec)) . '.' . $delta_milli;
		$end_date   = date('Y-m-d H:i:s.v', intval($flowtime));

		//debug("Flow: Case 1 SysUptime:{$sysuptime}, StartTime:{$flowtime}, StartDate:{$start_date}, EndTime:{$flowtime}, EndDate:{$end_date}");
	}

	$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
	$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

	$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
	$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

	if (isset($data[$fieldname['src_port']])) {
		$src_rport = flowview_translate_port($data[$fieldname['src_port']], false, false);
	} else {
		$src_rport = 0;
	}

	if (isset($data[$fieldname['dst_port']])) {
		$dst_rport = flowview_translate_port($data[$fieldname['dst_port']], false, false);
	} else {
		$dst_rport = 0;
	}

	if (isset($data[$fieldname['dPkts']]) && $data[$fieldname['dPkts']] > 0) {
		$pps = round($data[$fieldname['dOctets']] / $data[$fieldname['dPkts']], 3);
	} else {
		$pps = 0;
	}

	$sql = '(' .
		$listener_id                                      . ', ' .
		check_set($data, $fieldname['engine_type'])       . ', ' .
		check_set($data, $fieldname['engine_id'])         . ', ' .
		check_set($data, $fieldname['sampling_interval']) . ', ' .
		db_qstr($peer)                                    . ', ' .
		$sysuptime                                        . ', ' .

		'INET6_ATON("' . $src_addr . '")'                 . ', ' .
		db_qstr($src_domain)                              . ', ' .
		db_qstr($src_rdomain)                             . ', ' .
		check_set($data, $fieldname['src_as'])            . ', ' .
		check_set($data, $fieldname['src_if'])            . ', ' .
		$src_prefix                                       . ', ' .
		check_set($data, $fieldname['src_port'])          . ', ' .
		db_qstr($src_rport)                               . ', ' .

		'INET6_ATON("' . $dst_addr . '")'                 . ', ' .
		db_qstr($dst_domain)                              . ', ' .
		db_qstr($dst_rdomain)                             . ', ' .
		check_set($data, $fieldname['dst_as'])            . ', ' .
		check_set($data, $fieldname['dst_if'])            . ', ' .
		$dst_prefix                                       . ', ' .
		check_set($data, $fieldname['dst_port'])          . ', ' .
		db_qstr($dst_rport)                               . ', ' .

		db_qstr($nexthop)                                 . ', ' .
		check_set($data, $fieldname['protocol'])          . ', ' .
		db_qstr($start_date)                              . ', ' .
		db_qstr($end_date)                                . ', ' .

		$flows                                            . ', ' .
		check_set($data, $fieldname['dPkts'])             . ', ' .
		check_set($data, $fieldname['dOctets'])           . ', ' .
		$pps                                              . ', ' .
		check_set($data, $fieldname['tos'])               . ', ' .
		check_set($data, $fieldname['flags'])             . ')';

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
	global $taskname, $config;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: Flowview Listener $taskname by signal", false, 'CLEANUP');

			unregister_process('flowview', $taskname, $config['poller_id'], getmypid());

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

