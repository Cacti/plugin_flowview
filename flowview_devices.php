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
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

flowview_connect();

include_once($config['base_path'] . '/plugins/flowview/arrays.php');

$flow_actions = array(
	1 => __('Delete', 'flowview'),
	2 => __('Enable', 'flowview'),
	3 => __('Disable', 'flowview')
);

set_default_action();

$device_edit = array(
	'name' => array(
		'method' => 'textbox',
		'friendly_name' => __('Listener Name', 'flowview'),
		'description' => __('Name of the device to be displayed.', 'flowview'),
		'value' => '|arg1:name|',
		'max_length' => '64',
	),
	'cmethod' => array(
		'friendly_name' => __('Collection Method', 'flowview'),
		'description' => __('There are two support collection methods, the first utilizes the legacy flow-tools binaries and the second leverages Cacti\'s own PHP based flow stream server.', 'flowview'),
		'value' => '1',
		'method' => 'hidden',
		'default' => '1'
	),
	'allowfrom' => array(
		'method' => 'textbox',
		'friendly_name' => __('Allowed Host Range', 'flowview'),
		'description' => __('IP Address of the device that is allowed to send to this flow collector.  Leave as 0 for any host.  Note that PHP is finicky about what it allows.  CIDR syntax is supported as well as range syntax for example 192.168.1.0 or 192.168.1.0/24.  If you want specific IP addresses, separate them by a comma.', 'flowview'),
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
	'protocol' => array(
		'method' => 'drop_array',
		'friendly_name' => __('Protocol', 'flowview'),
		'description' => __('The IP Protocol to use for this listener.', 'flowview'),
		'value' => '|arg1:protocol|',
		'array' => array('UDP' => __('UDP Protocol', 'flowview')), //'TCP' => __('TCP Protocol', 'flowview')),
		'default' => 'UDP'
	),
	'enabled' => array(
		'method' => 'checkbox',
		'friendly_name' => __('Enabled', 'flowview'),
		'description' => __('If checked, the Listener service will be started and will wait on incoming data.', 'flowview'),
		'value' => '|arg1:enabled|',
		'default' => ''
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
	case 'export':
		export_template();

		break;
	case 'save':
		save_device();

		break;
	case 'edit':
		top_header();
		edit_device();
		bottom_footer();

		break;
	default:
		top_header();
		show_devices();
		bottom_footer();

		break;
}

function export_template() {
	global $flow_fieldids;

	$template_id = get_filter_request_var('template');
	$device_id   = get_filter_request_var('id');
	$ex_addr     = get_nfilter_request_var('ex_addr');

	$data = flowview_db_fetch_cell_prepared('SELECT column_spec
		FROM plugin_flowview_device_templates
		WHERE device_id = ?
		AND template_id = ?
		AND ex_addr = ?',
		array($device_id, $template_id, $ex_addr));

	if ($data != '') {
		$data = json_decode($data, true);

		foreach($data as $index => $detail) {
			if (isset($flow_fieldids[$detail['field_id']])) {
				$data[$index]['supported'] = 'yes';
			} else {
				$data[$index]['supported'] = 'no';
			}
		}

		if (function_exists('yaml_emit')) {
			$data = yaml_emit($data, JSON_PRETTY_PRINT);
			header('Content-type: application/yaml');
	        header('Content-Disposition: attachment; filename=template_export.yaml');
		} else {
			$data = json_encode($data, JSON_PRETTY_PRINT);
			header('Content-type: application/json');
	        header('Content-Disposition: attachment; filename=template_export.json');
			print $data;
		}

		exit;
	} else {
		raise_message_javascript('notemplate', __('No Flow Template was found for Device ID:%s and Template ID:%s', $device_id, $template_id, 'flowview'), MESSAGE_LEVEL_ERROR);

		exit;
	}
}

function actions_devices () {
	global $flow_actions, $config;

	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') {
				foreach ($selected_items as $item) {
					flowview_db_execute_prepared('DELETE FROM plugin_flowview_devices
						WHERE id = ?', array($item));
				}
			} elseif (get_nfilter_request_var('drp_action') == '2') {
				foreach ($selected_items as $item) {
					flowview_db_execute_prepared('UPDATE plugin_flowview_devices
						SET enabled = "on"
						WHERE id = ?', array($item));
				}
			} elseif (get_nfilter_request_var('drp_action') == '3') {
				foreach ($selected_items as $item) {
					flowview_db_execute_prepared('UPDATE plugin_flowview_devices
						SET enabled = ""
						WHERE id = ?', array($item));
				}
			}

			restart_services();
		}

		header('Location: flowview_devices.php?header=false');
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

			$device_list .= '<li>' . flowview_db_fetch_cell('SELECT name FROM plugin_flowview_devices WHERE id=' . $matches[1]) . '</li>';
			$device_array[$i] = $matches[1];
		}
		$i++;
	}

	general_header();

	form_start('flowview_devices.php');

	html_start_box($flow_actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (get_nfilter_request_var('drp_action') == '1') { /* Delete */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Delete the following NetFlow Listeners.  After which, Cacti will attempt to restart your Flow-Capture Service.', 'flowview') . "</p>
				<div><ul class='itemlist'>$device_list</ul></div>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '2') { /* Enable */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Enable the following NetFlow Listeners.  After which, Cacti will attempt to restart your Flow-Capture Service.', 'flowview') . "</p>
				<div><ul class='itemlist'>$device_list</ul></div>
			</td>
		</tr>";
	} elseif (get_nfilter_request_var('drp_action') == '3') { /* Delete */
		print "<tr>
			<td colspan='2' class='textArea'>
				<p>" . __('Click \'Continue\' to Delete the following NetFlow Listeners.  After which, Cacti will attempt to restart your Flow-Capture Service.', 'flowview') . "</p>
				<div><ul class='itemlist'>$device_list</ul></div>
			</td>
		</tr>";
	}

	if (!isset($device_array)) {
		print "<tr><td class='even'><span class='textError'>" . __('You must select at least one Listener.', 'flowview') . "</span></td></tr>\n";
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

function save_device() {
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
	$save['protocol']    = get_nfilter_request_var('protocol');
	$save['enabled']     = isset_request_var('enabled') ? 'on':'';

	$id = flowview_sql_save($save, 'plugin_flowview_devices', 'id', true);

	$pid = db_fetch_cell('SELECT pid FROM processes WHERE tasktype="flowview" AND taskname="master"');

	if (is_error_message()) {
		raise_message(2);

		header('Location: flowview_devices.php?header=false&action=edit&id=' . (empty($id) ? get_request_var('id') : $id));
		exit;
	} else {
		if ($pid > 0) {
			restart_services();

			raise_message('flow_save', __('Save Successful.  The Flowview Listener has been saved, and the service restarted.', 'flowview'), MESSAGE_LEVEL_INFO);
		} else {
			raise_message('flow_save', __('Save Successful.  The Flowview Listener has been saved.  However, the service flow-capture is not running.', 'flowview'), MESSAGE_LEVEL_WARN);
		}
	}

	header("Location: flowview_devices.php?header=false&action=edit&id=$id");
	exit;
}

function restart_services() {
	$pid = db_fetch_cell('SELECT pid
		FROM processes
		WHERE tasktype="flowview"
		AND taskname="master"');

	if ($pid > 0) {
		if (!defined('SIGHUP')) {
			define('SIGHUP', 1);
		}

		posix_kill($pid, SIGHUP);

		sleep(3);
	}
}

function edit_device() {
	global $device_edit, $flow_fieldids;

	/* ================= input validation ================= */
	get_filter_request_var('id');
	/* ==================================================== */

	$device = array();

	if (!isempty_request_var('id')) {
		$device = flowview_db_fetch_row('SELECT *
			FROM plugin_flowview_devices
			WHERE id=' . get_request_var('id'), false);

		$header_label = __esc('Listener [edit: %s]', $device['name'], 'flowview');

		display_tabs(get_request_var('id'));
	} else {
		$header_label = __('Listener [new]', 'flowview');
	}

	if (!isset_request_var('tab') || get_request_var('tab') == 'general') {
		form_start('flowview_devices.php', 'flowview');

		html_start_box($header_label, '100%', '', '3', 'center', '');

		draw_edit_form(
			array(
				'config' => array('no_form_tag' => true),
				'fields' => inject_form_variables($device_edit, $device)
			)
		);

		html_end_box();

		form_save_button('flowview_devices.php', 'return');

		if (cacti_sizeof($device)) {
			$streams = flowview_db_fetch_assoc_prepared('SELECT ds.*, SUM(dt.templates) AS templates
				FROM plugin_flowview_device_streams AS ds
				LEFT JOIN (
					SELECT device_id, ex_addr, COUNT(*) AS templates
					FROM plugin_flowview_device_templates AS dt
					WHERE device_id = ?
					GROUP BY ex_addr
				) AS dt
				USING (device_id, ex_addr)
				WHERE ds.device_id = ?
				GROUP BY ds.ex_addr',
				array($device['id'], $device['id']));

			html_start_box('Inbound Streams and Status', '100%', '', '4', 'center', '');

			$display_array = array(
				array(
					'display' => __('Name', 'flowview'),
					'align'   => 'left'
				),
				array(
					'display' => __('Address', 'flowview'),
					'align'   => 'left'
				),
				array(
					'display' => __('Status', 'flowview'),
					'align'   => 'left'
				),
				array(
					'display' => __('Version', 'flowview'),
					'align'   => 'right'
				),
				array(
					'display' => __('Templates', 'flowview'),
					'align'   => 'right'
				),
				array(
					'display' => __('Last Updated', 'flowview'),
					'align'   => 'right',
					'tip'     => __('Active Streams update every 5 minutes', 'flowview')
				)
			);

			html_header($display_array, false);

			if (cacti_sizeof($streams)) {
				$i = 0;

				foreach ($streams as $row) {
					if (time()-strtotime($row['last_updated']) < 600) {
						$status = 3;
					} else {
						$status = 1;
					}

					if (empty($row['templates'])) {
						$row['templates'] = 0;
					}

					$enabled = flowview_db_fetch_cell_prepared('SELECT enabled
						FROM plugin_flowview_devices
						WHERE id = ?',
						array($device['id']));

					if ($enabled == 'on') {
						$disabled = false;
					} else {
						$disabled = true;
					}

					form_alternate_row('line' . $i, true);
					form_selectable_cell($row['name'], $i);
					form_selectable_cell($row['ex_addr'], $i);
					form_selectable_cell(get_colored_device_status($disabled, $status), $i);
					form_selectable_cell($row['version'], $i, '', 'right');
					form_selectable_cell($row['templates'], $i, '', 'right');
					form_selectable_cell($row['last_updated'], $i, '', 'right');
					form_end_row();

					$i++;
				}
			} else {
				print "<tr class='even'><td colspan='" . cacti_sizeof($display_array) . "'><center>" . __('No Inbound Streams Detected', 'flowview') . '</center></td></tr>';
			}

			html_end_box(false);
		}
	} else {
		if (!isset_request_var('ex_addr')) {
			$ex_addr = flowview_db_fetch_cell_prepared('SELECT ex_addr
				FROM plugin_flowview_device_templates
				WHERE device_id = ?
				LIMIT 1', array($device['id']));

			set_request_var('ex_addr', $ex_addr);
		}

		if (!isset_request_var('template')) {
			$template_id = flowview_db_fetch_cell_prepared('SELECT template_id
				FROM plugin_flowview_device_templates
				WHERE device_id = ?
				AND ex_addr = ?
				LIMIT 1', array($device['id'], get_request_var('ex_addr')));

			set_request_var('template', $template_id);
		}

		/* ================= input validation and session storage ================= */
		$filters = array(
			'template' => array(
				'filter' => FILTER_VALIDATE_INT,
				'default' => '-1'
			),
			'ex_addr' => array(
				'filter' => FILTER_CALLBACK,
				'default' => '-1',
				'options' => array('options' => 'sanitize_search_string')
			)
		);

		validate_store_request_vars($filters, 'sess_fvdt');
		/* ================= input validation ================= */

		if (isset_request_var('ex_addr') && get_request_var('ex_addr') != 0) {
			$sql_where = ' AND ex_addr = ?';
			$sql_params[] = $device['id'];
			$sql_params[] = get_nfilter_request_var('ex_addr');
		} else {
			$sql_where = '';
			$sql_params[] = $device['id'];
		}

		$templates = flowview_db_fetch_assoc_prepared("SELECT *
			FROM plugin_flowview_device_templates AS dt
			WHERE dt.device_id = ?
			$sql_where", $sql_params);

		$dtemplates = flowview_db_fetch_assoc_prepared("SELECT DISTINCT template_id
			FROM plugin_flowview_device_templates AS dt
			WHERE dt.device_id = ?
			$sql_where", $sql_params);

		$addrs = flowview_db_fetch_assoc_prepared('SELECT DISTINCT ex_addr
			FROM plugin_flowview_device_templates
			WHERE device_id = ?',
			array($device['id']));

		html_start_box(__('Listener Detected Templates', 'flowview'), '100%', '', '4', 'center', '');

		?>
		<tr class='even'>
			<td>
			<form id='listeners' action='flowview_devices.php'>
				<table class='filterTable'>
					<tr>
						<td>
							<?php print __('Ex Addr', 'flowview');?>
						</td>
						<td>
							<select id='ex_addr'>
								<?php
								print "<option value='0' " . (get_request_var('template') == '0' ? 'selected':'') . '>' . __('All', 'flowview') . '</option>';

								foreach($addrs as $a) {
									print "<option value='{$a['ex_addr']}' " . (get_request_var('ex_addr') == $a['ex_addr'] ? 'selected':'') . '>' . $a['ex_addr'] . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<?php print __('Template', 'flowview');?>
						</td>
						<td>
							<select id='template'>
								<?php
								print "<option value='0' " . (get_request_var('template') == '0' ? 'selected':'') . '>' . __('All', 'flowview') . '</option>';

								foreach($dtemplates as $t) {
									print "<option value='{$t['template_id']}' " . (get_request_var('template') == $t['template_id'] ? 'selected':'') . '>' . $t['template_id'] . '</option>';
								}
								?>
							</select>
						</td>
						<td>
							<span>
								<input id='go' type='button' value='<?php print __('Go', 'flowview');?>'>
								<input id='export' type='button' value='<?php print __('Export', 'flowview');?>'>
							</span>
						</td>
					</tr>
				</table>
			</form>
			<script type='text/javascript'>
			function applyFilter() {
				strURL  = 'flowview_devices.php?action=edit&id=<?php print get_request_var('id');?>&tab=templates&header=false';
				strURL += '&template=' + $('#template').val();
				strURL += '&ex_addr='  + $('#ex_addr').val();
				loadPageNoHeader(strURL);
			}

			function exportFilter() {
				strURL  = 'flowview_devices.php?action=export&id=<?php print get_request_var('id');?>&tab=templates&header=false';
				strURL += '&template=' + $('#template').val();
				strURL += '&ex_addr='  + $('#ex_addr').val();

				document.location = strURL;

				Pace.stop();
			}

			$(function() {
				$('#template, #ex_addr').change(function() {
					applyFilter();
				});

				$('#go').click(function() {
					applyFilter();
				});

				$('#export').click(function() {
					exportFilter();
				});

				$('#sorttable').tablesorter({
					widgets: ['zebra', 'resizable'],
					sortList: [[4,0]],
					widgetZebra: { css: ['even', 'odd'] },
					headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
					cssIconAsc: 'fa-sort-up',
					cssIconDesc: 'fa-sort-down',
					cssIconNone: 'fa-sort',
					cssIcon: 'fa'
				});

				$('.tablesorter-resizable-container').hide();
			});
			</script>
			</td>
		</tr>
		<?php

		html_end_box();

		html_start_box('', '100%', '', '4', 'center', '');

		$display_array = array(
			array(
				'display' => __('Address', 'flowview'),
				'align'   => 'left'
			),
			array(
				'display' => __('Template ID', 'flowview'),
				'align'   => 'left'
			),
			array(
				'display' => __('Field Name', 'flowview'),
				'align'   => 'left'
			),
			array(
				'display' => __('Supported', 'flowview'),
				'align'   => 'left'
			),
			array(
				'display' => __('Field ID', 'flowview'),
				'align'   => 'right'
			),
			array(
				'display' => __('Field Type', 'flowview'),
				'align'   => 'right'
			),
			array(
				'display' => __('Length', 'flowview'),
				'align'   => 'right'
			),
			array(
				'display' => __('Unpack', 'flowview'),
				'align'   => 'right'
			)
		);

		$table  = '<tr><table id="sorttable" class="cactiTable"><thead>';
		$table .= '<tr class="tableHeader">';
		foreach($display_array as $item) {
			$table .= '<th class="' . $item['align'] . '">' . $item['display'] . '</th>';
		}
		$table .= '</tr></thead>';
		$table .= '<tbody>';

		print $table;

		if (cacti_sizeof($templates)) {
			$i = 0;

			foreach ($templates as $row) {
				$items = json_decode($row['column_spec'], true);


				if (get_request_var('template') == 0 || get_request_var('template') == $row['template_id']) {
					if (cacti_sizeof($items)) {
						foreach($items as $ti) {
							print '<tr>';
							form_selectable_cell($row['ex_addr'], $i);
							form_selectable_cell($row['template_id'], $i);
							form_selectable_cell($ti['name'], $i);
							form_selectable_cell(get_colored_field_column($ti['field_id']), $i);
							form_selectable_cell($ti['field_id'], $i, '', 'right');
							form_selectable_cell($ti['pack'], $i, '', 'right');
							form_selectable_cell($ti['length'], $i, '', 'right');
							form_selectable_cell($ti['unpack'], $i, '', 'right');
							print '</tr>';

							$i++;
						}
					}
				}
			}
		} else {
			print "<tr class='even'><td colspan='" . cacti_sizeof($display_array) . "'><center>" . __('No Inbound Stream Templates Detected', 'flowview') . '</center></td></tr>';
		}

		print '</tr></tbody></table></tr>';
	}

	html_end_box();
}

function show_devices () {
	global $action, $expire_arr, $rotation_arr, $version_arr, $nesting_arr;
	global $config, $flow_actions;

	if (substr_count(strtolower(PHP_OS), 'freebsd')) {
		$os = 'freebsd';
	} else {
		$os = 'linux';
	}

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

	if (get_request_var('rows') == '-1' || isempty_request_var('rows')) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where = (get_request_var('filter') != '' ? 'WHERE name LIKE ' . db_qstr('%' . get_request_var('filter') . '%'):'');

	$sql_order = get_order_string();
	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$sql = "SELECT fd.*, COUNT(*) AS streams, GROUP_CONCAT(DISTINCT version) AS versions, MAX(fs.last_updated) AS last_updated
		FROM plugin_flowview_devices AS fd
		LEFT JOIN plugin_flowview_device_streams AS fs
		ON fd.id = fs.device_id
		$sql_where
		GROUP BY fd.id
		$sql_order
		$sql_limit";

	$result = flowview_db_fetch_assoc($sql);

	$total_rows = flowview_db_fetch_cell("SELECT COUNT(*) FROM plugin_flowview_devices $sql_where");

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
						<span>
							<input id='refresh' type='submit' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
							<input id='clear' type='button' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
						</span>
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

	$nav = html_nav_bar('flowview_devices.php', MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 10, __('Listeners', 'flowview'), 'page', 'main');

	form_start('flowview_devices.php', 'chk');

	print $nav;

	html_start_box('', '100%', '', '4', 'center', '');

	$display_array = array(
		'name' => array(
			'display' => __('Name', 'flowview'),
			'sort' => 'ASC'
		),
		'cmethod' => array(
			'display' => __('Method', 'flowview'),
			'sort' => 'ASC'
		),
		'allowfrom' => array(
			'display' => __('Allowed From', 'flowview'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'port' => array(
			'display' => __('Port', 'flowview'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'protocol' => array(
			'display' => __('Protocol', 'flowview'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'nosort0' => array(
			'display' => __('Status', 'flowview'),
			'sort' => 'ASC',
			'align' => 'right'
		),
		'nosort1' => array(
			'display' => __('Observed Listen', 'flowview'),
			'sort' => 'ASC',
			'tip'   => __('The port security actually observed on the listeners port.', 'flowview'),
			'align' => 'right'
		),
		'streams' => array(
			'display' => __('Streams', 'flowview'),
			'sort' => 'ASC',
			'tip'   => __('The number of inbound connections from various sources.', 'flowview'),
			'align' => 'right'
		),
		'nosort2' => array(
			'display' => __('Stream Versions', 'flowview'),
			'sort' => 'ASC',
			'tip'   => __('The Traffic Flow versions being observed.', 'flowview'),
			'align' => 'right'
		),
		'last_updated' => array(
			'display' => __('Last Updated', 'flowview'),
			'sort' => 'ASC',
			'tip'   => __('The maximum update time from all streams being collected.  This value is updated every 5 minutes.', 'flowview'),
			'align' => 'right'
		),
	);

	html_header_sort_checkbox($display_array, get_request_var('sort_column'), get_request_var('sort_direction'), false);

	if (cacti_sizeof($result)) {
		foreach ($result as $row) {
			if ($os == 'freebsd') {
				$status = shell_exec("/usr/bin/sockstat -4 -l | /usr/bin/grep ':" . $row['port'] . " '");
				$column = 3;
			} else {
				$status = shell_exec("ss -lntu | grep ':" . $row['port'] . " '");
				$column = 4;
				if (empty($status)) {
					$status = shell_exec("netstat -an | grep ':" . $row['port'] . " '");
					$column = 3;
				}
			}

			if (is_string($status)) {
				$parts = preg_split('/[\s]+/', trim($status));
			}

			if ($status != '') {
				$status = 3;
			} else {
				$status = 1;
			}

			if ($row['versions'] == '') {
				$row['versions'] = __('None', 'flowview');
			}

			if ($row['last_updated'] == '') {
				$row['last_updated'] = __('No Connections', 'flowview');
			}

			if ($row['enabled'] == '') {
				$disabled        = true;
				$row['versions'] = __('N/A', 'flowview');
				$row['streams']  = __('N/A', 'flowview');
				$row['observed'] = __('N/A', 'flowview');
			} else {
				$disabled = false;
				$row['observed'] = isset($parts[$column]) ? $parts[$column]:'-';
			}

			if ($row['allowfrom'] == 0) {
				$row['allowfrom'] = __('All', 'flowview');
			}

			form_alternate_row('line' . $row['id'], true);
			form_selectable_cell('<a class="linkEditMain" href="flowview_devices.php?action=edit&id=' . $row['id'] . '">' . $row['name'] . '</a>', $row['id']);
			form_selectable_cell(__('Cacti', 'flowview'), $row['id']);
			form_selectable_cell($row['allowfrom'], $row['id'], '', 'right');
			form_selectable_cell($row['port'], $row['id'], '', 'right');
			form_selectable_cell($row['protocol'], $row['id'], '', 'right');
			form_selectable_cell(get_colored_device_status($disabled, $status), $row['id'], '', 'right');
			form_selectable_cell($row['observed'], $row['id'], '', 'right');
			form_selectable_cell($row['streams'], $row['id'], '', 'right');
			form_selectable_cell($row['versions'], $row['id'], '', 'right');
			form_selectable_cell($row['last_updated'], $row['id'], '', 'right');
			form_checkbox_cell($row['name'], $row['id']);
			form_end_row();
		}
	} else {
		print "<tr class='even'><td colspan=10><center>" . __('No Flowview Listeners', 'flowview') . '</center></td></tr>';
	}

	html_end_box(false);

	if (cacti_sizeof($result)) {
		print $nav;
	}

	draw_actions_dropdown($flow_actions);

	form_end();
}

