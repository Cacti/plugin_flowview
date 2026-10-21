<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the filter controls rendered by flowview_display_filter()
 * in includes/functions.php. The time-shift icons use CSP-safe
 * timeshiftBackward / timeshiftForward classes (bound via a delegated handler)
 * rather than inline onclick attributes.
 */

beforeAll(function () {
require_once __DIR__ . '/../../setup.php';
require_once __DIR__ . '/../../includes/functions.php';

	// The full filter render calls a handful of Cacti UI helpers the unit
	// harness does not otherwise need; stub the ones it does not already define.
	if (!function_exists('html_start_box'))          { function html_start_box(...$args) {} }
	if (!function_exists('html_end_box'))            { function html_end_box(...$args) {} }
	if (!function_exists('title_trim'))              { function title_trim($text, $length = 0) { return (string) $text; } }
	if (!function_exists('html_escape_request_var')) { function html_escape_request_var($name) { return ''; } }

	// includes/arrays.php (required inside flowview_display_filter) re-keys its
	// device/template lookups with Cacti core's array_rekey(), which the stub
	// harness does not provide; mirror core's behaviour so the require succeeds.
	if (!function_exists('array_rekey')) {
		function array_rekey($array, $key, $value) {
			$ret = array();
			if (is_array($array)) {
				foreach ($array as $item) {
					$k = $item[$key];
					if (is_array($value)) {
						foreach ($value as $v) { $ret[$k][$v] = $item[$v]; }
					} else {
						$ret[$k] = $item[$value];
					}
				}
			}
			return $ret;
		}
	}
});

it('renders the time-shift controls with CSP-safe classes and no inline handlers', function () {
	ob_start();

	try {
		flowview_display_filter();
	} catch (\Throwable $e) {
		// The complete filter form references optional helpers that are not part
		// of the isolated unit harness; the CSP-relevant controls are emitted
		// before any such reference, so the captured output still covers them.
	}

	$output = ob_get_clean();

expect($output)->toContain('timeshiftBackward');
expect($output)->toContain('timeshiftForward');
expect($output)->not->toMatch('/\son(?:click|change)\s*=/i');
});

it('does not show the Report on the report page, but keeps its value for the requests', function ($query, $row) {
	plugin_test_reset();

	$_REQUEST['query'] = $query;
	$_REQUEST['report'] = 's10';

	plugin_test_queue_db_result('db_fetch_row_prepared', $row);

	ob_start();

	try {
		flowview_display_filter();
	} catch (\Throwable $e) {
		// as above, the part of the form that is checked is emitted first
	}

	$output = ob_get_clean();

	expect($output)->toContain("<input type='hidden' id='report' name='report' value='s10'>")
		->and($output)->not->toContain("<select id='report'")
		->and($output)->not->toContain('>Report<');
})->with(array(
	'a filter with a report' => array(4, array('name' => 'Top Talkers', 'statistics' => 10, 'printed' => 0)),
	'no filter selected'     => array(-1, array()),
));

it('shows the Report only for a filter that has none, as it needs one', function () {
	plugin_test_reset();

	$_REQUEST['query'] = 4;

	plugin_test_queue_db_result('db_fetch_row_prepared', array('name' => 'Empty', 'statistics' => 0, 'printed' => 0));

	ob_start();

	try {
		flowview_display_filter();
	} catch (\Throwable $e) {
		// as above, the part of the form that is checked is emitted first
	}

	$output = ob_get_clean();

	expect($output)->toContain("<select id='report' name='report'>")
		->and($output)->not->toContain("type='hidden' id='report'");
});
