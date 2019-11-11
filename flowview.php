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

chdir('../../');
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
include_once($config['base_path'] . '/lib/time.php');

set_default_action();

flowview_request_vars();

ini_set('max_execution_time', 240);
ini_set('memory_limit', '-1');

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
case 'updatefess':
	load_session_for_page();
	break;
case 'updatesess':
	flowview_report_session();
	break;
case 'gettimespan':
	flowview_gettimespan();
	break;
case 'edit':
	flowview_display_form();
	break;
default:
	general_header();

	if (get_filter_request_var('query') > 0) {
		load_session_for_filter();
	} else {
		load_session_for_page();
	}
cacti_log('sifh');

	flowview_display_report();

	bottom_footer();
}

function load_session_for_filter() {
	if (isset_request_var('query') && get_filter_request_var('query') > 0) {
		$q = db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		if (cacti_sizeof($q)) {
			foreach($q as $column => $value) {
				switch($column) {
					case 'name':
						break;
					case 'timespan':
						if (!isset_request_var('predefined_timespan')) {
							set_request_var('predefined_timespan', $q['timespan']);

							if ($q['timespan'] == 0) {
								set_request_var('date1', strtoupper($q['startdate']));
								set_request_var('date2', strtoupper($q['enddate']));
							} else {
								$span = array();
								get_timespan($span, time(), get_request_var('predefined_timespan'), read_user_setting('first_weekdayid'));
								set_request_var('date1', $span['current_value_date1']);
								set_request_var('date2', $span['current_value_date2']);
							}
						}

						break;
					default:
						set_request_var($column, $value);
						break;
				}
			}
		}
	}
}

/**
 * load_session_for_page()
 *
 * Loads the session from the page variables.
 *
 * Page variables are described below.  Flow-view tool parameters may
 * be somewhat different.
 *
 * Input arguments (received from the form):
 * Name         Description
 * -----------------------------------------------------------------------
 * device       An identifying name of the device (e.g. router1)
 * flow_select  Identifies which flows to include wrt time period
 * timespan     Using a Timespan instaed of start and end times
 * date1        Start date of analysis period
 * date2        End date of analysis period
 * sourceip     Constrain flows examined to these source IP addresses
 * sourceport   Constrain flows examined to these source ports
 * sourceif     Constrain flows examined to these input interfaces
 * sourceas     Constrain flows examined to these source ASes
 * destip       Constrain flows examined to these dest. IP addresses
 * destport     Constrain flows examined to these dest. ports
 * destif       Constrain flows examined to these output interfaces
 * destas       Constrain flows examined to these dest. ASes
 * tosfields    Constrain flows examined by specified TOS field values
 * tcpflags     Constrain flows examined by specified TCP flag values
 * protocols    Constrain flows examined to these protocols
 * printed      Select from these various report options
 * statistics   Select from these various statistics options
 * cutofflines  Number of report lines to print out
 * cutoffoctets Minimum number of octets for inclusion in report
 * sortfield    Which report column to sort lines upon
 * resolve      Whether or not to resolve IP addresses
 */
function load_session_for_page() {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'device' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'flow_select' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '1',
			'options' => array('options' => 'sanitize_search_string')
		),
		'predefined_timespan' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '7'
		),
		'date1' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'date2' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sourceip' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sourceport' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'sourceas' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'destip' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'name',
			'options' => array('options' => 'sanitize_search_string')
		),
		'destport' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'destas' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'tosfield' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'tcpflags' => array(
			'filter' => FILTER_CALLBACK,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'protocols' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'printed' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0',
		),
		'statistics' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0',
		),
		'cutofflines' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '100',
		),
		'cutoffoctets' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '',
		),
		'sortfield' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '4',
		),
		'resolve' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'Y',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fv');
	/* ================= input validation ================= */

	// Set some variables based upon pre-defined timespan
	if (get_request_var('device') == '') {
		$device = db_fetch_cell("SELECT folder FROM plugin_flowview_devices ORDER BY id LIMIT 1");;
		set_request_var('device', $device);
	}

	if (isset_request_var('predefined_timespan') && get_filter_request_var('predefined_timespan') > 0) {
		$timespan = get_request_var('predefined_timespan');
		set_request_var('predefined_timespan', $timespan);

		$span = array();
		get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
		set_request_var('date1', $span['current_value_date1']);
		set_request_var('date2', $span['current_value_date2']);
	} elseif (isset_request_var('date1')) {
		// date variables already set
	} else {
		set_request_var('predefined_timespan', '0');
		$timespan = 0;

		$date1 = date('Y-m-d H:i:s', time() - (8 * 3600));
		$date2 = date('Y-m-d H:i:s');
		set_request_var('date1', $date1);
		set_request_var('date2', $date2);
	}
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
	$sessionid = get_sessionid();

	db_execute_prepared('DELETE FROM plugin_flowview_session_cache
		WHERE user_id = ?
		AND sessionid = ?
		AND id = ?',
		array($_SESSION['sess_user_id'], session_id(), $sessionid));

	db_execute_prepared('DELETE FROM plugin_flowview_session_cache_details
		WHERE cache_id = ?',
		array($sessionid));

	db_execute_prepared('DELETE FROM plugin_flowview_session_cache_flow_stats
		WHERE cache_id = ?',
		array($sessionid));

	if (isset($_SESSION['flowview_flows'][$sessionid])) {
		unset($_SESSION['flowview_flows'][$sessionid]);
	}

	header('Location: flowview.php?tab=filters');
	exit;
}

function flowview_save_filter() {
	if (isset_request_var('new_query') && get_nfilter_request_var('new_query') != '') {
		$queryname    = get_nfilter_request_var('new_query');
		$save['id']   = '';
		$save['name'] = form_input_validate($queryname, 'queryname', '', false, 3);
	} else {
		$save['id']          = get_filter_request_var('query');
	}

	$save['device']          = get_nfilter_request_var('device');
	$save['timespan']        = get_nfilter_request_var('predefined_timespan');
	$save['startdate']       = get_nfilter_request_var('date1');
	$save['enddate']         = get_nfilter_request_var('date2');
	$save['tosfields']       = get_nfilter_request_var('tosfields');
	$save['tcpflags']        = get_nfilter_request_var('tcpflags');
	$save['protocols']       = get_nfilter_request_var('protocols');
	$save['sourceip']        = get_nfilter_request_var('sourceip');
	$save['sourceport']      = get_nfilter_request_var('sourceport');
	$save['sourceinterface'] = get_nfilter_request_var('sourceinterface');
	$save['sourceas']        = get_nfilter_request_var('sourceas');
	$save['destip']          = get_nfilter_request_var('destip');
	$save['destport']        = get_nfilter_request_var('destport');
	$save['destinterface']   = get_nfilter_request_var('desc_if');
	$save['destas']          = get_nfilter_request_var('desc_as');
	$save['statistics']      = get_nfilter_request_var('statistics');
	$save['printed']         = get_nfilter_request_var('printed');
	$save['includeif']       = get_nfilter_request_var('includeif');
	$save['sortfield']       = get_nfilter_request_var('sortfield');
	$save['cutofflines']     = get_nfilter_request_var('cutofflines');
	$save['cutoffoctets']    = get_nfilter_request_var('cutoffoctets');
	$save['resolve']         = get_nfilter_request_var('resolve');

	$id = sql_save($save, 'plugin_flowview_queries', 'id', true);

	if (is_error_message() || $id == '') {
		print 'error';
	} else {
		print $id;
	}
}

function flowview_request_vars() {
	if (isset_request_var('statistics') && get_filter_request_var('statistics') > 0) {
		set_request_var('printed', 0);
	} elseif (isset_request_var('printed') && get_filter_request_var('printed') > 0) {
		set_request_var('statistics', 0);
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
		'includeif' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
			),
		'statistics' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '10'
			),
		'printed' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'sortfield' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '3'
			),
		'cutofflines' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
			),
		'cutoffoctets' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
			),
		'device' => array(
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
		'includeif' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'tcpflags' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'tosfields' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'sourceip' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'sourceport' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'sourceinterface' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'sourceas' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'destip' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'destport' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'destinterface' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'destas' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string')
			),
		'resolve' => array(
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

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$devices_arr = db_fetch_assoc('SELECT folder, name FROM plugin_flowview_devices ORDER BY name');
	$devices = array();
	if (!empty($devices_arr)) {
		$ddevice = $devices_arr[0]['folder'];
		foreach ($devices_arr as $d) {
			$devices[$d['folder']] = $d['name'];
		}
	} else {
		$ddevice = 0;
	}

	$filter = array(
		'spacer0' => array(
			'method' => 'spacer',
			'collapsible' => true,
			'friendly_name' => __('General Filters', 'flowview'),
		),
		'query' => array(
			'friendly_name' => __('Filter', 'flowview'),
			'description' => __('The Saved Filter to display.', 'flowview'),
			'method' => 'drop_sql',
			'value' => (isset_request_var('query') ? get_filter_request_var('query') : 0),
			'sql' => 'SELECT id, name FROM plugin_flowview_queries ORDER BY name',
			'default' => 0,
			'none_value' => __('None', 'flowview'),
		),
		'device' => array(
			'friendly_name' => __('Listener', 'flowview'),
			'description' => __('The Listener to use for the Filter.', 'flowview'),
			'method' => 'drop_array',
			'value' => (isset_request_var('device') ? get_nfilter_request_var('device') : $ddevice),
			'array' => $devices,
			'default' => '0',
			'none_value' => __('None', 'flowview'),
		),
		'predefined_timespan' => array(
			'friendly_name' => __('Presets', 'flowview'),
			'description' => __('If this Filter is based upon a pre-defined Timespan, select it here.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('predefined_timespan'),
			'array' => $graph_timespans,
			'default' => '0',
		),
		'date1' => array(
			'friendly_name' => __('Start Date', 'flowview'),
			'description' => __('The Date and Time to Start the Filter on.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('date1'),
			'max_length' => '10',
			'size' => '14'
		),
		'date2' => array(
			'friendly_name' => __('End Date', 'flowview'),
			'description' => __('The Date and Time to End the Filter on.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('date2'),
			'max_length' => '10',
			'size' => '14'
		),
		'spacer1' => array(
			'method' => 'spacer',
			'collapsible' => true,
			'friendly_name' => __('Detailed Filter Criteria', 'flowview'),
		),
		'statistics' => array(
			'friendly_name' => __('Filter Type', 'flowview'),
			'description' => __('The Filter Type to display by default for this Filter.', 'flowview'),
			'method' => 'drop_array',
			'value' => isset_request_var('statistics'),
			'array' => $stat_report_array,
			'default' => '10',
			'none_value' => __('None', 'flowview'),
		),
		'includeif' => array(
			'friendly_name' => __('Range Rules', 'flowview'),
			'description' => __('Constrain the Filter Data by these time filter rules.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('includeif'),
			'default' => '1',
			'array' => $flow_select_array
		),
		'resolve' => array(
			'friendly_name' => __('Resolve IP\'s', 'flowview'),
			'description' => __('Resolve IP Addresses to Domain Names.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('resolve'),
			'default' => 'Y',
			'array' => array(
				'Y' => __('Yes', 'flowview'),
				'N' => __('No', 'flowview')
			)
		),
		'sortfield' => array(
			'friendly_name' => __('Sort Field', 'flowview'),
			'description' => __('The default Sort Field for the Filter.  This setting will be applied for any Scheduled Reports.', 'flowview'),
			'value' => get_request_var('sortfield'),
			'method' => 'drop_array',
			'default' => '0',
			'array' => array()
		),
		'cutofflines' => array(
			'friendly_name' => __('Maximum Rows', 'flowview'),
			'description' => __('The Maximum Rows to provide in the Filter.  This setting will be applied for any Scheduled Reports.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('cutofflines'),
			'default' => '20',
			'array' => array(
				'999999' => __('All', 'flowview'),
				'5'   => __('Top %d', 5, 'flowview'),
				'10'  => __('Top %d', 10, 'flowview'),
				'20'  => __('Top %d', 20, 'flowview'),
				'30'  => __('Top %d', 30, 'flowview'),
				'40'  => __('Top %d', 40, 'flowview'),
				'50'  => __('Top %d', 50, 'flowview'),
				'100' => __('Top %d', 100, 'flowview'),
				'200' => __('Top %d', 200, 'flowview')
			)
		),
		'cutoffoctets' => array(
			'friendly_name' => __('Minimum Bytes', 'flowview'),
			'description' => __('The Minimum Total Bytes to consider for the Filter.  Any flow totals that are less than this many bytes will be ignored.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('cutoffoctets'),
			'default' => '0',
			'array' => array(
				'0'         => __('No Limit', 'flowview'),
				'1024'      => __('%s Bytes', '1K', 'flowview'),
				'10240'     => __('%s Bytes', '10K', 'flowview'),
				'20480'     => __('%s Bytes', '20K', 'flowview'),
				'102400'    => __('%s Bytes', '100K', 'flowview'),
				'512000'    => __('%s Bytes', '500K', 'flowview'),
				'1024000'   => __('%s Bytes', '1M', 'flowview'),
				'10240000'  => __('%s Bytes', '10M', 'flowview'),
				'20480000'  => __('%s Bytes', '20M', 'flowview'),
				'51200000'  => __('%s Bytes', '50M', 'flowview'),
				'102400000' => __('%s Bytes', '100M', 'flowview'),
				'204800000' => __('%s Bytes', '200M', 'flowview'),
				'512000000' => __('%s Bytes', '500M', 'flowview'),
				'1024000000'=> __('%s Bytes', '1G', 'flowview')
			)
		),
		'spacer2' => array(
			'method' => 'spacer',
			'collapsible' => true,
			'friendly_name' => __('Protocol Filters', 'flowview'),
		),
		'protocols' => array(
			'friendly_name' => __('Protocol', 'flowview'),
			'description' => __('Select the Specific Protocol for the Filter.', 'flowview'),
			'method' => 'drop_array',
			'value' => get_request_var('protocols'),
			'default' => '0',
			'array' => $ip_protocols_array
		),
		'tcpflags' => array(
			'friendly_name' => __('TCP Flags', 'flowview'),
			'description' => __('The TCP Flags to search for in the Filter.  This can be a comma delimited list of TCP Flags', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('tcpflags'),
			'max_length' => '20',
			'size' => '14'
		),
		'tosfields' => array(
			'friendly_name' => __('TOS Fields', 'flowview'),
			'description' => __('The TOS Fields to search for in the Filter.  This can be a comma delimited list of TOS Fields', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('tosfields'),
			'max_length' => '20',
			'size' => '14'
		),
		'sourceip' => array(
			'friendly_name' => __('Source IP', 'flowview'),
			'description' => __('Filter on the select Source IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('sourceip'),
			'max_length' => '20',
			'size' => '14'
		),
		'sourceport' => array(
			'friendly_name' => __('Source Ports', 'flowview'),
			'description' => __('Filter on the select Source Ports for in the Filter.  This can be a comma delimited list of Source Ports.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('sourceport'),
			'max_length' => '20',
			'size' => '14'
		),
		'sourceinterface' => array(
			'friendly_name' => __('Source Interface', 'flowview'),
			'description' => __('Filter on the select Source Interface for in the Filter.  This can be a comma delimited list of Source Interfaces', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('sourceinterface'),
			'max_length' => '20',
			'size' => '14'
		),
		'destas' => array(
			'friendly_name' => __('Dest AS', 'flowview'),
			'description' => __('Filter on the select Destination AS for in the Filter.  This can be a comma delimited list of Source AS\'s', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('destas'),
			'max_length' => '20',
			'size' => '14'
		),
		'destip' => array(
			'friendly_name' => __('Dest IP', 'flowview'),
			'description' => __('Filter on the select Destination IP for in the Filter.  This can be a comma delimited list of IPv4 or IPv6 addresses, or a comma delimited list of IPv4 or IPv6 address ranges in CIDR format.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('destip'),
			'max_length' => '20',
			'size' => '14'
		),
		'destport' => array(
			'friendly_name' => __('Dest Ports', 'flowview'),
			'description' => __('Filter on the select Destination Ports for in the Filter.  This can be a comma delimited list of Destimation Ports.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('destport'),
			'max_length' => '20',
			'size' => '14'
		),
		'destinterface' => array(
			'friendly_name' => __('Dest Interface', 'flowview'),
			'description' => __('Filter on the select Destination Interface for in the Filter.  This can be a comma delimited list of Destimation Interfaces.', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('destinterface'),
			'max_length' => '20',
			'size' => '14'
		),
		'destas' => array(
			'friendly_name' => __('Dest AS', 'flowview'),
			'description' => __('Filter on the select Destination AS for in the Filter.  This can be a comma delimited list of Destimation AS\'s', 'flowview'),
			'method' => 'textbox',
			'value' => get_request_var('destas'),
			'max_length' => '20',
			'size' => '14'
		),
	);

	html_start_box('Filters', '100%', true, '3', 'center', '');

	form_start('flowview.php', 'chk');

	draw_edit_form(
		array(
			'config' => array('no_form_tag' => true),
			'fields' => $filter
        )
	);

	form_end();

	?>
	</tr><tr>
		<td>
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
	<?php

	html_end_box();

	html_start_box(__('Filter Data', 'flowview'), '100%', true, '3', 'center', '');
	html_end_box();

	$note1 = __('Multiple field entries, separated by commas, are permitted in the fields above. A minus sign (-) will negate an entry (e.g. -80 for Port, would mean any Port but 80)', 'flowview');

	$note2 = __('Printed Reports presently can run very long as they are currently inserting all data into in range into MySQL/MariaDB cache tables.', 'flowview');

	?>
	<div style='display:none;'>
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
	</div>
	<script type='text/javascript'>

	var date1Open = false;
	var date2Open = false;

	function applyTimespan() {
		$.getJSON('flowview.php?action=gettimespan&predefined_timespan='+$('#predefined_timespan').val(), function(data) {
			$('#date1').val(data['current_value_date1']);
			$('#date2').val(data['current_value_date2']);
		});
	}

	function applyFilter() {
		loadPageNoHeader('flowview.php?action=loadquery&tab=filters&header=false&query='+$('#query').val());
	}

	function statSelect() {
		statval = $('#statistics').val();
		setStatOption(statval);

		if (statval > 0) {
			$('#printed').attr('value', 0);
			$('#printed').prop('disabled', true);
			$('#printed').addClass('ui-state-disabled');
			$('#rlimits').children('.sortfield').show();
		} else {
			$('#printed').prop('disabled', false);
			$('#printed').removeClass('ui-state-disabled');
		}

		if (statval == 99 || statval < 1) {
			$('#rlimits').hide();
		} else {
			$('#rlimits').show();
		}

		if (statval == 0 && $('#printed').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#saveas').prop('disabled', true);
			$('#view').addClass('ui-state-disabled');
			$('#save').addClass('ui-state-disabled');
			$('#saveas').addClass('ui-state-disabled');
		} else {
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#saveas').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled');
			$('#save').removeClass('ui-state-disabled');
			$('#saveas').removeClass('ui-state-disabled');
		}

		if ($('#printed').selectmenu('instance')) {
			$('#printed').selectmenu('refresh', true);
		}
	}

	function printSelect() {
		statval = $('#printed').val();

		if (statval > 0) {
			$('#statistics').attr('value',0);
			$('#statistics').prop('disabled', false);
			$('#sortfield').prop('disabled', false);
			$('#rlimits').hide();
			$('#rlimits').children('.sortfield').hide();
		} else {
			$('#rlimits').show();
			$('#cutofflines').prop('disabled', false);
			$('#cutoffoctets').prop('disabled', false);

			if ($('#statistics').val() == 0) {
				$('#statistics').attr('value', 10);
			}

			$('#statistics').prop('disabled', false);
			statSelect();
			return;
		}

		if (statval == 4 || statval == 5) {
			$('#cutofflines').prop('disabled', false);
			$('#cutoffoctets').prop('disabled', false);
			$('#rlimits').show();
		} else {
			$('#cutofflines').prop('disabled', true);
			$('#cutoffoctets').prop('disabled', true);
			$('#rlimits').hide();
		}

		if (statval == 0 && $('#statistics').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#saveas').prop('disabled', true);
			$('#view').addClass('ui-state-disabled', true);
			$('#save').addClass('ui-state-disabled', true);
			$('#saveas').addClass('ui-state-disabled', true);
		} else {
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#saveas').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled', true);
			$('#save').removeClass('ui-state-disabled', true);
			$('#saveas').removeClass('ui-state-disabled', true);
		}

		$('#statistics').selectmenu('refresh', true);
	}

	$('#device').change(function () {
		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($(this).val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#view').addClass('ui-state-disabled', true);
			$('#save').addClass('ui-state-disabled', true);
		} else {
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled', true);
			$('#save').removeClass('ui-state-disabled', true);
		}
		<?php } else { ?>
		if ($(this).val() == 0) {
			$('#view').prop('disabled', true);
			$('#view').addClass('ui-state-disabled', true);
		} else {
			$('#view').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled', true);
		}
		<?php } ?>
	});

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

		$('#saveas').hide();

		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($('#query').val() == 0) {
			$('#delete').hide();
		} else {
			$('#save').attr('value', '<?php print __('Update', 'flowview');?>');
			$('#saveas').show();
		}
		<?php } else { ?>
		$('#delete').hide();
		$('#save').hide();
		<?php } ?>

		$('#chk').change(function() {
			$('#changed').attr('value', '1');
		});

		<?php if (api_user_realm_auth('flowview_devices.php')) { ?>
		if ($('#device').val() == 0) {
			$('#view').prop('disabled', true);
			$('#save').prop('disabled', true);
			$('#view').addClass('ui-state-disabled');
			$('#save').addClass('ui-state-disabled');
		} else {
			$('#view').prop('disabled', false);
			$('#save').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled');
			$('#save').removeClass('ui-state-disabled');
		}
		<?php } else { ?>
		if ($('#device').val() == 0) {
			$('#view').prop('disabled', true);
			$('#view').addClass('ui-state-disabled');
		} else {
			$('#view').prop('disabled', false);
			$('#view').removeClass('ui-state-disabled');
		}
		<?php } ?>

		$('#statistics').change(function() {
			statSelect();
			$('#printed').selectmenu('refresh');
		});

		$('#printed').change(function() {
			printSelect();
			$('#statistics').selectmenu('refresh');
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
		$('#view').prop('disabled', true);
		$('#view').addClass('ui-state-disabled');
		$('#action').attr('value', 'view');
		$.post('flowview.php', $('input, select, textarea').serializeForm(), function(data) {
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
			$.post('flowview.php', $('#chk').serializeForm, function(data) {
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
				$.post('flowview.php', $('#chk').serializeForm(), function(data) {
					if (data!='error') {
						loadPageNoHeader('flowview.php?tab=filters&header=false&action=loadquery&query='+data);
						$('#text').show().text('<?php print __('Filter Settings Saved');?>').fadeOut(2000);
					}
				});
				$('#fdialog').dialog('close');
			});
		} else {
			$('#action').attr('value', 'save');
			$.post('flowview.php', $('#chk').serializeForm(), function(data) {
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
		$('#sourceip').attr('value','');
		$('#sourceport').attr('value','');
		$('#sourceinterface').attr('value','');
		$('#sourceas').attr('value','');
		$('#destip').attr('value','');
		$('#destport').attr('value','');
		$('#destinterface').attr('value','');
		$('#destas').attr('value','');
		$('#protocols').attr('value',0);
		$('#tosfields').attr('value','');
		$('#tcpflags').attr('value','');
		// Report Settings
		$('#statistics').attr('value',10);
		$('#printed').attr('value',0);
		$('#includeif').attr('value',1);
		$('#sortfield').attr('value',4);
		$('#cutofflines').attr('value','100');
		$('#cutoffoctets').attr('value', '');
		$('#resolve').attr('value',0);
		statSelect();
	}

	function setStatOption(choose) {
		$('#sortfield').empty();

		defsort = 1;
		if (choose == 10) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Source IP', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Destination IP', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 5 || choose == 6 || choose == 7) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Port', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 8 || choose == 9 || choose == 11) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('IP', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 12) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Protocol', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 17 || choose == 18) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Interface', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 23) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Input Interface', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Output Interface', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 19 || choose == 20) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('AS', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 21) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Source AS', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Destination AS', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		} else if (choose == 22) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('TOS', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 24 || choose == 25) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Prefix', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 3;
		} else if (choose == 26) {
			$('#sortfield').append($('<option>', { value: 1, text: '<?php print __('Source Prefix', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 2, text: '<?php print __('Destination Prefix', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 3, text: '<?php print __('Flows', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 4, text: '<?php print __('Bytes', 'flowview');?>'}));
			$('#sortfield').append($('<option>', { value: 5, text: '<?php print __('Packets', 'flowview');?>'}));

			defsort = 4;
		}

		if (choose != '0' && choose != '99') {
			if (statreport == choose) {
				$('#sortfield').val(sortfield).selectmenu('refresh');
			} else {
				$('#sortfield').val(defsort).selectmenu('refresh');
			}

			$('#printed').val('0').selectmenu('refresh');
		}
	}

	var sortfield='<?php print get_request_var('sortfield'); ?>';
	var statreport='<?php print (get_request_var('statistics') > 0 ? get_request_var('statistics') : 0); ?>';

	</script>

	<?php
}

