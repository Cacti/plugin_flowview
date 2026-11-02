<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression guards for plugin_flowview_run_schedule(), the daily scheduled
 * report runner. When a schedule points at a query that has since been
 * deleted, the runner used to read $query['timespan'], $query['name'] and
 * run the empty result through run_flow_query(), emitting a burst of
 * "Undefined array key" / "array offset on bool" wanings into the PHP log
 * on every scheduled run. The runner now logs a single explanatory waning
 * and retuns before touching the missing row.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../includes/database.php';
	require_once __DIR__ . '/../../includes/functions.php';
});

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['__test_cacti_log'] = array();
});

it('skips a schedule whose query was deleted and logs why', function () {
	/* the schedule still exists, but the query it points at is gone */
	plugin_test_queue_db_result('db_fetch_row_prepared', array('id' => 5, 'title' => 'Nightly', 'query_id' => 42));
	plugin_test_queue_db_result('db_fetch_row_prepared', array());

	plugin_flowview_run_schedule(5, 11);

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('references Query 42 which no longer exists');
});

it('skips a schedule that no longer exists and logs why', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', array());

	plugin_flowview_run_schedule(7, 11);

	expect(implode("\n", $GLOBALS['__test_cacti_log']))->toContain('Flowview Schedule 7 no longer exists');
});
