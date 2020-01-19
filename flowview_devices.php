<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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
include_once($config['base_path'] . '/plugins/flowview/functions.php');

$flow_actions = array(
	1 => __('Delete', 'flowview')
);

set_default_action();

$device_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Device Name', 'flowview'),
		'description' => __('Name of the device to be displayed.', 'flowview'),
		'value' => '|arg1:name|',
		'max_length' => '64',
	),
	'cmethod' => array(
		'friendly_name' => __('Collection Medhod', 'flowview'),
		'description' => __('There are two support collection methods, the first utilizes the legacy flow-tools binaries and the second leverages Cacti\'s own PHP based flow stream server.', 'flowview'),
		'value' => '1',
		'method' => 'hidden',
		'default' => '1'
	),
	'allowfrom' => array(
		'method' => 'textbox',
		'friendly_name' => __('Allowed Host', 'flowview'),
		'description' => __('IP Address of the device that is allowed to send to this flow collector.  Leave as 0 for any host.', 'flowview'),
		'value' => '|arg1:allowfrom|',
		'default' => '0',
		'max_length' => '64',
		'size' => '30'
	),
	'port' => array(
		'method' => 'textbox',
		'friendly_name' => __('Port', 'flowview'),
		'description' => __('Port this collector will listen on.', 'flowview'),
		'value' => '|arg1:port|',
		'default' => '2055',
		'max_length' => '5',
		'size' => '30'
	),
	'id' => array(
		'method' => 'hidden_zero',
		'value' => '|arg1:id|'
	)
);

switch (get_request_var('action')) {
	case 'actions':
		actions_devices();
		break;
	case 'save':
		save_devices ();
		break;
	case 'edit':
		top_header();
		edit_devices();
		bottom_footer();
		break;
	default:
		top_header();
		show_devices ();
		bottom_footer();
		break;
}

function actions_devices () {
	global $flow_actions, $config;

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute_prepared('DELETE FROM plugin_flowview_devices WHERE id = ?', array($selected_items[$i]));
				}
			}
		}

		header('Location: flowview_devices.php?tab=listeners&header=false');
		exit;
	}


	/* setup some variables */
	$device_list = '';
	$i = 0;

	/* loop through each of the devices selected on the previous page and get more info about them */
	foreach($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$device_list .= '<li>' . db_fetch_cell('SELECT name FROM plugin_flowview_devices WHERE id=' . $matches[1]) . '</li>';
			$device_array[$i] = $matches[1];
		}
		$i++;
	}

	general_header();

	form_start('flowview_devices.php');

	html_start_box($flow_actions{get_nfilter_request_var('drp_action')}, '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == '1') { /* Delete */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to delete the following Net-Flow Listeners.  After which, you will need to restart your Flow-Capture Service.', 'flowview') . "</p>
				<p>" . __('Also, remember to remove any leftover files from your Net-Flow Capture location.', 'flowview') . "</p>
				<p><ul>$device_list</ul></p>
			</td>
		</tr>\n";
	}

	if (!isset($device_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one device.', 'flowview') . "</span></td></tr>\n";
		$save_html = '';
	} else {
		$save_html = "<input type='submit' value='" . __esc('Continue', 'flowview') . "'>";
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($device_array) ? serialize($device_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_filter_request_var('drp_action') . "'>
			<input type='button' onClick='javascript:document.location=\"flowview_devices.php\"' value='" . __esc('Cancel', 'flowview') . "'>
			$save_html
		</td>
	</tr>\n";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_devices () {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = '';
	}

	$save['name']        = get_nfilter_request_var('name');
	$save['cmethod']     = get_nfilter_request_var('cmethod');
	$save['allowfrom']   = get_nfilter_request_var('allowfrom');
	$save['port']        = get_nfilter_request_var('port');

	$id = sql_save($save, 'plugin_flowview_devices', 'id', true);

	if (is_error_message()) {
		raise_message(2);

		header('Location: flowview_devices.php?tab=listeners&header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	}

	raise_message(1);

	header('Location: flowview_devices.php?tab=listeners&header=false');
	exit;
}

function edit_devices () {
	global $device_edit;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$device = array();
	if (!isempty_request_var('id')) {
		$device = db_fetch_row('SELECT * FROM plugin_flowview_devices WHERE id=' . get_request_var('id'), false);
		$header_label = __esc('Device [edit: %s]', $device['name'], 'flowview');
	} else {
		$header_label = __('Device [new]', 'flowview');
	}

	form_start('flowview_devices.php', 'flowview');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	draw_edit_form(array(
		'config' => array('no_form_tag' => true),
		'fields' => inject_form_variables($device_edit, $device)
		)
	);

	html_end_box();

	form_save_button('flowview_devices.php?tab=listeners');
}

function show_devices () {
	global $action, $expire_arr, $rotation_arr, $version_arr, $nesting_arr;
	global $config, $flow_actions;

    /* ================= input validation and session storage ================= */
    $filters = array(
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
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_fvd');
	/* ================= input validation ================= */

	$sql_where = (get_request_var('filter') != '' ? 'WHERE name LIKE ' . db_qstr('%' . get_request_var('filter') . '%'):'');
	$rows      = read_config_option('num_rows_table');

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql = "SELECT *
		FROM plugin_flowview_devices
		$sql_where
		$sql_order
		$sql_limit";

	$result = db_fetch_assoc($sql);

	$total_rows = db_fetch_cell("SELECT COUNT(*) FROM plugin_flowview_devices $sql_where");

	html_start_box(__('FlowView Listeners', 'flowview'), '100%', '', '4', 'center', 'flowview_devices.php?action=edit');

	?>
	<tr class='even'>
		<td>
		<form id='listeners' action='flowview_devices.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<input id='refresh' type='submit' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
					</td>
					<td>
						<input id='clear' type='button' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
					</td>
				</tr>
			</table>
		</form>
		<script type='text/javascript'>
		function applyFilter() {
			strURL  = 'flowview_devices.php?header=false';
			strURL += '&filter='+escape($('#filter').val());
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'flowview_devices.php?clear=true&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#clear').click(function() {
				clearFilter();
			});

			$('#listeners').submit(function(event) {
				event.preventDefault();
				applyFilter();
			});
		});
		</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$nav = html_nav_bar('flowview_devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $total_rows, $total_rows, 10, __('Listeners', 'flowview'), 'page', 'main');

	form_start('flowview_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_array = array(
		'name'        => array(__('Name', 'flowview'), 'ASC'),
		'cmethod'     => array(__('Method', 'flowview'), 'ASC'),
		'allowfrom'   => array(__('Allowed From', 'flowview'), 'ASC'),
		'port'        => array(__('Port', 'flowview'), 'ASC'),
		'nosort0'     => array(__('Status', 'flowview'), 'ASC'),
		'nosort1'     => array(__('Observed Listen', 'flowview'), 'ASC'),
	);

	html_header_sort_checkbox($display_array, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (count($result)) {
		foreach ($result as $row) {
			$status = shell_exec("netstat -anp | grep ':" . $row['port'] . " '");
			$parts = preg_split('/[\s]+/', trim($status));

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="flowview_devices.php?&tab=listeners&action=edit&id=' . $row['id'] . '">' . $row['name'] . '</a>', $row['id']);
			form_selectable_cell(__('Cacti', 'flowview'), $row['id']);
			form_selectable_cell($row['allowfrom'], $row['id']);
			form_selectable_cell($row['port'], $row['id']);
			form_selectable_cell($status != '' ? __('Up'):__('Down'), $row['id']);
			form_selectable_cell(isset($parts[3]) ? $parts[3]:'-', $row['id']);
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		print "<tr class='even'><td colspan=10><center>" . __('No Flowview Listeners', 'flowview') . "</center></td></tr>\n";
	}

	html_end_box(false);

	if (count($result)) {
		print $nav;
	}

	draw_actions_dropdown($flow_actions);

	form_end();
}

