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

if (!function_exists('flowview_filters_render_with_layout')) {
	function flowview_filters_render_with_layout($renderer, $respect_embed = false) {
		$should_wrap = (!$respect_embed || !isset_request_var('embed'));

		if ($should_wrap) {
			top_header();
		}

		call_user_func($renderer);

		if ($should_wrap) {
			bottom_footer();
		}
	}
}
