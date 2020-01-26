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
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
include_once($config['base_path'] . '/lib/time.php');

set_default_action();

flowview_request_vars();

ini_set('max_execution_time', 240);
ini_set('memory_limit', '-1');

switch(get_request_var('action')) {
	case 'save':
		save_filter();
		break;
	case 'sort_filter':
		sort_filter();
		break;
	case 'updatesess':
		flowview_request_vars();
		break;
	case 'chartdata':
		flowview_viewchart();
		break;
	case 'tabledata':
		flowview_viewtable();
		break;
	case 'gettimespan':
		flowview_gettimespan();
		break;
	default:
		general_header();

		flowview_display_filter();

		if (get_filter_request_var('query') > 0) {
			$title = load_session_for_filter();

			$data = load_data_for_filter();

			if (get_request_var('statistics') > 0 && get_request_var('statistics') != 99) {
				flowview_draw_table($data);
				flowview_draw_chart('bytes', $title);
				flowview_draw_chart('packets', $title);
				flowview_draw_chart('flows', $title);
			} else {
				flowview_show_summary($data);
			}
		}

		bottom_footer();
}

exit;

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

	// load/validate request variables
	flowview_request_vars();

	return isset($q['name']) ? $q['name']:'';
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
			'default' => '1000000'
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
			'options' => array('options' => 'sanitize_search_string'),
			'default' => ''
		),
		'date2' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => ''
		),
		'protocols' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_ARRAY,
			'default' => '6'
		),
		'includeif' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
		),
		'tcpflags' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'tosfields' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'sourceip' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => ''
		),
		'sourceport' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'sourceinterface' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'sourceas' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'destip' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => ''
		),
		'destport' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'destinterface' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'destas' => array(
			'filter' => FILTER_VALIDATE_IS_NUMERIC_LIST,
			'default' => ''
		),
		'domains' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'true'
		),
		'table' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'true'
		),
		'bytes' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'false'
		),
		'packets' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'false'
		),
		'flows' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'false'
		),
		'resolve' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(Y|N)')),
			'default' => 'true'
		)
	);

	validate_store_request_vars($filters, 'sess_fv');
	/* ================= input validation ================= */
}

