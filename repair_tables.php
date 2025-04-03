<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2025 The Cacti Group                                 |
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
include_once($config['base_path'] . '/plugins/flowview/setup.php');
include_once($config['base_path'] . '/plugins/flowview/functions.php');

flowview_connect();

$tables = flowview_db_fetch_assoc('SHOW TABLES');

if (cacti_sizeof($tables)) {
	foreach($tables as $t) {
		if (strpos($t['Tables_in_' . $flowviewdb_default], 'plugin_flowview_raw') !== false) {
			print "Altering Table " . $t['Tables_in_' . $flowviewdb_default] . PHP_EOL;
			flowview_db_execute("ALTER TABLE " . $t['Tables_in_' . $flowviewdb_default] . "
				MODIFY COLUMN src_domain VARCHAR(256) NOT NULL DEFAULT '',
				MODIFY COLUMN src_rdomain VARCHAR(40) NOT NULL DEFAULT '',
				MODIFY COLUMN src_rport VARCHAR(20) NOT NULL DEFAULT '',
				MODIFY COLUMN dst_domain VARCHAR(256) NOT NULL DEFAULT '',
				MODIFY COLUMN dst_rdomain VARCHAR(40) NOT NULL DEFAULT '',
				MODIFY COLUMN dst_rport VARCHAR(20) NOT NULL DEFAULT '',
				MODIFY COLUMN nexthop VARCHAR(48) NOT NULL DEFAULT '',
				COLLATE=latin1_swedish_ci, CHARSET=latin1");
		}
	}
}

