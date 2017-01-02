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

/*
#  Input arguments (received from the form):
#  Name                 Description
#  -----------------------------------------------------------------------
#  device               An identifying name of the device (e.g. router1)
#  flow_select          Identifies which flows to include wrt time period
#  timespan             Using a Timespan instaed of start and end times
#  date1                Start date of analysis period
#  date2                End date of analysis period
#  source_addresses     Constrain flows examined to these source IP addresses
#  source_ports         Constrain flows examined to these source ports
#  source_ifs           Constrain flows examined to these input interfaces
#  source_ases          Constrain flows examined to these source ASes
#  dest_addresses       Constrain flows examined to these dest. IP addresses
#  dest_ports           Constrain flows examined to these dest. ports
#  dest_ifs             Constrain flows examined to these output interfaces
#  dest_ases            Constrain flows examined to these dest. ASes
#  tos_fields           Constrain flows examined by specified TOS field values
#  tcp_flags            Constrain flows examined by specified TCP flag values
#  protocols            Constrain flows examined to these protocols
#  print_report         Select from these various report options
#  stat_report          Select from these various statistics options
#  cutoff_lines         Number of report lines to print out
#  cutoff_octets        Minimum number of octets for inclusion in report
#  sort_field           Which report column to sort lines upon
#  resolve_addresses    Whether or not to resolve IP addresses
*/

global $config;

include_once($config['base_path'] . '/lib/time.php');

$query = '';

if (isset_request_var('query') && get_filter_request_var('query') > 0 && get_request_var('action') == 'loadquery') {
	$query = get_filter_request_var('query');
	$q     = db_fetch_row("SELECT * FROM plugin_flowview_queries WHERE id=$query");
	$_POST['query']               = $query             = $q['name'];
	$_POST['device_name']         = $device            = $q['device'];

	$_POST['predefined_timespan'] = $timespan        = $q['timespan'];
	if ($timespan == 0) {
		$_POST['date1']           = $date1             = strtoupper($q['startdate']);
		$_POST['date2']           = $date2             = strtoupper($q['enddate']);
	}else{
		$span = array();
		get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
		$_POST['date1']           = $date1             = $span['current_value_date1'];
		$_POST['date2']           = $date2             = $span['current_value_date2'];
	}

	$_POST['tos_fields']          = $tos_fields        = $q['tosfields'];
	$_POST['tcp_flags']           = $tcp_flags         = $q['tcpflags'];
	$_POST['protocols']           = $protocols         = $q['protocols'];
	$_POST['source_address']      = $source_address    = $q['sourceip'];
	$_POST['source_port']         = $source_port       = $q['sourceport'];
	$_POST['source_if']           = $source_if         = $q['sourceinterface'];
	$_POST['source_as']           = $source_as         = $q['sourceas'];
	$_POST['dest_address']        = $dest_address      = $q['destip'];
	$_POST['dest_port']           = $dest_port         = $q['destport'];
	$_POST['dest_if']             = $dest_if           = $q['destinterface'];
	$_POST['dest_as']             = $dest_as           = $q['destas'];
	$_POST['sort_field']          = $sort_field        = $q['sortfield'];
	$_POST['cutoff_lines']        = $cutoff_lines      = $q['cutofflines'];
	$_POST['cutoff_octets']       = $cutoff_octets     = $q['cutoffoctets'];
	$_POST['action']              = $action            = $_REQUEST['action'];
	$_POST['stat_report']         = $stat_report       = $q['statistics'];
	$_POST['flow_select']         = $flow_select       = $q['includeif'];
	$_POST['print_report']        = $print_report      = $q['printed'];
	$_POST['resolve_addresses']   = $resolve_addresses = $q['resolve'];
} else {
	$device = '';
	if (isset_request_var('device_name')) {
		$device = get_nfilter_request_var('device_name');
	}else{
		$device = db_fetch_cell("SELECT folder FROM plugin_flowview_devices ORDER BY id LIMIT 1");;
		$_POST['device_name'] = $device;
	}

	$timespan = 0;
	if (isset_request_var('predefined_timespan') && get_filter_request_var('predefined_timespan') > 0) {
		$timespan = get_request_var('predefined_timespan');
		set_request_var('predefined_timespan', $timespan);
	
		$span = array();
		get_timespan($span, time(), $timespan, read_user_setting('first_weekdayid'));
		$_POST['date1'] = $date1 = $span['current_value_date1'];
		$_POST['date2'] = $date2 = $span['current_value_date2'];
	}else{
		set_request_var('predefined_timespan', '0');
		$timespan = 0;

		$date1 = date('Y-m-d H:i:s', time() - (8 * 3600)); 
		if (isset_request_var('date1')) {
			$date1 = get_nfilter_request_var('date1');
		}

		$date2 = date('Y-m-d H:i:s');
		if (isset_request_var('date2')) {
			$date2 = get_nfilter_request_var('date2');
		}
	}

	$tos_fields = '';
	if (isset_request_var('tos_fields')) {
		$tos_fields = get_nfilter_request_var('tos_fields');
	}

	$tcp_flags = '';
	if (isset_request_var('tcp_flags')) {
		$tcp_flags = get_nfilter_request_var('tcp_flags');
	}

	$protocols = '';
	if (isset_request_var('protocols')) {
		$protocols = get_nfilter_request_var('protocols');
	}

	$source_address = '';
	if (isset_request_var('source_address')) {
		$source_address = get_nfilter_request_var('source_address');
	}

	$source_port = '';
	if (isset_request_var('source_port')) {
		$source_port = get_nfilter_request_var('source_port');
	}

	$source_if = '';
	if (isset_request_var('source_if')) {
		$source_if = get_nfilter_request_var('source_if');
	}

	$source_as = '';
	if (isset_request_var('source_as')) {
		$source_as = get_nfilter_request_var('source_as');
	}

	$dest_address = '';
	if (isset_request_var('dest_address')) {
		$dest_address = get_nfilter_request_var('dest_address');
	}

	$dest_port = '';
	if (isset_request_var('dest_port')) {
		$dest_port = get_nfilter_request_var('dest_port');
	}

	$dest_if = '';
	if (isset_request_var('dest_if')) {
		$dest_if = get_nfilter_request_var('dest_if');
	}

	$dest_as = '';
	if (isset_request_var('dest_as')) {
		$dest_as = get_nfilter_request_var('dest_as');
	}

	$sort_field = 4;
	if (isset_request_var('sort_field')) {
		$sort_field = get_nfilter_request_var('sort_field');
	}

	$cutoff_lines = 100;
	if (isset_request_var('cutoff_lines')) {
		$cutoff_lines = get_nfilter_request_var('cutoff_lines');
	}

	$cutoff_octets = '';
	if (isset_request_var('cutoff_octets')) {
		$cutoff_octets = get_nfilter_request_var('cutoff_octets');
	}

	$action = '';
	if (isset_request_var('action')) {
		$action = get_nfilter_request_var('action');
	}

	$stat_report = '0';
	if (isset_request_var('stat_report')) {
		$stat_report = get_nfilter_request_var('stat_report');
	}

	$flow_select = '1';
	if (isset_request_var('flow_select')) {
		$flow_select = get_nfilter_request_var('flow_select');
	}

	$print_report = '0';
	if (isset_request_var('print_report')) {
		$print_report = get_nfilter_request_var('print_report');
	}

	$resolve_addresses = 'Y';
	if (isset_request_var('resolve_addresses')) {
		$resolve_addresses = get_nfilter_request_var('resolve_addresses');
	}
}

?>
