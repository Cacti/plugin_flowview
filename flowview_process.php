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

$no_http_headers = true;

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
   die('<br><strong>This script is only meant to run at the command line.</strong>');
}

error_reporting(E_ALL & ~E_DEPRECATED);
$dir = dirname(__FILE__);
chdir($dir);

ini_set('max_execution_time', 0);
ini_set('memory_limit', '512M');

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}
include('./include/global.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

$t = time();
$r = intval($t / 60) * 60;

$schedules = db_fetch_assoc("SELECT * FROM plugin_flowview_schedules WHERE enabled = 'on' AND ($t - sendinterval > lastsent)");

if (count($schedules)) {
	foreach ($schedules as $s) {
		plugin_flowview_run_schedule($s['id']);
		db_execute("UPDATE plugin_flowview_schedules SET lastsent = $r WHERE id = " . $s['id']);
	}
}

