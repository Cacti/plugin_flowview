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

$sched_actions = array(2 => "Send Now", 1 => "Delete", 3 => "Disable", 4 => "Enable");

$action = "";
if (isset($_POST['action'])) {
	$action = $_POST['action'];
} else if (isset($_GET['action'])) {
	$action = $_GET['action'];
}

$sendinterval_arr = array(
	3600    => 'Every Hour',
	7200    => 'Every 2 Hours',
	14400   => 'Every 4 Hours',
	21600   => 'Every 6 Hours',
	43200   => 'Every 12 Hours',
	86400   => 'Every Day',
	432000  => 'Every Week',
	864000  => 'Every 2 Weeks',
	1728000 => 'Every Month',
);

$schedule_edit = array(
	"title" => array(
		"friendly_name" => "Title",
		"method" => "textbox",
		"default" => "New Schedule",
		"description" => "Enter a Report Title for the FlowView Schedule.",
		"value" => "|arg1:title|",
		"max_length" => 128,
		"size" => 60
	),
	"enabled" => array(
		"friendly_name" => "Enabled",
		"method" => "checkbox",
		"default" => "on",
		"description" => "Whether or not this Netflow Scan will be sent.",
		"value" => "|arg1:enabled|",
	),
	"savedquery" => array(
		"method" => "drop_sql",
		"friendly_name" => "Filter Name",
		"description" => "Name of the query to run.",
		"value" => "|arg1:savedquery|",
		"sql" => "SELECT id, name FROM plugin_flowview_queries"
	),
	"sendinterval" => array(
		"friendly_name" => "Send Interval",
		"description" => "How often to send this Netflow Report?",
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
		"method" => "textarea",
		"friendly_name" => "Email Addresses",
		"description" => "Email addresses (command delimitinated) to send this Netflow Scan to.",
		"textarea_rows" => 4,
		"textarea_cols" => 60,
		"class" => "textAreaNotes",
		"value" => "|arg1:email|"
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
		include_once("./plugins/flowview/general_header.php");
		display_tabs ();
		edit_schedule();
		include_once("./include/bottom_footer.php");
		break;
	default:
		include_once("./plugins/flowview/general_header.php");
		display_tabs ();
		show_schedules ();
		include_once("./include/bottom_footer.php");
		break;
}

function actions_schedules () {
	global $colors, $sched_actions, $config;

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
				db_execute("DELETE FROM plugin_flowview_schedules WHERE id = " . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "3") {
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("UPDATE plugin_flowview_schedules SET enabled='' WHERE id = " . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "4") {
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				db_execute("UPDATE plugin_flowview_schedules SET enabled='on' WHERE id = " . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == "2") {
			for ($i=0; $i<count($selected_items); $i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				plugin_flowview_run_schedule($selected_items[$i]);
			}
		}
		header("Location: flowview_schedules.php");
		exit;
	}

	/* setup some variables */
	$schedule_list = "";

	/* loop through each of the devices selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (ereg("^chk_([0-9]+)$", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$schedule_list .= "<li>" . db_fetch_cell("SELECT name FROM plugin_flowview_queries AS pfq
				INNER JOIN plugin_flowview_schedules AS pfs 
				ON pfq.id=pfs.savedquery
				WHERE pfs.id=" . $matches[1]) . "</li>";
			$schedule_array[] = $matches[1];
		}
	}

	include_once("./plugins/flowview/general_header.php");

	html_start_box("<strong>" . $sched_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	print "<form action='flowview_schedules.php' method='post'>\n";

	if ($_POST["drp_action"] == "1") { /* Delete */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To delete the following Schedule(s), press the 'Continue' button.</p>
					<p><ul>$schedule_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "2") { /* Send Now */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To send the following Schedule(s), press the 'Continue' button.</p>
					<p><ul>$schedule_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "3") { /* Disable */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To Disable the following Schedule(s), press the 'Continue' button.</p>
					<p><ul>$schedule_list</ul></p>
				</td>
				</tr>";
	}elseif ($_POST["drp_action"] == "4") { /* Enable */
		print "	<tr>
				<td colspan='2' class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>To Enable the following Schedule(s), press the 'Continue' button.</p>
					<p><ul>$schedule_list</ul></p>
				</td>
				</tr>";
	}

	if (!isset($schedule_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one schedule.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Continue'>";
	}

	print "	<tr>
			<td colspan='2' align='right' bgcolor='#eaeaea'>
				<input type='hidden' name='action' value='actions'>
				<input type='hidden' name='selected_items' value='" . (isset($schedule_array) ? serialize($schedule_array) : '') . "'>
				<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
				<input type='button' onClick='javascript:document.location=\"flowview_schedules.php\"' value='Cancel'>
				$save_html
			</td>
		</tr>
		";

	html_end_box();

	include_once("./include/bottom_footer.php");
}

function save_schedules() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('id'));
	input_validate_input_number(get_request_var_post('savedquery'));
	input_validate_input_number(get_request_var_post('sendinterval'));
	/* ==================================================== */

	$save['title']        = sql_sanitize($_POST['title']);
	$save['savedquery']   = $_POST['savedquery'];
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

function edit_schedule() {
	global $config, $schedule_edit, $colors;

	print '<script type="text/javascript" src="' . $config['url_path'] . '/include/jscalendar/calendar.js"></script>
		<script type="text/javascript" src="' . $config['url_path'] . '/include/jscalendar/lang/calendar-en.js"></script>
		<script type="text/javascript" src="' . $config['url_path'] . '/include/jscalendar/calendar-setup.js"></script>';

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	$report = array();
	if (!empty($_GET["id"])) {
		$report = db_fetch_row("SELECT pfs.*, pfq.name 
			FROM plugin_flowview_schedules AS pfs 
			LEFT JOIN plugin_flowview_queries AS pfq
			ON (pfs.savedquery=pfq.id) 
			WHERE pfs.id=" . $_GET["id"], FALSE);

		$header_label = "[edit: " . $report["name"] . "]";
	}else{
		$header_label = "[new]";
	}

	html_start_box("<strong>Report:</strong> $header_label", "100%", $colors["header"], "3", "center", "");
	draw_edit_form(array(
		"config" => array("form_name" => "chk"),
		"fields" => inject_form_variables($schedule_edit, $report)
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

	$('#start').after('&nbsp;<input type="image" src="<?php print $config['url_path'];?>images/calendar.gif" align="absmiddle" title="Start Selector" onclick="return showCalendar(\'start\')">');
	$('#start').click(function() { showCalendar2(); });
	</script>
	<?php
}

function show_schedules () {
	global $sendinterval_arr, $colors, $config, $sched_actions;

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
	if (isset($_REQUEST["clear"])) {
		kill_session_var("sess_schedules_current_page");
		kill_session_var("sess_schedules_filter");
		kill_session_var("sess_schedules_sort_column");
		kill_session_var("sess_schedules_sort_direction");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_schedules_current_page", "1");
	load_current_session_value("filter", "sess_schedules_filter", "");
	load_current_session_value("sort_column", "sess_schedules_sort_column", "title");
	load_current_session_value("sort_direction", "sess_schedules_sort_direction", "ASC");

	html_start_box("<strong>Host Templates</strong>", "100%", $colors["header"], "3", "center", "flowview_schedules.php?action=edit");
	?>
	<tr bgcolor="#<?php print $colors["panel"];?>">
		<td>
		<form name="form_schedule" action="flowview_schedules.php">
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

	$sql_where = "WHERE (name LIKE '%%" . get_request_var_request("filter") . "%%')";
	$num_rows  = read_config_option("num_rows_device");
	define("MAX_DISPLAY_PAGES", 21);

	$sql = "SELECT pfs.*, pfq.name 
		FROM plugin_flowview_schedules AS pfs
		LEFT JOIN plugin_flowview_queries AS pfq 
		ON (pfs.savedquery=pfq.id) 
		$sql_where
		ORDER BY " . get_request_var_request("sort_column") . " " . get_request_var_request("sort_direction") . "
		LIMIT " . ($num_rows*($_REQUEST["page"]-1)) . ", $num_rows";

	$result     = db_fetch_assoc($sql);
	$total_rows = db_fetch_cell("SELECT COUNT(*) 
		FROM plugin_flowview_schedules AS pfs
		LEFT JOIN plugin_flowview_queries AS pfq 
		ON (pfs.savedquery=pfq.id) 
		$sql_where");

	$url_page_select = get_page_list($_REQUEST["page"], MAX_DISPLAY_PAGES, $num_rows, $total_rows, "flowview_schedules.php?");

    /* print checkbox form for validation */
    print "<form name='chk' method='post' action='flowview_schedules.php'>\n";

	html_start_box("", "100%", $colors["header"], "3", "center", "");

	if ($total_rows > 0) {
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='10'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if ($_REQUEST["page"] > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("flowview_schedules.php?page=" . ($_REQUEST["page"]-1)) . "'>"; } $nav .= "Previous"; if ($_REQUEST["page"] > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							Showing Rows " . (($num_rows*($_REQUEST["page"]-1))+1) . " to " . ((($total_rows < $num_rows) || ($total_rows < ($num_rows*$_REQUEST["page"]))) ? $total_rows : ($num_rows*$_REQUEST["page"])) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars("flowview_schedules.php?page=" . ($_REQUEST["page"]+1)) . "'>"; } $nav .= "Next"; if (($_REQUEST["page"] * $num_rows) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
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

	print $nav;
	$display_array = array(
		'title'                 => array('Schedule Title', 'ASC'),
		'name'                  => array('Filter Name', 'ASC'),
		'sendinterval'          => array('Interval', 'ASC'),
		'start'                 => array('Start Date', 'ASC'),
		'lastsent+sendinterval' => array('Next Send', 'ASC'),
		'email'                 => array('Email', 'ASC'),
		'enabled'               => array('Enabled', 'ASC')
	);

	html_header_sort_checkbox($display_array, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i=0;
	if (count($result)) {
		foreach ($result as $row) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $row['id']); $i++;
			form_selectable_cell('<a href="' . htmlspecialchars('flowview_schedules.php?&action=edit&id=' . $row['id']) . '"><strong>' . $row['title'] . '</strong></a>', $row['id']);
			form_selectable_cell($row['name'], $row['id']);
			form_selectable_cell($sendinterval_arr[$row['sendinterval']], $row['id']);
			form_selectable_cell($row['start'], $row['id']);
			form_selectable_cell(date("Y-m-d G:i:s", $row['lastsent']+$row['sendinterval']), $row['id']);
			form_selectable_cell($row['email'], $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? "<font color=green><b>Yes</b></font>":"<font color=red><b>No</b></font>"), $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	}
	html_end_box(false);
	draw_actions_dropdown($sched_actions);
}

