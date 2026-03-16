<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 */

require_once __DIR__ . '/../ui_helpers.php';

$events = [];
$request_vars = [];

function top_header() {
	global $events;
	$events[] = 'top';
}

function bottom_footer() {
	global $events;
	$events[] = 'bottom';
}

function isset_request_var($key) {
	global $request_vars;
	return isset($request_vars[$key]);
}

function test_renderer() {
	global $events;
	$events[] = 'render';
}

function assert_same($expected, $actual, $message) {
	if ($expected !== $actual) {
		fwrite(STDERR, $message . PHP_EOL);
		fwrite(STDERR, 'Expected: ' . json_encode($expected) . PHP_EOL);
		fwrite(STDERR, 'Actual:   ' . json_encode($actual) . PHP_EOL);
		exit(1);
	}
}

function assert_regex($pattern, $subject, $message) {
	if (!preg_match($pattern, $subject)) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

flowview_filters_render_with_layout('test_renderer');
assert_same(['top', 'render', 'bottom'], $events, 'Default wrapper should render with layout.');

$events = [];
$request_vars['embed'] = 1;
flowview_filters_render_with_layout('test_renderer', true);
assert_same(['render'], $events, 'Embed-aware wrapper should skip layout when embed is set.');

$events = [];
flowview_filters_render_with_layout('test_renderer', false);
assert_same(['top', 'render', 'bottom'], $events, 'Non-embed wrapper should keep layout even when embed is set.');

$source = file_get_contents(__DIR__ . '/../flowview_filters.php');
if ($source === false) {
	fwrite(STDERR, 'Unable to read flowview_filters.php' . PHP_EOL);
	exit(1);
}

assert_regex(
	"/flowview_filters_render_with_layout\\(\\s*'edit_filter'\\s*,\\s*true\\s*\\)\\s*;/",
	$source,
	'Expected edit action to use shared layout helper.'
);

assert_regex(
	"/flowview_filters_render_with_layout\\(\\s*'show_filters'\\s*\\)\\s*;/",
	$source,
	'Expected default action to use shared layout helper.'
);

echo "OK\n";
