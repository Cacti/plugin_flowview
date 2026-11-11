<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

beforeEach(function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_timespan.php');
});

it('keeps in-retention presets, drops the full-window duplicate, and appends the retention option', function () {
	$now    = strtotime('2026-10-22 12:00:00');
	$days   = 7;
	$cutoff = $now - $days * 86400;

	// Drive get_timespan so each preset lands in a specific place relative to
	// the retention window, exercising every branch of the preset filter.
	$GLOBALS['__test_get_timespan'] = function (&$span, $tnow, $timespan, $weekday) use ($now, $cutoff) {
		$windows = [
			'inside' => [$cutoff + 3600, $now - 3600], // inside retention, not the full window
			'full'   => [$cutoff, $now],               // exactly the retention window -> skipped
			'before' => [$cutoff - 86400, $now - 3600], // starts before the cutoff -> excluded
			'future' => [$cutoff + 3600, $now + 3600],  // ends after now -> excluded
		];

		[$from, $to] = $windows[$timespan];
		$span['current_value_date1'] = date('Y-m-d H:i:s', $from);
		$span['current_value_date2'] = date('Y-m-d H:i:s', $to);
	};

	$presets = [
		'inside' => 'Inside',
		'full'   => 'Full',
		'before' => 'Before',
		'future' => 'Future',
	];

	expect(fv_nbar_timespan_options($presets, $now, $days, 1))->toBe([
		'inside' => 'Inside',
		1000     => 'Last 7 Days',
	]);
});

it('labels the retention option as a single day when retention is one day', function () {
	$now = strtotime('2026-10-22 12:00:00');

	$GLOBALS['__test_get_timespan'] = function (&$span, $tnow, $timespan, $weekday) use ($now) {
		$span['current_value_date1'] = date('Y-m-d H:i:s', $now - 1800);
		$span['current_value_date2'] = date('Y-m-d H:i:s', $now);
	};

	expect(fv_nbar_timespan_options(['recent' => 'Recent'], $now, 1, 1))->toBe([
		'recent' => 'Recent',
		1000     => 'Last Day',
	]);
});
