<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

set_default_action();

flowview_request_vars();

ini_set('max_execution_time', 240);
ini_set('memory_limit', '512M');

switch(get_request_var('action')) {
case 'save':
	flowview_save_filter();
	break;
case 'delete':
	flowview_delete_filter();
	break;
case 'killsession':
	flowview_delete_session();
	break;
case 'chartdata':
	flowview_viewchart();
	break;
case 'tabledata':
	flowview_viewtable();
	break;
case 'updatesess':
	flowview_updatesess();
	break;
case 'gettimespan':
	flowview_gettimespan();
	break;
case 'view':
	flowview_display_report();
	break;
default:
	general_header();

	flowview_display_form();

	bottom_footer();
}

function flowview_gettimespan() {
	global $config;

	include_once($config['base_path'] . '/lib/time.php');

	$timespan = get_filter_request_var('timespan');
	$date1    = get_nfilter_request_var('date1');
	$date2    = get_nfilter_request_var('date2');
	$span     = array();

	if ($timespan > 0) {
		get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
	}else{
		$span['current_value_date1'] = $date1;
		$span['current_value_date2'] = $date2;
		$span['begin_now']           = strtotime($date1);
		$span['end_now']             = strtotime($date2);
	}

	print json_encode($span);
}

function flowview_delete_filter() {
	db_execute_prepared('DELETE FROM plugin_flowview_queries
		WHERE id = ?',
		array(get_filter_request_var('query')));

	db_execute_prepared('DELETE FROM plugin_flowview_schedules
		WHERE savedquery = ?',
		array(get_filter_request_var('query')));

	raise_message('flow_deleted');

	header('Location: flowview.php?tab=filters&header=false');
	exit;
}

function flowview_delete_session() {
	if (isset($_SESSION['flowview_flows'][get_request_var('session')])) {
		unset($_SESSION['flowview_flows'][get_request_var('session')]);
	}

	header('Location: flowview.php?tab=filters');
	exit;
}

function flowview_save_filter() {
	if (isset_request_var('new_query') && get_nfilter_request_var('new_query') != '') {
		$queryname    = get_nfilter_request_var('new_query');
		$save['id']   = '';
		$save['name'] = form_input_validate($queryname, 'queryname', '', false, 3);
	}else{
		$save['id']          = get_filter_request_var('query');
	}

	$save['device']          = get_nfilter_request_var('device_name');
	$save['timespan']        = get_nfilter_request_var('predefined_timespan');
	$save['startdate']       = get_nfilter_request_var('date1');
	$save['enddate']         = get_nfilter_request_var('date2');
	$save['tosfields']       = get_nfilter_request_var('tos_fields');
	$save['tcpflags']        = get_nfilter_request_var('tcp_flags');
	$save['protocols']       = get_nfilter_request_var('protocols');
	$save['sourceip']        = get_nfilter_request_var('source_address');
	$save['sourceport']      = get_nfilter_request_var('source_port');
	$save['sourceinterface'] = get_nfilter_request_var('source_if');
	$save['sourceas']        = get_nfilter_request_var('source_as');
	$save['destip']          = get_nfilter_request_var('dest_address');
	$save['destport']        = get_nfilter_request_var('dest_port');
	$save['destinterface']   = get_nfilter_request_var('desc_if');
	$save['destas']          = get_nfilter_request_var('desc_as');
	$save['statistics']      = get_nfilter_request_var('stat_report');
	$save['printed']         = get_nfilter_request_var('print_report');
	$save['includeif']       = get_nfilter_request_var('flow_select');
	$save['sortfield']       = get_nfilter_request_var('sort_field');
	$save['cutofflines']     = get_nfilter_request_var('cutoff_lines');
	$save['cutoffoctets']    = get_nfilter_request_var('cutoff_octets');
	$save['resolve']         = get_nfilter_request_var('resolve_addresses');

	$id = sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message() || $id == '') {
		print 'error';
	}else{
		print $id;
	}
}

function flowview_request_vars() {
	if (isset_request_var('stat_report') && get_filter_request_var('stat_report') > 0) {
		set_request_var('print_report', 0);
	} elseif (isset_request_var('print_report') && get_filter_request_var('print_report') > 0) {
		set_request_var('stat_report', 0);
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
		'flow_select' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'stat_report' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '10'
			),
		'print_report' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'sort_field' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '3'
			),
		'cutoff_lines' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
			),
		'cutoff_octets' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'device_name' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => '0'
			),
		'predefined_timespan' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('default_timespan')
			),
		'date1' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'date2' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'protocols' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '6',
			),
		'flow_select' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'tcp_flags' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'tos_fields' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'source_address' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'source_port' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'source_if' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'source_as' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'desc_address' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'dest_port' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'dest_if' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'dest_as' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'resolve_addresses' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(Y|N)')),
			'default' => 'true'
			)
	);

	validate_store_request_vars($filters, 'sess_flowv');
	/* ================= input validation ================= */
}

function flowview_display_form() {
	global $config, $graph_timespans;

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	display_tabs();

	form_start('flowview.php', 'flowview');

	html_start_box(__('Flow Filter Constraints', 'flowview') . '  <span id="text"></span>', '100%', '', '3', 'center', '');

	?>
	<tr class='even center'>
		<td style='text-align:center'>
			<table class='filterTable' width='100%'>
				<tr>
					<td>
						<?php print __('Filter', 'flowview');?>
					</td>
					<td>
						<?php draw_edit_control('query', $query_name_field);?>
					</td>
					<td>
						<?php print __('Listener', 'flowview');?>
					</td>
					<td>
						<?php draw_edit_control('device_name', $device_name_field);?>
					</td>
				</tr>
				<tr>
					<td>
                        <?php print __('Presets', 'flowview');?>
					</td>
					<td>
						<select id='predefined_timespan' name='predefined_timespan' onChange='applyTimespan()'>
							<?php
							$graph_timespans[GT_CUSTOM] = __('Custom', 'flowview');
							$start_val = 0;
							$end_val = sizeof($graph_timespans);

							if (sizeof($graph_timespans)) {
								for ($value=$start_val; $value < $end_val; $value++) {
									print "<option value='$value'"; if ($timespan == $value) { print ' selected'; } print '>' . title_trim($graph_timespans[$value], 40) . "</option>\n";
								}
							}
							?>
						</select>
					</td>

					<td>
						<?php print __('Start Date', 'flowview');?>
					</td>
					<td class='nowrap'>
						<input type='text' size='15' id='date1' value='<?php echo $date1; ?>'>
						<i id='startDate' class='calendar fa fa-calendar' title='<?php print __esc('Start Date Selector', 'flowview');?>'></i>
					</td>
					<td>
						<?php print __('End Date', 'flowview');?>
					</td>
					<td>
						<input type='text' size='15' id='date2' value='<?php echo $date2;?>'>
						<i id='endDate' class='calendar fa fa-calendar' title='<?php print __esc('End Date Selector', 'flowview');?>'></i>
					</td>
				</tr>
				<tr>
					<td colspan='9'><hr size='2'></td>
				</tr>
				<tr>
					<td>
						<?php print __('Protocols', 'flowview');?>
					</td>
					<td>
						<?php draw_edit_control('protocols', $ip_protocol_field);?>
					</td>
					<td>
						<?php print __('TCP Flags', 'flowview');?>
					</td>
					<td>
						<input type='text' size='10' name='tcp_flags' value='<?php echo $tcp_flags;?>'>
					</td>
					<td>
						<?php print __('TOS Fields', 'flowview');?>
					</td>
					<td>
						<input type='text' size='10' name='tos_fields' value='<?php echo $tos_fields;?>'>
					</td>
					<td colspan=2>
						<?php print __('(e.g., -0x0b/0x0F)', 'flowview');?>
					</td>
				</tr>
				<tr>
					<td>
						<?php print __('Source IP', 'flowview');?>
					</td>
					<td>
						<input type='text' size='19' name='source_address' value='<?php echo $source_address;?>'>
					</td>
					<td>
						<?php print __('Source Port(s)', 'flowview');?>
					</td>
					<td>
						<input type='text' size='20' name='source_port' value='<?php echo $source_port;?>'>
					</td>
					<td>
						<?php print __('Source Interface', 'flowview');?>
					</td>
					<td>
						<input type='text' size='2' name='source_if' value='<?php echo $source_if;?>'>
					</td>
					<td>
						<?php print __('Source AS', 'flowview');?>
					</td>
					<td>
						<input type='text' size='6' name='source_as' value='<?php echo $source_as;?>'>
					</td>
				</tr>
				<tr>
					<td>
						<?php print __('Dest IP', 'flowview');?>
					</td>
					<td>
						<input type='text' size='19' name='dest_address' value='<?php echo $dest_address; ?>'></td>
					<td>
						<?php print __('Dest Port(s)', 'flowview');?>
					</td>
					<td>
						<input type='text' size='20' name='dest_port' value='<?php echo $dest_port; ?>'>
					</td>
					<td>
						<?php print __('Dest Interface', 'flowview');?>
					</td>
					<td>
						<input type='text' size='2' name='dest_if' value='<?php echo $dest_if; ?>'>
					</td>
					<td>
						<?php print __('Dest AS', 'flowview');?>
					</td>
					<td>
						<input type='text' size='6' name='dest_as' value='<?php echo $dest_as; ?>'>
						<input type='hidden' name='header' value='false'>
					</td>
				</tr>
				<tr>
					<td colspan='9'>
						<hr size='2'>
						<center><strong><?php print __('Note:', 'flowview');?></strong><?php print __(' Multiple field entries, separated by commas, are permitted in the fields above. A minus sign (-) will negate an entry (e.g. -80 for Port, would mean any Port but 80)', 'flowview');?></center>
						<hr size='2'>
					</td>
				</tr>
			</table>
		</td>
	</tr>
	<?php html_end_box(false);?>

	<?php html_start_box(__('Report Parameters', 'flowview'), '100%', '', '3', 'center', '');?>
	<tr class='even'>
		<td>
			<table class='filterTable'>
				<tr id='rsettings'>
					<td><?php print __('Statistics:', 'flowview');?></td>
					<td><?php draw_edit_control('stat_report', $stat_report_field);?></td>
					<td><?php print __('Printed:', 'flowview');?></td>
					<td><?php draw_edit_control('print_report', $print_report_field);?></td>
					<td><?php print __('Include if:', 'flowview');?></td>
					<td><?php draw_edit_control('flow_select', $flow_select_field);?></td>
					<td><?php print __('Resolve Addresses:', 'flowview');?></td>
					<td><?php draw_edit_control('resolve_addresses', $resolve_addresses_field);?></td>
				</tr>
				<tr id='rlimits'>
					<td class='sortfield'><?php print __('Sort Field:', 'flowview');?></td>
					<td class='sortfield'><select id='sort_field' name='sort_field'></select></td>
					<td><?php print __('Max Flows:', 'flowview');?></td>
					<td><?php draw_edit_control('cutoff_lines', $cutoff_lines_field);?></td>
					<td><?php print __('Minimum Bytes:', 'flowview');?></td>
					<td><?php draw_edit_control('cutoff_octets', $cutoff_octets_field);?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td colspan='9'><hr size='2'></td>
	</tr>
	<tr>
		<td colspan='9'>
			<input type='hidden' id='action' name='action' value='view'>
			<input type='hidden' id='new_query' name='new_query' value=''>
			<input type='hidden' id='changed' name='changed' value='0'>
			<center>
				<input id='view' type='button' name='view' value='<?php print __('View', 'flowview');?>'>
				<input id='defaults' type='button' value='<?php print __('Defaults', 'flowview');?>'>
				<input id='save' type='button' name='save' value='<?php print __('Save', 'flowview');?>'>
				<input id='saveas' type='button' name='saveas' value='<?php print __('Save As', 'flowview');?>'>
				<input id='delete' type='button' name='delete' value='<?php print __('Delete', 'flowview');?>'>
			</center>
		</td>
	</tr>
	<tr style='display:none;'>
		<td>
			<div id='fdialog' style='text-align:center;display:none;padding:2px;'>
				<table>
					<tr>
						<td style='padding:3px;' class='nowrap'><?php print __('Filter Name', 'flowview');?></td>
						<td style='padding:3px;'><input type='text' size='40' name='squery' id='squery' value='<?php print __esc('New Query', 'flowview');?>'></td>
					</tr>
					<tr style='padding:3px;'>
						<td style='padding:3px;' colspan='2' class='right'>
							<input id='qcancel' type='button' value='<?php print __esc('Cancel', 'flowview');?>'>
							<input id='qsave' type='button' value='<?php print __esc('Save', 'flowview');?>'>
						</td>
					</tr>
				</table>
			</div>
		</td>
	</tr>
	<?php

	html_end_box();

	form_end();

	?>
	<script type='text/javascript'>

	var date1Open = false;
	var date2Open = false;

	function applyTimespan() {
		$.getJSON('flowview.php?action=gettimespan&timespan='+$('#predefined_timespan').val(), function(data) {
			$('#date1').val(data['current_value_date1']);
			$('#date2').val(data['current_value_date2']);
		});
	}

	function applyFilter() {
		loadPageNoHeader('flowview.php?action=loadquery&tab=filters&header=false&query='+$('#query').val());
	}

	function statSelect() {
		statval = $('#stat_report').val();
		setStatOption(statval);

		if (statval > 0) {
			$('#print_report').attr('value', 0);
			$('#print_report').prop('disabled', true);
			$('#rlimits').children('.sortfield').show();
		}else{
			$('#print_report').prop('disabled', false);
		}

		if (statval == 99 || statval < 1) {
			$('#rlimits').hide();
		} else {
			$('#rlimits').show();
		}

		if (statval == 0 && $('#print_report').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#saveas').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#saveas').prop('disabled', false);
		}

		$('#print_report').selectmenu('refresh', true);
	}

	function printSelect() {
		statval = $('#print_report').val();

		if (statval > 0) {
			$('#stat_report').attr('value',0);
			$('#stat_report').prop('disabled', false);
			$('#sort_field').prop('disabled', false);
			$('#rlimits').hide();
			$('#rlimits').children('.sortfield').hide();
		} else {
			$('#rlimits').show();
			$('#cutoff_lines').prop('disabled', false);
			$('#cutoff_octets').prop('disabled', false);

			if ($('#stat_report').val() == 0) {
				$('#stat_report').attr('value', 10);
			}

			$('#stat_report').prop('disabled', false);
			statSelect();
			return;
		}
		if (statval == 4 || statval == 5) {
			$('#cutoff_lines').prop('disabled', false);
			$('#cutoff_octets').prop('disabled', false);
			$('#rlimits').show();
		} else {
			$('#cutoff_lines').prop('disabled', true);
			$('#cutoff_octets').prop('disabled', true);
			$('#rlimits').hide();
		}

		if (statval == 0 && $('#stat_report').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#saveas').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#saveas').prop('disabled', false);
		}

		$('#stat_report').selectmenu('refresh', true);
	}

	$('#device_name').change(function () {
		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($(this).val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
		}
		<?php }else{ ?>
		if ($(this).val() == 0) {
			$('#view').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
		}
		<?php } ?>
	});

	$('#date1, #date2').change(function() {
		if ($('#predefined_timespan option').length == 28) {
			$('#predefined_timespan').prepend("<option value='0' selected='selected'><?php print __('Custom', 'flowview');?></option>");
			$('#predefined_timespan').val('0');
			<?php if (get_selected_theme() != 'classic') {?>
			$('#predefined_timespan').selectmenu('refresh');
			<?php }?>
		}
	});

	$(function() {
		$('#startDate').click(function() {
			if (date1Open) {
				date1Open = false;
				$('#date1').datetimepicker('hide');
			}else{
				date1Open = true;
				$('#date1').datetimepicker('show');
			}
		});

		$('#endDate').click(function() {
			if (date2Open) {
				date2Open = false;
				$('#date2').datetimepicker('hide');
			}else{
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

		$('#saveas').hide();

		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($('#query').val() == 0) {
			$('#delete').hide();
		}else{
			$('#save').attr('value', '<?php print __('Update', 'flowview');?>');
			$('#saveas').show();
		}
		<?php }else{ ?>
		$('#delete').hide();
		$('#save').hide();
		<?php } ?>

		$('#flowview').change(function() {
			$('#changed').attr('value', '1');
		});

		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($('#device_name').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
		}
		<?php }else{ ?>
		if ($('#device_name').val() == 0) {
			$('#view').prop('disabled', true);
		}else{
			$('#view').prop('disabled', false);
		}
		<?php } ?>

		$('#stat_report').change(function() {
			statSelect();
			$('#print_report').selectmenu('refresh');
		});

		$('#print_report').change(function() {
			printSelect();
			$('#stat_report').selectmenu('refresh');
		});

		statSelect();
		printSelect();

		$('#fdialog').dialog({
			autoOpen: false,
			width: 400,
			height: 120,
			resizable: false,
			modal: true
		});
	});

	$('#view').click(function() {
		$('#action').attr('value', 'view');
		$.post('flowview.php', $('input, select, textarea').serialize(), function(data) {
			$('#main').html(data);
			applySkin();
		});
	});

	$('#saveas').click(function() {
		$('#squery').attr('value', $('#query>option:selected').text()+' (New)');
		$('#fdialog').dialog('open');
		$('#qcancel').click(function() {
			$('#fdialog').dialog('close');
		});
		$('#qsave').click(function() {
			$('#new_query').attr('value', $('#squery').val());
			$('#action').attr('value', 'save');
			$.post('flowview.php', $('#flowview').serialize(), function(data) {
				if (data!='error') {
					$('#text').show().text('<?php print __('Filter Saved', 'flowview');?>').fadeOut(2000);
					$('#query').append("<option value='"+data+"'>"+$('#new_query').val()+"</option>");
					$('#query').attr('value', data);
				}
			});
			$('#fdialog').dialog('close');
		});
	});

	$('#save').click(function() {
		if ($('#query').val() == 0) {
			$('#fdialog').dialog('open');
			$('#qcancel').click(function() {
				$('#fdialog').dialog('close');
			});
			$('#qsave').click(function() {
				$('#new_query').attr('value', $('#squery').val());
				$('#action').attr('value', 'save');
				$.post('flowview.php', $('#flowview').serialize(), function(data) {
					if (data!='error') {
						loadPageNoHeader('flowview.php?tab=filters&header=false&action=loadquery&query='+data);
						$('#text').show().text('<?php print __('Filter Settings Saved');?>').fadeOut(2000);
					}
				});
				$('#fdialog').dialog('close');
			});
		}else{
			$('#action').attr('value', 'save');
			$.post('flowview.php', $('#flowview').serialize(), function(data) {
				$('#text').show().text('<?php print __('Filter Updated', 'flowview');?>').fadeOut(2000);
			});
		}
	});

	$('#delete').click(function() {
		loadPageNoHeader('flowview.php?header=false&action=delete&query='+$('#query').val());
	});

	$('#defaults').click(function() {
		setDefaults();
	});

	function setDefaults() {
		// Flow Filter Settings
		$('#device').attr('value',0);
		$('#date1').attr('value', '');
		$('#start_time').attr('value','-8 HOURS');
		$('#date2').attr('value','');
		$('#end_time').attr('value','NOW');
		$('#source_address').attr('value','');
		$('#source_port').attr('value','');
		$('#source_if').attr('value','');
		$('#source_as').attr('value','');
		$('#dest_address').attr('value','');
		$('#dest_port').attr('value','');
		$('#dest_if').attr('value','');
		$('#dest_as').attr('value','');
		$('#protocols').attr('value',0);
		$('#tos_fields').attr('value','');
		$('#tcp_flags').attr('value','');
		// Report Settings
		$('#stat_report').attr('value',10);
		$('#print_report').attr('value',0);
		$('#flow_select').attr('value',1);
		$('#sort_field').attr('value',4);
		$('#cutoff_lines').attr('value','100');
		$('#cutoff_octets').attr('value', '');
		$('#resolve_addresses').attr('value',0);
		statSelect();
	}

	function setStatOption(choose) {
		$('#sort_field').empty();

		defsort = 1;
		if (choose == 10) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Source IP', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Destination IP', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 5 || choose == 6 || choose == 7) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Port', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 8 || choose == 9 || choose == 11) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('IP', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 12) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Protocol', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 17 || choose == 18) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Interface', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 23) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Input Interface', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Output Interface', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 19 || choose == 20) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('AS', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 21) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Source AS', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Destination AS', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 22) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('TOS', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 24 || choose == 25) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Prefix', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 26) {
			$('#sort_field').append($('<option>', { value: 1, text: '<?php print __('Source Prefix', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 2, text: '<?php print __('Destination Prefix', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sort_field').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		}

		if (choose != '0' && choose != '99') {
			if (statreport == choose) {
				$('#sort_field').val(sortfield).selectmenu('refresh');
			} else {
				$('#sort_field').val(defsort).selectmenu('refresh');
			}

			$('#print_report').val('0').selectmenu('refresh');
		}
	}

	var sortfield='<?php echo $sort_field; ?>';
	var statreport='<?php echo ($stat_report > 0 ? $stat_report : 0); ?>';

	</script>

	<?php
}

