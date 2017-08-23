<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
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
	api_plugin_register_realm('flowview', 'flowview_devices.php,flowview_schedules.php', __('Plugin -> Flow Admin', 'flowview'), 1);

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

		if ($bad_titles) {
			/* update titles for those that don't have them */
			db_execute("UPDATE plugin_flowview_schedules SET title='Ugraded Schedule' WHERE title=''");

			/* Set the new version */
			db_execute_prepared("REPLACE INTO settings (name, value) VALUES ('plugin_flowview_version', ?)", array($current));

			db_execute('ALTER TABLE plugin_flowview_devices ENGINE=InnoDB');
		}

		db_execute("UPDATE plugin_config
			SET version='$current'
			WHERE directory='flowview'");

		db_execute("UPDATE plugin_config SET
			version='" . $info['version']  . "',
			name='"    . $info['longname'] . "',
			author='"  . $info['author']   . "',
			webpage='" . $info['homepage'] . "'
			WHERE directory='" . $info['name'] . "' ");
	}
}

function plugin_flowview_version() {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/flowview/INFO', true);
	return $info['info'];
}

function flowview_config_arrays() {
	global $menu, $messages;

	$messages['flow_deleted'] = array('message' => __('The Filter has been Deleted', 'flowview'), 'type' => 'info');
	$messages['flow_updated'] = array('message' => __('The Filter has been Updated', 'flowview'), 'type' => 'info');

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
		'mapping' => 'flowview.php:',
		'url' => 'flowview_devices.php',
		'level' => '1'
	);

	$nav['flowview_devices.php:edit'] = array(
		'title' => __('(edit)', 'flowview'),
		'mapping' => 'flowview.php:,flowview_devices.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_devices.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_devices.php:actions'] = array(
		'title' => __('(actions)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview_devices.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:'] = array(
		'title' => __('Schedules', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview_schedules.php',
		'level' => '1'
	);

	$nav['flowview_schedules.php:edit'] = array(
		'title' => __('(edit)', 'flowview'),
		'mapping' => 'flowview.php:,flowview_schedules.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:save'] = array(
		'title' => __('(save)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	$nav['flowview_schedules.php:actions'] = array(
		'title' => __('(actions)', 'flowview'),
		'mapping' => 'flowview.php:',
		'url' => 'flowview_schedules.php',
		'level' => '2'
	);

	return $nav;
}

function flowview_show_tab() {
	global $config;

	if (api_user_realm_auth('flowview.php')) {
		if (substr_count($_SERVER['REQUEST_URI'], 'flowview')) {
			print '<a href="' . htmlspecialchars($config['url_path'] . 'plugins/flowview/flowview.php') . '"><img src="' . $config['url_path'] . 'plugins/flowview/images/tab_flows_down.gif" alt="' . __('FlowView', 'flowview') . '"></a>';
		}else{
			print '<a href="' . htmlspecialchars($config['url_path'] . 'plugins/flowview/flowview.php') . '"><img src="' . $config['url_path'] . 'plugins/flowview/images/tab_flows.gif" alt="' . __('FlowView', 'flowview') . '"></a>';
		}
	}
}

function flowview_page_head() {
	global $config, $colors;
	if (substr_count($_SERVER['REQUEST_URI'], 'flowview')) {
		print "\t<script type='text/javascript' src='" . $config['url_path'] . "plugins/flowview/js/swfobject.js'></script>\n";
	}
}

function flowview_config_settings() {
	global $settings, $tabs;

	$temp = array(
		'flowview_header' => array(
			'friendly_name' => __('Flow Viewer', 'flowview'),
			'method' => 'spacer',
		),
		'path_flowtools' => array(
			'friendly_name' => __('Flow Tools Binary Path', 'flowview'),
			'description' => __('The path to your flow-cat, flow-filter, and flow-stat binary.', 'flowview'),
			'method' => 'dirpath',
			'max_length' => 255,
			'default' => '/usr/bin'
		),
		'path_flowtools_workdir' => array(
			'friendly_name' => __('Flow Tools Work Directory', 'flowview'),
			'description' => __('This is the path to a temporary directory to do work.', 'flowview'),
			'method' => 'dirpath',
			'max_length' => 255,
			'default' => '/tmp'
		),
		'path_flows_dir' => array(
			'friendly_name' => __('Flows Directory', 'flowview'),
			'description' => __('This is the path to base the path of your flow folder structure.', 'flowview'),
			'method' => 'dirpath',
			'max_length' => 255,
			'default' => '/var/netflow/flows/completed'
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
		'flowview_dns' => array(
			'friendly_name' => __('Alternate DNS Server', 'flowview'),
			'description' => __('This is the DNS Server used to resolve names.', 'flowview'),
			'method' => 'textbox',
			'max_length' => 255,
		),
		'flowview_strip_dns' => array(
			'friendly_name' => __('Strip Domain Names', 'flowview'),
			'description' => __('A comma delimited list of domains names to strip from the domain.', 'flowview'),
			'method' => 'textbox',
			'max_length' => 255,
			'size' => 80
		),
	);

	$tabs['misc'] = __('Misc', 'flowview');

	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function flowview_poller_bottom() {
	global $config;
	include_once($config['library_path'] . '/database.php');
	$time = time() - 3600;
	db_execute("DELETE FROM plugin_flowview_dnscache WHERE time > 0 AND time < $time");

	$t = time();
	$schedules = db_fetch_assoc("SELECT * FROM plugin_flowview_schedules WHERE enabled='on' AND ($t - sendinterval > lastsent)");
	if (!empty($schedules)) {
		$command_string = trim(read_config_option('path_php_binary'));
		if (trim($command_string) == '')
			$command_string = 'php';
		$extra_args = ' -q ' . $config['base_path'] . '/plugins/flowview/flowview_process.php';
		exec_background($command_string, $extra_args);
	}
}

function flowview_setup_table() {
	global $config;

	$data = array();
	$data['columns'][] = array('name' => 'ip', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'host', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'time', 'type' => 'int(20)', 'NULL' => false, 'default' => '0');
	$data['keys'][]    = array('name' => 'ip', 'columns' => 'ip');
	$data['type']      = 'MEMORY';
	$data['comment']   = 'Plugin Flowview - DNS Cache to help speed things up';
	api_plugin_db_table_create('flowview', 'plugin_flowview_dnscache', $data);

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
	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'folder', 'columns' => 'folder');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Plugin Flowview - List of Devices to collect flows from';
	api_plugin_db_table_create('flowview', 'plugin_flowview_devices', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false);
	$data['columns'][] = array('name' => 'device', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'timespan', 'type' => 'int(11)', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'startdate', 'type' => 'varchar(32)', 'NULL' => false);
	$data['columns'][] = array('name' => 'enddate', 'type' => 'varchar(32)', 'NULL' => false);
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
	$data['columns'][] = array('name' => 'cutofflines', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'cutoffoctets', 'type' => 'varchar(8)', 'NULL' => false);
	$data['columns'][] = array('name' => 'resolve', 'type' => 'varchar(2)', 'NULL' => false);
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Plugin Flowview - List of Saved Flow Queries';
	api_plugin_db_table_create('flowview', 'plugin_flowview_queries', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'title', 'type' => 'varchar(128)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'enabled', 'type' => 'varchar(3)', 'NULL' => false, 'default' => 'on');
	$data['columns'][] = array('name' => 'sendinterval', 'type' => 'int(20)', 'NULL' => false);
	$data['columns'][] = array('name' => 'lastsent', 'type' => 'int(20)', 'NULL' => false);
	$data['columns'][] = array('name' => 'start', 'type' => 'datetime', 'NULL' => false);
	$data['columns'][] = array('name' => 'email', 'type' => 'text', 'NULL' => false);
	$data['columns'][] = array('name' => 'savedquery', 'type' => 'int(12)', 'NULL' => false);
	$data['primary']   = 'id';
	$data['keys'][]    = array('name' => 'savedquery', 'columns' => 'savedquery');
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Plugin Flowview - Scheduling for running and emails of saved queries';
	api_plugin_db_table_create('flowview', 'plugin_flowview_schedules', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id',         'type' => 'int(12)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'service',    'type' => 'varchar(20)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'port',       'type' => 'int(12)', 'NULL' => false);
	$data['columns'][] = array('name' => 'proto',      'type' => 'char(4)', 'NULL' => false);
	$data['columns'][] = array('name' => 'description','type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary']   = 'id';
	$data['type']      = 'InnoDB';
	$data['comment']   = 'Plugin Flowview - Database of well known Ports';
	api_plugin_db_table_create('flowview', 'plugin_flowview_ports', $data);

	$inserts = file($config['base_path'] . '/plugins/flowview/plugin_flowview_ports.sql');
	if (sizeof($inserts)) {
		db_execute('TRUNCATE plugin_flowview_ports');
		foreach($inserts as $i) {
			db_execute($i);
		}
	}
}

