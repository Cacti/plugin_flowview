<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2020 The Cacti Group                                 |
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

function plugin_flowview_install() {
	api_plugin_register_hook('flowview', 'config_arrays',         'flowview_config_arrays',        'setup.php');
	api_plugin_register_hook('flowview', 'draw_navigation_text',  'flowview_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('flowview', 'config_settings',       'flowview_config_settings',      'setup.php');
	api_plugin_register_hook('flowview', 'poller_bottom',         'flowview_poller_bottom',        'setup.php');
	api_plugin_register_hook('flowview', 'top_header_tabs',       'flowview_show_tab',             'setup.php');
	api_plugin_register_hook('flowview', 'top_graph_header_tabs', 'flowview_show_tab',             'setup.php');
	api_plugin_register_hook('flowview', 'page_head',             'flowview_page_head',            'setup.php');

	api_plugin_register_realm('flowview', 'flowview.php', __('Plugin -> Flow Viewer', 'flowview'), 1);
	api_plugin_register_realm('flowview', 'flowview_devices.php,flowview_schedules.php,flowview_filters.php', __('Plugin -> Flow Admin', 'flowview'), 1);

	flowview_setup_table();
}

function plugin_flowview_uninstall() {
	// Do any extra Uninstall stuff here
}

function plugin_flowview_check_config() {
	// Here we will check to ensure everything is configured
	plugin_flowview_check_upgrade();
	return true;
}

function plugin_flowview_upgrade() {
	// Here we will upgrade to the newest version
	plugin_flowview_check_upgrade();
	return false;
}

function plugin_flowview_check_upgrade() {
	$files = array('plugins.php', 'flowview.php', 'index.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$info    = plugin_flowview_version();
	$current = $info['version'];
	$old     = read_config_option('plugin_flowview_version');

	if ($current != $old) {
		$bad_titles = db_fetch_cell('SELECT COUNT(*)
			FROM plugin_flowview_schedules
			WHERE title=""');

		if (!db_column_exists('plugin_flowview_devices', 'cmethod')) {
			db_execute('ALTER TABLE plugin_flowview_devices ADD COLUMN cmethod int unsigned default "0" AFTER name');

			db_execute('UPDATE plugin_flowview_devices SET cmethod=1');
		}

		if (db_column_exists('plugin_flowview_devices', 'nesting')) {
			db_execute('ALTER TABLE plugin_flowview_devices
				DROP COLUMN nesting,
				DROP COLUMN version,
				DROP COLUMN rotation,
				DROP COLUMN expire,
				DROP COLUMN compression'
			);

		}

		if (db_column_exists('plugin_flowview_schedules', 'savedquery')) {
			db_execute('ALTER TABLE plugin_flowview_schedules CHANGE COLUMN savedquery query_id INT unsigned NOT NULL default "0"');
		}

		if (!db_column_exists('plugin_flowview_schedules', 'format_file')) {
			db_execute('ALTER TABLE plugin_flowview_schedules ADD COLUMN format_file VARCHAR(128) DEFAULT "" AFTER email');
		}

		db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache');
		db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache_flow_stats');
		db_execute('DROP TABLE IF EXISTS plugin_flowview_session_cache_details');

		db_execute('ALTER TABLE plugin_flowview_queries MODIFY COLUMN protocols varchar(32) default ""');

		if (!db_column_exists('plugin_flowview_queries', 'device_id')) {
			db_execute('ALTER TABLE plugin_flowview_queries ADD COLUMN device_id int unsigned NOT NULL default "0" AFTER name');
		}

		$raw_tables = db_fetch_assoc('SELECT TABLE_NAME
			FROM information_schema.TABLES
			WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"');

		if (cacti_sizeof($raw_tables)) {
			foreach($raw_tables as $t) {
				cacti_log('NOTE: Updating unique key for ' . $t['TABLE_NAME'], false, 'FLOWVIEW');

				db_execute('ALTER TABLE ' . $t['TABLE_NAME'] . '
					DROP INDEX `keycol`,
					ADD UNIQUE INDEX `keycol` (`listener_id`,`src_addr`,`src_port`,`dst_addr`,`dst_port`, `start_time`, `end_time`)');
			}
		}

		if ($bad_titles) {
			/* update titles for those that don't have them */
			db_execute("UPDATE plugin_flowview_schedules SET title='Ugraded Schedule' WHERE title=''");

			/* Set the new version */
			db_execute_prepared("REPLACE INTO settings (name, value) VALUES ('plugin_flowview_version', ?)", array($current));

			db_execute('ALTER TABLE plugin_flowview_devices ENGINE=InnoDB');
		}

		db_execute("UPDATE plugin_realms
			SET file='flowview_devices.php,flowview_schedules.php,flowview_filters.php'
			WHERE plugin='flowview'
			AND file LIKE '%devices%'");

		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='flowview'");

		db_execute("UPDATE plugin_config SET
			version='" . $info['version']  . "',
			name='"    . $info['longname'] . "',
			author='"  . $info['author']   . "',
			webpage='" . $info['homepage'] . "'
			WHERE directory='" . $info['name'] . "' ");

		flowview_setup_table();
	}
}

function plugin_flowview_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/flowview/INFO', true);
	return $info['info'];
}

function flowview_config_arrays() {
	global $menu, $menu_glyphs, $messages;

	$messages['flow_deleted'] = array('message' => __('The Filter has been Deleted', 'flowview'), 'type' => 'info');
	$messages['flow_updated'] = array('message' => __('The Filter has been Updated', 'flowview'), 'type' => 'info');

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Import/Export')) {
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_devices.php']   = __('Listeners', 'flowview');
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_filters.php']   = __('Filters', 'flowview');
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_schedules.php'] = __('Schedules', 'flowview');
		}
	}
	$menu = $menu2;

	$menu_glyphs[__('FlowView', 'flowview')] = 'fas fa-crosshairs';

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('Normal User'), array('flowview.php'));
		auth_augment_roles(__('System Administration'), array('flowview_devices.php','flowview_schedules.php','flowview_filters.php'));
	}

	plugin_flowview_check_upgrade();
}

function flowview_draw_navigation_text($nav) {
	$nav['flowview.php:'] = array(
		'title' => __('Flow Viewer', 'flowview'),
		'mapping' => '',
		'url' => 'flowview.php',
		'level' => '0'
	);

	$nav['flowview.php:view'] = array(
		'title' => __('(view)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview.php',
		'level' => '1'
	);

	$nav['flowview.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview.php',
		'level' => '1'
	);

	$nav['flowview.php:loadquery'] = array(
		'title' => __('Flow Viewer', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview.php',
		'level' => '1'
	);

	$nav['flowview_devices.php:'] = array(
		'title' => __('Listeners', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_devices.php',
		'level' => '1'
	);

	$nav['flowview_devices.php:edit'] = array(
		'title' => __('(edit)', 'flowview'),
		'mapping' => 'index.php:,flowview_devices.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_devices.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_devices.php:actions'] = array(
		'title' => __('(actions)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:'] = array(
		'title' => __('Schedules', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_schedules.php',
		'level' => '1'
	);

	$nav['flowview_schedules.php:edit'] = array(
		'title' => __('(edit)', 'flowview'),
		'mapping' => 'index.php:,flowview_schedules.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:actions'] = array(
		'title' => __('(actions)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	$nav['flowview_filters.php:'] = array(
		'title' => __('Filters', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_filters.php',
		'level' => '1'
	);

	$nav['flowview_filters.php:edit'] = array(
		'title' => __('(edit)', 'flowview'),
		'mapping' => 'index.php:,flowview_filters.php:',
		'url' => 'flowview_filters.php',
		'level' => '2'
	);

	$nav['flowview_filters.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_filters.php',
		'level' => '2'
	);

	$nav['flowview_filters.php:actions'] = array(
		'title' => __('(actions)', 'flowview'),
		'mapping' => 'index.php:',
		'url' => 'flowview_filters.php',
		'level' => '2'
	);

	return $nav;
}

function flowview_show_tab() {
	global $config;

	if (api_user_realm_auth('flowview.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'flowview')) {
			print '<a href="' . htmlspecialchars($config['url_path'] . 'plugins/flowview/flowview.php') . '"><img src="' . $config['url_path'] . 'plugins/flowview/images/tab_flows_down.gif" alt="' . __('FlowView', 'flowview') . '"></a>';
		} else {
			print '<a href="' . htmlspecialchars($config['url_path'] . 'plugins/flowview/flowview.php') . '"><img src="' . $config['url_path'] . 'plugins/flowview/images/tab_flows.gif" alt="' . __('FlowView', 'flowview') . '"></a>';
		}
	}
}

function flowview_page_head() {
	global $config, $colors;

	$theme = get_selected_theme();

	if (file_exists($config['base_path'] . '/plugins/flowview/themes/' . $theme . '.css')) {
		print '<link href="' . $config['url_path'] . 'plugins/flowview/themes/' . $theme . '.css" type="text/css" rel="stylesheet">' . PHP_EOL;
	} else {
		print '<link href="' . $config['url_path'] . 'plugins/flowview/themes/default.css" type="text/css" rel="stylesheet">' . PHP_EOL;
	}
}

function flowview_config_settings() {
	global $config, $settings, $tabs;

	include_once($config['base_path'] . '/lib/reports.php');

	$formats = reports_get_format_files();

	$temp = array(
		'flowview_header' => array(
			'friendly_name' => __('Flow Viewer', 'flowview'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'flowview_format_file' => array(
			'friendly_name' => __('Format File to Use', 'monitor'),
			'method' => 'drop_array',
			'default' => 'default.format',
			'description' => __('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.  If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.  These files are located in the \'formats\' directory.', 'monitor'),
			'array' => $formats
		),
		'flowview_retention' => array(
			'friendly_name' => __('Data Retention Policy', 'flowview'),
			'description' => __('The amount of time Cacti will maintain the partitioned Flow tables.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				7   => __('%d Week', 1, 'flowview'),
				14  => __('%d Weeks', 2, 'flowview'),
				21  => __('%d Weeks', 3, 'flowview'),
				30  => __('%d Month', 1, 'flowview'),
				60  => __('%d Months', 2, 'flowview'),
				90  => __('%d Months', 3, 'flowview'),
				120 => __('%d Months', 4, 'flowview'),
				183 => __('%d Months', 6, 'flowview'),
				365 => __('%d Year', 1, 'flowview')
			),
			'default' => 30
		),
		'flowview_partition' => array(
			'friendly_name' => __('Database Partitioning Scheme', 'flowview'),
			'description' => __('Depending on the number of flows per minute, you may require more tables per day.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				0 => __('Daily', 'flowview'),
				1 => __('Hourly', 'flowview')
			),
			'default' => 0
		)
	);

	$tabs['misc'] = __('Misc', 'flowview');

	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function flowview_poller_bottom() {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	$time = time() - 86400;

	db_execute("DELETE FROM plugin_flowview_dnscache
		WHERE time > 0
		AND time < $time");

	$t = time();

	$command_string = trim(read_config_option('path_php_binary'));

	if (trim($command_string) == '') {
		$command_string = 'php';
	}

	$extra_args = ' -q ' . $config['base_path'] . '/plugins/flowview/flowview_process.php';
	exec_background($command_string, $extra_args);
}

function flowview_setup_table() {
	global $config;

	$data = array();
	$data['columns'][]  = array('name' => 'ip', 'type' => 'varchar(45)', 'NULL' => false, 'default' => '');
	$data['columns'][]  = array('name' => 'host', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][]  = array('name' => 'time', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['keys'][]     = array('name' => 'ip', 'columns' => 'ip');
	$data['type']       = 'MEMORY';
	$data['comment']    = 'Plugin Flowview - DNS Cache to help speed things up';
	api_plugin_db_table_create('flowview', 'plugin_flowview_dnscache', $data);

	$data = array();
	$data['columns'][]  = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]  = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'cmethod', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => '0');
	$data['columns'][]  = array('name' => 'allowfrom', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '0');
	$data['columns'][]  = array('name' => 'port', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);
	$data['primary']    = 'id';
	$data['type']       = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	$data['comment']    = 'Plugin Flowview - List of Devices to collect flows from';
	api_plugin_db_table_create('flowview', 'plugin_flowview_devices', $data);

	$data = array();
	$data['columns'][]  = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]  = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'device_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'timespan', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'default' => 0);
	$data['columns'][]  = array('name' => 'startdate', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'enddate', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'tosfields', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'tcpflags', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'protocols', 'type' => 'varchar(32)', 'NULL' => true);
	$data['columns'][]  = array('name' => 'sourceip', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'sourceport', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'sourceinterface', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'sourceas', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'destip', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'destport', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'destinterface', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'destas', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'statistics', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'printed', 'type' => 'int(3)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'includeif', 'type' => 'int(2)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'sortfield', 'type' => 'int(2)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'cutofflines', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'cutoffoctets', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'resolve', 'type' => 'varchar(2)', 'NULL' => false);
	$data['primary']    = 'id';
	$data['type']       = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	$data['comment']    = 'Plugin Flowview - List of Saved Flow Queries';
	api_plugin_db_table_create('flowview', 'plugin_flowview_queries', $data);

	$data = array();
	$data['columns'][]  = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]  = array('name' => 'title', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][]  = array('name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on');
	$data['columns'][]  = array('name' => 'sendinterval', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'lastsent', 'type' => 'bigint(20)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'start', 'type' => 'datetime', 'NULL' => false);
	$data['columns'][]  = array('name' => 'email', 'type' => 'text', 'NULL' => false);
	$data['columns'][]  = array('name' => 'format_file', 'type' => 'varchar(128)', 'NULL' => true, 'default' => '');
	$data['columns'][]  = array('name' => 'query_id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);
	$data['primary']    = 'id';
	$data['keys'][]     = array('name' => 'query_id', 'columns' => 'query_id');
	$data['type']       = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	$data['comment']    = 'Plugin Flowview - Scheduling for running and emails of saved queries';
	api_plugin_db_table_create('flowview', 'plugin_flowview_schedules', $data);

	$data = array();
	$data['columns'][]  = array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true);
	$data['columns'][]  = array('name' => 'service', 'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][]  = array('name' => 'port', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false);
	$data['columns'][]  = array('name' => 'proto', 'type' => 'char(4)', 'NULL' => false);
	$data['columns'][]  = array('name' => 'description','type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary']    = 'id';
	$data['type']       = 'InnoDB';
	$data['row_format'] = 'Dynamic';
	$data['comment']    = 'Plugin Flowview - Database of well known Ports';
	api_plugin_db_table_create('flowview', 'plugin_flowview_ports', $data);

	$inserts = file($config['base_path'] . '/plugins/flowview/plugin_flowview_ports.sql');
	if (cacti_sizeof($inserts)) {
		db_execute('TRUNCATE plugin_flowview_ports');
		foreach($inserts as $i) {
			db_execute($i);
		}
	}
}

