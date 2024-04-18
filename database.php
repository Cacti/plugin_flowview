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

/**
 * flowview_db_connect_real - makes a connection to the database server
 * @param  $host - the hostname of the database server, 'localhost' if the database server is running
 *    on this machine
 *
 * @param  $user - the username to connect to the database server as
 * @param  $pass - the password to connect to the database server with
 * @param  $db_name - the name of the database to connect to
 * @param  $db_type - the type of database server to connect to, only 'mysql' is currently supported
 * @param  $retries - the number a time the server should attempt to connect before failing
 * @param  $db_ssl - true or false, is the database using ssl
 * @param  $db_ssl_key - the path to the ssl key file
 * @param  $db_ssl_cert - the path to the ssl cert file
 * @param  $db_ssl_ca - the path to the ssl ca file
 *
 * @return (object) connection_id for success, (bool) '0' for error
 */
function flowview_db_connect_real($host, $user, $pass, $db_name, $db_type, $port = '3306', $retries = 20, $db_ssl = '',
	$db_ssl_key = '', $db_ssl_cert = '', $db_ssl_ca = '') {
	return db_connect_real($host, $user, $pass, $db_name, $db_type, $port, $retries, $db_ssl, $db_ssl_key, $db_ssl_cert, $db_ssl_ca);
}

/**
 * flowview_db_close - closes the open connection
 *
 * @param  $flowview_cnn - the connection object to connect to
 *
 * @return the result of the close command
 */
function flowview_db_close($flowview_cnn) {
	return db_close($flowview_cnn);
}

/**
 * flowview_db_execute - run an sql query and do not return any output
 *
 * @param  $flowview_cnn - the connection object to connect to
 * @param  $sql - the sql query to execute
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return '1' for success, '0' for error
 */
function flowview_db_execute($sql, $log = TRUE) {
	global $flowview_cnn;
	return db_execute($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_execute_prepared - run an sql query and do not return any output
 *
 * @param  $sql - the sql query to execute
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return '1' for success, '0' for error
 */
function flowview_db_execute_prepared($sql, $parms = array(), $log = TRUE) {
	global $flowview_cnn;
	return db_execute_prepared($sql, $parms, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  $sql - the sql query to execute
 * @param  $log - whether to log error messages, defaults to true
 * @param  $col_name - use this column name instead of the first one
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_fetch_cell($sql, $col_name = '', $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_cell($sql, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  $sql - the sql query to execute
 * @param  $params - an array of parameters
 * @param  $col_name - use this column name instead of the first one
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_cell_prepared($sql, $params, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row - run a 'select' sql query and return the first row found
 *
 * @param  $sql - the sql query to execute
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return the first row of the result as a hash
 */
function flowview_db_fetch_row($sql, $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_row($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param  $sql - the sql query to execute
 * @param  $params - an array of parameters
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return the first row of the result as a hash
 */
function flowview_db_fetch_row_prepared($sql, $params = array(), $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_row_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param  $sql - the sql query to execute
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return the entire result set as a multi-dimensional hash
 */
function flowview_db_fetch_assoc($sql, $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_assoc($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param  $sql - the sql query to execute
 * @param  $params - an array of parameters
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return the entire result set as a multi-dimensional hash
 */
function flowview_db_fetch_assoc_prepared($sql, $params = array(), $log = TRUE) {
	global $flowview_cnn;
	return db_fetch_assoc_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_insert_id - get the last insert_id or auto incriment
 *
 * @param $flowview_cnn - the connection object to connect to
 *
 * @return the id of the last auto incriment row that was created
 */
function flowview_db_fetch_insert_id() {
	global $flowview_cnn;
	return  db_fetch_insert_id($flowview_cnn);
}

/**
 * flowview_db_replace - replaces the data contained in a particular row
 *
 * @param  $table_name - the name of the table to make the replacement in
 * @param  $array_items - an array containing each column -> value mapping in the row
 * @param  $keyCols - the name of the column containing the primary key
 * @param  $autoQuote - whether to use intelligent quoting or not
 *
 * @return the auto incriment id column (if applicable)
 */
function flowview_db_replace($table_name, $array_items, $keyCols) {
	global $flowview_cnn;
	return db_replace($table_name, $array_items, $keyCols, $flowview_cnn);
}

/**
 * flowview_sql_save - saves data to an sql table
 *
 * @param  $array_items - an array containing each column -> value mapping in the row
 * @param  $table_name - the name of the table to make the replacement in
 * @param  $key_cols - the primary key(s)
 *
 * @return the auto incriment id column (if applicable)
 */
function flowview_sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = true) {
	global $flowview_cnn;
	return sql_save($array_items, $table_name, $key_cols, $autoinc, $flowview_cnn);
}

/**
 * flowview_db_table_exists - checks whether a table exists
 *
 * @param  $table - the name of the table
 * @param  $log - whether to log error messages, defaults to true
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_table_exists($table, $log = true) {
	global $flowview_cnn;

	preg_match("/([`]{0,1}(?<database>[\w_]+)[`]{0,1}\.){0,1}[`]{0,1}(?<table>[\w_]+)[`]{0,1}/", $table, $matches);
	if ($matches !== false && array_key_exists('table', $matches)) {
		$sql = 'SHOW TABLES LIKE \'' . $matches['table'] . '\'';

		return (db_fetch_cell($sql, '', $log, $flowview_cnn) ? true : false);
	}
	return false;
}


function flowview_db_table_create( $table, $data) {
        global $flowview_cnn;

        $result = flowview_db_fetch_assoc('SHOW TABLES');
        $tables = array();
        foreach($result as $index => $arr) {
                foreach ($arr as $t) {
                        $tables[] = $t;
                }
        }

        if (!in_array($table, $tables)) {
                $c = 0;
                $sql = 'CREATE TABLE `' . $table . "` (\n";
                foreach ($data['columns'] as $column) {
                        if (isset($column['name'])) {
                                if ($c > 0) {
                                        $sql .= ",\n";
                                }

                                $sql .= '`' . $column['name'] . '`';

                                if (isset($column['type'])) {
                                        $sql .= ' ' . $column['type'];
                                }

                                if (isset($column['unsigned'])) {
                                        $sql .= ' unsigned';
                                }

                                if (isset($column['NULL']) && $column['NULL'] == false) {
                                        $sql .= ' NOT NULL';
                                }

                                if (isset($column['NULL']) && $column['NULL'] == true && !isset($column['default'])) {
                                        $sql .= ' default NULL';
                                }

                                if (isset($column['default'])) {
                                        if (strtolower($column['type']) == 'timestamp' && $column['default'] === 'CURRENT_TIMESTAMP') {
                                                $sql .= ' default CURRENT_TIMESTAMP';
                                        } else {
                                                $sql .= ' default ' . (is_numeric($column['default']) ? $column['default'] : "'" . $column['default'] . "'");
                                        }
                                }

                                if (isset($column['auto_increment'])) {
                                        $sql .= ' auto_increment';
                                }

                                $c++;
                        }
                }

                if (isset($data['primary'])) {
                        $sql .= ",\n PRIMARY KEY (`" . $data['primary'] . '`)';
                }

                if (isset($data['keys']) && cacti_sizeof($data['keys'])) {
                        foreach ($data['keys'] as $key) {
                                if (isset($key['name'])) {
                                        $sql .= ",\n INDEX `" . $key['name'] . '` (' . db_format_index_create($key['columns']) . ')';
                                }
                        }
                }

                if (isset($data['unique_keys'])) {
                        foreach ($data['unique_keys'] as $key) {
                                if (isset($key['name'])) {
                                        $sql .= ",\n UNIQUE INDEX `" . $key['name'] . '` (' . db_format_index_create($key['columns']) . ')';
                                }
                        }
                }

                $sql .= ') ENGINE = ' . $data['type'];

                if (isset($data['charset'])) {
                        $sql .= ' DEFAULT CHARSET = ' . $data['charset'];
                }

                if (isset($data['row_format']) && strtolower(db_get_global_variable('innodb_file_format')) == 'barracuda') {
                        $sql .= ' ROW_FORMAT = ' . $data['row_format'];
                }

                if (isset($data['comment'])) {
                        $sql .= " COMMENT = '" . $data['comment'] . "'";
                }

                if (flowview_db_execute($sql)) {
                        db_execute_prepared("REPLACE INTO plugin_db_changes
                                (plugin, `table`, `column`, `method`)
                                VALUES (?, ?, '', 'create')",
                                array('flowview', $table));

                        if (isset($data['collate'])) {
                                flowview_db_execute("ALTER TABLE `$table` COLLATE = " . $data['collate']);
                        }
                }
        }
}

function flowview_db_column_exists($table, $column, $log = true) {
	global $flowview_cnn;
	return db_column_exists($table, $column, $log, $flowview_cnn);
}

function flowview_db_add_column($table, $column, $log = true) {
	global $flowview_cnn;
	return db_add_column($table, $column, $log, $flowview_cnn);
}

/**
 * flowview_db_affected_rows - return the number of rows affected by the last transaction
 *
 * @return (bool|int)      The number of rows affected by the last transaction,
 *                         or false on error
 */
function flowview_db_affected_rows() {
	global $flowview_cnn;
	return db_affected_rows($flowview_cnn);;
}

