<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

function plugin_init_flowview() {
	global $plugin_hooks;
	$plugin_hooks['config_arrays']['flowview'] = 'flowview_config_arrays';
	$plugin_hooks['draw_navigation_text']['flowview'] = 'flowview_draw_navigation_text';
	$plugin_hooks['config_settings']['flowview'] = 'flowview_config_settings';
	$plugin_hooks['poller_bottom']['flowview'] = 'flowview_poller_bottom';
}

function flowview_version () {
	return array( 'name' 	=> 'flowview',
			'version' 	=> '0.5',
			'longname'	=> 'FlowView',
			'author'	=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'	=> 'jimmy@sqmail.org',
			'url'		=> 'http://cactiusers.org/cacti/versions.php'
			);
}

function flowview_config_arrays () {
	global $user_auth_realms, $user_auth_realm_filenames, $menu;

	$user_auth_realms[68]='Flow Viewer';
	$user_auth_realm_filenames['flowview.php'] = 68;
	$user_auth_realm_filenames['flowview_devices.php'] = 68;

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
}

function flowview_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	$sql = "show tables from `" . $database_default . "`";

	$result = db_fetch_assoc($sql) or die (mysql_error());

	$tables = array();
	$sql = array();

	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}

	if (!in_array('plugin_flowview_dnscache', $tables)) {
		$sql[] = "CREATE TABLE plugin_flowview_dnscache (
				ip varchar(32) NOT NULL default '',
				host varchar(255) NOT NULL default '',
				`time` int(20) NOT NULL default '0',
				KEY ip (ip)
				) TYPE=HEAP;";
		$sql[] = "REPLACE INTO user_auth_realm VALUES (68, 1);";
	}

	if (!in_array('plugin_flowview_devices', $tables)) {
		$sql[] = "CREATE TABLE plugin_flowview_devices (
				  id int(12) NOT NULL auto_increment,
				  name varchar(64) NOT NULL,
				  folder varchar(64) NOT NULL,
				  allowfrom varchar(32) NOT NULL default '0',
				  port int(12) NOT NULL,
				  nesting varchar(4) NOT NULL default '-1',
				  version varchar(12) NOT NULL default '5',
				  rotation int(12) NOT NULL default '1439',
				  expire int(3) NOT NULL default '7',
				  compression int(1) NOT NULL default '0',
				  PRIMARY KEY  (id),
				  KEY folder (folder),
				) TYPE=MyISAM;";
		$sql[] = "INSERT INTO plugin_flowview_devices (name, folder, port) VALUES ('Default', 'Router', 2055)";
	}

	if (!in_array('plugin_flowview_queries', $tables)) {
		$sql[] = "CREATE TABLE `plugin_flowview_queries` (
				  `id` int(12) NOT NULL auto_increment,
				  `name` varchar(255) NOT NULL,
				  `device` varchar(32) NOT NULL,
				  `startdate` varchar(32) NOT NULL,
				  `starttime` varchar(32) NOT NULL,
				  `enddate` varchar(32) NOT NULL,
				  `endtime` varchar(32) NOT NULL,
				  `tosfields` varchar(32) NOT NULL,
				  `tcpflags` varchar(32) NOT NULL,
				  `protocols` varchar(8) NOT NULL,
				  `sourceip` varchar(255) NOT NULL,
				  `sourceport` varchar(255) NOT NULL,
				  `sourceinterface` varchar(64) NOT NULL,
				  `sourceas` varchar(64) NOT NULL,
				  `destip` varchar(255) NOT NULL,
				  `destport` varchar(255) NOT NULL,
				  `destinterface` varchar(64) NOT NULL,
				  `destas` varchar(64) NOT NULL,
				  `statistics` int(3) NOT NULL,
				  `printed` int(3) NOT NULL,
				  `includeif` int(2) NOT NULL,
				  `sortfield` int(2) NOT NULL,
				  `cutofflines` int(4) NOT NULL,
				  `cutoffoctets` varchar(8) NOT NULL,
				  `resolve` varchar(2) NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `name` (`name`)
				) TYPE=MyISAM;";
	}


	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = mysql_query($sql[$a]);
		}
	}
}



