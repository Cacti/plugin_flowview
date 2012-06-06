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

function flowview_display_report() {
	global $config, $colors;

	if (isset($_REQUEST['tab']) && strlen($_REQUEST['tab']) > 10) {
		$flowdata  = unserialize(base64_decode($_REQUEST['tab']));
		$sessionid = $_REQUEST['tab'];
		foreach($flowdata['post'] as $item => $value) {
			switch ($item) {
			case 'bytes':
			case 'flows':
			case 'packets':
				break;
			default:
				$_POST[$item] = $value;
			}
		}
		$_REQUEST['query'] = $_POST['query'];
		$_REQUEST['action'] = 'view';
	}else{
		$sessionid = '';
	}

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$rname = '';
	if ($stat_report > 0)
		$rname = $stat_report_array[$stat_report];
	if ($print_report > 0)
		$rname = $print_report_array[$print_report];

	$current = '';
	$error = flowview_check_fields();
	if ($error != '') {
		display_tabs();
		print "<font color=red><strong>$error</strong></font>";
		html_end_box();
		return;
	}

   /* if the user pushed the 'clear' button */
    if (isset($_REQUEST["clear"])) {
        kill_session_var("sess_flows_exclude");
        kill_session_var("sess_flows_table");
        kill_session_var("sess_flows_bytes");
        kill_session_var("sess_flows_packets");
        kill_session_var("sess_flows_flows");

        unset($_REQUEST["exclude"]);
        unset($_REQUEST["table"]);
        unset($_REQUEST["bytes"]);
        unset($_REQUEST["packets"]);
        unset($_REQUEST["flows"]);
    }

    /* remember these search fields in session vars so we don't have to keep passing them around */
    load_current_session_value("exclude", "sess_flows_exclude", "0");
    load_current_session_value("table",   "sess_flows_table",   "on");
    load_current_session_value("bytes",   "sess_flows_bytes",   "");
    load_current_session_value("packets", "sess_flows_packets", "");
    load_current_session_value("flows",   "sess_flows_flows",   "");

	$filter = createfilter($sessionid);

	display_tabs();

	if (isset($_POST['stat_report']) && $_POST['stat_report'] != 99) {
		html_start_box("<strong>Report: $rname</strong>", "100%", $colors["header"], "3", "center", "");
		?>
		<tr bgcolor="#<?php print $colors["panel"];?>">
			<td>
			<form id="view" name="view" action="flowview.php" method="post">
				<table cellpadding="2" cellspacing="0">
					<tr>
						<td nowrap style='white-space: nowrap;'>
							<strong>Exclude:</strong>&nbsp;
						</td>
						<td nowrap style='white-space: nowrap;'>
							<select name='exclude' id='exclude'>
								<option value='0'<?php echo ($_REQUEST["exclude"] == 0 ? " selected":"");?>>None</option>
								<option value='1'<?php echo ($_REQUEST["exclude"] == 1 ? " selected":"");?>>Top Sample</option>
								<option value='2'<?php echo ($_REQUEST["exclude"] == 2 ? " selected":"");?>>Top 2 Samples</option>
								<option value='3'<?php echo ($_REQUEST["exclude"] == 3 ? " selected":"");?>>Top 3 Samples</option>
								<option value='4'<?php echo ($_REQUEST["exclude"] == 4 ? " selected":"");?>>Top 4 Samples</option>
								<option value='5'<?php echo ($_REQUEST["exclude"] == 5 ? " selected":"");?>>Top 5 Samples</option>
							</select>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<strong>Show/Hide:</strong>&nbsp;
						</td>
						<td width="1">
							<input type="checkbox" name="table" id="table" <?php print ($_REQUEST["table"] == "true" || $_REQUEST["table"] == "on" ? "checked":"");?>>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<label for="table">Table</label>
						</td>
						<td width="1">
							<input type="checkbox" name="bytes" id="bytes" <?php print ($_REQUEST["bytes"] == "true" || $_REQUEST["bytes"] == "on" ? "checked":"");?>>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<label for="bytes">Bytes Bar</label>
						</td>
						<td width="1">
							<input type="checkbox" name="packets" id="packets" <?php print ($_REQUEST["packets"] == "true" || $_REQUEST["packets"] == "on" ? "checked":"");?>>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<label for="packets">Packets Bar</label>
						</td>
						<td width="1">
							<input type="checkbox" name="flows" id="flows" <?php print ($_REQUEST["flows"] == "true" || $_REQUEST["flows"] == "on" ? "checked":"");?>>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<label for="flows">Flows Bar</label>
						</td>
						<td nowrap style='white-space: nowrap;'>
							<input type="submit" name="clear" value="Clear" title="Clear Filters">
						</td>
					</tr>
				</table>
				<input type='hidden' name='page' value='1'>
				<input type='hidden' name='tab'  id='tab' value='<?php print $sessionid;?>'>
			</form>
			</td>
		</tr>
		<?php
		html_end_box();

		flowview_draw_chart('bytes', $rname);
		flowview_draw_chart('packets', $rname);
		flowview_draw_chart('flows', $rname);
	}elseif (isset($_POST['print_report']) && $_POST['print_report'] > 0) {
		html_start_box("<strong>Report: $rname</strong>", "100%", $colors["header"], "3", "center", "");
	}

	echo "<div id='flowcontent'>";
	echo $filter;
	html_end_box();
	echo "</div>";
	?>
	<script type='text/javascript'>

	swfobject.embedSWF('open-flash-chart.swf', 'chartbytes', '98%', '275', '9.0.0', 'expressInstall.swf', {'data-file':'<?php print urlencode($config["url_path"] . "plugins/flowview/flowview.php?session=" . $sessionid . "&action=chartdata&exclude=" . $_REQUEST['exclude'] . "&type=bytes&title=$rname");?>', 'id':'chartbytes'});
	swfobject.embedSWF('open-flash-chart.swf', 'chartpackets', '98%', '275', '9.0.0', 'expressInstall.swf', {'data-file':'<?php print urlencode($config["url_path"] . "plugins/flowview/flowview.php?session=" . $sessionid . "&action=chartdata&exclude=" . $_REQUEST['exclude'] . "&type=packets&title=$rname");?>', 'id':'chartpackets'});
	swfobject.embedSWF('open-flash-chart.swf', 'chartflows', '98%', '275', '9.0.0', 'expressInstall.swf', {'data-file':'<?php print urlencode($config["url_path"] . "plugins/flowview/flowview.php?session=" . $sessionid . "&action=chartdata&exclude=" . $_REQUEST['exclude'] . "&type=flows&title=$rname");?>', 'id':'chartflows'});

	$('#bytes').click(function() {
		if (!$('#bytes').is(':checked')) {
			$('#wrapperbytes').hide();
			$.get('flowview.php?action=updatesess&type=bytes&value=');
		}else{
			$('#wrapperbytes').show();
		}
	});

	$('#packets').click(function() {
		if (!$('#packets').is(':checked')) {
			$('#wrapperpackets').hide();
			$.get('flowview.php?action=updatesess&type=packets&value=');
		}else{
			$('#wrapperpackets').show();
		}
	});

	$('#flows').click(function() {
		if (!$('#flows').is(':checked')) {
			$('#wrapperflows').hide();
			$.get('flowview.php?action=updatesess&type=flows&value=');
		}else{
			$('#wrapperflows').show();
		}
	});

	$('#table').click(function() {
		if (!$('#table').is(':checked')) {
			$('#flowcontent').hide();
			$.get('flowview.php?action=updatesess&type=table&value=');
		}else{
			$.get('flowview.php?action=updatesess&type=table&value=on');
			$('#flowcontent').show();
		}
	});

	$('#exclude').change(function() {
		document.view.submit();
	});
			
	if ($('#table').is(':checked') || <?php print (isset($_POST['stat_report']) ? ($_POST['stat_report'] == 99 ? 'true':'false'):'true');?>) {
		$('#flowcontent').show();
	}else{
		$('#flowcontent').hide();
	}

	if ($('#bytes').is(':checked')) {
		$('#wrapperbytes').show();
	}

	if ($('#packets').is(':checked')) {
		$('#wrapperpackets').show();
	}

	if ($('#flows').is(':checked')) {
		$('#wrapperflows').show();
	}

    $.tablesorter.addParser({ 
        id: 'bytes', 
        is: function(s) { 
            return false; 
        }, 
        format: function(s) { 
			if (s.indexOf('MB') > 0) {
				loc=s.indexOf('MB');
				return s.substring(0,loc) * 1024 * 1024;
			}else if (s.indexOf('KB') > 0) {
				loc=s.indexOf('KB');
				return s.substring(0,loc) * 1024;
			}else if (s.indexOf('Bytes') > 0) {
				loc=s.indexOf('Bytes');
				return s.substring(0,loc);
			}else if (s.indexOf('GB') > 0) {
				loc=s.indexOf('GB');
				return s.substring(0,loc) * 1024 * 1024 * 1024;
			}else if (s.indexOf('TB') > 0) {
				loc=s.indexOf('TB');
				return s.substring(0,loc) * 1024 * 1024 * 1024 * 1024;
			}else{
				return s;
			}
        }, 
        type: 'numeric' 
    }); 

	$().ready(function() {
		$('#sorttable').tablesorter();
	});
	</script>
	<?php
}

function get_port_name($port_num, $port_proto) {
}

function display_tabs() {
	/* purge old flows if they exist */
	purgeFlows();

	/* draw the categories tabs on the top of the page */
	if (isset($_REQUEST['tab'])) {
		$_SESSION['flowview_current_tab'] = $_REQUEST['tab'];
	}elseif (isset($_SESSION['flowview_current_tab'])) {
		/* do nothing */
	}else{
		$_SESSION['flowview_current_tab'] = 'filters';
	}
	$ct = $_SESSION['flowview_current_tab'];

	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='" . ($ct == 'filters' ? "silver":"#DFDFDF") . "' nowrap='nowrap' width='" . (strlen('Filters') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a title='Setup Flows' href='" . htmlspecialchars("flowview.php?tab=filters") . "'>Filters</a></span>
			</td>\n
			<td width='1'></td>\n";
	if (api_user_realm_auth('flowview_devices.php')) {
		print "<td bgcolor='" . ($ct == 'listeners' ? "silver":"#DFDFDF") . "' nowrap='nowrap' width='" . (strlen('Listeners') * 9) . "' align='center' class='tab'>
				<span class='textHeader'><a title='Manage Listeners' href='" . htmlspecialchars("flowview_devices.php?tab=listeners") . "'>Listeners</a></span>
				</td>\n
				<td width='1'></td>\n";
	}

	if (api_user_realm_auth('flowview_schedules.php')) {
		print "<td bgcolor='" . ($ct == 'sched' ? "silver":"#DFDFDF") . "' nowrap='nowrap' width='" . (strlen('Schedules') * 9) . "' align='center' class='tab'>
				<span class='textHeader'><a title='Manage e-Mail Reports' href='" . htmlspecialchars("flowview_schedules.php?tab=sched") . "'>Schedules</a></span>
				</td>\n
				<td width='1'></td>\n";
	}

	if (isset($_SESSION['flowview_flows']) && is_array($_SESSION['flowview_flows']) && sizeof($_SESSION['flowview_flows'])) {
	foreach($_SESSION['flowview_flows'] as $sessionid => $data) {
		if (!isset($data['title'])) $_SESSION['flowview_flows'][$sessionid]['title'] = $data['title'] = "Unknown";
		print "<td bgcolor='" . ($ct == $sessionid ? "silver":"#DFDFDF") . "' nowrap='nowrap' width='" . (strlen($data['title']) * 9) . "' align='center' class='tab'>
				<span class='textHeader'><a style='white-space:nowrap;' href='" . htmlspecialchars("flowview.php?tab=$sessionid") . "' title='View Flow'>" . $data['title'] . "</a>&nbsp<a href='" . htmlspecialchars("flowview.php?action=killsession&session=$sessionid") . "' title='Remove Flow Cache'>x</a></span>
				</td>\n
				<td width='1'></td>\n";
	}
	}
	print "<td></td>\n</tr></table>\n";
}

function plugin_flowview_run_schedule($id) {
	global $config;

	$schedule = db_fetch_row("SELECT * FROM plugin_flowview_schedules WHERE id=$id");
	$query    = db_fetch_row("SELECT * FROM plugin_flowview_queries WHERE id=" . $schedule['savedquery']);

	$fromname = read_config_option('settings_from_name');
	if (strlen($fromname) < 1) {
		$fromname = 'Cacti Flowview';
	}

	$from = read_config_option('settings_from_email');
	if (strlen($from) < 1) {
		$from = 'cacti@cactiusers.org';
	}

	$subject = 'Netflow - ' . $schedule['title'];

	$_REQUEST['schedule'] = $id;
	$_REQUEST['query']    = $schedule['savedquery'];
	$_REQUEST['action']   = 'loadquery';
	include($config['base_path'] . '/plugins/flowview/variables.php');
	$message  = "<body style='margin:10px;'>";
	$message .= "<style type='text/css'>\n";
	$message .= file_get_contents($config['base_path'] . '/include/main.css');
	$message .= "</style>";
	$sessionid = -1;
	$message .= createfilter($sessionid);
	$message .= "</body>";
	send_mail($schedule['email'], $from, $subject, $message, ' ', '', $fromname);
}

function purgeFlows() {
	$time = time();
	if (isset($_SESSION['flowview_flows']) && is_array($_SESSION['flowview_flows'])) {
	foreach($_SESSION['flowview_flows'] AS $session => $data) {
		if ($time > $data['expires']) {
			unset($_SESSION['flowview_flows'][$session]);
		}
	}
	}
}

/** creatfilter($sessionid)
 *  
 *  This function creates the NetFlow Report for the UI.  It presents this in a table
 *  format and returns as a test string to the calling function.
 */
function createfilter(&$sessionid='') {
	global $config;

	$output = '';
	$title  = '';
	if ($sessionid != '' && $sessionid != -1) {
		$flowdata = unserialize(base64_decode($sessionid));
		$title    = $flowdata['title'];
		foreach($flowdata['post'] AS $item => $value) {
			switch ($item) {
			case 'bytes':
			case 'flows':
			case 'packets':
				break;
			default:
				$_POST[$item] = $value;
			}
		}	
		if (time() < $flowdata['expires']) {
			$output = $_SESSION['flowview_flows'][$sessionid]['rawdata'];
		}
	}

	include($config['base_path'] . '/plugins/flowview/variables.php');

	if ($output=='') {
		/* initialize the return string */
		$filter  = '';

		/* get the flow report tool binary location */
		$flowbin = read_config_option('path_flowtools');
		if ($flowbin == '') {
			$flowbin = '/usr/bin';
		}
		if (substr($flowbin, -1 , 1) == '/') {
			$flowbin = substr($flowbin, 0, -1);
		}

		/* get working directory for temporary output */
		$workdir = read_config_option('path_flowtools_workdir');
		if ($workdir == '') {
			$workdir = '/tmp';
		}

		if (substr($workdir, -1 , 1) == '/') {
			$workdir = substr($workdir, 0, -1);
		}

		/* determine the location for the netflow reports */
		$pathstructure = '';
		if ($device != '') {
			$pathstructure = db_fetch_cell("SELECT nesting FROM plugin_flowview_devices WHERE folder = '$device'");
		}

		if ($pathstructure == '') {
			$pathstructure = 0;
		}

		/* construct the report command */
		$time       = time();
		$filterfile = "$workdir/FlowViewer_filter_" . time();

		$start = strtotime($start_date . ' ' . $start_time);
		$end   = strtotime($end_date   . ' ' . $end_time);

		$flow_cat_command     = "$flowbin/flow-cat -t \"" . date("m/d/Y H:i:s", $start) . '" -T "' . date("m/d/Y H:i:s", $end) . '" ';
		$flow_cat_command    .= getfolderpath($pathstructure, $device, $start, $end);
		$flownfilter_command  = "$flowbin/flow-nfilter -f $filterfile -FFlowViewer_filter";

		$flowstat             = $flowbin . '/flow-stat';
		$flowstat_command     = '';
		$flow_command         = "$flow_cat_command | $flownfilter_command";
	
		if ($stat_report != 0) {
			if ($stat_report == 99) {
				$flowstat_command = "$flowbin/flow-stat -S" . $sort_field;
			} else {
				$flowstat_command = "$flowbin/flow-stat -f" . $stat_report . " -S" . ($sort_field-1);
			}
			$flow_command .= " | $flowstat_command";
		}

		if ($print_report != 0) {
			$flow_command .= " | $flowbin/flow-print -f" . $print_report;
		}

		/* Check to see if the flowtools binaries exists */
		if (!is_file("$flowbin/flow-cat"))
			return "Can not find the '<strong>flow-cat</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Flowtools Path Setting</a>!";
		if (!is_file("$flowbin/flow-nfilter"))
			return "Can not find the '<strong>flow-nfilter</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Flowtools Path Setting</a>!";
		if (!is_file("$flowbin/flow-stat"))
			return "Can not find the '<strong>flow-stat</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Flowtools Path Setting</a>!";
		if (!is_file("$flowbin/flow-print"))
			return "Can not find the '<strong>flow-print</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Flowtools Path Setting</a>!";

		// Create Filters
		$filter .= flowview_create_ip_filter ($source_address, 'source');
		$filter .= flowview_create_if_filter ($source_if, 'source');
		$filter .= flowview_create_port_filter ($source_port, 'source');
		$filter .= flowview_create_as_filter ($source_as, 'source');
		$filter .= flowview_create_ip_filter ($dest_address, 'dest');
		$filter .= flowview_create_if_filter ($dest_if, 'dest');
		$filter .= flowview_create_port_filter ($dest_port, 'dest');
		$filter .= flowview_create_as_filter ($dest_as, 'dest');
		$filter .= flowview_create_protocol_filter ($protocols);
		$filter .= flowview_create_tcp_flag_filter ($tcp_flags);
		$filter .= flowview_create_tos_field_filter ($tos_fields);
		$filter .= flowview_create_time_filter($start, $end);
		$filter .= flowview_create_flowview_filter();

		/* Write filters to file */
		$f = @fopen($filterfile, 'w');
		if (!$f) {
			clearstatcache();
			if (!is_dir($workdir)) {
				return "<strong>Flow Tools Work directory ($workdir) does not exist!, please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Settings</a></strong>";
			}

			return "<strong>Flow Tools Work directory ($workdir) is not writable!, please check your <a href='" . htmlspecialchars($config['url_path'] . "settings.php?tab=path") . "'>Settings</a></strong>";
		}

		@fputs($f, $filter);
		@fclose($f);

		/* let's calculate the title and then session id */
		if ($title == '') {
			if (isset($_REQUEST["query"]) && $_REQUEST["query"] > 0) {
				$title = db_fetch_cell("SELECT name FROM plugin_flowview_queries WHERE id=" . $_REQUEST["query"]);
			}else{
				$title = "New Flow";
			}
		}

		if ($sessionid == '' && isset($_SESSION['flowview_flows'])) {
			$parts = explode("(", $title);
			$base = trim($parts[0]);
			$i = 1;
			while (true) {
				$unique = true;
				foreach($_SESSION['flowview_flows'] AS $sess => $data) {
					if ($title == $data['title']) {
						$title = $base . " (" . $i . ")";
						$i++;
						$unique = false;
						break;
					}
				}

				if ($unique) {
					break;
				}
			}
		}

		if ($sessionid != -1) {
			$flowdata['command'] = $flow_command;
			$flowdata['post']    = $_POST;
			$flowdata['expires'] = time()+300;
			$flowdata['title']   = $title;
			$sessionid = base64_encode(serialize($flowdata));
			$_REQUEST['tab'] = $sessionid;
		}

		/* Run the command */
		$output = shell_exec($flow_command);
		unlink($filterfile);

		if ($sessionid != -1) {
			/* store the raw data in to the request variable */
			$_SESSION['flowview_flows'][$sessionid]['rawdata'] = $output;
			$_SESSION['flowview_flows'][$sessionid]['title']   = $title;
			$_SESSION['flowview_flows'][$sessionid]['expires'] = $flowdata['expires'];
		}
	}

	if ($stat_report != 0) {
		$output = parsestatoutput($output, $title, $sessionid);
	}elseif ($print_report != 0) {
		$output = parseprintoutput($output, $title, $sessionid);
	}

	return $output;
}

function get_column_alignment($column) {
	switch($column) {
	case "Bytes":
	case "Packets":
	case "Flows":
		return "right";
		break;
	default:
		return "left";
	}
}

function parseSummaryReport($output) {
	global $config, $colors;

	$output = explode("\n", $output);

	$insummary = true;
	$inippsd   = false;
	$inppfd    = false;
	$inopfd    = false;
	$inftd     = false;
	$section   = "insummary";
	$i = 0; $j = 0;

	/* do some output buffering */
	ob_start();

	html_start_box("<strong>Summary Statistics</strong>", "100%", $colors["header"], "3", "center", "");
	if (sizeof($output)) {
		foreach($output as $l) {
			$l = trim($l);
			if (substr($l,0,1) == "#" || strlen($l) == 0) continue;

			if (substr_count($l, "IP packet size distribution")) {
				html_end_box(false);
				html_start_box("<strong>IP Packet Size Distribution (%)</strong>", "100%", $colors["header"], "3", "center", "");
				$section = "inippsd";
				continue;
			}elseif (substr_count($l, "Packets per flow distribution")) {
				html_end_box(false);
				html_start_box("<strong>Packets per Flow Distribution (%)</strong>", "100%", $colors["header"], "3", "center", "");
				$section = "inppfd";
				continue;
			}elseif (substr_count($l, "Octets per flow distribution")) {
				html_end_box(false);
				html_start_box("<strong>Octets per Flow Distribution (%)</strong>", "100%", $colors["header"], "3", "center", "");
				$section = "inopfd";
				continue;
			}elseif (substr_count($l, "Flow time distribution")) {
				html_end_box(false);
				html_start_box("<strong>Flow Time Distribution (%)</strong>", "100%", $colors["header"], "3", "center", "");
				$section = "inftd";
				continue;
			}

			switch($section) {
			case "insummary":
				if ($i % 2 == 0) {
					if ($i > 0) {
						echo "</tr>";
					}
					echo "<tr bgcolor='" . flowview_altcolor($j) . "' style='border:1px solid #FEFEFE;'>";
					$j++;
				}
				$parts = explode(":", $l);
				$header = trim($parts[0]);
				$value  = trim($parts[1]);
				echo "<td><strong>" . $header . "</strong></td><td>" . number_format($value) . "</td>"; 
				break;
			case "inippsd":
			case "inppfd":
			case "inopfd":
			case "inftd":
				/* Headers have no decimals */
				if (!substr_count($l, ".")) {
					echo "<tr bgcolor='" . flowview_altcolor($i) . "'>";
					$parts = flowview_explode($l);
					$k = 0;
					$l = sizeof($parts);
					foreach($parts as $p) {
						echo "<td align='right'><strong>" . $p . "</strong></td>";
						if ($l < 15 && $k == 10) {
							echo "<td colspan='4'></td>";
						}
						$k++;
					}
					echo "</tr>";
				}else{
					echo "<tr bgcolor='" . flowview_altcolor($i) . "'>";
					$parts = flowview_explode($l);
					$k = 0;
					$l = sizeof($parts);
					foreach($parts as $p) {
						echo "<td align='right'>" . ($p*100) . "</td>";
						if ($l < 15 && $k == 10) {
							echo "<td colspan='4'></td>";
						}
						$k++;
					}
					echo "</tr>";
				}
				break;
			}
			$i++;
		}
	}
	html_end_box(false);

	return ob_get_clean();
}

function flowview_explode($string) {
	$string=trim($string);

	if (!strlen($string)) return array();

	$array=explode(" ", $string);
	foreach($array as $e) {
		if ($e != '') {
			$newa[] = $e;
		}
	}

	return $newa;
}

function removeWhiteSpace($string) {
	$string = str_replace("\t", " ", $string);
	while (substr_count("  ",$string)) {
		$string = str_replace("  ", " ", $string);
	}
	return $string;
}

function parsestatoutput($output, $title, $sessionid) {
	global $config, $colors;

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if ($stat_report == 99) {
		return parseSummaryReport($output);
	}elseif (!isset($stat_columns_array[$stat_report])) {
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";
	}

	$output = explode("\n", $output);

	/* cheasy way to get output */
	ob_start();
	html_start_box("<strong>" . $title . "</strong>", "100%", $colors["header"], "3", "center", "");
	$o  = ob_get_clean();

	$o .= '<table id="sorttable" width="100%" cellspacing=0 cellpadding=2 border=0 bgcolor="#' . $colors["header"] . '">
			<thead>
				<tr bgcolor="#' . $colors["header_panel"] . '" class="textHeaderDark" align=center>';

	$clines     = $stat_columns_array[$stat_report][0];
	$octet_col  = $stat_columns_array[$stat_report][1];
	$proto_col  = $stat_columns_array[$stat_report][3];
	$port_col   = $stat_columns_array[$stat_report][4];
	$ip_col     = $stat_columns_array[$stat_report][2];
	if (strlen($ip_col)) {
		$ip_col = explode(',',$ip_col);
	}else{
		$ip_col = array();
	}

	$columns    = $stat_columns_array[$stat_report];

	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);

	if ($sessionid != -1) {
		$_SESSION['flowview_flows'][$sessionid]['columns'] = $columns;
	}

	$x = 1;
	foreach ($columns as $column) {
		$o .= "<th " . ($column == "Bytes" ? "class=\"{sorter: 'bytes'}\"":"") . " align='" . get_column_alignment($column) . "'>$column</th>";
		$x++;
	}
	$o .= "</tr></thead><tbody>";
	$cut = 1;

	$dns = '';

	if ($resolve_addresses == 'Y' && isset($ip_col[0]) && $ip_col[0] != '') {
		$dns = read_config_option('flowview_dns');
		if ($dns == '') {
			$dns = read_config_option('discovery_dns');
			if ($dns == '') {
				$dns = read_config_option('mt_dns_primary');
			}
		}
	}

	$i = 0;

	if (isset($_REQUEST['exclude'])) {
		$j = $_REQUEST['exclude'];
	}else{
		$j = 0;
	}
	$r = 0;
	$data_array = array();
	foreach ($output as $out) {
		if (substr($out, 0, 1) != '#' && $out != '') {
			$out = trim($out);
			while (strpos($out, '  ')) {
				$out = str_replace('  ', ' ', $out);
			}
			$out = explode(' ', $out);
			if ($octet_col == '' || $cutoff_octets == '' || $out[$octet_col] > $cutoff_octets-1) {
				/* remove outliers */
				if ($r < $j) {
					$r++;
					continue;
				}

				$o .= '<tr bgcolor="' . flowview_altcolor($i) . '">';
				$c = 0;
				foreach ($out as $out2) {
					if ($out2 != '') {
						if ($resolve_addresses == 'Y' && ($dns != '' || read_config_option("flowview_dns_method") == 0) && (sizeof($ip_col) && in_array($c, $ip_col))) {
							$out2 = flowview_get_dns_from_ip($out2, $dns);
							$data_array[$i][$c] = $out2;
						}elseif ($c == $octet_col) {
							$data_array[$i][$c] = $out2;
							$out2 = plugin_flowview_formatoctet($out2);
						}elseif ($c == $port_col) {
							$out2 = flowview_translate_port($out2, false);
							$data_array[$i][$c] = $out2;
						}elseif ($c == $proto_col) {
							$out2 = plugin_flowview_get_protocol($out2, 0);
							$data_array[$i][$c] = $out2;
						}else{
							$data_array[$i][$c] = $out2;
						}
						$o .= "<td align='" . get_column_alignment($columns[$c]) . "'>$out2</td>";
						$c++;
					}
				}
				$o .= "</tr>";
				$cut++;
			}
		}
		if ($cutoff_lines < $cut)
			break;
		$i++;
	}

	if ($sessionid != -1) {
		$_SESSION['flowview_flows'][$sessionid]['data'] = $data_array;
	}

	$o .= '</tbody></table>';
	return $o;
}

function plugin_flowview_get_protocol ($prot, $prot_hex) {
	global $config;
	include($config['base_path'] . '/plugins/flowview/arrays.php');
	$prot = ltrim($prot,'0');
	$prot = ($prot_hex ? hexdec($prot):$prot);

	if (isset($ip_protocols_array[$prot]))
		return $ip_protocols_array[$prot];
	return $prot;
}

function plugin_flowview_formatoctet($size, $div = 1024) {
	$x=0;
	$tag = array('Bytes', 'KB', 'MB', 'GB', 'TB');
	while ($size > $div) {
		$size = $size / $div;
		$x++;
	}
	return round($size, 2) . ' ' . $tag[$x];
}

function flowview_altcolor($i) {
	global $colors;
	if ($i/2 == intval($i/2)) {
		return '#' . $colors["light"];
	} else {
		return '#' . $colors["alternate"];
	}
}


function parseprintoutput($output, $title, $sessionid) {
	global $config, $colors;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (!isset($print_columns_array[$print_report]))
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";

	$output = explode("\n", $output);

	/* cheasy way to get output */
	ob_start();
	html_start_box("<strong>" . $title . "</strong>", "100%", $colors["header"], "3", "center", "");
	$o  = ob_get_clean();

	$o = '<table width="100%" cellspacing=0 cellpadding=2 border=0 bgcolor="#' . $colors["header"] . '">
		<tr bgcolor="#' . $colors["header_panel"] . '" class="textHeaderDark" align=center>';

	$clines     = $print_columns_array[$print_report][0];
	$octet_col  = $print_columns_array[$print_report][1];
	$proto_hex  = $print_columns_array[$print_report][3];
	$proto_col  = $print_columns_array[$print_report][4];
	$ports_col  = explode(',', $print_columns_array[$print_report][6]);
	$ports_hex  = $print_columns_array[$print_report][5];
	$ip_col     = $stat_columns_array[$stat_report][2];
	if (strlen($ip_col)) {
		$ip_col = explode(',',$ip_col);
	}else{
		$ip_col = array();
	}

	$columns    = $print_columns_array[$print_report];

	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);

	if ($sessionid != -1) {
		$_SESSION['flowview_flows'][$sessionid]['columns'] = $columns;
	}

	foreach ($columns as $column) {
		$o .= "<th align='" . get_column_alignment($column) . "'>$column</th>";
	}
	$o .= "</tr>\n";
	$cut = 1;

	$dns = '';

	if ($resolve_addresses == 'Y' && isset($ip_col[0]) && $ip_col[0] != '') {
		$dns = read_config_option('flowview_dns');
		if ($dns == '') {
			$dns = read_config_option('discovery_dns');
			if ($dns == '') {
				$dns = read_config_option('mt_dns_primary');
			}
		}
	}

	$i = 0;
	$firstline  = true;
	$cfirst     = false;
	$data_array = array();
	foreach ($output as $out) {
		if ($clines > 1 && strlen($out) && substr($out,0,1) != ' ') {
			$cfirst = true;
			$outf   = rtrim($out);
			continue;
		}elseif (trim($out) == '') {
			continue;
		}elseif ($clines > 1 && $cfirst = true) {
			$out    = $outf . " " . trim($out);
			$cfirst = false;
		}

		if (substr($out, 0, 1) != '#' && $out != '' && $firstline == false) {
			$out = trim($out);
			while (strpos($out, '  ')) {
				$out = str_replace('  ', ' ', $out);
			}
			$out = explode(' ', $out);

			if ($octet_col == '' || $cutoff_octets == '' || $out[$octet_col] > $cutoff_octets-1) {
				$o .= '<tr align=left bgcolor="' . flowview_altcolor($i) . '">';
				$c = 0;
				foreach ($out as $out2) {
					if ($out2 != '') {
						if (($dns != '' || read_config_option("flowview_dns_method") == 0) && (sizeof($ip_col) && in_array($c, $ip_col))) {
							$out2 = flowview_get_dns_from_ip($out2, $dns);
							$data_array[$i][$c] = $out2;
						}elseif (in_array($c, $ports_col)) {
							$out2 = flowview_translate_port($out2, $ports_hex);
							$data_array[$i][$c] = $out2;
						}elseif ($c == $octet_col) {
							$data_array[$i][$c] = $out2;
							$out2 = plugin_flowview_formatoctet($out2);
						}elseif ($c == $proto_col) {
							$out2 = plugin_flowview_get_protocol($out2, $proto_hex);
							$data_array[$i][$c] = $out2;
						}else{
							$data_array[$i][$c] = $out2;
						}
						$o .= "<td align='" . get_column_alignment($columns[$c]) . "'>$out2</td>";
						$c++;
					}
				}
				$o .= "</tr>\n";
				$cut++;
			}
		}
		if ($cutoff_lines < $cut)
			break;
		$firstline = false;
		$i++;
	}

	if ($sessionid != -1) {
		$_SESSION['flowview_flows'][$sessionid]['data'] = $data_array;
	}

	$o .= '</table>';
	return $o;
}

function flowview_translate_port($port, $is_hex) {
	if ($is_hex) {
		$port = hexdec($port);
	}

	$service = db_fetch_cell("SELECT service FROM plugin_flowview_ports WHERE port=$port LIMIT 1");

	if ($service != '') {
		return "$service ($port)";
	}elseif ($port >= 49152) {
		return "dynamic ($port)";
	}else{
		return "unknown ($port)";
	}
}

function flowview_create_flowview_filter() {
	global $config;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	$filter = "filter-definition FlowViewer_filter\n";
	if ($source_address != "")
       	$filter .= "  match ip-source-address source_address\n";
	if ($source_if != "")
		$filter .= "  match input-interface source_if\n";
	if ($source_port != "")
		$filter .= "  match ip-source-port source_port\n";
	if ($source_as != "")
		$filter .= "  match source-as source_as\n";
	if ($dest_address != "")
       	$filter .= "  match ip-destination-address dest_address\n";
	if ($dest_if != "")
       	$filter .= "  match output-interface dest_if\n";
	if ($dest_port != "")
       	$filter .= "  match ip-destination-port dest_port\n";
	if ($dest_as != "")
       	$filter .= "  match destination-as dest_as\n";
	if ($protocols != "")
       	$filter .= "  match ip-protocol protocol\n";
	if ($tcp_flags != "")
       	$filter .= "  match ip-tcp-flags tcp_flag\n";
	if ($tos_fields != "")
       	$filter .= "  match ip-tos tos_field\n";
	switch ($flow_select) {
		case 1:
			$filter .= "  match end-time start_flows\n";
			$filter .= "  match start-time end_flows\n";
			break;
		case 2:
			$filter .= "  match end-time start_flows\n";
			$filter .= "  match end-time end_flows\n";
			break;
		case 3:
			$filter .= "  match start-time start_flows\n";
			$filter .= "  match start-time end_flows\n";
			break;
		case 4:
			$filter .= "  match start-time start_flows\n";
			$filter .= "  match end-time end_flows\n";
			break;
	}
	return $filter;
}

function flowview_create_time_filter($start, $end) {
	$filter = "filter-primitive start_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit ge " . date("F j, Y H:i:s ", $start) . "\n";
	$filter .= "   default deny\n";
	$filter .= "filter-primitive end_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit lt " . date("F j, Y H:i:s ", $end) . "\n";
	$filter .= "   default deny\n\n";
	return $filter;
}

function flowview_create_tos_field_filter ($tos_fields) {
	if ($tos_fields == '')
		return '';
	$filter  = "filter-primitive tos_field\n";
	$filter .= "   type ip-tos\n";
	$tos_fields = str_replace(' ', '', $tos_fields);
	$s_if = explode(',', $tos_fields);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$s = explode('/', $s);
				if (isset($s[1]))
					$filter .= "   mask " . $s[1] . "\n";
				$filter .= "   deny " . $s[0] . "\n";
				$excluded = true;
		} else {
			$s = explode('/', $s);
			if (isset($s[1]))
				$filter .= "   mask " . $s[1] . "\n";
			$filter .= "   permit " . $s[0] . "\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_tcp_flag_filter ($tcp_flags) {
	if ($tcp_flags == '')
		return '';
	$filter  = "filter-primitive tcp_flag\n";
	$filter .= "   type ip-tcp-flag\n";
	$tcp_flags = str_replace(' ', '', $tcp_flags);
	$s_if = explode(',',$tcp_flags);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$s = explode('/', $s);
				if (isset($s[1]))
					$filter .= "   mask " . $s[1] . "\n";
				$filter .= "   deny " . $s[0] . "\n";
				$excluded = true;
		} else {
			$s = explode('/', $s);
			if (isset($s[1]))
				$filter .= "   mask " . $s[1] . "\n";
			$filter .= "   permit " . $s[0] . "\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_protocol_filter ($protocols) {
	if ($protocols == '')
		return '';
	$filter  = "filter-primitive protocol\n";
	$filter .= "   type ip-protocol\n";
	$protocols = str_replace(' ', '', $protocols);
	$s_if = explode(',',$protocols);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_as_filter ($as, $type) {
	if ($as == '')
		return '';
	$filter  = "filter-primitive $type" . "_as\n";
	$filter .= "   type as\n";
	$as = str_replace(' ', '', $as);
	$s_if = explode(',',$as);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_port_filter ($port, $type) {
	if ($port == '')
		return '';
	$filter  = "filter-primitive $type" . "_port\n";
	$filter .= "   type ip-port\n";
	$port = str_replace(' ', '', $port);
	$s_if = explode(',',$port);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_if_filter ($source_if, $type) {
	if ($source_if == '')
		return '';
	$filter  = "filter-primitive $type" . "_if\n";
	$filter .= "   type ifindex\n";
	$source_if = str_replace(' ', '', $source_if);
	$s_if = explode(',',$source_if);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function flowview_create_ip_filter ($source_address, $type) {
	if ($source_address == '')
		return;
	$filter  = "filter-primitive $type" . "_address\n";
	$filter .= "   type ip-address-prefix\n";
	$exclude = false;
	$s_a = explode(',', $source_address);
	$excluded = false;
	foreach ($s_a as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function getfolderpath($n, $device, $start, $end) {
	$folderpath = '';

	// Add Flow Interval plus 1
	$end = $end + 300 + 1;

	$dir = read_config_option('path_flows_dir');
	if ($dir == '')
		$dir = '/var/netflow/flows/completed';
	if (substr($dir, -1 , 1) == '/')
		$dir = substr($dir, 0, -1);

	if ($device != '')
		$dir .= "/$device";

	switch ($n) {
		case -2:
 		case -1:
		case 0:
		case 1:
		case 2:
		case -3:
		case 3:
 			$start = strtotime(date('m/d/Y', $start));
 			break;
 		case 4:
 			$start = strtotime(date('m/d/Y G:00', $start));
  			break;
 	}

	while ($start < $end) {
		$y = date("Y", $start);
		$m = date("m", $start);
		$d = date("d", $start);
		$h = date("G", $start);
		$folderpath .= $dir;
		switch ($n) {
			case -2:
				$folderpath .= "/$y-$m/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case -1:
				$folderpath .= "/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case 0:
				$folderpath .= ' ';
				$start = $start + 86400;
				break;
			case 1:
				$folderpath .= "/$y ";
				$start = $start + 86400;
				break;
			case 2:
				$folderpath .= "/$y/$y-$m ";
				$start = $start + 86400;
				break;
			case -3:
			case 3:
				$folderpath .= "/$y/$y-$m/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case 4:
				$folderpath .= "/$y-$m-$d-$h ";
				$start = $start + 3600;
				break;
		}
	}
	return $folderpath;
}

function flowview_check_fields () {
	global $config;

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if ($stat_report == 0 && $print_report == 0)
		return "You must select a Statistics Report or Printed Report!";

	if ($stat_report > 0 && $print_report > 0)
		return "You must select only a Statistics Report or a Printed Report (not both)!";

	if (!ereg("^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$", $start_date) && $start_date)
		return "Invalid start date!<br>Must be in the format (mm/dd/yyyy)";

	if (!ereg("^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$", $end_date) && $end_date != '')
		return "Invalid end date!<br>Must be in the format (mm/dd/yyyy)";

	if (!ereg("^[0-9]{1,2}:[0-9]{2}(:[0-9]{2}){0,1}$", $start_time) && !ereg("^[-]{1,1}[0-9]{1,3}[ ]{0,1}(HOUR|HOURS|DAY|DAYS|MINUTE|MINUTES)$", $start_time))
		return "Invalid start time!<br>Must be in the format (hh:mm or hh:mm:ss)";

	if (!ereg("^[0-9]{1,2}:[0-9]{2}(:[0-9]{2}){0,1}$", $end_time) && $end_time != 'NOW')
		return "Invalid end time!<br>Must be in the format (hh:mm or hh:mm:ss)";

	if (strtotime($start_date . ' ' . $start_time) > strtotime($end_date . ' ' . $end_time))
		return "Invalid dates, End Date/Time is earlier than Start Date/Time!";

	if ($source_address != '') {
		$a = explode(',',$source_address);
		foreach ($a as $source_a) {
			$s = explode('/',$source_a);
			$source_ip = $s[0];
			if (!ereg("^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $source_ip)) {
				return "Invalid IP for the Source Address!<br>(Must be in the form of '192.168.0.1')";
			}
			$subs = explode('.', $source_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return "Invalid IP for the Source Address!<br>(Must be in the form of '192.168.0.1')";
			}
			if (isset($s[1])) {
				$subnet = $s[1];
				if (!ereg("^[0-9]{1,3}$", $subnet)) {
					if (!ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $subnet)) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
					$subs = explode('.', $subnet);
					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				}
			}
		}
	}

	if ($dest_address != '') {
		$a = explode(',',$dest_address);
		foreach ($a as $dest_a) {
			$s = explode('/',$dest_a);
			$dest_ip = $s[0];
			if (!ereg("^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $dest_ip)) {
				return "Invalid IP for the Destination Address!<br>(Must be in the form of '192.168.0.1')";
			}
			$subs = explode('.', $dest_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return "Invalid IP for the Destination Address!<br>(Must be in the form of '192.168.0.1')";
			}
			if (isset($s[1])) {
				$subnet = $s[1];
				if (!ereg("^[0-9]{1,3}$", $subnet)) {
					if (!ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $subnet)) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
					$subs = explode('.', $subnet);
					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				}
			}
		}
	}

	if ($source_if != '') {
		$source_if = str_replace(' ', '', $source_if);
		$s_if = explode(',',$source_if);
		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 999 || !is_numeric($s))
					return "Invalid value for Source Interface!";
		}
	}

	if ($source_port != '') {
		$source_port = str_replace(' ', '', $source_port);
		$s_port = explode(',',$source_port);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Source Port! (0 - 65535)";
		}
	}

	if ($source_as != '') {
		$source_as = str_replace(' ', '', $source_as);
		$s_as = explode(',',$source_as);
		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Source AS! (0 - 65535)";
		}
	}

	if ($dest_if != '') {
		$dest_if = str_replace(' ', '', $dest_if);
		$s_if = explode(',',$dest_if);
		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 999 || !is_numeric($s))
					return "Invalid value for Destination Interface!";
		}
	}

	if ($dest_port != '') {
		$dest_port = str_replace(' ', '', $dest_port);
		$s_port = explode(',',$dest_port);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Destination Port! (0 - 65535)";
		}
	}

	if ($dest_as != '') {
		$dest_as = str_replace(' ', '', $dest_as);
		$s_as = explode(',',$dest_as);
		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Destination AS! (0 - 65535)";
		}
	}

	if ($protocols != '') {
		$protocols = str_replace(' ', '', $protocols);
		$s_port = explode(',', $protocols);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 255 || $s < 0 || !is_numeric($s))
					return "Invalid value for Portocol! (1 - 255)";
		}
	}

	if ($tcp_flags != '') {
		$tcp_flags = str_replace(' ', '', $tcp_flags);
		$tcp_flag = explode(',', $tcp_flags);
		foreach ($tcp_flag as $t) {
			if (!ereg("^[-]{0,1}((0x[0-9a-zA-Z]{1,3})|([0-9a-zA-Z]{1,3}))(/[0-9a-zA-Z]{1,3}){0,1}$", $t)) {
					return "Invalid value for TCP Flag! (ex: 0x1b or 0x1b/SA or SA/SA)";
			}
		}
	}
	if ($cutoff_octets != '' && ($cutoff_octets < 0 || $cutoff_octets > 99999999999999999 || !is_numeric($cutoff_octets)))
		return "Invalid value for Cutoff Octets!";

	if ($cutoff_lines != '' && ($cutoff_lines < 0 || $cutoff_lines > 999999 || !is_numeric($cutoff_lines)))
		return "Invalid value for Cutoff Lines!";

	if ($sort_field != '' && ($sort_field < 0 || $sort_field > 99 || !is_numeric($sort_field)))
		return "Invalid value for Sort Field!";

}

function flowview_draw_chart($type, $title) {
	global $colors, $config;
	static $chartid = 0;

	echo "<div id='wrapper" . $type . "' style='display:none;'>";
	html_start_box("<strong>FlowView Chart for " . $title . " Type is " . ucfirst($type) . "</strong>", "100%", $colors["header"], "3", "center", "");
	echo "<tr style='background-color:#F9F9F9;'><td align='center'>";
	echo "<div id='chart$type'></div>";
	echo "</td></tr>";
	html_end_box(false);
	echo "</div>";

	$chartid++;
}

/*	flowview_get_dns_from_ip - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function flowview_get_dns_from_ip($ip, $dns, $timeout = 1000) {
	// First check to see if its in the cache
	$cache = db_fetch_assoc("SELECT * from plugin_flowview_dnscache where ip='$ip'");

	if (isset($cache[0]['host']))
		return $cache[0]['host'];

	$time = time();

	$slashpos = strpos($ip, '/');
	if ($slashpos) {
		$suffix = substr($ip,$slashpos);
		$ip = substr($ip, 0,$slashpos);
	}else{
		$suffix = "";
	}

	if (read_config_option("flowview_dns_method") == 1) {
		/* random transaction number (for routers etc to get the reply back) */
		$data = rand(10, 99);

		/* trim it to 2 bytes */
		$data = substr($data, 0, 2);

		/* create request header */
		$data .= "\1\0\0\1\0\0\0\0\0\0";

		/* split IP into octets */
		$octets = explode(".", $ip);

		/* perform a quick error check */
		if (count($octets) != 4) return "ERROR";

		/* needs a byte to indicate the length of each segment of the request */
		for ($x=3; $x>=0; $x--) {
			switch (strlen($octets[$x])) {
			case 1: // 1 byte long segment
				$data .= "\1"; break;
			case 2: // 2 byte long segment
				$data .= "\2"; break;
			case 3: // 3 byte long segment
				$data .= "\3"; break;
			default: // segment is too big, invalid IP
				return "ERROR";
			}

			/* and the segment itself */
			$data .= $octets[$x];
		}

		/* and the final bit of the request */
		$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

		/* create UDP socket */
		$handle = @fsockopen("udp://$dns", 53);

		@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
		@stream_set_blocking($handle, 1);

		/* send our request (and store request size so we can cheat later) */
		$requestsize = @fwrite($handle, $data);

		/* get the response */
		$response = @fread($handle, 1000);

		/* check to see if it timed out */
		$info = @stream_get_meta_data($handle);

		/* close the socket */
		@fclose($handle);

		if ($info["timed_out"]) {
			db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
			return $ip . $suffix;
		}

		/* more error handling */
		if ($response == "") {
			db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
			return $ip;
		}

		/* parse the response and find the response type */
		$type = @unpack("s", substr($response, $requestsize+2));

		if ($type[1] == 0x0C00) {
			/* set up our variables */
			$host = "";
			$len = 0;

			/* set our pointer at the beginning of the hostname uses the request
			   size from earlier rather than work it out.
			*/
			$position = $requestsize + 12;

			/* reconstruct the hostname */
			do {
				/* get segment size */
				$len = unpack("c", substr($response, $position));

				/* null terminated string, so length 0 = finished */
				if ($len[1] == 0) {
					$hostname = flowview_strip_dns(substr($host, 0, strlen($host) -1));
					/* return the hostname, without the trailing '.' */
					db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '" . $hostname . "', '$time')");
					return $hostname . $suffix;
				}

				/* add the next segment to our host */
				$host .= substr($response, $position+1, $len[1]) . ".";

				/* move pointer on to the next segment */
				$position += $len[1] + 1;
			} while ($len != 0);

			/* error - return the hostname we constructed (without the . on the end) */
			db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
			return $ip . $suffix;
		}
	}else{
		$address = @gethostbyaddr($ip);
		$dns_name = $ip;

		if ($address !== false) {
			$dns_name = flowview_strip_dns($address);
		}

		if ($dns_name != $ip) {
			db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$dns_name', '" . ($time - 3540) . "')");
			return $dns_name . $suffix;
		}
	}

	/* error - return the hostname */
	db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
	return $ip . $suffix;
}

function flowview_strip_dns($value) {
	$strip = read_config_option("flowview_strip_dns");

	if (strlen($strip)) {
		$strips = explode(",", $strip);

		foreach($strips as $s) {
			$value = trim(str_replace($s, "", $value), ".");
		}
	}

	return $value;
}

function flowview_get_color($as_array = false) {
	static $position = 0;
	$pallette = array("#F23C2E", "#32599A", "#F18A47", "#AC9509", "#DAAC10");

	if ($as_array) {
		$position = 0;
		return $pallette;
	}else{
		$color = $pallette[$position % sizeof($pallette)];
		$position++;
		return $color;
	}
}

/** flowview_updatesess()
 *
 * This function will update the checkbox
 * session values for page refreshes.
 */
function flowview_updatesess() {
	$_SESSION['sess_flows_' . $_REQUEST['type']] = $_REQUEST['value'];
}

/** flowview_viewtable() 
 *
 *  This function is will echo the stored table
 *  less any outliers back to the browser.
 */
function flowview_viewtable() {
	global $config;

	$sessionid  = $_REQUEST["session"];
	$flowdata  = unserialize(base64_decode($_REQUEST['session']));
	foreach($flowdata['post'] as $item => $value) {
		switch ($item) {
		case 'bytes':
		case 'flows':
		case 'packets':
			break;
		default:
			$_POST[$item] = $value;
		}
	}

    /* remember these search fields in session vars so we don't have to keep passing them around */
	$_SESSION['sess_flows_table'] = 'on';

	include($config['base_path'] . '/plugins/flowview/variables.php');

	$title      = $_REQUEST["title"];
	$output     = $_SESSION['flowview_flows'][$sessionid]['rawdata'];

	echo parsestatoutput($output, $title, $sessionid);
}

/** flowview_viewchart()
 *
 *  This function is taken from Slowlog.  Given
 *  a title, chart type and chart data, it will
 *  echo the required syntax for the Callback
 *  from the chart page to operate corectly.
 */
function flowview_viewchart() {
	global $colors, $config;

	include($config['base_path'] . "/plugins/flowview/lib/open-flash-chart-object.php");
	include($config['base_path'] . "/plugins/flowview/lib/open-flash-chart.php");

	$title      = $_REQUEST["title"];
	$chart_type = "bar";
	$column     = $_REQUEST["type"];
	$sessionid  = $_REQUEST["session"];

	/* get the chart data from the session */
	if (isset($_SESSION['flowview_flows'][$sessionid]['data'])) {
		$data = $_SESSION['flowview_flows'][$sessionid]['data'];
	}else{
		$filter = createfilter($sessionid);
		$data = $_SESSION['flowview_flows'][$sessionid]['data'];
	}

	switch($column) {
	case 'flows':
		$unit = ucfirst($column);
		$suffix = "Total Flows";
		$_SESSION['sess_flows_flows'] = 'on';

		break;
	case 'bytes':
		$unit = ucfirst($column);
		$suffix = "Bytes Exchanged";
		$_SESSION['sess_flows_bytes'] = 'on';
		break;
	case 'packets':
		$unit = ucfirst($column);
		$suffix = "Packets Examined";
		$_SESSION['sess_flows_packets'] = 'on';
		break;
	}

	$columns = $_SESSION['flowview_flows'][$sessionid]['columns'];
	foreach ($columns as $key=>$cdata) {
		if (strtolower($cdata) == $column) {
			$column = $key;
		}
	}

	if (sizeof($data)) {
		$elements = array();
		$legend   = array();
		$maxvalue = 0;

		if (isset($_REQUEST['exclude']) && $_REQUEST['exclude'] > 0) {
			for($i = 0; $i < $_REQUEST['exclude']; $i++) {
				array_shift($data);
			}
		}

		foreach($data as $row) {
			if ($maxvalue < $row[$column]) {
				$maxvalue = $row[$column];
				$scaling  = flowview_autoscale($row[$column]);
			}
		}

		$maxvalue  = flowview_getmax($maxvalue);
		$autorange = flowview_autoscale($maxvalue);
		$maxvalue  = $maxvalue / $autorange[0];

		$i = 0;
		foreach($data as $row) {
			$elements[$i] = new bar_value(round($row[$column]/$autorange[0], 3));
			$elements[$i]->set_colour(flowview_get_color());
			$elements[$i]->set_tooltip($unit . ": #val# " . $autorange[1]);
			if (sizeof($row) == 4) {
				$legend[] = $row[0];
			}else{
				$legend[] = $row[0] . " -\n" . $row[1];
			}
			$i++;
		}

		$bar = new bar_glass();
		$bar->set_values($elements);

		$title = new title($title . " (" . $suffix . ")");
		$title->set_style("{font-size: 18px; color: #444444; text-align: center;}");

		$x_axis_labels = new x_axis_labels();
		$x_axis_labels->set_size(10);
		$x_axis_labels->rotate(45);
		$x_axis_labels->set_labels($legend);

		$x_axis = new x_axis();
		//$x_axis->set_3d( 3 );
		$x_axis->set_colours('#909090', '#909090');
		$x_axis->set_labels( $x_axis_labels );

		$y_axis = new y_axis();
		$y_axis->set_offset(true);
		$y_axis->set_colours('#909090', '#909090');
		$y_axis->set_range(0, $maxvalue, $maxvalue/10);
		$y_axis->set_label_text("#val# " . $autorange[1]);

		$chart = new open_flash_chart();
		$chart->set_title($title);
		$chart->add_element($bar);
		$chart->set_x_axis($x_axis);
		$chart->add_y_axis($y_axis);
		$chart->set_bg_colour('#FEFEFE');
		echo $chart->toString();
	}
}

function flowview_getmax($value) {
	$value = round($value * 1.01, 0);

	$length  = strlen($value) - 2;
	if ($length > 0) {
		$divisor = ("1" . str_repeat("0", $length));
	}else{
		$divisor = 1;
	}

	$temp = $value / $divisor;
	$temp = ceil($temp);

	return $temp * $divisor;
}

function flowview_autoscale($value) {
	if ($value < 10000) {
		return  array(1, "");
	}elseif ($value < 1000000) {
		return array(1000, "K");
	}elseif ($value < 100000000) {
		return array(1000000, "M");
	}elseif ($value < 10000000000) {
		return array(100000000, "G");
	}else{
		return array(10000000000, "P");
	}
}

?>
