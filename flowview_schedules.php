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

chdir('../../');

include('./include/auth.php');
include_once('./lib/time.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

set_default_action();

$sched_actions = array(
	2 => __('Send Now', 'flowview'),
	1 => __('Delete', 'flowview'),
	3 => __('Disable', 'flowview'),
	4 => __('Enable', 'flowview')
);

$sendinterval_arr = array(
	3600    => __('Every Hour', 'flowview'),
	7200    => __('Every %d Hours', 2, 'flowview'),
	14400   => __('Every %d Hours', 4, 'flowview'),
	21600   => __('Every %d Hours', 6, 'flowview'),
	43200   => __('Every %d Hours', 12, 'flowview'),
	86400   => __('Every Day', 'flowview'),
	432000  => __('Every Week', 'flowview'),
	864000  => __('Every %d Weeks, 2', 'flowview'),
	1728000 => __('Every Month', 'flowview'),
);

$formats = reports_get_format_files();

$schedule_edit = array(
	'title' => array(
		'friendly_name' => __('Title', 'flowview'),
		'method' => 'textbox',
		'default' => __('New Schedule', 'flowview'),
		'description' => __('Enter a Report Title for the FlowView Schedule.', 'flowview'),
		'value' => '|arg1:title|',
		'max_length' => 128,
		'size' => 60
	),
	'enabled' => array(
		'friendly_name' => __('Enabled', 'flowview'),
		'method' => 'checkbox',
		'default' => 'on',
		'description' => __('Whether or not this NetFlow Scan will be sent.', 'flowview'),
		'value' => '|arg1:enabled|',
	),
	'query_id' => array(
		'method' => 'drop_sql',
		'friendly_name' => __('Filter Name', 'flowview'),
		'description' => __('Name of the query to run.', 'flowview'),
		'value' => '|arg1:query_id|',
		'sql' => 'SELECT id, name FROM plugin_flowview_queries'
	),
	'sendinterval' => array(
		'friendly_name' => __('Send Interval', 'flowview'),
		'description' => __('How often to send this NetFlow Report?', 'flowview'),
		'value' => '|arg1:sendinterval|',
		'method' => 'drop_array',
		'default' => '0',
		'array' => $sendinterval_arr
	),
	'start' => array(
		'method' => 'textbox',
		'friendly_name' => __('Start Time', 'flowview'),
		'description' => __('This is the first date / time to send the NetFlow Scan email.  All future Emails will be calculated off of this time plus the interval given above.', 'flowview'),
		'value' => '|arg1:start|',
		'max_length' => '26',
		'size' => 20,
		'default' => date('Y-m-d G:i:s', time())
	),
	'email' => array(
		'method' => 'textarea',
		'friendly_name' => __('Email Addresses', 'flowview'),
		'description' => __('Email addresses (command delimited) to send this NetFlow Scan to.', 'flowview'),
		'textarea_rows' => 4,
		'textarea_cols' => 60,
		'class' => 'textAreaNotes',
		'value' => '|arg1:email|'
	),
	'format_file' => array(
		'friendly_name' => __('Format File to Use', 'monitor'),
		'method' => 'drop_array',
		'default' => read_config_option('flowview_format_file'),
		'description' => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.', 'monitor'),
		'array' => $formats,
		'value' => '|arg1:format_file|'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	),
);

switch (get_request_var('action')) {
	case 'actions':
		actions_schedules();
		break;
	case 'save':
		save_schedules();
		break;
	case 'edit':
		top_header();
		edit_schedule();
		bottom_footer();
		break;
	default:
		top_header();
		show_schedules();
		bottom_footer();
		break;
}

function actions_schedules () {
	global $colors, $sched_actions, $config;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action');
	/* ==================================================== */

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') {
				foreach($selected_items as $item) {
					db_execute_prepared('DELETE FROM plugin_flowview_schedules
						WHERE id = ?', array($item));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') {
				foreach($selected_items as $item) {
					db_execute_prepared('UPDATE plugin_flowview_schedules
						SET enabled = ""
						WHERE id = ?',
						array($item));
				}
			} elseif (get_nfilter_request_var('drp_action') == '4') {
				foreach($selected_items as $item) {
					db_execute_prepared('UPDATE plugin_flowview_schedules
						SET enabled = "on"
						WHERE id = ?',
						array($item));
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') {
				$php = read_config_option('path_php_binary');
				foreach($selected_items as $item) {
					$title = db_fetch_cell_prepared('SELECT title
						FROM plugin_flowview_schedules
						WHERE id = ?',
						array($item));

					exec_background($php, ' -q ' . $config['base_path'] . '/plugins/flowview/run_schedule.php --force --schedule=' . $item);

					raise_message('report_send_' . $item, __('Sent Scheduled Report %s in Background.', $title, 'flowview'), MESSAGE_LEVEL_INFO);
				}

				raise_message('report_send_finish', __('Reports will arrive once Complete.', 'flowview'), MESSAGE_LEVEL_INFO);
			}
		}

		header('Location: flowview_schedules.php?header=false');
		exit;
	}

	/* setup some variables */
	$schedule_list = '';

	/* loop through each of the devices selected on the previous page and get more info about them */
	foreach($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$schedule_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_flowview_queries AS pfq
				INNER JOIN plugin_flowview_schedules AS pfs
				ON pfq.id=pfs.query_id
				WHERE pfs.id = ?', array($matches[1])) . '</li>';
			$schedule_array[] = $matches[1];
		}
	}

	general_header();

	form_start('flowview_schedules.php');

	html_start_box($sched_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == '1') { /* Delete */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to delete the following Schedule(s).', 'flowview') . "</p>
				<ul>$schedule_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '2') { /* Send Now */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to send the following Schedule(s) now.', 'flowview') . "</p>
				<ul>$schedule_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '3') { /* Disable */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Disable the following Schedule(s).', 'flowview') . "</p>
				<ul>$schedule_list</ul>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '4') { /* Enable */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Enable the following Schedule(s).', 'flowview') . "</p>
				<ul>$schedule_list</ul>
			</td>
		</tr>";
	}

	if (!isset($schedule_array)) {
		print "<tr><td><span class='textError'>" . __('You must select at least one schedule.', 'flowview') . "</span></td></tr>\n";
		$save_html = '';
	} else {
		$save_html = "<input type='submit' value='" . __esc('Continue', 'flowview') . "'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($schedule_array) ? serialize($schedule_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			<input type='button' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'flowview') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_schedules() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('query_id');
	get_filter_request_var('sendinterval');
	/* ==================================================== */

	$save['title']        = get_nfilter_request_var('title');
	$save['query_id']     = get_nfilter_request_var('query_id');
	$save['sendinterval'] = get_nfilter_request_var('sendinterval');
	$save['start']        = get_nfilter_request_var('start');
	$save['email']        = get_nfilter_request_var('email');
	$save['format_file']  = get_nfilter_request_var('format_file');

	$t = time();
	$d = strtotime(get_nfilter_request_var('start'));
	$i = $save['sendinterval'];
	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');

		$q = db_fetch_row('SELECT * FROM plugin_flowview_schedules WHERE id = ' . $save['id']);
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

	if (isset_request_var('enabled'))
		$save['enabled'] = 'on';
	else
		$save['enabled'] = 'off';

	$id = sql_save($save, 'plugin_flowview_schedules', 'id', true);

	if (is_error_message()) {
		raise_message(2);

		header('Location: flowview_schedules.php?header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
		exit;
	}

	raise_message(1);

	header('Location: flowview_schedules.php?header=false');
	exit;
}

function edit_schedule() {
	global $config, $schedule_edit, $colors;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$report = array();
	if (!isempty_request_var('id')) {
		$report = db_fetch_row_prepared('SELECT pfs.*, pfq.name
			FROM plugin_flowview_schedules AS pfs
			LEFT JOIN plugin_flowview_queries AS pfq
			ON (pfs.query_id=pfq.id)
			WHERE pfs.id = ?',
			array(get_request_var('id')));

		$header_label = __esc('Report: [edit: %s]', $report['name'], 'flowview');
	} else {
		$header_label = __('Report: [new]', 'flowview');
	}

	form_start('flowview_schedules.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($schedule_edit, $report)
		)
	);

	html_end_box();

	?>
	<script type='text/javascript'>
	var startOpen = false;

	$(function() {
		$('#start').after("<i id='startDate' class='calendar fa fa-calendar' title='<?php print __esc('Start Date Selector', 'flowview');?>'></i>");
		$('#startDate').click(function() {
			if (startOpen) {
				startOpen = false;
				$('#start').datetimepicker('hide');
			} else {
				startOpen = true;
				$('#start').datetimepicker('show');
			}
		});

		$('#start').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});
	});
	</script>
	<?php

	form_save_button('flowview_schedules.php');
}

function show_schedules () {
	global $sendinterval_arr, $colors, $config, $sched_actions, $item_rows;

    /* ================= input validation and session storage ================= */
    $filters = array(
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => ''
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'title',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fvschd');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$listeners = db_fetch_cell('SELECT COUNT(*) FROM plugin_flowview_devices');

	if ($listeners) {
		html_start_box(__('FlowView Schedules', 'flowview'), '100%', '', '3', 'center', 'flowview_schedules.php?action=edit');
	} else {
		html_start_box(__('FlowView Schedules [ Add Devices before Schedules ]', 'flowview'), '100%', '', '3', 'center', '');
	}

	?>
	<tr class='even'>
		<td>
		<form id='form_schedule' action='flowview_schedules.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Schedules', 'flowview');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'flowview');?></option>
							<?php
							if (cacti_sizeof($item_rows)) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'"; if (get_request_var('rows') == $key) { print ' selected'; } print '>' . $value . "</option>\n";
								}
							}
							?>
						</select>
					</td>
					<td>
						<input type='submit' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
					</td>
					<td>
						<input type='button' name='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'flowview_schedules.php?header=false';
			strURL += '&filter='+escape($('#filter').val());
			strURL += '&rows='+$('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'flowview_schedules.php?clear=true&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#clear').click(function() {
				clearFilter();
			});

			$('#rows').change(function() {
				applyFilter();
			});

			$('#form_schedule').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php
	html_end_box();

	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE name LIKE ' . db_qstr('%' . get_request_var('filter') . '%');
	} else {
		$sql_where = '';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql = "SELECT pfs.*, pfq.name
		FROM plugin_flowview_schedules AS pfs
		LEFT JOIN plugin_flowview_queries AS pfq
		ON (pfs.query_id=pfq.id)
		$sql_where
		$sql_order
		$sql_limit";

	$result = db_fetch_assoc($sql);

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM plugin_flowview_schedules AS pfs
		LEFT JOIN plugin_flowview_queries AS pfq
		ON (pfs.query_id=pfq.id)
		$sql_where");

	$display_array = array(
		'title' => array(
			'display' => __('Schedule Title', 'flowview'),
			'sort' => 'ASC'
		),
		'name' => array(
			'display' => __('Filter Name', 'flowview'),
			'sort' => 'ASC'
		),
		'sendinterval' => array(
			'display' => __('Interval', 'flowview'),
			'sort' => 'ASC'
		),
		'start' => array(
			'display' => __('Start Date', 'flowview'),
			'sort' => 'ASC'
		),
		'lastsent+sendinterval' => array(
			'display' => __('Next Send', 'flowview'),
			'sort' => 'ASC'
		),
		'email' => array(
			'display' => __('Email', 'flowview'),
			'sort' => 'ASC'
		),
		'enabled' => array(
			'display' => __('Enabled', 'flowview'),
			'sort' => 'ASC'
		)
	);

	$nav = html_nav_bar('flowview_schedules.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($display_array) + 1, __('Schedules', 'flowview'), 'page', 'main');

	form_start('flowview_schedules.php', 'chk');

    print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort_checkbox($display_array, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	$i=0;
	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('flowview_schedules.php?action=edit&id=' . $row['id']) . '">' . html_escape($row['title']) . '</a>', $row['id']);
			form_selectable_cell($row['name'], $row['id']);
			form_selectable_cell($sendinterval_arr[$row['sendinterval']], $row['id']);
			form_selectable_cell(substr($row['start'], 5), $row['id']);
			form_selectable_cell(date('m-d H:i', $row['lastsent']+$row['sendinterval']), $row['id']);
			form_selectable_cell($row['email'], $row['id']);
			form_selectable_cell(($row['enabled'] == 'on' ? "<span class='deviceUp'><b>" . __('Yes', 'flowview') . "</b></span>":"<span class='deviceDown'><b>" . __('No', 'flowview') . "</b></span>"), $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($sched_actions);

	form_end();
}

