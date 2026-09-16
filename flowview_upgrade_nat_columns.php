#!/usr/bin/env php
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

/*
 * flowview_upgrade_nat_columns.php - Adds the post-NAT (Network Address
 * Translation) columns introduced for issue#110 to existing
 * 'plugin_flowview_raw_*' partition tables.
 *
 * This is deliberately a standalone CLI rather than part of the automatic
 * background schema upgrade (flowview_upgrade.php): a busy install can have
 * hundreds of large raw partition tables, and altering all of them can take
 * a long time.  Run this manually (or on a schedule) after upgrading the
 * plugin to backfill existing partitions; new partitions created going
 * forward already include these columns (see create_raw_partition() in
 * functions.php).
 *
 * Older partitions that are never run through this tool keep working: the
 * query engine (flowview_nat_safe_sql() in functions.php) detects missing
 * NAT columns per-table and substitutes NULL for them, so reports simply
 * show no NAT data for those older partitions instead of erroring.
 */

chdir('../../');
include('./include/cli_check.php');
include_once('./plugins/flowview/functions.php');
include_once('./plugins/flowview/setup.php');
include_once('./plugins/flowview/database.php');

flowview_connect();

ini_set('max_execution_time', '0');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$dry_run = false;

if (cacti_sizeof($parms)) {
	foreach ($parms as $parameter) {
		switch ($parameter) {
			case '--dry-run':
				$dry_run = true;

				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit(0);
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit(0);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(1);
		}
	}
}

/* name => column definition, matching create_raw_partition() in functions.php */
$nat_columns = [
	'post_nat_src_addr'    => "varbinary(16) NOT NULL DEFAULT ''",
	'post_nat_src_domain'  => "varchar(256) NOT NULL DEFAULT ''",
	'post_nat_src_rdomain' => "varchar(80) NOT NULL DEFAULT ''",
	'post_nat_src_port'    => "int(11) unsigned NOT NULL DEFAULT '0'",
	'post_nat_dst_addr'    => "varbinary(16) NOT NULL DEFAULT ''",
	'post_nat_dst_domain'  => "varchar(256) NOT NULL DEFAULT ''",
	'post_nat_dst_rdomain' => "varchar(80) NOT NULL DEFAULT ''",
	'post_nat_dst_port'    => "int(11) unsigned NOT NULL DEFAULT '0'"
];

$tables = flowview_db_fetch_assoc('SELECT TABLE_NAME
	FROM information_schema.TABLES
	WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"
	ORDER BY TABLE_NAME');

$altered = 0;
$skipped = 0;

if (cacti_sizeof($tables)) {
	foreach ($tables as $t) {
		$table  = $t['TABLE_NAME'];
		$adding = [];

		foreach ($nat_columns as $column => $definition) {
			if (!flowview_db_column_exists($table, $column, false)) {
				$adding[] = "ADD COLUMN `$column` $definition";
			}
		}

		if (!cacti_sizeof($adding)) {
			print "Skipping Table $table, NAT columns already present" . PHP_EOL;

			$skipped++;

			continue;
		}

		print "Altering Table $table, adding " . cacti_sizeof($adding) . ' NAT column(s)' . PHP_EOL;

		if (!$dry_run) {
			flowview_db_execute("ALTER TABLE `$table` " . implode(', ', $adding));
		}

		$altered++;
	}
}

print PHP_EOL . "Complete.  Altered:$altered, Already Upgraded:$skipped" . ($dry_run ? ' (dry run, no changes made)':'') . PHP_EOL;

/*  display_version - displays version information */
function display_version() {
	$info    = plugin_flowview_version();
	$version = $info['version'];

	print "Cacti Flowview NAT Column Upgrade Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help() {
	display_version();

	print PHP_EOL . 'usage: flowview_upgrade_nat_columns.php [--dry-run]' . PHP_EOL . PHP_EOL;
	print 'Adds the post-NAT columns (issue#110) to existing plugin_flowview_raw_*' . PHP_EOL;
	print 'partition tables that pre-date this feature.  Safe to re-run; tables that' . PHP_EOL;
	print 'already have the columns are skipped.' . PHP_EOL . PHP_EOL;
	print '--dry-run   Report which tables would be altered without making changes.' . PHP_EOL;
}
