<?php

chdir('../../');
include_once("./include/auth.php");
include_once("./include/config.php");
include($config['base_path'] . '/plugins/flowview/functions.php');

ini_set("max_execution_time", 240);

flowview_display_form();

if (isset($_POST['action']) && $_POST['action'] == 'view') {
	flowview_display_report();
}
include("./include/bottom_footer.php");

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
	global $config;
	include_once($config['base_path'] . '/include/top_header.php');
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	print '<br><br><center>';
	html_start_box("<strong>Flow Viewer</strong>", "80%", $colors["header"], "3", "center", "");
	print "<tr><td><table width='100%'>";

	print '<form action="' . $config['url_path'] . 'plugins/flowview/flowview.php" method=POST name=flowview>';
//	print '<form action="' . $config['url_path'] . 'plugins/flowview/scripts/FlowViewer.cgi" method=POST name=flowview>';

	?>
	<tr><td><b>Device</b>:</td><td colspan=8>
	<?php draw_edit_control("device_name", $device_name_field); ?>

	<tr><td><b>Start Date</b>:</td><td><input type=text size=10 name=start_date value='<?php echo $start_date; ?>'></td><td><b>Start Time:</td><td><input type=text size=8 name=start_time value='<?php echo $start_time; ?>'>  </b></td><td><b>TOS Fields:</td><td><input type=text size=10 name=tos_fields  value='<?php echo $tos_fields; ?>'></td><td colspan=2>(e.g., -0x0b/0x0F)</td></tr><b>
	<tr><td><b>End Date</b>:</td><td><input type=text size=10 name=end_date value='<?php echo $end_date; ?>'></td><td><b>End Time:</td><td><input type=text size=8 name=end_time value='<?php echo $end_time; ?>'>  </b></td><td><b>TCP Flags:</td><td><input type=text size=10 name=tcp_flags  value='<?php echo $tcp_flags; ?>'></td><td><b>Protocols</b>:</td><td><input type=text size=6 name=protocols value='<?php echo $protocols; ?>'></td></tr>
	<tr><td><b>Source IP</b>:</td><td><input type=text size=19 name=source_address  value='<?php echo $source_address; ?>'></td><td><b>Source Port</b>:</td><td><input type=text size=5 name=source_port  value='<?php echo $source_port; ?>'></td><td><b>Source Interface</b>:</td><td><input type=text size=2 name=source_if  value='<?php echo $source_if; ?>'></td><td><b>Source AS</b>:</td><td><input type=text size=6 name=source_as  value='<?php echo $source_as; ?>'></td></tr>
	<tr><td><b>Dest IP</b>:</td><td><input type=text size=19 name=dest_address  value='<?php echo $dest_address; ?>'></td><td><b>Dest Port</b>:</td><td><input type=text size=5 name=dest_port  value='<?php echo $dest_port; ?>'></td><td><b>Dest Interface</b>:</td><td><input type=text size=2 name=dest_if  value='<?php echo $dest_if; ?>'></td><td><b>Dest AS</b>:</td><td><input type=text size=6 name=dest_as  value='<?php echo $dest_as; ?>'></td></tr>

	<tr><td colspan=9><center>Note: Multiple field entries, separated by commas, are permitted in the fields above.<br>
	A minus sign (-) will negate an entry (e.g. -1776 for AS, would mean any AS but 1776)<b></center><HR size=2></td></tr>
	<tr><td colspan=2><b>Reporting Parameters</b>:</td></tr>
	<tr><td><b>Statistics</b>:</td><td colspan=2>
	<?php draw_edit_control("stat_report", $stat_report_field); ?>
	</td><td><b>Printed</b>:</td><td colspan=2>
	<?php draw_edit_control("print_report", $print_report_field); ?>
	</td><td><b>Include if</b>:</td><td colspan=2>
	<?php draw_edit_control("flow_select", $flow_select_field); ?>
	</td></tr>
	<tr><td><b>Sort Field</b>:</td><td><input type=text size=3 name=sort_field  value='<?php echo $sort_field; ?>'></td><td><b>Cutoff Lines</b>:</td><td><input type=text size=3 name=cutoff_lines  value='<?php echo $cutoff_lines; ?>'></td><td><b>Cutoff Octets</b>:</td><td><input type=text size=13 name=cutoff_octets  value='<?php echo $cutoff_octets; ?>'></td><td><b>Resolve Addresses</b>:</td>
	<td>
	<?php draw_edit_control("resolve_addresses", $resolve_addresses_field); ?>
	</td></tr>
	<tr><td colspan=9><HR size=2></td></tr>
	<tr><td colspan=9>
	<input type='hidden' name='action' value='view'>
	<CENTER><input type=image name=action src='<?php echo $config['url_path']; ?>images/button_view.gif' value='view'>&nbsp;<a href='<?php echo $config['url_path']; ?>plugins/flowview/flowview.php'><img src='<?php echo $config['url_path']; ?>images/button_clear.gif' border=0>
	</CENTER></FORM></td></tr>

	<?php
	print "</table></td></tr>";
	html_end_box();

	?>
	<script language="JavaScript">
	function StatSelect() {
		stat = document.flowview.stat_report;
		statval = stat.options[stat.selectedIndex].value;
		if (statval > 0) {
			document.flowview.print_report.selectedIndex = 0;
		} else {
			return;
		}
		if (statval == 99) {
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

	StatSelect();
	PrintSelect();
	document.flowview.stat_report.onchange = StatSelect;
	document.flowview.print_report.onchange = PrintSelect;
	</script>
	<?php
}
?>