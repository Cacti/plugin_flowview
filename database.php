<?php

declare(strict_types=1);
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

/**
 * flowview_db_connect_real - makes a connection to the database server
 * @param  $host - the hostname of the database server, 'localhost' if the database server is running
 *    on this machine
 *
 * @param  string        Username to connect to the database server as
 * @param  string        Password to connect to the database server with
 * @param  string        Name of the database to connect to
 * @param  string        Type of database server to connect to, only 'mysql' is currently supported
 * @param  int           Number a time the server should attempt to connect before failing
 * @param  bool          Either true or false, is the database using ssl
 * @param  string        Path to the ssl key file
 * @param  string        Path to the ssl cert file
 * @param  string        Path to the ssl ca file
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
function flowview_db_close(&$flowview_cnn) {
	return db_close($flowview_cnn);
}

/**
 * flowview_db_execute - run an sql query and do not return any output
 *
 * @param  string        The sql query to execute
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return '1' for success, '0' for error
 */
function flowview_db_execute($sql, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_execute($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_execute_prepared - run an sql query and do not return any output
 *
 * @param  string        The sql query to execute
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return '1' for success, '0' for error
 */
function flowview_db_execute_prepared($sql, $parms = [], $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_execute_prepared($sql, $parms, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  string        The sql query to execute
 * @param  bool          Whether to log error messages, defaults to true
 * @param  string        Use this column name instead of the first one
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_fetch_cell($sql, $col_name = '', $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_cell($sql, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 *   first row found
 *
 * @param  string        The sql query to execute
 * @param  array         An array of parameters
 * @param  string        Use this column name instead of the first one
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_fetch_cell_prepared($sql, $params = [], $col_name = '', $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_cell_prepared($sql, $params, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row - run a 'select' sql query and return the first row found
 *
 * @param  string        The sql query to execute
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the first row of the result as a hash
 */
function flowview_db_fetch_row($sql, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_row($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param  string        The sql query to execute
 * @param  array         An array of parameters
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the first row of the result as a hash
 */
function flowview_db_fetch_row_prepared($sql, $params = [], $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_row_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param  string        The sql query to execute
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the entire result set as a multi-dimensional hash
 */
function flowview_db_fetch_assoc($sql, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_assoc($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param  string        The sql query to execute
 * @param  array         An array of parameters
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the entire result set as a multi-dimensional hash
 */
function flowview_db_fetch_assoc_prepared($sql, $params = [], $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_assoc_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_insert_id - get the last insert_id or auto incriment
 *
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the id of the last auto incriment row that was created
 */
function flowview_db_fetch_insert_id($cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return  db_fetch_insert_id($flowview_cnn);
}

/**
 * flowview_db_replace - replaces the data contained in a particular row
 *
 * @param  string        The name of the table to make the replacement in
 * @param  array         An array containing each column -> value mapping in the row
 * @param  string|array  The name of the column containing the primary key
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the auto incriment id column (if applicable)
 */
function flowview_db_replace($table_name, $array_items, $keyCols, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_replace($table_name, $array_items, $keyCols, $flowview_cnn);
}

/**
 * flowview_sql_save - saves data to an sql table
 *
 * @param  array         An array containing each column -> value mapping in the row
 * @param  string        The name of the table to make the replacement in
 * @param  string|array  The primary key(s)
 * @param  bool          Notify if the table is auto_increment or not
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return the auto incriment id column (if applicable)
 */
function flowview_sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return sql_save($array_items, $table_name, $key_cols, $autoinc, $flowview_cnn);
}

/**
 * flowview_db_table_exists - checks whether a table exists
 *
 * @param  string        The name of the table
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return (bool) the output of the sql query as a single variable
 */
function flowview_db_table_exists($table, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	preg_match("/([`]{0,1}(?<database>[\w_]+)[`]{0,1}\.){0,1}[`]{0,1}(?<table>[\w_]+)[`]{0,1}/", $table, $matches);
	if ($matches !== false && array_key_exists('table', $matches)) {
		$sql = 'SHOW TABLES LIKE \'' . $matches['table'] . '\'';

		return (db_fetch_cell($sql, '', $log, $flowview_cnn) ? true : false);
	}
	return false;
}

function flowview_db_table_create($table, $data, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	$result = flowview_db_fetch_assoc('SHOW TABLES');
	$tables = [];
	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}

	if (!in_array($table, $tables)) {
		$c = 0;
		$sql = 'CREATE TABLE IF NOT EXISTS `' . $table . "` (\n";

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
				['flowview', $table]);

			if (isset($data['collate'])) {
				flowview_db_execute("ALTER TABLE `$table` COLLATE = " . $data['collate']);
			}
		}
	}
}

function flowview_db_column_exists($table, $column, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_column_exists($table, $column, $log, $flowview_cnn);
}

function flowview_db_add_column($table, $column, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_add_column($table, $column, $log, $flowview_cnn);
}

/**
 * flowview_db_affected_rows - return the number of rows affected by the last transaction
 *
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return bool|int      The number of rows affected by the last transaction,
 *                       or false on error
 */
function flowview_db_affected_rows($cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_affected_rows($flowview_cnn);
}

/**
 * flowview_db_index_exists - checks whether an index exists
 *
 * @param  string        The name of the table
 * @param  string        The name of the index
 * @param  bool          Whether to log error messages, defaults to true
 * @param  bool|object   Optional connection id in case you are using a proxy
 *
 * @return bool          The output of the sql query as a single variable
 */
function flowview_db_index_exists($table, $index, $log = true, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_index_exists($table, $index, $log, $flowview_cnn);
}

function flowview_get_connection($cnn_id) {
	global $flowview_cnn;

	if ($cnn_id === false) {
		return $flowview_cnn;
	} else {
		return $cnn_id;
	}
}

/**
 * flowview_db_get_table_column_types - returns all the types for each column of a table
 *
 * @param  (string)        The name of the table
 * @param  (bool|resource) The connection to use or false to use the default
 *
 * @return (array) An array of column types indexed by the column names
 */
function flowview_db_get_table_column_types($table, $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

    $columns = db_fetch_assoc("SHOW COLUMNS FROM $table", false, $flowview_cnn);
    $cols    = [];
    if (cacti_sizeof($columns)) {
        foreach($columns as $col) {
            $cols[$col['Field']] = ['type' => $col['Type'], 'null' => $col['Null'], 'default' => $col['Default'], 'extra' => $col['Extra']];
        }
    }

    return $cols;
}
