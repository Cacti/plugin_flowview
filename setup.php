<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
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

function plugin_flowview_install () {
	api_plugin_register_hook('flowview', 'config_arrays', 'flowview_config_arrays', 'setup.php');
	api_plugin_register_hook('flowview', 'draw_navigation_text', 'flowview_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('flowview', 'config_settings', 'flowview_config_settings', 'setup.php');
	api_plugin_register_hook('flowview', 'poller_bottom', 'flowview_poller_bottom', 'setup.php');

	api_plugin_register_realm('flowview', 'flowview.php,flowview_devices.php,flowview_schedules.php', 'Flow Viewer', 1);
}

function plugin_flowview_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_flowview_check_config () {
	// Here we will check to ensure everything is configured
	flowview_check_upgrade ();
	return true;
}

function plugin_flowview_upgrade () {
	// Here we will upgrade to the newest version
	flowview_check_upgrade ();
	return false;
}

function flowview_version () {
	return plugin_flowview_version();
}

function flowview_check_upgrade () {
	$current = plugin_flowview_version ();
	$current = $current['version'];
	$old = read_config_option('plugin_flowview_version');
	if ($current != $old)
		flowview_setup_table ();
	// Set the new version
	db_execute("REPLACE INTO settings (name, value) VALUES ('plugin_flowview_version', '$current')");
}


function plugin_flowview_version () {
	return array( 'name' 	=> 'flowview',
			'version' 	=> '0.6',
			'longname'	=> 'FlowView',
			'author'	=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'	=> 'jimmy@sqmail.org',
			'url'		=> 'http://cactiusers.org/cacti/versions.php'
			);
}

function flowview_config_arrays () {
	global $menu;

	$temp = $menu["Utilities"]['logout.php'];
	unset($menu["Utilities"]['logout.php']);
	$menu["Utilities"]['plugins/flowview/flowview.php'] = "Flow Viewer";
	$menu["Utilities"]['logout.php'] = $temp;

}
function flowview_draw_navigation_text ($nav) {
	$nav["flowview.php:"] = array("title" => "Flow Viewer", "mapping" => "index.php:", "url" => "flowview.php", "level" => "1");
	$nav["flowview.php:view"] = array("title" => "Flow Viewer", "mapping" => "flowview.php:", "url" => "flowview.php", "level" => "2");
	$nav["flowview.php:save"] = array("title" => "Flow Viewer", "mapping" => "flowview.php:", "url" => "flowview.php", "level" => "2");
	$nav["flowview.php:loadquery"] = array("title" => "Flow Viewer", "mapping" => "flowview.php:", "url" => "flowview.php", "level" => "2");
	$nav["flowview_devices.php:"] = array("title" => "Devices", "mapping" => "flowview.php:", "url" => "flowview_devices.php", "level" => "2");
	$nav["flowview_devices.php:edit"] = array("title" => "Devices", "mapping" => "flowview.php:", "url" => "flowview_devices.php", "level" => "2");
	$nav["flowview_devices.php:save"] = array("title" => "Devices", "mapping" => "flowview.php:", "url" => "flowview_devices.php", "level" => "2");
	$nav["flowview_devices.php:actions"] = array("title" => "Devices", "mapping" => "flowview.php:", "url" => "flowview_devices.php", "level" => "2");
	$nav["flowview_schedules.php:"] = array("title" => "Schedules", "mapping" => "flowview.php:", "url" => "flowview_schedules.php", "level" => "2");
	$nav["flowview_schedules.php:edit"] = array("title" => "Schedules", "mapping" => "flowview.php:", "url" => "flowview_schedules.php", "level" => "2");
	$nav["flowview_schedules.php:save"] = array("title" => "Schedules", "mapping" => "flowview.php:", "url" => "flowview_schedules.php", "level" => "2");
	$nav["flowview_schedules.php:actions"] = array("title" => "Schedules", "mapping" => "flowview.php:", "url" => "flowview_schedules.php", "level" => "2");
	return $nav;
}

function flowview_config_settings () {
	global $settings, $tabs;
	$temp = array(
		"flowview_header" => array(
		"friendly_name" => "Flow Viewer",
		"method" => "spacer",
		),
			"path_flowtools" => array(
			"friendly_name" => "Flow Tools Binary Path",
			"description" => "The path to your flow-cat, flow=filter, and flow-stat binary.",
			"method" => "dirpath",
			"max_length" => 255,
			'default' => '/usr/bin/'
		),
			"path_flowtools_workdir" => array(
			"friendly_name" => "Flow Tools Work Directory",
			"description" => "This is the path to a temporary directory to do work.",
			"method" => "dirpath",
			"max_length" => 255,
			'default' => '/tmp/'
		),
			"path_flows_dir" => array(
			"friendly_name" => "Flows Directory",
			"description" => "This is the path to base the path of your flow folder structure.",
			"method" => "dirpath",
			"max_length" => 255,
			'default' => '/var/netflow/flows/completed/'
		),
	);

	if (isset($settings["path"]))
		$settings["path"] = array_merge($settings["path"], $temp);
	else
		$settings["path"] = $temp;

	$tabs["misc"] = "Misc";
	
	$temp = array(
		"flowview_header" => array(
			"friendly_name" => "Flow View",
			"method" => "spacer",
			),
		"flowview_dns" => array(
			"friendly_name" => "DNS Server",
			"description" => "This is the DNS Server used to resolve names.",
			"method" => "textbox",
			"max_length" => 255,
			),
	);
	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"]=$temp;
}

function flowview_poller_bottom () {
	global $config;
	include_once($config["library_path"] . "/database.php");
	flowview_setup_table ();
	$time = time() - 3600;
	db_execute("delete from plugin_flowview_dnscache where time > 0 and time < $time");

	$t = time();
	$schedules = db_fetch_assoc("SELECT * FROM plugin_flowview_schedules WHERE enabled = 'on' AND ($t - sendinterval > lastsent)");
	if (!empty($schedules)) {
		$command_string = trim(read_config_option("path_php_binary"));
		if (trim($command_string) == '')
			$command_string = "php";
		$extra_args = ' -q ' . $config['base_path'] . '/plugins/flowview/flowview_process.php';
		exec_background($command_string, $extra_args);
	}
}

function flowview_setup_table () {
	$data = array();
	$data['columns'][] = array('name' => 'ip', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'host', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'time', 'type' => 'int(20)', 'NULL' => false, 'default' => '0');
	$data['keys'][] = array('name' => 'ip', 'columns' => 'ip');
	$data['type'] = 'HEAP';
	$data['comment'] = 'Plugin Flowview - DNS Cache to help speed things up';
	api_plugin_db_table_create ('flowview', 'plugin_flowview_dnscache', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'folder', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'allowfrom', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'port', 'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'nesting', 'type' => 'varchar(4)', 'NULL' => false, 'default' => '-1');
	$data['columns'][] = array('name' => 'version', 'type' => 'varchar(12)', 'NULL' => false, 'default' => '5');
	$data['columns'][] = array('name' => 'rotation', 'type' => 'int(12)', 'NULL' => false, 'default' => '1439');
	$data['columns'][] = array('name' => 'expire', 'type' => 'int(3)', 'NULL' => false, 'default' => '7');
	$data['columns'][] = array('name' => 'compression', 'type' => 'int(1)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'folder', 'columns' => 'folder');
	$data['type'] = 'HEAP';
	$data['comment'] = 'Plugin Flowview - List of Devices to collect flows from';
	api_plugin_db_table_create ('flowview', 'plugin_flowview_devices', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'device', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'startdate', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'starttime', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'enddate', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'endtime', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'tosfields', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'tcpflags', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'protocols', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'sourceip', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'sourceport', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'sourceinterface', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'sourceas', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'destip', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'destport', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'destinterface', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'destas', 'type' => 'varchar(64)', 'NULL' => false);
	$data['columns'][] = array('name' => 'statistics', 'type' => 'int(3)', 'NULL' => false);
	$data['columns'][] = array('name' => 'printed', 'type' => 'int(3)', 'NULL' => false);
	$data['columns'][] = array('name' => 'includeif', 'type' => 'int(2)', 'NULL' => false);
	$data['columns'][] = array('name' => 'sortfield', 'type' => 'int(2)', 'NULL' => false);
	$data['columns'][] = array('name' => 'cutofflines', 'type' => 'int(4)', 'NULL' => false);
	$data['columns'][] = array('name' => 'curoffoctets', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'resolve', 'type' => 'varchar(2)', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'name', 'columns' => 'name');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Plugin Flowview - List of Saved Flow Queries';
	api_plugin_db_table_create ('flowview', 'plugin_flowview_queries', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'sendinterval', 'type' => 'int(20)', 'NULL' => false);
	$data['columns'][] = array('name' => 'lastsent', 'type' => 'int(20)', 'NULL' => false);
	$data['columns'][] = array('name' => 'start', 'type' => 'datetime', 'NULL' => false);
	$data['columns'][] = array('name' => 'email', 'type' => 'text', 'NULL' => false);
	$data['columns'][] = array('name' => 'savedquery', 'type' => 'int(12)', 'NULL' => false);
	$data['primary'] = 'id';
	$data['keys'][] = array('name' => 'savedquery', 'columns' => 'savedquery');
	$data['type'] = 'HEAP';
	$data['comment'] = 'Plugin Flowview - Scheduling for running and emails of saved queries';
	api_plugin_db_table_create ('flowview', 'plugin_flowview_schedules', $data);
}



