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
		$_SESSION['flowview_rematerialize_saved'] = 4;

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		ob_start();
		edit_filter();
		$output = ob_get_clean();

		expect($output)->toContain("id='flowview_save_warning'")
			->and($output)->toContain('Cached Results Affected')
			->and($output)->toContain("var useSelect2 = " . (version_compare(CACTI_VERSION, '1.2.32', '>=') ? 'true' : 'false') . ';')
			->and($output)->toContain("\$('#chk #submit').off('click.flowview').on('click.flowview'")
			->and($output)->toContain("{ name: 'action', value: 'check_material' }")
			->and($output)->toContain("\$('#protocols').multiselect();")
			->and($output)->toContain("id='flowview_saved_dialog'")
			->and(isset($_SESSION['flowview_rematerialize_saved']))->toBeFalse()
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

	it('leaves a dialog to say it was saved and has to be materialized again when the cache is affected', function () {
		$_REQUEST['sourceip'] = '10.0.0.2';

		plugin_test_queue_db_result('db_fetch_row_prepared', plugin_test_stored_filter());

		expect(fn () => save_filter())->toThrow(FlowviewTestExit::class);

		expect($_SESSION['flowview_rematerialize_saved'])->toBe(4)
			->and($GLOBALS['__test_messages'])->toBe(array());
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

		return array(json_decode($output, true), $update[0]['params']);
	};

	it('keeps the report of a Filter that has one', function () use ($save) {
		list($answer, $params) = $save(array('report' => 'p1'), plugin_test_stored_filter());

		expect($params[2])->toBe(10)
			->and($params[3])->toBe(0)
			->and($answer)->toBe(array('rematerialize' => false));
	});

	it('takes a statistical report for a Filter that has none', function () use ($save) {
		list($answer, $params) = $save(array('report' => 's11'), plugin_test_stored_filter(array('statistics' => '0')));

		expect($params[2])->toBe(11)
			->and($params[3])->toBe(0)
			->and($answer)->toBe(array('rematerialize' => true));
	});

	it('takes a printed report for a Filter that has none', function () use ($save) {
		list($answer, $params) = $save(array('report' => 'p1'), plugin_test_stored_filter(array('statistics' => '0')));

		expect($params[2])->toBe(0)
			->and($params[3])->toBe(1);
	});

	it('says to materialize again when the listener changes', function () use ($save) {
		list($answer) = $save(array('device_id' => '3'), plugin_test_stored_filter());

		expect($answer)->toBe(array('rematerialize' => true));
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
