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
include_once($config['base_path'] . '/lib/time.php');
include_once($config['base_path'] . '/lib/timespan_settings.php');

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

switch (get_request_var('action')) {
	case 'actions':
		actions_filters();
		break;
	case 'save':
		save_filter();
		break;
	case 'edit':
		if (!isset_request_var('embed')) {
			top_header();
		}

		edit_filter();

		if (!isset_request_var('embed')) {
			bottom_footer();
		}

		break;
	default:
		top_header();
		show_filters();
		bottom_footer();
		break;
}

function actions_filters() {
	global $colors, $sched_actions, $config;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_post('drp_action'));
	/* ==================================================== */

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute('DELETE FROM plugin_flowview_queries WHERE id = ' . $selected_items[$i]);
				}
			}elseif (get_nfilter_request_var('drp_action') == '3') {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute("UPDATE plugin_flowview_queries SET enabled='' WHERE id = " . $selected_items[$i]);
				}
			}elseif (get_nfilter_request_var('drp_action') == '4') {
				for ($i=0; $i<count($selected_items); $i++) {
					db_execute("UPDATE plugin_flowview_queries SET enabled='on' WHERE id = " . $selected_items[$i]);
				}
			}elseif (get_nfilter_request_var('drp_action') == '2') {
				for ($i=0; $i<count($selected_items); $i++) {
					plugin_flowview_run_schedule($selected_items[$i]);
				}
			}
		}

		header('Location: flowview_filters.php?tab=sched&header=false');
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

			$filter_list .= '<li>' . db_fetch_cell_prepared('SELECT name FROM plugin_flowview_queries
				WHERE id = ?', array($matches[1])) . '</li>';
			$filter_array[] = $matches[1];
		}
	}

	general_header();

	form_start('flowview_filters.php');

	html_start_box($sched_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == '1') { /* Delete */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to delete the following Filter(s) and all matching Filter.', 'flowview') . "</p>
				<ul>$filter_list</ul>
			</td>
		</tr>";
	}elseif (get_nfilter_request_var('drp_action') == '3') { /* Disable */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Disable the following Filters(s) and all matching Filter.', 'flowview') . "</p>
				<ul>$filter_list</ul>
			</td>
		</tr>";
	}elseif (get_nfilter_request_var('drp_action') == '4') { /* Enable */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Enable the following Filters(s).', 'flowview') . "</p>
				<ul>$filter_list</ul>
			</td>
		</tr>";
	}

	if (!isset($filter_array)) {
		print "<tr><td><span class='textError'>" . __('You must select at least one Filter.', 'flowview') . "</span></td></tr>\n";
		$save_html = '';
	}else{
		$save_html = "<input type='submit' value='" . __esc('Continue', 'flowview') . "'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($filter_array) ? serialize($filter_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . get_nfilter_request_var('drp_action') . "'>
			<input type='button' onClick='cactiReturnTo()' value='" . __esc('Cancel', 'flowview') . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function save_filter() {
	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('device_id');
	get_filter_request_var('timespan');
	get_filter_request_var('statistics');
	get_filter_request_var('printed');
	get_filter_request_var('includeif');
	get_filter_request_var('sortfield');
	/* ==================================================== */

	$save['id']              = get_nfilter_request_var('id');
	$save['name']            = get_nfilter_request_var('name');
	$save['device_id']       = get_nfilter_request_var('device_id');

	$save['timespan']        = get_nfilter_request_var('timespan');
	$save['startdate']       = get_nfilter_request_var('date1');
	$save['enddate']         = get_nfilter_request_var('date2');

	$save['tosfields']       = get_nfilter_request_var('tosfields');
	$save['tcpflags']        = get_nfilter_request_var('tcpflags');
	$save['protocols']       = implode(', ', get_nfilter_request_var('protocols'));

	$save['sourceip']        = get_nfilter_request_var('sourceip');
	$save['sourceport']      = get_nfilter_request_var('sourceport');
	$save['sourceinterface'] = get_nfilter_request_var('sourceinterface');
	$save['sourceas']        = get_nfilter_request_var('sourceas');

	$save['destip']          = get_nfilter_request_var('destip');
	$save['destport']        = get_nfilter_request_var('destport');
	$save['destinterface']   = get_nfilter_request_var('destinterface');
	$save['destas']          = get_nfilter_request_var('destas');

	$save['statistics']      = get_nfilter_request_var('statistics');
	$save['printed']         = get_nfilter_request_var('printed');
	$save['includeif']       = get_nfilter_request_var('includeif');
	$save['sortfield']       = get_nfilter_request_var('sortfield');
	$save['cutofflines']     = get_nfilter_request_var('cutofflines');
	$save['cutoffoctets']    = get_nfilter_request_var('cutoffoctets');
	$save['resolve']         = get_nfilter_request_var('resolve');

	$id = sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message()) {
		raise_message(2);
		header('Location: flowview_filters.php?tab=sched&header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
		exit;
	}

	raise_message(1);

	header('Location: flowview_filters.php?tab=sched&header=false');
	exit;
}

function edit_filter() {
	global $config, $filter_edit, $colors, $graph_timespans;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$report = array();
	if (!isempty_request_var('id')) {
		$report = db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __('Filter: [edit: %s]', $report['name'], 'flowview');
	}else{
		$header_label = __('Filter: [new]', 'flowview');
	}

	form_start('flowview_filters.php', 'chk');

	html_start_box($header_label, '100%', '', '3', 'center', '');

	get_timespan($span, time(), get_request_var('predefined_timespan'), read_user_setting('first_weekdayid'));

	$filter_edit['date1'] = array(
		'value'  => $span['current_value_date1'],
		'method' => 'hidden'
	);

	$filter_edit['date2'] = array(
		'value'  => $span['current_value_date2'],
		'method' => 'hidden'
	);

	if (sizeof($report)) {
		if ($report['statistics'] > 0) {
			$filter_edit['sortfield']['array'] = $stat_columns_array[$report['statistics']];
		} else {
			$filter_edit['sortfield']['array'] = $print_columns_array[$report['printed']];
		}
	} else {
		$filter_edit['sortfield']['array'] = $stat_columns_array[10];
	}

	if (isset_request_var('embed')) {
		$filter_edit['embed'] = array(
			'value'  => 1,
			'method' => 'hidden'
		);
	}

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => inject_form_variables($filter_edit, $report)
		)
	);

	html_end_box();

	form_save_button('flowview_filters.php');

	?>
	<script type='text/javascript'>
	$(function() {
		$('#protocols').multiselect();
	});
	</script>
	<?php
}

function show_filters() {
	global $sendinterval_arr, $colors, $config, $sched_actions, $graph_timespans, $item_rows;

	include('./plugins/flowview/arrays.php');

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
			'default' => 'fq.name',
			'options' => array('options' => 'sanitize_search_string')
			),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
			)
	);

	validate_store_request_vars($filters, 'sess_fvs');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	}else{
		$rows = get_request_var('rows');
	}

	html_start_box(__('FlowView Filters', 'flowview'), '100%', '', '3', 'center', 'flowview_filters.php?action=edit');
	?>
	<tr class='even'>
		<td>
		<form id='form_filter' action='flowview_filters.php'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' id='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Filters', 'flowview');?>
					</td>
					<td>
						<select id='rows'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'flowview');?></option>
							<?php
							if (sizeof($item_rows)) {
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
			strURL  = 'flowview_filters.php?header=false';
			strURL += '&filter='+escape($('#filter').val());
			strURL += '&rows='+$('#rows').val();
			loadPageNoHeader(strURL);
		}

		function clearFilter() {
			strURL  = 'flowview_filters.php?clear=true&header=false';
			loadPageNoHeader(strURL);
		}

		$(function() {
			$('#clear').click(function() {
				clearFilter();
			});

			$('#rows').change(function() {
				applyFilter();
			});

			$('#form_filter').submit(function(event) {
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
		$sql_where = 'WHERE fq.name LIKE ' . db_qstr('%' . get_request_var_request('filter') . '%');
	}else{
		$sql_where = '';
	}

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql = "SELECT fq.*, fd.name AS device
		FROM plugin_flowview_queries AS fq
		INNER JOIN plugin_flowview_devices AS fd
		ON fq.device_id = fd.id
		$sql_where
		$sql_order
		$sql_limit";

	$filters = db_fetch_assoc($sql);

	$total_rows = db_fetch_cell("SELECT COUNT(*)
		FROM plugin_flowview_queries AS fq
		INNER JOIN plugin_flowview_devices AS fd
		ON fq.device_id = fd.id
		$sql_where");

	$nav = html_nav_bar('flowview_filters.php?filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 5, __('Filters', 'flowview'), 'page', 'main');

	form_start('flowview_filters.php', 'chk');

    print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_array = array(
		'name' => array(
			'display' => __('Filter Name', 'flowview'),
			'align' => 'left',
			'sort' => 'ASC'
		),
		'device' => array(
			'display' => __('Listener', 'flowview'),
			'align' => 'left',
			'sort' => 'ASC'
		),
		'nosort0' => array(
			'display' => __('Report Type', 'flowview'),
			'align' => 'left',
			'sort' => 'ASC'
		),
		'id' => array(
			'display' => __('ID', 'flowview'),
			'align' => 'right',
			'sort' => 'ASC'
		),
		'sortfield' => array(
			'display' => __('Sort Field', 'flowview'),
			'align' => 'right',
			'sort' => 'ASC'
		),
		'nosort1' => array(
			'display' => __('Resolution', 'flowview'),
			'align' => 'right',
			'sort' => 'ASC'
		)
	);

	html_header_sort_checkbox($display_array, get_request_var_request('sort_column'), get_request_var_request('sort_direction'), false);

	if (cacti_sizeof($filters)) {
		foreach ($filters as $filter) {
			form_alternate_row('line' . $filter['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="' . html_escape('flowview_filters.php?action=edit&id=' . $filter['id']) . '">' . html_escape($filter['name']) . '</a>', $filter['id']);
			form_selectable_cell($filter['device'], $filter['id']);

			if ($filter['statistics'] > 0) {
				$type = $stat_report_array[$filter['statistics']];
				$sort = $stat_columns_array[$filter['statistics']][$filter['sortfield']];
			} else {
				$type = $print_report_array[$filter['printed']];
				$sort = $print_columns_array[$filter['printed']][$filter['sortfield']];
			}

			form_selectable_cell($type, $filter['id']);
			form_selectable_cell($filter['id'], $filter['id'], '', 'right');
			form_selectable_cell($sort, $filter['id'], '', 'right');
			form_selectable_cell($filter['resolve'], $filter['id'], '', 'right');
			form_checkbox_cell($filter['name'], $filter['id']);
			form_end_row();
		}
	}

	html_end_box(false);

	if (cacti_sizeof($filters)) {
		print $nav;
	}

	draw_actions_dropdown($sched_actions);

	form_end();
}

