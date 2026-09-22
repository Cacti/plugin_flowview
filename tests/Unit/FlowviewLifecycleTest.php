<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for the plugin lifecycle contract functions in setup.php:
 * plugin_flowview_check_config(), plugin_flowview_upgrade(), and
 * plugin_flowview_check_upgrade()'s page-guard.
 *
 * plugin_flowview_check_upgrade() include_once()s Cacti core's real
 * lib/poller.php and calls flowview_connect() (a real database connection
 * routine) once its page guard passes, so only the guarded (skipped)
 * path is exercised here - the same constraint documented for other
 * plugins' poller_bottom-style functions in this fleet.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

beforeEach(function () {
	plugin_test_reset();
	$_SERVER['PHP_SELF'] = '/graphs.php';
});

it('reports invalid config and raises a message when no config file exists', function () {
	expect(plugin_flowview_check_config())->toBeFalse();
	expect($GLOBALS['__test_messages'])->not->toBeEmpty();
	expect($GLOBALS['__test_messages'][0][0])->toBe('flowview_info');
});

it('always reports no upgrade pending', function () {
	expect(plugin_flowview_upgrade())->toBeFalse();
});

it('skips the upgrade check on pages that do not need it', function () {
	plugin_flowview_check_upgrade();

	expect($GLOBALS['__test_db_calls'])->toBeEmpty();
});
