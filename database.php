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

/**
 * flowview_db_connect_real - makes a connection to the database server
 * on this machine
 *
 * @param mixed $host
 * @param mixed $user
 * @param mixed $pass
 * @param mixed $db_name
 * @param mixed $db_type
 * @param string $port
 * @param int $retries
 * @param string $db_ssl
 * @param string $db_ssl_key
 * @param string $db_ssl_cert
 * @param string $db_ssl_ca
 *
 * @return mixed
 */
function flowview_db_connect_real($host, $user, $pass, $db_name, $db_type, string $port = '3306', int $retries = 20, string $db_ssl = '',
	string $db_ssl_key = '', string $db_ssl_cert = '', string $db_ssl_ca = '') {
	return db_connect_real($host, $user, $pass, $db_name, $db_type, (int) $port, $retries, (bool) $db_ssl, $db_ssl_key, $db_ssl_cert, $db_ssl_ca);
}

/**
 * flowview_db_close - closes the open connection
 *
 * @param mixed $flowview_cnn
 *
 * @return mixed
 */
function flowview_db_close(&$flowview_cnn) {
	return db_close($flowview_cnn);
}

/**
 * flowview_db_check_reconnect - check the flowview database connection.  If
 * the connection is gone, attempt to reconnect and update the global
 * flowview connection with the new one.
 *
 * @param bool $log
 *
 * @return mixed
 */
function flowview_db_check_reconnect(bool $log = true) {
	global $flowview_cnn, $config, $local_db_cnn_id, $remote_db_cnn_id;

	$previous_cnn = $flowview_cnn;

	$result = db_check_reconnect($flowview_cnn, $log);

	/**
	 * When flowview is sharing the main Cacti database, $flowview_cnn is
	 * just an alias of $local_db_cnn_id/$remote_db_cnn_id set up by
	 * flowview_connect().  Propagate the reconnected handle back to
	 * whichever one it was aliasing, otherwise a subsequent call to
	 * flowview_connect() will overwrite $flowview_cnn with the stale,
	 * dead connection again.
	 */
	if ($flowview_cnn !== $previous_cnn) {
		if ($config['poller_id'] == 1 && $local_db_cnn_id === $previous_cnn) {
			$local_db_cnn_id = $flowview_cnn;
		} elseif ($remote_db_cnn_id === $previous_cnn) {
			$remote_db_cnn_id = $flowview_cnn;
		}
	}

	return $result;
}

/**
 * flowview_db_execute - run an sql query and do not return any output
 *
 * @param mixed $sql
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_execute($sql, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_execute($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_execute_prepared - run an sql query and do not return any output
 *
 * @param mixed $sql
 * @param array $parms
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_execute_prepared($sql, array $parms = [], bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_execute_prepared($sql, $parms, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell - run a 'select' sql query and return the first column of the
 * first row found
 *
 * @param mixed $sql
 * @param string $col_name
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_cell($sql, string $col_name = '', bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_cell($sql, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
 * first row found
 *
 * @param mixed $sql
 * @param array $params
 * @param string $col_name
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_cell_prepared($sql, array $params = [], string $col_name = '', bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_cell_prepared($sql, $params, $col_name, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row - run a 'select' sql query and return the first row found
 *
 * @param mixed $sql
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_row($sql, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_row($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_row_prepared - run a 'select' sql query and return the first row found
 *
 * @param mixed $sql
 * @param array $params
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_row_prepared($sql, array $params = [], bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_row_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc - run a 'select' sql query and return all rows found
 *
 * @param mixed $sql
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_assoc($sql, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_assoc($sql, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_assoc_prepared - run a 'select' sql query and return all rows found
 *
 * @param mixed $sql
 * @param array $params
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_assoc_prepared($sql, array $params = [], bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_fetch_assoc_prepared($sql, $params, $log, $flowview_cnn);
}

/**
 * flowview_db_fetch_insert_id - get the last insert_id or auto incriment
 *
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_fetch_insert_id(mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return  db_fetch_insert_id($flowview_cnn);
}

/**
 * flowview_db_replace - replaces the data contained in a particular row
 *
 * @param mixed $table_name
 * @param mixed $array_items
 * @param mixed $keyCols
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_replace($table_name, $array_items, $keyCols, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_replace($table_name, $array_items, $keyCols, $flowview_cnn);
}

/**
 * flowview_sql_save - saves data to an sql table
 *
 * @param mixed $array_items
 * @param mixed $table_name
 * @param string $key_cols
 * @param bool $autoinc
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_sql_save($array_items, $table_name, string $key_cols = 'id', bool $autoinc = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return sql_save($array_items, $table_name, $key_cols, $autoinc, $flowview_cnn);
}

/**
 * flowview_db_table_exists - checks whether a table exists
 *
 * @param mixed $table
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_table_exists($table, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	$matched = preg_match('/^(?:`?(?<database>[A-Za-z0-9_]+)`?\.)?`?(?<table>[A-Za-z0-9_]+)`?$/D', $table, $matches);
	if ($matched === 1) {
		$table_pattern = addcslashes($matches['table'], '\\%_');
		$sql = 'SHOW TABLES LIKE \'' . $table_pattern . '\'';

		return (db_fetch_cell($sql, '', $log, $flowview_cnn) ? true : false);
	}
	return false;
}

/**
 * flowview_db_table_create - creates a database table if it does not already exist
 *
 * @param mixed $table
 * @param mixed $data
 * @param $cnn_id
 *
 * @return void
 */
function flowview_db_table_create($table, $data, mixed $cnn_id = false): void {
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

/**
 * flowview_db_column_exists - checks whether a column exists
 *
 * @param mixed $table
 * @param mixed $column
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_column_exists($table, $column, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_column_exists($table, $column, $log, $flowview_cnn);
}

/**
 * flowview_db_add_column - adds a column to a table if it does not already exist
 *
 * @param mixed $table
 * @param mixed $column
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_add_column($table, $column, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_add_column($table, $column, $log, $flowview_cnn);
}

/**
 * flowview_db_affected_rows - return the number of rows affected by the last transaction
 * or false on error
 *
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_affected_rows(mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_affected_rows($flowview_cnn);
}

/**
 * flowview_db_index_exists - checks whether an index exists
 *
 * @param mixed $table
 * @param mixed $index
 * @param bool $log
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_index_exists($table, $index, bool $log = true, mixed $cnn_id = false) {
	$flowview_cnn = flowview_get_connection($cnn_id);

	return db_index_exists($table, $index, $log, $flowview_cnn);
}

/**
 * flowview_get_connection - resolves the database connection to use for a
 * flowview_db_* call, falling back to this plugin's default connection
 * when no explicit connection id is given
 * the plugin's default connection
 *
 * @param mixed $cnn_id
 *
 * @return mixed
 */
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
 * @param mixed $table
 * @param $cnn_id
 *
 * @return mixed
 */
function flowview_db_get_table_column_types($table, mixed $cnn_id = false) {
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
