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

$proceed = false;

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter, 2);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--proceed':
				$proceed = true;
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

if ($proceed == false) {
	print "WARNING: This utility is meant for development purposes only.  It will drop all FlowView raw tables." . PHP_EOL;
	print "Use the --proceed option if you wish to do so.". PHP_EOL;
	exit(1);
}

$raw_tables = flowview_db_fetch_assoc('SELECT TABLE_NAME, TABLE_COLLATION
	FROM information_schema.TABLES
	WHERE TABLE_NAME LIKE "plugin_flowview_raw_%"
	ORDER BY TABLE_NAME DESC');

foreach($raw_tables as $t) {
	print "Dropping Table {$t['TABLE_NAME']}" . PHP_EOL;
	db_execute("DROP TABLE {$t['TABLE_NAME']}");
}

exit(0);

/*  display_version - displays version information */
function display_version() {
	$info    = plugin_flowview_version();
	$version = $info['version'];

	print "Cacti Flowview Drop Raw Tables, Version $version, " . COPYRIGHT_YEARS . PHP_EOL;
}

/*  display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: flowview_drop_raw_tables.php [--proceed]' . PHP_EOL . PHP_EOL;
	print 'A command line version of the Cacti Flowview database drop tables utility.  You must execute' . PHP_EOL;
	print 'this command as a super user and to force the dropping of the tables.' . PHP_EOL;
	print 'You must use the --proceed option.' . PHP_EOL . PHP_EOL;
	print 'This tool is to be used by developers who wish to reset their database.' . PHP_EOL;
}
