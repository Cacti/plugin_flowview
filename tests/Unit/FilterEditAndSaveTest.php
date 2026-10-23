<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * The Filter editor, the two ways a Filter is saved, and the chart data of a
 * report.  The Cacti UI helpers they call are stood in for, and the end of a
 * request is seen through flowview_exit(), which the unit bootstrap makes
 * throw FlowviewTestExit instead of ending the process.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/functions.php';

	if (!function_exists('isempty_request_var'))   { function isempty_request_var($name) { return empty($_REQUEST[$name]); } }
	if (!function_exists('form_start'))            { function form_start($action, $id = '') { $GLOBALS['__test_form_start'] = array($action, $id); } }
	if (!function_exists('html_start_box'))        { function html_start_box(...$args) {} }
	if (!function_exists('html_end_box'))          { function html_end_box(...$args) {} }
	if (!function_exists('read_user_setting'))     { function read_user_setting($name, $default = false) { return $default; } }
	if (!function_exists('inject_form_variables')) { function inject_form_variables($form, ...$args) { return $form; } }
	if (!function_exists('draw_edit_form'))        { function draw_edit_form($array) { $GLOBALS['__test_edit_form'] = $array; } }
	if (!function_exists('form_save_button'))      { function form_save_button($cancel, $type = '') { print "<input type='submit' id='submit'></form>"; } }
	if (!function_exists('get_selected_theme'))    { function get_selected_theme() { return 'modern'; } }
	if (!function_exists('title_trim'))            { function title_trim($text, $length = 0) { return (string) $text; } }

	if (!function_exists('get_timespan')) {
		function get_timespan(&$span, $now, $timespan, $first_weekdayid) {
			$span['current_value_date1'] = '2026-10-22 00:00';
			$span['current_value_date2'] = '2026-10-22 01:00';
		}
	}

	if (!function_exists('array_rekey')) {
		function array_rekey($array, $key, $value) {
			$ret = array();

			foreach ((array) $array as $item) {
				$ret[$item[$key]] = is_array($value) ? array_intersect_key($item, array_flip($value)) : $item[$value];
			}

			return $ret;
		}
	}
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['__test_cacti_log'] = array();
	unset($GLOBALS['__test_error_message']);
});

/* the stored Filter that is edited and saved */
function plugin_test_stored_filter(array $override = array()): array {
	return array_merge(array(
		'id' => 4, 'name' => 'Top Talkers', 'device_id' => '0', 'ex_addr' => '', 'template_id' => '-1',
		'timespan' => '3', 'startdate' => '', 'enddate' => '',
		'statistics' => '10', 'printed' => '0', 'sortfield' => 'bytes', 'cutofflines' => '20', 'cutoffoctets' => '0',
		'includeif' => '0', 'resolve' => 'Y', 'graph_type' => 'bar', 'graph_height' => '400',
		'panel_table' => 'on', 'panel_bytes' => 'on', 'panel_packets' => '', 'panel_flows' => '', 'usenat' => '',
		'protocols' => '', 'tosfields' => '', 'tcpflags' => '',
		'sourceip' => '', 'sourceport' => '', 'sourceinterface' => '', 'sourceas' => '',
		'destip' => '', 'destport' => '', 'destinterface' => '', 'destas' => '',
		'postnatsourceip' => '', 'postnatsourceport' => '', 'postnatdestip' => '', 'postnatdestport' => '',
	), $override);
}

describe('the Filter editor', function () {
	it('warns before a save that changes the cache, says when it succeeded, and picks the multi-select', function () {
		$_REQUEST['id'] = 4;

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		ob_start();
		edit_filter();
		$output = ob_get_clean();

		expect($output)->toContain("id='flowview_save_warning'")
			->and($output)->toContain('Cached Results Affected')
			->and($output)->toContain('a materialization in the background')
			->and($output)->toContain("var useSelect2 = " . (version_compare(CACTI_VERSION, '1.2.32', '>=') ? 'true' : 'false') . ';')
			->and($output)->toContain("\$('#chk #submit').off('click.flowview').on('click.flowview'")
			->and($output)->toContain("{ name: 'action', value: 'check_material' }")
			->and($output)->toContain("\$('#protocols').multiselect();")
			->and($GLOBALS['__test_form_start'][1])->toBe('chk')
			->and($GLOBALS['__test_edit_form']['fields']['protocols']['select_all_text'])->toBe('All Protocols');
	});

	it('uses select2 from Cacti 1.2.32', function () {
		expect(flowview_cacti_uses_select2())->toBe(version_compare(CACTI_VERSION, '1.2.32', '>='));
	});
});

describe('saving a Filter from its editor', function () {
	beforeEach(function () {
		$_REQUEST = array_merge(plugin_test_stored_filter(), array('id' => '4', 'date1' => '', 'date2' => ''));
		$_REQUEST['panel_table'] = 'on';
		unset($_REQUEST['panel_bytes'], $_REQUEST['panel_packets'], $_REQUEST['panel_flows'], $_REQUEST['usenat']);
	});

	it('starts a materialization in the background and says so when the cache is affected', function () {
		$_REQUEST['sourceip'] = '10.0.0.2';

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);

		/* the materialization of the saved Filter was launched */
		expect($GLOBALS['__test_exec'])->toHaveCount(1)
			->and($GLOBALS['__test_exec'][0][1])->toContain('--query-id=4')
			->and($GLOBALS['__test_messages'][0][0])->toBe('flowview_materialize_started')
			->and($GLOBALS['__test_messages'][0][1])->toContain('materialization')
			->and(isset($_SESSION['flowview_rematerialize_saved']))->toBeFalse();
	});

	it('saves the timespan that is picked in the editor', function () {
		unset($_REQUEST['timespan']);
		$_REQUEST['predefined_timespan'] = '5';

		expect(flowview_filter_form_values()['timespan'])->toBe('5');
	});

	it('says it was saved, as Cacti always does, when the cache is not affected', function () {
		$_REQUEST['name'] = 'Renamed';

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);

		expect(isset($_SESSION['flowview_rematerialize_saved']))->toBeFalse()
			->and($GLOBALS['__test_messages'][0][0])->toBe(1);
	});

	it('goes back to the page it came from', function () {
		$_REQUEST['name']   = 'Renamed';
		$_REQUEST['return'] = 'flowview.php';

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);

		expect($GLOBALS['__test_messages'][0][0])->toBe(1);
	});

	it('says the save failed and stops, whichever page it came from', function () {
		$GLOBALS['__test_error_message'] = true;

		foreach (array('', 'flowview.php') as $return) {
			$_REQUEST['return'] = $return;

			plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

			expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);
		}

		unset($GLOBALS['__test_error_message']);

		expect(array_column($GLOBALS['__test_messages'], 0))->toBe(array(2, 2))
			->and(isset($_SESSION['flowview_rematerialize_saved']))->toBeFalse();
	});
});

describe('saving a Filter from the report page', function () {
	$save = function (array $request, array $stored) {
		$_REQUEST = array_merge(array(
			'query' => '4', 'timespan' => '3', 'device_id' => '0', 'sortfield' => 'bytes', 'cutofflines' => '20',
			'cutoffoctets' => '0', 'graph_type' => 'bar', 'graph_height' => '400',
			'table' => 'true', 'bytes' => 'false', 'packets' => 'false', 'flows' => 'false', 'usenat' => 'false',
		), $request);

		plugin_test_queue_db_result('db_fetch_row_prepared', $stored);

		ob_start();
		save_filter_form();
		$output = ob_get_clean();

		$update = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
			return $call['fn'] == 'db_execute_prepared' && strpos($call['sql'], 'UPDATE plugin_flowview_queries') === 0;
		}));

		/* the parameters by the column they are saved in */
		preg_match_all('/(\w+) = \?/', $update[0]['sql'], $columns);

		return array(json_decode($output, true), array_combine($columns[1], $update[0]['params']));
	};

	it('keeps the report of a Filter that has one', function () use ($save) {
		list($answer, $params) = $save(array('report' => 'p1'), plugin_test_stored_filter());

		expect($params['statistics'])->toBe(10)
			->and($params['printed'])->toBe(0)
			->and($answer)->toBe(array('rematerialize' => false));
	});

	it('takes a statistical report for a Filter that has none', function () use ($save) {
		list($answer, $params) = $save(array('report' => 's11'), plugin_test_stored_filter(array('statistics' => '0')));

		expect($params['statistics'])->toBe(11)
			->and($params['printed'])->toBe(0)
			->and($answer)->toBe(array('rematerialize' => true));
	});

	it('takes a printed report for a Filter that has none', function () use ($save) {
		list($answer, $params) = $save(array('report' => 'p1'), plugin_test_stored_filter(array('statistics' => '0')));

		expect($params['statistics'])->toBe(0)
			->and($params['printed'])->toBe(1);
	});

	it('starts a materialization when the printed sort field changes, as it is part of the cache', function () use ($save) {
		$GLOBALS['__test_exec'] = array();

		list($answer) = $save(array('sortfield' => 'packets'), plugin_test_stored_filter(array('statistics' => '0', 'printed' => '1', 'sortfield' => 'bytes')));

		expect($answer)->toBe(array('rematerialize' => true))
			->and($GLOBALS['__test_exec'])->toHaveCount(1)
			->and($GLOBALS['__test_exec'][0][1])->toContain('--query-id=4');
	});

	it('saves the sort field and never the Listener or Stream, which are not on the page', function () use ($save) {
		$GLOBALS['__test_exec'] = array();

		list($answer, $params) = $save(array('device_id' => '3', 'ex_addr' => '10.1.1.1', 'sortfield' => 'flows'), plugin_test_stored_filter(array('device_id' => '1', 'ex_addr' => '10.9.9.9')));

		expect($params['sortfield'])->toBe('flows')
			->and($params['id'])->toBe('4')
			->and(array_key_exists('device_id', $params))->toBeFalse()
			->and(array_key_exists('ex_addr', $params))->toBeFalse()
			->and($answer)->toBe(array('rematerialize' => false))
			->and($GLOBALS['__test_exec'])->toBe(array());
	});

	it('saves the dates of a custom timespan', function () use ($save) {
		list(, $params) = $save(array('timespan' => '0', 'date1' => '2026-10-20 00:00', 'date2' => '2026-10-21 00:00'), plugin_test_stored_filter());

		expect($params['timespan'])->toBe('0')
			->and($params['startdate'])->toBe('2026-10-20 00:00')
			->and($params['enddate'])->toBe('2026-10-21 00:00');
	});

	it('keeps the saved dates when the page does not send them', function () use ($save) {
		list(, $params) = $save(array(), plugin_test_stored_filter(array('startdate' => '2026-10-01 00:00', 'enddate' => '2026-10-02 00:00')));

		expect($params['startdate'])->toBe('2026-10-01 00:00')
			->and($params['enddate'])->toBe('2026-10-02 00:00');
	});

	it('shows the saved dates on the next visit when they were saved without pressing Go', function () use ($save) {
		/* what the page last stored for the Filter, before the dates were changed */
		$_SESSION = array(
			'sess_fv_4_predefined_timespan' => '3',
			'sess_fv_4_date1'               => '2026-10-01 00:00',
			'sess_fv_4_date2'               => '2026-10-02 00:00',
			'sess_flowdata'                 => array('4_old' => array('data' => 1)),
		);

		list(, $params) = $save(array('timespan' => '0', 'date1' => '2026-10-20 00:00', 'date2' => '2026-10-21 00:00'), plugin_test_stored_filter());

		expect(flowview_filter_session_exists(4))->toBeFalse()
			->and($_SESSION['sess_flowdata'])->toBe(array());

		/* the next visit has no session for the Filter, so it takes it as it was saved */
		$_REQUEST = array('query' => '4');

		flowview_seed_filter_request_vars(plugin_test_stored_filter(array(
			'timespan' => $params['timespan'], 'startdate' => $params['startdate'], 'enddate' => $params['enddate'],
		)));

		expect($_REQUEST['predefined_timespan'])->toBe('0')
			->and($_REQUEST['date1'])->toBe('2026-10-20 00:00')
			->and($_REQUEST['date2'])->toBe('2026-10-21 00:00');
	});
});

describe('the report page settings of a Filter', function () {
	beforeEach(function () {
		$_REQUEST = array();
		$_SESSION = array();
	});

	it('knows when the session holds the settings of a Filter', function () {
		$_SESSION['sess_fv_10_sortfield'] = 'bytes';

		expect(flowview_filter_session_exists(1))->toBeFalse()
			->and(flowview_filter_session_exists(10))->toBeTrue()
			->and(flowview_filter_session_exists('10'))->toBeTrue();
	});

	it('forgets the settings and cached results of only the Filter that was saved', function () {
		$_SESSION = array(
			'sess_fv_4_sortfield'       => 'flows',
			'sess_fv_4_device_id'       => '1',
			'sess_fv_40_sortfield'      => 'bytes',
			'sess_fview_query_last'     => '4',
			'sess_last_flowview_filter' => array('query' => '4', 'sortfield' => 'flows'),
			'sess_flowdata'             => array('4_abc' => array('data' => 1), '40_abc' => array('data' => 2)),
		);

		flowview_reset_filter_session(4);

		expect(flowview_filter_session_exists(4))->toBeFalse()
			->and($_SESSION['sess_fv_40_sortfield'])->toBe('bytes')
			->and($_SESSION['sess_fview_query_last'])->toBe('4')
			->and(isset($_SESSION['sess_last_flowview_filter']))->toBeFalse()
			->and(array_keys($_SESSION['sess_flowdata']))->toBe(array('40_abc'));
	});

	it('keeps the last settings of the page when another Filter was saved', function () {
		$_SESSION['sess_last_flowview_filter'] = array('query' => '2');

		flowview_reset_filter_session(4);

		expect($_SESSION['sess_last_flowview_filter'])->toBe(array('query' => '2'));
	});

	it('shows the Filter as it is saved', function () {
		flowview_seed_filter_request_vars(plugin_test_stored_filter(array(
			'device_id' => '1', 'ex_addr' => '-1', 'sortfield' => 'flows', 'cutofflines' => '50', 'cutoffoctets' => '1024',
			'graph_type' => 'pie', 'graph_height' => '300', 'panel_packets' => 'on', 'usenat' => 'on', 'timespan' => '3',
		)));

		/* the Listener and Stream are read from the saved Filter at query time, not seeded onto the page */
		expect(isset($_REQUEST['device_id']))->toBeFalse()
			->and(isset($_REQUEST['ex_addr']))->toBeFalse()
			->and($_REQUEST['sortfield'])->toBe('flows')
			->and($_REQUEST['cutofflines'])->toBe('50')
			->and($_REQUEST['cutoffoctets'])->toBe('1024')
			->and($_REQUEST['graph_type'])->toBe('pie')
			->and($_REQUEST['graph_height'])->toBe('300')
			->and($_REQUEST['table'])->toBe('true')
			->and($_REQUEST['bytes'])->toBe('true')
			->and($_REQUEST['packets'])->toBe('true')
			->and($_REQUEST['flows'])->toBe('false')
			->and($_REQUEST['usenat'])->toBe('true')
			->and($_REQUEST['predefined_timespan'])->toBe('3')
			->and(isset($_REQUEST['date1']))->toBeFalse()
			->and(isset($_REQUEST['date2']))->toBeFalse();
	});

	it('leaves the dates of a preset timespan to be worked out on every visit', function () {
		$filter = plugin_test_stored_filter(array('timespan' => '3', 'startdate' => '2026-10-01 00:00', 'enddate' => '2026-10-02 00:00'));

		/* the first visit, where the session holds nothing for the Filter */
		flowview_seed_filter_request_vars($filter);

		expect(isset($_REQUEST['date1']))->toBeFalse()
			->and(isset($_REQUEST['date2']))->toBeFalse();

		/* validate_store_request_vars() keeps the empty default, so a later visit works the window out again */
		$_SESSION['sess_fv_4_predefined_timespan'] = $_REQUEST['predefined_timespan'];
		$_SESSION['sess_fv_4_date1']               = '';
		$_SESSION['sess_fv_4_date2']               = '';

		$_REQUEST = array('query' => '4', 'predefined_timespan' => '3', 'date1' => '', 'date2' => '');

		$start    = strtotime('2026-10-22 00:00');
		$end      = strtotime('2026-10-22 01:00');
		$sentinel = array('id' => 4, 'table' => 'current-window');

		$_SESSION['sess_flowdata'] = array(
			get_flowview_session_key(4, $start, $end) => array('data' => $sentinel, 'timeout' => time() + 60),
		);

		plugin_test_queue_db_result('db_fetch_cell_prepared', '3');

		expect(flowview_filter_session_exists(4))->toBeTrue()
			->and(load_data_for_filter())->toBe($sentinel)
			->and($_REQUEST['date1'])->toBe('2026-10-22 00:00')
			->and($_REQUEST['date2'])->toBe('2026-10-22 01:00');
	});

	it('does nothing without a session or a Filter', function () {
		$session = $_SESSION;
		unset($_SESSION);

		expect(flowview_filter_session_exists(4))->toBeFalse();

		flowview_reset_filter_session(4);

		expect(isset($_SESSION))->toBeFalse();

		$_SESSION = $session;

		$_SESSION['sess_fv_4_sortfield'] = 'flows';

		flowview_reset_filter_session(0);
		flowview_seed_filter_request_vars(array());

		expect($_SESSION['sess_fv_4_sortfield'])->toBe('flows')
			->and($_REQUEST)->toBe(array());
	});

	it('leaves a setting the Filter has no value for to the page default', function () {
		flowview_seed_filter_request_vars(plugin_test_stored_filter(array('sortfield' => '')));

		expect(isset($_REQUEST['sortfield']))->toBeFalse();
	});

	it('keeps what the request asks for over the saved Filter', function () {
		$_REQUEST = array('device_id' => '0', 'sortfield' => 'packets', 'predefined_timespan' => '5', 'date1' => 'a', 'date2' => 'b');

		flowview_seed_filter_request_vars(plugin_test_stored_filter(array('device_id' => '1', 'sortfield' => 'flows')));

		expect($_REQUEST['device_id'])->toBe('0')
			->and($_REQUEST['sortfield'])->toBe('packets')
			->and($_REQUEST['predefined_timespan'])->toBe('5')
			->and($_REQUEST['date1'])->toBe('a');
	});

	it('shows the dates of a Filter saved with a custom timespan', function () {
		flowview_seed_filter_request_vars(plugin_test_stored_filter(array('timespan' => '0', 'startdate' => '2026-10-20 00:00', 'enddate' => '2026-10-21 00:00')));

		expect($_REQUEST['predefined_timespan'])->toBe('0')
			->and($_REQUEST['date1'])->toBe('2026-10-20 00:00')
			->and($_REQUEST['date2'])->toBe('2026-10-21 00:00');
	});

	it('forgets the page settings of a Filter once it is saved from its editor', function () {
		$_SESSION['sess_fv_4_sortfield'] = 'flows';
		$_REQUEST = array_merge(plugin_test_stored_filter(), array('id' => '4', 'name' => 'Renamed', 'date1' => '', 'date2' => '', 'panel_table' => 'on'));

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);

		expect(flowview_filter_session_exists(4))->toBeFalse();
	});

	it('takes the saved settings when the session has none for the Filter, and the Listener and Stream from the selected Filter', function () {
		$page = plugin_test_read_source('flowview.php');
		$js   = plugin_test_read_source('includes/functions.php');

		preg_match('/function changeQuery\(\) \{.*?\n\t\}/s', $js, $change);

		expect($page)->toContain("!flowview_filter_session_exists(get_request_var('query'))")
			->and($page)->toContain('flowview_seed_filter_request_vars($listener);')
			->and($page)->not->toContain("!isset(\$_SESSION['sess_fv_' . get_filter_request_var('query')])")
			->and($change[0])->not->toContain('device_id')
			->and($change[0])->not->toContain('ex_addr')
			->and($change[0])->not->toContain('usenat');
	});

	it('opens the selected Filter from the Edit button', function () {
		$js = plugin_test_read_source('includes/functions.php');

		expect($js)->toContain("var flowviewQueryId = <?php print (int) get_request_var('query');?>;")
			->and($js)->toContain("var query = \$('#query').val() > 0 ? \$('#query').val() : flowviewQueryId;")
			->and($js)->not->toContain("urlPath + '/plugins/flowview/flowview_filters.php'");
	});
});

describe('the chart data of a report', function () {
	beforeEach(function () {
		$_REQUEST = array('query' => '4', 'type' => 'bytes', 'domains' => 'false', 'date1' => '2026-10-22 00:00', 'date2' => '2026-10-22 01:00');
	});

	it('charts the report of the Filter, as the page no longer sends it', function () {
		$start = strtotime('2026-10-22 00:00');
		$end   = strtotime('2026-10-22 01:00');

		$_SESSION['sess_flowdata'][get_flowview_session_key(4, $start, $end)]['data'] = array(
			'data' => array(
				array('dst_if' => 'ge-0/0/1', 'bytes' => 300),
				array('dst_if' => 'ge-0/0/2', 'bytes' => 100),
			),
		);

		plugin_test_queue_db_result('db_fetch_row_prepared', array('statistics' => '18', 'printed' => '0'));

		ob_start();

		try {
			flowview_get_chartdata();
		} catch (FlowviewTestExit $e) {
			// the chart data ends the request
		}

		$chart = json_decode(ob_get_clean(), true);

		expect($chart)->toBe(array(
			array('name' => 'ge-0/0/1', 'value' => 300, 'index' => 0),
			array('name' => 'ge-0/0/2', 'value' => 100, 'index' => 1),
		));
	});

	it('has no chart for a printed report', function () {
		plugin_test_queue_db_result('db_fetch_row_prepared', array('statistics' => '0', 'printed' => '1'));

		ob_start();

		try {
			flowview_get_chartdata();
		} catch (FlowviewTestExit $e) {
			// the chart data ends the request
		}

		expect(ob_get_clean())->toBe('[]');
	});
});
