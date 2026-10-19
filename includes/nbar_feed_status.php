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

/**
 * Render record freshness independently for each selected capture.
 *
 * @param array $devices     Available capture definitions.
 * @param array $capture_ids Selected capture identifiers.
 *
 * @return void
 */
function fv_nbar_feed_status(array $devices, array $capture_ids): void {
	$now = time();
	print '<div class="nbar-feed-status"><strong>' . fv_nbar_h(__('STATUS:', 'flowview')) . '</strong> ';
	$items = [];

	foreach ($devices as $device) {
		$id = (int) $device['id'];

		if (!in_array($id, $capture_ids, true)) {
			continue;
		}
		// Indexed lookup independent of historical report filters.
		$latest = flowview_db_fetch_row_prepared('SELECT minute FROM plugin_flowview_nbar_data WHERE listener_id=? ORDER BY minute DESC LIMIT 1', [$id]);

		if (!is_array($latest)) {
			$items[] = '<span class="textError">' . fv_nbar_h($device['name']) . ': ' . fv_nbar_h(__('Unable to load NBAR status. Please try again.', 'flowview')) . '</span>';

			continue;
		}
		$last      = isset($latest['minute']) ? (int) $latest['minute'] : 0;
		$receiving = $last > 0 && $last >= $now - 300 && $last <= $now + 60;
		$label     = $receiving ? __('Receiving', 'flowview') : __('No recent NBAR', 'flowview');
		$seen      = $last > 0 ? __('Last NBAR record: %s', date('Y-m-d H:i', $last), 'flowview') : __('No retained NBAR records', 'flowview');
		$icon      = $receiving ? 'fa-check-circle' : 'fa-times-circle';
		$color     = $receiving ? '#27852b' : '#d32f2f';
		$items[]   = '<span title="' . fv_nbar_h($seen) . '"><i class="fa ' . $icon . '" style="color:' . $color . '" aria-hidden="true"></i> ' . fv_nbar_h($device['name']) . ': ' . fv_nbar_h($label) . ' <small>(' . fv_nbar_h($seen) . ')</small></span>';
	}
	print implode(' &nbsp; | &nbsp; ', $items);

	if (!$items) {
		print fv_nbar_h(__('No captures selected', 'flowview'));
	}
	print '</div>';
}
