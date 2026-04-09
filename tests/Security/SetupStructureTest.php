<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify setup.php defines required plugin hooks and info function.
 */

describe('flowview setup.php structure', function () {
	$source = file_get_contents(realpath(__DIR__ . '/../../setup.php'));

	it('defines plugin_flowview_install function', function () use ($source) {
		expect($source)->toContain('function plugin_flowview_install');
	});

	it('defines plugin_flowview_version function', function () use ($source) {
		expect($source)->toContain('function plugin_flowview_version');
	});

	it('defines plugin_flowview_uninstall function', function () use ($source) {
		expect($source)->toContain('function plugin_flowview_uninstall');
	});

	it('returns version array with name key', function () use ($source) {
		expect($source)->toMatch('/[\'\""]name[\'\""]\s*=>/');
	});

	it('returns version array with version key', function () use ($source) {
		expect($source)->toMatch('/[\'\""]version[\'\""]\s*=>/');
	});
});
