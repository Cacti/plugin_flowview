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
include_once('./include/auth.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
include_once($config['base_path'] . '/lib/time.php');

set_default_action();

ini_set('max_execution_time', 240);
ini_set('memory_limit', '-1');

switch(get_request_var('action')) {
	case 'save':
		save_filter();
		break;
	case 'savefilter':
		save_filter_form();
		break;
	case 'sort_filter':
		sort_filter();
		break;
	case 'updatesess':
		flowview_request_vars();
		break;
	case 'chartdata':
		flowview_request_vars();
		flowview_get_chartdata();
		break;
	case 'gettimespan':
		flowview_request_vars();
		flowview_gettimespan();
		break;
	case 'query':
		load_session_for_filter();
	default:
		general_header();

		flowview_request_vars();

		$title = load_session_for_filter();

		$data  = load_data_for_filter();

		flowview_display_filter($data);

		if (get_request_var('statistics') != 99) {
			flowview_draw_table($data);
			flowview_draw_chart('bytes', $title);
			flowview_draw_chart('packets', $title);
			flowview_draw_chart('flows', $title);
		} else {
			flowview_show_summary($data);
		}

		bottom_footer();
}

exit;

function load_session_for_filter() {
	if ((isset_request_var('query') && get_filter_request_var('query') > 0)) {
		// Handle Report Column
		if (isset_request_var('report')) {
			if (get_nfilter_request_var('report') != '0' && trim(get_nfilter_request_var('report'), 'sp') != '0') {
				if (substr(get_nfilter_request_var('report'), 0, 1) == 's') {
					set_request_var('statistics', trim(get_nfilter_request_var('report'), 'sp'));
					set_request_var('printed', 0);
				} else {
					set_request_var('printed', trim(get_nfilter_request_var('report'), 'sp'));
					set_request_var('statistics', 0);
				}
			}
		}

		$query = db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		if (cacti_sizeof($query)) {
			foreach($query as $column => $value) {
				switch($column) {
					case 'name':
						break;
					case 'timespan':
						if (!isset_request_var('predefined_timespan')) {
							set_request_var('predefined_timespan', $query['timespan']);

							if ($query['timespan'] == 0) {
								set_request_var('date1', strtoupper($query['startdate']));
								set_request_var('date2', strtoupper($query['enddate']));
							} else {
								$span = array();
								get_timespan($span, time(), get_request_var('predefined_timespan'), read_user_setting('first_weekdayid'));
								set_request_var('date1', $span['current_value_date1']);
								set_request_var('date2', $span['current_value_date2']);
							}
						}

						break;
					case 'statistics':
						if ($value > 0) {
							if (!isset_request_var('report') || trim(get_nfilter_request_var('report'), 'sp') == '0') {
								set_request_var('report', 's' . $value);
								set_request_var('statistics', $value);
								set_request_var('printed', 0);
							} elseif (trim(get_nfilter_request_var('report'), 'sp') != '0') {
								$value = trim(get_nfilter_request_var('report'), 'sp');
								if (substr(get_request_var('report'), 0, 1) == 's') {
									set_request_var('report', 's' . $value);
									set_request_var('statistics', $value);
								} else {
									set_request_var('report', 'p' . $value);
									set_request_var('printed', $value);
								}
							}
						}

						break;
					case 'printed':
						if ($value > 0) {
							if (!isset_request_var('report') || trim(get_nfilter_request_var('report'), 'sp') == '0') {
								set_request_var('report', 'p' . $value);
								set_request_var('printed', $value);
								set_request_var('statistics', 0);
							} elseif (trim(get_nfilter_request_var('report'), 'sp') != '0') {
								$value = trim(get_nfilter_request_var('report'), 'sp');
								if (substr(get_request_var('report'), 0, 1) == 's') {
									set_request_var('report', 's' . $value);
									set_request_var('statistics', $value);
									set_request_var('printed', 0);
								} else {
									set_request_var('report', 'p' . $value);
									set_request_var('printed', $value);
									set_request_var('statistics', 0);
								}
							}
						}

						break;
					default:
						// cacti_log('The column is : ' . $column . ', Value is: ' . $value);
						if (!isset_request_var($column)) {
							if ($column == 'protocols' && $value != '') {
								set_request_var($column, explode(',', $value));
							} else {
								set_request_var($column, $value);
							}
						} elseif ($value != '' && get_nfilter_request_var($column) == '') {
							set_request_var($column, $value);
						}

						break;
				}
			}
		}
	} elseif (isset_request_var('report')) {
		set_request_var('printed', 0);
		set_request_var('statistics', 0);
	}

	return isset($query['name']) ? $query['name']:'';
}

function flowview_request_vars() {
    /* ================= input validation and session storage ================= */
    $filters = array(
		'sortfield' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => ''
		),
		'sortvalue' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => 0
		),
		'report' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => 0
		),
		'cutofflines' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '20'
		),
		'cutoffoctets' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1000000'
		),
		'predefined_timespan' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('default_timespan')
		),
		'exclude' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
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
		)
	);

	validate_store_request_vars($filters, 'sess_fv_' . get_filter_request_var('query'));
	/* ================= input validation ================= */
}

