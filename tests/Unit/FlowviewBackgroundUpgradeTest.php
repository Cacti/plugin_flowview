<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the background-upgrade launcher in setup.php:
 * flowview_launch_background_upgrade() and flowview_background_upgrade_running().
 *
 * The launcher must start flowview_upgrade.php and raise the "upgrading in the
 * background" notice only once, rather than relaunching the CLI and repeating
 * the notice on every page load while the recorded version still lags the code
 * (issue #308). A run that crashed past its process-table timeout is treated as
 * not running so the launcher relaunches it.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	plugin_test_reset();

	$GLOBALS['__original_base_path'] = $GLOBALS['config']['base_path'];
	$GLOBALS['config']['base_path']  = '/var/www/cacti';

	$GLOBALS['__test_config']['path_php_binary'] = '/usr/bin/php';
	$GLOBALS['__test_tables']['processes']       = true;
});

afterEach(function () {
	$GLOBALS['config']['base_path'] = $GLOBALS['__original_base_path'];
});

it('launches the CLI and raises the notice once when no upgrade is running', function () {
	// No process registered.
	plugin_test_queue_db_result('db_fetch_row_prepared', array());

	flowview_launch_background_upgrade();

	expect($GLOBALS['__test_exec'])->toBe(array(
		array('/usr/bin/php', "-q '/var/www/cacti/plugins/flowview/flowview_upgrade.php'"),
	));

	expect($GLOBALS['__test_messages'])->toHaveCount(1)
		->and($GLOBALS['__test_messages'][0][0])->toBe('flowview_upgrade');

	expect($GLOBALS['__test_config']['flowview_db_upgrade_notified'])->toBe('on');
});

it('does not relaunch or re-notify while a non-stale run is registered', function () {
	$GLOBALS['__test_config']['flowview_db_upgrade_notified'] = 'on';

	// A registered run that is well within its timeout.
	plugin_test_queue_db_result('db_fetch_row_prepared', array(
		'started' => 1000,
		'now'     => 1050,
		'timeout' => 86400,
	));

	flowview_launch_background_upgrade();

	expect($GLOBALS['__test_exec'])->toBe(array())
		->and($GLOBALS['__test_messages'])->toBe(array());
});

it('relaunches when the registered run has outlived its timeout', function () {
	// started long enough ago that now - started exceeds timeout => stale.
	plugin_test_queue_db_result('db_fetch_row_prepared', array(
		'started' => 1000,
		'now'     => 1000 + 86400 + 60,
		'timeout' => 86400,
	));

	flowview_launch_background_upgrade();

	expect($GLOBALS['__test_exec'])->toHaveCount(1)
		->and($GLOBALS['__test_exec'][0][1])->toContain('flowview_upgrade.php')
		->and($GLOBALS['__test_messages'])->toHaveCount(1);
});

it('force launches even when a non-stale run is registered', function () {
	// A live run is registered, but --force must still relaunch.
	plugin_test_queue_db_result('db_fetch_row_prepared', array(
		'started' => 1000,
		'now'     => 1050,
		'timeout' => 86400,
	));

	flowview_launch_background_upgrade(true);

	expect($GLOBALS['__test_exec'])->toHaveCount(1);
});

it('treats a missing processes table as not running', function () {
	$GLOBALS['__test_tables']['processes'] = false;

	expect(flowview_background_upgrade_running())->toBeFalse();
});

it('reports a non-stale registered run as running', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', array(
		'started' => 1000,
		'now'     => 1050,
		'timeout' => 86400,
	));

	expect(flowview_background_upgrade_running())->toBeTrue();
});
