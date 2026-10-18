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

if (!isset($GLOBALS['config']) || !function_exists('flowview_connect')) {
	exit;
}

require_once __DIR__ . '/nbar_daily.php';

/**
 * Install hourly summaries and idempotent compatibility upgrades.
 *
 * @return bool
 */
function fv_nbar_summary_schema() {
	foreach (['hours', 'apps', 'pairs', 'connections'] as $suffix) {
		if (!fv_nbar_schema_table($suffix)) {
			return false;
		}
	}
	$column = flowview_db_fetch_row("SHOW COLUMNS FROM plugin_flowview_nbar_hours LIKE 'connection_built'");
	if (!$column && flowview_db_add_column('plugin_flowview_nbar_hours', ['name' => 'connection_built', 'type' => 'bigint', 'NULL' => true, 'default' => -1]) === false) {
		return false;
	}
	return fv_nbar_daily_schema() && fv_nbar_seen_schema();
}

/**
 * Write raw records and invalidate affected hours in one transaction.
 *
 * @param string $sql Trusted SQL statement.
 * @param int $listener Capture identifier.
 * @param array $hours Affected UTC hour boundaries.
 * @param array $args Prepared statement values.
 *
 * @return bool
 */
function fv_nbar_summary_store($sql, $listener, $hours, $args = []) {
	if (flowview_db_execute('START TRANSACTION') === false) {
		return false;
	}
	$ok = flowview_db_execute_prepared($sql, $args) !== false;
	if ($ok) {
		$values = [];
		foreach (array_keys($hours) as $hour) {
			$values[] = '(' . (int) $listener . ',' . (int) $hour . ',1,-1)';
		}
		$ok = flowview_db_execute('INSERT INTO plugin_flowview_nbar_hours (listener_id,hour,revision,built) VALUES ' . implode(',', $values) . ' ON DUPLICATE KEY UPDATE revision=revision+1') !== false;
	}
	if (!$ok) {
		flowview_db_execute('ROLLBACK');
		return false;
	}
	return flowview_db_execute('COMMIT') !== false;
}

/**
 * Maintain closed-hour summaries under a database-scoped worker lock.
 *
 * @param int $budget Worker time budget in seconds.
 *
 * @return int
 */
function fv_nbar_summary_refresh($budget = 20, $supervised = false) {
	$lock = flowview_db_fetch_row("SELECT GET_LOCK(CONCAT('flowview_nbar_summary:',LEFT(SHA2(DATABASE(),256),16)),0) acquired");
	if ((int) ($lock['acquired'] ?? 0) !== 1) {
		return 0;
	}
	$done = 0;
	$started = microtime(true);
	try {
		$cutoff = time() - fv_nbar_retention_days() * 86400;
		$closed = (int) (floor((time() - 300) / 3600) * 3600);
		$listeners = flowview_db_fetch_assoc('SELECT id FROM plugin_flowview_devices');
		foreach ($listeners as $listener) {
			$id = (int) $listener['id'];
			$first = flowview_db_fetch_row_prepared('SELECT minute FROM plugin_flowview_nbar_data WHERE listener_id=? ORDER BY minute LIMIT 1', [$id]);
			if (!isset($first['minute'])) {
				continue;
			}
			$values = [];
			for ($h = (int) (floor(max($cutoff, (int) $first['minute']) / 3600) * 3600); $h < $closed; $h += 3600) {
				$values[] = "($id,$h,0,-1)";
			}
			if ($values && flowview_db_execute('INSERT IGNORE INTO plugin_flowview_nbar_hours (listener_id,hour,revision,built) VALUES ' . implode(',', $values)) === false) {
				return $done;
			}
		}
		$pending = flowview_db_fetch_assoc("SELECT listener_id,hour,revision FROM plugin_flowview_nbar_hours WHERE hour>=" . (int) (ceil($cutoff / 3600) * 3600) . " AND hour<$closed AND (built<0 OR built<>revision OR connection_built<0 OR connection_built<>revision) ORDER BY hour DESC,listener_id LIMIT 100");
		foreach ($pending as $item) {
			if (microtime(true) - $started >= max(1, (int) $budget)) {
				break;
			}
			if (fv_nbar_summary_build((int) $item['listener_id'], (int) $item['hour'], $supervised)) {
				$done++;
			}
		}
	} finally {
		flowview_db_fetch_row("SELECT RELEASE_LOCK(CONCAT('flowview_nbar_summary:',LEFT(SHA2(DATABASE(),256),16))) released");
	}
	return $done + fv_nbar_daily_refresh() + fv_nbar_seen_backfill(5);
}

/**
 * Publish an hourly summary only if its raw revision remains unchanged.
 *
 * @param int $listener Capture identifier.
 * @param int $hour UTC hour boundary in Unix seconds.
 *
 * @return bool
 */
function fv_nbar_summary_build($listener, $hour, $supervised = false) {
	// A MySQL build without DML timeouts is allowed only in the supervised child.
	$old_timeout = fv_nbar_worker_timeout(20);
	if ($old_timeout === null && !$supervised) {
		return false;
	}
	try {
		// READ COMMITTED avoids holding raw-record locks during INSERT ... SELECT.
		if (flowview_db_execute('SET TRANSACTION ISOLATION LEVEL READ COMMITTED') === false || flowview_db_execute('START TRANSACTION') === false) {
			return false;
		}
		$state = flowview_db_fetch_row_prepared('SELECT revision FROM plugin_flowview_nbar_hours WHERE listener_id=? AND hour=?', [$listener, $hour]);
		if (!isset($state['revision'])) {
			throw new RuntimeException('Missing summary state');
		}
		$where = ' FROM plugin_flowview_nbar_data WHERE listener_id=? AND minute>=? AND minute<?';
		$args = [$listener, $hour, $hour + 3600];
		$specs = [
			'apps' => 'app_id,LENGTH(src)',
			'pairs' => 'app_id,src,dst',
			'connections' => 'app_id,src,dst,sport,dport,protocol'
		];
		foreach ($specs as $table => $fields) {
			if (flowview_db_execute_prepared("DELETE FROM plugin_flowview_nbar_$table WHERE listener_id=? AND hour=?", [$listener, $hour]) === false) {
				throw new RuntimeException('Summary delete failed');
			}
			$sql = "INSERT INTO plugin_flowview_nbar_$table SELECT $listener,$hour,$fields,SUM(bytes),SUM(packets),SUM(flows),MIN(minute),MAX(minute),router$where GROUP BY $fields,router";
			if (flowview_db_execute_prepared($sql, $args) === false) {
				throw new RuntimeException('Summary aggregate failed');
			}
		}
		$latest = flowview_db_fetch_row_prepared('SELECT revision FROM plugin_flowview_nbar_hours WHERE listener_id=? AND hour=?', [$listener, $hour]);
		if (!isset($latest['revision']) || (string) $latest['revision'] !== (string) $state['revision']) {
			throw new RuntimeException('Hour changed during summary build');
		}
		if (flowview_db_execute_prepared('UPDATE plugin_flowview_nbar_hours SET built=?,connection_built=? WHERE listener_id=? AND hour=?', [$state['revision'], $state['revision'], $listener, $hour]) === false) {
			throw new RuntimeException('Summary publish failed');
		}
		if (flowview_db_execute('COMMIT') === false) {
			throw new RuntimeException('Summary commit failed');
		}
		return true;
	} catch (Throwable $e) {
		flowview_db_execute('ROLLBACK');
		return false;
	} finally {
		if ($old_timeout !== null) {
			flowview_db_execute('SET SESSION max_statement_time=' . $old_timeout);
		}
	}
}

/**
 * Choose mutually exclusive ready-summary or raw branches for each block.
 *
 * @param string $kind Allowlisted summary type.
 * @param array $listeners Selected capture identifiers.
 * @param int $start Inclusive range start in Unix seconds.
 * @param int $end Exclusive range end in Unix seconds.
 * @param int|null $now Current Unix time, or null to read the clock.
 *
 * @return string
 */
function fv_nbar_hourly_source($kind, $listeners, $start, $end, $now = null) {
	if (!in_array($kind, ['apps', 'pairs', 'connections'], true)) {
		throw new InvalidArgumentException('Unknown summary kind');
	}
	$listeners = array_values(array_unique(array_filter(array_map('intval', $listeners), static function ($id) {return $id > 0;})));
	if (!$listeners) {
		return '(SELECT listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router FROM plugin_flowview_nbar_data)';
	}
	$start = (int) $start;
	$end = (int) $end;
	$full_start = (int) (ceil($start / 3600) * 3600);
	$full_end = min((int) (floor($end / 3600) * 3600), (int) (floor((($now ?? time()) - 300) / 3600) * 3600));
	if ($full_end <= $full_start) {
		return '(SELECT listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router FROM plugin_flowview_nbar_data)';
	}
	$built_column = $kind === 'connections' ? 'connection_built' : 'built';
	$parts = [];
	foreach ($listeners as $listener) {
		$raw = 'listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router';
		if ($start < $full_start) {
			$parts[] = "SELECT $raw FROM plugin_flowview_nbar_data WHERE listener_id=$listener AND minute>=$start AND minute<$full_start";
		}
		if ($full_end < $end) {
			$parts[] = "SELECT $raw FROM plugin_flowview_nbar_data WHERE listener_id=$listener AND minute>=$full_end AND minute<$end";
		}
		for ($h = $full_start; $h < $full_end; $h = $to) {
			$to = min($h + 21600, $full_end);
			$count = (int) (($to - $h) / 3600);
			$ready = "(SELECT COUNT(*) FROM plugin_flowview_nbar_hours WHERE listener_id=$listener AND hour>=$h AND hour<$to AND $built_column>=0 AND $built_column=revision)=$count";
			if ($kind === 'apps') {
				$fields = "listener_id,hour minute,app_id,REPEAT(CHAR(0),family) src,REPEAT(CHAR(0),family) dst,0 sport,0 dport,0 protocol,bytes,packets,flows,first_seen,last_seen,router";
			} elseif ($kind === 'pairs') {
				$fields = 'listener_id,hour minute,app_id,src,dst,0 sport,0 dport,0 protocol,bytes,packets,flows,first_seen,last_seen,router';
			} else {
				$fields = 'listener_id,hour minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,first_seen,last_seen,router';
			}
			$parts[] = "SELECT $fields FROM plugin_flowview_nbar_$kind WHERE listener_id=$listener AND hour>=$h AND hour<$to AND ($ready)";
			$parts[] = "SELECT $raw FROM plugin_flowview_nbar_data WHERE listener_id=$listener AND minute>=$h AND minute<$to AND NOT ($ready)";
		}
	}
	return '(' . implode(' UNION ALL ', $parts) . ')';
}
