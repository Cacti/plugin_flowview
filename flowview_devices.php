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
include("./include/auth.php");
include_once($config['base_path'] . '/plugins/flowview/functions.php');

$ds_actions = array(1 => "Delete");

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

$expire_arr = array(
	2   => '2 Days',
	5   => '5 Days',
	7   => '1 Week',
	14  => '2 Weeks',
	30  => '1 Month',
	61  => '2 Months',
	92  => '3 Months',
	183 => '6 Months',
	365 => '1 Year',
);

$rotation_arr = array(
	1439 => '1 Minute',
	287 => '5 Minutes',
	144 => '10 Minutes',
	95 => '15 Minutes',
);

$version_arr = array(
	'1'    => 'NetFlow version 1',
	'5'    => 'NetFlow version 5',
	'6'    => 'NetFlow version 6',
	'7'    => 'NetFlow version 7',
	'8.1'  => 'NetFlow AS Aggregation',
	'8.2'  => 'NetFlow Proto Port Aggregation',
	'8.3'  => 'NetFlow Source Prefix Aggregation',
	'8.4'  => 'NetFlow Destination Prefix Aggregation',
	'8.5'  => 'NetFlow Prefix Aggregation',
	'8.6'  => 'NetFlow Destination',
	'8.7'  => 'NetFlow Source Destination',
	'8.8'  => 'NetFlow Full Flow',
	'8.9'  => 'NetFlow ToS AS Aggregation',
	'8.10' => 'NetFlow ToS Proto Port Aggregation',
	'8.11' => 'NetFlow ToS Source Prefix Aggregation',
	'8.12' => 'NetFlow ToS Destination Prefix Aggregation',
	'8.13' => 'NetFlow ToS Prefix Aggregation',
	'8.14' => 'NetFlow ToS Prefix Port Aggregation',
	'1005' => 'Flow-Tools tagged version 5',
);

$nesting_arr = array(
	-2 => '/YYYY-MM/YYYY-MM-DD',
	-1 => '/YYYY-MM-DD',
	0  => '/',
	1  => '/YYYY',
	2  => '/YYYY/YYYY-MM',
	3  => '/YYYY/YYYY-MM/YYYY-MM-DD'
);

$compression_arr = array(
	0 => '0 (Disabled)',
	1 => '1',
	2 => '2',
	3 => '3',
	4 => '4',
	5 => '5',
	6 => '6',
	7 => '7',
	8 => '8',
	9 => '9 (Highest)'
);

$device_edit = array(
	"name" => array(
		"method" => "textbox",
		"friendly_name" => "Device Name",
		"description" => "Name of the device to be displayed.",
		"value" => "|arg1:name|",
		"max_length" => "64",
	),
	"folder" => array(
		"method" => "textbox",
		"friendly_name" => "Directory",
		"description" => "Directory that this devices flows are in.  This directory must be in the Flow Directory path.  Do not put the full path here.",
		"value" => "|arg1:folder|",
		"max_length" => "64",
	),
	"allowfrom" => array(
		"method" => "textbox",
		"friendly_name" => "Allowed Host",
		"description" => "IP Address of the device that is allowed to send to this flow collector.  Leave as 0 for any host.",
		"value" => "|arg1:allowfrom|",
		"default" => '0',
		"max_length" => "64",
		"size" => "30"
	),
	"port" => array(
		"method" => "textbox",
		"friendly_name" => "Port",
		"description" => "Port this collector will listen on.",
		"value" => "|arg1:port|",
		"default" => '2055',
		"max_length" => "5",
		"size" => "30"
	),
	"nesting" => array(
		"friendly_name" => "Nesting",
		"description" => "Directory Structure that will be used for the flows for this device.",
		"value" => "|arg1:nesting|",
		"method" => "drop_array",
		'default' => '-1',
		"array" => $nesting_arr
	),
	"version" => array(
		"friendly_name" => "Netflow Version",
		"description" => "Netflow Protocol version used by the device.",
		"value" => "|arg1:version|",
		"method" => "drop_array",
		'default' => '5',
		"array" => $version_arr
	),
	"compression" => array(
		"friendly_name" => "Compression Level",
		"description" => "Compression level of flow files.  Higher compression saves space but uses more CPU to store and retrieve results.",
		"value" => "|arg1:compression|",
		"method" => "drop_array",
		'default' => '0',
		"array" => $compression_arr,
	),
	"rotation" => array(
		"friendly_name" => "Rotation",
		"description" => "How often to create a new Flow File.",
		"value" => "|arg1:rotation|",
		"method" => "drop_array",
		'default' => '1439',
		"array" => $rotation_arr
	),
	"expire" => array(
		"friendly_name" => "Expiration",
		"description" => "How long to keep your flow files.",
		"value" => "|arg1:expire|",
		"method" => "drop_array",
		'default' => '0',
		"array" => $expire_arr
	),
	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
	),
);

switch ($action) {
	case 'actions':
		actions_devices();
		break;
	case 'save':
		save_devices ();
		break;
	case 'edit':
		include_once("./plugins/flowview/general_header.php");
		display_tabs ();
		edit_devices();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./plugins/flowview/general_header.php");
		display_tabs ();
		show_devices ();
		include_once("./include/bottom_footer.php");
		break;
}

function actions_devices () {
	global $colors, $ds_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		if ($_POST["drp_action"] == "1") {

			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("DELETE FROM plugin_flowview_devices WHERE id = " . $selected_items[$i]);
			}
		}
		header("Location: flowview_devices.php");
		exit;
	}


	/* setup some variables */
	$device_list = "";
	$i = 0;

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_list .= "<li>" . db_fetch_cell("select name from plugin_flowview_devices where id=" . $matches[1]) . "</li>";
			$device_array[$i] = $matches[1];
		}
		$i++;
	}

	include_once("./plugins/flowview/general_header.php");
	//display_tabs ();

	html_start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='flowview_devices.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Delete */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To delete the following Net-Flow Listeners, press the 'Continue' button below.  After which, you will need to restart your Flow-Capture Service.</p>
					<p>Also, remember to remove any left over files from your Net-Flow Capture location.</p>
					<p><ul>$device_list</ul></p>
				</td>
				</tr>";
	}

	if (!isset($device_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one device.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Continue'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<input type='button' onClick='javascript:document.location=\"flowview_devices.php\"' value='Cancel'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function save_devices () {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('version'));
	input_validate_input_number(get_request_var_post('rotation'));
	input_validate_input_number(get_request_var_post('expire'));
	input_validate_input_number(get_request_var_post('port'));
	input_validate_input_number(get_request_var_post('compression'));
	/* ==================================================== */

	if (isset($_POST['id'])) {
		$save['id'] = $_POST['id'];
	} else {
		$save['id'] = '';
	}

	$save['name']        = sql_sanitize($_POST['name']);
	$save['folder']      = sql_sanitize($_POST['folder']);
	$save['allowfrom']   = sql_sanitize($_POST['allowfrom']);
	$save['port']        = $_POST['port'];
	$save['nesting']     = sql_sanitize($_POST['nesting']);
	$save['version']     = $_POST['version'];
	$save['rotation']    = $_POST['rotation'];
	$save['expire']      = $_POST['expire'];
	$save['compression'] = $_POST['compression'];

	$id = sql_save($save, 'plugin_flowview_devices', 'id', true);

	if (is_error_message()) {
		header('Location: flowview_devices.php?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
		exit;
	}
	header("Location: flowview_devices.php");
	exit;
}

function edit_devices () {
	global $device_edit, $colors;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$device = array();
	if (!empty($_GET["id"])) {
		$device = db_fetch_row("SELECT * FROM plugin_flowview_devices WHERE id=" . $_GET["id"], FALSE);
		$header_label = "[edit: " . $device["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Device:</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($device_edit, $device)
		)
	);

	html_end_box();
	form_save_button("flowview_devices.php");
}

function show_devices () {
	global $action, $expire_arr, $rotation_arr, $version_arr, $nesting_arr;
	global $colors, $config, $ds_actions;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_flowview_current_page");
		kill_session_var("sess_flowview_filter");
		kill_session_var("sess_flowview_sort_column");
		kill_session_var("sess_flowview_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_flowview_current_page", "1");
	load_current_session_value("filter", "sess_flowview_filter", "");
	load_current_session_value("sort_column", "sess_flowview_sort_column", "name");
	load_current_session_value("sort_direction", "sess_flowview_sort_direction", "ASC");

	$sql_where = (strlen($_REQUEST["filter"]) ? "name LIKE '%" . $_REQUEST["filter"] . "%'":"");
	$num_rows  = read_config_option("num_rows_device");

	$sql    = "SELECT * 
		FROM plugin_flowview_devices 
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") .
		" LIMIT " . ($num_rows*(get_request_var_request("page")-1) . "," . $num_rows);

	$result = db_fetch_assoc($sql);

	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_flowview_devices $sql_where");
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "flowview_devices.php?");

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='flowview_devices.php'>\n";

	html_start_box("<strong>FlowView Listeners</strong>", "100%", $colors["header"], "4", "center", "flowview_devices.php?action=edit");

	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="listeners" action="flowview_devices.php">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td nowrap style='white-space: nowrap;' width="50">
						Search:&nbsp;
					</td>
					<td width="1">
						<input type="text" name="filter" size="40" value="<?php print htmlspecialchars(get_request_var_request("filter"));?>">
					</td>
					<td nowrap style='white-space: nowrap;'>
						&nbsp;<input type="submit" value="Go" title="Set/Refresh Filters">
						<input type="submit" name="clear" value="Clear" title="Clear Filters">
					</td>
				</tr>
			</table>
		<input type='hidden' name='page' value='1'>
		</form>
		</td>
	</tr>
	<?php

	html_end_box();

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='left' class='textHeaderDark'>
								<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("flowview_devices.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
							</td>\n
							<td align='center' class='textHeaderDark'>
								Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
							</td>\n
							<td align='right' class='textHeaderDark'>
								<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("flowview_devices.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
				<td colspan='10'>
					<table width='100%' cellspacing='0' cellpadding='0' border='0'>
						<tr>
							<td align='center' class='textHeaderDark'>
								No Rows Found
							</td>\n
						</tr>
					</table>
				</td>
			</tr>\n";
	}

	html_start_box("", "100%", $colors["header"], "4", "center", "");
	print $nav;

	$display_array = array(
		'name'        => array('Name', 'ASC'),
		'folder'      => array('Directory', 'ASC'),
		'nexting'     => array('Nesting', 'ASC'),
		'allowfrom'   => array('Allowed From', 'ASC'),
		'port'        => array('Port', 'ASC'),
		'version'     => array('Version', 'ASC'),
		'compression' => array('Compression', 'ASC'),
		'rotation'    => array('Rotation', 'ASC'),
		'expire'      => array('Expire', 'ASC')
	);

	html_header_sort_checkbox($display_array, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$c=0;
	$i=0;
	if (count($result)) {
		foreach ($result as $row) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $row['id']); $i++;
			form_selectable_cell('<a href="' . htmlspecialchars('flowview_devices.php?&action=edit&id=' . $row['id']) . '"><strong>' . $row['name'] . '</strong></a>', $row['id']);
			form_selectable_cell($row['folder'], $row['id']);
			form_selectable_cell($nesting_arr[$row['nesting']], $row['id']);
			form_selectable_cell($row['allowfrom'], $row['id']);
			form_selectable_cell($row['port'], $row['id']);
			form_selectable_cell($version_arr[$row['version']], $row['id']);
			form_selectable_cell($row['compression'], $row['id']);
			form_selectable_cell($rotation_arr[$row['rotation']], $row['id']);
			form_selectable_cell($expire_arr[$row['expire']], $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}

		print $nav;
	} else {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print '<td colspan=10><center>No Devices</center></td></tr>';
	}
	html_end_box(false);
	draw_actions_dropdown($ds_actions);
}

