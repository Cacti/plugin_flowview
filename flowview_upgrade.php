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

$forcever    = '';

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--forcever':
				$forcever = $value;
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


/* we need to rerun the upgrade, force the current version */
if ($forcever == '') {
	$old_version = db_fetch_cell('SELECT version FROM plugin_config WHERE directory = "flowview"');
} else {
	$old_version = $forcever;
}

$info            = plugin_flowview_version();
$current_version = $info['version'];

/* do a version check */
if ($forcever == '' && $old_version == $current_version) {
	cacti_log('Your Flowview is already up to date (v' . $current_version . ' vs v' . $old_version . ') not upgrading.  Use --forcever to override', true, 'FLOWVIEW');
	exit(0);
}

if (!register_process_start('flowview', 'upgrade', 1, 86400)) {
	if (empty($forcever)) {
		print 'WARNING: Running process detected.  To override use --forcever' . PHP_EOL;
		exit(1);
	}
}

cacti_log('Upgrading from v' . $old_version . ' to ' . $current_version, true, 'FLOWVIEW');

flowview_upgrade($current_version, $old_version);

unregister_process('flowview', 'upgrade', 1);

exit(0);

function flowview_upgrade($current, $old) {
	global $config, $info;

	if ($current != $old) {

		api_plugin_register_hook('flowview', 'global_settings_update', 'flowview_global_settings_update', 'setup.php', true);

		api_plugin_register_hook('flowview', 'graph_buttons',            'flowview_graph_button', 'setup.php', true);
		api_plugin_register_hook('flowview', 'graph_buttons_thumbnails', 'flowview_graph_button', 'setup.php', true);

		if (function_exists('api_plugin_upgrade_register')) {
			api_plugin_upgrade_register('flowview');
		} else {
			db_execute_prepared("UPDATE plugin_config SET
				version = ?, name = ?, author = ?, webpage = ?
				WHERE directory = ?",
				[
					$info['version'],
					$info['longname'],
					$info['author'],
					$info['homepage'],
					$info['name']
				]
			);
		}

		$bad_titles = flowview_db_fetch_cell('SELECT COUNT(*)
			FROM plugin_flowview_schedules
			WHERE title=""');

		if (!flowview_db_column_exists('plugin_flowview_devices', 'cmethod', false)) {
			cacti_log("Adding column cmethod to plugin_flowview_devices table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_devices ADD COLUMN cmethod int unsigned default "0" AFTER name');

			flowview_db_execute('UPDATE plugin_flowview_devices SET cmethod=1');
		}

		if (flowview_db_column_exists('plugin_flowview_devices', 'nesting', false)) {
			cacti_log("Removing nesting columns from plugin_flowview_devices table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_devices
				DROP COLUMN nesting,
				DROP COLUMN version,
				DROP COLUMN rotation,
				DROP COLUMN expire,
				DROP COLUMN compression'
			);
		}

		if (!flowview_db_column_exists('plugin_flowview_devices', 'protocol', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_devices ADD COLUMN protocol char(3) NOT NULL default "UDP" AFTER port');
		}

		if (!flowview_db_column_exists('plugin_flowview_devices', 'enabled', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_devices ADD COLUMN enabled char(2) NOT NULL default "on" AFTER name');
		}

		if (flowview_db_column_exists('plugin_flowview_schedules', 'savedquery', false)) {
			cacti_log("Adding savedquery column to plugin_flowview_schedules table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_schedules CHANGE COLUMN savedquery query_id INT unsigned NOT NULL default "0"');
		}

		if (!flowview_db_column_exists('plugin_flowview_schedules', 'format_file', false)) {
			cacti_log("Adding format_file column to plugin_flowview_schedules table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_schedules ADD COLUMN format_file VARCHAR(128) DEFAULT "" AFTER email');
		}

		if (!flowview_db_column_exists('plugin_flowview_schedules', 'timeout', false)) {
			cacti_log("Adding timeout and notification_list columns to plugin_flowview_schedules table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_schedules
				ADD COLUMN timeout int(10) unsigned NOT NULL default 60 AFTER sendinterval,
				ADD COLUMN notification_list int(10) unsigned NOT NULL default 0 AFTER start,
				MODIFY COLUMN start timestamp NOT NULL default "0000-00-00"');
		}

		flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache');
		flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache_flow_stats');
		flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache_details');

		flowview_db_execute('ALTER TABLE plugin_flowview_queries MODIFY COLUMN protocols varchar(32) default ""');
		flowview_db_execute('ALTER TABLE plugin_flowview_queries MODIFY COLUMN sortfield varchar(15) default "bytes"');

		if (!flowview_db_column_exists('plugin_flowview_queries', 'device_id', false)) {
			cacti_log("Adding device_id column to plugin_flowview_queries table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_queries ADD COLUMN device_id int(11) unsigned NOT NULL default "0" AFTER name');
		}

		if (!flowview_db_column_exists('plugin_flowview_queries', 'template_id', false)) {
			cacti_log("Adding template_id column to plugin_flowview_queries table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_queries ADD COLUMN template_id int(10) NOT NULL default "-1" AFTER device_id');
		} else {
			$col_type = flowview_db_fetch_cell('SELECT COLUMN_TYPE
				FROM information_schema.COLUMNS
				WHERE TABLE_NAME = "plugin_flowview_queries"
				AND COLUMN_NAME = "template_id"
				LIMIT 1');

			if (strpos($col_type, 'unsigned') !== false) {
				cacti_log("Updating template_id column to plugin_flowview_queries table to signed.", true, 'FLOWVIEW');

				flowview_db_execute('ALTER TABLE plugin_flowview_queries MODIFY COLUMN template_id int(10) NOT NULL default "-1" AFTER device_id');
			}
		}

		/* check the lastsent default and fix it if required */
		$col_default = flowview_db_fetch_cell('SELECT COLUMN_DEFAULT
			FROM information_schema.COLUMNS
			WHERE TABLE_NAME = "plugin_flowview_schedules"
			AND COLUMN_NAME = "lastsent"
			LIMIT 1');

		if ($col_default == '' || stripos($col_default, 'null') !== false) {
			cacti_log("Updating lastsent column to plugin_flowview_schedules table to have a default.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_schedules MODIFY COLUMN lastsent bigint UNSIGNED NOT NULL default "0"');
		}

		if (!flowview_db_column_exists('plugin_flowview_queries', 'ex_addr', false)) {
			cacti_log("Adding ex_addr column to plugin_flowview_queries table.", true, 'FLOWVIEW');

			flowview_db_execute('ALTER TABLE plugin_flowview_queries ADD COLUMN ex_addr varchar(46) NOT NULL default "" AFTER template_id');
		}

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_arin_information` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`cidr` varchar(20) NOT NULL DEFAULT '',
			`net_range` varchar(64) NOT NULL DEFAULT '',
			`name` varchar(64) NOT NULL DEFAULT '',
			`parent` varchar(64) NOT NULL DEFAULT '',
			`net_type` varchar(64) NOT NULL DEFAULT '',
			`origin` varchar(64) NOT NULL DEFAULT '',
			`registration` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			`last_changed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
			`comments` varchar(128) NOT NULL DEFAULT '',
			`self` varchar(128) NOT NULL DEFAULT '',
			`alternate` varchar(128) NOT NULL DEFAULT '',
			`json_data` blob NOT NULL DEFAULT '',
			PRIMARY KEY (`id`),
			UNIQUE KEY `cidr` (`cidr`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC
			COMMENT='Holds ARIN Records Downloaded for Caching'");

		if (!db_column_exists('sent_by', 'reports_log', false)) {
			db_execute('DROP TABLE IF EXISTS reports_log');
		}

		db_execute("CREATE TABLE IF NOT EXISTS `reports_log` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(64) NOT NULL DEFAULT '',
			`source` varchar(20) NOT NULL DEFAULT '',
			`source_id` int(10) unsigned NOT NULL DEFAULT 0,
			`report_output_type` varchar(5) NOT NULL DEFAULT '',
			`report_raw_data` longblob,
			`report_raw_output` longblob,
			`report_txt_output` longblob,
			`report_html_output` longblob,
			`notification` blob NOT NULL DEFAULT '',
			`send_type` int(10) unsigned NOT NULL DEFAULT 0,
			`send_time` timestamp NOT NULL DEFAULT current_timestamp(),
			`run_time` double NOT NULL DEFAULT 0,
			`sent_by` varchar(20) NOT NULL DEFAULT '',
			`sent_id` int(11) NOT NULL DEFAULT -1,
			PRIMARY KEY (`id`),
			KEY `source` (`source`),
			KEY `source_id` (`source_id`))
			ENGINE=InnoDB
			COMMENT='Holds All Cacti Report Output'");

		db_execute("CREATE TABLE IF NOT EXISTS `reports_queued` (
			`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(64) NOT NULL DEFAULT '',
			`source` varchar(20) NOT NULL DEFAULT '',
			`source_id` int(10) unsigned NOT NULL DEFAULT 0,
			`status` varchar(10) NOT NULL DEFAULT 'pending',
			`scheduled_time` timestamp NOT NULL DEFAULT '0000-00-00',
			`start_time` timestamp NOT NULL DEFAULT '0000-00-00',
			`run_command` varchar(512) NOT NULL DEFAULT '',
			`run_timeout` int(10) NOT NULL DEFAULT '60',
			`notification` blob NOT NULL DEFAULT '',
			`request_type` int(10) unsigned NOT NULL DEFAULT 0,
			`requested_by` varchar(20) NOT NULL DEFAULT '',
			`requested_id` int(11) NOT NULL DEFAULT -1,
			PRIMARY KEY (`id`),
			KEY `source` (`source`),
			KEY `source_id` (`source_id`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC
			COMMENT='Holds Scheduled Reports'");

		if (db_column_exists('reports_queued', 'requeste_type', false)) {
			db_execute('ALTER TABLE reports_queued
				CHANGE COLUMN requeste_type request_type int(10) unsigned NOT NULL DEFAULT 0');
		}

		if (!flowview_db_column_exists('plugin_flowview_queries', 'graph_type', false)) {
			cacti_log("Adding charting columns to the plugin_flowview_queries table.", true, 'FLOWVIEW');

			flowview_db_execute("ALTER TABLE plugin_flowview_queries
				ADD COLUMN graph_type varchar(10) NOT NULL default 'bar' AFTER resolve,
				ADD COLUMN graph_height int unsigned NOT NULL default '400' AFTER graph_type,
				ADD COLUMN panel_table char(2) NOT NULL default 'on' AFTER graph_height,
				ADD COLUMN panel_bytes char(2) NOT NULL default 'on' AFTER panel_table,
				ADD COLUMN panel_packets char(2) NOT NULL default 'on' AFTER panel_bytes,
				ADD COLUMN panel_flows char(2) NOT NULL default 'on' AFTER panel_packets");
		}

		if (!flowview_db_column_exists('plugin_flowview_dnscache', 'id', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_dnscache
				ADD COLUMN id int(11) unsigned AUTO_INCREMENT FIRST,
				DROP PRIMARY KEY,
				ADD PRIMARY KEY(id),
				ADD UNIQUE KEY ip(ip),
				ADD COLUMN source VARCHAR(40) NOT NULL default "" AFTER host');
		}

		if (!flowview_db_column_exists('plugin_flowview_dnscache', 'arin_verified', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_dnscache
				ADD COLUMN arin_verified tinyint(3) unsigned DEFAULT 0 AFTER source,
				ADD COLUMN arin_id int(11) unsigned DEFAULT 0 AFTER arin_verified,
				ADD KEY `arin_id` (`arin_id`)');
		}

		flowview_db_execute('ALTER TABLE plugin_flowview_dnscache ENGINE=InnoDB');

		if ($bad_titles) {
			cacti_log("Fixing Bad Titles in the plugin_flowview_schedules table.", true, 'FLOWVIEW');

			/* update titles for those that don't have them */
			flowview_db_execute("UPDATE plugin_flowview_schedules SET title='Ugraded Schedule' WHERE title=''");

			/* Set the new version */
			db_execute_prepared("REPLACE INTO settings (name, value) VALUES ('plugin_flowview_version', ?)", [$current]);

			flowview_db_execute('ALTER TABLE plugin_flowview_devices ENGINE=InnoDB');
		}

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_device_streams` (
			device_id int(11) unsigned NOT NULL default '0',
			ex_addr varchar(46) NOT NULL default '',
			name varchar(64) NOT NULL default '',
			version varchar(5) NOT NULL default '',
			last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (device_id, ex_addr))
			ENGINE=InnoDB,
			ROW_FORMAT=DYNAMIC,
			COMMENT='Plugin Flowview - List of Streams coming into each of the listeners'");

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_device_templates` (
			device_id int(11) unsigned NOT NULL default '0',
			ex_addr varchar(46) NOT NULL default '',
			template_id int(10) NOT NULL default '0',
			supported tinyint unsigned NOT NULL default '0',
			column_spec blob default '',
			last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (device_id, ex_addr, template_id))
			ENGINE=InnoDB,
			ROW_FORMAT=DYNAMIC,
			COMMENT='Plugin Flowview - List of Stream Templates coming into each of the listeners'");

		if (!flowview_db_column_exists('plugin_flowview_device_templates', 'supported', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_device_templates
				ADD COLUMN supported tinyint unsigned NOT NULL default "0" AFTER template_id');
		}

		if (!flowview_db_column_exists('plugin_flowview_device_streams', 'version', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_device_streams
				ADD COLUMN version varchar(5) NOT NULL default "" AFTER name');
		}

		if (!flowview_db_column_exists('plugin_flowview_devices', 'last_updated', false)) {
			flowview_db_execute('ALTER TABLE plugin_flowview_devices ADD COLUMN last_updated TIMESTAMP NOT NULL default CURRENT_TIMESTAMP');
		}

		if (flowview_db_column_exists('plugin_flowview_device_streams', 'ext_addr', false)) {
			flowview_db_execute('TRUNCATE plugin_flowview_device_streams');

			flowview_db_execute('ALTER TABLE plugin_flowview_device_streams
				DROP PRIMARY KEY,
				CHANGE COLUMN ext_addr ex_addr varchar(46) NOT NULL default "",
				ADD PRIMARY KEY (device_id, ex_addr)');
		}

		if (flowview_db_column_exists('plugin_flowview_device_templates', 'ext_addr', false)) {
			flowview_db_execute('TRUNCATE plugin_flowview_device_templates');

			flowview_db_execute('ALTER TABLE plugin_flowview_device_templates
				DROP PRIMARY KEY,
				CHANGE COLUMN ext_addr ex_addr varchar(46) NOT NULL default "",
				ADD PRIMARY KEY (device_id, ex_addr, template_id)');
		}

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `parallel_database_query` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`md5sum` varchar(32) NOT NULL DEFAULT '',
			`md5sum_tables` varchar(32) NOT NULL DEFAULT '',
			`status` varchar(10) NOT NULL DEFAULT 'pending',
			`user_id` int(10) unsigned NOT NULL DEFAULT 0,
			`total_shards` int(10) unsigned NOT NULL DEFAULT 0,
			`cached_shards` int(10) unsigned NOT NULL DEFAULT 0,
			`finished_shards` int(10) unsigned NOT NULL DEFAULT 0,
			`map_table` varchar(40) NOT NULL DEFAULT '',
			`map_create` blob NOT NULL DEFAULT '',
			`map_query` blob NOT NULL DEFAULT '',
			`map_range` varchar(128) NOT NULL DEFAULT '',
			`map_range_params` varchar(128) NOT NULL DEFAULT '',
			`reduce_query` blob NOT NULL DEFAULT '',
			`results` longblob NOT NULL DEFAULT '',
			`created` timestamp NOT NULL DEFAULT current_timestamp(),
			`time_to_live` int(10) unsigned NOT NULL DEFAULT 300,
			PRIMARY KEY (`id`),
			KEY `user_id` (`user_id`),
			KEY `md5sum` (`md5sum`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC
			COMMENT='Holds Parallel Query Requests'");

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `parallel_database_query_shard` (
			`query_id` bigint(20) unsigned NOT NULL DEFAULT 0,
			`shard_id` int(10) unsigned NOT NULL DEFAULT 0,
			`full_scan` tinyint(3) unsigned DEFAULT 1,
			`status` varchar(10) NOT NULL DEFAULT 'pending',
			`map_table` varchar(64) NOT NULL DEFAULT '',
			`map_partition` varchar(20) NOT NULL DEFAULT '',
			`map_query` blob NOT NULL DEFAULT '',
			`map_params` blob NOT NULL DEFAULT '',
			`created` timestamp NULL DEFAULT current_timestamp(),
			`completed` timestamp NULL DEFAULT NULL,
			PRIMARY KEY (`query_id`,`shard_id`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC
			COMMENT='Holds Parallel Query Shard Requests'");

		if (!flowview_db_column_exists('parallel_database_query', 'map_range', false)) {
			flowview_db_execute("ALTER TABLE `parallel_database_query`
				ADD COLUMN map_range VARCHAR(128) NOT NULL default '' AFTER map_query,
				ADD COLUMN map_range_params VARCHAR(128) NOT NULL default '' AFTER map_range");
		}

		if (!flowview_db_column_exists('parallel_database_query', 'md5sum_tables', false)) {
			flowview_db_execute("ALTER TABLE `parallel_database_query`
				ADD COLUMN md5sum_tables VARCHAR(32) NOT NULL default '' AFTER md5sum");
		}

		if (!flowview_db_column_exists('parallel_database_query', 'cached_shards', false)) {
			flowview_db_execute("ALTER TABLE `parallel_database_query`
				ADD COLUMN cached_shards int(10) unsigned NOT NULL DEFAULT 0 AFTER total_shards");
		}

		if (!flowview_db_column_exists('parallel_database_query', 'map_create', false)) {
			flowview_db_execute("ALTER TABLE `parallel_database_query`
				ADD COLUMN map_create blob NOT NULL DEFAULT '' AFTER map_table");
		} else {
			flowview_db_execute("ALTER TABLE `parallel_database_query`
				MODIFY COLUMN map_create blob NOT NULL DEFAULT ''");
		}

		if (!flowview_db_column_exists('parallel_database_query_shard', 'full_scan', false)) {
			flowview_db_execute("ALTER TABLE `parallel_database_query_shard`
				ADD COLUMN full_scan tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER shard_id");
		}

		flowview_db_execute("CREATE TABLE IF NOT EXISTS `parallel_database_query_shard_cache` (
			`md5sum` varchar(32) NOT NULL DEFAULT '',
			`map_table` varchar(64) NOT NULL DEFAULT '',
			`map_partition` varchar(20) NOT NULL DEFAULT '',
			`min_date` timestamp(6) NOT NULL default '0000-00-00',
			`max_date` timestamp(6) NOT NULL default '0000-00-00',
			`results` longblob NOT NULL DEFAULT '',
			`date_created` timestamp DEFAULT current_timestamp(),
			PRIMARY KEY (`md5sum`,`map_table`,`map_partition`))
			ENGINE=InnoDB
			ROW_FORMAT=DYNAMIC
			COMMENT='Holds Parallel Query Shard Results for Partition Full Scans based upon the md5sum of the Map Query'");

		if (flowview_db_table_exists('parallel_database_query_shards')) {
			flowview_db_execute('RENAME TABLE parallel_database_query_shards TO parallel_database_query_shard');
		}

		/* going through a learning curve here */
		if (flowview_db_table_exists('plugin_flowivew_radb_routes')) {
			flowview_db_execute('DROP TABLE IF EXISTS plugin_flowivew_radb_routes');
		}

		if (flowview_db_table_exists('plugin_flowivew_routes')) {
			flowview_db_execute('DROP TABLE IF EXISTS plugin_flowivew_routes');
		}

		if (!flowview_db_column_exists('plugin_flowview_irr_route', 'country', false)) {
			flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_irr_route');
		}

		include($config['base_path'] . '/plugins/flowview/irr_tables.php');

		if (flowview_db_column_exists('plugin_flowview_arin_information', 'origin_as', false)) {
			flowview_db_execute("ALTER TABLE plugin_flowview_arin_information CHANGE COLUMN origin_as origin varchar(20) NOT NULL DEFAULT ''");
		}

		db_execute("UPDATE plugin_realms
			SET file='flowview_devices.php,flowview_schedules.php,flowview_filters.php,flowview_databases.php'
			WHERE plugin='flowview'
			AND file LIKE '%devices%'");

		$raw_tables = flowview_db_fetch_assoc('SELECT TABLE_NAME, TABLE_COLLATION, ENGINE
			FROM information_schema.TABLES
			WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"
			ORDER BY TABLE_NAME DESC');

		$raw_engine = get_set_default_fast_engine();

		foreach($raw_tables as $t) {
			$alter = '';

			if ($t['TABLE_COLLATION'] != 'latin1_swedish_ci') {
				$alter = 'CONVERT TO CHARACTER SET latin1';
			}

			if (flowview_db_column_exists($t['TABLE_NAME'], 'keycol', false)) {
				$alter .= ($alter != '' ? ', ':'') . 'DROP KEY keycol';
			}

			if (!flowview_db_column_exists($t['TABLE_NAME'], 'template_id', false)) {
				$alter .= ($alter != '' ? ', ':'') . 'ADD COLUMN template_id int(11) unsigned NOT NULL default "0" AFTER listener_id';
			}

			if (!flowview_db_index_exists($t['TABLE_NAME'], 'template_id')) {
				$alter .= ($alter != '' ? ', ':'') . 'ADD INDEX template_id (template_id)';
			}

			if (!flowview_db_index_exists($t['TABLE_NAME'], 'start_time')) {
				$alter .= ($alter != '' ? ', ':'') . 'ADD INDEX start_time (start_time)';
			}

			if (!flowview_db_index_exists($t['TABLE_NAME'], 'end_time')) {
				$alter .= ($alter != '' ? ', ':'') . 'ADD INDEX end_time (end_time)';
			}

			$columns = array_rekey(
				flowview_db_fetch_assoc('SHOW COLUMNS FROM ' . $t['TABLE_NAME']),
				'Field', ['Type', 'Null', 'Key', 'Default', 'Extra']
			);

			if (isset($columns['ex_addr']) && stripos($columns['ex_addr']['Type'], 'VARCHAR') === false) {
				$alter .= ($alter != '' ? ', ':'') . 'MODIFY COLUMN ex_addr VARCHAR(46) NOT NULL default ""';
				$ex_change = true;
			} else {
				$ex_change = false;
			}

			if (!flowview_db_index_exists($t['TABLE_NAME'], 'ex_addr')) {
				$alter .= ($alter != '' ? ', ':'') . 'ADD INDEX ex_addr (ex_addr)';
			}

			if ($t['ENGINE'] != $raw_engine) {
				if ($raw_engine == 'Aria') {
					$alter .= ($alter != '' ? ',':'') . " ENGINE=$raw_engine ROW_FORMAT=page";
				} else {
					$alter .= ($alter != '' ? ',':'') . " ENGINE=$raw_engine ROW_FORMAT=Dynamic";
				}
			}

			if ($alter != '') {
				cacti_log("Altering Table {$t['TABLE_NAME']} Using this alter $alter.", true, 'FLOWVIEW');

				flowview_db_execute('ALTER TABLE ' . $t['TABLE_NAME'] . ' ' . $alter);

				if ($ex_change) {
					flowview_db_execute('UPDATE ' . $t['TABLE_NAME'] . ' SET ex_addr = SUBSTRING_INDEX(ex_addr, ":", 1)');
				}
			}

			flowview_db_execute('ALTER TABLE plugin_flowview_arin_information
				MODIFY COLUMN comments VARCHAR(512) NOT NULL default ""');

			flowview_db_execute('ALTER TABLE plugin_flowview_queries
				MODIFY COLUMN protocols VARCHAR(255) NOT NULL default ""');
		}

		if (!flowview_db_column_exists('plugin_flowview_devices', 'bind_address', false)) {
			flowview_db_execute("ALTER TABLE `plugin_flowview_devices`
				ADD COLUMN bind_address VARCHAR(32) NOT NULL default '0.0.0.0' AFTER cmethod");
		}

		if (flowview_db_column_exists('plugin_flowview_devices', 'allowfrom', false)) {
			flowview_db_execute("ALTER TABLE plugin_flowview_devices MODIFY COLUMN allowfrom varchar(256) NOT NULL DEFAULT '0.0.0.0'");
		}

		flowview_db_execute('UPDATE plugin_flowview_devices SET bind_address = "0.0.0.0" WHERE bind_address = "0"');
		flowview_db_execute('UPDATE plugin_flowview_devices SET allowfrom = "0.0.0.0" WHERE allowfrom = "0"');

		cacti_log('Flowview Database Upgrade Complete', true, 'FLOWVIEW');
	}

}

/*  display_version - displays version information */
function display_version() {
	$info    = plugin_flowview_version();
	$version = $info['version'];

	print "Cacti Flowview Database Upgrade Utility, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: flowview_database.php [--forcever=VERSION]' . PHP_EOL . PHP_EOL;
	print 'A command line version of the Cacti Flowview database upgrade tool.  You must execute' . PHP_EOL;
	print 'this command as a super user, or someone who can write a PHP session file.' . PHP_EOL;
	print 'Typically, this user account will be apache, www-run, or root.' . PHP_EOL . PHP_EOL;
	print 'If you are running a beta or alpha version of Cacti and need to rerun' . PHP_EOL;
	print 'the upgrade script, simply set the forcever to the previous release.' . PHP_EOL . PHP_EOL;
	print '--forcever - Force the starting version, say ' . CACTI_VERSION . PHP_EOL;
}
