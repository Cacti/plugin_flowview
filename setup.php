<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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
	api_plugin_register_realm('flowview', 'flowview_devices.php,flowview_schedules.php,flowview_filters.php,flowview_dnscache.php', __('Plugin -> Flow Admin', 'flowview'), 1);

	flowview_determine_config();

	if (defined('FLOWVIEW_CONFIG')) {
		include(FLOWVIEW_CONFIG);
	} else {
		raise_message('flowview_info', __('Please rename either your config.php.dist or config_local.php.dist files in the flowview directory, and change setup your database before installing.', 'flowview'), MESSAGE_LEVEL_ERROR);
		header('Location:' . $config['url_path'] . 'plugins.php?header=false');
		exit;
	}

	flowview_setup_table();
}

function plugin_flowview_uninstall() {
	// Do any extra Uninstall stuff here
	flowview_drop_table(
		'plugin_flowview_devices',
		'plugin_flowview_dnscache',
		'plugin_flowview_ports',
		'plugin_flowview_queries'
	);
}

function plugin_flowview_check_config() {
	// Here we will check to ensure everything is configured
	if (!file_exists(dirname(__FILE__) . '/config_local.php') && !file_exists(dirname(__FILE__) . '/config.php')) {
		raise_message('flowview_info', __('Please rename either your config.php.dist or config_local.php.dist files in the flowview directory, and change setup your database before installing.', 'flowview'), MESSAGE_LEVEL_ERROR);

		return false;
	}

	plugin_flowview_check_upgrade();

	return true;
}

function plugin_flowview_upgrade() {
	// Here we will upgrade to the newest version
	plugin_flowview_check_upgrade();
	return false;
}

function plugin_flowview_check_upgrade($force = false) {
	global $config;

	$files = array('plugins.php', 'flowview.php', 'index.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	include_once($config['base_path'] . '/lib/poller.php');

	flowview_connect();

	$info    = plugin_flowview_version();
	$current = $info['version'];

	$old = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="flowview"');

	if ($current != $old || $force) {
		$php_binary = read_config_option('path_php_binary');

		exec_background($php_binary . ' ' . $config['base_path'] . '/plugins/flowview/flowview_upgrade.php');

		db_execute_prepared("UPDATE plugin_config SET
			version = ?, name = ?, author = ?, webpage = ?
			WHERE directory = ?",
			array(
				$info['version'],
				$info['longname'],
				$info['author'],
				$info['homepage'],
				$info['name']
			)
		);

		raise_message('flowview_upgrade', __('Please be advised the Flowview plugins Tables are being upgraded in the background.  This may take some time. Check the Cacti log for more information'), MESSAGE_LEVEL_INFO);
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
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_dnscache.php']  = __('DNS Cache', 'flowview');
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
		'title' => __('Dashboard', 'flowview'),
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
		'flowview_dns_method' => array(
			'friendly_name' => __('Hostname Resolution', 'flowview'),
			'description' => __('The method by which you wish to resolve hostnames.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				0 => __('Use Local Server', 'flowview'),
				1 => __('Use DNS Server Below', 'flowview'),
				2 => __('Don\'t Resolve DNS', 'flowview')
			),
			'default' => 0
		),
		'flowview_use_arin' => array(
			'friendly_name' => __('Use Arin to Find Unregistered Domains', 'syslog'),
			'description' => __('Many Big Tech data collection services like to mask their ownership of domains to obfuscate the fact that they are collecting your personal information.  If you are concerned with this and your Cacti install has access to the Internet, you can use Arin to remove the mask from those Big Tech companies.', 'flowview'),
			'method' => 'checkbox',
			'default' => 'on'
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

	if (isset($settings['misc'])) {
		$settings['misc'] = array_merge($settings['misc'], $temp);
	} else {
		$settings['misc'] = $temp;
	}
}

function flowview_poller_bottom() {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	flowview_connect();

	$time = time() - 86400;

	flowview_db_execute("DELETE FROM plugin_flowview_dnscache
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

function flowview_determine_config() {
	global $config, $flowview_use_cacti_db;

	// Setup the flowview database settings path
	if (!defined('FLOWVIEW_CONFIG')) {
		if (file_exists(dirname(__FILE__) . '/config_local.php')) {
			define('FLOWVIEW_CONFIG', dirname(__FILE__) . '/config_local.php');
			$config['flowview_remote_db'] = true;
		} elseif (file_exists(dirname(__FILE__) . '/config.php')) {
			define('FLOWVIEW_CONFIG', dirname(__FILE__) . '/config.php');
			$config['flowview_remote_db'] = false;
		}
	}
}

function flowview_connect() {
	global $config, $flowview_cnn, $flowviewdb_default, $local_db_cnn_id, $remote_db_cnn_id;

	flowview_determine_config();

	// Handle remote flowview processing
	include(FLOWVIEW_CONFIG);

	include_once(dirname(__FILE__) . '/functions.php');
	include_once(dirname(__FILE__) . '/database.php');

	/**
	 * Boolean that denotes connecting to a database other
	 * than the Cacti database.
	 */
	$connect_remote = false;

	/* Connect to the Flowview Database */
	if ($config['poller_id'] == 1) {
		if ($flowview_use_cacti_db === true) {
			$flowview_cnn = $local_db_cnn_id;
		} else {
			$connect_remote = true;
		}
	} elseif ($flowview_use_cacti_db === true) {
		$flowview_cnn = $remote_db_cnn_id;
	} else {
		$connect_remote = true;
	}

	if ($connect_remote && !is_object($flowview_cnn)) {
		if (!isset($flowviewdb_port)) {
			$flowviewdb_port = '3306';
		}

		if (!isset($flowviewdb_retries)) {
			$flowviewdb_retries = '5';
		}

		if (!isset($flowviewdb_ssl)) {
		    $flowviewdb_ssl = false;
		}

		if (!isset($flowviewdb_ssl_key)) {
		    $flowviewdb_ssl_key = '';
		}

		if (!isset($flowviewdb_ssl_cert)) {
		    $flowviewdb_ssl_cert = '';
		}

		if (!isset($flowviewdb_ssl_ca)) {
		    $flowviewdb_ssl_ca = '';
		}

		$flowview_cnn = flowview_db_connect_real(
			$flowviewdb_hostname,
			$flowviewdb_username,
			$flowviewdb_password,
			$flowviewdb_default,
			$flowviewdb_type,
			$flowviewdb_port,
			$flowviewdb_retries,
			$flowviewdb_ssl,
			$flowviewdb_ssl_key,
			$flowviewdb_ssl_cert,
			$flowviewdb_ssl_ca
		);

		if ($flowview_cnn == false) {
			cacti_log("FATAL Can not connect to the flowview database", false, 'FLOWVIEW');
			exit;
		}
	}

	return $flowview_cnn !== false;
}

function flowview_setup_table() {
	global $config, $settings, $flowviewdb_default;

	flowview_connect();

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_dnscache` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		ip varchar(45) NOT NULL default '',
		host varchar(255) NOT NULL default '',
		source varchar(40) NOT NULL default '',
		time bigint(20) unsigned NOT NULL default '0',
		PRIMARY KEY (id),
		UNIQUE KEY ip (ip))
		ENGINE=MEMORY,
		COMMENT='Plugin Flowview - DNS Cache to help speed things up'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowview_default . "`.`plugin_flowview_arin_information` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`cidr` varchar(20) NOT NULL DEFAULT '',
		`net_range` varchar(64) NOT NULL DEFAULT '',
		`name` varchar(64) NOT NULL DEFAULT '',
		`parent` varchar(64) NOT NULL DEFAULT '',
		`net_type` varchar(64) NOT NULL DEFAULT '',
		`origin_as` varchar(64) NOT NULL DEFAULT '',
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

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_devices` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(64) NOT NULL,
		cmethod int(11) unsigned NOT NULL default '0',
		allowfrom varchar(32) NOT NULL default '0',
		port int(11) unsigned NOT NULL,
		PRIMARY KEY (id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Devices to collect flows from'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_device_streams` (
		device_id int(11) unsigned NOT NULL default '0',
		ext_addr varchar(32) NOT NULL default '',
		name varchar(64) NOT NULL default '',
		version varchar(5) NOT NULL default '',
		last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (device_id, ext_addr))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Streams coming into each of the listeners'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_device_templates` (
		device_id int(11) unsigned NOT NULL default '0',
		ext_addr varchar(32) NOT NULL default '',
		template_id int(11) unsigned NOT NULL default '0',
		column_spec blob default '',
		last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (device_id, ext_addr, template_id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Stream Templates coming into each of the listeners'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_queries` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		device_id int(11) unsigned NOT NULL,
		timespan int(11) unsigned NOT NULL DEFAULT '0',
		startdate varchar(32) NOT NULL,
		enddate varchar(32) NOT NULL,
		tosfields varchar(32) NOT NULL,
		tcpflags varchar(32) NOT NULL,
		protocols varchar(32) DEFAULT NULL,
		sourceip varchar(255) NOT NULL,
		sourceport varchar(255) NOT NULL,
		sourceinterface varchar(64) NOT NULL,
		sourceas varchar(64) NOT NULL,
		destip varchar(255) NOT NULL,
		destport varchar(255) NOT NULL,
		destinterface varchar(64) NOT NULL,
		destas varchar(64) NOT NULL,
		statistics int(3) unsigned NOT NULL,
		printed int(3) unsigned NOT NULL,
		includeif int(2) unsigned NOT NULL,
		sortfield int(2) unsigned NOT NULL,
		cutofflines varchar(8) NOT NULL,
		cutoffoctets varchar(8) NOT NULL,
		resolve varchar(2) NOT NULL,
		graph_type varchar(10) NOT NULL default 'bar',
		graph_height int unsigned NOT NULL default '400',
		panel_table char(2) NOT NULL default 'on',
		panel_bytes char(2) NOT NULL default 'on',
		panel_packets char(2) NOT NULL default 'on',
		panel_flows char(2) NOT NULL default 'on',
		PRIMARY KEY (`id`))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Saved Flow Queries'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_schedules` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(128) NOT NULL default '',
		enabled varchar(3) NOT NULL default 'on',
		sendinterval bigint(20) unsigned NOT NULL,
		lastsent bigint(20) unsigned NOT NULL,
		start datetime NOT NULL,
		email text NOT NULL,
		format_file varchar(128) default '',
		query_id int(11) unsigned NOT NULL,
		PRIMARY KEY (`id`),
		INDEX query_id (query_id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - Scheduling for running and emails of saved queries'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `" . $flowviewdb_default . "`.`plugin_flowview_ports` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		service varchar(20) NOT NULL default '',
		port int(11) unsigned NOT NULL,
		proto char(4) NOT NULL,
		description varchar(255) NOT NULL default '',
		PRIMARY KEY (`id`))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - Database of well known Ports'");

	$inserts = file($config['base_path'] . '/plugins/flowview/plugin_flowview_ports.sql');

	if (cacti_sizeof($inserts)) {
		flowview_db_execute('TRUNCATE plugin_flowview_ports');
		foreach($inserts as $i) {
			flowview_db_execute($i);
		}
	}
}

function flowview_drop_table($tables) {
	global $config, $flowviewdb_default;

	flowview_connect();

	if (cacti_sizeof($tables)) {
		foreach($tables as $table) {
			flowview_db_execute("DROP TABLE IF EXISTS `" . $flowviewdb_default . "`.$table");
		}
	}
}
