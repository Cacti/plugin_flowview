<?php

declare(strict_types=1);
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

$guest_account = true;

chdir('../../');
include('./include/auth.php');
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');
include_once($config['base_path'] . '/lib/time.php');

flowview_connect();

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
	case 'saveasfilter':
		save_filter_as();

		break;
	case 'renamefilter':
		rename_filter();

		break;
	case 'deletefilter':
		delete_filter();

		break;
	case 'sort_filter':
		sort_filter();

		break;
	case 'updatesess':
		flowview_request_vars();

		break;
	case 'export':
		flowview_export_data();

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

function flowview_export_data() {
	flowview_request_vars();
	$data  = load_data_for_filter();

	if (cacti_sizeof($data['data'])) {
        $columns = array_keys($data['data'][0]);

		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename=flowview_data.csv');

		print implode(', ', $columns) . PHP_EOL;

		foreach($data['data'] as $row) {
			print implode(', ', array_values($row)) . PHP_EOL;
		}
	} else {
		raise_message('no_export_data', __('Unable to find Raw Data for to Export', 'flowview'), MESSAGE_LEVEL_ERROR);
	}
}

function save_filter_as() {
	if (isset_request_var('query') && get_filter_request_var('query') > 0) {
		$save = db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		if (cacti_sizeof($save)) {
			$save['name'] = get_request_var('sname');

			if (isset_request_var('report')) {
				$report = get_request_var('report');

				if (strpos($report, 's') !== false) {
					$report = trim($report, "s \n\t\r");

					$save['printed']    = 0;
					$save['statistics'] = $report;
				} else {
					$report = trim($report, "r \n\t\r");

					$save['printed']    = $report;
					$save['statistics'] = 0;
				}
			}

			$overrides = array(
				'device_id',
				'excluded',
				'sortfield',
				'cutofflines',
				'cutoffoctets',
				'predefined_timespan',
				'graph_type',
				'graph_height',
			);

			foreach($overrides as $variable) {
				if (isset_request_var($variable)) {
					if ($variable == 'predefined_timespan') {
						$save['timespan'] = get_request_var($variable);
					} else {
						$save[$variable]  = get_request_var($variable);
					}
				}
			}

			$checkbox_overrides = array(
				'table',
				'bytes',
				'packets',
				'flows'
			);

			foreach($checkbox_overrides as $variable) {
				if (isset_request_var($variable)) {
					$save["panel_$variable"] = 'on';
				} else {
					$save["panel_$variable"] = '';
				}
			}

			$save['id'] = 0;

			$new_id = flowview_sql_save($save, 'plugin_flowview_queries');

			if ($new_id > 0) {
				raise_message(1);
				header("Location: flowview.php?action=view&header=false&query=$new_id");
				exit;
			} else {
				raise_message(2);
				header("Location: flowview.php?action=view&header=false");
				exit;
			}
		} else {
			raise_message('bad_query', __('Flowview Query provided does not exist.  Please submit with a valid query.', 'flowview'), MESSAGE_LEVEL_ERROR);
			header('Location: flowview.php?action=view&header=false');
			exit;
		}
	} else {
		raise_message('bad_query', __('Invalid source Query provided.  Please submit with a valid query.', 'flowview'), MESSAGE_LEVEL_ERROR);
		header('Location: flowview.php?action=view&header=false');
		exit;
	}
}

function rename_filter() {
	$name  = get_nfilter_request_var('sname');
	$query = get_nfilter_request_var('query');

	flowview_db_execute_prepared('UPDATE plugin_flowview_queries
		SET name = ?
		WHERE id = ?',
		array($name, $query));
}

function delete_filter() {
	$query = get_nfilter_request_var('query');

	$exists = flowview_db_fetch_cell_prepared('SELECT COUNT(*)
		FROM plugin_flowview_schedules
		WHERE query_id = ?',
		array($query));

	$name = flowview_db_fetch_cell_prepared('SELECT name
		FROM plugin_flowview_queries
		WHERE id = ?',
		array($query));

	if ($exists) {
		raise_message('flow_delete', __esc('Unable to Delete Flow Filter \'%s\' as its in use in a Scheduled Report.', $name, 'flowview'), MESSAGE_LEVEL_WARN);
	} else {
		flowview_db_execute_prepared('DELETE FROM plugin_flowview_queries WHERE id = ?', array($query));
		raise_message('flow_delete', __esc('Flow Filter \'%s\' Deleted.', 'flowview'), MESSAGE_LEVEL_INFO);
	}
}

function load_session_for_filter() {
	/**
	 * Take the last session filter settings
	 * only when the user not changes the session.
	 */
	if (!isset_request_var('query')) {
		if (isset($_SESSION['sess_last_flowview_filter'])) {
			$_REQUEST = $_SESSION['sess_last_flowview_filter'];
		}
	}

	/**
	 * If the query request variable is set, we will take from the database
	 * settings first, and then overlay others only if they are set
	 */
	if (isset_request_var('query') && get_filter_request_var('query') > 0) {
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

		$query = flowview_db_fetch_row_prepared('SELECT *
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
					case 'device_id':
						if (!isset_request_var('device_id') || get_nfilter_request_var('device_id') == '-1') {
							set_request_var('device_id', $value);
						}

						break;
					case 'panel_table':
					case 'panel_bytes':
					case 'panel_packets':
					case 'panel_flows':
						if (!isset_request_var($column)) {
							$column = str_replace('panel_', '', $column);

							if ($value == 'on') {
								set_request_var($column, 'true');
							} else {
								set_request_var($column, 'false');
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

		$_SESSION['sess_last_flowview_filter'] = $_REQUEST;
	} elseif (isset_request_var('report')) {
		set_request_var('printed', 0);
		set_request_var('statistics', 0);
	}

	return isset($query['name']) ? $query['name']:'';
}

function flowview_request_vars() {
	/* restore the last session just in case */
	if (!isset_request_var('query')) {
		if (isset($_SESSION['sess_fview_query_last'])) {
			set_request_var('query', $_SESSION['sess_fview_query_last']);
		}
	} else {
		$_SESSION['sess_fview_query_last'] = get_filter_request_var('query');
	}

	/* initialize settings from the database if they are not set already */
	if (isset_request_var('query') && !isset($_SESSION['sess_fv_' . get_filter_request_var('query')])) {
		$listener = flowview_db_fetch_row_prepared('SELECT *
			FROM plugin_flowview_queries
			WHERE id = ?',
			array(get_request_var('query')));

		$columns = array(
			'graph_type',
			'graph_height',
			'panel_table',
			'panel_bytes',
			'panel_packets',
			'panel_flows'
		);

		if (cacti_sizeof($listener)) {
			foreach($columns as $c) {
				if (strpos($c, 'panel')) {
					$rv  = str_replace('panel_', '', $c);

					if (!isset_request_var($rv)) {
						set_request_var($rv, $listener[$c] == 'on' ? 'true':'false');
					}
				} elseif (!isset_request_var($c)) {
					set_request_var($c, $listener[$c]);
				}
			}

			if (isset_request_var('graph_link')) {
				set_request_var('predefined_timespan', '0');
			}
		}
	}

    /* ================= input validation and session storage ================= */
    $filters = array(
		'device_id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '0'
		),
		'sortfield' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => 'bytes'
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
		'ex_addr' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => '0'
		),
		'domains' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'true'
		),
		'table' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(true|false)')),
			'default' => 'false'
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
		'graph_type' => array(
			'filter' => FILTER_VALIDATE_REGEXP,
			'options' => array('options' => array('regexp' => '(bar|pie|treemap)')),
			'default' => 'bar'
		),
		'graph_height' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '400'
		)
	);

	validate_store_request_vars($filters, 'sess_fv_' . get_filter_request_var('query'));
	/* ================= input validation ================= */
}

