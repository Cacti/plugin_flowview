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

/**
 * Render minute-resolution observation dates or explicit unavailable cells.
 *
 * @param array $row Exported tuple or observation fields.
 *
 * @return string
 */
function fv_nbar_seen_cells(array $row) {
	$html = '';
	foreach (['first_seen', 'last_seen'] as $field) {
		$stamp = (int) ($row[$field] ?? 0);
		$text = $stamp > 0 ? date('Y-m-d H:i', $stamp) : __('Unavailable', 'flowview');
		$hint = __('FlowView observation within the selected period; minute precision. Time zone: %s', date_default_timezone_get(), 'flowview');
		$html .= '<td style="white-space:normal" data-text="' . $stamp . '" title="' . fv_nbar_h($hint) . '">' . fv_nbar_h($text) . '</td>';
	}
	return $html;
}
/**
 * Merge observation dates while preserving missing-date information.
 *
 * @param array $total Accumulated dates and missing-date marker.
 * @param array $row Exported tuple or observation fields.
 *
 * @return void
 */
function fv_nbar_seen_merge(array &$total, array $row) {
	if (!isset($row['first_seen'],$row['last_seen'])) {
		$total['_seen_missing'] = true;
		return;
	}
	$first = (int) $row['first_seen'];
	$last = (int) $row['last_seen'];
	$total['first_seen'] = $total['first_seen'] === null ? $first : min($total['first_seen'], $first);
	$total['last_seen'] = $total['last_seen'] === null ? $last : max($total['last_seen'], $last);
}
/**
 * Install observation columns and resumable backfill checkpoints.
 *
 * @return bool
 */
function fv_nbar_seen_schema() {
	foreach (['', 'daily_'] as $prefix) {
		foreach (['apps', 'pairs', 'connections'] as $kind) {
			$table = 'plugin_flowview_nbar_' . $prefix . $kind;
			foreach (['first_seen', 'last_seen'] as $field) {
				if (!flowview_db_fetch_row("SHOW COLUMNS FROM $table LIKE '$field'")) {
					if (flowview_db_add_column($table, ['name' => $field, 'type' => 'int', 'unsigned' => true, 'NULL' => true]) === false) {
						return false;
					}
				}
			}
		}
	}
	$exists = flowview_db_fetch_cell("SHOW TABLES LIKE 'plugin_flowview_nbar_seen_upgrade'");
	if (!$exists) {
		if (fv_nbar_schema_table('seen_upgrade') === false) {
			return false;
		}
		foreach (['0' => 'hours', '1' => 'days'] as $kind => $table) {
			$field = $kind === 0 ? 'hour' : 'day';
			if (flowview_db_execute("INSERT IGNORE INTO plugin_flowview_nbar_seen_upgrade (kind,listener_id,period) SELECT $kind,listener_id,$field FROM plugin_flowview_nbar_$table") === false) {
				return false;
			}
		}
	}
	foreach (['phase' => ['name' => 'phase', 'type' => 'tinyint', 'unsigned' => true, 'NULL' => false, 'default' => 0], 'app_cursor' => ['name' => 'app_cursor', 'type' => 'varchar(32)', 'NULL' => false, 'default' => '']] as $field => $definition) {
		if (!flowview_db_fetch_row("SHOW COLUMNS FROM plugin_flowview_nbar_seen_upgrade LIKE '$field'") && flowview_db_add_column('plugin_flowview_nbar_seen_upgrade', $definition) === false) {
			return false;
		}
	}
	return true;
}
/**
 * Backfill dates only where retained counters exactly match source totals.
 *
 * @param int $budget Worker time budget in seconds.
 *
 * @return int
 */
function fv_nbar_seen_backfill($budget = 10) {
	if ((int) flowview_db_fetch_cell("SELECT GET_LOCK(CONCAT('flowview_nbar_summary:',LEFT(SHA2(DATABASE(),256),16)),0)") !== 1) {
		return 0;
	}
	$daily = false;
	$done = 0;
	$began = microtime(true);
	$old = flowview_db_fetch_cell('SELECT @@session.max_statement_time');
	$isolation = flowview_db_fetch_cell('SELECT @@session.tx_isolation');
	try {
		$daily = (int) flowview_db_fetch_cell("SELECT GET_LOCK(CONCAT('flowview_nbar_daily:',LEFT(SHA2(DATABASE(),256),16)),0)") === 1;
		if (!$daily) {
			return 0;
		}
		flowview_db_execute('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
		flowview_db_execute('SET SESSION max_statement_time=20');
		$jobs = flowview_db_fetch_assoc('SELECT kind,listener_id,period,phase,app_cursor FROM plugin_flowview_nbar_seen_upgrade WHERE done=0 ORDER BY kind,period DESC,listener_id LIMIT 100');
		foreach ($jobs as $job) {
			if (microtime(true) - $began >= $budget) {
				break;
			}
			$listener = (int) $job['listener_id'];
			$period = (int) $job['period'];
			$isDay = (int) $job['kind'] === 1;
			$end = $period + 3600;
			$prefix = '';
			if ($isDay) {
				$prefix = 'daily_';
				$end = (int) flowview_db_fetch_cell_prepared('SELECT covered_to FROM plugin_flowview_nbar_days WHERE listener_id=? AND day=?', [$listener, $period]);
			}
			$ok = true;
			$phase = 0;
			foreach (['apps' => 'app_id,LENGTH(src) family', 'pairs' => 'app_id,src,dst', 'connections' => 'app_id,src,dst,sport,dport,protocol'] as $kind => $fields) {
				if ($isDay && $phase < (int) $job['phase']) {
					$phase++;
					continue;
				}
				$group = $kind === 'apps' ? 'app_id,LENGTH(src)' : $fields;
				$keys = $kind === 'apps' ? ['app_id', 'family'] : explode(',', $fields);
				$column = flowview_db_fetch_row("SHOW FULL COLUMNS FROM plugin_flowview_nbar_$prefix$kind LIKE 'app_id'");
				$collation = $column['Collation'];
				if (!preg_match('/^[a-zA-Z0-9_]+$/D', $collation)) {
					throw new RuntimeException('Invalid application collation');
				}
				$join = implode(' AND ', array_map(static function ($k) use ($collation) {return $k === 'app_id' ? "s.$k=r.$k COLLATE $collation" : "s.$k=r.$k";}, $keys));
				$source = $isDay ? fv_nbar_hourly_source($kind, [$listener], $period, $end) : '(SELECT *,minute first_seen,minute last_seen FROM plugin_flowview_nbar_data)';
				$dates = 'IF(COUNT(first_seen)=COUNT(*),MIN(first_seen),NULL) first_seen,IF(COUNT(last_seen)=COUNT(*),MAX(last_seen),NULL) last_seen';
				$sql = "UPDATE plugin_flowview_nbar_$prefix$kind s JOIN (SELECT $fields,SUM(bytes) bytes,SUM(packets) packets,SUM(flows) flows,$dates FROM $source d WHERE listener_id=$listener AND minute>=$period AND minute<$end GROUP BY $group) r ON $join SET s.first_seen=r.first_seen,s.last_seen=r.last_seen WHERE s.listener_id=$listener AND s.hour=$period AND s.bytes=r.bytes AND s.packets=r.packets AND s.flows=r.flows AND (s.first_seen IS NULL OR s.last_seen IS NULL)";
				$applications = [null];
				if ($isDay && $kind === 'connections') {
					$applications = array_column(flowview_db_fetch_assoc_prepared('SELECT DISTINCT app_id FROM plugin_flowview_nbar_daily_apps WHERE listener_id=? AND hour=? AND app_id>? ORDER BY app_id', [$listener, $period, ($job['app_cursor'] ?? '')]), 'app_id');
				}
				foreach ($applications as $application) {
					if (microtime(true) - $began >= $budget) {
						$ok = false;
						break;
					}
					$statement = $sql;
					if ($application !== null) {
						$value = 'CONVERT(0x' . bin2hex($application) . ' USING utf8mb4) COLLATE ' . $collation;
						$statement = str_replace(' GROUP BY ', " AND app_id=$value GROUP BY ", $statement);
						$statement .= " AND s.app_id=$value";
					}
					if (flowview_db_execute($statement) === false) {
						$ok = false;
						break;
					}
					if ($application !== null) {
						flowview_db_execute_prepared('UPDATE plugin_flowview_nbar_seen_upgrade SET app_cursor=? WHERE kind=? AND listener_id=? AND period=?', [$application, $job['kind'], $listener, $period]);
					}
				}
				if (!$ok) {
					break;
				}
				$phase++;
				if ($isDay) {
					flowview_db_execute_prepared('UPDATE plugin_flowview_nbar_seen_upgrade SET phase=? WHERE kind=? AND listener_id=? AND period=?', [$phase, $job['kind'], $listener, $period]);
				}
			}
			if (!$ok) {
				break;
			}
			if (flowview_db_execute_prepared('UPDATE plugin_flowview_nbar_seen_upgrade SET done=1 WHERE kind=? AND listener_id=? AND period=?', [$job['kind'], $listener, $period]) === false) {
				break;
			}
			$done++;
		}
	} catch (Throwable $e) {
		cacti_log('NBAR observation upgrade: ' . $e->getMessage(), false, 'FLOWVIEW');
	} finally {
		flowview_db_execute('SET SESSION max_statement_time=' . (float) $old);
		if (in_array($isolation, ['READ-UNCOMMITTED', 'READ-COMMITTED', 'REPEATABLE-READ', 'SERIALIZABLE'], true)) {
			flowview_db_execute('SET SESSION TRANSACTION ISOLATION LEVEL ' . str_replace('-', ' ', $isolation));
		}
		if ($daily) {
			flowview_db_fetch_cell("SELECT RELEASE_LOCK(CONCAT('flowview_nbar_daily:',LEFT(SHA2(DATABASE(),256),16)))");
		}
		flowview_db_fetch_cell("SELECT RELEASE_LOCK(CONCAT('flowview_nbar_summary:',LEFT(SHA2(DATABASE(),256),16)))");
	}
	return $done;
}
