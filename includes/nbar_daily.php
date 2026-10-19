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
 * Create reusable daily summary tables and their checkpoint schema.
 *
 * @return bool
 */
function fv_nbar_daily_schema(): bool {
	foreach (['apps', 'pairs', 'connections'] as $kind) {
		if (!fv_nbar_schema_table('daily_' . $kind)) {
			return false;
		}
	}
	if (!flowview_db_fetch_row("SHOW INDEX FROM plugin_flowview_nbar_daily_connections WHERE Key_name='capture_day_bytes'")) {
		if (!flowview_db_execute('ALTER TABLE plugin_flowview_nbar_daily_connections ADD INDEX capture_day_bytes (listener_id,hour,bytes), ALGORITHM=INPLACE, LOCK=NONE')) {
			return false;
		}
	}
	return fv_nbar_schema_table('days');
}
/**
 * Return local calendar-day boundaries, including daylight-saving changes.
 *
 * @param int $timestamp Unix observation time.
 *
 * @return array
 */
function fv_nbar_daily_bounds(int $timestamp): array {
	$day = new DateTimeImmutable(date('Y-m-d', (int) $timestamp), new DateTimeZone(date_default_timezone_get()));
	return [$day->getTimestamp(), $day->modify('+1 day')->getTimestamp()];
}
/**
 * Require every overlapping hour to match its completed raw revision.
 *
 * @param int $listener Capture identifier.
 * @param int $from Inclusive boundary in Unix seconds.
 * @param int $to Exclusive boundary in Unix seconds.
 *
 * @return string|null
 */
function fv_nbar_daily_signature(int $listener, int $from, int $to): ?string {
	$lo = (int) (floor($from / 3600) * 3600);
	$hi = (int) (ceil($to / 3600) * 3600);
	$r = flowview_db_fetch_row_prepared('SELECT COUNT(*) n, COALESCE(SUM(revision),0) rev,
        COALESCE(SUM(built=revision AND connection_built=revision AND built>=0),0) ready
        FROM plugin_flowview_nbar_hours WHERE listener_id=? AND hour>=? AND hour<?', [$listener, $lo, $hi]);
	return (int) ($r['n'] ?? 0) === (int) (($hi - $lo) / 3600) && (int) ($r['ready'] ?? 0) === (int) $r['n'] ? (string) $r['rev'] : null;
}
/**
 * Build a SQL guard that rejects days invalidated by late records.
 *
 * @param int $listener Capture identifier.
 * @param int $day Local calendar-day start in Unix seconds.
 * @param int $to Exclusive boundary in Unix seconds.
 * @param string $revision Expected summary revision.
 *
 * @return string
 */
function fv_nbar_daily_guard(int $listener, int $day, int $to, string $revision): string {
	$listener = (int) $listener;
	$lo = (int) (floor($day / 3600) * 3600);
	$hi = (int) (ceil($to / 3600) * 3600);
	$count = (int) (($hi - $lo) / 3600);
	$revision = preg_match('/^[0-9]+$/', (string) $revision) ? $revision : '-1';
	return "EXISTS (SELECT 1 FROM plugin_flowview_nbar_days WHERE listener_id=$listener AND day=$day AND covered_to=$to AND revision=$revision) AND (SELECT COUNT(*)=$count AND COALESCE(SUM(revision),0)=$revision AND
        COALESCE(SUM(built=revision AND connection_built=revision AND built>=0),0)=$count
        FROM plugin_flowview_nbar_hours WHERE listener_id=$listener AND hour>=$lo AND hour<$hi)";
}
/**
 * Add one hour atomically or restart a day invalidated by late records.
 *
 * @param int $listener Capture identifier.
 * @param int $day Local calendar-day start in Unix seconds.
 * @param int $target Requested exclusive summary boundary.
 *
 * @return int
 */
function fv_nbar_daily_build(int $listener, int $day, int $target): int {
	$listener = (int) $listener;
	$day = (int) $day;
	$target = (int) $target;
	if (!flowview_db_execute('START TRANSACTION WITH CONSISTENT SNAPSHOT')) {
		return 0;
	}
	$old = flowview_db_fetch_row_prepared('SELECT * FROM plugin_flowview_nbar_days WHERE listener_id=? AND day=?', [$listener, $day]);
	$valid = $old && $old['timezone'] === date_default_timezone_get() && (int) $old['covered_to'] <= $target &&
		fv_nbar_daily_signature($listener, $day, (int) $old['covered_to']) === (string) $old['revision'];
	$from = $valid ? (int) $old['covered_to'] : $day;
	if ($from >= $target) {
		flowview_db_execute('ROLLBACK');
		return 0;
	}
	$to = min($target, (int) (floor($from / 3600) * 3600 + 3600));
	$revision = fv_nbar_daily_signature($listener, $day, $to);
	if ($revision === null) {
		flowview_db_execute('ROLLBACK');
		return 0;
	}
	$statement_timeout = fv_nbar_worker_timeout(20);
	if ($statement_timeout === null) {
		flowview_db_execute('ROLLBACK');
		return 0;
	}
	try {
		foreach (['apps' => 'app_id,LENGTH(src)', 'pairs' => 'app_id,src,dst', 'connections' => 'app_id,src,dst,sport,dport,protocol'] as $kind => $fields) {
			if (!$valid && !flowview_db_execute_prepared("DELETE FROM plugin_flowview_nbar_daily_$kind WHERE listener_id=? AND hour=?", [$listener, $day])) {
				throw new RuntimeException('Daily reset failed');
			}
			$source = fv_nbar_hourly_source($kind, [$listener], $from, $to);
			$sql = "INSERT INTO plugin_flowview_nbar_daily_$kind SELECT $listener,$day,$fields,SUM(bytes),SUM(packets),SUM(flows),IF(COUNT(first_seen)=COUNT(*),MIN(first_seen),NULL),IF(COUNT(last_seen)=COUNT(*),MAX(last_seen),NULL),router
                FROM $source d WHERE d.listener_id=$listener AND d.minute>=$from AND d.minute<$to GROUP BY $fields,router
                ON DUPLICATE KEY UPDATE bytes=bytes+VALUES(bytes),packets=packets+VALUES(packets),flows=flows+VALUES(flows),first_seen=LEAST(first_seen,VALUES(first_seen)),last_seen=GREATEST(last_seen,VALUES(last_seen))";
			if (!flowview_db_execute($sql)) {
				throw new RuntimeException('Daily aggregate failed');
			}
		}
		if (!flowview_db_execute_prepared(
			'INSERT INTO plugin_flowview_nbar_days
            (listener_id,day,covered_to,revision,timezone,updated) VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE covered_to=VALUES(covered_to),revision=VALUES(revision),timezone=VALUES(timezone),updated=VALUES(updated)',
			[$listener, $day, $to, $revision, date_default_timezone_get(), time()]
		)) {
			throw new RuntimeException('Daily checkpoint failed');
		}
		if (!flowview_db_execute('COMMIT')) {
			throw new RuntimeException('Daily commit failed');
		}
		return 1;
	} catch (Throwable $e) {
		flowview_db_execute('ROLLBACK');
		cacti_log('NBAR daily summary: ' . $e->getMessage(), false, 'FLOWVIEW');
		return 0;
	} finally {
		flowview_db_execute('SET SESSION max_statement_time=' . $statement_timeout);
	}
}
/**
 * Maintain reusable calendar-day summaries within a worker budget.
 *
 * @param int $budget Worker time budget in seconds.
 *
 * @return int
 */
function fv_nbar_daily_refresh(int $budget = 20): int {
	if ((int) flowview_db_fetch_cell("SELECT GET_LOCK(CONCAT('flowview_nbar_daily:',LEFT(SHA2(DATABASE(),256),16)),0)") !== 1) {
		return 0;
	}
	$deadline = microtime(true) + max(1, (int) $budget);
	$done = 0;
	try {
		$cutoff = time() - fv_nbar_retention_days() * 86400;
		$closed = (int) (floor((time() - 300) / 3600) * 3600);
		$listeners = flowview_db_fetch_assoc('SELECT listener_id,MIN(hour) first_hour FROM plugin_flowview_nbar_hours GROUP BY listener_id');
		$candidates = [];
		foreach ($listeners as $row) {
			$first_record = flowview_db_fetch_cell_prepared('SELECT minute FROM plugin_flowview_nbar_data WHERE listener_id=? ORDER BY minute LIMIT 1', [(int) $row['listener_id']]);
			if (!$first_record) {
				continue;
			}
			$row['first_hour'] = (int) (floor((int) $first_record / 3600) * 3600);
			[$first] = fv_nbar_daily_bounds(max($cutoff, (int) $first_record));
			if ($first >= $cutoff) {
				for ($empty = (int) (floor($first / 3600) * 3600);$empty < (int) $row['first_hour'];$empty += 3600) {
					flowview_db_execute_prepared('INSERT IGNORE INTO plugin_flowview_nbar_hours (listener_id,hour,revision,built,connection_built) VALUES (?,?,0,0,0)', [(int) $row['listener_id'], $empty]);
				}
			}
			[$today] = fv_nbar_daily_bounds($closed);
			for ($day = $first;$day <= $today;$day = $next) {
				[, $next] = fv_nbar_daily_bounds($day);
				if ($day < $cutoff) {
					continue;
				}
				$candidates[] = [(int) $row['listener_id'], $day, min($next, $closed)];
			}
		}
		// Finish older days first; each checkpoint appends only the next hour.
		usort($candidates, static function (array $a, array $b): int {return $a[1] <=> $b[1] ?: $a[0] <=> $b[0];});
		do {
			$progress = 0;
			foreach ($candidates as [$listener,$day,$target]) {
				if (microtime(true) >= $deadline) {
					break 2;
				}
				$progress += fv_nbar_daily_build($listener, $day, $target);
			}
			$done += $progress;
		} while ($progress && microtime(true) < $deadline);
	} finally {
		flowview_db_fetch_cell("SELECT RELEASE_LOCK(CONCAT('flowview_nbar_daily:',LEFT(SHA2(DATABASE(),256),16)))");
	}
	return $done;
}
/**
 * Expire daily summary rows and checkpoints in bounded batches.
 *
 * @return void
 */
function fv_nbar_daily_cleanup(): void {
	fv_nbar_cleanup_batches(fv_nbar_daily_cleanup_queries(), microtime(true) + 10);
}
/** Return allowlisted daily retention batches for the shared cleanup worker. */
function fv_nbar_daily_cleanup_queries(): array {
	$cutoff = time() - fv_nbar_retention_days() * 86400;
	$queries = [];
	foreach (['apps', 'pairs', 'connections'] as $kind) {
		$queries[] = ["DELETE FROM plugin_flowview_nbar_daily_$kind WHERE hour<$cutoff LIMIT 5000", 5000];
	}
	$queries[] = ["DELETE FROM plugin_flowview_nbar_days WHERE day<$cutoff LIMIT 100", 100];
	return $queries;
}
/**
 * Use valid full-day summaries and hourly or raw boundary records.
 *
 * @param string $kind Allowlisted summary type.
 * @param array $listeners Selected capture identifiers.
 * @param int $start Inclusive range start in Unix seconds.
 * @param int $end Exclusive range end in Unix seconds.
 * @param int|null $now Current Unix time, or null to read the clock.
 *
 * @return string
 */
function fv_nbar_summary_source(string $kind, array $listeners, int $start, int $end, ?int $now = null): string {
	if (!in_array($kind, ['apps', 'pairs', 'connections'], true)) {
		throw new InvalidArgumentException('Unknown summary kind');
	}
	$listeners = array_values(array_unique(array_filter(array_map('intval', $listeners), static function (int $id): bool {return $id > 0;})));
	if (!$listeners) {
		return '(SELECT listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router FROM plugin_flowview_nbar_data)';
	}
	$start = (int) $start;
	$end = (int) $end;
	$now = $now ?? time();
	if ($end - $start < 3600) {
		return fv_nbar_hourly_source($kind, $listeners, $start, $end, $now);
	}
	$parts = [];
	foreach ($listeners as $listener) {
		$days = flowview_db_fetch_assoc_prepared('SELECT * FROM plugin_flowview_nbar_days WHERE listener_id=? AND day>=? AND covered_to<=? AND covered_to>day ORDER BY day', [$listener, $start, $end]);
		$cursor = $start;
		foreach ($days as $row) {
			$day = (int) $row['day'];
			$to = (int) $row['covered_to'];
			if ($row['timezone'] !== date_default_timezone_get() || $day < $cursor || $to > (int) (floor(($now - 300) / 3600) * 3600)) {
				continue;
			}
			if ($day > $cursor) {
				$s = fv_nbar_hourly_source($kind, [$listener], $cursor, $day, $now);
				$parts[] = "SELECT * FROM $s fallback_before WHERE listener_id=$listener AND minute>=$cursor AND minute<$day";
			}
			$ready = fv_nbar_daily_guard($listener, $day, $to, $row['revision']);
			if ($kind === 'apps') {
				$fields = 'listener_id,hour minute,app_id,REPEAT(CHAR(0),family) src,REPEAT(CHAR(0),family) dst,0 sport,0 dport,0 protocol,bytes,packets,flows,first_seen,last_seen,router';
			} elseif ($kind === 'pairs') {
				$fields = 'listener_id,hour minute,app_id,src,dst,0 sport,0 dport,0 protocol,bytes,packets,flows,first_seen,last_seen,router';
			} else {
				$fields = 'listener_id,hour minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,first_seen,last_seen,router';
			}
			$parts[] = "SELECT $fields FROM plugin_flowview_nbar_daily_$kind WHERE listener_id=$listener AND hour=$day AND ($ready)";
			$s = fv_nbar_hourly_source($kind, [$listener], $day, $to, $now);
			$parts[] = "SELECT * FROM $s fallback_day WHERE listener_id=$listener AND minute>=$day AND minute<$to AND NOT ($ready)";
			$cursor = $to;
		}
		if ($cursor < $end) {
			$s = fv_nbar_hourly_source($kind, [$listener], $cursor, $end, $now);
			$parts[] = "SELECT * FROM $s fallback_after WHERE listener_id=$listener AND minute>=$cursor AND minute<$end";
		}
	}
	return '(' . implode(' UNION ALL ', $parts) . ')';
}
