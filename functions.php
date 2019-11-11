<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2019 The Cacti Group                                 |
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

function flowview_display_report() {
	global $config, $graph_timeshifts, $graph_timespans;

	$sessionid = get_sessionid();

	if ($sessionid > 0) {
		$params = db_fetch_cell_prepared('SELECT params
			FROM plugin_flowview_session_cache
			WHERE id = ?', array($sessionid));

		$params = json_decode($params, true);

		foreach($params as $variable => $value) {
			switch ($variable) {
			case 'bytes':
			case 'flows':
			case 'packets':
				break;
			case 'exclude':
				if (isset_request_var('exclude')) {
					get_filter_request_var('exclude');
					break;
				} else {
					set_request_var($variable, $value);
					break;
				}
			default:
				set_request_var($variable, $value);
				break;
			}
		}
		set_request_var('action', 'view');
	} elseif (isset_request_var('query') && get_filter_request_var('query') > 0) {
		load_session_for_filter();
		$sessionid = -1;
	} else {
		load_session_for_page();
		$sessionid = -1;
	}

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$rname = '';
	if (get_request_var('statistics') > 0) {
		$rname = $stat_report_array[get_request_var('statistics')];
	}

	if (get_request_var('printed') > 0) {
		$rname = $print_report_array[get_request_var('printed')];
	}

	// Load session history
	flowview_report_session();

	$filter = createFilter($sessionid);

	if (isset_request_var('statistics') && get_request_var('statistics') > 0 && get_nfilter_request_var('statistics') != 99) {
		html_start_box(__('Report: %s', $rname, 'flowview'), '100%', '', '3', 'center', '');
		?>
		<tr class='even'>
			<td>
			<form id='view' name='view' action='flowview.php' method='post'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Filter', 'flowview');?>
						</td>
						<td>
							<select name='query' id='query'>
								<option value='-1'><?php print __('Select a Filter', 'flowview');?></option>
								<?php
								$queries = db_fetch_assoc('SELECT id, name
									FROM plugin_flowview_queries
									ORDER BY name');

								if (cacti_sizeof($queries)) {
									foreach($queries as $q) {
										print "<option value='" . $q['id'] . "'" . (get_request_var('query') == $q['id'] ? ' selected':'') . ">" . html_escape($q['name']) . "</option>";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Exclude', 'flowview');?>
						</td>
						<td>
							<select name='exclude' id='exclude'>
								<option value='0'<?php print (get_request_var('exclude') == 0 ? ' selected':'');?>><?php print __('None', 'flowview');?></option>
								<option value='1'<?php print (get_request_var('exclude') == 1 ? ' selected':'');?>><?php print __('Top Sample', 'flowview');?></option>
								<option value='2'<?php print (get_request_var('exclude') == 2 ? ' selected':'');?>><?php print __('Top 2 Samples', 'flowview');?></option>
								<option value='3'<?php print (get_request_var('exclude') == 3 ? ' selected':'');?>><?php print __('Top 3 Samples', 'flowview');?></option>
								<option value='4'<?php print (get_request_var('exclude') == 4 ? ' selected':'');?>><?php print __('Top 4 Samples', 'flowview');?></option>
								<option value='5'<?php print (get_request_var('exclude') == 5 ? ' selected':'');?>><?php print __('Top 5 Samples', 'flowview');?></option>
							</select>
						</td>
						<td class='nowrap'>
							<input type='checkbox' name='domains' id='domains' <?php print (get_request_var('domains') == 'true' ? 'checked':'');?>>
							<label for='domains'><?php print __('Domains Only', 'flowview');?></label>
						</td>
						<td>
							<span>
								<input type='button' id='go' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Apply Filter', 'flowview');?>'>
								<input type='button' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filter', 'flowview');?>'>
								<input type='button' id='edit' value='<?php print __esc('Edit', 'flowview');?>' title='<?php print __esc('Edit Current Filter', 'flowview');?>'>
							</span>
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
										print "<option value='$value'"; if (get_request_var('predefined_timespan') == $value) { print ' selected'; } print '>' . title_trim($graph_timespans[$value], 40) . "</option>\n";
									}
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('From', 'flowview');?>
						</td>
						<td>
							<input type='text' id='date1' size='15' value='<?php print get_request_var('date1');?>'>
						</td>
						<td>
							<i title='<?php print __esc('Start Date Selector', 'flowview');?>' class='calendar fa fa-calendar-alt' id='startDate'></i>
						</td>
						<td>
							<?php print __('To', 'flowview');?>
						</td>
						<td>
							<input type='text' id='date2' size='15' value='<?php print get_request_var('date2');?>'>
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
										print "<option value='$shift_value'"; if (get_request_var('predefined_timeshift') == $shift_value) { print ' selected'; } print '>' . title_trim($graph_timeshifts[$shift_value], 40) . '</option>';
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
							<input type='checkbox' name='table' id='table' <?php print (get_request_var('table') == 'true' ? 'checked':'');?>>
							<label for='table'><?php print __('Table', 'flowview');?></label>
						</td>
						<td class='nowrap'>
							<input type='checkbox' name='bytes' id='bytes' <?php print (get_request_var('bytes') == 'true' ? 'checked':'');?>>
							<label for='bytes'><?php print __('Bytes Bar', 'flowview');?></label>
						</td>
						<td class='nowrap'>
							<input type='checkbox' name='packets' id='packets' <?php print (get_request_var('packets') == 'true' ? 'checked':'');?>>
							<label for='packets'><?php print __('Packets Bar', 'flowview');?></label>
						</td>
						<td class='nowrap'>
							<input type='checkbox' name='flows' id='flows' <?php print (get_request_var('flows') == 'true' ? 'checked':'');?>>
							<label for='flows'><?php print __('Flows Bar', 'flowview');?></label>
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

		flowview_draw_table($filter, $rname);
		flowview_draw_chart('bytes', $rname);
		flowview_draw_chart('packets', $rname);
		flowview_draw_chart('flows', $rname);
	} elseif (get_request_var('statistics') == 99) {
		flowview_draw_statistics($filter, $rname);
	} elseif (isset_request_var('printed') && get_request_var('printed') > 0) {
		html_start_box(__('Report: %s', $rname, 'flowview'), '100%', '', '3', 'center', '');
		print $filter;
		html_end_box();
	}

	?>
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

		$('#domains, #query, #go, #exclude').unbind('change').change(function() {
			applyFilter();
		});

		$('#table').unbind('click').click(function() {
			updateSession();

			if (!$('#table').is(':checked')) {
				$('#flowcontent').hide();
			} else {
				$('#flowcontent').show();
			}
		});

		$('#clear').unbind('click').click(function() {
			clearFilter();
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

	function applyFilter() {
		loadPageNoHeader(urlPath+'plugins/flowview/flowview.php' +
			'?action=view' +
			'&domains='  + $('#domains').is(':checked') +
			'&timespan=' + $('#predefined_timespan').val() +
			'&date1='    + $('#date1').val() +
			'&date2='    + $('#date2').val() +
			'&query='    + $('#query').val() +
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

	bottom_footer();
}

function get_port_name($port_num, $port_proto) {
}

function plugin_flowview_run_schedule($id) {
	global $config;

	$schedule = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_schedules
		WHERE id = ?',
		array($id));

	$query    = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($schedule['savedquery']));

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
	set_request_var('query', $schedule['savedquery']);
	set_request_var('action', 'loadquery');

	$message  = "<body style='margin:10px;'>";
	$message .= "<style type='text/css'>\n";
	$message .= file_get_contents($config['base_path'] . '/include/themes/modern/main.css');
	$message .= '</style>';
	$sessionid = -1;
	$message .= createFilter($sessionid);
	$message .= '</body>';

	send_mail($schedule['email'], $from, $subject, $message, ' ', '', $fromname);
}

function purgeFlows() {
	$time = time();

	$expired = db_fetch_cell_prepared('SELECT GROUP_CONCAT(id)
		FROM plugin_flowview_session_cache
		WHERE user_id = ?
        AND UNIX_TIMESTAMP(last_updated) < ?',
        array($_SESSION['sess_user_id'], $time-900));

	if ($expired != '') {
		$each = explode(',', $expired);
		foreach($each as $id) {
			unset($_SESSION['flowview_flows'][$id]);
		}

		// Remove database session data
		db_execute("DELETE FROM plugin_flowview_session_cache
			WHERE id IN($expired)");

		db_execute("DELETE FROM plugin_flowview_session_cache_details
			WHERE cache_id IN($expired)");

		db_execute("DELETE FROM plugin_flowview_session_cache_flow_stats
			WHERE cache_id IN($expired)");
	}

	// Reset auto-increment if applicable
	$rows = db_fetch_cell('SELECT count(*) FROM plugin_flowview_session_cache');
	if ($rows == 0) {
		db_execute('ALTER IGNORE TABLE plugin_flowview_session_cache AUTO_INCREMENT = 1');
	}
}

/** creatfilter($sessionid)
 *
 *  This function creates the NetFlow Report for the UI.  It presents this in a table
 *  format and returns as a test string to the calling function.
 */
function createFilter() {
	global $config;

	$output    = '';
	$title     = '';
	$sql_where = '';
	$histogram = false;
	$time      = time();
	$start     = strtotime(get_request_var('date1'));
	$end       = strtotime(get_request_var('date2'));

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
	if (get_request_var('protocols') != '') {
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
	return run_flow_query($sql_where, $start, $end);
}

function get_numeric_filter($sql_where, $value, $column) {
	$values = array();
	$parts  = explode(',', $value);
	foreach($parts as $part) {
		$part = trim($part);

		if (is_numeric($part)) {
			$values[] = $part;
		}
	}

	return ($sql_where != '' ? ' AND ':'WHERE ') . '`' . $column . '` IN (' . implode(',', $values) . ')';
}

function get_ip_filter($sql_where, $value, $column) {
	$values = array();
	$parts  = explode(',', $value);

	foreach($parts as $part) {
		$part = trim($part);

		if (strpos('/', $part) !== false) {
		} else {
		}
	}

	return ($sql_where != '' ? ' AND ':'WHERE ') . '`' . $column . '` IN (' . implode(',', $values) . ')';
}

function get_date_filter($sql_where, $date1, $date2, $range_type) {
	switch($range_type) {
		case 1: // Any part in specified time span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .
				'(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '") OR
				(`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 2: // End Time in Specified Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 3: // Start Time in Specified Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . '(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		case 4: // Entirety in Specitifed Time Span
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') .
				'(`start_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '") AND
				(`end_time` BETWEEN "' . $date1 . '" AND "' . $date2 . '")';
			break;
		default:
			cacti_log('ERROR: get_date_filter range type not recognized', false, 'FLOWVIEW');
			break;
	}

	return $sql_where;
}

function run_flow_query($sql_query, $start, $end) {
	cacti_log($sql_query);
	return false;
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

function find_good_title($title) {
	$i = 1;
	$otitle = $title;

	while ($i < 40) {
		$title_exists = db_fetch_cell_prepared('SELECT COUNT(*)
			FROM plugin_flowview_session_cache
			WHERE title = ?
			AND user_id = ?
			AND sessionid = ?',
			array($title, $_SESSION['sess_user_id'], session_id()));

		if ($title_exists) {
			$title = $otitle . ' (' . $i . ')';
			$i++;
		} else {
			break;
		}
	}

	return $title;
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

function parsestatoutput($output, $title, $sessionid) {
	global $config;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (get_request_var('statistics') == 99) {
		return parseSummaryReport($output);
	} elseif (!isset($stat_columns_array[get_request_var('statistics')])) {
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";
	}

	$output = explode("\n", $output);

	ob_start();
	html_start_box(__('Table View for %s', $title), '100%', '', '3', 'center', '');
	$o  = ob_get_clean();

	$o .= '<tr><td><table id="sorttable" class="cactiTable">
			<thead>
				<tr class="tableHeader tableFixed">';

	$clines     = $stat_columns_array[get_request_var('statistics')][0];
	$octect_col = $stat_columns_array[get_request_var('statistics')][1];
	$proto_col  = $stat_columns_array[get_request_var('statistics')][3];
	$port_col   = $stat_columns_array[get_request_var('statistics')][4];

	$ip_col     = $stat_columns_array[get_request_var('statistics')][2];
	$ip_col     = explode(',',$ip_col);

	$columns    = $stat_columns_array[get_request_var('statistics')];

	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);

	$_SESSION['flowview_flows'][$sessionid]['columns'] = $columns;

	$x = 1;
	foreach ($columns as $column) {
		if (preg_match('/(Bytes)/i', $column)) {
			$o .= "<th class='ui-resizable subHeaderColumn {sorter: \"bytes\"} " . get_column_alignment($column) . "'>$column</th>";
		} elseif (preg_match('/(Flows|Packets)/i', $column)) {
			$o .= "<th class='ui-resizable subHeaderColumn sorter-digit " . get_column_alignment($column) . "'>$column</th>";
		} else {
			$o .= "<th class='ui-resizable subHeaderColumn " . get_column_alignment($column) . "'>$column</th>";
		}
		$x++;
	}
	$o .= '</tr></thead><tbody>';
	$cut = 1;

	$i = 0;

	if (isset_request_var('exclude') && get_request_var('exclude') > 0) {
		$j = get_filter_request_var('exclude');
	} else {
		$j = 0;
	}

	$r = 0;
	$data_array = array();
	foreach ($output as $out) {
		$out = trim($out);

		if (substr($out, 0, 1) != '#' && $out != '') {
			$out  = preg_split('/[\s]+/', trim($out));

			if ($octect_col == '' || get_request_var('cutoffoctets') == '' || $out[$octect_col] > get_request_var('cutoffoctets')-1) {
				/* remove outliers */
				if ($r < $j) {
					$r++;
					continue;
				}

				$o .= '<tr class="' . flowview_altrow($i) . '">';
				$c = 0;
				foreach ($out as $out2) {
					if ($out2 != '') {
						if (in_array($c, $ip_col)) {
							$out2 = flowview_get_dns_from_ip($out2, 100);
							$data_array[$i][$c] = $out2;
						} elseif ($c == $octect_col && $octect_col != '') {
							$data_array[$i][$c] = $out2;
							$out2 = plugin_flowview_formatoctet($out2);
						} elseif ($c == $port_col && $port_col != '') {
							$out2 = flowview_translate_port($out2, false, true);
							$data_array[$i][$c] = $out2;
						} elseif ($c == $proto_col && $proto_col != '') {
							$out2 = plugin_flowview_get_protocol($out2, 0);
							$data_array[$i][$c] = $out2;
						} else {
							$data_array[$i][$c] = $out2;
						}
						$o .= "<td class='" . get_column_alignment($columns[$c]) . "'>" . (get_column_alignment($columns[$c]) == 'right' ? (is_numeric($out2) ? number_format_i18n($out2):$out2):$out2) . '</td>';
						$c++;
					}
				}

				$o .= '</tr>';

				$cut++;
			}
		}

		if (get_request_var('cutofflines') < $cut) {
			break;
		}

		$i++;
	}

	$_SESSION['flowview_flows'][$sessionid]['data'] = $data_array;

	$o .= '</tbody></table></td></tr></table>';

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

function flowview_altrow($i) {
	if ($i/2 == intval($i/2)) {
		return 'even';
	} else {
		return 'odd';
	}
}


function parseprintoutput($report, $output, $title, $sessionid) {
	global $config;
	static $domains = array();

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (!isset($print_array[$report])) {
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";
	}

	$output = explode("\n", $output);

	$sql_prefix = 'INSERT INTO plugin_flowview_session_cache_details (cache_id, report_id, ' . $print_array[$report]['db_columns'] . ') VALUES ';

	$clines     = $print_array[$report]['clines'];
	$columns    = $print_array[$report]['spec'];

	if ($sessionid != -1) {
		$_SESSION['flowview_flows'][$sessionid]['columns'] = $columns;
	}

	$dns1 = read_config_option('settings_dns_primary');
	$dns2 = read_config_option('settings_dns_secondary');

	if (get_request_var('resolve') == 'Y') {
		$resolve = true;
	} else {
		$resolve = false;
	}

	$prefix     = '(' . $sessionid . ', ' . $report;
	$firstline  = true;
	$cfirst     = false;

	$sql = array();
	$i   = 0;

	foreach ($output as $out) {
		if ($clines > 1 && $out != '' && substr($out, 0, 1) != ' ') {
			$cfirst = true;
			$outf   = trim($out);
			continue;
		} elseif (trim($out) == '') {
			// Empty line
			continue;
		} elseif ($clines > 1 && $cfirst == true) {
			$out    = $outf . ' ' . trim($out);
			$cfirst = false;
		} else {
			$out = trim($out);
		}

		$time = time();

		if (substr($out, 0, 1) != '#' && $firstline == false) {
			$out = preg_split('/[\s]+/', $out);
			$str = $prefix;
			foreach($out as $index => $value) {
				switch($print_array[$report]['spec'][$index]['column']) {
					case 'SIf':
					case 'DIf':
						if ($print_array[$report]['if_hex']) {
							$str .= ', ' . hexdec($value);
						} else {
							$str .= ', ' . $value;
						}

						break;
					case 'srcIP':
					case 'dstIP':
						$parts = explode('/', $value);

						if (isset($parts[1])) {
							$str .= ', ' . $parts[1];
							$value = $parts[0];
						} else {
							$str .= ', 0';
						}
					case 'Source':
					case 'Destination':
					case 'SrcIPaddress':
					case 'DstIPaddress':
						$str .= ', ' . db_qstr($value);

						if ($resolve) {
							$domain = flowview_get_dns_from_ip($value, 100);

							$str .= ', ' . db_qstr($domain);

							if ($domain != '' && strpos($domain, '.') !== false) {
								$parts = explode('.', $domain);
								$size  = sizeof($parts);
								$str .= ', ' . db_qstr($parts[$size - 2] . '.' . $parts[$size - 1]);
							} else {
								$str .= ', ' . db_qstr('');
							}
						} else {
							$str .= ', ' . db_qstr('');
							$str .= ', ' . db_qstr('');
						}

						break;
					case 'SrcP':
					case 'DstP':
						if ($print_array[$report]['ports_hex']) {
							$str .= ', ' . hexdec($value);
							$str .= ', ' . db_qstr(flowview_translate_port($value, true, false));
						} else {
							$str .= ', ' . $value;
							$str .= ', ' . db_qstr(flowview_translate_port($value, false, false));
						}
						break;
					case 'Start':     // Start time of flow 0727.13:19:03.263
					case 'End':       // End time of flow   0727.13:19:03.263
					case 'StartTime': // Start time of flow 0727.13:19:03.263
					case 'EndTime':   // End time of flow   0727.13:19:03.263
						$parts = explode('.', $value);
						$month_day = date('md');

						$year = date('Y');

						if ($parts[0] > $month_day) {
							$year--;
						}

						$month = substr($parts[0], 0, 2);
						$day   = substr($parts[0], 2, 2);
						$hms   = $parts[1];
						$ms    = $parts[2];

						$date =
							$year   .  '-' .
							$month  .  '-' .
							$day    .  ' ' .
							$hms    .  '.' .
							$ms;

						$str .= ', ' . db_qstr($date);

						break;
					case 'Ts':        // Type of service
					case 'Fl':        // Flags
						$str .= ', ' . hexdec($value);
						break;
					case 'srcAS':
					case 'dstAS':
					case 'prot':
					case 'flows':
					case 'Pkts':
					case 'Octets':
					case 'Packets':
					case 'Bytes':
					case 'P':         // Protocol
					case 'Pr':        // Protocol
					case 'Active':    // Milliseconds active
					case 'B/Pk':      // Bytes per Packet
						$str .= ', ' . $value;
						break;
					default:
						$str .= ', ' . db_qstr($value);
						break;
				}
			}

			$str .= ')';

			$sql[] = $str;

			if ($i > 1000) {
				if (!db_execute($sql_prefix . implode(', ', $sql))) {
					cacti_log($sql_prefix . substr(implode(', ', $sql), 0, 1000));
				}

				$sql = array();
				$i = 0;
			} else {
				$i++;
			}
		}

		$firstline = false;
	}

	if ($i > 0) {
		db_execute($sql_prefix . implode(', ', $sql));
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

function flowview_create_flowview_filter() {
	global $config;

	$filter = "filter-definition FlowViewer_filter\n";

	if (get_request_var('sourceip') != '') {
       	$filter .= "  match ip-source-address source_address\n";
	}

	if (get_request_var('sourceinterface') != '') {
		$filter .= "  match input-interface source_if\n";
	}

	if (get_request_var('sourceport') != '') {
		$filter .= "  match ip-source-port source_port\n";
	}

	if (get_request_var('sourceas') != '') {
		$filter .= "  match source-as source_as\n";
	}

	if (get_request_var('destip') != '') {
       	$filter .= "  match ip-destination-address dest_address\n";
	}

	if (get_request_var('destinterface') != '') {
       	$filter .= "  match output-interface dest_if\n";
	}

	if (get_request_var('destport') != '') {
       	$filter .= "  match ip-destination-port dest_port\n";
	}

	if (get_request_var('destas') != '') {
       	$filter .= "  match destination-as destas\n";
	}

	if (get_request_var('protocols') != '') {
       	$filter .= "  match ip-protocol protocol\n";
	}

	if (get_request_var('tcpflags') != '') {
       	$filter .= "  match ip-tcp-flags tcp_flag\n";
	}

	if (get_request_var('tosfields') != '') {
       	$filter .= "  match ip-tos tos_field\n";
	}

	switch (get_request_var('includeif')) {
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
	$filter  = "filter-primitive start_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit ge " . date("F j, Y H:i:s ", strtotime($start)) . "\n";
	$filter .= "   default deny\n";
	$filter .= "filter-primitive end_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit lt " . date("F j, Y H:i:s ", strtotime($end)) . "\n";
	$filter .= "   default deny\n\n";
	return $filter;
}

function flowview_create_tos_field_filter ($tosfields) {
	if ($tosfields == '') {
		return '';
	}

	$filter  = "filter-primitive tos_field\n";
	$filter .= "   type ip-tos\n";
	$tosfields = str_replace(' ', '', $tosfields);
	$s_if = explode(',', $tosfields);
	$excluded = false;

	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
			$s = substr($s, 1);
			$s = explode('/', $s);

			if (isset($s[1])) {
				$filter .= "   mask " . $s[1] . "\n";
			}

			$filter .= "   deny " . $s[0] . "\n";
			$excluded = true;
		} else {
			$s = explode('/', $s);

			if (isset($s[1])) {
				$filter .= "   mask " . $s[1] . "\n";
			}

			$filter .= "   permit " . $s[0] . "\n";
		}
	}

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_tcp_flag_filter ($tcpflags) {
	if ($tcpflags == '') {
		return '';
	}

	$filter  = "filter-primitive tcp_flag\n";
	$filter .= "   type ip-tcp-flag\n";
	$tcpflags = str_replace(' ', '', $tcpflags);
	$s_if = explode(',', $tcpflags);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
			$s = substr($s, 1);
			$s = explode('/', $s);

			if (isset($s[1])) {
				$filter .= "   mask " . $s[1] . "\n";
			}

			$filter .= "   deny " . $s[0] . "\n";
			$excluded = true;
		} else {
			$s = explode('/', $s);
			if (isset($s[1]))
				$filter .= "   mask " . $s[1] . "\n";
			$filter .= "   permit " . $s[0] . "\n";
		}
	}

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_protocol_filter ($protocols) {
	if ($protocols == '') {
		return '';
	}

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

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_as_filter ($as, $type) {
	if ($as == '') {
		return '';
	}

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

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_port_filter ($port, $type) {
	if ($port == '') {
		return '';
	}

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

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_if_filter ($sourceinterface, $type) {
	if ($sourceinterface == '') {
		return '';
	}

	$filter  = "filter-primitive $type" . "_if\n";
	$filter .= "   type ifindex\n";
	$sourceinterface = str_replace(' ', '', $sourceinterface);
	$s_if = explode(',',$sourceinterface);
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

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

	return $filter;
}

function flowview_create_ip_filter ($sourceip, $type) {
	if ($sourceip == '') {
		return;
	}

	$filter  = "filter-primitive $type" . "_address\n";
	$filter .= "   type ip-address-prefix\n";
	$exclude = false;
	$s_a = explode(',', $sourceip);
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

	if ($excluded) {
		$filter .= "   default permit\n";
	} else {
		$filter .= "   default deny\n";
	}

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
		$y = date('Y', $start);
		$m = date('m', $start);
		$d = date('d', $start);
		$h = date('G', $start);
		$temppath = $dir;
		switch ($n) {
			case -2:
				$temppath .= "/$y-$m/$y-$m-$d";
				$start = $start + 86400;
				break;
			case -1:
				$temppath .= "/$y-$m-$d";
				$start = $start + 86400;
				break;
			case 0:
				$start = $start + 86400;
				break;
			case 1:
				$tempparth .= "/$y";
				$start = $start + 86400;
				break;
			case 2:
				$temppath .= "/$y/$y-$m";
				$start = $start + 86400;
				break;
			case -3:
			case 3:
				$temppath .= "/$y/$y-$m/$y-$m-$d";
				$start = $start + 86400;
				break;
			case 4:
				$temppath .= "/$y-$m-$d-$h";
				$start = $start + 3600;
				break;
		}

		if ($n != 0 && file_exists($temppath)) {
			$folderpath .= $temppath . ' ';
		}
	}
	return $folderpath;
}

function flowview_check_fields () {
	global $config;

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

function flowview_draw_table(&$output, $rname) {
	print "<div>";
	print "<div id='flowcontent' style='display:none'>";
	print $output;
	print "</div>";
	print "</div>";
}

function flowview_draw_statistics(&$output, $rname) {
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

/** flowview_viewtable()
 *
 *  This function is will print the stored table
 *  less any outliers back to the browser.
 */
function flowview_viewtable() {
	global $config;

	$sessionid  = get_sessionid();

	$data = db_fetch_row_prepared('SELECT *
		FROM plugin_flowview_session_cache
		WHERE id = ?
		AND user_id = ?',
		array($sessionid, $_SESSION['sess_user_id']));

	if (cacti_sizeof($data)) {
		$output = $data['data'];
		$title  = $data['title'];
		$params = json_decode($data['params'], true);

		foreach($params as $item => $value) {
			switch ($item) {
			case 'bytes':
			case 'flows':
			case 'packets':
				break;
			default:
				set_request_var($item, $value);
			}
		}
	}

	print parsestatoutput($output, $title, $sessionid);
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

	include($config['base_path'] . '/plugins/flowview/lib/open-flash-chart-object.php');
	include($config['base_path'] . '/plugins/flowview/lib/open-flash-chart.php');

	$sessionid  = get_sessionid();

	flowview_report_session();

	// Load up the data array
	$data       = createFilter($sessionid);

	$title      = get_nfilter_request_var('title');
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

