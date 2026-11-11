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

/*
 * The raw NBAR records are stored one table per time slice, the same way the raw flow tables
 * are: plugin_flowview_nbar_data_<year><day of year>[<hour>].  The Database Partitioning Scheme
 * setting picks hourly (the default) or daily slices, slices are created when the first record
 * for them arrives, and expired slices are dropped instead of deleted row by row.
 *
 * Slices are named by UTC time.  NBAR's hour boundaries are UTC aligned, so a UTC hour is always
 * inside exactly one hourly slice, and the repeated hour when daylight saving time ends cannot
 * make two different hours share a table.  Readers never recompute names: they list the slices
 * that exist and test each slice's time range, so slices from before a scheme change keep working.
 */

/**
 * The raw record columns shared by every slice, with the observation dates the summaries expect.
 *
 * @return string
 */
function fv_nbar_data_columns(): string {
	return 'listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router';
}

/**
 * Name the slice that holds the records of a given minute.
 *
 * @param int $time Unix time of the record.
 *
 * @return string
 */
function fv_nbar_data_table(int $time): string {
	$suffix = gmdate('Y', $time) . substr('000' . gmdate('z', $time), -3);

	if ((string) read_config_option('flowview_partition') !== '0') {
		$suffix .= gmdate('H', $time);
	}

	return 'plugin_flowview_nbar_data_' . $suffix;
}

/**
 * The time range [start, end) covered by a slice, taken from its name.
 *
 * @param string $table Slice table name.
 *
 * @return array{0: int, 1: int}|null null when the name is not a slice.
 */
function fv_nbar_data_bounds(string $table): ?array {
	if (!preg_match('/^plugin_flowview_nbar_data_([0-9]{4})([0-9]{3})([0-9]{2})?$/D', $table, $match)) {
		return null;
	}
	$hourly = isset($match[3]);

	if ((int) $match[2] > 365 || ($hourly && (int) $match[3] > 23)) {
		return null;
	}
	$start = (int) gmmktime($hourly ? (int) $match[3] : 0, 0, 0, 1, 1 + (int) $match[2], (int) $match[1]);

	return [$start, $start + ($hourly ? 3600 : 86400)];
}

/**
 * List the slices that exist, oldest first.
 *
 * @throws RuntimeException When the database cannot list its tables, so a failure is never mistaken for "no slices".
 *
 * @return list<array{table: string, start: int, end: int}>
 */
function fv_nbar_data_tables(): array {
	$rows = flowview_db_fetch_assoc("SHOW TABLES LIKE 'plugin\\_flowview\\_nbar\\_data\\_%'");

	if (!is_array($rows)) {
		throw new RuntimeException('Cannot list the NBAR raw tables');
	}
	$tables = [];

	foreach ($rows as $row) {
		$name   = (string) reset($row);
		$bounds = fv_nbar_data_bounds($name);

		if ($bounds !== null) {
			$tables[] = ['table' => $name, 'start' => $bounds[0], 'end' => $bounds[1]];
		}
	}
	usort($tables, static fn (array $a, array $b): int => [$a['start'], $a['table']] <=> [$b['start'], $b['table']]);

	return $tables;
}

/**
 * The existing slices that overlap a time range.
 *
 * @param int                                                   $from   Inclusive range start in Unix seconds.
 * @param int                                                   $to     Exclusive range end in Unix seconds.
 * @param list<array{table: string, start: int, end: int}>|null $tables Slices from fv_nbar_data_tables(), or null to list them.
 *
 * @return list<string>
 */
function fv_nbar_data_slices(int $from, int $to, ?array $tables = null): array {
	$slices = [];

	foreach ($tables ?? fv_nbar_data_tables() as $slice) {
		if ($slice['start'] < $to && $slice['end'] > $from) {
			$slices[] = $slice['table'];
		}
	}

	return $slices;
}

/**
 * One SELECT per slice that overlaps the range, each carrying the same filter so no slice is read whole.
 *
 * @param string                                                $columns Select list.
 * @param string                                                $where   Trusted filter.
 * @param int                                                   $from    Inclusive range start in Unix seconds.
 * @param int                                                   $to      Exclusive range end in Unix seconds.
 * @param list<array{table: string, start: int, end: int}>|null $tables  Slices from fv_nbar_data_tables(), or null to list them.
 *
 * @return list<string>
 */
function fv_nbar_data_parts(string $columns, string $where, int $from, int $to, ?array $tables = null): array {
	return array_map(
		static fn (string $table): string => "SELECT $columns FROM `$table` WHERE $where",
		fv_nbar_data_slices($from, $to, $tables)
	);
}

/**
 * A relation over the slices of a range.  An empty relation with the right columns is returned
 * when no slice exists yet, so reports on a fresh installation still run.
 *
 * @param string                                                $columns Select list.
 * @param string                                                $where   Trusted filter.
 * @param int                                                   $from    Inclusive range start in Unix seconds.
 * @param int                                                   $to      Exclusive range end in Unix seconds.
 * @param list<array{table: string, start: int, end: int}>|null $tables  Slices from fv_nbar_data_tables(), or null to list them.
 *
 * @return string
 */
function fv_nbar_data_source(string $columns, string $where, int $from, int $to, ?array $tables = null): string {
	$parts = fv_nbar_data_parts($columns, $where, $from, $to, $tables);

	if (!$parts) {
		$parts[] = "SELECT $columns FROM (SELECT CAST(0 AS UNSIGNED) listener_id,CAST(0 AS UNSIGNED) minute,CAST('' AS CHAR(32)) app_id,CAST('' AS BINARY(16)) src,CAST('' AS BINARY(16)) dst,CAST(0 AS UNSIGNED) sport,CAST(0 AS UNSIGNED) dport,CAST(0 AS UNSIGNED) protocol,CAST(0 AS UNSIGNED) bytes,CAST(0 AS UNSIGNED) packets,CAST(0 AS UNSIGNED) flows,CAST('' AS CHAR(45)) router) fv_nbar_empty WHERE 1=0";
	}

	return '(' . implode(' UNION ALL ', $parts) . ')';
}

/**
 * Create a slice the first time a record for it arrives.  The answer is remembered for a minute so
 * the collector does not check for the table on every packet.
 *
 * @param string $table Slice table name.
 *
 * @return bool
 */
function fv_nbar_data_ensure(string $table): bool {
	if (($GLOBALS['fv_nbar_data_known'][$table] ?? 0) > time() - 60) {
		return true;
	}

	if (!flowview_db_create_nbar_data_partition($table)) {
		return false;
	}
	$GLOBALS['fv_nbar_data_known'][$table] = time();

	return true;
}

/**
 * Drop the slices that ended before the retention cutoff.
 *
 * @param int        $cutoff   Unix time before which records are no longer retained.
 * @param float|null $deadline Unix time with microseconds after which no further slice is dropped, or null for no limit.
 *
 * @return int The number of slices dropped.
 */
function fv_nbar_data_drop_expired(int $cutoff, ?float $deadline = null): int {
	$dropped = 0;

	try {
		$tables = fv_nbar_data_tables();
	} catch (RuntimeException $e) {
		// Nothing is dropped; retention simply runs again on the next maintenance pass.
		return 0;
	}

	// Oldest slices first, so an interrupted pass leaves only the newest expired slices for the next one.
	foreach ($tables as $slice) {
		if ($deadline !== null && microtime(true) >= $deadline) {
			break;
		}

		if ($slice['end'] <= $cutoff && flowview_db_execute('DROP TABLE IF EXISTS `' . $slice['table'] . '`') !== false) {
			unset($GLOBALS['fv_nbar_data_known'][$slice['table']]);
			$dropped++;
		}
	}

	return $dropped;
}

/**
 * The oldest or newest retained record of a capture.
 *
 * The slices are probed in batches of 32, one query per batch, so a capture with no records does
 * not cost a query per retained slice.
 *
 * @param int  $listener Capture identifier.
 * @param bool $latest   True for the newest record instead of the oldest.
 *
 * @return array|false A row with the minute, an empty array when the capture has no records, or false when the database cannot be read.
 */
function fv_nbar_data_edge(int $listener, bool $latest = false): array|false {
	try {
		$tables = fv_nbar_data_tables();
	} catch (RuntimeException $e) {
		return false;
	}

	// Slices from different schemes overlap, so the first slice with a record is not necessarily the
	// answer.  Keep going until no remaining slice can hold an earlier (or later) minute.
	if ($latest) {
		usort($tables, static fn (array $a, array $b): int => [$b['end'], $b['table']] <=> [$a['end'], $a['table']]);
	}
	$found = [];

	foreach (array_chunk($tables, 32) as $batch) {
		if ($found && ($latest ? $batch[0]['end'] <= (int) $found['minute'] : $batch[0]['start'] >= (int) $found['minute'])) {
			break;
		}
		$members = array_map(
			static fn (array $slice): string => '(SELECT minute FROM `' . $slice['table'] . '` WHERE listener_id=? ORDER BY minute' . ($latest ? ' DESC' : '') . ' LIMIT 1)',
			$batch
		);
		$row = flowview_db_fetch_row_prepared('SELECT ' . ($latest ? 'MAX' : 'MIN') . '(minute) minute FROM (' . implode(' UNION ALL ', $members) . ') edge', array_fill(0, cacti_sizeof($batch), $listener));

		if (!is_array($row)) {
			return false;
		}

		if (isset($row['minute']) && (!$found || ($latest ? (int) $row['minute'] > (int) $found['minute'] : (int) $row['minute'] < (int) $found['minute']))) {
			$found = ['minute' => $row['minute']];
		}
	}

	return $found;
}
