<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

// Loaded by flowview_upgrade.php after Cacti's CLI bootstrap. Keep schema
// mutations out of database.php, which is included by ordinary web requests.
/** Repair legacy state only from the background upgrade process. */
function flowview_filter_status_upgrade(): bool {
	if (!flowview_db_table_exists('plugin_flowview_queries')) {
		return false;
	}
	if (flowview_filter_status_ready()) {
		return true;
	}
	if (flowview_db_execute("ALTER TABLE plugin_flowview_queries ADD COLUMN enabled char(2) NOT NULL DEFAULT 'on' AFTER name", false)) {
		return true;
	}
	return flowview_filter_status_ready();
}
