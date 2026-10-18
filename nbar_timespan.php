<?php

/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (!function_exists('fv_nbar_retention_days')) {
	exit;
}
/**
 * Limit preset choices to the configured retention window.
 *
 * @param array $presets Cacti timespan labels by identifier.
 * @param int $now Current Unix time, or null to read the clock.
 * @param int $days Configured retention in days.
 * @param int|string $weekday Cacti first-weekday setting.
 *
 * @return array
 */
function fv_nbar_timespan_options($presets, $now, $days, $weekday) {
	$options = [];
	$cutoff = $now - $days * 86400;
	foreach ($presets as $id => $label) {
		$span = [];
		get_timespan($span, $now, $id, $weekday);
		$from = strtotime($span['current_value_date1']);
		$to = strtotime($span['current_value_date2']);
		if ($from >= $cutoff && $to > $from && $to <= $now) {
			// The exact retention option below replaces duplicate rolling periods.
			if ($from === $cutoff && $to === $now) {
				continue;
			}
			$options[$id] = $label;
		}
	}
	$options[1000] = $days === 1 ? __('Last Day', 'flowview') : __('Last %s Days', $days, 'flowview');
	return $options;
}
/**
 * Clamp a requested range to retained history and the current time.
 *
 * @param int $start Inclusive range start in Unix seconds.
 * @param int $end Exclusive range end in Unix seconds.
 * @param int $now Current Unix time, or null to read the clock.
 * @param int $days Configured retention in days.
 *
 * @return array
 */
function fv_nbar_bound_dates($start, $end, $now, $days) {
	$cutoff = $now - $days * 86400;
	$from = max($cutoff, $start);
	$to = min($now, $end);
	if ($to <= $from) {
		return [$now - 1800, $now, true];
	}
	return [$from, $to, $from !== $start || $to !== $end];
}
