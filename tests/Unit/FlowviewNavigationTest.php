<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Unit coverage for flowview_draw_navigation_text() in setup.php.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
});

it('adds the flowview breadcrumb entries without disturbing existing ones', function () {
	$nav = flowview_draw_navigation_text(array('other.php:' => array('title' => 'Other')));

	expect($nav)->toHaveKey('other.php:');

	foreach (array('flowview.php:', 'flowview.php:view', 'flowview_devices.php:', 'flowview_schedules.php:', 'flowview_filters.php:') as $key) {
		expect($nav)->toHaveKey($key);
	}

	expect($nav['flowview_devices.php:edit']['mapping'])->toBe('index.php:,flowview_devices.php:');
});
