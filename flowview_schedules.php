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

include("./include/auth.php");

include_once($config['base_path'] . '/plugins/flowview/functions.php');

$ds_actions = array(1 => "Delete");

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}


$sendinterval_arr = array(
			3600 => 'Every Hour',
			7200 => 'Every 2 Hours',
			14400 => 'Every 4 Hours',
			21600 => 'Every 6 Hours',
			43200 => 'Every 12 Hours',
			86400 => 'Every Day',
			432000 => 'Every Week',
			864000 => 'Every 2 Weeks',
			1728000 => 'Every Month',
			);

$schedule_edit = array(
	"enabled" => array(
		"friendly_name" => "Enabled",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Whether or not this Netflow Scan will be sent.",
		"value" => "|arg1:enabled|",
	),
	"savedquery" => array(
		"method" => "drop_sql",
		"friendly_name" => "Query Name",
		"description" => "Name of the query to run.",
		"value" => "|arg1:savedquery|",
		"sql" => "SELECT id, name FROM plugin_flowview_queries"
		),
	"sendinterval" => array(
		"friendly_name" => "Send Interval",
		"description" => "How often to send this Netflow Query.",
		"value" => "|arg1:sendinterval|",
		"method" => "drop_array",
		'default' => '0',
		"array" => $sendinterval_arr
		),
	"start" => array(
		"method" => "textbox",
		"friendly_name" => "Start Time",
		"description" => "This is the first date / time to send the Netflow Scan email.  All future sendings will be calculated off of this time plus the interval given above.",
		"value" => "|arg1:start|",
		"max_length" => '26',
		"size" => 20,
		"default" => date("Y-m-d G:i:s", time())
		),
	"email" => array(
		"method" => "textbox",
		"friendly_name" => "Email Addresses",
		"description" => "Email addresses (command delimitinated) to send this Netflow Scan to.",
		"value" => "|arg1:email|",
		"max_length" => '1000'
		),

	"id" => array(
		"method" => "hidden_zero",
		"value" => "|arg1:id|"
		),
	);

switch ($action) {
	case 'actions':
		actions_schedules();
		break;
	case 'save':
		save_schedules ();
		break;
	case 'edit':
		include_once("./include/top_header.php");
		display_tabs ();
		edit_devices();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./include/top_header.php");
		display_tabs ();
		show_schedules ();
		include_once("./include/bottom_footer.php");
		break;
}

function display_tabs () {
	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='98%' cellspacing='0' cellpadding='3' align='center'><tr>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Viewer') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview.php'>Viewer</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='#DFDFDF' nowrap='nowrap' width='" . (strlen('Devices') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview_devices.php'>Devices</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td bgcolor='silver' nowrap='nowrap' width='" . (strlen('Schedules') * 9) . "' align='center' class='tab'>
			<span class='textHeader'><a href='flowview_schedules.php'>Schedules</a></span>
			</td>\n
			<td width='1'></td>\n";
	print "<td></td>\n</tr></table>\n";
}

function actions_schedules () {
	global $colors, $ds_actions, $config;
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));
		if ($_POST["drp_action"] == "1") {

			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("DELETE FROM plugin_flowview_schedules WHERE id = " . $selected_items[$i]);
			}
		}
		header("Location: flowview_schedules.php");
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

			$device_list .= "<li>" . db_fetch_cell("select name from plugin_flowview_schedules where id=" . $matches[1]) . "<br>";
			$device_array[$i] = $matches[1];
		}
		$i++;
	}

	include_once("./include/top_header.php");
	//display_tabs ();

	html_start_box("<strong>" . $ds_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='flowview_schedules.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Delete */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To delete the following schedules, press the \"yes\" button below.</p>
					<p>$device_list</p>
				</td>
				</tr>";
	}

	if (!isset($device_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one schedule.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='image' src='" . $config['url_path'] . "images/button_yes.gif' alt='Save' align='absmiddle'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<a href='flowview_schedules.php'><img src='" . $config['url_path'] . "images/button_no.gif' alt='Cancel' align='absmiddle' border='0'></a>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");


}

function save_schedules () {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('savedquery'));
	input_validate_input_number(get_request_var_post('sendinterval'));
	/* ==================================================== */

	$save['savedquery'] = $_POST['savedquery'];
	$save['sendinterval'] = $_POST['sendinterval'];
	$save['start'] = sql_sanitize($_POST['start']);
	$save['email'] = sql_sanitize($_POST['email']);

	$t = time();
	$d = strtotime($_POST['start']);
	$i = $save['sendinterval'];
	if (isset($_POST['id'])) {
		$save['id'] = $_POST['id'];
		$q = db_fetch_row("SELECT * FROM plugin_flowview_schedules WHERE id = " . $save['id']);
		if (!isset($q['lastsent']) || $save['start'] != $q['start'] || $save['sendinterval'] != $q['sendinterval']) {
			while ($d < $t) {
				$d += $i;
			}
			$save['lastsent'] = $d - $i;
		}
	} else {
		$save['id'] = '';
		while ($d < $t) {
			$d += $i;
		}
		$save['lastsent'] = $d - $i;
	}

	if (isset($_POST["enabled"]))
		$save["enabled"] = 'on';
	else
		$save["enabled"] = 'off';

	$id = sql_save($save, 'plugin_flowview_schedules', 'id', true);

	if (is_error_message()) {
		header('Location: flowview_schedules.php?action=edit&id=' . (empty($id) ? $_POST['id'] : $id));
		exit;
	}
	header("Location: flowview_schedules.php");
	exit;
}

function edit_devices () {
	global $schedule_edit, $colors;

		print '		<script type="text/javascript" src="/include/jscalendar/calendar.js"></script>
			<script type="text/javascript" src="/include/jscalendar/lang/calendar-en.js"></script>
			<script type="text/javascript" src="/include/jscalendar/calendar-setup.js"></script>';

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$device = array();
	if (!empty($_GET["id"])) {
		$device = db_fetch_row("SELECT plugin_flowview_schedules.*,plugin_flowview_queries.name FROM plugin_flowview_schedules LEFT JOIN plugin_flowview_queries ON (plugin_flowview_schedules.savedquery = plugin_flowview_queries.id) WHERE plugin_flowview_schedules.id=" . $_GET["id"], FALSE);
		$header_label = "[edit: " . $device["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Device:</strong> $header_label", "98%", $colors["header"], "3", "center", "");
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($schedule_edit, $device)
		)
	);

	html_end_box();
	form_save_button("flowview_schedules.php");

?>
	<script type='text/javascript'>
	// Initialize the calendar
	calendar=null;

	// This function displays the calendar associated to the input field 'id'
	function showCalendar2() {

		var el = document.getElementById('start');
		if (calendar != null) {
			// we already have some calendar created
			calendar.hide();  // so we hide it first.
		} else {
			// first-time call, create the calendar.
			var cal = new Calendar(true, null, selected, closeHandler);
			cal.weekNumbers = false;  // Do not display the week number
			cal.showsTime = true;     // Display the time
			cal.time24 = true;        // Hours have a 24 hours format
			cal.showsOtherMonths = false;    // Just the current month is displayed
			calendar = cal;                  // remember it in the global var
			cal.setRange(1900, 2070);        // min/max year allowed.
			cal.create();
		}

		calendar.setDateFormat('%Y-%m-%d %H:%M');    // set the specified date format
		calendar.parseDate(el.value);                // try to parse the text in field
		calendar.sel = el;                           // inform it what input field we use

		// Display the calendar below the input field
		calendar.showAtElement(el, "Br");        // show the calendar

		return true;
	}

	// This function update the date in the input field when selected
	function selected(cal, date) {
		cal.sel.value = date;      // just update the date in the input field.
	}

	// This function gets called when the end-user clicks on the 'Close' button.
	// It just hides the calendar without destroying it.
	function closeHandler(cal) {
		cal.hide();                        // hide the calendar
		calendar = null;
	}

	var el2 = document.getElementById('start');
//	el2.OnClick = showCalendar2();



var myDiv = document.getElementById('start');
if (myDiv.addEventListener){
  myDiv.addEventListener('click', showCalendar2, false);
} else if (myDiv.attachEvent){
  myDiv.attachEvent('onclick', showCalendar2);
}

</script>

<?




}

function show_schedules () {
	global $action, $sendinterval_arr;
	global $colors, $config, $ds_actions;

	load_current_session_value("page", "sess_flowview_schedules_current_page", "1");
	$num_rows = 30;

	$sql = "SELECT plugin_flowview_schedules.*,plugin_flowview_queries.name FROM plugin_flowview_schedules LEFT JOIN plugin_flowview_queries ON (plugin_flowview_schedules.savedquery = plugin_flowview_queries.id) limit " . ($num_rows*($_REQUEST["page"]-1)) . ", $num_rows";
	$result = db_fetch_assoc($sql);

	define("MAX_DISPLAY_PAGES", 21);
	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_flowview_schedules");
	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "flowview_schedules.php?");

	html_start_box("", "98%", $colors["header"], "4", "center", "");
	$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='10'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='flowview_schedules.php?page=" . ($_REQUEST["page"]-1) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='flowview_schedules.php?page=" . ($_REQUEST["page"]+1) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";

	print $nav;
	html_header_checkbox(array('Query', 'Interval', 'Start Date', 'Next Send', 'Email', 'Enabled'));

	$c=0;
	$i=0;
	foreach ($result as $row) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print '<td><a href="flowview_schedules.php?&action=edit&id=' . $row['id'] . '">' . $row['name'] . '</a></td>';
		print '<td>' . $sendinterval_arr[$row['sendinterval']] . '</td>';
		print '<td>' . $row['start'] . '</td>';
		print '<td>' . date("Y-m-d G:i:s", $row['lastsent']+$row['sendinterval']) . '</td>';
		print '<td>' . $row['email'] . '</td>';

		print '<td>' . ($row['enabled'] == 'on' ? "<font color=green><b>Yes</b></font>" :  "<font color=red><b>No</b></font>" ). '</td>';

		print '<td style="' . get_checkbox_style() . '" width="1%" align="right">';
		print '<input type="checkbox" style="margin: 0px;" name="chk_' . $row["id"] . '" title="' . $row["name"] . '"></td>';
		print "</tr>";
	}
	html_end_box(false);
	draw_actions_dropdown($ds_actions);

	print "&nbsp;&nbsp;&nbsp;<a href='flowview_schedules.php?action=edit'><img border=0 src='" . $config['url_path'] . "images/button_add.gif'></a>";

}






