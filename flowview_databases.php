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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

chdir('../../');
include('./include/auth.php');
include_once('./plugins/flowview/functions.php');
include_once('./plugins/flowview/database.php');

flowview_connect();

$actions = array(
	1 => __('Delete', 'flowview'),
);

/* set default action */
set_default_action();

switch (get_request_var('action')) {
	case 'actions':
		form_actions();

		break;
	case 'database_details':
		flowview_get_item_details();

		break;
	case 'purge':
		flowview_db_execute('TRUNCATE plugin_flowview_dnscache');
		raise_message('flowview_dns_purge', __('DNS Cache has been purged.  It will refill as records come in.', 'flowview'), MESSAGE_LEVEL_INFO);
	default:
		top_header();

		view_databases();

		bottom_footer();
		break;
}

function flowview_get_item_details() {
	global $config, $db_tabs;
	global $graph_timeshifts, $graph_timespans, $graph_heights;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	$cols  = get_all_columns();
	$tab   = get_request_var('tab');
	$ids   = json_decode(base64_decode(str_replace('line_', '', get_request_var('id'))), true);
	$table = "plugin_flowview_irr_$tab";

	$sql_where  = '';
	$sql_params = array();

	if (cacti_sizeof($ids)) {
		foreach($ids as $col => $value) {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . "$col = ?";
			$sql_params[] = $value;
		}
	} else {
		return false;
	}

	$details = flowview_db_fetch_row_prepared("SELECT *
		FROM $table
		$sql_where
		LIMIT 1", $sql_params);

	ob_start();

	$mnt_by_present = false;

	html_start_box(__('Internet Route Registry Details', 'flowview'), '100%', '', '3', 'center', '');

	$response = flowview_print_details($cols, $details);

	html_end_box();

	if ($response['mnt_by_present'] == true) {
		$sql_params   = array();
		$sql_params[] = $response['mnt_by'];
		$sql_params[] = $response['mnt_by_source'];

		$details = flowview_db_fetch_row_prepared("SELECT *
			FROM plugin_flowview_irr_mntner
			WHERE mntner = ?
			AND source = ?
			LIMIT 1", $sql_params);

		if (cacti_sizeof($details)) {
			html_start_box(__('Authorized Agent Details', 'flowview'), '100%', '', '3', 'center', '');

			flowview_print_details($cols, $details);

			html_end_box();
		}
	}

	ob_get_flush();
}

function flowview_print_details(&$cols, &$details) {
	$mnt_by_present = false;
	$mnt_by         = '';
	$mnt_by_source  = '';

	if (cacti_sizeof($details)) {
		foreach($details as $column => $value) {
			if ($column == 'mnt_by' && $value != '') {
				$mnt_by_present = true;
				$mnt_by = $value;
			}

			if ($column == 'source') {
				$mnt_by_source = $value;
			}

			/* get rid of some nagging hypens */
			$value = str_replace("------------------------------------------\n", '', $value);

			if ($value != '' && $value != '0000-00-00 00:00:00' && $column != 'present') {
				print '<tr>';

				if ($column == 'as_set') {
					$value = strtoupper($value);
				}

				if (isset($cols[$column])) {
					print '<td style="width:18%;font-weight:bold;vertical-align:text-top">' . $cols[$column]['display'] . ':</td>';
				} else {
					print '<td style="width:18%;font-weight:bold;vertical-align:text-top">' . ucfirst(str_replace('_', ' ', $column)) . ':</td>';
				}

				if ($column == 'remarks' ||
					$column == 'import' ||
					$column == 'export' ||
					$column == 'mp_import' ||
					$column == 'mp_export') {
					print '<td style="width:82%">' . str_replace("\n", '<br>', html_escape($value)) . '</td>';
				} else {
					print '<td style="width:82%;white-space:pre-wrap">' . str_replace("\n", ', ', html_escape($value)) . '</td>';
				}

				print '</tr>';
			}
		}
	} else {
		print "<tr><td><em>No Details Found</em></td></tr>";
	}

	return array('mnt_by_present' => $mnt_by_present, 'mnt_by' => $mnt_by, 'mnt_by_source' => $mnt_by_source);
}

function view_databases() {
	global $config, $actions, $item_rows, $db_tabs;
	global $graph_timeshifts, $graph_timespans, $graph_heights;

	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (!isset_request_var('tab')) {
		if (isset($_SESSION['sess_fv_db_tab'])) {
			set_request_var('tab', $_SESSION['sess_fv_db_tab']);
		} else {
			set_request_var('tab', 'dns_cache');
		}
	}

	$_SESSION['sess_fv_db_tab'] = get_request_var('tab');

	display_db_tabs($db_tabs);

	if (get_request_var('tab') == 'dns_cache') {
		view_dns_cache();
	} elseif (get_request_var('tab') == 'route') {
		view_routes();
	} else {
		view_db_table(get_request_var('tab'), $db_tabs);
	}
}

function get_all_columns() {
	$display_text = array();

	$columns = array(
		'route' => array(
			'display' => __esc('CIDR/Route', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'aut_num'=> array(
			'display' => __esc('Autonomous Number', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'as_block' => array(
			'display' => __esc('AS Block', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'as_set' => array(
			'display' => __esc('AS Set', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'route_set' => array(
			'display' => __esc('Route Set', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'domain' => array(
			'display' => __esc('Domain', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'filter_set' => array(
			'display' => __esc('Filter Set', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'peering_set' => array(
			'display' => __esc('Peering Set', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'inetnum' => array(
			'display' => __esc('Network', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'inet_rtr' => array(
			'display' => __esc('Internet Router', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'irt' => array(
			'display' => __esc('Incident Response Team', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mntner' => array(
			'display' => __esc('Auth Agent', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'organisation' => array(
			'display' => __esc('Organization', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'role' => array(
			'display' => __esc('Role', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'person' => array(
			'display' => __esc('Person', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'rtr_set' => array(
			'display' => __esc('Router Set', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'remarks' => array(
			'display' => __esc('Remarks', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'last_modified' => array(
			'display' => __esc('Last Modified', 'flowview'),
			'align'   => 'right',
			'order'   => 'ASC'
		),
		'source' => array(
			'display' => __esc('Source', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'created' => array(
			'display' => __esc('Registration Date', 'flowview'),
			'align'   => 'right',
			'order'   => 'ASC'
		),
		'mnt_by' => array(
			'display' => __esc('MNT By', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'admin_c' => array(
			'display' => __esc('Admin Contact', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'org' => array(
			'display' => __esc('Organization', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'changed' => array(
			'display' => __esc('Changes', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'notify' => array(
			'display' => __esc('Notify', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'descr' => array(
			'display' => __esc('Description', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'tech_c' => array(
			'display' => __esc('Tech Contact', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_lower' => array(
			'display' => __esc('MNT Lower', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'country' => array(
			'display' => __esc('Country', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'abuse_mailbox' => array(
			'display' => __esc('Abuse Email', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'fax_no' => array(
			'display' => __esc('Fax Number', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'phone' => array(
			'display' => __esc('Phone', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'e_mail' => array(
			'display' => __esc('Email', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'address' => array(
			'display' => __esc('Address', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'status' => array(
			'display' => __esc('Status', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mbrs_by_ref' => array(
			'display' => __esc('Members By Fef', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'abuse_c' => array(
			'display' => __esc('Abuse Contacts', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_ref' => array(
			'display' => __esc('MNT Reference', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'members' => array(
			'display' => __esc('Members', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_nfy' => array(
			'display' => __esc('MNT Notify', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'auth' => array(
			'display' => __esc('Authorization', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_routes' => array(
			'display' => __esc('MNT Routes', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'member_of' => array(
			'display' => __esc('Member of', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'upd_to' => array(
			'display' => __esc('Updates Email', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'nic_hdl' => array(
			'display' => __esc('NOC Handle', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'sponsoring_org' => array(
			'display' => __esc('Sponsor Org', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_irt' => array(
			'display' => __esc('MNT Irt', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_members' => array(
			'display' => __esc('MP Members', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'sub_dom' => array(
			'display' => __esc('Sub Domain', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'pingable' => array(
			'display' => __esc('Pingable', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'alias' => array(
			'display' => __esc('Alias', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'org_type' => array(
			'display' => __esc('Org Type', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'refer' => array(
			'display' => __esc('Refer To', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_peering' => array(
			'display' => __esc('MP Peering', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'export' => array(
			'display' => __esc('Export', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'holes' => array(
			'display' => __esc('Holes', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_default' => array(
			'display' => __esc('MP Default', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'components' => array(
			'display' => __esc('Components', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'rs_out' => array(
			'display' => __esc('RS Out', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'org_name' => array(
			'display' => __esc('Organization Name', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'dom_net' => array(
			'display' => __esc('Network', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'as_name' => array(
			'display' => __esc('AS Name', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'author' => array(
			'display' => __esc('Author', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'irt_nfy' => array(
			'display' => __esc('IRT Notify', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'aggr_mtd' => array(
			'display' => __esc('Aggr MTD', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'type' => array(
			'display' => __esc('AS Type', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_filter' => array(
			'display' => __esc('MP Filter', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'default' => array(
			'display' => __esc('Default Route', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'assignment_size' => array(
			'display' => __esc('Assignment Size', 'flowview'),
			'align'   => 'right',
			'order'   => 'DESC'
		),
		'export_comps' => array(
			'display' => __esc('Export Comps', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'rs_in' => array(
			'display' => __esc('RS In', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'netname' => array(
			'display' => __esc('Net Name', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'ds_rdata' => array(
			'display' => __esc('DS Rdata', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'aggr_bndry' => array(
			'display' => __esc('Aggr Boundary', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'filter' => array(
			'display' => __esc('Filter', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_import' => array(
			'display' => __esc('MP Import', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'language' => array(
			'display' => __esc('Language', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'roa_uri' => array(
			'display' => __esc('RESFful Link', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'peer' => array(
			'display' => __esc('Peer', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'zone_c' => array(
			'display' => __esc('Zone Contact', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'inject' => array(
			'display' => __esc('Inject', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_peer' => array(
			'display' => __esc('MP Peer', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'referral_by' => array(
			'display' => __esc('Referral By', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'form' => array(
			'display' => __esc('Form', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mnt_domains' => array(
			'display' => __esc('MNT Domains', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'origin' => array(
			'display' => __esc('Origin AS', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'mp_export' => array(
			'display' => __esc('MP Export', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'encryption' => array(
			'display' => __esc('Encyption', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'geofeed' => array(
			'display' => __esc('Geo Feed', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'geoidx' => array(
			'display' => __esc('Geo Index', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'ifaddr' => array(
			'display' => __esc('Interface Addr', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'export_via' => array(
			'display' => __esc('Export Via', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'trouble' => array(
			'display' => __esc('Trouble', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'nserver' => array(
			'display' => __esc('Name Servers', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'ping_hdl' => array(
			'display' => __esc('Ping Handle', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'interface' => array(
			'display' => __esc('Interface', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'import' => array(
			'display' => __esc('Import', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'signature' => array(
			'display' => __esc('Signature', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'geoloc' => array(
			'display' => __esc('Geo Location', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'local_as' => array(
			'display' => __esc('Local AS', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		),
		'import_via' => array(
			'display' => __esc('Import Via', 'flowview'),
			'align'   => 'left',
			'order'   => 'ASC'
		)
	);

	$display_text = array_merge($display_text, $columns);

	return $display_text;
}


function view_db_table($tab, &$tabs) {
	global $item_rows;

	$display_text = get_all_columns();

	$table_det     = $tabs[$tab];
	$columns       = array_map('trim', explode(',', $table_det['columns']));
	$search        = array_map('trim', explode(',', $table_det['search']));
	$filter        = array_map('trim', explode(',', $table_det['filter']));
	$rowid         = array_map('trim', explode(',', $table_det['rowid']));
	$table_name    = "plugin_flowview_irr_$tab";
	$odisplay_text = array();

	/* create display text for column */
	if (isset($tabs[$tab])) {
		$i = 0;

		foreach($columns as $key => $c) {
			$c = trim($c);

			if ($i == 0) {
				$default_column    = $c;

				if (isset($display_text[$c]['sort'])) {
					$default_direction = $display_text[$c]['sort'];
				} else {
					$default_direction = 'ASC';
				}
			}

			if (isset($display_text[$c])) {
				$odisplay_text[$c] = $display_text[$c];
			}

			$i++;
		}
	} else {
		print __esc("FATAL: Unknown Database Table %s", $tab);
		exit;
	}

	//print "Default Column: $default_column, Default Direction $default_direction";exit;

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
		'source' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '-1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => $default_column,
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => $default_direction,
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fv_db_' . $tab);
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1' || isempty_request_var('rows')) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	$sql_where  = '';
	$sql_params = array();
	$sql_order  = get_order_string();
	$sql_limit  = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	/* sort naturally if the orgin is in the sort */
	$natural_columns = array(
		'origin',
		'route',
		'aut_num',
		'as_block',
		'as_net'
	);

	foreach($natural_columns as $c) {
		if (strpos($sql_order, "`$c` ") !== false) {
			$sql_order = str_replace("`$c` ", "NATURAL_SORT_KEY(`$c`) ", $sql_order);
		}
	}

	if (get_request_var('source') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'source = ?';
		$sql_params[] = get_request_var('source');
	}

	if (get_request_var('filter') != '') {
		if (cacti_sizeof($search)) {
			foreach($search as $index => $column) {
				$column = '`' . trim($column) . '`';

				if ($index == 0) {
					$sql_where .= ($sql_where != '' ? ' OR (':'WHERE (') . "$column LIKE ?";
				} else {
					$sql_where .= ' OR ' . "$column LIKE ?";
				}

				$sql_params[] = '%' . get_request_var('filter') . '%';
			}

			$sql_where .= ')';
		}
	}

	$results = flowview_db_fetch_assoc_prepared("SELECT *
		FROM $table_name
		$sql_where
		$sql_order
		$sql_limit",
		$sql_params);

	$total_rows = flowview_db_fetch_cell_prepared("SELECT COUNT(*)
		FROM $table_name
		$sql_where",
		$sql_params);


	html_start_box($table_det['name'], '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form' action='flowview_databases.php&tab=dns_cache'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Source', 'flowview');?>
					</td>
					<td>
						<select id='source' name='source' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('source') == '-1' ? ' selected>':'>') . __('Any', 'flowview');?></option>
							<?php
							$sources = array_rekey(
								flowview_db_fetch_assoc("SELECT DISTINCT source
									FROM plugin_flowview_irr_$tab
									ORDER BY source"),
								'source', 'source'
							);

							if (cacti_sizeof($sources) > 0) {
								foreach ($sources as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('source') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Entries', 'flowview');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'flowview');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge', 'flowview');?>' title='<?php print __esc('Purge the DNS Cache', 'flowview');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			var tab='<?php print $tab;?>';
			var myTimer;

			function applyFilter() {
				strURL  = 'flowview_databases.php?header=false';
				strURL += '&tab='      + tab;
				strURL += '&filter='   + $('#filter').val();
				strURL += '&source='   + $('#source').val();
				strURL += '&rows='     + $('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'flowview_databases.php?tab='+tab+'&clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			function purgeFilter() {
				strURL = 'flowview_databases.php?tab='+tab+'&action=purge&header=false';
				loadPageNoHeader(strURL);
			}

			function initializeTips() {
				// Servers need tooltips
				$('table[id^="flowview_databases_"]').tooltip({
					items: 'tr.selectable',
					open: function(event, ui) {
						if (typeof(event.originalEvent) == 'undefined') {
							return false;
						}

						var id = $(ui.tooltip).attr('id');

						$('div.ui-tooltip').not('#'+ id).remove();

						$('#'+id).tooltip();

						ui.tooltip.position({
							my: 'left+20 top',
							at: 'right+15 center',
							of: event
						});

						ui.tooltip.css('width', '800px');
						ui.tooltip.css('max-width', '800px');
						ui.tooltip.css('height', '400px');
						ui.tooltip.css('max-height', '400px');
						ui.tooltip.css('overflow-y', 'scroll');
					},
					close: function(event, ui) {
						ui.tooltip.hover(
							function () {
								$(this).stop(true).fadeTo(400, 1);
							},
							function() {
								$(this).fadeOut('400', function() {
								$(this).remove();
							});
						});
					},
					position: {my: "left:15 top", at: "right center", of:self},
					content: function(callback) {
						var id = $(this).attr('id');

						$.get('flowview_databases.php?action=database_details&tab='+tab+'&id='+id, function(data) {
							callback(data);
						});
					}
				});
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#purge').click(function() {
					purgeFilter();
				});

				$('#form').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});

				initializeTips();
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$nav = html_nav_bar("flowview_databases.php?tab={$tab}&filter=" . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, cacti_sizeof($odisplay_text), __('Entries', 'flowview'), 'page', 'main');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	html_header_sort($odisplay_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, "flowview_databases.php?tab=$tab");

	$i = 0;
	if (cacti_sizeof($results)) {
		foreach($results as $result) {
			$id = array();
			foreach($rowid as $col) {
				$id[$col] = $result[$col];
			}

			$rid = base64_encode(json_encode($id));

			form_alternate_row('line_' . $rid, false);

			foreach($columns as $c) {
				$align = $odisplay_text[$c]['align'];

				if ($c == 'descr' ||
					$c == 'remarks' ||
					$c == 'members' ||
					$c == 'filter' ||
					$c == 'netname') {
					$align = 'white-space:pre-wrap;text-align:left';
					$result[$c] = str_replace("\n", ', ', $result[$c]);
				}

				if ($c == 'as_set' || $c == 'mnt_by' || $c == 'local_as') {
					$result[$c] = strtoupper($result[$c]);
				}

				if (in_array($c, $search, true)) {
					form_selectable_cell(filter_value($result[$c], get_request_var('filter')), $i, '', $align);
				} else {
					if ($c == 'last_modified' || $c == 'created') {
						if ($result[$c] == '0000-00-00 00:00:00') {
							$value = __('Not Specified', 'flowview');
						} else {
							$value = $result[$c];
						}

						form_selectable_cell($value, $i, '', $align);
					} else {
						form_selectable_cell($result[$c], $i, '', $align);
					}
				}
			}

			form_end_row();
			$i++;
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($odisplay_text)) . "'><em>" . __('No Matching Entries Found', 'flowview') . "</em></td></tr>";
	}

	html_end_box(false);

	if (cacti_sizeof($results)) {
		print $nav;
	}

	/* location for tooltips */
	print "<div id='database' class='database' style='width:1024px'></div>";
}

function form_actions() {
	global $actions;

	/* ================= input validation ================= */
	get_filter_request_var('drp_action', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z0-9_]+)$/')));
	/* ==================================================== */

	/* if we are to save this form, instead of display it */
	if (isset_request_var('selected_items')) {
		$selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

		if ($selected_items != false) {
			if (get_nfilter_request_var('drp_action') == '1') { /* delete */
				flowview_db_execute('DELETE FROM plugin_flowview_dnscache WHERE ' . array_to_sql_or($selected_items, 'id'));
			}
		}

		header('Location: flowview_databases.php?tab=dns_cache&header=false');
		exit;
	}

	/* setup some variables */
	$dns_list = '';
	$dns_array = array();

	/* loop through each of the graphs selected on the previous page and get more info about them */
	foreach ($_POST as $var => $val) {
		if (preg_match('/^chk_([0-9]+)$/', $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */

			$dns_list .= '<li>' . html_escape(flowview_db_fetch_cell_prepared('SELECT CONCAT(host, "(", ip, ")") AS name FROM plugin_flowview_dnscache WHERE id = ?', array($matches[1]))) . '</li>';
			$dns_array[] = $matches[1];
		}
	}

	top_header();

	form_start('flowview_databases.php?tab=dns_cache');

	html_start_box($actions[get_nfilter_request_var('drp_action')], '60%', '', '3', 'center', '');

	if (isset($dns_array) && cacti_sizeof($dns_array)) {
		if (get_nfilter_request_var('drp_action') == '1') { /* delete */
			print "<tr>
				<td class='textArea'>
					<p>" . __n('Click \'Continue\' to delete the following DNS Cache Entriy.', 'Click \'Continue\' to delete all following DNS Cache Entries.', cacti_sizeof($dns_array), 'flowview') . "</p>
					<div class='itemlist'><ul>$dns_list</ul></div>
				</td>
			</tr>\n";

			$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel', 'flowview') . "' onClick='cactiReturnTo()'>&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue', 'flowview') . "' title='" . __n('Delete DNS Entry', 'Delete DNS Entries', cacti_sizeof($dns_array), 'flowview') . "'>";
		}
	} else {
		raise_message(40);
		header('Location: flowview_databases.php?tab=dns_cache&header=false');
		exit;
	}

	print "<tr>
		<td class='saveRow'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($dns_array) ? serialize($dns_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . html_escape(get_nfilter_request_var('drp_action')) . "'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function view_dns_cache() {
	global $actions, $item_rows;

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
		'source' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '-1'
		),
		'verified' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '-1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'host',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fv_dnscache');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1') {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Flowview DNS Cache Entries', 'flowview'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form' action='flowview_databases.php&tab=dns_cache'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('Verified', 'flowview');?>
					</td>
					<td>
						<select id='verified' name='verified' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('verified') == '-1' ? ' selected>':'>') . __('Any', 'flowview');?></option>
							<option value='0'<?php print (get_request_var('verified') == '0' ? ' selected>':'>') . __('Unverified', 'flowview');?></option>
							<option value='1'<?php print (get_request_var('verified') == '1' ? ' selected>':'>') . __('Verified', 'flowview');?></option>
						</select>
					</td>
					<td>
						<?php print __('Source', 'flowview');?>
					</td>
					<td>
						<select id='source' name='source' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('source') == '-1' ? ' selected>':'>') . __('Any', 'flowview');?></option>
							<?php
							$sources = array_rekey(
								flowview_db_fetch_assoc('SELECT DISTINCT source
									FROM plugin_flowview_dnscache
									ORDER BY source'),
								'source', 'source'
							);

							if (cacti_sizeof($sources) > 0) {
								foreach ($sources as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('source') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Entries', 'flowview');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'flowview');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='purge' value='<?php print __esc('Purge', 'flowview');?>' title='<?php print __esc('Purge the DNS Cache', 'flowview');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'flowview_databases.php?header=false';
				strURL += '&tab=dns_cache';
				strURL += '&filter='+$('#filter').val();
				strURL += '&source='+$('#source').val();
				strURL += '&verified='+$('#verified').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'flowview_databases.php?tab=dns_cache&clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			function purgeFilter() {
				strURL = 'flowview_databases.php?tab=dns_cache&action=purge&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#purge').click(function() {
					purgeFilter();
				});

				$('#form').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (host LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR ip LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR source LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('source') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' source = ' . db_qstr(get_request_var('source'));
	}

	if (get_request_var('verified') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' arin_verified = ' . db_qstr(get_request_var('verified'));
	}

	$total_rows = flowview_db_fetch_cell("SELECT COUNT(*)
		FROM plugin_flowview_dnscache AS dc
		LEFT JOIN plugin_flowview_arin_information AS ai
		ON dc.arin_id = ai.id
		$sql_where");

	$sql_order = get_order_string();

	/* sort naturally if the IP is in the sort */
	if (strpos($sql_order, 'ip ') !== false) {
		$sql_order = str_replace('ip ', 'NATURAL_SORT_KEY(ip) ', $sql_order);
	}

	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$dns_cache = flowview_db_fetch_assoc("SELECT dc.*, ai.origin
		FROM plugin_flowview_dnscache AS dc
		LEFT JOIN plugin_flowview_arin_information AS ai
		ON dc.arin_id = ai.id
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('flowview_databases.php?tab=dns_cache&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Entries', 'flowview'), 'page', 'main');

	form_start('flowview_databases.php?tab=dns_cache', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'ip' => array(
			'display' => __('IP Address', 'flowview'),
			'align'   => 'left',
			'tip'     => __('This is the IP Address of the Cache entry.', 'flowview')
		),
		'host' => array(
			'display' => __('DNS Hostname', 'flowview'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The DNS Name assigned to the IP Address.', 'flowview')
		),
		'source' => array(
			'display' => __('Source', 'flowview'),
			'align'   => 'left',
			'sort'    => 'DESC',
			'tip'     => __('The source of the DNS Hostname.  It can either be DNS, Static Lookup or ARIN.', 'flowview')
		),
		'arin_verified' => array(
			'display' => __('Arin Verified', 'flowview'),
			'align'   => 'left',
			'sort'    => 'DESC',
			'tip'     => __('The Arin information for this IP Address is verified, or it\'s a Local Domain IP Address.', 'flowview')
		),
		'arin_id' => array(
			'display' => __('CIDR ID', 'flowview'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Arin primary key.  This is not official Arin information.', 'flowview')
		),
		'origin' => array(
			'display' => __('Autonomous System ID', 'flowview'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('The Arin Origin AS.  This is official Arin AS information.', 'flowview')
		),
		'time' => array(
			'display' => __('Updated Time', 'flowview'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip'     => __('This is the time that the DNS cache was entered or last updated.', 'flowview')
		)
	);

	html_header_sort_checkbox($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'flowview_databases.php?tab=dns_cache');

	$i = 0;
	if (cacti_sizeof($dns_cache)) {
		foreach ($dns_cache as $l) {
			form_alternate_row('line' . $l['id'], false);
			form_selectable_cell(filter_value($l['ip'], get_request_var('filter')), $l['id']);
			form_selectable_cell(filter_value($l['host'], get_request_var('filter')), $l['id']);
			form_selectable_cell($l['source'], $l['id']);
			form_selectable_cell($l['arin_verified'] == 1 ? __('Verified', 'flowview'):__('Unverified', 'flowview'), $l['id']);
			form_selectable_cell($l['arin_id'], $l['id'], '', 'right');
			form_selectable_cell($l['origin'], $l['id'], '', 'right');
			form_selectable_cell(date('Y-m-d H:i:s', $l['time']), $l['id'], '', 'right');
			form_checkbox_cell($l['host'], $l['id']);
			form_end_row();
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No DNS Cache Entries Found', 'flowview') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($dns_cache)) {
		print $nav;
	}

	/* draw the dropdown containing a list of available actions for this form */
	draw_actions_dropdown($actions);

	form_end();
}

function view_routes() {
	global $actions, $item_rows;

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
		'version' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'source' => array(
			'filter' => FILTER_DEFAULT,
			'pageset' => true,
			'default' => '-1'
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'route',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_fv_routes');
	/* ================= input validation ================= */

	if (get_request_var('rows') == '-1' || isempty_request_var('rows')) {
		$rows = read_config_option('num_rows_table');
	} else {
		$rows = get_request_var('rows');
	}

	html_start_box(__('Flowview Internet Routes', 'flowview'), '100%', '', '3', 'center', '');

	?>
	<tr class='even'>
		<td>
			<form id='form' action='flowview_databases.php?tab=route'>
			<table class='filterTable'>
				<tr>
					<td>
						<?php print __('Search', 'flowview');?>
					</td>
					<td>
						<input type='text' class='ui-state-default ui-corner-all' id='filter' name='filter' size='25' value='<?php print html_escape_request_var('filter');?>'>
					</td>
					<td>
						<?php print __('IP Version', 'flowview');?>
					</td>
					<td>
						<select id='version' name='verified' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('version') == '-1' ? ' selected>':'>') . __('Any', 'flowview');?></option>
							<option value='0'<?php print (get_request_var('version') == '0' ? ' selected>':'>') . __('IPv4', 'flowview');?></option>
							<option value='1'<?php print (get_request_var('version') == '1' ? ' selected>':'>') . __('IPv6', 'flowview');?></option>
						</select>
					</td>
					<td>
						<?php print __('Source', 'flowview');?>
					</td>
					<td>
						<select id='source' name='source' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('source') == '-1' ? ' selected>':'>') . __('Any', 'flowview');?></option>
							<?php
							$sources = array_rekey(
								flowview_db_fetch_assoc('SELECT DISTINCT source
									FROM plugin_flowview_irr_route
									ORDER BY source'),
								'source', 'source'
							);

							if (cacti_sizeof($sources)) {
								foreach ($sources as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('source') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<?php print __('Entries', 'flowview');?>
					</td>
					<td>
						<select id='rows' name='rows' onChange='applyFilter()'>
							<option value='-1'<?php print (get_request_var('rows') == '-1' ? ' selected>':'>') . __('Default', 'flowview');?></option>
							<?php
							if (cacti_sizeof($item_rows) > 0) {
								foreach ($item_rows as $key => $value) {
									print "<option value='" . $key . "'" . (get_request_var('rows') == $key ? ' selected':'') . '>' . html_escape($value) . '</option>';
								}
							}
							?>
						</select>
					</td>
					<td>
						<span>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='refresh' value='<?php print __esc('Go', 'flowview');?>' title='<?php print __esc('Set/Refresh Filters', 'flowview');?>'>
							<input type='button' class='ui-button ui-corner-all ui-widget' id='clear' value='<?php print __esc('Clear', 'flowview');?>' title='<?php print __esc('Clear Filters', 'flowview');?>'>
						</span>
					</td>
				</tr>
			</table>
			</form>
			<script type='text/javascript'>

			function applyFilter() {
				strURL  = 'flowview_databases.php?header=false';
				strURL += '&tab=route';
				strURL += '&filter='+$('#filter').val();
				strURL += '&version='+$('#version').val();
				strURL += '&source='+$('#source').val();
				strURL += '&verified='+$('#verified').val();
				strURL += '&rows='+$('#rows').val();
				loadPageNoHeader(strURL);
			}

			function clearFilter() {
				strURL = 'flowview_databases.php?tab=route&clear=1&header=false';
				loadPageNoHeader(strURL);
			}

			function purgeFilter() {
				strURL = 'flowview_databases.php?tab=route&action=purge&header=false';
				loadPageNoHeader(strURL);
			}

			$(function() {
				$('#refresh').click(function() {
					applyFilter();
				});

				$('#clear').click(function() {
					clearFilter();
				});

				$('#purge').click(function() {
					purgeFilter();
				});

				$('#form').submit(function(event) {
					event.preventDefault();
					applyFilter();
				});
			});

			</script>
		</td>
	</tr>
	<?php

	html_end_box();

	$sql_where = '';

	/* form the 'where' clause for our main sql query */
	if (get_request_var('filter') != '') {
		$sql_where = 'WHERE (remarks LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR mnt_by LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR admin_c LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR tech_c LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR member_of LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR origin LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR status LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR route LIKE ' . db_qstr('%' . get_request_var('filter') . '%') .
			' OR descr LIKE ' . db_qstr('%' . get_request_var('filter') . '%') . ')';
	}

	if (get_request_var('source') != '-1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' source = ' . db_qstr(get_request_var('source'));
	}

	if (get_request_var('version') == '1') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' route LIKE "%:%"';
	} elseif (get_request_var('version') == '0') {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . ' route NOT LIKE "%:%"';
	}

	$total_rows = flowview_db_fetch_cell("SELECT COUNT(*)
		FROM plugin_flowview_irr_route AS dc
		$sql_where");

	$sql_order = get_order_string();

	/* sort naturally if the orgin is in the sort */
	if (strpos($sql_order, '`origin` ') !== false) {
		$sql_order = str_replace('`origin` ', 'NATURAL_SORT_KEY(`origin`) ', $sql_order);
	}

	/* sort naturally if the route is in the sort */
	if (strpos($sql_order, '`route` ') !== false) {
		$sql_order = str_replace('route ', 'NATURAL_SORT_KEY(`route`) ', $sql_order);
	}

	$sql_limit = ' LIMIT ' . ($rows*(get_request_var('page')-1)) . ',' . $rows;

	$dns_cache = flowview_db_fetch_assoc("SELECT routes.*
		FROM plugin_flowview_irr_route AS routes
		$sql_where
		$sql_order
		$sql_limit");

	$nav = html_nav_bar('flowview_databases.php?tab=route&filter=' . get_request_var('filter'), MAX_DISPLAY_PAGES, get_request_var('page'), $rows, $total_rows, 8, __('Entries', 'flowview'), 'page', 'main');

	form_start('flowview_databases.php?tab=route', 'chk');

	print $nav;

	html_start_box('', '100%', '', '3', 'center', '');

	$display_text = array(
		'route' => array(
			'display' => __('Route', 'flowview'),
			'align'   => 'left',
			'tip'     => __('This is the published Internet Route.', 'flowview')
		),
		'origin' => array(
			'display' => __('AS Number', 'flowview'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Autonomous System Number for the route.', 'flowview')
		),
		'descr' => array(
			'display' => __('Route Description', 'flowview'),
			'align'   => 'left',
			'sort'    => 'ASC',
			'tip'     => __('The Descrption logged by the Route Maintainer.', 'flowview')
		),
		'source' => array(
			'display' => __('Source', 'flowview'),
			'align'   => 'left',
			'sort'    => 'DESC',
			'tip'     => __('The source database used to publish the route.', 'flowview')
		),
		'mnt_by' => array(
			'display' => __('Maintained By', 'flowview'),
			'align'   => 'left',
			'sort'    => 'DESC',
			'tip'     => __('The Internet Carrier who is maintaining the route for the customer.', 'flowview')
		),
		'last_modified' => array(
			'display' => __('Last Modified', 'flowview'),
			'align'   => 'right',
			'sort'    => 'DESC',
			'tip' => __('The last time the information on this route was modified.', 'flowview')
		)
	);

	html_header_sort($display_text, get_request_var('sort_column'), get_request_var('sort_direction'), false, 'flowview_databases.php?tab=route');

	$i = 0;
	if (cacti_sizeof($dns_cache)) {
		foreach ($dns_cache as $l) {
			form_alternate_row('line' . $i, false);
			form_selectable_cell(filter_value($l['route'], get_request_var('filter')), $i);
			form_selectable_cell(filter_value($l['origin'], get_request_var('filter')), $i);
			form_selectable_cell(filter_value($l['descr'], get_request_var('filter')), $i);
			form_selectable_cell(filter_value($l['source'], get_request_var('filter')), $i);
			form_selectable_cell(filter_value($l['mnt_by'], get_request_var('filter')), $i);
			form_selectable_cell($l['last_modified'], $i, '', 'right');
			form_end_row();

			$i++;
		}
	} else {
		print "<tr class='tableRow'><td colspan='" . (cacti_sizeof($display_text)+1) . "'><em>" . __('No Matching Routes Found', 'flowview') . "</em></td></tr>\n";
	}

	html_end_box(false);

	if (cacti_sizeof($dns_cache)) {
		print $nav;
	}

	form_end();
}

