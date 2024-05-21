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

global $config, $database_type, $database_default, $database_hostname;
global $database_username, $database_password, $database_port, $database_retries;
global $database_ssl, $database_ssl_key, $database_ssl_cert, $database_ssl_ca;

/* revert if you dont use the Cacti database */
$flowview_use_cacti_db = true;

if (!$flowview_use_cacti_db) {
	$flowviewdb_type     = 'mysql';
	$flowviewdb_default  = 'flowview';
	$flowviewdb_hostname = 'localhost';
	$flowviewdb_username = 'cactiuser';
	$flowviewdb_password = 'cactiuser';
	$flowviewdb_port     = 3306;
	$flowviewdb_retries  = 5;
	$flowviewdb_ssl      = false;
	$flowviewdb_ssl_key  = '';
	$flowviewdb_ssl_cert = '';
	$flowviewdb_ssl_ca   = '';
} else {
	$flowviewdb_type     = $database_type;
	$flowviewdb_default  = $database_default;
	$flowviewdb_hostname = $database_hostname;
	$flowviewdb_username = $database_username;
	$flowviewdb_password = $database_password;
	$flowviewdb_port     = $database_port;
	$flowviewdb_retries  = $database_retries;
	$flowviewdb_ssl      = $database_ssl;
	$flowviewdb_ssl_key  = $database_ssl_key;
	$flowviewdb_ssl_cert = $database_ssl_cert;
	$flowviewdb_ssl_ca   = $database_ssl_ca;
}

