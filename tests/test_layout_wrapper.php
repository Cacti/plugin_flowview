<?php

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

if (strpos($source, "flowview_filters_render_with_layout('edit_filter', true);") === false) {
	fwrite(STDERR, 'Expected edit action to use shared layout helper.' . PHP_EOL);
	exit(1);
}

if (strpos($source, "flowview_filters_render_with_layout('show_filters');") === false) {
	fwrite(STDERR, 'Expected default action to use shared layout helper.' . PHP_EOL);
	exit(1);
}

echo "OK\n";
