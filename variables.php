<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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
#  start_date           Start date of analysis period
#  start_time           Start time of analysis period
#  end_date             End date of analysis period
#  end_time             End time of analysis period
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



	$query = '';
	if (isset($_REQUEST['query']) && $_REQUEST['query'] != '' && is_numeric($_REQUEST['query']) && isset($_REQUEST['action']) && !isset($_REQUEST['action2_x']) && ($_REQUEST['action'] == 'view' || $_REQUEST['action'] == 'loadquery')) {
		$query = $_REQUEST['query'];
		$q = db_fetch_row("SELECT * FROM plugin_flowview_queries WHERE id = $query");
		$_POST['query'] = $query = $q['name'];
		$_POST['device_name'] = $device = $q['device'];
		$_POST['start_date'] = $start_date = strtoupper($q['startdate']);
		$_POST['start_time'] = $start_time = strtoupper($q['starttime']);
		$_POST['tos_fields'] = $tos_fields = $q['tosfields'];
		$_POST['end_date'] = $end_date = strtoupper($q['enddate']);
		$_POST['end_time'] = $end_time = strtoupper($q['endtime']);
		$_POST['tcp_flags'] = $tcp_flags = $q['tcpflags'];
		$_POST['protocols'] = $protocols = $q['protocols'];
		$_POST['source_address'] = $source_address = $q['sourceip'];
		$_POST['source_port'] = $source_port = $q['sourceport'];
		$_POST['source_if'] = $source_if = $q['sourceinterface'];
		$_POST['source_as'] = $source_as = $q['sourceas'];
		$_POST['dest_address'] = $dest_address = $q['destip'];
		$_POST['dest_port'] = $dest_port = $q['destport'];
		$_POST['dest_if'] = $dest_if = $q['destinterface'];
		$_POST['dest_as'] = $dest_as = $q['destas'];
		$_POST['sort_field'] = $sort_field = $q['sortfield'];
		$_POST['cutoff_lines'] = $cutoff_lines = $q['cutofflines'];
		$_POST['cutoff_octets'] = $cutoff_octets = $q['cutoffoctets'];
		$_POST['action'] = $action = $_REQUEST['action'];
		$_POST['stat_report'] = $stat_report = $q['statistics'];
		$_POST['flow_select'] = $flow_select = $q['includeif'];
		$_POST['print_report'] = $print_report = $q['printed'];
		$_POST['resolve_addresses'] = $resolve_addresses = $q['resolve'];
	} else {

	$device = '';
	if (isset($_POST['device_name']))
		$device = $_POST['device_name'];

	$start_date = ''; // date("n/j/Y",time() - 28800);
	if (isset($_POST['start_date']))
		$start_date = strtoupper($_POST['start_date']);

	$start_time = '-8 HOURS'; // date("G:i",time() - 28800);
	if (isset($_POST['start_time']))
		$start_time = strtoupper($_POST['start_time']);

	$tos_fields = '';
	if (isset($_POST['tos_fields']))
		$tos_fields = $_POST['tos_fields'];

	$end_date = ''; // date("n/j/Y",time());
	if (isset($_POST['end_date']))
		$end_date = strtoupper($_POST['end_date']);

	$end_time = 'NOW'; // date("G:i",time());
	if (isset($_POST['end_time']))
		$end_time = strtoupper($_POST['end_time']);

	$tcp_flags = '';
	if (isset($_POST['tcp_flags']))
		$tcp_flags = $_POST['tcp_flags'];

	$protocols = '';
	if (isset($_POST['protocols']))
		$protocols = $_POST['protocols'];

	$source_address = '';
	if (isset($_POST['source_address']))
		$source_address = $_POST['source_address'];

	$source_port = '';
	if (isset($_POST['source_port']))
		$source_port = $_POST['source_port'];

	$source_if = '';
	if (isset($_POST['source_if']))
		$source_if = $_POST['source_if'];

	$source_as = '';
	if (isset($_POST['source_as']))
		$source_as = $_POST['source_as'];

	$dest_address = '';
	if (isset($_POST['dest_address']))
		$dest_address = $_POST['dest_address'];

	$dest_port = '';
	if (isset($_POST['dest_port']))
		$dest_port = $_POST['dest_port'];

	$dest_if = '';
	if (isset($_POST['dest_if']))
		$dest_if = $_POST['dest_if'];

	$dest_as = '';
	if (isset($_POST['dest_as']))
		$dest_as = $_POST['dest_as'];

	$sort_field = 4;
	if (isset($_POST['sort_field']))
		$sort_field = $_POST['sort_field'];

	$cutoff_lines = 100;
	if (isset($_POST['cutoff_lines']))
		$cutoff_lines = $_POST['cutoff_lines'];

	$cutoff_octets = '';
	if (isset($_POST['cutoff_octets']))
		$cutoff_octets = $_POST['cutoff_octets'];

	$action = '';
	if (isset($_POST['action']))
		$action = $_POST['action'];

	$stat_report = '0';
	if (isset($_POST['stat_report']))
		$stat_report = $_POST['stat_report'];

	$flow_select = '1';
	if (isset($_POST['flow_select']))
		$flow_select = $_POST['flow_select'];

	$print_report = '0';
	if (isset($_POST['print_report']))
		$print_report = $_POST['print_report'];

	$resolve_addresses = 'Y';
	if (isset($_POST['resolve_addresses']))
		$resolve_addresses = $_POST['resolve_addresses'];

	}
?>