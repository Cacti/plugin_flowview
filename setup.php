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
	// Setup core hooks for just about every plugin
	api_plugin_register_hook('flowview', 'config_arrays',          'flowview_config_arrays',          'setup.php');
	api_plugin_register_hook('flowview', 'draw_navigation_text',   'flowview_draw_navigation_text',   'setup.php');
	api_plugin_register_hook('flowview', 'config_settings',        'flowview_config_settings',        'setup.php');
	api_plugin_register_hook('flowview', 'poller_bottom',          'flowview_poller_bottom',          'setup.php');
	api_plugin_register_hook('flowview', 'top_header_tabs',        'flowview_show_tab',               'setup.php');
	api_plugin_register_hook('flowview', 'top_graph_header_tabs',  'flowview_show_tab',               'setup.php');

	// Allow the injection of CSS and other components
	api_plugin_register_hook('flowview', 'page_head',              'flowview_page_head',              'setup.php');

	// Allow the flow-caputre service to be restart after a key change in settings
	api_plugin_register_hook('flowview', 'global_settings_update', 'flowview_global_settings_update', 'setup.php');

	// Setup buttons on Graph Pages
	api_plugin_register_hook('flowview', 'graph_buttons',            'flowview_graph_button', 'setup.php');
	api_plugin_register_hook('flowview', 'graph_buttons_thumbnails', 'flowview_graph_button', 'setup.php');

	// Setup permissions to Flowview Components
	api_plugin_register_realm('flowview', 'flowview.php', __('NetFlow User', 'flowview'), 1);
	api_plugin_register_realm('flowview', 'flowview_devices.php,flowview_schedules.php,flowview_filters.php,flowview_databases.php', __('NetFlow Admin', 'flowview'), 1);

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
	flowview_connect();

	$tables = array_rekey(
		flowview_db_fetch_assoc('SELECT TABLE_NAME
			FROM information_schema.TABLES
			WHERE TABLE_NAME LIKE "plugin_flowview%"
			OR TABLE_NAME LIKE "parallel_database%"'),
		'TABLE_NAME', 'TABLE_NAME'
	);

	flowview_drop_table($tables);
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

		exec_background($php_binary, $config['base_path'] . '/plugins/flowview/flowview_upgrade.php');

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
	global $menu, $menu_glyphs, $messages, $flowview_sighup_settings;

	$messages['flow_deleted'] = array('message' => __('The Filter has been Deleted', 'flowview'), 'type' => 'info');
	$messages['flow_updated'] = array('message' => __('The Filter has been Updated', 'flowview'), 'type' => 'info');

	$menu2 = array ();
	foreach ($menu as $temp => $temp2 ) {
		$menu2[$temp] = $temp2;
		if ($temp == __('Import/Export')) {
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_devices.php']   = __('Listeners', 'flowview');
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_filters.php']   = __('Filters', 'flowview');
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_schedules.php'] = __('Schedules', 'flowview');
			$menu2[__('FlowView', 'flowview')]['plugins/flowview/flowview_databases.php'] = __('Databases', 'flowview');
		}
	}
	$menu = $menu2;

	$menu_glyphs[__('FlowView', 'flowview')] = 'fas fa-crosshairs';

	if (function_exists('auth_augment_roles')) {
		auth_augment_roles(__('Normal User'), array('flowview.php'));
		auth_augment_roles(__('System Administration'), array('flowview_devices.php','flowview_schedules.php','flowview_filters.php'));
	}

	$flowview_sighup_settings = array(
		'flowview_partition',
		'settings_from_email',
		'settings_from_name',
		'flowview_use_arin',
		'flowview_dns_method',
		'settings_dns_primary',
		'settings_dns_secondary',
		'settings_dns_timeout',
		'flowview_local_domain',
		'flowview_local_iprange'
	);

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

function flowview_global_settings_update() {
	global $config, $flowview_sighup_settings;

	$hup_process   = false;

	foreach($flowview_sighup_settings as $setting) {
		$$setting = read_config_option($setting, true);

		if ($$setting != $_SESSION['sess_flowview_settings'][$setting]) {
			$hup_process = true;
		}
	}

	if ($hup_process) {
		$pid = db_fetch_cell('SELECT pid FROM processes WHERE tasktype="flowview" AND taskname="master"');

		if ($pid > 0) {
			if (!defined('SIGHUP')) {
				define('SIGHUP', 1);
			}

			posix_kill($pid, SIGHUP);
		}
	}
}

function flowview_config_settings() {
	global $config, $settings, $tabs, $flowview_sighup_settings;

	include_once($config['base_path'] . '/lib/reports.php');

	flowview_connect();

	if (cacti_sizeof($flowview_sighup_settings)) {
		foreach($flowview_sighup_settings as $setting) {
			$$setting = read_config_option($setting, true);

			$_SESSION['sess_flowview_settings'][$setting] = $$setting;
		}
	}

	$formats = reports_get_format_files();

	if (!isset($settings['poller']['reports_concurrent'])) {
		$nsettings = array();

		foreach($settings['poller'] as $setting => $data) {
			if ($setting != 'reports_timeout') {
				$nsettings[$setting] = $data;
			} else {
				$processes = array();

				for($i = 1; $i <= 10; $i++) {
					if ($i == 1) {
						$processes[$i] = __('%d Process', $i);
					} else {
						$processes[$i] = __('%d Processes', $i);
					}
				}

				$nsettings[$setting] = $data;
				$nsettings['reports_concurrent'] = array(
					'friendly_name' => __('Report Concurrent Processes (FlowView Only)', 'flowview'),
					'description'   => __('Select the maximum concurrent reports processes that can be running at any one time.', 'flowview'),
					'default'       => 1,
					'method'        => 'drop_array',
					'array'         => $processes
				);
			}
		}

		$settings['poller'] = $nsettings;
	}

	$engines = array(
		'MyISAM' => __('MyISAM (Fast, Non-Crash Safe)', 'flowview'),
		'Aria'   => __('Aria (Fast, Crash Safe)', 'flowview'),
		'InnoDB' => __('InnoDB (Slow, High Concurrency)', 'flowview'),
	);

	$supported_engines = array_rekey(
		flowview_db_fetch_assoc("SELECT ENGINE
			FROM information_schema.ENGINES
			WHERE ENGINE IN ('MyISAM', 'Aria', 'InnoDB')
			AND SUPPORT IN ('YES','DEFAULT')"),
		'ENGINE', 'ENGINE'
	);

	if (!isset($supported_engines['Aria'])) {
		unset($engines['Aria']);
		$default_engine = 'MyISAM';
	} else {
		unset($engines['MyISAM']);
		$default_engine = 'Aria';
	}

	if (flowview_db_table_exists('plugin_flowview_queries')) {
		$queries = array_rekey(
			flowview_db_fetch_assoc('SELECT id, name
				FROM plugin_flowview_queries
				ORDER BY name'),
			'id', 'name'
		);
	} else {
		$queries = array();
	}

	$temp = array(
		'flowview_header' => array(
			'friendly_name' => __('Name Resolution', 'flowview'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'flowview_dns_method' => array(
			'friendly_name' => __('Hostname Resolution', 'flowview'),
			'description' => __('The method by which you wish to resolve hostnames.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				0 => __('Use Local Server', 'flowview'),
				1 => __('Use DNS Servers under Mail/Reporting/DNS', 'flowview'),
				2 => __('Don\'t Resolve DNS', 'flowview')
			),
			'default' => 0
		),
		'flowview_local_domain' => array(
			'friendly_name' => __('Local Domain Name', 'monitor'),
			'method' => 'textbox',
			'default' => 'mydomain.net',
			'description' => __('For IPv4 addresses on the local network that do not resolve locally, append this suffix to the resultant ip address.', 'monitor'),
			'max_length' => 30,
			'size' => 30
		),
		'flowview_local_iprange' => array(
			'friendly_name' => __('Local IP Range', 'monitor'),
			'method' => 'textbox',
			'default' => '192.168.1.0/24',
			'description' => __('Provide the IPv4 ip address range for your local network for hosts that may not be registered in DNS.  These hosts will be mapped to the Local Domain Name above.  This more for home users.  You can use either CIDR or non-CIDR formats.  For examle: 192.168.11.0 or 192.168.11.0/24', 'monitor'),
			'placeholder' => __('Use CIDR or Non-CIDR', 'flowview'),
			'max_length' => 30,
			'size' => 30
		),
		'flowview_use_arin' => array(
			'friendly_name' => __('Use Arin to find Domains and AS Numbers', 'syslog'),
			'description' => __('Many Big Tech data collection services like to mask their ownership of domains to obfuscate the fact that they are collecting your personal information.  If you are concerned with this and your Cacti install has access to the Internet, you can use Arin to remove the mask from those Big Tech companies.', 'flowview'),
			'method' => 'checkbox',
			'default' => 'on'
		),
		'flowview_whois_provider' => array(
			'friendly_name' => __('Whois Provider Host', 'monitor'),
			'method' => 'textbox',
			'default' => 'whois.radb.net',
			'description' => __('Please provide the hostname for resolving whois calls.  If not null, you must have the whois binary in your system path.', 'monitor'),
			'placeholder' => __('whois.radb.net', 'flowview'),
			'max_length' => 30,
			'size' => 30
		),
		'flowview_path_whois' => array(
			'friendly_name' => __('Whois Binary Path', 'monitor'),
			'method' => 'filepath',
			'default' => '/usr/bin/whois',
			'description' => __('Please provide the pathname for the \'whois\' binary.  The \'whois\' binary will be used to find AS information supplementing Arin.', 'monitor'),
			'placeholder' => __('Enter binary path', 'flowview'),
			'max_length' => 30,
			'size' => 30
		),
		'flowview_dd_header' => array(
			'friendly_name' => __('Graph Drilldown Settings', 'flowview'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'flowview_default_filter' => array(
			'friendly_name' => __('Default Search Filter for Graph Drilldowns', 'monitor'),
			'method' => 'drop_array',
			'default' => '',
			'description' => __('Choose an existing Flowview Search Filter to use for Graph Drilldowns.', 'monitor'),
			'array' => $queries
		),
		'flowview_data_header' => array(
			'friendly_name' => __('Data Retention and Report Generation', 'flowview'),
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
			'default' => 1
		),
		'flowview_engine' => array(
			'friendly_name' => __('Storage Engine for Raw Tables', 'flowview'),
			'description' => __('The Aria Engine is perfect for all but the Live raw table.  The Live raw table will always be InnoDB.  Take your pick.', 'flowview'),
			'method' => 'drop_array',
			'array' => $engines,
			'default' => $default_engine
		),
		'flowview_parallel_header' => array(
			'friendly_name' => __('Parallel Queries', 'flowview'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'flowview_parallel_threads' => array(
			'friendly_name' => __('Max Concurrent Threads', 'flowview'),
			'description' => __('The maximum number of threads that will be dispatched to run the FlowView queries.  Note that you can have at most 1 thread per database partition, and you should be careful not to overload your database server with having too many concurrent threads running.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				1  => __('Disabled', 'flowview'),
				2  => __('%d Threads', 2, 'flowview'),
				3  => __('%d Threads', 3, 'flowview'),
				4  => __('%d Threads', 4, 'flowview'),
				5  => __('%d Threads', 5, 'flowview'),
				6  => __('%d Threads', 6, 'flowview'),
				7  => __('%d Threads', 7, 'flowview'),
				8  => __('%d Threads', 8, 'flowview'),
				9  => __('%d Threads', 9, 'flowview'),
				10 => __('%d Threads', 10, 'flowview'),
				11 => __('%d Threads', 11, 'flowview'),
				12 => __('%d Threads', 12, 'flowview'),
				13 => __('%d Threads', 13, 'flowview'),
				14 => __('%d Threads', 14, 'flowview'),
				15 => __('%d Threads', 15, 'flowview'),
				16 => __('%d Threads', 16, 'flowview'),
				17 => __('%d Threads', 17, 'flowview'),
				18 => __('%d Threads', 18, 'flowview'),
				19 => __('%d Threads', 19, 'flowview'),
				20 => __('%d Threads', 20, 'flowview'),
			),
			'default' => 4
		),
		'flowview_parallel_runlimit' => array(
			'friendly_name' => __('Maximum Run Time', 'flowview'),
			'description' => __('If the Parallel Query does not finish in this time, exit.', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				60   => __('%d Minute', 1, 'flowview'),
				120  => __('%d Minutes', 2, 'flowview'),
				180  => __('%d Minutes', 3, 'flowview'),
				240  => __('%d Minutes', 4, 'flowview'),
				300  => __('%d Minutes', 5, 'flowview'),
				600  => __('%d Minutes', 10, 'flowview'),
				900 => __('%d Minutes', 15, 'flowview'),
			),
			'default' => 300
		),
		'flowview_parallel_time_to_live' => array(
			'friendly_name' => __('Cached Data Time to Live', 'flowview'),
			'description' => __('How long should FlowView hold onto Cached Query results before purging them?', 'flowview'),
			'method' => 'drop_array',
			'array' => array(
				21600   => __('%d Hours', 6, 'flowview'),
				43200   => __('%d Hours', 12, 'flowview'),
				86400   => __('%d Day', 1, 'flowview'),
				172800  => __('%d Days', 2, 'flowview'),
			),
			'default' => 21600
		),
		'flowview_maxscale_header' => array(
			'friendly_name' => __('MaxScale Sharding', 'flowview'),
			'description' => __('This is only required if your Cacti System\'s Flowview Database connection is not already using a MaxScale enabled Read-Write Split port.', 'flowview'),
			'method' => 'spacer',
			'collapsible' => 'true'
		),
		'flowview_use_maxscale' => array(
			'friendly_name' => __('Leverage MaxScale to Distribute Query Shards', 'syslog'),
			'description' => __('If you have multiple service acting as slaves for MaxScale, you can increase the speed of querying by distributing the parallel queries to multiple MariaDB backend servers.', 'flowview'),
			'method' => 'checkbox',
			'default' => ''
		),
		'flowview_maxscale_port' => array(
			'friendly_name' => __('MaxScale Read-Write Split Port', 'monitor'),
			'method' => 'textbox',
			'default' => '3307',
			'description' => __('This should be the port of the Read-Write-Split-Service (readwrite) service and router.', 'monitor'),
			'max_length' => 30,
			'size' => 30
		),
	);

	$tabs['flowview'] = __('Flowview', 'flowview');

	if (isset($settings['flowview'])) {
		$settings['flowview'] = array_merge($settings['flowview'], $temp);
	} else {
		$settings['flowview'] = $temp;
	}
}

function flowview_poller_bottom() {
	global $config;

	include_once($config['base_path'] . '/lib/poller.php');

	flowview_connect();

	$retention_days = read_config_option('flowview_retention');

	if (empty($retention_days)) {
		$retention_days = 30;
		set_config_option('flowview_retention', $retention_days);
	}

	$time = time() - ($retention_days * 86400);

	flowview_db_execute("DELETE FROM plugin_flowview_dnscache
		WHERE time > 0
		AND time < $time");

	$t = time();

	$php = trim(read_config_option('path_php_binary'));

	if ($php == '') {
		$php = 'php';
	}

	exec_background($php, $config['base_path'] . '/plugins/flowview/flowview_process.php');
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

function flowview_connect($maxscale = false) {
	global $config, $flowview_cnn, $flowviewdb_default, $local_db_cnn_id, $remote_db_cnn_id, $database_hostname;

	$cnn_id = false;

	flowview_determine_config();

	// Handle remote flowview processing
	include(FLOWVIEW_CONFIG);

	include_once(dirname(__FILE__) . '/functions.php');
	include_once(dirname(__FILE__) . '/database.php');

	/**
	 * If connecting to MaxScale, set the port properly
	 */
	if ($maxscale) {
		$maxscale_port = read_config_option('flowview_maxscale_port');

		if ($maxscale_port > 0) {
			$flowviewdb_port = $maxscale_port;
		}
	}

	/**
	 * Boolean that denotes connecting to a database other
	 * than the Cacti database.
	 */
	$connect_remote = false;

	/* Connect to the Flowview Database */
	if (!$maxscale) {
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
	} else {
		if ($flowview_use_cacti_db === true) {
			/**
			 * We assume, maybe falsely that MaxScale is watching
			 * on the hostname as opposed to 'localhost'.
			 * so we make this change here.
			 *
			 */
			if ($database_hostname == 'localhost' || $database_hostname == '127.0.0.1') {
				$flowviewdb_hostname = gethostbyname(gethostname());
			}
		}

		$connect_remote = true;
	}

	if ($maxscale || ($connect_remote && !is_object($flowview_cnn))) {
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

		$cnn_id = $flowview_cnn;

		if ($flowview_cnn === false) {
			cacti_log("FATAL Can not connect to the flowview database", false, 'FLOWVIEW');
			exit;
		}
	} else {
		$cnn_id = $flowview_cnn;
	}

	return $cnn_id;
}

function flowview_setup_table() {
	global $config, $settings, $flowviewdb_default;

	flowview_connect();

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_dnscache` (
		`id` int(11) unsigned NOT NULL AUTO_INCREMENT,
		`ip` varchar(45) NOT NULL DEFAULT '',
		`host` varchar(255) NOT NULL DEFAULT '',
		`source` varchar(40) NOT NULL DEFAULT '',
		`arin_verified` tinyint(3) unsigned NOT NULL DEFAULT 0,
		`arin_id` int(10) unsigned NOT NULL DEFAULT 0,
		`time` bigint(20) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY (`id`),
		KEY `arin_id` (`arin_id`),
		UNIQUE KEY `ip` (`ip`))
		ENGINE=InnoDB,
		COMMENT='Plugin Flowview - DNS Cache to help speed things up'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_arin_information` (
		`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
		`cidr` varchar(20) NOT NULL DEFAULT '',
		`net_range` varchar(64) NOT NULL DEFAULT '',
		`name` varchar(64) NOT NULL DEFAULT '',
		`parent` varchar(64) NOT NULL DEFAULT '',
		`net_type` varchar(64) NOT NULL DEFAULT '',
		`origin` varchar(20) NOT NULL DEFAULT '',
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

	include_once($config['base_path'] . '/plugins/flowview/irr_tables.php');

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_devices` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(64) NOT NULL,
		enabled char(2) NOT NULL default 'on',
		cmethod int(11) unsigned NOT NULL default '0',
		allowfrom varchar(32) NOT NULL default '0',
		port int(11) unsigned NOT NULL,
		protocol char(3) NOT NULL default 'UDP',
		last_updated timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
		PRIMARY KEY (id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Devices to collect flows from'");

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
		template_id int(11) NOT NULL default '0',
		supported tinyint unsigned NOT NULL default '0',
		column_spec blob default '',
		last_updated timestamp NOT NULL default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (device_id, ex_addr, template_id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - List of Stream Templates coming into each of the listeners'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_queries` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		device_id int(11) unsigned NOT NULL,
		template_id int(11) NOT NULL DEFAULT '0',
		ex_addr varchar(46) NOT NULL DEFAULT '',
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
		sortfield varchar(15) NOT NULL DEFAULT 'bytes',
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

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_schedules` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		title varchar(128) NOT NULL default '',
		enabled varchar(3) NOT NULL default 'on',
		sendinterval bigint(20) unsigned NOT NULL,
		timeout int(10) unsigned not null default 60,
		lastsent bigint(20) unsigned NOT NULL default 0,
		start timestamp NOT NULL default '0000-00-00',
		notification_list int(10) unsigned not null default 0,
		email text NOT NULL,
		format_file varchar(128) default '',
		query_id int(11) unsigned NOT NULL,
		PRIMARY KEY (`id`),
		INDEX query_id (query_id))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - Scheduling for running and emails of saved queries'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_ports` (
		id int(11) unsigned NOT NULL AUTO_INCREMENT,
		service varchar(20) NOT NULL default '',
		port int(11) unsigned NOT NULL,
		proto char(4) NOT NULL,
		description varchar(255) NOT NULL default '',
		PRIMARY KEY (`id`))
		ENGINE=InnoDB,
		ROW_FORMAT=DYNAMIC,
		COMMENT='Plugin Flowview - Database of well known Ports'");

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
		`map_table` varchar(64)  NOT NULL DEFAULT '',
		`map_partition` varchar(20) NOT NULL DEFAULT '',
		`map_query` blob NOT NULL DEFAULT '',
		`map_params` blob NOT NULL DEFAULT '',
		`created` timestamp NULL DEFAULT current_timestamp(),
		`completed` timestamp NULL DEFAULT NULL,
		PRIMARY KEY (`query_id`,`shard_id`))
		ENGINE=InnoDB
		ROW_FORMAT=DYNAMIC
		COMMENT='Holds Parallel Query Shard Requests'");

	flowview_db_execute("CREATE TABLE IF NOT EXISTS `parallel_database_query_shard_cache` (
		`md5sum` varchar(32) NOT NULL DEFAULT '',
		`map_table` varchar(64)  NOT NULL DEFAULT '',
		`map_partition` varchar(20) NOT NULL DEFAULT '',
		`min_date` timestamp NOT NULL default '0000-00-00',
		`max_date` timestamp NOT NULL default '0000-00-00',
		`results` longblob NOT NULL DEFAULT '',
		`date_created` timestamp DEFAULT current_timestamp(),
		PRIMARY KEY (`md5sum`,`map_table`,`map_partition`))
		ENGINE=InnoDB
		ROW_FORMAT=DYNAMIC
		COMMENT='Holds Parallel Query Shard Results for Partition Full Scans based upon the md5sum of the Map Query'");

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
			flowview_db_execute("DROP TABLE IF EXISTS $table");
		}
	}
}

function flowview_graph_button($data) {
	global $config, $timespan, $graph_timeshifts;

	static $flow_hosts = array();
	static $flow_hosts_map = array();
	static $flowview_default_filter;

	$flowview_default_filter = read_config_option('flowview_default_filter');

	flowview_connect();

	if (get_current_page() != 'graph_view.php') {
		return false;
	}

	/* set the default filter if the admin had not */
	if (empty($flowview_default_filter)) {
		/* no default filter defined */
		$flowview_default_filter = flowview_db_fetch_cell('SELECT id FROM plugin_flowview_queries LIMIT 1');

		if ($flowview_default_filter > 0) {
			set_config_option('flowview_default_filter', $flowview_default_filter);
		} else {
			return false;
		}
	}

	/* from the local graph id, get the host id */
	$local_graph_id = $data[1]['local_graph_id'];

	$host_id = db_fetch_cell_prepared('SELECT host_id
		FROM graph_local
		WHERE id = ?',
		array($local_graph_id));

	/* get all the IP addresses and hostname for various streams */
	if (!cacti_sizeof($flow_hosts)) {
		$flow_hosts = flowview_db_fetch_assoc('SELECT fvs.device_id AS id, fvs.ex_addr,
			fvq.ex_addr AS qex_addr, SUBSTRING_INDEX(fvs.name, ".", 1) AS name
			FROM plugin_flowview_device_streams AS fvs
			LEFT JOIN plugin_flowview_queries AS fvq
			ON fvs.device_id = fvq.device_id
			ORDER BY qex_addr DESC');
	}

	$sql_where1   = '';
	$sql_where2   = '';
	$sql_params   = array();

	/**
	 * Find an elegant way to match the queries stream clients
	 * with the Cacti hosts by first getting the list of
	 * all the possible combinations for hostname and ip.
	 *
	 * If there are not streams working, just ignore this
	 * and return.
	 */
	if (cacti_sizeof($flow_hosts)) {
		$i = 0;
		$sql_params1 = array();
		$sql_params2 = array();

		foreach($flow_hosts as $id => $host) {
			if ($i == 0) {
				$sql_params1[] = $local_graph_id;
				$sql_where1 .= " AND ((hostname = ? OR hostname = ? OR hostname LIKE ?)";
			} else {
				$sql_where1 .= " OR (hostname = ? OR hostname = ? OR hostname LIKE ?)";
			}


			$sql_params1[] = $host['ex_addr'];
			$sql_params1[] = $host['name'];
			$sql_params1[] = $host['name'] . '.%';

			if ($i == 0) {
				$sql_params2[] = $local_graph_id;
				$sql_where2 .= " AND ((description = ? OR description = ? OR description LIKE ?)";
			} else {
				$sql_where2 .= " OR (description = ? OR description = ? OR description LIKE ?)";
			}


			$sql_params2[] = $host['ex_addr'];
			$sql_params2[] = $host['name'];
			$sql_params2[] = $host['name'] . '.%';

			$i++;
		}

		$sql_where1 .= ')';
		$sql_where2 .= ')';

		$sql_params = array_merge($sql_params1, $sql_params2);
	} else {
		return false;
	}

	/**
	 * Now we will attempt to find a query with a valid stream
	 * or ex_addr in the database and set that.  First we get
	 * a list of all host id's and either the hostname or the
	 * description as the ex_addr.
	 *
	 * We will then try to align on the first matche between
	 * the Cacti host and the ex_addr of the stream.
	 */
	$host_data = array_rekey(
		db_fetch_assoc_prepared("SELECT h.id, h.hostname AS ex_addr
			FROM graph_local AS gl
			INNER JOIN host AS h
			ON h.id = gl.host_id
			WHERE gl.id = ?
			$sql_where1
			UNION
			SELECT h.id, h.description AS ex_addr
			FROM graph_local AS gl
			INNER JOIN host AS h
			ON h.id = gl.host_id
            WHERE gl.id = ?
			$sql_where2",
			$sql_params),
		'ex_addr', 'id'
	);

	/**
	 * If the $host_data array is not empty, then we have
	 * at least one host that matches the ex_addr information
	 * from a filter.  So, let's look for it.
	 */
	if (cacti_sizeof($host_data)) {
		$query_data = false;

		/* if the setting is not already cached, search */
		if (!isset($flow_hosts_map[$host_id])) {
			foreach($host_data as $ex_addr => $hd_host_id) {
				/* trim the domains from the ex_addr */
				if (!is_ipaddress($ex_addr)) {
					$ex_addr = explode('.', $ex_addr)[0];
				}

				foreach($flow_hosts as $host) {
					if ($host['ex_addr'] == $ex_addr) {
						$flow_hosts_map[$host_id] = flowview_db_fetch_row_prepared('SELECT *
							FROM plugin_flowview_queries
							WHERE device_id = ?
							LIMIT 1',
							array($host['id']));

						$flow_hosts_map[$host_id]['ex_addr'] = $ex_addr;

						$query_data = $flow_hosts_map[$host_id];

						break;
					}
				}
			}
		} else {
			$query_data = $flow_hosts_map[$host_id];
		}

		if ($query_data === false) {
			return false;
		}

		$url = $config['url_path'] . "plugins/flowview/flowview.php?action=view&query=$flowview_default_filter&timespan=session&ex_addr={$query_data['ex_addr']}";

		/* initialize settings from the database if they are not set already */
		if (cacti_sizeof($query_data)) {
			$columns = array(
				'device_id',
				'includeif',
				'sortfield',
				'cutofflines',
				'cutoffoctets',
				'printed',
				'statistics',
				'resolve',
				'graph_type',
				'graph_height',
				'panel_table',
				'panel_bytes',
				'panel_packets',
				'panel_flows'
			);

			foreach($columns as $c) {
				if (strpos($c, 'panel') !== false) {
					$rv  = str_replace('panel_', '', $c);

					if ($query_data[$c] == 'on') {
						$url .= "&$rv=true";
					} else {
						$url .= "&$rv=false";
					}
				} elseif ($c == 'resolve') {
					if ($query_data[$c] == 'on') {
						$url .= "&domains=true";
					} else {
						$url .= "&domains=false";
					}
				} elseif ($c == 'printed' && $query_data[$c] > 0) {
					$url .= "&report=p{$query_data[$c]}";
				} elseif ($c == 'statistics' && $query_data[$c] > 0) {
					$url .= "&report=s{$query_data[$c]}";
				} else {
					$url .= "&$c={$query_data[$c]}";
				}
			}

			$url .= "&predefined_timespan=0";
		}

		//cacti_log("The URL is this:" . $url);

		if (api_user_realm_auth('flowview.php') && !empty($host_id)) {
			print '<a class="iconLink flowview" href="' .  html_escape($url) . '" title="' . __esc('View NetFlow Traffic In Range', 'flowview') . '"><i class="deviceRecovering fas fa-water"></i></a><br>';
		}
	}
}

