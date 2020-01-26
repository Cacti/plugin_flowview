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

function sort_filter() {
	global $config, $filter_edit, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (isset_request_var('printed') && get_filter_request_var('printed') > 0) {
		foreach($print_columns_array[get_request_var('printed')] as $key => $value) {
			print "<option value='$key'" . (get_request_var('printed') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
		}
	} elseif (get_filter_request_var('statistics') > 0) {
		foreach($stat_columns_array[get_request_var('statistics')] as $key => $value) {
			print "<option value='$key'" . (get_request_var('statistics') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
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
		$report = db_fetch_row_prepared('SELECT *
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

	form_save_button($page);

	?>
	<script type='text/javascript'>
	var date1Open = false;
	var date2Open = false;
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

		$('#statistics, #printed').change(function() {
			applyFilter();
		});

		$('#rtype').change(function() {
			changeRType();
			applyFilter();
		});

		changeRType();

		applyTimespan();
	});
	</script>
	<?php
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

	if (is_array(get_nfilter_request_var('protocols')) && sizeof(get_nfilter_request_var('protocols'))) {
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

	$id = sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message()) {
		raise_message(2);

		if (!isset_request_var('return')) {
			header('Location: flowview_filters.php?tab=sched&header=false&action=edit&id=' . (empty($id) ? get_filter_request_var('id') : $id));
		} else {
			header('Location: ' . html_escape(get_nfilter_request_var('return') . '?query=' . (empty($id) ? get_filter_request_var('id') : $id)));
		}
		exit;
	}

	raise_message(1);

	if (!isset_request_var('return')) {
		header('Location: flowview_filters.php?id=' . $id . '&header=false');
	} else {
		header('Location: ' . html_escape(get_nfilter_request_var('return') . '?query=' . (empty($id) ? get_filter_request_var('id') : $id)));
	}
	exit;
}

function flowview_delete_filter() {
	db_execute_prepared('DELETE FROM plugin_flowview_queries
		WHERE id = ?',
		array(get_filter_request_var('query')));

	db_execute_prepared('DELETE FROM plugin_flowview_schedules
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

function flowview_show_summary() {
print 'Puke';
}

function flowview_display_filter() {
	global $config, $graph_timeshifts, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$title  = __esc('Undefined Filter [ Select Filter to get Details ]', 'flowview');

	if (get_filter_request_var('query') > 0) {
		$row = db_fetch_row_prepared('SELECT name, statistics, printed
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		if (cacti_sizeof($row)) {
			if ($row['statistics'] > 0) {
				$title = __esc('Statistical Report: %s [ Including overrides as specififed below ]', $stat_report_array[$row['statistics']]);
			} elseif ($row['printed'] > 0) {
				$title = __esc('Printed Report: %s [ Including overrides as specififed below ]', $print_report_array[$row['statistics']]);
			}
		}
	} else {
		raise_message('flowmessage', __('Select a Filter to display data', 'flowview'), MESSAGE_LEVEL_INFO);
	}

	html_start_box($title, '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
		<form id='view' action='flowview.php' method='post'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Filter', 'flowview');?>
					</td>
					<td>
						<select id='query'>
							<option value='-1'><?php print __('Select a Filter', 'flowview');?></option>
							<?php
							$queries = db_fetch_assoc('SELECT id, name
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
						<?php print __('Exclude', 'flowview');?>
					</td>
					<td>
						<select id='exclude'>
							<option value='0'<?php print (get_request_var('exclude') == 0 ? ' selected':'');?>><?php print __('None', 'flowview');?></option>
							<option value='1'<?php print (get_request_var('exclude') == 1 ? ' selected':'');?>><?php print __('Top Sample', 'flowview');?></option>
							<option value='2'<?php print (get_request_var('exclude') == 2 ? ' selected':'');?>><?php print __('Top 2 Samples', 'flowview');?></option>
							<option value='3'<?php print (get_request_var('exclude') == 3 ? ' selected':'');?>><?php print __('Top 3 Samples', 'flowview');?></option>
							<option value='4'<?php print (get_request_var('exclude') == 4 ? ' selected':'');?>><?php print __('Top 4 Samples', 'flowview');?></option>
							<option value='5'<?php print (get_request_var('exclude') == 5 ? ' selected':'');?>><?php print __('Top 5 Samples', 'flowview');?></option>
						</select>
					</td>
					<td class='nowrap' title='<?php print __esc('Show only Domains on Charts Below');?>'>
						<input type='checkbox' id='domains' <?php print (get_request_var('domains') == 'true' ? 'checked':'');?>>
						<label for='domains'><?php print __('Domains Only', 'flowview');?></label>
					</td>
					<td>
						<span>
							<input type='button' id='go' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Apply Filter', 'flowview');?>'>
							<input type='button' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filter', 'flowview');?>'>
							<input type='button' id='edit' value='<?php print __esc('Edit', 'flowview');?>' title='<?php print __esc('Edit Current Filter', 'flowview');?>'>
							<input type='button' id='save' value='<?php print __esc('Save', 'flowview');?>' title='<?php print __esc('Save Overrides to Selected Filter', 'flowview');?>'>
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
						<select id='report' onChange='applyFilter(false)'>
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
						<select id='sortfield' onChange='applyFilter(false)'>
							<?php
							$columns[0] = __('Select a Filter First', 'flowview');

							if (trim(get_request_var('report'), 'sp') != '0') {
								if (substr(get_request_var('report'), 0, 1) == 's') {
									$columns = $stat_columns_array[trim(get_request_var('report'), 'sp')];
								} else {
									$columns = $print_columns_array[trim(get_request_var('report'), 'sp')];
								}
							} elseif (get_request_var('query') > 0) {
								$report = db_fetch_row_prepared('SELECT printed, statistics, sortfield
									FROM plugin_flowview_queries
									WHERE id = ?',
									array(get_request_var('query')));

								if (sizeof($report)) {
									if ($report['statistics'] > 0) {
										$columns = $stat_columns_array[$report['statistics']];
									} elseif ($report['printed'] > 0) {
										$columns = $print_columns_array[$report['printed']];
									}
								}
							}

							if (cacti_sizeof($columns)) {
								foreach($columns as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('sortvalue') == $value ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					<td>
						<?php print __('Lines', 'flowview');?>
					</td>
					<td>
						<select id='cutofflines' onChange='applyFilter(false)'>
							<?php
							if (cacti_sizeof($cutoff_lines)) {
								foreach($cutoff_lines as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('cutofflines') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Octets', 'flowview');?>
					</td>
					<td>
						<select id='cutoffoctets' onChange='applyFilter(false)'>
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
						<select id='predefined_timespan' onChange='applyTimespan()'>
							<?php
							if (isset_request_var('custom') && get_request_var('custom') == true) {
								$graph_timespans[GT_CUSTOM] = __('Custom', 'flowview');
								set_request_var('predefined_timespan', GT_CUSTOM);
								$start_val = 0;
								$end_val = sizeof($graph_timespans);
							} else {
								if (isset($graph_timespans[GT_CUSTOM])) {
									asort($graph_timespans);
									array_shift($graph_timespans);
								}
								$start_val = 1;
								$end_val = sizeof($graph_timespans)+1;
							}

							if (cacti_sizeof($graph_timespans)) {
								for ($value=$start_val; $value < $end_val; $value++) {
									print "<option value='$value'" . (get_request_var('predefined_timespan') == $value ? ' selected':'') . '>' . $graph_timespans[$value] . '</option>';
								}
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
							$end_val = sizeof($graph_timeshifts) + 1;
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
						<?php print __('Show/Hide', 'flowview');?>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='table' <?php print (get_request_var('table') == 'true' ? 'checked':'');?>>
						<label for='table'><?php print __('Table', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='bytes' <?php print (get_request_var('bytes') == 'true' ? 'checked':'');?>>
						<label for='bytes'><?php print __('Bytes Bar', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='packets' <?php print (get_request_var('packets') == 'true' ? 'checked':'');?>>
						<label for='packets'><?php print __('Packets Bar', 'flowview');?></label>
					</td>
					<td class='nowrap'>
						<input type='checkbox' id='flows' <?php print (get_request_var('flows') == 'true' ? 'checked':'');?>>
						<label for='flows'><?php print __('Flows Bar', 'flowview');?></label>
					</td>
				</tr>
			</table>
		</form>
		</td>
	</tr>
	<script type='text/javascript'>

	var height = $(window).height() - 200;
	var date1Open = false;
	var date2Open = false;

	if (height < 300 || height > 400) {
		height = 400;
	}

	$(function() {
		$('#bytes').unbind('click').click(function() {
			updateSession();

			if (!$('#bytes').is(':checked')) {
				$('#wrapperbytes').hide();
			} else {
				$('#wrapperbytes').show();
			}
		});

		$('#packets').unbind('click').click(function() {
			updateSession();

			if (!$('#packets').is(':checked')) {
				$('#wrapperpackets').hide();
			} else {
				$('#wrapperpackets').show();
			}
		});

		$('#flows').unbind('click').click(function() {
			updateSession();

			if (!$('#flows').is(':checked')) {
				$('#wrapperflows').hide();
			} else {
				$('#wrapperflows').show();
			}
		});

		$('#query').unbind('change').change(function() {
			applyFilter(true);
		});

		$('#domains, #exclude').unbind('change').change(function() {
			applyFilter(false);
		});

		$('#go').unbind('click').click(function() {
			applyFilter(false);
		});

		$('#clear').unbind('click').click(function() {
			clearFilter();
		});

		$('#edit').unbind('click').click(function() {
			strURL = urlPath + '/plugins/flowview/flowview_filters.php' +
				'?action=edit&header=false' +
				($('#query').val() > 0 ? '&id='+$('#query').val():'') +
				'&return=flowview.php';

			loadPageNoHeader(strURL);
		});

		$('#table').unbind('click').click(function() {
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

		applyTimespan();
	});

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

	function applyTimespan() {
		$.getJSON(urlPath + 'plugins/flowview/flowview.php' +
			'?action=gettimespan' +
			'&predefined_timespan='+$('#predefined_timespan').val(), function(data) {
			$('#date1').val(data['current_value_date1']);
			$('#date2').val(data['current_value_date2']);
		});
	}

	function applyFilter(reset) {
		if (reset) {
			var report = 0;
		} else {
			var report = $('#report').val();
		}

		loadPageNoHeader(urlPath+'plugins/flowview/flowview.php' +
			'?action=view'   +
			'&domains='      + $('#domains').is(':checked') +
			'&timespan='     + $('#predefined_timespan').val() +
			'&report='       + report +
			'&sortfield='    + $('#sortfield').val() +
			'&sortvalue='    + ($('#sortfield').val() != '' ? $('#sortfield option:selected').html():'Bytes') +
			'&cutofflines='  + $('#cutofflines').val() +
			'&cutoffoctets=' + $('#cutoffoctets').val() +
			'&exclude='      + $('#exclude').val() +
			'&date1='        + $('#date1').val() +
			'&date2='        + $('#date2').val() +
			'&query='        + $('#query').val() +
			'&header=false');
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
	<?php

	html_end_box();
}

function get_port_name($port_num, $port_proto) {
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
		$port_name = db_fetch_cell_prepared('SELECT service
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

	$schedule = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_schedules
		WHERE id = ?',
		array($id));

	$query = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($schedule['query_id']));

	$fromname = read_config_option('settings_from_name');
	if (strlen($fromname) < 1) {
		$fromname = __('Cacti Flowview', 'flowview');
	}

	$from = read_config_option('settings_from_email');
	if (strlen($from) < 1) {
		$from = 'cacti@cactiusers.org';
	}

	$subject = __('Netflow - %', $schedule['title'], 'flowview');

	set_request_var('schedule', $id);
	set_request_var('query', $schedule['query_id']);
	set_request_var('action', 'loadquery');

	$message  = "<body style='margin:10px;'>";
	$message .= "<style type='text/css'>\n";
	$message .= file_get_contents($config['base_path'] . '/include/themes/modern/main.css');
	$message .= '</style>';
	$sessionid = -1;

	$data = load_data_for_filter();
	if ($data !== false) {
		$message .= $data['table'];
	}

	$message .= '</body>';

	send_mail($schedule['email'], $from, $subject, $message, ' ', '', $fromname);
}

/** creatfilter($sessionid)
 *
 *  This function creates the NetFlow Report for the UI.  It presents this in a table
 *  format and returns as a test string to the calling function.
 */
function load_data_for_filter($session = false) {
	global $config;

	$output    = '';
	$title     = '';
	$sql_where = '';
	$histogram = false;
	$time      = time();
	$start     = strtotime(get_request_var('date1'));
	$end       = strtotime(get_request_var('date2'));

	if ($session && isset($_SESSION['sess_flowdata'])) {
		return $_SESSION['sess_flowdata'];
	}

	if (get_request_var('statistics') != 0) {
		$histogram = true;
	}

	/* source ip filter */
	if (get_request_var('sourceip') != '') {
		$sql_where = get_ip_filter($sql_where, get_request_var('sourceip'), 'src_addr');
	}

	/* source interface filter */
	if (get_request_var('sourceinterface') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('sourceinterface'), 'src_if');
	}

	/* source port filter */
	if (get_request_var('sourceport') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('sourceport'), 'src_port');
	}

	/* source as filter */
	if (get_request_var('sourceas') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('sourceas'), 'src_as');
	}

	/* destination ip filter */
	if (get_request_var('destip') != '') {
		$sql_where = get_ip_filter($sql_where, get_request_var('destip'), 'dest_sddr');
	}

	/* destination interface filter */
	if (get_request_var('destinterface') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('destinterface'), 'dest_if');
	}

	/* destination port filter */
	if (get_request_var('destport') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('destport'), 'dest_port');
	}

	/* destination as filter */
	if (get_request_var('destas') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('destas'), 'dest_as');
	}

	/* protocols filter */
	if (get_request_var('protocols') != '' && get_request_var('protocols') != '0') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('protocols'), 'protocol');
	}

	/* tcp flags filter */
	if (get_request_var('tcpflags') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('tcpflags'), 'flags');
	}

	/* tos filter */
	if (get_request_var('tosfields') != '') {
		$sql_where = get_numeric_filter($sql_where, get_request_var('tosfields'), 'tos');
	}

	/* date time range */
	$sql_where = get_date_filter($sql_where, get_request_var('date1'), get_request_var('date2'), get_request_var('includeif'));

	/* let's calculate the title and then session id */
	if ($title == '') {
		if (isset_request_var('query') && get_filter_request_var('query') > 0) {
			$title = db_fetch_cell('SELECT name FROM plugin_flowview_queries WHERE id=' . get_request_var('query'));
		} else {
			$title = __('New Flow', 'flowview');
		}
	}

	/* Run the query */
	$data = run_flow_query(get_request_var('query'), $title, $sql_where, $start, $end);

	$_SESSION['sess_flowdata'] = $data;

	return $data;
}

function get_numeric_filter($sql_where, $value, $column) {
	$values = array();

	if (is_array($value)) {
		$value = implode(',', $value);
	}

	if ($value != '') {
		$parts  = explode(',', $value);

		foreach($parts as $part) {
			$part = trim($part);

			if (is_numeric($part)) {
				$values[] = $part;
			}
		}

		return ($sql_where != '' ? ' AND ':'WHERE ') . '`' . $column . '` IN (' . implode(',', $values) . ')';
	}

	return $sql_where;
}

function get_ip_filter($sql_where, $value, $column) {
	if ($value != '') {
		$values = array();
		$parts  = explode(',', $value);

		foreach($parts as $part) {
			$part = trim($part);

			if (strpos('/', $part) !== false) {
			} else {
			}
		}

		if (sizeof($values)) {
			return ($sql_where != '' ? ' AND ':'WHERE ') . '`' . $column . '` IN (' . implode(',', $values) . ')';
		}
	}

	return $sql_where;
}

function get_date_filter($sql_where, $date1, $date2, $range_type) {
	switch($range_type) {
		case 1: // Any part in specified time span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .
				'(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '" OR
				`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 2: // End Time in Specified Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 3: // Start Time in Specified Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 4: // Entirety in Specitifed Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .
				'(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '" AND
				`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		default:
			break;
	}

	return $sql_where;
}

function get_tables_for_query($start, $end) {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$part_type  = read_config_option('flowview_partition');
	$inc_tables = array();

	if ($part_type == 0) {
		$start_part = date('Y', $start) . substr('000' . date('z', $start), -3) . '00';
		$end_part   = date('Y', $end)   . substr('000' . date('z', $end), -3)   . '00';
	} else {
		$start_part = date('Y', $start) . substr('000' . date('z', $start), -3) . date('H', $start);
		$end_part   = date('Y', $end)   . substr('000' . date('z', $end), -3)   . date('H', $end);
	}

	$tables = db_fetch_assoc('SELECT TABLE_NAME AS `table`
		FROM information_schema.TABLES
		WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"');

	if (sizeof($tables)) {
		foreach($tables as $t) {
			$parts = explode('_', $t['table']);
			$partition = trim($parts[3]);

			// Normalize the partition to hour zero
			if (strlen($partition) == '7') {
				$partition .= '00';
			}

			if ($partition >= $start_part && $partition <= $end_part) {
				$inc_tables[] = $t['table'];
			}
		}
	}

	return $inc_tables;
}

function run_flow_query($query_id, $title, $sql_where, $start, $end) {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$data = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($query_id));

	// Handle Limit Override
	if (isset_request_var('cutofflines') && get_request_var('cutofflines') != 999999) {
		$lines = get_request_var('cutofflines');
	} elseif ($data['cutofflines'] != 999999) {
		$lines = $data['cutofflines'];
	} else {
		$lines = 20;
	}

	if (get_request_var('exclude') > 0) {
		$sql_limit = 'LIMIT ' . get_request_var('exclude') .  ',' . $lines;
	} else {
		$sql_limit = 'LIMIT ' . $lines;
	}

	// Handle Octets Override
	if (isset_request_var('cutoffoctets') && get_request_var('cutoffoctets') > 0) {
		$sql_having = 'HAVING bytes > ' . get_request_var('cutoffoctets');
	} elseif ($data['cutoffoctets'] > 0) {
		$sql_having = 'HAVING bytes < ' . $data['cutoffoctets'];
	} else {
		$sql_having = '';
	}

	// Handle Report Override
	if (isset_request_var('report') && trim(get_nfilter_request_var('report'), 'sp') != 0) {
		if (substr(get_nfilter_request_var('report'), 0, 1) == 's') {
			$data['statistics'] = trim(get_nfilter_request_var('report'), 'sp');
			$data['printed']    = 0;
		} else {
			$data['printed']    = trim(get_nfilter_request_var('report'), 'sp');
			$data['statistics'] = 0;
		}
	}

	// Handle Sort Field Override
	if (isset_request_var('sortfield')) {
		$data['sortfield'] = get_request_var('sortfield');
	}

	$sql = '';

	if (cacti_sizeof($data)) {
		if ($data['statistics'] > 0) {
			switch($data['statistics']) {
				case 99:
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

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 8:
					$sql_query = 'SELECT INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, dst_domain';
					$sql_inner = 'SELECT dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY dst_addr';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 9:
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain';
					$sql_inner = 'SELECT src_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 0 ? ' DESC':' ASC');
					break;
				case 10:
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
				case 11:
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
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
		} else {
			switch($data['printed']) {
				case '1':
					$sql_query = 'SELECT src_if, INET6_NTOA(src_addr) AS src_addr, dst_if, INET6_NTOA(dst_addr) AS dst_addr, protocol, src_port, dst_port, tos, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_if, src_addr, dst_if, dst_addr, protocol, src_port, dst_port, tos, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY src_if, INET6_NTOA(src_addr), dst_if, INET6_NTOA(dst_addr), protocol, src_port, dst_port, tos, flags';
					$sql_inner_groupby = 'GROUP BY src_if, src_addr, dst_if, dst_addr, protocol, src_port, dst_port, tos, flags';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 8 ? ' DESC':' ASC');
					break;
				case '4':
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, protocol, src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, protocol, src_as, dst_as, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr), protocol, src_as, dst_as';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr, protocol, src_as, dst_as';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 4 ? ' DESC':' ASC');
					break;
				case '5':
					$sql_query = 'SELECT start_time, end_time, src_if, INET6_NTOA(src_addr) AS src_addr, src_port, dst_if, INET6_NTOA(dst_addr) AS dst_addr, dst_port, protocol, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT start_time, end_time, src_if, src_addr, src_port, dst_if, dst_addr, dst_port, protocol, flags, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY start_time, end_time, src_if, INET6_NTOA(src_addr), src_port, dst_if, INET6_NTOA(dst_addr), dst_port, protocol, flags';
					$sql_inner_groupby = 'GROUP BY start_time, end_time, src_if, src_addr, src_port, dst_if, dst_addr, dst_port, protocol, flags';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 9 ? ' DESC':' ASC');
					break;
				case '6':
					$sql_query = 'SELECT INET6_NTOA(src_addr) AS src_addr, INET6_NTOA(dst_addr) AS dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';
					$sql_inner = 'SELECT src_addr, dst_addr, SUM(flows) AS flows, SUM(bytes) AS bytes, SUM(packets) AS packets, src_domain, dst_domain';

					$sql_groupby       = 'GROUP BY INET6_NTOA(src_addr), INET6_NTOA(dst_addr)';
					$sql_inner_groupby = 'GROUP BY src_addr, dst_addr';

					$sql_order         = 'ORDER BY ' . ($data['sortfield'] + 1) . ($data['sortfield'] > 1 ? ' DESC':' ASC');
					break;
			}
		}

		$tables = get_tables_for_query($start, $end);

		$sql = '';

		if (sizeof($tables)) {
			foreach($tables as $t) {
				$sql .= ($sql != '' ? ' UNION ':'') . "$sql_inner FROM $t $sql_where $sql_inner_groupby";
			}
		}

		$sql = "$sql_query FROM ($sql) AS rs $sql_groupby $sql_having $sql_order $sql_limit";

		//cacti_log(str_replace("\n", " ", str_replace("\t", '', $sql)));

		$results = db_fetch_assoc($sql);

		$output = $data;
		$output['data'] = $results;

		$i = 0;
		$table = '';
		if (cacti_sizeof($results)) {
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
					$table .= '<td class="left">' . substr($r['start_time'], 0, 19) . '</td>';
				}

				if (isset($r['end_time'])) {
					$table .= '<td class="left">' . substr($r['end_time'], 0, 19) . '</td>';
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

				if (isset($r['src_addr'])) {
					$table .= '<td class="left">' . html_escape($r['src_addr']) . '</td>';
				}

				if (isset($r['dst_addr'])) {
					$table .= '<td class="left">' . html_escape($r['dst_addr']) . '</td>';
				}

				if (isset($r['src_port'])) {
					$table .= '<td class="left nowrap">' . get_port_name($r['src_port'], $r['protocol']) . '</td>';
				}

				if (isset($r['dst_port'])) {
					$table .= '<td class="left nowrap">' . get_port_name($r['dst_port'], $r['protocol']) . '</td>';
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

				$table .= '<td class="right">' . number_format_i18n($r['bytes']/$r['packets'], 0) . '</td>';

				$table .= '</tr>';

				$i++;
			}

			$table .= '</tbody></table>';
		}

		$output['table'] = $table;

		return $output;
	}

	return false;
}

function display_domain($domain) {
	if ($domain != '') {
		return $domain;
	} else {
		return __('-- unresolved --', 'flowview');
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
					$l = sizeof($parts);
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
					$l = sizeof($parts);
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
		$size  = sizeof($parts);
		$str .= $parts[$size - 2] . '.' . $parts[$size - 1];
	}

	return $str;
}

function flowview_translate_port($port, $is_hex, $detail = true) {
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

	$service = db_fetch_cell_prepared('SELECT service
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

function flowview_check_fields () {
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
	if ($output !== false) {
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
	print "<div id='chart$type'></div>";
	print "</td></tr>";
	html_end_box(false, true);
	print "</div>";

	$chartid++;
}

/*	flowview_get_dns_from_ip - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function flowview_get_dns_from_ip($ip, $timeout = 1000) {
	// First check to see if its in the cache
	$cache = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_dnscache
		WHERE ip = ?',
		array($ip));

	if (isset($cache['host'])) {
		return $cache['host'];
	}

	$dns = read_config_option('settings_dns_primary');

	$time = time();

	$slashpos = strpos($ip, '/');
	if ($slashpos) {
		$suffix = substr($ip, $slashpos);
		$ip = substr($ip, 0,$slashpos);
	} else {
		$suffix = '';
	}

	if (read_config_option('flowview_dns_method') == 1 && $dns != '') {
		/* random transaction number (for routers etc to get the reply back) */
		$data = rand(10, 99);

		/* trim it to 2 bytes */
		$data = substr($data, 0, 2);

		/* create request header */
		$data .= "\1\0\0\1\0\0\0\0\0\0";

		/* split IP into octets */
		$octets = explode('.', $ip);

		/* perform a quick error check */
		if (count($octets) != 4) {
			return 'ERROR';
		}

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
				return 'ERROR';
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

		if ($info['timed_out']) {
			return 'ip-' . str_replace('.', '-', $ip) . '.timeout.net';
		}

		/* more error handling */
		if ($response == '') {
			return 'ip-' . str_replace('.', '-', $ip) . '.error.net';
		}

		/* parse the response and find the response type */
		$type = @unpack('s', substr($response, $requestsize+2));

		if ($type[1] == 0x0C00) {
			/* set up our variables */
			$host = '';
			$len = 0;

			/* set our pointer at the beginning of the hostname uses the request
			   size from earlier rather than work it out.
			*/
			$position = $requestsize + 12;

			/* reconstruct the hostname */
			do {
				/* get segment size */
				$len = unpack('c', substr($response, $position));

				/* null terminated string, so length 0 = finished */
				if ($len[1] == 0) {
					$hostname = substr($host, 0, strlen($host) -1);

					/* return the hostname, without the trailing '.' */
					db_execute_prepared('INSERT INTO plugin_flowview_dnscache
						(ip, host, time)
						VALUES (?, ?, ?)',
						array($ip, $hostname, $time));

					return $hostname . $suffix;
				}

				/* add the next segment to our host */
				$host .= substr($response, $position+1, $len[1]) . '.';

				/* move pointer on to the next segment */
				$position += $len[1] + 1;
			} while ($len != 0);

			$dns_name = flowview_get_owner_from_arin($ip);

			if ($ip != $dns_name) {
				/* error - return the hostname we constructed (without the . on the end) */
				db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, time)
					VALUES (?, ?, ?)',
					array($ip, $dns_name, $time));

				return $ip . $suffix;
			} else {
				/* error - return the hostname we constructed (without the . on the end) */
				db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, time)
					VALUES (?, ?, ?)',
					array($ip, $ip, $time));

				return $ip . $suffix;
			}
		} else {
			$dns_name = flowview_get_owner_from_arin($ip);

			if ($ip != $dns_name) {
				/* error - return the hostname we constructed (without the . on the end) */
				db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, time)
					VALUES (?, ?, ?)',
					array($ip, $dns_name, $time));

				return $ip . $suffix;
			}
		}
	} else {
		$address = @gethostbyaddr($ip);
		$dns_name = $ip;

		if ($address !== false) {
			$dns_name = $address;
		}

		if ($dns_name != $ip) {
			db_execute_prepared('INSERT INTO plugin_flowview_dnscache
				(ip, host, time)
				VALUES (?, ?, ?)',
				array($ip, $dns_name, $time));

			return $dns_name . $suffix;
		} else {
			$dns_name = flowview_get_owner_from_arin($ip);

			if ($dns_name != $ip) {
				db_execute_prepared('INSERT INTO plugin_flowview_dnscache
					(ip, host, time)
					VALUES (?, ?, ?)',
					array($ip, $dns_name, $time));

				return $dns_name . $suffix;
			}
		}

	}

	/* error - return the hostname */
	db_execute_prepared('INSERT INTO plugin_flowview_dnscache
		(ip, host, time)
		VALUES (?, ?, ?)',
		array($ip, $ip, $time));

	return $ip . $suffix;
}

function flowview_get_color($as_array = false) {
	static $position = 0;
	$pallette = array('#F23C2E', '#32599A', '#F18A47', '#AC9509', '#DAAC10');

	if ($as_array) {
		$position = 0;
		return $pallette;
	} else {
		$color = $pallette[$position % sizeof($pallette)];
		$position++;
		return $color;
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

function get_sessionid() {
	if (isset_request_var('tab') && strpos(get_nfilter_request_var('tab'), 'tab_') !== false) {
		return str_replace('tab_', '', get_nfilter_request_var('tab'));
	}

	return -1;
}

/** flowview_viewchart()
 *
 *  This function is taken from Slowlog.  Given
 *  a title, chart type and chart data, it will
 *  print the required syntax for the Callback
 *  from the chart page to operate corectly.
 */
function flowview_viewchart() {
	global $config;

	// Load up the data array
	if (isset($_SESSION['sess_flowdata'])) {
		$data = $_SESSION['sess_flowdata'];
	} else {
		$data = array();
	}

	$chart_type = 'bar';
	$column     = get_nfilter_request_var('type');

	switch($column) {
	case 'flows':
		$unit = ucfirst($column);
		$suffix = __('Total Flows', 'flowview');

		break;
	case 'bytes':
		$unit = ucfirst($column);
		$suffix = __('Bytes Exchanged', 'flowview');
		break;
	case 'packets':
		$unit = ucfirst($column);
		$suffix = __('Packets Examined', 'flowview');
		break;
	}

	$columns = $_SESSION['flowview_flows'][$sessionid]['columns'];
	$data    = $_SESSION['flowview_flows'][$sessionid]['data'];

	foreach ($columns as $key => $cdata) {
		if (strtolower($cdata) == $column) {
			$column = $key;
		}
	}

	if (cacti_sizeof($data)) {
		$elements = array();
		$legend   = array();
		$maxvalue = 0;

		if (isset_request_var('exclude') && get_filter_request_var('exclude') > 0) {
			for($i = 0; $i < get_request_var('exclude'); $i++) {
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
			$elements[$i]->set_tooltip($unit . ': #val# ' . $autorange[1]);
			if (cacti_sizeof($row) == 4) {
				$legend[] = flowview_get_domain($row[0], get_request_var('domains'));
			} else {
				$legend[] = flowview_get_domain($row[0], get_request_var('domains')) . " -\n" . flowview_get_domain($row[1], get_request_var('domains'));
			}
			$i++;
		}

		$bar = new bar_glass();
		$bar->set_values($elements);

		$title = new title($title . ' (' . $suffix . ')');
		$title->set_style('{font-size: 18px; color: #444444; text-align: center;}');

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
		$y_axis->set_label_text('#val# ' . $autorange[1]);

		$chart = new open_flash_chart();
		$chart->set_title($title);
		$chart->add_element($bar);
		$chart->set_x_axis($x_axis);
		$chart->add_y_axis($y_axis);
		$chart->set_bg_colour('#F0F0F0');
		print $chart->toString();
	}
}

function flowview_get_owner_from_arin($host) {
	static $curlgood = true;

	$parts = explode('.', $host);

	if ($parts[0] == '172') {
		if ($parts[1] >= 16 && $parts[1] <= 31) {
			return 'ip-' . str_replace('.', '-', $host) . '.private.net';
		}
	} elseif ($parts[0] == '192') {
		return 'ip-' . str_replace('.', '-', $host) . '.private.net';
	} elseif ($parts[0] == '10') {
		return 'ip-' . str_replace('.', '-', $host) . '.private.net';
	} elseif ($curlgood == false) {
		return 'ip-' . str_replace('.', '-', $host) . '.unknown.net';
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'http://whois.arin.net/rest/ip/' . $host);
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
		return $host;
	} else {
		$json = json_decode($response, true);
		return 'ip-' . str_replace('.', '-', $host) . '.' . strtolower($json['net']['name']['$']) . '.net';
	}
}

function flowview_get_domain($host, $domain = 'false') {
	if ($domain == 'false') {
		return $host;
	} elseif (is_ipaddress($host)) {
		return $host;
	} else {
		$parts = explode('.', $host);
		$size  = sizeof($parts);
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
	$data = array();
	// Auto increment sequence
	$data['columns'][] = array('name' => 'sequence', 'type' => 'bigint(20)', 'unsigned' => true, 'auto_increment' => true);

	// Listener information
	$data['columns'][] = array('name' => 'listener_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);

	// Engine Information
	$data['columns'][] = array('name' => 'engine_type', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'engine_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sampling_interval', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');

	// Router information
	$data['columns'][] = array('name' => 'ex_addr', 'type' => 'varbinary(16)', 'NULL' => false, 'default' => '');
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

	// Generic Infromation for Combo Reports
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
	$data['unique_keys'][]    = array('name' => 'keycol', 'columns' => 'listener_id`,`src_addr`,`src_port`,`dst_addr`,`dst_port', 'unique' => true);
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Plugin Flowview - Details Report Data';
	api_plugin_db_table_create('flowview', $table, $data);
}

function import_flows() {
	$flow_directory = read_config_option('path_flows_dir');
	$listeners      = db_fetch_assoc('SELECT * FROM plugin_flowview_devices');
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

			if (!$dflows_check && sizeof($cd) == 24) {
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
				db_execute($sql_prefix . implode(', ', $sql));
				$i = 0;
				$sql = array();
			}
		}

		if ($i > 0) {
			db_execute($sql_prefix . implode(', ', $sql));
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

		$tables[]  = 'plugin_flowview_raw_' . $suffix;
	}

	return $tables;
}

