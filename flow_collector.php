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

chdir(__DIR__);
include('../../include/cli_check.php');
include_once('./functions.php');

$debug     = false;
$lversion  = array();

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

$templates = array();
$start     = 0;

$allfields = array(
	1  =>  array('name' => 'octetDeltaCount',                  'pack' => ''),
	2  =>  array('name' => 'packetDeltaCount',                 'pack' => ''),
	3  =>  array('name' => 'deltaFlowCount',                   'pack' => ''),
	4  =>  array('name' => 'protocolIdentifier',               'pack' => 'C'),
	5  =>  array('name' => 'ipClassOfService',                 'pack' => 'C'),
	6  =>  array('name' => 'tcpControlBits',                   'pack' => 'C'),
	7  =>  array('name' => 'sourceTransportPort',              'pack' => ''),
	8  =>  array('name' => 'sourceIPv4Address',                'pack' => 'C4'),
	9  =>  array('name' => 'sourceIPv4PrefixLength',           'pack' => ''),
	10 =>  array('name' => 'ingressInterface',                 'pack' => ''),
	11 =>  array('name' => 'destinationTransportPort',         'pack' => ''),
	12 =>  array('name' => 'destinationIPv4Address',           'pack' => 'C4'),
	13 =>  array('name' => 'destinationIPv4PrefixLength',      'pack' => ''),
	14 =>  array('name' => 'egressInterface',                  'pack' => ''),
	15 =>  array('name' => 'ipNextHopIPv4Address',             'pack' => 'C4'),
	16 =>  array('name' => 'bgpSourceAsNumber',                'pack' => ''),
	17 =>  array('name' => 'bgpDestinationAsNumber',           'pack' => ''),
	18 =>  array('name' => 'bgpNexthopIPv4Address',            'pack' => 'C4'),
	19 =>  array('name' => 'postMCastPacketDeltaCount',        'pack' => ''),
	20 =>  array('name' => 'postMCastOctetDeltaCount',         'pack' => ''),
	21 =>  array('name' => 'flowEndSysUpTime',                 'pack' => ''),
	22 =>  array('name' => 'flowStartSysUpTime',               'pack' => ''),
	23 =>  array('name' => 'postOctetDeltaCount',              'pack' => ''),
	24 =>  array('name' => 'postPacketDeltaCount',             'pack' => ''),
	25 =>  array('name' => 'minimumIpTotalLength',             'pack' => ''),
	26 =>  array('name' => 'maximumIpTotalLength',             'pack' => ''),
	27 =>  array('name' => 'sourceIPv6Address',                'pack' => 'n8'),
	28 =>  array('name' => 'destinationIPv6Address',           'pack' => 'n8'),
	29 =>  array('name' => 'sourceIPv6PrefixLength',           'pack' => ''),
	30 =>  array('name' => 'destinationIPv6PrefixLength',      'pack' => ''),
	31 =>  array('name' => 'flowLabelIPv6',                    'pack' => ''),
	32 =>  array('name' => 'icmpTypeCodeIPv4',                 'pack' => ''),
	33 =>  array('name' => 'igmpType',                         'pack' => ''),
	34 =>  array('name' => 'samplingInterval',                 'pack' => ''),
	35 =>  array('name' => 'samplingAlgorithm',                'pack' => ''),
	36 =>  array('name' => 'flowActiveTimeout',                'pack' => ''),
	37 =>  array('name' => 'flowIdleTimeout',                  'pack' => ''),
	38 =>  array('name' => 'engineType',                       'pack' => ''),
	39 =>  array('name' => 'engineId',                         'pack' => ''),
	40 =>  array('name' => 'exportedOctetTotalCount',          'pack' => ''),
	41 =>  array('name' => 'exportedMessageTotalCount',        'pack' => ''),
	42 =>  array('name' => 'exportedFlowRecordTotalCount',     'pack' => ''),
	44 =>  array('name' => 'sourceIPv4Prefix',                 'pack' => ''),
	45 =>  array('name' => 'destinationIPv4Prefix',            'pack' => ''),
	46 =>  array('name' => 'mplsTopLabelType',                 'pack' => ''),
	47 =>  array('name' => 'mplsTopLabelIPv4Address',          'pack' => 'C4'),
	48 =>  array('name' => 'samplerId',                        'pack' => ''),
	49 =>  array('name' => 'samplerMode',                      'pack' => ''),
	50 =>  array('name' => 'samplerRandomInterval',            'pack' => ''),
	51 =>  array('name' => 'classId',                          'pack' => ''),
	52 =>  array('name' => 'minimumTTL',                       'pack' => ''),
	53 =>  array('name' => 'maximumTTL',                       'pack' => ''),
	54 =>  array('name' => 'fragmentIdentification',           'pack' => ''),
	55 =>  array('name' => 'postIpClassOfService',             'pack' => ''),
	56 =>  array('name' => 'sourceMacAddress',                 'pack' => 'C6'),
	57 =>  array('name' => 'postDestinationMacAddress',        'pack' => ''),
	58 =>  array('name' => 'vlanId',                           'pack' => ''),
	59 =>  array('name' => 'postVlanId',                       'pack' => ''),
	60 =>  array('name' => 'ipVersion',                        'pack' => ''),
	61 =>  array('name' => 'flowDirection',                    'pack' => ''),
	62 =>  array('name' => 'ipNextHopIPv6Address',             'pack' => 'n8'),
	63 =>  array('name' => 'bgpNexthopIPv6Address',            'pack' => 'n8'),
	64 =>  array('name' => 'ipv6ExtensionHeaders',             'pack' => ''),

	70 =>  array('name' => 'mplsTopLabelStackSection',         'pack' => ''),
	71 =>  array('name' => 'mplsLabelStackSection2',           'pack' => ''),
	72 =>  array('name' => 'mplsLabelStackSection3',           'pack' => ''),
	73 =>  array('name' => 'mplsLabelStackSection4',           'pack' => ''),
	74 =>  array('name' => 'mplsLabelStackSection5',           'pack' => ''),
	75 =>  array('name' => 'mplsLabelStackSection6',           'pack' => ''),
	76 =>  array('name' => 'mplsLabelStackSection7',           'pack' => ''),
	77 =>  array('name' => 'mplsLabelStackSection8',           'pack' => ''),
	78 =>  array('name' => 'mplsLabelStackSection9',           'pack' => ''),
	79 =>  array('name' => 'mplsLabelStackSection10',          'pack' => ''),
	80 =>  array('name' => 'destinationMacAddress',            'pack' => 'C6'),
	81 =>  array('name' => 'postSourceMacAddress',             'pack' => 'C6'),
	82 =>  array('name' => 'interfaceName',                    'pack' => ''),
	83 =>  array('name' => 'interfaceDescription',             'pack' => ''),
	84 =>  array('name' => 'samplerName',                      'pack' => ''),
	85 =>  array('name' => 'octetTotalCount',                  'pack' => ''),
	86 =>  array('name' => 'packetTotalCount',                 'pack' => ''),
	87 =>  array('name' => 'flagsAndSamplerId',                'pack' => ''),
	88 =>  array('name' => 'fragmentOffset',                   'pack' => ''),
	89 =>  array('name' => 'forwardingStatus',                 'pack' => ''),
	90 =>  array('name' => 'mplsVpnRouteDistinguisher',        'pack' => ''),
	91 =>  array('name' => 'mplsTopLabelPrefixLength',         'pack' => ''),
	92 =>  array('name' => 'srcTrafficIndex',                  'pack' => ''),
	93 =>  array('name' => 'dstTrafficIndex',                  'pack' => ''),
	94 =>  array('name' => 'applicationDescription',           'pack' => ''),
	95 =>  array('name' => 'applicationId',                    'pack' => ''),
	96 =>  array('name' => 'applicationName',                  'pack' => ''),
	97 =>  array('name' => 'Assigned For V9 Compatibility',    'pack' => ''),
	98 =>  array('name' => 'postIpDiffServCodePoint',          'pack' => ''),
	99 =>  array('name' => 'multicastReplicationFactor',       'pack' => ''),
	100 =>  array('name' => 'className',                       'pack' => ''),
	101 =>  array('name' => 'classificationEngineId',          'pack' => ''),
	102 =>  array('name' => 'layer2packetSectionOffset',       'pack' => ''),
	103 =>  array('name' => 'layer2packetSectionSize',         'pack' => ''),
	104 =>  array('name' => 'layer2packetSectionData',         'pack' => ''),

	128 => array('name' => 'bgpNextAdjacentAsNumber',          'pack' => ''),
	129 => array('name' => 'bgpPrevAdjacentAsNumber',          'pack' => ''),
	130 => array('name' => 'exporterIPv4Address',              'pack' => 'C4'),
	131 => array('name' => 'exporterIPv6Address',              'pack' => 'n8'),
	132 => array('name' => 'droppedOctetDeltaCount',           'pack' => ''),
	133 => array('name' => 'droppedPacketDeltaCount',          'pack' => ''),
	134 => array('name' => 'droppedOctetTotalCount',           'pack' => ''),
	135 => array('name' => 'droppedPacketTotalCount',          'pack' => ''),
	136 => array('name' => 'flowEndReason',                    'pack' => ''),
	137 => array('name' => 'commonPropertiesId',               'pack' => ''),
	138 => array('name' => 'observationPointId',               'pack' => ''),
	139 => array('name' => 'icmpTypeCodeIPv6',                 'pack' => ''),
	140 => array('name' => 'mplsTopLabelIPv6Address',          'pack' => ''),
	141 => array('name' => 'lineCardId',                       'pack' => ''),
	142 => array('name' => 'portId',                           'pack' => ''),
	143 => array('name' => 'meteringProcessId',                'pack' => ''),
	144 => array('name' => 'exportingProcessId',               'pack' => ''),
	145 => array('name' => 'templateId',                       'pack' => ''),
	146 => array('name' => 'wlanChannelId',                    'pack' => ''),
	147 => array('name' => 'wlanSSID',                         'pack' => ''),
	148 => array('name' => 'flowId',                           'pack' => ''),
	149 => array('name' => 'observationDomainId',              'pack' => ''),
	150 => array('name' => 'flowStartSeconds',                 'pack' => ''),
	151 => array('name' => 'flowEndSeconds',                   'pack' => ''),
	152 => array('name' => 'flowStartMilliseconds',            'pack' => ''),
	153 => array('name' => 'flowEndMilliseconds',              'pack' => ''),
	154 => array('name' => 'flowStartMicroseconds',            'pack' => ''),
	155 => array('name' => 'flowEndMicroseconds',              'pack' => ''),
	156 => array('name' => 'flowStartNanoseconds',             'pack' => ''),
	157 => array('name' => 'flowEndNanoseconds',               'pack' => ''),
	158 => array('name' => 'flowStartDeltaMicroseconds',       'pack' => ''),
	159 => array('name' => 'flowEndDeltaMicroseconds',         'pack' => ''),
	160 => array('name' => 'systemInitTimeMilliseconds',       'pack' => ''),
	161 => array('name' => 'flowDurationMilliseconds',         'pack' => ''),
	162 => array('name' => 'flowDurationMicroseconds',         'pack' => ''),
	163 => array('name' => 'observedFlowTotalCount',           'pack' => ''),
	164 => array('name' => 'ignoredPacketTotalCount',          'pack' => ''),
	165 => array('name' => 'ignoredOctetTotalCount',           'pack' => ''),
	166 => array('name' => 'notSentFlowTotalCount',            'pack' => ''),
	167 => array('name' => 'notSentPacketTotalCount',          'pack' => ''),
	168 => array('name' => 'notSentOctetTotalCount',           'pack' => ''),
	169 => array('name' => 'destinationIPv6Prefix',            'pack' => ''),
	170 => array('name' => 'sourceIPv6Prefix',                 'pack' => ''),
	171 => array('name' => 'postOctetTotalCount',              'pack' => ''),
	172 => array('name' => 'postPacketTotalCount',             'pack' => ''),
	173 => array('name' => 'flowKeyIndicator',                 'pack' => ''),
	174 => array('name' => 'postMCastPacketTotalCount',        'pack' => ''),
	175 => array('name' => 'postMCastOctetTotalCount',         'pack' => ''),
	176 => array('name' => 'icmpTypeIPv4',                     'pack' => ''),
	177 => array('name' => 'icmpCodeIPv4',                     'pack' => ''),
	178 => array('name' => 'icmpTypeIPv6',                     'pack' => ''),
	179 => array('name' => 'icmpCodeIPv6',                     'pack' => ''),
	180 => array('name' => 'udpSourcePort',                    'pack' => ''),
	181 => array('name' => 'udpDestinationPort',               'pack' => ''),
	182 => array('name' => 'tcpSourcePort',                    'pack' => ''),
	183 => array('name' => 'tcpDestinationPort',               'pack' => ''),
	184 => array('name' => 'tcpSequenceNumber',                'pack' => ''),
	185 => array('name' => 'tcpAcknowledgementNumber',         'pack' => ''),
	186 => array('name' => 'tcpWindowSize',                    'pack' => ''),
	187 => array('name' => 'tcpUrgentPointer',                 'pack' => ''),
	188 => array('name' => 'tcpHeaderLength',                  'pack' => ''),
	189 => array('name' => 'ipHeaderLength',                   'pack' => ''),
	190 => array('name' => 'totalLengthIPv4',                  'pack' => ''),
	191 => array('name' => 'payloadLengthIPv6',                'pack' => ''),
	192 => array('name' => 'ipTTL',                            'pack' => ''),
	193 => array('name' => 'nextHeaderIPv6',                   'pack' => ''),
	194 => array('name' => 'mplsPayloadLength',                'pack' => ''),
	195 => array('name' => 'ipDiffServCodePoint',              'pack' => ''),
	196 => array('name' => 'ipPrecedence',                     'pack' => ''),
	197 => array('name' => 'fragmentFlags',                    'pack' => ''),
	198 => array('name' => 'octetDeltaSumOfSquares',           'pack' => ''),
	199 => array('name' => 'octetTotalSumOfSquares',           'pack' => ''),
	200 => array('name' => 'mplsTopLabelTTL',                  'pack' => ''),
	201 => array('name' => 'mplsLabelStackLength',             'pack' => ''),
	202 => array('name' => 'mplsLabelStackDepth',              'pack' => ''),
	203 => array('name' => 'mplsTopLabelExp',                  'pack' => ''),
	204 => array('name' => 'ipPayloadLength',                  'pack' => ''),
	205 => array('name' => 'udpMessageLength',                 'pack' => ''),
	206 => array('name' => 'isMulticast',                      'pack' => ''),
	207 => array('name' => 'ipv4IHL',                          'pack' => ''),
	208 => array('name' => 'ipv4Options',                      'pack' => ''),
	209 => array('name' => 'tcpOptions',                       'pack' => ''),
	210 => array('name' => 'paddingOctets',                    'pack' => ''),
	211 => array('name' => 'collectorIPv4Address',             'pack' => 'C4'),
	212 => array('name' => 'collectorIPv6Address',             'pack' => 'n8'),
	213 => array('name' => 'exportInterface',                  'pack' => ''),
	214 => array('name' => 'exportProtocolVersion',            'pack' => ''),
	215 => array('name' => 'exportTransportProtocol',          'pack' => ''),
	216 => array('name' => 'collectorTransportPort',           'pack' => ''),
	217 => array('name' => 'exporterTransportPort',            'pack' => ''),
	218 => array('name' => 'tcpSynTotalCount',                 'pack' => ''),
	219 => array('name' => 'tcpFinTotalCount',                 'pack' => ''),
	220 => array('name' => 'tcpRstTotalCount',                 'pack' => ''),
	221 => array('name' => 'tcpPshTotalCount',                 'pack' => ''),
	222 => array('name' => 'tcpAckTotalCount',                 'pack' => ''),
	223 => array('name' => 'tcpUrgTotalCount',                 'pack' => ''),
	224 => array('name' => 'ipTotalLength',                    'pack' => ''),
	225 => array('name' => 'postNATSourceIPv4Address',         'pack' => 'C4'),
	226 => array('name' => 'postNATDestinationIPv4Address',    'pack' => 'C4'),
	227 => array('name' => 'postNAPTSourceTransportPort',      'pack' => ''),
	228 => array('name' => 'postNAPTDestinationTransportPort', 'pack' => ''),
	229 => array('name' => 'natOriginatingAddressRealm',       'pack' => ''),
	230 => array('name' => 'natEvent',                         'pack' => ''),
	231 => array('name' => 'initiatorOctets',                  'pack' => ''),
	232 => array('name' => 'responderOctets',                  'pack' => ''),
	233 => array('name' => 'firewallEvent',                    'pack' => ''),
	234 => array('name' => 'ingressVRFID',                     'pack' => ''),
	235 => array('name' => 'egressVRFID',                      'pack' => ''),
	236 => array('name' => 'VRFname',                          'pack' => ''),
	237 => array('name' => 'postMplsTopLabelExp',              'pack' => ''),
	238 => array('name' => 'tcpWindowScale',                   'pack' => '')
);

$lens = array(
	1 => 'C',
	2 => 'n',
	4 => 'N',
	8 => 'N2',
	12 => 'N3'
);

$partition = read_config_option('flowview_partition');

$listener  = db_fetch_row_prepared('SELECT *
	FROM plugin_flowview_devices
	WHERE id = ?',
	array($listener_id));

if (cacti_sizeof($listener)) {
	while (true) {
		$socket = stream_socket_server('udp://0.0.0.0:' . $listener['port'], $errno, $errstr, STREAM_SERVER_BIND);

		if (!$socket) {
		    die("$errstr ($errno)");
		}


		while (true) {
			$p = stream_socket_recvfrom($socket, 1500, 0, $peer);
			if ($start > 0) {
				$end = microtime(true);
				debug('-----------------------------------------------');
				debug(sprintf('Flow: Sleep Time: %0.2f', $end - $start));
			}

			$start = microtime(true);

			if ($p !== false ) {
				$version = unpack('n', substr($p, 0, 2));

				if (isset($lversion[$peer]) && $version[1] != $lversion[$peer]) {
					$templates = array();
					$lversion[$peer] = $version[1];
					debug('Flow: Detecting version change');
				}

				debug("Flow: Packet from: $peer v" . $version[1] . " - Len: " . strlen($p));

				if ($version[1] == 5) {
					process_fv5($p, $peer);
				} elseif ($version[1] == 9) {
					process_fv9($p, $peer);
				} elseif ($version[1] == 10) {
					process_fv10($p, $peer);
				}

				$end = microtime(true);

				debug(sprintf('Flow: Cycle Time: %0.2f', $end - $start));

				$start = microtime(true);
			} else {
				break;
			}
		}
	}
}

exit(0);

function process_fv5($p, $peer) {
	global $listener_id;

	$header_len  = 24;
	$flowrec_len = 48;

	$header = unpack('nversion/ncount/Nsysuptime/Nunix_secs/Nunix_nsecs/Nflow_sequence/Cengine_type/Cengine_id/nsample_int', substr($p, 0, 24));
	$count = $header['count'];
	$flows = 1;
	$sql   = array();

	debug('Flow: Processing v5 Data, Len: ' . $count);

	for ($i = 0; $i < $count; $i++) {
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

		$start_time = date('Y-m-d H:i:s', $flowtime + $rstime) . '.' . $rsmsec;
		$end_time   = date('Y-m-d H:i:s', $flowtime + $retime) . '.' . $remsec;

		$sql_prefix = get_sql_prefix($flowtime);

		$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
		$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

		$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
		$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

		$src_rport  = flowview_translate_port($data['src_port'], false, false);
		$dst_rport  = flowview_translate_port($data['dst_port'], false, false);

		$pps = round($data['dOctets'] / $data['dPkts'], 3);

		$sql[] = '(' .
			$listener_id           . ', ' .
			$header['engine_type'] . ', ' .
			$header['engine_id']   . ', ' .
			$header['sample_int']  . ', ' .
			db_qstr($ex_addr)      . ', ' .
			$header['sysuptime']   . ', ' .

			'INET6_ATON("' . $src_addr . '")' . ', ' .
			db_qstr($src_domain)   . ', ' .
			db_qstr($src_rdomain)  . ', ' .
			$data['src_as']        . ', ' .
			$data['src_if']        . ', ' .
			$data['src_prefix']    . ', ' .
			$data['src_port']      . ', ' .
			db_qstr($src_rport)    . ', ' .

			'INET6_ATON("' . $dst_addr . '")' . ', ' .
			db_qstr($dst_domain)   . ', ' .
			db_qstr($dst_rdomain)  . ', ' .
			$data['dst_as']        . ', ' .
			$data['dst_if']        . ', ' .
			$data['dst_prefix']    . ', ' .
			$data['dst_port']      . ', ' .
			db_qstr($dst_rport)    . ', ' .

			db_qstr($nexthop)      . ', ' .
			$data['protocol']      . ', ' .
			db_qstr($start_time)   . ', ' .
			db_qstr($end_time)     . ', ' .

			$flows                 . ', ' .
			$data['dPkts']         . ', ' .
			$data['dOctets']       . ', ' .
			$pps                   . ', ' .
			$data['tos']           . ', ' .
			$data['flags']         . ')';
	}

	if (sizeof($sql)) {
		debug('Flow: Writing Records: ' . sizeof($sql));
		db_execute($sql_prefix . implode(' ,', $sql));
	}
}

function debug($string) {
	global $debug;

	if ($debug) {
		print trim($string) . PHP_EOL;
	}
}

function process_fv9($p, $peer) {
	global $templates, $allfields, $lens;

	if (!isset($templates[$peer])) {
		$templates[$peer] = array();
	}

	$header_len = 20;
	$header     = unpack('nversion/ncount/Nsysuptime/Nunix_seconds/Nseq_num/Nsource_id', substr($p, 0, $header_len));
	$records    = $header['count'];
	$i          = $header_len;
	$flowtime   = $header['unix_seconds'];
	$sysuptime  = $header['sysuptime'];
	$j          = 0;
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
			$k = 4;

			if ($fslen > 0) {
				debug('Flow: Template Found');
				debug('Flow: Template Len: ' . $fslen);

				while ($k < $fslen) {
					$theader = substr($p, $h, 4);
					$theader = unpack('ntemplate_id/nfieldcount', $theader);
					$tid     = $theader['template_id'];
					$fcount  = $theader['fieldcount'];
					$h += 4;
					$k += 4;

					debug('Flow: Template Id: ' . $tid . ' with ' . $fcount . ' fields');

					$templates[$peer][$tid] = array();

					for ($a = 0; $a < $fcount; $a++) {
						$field = substr($p, $h, 4);
						$field = unpack('nfield_id/nfield_len', $field);
						$tf    = array();
						$tf['field_id'] = $field['field_id'];
						$tf['length']   = $field['field_len'];

						if (($field['field_id'] & 32768)) {
							$tf['field_id']   = $field['field_id'] & ~32768;
							$tf['enterprise'] = 1;

							$entnum = substr($p, $h, 4);
							$entnum = unpack('Nentnum', $entnum);

							$tf['enterprise_number'] = $entnum['entnum'];
							$h += 4;
						} else {
							$tf['enterprise'] = 0;
						}

						if (isset($allfields[$tf['field_id']])) {
							$tf['name'] = $allfields[$tf['field_id']]['name'];
							if ($allfields[$tf['field_id']]['pack'] != '') {
								$tf['unpack'] = $allfields[$tf['field_id']]['pack'];
							} else {
								$tf['unpack'] = $lens[$tf['length']];
							}
						} else {
							$tf['name'] = 'Unknown';
							$tf['unpack'] = $lens[$tf['length']];
						}

						$templates[$peer][$tid][] = $tf;
						$h += 4;
						$k += 4;
					}
				}

				debug('Flow: Templates Captured');
				//print_r($templates);

				$i += $fslen;
				$j++;
			} else {
				$i += $fslen;
				$j++;
			}
		}

		// Option Set
		if ($fsid == 1) {
			debug('Flow: Options Found');

			$i += $fslen;
			$j++;
		}

		// Flow Data Set
		if ($fsid > 255) {
			if (sizeof($templates[$peer])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;
			$k   = 4;

			if (isset($templates[$peer][$tid])) {
				debug('Flow: Template Found: ' . $tid);
			}

			while ($k < $fslen) {
				if (isset($templates[$peer][$tid])) {
					$data = array();

					foreach ($templates[$peer][$tid] as $t) {
						$id = $t['field_id'];
						$field = substr($p, $h, $t['length']);
						$field = unpack($t['unpack'], $field);

						if ($t['unpack'] == 'C4') {
							$field = implode('.', $field);
						} elseif ($t['unpack'] == 'n8') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('0000' . dechex($v), -4);
							}

							$field = strtoupper($ofield);
						} elseif ($t['unpack'] == 'C6') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('00' . dechex($v), -2);
							}

							$field = strtoupper($ofield);
						} elseif (count($field) > 1) {
							$c = 0;
							$d = 1;

							for ($b = count($field); $b > 0; $b--) {
								$c += $field[$b] * $d;
								$d = $d * 256;
							}
							$field = $c;
						} else {
							$field = $field[1];
						}

						$h += $t['length'];
						$k += $t['length'];
						$data[$id] = $field;
					}

					$remaining = $fslen - $k;

					debug('Flow: Processed, Remaining: ' . $remaining);

					$result = false;
					$result = process_v9_v10($data, $peer, $flowtime, $sysuptime);

					if ($result !== false) {
						$sql[] = $result;
					} else {
						debug('Bad Record');
						//print_r($data);
					}

					if ($remaining <= 4) {
						$j = $records;
						break;
					}
				} else {
					$j = $records;
					break;
				}
			}
		}

		$i += $fslen;
		$j++;
	}

	if (sizeof($sql)) {
		debug('Flow: Writing Records: ' . sizeof($sql));
		db_execute($sql_prefix . implode(', ', $sql));
	}
}

function get_sql_prefix($flowtime) {
	global $partition;
	static $last_table = '';

	if ($partition == 0) {
		$suffix = date('Y', $flowtime) . substr('000' . date('z', $flowtime), -3);
	} else {
		$suffix = date('Y', $flowtime) . substr('000' . date('z', $flowtime), -3) . date('H', $flowtime);
	}

	$table  = 'plugin_flowview_raw_' . $suffix;

	if ($table != $last_table) {
		if (!db_table_exists($table)) {
			create_raw_partition($table);
		}
	}

	$last_table = $table;

	return 'INSERT IGNORE INTO ' . $table . ' (listener_id, engine_type, engine_id, sampling_interval, ex_addr, sysuptime, src_addr, src_domain, src_rdomain, src_as, src_if, src_prefix, src_port, src_rport, dst_addr, dst_domain, dst_rdomain, dst_as, dst_if, dst_prefix, dst_port, dst_rport, nexthop, protocol, start_time, end_time, flows, packets, bytes, bytes_ppacket, tos, flags) VALUES ';
}

function process_fv10($p, $peer) {
	global $templates, $allfields, $lens;

	if (!isset($templates[$peer])) {
		$templates[$peer] = array();
	}

	$header_len = 16;
	$header     = unpack('nversion/ncount/Nexporttime/Nseq_num/Ndomainid', substr($p, 0, $header_len));
	$count      = $header['count'];
	$i          = $header_len;
	$flowtime   = $header['exporttime'];
	$sql        = array();
	$sql_prefix = get_sql_prefix($flowtime);

	debug('Flow: Processing v10/IPFIX Data, Len: ' . $count);

	while ($i < $count) {
		$header = substr($p, $i, 4);
		$header = unpack('nflowset_id/nflowset_length', $header);
		$h      = $i + 4;
		$fsid   = $header['flowset_id'];
		$fslen  = $header['flowset_length'];

		// Template Set
		if ($fsid == 2) {
			debug('Flow: Template Found');

			$theader = substr($p, $h, 4);
			$theader = unpack('ntemplate_id/nfieldcount', $theader);
			$tid = $theader['template_id'];
			$fcount = $theader['fieldcount'];
			$h += 4;

			if (!isset($templates[$peer][$tid])) {
				$templates[$peer][$tid] = array();

				for ($a = 0; $a < $fcount; $a++) {
					$field = substr($p, $h, 4);
					$field = unpack('nfield_id/nfield_len', $field);
					$tf = array();
					$tf['field_id'] = $field['field_id'];
					$tf['length'] = $field['field_len'];

					if (($field['field_id'] & 32768)) {
						$tf['field_id'] = $field['field_id'] & ~32768;
						$tf['enterprise'] = 1;
						$entnum = substr($p, $h, 4);
						$entnum = unpack('Nentnum', $entnum);
						$tf['enterprise_number'] = $entnum['entnum'];
						$h += 4;
					} else {
						$tf['enterprise'] = 0;
					}

					if (isset($allfields[$tf['field_id']])) {
						$tf['name'] = $allfields[$tf['field_id']]['name'];
						if ($allfields[$tf['field_id']]['pack'] != '') {
							$tf['unpack'] = $allfields[$tf['field_id']]['pack'];
						} else {
							$tf['unpack'] = $lens[$tf['length']];
						}
					} else {
						$tf['name'] = 'Unknown';
						$tf['unpack'] = $lens[$tf['length']];
					}

					$templates[$peer][$tid][] = $tf;
					$h += 4;
				}

				debug('Flow: Template Captured');
				//print_r($templates);
			}

			$i += $fslen;

			debug('Flow: Flowset 2: Total bytes:' . $count . ', Current bytes:' . $i);
		}

		// Option Set
		if ($fsid == 3) {
			debug('Flow: Options Found');

			$i += $fslen;

			debug('Flow: Flowset 3: Total bytes:' . $count . ', Current bytes:' . $i);
		}

		// Data Set
		if ($fsid > 255) {
			if (sizeof($templates[$peer])) {
				debug('Flow: Data Found, Processing');
			} else {
				debug('Flow: Data Found, Awaiting Templates');
			}

			$tid = $fsid;

			if (isset($templates[$peer][$tid])) {
				while ($h < $fslen) {
					$data = array();

					foreach ($templates[$peer][$tid] as $t) {
						$id    = $t['field_id'];
						$field = substr($p, $h, $t['length']);
						$field = unpack($t['unpack'], $field);

						if ($t['unpack'] == 'C4') {
							$field = implode('.', $field);
						} elseif ($t['unpack'] == 'n8') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('0000' . dechex($v), -4);
							}

							$field = strtoupper($ofield);
						} elseif ($t['unpack'] == 'C6') {
							$ofield = '';

							foreach($field as $v) {
								$ofield .= ($ofield != '' ? ':':'') . substr('00' . dechex($v), -2);
							}

							$field = strtoupper($ofield);
						} elseif (count($field) > 1) {
							$c = 0;
							$d = 1;

							for ($b = count($field); $b > 0; $b--) {
								$c += $field[$b] * $d;
								$d = $d * 256;
							}
							$field = $c;
						} else {
							$field = $field[1];
						}

						$h += $t['length'];
						$data[$id] = $field;
					}

					$result = process_v9_v10($data, $peer, $flowtime);

					if ($result !== false) {
						$sql[] = $result;
					} else {
						debug('Bad Record');
						print_r($data);
					}
				}
			}

			$i += $fslen;
		}
	}

	if (sizeof($sql)) {
		debug('Flow: Writing Records: ' . sizeof($sql));
		db_execute($sql_prefix . implode(', ', $sql));
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
		$src_prefix = $data[$fieldname['src_prefix_ipv6']];
	} elseif (isset($data[$fieldname['src_addr']])) {
		$src_addr   = $data[$fieldname['src_addr']];
		$src_prefix = $data[$fieldname['src_prefix']];
	} else {
		return false;
	}

	if (isset($data[$fieldname['dst_addr_ipv6']])) {
		$dst_addr   = $data[$fieldname['dst_addr_ipv6']];
		$dst_prefix = $data[$fieldname['dst_prefix_ipv6']];
	} else {
		$dst_addr   = $data[$fieldname['dst_addr']];
		$dst_prefix = $data[$fieldname['dst_prefix']];
	}

	if (isset($data[$fieldname['nexthop_ipv6']])) {
		$nexthop = $data[$fieldname['nexthop_ipv6']];
	} else {
		$nexthop = $data[$fieldname['nexthop']];
	}

	if (isset($data[$fieldname['sysuptime']])) {
		$rstime = ($data[$fieldname['start_time']] - $data[$fieldname['sysuptime']]) / 1000;
		$rsmsec = substr($data[$fieldname['start_time']] - $data[$fieldname['sysuptime']], -3);
		$retime = ($data[$fieldname['end_time']] - $data[$fieldname['sysuptime']]) / 1000;
		$remsec = substr($data[$fieldname['end_time']] - $data[$fieldname['sysuptime']], -3);

		$start_time = date('Y-m-d H:i:s', $flowtime + $rstime) . '.' . $rsmsec;
		$end_time   = date('Y-m-d H:i:s', $flowtime + $retime) . '.' . $remsec;
		$sysuptime = $data[$fieldname['sysuptime']];
	} elseif ($sysuptime > 0) {
		$rstime = ($data[$fieldname['start_time']] - $sysuptime) / 1000;
		$rsmsec = substr($data[$fieldname['start_time']] - $sysuptime, -3);
		$retime = ($data[$fieldname['end_time']] - $sysuptime) / 1000;
		$remsec = substr($data[$fieldname['end_time']] - $sysuptime, -3);

		$start_time = date('Y-m-d H:i:s', $flowtime + $rstime) . '.' . $rsmsec;
		$end_time   = date('Y-m-d H:i:s', $flowtime + $retime) . '.' . $remsec;
	} else {
		$start_time = date('Y-m-d H:i:s', $flowtime);
		$end_time   = date('Y-m-d H:i:s', $flowtime);
	}

	$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
	$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

	$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
	$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

	$src_rport  = flowview_translate_port($data[$fieldname['src_port']], false, false);
	$dst_rport  = flowview_translate_port($data[$fieldname['dst_port']], false, false);

	$pps = round($data[$fieldname['dOctets']] / $data[$fieldname['dPkts']], 3);

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
		db_qstr($start_time)                              . ', ' .
		db_qstr($end_time)                                . ', ' .

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

/*  display_version - displays version information */
function display_version() {
	$version = get_cacti_cli_version();
	print "Cacti Flow Capture Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

function display_help() {
	display_version();

	print PHP_EOL . "usage: flow_collector.php --listener-id=ID [--debug]" . PHP_EOL . PHP_EOL;

	print "Cacti utility receiving flow data over a socket." . PHP_EOL . PHP_EOL;

	print "Options:" . PHP_EOL;
	print "    --listener-id=ID  The listner-id to collect for." . PHP_EOL;
	print "    --debug           Provide some debug output during collection." . PHP_EOL . PHP_EOL;
}

