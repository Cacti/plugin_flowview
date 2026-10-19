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
 * Describe the NBAR tables using FlowView's connection-aware schema API.
 *
 * @param string $suffix Validated NBAR table suffix.
 *
 * @return array
 */
function fv_nbar_schema_definition(string $suffix): array {
	$fields = [
		'listener_id' => ['type' => 'int', 'unsigned' => true],
		'router'      => ['type' => 'varchar(45)', 'default' => ''],
		'minute'      => ['type' => 'int', 'unsigned' => true],
		'hour'        => ['type' => 'int', 'unsigned' => true],
		'app_id'      => ['type' => 'varchar(32)'],
		'src'         => ['type' => 'varbinary(16)'],
		'dst'         => ['type' => 'varbinary(16)'],
		'sport'       => ['type' => 'smallint', 'unsigned' => true],
		'dport'       => ['type' => 'smallint', 'unsigned' => true],
		'protocol'    => ['type' => 'tinyint', 'unsigned' => true],
		'family'      => ['type' => 'tinyint', 'unsigned' => true],
		'bytes'       => ['type' => 'bigint', 'unsigned' => true],
		'packets'     => ['type' => 'bigint', 'unsigned' => true],
		'flows'       => ['type' => 'bigint', 'unsigned' => true],
		'first_seen'  => ['type' => 'int', 'unsigned' => true, 'NULL' => true],
		'last_seen'   => ['type' => 'int', 'unsigned' => true, 'NULL' => true]
	];
	$counters = ['bytes', 'packets', 'flows'];
	$dates    = ['first_seen', 'last_seen'];
	$tuple    = ['app_id', 'src', 'dst', 'sport', 'dport', 'protocol'];
	$kind     = str_replace('daily_', '', $suffix);
	$keys     = [];
	$extra    = [];

	switch ($kind) {
		case 'names':
			$extra   = ['router' => ['type' => 'varchar(45)'], 'name' => ['type' => 'varchar(255)'], 'updated' => ['type' => 'int', 'unsigned' => true]];
			$primary = ['router', 'app_id'];
			$columns = ['router', 'app_id', 'name', 'updated'];

			break;
		case 'data':
			$primary = array_merge(['listener_id', 'minute'], $tuple);
			$columns = array_merge($primary, $counters);
			$keys[]  = ['name' => 'minute', 'columns' => ['minute']];

			break;
		case 'hours':
			$extra   = ['revision' => ['type' => 'bigint', 'unsigned' => true, 'default' => 0], 'built' => ['type' => 'bigint', 'NULL' => true, 'default' => -1], 'connection_built' => ['type' => 'bigint', 'NULL' => true, 'default' => -1]];
			$primary = ['listener_id', 'hour'];
			$columns = array_merge($primary, array_keys($extra));
			$keys[]  = ['name' => 'hour', 'columns' => ['hour']];

			break;
		case 'apps':
		case 'pairs':
		case 'connections':
			$group   = $kind === 'apps' ? ['app_id', 'family'] : ($kind === 'pairs' ? ['app_id', 'src', 'dst'] : $tuple);
			$primary = array_merge(['listener_id', 'hour'], $group);
			$columns = array_merge($primary, $counters, $dates);
			$keys[]  = ['name' => 'hour', 'columns' => ['hour']];

			if ($suffix === 'daily_connections') {
				$keys[] = ['name' => 'capture_day_bytes', 'columns' => ['listener_id', 'hour', 'bytes']];
			}

			break;
		case 'days':
			$extra   = ['day' => ['type' => 'int', 'unsigned' => true], 'covered_to' => ['type' => 'int', 'unsigned' => true], 'revision' => ['type' => 'decimal(30,0)'], 'timezone' => ['type' => 'varchar(64) CHARACTER SET ascii'], 'updated' => ['type' => 'int', 'unsigned' => true]];
			$primary = ['listener_id', 'day'];
			$columns = array_merge(['listener_id'], array_keys($extra));
			$keys[]  = ['name' => 'day', 'columns' => ['day']];

			break;
		case 'seen_upgrade':
			$extra   = ['kind' => ['type' => 'tinyint', 'unsigned' => true], 'period' => ['type' => 'int', 'unsigned' => true], 'done' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 0], 'phase' => ['type' => 'tinyint', 'unsigned' => true, 'default' => 0], 'app_cursor' => ['type' => 'varchar(32)', 'NULL' => true]];
			$primary = ['kind', 'listener_id', 'period'];
			$columns = ['kind', 'listener_id', 'period', 'done', 'phase', 'app_cursor'];
			$keys[]  = ['name' => 'pending', 'columns' => ['done', 'kind', 'period']];

			break;
		default:
			throw new InvalidArgumentException('Unknown NBAR schema');
	}

	if (!in_array($suffix, ['names', 'data', 'hours', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections', 'days', 'seen_upgrade'], true)) {
		throw new InvalidArgumentException('Unknown NBAR schema');
	}

	if (in_array($kind, ['data', 'apps', 'pairs', 'connections'], true)) {
		$primary[] = 'router';
		$columns[] = 'router';
	}
	$fields     = array_merge($fields, $extra);
	$definition = ['columns' => [], 'primary' => implode('`,`', $primary), 'keys' => $keys, 'type' => 'InnoDB'];

	foreach ($columns as $name) {
		$definition['columns'][] = array_merge(['name' => $name, 'NULL' => false], $fields[$name]);
	}

	return $definition;
}

/**
 * Create a table in the selected FlowView database and verify its presence.
 *
 * @param string $suffix Validated NBAR table suffix.
 *
 * @return bool
 */
function fv_nbar_schema_table(string $suffix): bool {
	$definition = fv_nbar_schema_definition($suffix);
	$table      = 'plugin_flowview_nbar_' . $suffix;
	// Core plugin APIs use Cacti's connection. FlowView's equivalent also
	// records plugin ownership, while supporting its separate flow database.
	flowview_db_table_create($table, $definition);

	return flowview_db_fetch_cell("SHOW TABLES LIKE '$table'") === $table;
}

/** Preserve exporter identity when upgrading records collected by older builds. */
function fv_nbar_exporter_schema(): bool {
	foreach (['data', 'apps', 'pairs', 'connections', 'daily_apps', 'daily_pairs', 'daily_connections'] as $suffix) {
		$table = 'plugin_flowview_nbar_' . $suffix;

		if (!flowview_db_fetch_row("SHOW COLUMNS FROM $table LIKE 'router'")) {
			$primary = fv_nbar_schema_definition($suffix)['primary'];

			if (!flowview_db_execute("ALTER TABLE $table ADD COLUMN router varchar(45) NOT NULL DEFAULT '', DROP PRIMARY KEY, ADD PRIMARY KEY (`$primary`)")) {
				return false;
			}
		}
	}

	return true;
}
