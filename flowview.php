<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2010 The Cacti Group                                 |
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

if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'save') {
	flowview_save_filter();
}elseif (isset($_REQUEST['action']) && $_REQUEST['action'] == 'delete') {
	flowview_delete_filter();
}elseif ((isset($_REQUEST['action']) && $_REQUEST['action'] == 'view') || (isset($_REQUEST['tab']) && $_REQUEST["tab"] == 'current')) {
	include_once("./plugins/flowview/general_header.php");
	flowview_display_report();
	include("./include/bottom_footer.php");
}else{
	include_once("./plugins/flowview/general_header.php");
	display_output_messages();
	flowview_display_form();
	include("./include/bottom_footer.php");
}

function flowview_delete_filter() {
	global $config, $colors;
	db_execute("DELETE FROM plugin_flowview_queries WHERE id=" . get_request_var_request('query'));
	raise_message('flow_deleted');
	header("Location: flowview.php");
	exit;
}

function flowview_save_filter() {
	global $config, $colors;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (isset($_POST['new_query']) && $_POST['new_query'] != '') {
		$queryname = $_POST['new_query'];

		$save['id']              = '';
		$save['name']            = form_input_validate($queryname, "queryname", "", false, 3);
	}else{
		$save['id']          = $_POST['query'];
	}

	$save['device']          = $device;
	$save['startdate']       = $start_date;
	$save['starttime']       = $start_time;
	$save['enddate']         = $end_date;
	$save['endtime']         = $end_time;
	$save['tosfields']       = $tos_fields;
	$save['tcpflags']        = $tcp_flags;
	$save['protocols']       = $protocols;
	$save['sourceip']        = $source_address;
	$save['sourceport']      = $source_port;
	$save['sourceinterface'] = $source_if;
	$save['sourceas']        = $source_as;
	$save['destip']          = $dest_address;
	$save['destport']        = $dest_port;
	$save['destinterface']   = $dest_if;
	$save['destas']          = $dest_as;
	$save['statistics']      = $stat_report;
	$save['printed']         = $print_report;
	$save['includeif']       = $flow_select;
	$save['sortfield']       = $sort_field;
	$save['cutofflines']     = $cutoff_lines;
	$save['cutoffoctets']    = $cutoff_octets;
	$save['resolve']         = $resolve_addresses;

	$id = sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message() || $id == '') {
		print "error";
	}else{
		print $id;
	}
}

function flowview_display_form() {
	global $config, $colors;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	print '<form id="flowview" action="' . $config['url_path'] . 'plugins/flowview/flowview.php" method="post" name="flowview">';
	display_tabs ();
	html_start_box("<strong>Flow Filter Constraints</strong>", "100%", $colors["header"], "1", "center", "");
	?>
	<tr>
		<td>
			<table border='0' cellspacing='0' cellpadding='1' width='100%'>
				<tr>
					<td>Saved Query:</td>
					<td><?php draw_edit_control("query", $query_name_field);?></td>
					<td>Listener:</td>
					<td><?php draw_edit_control("device_name", $device_name_field);?></td>
				</tr>
				<tr>
					<td>Start Date:</td>
					<td><input type='text' size='10' name='start_date' value='<?php echo $start_date; ?>'></td>
					<td>Start Time:</td><td><input type='text' size='8' name='start_time' value='<?php echo $start_time; ?>'></td>
					<td>TOS Fields:</td>
					<td><input type='text' size='10' name='tos_fields' value='<?php echo $tos_fields;?>'></td>
					<td colspan=2>(e.g., -0x0b/0x0F)</td>
				</tr>
				<tr>
					<td>End Date:</td>
					<td><input type='text' size='10' name='end_date' value='<?php echo $end_date;?>'></td>
					<td>End Time:</td>
					<td><input type='text' size='8' name='end_time' value='<?php echo $end_time;?>'></td>
					<td>TCP Flags:</td>
					<td><input type='text' size='10' name='tcp_flags' value='<?php echo $tcp_flags;?>'></td>
					<td>Protocols:</td>
					<td><?php draw_edit_control("protocols", $ip_protocol_field);?></td>
				</tr>
				<tr>
					<td>Source IP:</td>
					<td><input type='text' size='19' name='source_address' value='<?php echo $source_address;?>'></td>
					<td>Source Port(s):</td>
					<td><input type='text' size='20' name='source_port' value='<?php echo $source_port;?>'></td>
					<td>Source Interface:</td>
					<td><input type='text' size='2' name='source_if' value='<?php echo $source_if;?>'></td>
					<td>Source AS:</td>
					<td><input type='text' size='6' name='source_as' value='<?php echo $source_as;?>'></td>
				</tr>
				<tr>
					<td>Dest IP:</td>
					<td><input type='text' size='19' name='dest_address' value='<?php echo $dest_address; ?>'></td>
					<td>Dest Port(s):</td>
					<td><input type='text' size='20' name='dest_port' value='<?php echo $dest_port; ?>'></td>
					<td>Dest Interface:</td>
					<td><input type='text' size='2' name='dest_if' value='<?php echo $dest_if; ?>'></td>
					<td>Dest AS:</td>
					<td><input type='text' size='6' name='dest_as' value='<?php echo $dest_as; ?>'></td>
				</tr>
				<tr>
					<td colspan='9'>
						<hr size='2'>
						<center>Note: Multiple field entries, separated by commas, are permitted in the fields above. A minus sign (-) will negate an entry (e.g. -80 for Port, would mean any Port but 80)</center>
						<hr size='2'>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php html_end_box(false);?>
	<?php html_start_box("<strong>Report Parameters</strong>", "100%", $colors["header"], "1", "center", "");?>
	<tr>
		<td>Statistics:</td>
		<td colspan='2'><?php draw_edit_control("stat_report", $stat_report_field);?></td>
		<td>Printed:</td>
		<td colspan='2'><?php draw_edit_control("print_report", $print_report_field);?></td>
		<td>Include if:</td>
		<td colspan='2'><?php draw_edit_control("flow_select", $flow_select_field);?></td>
	</tr>
	<tr>
		<td>Sort Field:</td>
		<td><select id='sort_field' name='sort_field'></select></td>
		<td>Cutoff Lines:</td>
		<td><input type='text' size='3' name='cutoff_lines' value='<?php echo $cutoff_lines; ?>'></td>
		<td>Cutoff Octets:</td>
		<td><input type='text' size='13' name='cutoff_octets' value='<?php echo $cutoff_octets; ?>'></td>
		<td>Resolve Addresses:</td>
		<td><?php draw_edit_control("resolve_addresses", $resolve_addresses_field);?></td>
	</tr>
	<tr>
		<td colspan='9'><hr size='2'></td>
	</tr>
	<tr>
		<td colspan='9'>
			<input type='hidden' id='action' name='action' value='view'>
			<input type='hidden' id='new_query' name='new_query' value=''>
			<center>
				<input id='view' type='button' name='view' value='View'>
				<input id='defaults' type='button' value='Defaults'>
				<input id='save' type='button' name='save' value='Save'>
				<input id='saveas' type='button' name='saveas' value='Save As'>
				<input id='delete' type='button' name='delete' value='Delete'>
			</center>
		</td>
	</tr>
	<?php html_end_box();?>
	</table></td></tr>
	<?php

	print '</form>';

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

	$('#device_name').change(function () {
		if ($(this).val() == 0) {
			$('#view').attr('disabled', 'disabled');
			$('#save').attr('disabled', 'disabled');
		}else{
			$('#view').removeAttr('disabled');
			$('#save').removeAttr('disabled');
		}
	});

	$().ready(function () {
		$('#saveas').hide();
		if ($('#query').val() == 0) {
			$('#delete').hide();
		}else{
			$('#save').attr('value', 'Update');
			$('#saveas').show();
		}

		$('#query').change(function() {
			window.location="flowview.php?action=loadquery&query="+$('#query').val();
		});

		if ($('#device_name').val() == 0) {
			$('#view').attr('disabled', 'disabled');
			$('#save').attr('disabled', 'disabled');
		}else{
			$('#view').removeAttr('disabled');
			$('#save').removeAttr('disabled');
		}

		$('#stat_report').change(function() {
			StatSelect();
		});
		$('#print_report').change(function() {
			PrintSelect();
		});

		StatSelect();
		PrintSelect();

		$("#fdialog").dialog({
			autoOpen: false,
			width: 320,
			height: 90,
			resizable: false,
			modal: true
		});
	});

	$('#view').click(function() {
		$('#action').attr('value', 'view');
		document.flowview.submit();
	});

	$('#saveas').click(function() {
		$('#squery').attr('value', $('#query>option:selected').text()+' (New)');
		$('#fdialog').dialog('open');
		$('#qcancel').click(function() {
			$('#fdialog').dialog('close');
		});
		$('#qsave').click(function() {
			$('#new_query').attr('value', $('#squery').val());
			$('#action').attr('value', 'save');
			$.post('flowview.php', $('#flowview').serialize(), function(data) {
				if (data!="error") {
					$('#query').append("<option value='"+data+"'>"+$('#new_query').val()+"</option>");
					$('#query').attr('value', data);
				}
			});
			$('#fdialog').dialog('close');
		});
	});

	$('#save').click(function() {
		if ($('#query').val() == 0) {
			$('#fdialog').dialog('open');
			$('#qcancel').click(function() {
				$('#fdialog').dialog('close');
			});
			$('#qsave').click(function() {
				$('#new_query').attr('value', $('#squery').val());
				$('#action').attr('value', 'save');
				$.post('flowview.php', $('#flowview').serialize(), function(data) {
					if (data!="error") {
						$('#query').append("<option value='"+data+"'>"+$('#new_query').val()+"</option>");
						$('#query').attr('value', data);
					}
				});
				//document.flowview.submit();
			});
		}else{
			$('#action').attr('value', 'save');
			$.post('flowview.php', $('#flowview').serialize(), function(data) {
				if (data!="error") {
					$('#query').append("<option value='"+data+"'>"+$('#new_query').val()+"</option>");
					$('#query').attr('value', data);
				}
			});
			//document.flowview.submit();
		}
	});

	$('#delete').click(function() {
		document.location="flowview.php?action=delete&query="+$('#query').val();
	});

	$('#defaults').click(function() {
		setDefaults();
	});

	function setDefaults() {
		// Flow Filter Settings
		$('#device').attr('value',0);
		$('#start_date').attr('value', '');
		$('#start_time').attr('value','-8 HOURS');
		$('#end_date').attr('value','');
		$('#end_time').attr('value','NOW');
		$('#source_address').attr('value','');
		$('#source_port').attr('value','');
		$('#source_if').attr('value','');
		$('#source_as').attr('value','');
		$('#dest_address').attr('value','');
		$('#dest_port').attr('value','');
		$('#dest_if').attr('value','');
		$('#dest_as').attr('value','');
		$('#protocols').attr('value',0);
		$('#tos_fields').attr('value','');
		$('#tcp_flags').attr('value','');
		// Report Settings
		$('#stat_report').attr('value',0);
		$('#print_report').attr('value',0);
		$('#flow_select').attr('value',1);
		$('#sort_field').attr('value',4);
		$('#cutoff_lines').attr('value','100');
		$('#cutoff_octets').attr('value', '');
		$('#resolve_addresses').attr('value',0);
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

	var sortfield='<?php echo $sort_field; ?>';
	var statreport='<?php echo ($stat_report > 0 ? $stat_report : 0); ?>';

	-->
	</script>

	<?php
}


