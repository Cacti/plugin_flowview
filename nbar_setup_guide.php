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

if (!function_exists('fv_nbar_h')) {
	http_response_code(403);
	exit;
}
?>
<dialog class="ui-dialog ui-widget ui-widget-content ui-corner-all" id="nbarSetupGuide" aria-labelledby="nbarSetupTitle"><div class="nbarGuideHeader ui-widget-header ui-corner-top"><strong id="nbarSetupTitle"><?php print html_escape(__('Setup guide — Cisco Apps (NBAR)', 'flowview')); ?></strong><button type="button" class="ui-button ui-corner-all ui-widget" id="nbarSetupClose"><?php print html_escape(__('Close', 'flowview')); ?></button></div><div class="nbarGuideBody"><h3><?php print html_escape(__('Requirements', 'flowview')); ?></h3><p><?php print html_escape(__('This configuration example is based on a Cisco 1100 Series ISR installation using NBAR2, Flexible NetFlow and application export. For other router types, refer to Cisco documentation for your exact router model and IOS XE release for supported commands, features and configuration requirements. Input/output monitoring and licence requirements vary by platform. Protocol packs must match their supported IOS XE release and NBAR engine. This guide does not apply router changes.', 'flowview')); ?></p><h3><?php print html_escape(__('1. FlowView listener', 'flowview')); ?></h3><p><?php print html_escape(__('Create and enable a listener with the correct UDP port and allowed router export-source address. Start or restart the capture service after adding a listener. Allow the UDP port through the collector firewall. The export source must reach the collector. An IPv4 exporter can carry both IPv4 and IPv6 records.', 'flowview')); ?></p><h3><?php print html_escape(__('2. Router configuration example', 'flowview')); ?></h3><p><?php print html_escape(__('Replace the red placeholders with your own values:', 'flowview')); ?> <strong style="color:#c62828">COLLECTOR_IP</strong> <?php print html_escape(__('is the Cacti collector address;', 'flowview')); ?> <strong style="color:#c62828">LISTENER_PORT</strong> <?php print html_escape(__('is the listener UDP port;', 'flowview')); ?> <strong style="color:#c62828">EXPORT_SOURCE</strong> <?php print html_escape(__('is a router interface whose address can reach the collector;', 'flowview')); ?> <strong style="color:#c62828">VlanXX</strong> <?php print html_escape(__('is the routed LAN interface to monitor. The export source identifies the router sending flow records; it does not choose which traffic is captured. It may differ from the monitored LAN VLAN. Set the FlowView listener to allow the chosen export-source IP. This example uses NetFlow v9 application records from a Cisco 1100 Series ISR. It omits optional routing metadata. Check Cisco documentation for defaults and supported record fields on your platform. The NBAR decoder currently supports application records exported using NetFlow v9; IPFIX application records are not decoded by this integration.', 'flowview')); ?></p><pre class="ui-widget-content ui-corner-all" style="overflow:auto;max-height:420px">flow record CACTI_NBAR4
 match ipv4 tos
 match ipv4 protocol
 match ipv4 source address
 match ipv4 destination address
 match transport source-port
 match transport destination-port
 match interface input
 match application name
 collect transport tcp flags
 collect interface output
 collect counter bytes long
 collect counter packets long
 collect timestamp sys-uptime first
 collect timestamp sys-uptime last
!
flow record CACTI_NBAR6
 match ipv6 traffic-class
 match ipv6 flow-label
 match ipv6 protocol
 match ipv6 extension map
 match ipv6 source address
 match ipv6 destination address
 match transport source-port
 match transport destination-port
 match interface input
 match flow direction
 match application name
 collect transport tcp flags
 collect interface output
 collect counter bytes long
 collect counter packets long
 collect timestamp sys-uptime first
 collect timestamp sys-uptime last
!
flow exporter CACTI_LAN
 destination <strong style="color:#c62828">COLLECTOR_IP</strong>
 source <strong style="color:#c62828">EXPORT_SOURCE</strong>
 transport udp <strong style="color:#c62828">LISTENER_PORT</strong>
 template data timeout 60
 option application-table timeout 60
!
flow monitor CACTI_APP4_LAN
 exporter CACTI_LAN
 cache timeout active 60
 record CACTI_NBAR4
!
flow monitor CACTI_APP6_LAN
 exporter CACTI_LAN
 cache timeout active 60
 record CACTI_NBAR6
!
interface <strong style="color:#c62828">VlanXX</strong>
 ip flow monitor CACTI_APP4_LAN input
 ip flow monitor CACTI_APP4_LAN output
 ipv6 flow monitor CACTI_APP6_LAN input
 ipv6 flow monitor CACTI_APP6_LAN output</pre><h3><?php print html_escape(__('3. Capture and report settings', 'flowview')); ?></h3><p><?php print html_escape(__('For example, replace', 'flowview')); ?> <strong style="color:#c62828">VlanXX</strong> <?php print html_escape(__('with', 'flowview')); ?> <code>Vlan1</code> <?php print html_escape(__('if that is the routed gateway interface for your LAN. Apply both the IPv4 and IPv6 monitors in input and output directions on that interface, as shown above. Input captures traffic arriving from that LAN towards the WAN; output captures traffic returning from the WAN to that LAN. This covers LAN-to-WAN traffic routed through that interface without also attaching the same monitors to the WAN. Use your actual routed LAN interface or VLAN interface; Vlan1 is an example, not a requirement. Traffic routed between VLANs can also cross this interface, so the capture is not limited to Internet traffic. Locally switched traffic that does not cross the router is outside this capture. Avoid combining LAN and WAN copies of the same traffic. For combined routers, select up to three captures, normally one LAN capture per router.', 'flowview')); ?></p><p><?php print html_escape(__('Top applications and Connections use exported addresses directly. Conversations needs internal IPv4 and IPv6 networks in Options to distinguish Internal Hosts from External Hosts. Update delegated IPv6 prefixes when they change; retain old prefixes while viewing their history.', 'flowview')); ?></p><h3><?php print html_escape(__('4. Verify collection', 'flowview')); ?></h3><p><?php print html_escape(__('Use your monitor and exporter names. Generate traffic and check both caches and export counters. Allow 1-2 minutes for exports to appear. Check QFP load during a speed test; overhead depends on platform, traffic and capture count.', 'flowview')); ?></p><pre class="ui-widget-content ui-corner-all" style="overflow:auto">show ip nbar version
show ip nbar protocol-pack active
show flow interface
show flow exporter CACTI_LAN statistics
show flow monitor CACTI_APP4_LAN statistics
show flow monitor CACTI_APP6_LAN statistics
show flow monitor CACTI_APP6_LAN cache format table
show platform hardware qfp active datapath utilization
</pre><h3><?php print html_escape(__('Reading reports', 'flowview')); ?></h3><p><?php print html_escape(__('Average bandwidth covers the selected timespan, not an instantaneous or peak rate. Conversations combines both directions for each Internal Host, External Host and application. Classification comes from the router; encrypted traffic can remain SSL or QUIC. DNS names identify hosts, not the exact HTTPS page.', 'flowview')); ?></p><hr><small>Copyright (c) 2004-2026 The Cacti Group.</small></div></dialog>
<?php

print '<script src="' . plugin_flowview_escape_attr($config['url_path'] . 'plugins/flowview/js/nbar-setup-guide.js') . '"></script>';
