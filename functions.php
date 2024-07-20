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

function display_tabs($id) {
	global $config;

	$streams = listener_has_templates($id);

	/* present a tabbed interface */
	$tabs['general'] = array('url' => 'flowview_devices.php', 'name' => __('General', 'flowview'));

	if ($streams) {
		$tabs['templates'] = array('url' => 'flowview_devices.php', 'name' => __('Templates', 'flowview'));
	}

	/* if they were redirected to the page, let's set that up */
	if (!isset_request_var('tab')) {
		$current_tab = 'general';
	} else {
		$current_tab = get_request_var('tab');
	}

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($tabs)) {
		foreach ($tabs as $shortname => $tab) {
			print '<li><a class="tab ' . (($shortname == $current_tab) ? 'selected"':'"') . " href='" . html_escape($config['url_path'] .
				'plugins/flowview/' . $tab['url'] .
				'?action=edit' .
				'&id=' . $id .
				'&tab=' . $shortname) .
				"'>" . $tab['name'] . "</a></li>";
		}
	}

	print "</ul></nav></div>";
}

function display_db_tabs() {
	global $db_tabs, $config;

	$base_url = 'flowview_databases.php';

	/* if they were redirected to the page, let's set that up */
	if (!isset_request_var('tab')) {
		$current_tab = 'general';
	} else {
		$current_tab = get_request_var('tab');
	}

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>";

	if (cacti_sizeof($db_tabs)) {
		foreach ($db_tabs as $shortname => $tab) {
			print '<li><a class="tab ' . (($shortname == $current_tab) ? 'selected"':'"') . " href='" . html_escape($config['url_path'] .
				'plugins/flowview/' . $base_url .
				'?tab=' . $shortname) .
				"'>" . $tab['name'] . '</a></li>';
		}
	}

	print '</ul></nav></div>';
}

function listener_has_templates($id) {
	$streams = flowview_db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_flowview_device_streams
		WHERE version != "v5"
		AND device_id = ?',
		array($id));

	return $streams > 0 ? true:false;
}

function sort_filter() {
	global $config, $filter_edit, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (isset_request_var('printed') && get_filter_request_var('printed') > 0) {
		foreach($print_columns_array[get_request_var('printed')] as $key => $value) {
			print "<option value='$key'" . (get_request_var('sortfield') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
		}
	} elseif (get_filter_request_var('statistics') > 0) {
		foreach($stat_columns_array[get_request_var('statistics')] as $key => $value) {
			print "<option value='$key'" . (get_request_var('sortfield') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
		}
	} else {
		print "<option value='0'>" . __('Select a Report Type First', 'flowview') . '</option>';
	}
}

function edit_filter() {
	global $config, $filter_edit, $graph_timespans;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (isset_request_var('return')) {
		$page = get_nfilter_request_var('return');
	} else {
		$page = 'flowview_filters.php';
	}

	$report = array();
	if (!isempty_request_var('id')) {
		$report = flowview_db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('id')));

		$header_label = __esc('Filter: [edit: %s]', $report['name'], 'flowview');
	} else {
		$header_label = __('Filter: [new]', 'flowview');
	}

	if (cacti_sizeof($report)) {
		$found = true;
	} else {
		$found = false;
	}

	if (isset_request_var('rtype')) {
		if (get_filter_request_var('rtype') == 0) {
			if (isset_request_var('statistics')) {
				$report['statistics'] = get_filter_request_var('statistics');
				$report['printed']    = 0;
			}
		} else {
			if (isset_request_var('printed')) {
				$report['printed']    = get_filter_request_var('printed');
				$report['statistics'] = 0;
			}
		}

		$report['rtype'] = get_filter_request_var('rtype');
	}

	form_start($page, 'chk');

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

	if ($found) {
		if (cacti_sizeof($report)) {
			if ($report['statistics'] > 0) {
				$filter_edit['sortfield']['array'] = $stat_columns_array[$report['statistics']];
			} else {
				$filter_edit['sortfield']['array'] = $print_columns_array[$report['printed']];
			}
		} else {
			$filter_edit['sortfield']['array'] = $stat_columns_array[10];
		}
	}

	if (isset_request_var('return')) {
		$filter_edit['return'] = array(
			'value'  => get_nfilter_request_var('return'),
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

	form_save_button($page, 'return');

	?>
	<script type='text/javascript'>
	var date1Open  = false;
	var date2Open  = false;
	var returnPage = '<?php print $page;?>';

	function applyTimespan() {
		$.getJSON(returnPage+'?action=gettimespan&predefined_timespan='+$('#predefined_timespan').val(), function(data) {
			$('#date1').val(data['current_value_date1']);
			$('#date2').val(data['current_value_date2']);
		});
	}

	function applyFilter() {
		strURL = returnPage +
			'?header=false' +
			'&action=sort_filter' +
			'&rtype=' + $('#rtype').val() +
			'&statistics=' + $('#statistics').val() +
			'&printed=' + $('#printed').val();

		$.get(strURL, function(data) {
			$('#sortfield').html(data).selectmenu('refresh');
			if ($('#statistics').val() == 99) {
				$('#row_sortfield').hide();
			} else {
				$('#row_sortfield').show();
			}
			Pace.stop();
		});
	}

	$('#date1, #date2').change(function() {
		$('#predefined_timespan').val('0');
		<?php if (get_selected_theme() != 'classic') {?>
		$('#predefined_timespan').selectmenu('refresh');
		<?php }?>
	});

	$(function() {
		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			} else {
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			} else {
				date2Open = true;
				$('#date2').datetimepicker('show');
			}
		});

		$('#date1').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#date2').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#protocols').multiselect();

		function changeRType() {
			if ($('#rtype').val() == 0) {
				$('#printed').val(0);
				$('#row_printed').hide();
				$('#row_statistics').show();
			} else {
				$('#statistics').val(0);
				$('#row_statistics').hide();
				$('#row_printed').show();
			}
		}

		$('#statistics, #printed, #device_id').change(function() {
			applyFilter();
		});

		$('#rtype').change(function() {
			changeRType();
			applyFilter();
		});

		changeRType();

		applyTimespan();

		$('.tablesorter-resizable-container').hide();
	});
	</script>
	<?php
}

function save_filter_form() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('timespan');
	get_filter_request_var('sortfield');
	get_filter_request_var('cutofflines');
	get_filter_request_var('cutoffoctets');
	get_filter_request_var('query');
	get_filter_request_var('device_id');
	get_filter_request_var('graph_height');
	/* ==================================================== */

	$report = get_nfilter_request_var('report');

	if (substr($report, 0, 1) == 's') {
		$report      = intval(trim($report, 's'));
		$printed     = 0;
		$statistical = $report;
	} elseif (substr($report, 0, 1) == 'p') {
		$report      = intval(trim($report, 'p'));
		$printed     = $report;
		$statistical = 0;
	}

	flowview_db_execute_prepared('UPDATE plugin_flowview_queries
		SET timespan = ?,
		device_id = ?,
		statistics = ?,
		printed = ?,
		sortfield = ?,
		cutofflines = ?,
		cutoffoctets = ?,
		graph_type = ?,
		graph_height = ?,
		panel_table = ?,
		panel_bytes = ?,
		panel_packets = ?,
		panel_flows = ?
		WHERE id = ?',
		array(
			get_request_var('timespan'),
			get_request_var('device_id'),
			$statistical,
			$printed,
			get_request_var('sortfield'),
			get_request_var('cutofflines'),
			get_request_var('cutoffoctets'),
			get_request_var('graph_type'),
			get_request_var('graph_height'),
			get_request_var('table') == 'true' ? 'on':'',
			get_request_var('bytes') == 'true' ? 'on':'',
			get_request_var('packets') == 'true' ? 'on':'',
			get_request_var('flows') == 'true' ? 'on':'',
			get_request_var('query')
		)
	);
}

function save_filter() {
	global $config;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	get_filter_request_var('device_id');
	get_filter_request_var('template_id');
	get_filter_request_var('timespan');
	get_filter_request_var('statistics');
	get_filter_request_var('printed');
	get_filter_request_var('includeif');
	get_filter_request_var('sortfield');
	/* ==================================================== */

	$save['id']              = get_nfilter_request_var('id');
	$save['name']            = get_nfilter_request_var('name');
	$save['device_id']       = get_nfilter_request_var('device_id');
	$save['template_id']     = get_nfilter_request_var('template_id');
	$save['ex_addr']         = get_nfilter_request_var('ex_addr');

	$save['timespan']        = get_nfilter_request_var('timespan');
	$save['startdate']       = get_nfilter_request_var('date1');
	$save['enddate']         = get_nfilter_request_var('date2');

	$save['tosfields']       = get_nfilter_request_var('tosfields');
	$save['tcpflags']        = get_nfilter_request_var('tcpflags');

	if (is_array(get_nfilter_request_var('protocols')) && cacti_sizeof(get_nfilter_request_var('protocols'))) {
		$save['protocols']   = implode(', ', get_nfilter_request_var('protocols'));
	} else {
		$save['protocols']   = '';
	}

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

	$save['graph_type']      = get_nfilter_request_var('graph_type');
	$save['graph_height']    = get_nfilter_request_var('graph_height');
	$save['panel_table']     = isset_request_var('table') ? 'on':'';
	$save['panel_bytes']     = isset_request_var('bytes') ? 'on':'';
	$save['panel_packets']   = isset_request_var('packets') ? 'on':'';
	$save['panel_flows']     = isset_request_var('flows') ? 'on':'';

	$id = flowview_sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message()) {
		raise_message(2);

		if (!isset_request_var('return') || get_request_var('return') == '') {
			header('Location: flowview_filters.php?tab=sched&header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
		} else {
			header('Location: ' . html_escape(get_nfilter_request_var('return') . '?query=' . (empty($id) ? get_filter_request_var('id') : $id)));
		}

		exit;
	}

	raise_message(1);

	flowview_purge_query_caches($id);

	if (!isset_request_var('return') || get_request_var('return') == '') {
		header('Location: flowview_filters.php?action=edit&id=' . $id . '&header=false');
	} else {
		header('Location: ' . html_escape(get_nfilter_request_var('return') . '?query=' . (empty($id) ? get_filter_request_var('id') : $id)));
	}

	exit;
}

function flowview_purge_query_caches($id) {
	if (isset($_SESSION['sess_flowdata']) && cacti_sizeof($_SESSION['sess_flowdata'])) {
		foreach($_SESSION['sess_flowdata'] as $key => $data) {
			$parts = explode('_', $key, 2);

			if ($parts[0] == $id) {
				unset($_SESSION['sess_flowdata'][$key]);
			}
		}
	}
}

function flowview_delete_filter() {
	global $config;

	flowview_db_execute_prepared('DELETE FROM plugin_flowview_queries
		WHERE id = ?',
		array(get_filter_request_var('query')));

	flowview_db_execute_prepared('DELETE FROM plugin_flowview_schedules
		WHERE query_id = ?',
		array(get_filter_request_var('query')));

	raise_message('flow_deleted');

	header('Location: flowview.php?header=false');
	exit;
}

function flowview_gettimespan() {
	global $config;

	$timespan = get_filter_request_var('predefined_timespan');
	$date1    = get_nfilter_request_var('date1');
	$date2    = get_nfilter_request_var('date2');
	$span     = array();

	if ($timespan > 0) {
		get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
	} else {
		$span['current_value_date1'] = $date1;
		$span['current_value_date2'] = $date2;
		$span['begin_now']           = strtotime($date1);
		$span['end_now']             = strtotime($date2);
	}

	print json_encode($span);
}

function flowview_show_summary(&$data) {
	print isset($data['table']) ? $data['table'] : '';
}


function flowview_display_filter() {
	global $config, $graph_timeshifts, $graph_timespans, $graph_heights;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$title  = __esc('Undefined Filter [ Select Filter to get Details ]', 'flowview');

	if (get_filter_request_var('query') > 0) {
		$row = flowview_db_fetch_row_prepared('SELECT name, statistics, printed
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		if (cacti_sizeof($row)) {
			if ($row['statistics'] > 0) {
				$title = __esc('Statistical Report: %s [ Including overrides as specified below ]', $stat_report_array[$row['statistics']]);
			} elseif ($row['printed'] > 0) {
				$title = __esc('Printed Report: %s [ Including overrides as specified below ]', $print_report_array[$row['statistics']]);
			}
		}
	}

	html_start_box($title . '&nbsp;<span id="text"></span>', '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='flowview_filter' action='flowview.php' method='post'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Filter', 'flowview');?>
					</td>
					<td>
						<select id='query' name='query'>
							<option value='-1'><?php print __('Select a Filter', 'flowview');?></option>
							<?php
							$queries = flowview_db_fetch_assoc('SELECT id, name
								FROM plugin_flowview_queries
								ORDER BY name');

							if (cacti_sizeof($queries)) {
								foreach($queries as $q) {
									print "<option value='" . $q['id'] . "'" . (get_request_var('query') == $q['id'] ? ' selected':'') . '>' . html_escape($q['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Listener', 'flowview');?>
					</td>
					<td>
						<select id='device_id' name='device_id'>
							<option value='-1'><?php print __('Select a Listener', 'flowview');?></option>
							<?php
							$listeners = flowview_db_fetch_assoc('SELECT DISTINCT id, name
								FROM (
									SELECT 0 AS id, "' . __esc('All', 'flowview') . '" AS name
									UNION
									SELECT id, name
									FROM plugin_flowview_devices
								) AS rs
								ORDER BY name');

							if (cacti_sizeof($listeners)) {
								foreach($listeners as $l) {
									print "<option value='" . $l['id'] . "'" . (get_request_var('device_id') == $l['id'] ? ' selected':'') . '>' . html_escape($l['name']) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Exclude', 'flowview');?>
					</td>
					<td>
						<select id='exclude' name='exclude'>
							<option value='0'<?php print (get_request_var('exclude') == 0 ? ' selected':'');?>><?php print __('None', 'flowview');?></option>
							<option value='1'<?php print (get_request_var('exclude') == 1 ? ' selected':'');?>><?php print __('Top Sample', 'flowview');?></option>
							<?php
							$samples = array(2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15);

							foreach($samples as $s) {
								print "<option value='$s'" . (get_request_var('exclude') == $s ? ' selected':'') . ">" . __('Top %d Samples', $s, 'flowview') . '</option>';
							}
							?>
						</select>
					</td>
					<td class='nowrap' title='<?php print __esc('Show only Domains on Charts Below');?>'>
						<input type='checkbox' id='domains' name='domains' <?php print (get_request_var('domains') == 'true' ? 'checked':'');?>>
						<label for='domains'><?php print __('Domains/Hostnames Only', 'flowview');?></label>
					</td>
					<td>
						<span>
							<input type='button' id='go' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Apply Filter', 'flowview');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filter', 'flowview');?>'>
							<input type='button' id='new' value='<?php print __esc('New', 'flowview');?>' title='<?php print __esc('Create new Flow Filter', 'flowview');?>'>
							<input type='button' id='edit' value='<?php print __esc('Edit', 'flowview');?>' title='<?php print __esc('Edit the Flow Filter', 'flowview');?>'>
							<input type='button' id='save' value='<?php print __esc('Save', 'flowview');?>' title='<?php print __esc('Save the Flow Filter', 'flowview');?>'>
							<input type='button' id='saveas' value='<?php print __esc('Save As', 'flowview');?>' title='<?php print __esc('Save the existing Flow Filter as new Filter', 'flowview');?>'>
							<input type='button' id='rename' value='<?php print __esc('Rename', 'flowview');?>' title='<?php print __esc('Rename the Flow Filter', 'flowview');?>'>
							<input type='button' id='delete' value='<?php print __esc('Delete', 'flowview');?>' title='<?php print __esc('Delete the Flow Filter', 'flowview');?>'>
						</span>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Report', 'flowview');?>
					</td>
					<td>
						<select id='report' name='report' onChange='applyFilter(false)'>
							<?php
							$reports = array();

							if (get_request_var('query') > 0) {
								$reports[0] = __('Select a Report', 'flowview');
								foreach($stat_report_array as $key => $value) {
									if ($key > 0) {
										$reports['s' . $key] = __('Statistical: %s', $value, 'flowview');
									}
								}

								foreach($print_report_array as $key => $value) {
									if ($key > 0) {
										$reports['p' . $key] = __('Printed: %s', $value, 'flowview');
									}
								}
							} else {
								$reports[0] = __('Select a Filter First', 'flowview');
							}

							if (cacti_sizeof($reports)) {
								foreach($reports as $key => $value) {
									print "<option value='" . $key . "'" . (get_nfilter_request_var('report') == $key ? ' selected':'') . '>' . $value . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Sort Field', 'flowview');?>
					</td>
					<td>
						<select id='sortfield' name='sortfield' onChange='applyFilter(false)'>
							<?php
							$columns[0] = __('Select a Filter First', 'flowview');

							if (trim(get_request_var('report'), 'sp') != '0') {
								if (substr(get_request_var('report'), 0, 1) == 's') {
									$report = trim(get_request_var('report'), 's');

									if (isset($stat_columns_array[$report])) {
										$columns = $stat_columns_array[$report];
									}
								} else {
									$report = trim(get_request_var('report'), 'p');

									if (isset($print_columns_array[$report])) {
										$columns = $print_columns_array[$report];
									}
								}
							} elseif (get_request_var('query') > 0) {
								$report = flowview_db_fetch_row_prepared('SELECT printed, statistics, sortfield
									FROM plugin_flowview_queries
									WHERE id = ?',
									array(get_request_var('query')));

								if (cacti_sizeof($report)) {
									if ($report['statistics'] > 0) {
										$columns = $stat_columns_array[$report['statistics']];
									} elseif ($report['printed'] > 0) {
										$columns = $print_columns_array[$report['printed']];
									}
								}
							}

							if (cacti_sizeof($columns)) {
								foreach($columns as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('sortfield') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					<td>
						<?php print __('Lines', 'flowview');?>
					</td>
					<td>
						<select id='cutofflines' name='cutofflines' onChange='applyFilter(false)'>
							<?php
							if (cacti_sizeof($cutoff_lines)) {
								if (get_request_var('report') != 's99') {
									foreach($cutoff_lines as $key => $value) {
										print "<option value='" . $key . "'" . (get_request_var('cutofflines') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
									}
								} else {
									print "<option value='20' selected>" . __('N/A', 'flowview') . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Octets', 'flowview');?>
					</td>
					<td>
						<select id='cutoffoctets' name='cutoffoctets' onChange='applyFilter(false)'>
							<?php
							if (cacti_sizeof($cutoff_octets)) {
								foreach($cutoff_octets as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('cutoffoctets') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Timespan', 'flowview');?>
					</td>
					<td>
						<select id='predefined_timespan' name='predefined_timespan' onChange='applyTimespan()'>
							<?php
							if (cacti_sizeof($graph_timespans)) {
								foreach($graph_timespans as $key => $value) {
									print "<option value='$key'" . (get_request_var('predefined_timespan') == $key ? ' selected':'') . '>' . $value . '</option>';
								}
								print "<option value='0'" . (get_request_var('predefined_timespan') == '0' ? ' selected':'') . '>' . __('Custom', 'flowview') . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('From', 'flowview');?>
					</td>
					<td>
						<input type='text' id='date1' size='15' value='<?php print html_escape_request_var('date1');?>'>
					</td>
					<td>
						<i title='<?php print __esc('Start Date Selector', 'flowview');?>' class='calendar fa fa-calendar-alt' id='startDate'></i>
					</td>
					<td>
						<?php print __('To', 'flowview');?>
					</td>
					<td>
						<input type='text' id='date2' size='15' value='<?php print html_escape_request_var('date2');?>'>
					</td>
					<td>
						<i title='<?php print __esc('End Date Selector', 'flowview');?>' class='calendar fa fa-calendar-alt' id='endDate'></i>
					</td>
					<td>
						<i title='<?php print __esc('Shift Time Backward', 'flowview');?>' onclick='timeshiftFilterLeft()' class='shiftArrow fa fa-backward'></i>
					</td>
					<td>
						<select id='predefined_timeshift' title='<?php print __esc('Define Shifting Interval', 'flowview');?>'>
							<?php
							$start_val = 1;
							$end_val = cacti_sizeof($graph_timeshifts) + 1;
							if (cacti_sizeof($graph_timeshifts)) {
								for ($shift_value=$start_val; $shift_value < $end_val; $shift_value++) {
									print "<option value='$shift_value'" . (get_request_var('predefined_timeshift') == $shift_value ? ' selected':'') . '>' . title_trim($graph_timeshifts[$shift_value], 40) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<i title='<?php print __esc('Shift Time Forward', 'flowview');?>' onclick='timeshiftFilterRight()' class='shiftArrow fa fa-forward'></i>
					</td>
				</tr>
			</table>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Graph', 'flowview');?>
					</td>
					<td>
						<select id='graph_type' name='graph_type'>
						<?php print '<option value="bar"' . (get_request_var('graph_type') == 'bar' ? ' selected':'') . '>' . __('Bar', 'flowview') . '</option>'?>
						<?php print '<option value="pie"' . (get_request_var('graph_type') == 'pie' ? ' selected':'') . '>' . __('Pie', 'flowview') . '</option>'?>
						<?php print '<option value="treemap"' . (get_request_var('graph_type') == 'treemap' ? ' selected':'') . '>' . __('Treemap', 'flowview') . '</option>'?>
						</select>
					</td>
					<td>
						<?php print __('Height', 'flowview');?>
					</td>
					<td>
						<select id='graph_height' name='graph_height'>
							<?php
							foreach($graph_heights as $h => $name) {
								print "<option value='$h'" . (get_request_var('graph_height') == $h ? ' selected':'') . '>' . html_escape($name) . '</option>';
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Show/Hide', 'flowview');?>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='table' name='table' <?php print (get_request_var('table') == 'true' ? 'checked':'');?>>
						<label for='table'><?php print __('Table', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='bytes' name='bytes' <?php print (get_request_var('bytes') == 'true' ? 'checked':'');?>>
						<label for='bytes'><?php print __('Bytes', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='packets' name='packets' <?php print (get_request_var('packets') == 'true' ? 'checked':'');?>>
						<label for='packets'><?php print __('Packets', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='flows' name='flows' <?php print (get_request_var('flows') == 'true' ? 'checked':'');?>>
						<label for='flows'><?php print __('Flows', 'flowview');?></label>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
    <tr>
		<td>
			<div id='save_div' style='display:none;' title='<?php print __esc('Save Flow Filter', 'flowview');?>'>
				<form id='save_form' style='padding:3px;margin:3px;' method='post' action='#'>
					<label for='snewname' style='margin:5px;'><?php print __esc('New Name', 'flowview');?></label>
					<input id='snewname' type='text' style='margin:5px;' size='35'>
					<br>
					<input id='ssave' type='submit' style='float:right;margin:5px;' value='<?php print __esc('Save', 'flowview');?>'>
					<input id='scancel' type='button' style='float:right;margin:5px;' value='<?php print __esc('Cancel', 'flowview');?>'>
					<input id='snew' type='hidden' value='0'>
					<input id='srename' type='hidden' value='0'>
				</form>
			</div>
			<div id='delete_div' style='display:none;' title='<?php print __esc('Delete Flow Filter', 'flowview');?>'>
				<form id='delete_form' style='padding:3px;margin:3px;' method='post' action='#'>
					<p><?php print __('To Delete this Flow Filter, press Continue.  If the Flow Filter is in use in a Scheduled Report, the operation will be blocked.', 'flowview');?></p>
					<br>
					<input id='dsave' type='submit' style='float:right;margin:5px;' value='<?php print __esc('Continue', 'flowview');?>'>
					<input id='dcancel' type='button' style='float:right;margin:5px;' value='<?php print __esc('Cancel', 'flowview');?>'>
				</form>
			</div>
		</td>
	</tr>
	<tr><td>
	<script type='text/javascript'>

	var height    = $(window).height() - 200;
	var date1Open = false;
	var date2Open = false;
	var graphType = '<?php print get_request_var('graph_type');?>';

	var byteLabel       = '<?php print __('Bytes', 'flowview');?>';
	var byteBarTitle    = '<?php print __('Top %s Distribution Chart of Bytes', get_request_var('cutofflines'), 'flowview');?>';
	var bytePieTitle    = '<?php print __('Top %s Pie Chart of Bytes', get_request_var('cutofflines'), 'flowview');?>';
	var byteTreeTitle   = '<?php print __('Top %s Treemap Chart of Bytes', get_request_var('cutofflines'), 'flowview');?>';

	var packetLabel     = '<?php print __('Packets', 'flowview');?>';
	var packetBarTitle  = '<?php print __('Top %s Distribution Chart of Packets', get_request_var('cutofflines'), 'flowview');?>';
	var packetPieTitle  = '<?php print __('Top %s Pie Chart of Packets', get_request_var('cutofflines'), 'flowview');?>';
	var packetTreeTitle = '<?php print __('Top %s Treemap Chart of Packets', get_request_var('cutofflines'), 'flowview');?>';

	var flowLabel       = '<?php print __('Flows', 'flowview');?>';
	var flowBarTitle    = '<?php print __('Top %s Distribution Chart of Flows', get_request_var('cutofflines'), 'flowview');?>';
	var flowPieTitle    = '<?php print __('Top %s Pie Chart of Flows', get_request_var('cutofflines'), 'flowview');?>';
	var flowTreeTitle   = '<?php print __('Top %s Treemap Chart of Flows', get_request_var('cutofflines'), 'flowview');?>';

	var pattern = [
		'#1f77b4',
		'#aec7e8',
		'#ff7f0e',
		'#ffbb78',
		'#2ca02c',
		'#98df8a',
		'#d62728',
		'#ff9896',
		'#9467bd',
		'#c5b0d5',
		'#8c564b',
		'#c49c94',
		'#e377c2',
		'#f7b6d2',
		'#7f7f7f',
		'#c7c7c7',
		'#bcbd22',
		'#dbdb8d',
		'#17becf',
		'#9edae5'
	];

	if (height < 300 || height > 400) {
		height = 400;
	}

	$(function() {
		$('#bytes').off('click').on('click', function() {
			updateSession();

			if (!$('#bytes').is(':checked')) {
				$('#wrapperbytes').hide();
			} else {
				$('#wrapperbytes').show();
			}
		});

		$('#packets').off('click').on('click', function() {
			updateSession();

			if (!$('#packets').is(':checked')) {
				$('#wrapperpackets').hide();
			} else {
				$('#wrapperpackets').show();
			}
		});

		$('#flows').off('click').on('click', function() {
			updateSession();

			if (!$('#flows').is(':checked')) {
				$('#wrapperflows').hide();
			} else {
				$('#wrapperflows').show();
			}
		});

		$('#query').off('change').on('change', function() {
			changeQuery(true);
		});

		$('#domains, #exclude, #graph_type, #graph_height, #device_id').off('change').on('change', function() {
			applyFilter(false);
		});

		$('#go').off('click').on('click', function() {
			applyFilter(false);
		});

		$('#clear').off('click').on('click', function() {
			clearFilter();
		});

		$('#save').off('click').on('click', function() {
			saveFilter();
		});

		$('#edit').off('click').on('click', function() {
			strURL = urlPath + '/plugins/flowview/flowview_filters.php' +
				'?action=edit&header=false' +
				($('#query').val() > 0 ? '&id='+$('#query').val():'') +
				'&return=flowview.php';

			loadPageNoHeader(strURL);
		});

		$('#new').off('click').on('click', function() {
			strURL = urlPath + '/plugins/flowview/flowview_filters.php' +
				'?action=edit&header=false' +
				'&return=flowview.php';

			loadPageNoHeader(strURL);
		});

		$('#save_div, #delete_div').dialog({
			autoOpen: false,
			autoResize: true,
			modal: true,
			resizable: false,
			minHeight: 80,
			minWidth: 500
		});

		$('#rename').off('click').on('click', function() {
			$('#save_div').dialog('option', 'title', '<?php print __('Rename Layout', 'analytics');?>');
			$('#snewname').attr('value', $('#query option:selected').text());
			$('#snew').attr('value', '0');
			$('#srename').attr('value', '1');
			$('#save_div').dialog('open');
		});

		$('#saveas').off('click').on('click', function() {
			$('#snewname').attr('value', $('#query option:selected').text() + '<?php print __(' <new>', 'flowview');?>');
			$('#snew').attr('value', '1');
			$('#srename').attr('value', '0');
			$('#save_div').dialog('open');
		});

		$('#delete').off('click').on('click', function() {
			$('#delete_div').dialog('open');
		});

		$('#dcancel').off('click').on('click', function() {
			$('#delete_div').dialog('close');
		});

		$('#delete_form').off('submit').on('submit', function(event) {
			event.preventDefault();
			$('#delete_div').dialog('close');
			deleteFilter();
		});

		$('#dsave').off('click').on('click', function() {
			$('#delete_div').dialog('close');
			deleteFilter();
		});

		$('#scancel').off('click').on('click', function() {
			$('#save_div').dialog('close');
		});

		$('#save_form').off('submit').on('submit', function(event) {
			event.preventDefault();
			$('#save_div').dialog('close');

			if ($('#srename').val() == '1') {
				renameFilter();
			} else {
				saveAsFilter();
			}
		});

		function saveAsFilter() {
			var strURL  = 'flowview.php?action=saveasfilter';
			var postData = $('#flowview_filter').serializeForm();

			strURL += '&sname='+$('#snewname').val();

			loadPageUsingPost(strURL, postData);
		}

		function renameFilter() {
			var strURL  = 'flowview.php?action=renamefilter';
			strURL += '&query='+$('#query').val();
			strURL += '&sname='+$('#snewname').val();
			$.get(strURL, function(data) {
				var strURL  = 'flowview.php?query='+$('#query').val()+'&header=false';
				loadPageNoHeader(strURL);
			});
		}

		function deleteFilter() {
			var strURL  = 'flowview.php?action=deletefilter';
			strURL += '&query='+$('#query').val();
			$.get(strURL, function(data) {
				var strURL  = 'flowview.php?query=-1&header=false';
				loadPageNoHeader(strURL);
			});
		}

		if ($('#query').val() == -1) {
			$('#save').prop('disabled', true);
			$('#saveas').prop('disabled', true);
			$('#edit').prop('disabled', true);
			$('#rename').prop('disabled', true);
			$('#delete').prop('disabled', true);

			if ($('#save').button('instance') !== undefined) {
				$('#save').button('disable');
				$('#saveas').button('disable');
				$('#edit').button('disable');
				$('#rename').button('disable');
				$('#delete').button('disable');
			}
		} else {
			$('#save').prop('disabled', false);
			$('#saveas').prop('disabled', false);
			$('#edit').prop('disabled', false);
			$('#rename').prop('disabled', false);
			$('#delete').prop('disabled', false);

			if ($('#save').button('instance') !== undefined) {
				$('#save').button('enable');
				$('#saveas').button('enable');
				$('#edit').button('enable');
				$('#rename').button('enable');
				$('#delete').button('enable');
			}
		}

		$('#table').off('click').on('click', function() {
			updateSession();

			if (!$('#table').is(':checked')) {
				$('#flowcontent').hide();
			} else {
				$('#flowcontent').show();
			}
		});

		if ($('#table').is('checked')) {
			$('#flowcontent').show();
		}

		if ($('#table').is(':checked') || <?php print (isset_request_var('statistics') ? (get_nfilter_request_var('statistics') == 99 ? 'true':'false'):'true');?>) {
			$('#flowcontent').show();
		} else {
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
			is: function(s, table, cell, cellIndex) {
				return false;
			},

			format: function(s, table, cell, cellIndex) {
				if (s.indexOf('MB') > 0) {
					loc=s.indexOf('MB');
					return s.substring(0,loc) * 1024 * 1024;
				} else if (s.indexOf('KB') > 0) {
					loc=s.indexOf('KB');
					return s.substring(0,loc) * 1024;
				} else if (s.indexOf('Bytes') > 0) {
					loc=s.indexOf('Bytes');
					return s.substring(0,loc);
				} else if (s.indexOf('GB') > 0) {
					loc=s.indexOf('GB');
					return s.substring(0,loc) * 1024 * 1024 * 1024;
				} else if (s.indexOf('TB') > 0) {
					loc=s.indexOf('TB');
					return s.substring(0,loc) * 1024 * 1024 * 1024 * 1024;
				} else {
					return s;
				}
			},

			type: 'numeric'
		});

		$('#sorttable').tablesorter({
			widgets: ['zebra', 'resizable'],
			widgetZebra: { css: ['even', 'odd'] },
			headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
			cssIconAsc: 'fa-sort-up',
			cssIconDesc: 'fa-sort-down',
			cssIconNone: 'fa-sort',
			cssIcon: 'fa'
		});

		$('.tablesorter-resizable-container').hide();

		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			} else {
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			} else {
				date2Open = true;
				$('#date2').datetimepicker('show');
			}
		});

		$('#date1').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#date2').datetimepicker({
			minuteGrid: 10,
			stepMinute: 1,
			showAnim: 'slideDown',
			numberOfMonths: 1,
			timeFormat: 'HH:mm',
			dateFormat: 'yy-mm-dd',
			showButtonPanel: false
		});

		$('#fdialog').dialog({
			autoOpen: false,
			width: 400,
			height: 120,
			resizable: false,
			modal: true
		});

		$('td').tooltip();

		if ($('#date1').val() == '') {
			initTimespan();
		}

		if ($('#report').val().indexOf('p') >= 0) {
			$('#bytes').prop('disabled', true);
			$('#flows').prop('disabled', true);
			$('#packets').prop('disabled', true);
		} else {
			$('#bytes').prop('disabled', false);
			$('#flows').prop('disabled', false);
			$('#packets').prop('disabled', false);
		}

		// Setup the charts
		var charts = [ 'chartbytes', 'chartpackets', 'chartflows' ];
		var width = $(window).width() - 50;

		$.each(charts, function(key, value) {
			switch(value) {
				case 'chartbytes':
					if ($('#graph_type').val() == 'bar') {
						renderBarChart('bytes', 'chartbytes', byteBarTitle, byteLabel, $('#graph_height').val(), width);
					} else if ($('#graph_type').val() == 'pie') {
						renderPieChart('bytes', 'chartbytes', bytePieTitle, byteLabel, $('#graph_height').val(), width);
					} else {
						renderTreemapChart('bytes', 'chartbytes', byteTreeTitle, byteLabel, $('#graph_height').val(), width);
					}

					break;
				case 'chartflows':
					if ($('#graph_type').val() == 'bar') {
						renderBarChart('flows', 'chartflows', flowBarTitle, flowLabel, $('#graph_height').val(), width);
					} else if ($('#graph_type').val() == 'pie') {
						renderPieChart('flows', 'chartflows', flowPieTitle, flowLabel, $('#graph_height').val(), width);
					} else {
						renderTreemapChart('flows', 'chartflows', flowTreeTitle, flowLabel, $('#graph_height').val(), width);
					}

					break;
				case 'chartpackets':
					if ($('#graph_type').val() == 'bar') {
						renderBarChart('packets', 'chartpackets', packetBarTitle, packetLabel, $('#graph_height').val(), width);
					} else if ($('#graph_type').val() == 'pie') {
						renderPieChart('packets', 'chartpackets', packetPieTitle, packetLabel, $('#graph_height').val(), width);
					} else {
						renderTreemapChart('packets', 'chartpackets', packetTreeTitle, packetLabel, $('#graph_height').val(), width);
					}

					break;
			}
		});
	});

	function renderBarChart(type, bindto, title, label, height, width) {
		$.getJSON('flowview.php?action=chartdata&type=' + type +
			'&domains='      + $('#domains').is(':checked') +
			'&query='        + $('#query').val()  +
			'&report='       + $('#report').val() +
			'&device_id='    + $('#device_id').val() +
			'&sortfield='    + ($('#sortfield').val() != null ? $('#sortfield').val():'') +
			'&sortvalue='    + ($('#sortfield').val() != null ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='  + $('#cutofflines').val()  +
			'&cutoffoctets=' + $('#cutoffoctets').val() +
			'&exclude='      + $('#exclude').val() +
			'&graph_type='   + $('#graph_type').val() +
			'&graph_height=' + $('#graph_height').val() +
			'&date1='        + $('#date1').val()   +
			'&date2='        + $('#date2').val(), function(data) {

			var chartBar = bb.generate({
				title: { text: title },
				bindto: '#'+bindto,
				size: {
					height: height,
					width: width
				},
				onresize: function() {
					width = $(window).width() - 50;
					this.resize({width:width});
				},
				data: {
					type: 'bar',
					json: data,
					mimeType: 'json',
					keys: {
						x: 'name',
						value: ['value'],
						index: ['index']
					},
					color: function(color, d) {
						return pattern[d.index];
					}
				},
				legend: { hide: true },
				axis: {
					x: {
						type: 'category',
						tick: {
							rotate: 15,
							multiline: false
						},
						height: 80
					},
					y: {
						label: label,
						position: 'outer-middle',
						tick: {
							format: function(d) { return numFormatter(d); }
						}
					}
				}
			});

			Pace.stop();
		});
	}

	function renderTreemapChart(type, bindto, title, label, height, width) {
		$.getJSON('flowview.php?action=chartdata&type=' + type +
			'&domains='      + $('#domains').is(':checked') +
			'&query='        + $('#query').val()  +
			'&report='       + $('#report').val() +
			'&device_id='    + $('#device_id').val() +
			'&sortfield='    + ($('#sortfield').val() != null ? $('#sortfield').val():'') +
			'&sortvalue='    + ($('#sortfield').val() != null ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='  + $('#cutofflines').val()  +
			'&cutoffoctets=' + $('#cutoffoctets').val() +
			'&exclude='      + $('#exclude').val() +
			'&graph_type='   + $('#graph_type').val() +
			'&graph_height=' + $('#graph_height').val() +
			'&date1='        + $('#date1').val()   +
			'&date2='        + $('#date2').val(), function(data) {

			var columns = [];

			$.each(data, function(index, value) {
				columns[index] = [value.name, value.value];
			});

			var chartTreemap = bb.generate({
				title: { text: title },
				bindto: '#'+bindto,
				padding: {
					top: 40,
					right: 100,
					bottom: 40,
					left: 100
				},
				size: {
					height: height,
					width: width
				},
				onresize: function() {
					width = $(window).width() - 50;
					this.resize({width:width});
				},
				treemap: {
					tile: 'binary',
					label: {
						threshold: 0.03,
						format: function(value, ratio, id) {
							var ratio = ratio * 100;
							var dvalue = value;
							var dratio = ratio.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' %';

							return id + "\n" + numFormatter(dvalue).toLocaleString(undefined) + ' ' + label + "\n" + dratio;
						}
					}
				},
				data: {
					type: 'treemap',
					columns: columns,
					labels: {
						colors: '#fff'
					}
				},
			});

			Pace.stop();
		});
	}

	function renderPieChart(type, bindto, title, label, height, width) {
		$.getJSON('flowview.php?action=chartdata&type=' + type +
			'&domains='      + $('#domains').is(':checked') +
			'&query='        + $('#query').val()  +
			'&report='       + $('#report').val() +
			'&device_id='    + $('#device_id').val() +
			'&sortfield='    + ($('#sortfield').val() != null ? $('#sortfield').val():'') +
			'&sortvalue='    + ($('#sortfield').val() != null ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='  + $('#cutofflines').val()  +
			'&cutoffoctets=' + $('#cutoffoctets').val() +
			'&exclude='      + $('#exclude').val() +
			'&graph_type='   + $('#graph_type').val() +
			'&graph_height=' + $('#graph_height').val() +
			'&date1='        + $('#date1').val()   +
			'&date2='        + $('#date2').val(), function(data) {

			var columns = [];

			$.each(data, function(index, value) {
				columns[index] = [value.name, value.value];
			});

			var chartPie = bb.generate({
				title: { text: title },
				bindto: '#'+bindto,
				padding: {
					top: 40,
					right: 100,
					bottom: 40,
					left: 100
				},
				size: {
					height: height,
					width: width
				},
				onresize: function() {
					width = $(window).width() - 50;
					this.resize({width:width});
				},
				pie: {
					label: {
						format: function(value, ratio, id) {
							var ratio = ratio * 100;
							var dvalue = value;
							var dratio = ratio.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' %';

							return numFormatter(dvalue).toLocaleString(undefined) + "\n" + dratio;
						}
					}
				},
				data: {
					type: 'pie',
					columns: columns,
					labels: {
						colors: '#fff'
					}
				},
			});

			Pace.stop();
		});
	}

	function numFormatter(num) {
		suffix = '';
		if (num >= 1000) {
			num /= 1000;
			suffix = 'K';
		}

		if (num >= 1000) {
			num /= 1000;
			suffix = 'M';
		}

		if (num >= 1000) {
			num /= 1000;
			suffix = 'G';
		}

		if (num >= 1000) {
			num /= 1000;
			suffix = 'T';
		}

		if (num >= 1000) {
			num /= 1000;
			suffix = 'P';
		}

		return num.toFixed(2) + ' ' + suffix;
	}

	function saveFilter() {
		$.get(urlPath + 'plugins/flowview/flowview.php' +
			'?action=savefilter' +
			'&query='        + $('#query').val() +
			'&domains='      + $('#domains').is(':checked') +
			'&timespan='     + $('#predefined_timespan').val() +
			'&report='       + $('#report').val() +
			'&device_id='    + $('#device_id').val() +
			'&sortfield='    + ($('#sortfield').val() != null ? $('#sortfield').val():'') +
			'&sortvalue='    + ($('#sortfield').val() != null ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='  + $('#cutofflines').val() +
			'&cutoffoctets=' + $('#cutoffoctets').val() +
			'&table='        + $('#table').is(':checked') +
			'&bytes='        + $('#bytes').is(':checked') +
			'&packets='      + $('#packets').is(':checked') +
			'&flows='        + $('#flows').is(':checked') +
			'&exclude='      + $('#exclude').val() +
			'&graph_type='   + $('#graph_type').val() +
			'&graph_height=' + $('#graph_height').val(), function() {
			Pace.stop();
			$('#text').show().text('[ <?php print __('Filter Settings Saved', 'flowview');?> ]').fadeOut(2000);
		});
	}

	function updateSession() {
		$.get(urlPath + 'plugins/flowview/flowview.php' +
			'?action=updatesess' +
			'&query='   + $('#query').val() +
			'&domains=' + $('#domains').is(':checked') +
			'&table='   + $('#table').is(':checked') +
			'&bytes='   + $('#bytes').is(':checked') +
			'&packets=' + $('#packets').is(':checked') +
			'&flows='   + $('#flows').is(':checked'));
	}

	function initTimespan() {
		if ($('#predefined_timespan').val() != '0') {
			$.getJSON(urlPath + 'plugins/flowview/flowview.php' +
				'?action=gettimespan' +
				'&init=true' +
				'&predefined_timespan='+$('#predefined_timespan').val(), function(data) {
				$('#date1').val(data['current_value_date1']);
				$('#date2').val(data['current_value_date2']);
			});
		}
	}

	function applyTimespan() {
		if ($('#predefined_timespan').val() != '0') {
			$.getJSON(urlPath + 'plugins/flowview/flowview.php' +
				'?action=gettimespan' +
				'&init=apply' +
				'&predefined_timespan='+$('#predefined_timespan').val(), function(data) {
				$('#date1').val(data['current_value_date1']);
				$('#date2').val(data['current_value_date2']);
				applyFilter();
			});
		}
	}

	function changeQuery() {
		loadPageNoHeader(urlPath+'plugins/flowview/flowview.php' +
			'?action=query'         +
			'&domains='             + $('#domains').is(':checked') +
			'&device_id='           + $('#device_id').val() +
			'&query='               + $('#query').val() +
			'&predefined_timespan=' + $('#predefined_timespan').val() +
			'&date1='               + $('#date1').val() +
			'&date2='               + $('#date2').val() +
			'&header=false');
	}

	function applyFilter(reset) {
		if (reset) {
			var report = 0;
			var device_id = 0;
		} else {
			var report = $('#report').val();
			var device_id = $('#device_id').val();
		}

		if (report.indexOf('p') >= 0) {
			var extra='&table=true&bytes=false&packets=false&flows=false';
		} else {
			var extra='';
		}

		loadPageNoHeader(urlPath+'plugins/flowview/flowview.php' +
			'?action=view'          +
			'&domains='             + $('#domains').is(':checked') +
			'&query='               + $('#query').val() +
			'&predefined_timespan=' + $('#predefined_timespan').val() +
			'&report='              + report +
			'&device_id='           + device_id +
			'&sortfield='           + ($('#sortfield').val() != null ? $('#sortfield').val():'') +
			'&sortvalue='           + ($('#sortfield').val() != null ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='         + $('#cutofflines').val() +
			'&cutoffoctets='        + $('#cutoffoctets').val() +
			'&exclude='             + $('#exclude').val() +
			'&graph_type='          + $('#graph_type').val() +
			'&graph_height='        + $('#graph_height').val() +
			'&date1='               + $('#date1').val() +
			'&date2='               + $('#date2').val() +
			'&header=false'+extra);
	}

	function clearFilter() {
		loadPageNoHeader('flowview.php?header=false&clear=true');
	}

	$('#date1, #date2').change(function() {
		$('#predefined_timespan').val('0');
		<?php if (get_selected_theme() != 'classic') {?>
		$('#predefined_timespan').selectmenu('refresh');
		<?php }?>
	});

	</script>
	</td></tr>
	<?php

	html_end_box();
}

function get_port_name($port_num, $port_proto = 6) {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (isset($ip_protocols_array[$port_proto])) {
		$port_proto = strtolower($ip_protocols_array[$port_proto]);
	} else {
		$port_proto = '';
	}

	if ($port_num >= 49152) {
		return __('Client/Private (%s)', $port_num, 'flowview');
	} elseif ($port_num == 0) {
		return __('icmp (0)', 'flowview');
	} else {
		$port_name = flowview_db_fetch_cell_prepared('SELECT service
			FROM plugin_flowview_ports
			WHERE port = ?
			AND proto = ?',
			array($port_num, $port_proto));

		if ($port_name != '') {
			return sprintf('%s (%s)', $port_name, $port_num, 'flowview');
		} else {
			return __esc('Unknown (%s)', $port_num, 'flowview');
		}
	}
}

function plugin_flowview_run_schedule($id) {
	global $config;

	$schedule = flowview_db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_schedules
		WHERE id = ?',
		array($id));

	$query = flowview_db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($schedule['query_id']));

	/* get the timespan from the query */
	get_timespan($span, time(), $query['timespan'], read_user_setting('first_weekdayid'));

	$start = $span['begin_now'];
	$end   = $span['end_now'];

	$fromemail = read_config_option('settings_from_email');
	if ($fromemail == '') {
		$fromemail = 'cacti@cactiusers.org';
	}

	$fromname = read_config_option('settings_from_name');
	if ($fromname == '') {
		$fromname = __('Cacti Flowview', 'flowview');
	}

	$from[0] = $fromemail;
	$from[1] = $fromname;

	$subject = __('Netflow - %s', $schedule['title'], 'flowview');

	$body  = '<center>' . PHP_EOL;
	$body .= '<h1>' . html_escape($schedule['title']) . '</h1>' . PHP_EOL;
	$body .= '<h2>From ' . date('Y-m-d H:i:s', $start) . ' to ' . date('Y-m-d H:i:s', $end) . '</h2>' . PHP_EOL;
	$body .= '<h2>Using Query \'' . html_escape($query['name']) . '\'</h2>' . PHP_EOL;
	$body .= '</center>' . PHP_EOL;

	$data = load_data_for_filter($schedule['query_id'], $start, $end);
	if ($data !== false) {
		$body .= $data['table'];
	}

	$report_tag = '';
	$theme      = 'modern';
	$output     = '';
	$format     = $schedule['format_file'] != '' ? $schedule['format_file']:'default';

	flowview_debug('Loading Format File');

	$format_ok = reports_load_format_file($format, $output, $report_tag, $theme);

	flowview_debug('Format File Loaded, Format is ' . ($format_ok ? 'Ok':'Not Ok') . ', Report Tag is ' . $report_tag);

	if ($format_ok) {
		if ($report_tag) {
			$output = str_replace('<REPORT>', $body, $output);
		} else {
			$output = $output . PHP_EOL . $body;
		}
	} else {
		$output = $body;
	}

	flowview_debug('HTML Processed');

	$body_text = strip_tags(str_replace('<br>', "\n", $output));

	$version = db_fetch_cell("SELECT version
		FROM plugin_config
		WHERE directory='flowview'");

    $headers['X-Mailer']   = 'Cacti-FlowView-v' . $version;
    $headers['User-Agent'] = 'Cacti-FlowView-v' . $version;
	$headers['X-Priority'] = '1';

	mailer($from, $schedule['email'], '', '', '', $subject, $output, $body_text, array(), $headers);
}

function flowview_debug($string) {
	global $debug;

	if ($debug) {
		print 'DEBUG: ' . trim($string) . PHP_EOL;
	}
}

function get_flowview_session_key($id, $start, $end) {
	if (isset_request_var('sortfield')) {
		$key = $id . '_' . $start . '_' . $end . '_' .
			get_request_var('report')        . '_' .
			get_request_var('timespan')      . '_' .
			get_request_var('device_id')     . '_' .
			get_request_var('sortfield')     . '_' .
			get_request_var('cutofflines')   . '_' .
			get_request_var('cutoffoctets')  . '_' .
			get_request_var('exclude');

		return $id . '_' . md5($key);
	} else {
		return $id . '_' . md5($id . '_' . $start . '_' . $end);
	}
}

/**
 * purge_flowview_session - This function manages the Cacti session
 * to prevent it from growing too large with session data
 *
 * @return null
 */
function purge_flowview_sessions() {
	$now = time();
	$i   = 0;

	if (isset($_SESSION['sess_flowdata']) && cacti_sizeof($_SESSION['sess_flowdata'])) {
		foreach($_SESSION['sess_flowdata'] as $key => $data) {
			if ($now > $data['timeout']) {
				unset($_SESSION['sess_flowdata'][$key]);
			}

			$i++;
		}
	}
}

/**
 * load_data_for_filter - This function will run the query against the
 * database of pull it from a saved session.
 *
 * @param  int         The query to load without overrides
 * @param  bool|string The start date for the query in Y-m-d H:i:s
 *                     format.  If false, it will calculate the
 *                     time from the query default.
 * @param  bool|string The end date for the query in Y-m-d H:i:s
 *                     format.  If false, it will calculate the
 *                     time from the query default.
 *
 * @return array       The query results either from the cache
 *                     or from the results of the query.
 */
function load_data_for_filter($id = 0, $start = false, $end = false) {
	global $config;

	$output    = '';
	$title     = '';
	$time      = time();
	$data      = array();

	if ($id > 0) {
		$session = false;

		if ($start == false || $end == false) {
			$timespan = flowview_db_fetch_cell_prepared('SELECT timespan
				FROM plugin_flowview_queries
				WHERE id = ?',
				array($id));

			$span = array();
			get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));

			$start = strtotime($span['current_value_date1']);
			$end   = strtotime($span['current_value_date2']);
		}
	} elseif (isset_request_var('query') && get_request_var('query') > 0) {
		$id = get_request_var('query');
		if (!isset_request_var('date1') || get_request_var('date1') == '') {
			$timespan = flowview_db_fetch_cell_prepared('SELECT timespan
				FROM plugin_flowview_queries
				WHERE id = ?',
				array($id));

			$span = array();
			get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
			set_request_var('date1', $span['current_value_date1']);
			set_request_var('date2', $span['current_value_date2']);
		}

		$start = strtotime(get_request_var('date1'));
		$end   = strtotime(get_request_var('date2'));

		$session = true;

		purge_flowview_sessions();
	} else {
		return false;
	}

	if ($id > 0) {
		$data = run_flow_query($session, $id, $start, $end);
	} else {
		$data['id'] = 0;
	}

	return $data;
}

/**
 * get_numeric_filter - This function constructs a $sql_where
 * from numeric data.
 *
 * @param  string    The sql where clause to append
 * @param  array     An array of parameters to append for the query
 * @param  string    The value to match
 * @param  string    The column to match
 *
 * @return string    The updated sql where clause
 * @return byref     The updated sql params.
 *
 */
function get_numeric_filter($sql_where, &$sql_params, $value, $column) {
	$values = array();

	$sql_where = trim($sql_where);

	if (is_array($value)) {
		$value = implode(',', $value);
	}

	$instr = '';

	if ($value != '') {
		$parts  = explode(',', $value);

		foreach($parts as $part) {
			$part = trim($part);

			if (is_numeric($part)) {
				$instr .= ($instr != '' ? ', ':'') . '?';
				$sql_params[] = $part;
			}
		}

		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . "`$column` IN ($instr)";
	}

	return $sql_where;
}

/**
 * get_ip_filter - This function constructs a sql _where from
 * the ip ranges.  This function accepts ip addresses or ranges
 * using the CIDR format.
 *
 * @param  string    The sql where clause to append
 * @param  array     An array of parameters to append for the query
 * @param  string    The value to match
 * @param  string    The column to match
 *
 * @return string    The updated sql where clause
 * @return byref     The updated sql params.
 */
function get_ip_filter($sql_where, &$sql_params, $value, $column) {
	$sql_where = trim($sql_where);

	if ($value != '') {
		$values = array();
		$parts  = explode(',', $value);
		$i      = 0;

		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(';

		foreach($parts as $part) {
			$part = trim($part);

			if (strpos($part, '/') !== false) {
				// Split to $addr['subnet'], $addr['ip']
				$addr = cacti_pton($part);

				if (isset($addr['subnet'])) {
					// Example looking for IP's in the network to the right: 192.168.11.0/24
					// src_addr & inet6_aton('255.255.255.0') = inet6_aton('192.168.11.0')
					$subnet  = inet_ntop($addr['subnet']);
					$network = inet_ntop($addr['subnet'] & $addr['ip']);

					$sql_where .= ($i == 0 ? '':' OR ') .
						"(`$column` & INET6_ATON(?) = INET6_ATON(?) OR `$column` & INET_ATON(?) = INET_ATON(?))";

					$sql_params[] = $subnet;
					$sql_params[] = $network;
					$sql_params[] = $subnet;
					$sql_params[] = $network;
				} else {
					raise_message('subnet_filter', __('Subnet Filter: %s is not a value CIDR format', $part, 'flowview'), MESSAGE_LEVEL_ERROR);
				}
			} else {
				$sql_where .= ($i == 0 ? '':' OR ') . "(`$column` = INET6_ATON(?) OR `$column` = INET_ATON(?)";

				$sql_params[] = $part;
				$sql_params[] = $part;
			}

			$i++;
		}

		$sql_where .= ')';
	}

	return $sql_where;
}

/**
 * get_date_filter - given the dates and the range type
 * return a well formed sql_range value and it's params.
 *
 * @param  string      A sql_range clause to be appended to the $sql_where
 * @param  array       Array of parameters.
 * @param  int         Start date from the filter
 * @param  int         End date from the filter
 * @param  int         The range type defined by the filter
 *
 * @return string      The modified sql_range
 *
 */
function get_date_filter($sql_range, &$sql_range_params, $start, $end, $range_type = 1) {
	$sql_range = trim($sql_range);

	$date1 = date('Y-m-d H:i:s', $start);
	$date2 = date('Y-m-d H:i:s', $end);

	switch($range_type) {
		case 1: // Any part in specified time span
			$sql_range = '(`start_time` BETWEEN ? AND ? OR `end_time` BETWEEN ? AND ?)';

			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;
			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;

			break;
		case 2: // End Time in Specified Time Span
			$sql_range = '(`end_time` BETWEEN ? AND ?)';

			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;

			break;
		case 3: // Start Time in Specified Time Span
			$sql_range = '(`start_time` BETWEEN ? AND ?)';

			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;

			break;
		case 4: // Entirety in Specitifed Time Span
			$sql_range = '(`start_time` BETWEEN ? AND ? AND `end_time` BETWEEN ? AND ?)';

			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;
			$sql_range_params[] = $date1;
			$sql_range_params[] = $date2;

			break;
		default:
			break;
	}

	return $sql_range;
}

/**
 * get_tables_for_query - This function creates an array
 * of tables for a query and returns them to the caller.
 * This list of tables depends on the start and end times
 * if the end time is null, it means current time.
 *
 * The table names will be constructed by looking first
 * at the partitioning scheme selected and then constructing
 * the table names.  The tables are constructed through
 * a suffix that includes the YYYYDDDHH or for example:
 *
 * For hourly partitioning we use:
 *
 * plugin_flowview_raw_202416412
 *                     |   |  |
 *                     Yr  DoY Hr
 *
 * For daily partitioning we use:
 *
 * plugin_flowview_raw_2024164
 *                     |   |
 *                     Yr  DoY
 *
 * @param  int       The unix timestamp of the range start
 * @param  bool|int  The unix timestamp of the range end or null
 *
 * @return array     An array of table names
 */
function get_tables_for_query($start, $end = null) {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$part_type  = read_config_option('flowview_partition');
	$inc_tables = array();

	if ($end === null) {
		$end = time();
	}

	if ($part_type == 0) {
		$start_part = date('Y', $start) . substr('000' . date('z', $start), -3) . '00';
		$end_part   = date('Y', $end)   . substr('000' . date('z', $end), -3)   . '00';
	} else {
		$start_part = date('Y', $start) . substr('000' . date('z', $start), -3) . date('H', $start);
		$end_part   = date('Y', $end)   . substr('000' . date('z', $end), -3)   . date('H', $end);
	}

	$tables = flowview_db_fetch_assoc('SELECT TABLE_NAME AS `table`
		FROM information_schema.TABLES
		WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"');

	if (cacti_sizeof($tables)) {
		foreach($tables as $t) {
			$parts = explode('_', $t['table']);
			$partition = trim($parts[3]);

			// Normalize the partition to hour zero
			if (strlen($partition) == '7') {
				$partition .= '00';
			}

			if ($partition >= $start_part && $partition <= $end_part) {
				$inc_tables[$t['table']]['table_name'] = $t['table'];

				if (!isset($_SESSION['sess_flowview_table_details'][$t['table']])) {
					$details = flowview_db_fetch_row("SELECT MIN(start_time) AS min_date, MAX(end_time) AS max_date, '' AS table_partition FROM {$t['table']}");

					if (!cacti_sizeof($details)) {
						cacti_log("WARNING: Not Deatils for {$t['table']} returned");
					}

					$_SESSION['sess_flowview_table_details'][$t['table']] = $details;
				} else {
					$details = $_SESSION['sess_flowview_table_details'][$t['table']];
				}

				$inc_tables[$t['table']]['table_partition'] = $details['table_partition'];
				$inc_tables[$t['table']]['min_date']        = $details['min_date'];
				$inc_tables[$t['table']]['max_date']        = $details['max_date'];
			}
		}
	}

	return $inc_tables;
}

/**
 * flowview_get_chartdata() - This function returns chart
 * data from the session.
 */
function flowview_get_chartdata() {
	$query_id = get_filter_request_var('query');
	$type     = get_nfilter_request_var('type');
	$domains  = get_nfilter_request_var('domains');
	$report   = get_nfilter_request_var('report');
	$start    = strtotime(get_nfilter_request_var('date1'));
	$end      = strtotime(get_nfilter_request_var('date2'));

	if (!empty($report) && substr($report, 0, 1) == 's') {
		$report = trim(get_nfilter_request_var('report'), 'sp');

		$output = run_flow_query(true, $query_id, $start, $end);

		if ($output !== false && cacti_sizeof($output['data']) && $report > 0 && $report < 99) {
			$columns  = array_keys($output['data'][0]);
			$category = get_category_columns($report, $domains);

			foreach($output['data'] as $index => $row) {
				$catstring = '';

				foreach($category as $c) {
					if ($domains != 'false' && strpos($c, 'domain') && ($report < 8 || $report > 11)) {
						$p = array();

						if (isset($row[$c])) {
							if ($row[$c] != '') {
								$p = explode('.', $row[$c]);
							} else {
								$p[] = __('unresolved', 'flowview');
							}
						} elseif (($c == 'src_domain' || $c == 'dst_domain') && isset($row['domain'])) {
							if ($row['domain'] != '') {
								$p = explode('.', $row['domain']);
							} else {
								$p[] = __('unresolved', 'flowview');
							}
						} elseif (($c == 'src_domain' || $c == 'dst_domain') && isset($row['rdomain'])) {
							if ($row['rdomain'] != '') {
								$p = explode('.', $row['rdomain']);
							} else {
								$p[] = __('unresolved', 'flowview');
							}
						} else {
							$p[] = __('unresolved', 'flowview');
						}

						if (cacti_sizeof($p)) {
							$p = array_reverse($p);

							if (isset($p[1])) {
								$string = (isset($p[1]) ? $p[1]:'') . '.' . $p[0];
							} else {
								$string = $p[0];
							}

							$catstring .= ($catstring != '' ? ' / ':'') . $string;
						} else {
							cacti_log("ERROR: Unable to process domain column:$c information for report:$report, type:$type.  Please open a ticket on GitHub", false, 'FLOWVIEW');
						}
					} else {
						if (isset($row[$c])) {
							$catstring .= ($catstring != '' ? ' / ':'') . $row[$c];
						} else {
							cacti_log("ERROR: Unable to process column:$c information for report:$report, type:$type.  Please open a ticket on GitHub", false, 'FLOWVIEW');
						}
					}
				}

				if ($catstring == '') {
					$catstring = __('unresolved', 'flowview');
				}

				$chartData[] = array(
					'name'  => $catstring,
					'value' => $row[$type],
					'index' => $index
				);
			}

			$outputData = array(
				$chartData,
			);

			print json_encode($chartData, JSON_NUMERIC_CHECK);
			exit;
		}
	}

	print json_encode(array());
	exit;
}

/**
 * get_category_columns - This function helps construct the
 * columns required for a query
 *
 * @param  string   The report to get columns for
 * @param  string   The domain to include
 *
 * @return array    The columns
 */
function get_category_columns($statistics, $domain) {
	$category = array();

	if ($statistics > 0) {
		switch($statistics) {
			case 99:
				break;
			case 2:
				$category = array('src_rdomain');
				break;
			case 3:
				$category = array('dst_rdomain');
				break;
			case 4:
				$category = array('src_rdomain', 'dst_rdomain');
				break;
			case 5:
				$category = array('dst_port');
				break;
			case 6:
				$category = array('src_port');
				break;
			case 7:
				$category = array('src_port', 'dst_port');
				break;
			case 8:
				if ($domain == 'false') {
					$category = array('src_addr', 'dst_addr');
				} else {
					$category = array('src_domain', 'dst_domain');
				}
				break;
			case 9:
				if ($domain == 'false') {
					$category = array('src_addr');
				} else {
					$category = array('src_domain');
				}
				break;
			case 10:
				if ($domain == 'false') {
					$category = array('src_addr', 'dst_addr');
				} else {
					$category = array('src_domain', 'dst_domain');
				}
				break;
			case 11:
				if ($domain == 'false') {
					$category = array('ip_addr');
				} else {
					$category = array('domain');
				}
				break;
			case 12:
				$category = array('protocol');
				break;
			case 17:
				$category = array('src_if');
				break;
			case 18:
				$category = array('dst_if');
				break;
			case 23:
				$category = array('src_if', 'dst_if');
				break;
			case 19:
				$category = array('src_as');
				break;
			case 20:
				$category = array('dst_as');
				break;
			case 21:
				$category = array('src_as', 'dst_as');
				break;
			case 22:
				$category = array('tos');
				break;
			case 24:
				$category = array('src_prefix');
				break;
			case 25:
				$category = array('dst_prefix');
				break;
			case 26:
				$category = array('src_prefix', 'dst_prefix');
				break;
		}
	}

	return $category;
}

/**
 * run_flow_query - This function will take the combination of
 * request variables and data stored in the table
 * plugin_flowview_query to gather data for calling function.
 *
 * The current instantiation of the function includes a session
 * cache that was used previously to speed up the gathering
 * of data for the various rendering functions such as bar
 * charts. This legacy functionality is commented out for now
 * as it generates a rather large session file over time.
 *
 * @param  bool    Direction as to whether or not to use the session
 *                 cache.  If true, the cache can be used.
 * @param  int     The ID of the query stored in the flowview
 *                 filter table
 * @param  int     The unix timestamp of the query range start
 * @param  int     The unix timestamp of the query range end
 *
 * @return bool|array Either the results or false if there was
 *                    an error.
 */
function run_flow_query($session, $query_id, $start, $end) {
	global $config, $graph_timespans;

	if (empty($query_id)) {
		return false;
	}

	$time = time();

	$sql_where        = '';
	$sql_params       = array();
	$sql_range        = '';
	$sql_range_params = array();

	$key  = get_flowview_session_key($query_id, $start, $end);
	if ($session && isset($_SESSION['sess_flowdata'][$key])) {
		return $_SESSION['sess_flowdata'][$key]['data'];
	}

	/* close session to allow offpage navigation */
	cacti_session_close();

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$data = flowview_db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($query_id));

	$title = flowview_db_fetch_cell_prepared('SELECT name
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($query_id));

	/*-------------------------------------------------------------*/
	/* Overrides - These variables can be overridden by the user   */
	/* in the user interface.                                      */
	/*-------------------------------------------------------------*/

	/* date range override */
	if (isset_request_var('includeif') && get_request_var('includeif') > 0) {
		$sql_range = get_date_filter($sql_range, $sql_range_params, $start, $end, get_request_var('includeif'));
	} elseif ($data['includeif'] > 0) {
		$sql_range = get_date_filter($sql_range, $sql_range_params, $start, $end, $data['includeif']);
	} else {
		$sql_range = get_date_filter($sql_range, $sql_range_params, $start, $end);
	}

	/* limit override */
	if (isset_request_var('cutofflines') && get_request_var('cutofflines') != 999999) {
		$lines = get_request_var('cutofflines');
	} elseif (isset_request_var('cutofflines') && get_request_var('cutofflines') == 999999) {
		$lines = '9999999999';
	} elseif ($data['cutofflines'] != 999999) {
		$lines = $data['cutofflines'];
	} elseif ($data['cutofflines'] == 999999) {
		$lines = '9999999999';
	} else {
		$lines = 20;
	}

	/* limits override */
	if (get_request_var('exclude') > 0) {
		$sql_limit = 'LIMIT ' . get_filter_request_var('exclude') .  ',' . $lines;
	} else {
		$sql_limit = 'LIMIT ' . $lines;
	}

	/* octets override */
	if (isset_request_var('cutoffoctets') && get_filter_request_var('cutoffoctets') > 0) {
		$sql_having = 'HAVING bytes > ' . get_filter_request_var('cutoffoctets');
	} elseif ($data['cutoffoctets'] > 0) {
		$sql_having = 'HAVING bytes < ' . $data['cutoffoctets'];
	} else {
		$sql_having = '';
	}

	/* device id override */
	if (isset_request_var('device_id') && get_filter_request_var('device_id') >= 0) {
		if (get_request_var('device_id') > 0) {
			$sql_where = get_numeric_filter($sql_where, $sql_params, get_request_var('device_id'), 'listener_id');
		}
	} elseif (isset($data['device_id']) && $data['device_id'] > 0) {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['device_id'], 'listener_id');
	}

	/* report override */
	if (isset_request_var('report')) {
		$report  = get_nfilter_request_var('report');
		$nreport = trim(get_nfilter_request_var('report'), 'sp');
		if (strpos($report, 's') !== false && is_numeric($nreport)) {
			$data['statistics'] = $nreport;
			$data['printed']    = 0;
		} elseif (strpos($report, 'p') !== false && is_numeric($nreport)) {
			$data['printed']    = $nreport;
			$data['statistics'] = 0;
		}
	} elseif (!sizeof($data)) {
		return false;
	}

	/* sort field override */
	if (isset_request_var('sortfield')) {
		$data['sortfield'] = get_request_var('sortfield');
	}

	/*-------------------------------------------------------------*/
	/* Non-Overrides - These variables can not be overridden       */
	/* in the user interface.                                      */
	/*-------------------------------------------------------------*/

	/* ex_addr filter */
	if (isset($data['ex_addr']) && $data['ex_addr'] != '-1' && $data['ex_addr'] != '') {
		$sql_where .= ($sql_where != '' ? ' AND ':'') . 'ex_addr = ?';
		$sql_params[] = $data['ex_addr'];
	}

	/* template id filter */
	if (isset($data['template_id']) && $data['template_id'] >= 0) {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['template_id'], 'template_id');
	}

	/* source ip filter */
	if (isset($data['sourceip']) && $data['sourceip'] != '') {
		$sql_where = get_ip_filter($sql_where, $sql_params, $data['sourceip'], 'src_addr');
	}

	/* source interface filter */
	if (isset($data['sourceinterface']) && $data['sourceinterface'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['sourceinterface'], 'src_if');
	}

	/* source port filter */
	if (isset($data['sourceport']) && $data['sourceport'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['sourceport'], 'src_port');
	}

	/* source as filter */
	if (isset($data['sourceas']) && $data['sourceas'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['sourceas'], 'src_as');
	}

	/* destination ip filter */
	if (isset($data['destip']) && $data['destip'] != '') {
		$sql_where = get_ip_filter($sql_where, $sql_params, $data['destip'], 'dst_addr');
	}

	/* destination interface filter */
	if (isset($data['destinterface']) && $data['destinterface'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['destinterface'], 'dst_if');
	}

	/* destination port filter */
	if (isset($data['destport']) && $data['destport'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['destport'], 'dst_port');
	}

	/* destination as filter */
	if (isset($data['destas']) && $data['destas'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['destas'], 'dst_as');
	}

	/* protocols filter */
	if (isset($data['protocols']) && $data['protocols'] != '' && $data['protocols'] != '0') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['protocols'], 'protocol');
	}

	/* tcp flags filter */
	if (isset($data['tcpflags']) && $data['tcpflags'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['tcpflags'], 'flags');
	}

	/* tos filter */
	if (isset($data['tosfields']) && $data['tosfields'] != '') {
		$sql_where = get_numeric_filter($sql_where, $sql_params, $data['tosfields'], 'tos');
	}

	/*-------------------------------------------------------------*/
	/* Query construction phase.  For each report, construct the   */
	/* inner query (map phase) and the outer query (reduce phase)  */
	/* the will pull the results from the database, either in      */
	/* parallel (the new default), or as a single union query      */
	/*-------------------------------------------------------------*/

	$sql = '';

	if (cacti_sizeof($data)) {
		if ($data['statistics'] > 0) {
			if ($data['statistics'] == 99) {
				$sortvalue = '';
			} elseif (isset($stat_columns_array[$data['statistics']][$data['sortfield']])) {
				$sortvalue = $stat_columns_array[$data['statistics']][$data['sortfield']];
			}

			if ($data['statistics'] == 99) {
				$data['sortfield'] = '';
			} elseif (!isset($sortvalue)) {
				$data['sortfield'] = array_search('Bytes', $stat_columns_array[$data['statistics']]);
			} elseif (array_search($sortvalue, $stat_columns_array[$data['statistics']]) === false) {
				$data['sortfield'] = array_search('Bytes', $stat_columns_array[$data['statistics']]);
			}

			switch($data['statistics']) {
				case 99:
					$sql_array = array(
						array('name' => 'p0To32',       'min' => 0,    'max' => 32,   'title' => __('%d - %d Bytes', 0, 32, 'flowview')),
						array('name' => 'p32To64',      'min' => 32,   'max' => 64,   'title' => __('%d - %d Bytes', 32, 64, 'flowview')),
						array('name' => 'p64To96',      'min' => 64,   'max' => 96,   'title' => __('%d - %d Bytes', 64, 96, 'flowview')),
						array('name' => 'p96To128',     'min' => 96,   'max' => 128,  'title' => __('%d - %d Bytes', 96, 128, 'flowview')),
						array('name' => 'p128To160',    'min' => 128,  'max' => 160,  'title' => __('%d - %d Bytes', 128, 160, 'flowview')),
						array('name' => 'p160To192',    'min' => 160,  'max' => 192,  'title' => __('%d - %d Bytes', 160, 192, 'flowview')),
						array('name' => 'p192To224',    'min' => 192,  'max' => 224,  'title' => __('%d - %d Bytes', 192, 224, 'flowview')),
						array('name' => 'p224To256',    'min' => 224,  'max' => 256,  'title' => __('%d - %d Bytes', 224, 256, 'flowview')),
						array('name' => 'p256To288',    'min' => 256,  'max' => 288,  'title' => __('%d - %d Bytes', 256, 288, 'flowview')),
						array('name' => 'p288To320',    'min' => 288,  'max' => 320,  'title' => __('%d - %d Bytes', 288, 320, 'flowview')),
						array('name' => 'p320To352',    'min' => 320,  'max' => 352,  'title' => __('%d - %d Bytes', 320, 352, 'flowview')),
						array('name' => 'p352To384',    'min' => 352,  'max' => 384,  'title' => __('%d - %d Bytes', 352, 384, 'flowview')),
						array('name' => 'p384To416',    'min' => 384,  'max' => 416,  'title' => __('%d - %d Bytes', 384, 416, 'flowview')),
						array('name' => 'p416To448',    'min' => 416,  'max' => 448,  'title' => __('%d - %d Bytes', 416, 448, 'flowview')),
						array('name' => 'p448To480',    'min' => 448,  'max' => 480,  'title' => __('%d - %d Bytes', 448, 480, 'flowview')),
						array('name' => 'p480To512',    'min' => 480,  'max' => 512,  'title' => __('%d - %d Bytes', 480, 512, 'flowview')),
						array('name' => 'p512To544',    'min' => 512,  'max' => 544,  'title' => __('%d - %d Bytes', 512, 544, 'flowview')),
						array('name' => 'p544To576',    'min' => 544,  'max' => 576,  'title' => __('%d - %d Bytes', 544, 576, 'flowview')),
						array('name' => 'p576To1024',   'min' => 576,  'max' => 1024, 'title' => __('%d - %d Bytes', 576, 1024, 'flowview')),
						array('name' => 'p1024To1536',  'min' => 1024, 'max' => 1536, 'title' => __('%d - %d Bytes', 1024, 1536, 'flowview')),
						array('name' => 'p1536To2048',  'min' => 1536, 'max' => 2048, 'title' => __('%d - %d Bytes', 1536, 2048, 'flowview')),
						array('name' => 'p2048To2560',  'min' => 2048, 'max' => 2560, 'title' => __('%d - %d Bytes', 2048, 2560, 'flowview')),
						array('name' => 'p2560To3072',  'min' => 2560, 'max' => 3072, 'title' => __('%d - %d Bytes', 2560, 3072, 'flowview')),
						array('name' => 'p3072To3568',  'min' => 3072, 'max' => 3568, 'title' => __('%d - %d Bytes', 3072, 3568, 'flowview')),
						array('name' => 'p3568To4096',  'min' => 3568, 'max' => 4096, 'title' => __('%d - %d Bytes', 3568, 4096, 'flowview')),
						array('name' => 'p4096To4608',  'min' => 4096, 'max' => 4608, 'title' => __('%d - %d Bytes', 4096, 4680, 'flowview')),
						array('name' => 'p4608ToInfin', 'min' => 4680, 'max' => -1,   'title' => __esc('> %d Bytes', 4680, 'flowview'))
					);

					$sql_query = $sql_inner = '';

					foreach($sql_array as $el) {
						$sql_query .= ($sql_query != '' ? ', ':'SELECT ') . 'SUM(' . $el['name'] . ') AS ' . $el['name'];
						$sql_inner .= ($sql_inner != '' ? ', ':'SELECT ') .
							'SUM(CASE WHEN bytes_ppacket BETWEEN ' .
							$el['min'] . ' AND ' . $el['max'] . ' THEN bytes_ppacket ELSE 0 END) AS ' . $el['name'];
					}

					$sql_groupby       = '';
					$sql_inner_groupby = '';
					$sql_order         = '';

					break;
				case 2:
					$sql_query = 'SELECT src_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_rdomain';
					$sql_inner_groupby = 'GROUP BY src_rdomain';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 3:
					$sql_query = 'SELECT dst_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT dst_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY dst_rdomain';
					$sql_inner_groupby = 'GROUP BY dst_rdomain';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 4:
					$sql_query = 'SELECT src_rdomain, dst_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_rdomain, dst_rdomain, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_rdomain, dst_rdomain';
					$sql_inner_groupby = 'GROUP BY src_rdomain, dst_rdomain';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 5:
					$sql_query = 'SELECT dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';
					$sql_inner = 'SELECT dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';

					$sql_groupby       = 'GROUP BY dst_port, protocol';
					$sql_inner_groupby = 'GROUP BY dst_port, protocol';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 6:
					$sql_query = 'SELECT src_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';
					$sql_inner = 'SELECT src_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';

					$sql_groupby       = 'GROUP BY src_port, protocol';
					$sql_inner_groupby = 'GROUP BY src_port, protocol';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 7:
					$sql_query = 'SELECT src_port, dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';
					$sql_inner = 'SELECT src_port, dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, protocol';

					$sql_groupby       = 'GROUP BY src_port, dst_port, protocol';
					$sql_inner_groupby = 'GROUP BY src_port, dst_port, protocol';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 2) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 8:
					$sql_query = 'SELECT INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, dst_domain, src_domain, src_addr';
					$sql_inner = 'SELECT dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, dst_domain, src_domain, src_addr';

					$sql_groupby       = 'GROUP BY INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY dst_addr';

					if ($data['sortfield'] < 1) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					}

					break;
				case 9:
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain';
					$sql_inner = 'SELECT src_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr';

					if ($data['sortfield'] < 1) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					}

					break;
				case 10:
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr';

					if ($data['sortfield'] < 2) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					}

					break;
				case 11:
					$sql_query = 'SELECT INET6_NTOA(ip_addr) AS ip_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, domain, rdomain';
					$sql_inner1 = 'SELECT src_addr AS ip_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain AS domain, src_rdomain AS rdomain';
					$sql_inner2 = 'SELECT dst_addr AS ip_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, dst_domain AS domain, dst_rdomain AS rdomain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(ip_addr)';
					$sql_inner_groupby1 = 'GROUP BY src_addr';
					$sql_inner_groupby2 = 'GROUP BY dst_addr';

					if ($data['sortfield'] < 1) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					}

					break;
				case 12:
					$sql_query = 'SELECT protocol, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT protocol, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY protocol';
					$sql_inner_groupby = 'GROUP BY protocol';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 17:
					$sql_query = 'SELECT src_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_if';
					$sql_inner_groupby = 'GROUP BY src_if';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 18:
					$sql_query = 'SELECT dst_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT dst_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY dst_if';
					$sql_inner_groupby = 'GROUP BY dst_if';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 23:
					$sql_query = 'SELECT src_if, dst_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_if, dst_if, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_if, dst_if';
					$sql_inner_groupby = 'GROUP BY src_if, dst_if';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 19:
					$sql_query = 'SELECT src_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_as';
					$sql_inner_groupby = 'GROUP BY src_as';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 20:
					$sql_query = 'SELECT dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY dst_as';
					$sql_inner_groupby = 'GROUP BY dst_as';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 21:
					$sql_query = 'SELECT src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_as, dst_as';
					$sql_inner_groupby = 'GROUP BY src_as, dst_as';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 22:
					$sql_query = 'SELECT tos, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT tos, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY tos';
					$sql_inner_groupby = 'GROUP BY tos';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 24:
					$sql_query = 'SELECT src_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_prefix';
					$sql_inner_groupby = 'GROUP BY src_prefix';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 25:
					$sql_query = 'SELECT dst_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT dst_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY dst_prefix';
					$sql_inner_groupby = 'GROUP BY dst_prefix';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 26:
					$sql_query = 'SELECT src_prefix, dst_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';
					$sql_inner = 'SELECT src_prefix, dst_prefix, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets';

					$sql_groupby       = 'GROUP BY src_prefix, dst_prefix';
					$sql_inner_groupby = 'GROUP BY src_prefix, dst_prefix';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
			}
		} elseif ($data['printed'] > 0) {
			if (isset($print_columns_array[$data['statistics']][$data['sortfield']])) {
				$sortname = $print_columns_array[$data['statistics']][$data['sortfield']];
			}

			if (!isset($sortname) || ($sortname != get_request_var('sortname'))) {
				$data['sortfield'] = array_search('Bytes', $print_columns_array[$data['printed']]);
			}

			switch($data['printed']) {
				case '1':
					$sql_query = 'SELECT src_if, INET6_NTOA(src_addr) AS src_addr, dst_if, INET6_NTOA(dst_addr) AS dst_addr, protocol, src_port, dst_port, tos, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_if, src_addr, dst_if, dst_addr, protocol, src_port, dst_port, tos, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY src_if, INET6_NTOA(src_addr), dst_if, INET6_NTOA(dst_addr), protocol, src_port, dst_port, tos, flags';
					$sql_inner_groupby = 'GROUP BY src_if, src_addr, dst_if, dst_addr, protocol, src_port, dst_port, tos, flags';

					if ($data['sortfield'] == 1 || $data['sortfield'] == 3) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 8 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 8 ? ' DESC':' ASC');
					}

					break;
				case '4':
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, protocol, src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, protocol, src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr), protocol, src_as, dst_as';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr, protocol, src_as, dst_as';

					if ($data['sortfield'] < 2) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 4 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 4 ? ' DESC':' ASC');
					}

					break;
				case '5':
					$sql_query = 'SELECT start_time, end_time, src_if, INET6_NTOA(src_addr) AS src_addr, src_port, dst_if, INET6_NTOA(dst_addr) AS dst_addr, dst_port, protocol, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT start_time, end_time, src_if, src_addr, src_port, dst_if, dst_addr, dst_port, protocol, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY start_time, end_time, src_if, INET6_NTOA(src_addr), src_port, dst_if, INET6_NTOA(dst_addr), dst_port, protocol, flags';
					$sql_inner_groupby = 'GROUP BY start_time, end_time, src_if, src_addr, src_port, dst_if, dst_addr, dst_port, protocol, flags';

					if ($data['sortfield'] == 3 || $data['sortfield'] == 6) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 9 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 9 ? ' DESC':' ASC');
					}

					break;
				case '6':
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr';

					if ($data['sortfield'] < 2) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					}

					break;
				case '7':
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, src_port, protocol, INET6_NTOA(dst_addr) AS dst_addr, dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, src_port, protocol, dst_addr, dst_port, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), src_port, protocol, INET6_NTOA(dst_addr), dst_port';
					$sql_inner_groupby = 'GROUP BY src_addr, src_port, protocol, dst_addr, dst_port';

					if ($data['sortfield'] < 2) {
						$sql_order = 'ORDER BY INET6_NTOA(' . ($data['sortfield'] + 1) . ') ' . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					} else {
						$sql_order = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					}

					break;
			}
		}

		$tables     = get_tables_for_query($start, $end);
		$sql        = '';
		$all_params = array();
		$results    = array();
		$threads    = read_config_option('flowview_parallel_threads');
		$requests   = array();

		if (cacti_sizeof($tables)) {
			if (empty($threads) || $threads == 1) {
				foreach($tables as $table_name => $details) {
					$start_time  = strtotime($details['min_date']);
					$end_time    = strtotime($details['max_date']);
					$fsql_where  = '';
					$fsql_params = array();

					if ($start < $start_time && $end > $end_time) {
						$full_scan = true;
					} else {
						$full_scan = false;
					}

					if (isset($sql_inner1)) {
						if (!$full_scan) {
							$fsql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . $sql_range;
							$fsql_params = array_merge($sql_params, $sql_range_params);
						} else {
							$fsql_where  = $sql_where;
							$fsql_params = $sql_params;
						}

						$sql .= ($sql != '' ? ' UNION ALL ':'') . "$sql_inner1 FROM $t $fsql_where $sql_inner_groupby1";
						$all_params = array_merge($all_params, $fsql_params);

						$sql .= ($sql != '' ? ' UNION ALL ':'') . "$sql_inner2 FROM $t $fsql_where $sql_inner_groupby2";
						$all_params = array_merge($all_params, $fsql_params);
					} else {
						$sql .= ($sql != '' ? ' UNION ALL ':'') . "$sql_inner FROM $t $fsql_where $sql_inner_groupby";
						$all_params = array_merge($all_params, $fsql_params);
					}
				}

				$sql = "$sql_query FROM ($sql) AS rs $sql_groupby $sql_having $sql_order $sql_limit";

				$start   = microtime(true);
				$threads = 1;
				$shards  = cacti_sizeof($tables);

				if ($data['statistics'] == 99) {
					$results = flowview_db_fetch_row_prepared($sql, $all_params);
				} else {
					$results = flowview_db_fetch_assoc_prepared($sql, $all_params);
				}

				$end = microtime(true);

				cacti_log(sprintf('PARALLEL STATS: Time:%0.3f Threads:%d Shards:%d', $end - $start, $threads, $shards), false, 'FLOWVIEW');
			} else {
				if (isset($sql_inner1)) {
					$stru_inner1 = array(
						'sql_query'        => $sql_inner1,
						'sql_where'        => $sql_where,
						'sql_range'        => $sql_range,
						'sql_groupby'      => $sql_inner_groupby1,
						'sql_having'       => '',
						'sql_order'        => '',
						'sql_limit'        => '',
						'sql_params'       => $sql_params,
						'sql_range_params' => $sql_range_params,
						'sql_start_time'   => $start,
						'sql_end_time'     => $end
					);

					$stru_inner2 = array(
						'sql_query'        => $sql_inner2,
						'sql_where'        => $sql_where,
						'sql_range'        => $sql_range,
						'sql_groupby'      => $sql_inner_groupby2,
						'sql_having'       => '',
						'sql_order'        => '',
						'sql_limit'        => '',
						'sql_params'       => $sql_params,
						'sql_range_params' => $sql_range_params,
						'sql_start_time'   => $start,
						'sql_end_time'     => $end
					);

					$stru_outer = array(
						'sql_query'   => $sql_query,
						'sql_where'   => '',
						'sql_groupby' => $sql_groupby,
						'sql_having'  => $sql_having,
						'sql_order'   => $sql_order,
						'sql_limit'   => $sql_limit,
						'sql_params'  => array()
					);

					$requests[] = parallel_database_query_request($tables, $stru_inner1, $stru_outer);
					$requests[] = parallel_database_query_request($tables, $stru_inner2, $stru_outer);
				} else {
					$stru_inner = array(
						'sql_query'        => $sql_inner,
						'sql_where'        => $sql_where,
						'sql_range'        => $sql_range,
						'sql_having'       => '',
						'sql_order'        => '',
						'sql_limit'        => '',
						'sql_groupby'      => $sql_inner_groupby,
						'sql_params'       => $sql_params,
						'sql_range_params' => $sql_range_params,
						'sql_start_time'   => $start,
						'sql_end_time'     => $end
					);

					$stru_outer = array(
						'sql_query'        => $sql_query,
						'sql_where'        => '',
						'sql_having'       => $sql_having,
						'sql_groupby'      => $sql_groupby,
						'sql_order'        => $sql_order,
						'sql_limit'        => $sql_limit,
						'sql_params'       => array()
					);

					$requests[] = parallel_database_query_request($tables, $stru_inner, $stru_outer);
				}

				$results = parallel_database_query_run($requests);
			}
		}

		$output         = $data;
		$output['data'] = $results;

		$i = 0;
		$table = '';
		if (cacti_sizeof($results)) {
			if ($data['statistics'] != 99) {
				$table .= '<table id="sorttable" class="cactiTable"><thead>';

				foreach($results as $r) {
					if ($i == 0) {
						$table .= '<tr class="tableHeader">';

						if (isset($r['start_time'])) {
							$table .= '<th class="left">' . __('Start Time', 'flowview') . '</th>';
						}

						if (isset($r['end_time'])) {
							$table .= '<th class="left">' . __('End Time', 'flowview') . '</th>';
						}

						if (isset($r['src_domain'])) {
							$table .= '<th class="left">' . __('Source DNS', 'flowview') . '</th>';
						}

						if (isset($r['src_rdomain'])) {
							$table .= '<th class="left">' . __('Source Root DNS', 'flowview') . '</th>';
						}

						if (isset($r['dst_domain'])) {
							$table .= '<th class="left">' . __('Dest DNS', 'flowview') . '</th>';
						}

						if (isset($r['dst_rdomain'])) {
							$table .= '<th class="left">' . __('Dest Root DNS', 'flowview') . '</th>';
						}

						if (isset($r['domain'])) {
							$table .= '<th class="left">' . __('DNS', 'flowview') . '</th>';
						}

						if (isset($r['rdomain'])) {
							$table .= '<th class="left">' . __('Root DNS', 'flowview') . '</th>';
						}

						if (isset($r['ip_addr'])) {
							$table .= '<th class="left">' . __('IP Address', 'flowview') . '</th>';
						}

						if (isset($r['src_addr'])) {
							$table .= '<th class="left">' . __('Source IP', 'flowview') . '</th>';
						}

						if (isset($r['dst_addr'])) {
							$table .= '<th class="left">' . __('Dest IP', 'flowview') . '</th>';
						}

						if (isset($r['src_port'])) {
							$table .= '<th class="left nowrap">' . __('Source Port', 'flowview') . '</th>';
						}

						if (isset($r['dst_port'])) {
							$table .= '<th class="left nowrap">' . __('Dest Port', 'flowview') . '</th>';
						}

						if (isset($r['src_if'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Source IF', 'flowview') . '</th>';
						}

						if (isset($r['dst_if'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Dest IF', 'flowview') . '</th>';
						}

						if (isset($r['src_as'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Source AS', 'flowview') . '</th>';
						}

						if (isset($r['dst_as'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Dest AS', 'flowview') . '</th>';
						}

						if (isset($r['src_prefix'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Source Prefix', 'flowview') . '</th>';
						}

						if (isset($r['dst_prefix'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Dest Prefix', 'flowview') . '</th>';
						}

						if (isset($r['protocol'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Protocol', 'flowview') . '</th>';
						}

						if (isset($r['tos'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Type of Service', 'flowview') . '</th>';
						}

						if (isset($r['flags'])) {
							$table .= '<th class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . __('Flags', 'flowview') . '</th>';
						}

						if (isset($r['flows'])) {
							$table .= '<th class="right">' . __('Flows', 'flowview') . '</th>';
						}

						if (isset($r['bytes'])) {
							$table .= '<th class="right">' . __('Bytes', 'flowview') . '</th>';
						}

						if (isset($r['packets'])) {
							$table .= '<th class="right">' . __('Packets', 'flowview') . '</th>';
						}

						$table .= '<th class="right">' . __('Bytes/Packet') . '</th>';

						$table .= '</tr></thead><tbody>';
					}

					$table .= '<tr class="selectable tableRow">';

					if (isset($r['start_time'])) {
						$table .= '<td class="left nowrap">' . substr($r['start_time'], 0, 19) . '</td>';
					}

					if (isset($r['end_time'])) {
						$table .= '<td class="left nowrap">' . substr($r['end_time'], 0, 19) . '</td>';
					}

					if (isset($r['src_domain'])) {
						$table .= '<td class="left">' . display_domain($r['src_domain']) . '</td>';
					}

					if (isset($r['src_rdomain'])) {
						$table .= '<td class="left">' . display_domain($r['src_rdomain']) . '</td>';
					}

					if (isset($r['dst_domain'])) {
						$table .= '<td class="left">' . display_domain($r['dst_domain']) . '</td>';
					}

					if (isset($r['dst_rdomain'])) {
						$table .= '<td class="left">' . display_domain($r['dst_rdomain']) . '</td>';
					}

					if (isset($r['domain'])) {
						$table .= '<td class="left">' . display_domain($r['domain']) . '</td>';
					}

					if (isset($r['rdomain'])) {
						$table .= '<td class="left">' . display_domain($r['rdomain']) . '</td>';
					}

					if (isset($r['ip_addr'])) {
						$table .= '<td class="left">' . html_escape($r['ip_addr']) . '</td>';
					}

					if (isset($r['src_addr'])) {
						$table .= '<td class="left">' . html_escape($r['src_addr']) . '</td>';
					}

					if (isset($r['dst_addr'])) {
						$table .= '<td class="left">' . html_escape($r['dst_addr']) . '</td>';
					}

					if (isset($r['src_port'])) {
						if (isset($r['protocol'])) {
							$table .= '<td class="left nowrap">' . get_port_name($r['src_port'], $r['protocol']) . '</td>';
						} else {
							$table .= '<td class="left nowrap">' . get_port_name($r['src_port'], 6) . '</td>';
						}
					}

					if (isset($r['dst_port'])) {
						if (isset($r['protocol'])) {
							$table .= '<td class="left nowrap">' . get_port_name($r['dst_port'], $r['protocol']) . '</td>';
						} else {
							$table .= '<td class="left nowrap">' . get_port_name($r['dst_port'], 6) . '</td>';
						}
					}

					if (isset($r['src_if'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['src_if']) . '</td>';
					}

					if (isset($r['dst_if'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['dst_if']) . '</td>';
					}

					if (isset($r['src_as'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['src_as']) . '</td>';
					}

					if (isset($r['dst_as'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['dst_as']) . '</td>';
					}

					if (isset($r['src_prefix'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['src_prefix']) . '</td>';
					}

					if (isset($r['dst_prefix'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['dst_prefix']) . '</td>';
					}

					if (isset($r['protocol'])) {
						$table .= '<td class="left">' . plugin_flowview_get_protocol($r['protocol'], false) . '</td>';
					}

					if (isset($r['tos'])) {
						$table .= parse_type_of_service($r['tos']);
					}

					if (isset($r['flags'])) {
						$table .= '<td class="' . ($data['statistics'] > 0 ? 'left':'right') . '">' . html_escape($r['flags']) . '</td>';
					}

					if (isset($r['flows'])) {
						$table .= '<td class="right">' . number_format_i18n($r['flows'], 0) . '</td>';
					}

					if (isset($r['bytes'])) {
						$table .= '<td class="right">' . number_format_i18n($r['bytes'], 0) . '</td>';
					}

					if (isset($r['packets'])) {
						$table .= '<td class="right">' . number_format_i18n($r['packets'], 0) . '</td>';
					}

					if ($r['packets'] > 0) {
						$table .= '<td class="right">' . number_format_i18n($r['bytes']/$r['packets'], 0) . '</td>';
					} else {
						$table .= '<td class="right">0</td>';
					}

					$table .= '</tr>';

					$i++;
				}

				$table .= '</tbody></table>';
			} elseif ($data['statistics'] == 99) {
				$total = 0;
				$i = 0;

				/**
				 * the parallel query function returns an associative
				 * array, convert it to a row.
				 */
				$results = $results[0];

				foreach($sql_array as $c) {
					$total += $results[$c['name']];
				}

				$table .= '<table class="cactiTable"><tbody>';
				$table .= '<tr class="tableHeader right">';

				for ($i = 0; $i < 14; $i++) {
					$table .= '<th class="right">' . $sql_array[$i]['title'] . '</th>';
				}

				$table .= '</tr>';
				$table .= '<tr>';

				for ($i = 0; $i < 14; $i++) {
					$name   = $sql_array[$i]['name'];

					if ($total > 0) {
						$table .= '<td class="right" style="width:7.14%">' . number_format_i18n(($results[$name] / $total) * 100, 2) . ' %</td>';
					} else {
						$table .= '<td class="right" style="width:7.14%">' . __('N/A', 'flowview') . '</td>';
					}
				}

				$table .= '</tr></tbody></table>';

				$table .= '<table class="cactiTable"><tbody>';
				$table .= '</br>';
				$table .= '<tr class="tableHeader right">';

				for ($i = 14; $i < 27; $i++) {
					$table .= '<th class="right">' . $sql_array[$i]['title'] . '</th>';
				}

				$table .= '<th></th>';
				$table .= '</tr>';

				$table .= '<tr>';

				for ($i = 14; $i < 27; $i++) {
					$name   = $sql_array[$i]['name'];
					if ($total > 0) {
						$table .= '<td class="right" style="width:7.14%">' . number_format_i18n(($results[$name] / $total) * 100, 2) . ' %</td>';
					} else {
						$table .= '<td class="right" style="width:7.14%">' . __('N/A', 'flowview') . '</td>';
					}
				}

				$table .= '<td style="width:7.14%"></td>';
				$table .= '</tr></tbody></table>';
			}

			$output['table'] = $table;
			$output['title'] = $title;

			if ($session) {
				if (1 == 0) {
					cacti_session_start();

					$_SESSION['sess_flowdata'][$key]['data']    = $output;
					$_SESSION['sess_flowdata'][$key]['timeout'] = $time + 600;

					cacti_session_close();
				}
			}

			return $output;
		}
	}

	return false;
}

function flowview_table_name_to_time($table, $type) {
	$suffix = str_replace('plugin_flowview_raw_', '', $table);

	$year = substr($suffix, 0, 4);
	$day  = substr($suffix, 4, 3);

	if (strlen($suffix) == 7) {
		$gran = 'days';
		$hour = 0;
	} else {
		$gran = 'hours';
		$hour = substr($suffix, 8, 2);
	}

	$dates = flowview_convert_yeardayhour_to_date($gran, $year, $day, $hour);

	return $dates[$type];
}

function flowview_convert_yeardayhour_to_date($range, $year, $day, $hour = 0) {
    $datetime = new DateTime();

	if ($range == 'days') {
    	$datetime->setTimestamp(mktime(0, 0, 0, 0, 0, $year) + ($day * 86400));
		$start_date = $datetime->format('Y-m-d 00:00:00');
		$end_date   = date('Y-m-d 00:00:00', strtotime($start_date)+86400);
	} else {
    	$datetime->setTimestamp(mktime(0, 0, 0, 0, 0, $year) + ($day * 86400) + ($hour * 3600));
		$start_date = $datetime->format('Y-m-d H:00:00');
		$end_date   = date('Y-m-d H:00:00', strtotime($start_date) + ($hour * 3600));
	}

	$start_time = strtotime($start_date);
	$end_time   = strtotime($end_date);

	return array(
		'start_date' => $start_date,
		'start_time' => $start_time,
		'end_date'   => $end_date,
		'end_time'   => $end_time
	);
}
/**
 * parallel_database_query_request - Given a series of tables and
 * and inner (map phase) and outer (reduce phase) SQL queries,
 * create a series of shards requests to break the larger query into multiple
 * components to be used as a map reduce construct.
 *
 * Currently, this request design does not work with classic partitioned
 * tables.  In a later version of the parallel query we will support
 * querying from a single table constructed of multiple partitions
 * so long as we can quickly find the range of partitions that need
 * to be queried for data.
 *
 * The $stru_inner and $stru_outer have a very specific layout that follows
 * the pattern below.  The current version of the parallel query is designed
 * primarily for time related event data like syslog, and netflow data.
 * any other Time Series data requests would benefit from this API as the
 * speedups are almost linear.
 *
 * The $stru_inner syntax is as follows and documented on the right
 *
 * $stru_inner = array(
 *   'sql_query'        => $sql_inner,         // The SQL query using prepared format WHERE a = ?
 *   'sql_where'        => $sql_where,         // The SQL where for the query separated from the sql_query
 *   'sql_range'        => $sql_range,         // The SQL where of the query that includes the time range
 *   'sql_having'       => '',                 // Optional - The SQL having clause to apply
 *   'sql_order'        => '',                 // Optional - The SQL order to apply
 *   'sql_limit'        => '',                 // Optional - The SQL limit to apply
 *   'sql_groupby'      => $sql_inner_groupby, // The SQL Group By to apply
 *   'sql_params'       => $sql_params,        // An array of SQL prepared parameters
 *   'sql_range_params' => $sql_range_params,  // An array of SQL range parameters
 *   'sql_start_time'   => $start,             // The start time of the query for table reduction
 *   'sql_end_time'     => $end                // The end time of the query for table reduction
 *
 * The $stru_outer syntax is below.  You will notice that the time dimension is removed here, but
 * much of the other attributes are intact and have the same meanings as above.
 *
 * $stru_outer = array(
 *   'sql_query'        => $sql_query,
 *   'sql_where'        => '',
 *   'sql_having'       => $sql_having,
 *   'sql_groupby'      => $sql_groupby,
 *   'sql_order'        => $sql_order,
 *   'sql_limit'        => $sql_limit,
 *   'sql_params'       => array()
 *
 * @param  array    An array of tables to use for the map query
 * @param  array    An array of map query parameters
 * @param  array    An array of reduce query parameters
 *
 * @return int      A request id that will be used in the run phase
 */
function parallel_database_query_request($tables, $stru_inner, $stru_outer) {
	$save = array();

	if (isset($_SESSION['sess_user_id'])) {
		$user_id = $_SESSION['sess_user_id'];
	} else {
		$user_id = 0;
	}

	/**
	 * Prepare the inner SQL for md5sum calculations.  We have two
	 * md5's, one for the query itself, and another for the tables
	 * that match the query criteria without the time range.
	 *
	 * This ensures that if there is a time range that is in scope
	 * and the data has already been cached, for that exact query
	 * we don't have to re-cache the data.
	 */
	$map_range        = $stru_inner['sql_range'];
	$map_range_params = $stru_inner['sql_range_params'];
	$map_query        = $stru_inner;
	$sql_start_time   = $stru_inner['sql_start_time'];
	$sql_end_time     = $stru_inner['sql_end_time'];
	$md5_array        = array($stru_inner, $stru_outer);
	$query_md5        = md5(json_encode($md5_array));

	unset($map_query['sql_range']);
	unset($map_query['sql_range_params']);
	unset($map_query['sql_start_time']);
	unset($map_query['sql_end_time']);

	$reduce_query = json_encode($stru_outer);
	$map_query    = json_encode($map_query);

	$table_md5 = md5($map_query);

	$request_id = db_fetch_cell_prepared('SELECT id
		FROM parallel_database_query
		WHERE md5sum = ?',
		array($query_md5));

	$time_to_live = read_config_option('flowview_parallel_time_to_live');

	if (empty($time_to_live)) {
		set_config_option('flowview_parallel_time_to_live', 21600);
		$time_to_live = 21600;
	}

	if (empty($request_id)) {
		$save['id']               = 0;
		$save['md5sum']           = $query_md5;
		$save['md5sum_tables']    = $table_md5;
		$save['user_id']          = $user_id;
		$save['total_shards']     = cacti_sizeof($tables);
		$save['map_query']        = $map_query;
		$save['map_range']        = $stru_inner['sql_range'];
		$save['map_range_params'] = json_encode($stru_inner['sql_range_params']);
		$save['reduce_query']     = $reduce_query;
		$save['created']          = date('Y-m-d H:i:s');
		$save['time_to_live']     = time() + $time_to_live;

		$request_id = flowview_sql_save($save, 'parallel_database_query');

		foreach($tables as $table => $details) {
			$base_table = $table;
			break;
		}

		$table_name = parallel_database_query_create_reduce_table($request_id, $stru_inner['sql_query'], $base_table);

		db_execute_prepared('UPDATE parallel_database_query
			SET map_table = ?
			WHERE id = ?',
			array($table_name, $request_id));

		$start = $sql_start_time;
		$end   = $sql_end_time;
		$index = 0;

		foreach($tables as $table => $details) {
			if (!empty($details['min_date'])) {
				$start_time = strtotime($details['min_date']);
			} else {
				$start_time = flowview_table_name_to_time($table, 'start_time');
			}

			if (!empty($details['min_date'])) {
				$end_time = strtotime($details['max_date']);
			} else {
				$end_time = flowview_table_name_to_time($table, 'end_time');
			}

			$fsql_where  = '';
			$fsql_params = array();

			if ($start < $start_time && $end > $end_time) {
				$full_scan = true;
			} else {
				$full_scan = false;
			}

			if (!$full_scan) {
				$fsql_where  = $stru_inner['sql_where'] . ($stru_inner['sql_where'] != '' ? ' AND ':'WHERE ') . $stru_inner['sql_range'];
				$fsql_params = array_merge($stru_inner['sql_params'], $stru_inner['sql_range_params']);
			} else {
				$fsql_where  = $stru_inner['sql_where'];
				$fsql_params = $stru_inner['sql_params'];
			}

			$map_query  = $stru_inner['sql_query'];
			$map_query .= " FROM $table";
			$map_query .= ($fsql_where != '' ? ' ' . $fsql_where:'');
			$map_query .= (isset($stru_inner['sql_groupby']) ? ' ' . $stru_inner['sql_groupby']:'');
			$map_query .= (isset($stru_inner['sql_having'])  ? ' ' . $stru_inner['sql_having']:'');
			$map_query .= (isset($stru_inner['sql_order'])   ? ' ' . $stru_inner['sql_order']:'');
			$map_query .= (isset($stru_inner['sql_limit'])   ? ' ' . $stru_inner['sql_limit']:'');

			flowview_db_execute_prepared('INSERT INTO parallel_database_query_shard
				(query_id, shard_id, full_scan, map_table, map_partition, map_query, map_params)
				VALUES (?, ?, ?, ?, ?, ?, ?)',
				array(
					$request_id,
					$index,
					$full_scan ? '1':'0',
					$details['table_name'],
					$details['table_partition'],
					$map_query,
					json_encode($fsql_params)
				)
			);

			$index++;
		}
	}

	return $request_id;
}

/**
 * parallel_database_query_run - Runs a parallel query by calling the flowview_running.php
 * in background.  If the data is already cached, then the number of pending request
 * will be zero and the flowview_runner.php will not be called and the results will
 * be pulled from the parallel_database_query tabbles 'results' column.
 *
 * @param  array   An array of requests to execut the parallel database.  In the case
 *                 where it takes multiple requests to form a proper UNION of data
 *                 there will be more than one request.
 *
 * @return array   An array of query results in the order for which they were requested
 */
function parallel_database_query_run($requests) {
	global $config, $debug;

	$php_binary = read_config_option('path_php_binary');
	$redirect   = '';

	foreach($requests as $query_id) {
		$pending = flowview_db_fetch_cell_prepared('SELECT COUNT(*)
			FROM parallel_database_query
			WHERE id = ?
			AND status = ?',
			array($query_id, 'pending'));

		if ($pending > 0) {
			db_debug('Launching FlowView Database Query Process ' . $query_id);

			cacti_log('NOTE: Launching FlowView Database Query Process ' . $query_id, false, 'BOOST', POLLER_VERBOSITY_MEDIUM);

			exec_background($php_binary, $config['base_path'] . "/plugins/flowview/flowview_runner.php --query-id=$query_id" . ($debug ? ' --debug':''), $redirect);
		} else {
			db_debug('Not Luanching FlowView Database Query Process ' . $query_id . ' as it has already completed or is running.');
		}
	}

	$max_time = read_config_option('flowview_parallel_runlimit');

	$start = time();
	$total_time = 0;

	while ($total_time < $max_time) {
		$running = db_fetch_cell('SELECT COUNT(*)
			FROM parallel_database_query
			WHERE ID IN(' . implode(', ', $requests) . ')
			AND status != "complete"');

		if ($running == 0) {
			break;
		}

		usleep(50000);

		$total_time = time() - $start;
	}

	if ($total_time >= $max_time) {
		raise_message_javascript('parallel_query_timeout', __('The Parallel Query Timed Out.  Please contact your administrator', 'flowview'), MESSAGE_LEVEL_ERROR);
		exit;
	}

	if (cacti_sizeof($requests) == 1) {
		return json_decode(flowview_db_fetch_cell_prepared('SELECT results
			FROM parallel_database_query
			WHERE id = ?',
			array($query_id)), true);
	} else {
		$reduce_query = flowview_db_fetch_cell_prepared('SELECT reduce_query
			FROM parallel_database_query
			WHERE id = ?',
			array($requests[0]));

		$tables = array_rekey(
			flowview_db_fetch_assoc('SELECT map_table
				FROM parallel_database_query
				WHERE id IN(' . implode(', ', $requests) . ')'),
			'map_table', 'map_table'
		);

		if ($reduce_query != '' && cacti_sizeof($tables)) {
			$query = json_decode($reduce_query, true);
			$sql   = $query['reduce_query'] . ' FROM (';

			$i = 0;
			foreach($tables as $table) {
				$sql .= ($i > 0 ? ' UNION ALL ':'') . "SELECT * FROM $table";
				$i++;
			}

			$sql .= ') AS rs';

			return flowview_db_fetch_assoc($sql);
		} else {
			return array();
		}
	}
}

/**
 * parallel_database_query_create_reduce_table - Based upon the request ID and the
 * base table as well as the query parameters, construct a valid temporary table
 * to hold the map query results that will be reduced in a subsequent step.
 *
 * This function is pretty simplistic at this point in time.  It assumes that
 * the map query has aliases for all the columns and those aliases should match
 * the names in the base table provided as the third parameter.  With that
 * it will create a table to hold the map data using that structure.
 *
 * This is a rather simplistic approach.  In a future release, we will look
 * both at the base table, but also we will look at the consolidation functions
 * that the user has requested, for example SUM(), MIN(), MAX() can retain
 * the column type of the underlying table, but a AVG() or STDDEV() consolidation
 * functions should always be converted to a float.  This can also be done
 * with a CAST() function call in the reduce phase, but it would be convenient
 * to do this automatically in this function.
 *
 * @param  int     The parallel query request id
 * @param  string  The SQL map query to run
 * @param  string  The base table to inspect for column types
 *
 * @return string  The name of the table that was created to hold the map data
 */
function parallel_database_query_create_reduce_table($request_id, $sql_query, $table) {
	$table_name = "parallel_database_query_map_$request_id";

	$sql_create = "CREATE TABLE IF NOT EXISTS $table_name (";

	/* get the columns from the base table */
	$columns = array_rekey(
		flowview_db_fetch_assoc_prepared('SELECT *
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE table_name = ?',
			array($table)),
		'COLUMN_NAME', array('DATA_TYPE', 'COLUMN_TYPE', 'IS_NULLABLE', 'COLUMN_DEFAULT')
	);

	/* simple tokenization of the SQL query */
	$column_data = explode(',', $sql_query);
	$i = 0;
	foreach($column_data as $c) {
		$parts   = explode(' ', $c);
		$colname = end($parts);

		if (isset($columns[$colname])) {
			$sql_create .= ($i > 0 != '' ? ', ':'') .
				'`' . $colname . '` ' .
				$columns[$colname]['COLUMN_TYPE'] .
				($columns[$colname]['IS_NULLABLE'] == 'NO' ? ' NOT NULL ':'') .
				($columns[$colname]['COLUMN_DEFAULT'] != '' ? ' DEFAULT "' . $columns[$colname]['COLUMN_DEFAULT'] . '"':'');
		} else {
			$found = false;

			foreach($columns as $column => $attrib) {
				if (stripos($c, $column) !== false) {
					$sql_create .= ($i > 0 != '' ? ', ':'') .
						'`' . $colname . '` ' .
						$attrib['COLUMN_TYPE'] .
						($attrib['IS_NULLABLE'] == 'NO' ? ' NOT NULL ':'') .
						($attrib['COLUMN_DEFAULT'] != '' ? ' DEFAULT "' . $attrib['COLUMN_DEFAULT'] . '"':'');

					$found = true;
					break;
				}
			}

			if (!$found) {
				$sql_create .= ($i > 0 != '' ? ', ':'') .
					'`' . $colname . '` bigint unsigned NOT NULL default "0"';
			}
		}

		$i++;
	}

	$sql_create .= ') ENGINE=InnoDB COMMENT="Holds Parallel Query Results"';

	flowview_db_execute($sql_create);

	return $table_name;
}

function db_debug($string) {
	global $debug;

	if ($debug) {
		cacti_log($string, false, 'FLOWVIEW');
	}
}

function display_domain($domain) {
	if ($domain != '') {
		return $domain;
	} else {
		return __('unresolved', 'flowview');
	}
}

function get_json_params() {
	$arr = array();

	foreach($_POST as $var => $val) {
		switch($var) {
			case '__csrf_magic':
			case 'domains':
			case 'table':
			case 'view':
			case 'bytes':
			case 'packets':
			case 'flows':
				break;
			default:
				$arr[$var] = $val;
		}
	}

	return json_encode($arr);
}

function get_column_alignment($column) {
	switch($column) {
	case __('Bytes', 'flowview'):
	case __('Packets', 'flowview'):
	case __('Flows', 'flowview'):
		return 'right';
		break;
	default:
		return 'left';
	}
}

function parse_type_of_service($tos) {
	$otosn = $tos;
	$otosx = dechex($tos);

	if ($tos != '') {
		$i = 0;
		$toslen = strlen($otosx);
		$output = '';

		while ($i < $toslen) {
			$value = substr($otosx, $i, 1);
			switch($value) {
				case '0':
					$output .= '0000';
					break;
				case '1':
					$output .= '0001';
					break;
				case '2':
					$output .= '0010';
					break;
				case '3':
					$output .= '0011';
					break;
				case '4':
					$output .= '0100';
					break;
				case '5':
					$output .= '0101';
					break;
				case '6':
					$output .= '0110';
					break;
				case '7':
					$output .= '0111';
					break;
				case '8':
					$output .= '1000';
					break;
				case '9':
					$output .= '1001';
					break;
				case 'a':
					$output .= '1010';
					break;
				case 'b':
					$output .= '1011';
					break;
				case 'c':
					$output .= '1100';
					break;
				case 'd':
					$output .= '1101';
					break;
				case 'e':
					$output .= '1110';
					break;
				case 'f':
					$output .= '1111';
					break;
			}

			$i++;
		}

		if (strlen($output) < 8) {
			$output .= '0000';
		}

		return '<td class="left" title="' . __('Boolean: %s, Numeric: %s, Hex: %s', '0b' . $output, $otosn, '0x' . strtoupper($otosx), 'flowview') . '">' . parse_tos($otosn) . '</td>';
	}

	return $tos;
}

function parse_tos($tos) {
	$iptos_tos_lower_mask = 30;
	$iptos_tos_upper_mask = 224;

	$tos_lower = array(
		16 => __('Low Delay', 'flowview'),
		8  => __('Throughput', 'flowview'),
		4  => __('Reliability', 'flowview'),
		2  => __('Mincost', 'flowview')
	);

	$tos_upper = array(
		224 => __('Net Control', 'flowview'),
		192 => __('Internet Control', 'flowview'),
		160 => __('Critic ECP', 'flowview'),
		128 => __('Flash Override', 'flowview'),
		96  => __('Flash', 'flowview'),
		64  => __('Immediate', 'flowview'),
		32  => __('Priority', 'flowview'),
		00  => __('Routine', 'flowview')
	);

	$output = '';

	foreach($tos_lower as $mask => $name) {
		$ntos = $tos & $iptos_tos_lower_mask;
		if (($ntos & $mask) == $mask) {
			$output .= ($output != '' ? ', ':'') . $name;
			break;
		}
	}

	foreach($tos_upper as $mask => $name) {
		$ntos = $tos & $iptos_tos_upper_mask;
		if (($ntos & $mask) == $mask) {
			$output .= ($output != '' ? ', ':'') . $name;
			break;
		}
	}

	return $output;
}

function parseSummaryReport($output) {
	global $config;

	$output = explode("\n", $output);

	$insummary = true;
	$inippsd   = false;
	$inppfd    = false;
	$inopfd    = false;
	$inftd     = false;
	$section   = 'insummary';
	$i = 0; $j = 0;

	/* do some output buffering */
	ob_start();

	html_start_box(__('Summary Statistics', 'flowview'), '100%', '', '3', 'center', '');

	if (cacti_sizeof($output)) {
		foreach($output as $l) {
			$l = trim($l);
			if (substr($l,0,1) == '#' || strlen($l) == 0) {
				continue;
			}

			if (substr_count($l, 'IP packet size distribution')) {
				html_end_box(false);
				html_start_box(__('IP Packet Size Distribution (%%)', 'flowview'), '100%', '', '3', 'center', '');
				$section = 'inippsd';
				continue;
			} elseif (substr_count($l, 'Packets per flow distribution')) {
				html_end_box(false);
				html_start_box(__('Packets per Flow Distribution (%%)', 'flowview'), '100%', '', '3', 'center', '');
				$section = 'inppfd';
				continue;
			} elseif (substr_count($l, 'Octets per flow distribution')) {
				html_end_box(false);
				html_start_box(__('Octets per Flow Distribution (%%)', 'flowview'), '100%', '', '3', 'center', '');
				$section = 'inopfd';
				continue;
			} elseif (substr_count($l, 'Flow time distribution')) {
				html_end_box(false);
				html_start_box(__('Flow Time Distribution (%%)', 'flowview'), '100%', '', '3', 'center', '');
				$section = 'inftd';
				continue;
			}

			switch($section) {
			case 'insummary':
				if ($i % 2 == 0) {
					if ($i > 0) {
						print '</tr>';
					}
					print "<tr class='" . flowview_altrow($j) . "'>";
					$j++;
				}

				$parts  = explode(':', $l);
				$header = trim($parts[0]);
				$value  = trim($parts[1]);

				print '<td>' . $header . '</td><td>' . number_format_i18n($value) . '</td>';

				break;
			case 'inippsd':
			case 'inppfd':
			case 'inopfd':
			case 'inftd':
				/* Headers have no decimals */
				if (!substr_count($l, '.')) {
					print "<tr class='" . flowview_altrow($i) . "'>";
					$parts = flowview_explode($l);
					$k = 0;
					$l = cacti_sizeof($parts);
					foreach($parts as $p) {
						print "<td class='right'><strong>" . $p . "</strong></td>";
						if ($l < 15 && $k == 10) {
							print "<td colspan='4'></td>";
						}
						$k++;
					}
					print "</tr>";
				} else {
					print "<tr class='" . flowview_altrow($i) . "'>";
					$parts = flowview_explode($l);
					$k = 0;
					$l = cacti_sizeof($parts);
					foreach($parts as $p) {
						print "<td class='right'>" . ($p*100) . "</td>";
						if ($l < 15 && $k == 10) {
							print "<td colspan='4'></td>";
						}
						$k++;
					}
					print '</tr>';
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

	$array=explode(' ', $string);
	foreach($array as $e) {
		if ($e != '') {
			$newa[] = $e;
		}
	}

	return $newa;
}

function removeWhiteSpace($string) {
	$string = str_replace("\t", ' ', $string);
	while (substr_count('  ',$string)) {
		$string = str_replace('  ', ' ', $string);
	}
	return $string;
}

function plugin_flowview_get_protocol($prot, $prot_hex) {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$prot = ltrim($prot,'0');
	$prot = ($prot_hex ? hexdec($prot):$prot);

	if (isset($ip_protocols_array[$prot])) {
		return $ip_protocols_array[$prot] . ' (' . $prot . ')';
	}

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

function flowview_altrow($i) {
	if ($i/2 == intval($i/2)) {
		return 'even';
	} else {
		return 'odd';
	}
}

function flowview_get_rdomain_from_domain($domain) {
	$str = '';

	if ($domain != '' && strpos($domain, '.') !== false) {
		$parts = explode('.', $domain);
		$size  = cacti_sizeof($parts);
		$str .= $parts[$size - 2] . '.' . $parts[$size - 1];
	}

	return $str;
}

function flowview_translate_port($port, $is_hex, $detail = true) {
	global $config;

	static $services = array();
	static $services_detail = array();

	if ($is_hex) {
		$port = hexdec($port);
	}

	if ($detail && isset($services_detail[$port])) {
		return $services_detail[$port];
	} elseif (!$detail && isset($services[$port])) {
		return $services[$port];
	}

	$service = flowview_db_fetch_cell_prepared('SELECT service
		FROM plugin_flowview_ports
		WHERE port = ?
		LIMIT 1', array($port));

	if ($service != '') {
		if (!$detail) {
			$services[$port] = $service;
			return $service;
		} else {
			$services_details[$port] = $service . '(' . $port . ')';
			return $services_details[$port];
		}
	} elseif ($port >= 49152) {
		if (!$detail) {
			$services[$port] = 'dynamic';
			return $services[$port];
		} else {
			$services_detail[$port] = 'dynamic (' . $port . ')';
			return $services_detail[$port];
		}
	} elseif (!$detail) {
		$services[$port] = 'unknown';
		return $services[$port];
	} else {
		$services_detail[$port] = 'unknown (' . $port . ')';
		return $services_detail[$port];
	}
}

function flowview_check_fields() {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (get_request_var('statistics') == 0 && get_request_var('printed') == 0) {
		return __('You must select a Statistics Report or Printed Report!', 'flowview');
	}

	if (get_request_var('statistics') > 0 && get_request_var('printed') > 0) {
		return __('You must select only a Statistics Report or a Printed Report (not both)!', 'flowview');
	}

	if (strtotime(get_request_var('date1')) > strtotime(get_request_var('date2'))) {
		return __('Invalid dates, End Date/Time is earlier than Start Date/Time!', 'flowview');
	}

	if (get_request_var('sourceip') != '') {
		$a = explode(',', get_request_var('sourceip'));

		foreach ($a as $source_a) {
			$s = explode('/', $source_a);
			$source_ip = $s[0];

			if (!preg_match('/^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $source_ip)) {
				return __('Invalid IP for the Source Address!<br>(Must be in the form of \'192.168.0.1\')', 'flowview');
			}

			$subs = explode('.', $source_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return __('Invalid IP for the Source Address!<br>(Must be in the form of \'192.168.0.1\')', 'flowview');
			}

			if (isset($s[1])) {
				$subnet = $s[1];
				if (!preg_match('/^[0-9]{1,3}$/', $subnet)) {
					if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $subnet)) {
						return __('Invalid subnet for the Source Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}

					$subs = explode('.', $subnet);

					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return __('Invalid subnet for the Source Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return __('Invalid subnet for the Source Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}
				}
			}
		}
	}

	if (get_request_var('destip') != '') {
		$a = explode(',', get_request_var('destip'));

		foreach ($a as $dest_a) {
			$s = explode('/',$dest_a);
			$dest_ip = $s[0];
			if (!preg_match('/^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $dest_ip)) {
				return __('Invalid IP for the Destination Address!<br>(Must be in the form of \'192.168.0.1\')', 'flowview');
			}
			$subs = explode('.', $dest_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return __('Invalid IP for the Destination Address!<br>(Must be in the form of \'192.168.0.1\')', 'flowview');
			}
			if (isset($s[1])) {
				$subnet = $s[1];
				if (!preg_match('/^[0-9]{1,3}$/', $subnet)) {
					if (!preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $subnet)) {
						return __('Invalid subnet for the Destination Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}
					$subs = explode('.', $subnet);
					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return __('Invalid subnet for the Destination Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return __('Invalid subnet for the Destination Address!<br>(Must be in the form of \'192.168.0.1/255.255.255.0\' or \'192.168.0.1/24\')', 'flowview');
					}
				}
			}
		}
	}

	if (get_request_var('sourceinterface') != '') {
		$sourceinterface = str_replace(' ', '', get_request_var('sourceinterface'));

		$s_if = explode(',',$sourceinterface);

		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 999 || !is_numeric($s)) {
				return __('Invalid value for Source Interface!', 'flowview');
			}
		}
	}

	if (get_request_var('sourceport') != '') {
		$sourceport = str_replace(' ', '', get_request_var('sourceport'));

		$s_port = explode(',',$sourceport);

		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 65535 || $s < 0 || !is_numeric($s)) {
				return __('Invalid value for Source Port! (0 - 65535)', 'flowview');
			}
		}
	}

	if (get_request_var('sourceas') != '') {
		$sourceas = str_replace(' ', '', $get_request_var('sourceas'));

		$s_as = explode(',',$sourceas);

		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 65535 || $s < 0 || !is_numeric($s)) {
				return __('Invalid value for Source AS! (0 - 65535)', 'flowview');
			}
		}
	}

	if (get_request_var('destinterface') != '') {
		$destinterface = str_replace(' ', '', get_request_var('destinterface'));

		$s_if = explode(',', $destinterface);

		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 999 || !is_numeric($s)) {
				return __('Invalid value for Destination Interface!', 'flowview');
			}
		}
	}

	if (get_request_var('destport') != '') {
		$destport = str_replace(' ', '', get_request_var('destport'));

		$s_port = explode(',', $destport);

		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 65535 || $s < 0 || !is_numeric($s)) {
				return __('Invalid value for Destination Port! (0 - 65535)', 'flowview');
			}
		}
	}

	if (get_request_var('destas') != '') {
		$destas = str_replace(' ', '', get_request_var('destas'));

		$s_as = explode(',', $destas);

		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 65535 || $s < 0 || !is_numeric($s)) {
				return __('Invalid value for Destination AS! (0 - 65535)', 'flowview');
			}
		}
	}

	if (get_request_var('protocols') != '') {
		$protocols = str_replace(' ', '', get_request_var('protocols'));

		$s_port = explode(',', $protocols);

		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-') {
				$s = substr($s, 1);
			}

			if ($s > 255 || $s < 0 || !is_numeric($s)) {
				return __('Invalid value for Protocol! (1 - 255)', 'flowview');
			}
		}
	}

	if (get_request_var('tcpflags') != '') {
		$tcpflags = str_replace(' ', '', get_request_var('tcpflags'));

		$tcp_flag = explode(',', $tcpflags);

		foreach ($tcp_flag as $t) {
			if (!preg_match("/^[-]{0,1}((0x[0-9a-zA-Z]{1,3})|([0-9a-zA-Z]{1,3}))(/[0-9a-zA-Z]{1,3}) {0,1}$/", $t)) {
					return __('Invalid value for TCP Flag! (ex: 0x1b or 0x1b/SA or SA/SA)', 'flowview');
			}
		}
	}

	if (get_request_var('cutoffoctets') != '' &&
		(get_request_var('cutoffoctets') < 0 ||
		get_request_var('cutoffoctets') > 99999999999999999 ||
		!is_numeric(get_request_var('cutoffoctets')))) {
		return __('Invalid value for Cutoff Octets!', 'flowview');
	}

	if (get_request_var('cutofflines') != '' &&
		(get_request_var('cutofflines') < 0 ||
		get_request_var('cutofflines') > 999999 ||
		!is_numeric(get_request_var('cutofflines')))) {
		return __('Invalid value for Cutoff Lines!', 'flowview');
	}

	if (get_request_var('sortfield') != '' &&
		(get_request_var('sortfield') < 0 ||
		get_request_var('sortfield') > 99 ||
		!is_numeric(get_request_var('sortfield')))) {
		return __('Invalid value for Sort Field!', 'flowview');
	}
}

function flowview_draw_table(&$output) {
	print "<div>";
	print "<div id='flowcontent' style='display:none'>";
	if ($output !== false && isset($output['table'])) {
		print $output['table'];
	}
	print "</div>";
	print "</div>";
}

function flowview_draw_statistics(&$output) {
	print "<div>";
	print "<div id='data'>";
	print $output;
	print "</div>";
	print "</div>";
}

function flowview_draw_chart($type, $title) {
	global $config;
	static $chartid = 0;

	print "<div id='wrapper" . $type . "' style='display:none;'>";
	html_start_box(__('FlowView Chart for %s Type is %s', $title, ucfirst($type), 'flowview'), '100%', true, '3', 'center', '');
	print "<tr><td class='center'>";
	print "<div id='chart$type' class='chart'></div>";
	print "</td></tr>";
	html_end_box(false, true);
	print "</div>";

	$chartid++;
}

/**
 * flowview_get_dns_from_ip - This function provides a good method of performing
 * a rapid lookup of a DNS entry for a host so long as you don't have to look far.
 */
function flowview_get_dns_from_ip($ip, $timeout = 1000) {
	global $config;

	include_once($config['base_path'] . '/plugins/flowview/Net/DNS2.php');

	// First check to see if its in the cache
	$cache = flowview_db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_dnscache
		WHERE ip = ?',
		array($ip));

	if (isset($cache['host'])) {
		return $cache['host'];
	}

	$dns1    = read_config_option('settings_dns_primary');
	$dns2    = read_config_option('settings_dns_secondary');
	$timeout = ceil(read_config_option('settings_dns_timeout')/1000);
	$ldomain = read_config_option('flowview_local_domain');

	$time = time();

	$slashpos = strpos($ip, '/');
	if ($slashpos) {
		$suffix = substr($ip, $slashpos);
		$ip = substr($ip, 0, $slashpos);
	} else {
		$suffix = '';
	}

	/* initializae the arin info */
	$arin_id  = 0;
	$arin_ver = 0;

	/* check for private ranges first */
	$dns_name = flowview_check_for_private_network($ip);
	if ($ip != $dns_name) {
		$priv_dns_name = gethostbyaddr($ip);
		$local_range   = flowview_check_local_iprange($ip);
		$arin_ver      = 1;

		if ($priv_dns_name == $ip || $priv_dns_name === false) {
			if ($local_range) {
				$dns_name = 'ip-' . $ip . '.' . $ldomain;

				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, arin_id, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $dns_name, 'Local Domain', $arin_ver, $arin_id, $time));

				return $dns_name;
			} else {
				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, arin_id, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $dns_name, 'Static Private', $arin_ver, $arin_id, $time));

				return $dns_name;
			}
		} else {
			if (strpos($priv_dns_name, '.') === false) {
				$priv_dns_name .= '.' . read_config_option('flowview_local_domain');
			}

			/* good dns_name */
			flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
				(ip, host, source, arin_verified, arin_id, time)
				VALUES (?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					time = VALUES(time),
					source = VALUES(source),
					host = VALUES(host),
					arin_verified = VALUES(arin_verified),
					arin_id = VALUES(arin_id)',
				array($ip, $priv_dns_name, 'Local DNS', $arin_ver, $arin_id, $time));

			return $priv_dns_name;
		}
	}

	/* now let's try our specified DNS if this option is selected */
	if (read_config_option('flowview_dns_method') == 1 && ($dns1 != '' || $dns2 != '')) {
		if ($dns1 != '') {
			$nameservers[] = $dns1;
		}

		if ($dns2 != '') {
			$nameservers[] = $dns2;
		}

		$resolver = new Net_DNS2_Resolver(
			array(
				'nameservers' => $nameservers,
				'timeout'     => $timeout
			)
		);

		try {
			$resp = $resolver->query($ip, 'PTR');

			if (isset($resp->answer[0])) {
				if (property_exists($resp->answer[0], 'ptrdname')) {
					$dns_name = $resp->answer[0]->ptrdname;

					if (read_config_option('flowview_use_arin') == 'on') {
						$data = flowview_get_owner_from_arin($ip);

						if ($data !== false) {
							$arin_id  = $data['arin_id'];
							$arin_ver = 1;
						}
					}

					/* return the hostname, without the trailing '.' */
					flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
						(ip, host, source, arin_verified, arin_id, time)
						VALUES (?, ?, ?, ?, ?, ?)
						ON DUPLICATE KEY UPDATE
							time = VALUES(time),
							source = VALUES(source),
							host = VALUES(host),
							arin_verified = VALUES(arin_verified),
							arin_id = VALUES(arin_id)',
						array($ip, $dns_name, 'Specified DNS', $arin_ver, $arin_id, $time));

					return $dns_name . $suffix;
				}
			} else {
				cacti_log("WARNING: DNS Lookup for IP $ip Failed. No Response!", false, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);
			}
		} catch(Net_DNS2_Exception $e) {
			cacti_log("WARNING: DNS Lookup for IP $ip Failed. Exception Response.", false, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);
		}

		/* the resolver did not work, try the old method */
		$dns_name = gethostbyaddr($ip);

		if ($dns_name === false || $dns_name == $ip) {
			if (read_config_option('flowview_use_arin') == 'on') {
				$data = flowview_get_owner_from_arin($ip);

				if ($data !== false) {
					$dns_name = $data['dns_name'];
					$arin_id  = $data['arin_id'];
				} else {
					$dns_name = false;
				}
			} else {
				$dns_name = $ip;
			}

			if ($ip != $dns_name && $dns_name !== false) {
				if (read_config_option('flowview_use_arin') == 'on') {
					$data = flowview_get_owner_from_arin($ip);

					if ($data !== false) {
						$arin_id  = $data['arin_id'];
						$arin_ver = 1;
					}
				}

				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, arin_id, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $dns_name, 'ARIN', $arin_ver, $arin_id, $time));

				return $dns_name;
			} else {
				$dns_name = 'ip-' . str_replace('.', '-', $ip) . '.arin-error.net';

				/* error - return the hostname we constructed (without the . on the end) */
				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, arin_id, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $dns_name, 'ARIN Error', $arin_ver, $arin_id, $time));

				return $dns_name;
			}
		} else {
			if (strpos($dns_name, '.') === false) {
				$dns_name .= '.' . read_config_option('flowview_local_domain');
			}

			/* return the hostname, without the trailing '.' */
			flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
				(ip, host, source, arin_verified, arin_id, time)
				VALUES (?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					time = VALUES(time),
					source = VALUES(source),
					host = VALUES(host),
					arin_verified = VALUES(arin_verified),
					arin_id = VALUES(arin_id)',
				array($ip, $dns_name, 'Local DNS', 1, 0, $time));

			return $dns_name . $suffix;
		}
	} else {
		$address = gethostbyaddr($ip);
		$dns_name = $ip;

		if ($address !== false) {
			$dns_name = $address;
		}

		if ($dns_name != $ip) {
			if (strpos($dns_name, '.') === false) {
				$dns_name .= '.' . read_config_option('flowview_local_domain');
			}

			flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
				(ip, host, source, arin_verified, arin_id, time)
				VALUES (?, ?, ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					time = VALUES(time),
					source = VALUES(source),
					host = VALUES(host),
					arin_verified = VALUES(arin_verified),
					arin_id = VALUES(arin_id)',
				array($ip, $dns_name, 'Local DNS', 1, 0, $time));

			return $dns_name;
		} else {
			if (read_config_option('flowview_use_arin') == 'on') {
				$data = flowview_get_owner_from_arin($ip);

				if ($data !== false) {
					$dns_name = $data['dns_name'];
					$arin_id  = $data['arin_id'];
				} else {
					$dns_name = false;
				}
			} else {
				$dns_name = $ip;
			}

			if ($dns_name != $ip && $dns_name !== false) {
				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, arin_id, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $dns_name, 'ARIN', $arin_ver, $arin_id, $time));

				return $dns_name;
			} else {
				$dns_name = 'ip-' . str_replace('.', '-', $ip);

				/* error - return the hostname we constructed (without the . on the end) */
				flowview_db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, source, arin_verified, time)
					VALUES (?, ?, ?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						time = VALUES(time),
						source = VALUES(source),
						host = VALUES(host),
						arin_verified = VALUES(arin_verified),
						arin_id = VALUES(arin_id)',
					array($ip, $ip, 'ARIN Error', $arin_ver, $arin_id, $time));

				return $dns_name;
			}
		}
	}
}

function flowview_get_color($as_array = false) {
	static $position = 0;
	$palette = array('#F23C2E', '#32599A', '#F18A47', '#AC9509', '#DAAC10');

	if ($as_array) {
		$position = 0;
		return $palette;
	} else {
		$color = $palette[$position % cacti_sizeof($palette)];
		$position++;
		return $color;
	}
}

/**
 * get_colored_field_column - given a flow templates field id
 * return a supported or non-supported text with the correct
 * color.
 *
 * @param int     - The flowview template  id
 *
 * @return - a string containing html that represents the field id's status
 */
function get_colored_field_column($field_id) {
	global $flow_fieldids;

	if (isset($flow_fieldids[$field_id])) {
		return "<span class='deviceUp'>" . __('Supported', 'flowview') . "</span>";
	} else {
		return "<span class='deviceDown'>" . __('Not Supported', 'flowview') . "</span>";
	}
}

/** flowview_report_session()
 *
 * This function will update the checkbox
 * session values for page refreshes.
 */
function flowview_report_session() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'exclude' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '0',
			'options' => array('options' => 'sanitize_search_string')
		),
		'domains' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
		),
		'table' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'true',
			'options' => array('options' => 'sanitize_search_string')
		),
		'packets' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'bytes' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'flows' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fvw');
	/* ================= input validation ================= */
}

function flowview_check_local_iprange($ip) {
    $local_iprange = read_config_option('flowview_local_iprange');
    $range_parts   = explode('/', $local_iprange);

    if (cacti_sizeof($range_parts) == 1) {
        /* without a mask we will assume zero's */
		$ip_parts = explode('.', $local_iprange);

        for ($i = cacti_count($ip_parts) - 1; $i > 0; $i--) {
            if ($ip_parts[$i] == 0) {
                unset($ip_parts[$i]);
            } else {
                break;
            }
        }

		$ip_start = implode('.', $ip_parts);
		$ip_len   = strlen($ip_start);

		if (substr($ip, 0, $ip_len) == $ip_start) {
			return true;
		}
    } else {
        $range = $range_parts[0];
        $cidr  = $range_parts[1];

		$matches = db_fetch_cell_prepared('SELECT
			INET_ATON(?) & -1 << 32 - ? = INET_ATON(?) & -1 << 32 - ? AS matches',
			array($ip, $cidr, $range, $cidr));

		if ($matches == 1) {
			return true;
		}
    }

	return false;
}


function flowview_check_databases($import_only = false, $force = false) {
	$databases = array(
		'afrinic' => array(
			'serial'  => 'AFRINIC.CURRENTSERIAL',
			'files'   => 'afrinic.db.gz',
			'ftp'     => 'ftp://ftp.afrinic.net/pub/dbase/'
		),
		'altdb' => array(
			'serial'  => 'ALTDB.CURRENTSERIAL',
			'files'   => 'altdb.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'apnic' => array(
			'serial'  => 'APNIC.CURRENTSERIAL',
			'files' => array(
				'apnic.db.as-block.gz',
				'apnic.db.as-set.gz',
				'apnic.db.aut-num.gz',
				'apnic.db.domain.gz',
				'apnic.db.filter-set.gz',
				'apnic.db.inet-rtr.gz',
				'apnic.db.inet6num.gz',
				'apnic.db.inetnum.gz',
				'apnic.db.irt.gz',
				'apnic.db.key-cert.gz',
				'apnic.db.mntner.gz',
				'apnic.db.organisation.gz',
				'apnic.db.peering-set.gz',
				'apnic.db.role.gz',
				'apnic.db.route-set.gz',
				'apnic.db.route.gz',
				'apnic.db.route6.gz',
				'apnic.db.rtr-set.gz'
			),
			'ftp'     => 'ftp://ftp.apnic.net/apnic/whois/'
		),
		'arin' => array(
			'serial'  => 'ARIN.CURRENTSERIAL',
			'files'   => 'arin.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'bboi' => array(
			'serial'  => 'BBOI.CURRENTSERIAL',
			'files'   => 'bboi.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'bell' => array(
			'serial'  => 'BELL.CURRENTSERIAL',
			'files'   => 'bell.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'canarie' => array(
			'serial'  => 'CANARIE.CURRENTSERIAL',
			'files'   => 'canarie.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'idnic' => array(
			'serial'  => 'IDNIC.CURRENTSERIAL',
			'files'   => 'idnic.db.gz',
			'ftp'     => 'ftp://irr-mirror.idnic.net/'
		),
		'jpirr' => array(
			'serial'  => 'JPIRR.CURRENTSERIAL',
			'files'   => 'jpirr.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'jpnic' => array(
			'serial'  => 'JPNIC.CURRENTSERIAL',
			'files'   => 'jpnic.db.gz',
			'ftp'     => 'ftp://ftp.apnic.net/public/apnic/dbase/data/'
		),
		'krnic' => array(
			'serial'  => 'KRNIC.CURRENTSERIAL',
			'files'   => 'krnic.db.gz',
			'ftp'     => 'ftp://ftp.apnic.net/public/apnic/dbase/data/'
		),
		'twnic' => array(
			'serial'  => 'TWNIC.CURRENTSERIAL',
			'files'   => 'twnic.db.gz',
			'ftp'     => 'ftp://ftp.apnic.net/public/apnic/dbase/data/'
		),
		'lacnic' => array(
			'serial'  => 'LACNIC.CURRENTSERIAL',
			'files'   => 'lacnic.db.gz',
			'ftp'     => 'https://irr.lacnic.net/'
		),
		'level3' => array(
			'serial'  => 'LEVEL3.CURRENTSERIAL',
			'files'   => 'level3.db.gz',
			'ftp'     => 'ftp://rr.level3.net/'
		),
		'wcgdb' => array(
			'serial'  => 'WCGDB.CURRENTSERIAL',
			'files'   => 'wcgdb.db.gz',
			'ftp'     => 'ftp://rr.level3.net/'
		),
		'netegg' => array(
			'serial'  => 'NETEGG.CURRENTSERIAL',
			'files'   => array('netegg.db.gz', 'nestegg.db.gz'),
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'nttcom' => array(
			'serial'  => 'NTTCOM.CURRENTSERIAL',
			'files'   => 'nttcom.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'radb' => array(
			'serial'  => 'RADB.CURRENTSERIAL',
			'files'   => 'radb.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'reach' => array(
			'serial'  => 'REACH.CURRENTSERIAL',
			'files'   => 'reach.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		),
		'ripe' => array(
			'serial'  => 'RIPE.CURRENTSERIAL',
			'files'   => 'ripe.db.gz',
			'ftp'     => 'ftp://ftp.ripe.net/ripe/dbase/'
		),
		'tc' => array(
			'serial'  => 'TC.CURRENTSERIAL',
			'files'   => 'tc.db.gz',
			'ftp'     => 'ftp://ftp.radb.net/radb/dbase/'
		)
	);

	$supported_tables = array(
		'as_block',
		'as_set',
		'aut_num',
		'domain',
		'filter_set',
		'inetnum',
		'inet_rtr',
		'irt',
		'mntner',
		'organisation',
		'peering_set',
		'person',
		'poem',
		'poetic_form',
		'role',
		'route',
		'route_set',
		'rtr_set'
	);

	$directory = sys_get_temp_dir();

	foreach($databases as $source => $details) {
		$ftp_base  = $details['ftp'];

		if ($import_only !== false && $import_only != $source) {
			continue;
		}

		$last_serial = read_config_option("flowview_{$source}_serial");
		$curr_serial = trim(file_get_contents("$ftp_base/{$details['serial']}"));

		if ($force) {
			cacti_log("IRR UPDATE: Forced Run, IRR Source:$source, Current Serial:$curr_serial, Last Serial:$last_serial", true, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);
		} else {
			cacti_log("IRR UPDATE: IRR Source:$source, Current Serial:$curr_serial, Last Serial:$last_serial", true, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);
		}

		if ($force || $import_only !== false || ($last_serial == '' || $curr_serial != $last_serial) && $curr_serial != '') {
			if (!is_array($details['files'])) {
				$details['files'] = array($details['files']);
			}

			foreach($supported_tables as $table) {
				flowview_db_execute_prepared("UPDATE plugin_flowview_irr_$table SET present = 0 WHERE source = ?", array($source));
			}

			$files_broken = false;

			foreach($details['files'] as $file) {
				cacti_log("IRR UPDATE: Downloading {$file}", true, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);

				$local_file  = "$directory/$file";
				$remote_file = "$ftp_base/$file";

				if (file_exists($local_file) && is_writable($local_file)) {
					unlink($local_file);
				}

				$return_var = 0;
				$output     = array();
				if (!file_exists($local_file)) {
					$last_line  = exec("wget --timeout=5 --output-document='$local_file' --output-file=/dev/null $remote_file", $output, $return_var);
				}

				if ($return_var == 0) {
					cacti_log("IRR UPDATE: Importing Database File: $file", true, 'FLOWVIEW', POLLER_VERBOSITY_MEDIUM);

					set_config_option("flowview_{$source}_serial", $curr_serial);

					if (filesize($local_file) > 0) {
						flowview_update_database($source, $local_file);
					} else {
						cacti_log("IRR UPDATE: WARNING: File: $local_file is empty", true, 'FLOWVIEW');
					}

					if (file_exists($local_file) && is_writable($local_file)) {
						unlink($local_file);
					}
				}
			}

			if (!$files_broken) {
				foreach($supported_tables as $table) {
					flowview_db_execute_prepared("DELETE FROM plugin_flowview_irr_$table WHERE present = 0 AND source = ?", array($source));
				}
			}
		}
	}
}

function flowview_update_database($source, $irr_file = false) {
	global $debug;

	if ($irr_file === false) {
		cacti_log(sprintf('IRR UPDATE: WARNING: Unable to open IRR database file %s as it was not specified', $irr_file), true, 'FLOWVIEW');
		return false;
	}

	$file         = gzopen($irr_file, 'r');
	$record       = array();
	$prefixes     = array();
	$column       = '';
	$skip         = false;
	$source       = strtoupper($source);
	$records      = array();
	$record_nums  = array();
	$prevc        = '';
	$section      = '';
	$curr_section = '';
	$start        = microtime(true);

	$supported_sections = array(
		'as-block',
		'as-set',
		'aut-num',
		'domain',
		'filter-set',
		'inetnum',
		'inet6num',
		'inet-rtr',
		'irt',
		'mntner',
		'organisation',
		'peering-set',
		'person',
		'poem',
		'poetic-form',
		'role',
		'route',
		'route6',
		'route-set',
		'rtr-set',
	);

	$unsupported_sections = array(
		'key-cert',
	);

	/* prime the template records with base columns */
	foreach($supported_sections as $section) {
		$section    = str_replace('6', '', $section);
		$db_section = str_replace('-', '_', $section);
		$db_table   = "plugin_flowview_irr_$db_section";
		$columns    = flowview_db_get_table_column_types($db_table);
		$i          = 0;

		$prefixes[$section] = '(';

		foreach($columns as $name => $details) {
			$prefixes[$section] .= ($i > 0 ? ', ':'') . "`{$name}`";

			if ($name == 'source') {
				$record[$section][$name] = strtoupper($source);
			} else {
				$record[$section][$name] = '';
			}

			$i++;
		}

		$prefixes[$section] .= ')';
	}

	$i = 0;
	$j = 0;

	$warnings        = 0;
	$section_clue    = '';
	$line_no         = 1;
	$total_count     = 0;
	$skip            = false;
	$prev_section    = '';
	$prev_db_column  = '';
	$prev_irr_column = '';
	$name_remove_ct  = 1;
	$sections_done   = 0;

	while (!feof($file) !== false) {
		$line = fgets($file);

		if (substr($line, 0, 1) == '#') {
			// Ignore comment
			$line_no++;
			continue;
		}

		$col_parts = explode(':', $line, 2);

		// Skip section breaks, we are smart enough to handle
		if (trim($line) == '') {
			$line_no++;
			continue;
		}

		if (trim(substr($line, 0, 16)) == '' || substr($line, 0, 1) == '+') {
			if (substr($line, 0, 1) == '+') {
				$line[0] = ' ';
			}

			$irr_column = $prev_irr_column;

			$db_column = $prev_db_column;
			$col_value = trim($line);
		} else {
			if (cacti_sizeof($col_parts) == 2) {
				$irr_column = $col_parts[0];
				$irr_column = str_replace('6', '', $irr_column);

				// We keep IPv4 and IPv6 together
				$db_column  = str_replace('-', '_', $irr_column);

				$col_value  = trim($col_parts[1]);
			} else {
				$irr_column = $prev_irr_column;

				$db_column = $prev_db_column;
				$col_value = trim($line);
			}
		}

		if ($irr_column != '') {
			if ($irr_column != '' && in_array($irr_column, $supported_sections, true)) {
				$prev_section = $section;

				// Let's not eat too much memory
				if ($sections_done > 10000) {
					$sections_done = 0;

					flowview_insert_irr_sections($records, $prefixes, $supported_sections);

					$records    = array();
					$record_num = array();
				} else {
					$sections_done++;
				}

				$skip    = false;
				$section = $irr_column;

				if (!isset($record_num[$section])) {
					$record_num[$section] = 1;
				} else {
					$record_num[$section]++;
				}

				if ($section == 'person') {
					if ($col_value == 'Name Removed') {
						$col_value = "Name Removed ($name_remove_ct)";
						$name_remove_ct++;
					}
				}

				$records[$section][$record_num[$section]] = $record[$section];

				$records[$section][$record_num[$section]][$db_column] = $col_value;
				$records[$section][$record_num[$section]]['present']  = 1;

				$cur_section  = $section;
			} elseif (in_array($irr_column, $unsupported_sections, true)) {
				$skip = true;
			} elseif (!$skip) {
				if (trim(substr($line, 0, 16)) == '') {
					if ($records[$section][$record_num[$section]][$db_column] != '') {
						$records[$section][$record_num[$section]][$db_column] .= PHP_EOL . $col_value;
					} else {
						$records[$section][$record_num[$section]][$db_column]  = $col_value;
					}
				} elseif (substr($line, 0, 1) == '+') {
					if ($records[$section][$record_num[$section]][$db_column] != '') {
						$records[$section][$record_num[$section]][$db_column] .= PHP_EOL . trim(str_replace('+', '', $col_value));
					}
				} elseif (trim($line) == '') {
					// Unreached, or should be
				} else {
					if ($db_column != 'source') {
						if ($db_column == 'last_modified' || $db_column == 'created') {
							$col_value = date('Y-m-d H:i:s', strtotime($col_value));
						}

						if (!isset($records[$section][$record_num[$section]][$db_column])) {
							cacti_log("WARNING: Table Schema Issues, Line: $line_no, Previous Section: $prev_section, Section: $section, Column: $db_column", true, 'FLOWVIEW');
						} elseif ($records[$section][$record_num[$section]][$db_column] != '') {
							$records[$section][$record_num[$section]][$db_column] .= PHP_EOL . $col_value;
						} else {
							$records[$section][$record_num[$section]][$db_column]  = $col_value;
						}
					}

					$prev_db_column = $db_column;
				}

				if ($irr_column != '') {
					$prev_irr_column = $irr_column;
				}
			}
		}

		$line_no++;

		if ($line_no % 1000000 == 0) {
			$total_count++;

			if ($debug) {
				print "Processed {$total_count}M lines" . PHP_EOL;
			}
		}
	}

	gzclose($file);

	flowview_insert_irr_sections($records, $prefixes, $supported_sections);

	$end = microtime(true);

	cacti_log(sprintf('STATS IRR UPDATE: Time:%0.2f File:%s Source:%s', $end - $start, basename($irr_file), strtolower($source)), true, 'SYSTEM');

	return true;
}

function flowview_insert_irr_sections(&$records, &$prefixes, &$supported_sections) {
	global $debug;

	if ($debug) {
		print "Writing Database Records" . PHP_EOL;
	}

	/* do the table inserts now */
	foreach($supported_sections as $section) {
		if (isset($records[$section])) {
			$db_section   = str_replace('-', '_', $section);
			$db_table     = "plugin_flowview_irr_$db_section";
			$sql_prefix   = "REPLACE INTO $db_table {$prefixes[$section]} VALUES ";
			$section_rows = $records[$section];

			$section_chunks = array_chunk($section_rows, 100);

			foreach($section_chunks as $section_chunk) {
				$sql_params  = array();
				$sql_replace = $sql_prefix;

				foreach($section_chunk as $index => $row) {
					$columns      = cacti_sizeof($row);
					$sql_replace .= ($index > 0 ? ', ':'') . ' (' . trim(str_repeat('?, ', $columns), ', ') . ')';

					foreach($row as $column) {
						$sql_params[] = $column;
					}
				}

				flowview_db_execute_prepared($sql_replace, $sql_params);
			}

		}
	}
}

function flowview_check_for_private_network($host) {
	if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		$parts = explode('.', $host);

		if ($parts[0] == '172' && $parts[1] >= 16 && $parts[1] <= 31) {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] == '192.168') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] . '.' . $parts[2] == '192.0.0') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] == '168.254') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] == '198.18') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] == '198.19') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] == '10') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] == '127') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		} elseif ($parts[0] . '.' . $parts[1] . '.' . $parts[2] == '198.51.100') {
			return 'ip-' . str_replace('.', '-', $host) . '.testnet2.private.net';
		} elseif ($parts[0] . '.' . $parts[1] . '.' . $parts[2] == '203.0.113') {
			return 'ip-' . str_replace('.', '-', $host) . '.testnet3.private.net';
		} elseif ($parts[0] >= 224 && $parts[0] <= 239) {
			return 'ip-' . str_replace('.', '-', $host) . '.mcast.net';
		} elseif ($parts[0] >= 240 && $parts[0] <= 255) {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		}
	} elseif (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		$parts = explode(':', $host);

		if ($parts[0] >= 'FF00' ) {
			return 'ip-' . str_replace('.', '-', $host) . '.mcast.net';
		} elseif ($parts[0] . ':' . $parts[1] . ':' . $parts[2] . ':' . $parts[3] == 'FE80:0000:0000:0000') {
			return 'ip-' . str_replace('.', '-', $host) . '.linklocal.net';
		} elseif ($parts[0] >= 'FC00') {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		}
	}

	return $host;
}

function flowview_get_owner_from_arin($host) {
	static $curlgood = true;

	if ($host == '' || $host == '0.0.0.0') {
		return false;
	}

	$whois_provider = read_config_option('flowview_whois_provider');
	$whois_path     = read_config_option('flowview_path_whois');

	if (function_exists('curl_init')) {
		$ch = curl_init();
	} else {
		cacti_log('ERROR: Unable to query Arin ensure php-curl is installed', true, 'FLOWVIEW');

		return false;
	}

	curl_setopt($ch, CURLOPT_URL, 'https://whois.arin.net/rest/ip/' . $host);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:application/json'));
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 40);
	curl_setopt($ch, CURLOPT_TIMEOUT, 400);

	$response = curl_exec($ch);

	$curl_errno = curl_errno($ch);
	$curl_error = curl_error($ch);

	curl_close($ch);

	if ($curl_errno > 0) {
		$curlgood = false;

		return false;
	} elseif ($response != '') {
		$json = json_decode($response, true);

		if (isset($json['net'])) {
			/* debugging */
			if (1 == 0) {
				cacti_log("The host is: $host", true, 'FLOWVIEW');
				print_r($json);
			}

			if (isset($json['net']['netBlocks']['netBlock']['startAddress'])) {
				$cidr = $json['net']['netBlocks']['netBlock']['startAddress']['$'] . '/' .
					$json['net']['netBlocks']['netBlock']['cidrLength']['$'];

				$net_range = $json['net']['netBlocks']['netBlock']['startAddress']['$'] . '/' .
					$json['net']['netBlocks']['netBlock']['cidrLength']['$'] . ' - ' .
					$json['net']['netBlocks']['netBlock']['endAddress']['$'];

				$net_type = $json['net']['netBlocks']['netBlock']['description']['$'];
			} elseif (isset($json['net']['netBlocks']['netBlock'][0]['startAddress'])) {
				/* only get the first net block */
				$cidr = $json['net']['netBlocks']['netBlock'][0]['startAddress']['$'] . '/' .
					$json['net']['netBlocks']['netBlock'][0]['cidrLength']['$'];

				$net_range = $json['net']['netBlocks']['netBlock'][0]['startAddress']['$'] . '/' .
					$json['net']['netBlocks']['netBlock'][0]['cidrLength']['$'] . ' - ' .
					$json['net']['netBlocks']['netBlock'][0]['endAddress']['$'];

				$net_type = $json['net']['netBlocks']['netBlock'][0]['description']['$'];
			}

			$name = $json['net']['name']['$'];

			if (isset($json['net']['registrationDate']['$'])) {
				$registration = strtotime($json['net']['registrationDate']['$']);
			} else {
				$registration = 0;
			}

			if (isset($json['net']['parentNetRef']['@name'])) {
				$parent = $json['net']['parentNetRef']['@name'];
			} else {
				$parent = '';
			}

			if (isset($json['net']['originASes']['originAS']['$'])) {
				$origin = $json['net']['originASes']['originAS']['$'];
			} else {
				$origin = flowview_db_fetch_cell_prepared('SELECT origin
					FROM plugin_flowview_irr_route
					WHERE route = ?',
					array($cidr));

				if ($origin == '') {
					$return_var = 0;
					$output = array();
					$origin = '';

					if (file_exists($whois_path) && is_executable($whois_path) && $whois_provider != '') {
						$last_line = exec("$whois_path -h $whois_provider $cidr | grep 'origin:' | head -1 | awk -F':' '{print \$2}'", $output, $return_var);

						if (cacti_sizeof($output)) {
							$origin = trim($output[0]);
						}
					}
				}
			}

			$last_changed = strtotime($json['net']['updateDate']['$']);

			if (isset($json['net']['comment']['line']['$'])) {
				$comments = $json['net']['comment']['line']['$'];

				if (strpos($comments, 'BEGIN CERTIFICATE') !== false) {
					$comments = '';
				}
			} else {
				$comments = '';
			}

			$self         = $json['net']['rdapRef']['$'];
			$alternate    = $json['net']['ref']['$'];

			flowview_db_execute_prepared('INSERT INTO plugin_flowview_arin_information
				(cidr, net_range, name, parent, net_type, origin, registration, last_changed, comments, self, alternate, json_data)
				VALUES (?, ?, ?, ?, ?, ?, FROM_UNIXTIME(?), FROM_UNIXTIME(?), ?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE
					name = VALUES(name),
					parent = VALUES(parent),
					net_type = VALUES(net_type),
					last_changed = VALUES(last_changed),
					json_data = VALUES(json_data),
					comments = VALUES(comments)',
				array(
					$cidr,
					$net_range,
					$name,
					$parent,
					$net_type,
					$origin,
					$registration,
					$last_changed,
					$comments,
					$self,
					$alternate,
					$response
				)
			);

			$arin_id = flowview_db_fetch_cell_prepared('SELECT id
				FROM plugin_flowview_arin_information
				WHERE cidr = ?',
				array($cidr));

			if (isset($json['net']['name']['$'])) {
				$dns_name = 'ip-' . str_replace('.', '-', $host) . '.' . strtolower($json['net']['name']['$']) . '.net';

				return array('dns_name' => $dns_name, 'arin_id' => $arin_id);
			} else {
				return false;
			}
		} else {
			return false;
		}
	} else {
		return false;
	}
}

function flowview_get_domain($host, $domain = 'false') {
	if ($domain == 'false') {
		return $host;
	} elseif (is_ipaddress($host)) {
		return $host;
	} else {
		$parts = explode('.', $host);
		$size  = cacti_sizeof($parts);
		return $parts[$size - 2] . '.' . $parts[$size - 1];
	}
}

function flowview_getmax($value) {
	$value = round($value * 1.01, 0);

	$length  = strlen($value) - 2;
	if ($length > 0) {
		$divisor = ('1' . str_repeat('0', $length));
	} else {
		$divisor = 1;
	}

	$temp = $value / $divisor;
	$temp = ceil($temp);

	return $temp * $divisor;
}

function flowview_autoscale($value) {
	if ($value < 1000) {
		return  array(1, '');
	} elseif ($value < 1000000) {
		return array(1000, 'K');
	} elseif ($value < 1000000000) {
		return array(1000000, 'M');
	} elseif ($value < 1000000000000) {
		return array(1000000000, 'G');
	} else {
		return array(1000000000000, 'P');
	}
}

function create_raw_partition($table) {
	global $config;

	$data = array();
	// Auto increment sequence
	$data['columns'][] = array('name' => 'sequence', 'type' => 'bigint(20)', 'unsigned' => true, 'auto_increment' => true);

	// Listener information
	$data['columns'][] = array('name' => 'listener_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);

	// Template information for v9 and IPFIX
	$data['columns'][] = array('name' => 'template_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);

	// Engine Information
	$data['columns'][] = array('name' => 'engine_type', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'engine_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sampling_interval', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	// Router information
	$data['columns'][] = array('name' => 'ex_addr', 'type' => 'varchar(46)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysuptime', 'type' => 'bigint(20)', 'NULL' => false, 'default' => '0');

	// Source Details
	$data['columns'][] = array('name' => 'src_addr', 'type' => 'varbinary(16)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'src_domain', 'type' => 'varchar(256)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'src_rdomain', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'src_as', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'src_if', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'src_prefix', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'src_port', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'src_rport', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');

	// Destination Details
	$data['columns'][] = array('name' => 'dst_addr', 'type' => 'varbinary(16)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dst_domain', 'type' => 'varchar(256)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dst_rdomain', 'type' => 'varchar(40)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'dst_as', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'dst_if', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'dst_prefix', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'dst_port', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'dst_rport', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');

	// Generic Information for Combo Reports
	$data['columns'][] = array('name' => 'nexthop', 'type' => 'varchar(48)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'protocol', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	// Timing for flow reports
	$data['columns'][] = array('name' => 'start_time', 'type' => 'timestamp(6)', 'NULL' => false, 'default' => '0000-00-00');
	$data['columns'][] = array('name' => 'end_time', 'type' => 'timestamp(6)', 'NULL' => false, 'default' => '0000-00-00');

	// Key Performance Data
	$data['columns'][] = array('name' => 'flows', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'packets', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'bytes', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	// Calculated field
	$data['columns'][] = array('name' => 'bytes_ppacket', 'type' => 'double', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	// Type of service and flags
	$data['columns'][] = array('name' => 'tos', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'flags', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	$data['primary']   = 'sequence';
	$data['keys'][]    = array('name' => 'listener_id', 'columns' => 'listener_id');
	$data['keys'][]    = array('name' => 'ex_addr', 'columns' => 'ex_addr');
	$data['keys'][]    = array('name' => 'start_time', 'columns' => 'start_time');
	$data['keys'][]    = array('name' => 'end_time', 'columns' => 'end_time');

	$data['type']       = 'InnoDB';
	$data['collate']    = 'latin1_swedish_ci';
	$data['charset']    = 'latin1';
	$data['row_format'] = 'Dynamic';
	$data['comment']    = 'Plugin Flowview - Details Report Data';

	flowview_db_table_create($table, $data);

	// Work around for unicode issues
	flowview_fix_collate_issues();
}

function flowview_fix_collate_issues() {
	global $config;

	return false;

	$tables = array_rekey(
		flowview_db_fetch_assoc('SELECT TABLE_NAME
			FROM information_schema.TABLES
			WHERE TABLE_NAME LIKE "plugin_flowview_raw%"
			AND TABLE_COLLATION != "utf8_unicode_ci"'),
		'TABLE_NAME', 'TABLE_NAME'
	);

	if (cacti_sizeof($tables)) {
		foreach($tables as $table) {
			flowview_db_execute("ALTER TABLE $table COLLATE=utf8mb4_unicode_ci");
		}
	}
}

function import_flows() {
	global $config;

	$flow_directory = read_config_option('path_flows_dir');
	$listeners      = flowview_db_fetch_assoc('SELECT * FROM plugin_flowview_devices');
	$last_date      = time();

	if (file_exists($flow_directory)) {
		foreach($listeners as $l) {
			$dir_iterator = new RecursiveDirectoryIterator($flow_directory . '/' . $l['folder']);
			$iterator = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);

			foreach($iterator as $file) {
				if (strpos($file, 'ft-') !== false) {
					$rfile = str_replace(rtrim($flow_directory, '/') . '/', '', $file);

					$parts = explode('/', $rfile);

					$listener_id = $l['id'];

					$start = microtime(true);
					print "Processing file: $rfile";
					flowview_load_flow_file_into_database($file, $listener_id);
					$end = microtime(true);
					print ', Total time ' . round($end - $start, 2) . PHP_EOL;
				}
			}
		}

		set_config_option('flowview_legacy_import_completed', 'true');
		set_config_option('flowview_last', $last_date);
	} else {
		print 'Flow directory does not exist.' . PHP_EOL;
	}
}

function flowview_load_flow_file_into_database($file, $listener_id) {
	$flow_export = read_config_option('path_flowtools') . '/flow-export';

	if (file_exists($flow_export)) {
		if (is_executable($flow_export)) {
			$data = shell_exec($flow_export . ' -f2 -mUNIX_SECS,UNIX_NSECS,SYSUPTIME,EXADDR,DFLOWS,DPKTS,DOCTETS,FIRST,LAST,ENGINE_TYPE,ENGINE_ID,SRCADDR,DSTADDR,NEXTHOP,INPUT,OUTPUT,SRCPORT,DSTPORT,PROT,TOS,TCP_FLAGS,SRC_MASK,DST_MASK,SRC_AS,DST_AS < ' . $file . ' 2> /dev/null');
		} else {
			cacti_log('Binary flow-export is not executable for import file ' . $file, false, 'FLOWVIEW');
		}
	} else {
		cacti_log('Can not find flow-export binary for import file ' . $file, false, 'FLOWVIEW');
	}

	$sql = array();

	if ($data != '') {
		$data = explode("\n", $data);

		$dflows_exist = false;
		$dflows_check = false;
		$flows = 0;
		$table_created = false;
		$i = 0;

		foreach($data as $row) {
			$row = trim($row);
			if ($row == '') {
				continue;
			} elseif (substr($row, 0, 1) == '#') {
				if (strpos($row, 'dflows') !== false) {
					$dflows_exist = true;
					$dflows_check = true;
				} else {
					$dflows_exist = false;
				}

				continue;
			}

			$cd = explode(',', $row);

			if (!$dflows_check && cacti_sizeof($cd) == 24) {
				$dflows_exists = true;
				$dflows_check = true;
			} else {
				$dflows_exists = false;
			}

			if ($dflows_exist) {
				list($unix_secs, $unix_nsecs, $sysuptime, $ex_addr, $flows, $packets, $bytes, $start_time, $end_time, $engine_type, $engine_id, $src_addr, $dst_addr, $nexthop, $src_if, $dst_if, $src_port, $dst_port, $protocol, $tos, $flags, $src_prefix, $dst_prefix, $src_as, $dst_as) = $cd;
			} else {
				$flows = 1;

				list($unix_secs, $unix_nsecs, $sysuptime, $ex_addr, $packets, $bytes, $start_time, $end_time, $engine_type, $engine_id, $src_addr, $dst_addr, $nexthop, $src_if, $dst_if, $src_port, $dst_port, $protocol, $tos, $flags, $src_prefix, $dst_prefix, $src_as, $dst_as) = $cd;
			}

			$cap_time = $unix_secs + ($unix_nsecs / 1000000);

			$rstime = ($start_time - $sysuptime) / 1000;
			$rsmsec = substr($start_time - $sysuptime, -3);
			$retime = ($end_time - $sysuptime) / 1000;
			$remsec = substr($end_time - $sysuptime, -3);

			$start_time = date('Y-m-d H:i:s', $cap_time + $rstime) . '.' . $rsmsec;
			$end_time   = date('Y-m-d H:i:s', $cap_time + $retime) . '.' . $remsec;

			if (!$table_created) {
				$partition = read_config_option('flowview_partition');

				if ($partition == 0) {
					$suffix = date('Y', $cap_time) . substr('000' . date('z', $cap_time), -3);
				} else {
					$suffix = date('Y', $cap_time) . substr('000' . date('z', $cap_time), -3) . date('H', $cap_time);
				}

				$table  = 'plugin_flowview_raw_' . $suffix;

				create_raw_partition($table);

				$table_created = true;

				$sql_prefix = 'INSERT IGNORE INTO ' . $table . ' (listener_id, engine_type, engine_id, ex_addr, sysuptime, src_addr, src_domain, src_rdomain, src_as, src_if, src_prefix, src_port, src_rport, dst_addr, dst_domain, dst_rdomain, dst_as, dst_if, dst_prefix, dst_port, dst_rport, nexthop, protocol, start_time, end_time, flows, packets, bytes, bytes_ppacket, tos, flags) VALUES ';
			}

			$src_domain  = flowview_get_dns_from_ip($src_addr, 100);
			$src_rdomain = flowview_get_rdomain_from_domain($src_domain, $src_addr);

			$dst_domain  = flowview_get_dns_from_ip($dst_addr, 100);
			$dst_rdomain = flowview_get_rdomain_from_domain($dst_domain, $dst_addr);

			$src_rport  = flowview_translate_port($src_port, false, false);
			$dst_rport  = flowview_translate_port($dst_port, false, false);

			$sql[] = '(' .
				$listener_id            . ', ' .
				$engine_type            . ', ' .
				$engine_id              . ', ' .
				db_qstr($ex_addr)       . ', ' .
				$sysuptime              . ', ' .

				'INET6_ATON("' . $src_addr . '")' . ', ' .
				db_qstr($src_domain)    . ', ' .
				db_qstr($src_rdomain)   . ', ' .
				$src_as                 . ', ' .
				$src_if                 . ', ' .
				$src_prefix             . ', ' .
				$src_port               . ', ' .
				db_qstr($src_rport)     . ', ' .

				'INET6_ATON("' . $dst_addr . '")' . ', ' .
				db_qstr($dst_domain)    . ', ' .
				db_qstr($dst_rdomain)   . ', ' .
				$dst_as                 . ', ' .
				$dst_if                 . ', ' .
				$dst_prefix             . ', ' .
				$dst_port               . ', ' .
				db_qstr($dst_rport)     . ', ' .

				db_qstr($nexthop)       . ', ' .
				$protocol               . ', ' .
				db_qstr($start_time)    . ', ' .
				db_qstr($end_time)      . ', ' .
				$flows                  . ', ' .
				$packets                . ', ' .
				$bytes                  . ', ' .
				round($bytes/$packets, 1) . ', ' .
				$tos . ', ' .
				$flags . ')';

			$i++;

			if ($i > 100) {
				flowview_db_execute($sql_prefix . implode(', ', $sql));
				$i = 0;
				$sql = array();
			}
		}

		if ($i > 0) {
			flowview_db_execute($sql_prefix . implode(', ', $sql));
		}
	}
}

function get_tables_range($begin, $end = null) {
	$tables    = array();
	$partition = read_config_option('flowview_partition');

	if ($end == null) {
		$end = time();
	}

	$current = $begin;

	while ($current < $end) {
		if ($partition == 0) {
			$suffix = date('Y', $current) . substr('000' . date('z', $current), -3);
			$current += 86400;
		} else {
			$suffix = date('Y', $current) . substr('000' . date('z', $current), -3) . date('H', $current);
			$current += 3600;
		}

		$table = 'plugin_flowview_raw_' . $suffix;

		if (!db_table_exists($table)) {
			create_raw_partition($table);
		}

		$tables[]  = $table;
	}

	return $tables;
}

