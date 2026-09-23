<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for flowview_config_arrays() in setup.php - wires up the
 * plugin's menu entries. It also calls plugin_flowview_check_upgrade() at
 * the end, so PHP_SELF is set to a page outside that function's guard
 * list to keep it from touching lib/poller.php or the flowview database
 * connection.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	plugin_test_reset();
	$_SERVER['PHP_SELF'] = '/graphs.php';
	$GLOBALS['menu'] = array('Import/Export' => array());
});

it('adds the FlowView menu entries under Import/Export', function () {
	global $menu;

	flowview_config_arrays();

	expect($menu)->toHaveKey('FlowView');
	expect($menu['FlowView'])->toHaveKey('plugins/flowview/flowview_devices.php');
	expect($menu['FlowView'])->toHaveKey('plugins/flowview/flowview_filters.php');
	expect($menu['FlowView'])->toHaveKey('plugins/flowview/flowview_schedules.php');
	expect($menu['FlowView'])->toHaveKey('plugins/flowview/flowview_databases.php');
});
