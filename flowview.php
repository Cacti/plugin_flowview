<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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

chdir('../../');
include_once("./include/auth.php");
include($config['base_path'] . '/plugins/flowview/functions.php');

ini_set("max_execution_time", 240);
ini_set("memory_limit", "256M");


flowview_display_form();

if (isset($_POST['action']) && $_POST['action'] == 'view' && !isset($_REQUEST['action2_x'])) {
	flowview_display_report();
}
include("./include/bottom_footer.php");

function display_tabs () {
	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='80%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Viewer') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview.php'>Viewer</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Devices') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview_devices.php'>Devices</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Schedules') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview_schedules.php'>Schedules</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td></td>\n</tr></table>\n";
}


function flowview_display_report() {
	global $config, $colors;

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$rname = '';
	if ($stat_report > 0)
		$rname = $stat_report_array[$stat_report];
	if ($print_report > 0)
		$rname = $print_report_array[$print_report];

	print '<br><br><center>';
	html_start_box("<strong>Report: $rname</strong>", "", $colors["header"], "3", "center", "");
	print "<tr><td><table width='100%'>";
	print '<tr><td><center>';


	$error = flowview_check_fields();
	if ($error != '') {
		print "<font color=red><strong>$error</strong></font>";
	} else {
		$filter = createfilter ();
		echo $filter;
	}

	print '</center></td></tr>';
	print "</table></td></tr>";
	html_end_box();
	?>
	<script language="JavaScript">
	function Sort(s) {
		document.flowview.sort_field.value = s;
	}
	</script>
	<?php
}

function flowview_display_form() {
	global $config, $colors;
	include_once($config['base_path'] . '/include/top_header.php');
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	print '<form action="' . $config['url_path'] . 'plugins/flowview/flowview.php" method=POST name=flowview>';

	print '<br><br><center>';
	display_tabs ();
	html_start_box("<strong>Flow Viewer</strong>", "80%", $colors["header"], "1", "center", "");
	print "<tr><td><table width='100%'>";

	?>
	<tr><td><b>Saved Query</b>:</td><td colspan=8>
	<?php draw_edit_control("query", $query_name_field); ?>

	<tr><td><b>Device</b>:</td><td colspan=8>
	<?php draw_edit_control("device_name", $device_name_field); ?>

	<tr><td><b>Start Date</b>:</td><td><input type=text size=10 name=start_date value='<?php echo $start_date; ?>'></td><td><b>Start Time:</td><td><input type=text size=8 name=start_time value='<?php echo $start_time; ?>'>  </b></td><td><b>TOS Fields:</td><td><input type=text size=10 name=tos_fields  value='<?php echo $tos_fields; ?>'></td><td colspan=2>(e.g., -0x0b/0x0F)</td></tr><b>
	<tr><td><b>End Date</b>:</td><td><input type=text size=10 name=end_date value='<?php echo $end_date; ?>'></td><td><b>End Time:</td><td><input type=text size=8 name=end_time value='<?php echo $end_time; ?>'>  </b></td><td><b>TCP Flags:</td><td><input type=text size=10 name=tcp_flags  value='<?php echo $tcp_flags; ?>'></td><td><b>Protocols</b>:</td><td><?php draw_edit_control("protocols", $ip_protocol_field); ?></td></tr>
	<tr><td><b>Source IP</b>:</td><td><input type=text size=19 name=source_address  value='<?php echo $source_address; ?>'></td><td><b>Source Port</b>:</td><td><input type=text size=5 name=source_port  value='<?php echo $source_port; ?>'></td><td><b>Source Interface</b>:</td><td><input type=text size=2 name=source_if  value='<?php echo $source_if; ?>'></td><td><b>Source AS</b>:</td><td><input type=text size=6 name=source_as  value='<?php echo $source_as; ?>'></td></tr>
	<tr><td><b>Dest IP</b>:</td><td><input type=text size=19 name=dest_address  value='<?php echo $dest_address; ?>'></td><td><b>Dest Port</b>:</td><td><input type=text size=5 name=dest_port  value='<?php echo $dest_port; ?>'></td><td><b>Dest Interface</b>:</td><td><input type=text size=2 name=dest_if  value='<?php echo $dest_if; ?>'></td><td><b>Dest AS</b>:</td><td><input type=text size=6 name=dest_as  value='<?php echo $dest_as; ?>'></td></tr>

	<tr><td colspan=9><center>Note: Multiple field entries, separated by commas, are permitted in the fields above.<br>
	A minus sign (-) will negate an entry (e.g. -80 for Port, would mean any Port but 80)<b></center><HR size=2></td></tr>
	<tr><td colspan=2><b>Reporting Parameters</b>:</td></tr>
	<tr><td><b>Statistics</b>:</td><td colspan=2>
	<?php draw_edit_control("stat_report", $stat_report_field); ?>
	</td><td><b>Printed</b>:</td><td colspan=2>
	<?php draw_edit_control("print_report", $print_report_field); ?>
	</td><td><b>Include if</b>:</td><td colspan=2>
	<?php draw_edit_control("flow_select", $flow_select_field); ?>
	</td></tr>
	<tr><td><b>Sort Field</b>:</td><td>
	<?php
	//	<input type=text size=3 name=sort_field  value='$sort_field'>
	print "<select id=sort_field name=sort_field></select>";

	?>
	</td><td><b>Cutoff Lines</b>:</td><td><input type=text size=3 name=cutoff_lines  value='<?php echo $cutoff_lines; ?>'></td><td><b>Cutoff Octets</b>:</td><td><input type=text size=13 name=cutoff_octets  value='<?php echo $cutoff_octets; ?>'></td><td><b>Resolve Addresses</b>:</td>
	<td>
	<?php draw_edit_control("resolve_addresses", $resolve_addresses_field); ?>
	</td></tr>
	<tr><td colspan=9><HR size=2></td></tr>
	<tr><td colspan=9>
	<input type='hidden' name='action' value='view'>
	<CENTER><input type=image name=action src='<?php echo $config['url_path']; ?>images/button_view.gif' value='view'>&nbsp;<a href='<?php echo $config['url_path']; ?>plugins/flowview/flowview.php'><img src='<?php echo $config['url_path']; ?>images/button_clear.gif' border=0></a>&nbsp;<input type=image name=action2 src='<?php echo $config['url_path']; ?>images/button_save.gif' value='save'>

	</CENTER></td></tr>

	<?php
	print "</table></td></tr>";
	html_end_box();

	if (isset($_REQUEST['action2_x']) && isset($_POST['queryname']) && $_POST['queryname'] != '') {
		$queryname = $_POST['queryname'];
		$queryname = form_input_validate($queryname, "queryname", "", false, 3);
		$sql = "INSERT INTO `plugin_flowview_queries` (`name` , `device` , `startdate` , `starttime` , `enddate` , `endtime` , `tosfields` , `tcpflags` , `protocols`, `sourceip` , `sourceport` , `sourceinterface` , `sourceas` , `destip` , `destport` , `destinterface` , `destas` , `statistics` , `printed` , `includeif` , `sortfield` , `cutofflines` , `cutoffoctets` , `resolve` )
			VALUES (
			'$queryname', '$device', '$start_date', '$start_time', '$end_date', '$end_time', '$tos_fields', '$tcp_flags', '$protocols', '$source_address', '$source_port', '$source_if', '$source_as', '$dest_address', '$dest_port', '$dest_if', '$dest_as', $stat_report, $print_report, $flow_select, $sort_field, $cutoff_lines, '$cutoff_octets', '$resolve_addresses')";
		db_execute($sql);
		echo "<center>Query '<b>$queryname</b>' has been saved.<center>";
	} else if (isset($_REQUEST['action2_x']) && isset($_POST['query']) && $_POST['query'] != '') {
		$queryname = $_POST['query'];
		input_validate_input_number($queryname);
		$sql = "UPDATE `plugin_flowview_queries` set `device` = '$device', `startdate` = '$start_date', `starttime` = '$start_time', `enddate` = '$end_date', `endtime` = '$end_time', 
			 `tosfields` = '$tos_fields', `tcpflags` = '$tcp_flags', `protocols` = '$protocols', `sourceip` = '$source_address', `sourceport` = '$source_port', `sourceinterface` = '$source_if', `sourceas` = '$source_as', `destip` = '$dest_address',
			 `destport` = '$dest_port', `destinterface` = '$dest_if', `destas` = '$dest_as', `statistics` = $stat_report, `printed` = $print_report, `includeif` = $flow_select, `sortfield` = $sort_field, `cutofflines` = $cutoff_lines, `cutoffoctets` = '$cutoff_octets', `resolve` = '$resolve_addresses'
			 WHERE `id` = $queryname";
		db_execute($sql);
		echo "<center>Query has been updated.<center>";
	} else if (isset($_REQUEST['action2_x'])) {
		print '<br><br>';
		html_start_box("<strong></strong>", "30%", $colors["header"], "1", "center", "");
		print '<tr><td><b>Query Name</b>:</td><td>';
		draw_edit_control("queryname", $query_newname_field);
		print "&nbsp;&nbsp;&nbsp;&nbsp;<input type=image name=action src='" . $config['url_path'] . "images/button_save.gif' value='save'>";
		print '</td></tr>';
		html_end_box();
	}

	print '</FORM>';

	?>
	<script type="text/javascript">
	<!--
	function StatSelect() {
		stat = document.flowview.stat_report;
		statval = stat.options[stat.selectedIndex].value;
		SetStatOption(stat.value);
		if (statval > 0) {
			document.flowview.print_report.selectedIndex = 0;
		}
		if (statval == 99 || statval < 1) {
			document.flowview.cutoff_octets.disabled = 1;
			document.flowview.sort_field.disabled = 1;
			document.flowview.cutoff_lines.disabled = 1;
		} else {
			document.flowview.cutoff_octets.disabled = 0;
			document.flowview.sort_field.disabled = 0;
			document.flowview.cutoff_lines.disabled = 0;
		}
	}

	function PrintSelect() {
		stat = document.flowview.print_report;
		statval = stat.options[stat.selectedIndex].value;
		if (statval > 0) {
			document.flowview.stat_report.selectedIndex = 0;
			document.flowview.sort_field.disabled = 1;
		} else {
			return;
		}
		if (statval == 4 || statval == 5) {
			document.flowview.cutoff_octets.disabled = 0;
			document.flowview.cutoff_lines.disabled = 0;
		} else {
			document.flowview.cutoff_octets.disabled = 1;
			document.flowview.cutoff_lines.disabled = 1;
		}
	}

	function QuerySelect() {
		stat = document.flowview.query;
		statval = stat.options[stat.selectedIndex].value;
		if (statval > 0) {
			window.location = "flowview.php?action=loadquery&query=" + statval;
		}
	}

	function SetStatOption(choose) {
		stat = document.flowview.sort_field;
		stat.options.length = 0;
		defsort = 1;
		if (choose == 10) {
			stat.options[stat.options.length] = new Option('Source IP', '1');
			stat.options[stat.options.length] = new Option('Destination IP', '2');
			stat.options[stat.options.length] = new Option('Flows', '3');
			stat.options[stat.options.length] = new Option('Bytes', '4');
			stat.options[stat.options.length] = new Option('Packets', '5');
			defsort = 4;
		} else if (choose == 5 || choose == 6 || choose == 7) {
			stat.options[stat.options.length] = new Option('Port', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 8 || choose == 9 || choose == 11) {
			stat.options[stat.options.length] = new Option('IP', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 12) {
			stat.options[stat.options.length] = new Option('Protocol', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 17 || choose == 18) {
			stat.options[stat.options.length] = new Option('Interface', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 23) {
			stat.options[stat.options.length] = new Option('Input Interface', '1');
			stat.options[stat.options.length] = new Option('Output Interface', '2');
			stat.options[stat.options.length] = new Option('Flows', '3');
			stat.options[stat.options.length] = new Option('Bytes', '4');
			stat.options[stat.options.length] = new Option('Packets', '5');
			defsort = 4;
		} else if (choose == 19 || choose == 20) {
			stat.options[stat.options.length] = new Option('AS', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 21) {
			stat.options[stat.options.length] = new Option('Source AS', '1');
			stat.options[stat.options.length] = new Option('Destination AS', '2');
			stat.options[stat.options.length] = new Option('Flows', '3');
			stat.options[stat.options.length] = new Option('Bytes', '4');
			stat.options[stat.options.length] = new Option('Packets', '5');
			defsort = 4;
		} else if (choose == 22) {
			stat.options[stat.options.length] = new Option('TOS', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 24 || choose == 25) {
			stat.options[stat.options.length] = new Option('Prefix', '1');
			stat.options[stat.options.length] = new Option('Flows', '2');
			stat.options[stat.options.length] = new Option('Bytes', '3');
			stat.options[stat.options.length] = new Option('Packets', '4');
			defsort = 3;
		} else if (choose == 26) {
			stat.options[stat.options.length] = new Option('Source Prefix', '1');
			stat.options[stat.options.length] = new Option('Destination Prefix', '2');
			stat.options[stat.options.length] = new Option('Flows', '3');
			stat.options[stat.options.length] = new Option('Bytes', '4');
			stat.options[stat.options.length] = new Option('Packets', '5');
			defsort = 4;
		} else {

		}

		if (statreport == choose) {
			stat.value = sortfield;
		} else {
			stat.value = defsort;
		}

	}

	var sortfield = '<?php echo $sort_field; ?>';
	var statreport = '<?php echo ($stat_report > 0 ? $stat_report : 0); ?>';

	StatSelect();
	PrintSelect();

	document.flowview.stat_report.onchange = StatSelect;
	document.flowview.print_report.onchange = PrintSelect;
	document.flowview.query.onchange = QuerySelect;

	-->
	</script>

	<?php
}


