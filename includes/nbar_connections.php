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
 * Add unsigned decimal counters without floating-point rounding.
 *
 * @param int|string $a First unsigned decimal counter.
 * @param int|string $b Second unsigned decimal counter.
 *
 * @return string
 */
function fv_nbar_decimal_add(int|string $a, int|string $b): string {
	$a     = strrev((string) $a);
	$b     = strrev((string) $b);
	$carry = 0;
	$out   = '';

	for ($i = 0,$n = max(strlen($a), strlen($b)); $i < $n || $carry; $i++) {
		$v = (isset($a[$i]) ? (int) $a[$i] : 0) + (isset($b[$i]) ? (int) $b[$i] : 0) + $carry;
		$out .= ($v % 10);
		$carry = intdiv($v, 10);
	}

	return ltrim(strrev($out), '0') ?: '0';
}
/**
 * Compare unsigned decimal counters without floating-point rounding.
 *
 * @param int|string $a First unsigned decimal counter.
 * @param int|string $b Second unsigned decimal counter.
 *
 * @return int
 */
function fv_nbar_decimal_compare(int|string $a, int|string $b): int {
	$a = ltrim((string) $a, '0') ?: '0';
	$b = ltrim((string) $b, '0') ?: '0';

	return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
}
/**
 * Identify an exported tuple using the resolved application-name rank.
 *
 * @param array $row      Exported tuple or observation fields.
 * @param array $names    Application-name ranks by capture.
 * @param int   $listener Capture identifier.
 *
 * @return string
 */
function fv_nbar_connection_key(array $row, array $names, int $listener): string {
	return json_encode([(string) $row['app_id'], (int) ($names[$listener][($row['router'] ?? '') . ':' . $row['app_id']]['name_id'] ?? 0), $row['src_hex'], $row['dst_hex'], (int) $row['sport'], (int) $row['dport'], (int) $row['protocol'], (string) ($row['router'] ?? '')], JSON_THROW_ON_ERROR);
}
/**
 * Order connection totals by exact byte count and deterministic tuple fields.
 *
 * @param array $a First connection total with its normalized tuple key.
 * @param array $b Second connection total with its normalized tuple key.
 *
 * @return int
 */
function fv_nbar_connection_compare(array $a, array $b): int {
	$cmp = fv_nbar_decimal_compare($b['bytes'], $a['bytes']);

	if ($cmp) {
		return $cmp;
	}

	foreach ($a['_key'] as $i => $v) {
		$cmp = ($i === 0 || $i === 2 || $i === 3 || $i === 7) ? strcmp((string) $v, (string) $b['_key'][$i]) : ($v <=> $b['_key'][$i]);

		if ($cmp) {
			return $cmp;
		}
	}

	return 0;
}
// Read completed hours independently instead of regrouping a whole partial day.
/**
 * Plan independently reusable complete hours and raw partial boundaries.
 *
 * @param int $listener Capture identifier.
 * @param int $from     Inclusive boundary in Unix seconds.
 * @param int $to       Exclusive boundary in Unix seconds.
 * @param int $now      Current Unix time.
 *
 * @return array
 */
function fv_nbar_connection_edges(int $listener, int $from, int $to, int $now): array {
	$segments = [];
	$ready    = flowview_db_fetch_assoc_prepared('SELECT hour FROM plugin_flowview_nbar_hours WHERE listener_id=? AND hour>=? AND hour<? AND connection_built>=0 AND connection_built=revision', [$listener, (int) (ceil($from / 3600) * 3600), (int) (floor(min($to, $now - 300) / 3600) * 3600)]);

	if (!is_array($ready)) {
		throw new RuntimeException('Cannot read hourly summary state');
	}
	$hours = array_fill_keys(array_column($ready, 'hour'), true);

	for ($cursor = $from; $cursor < $to; $cursor = $end) {
		$hour = (int) (floor($cursor / 3600) * 3600);
		$end  = min($to, $hour + 3600);

		if ($cursor == $hour && $end == $hour + 3600 && isset($hours[$hour])) {
			$source     = '(SELECT listener_id,hour minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,first_seen,last_seen,router FROM plugin_flowview_nbar_connections WHERE listener_id=' . (int) $listener . ' AND hour=' . $hour . ')';
			$segments[] = ['listener' => $listener, 'source' => $source, 'daily' => true];
		} else {
			$source     = '(SELECT listener_id,minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,minute first_seen,minute last_seen,router FROM plugin_flowview_nbar_data WHERE listener_id=' . (int) $listener . ' AND minute>=' . (int) $cursor . ' AND minute<' . (int) $end . ')';
			$segments[] = ['listener' => $listener, 'source' => $source, 'daily' => false];
		}
	}

	return $segments;
}
/**
 * Plan a capture range using current daily, hourly and raw revisions.
 *
 * @param array $listeners Selected capture identifiers.
 * @param int   $start     Inclusive range start in Unix seconds.
 * @param int   $end       Exclusive range end in Unix seconds.
 * @param int   $now       Current Unix time.
 *
 * @return array
 */
function fv_nbar_connection_plan(array $listeners, int $start, int $end, int $now): array {
	$segments = [];

	foreach ($listeners as $listener) {
		$listener = (int) $listener;

		if ($listener <= 0) {
			throw new InvalidArgumentException('Invalid capture');
		}
		$days = flowview_db_fetch_assoc_prepared('SELECT * FROM plugin_flowview_nbar_days WHERE listener_id=? AND day>=? AND covered_to<=? AND covered_to>day ORDER BY day', [$listener, $start, $end]);

		if (!is_array($days)) {
			throw new RuntimeException('Cannot read daily history');
		}
		$cursor = $start;

		foreach ($days as $day) {
			$from = (int) $day['day'];
			$to   = (int) $day['covered_to'];

			if ($day['timezone'] !== date_default_timezone_get() || $from < $cursor || $to > (int) (floor(($now - 300) / 3600) * 3600)) {
				continue;
			}

			if (fv_nbar_daily_signature($listener, $from, $to) !== (string) $day['revision']) {
				continue;
			}

			if ($from > $cursor) {
				$segments = array_merge($segments, fv_nbar_connection_edges($listener, $cursor, $from, $now));
			}
			$segments[] = ['listener' => $listener, 'source' => '(SELECT listener_id,hour minute,app_id,src,dst,sport,dport,protocol,bytes,packets,flows,first_seen,last_seen,router FROM plugin_flowview_nbar_daily_connections WHERE listener_id=' . $listener . ' AND hour=' . $from . ')', 'daily' => true];
			$cursor     = $to;
		}

		if ($cursor < $end) {
			$segments = array_merge($segments, fv_nbar_connection_edges($listener, $cursor, $end, $now));
		}
	}

	return $segments;
}
/**
 * Read an exact connection page from a consistent history snapshot.
 *
 * @param array  $listeners Selected capture identifiers.
 * @param int    $start     Inclusive range start in Unix seconds.
 * @param int    $end       Exclusive range end in Unix seconds.
 * @param string $where     Validated filter predicate.
 * @param array  $args      Prepared statement values.
 * @param int    $page      One-based connection page.
 * @param int    $size      Bounded page size including the next-page sentinel.
 *
 * @return array
 */
function fv_nbar_connections_direct(array $listeners, int $start, int $end, string $where, array $args, int $page, int $size = 101): array {
	$listeners = array_values(array_unique(array_map('intval', $listeners)));

	if (!$listeners || cacti_sizeof($listeners) > 3) {
		throw new InvalidArgumentException('Choose one to three captures');
	}
	$page   = max(1, (int) $page);
	$size   = max(101, min(501, (int) $size));
	$offset = ($page - 1) * 100;
	$need   = $offset + $size;
	// Keep page retrieval bounded. Deep jumps use the exact SQL fallback below.
	$limit = max(256, min(4096, $need * 2));

	if (!flowview_db_execute('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || !flowview_db_execute('START TRANSACTION WITH CONSISTENT SNAPSHOT')) {
		throw new RuntimeException('Cannot open history snapshot');
	}

	try {
		$segments = fv_nbar_connection_plan($listeners, (int) $start, (int) $end, time());
		$nameRows = flowview_db_fetch_assoc_prepared('SELECT l.id listener_id,l.allowfrom,n.router,n.app_id,n.name,DENSE_RANK() OVER (ORDER BY n.name) name_id FROM plugin_flowview_devices l CROSS JOIN plugin_flowview_nbar_names n WHERE l.id IN (' . implode(',', $listeners) . ')', []);

		if (!is_array($nameRows)) {
			throw new RuntimeException('Cannot read application names');
		}
		$names = [];

		foreach ($nameRows as $row) {
			if ($row['app_id'] !== null) {
				$names[(int) $row['listener_id']][($row['router'] ?? '') . ':' . $row['app_id']] = $row;

				if (($row['router'] ?? '') === ($row['allowfrom'] ?? '')) {
					$names[(int) $row['listener_id']][':' . $row['app_id']] = $row;
				}
			}
		}
		$fields   = 'd.router,d.app_id,HEX(d.src) src_hex,HEX(d.dst) dst_hex,d.sport,d.dport,d.protocol';
		$group    = 'd.router,d.app_id,d.src,d.dst,d.sport,d.dport,d.protocol';
		$counters = 'd.bytes bytes,d.packets packets,d.flows records,d.first_seen,d.last_seen';
		$sum      = 'SUM(d.bytes) bytes,SUM(d.packets) packets,SUM(d.flows) records,IF(COUNT(d.first_seen)=COUNT(*),MIN(d.first_seen),NULL) first_seen,IF(COUNT(d.last_seen)=COUNT(*),MAX(d.last_seen),NULL) last_seen';
		$order    = 'bytes DESC';

		for ($round = 0; $round < 7 && $need <= 32768; $round++,$limit *= 2) {
			$candidates = [];
			$bound      = '0';
			$exhausted  = true;

			foreach ($segments as $segment) {
				$sql  = 'SELECT ' . $fields . ',' . ($segment['daily'] ? $counters : $sum) . ' FROM ' . $segment['source'] . ' d WHERE ' . $where . ($segment['daily'] ? '' : ' GROUP BY ' . $group) . ' ORDER BY ' . $order . ' LIMIT ' . ($limit + 1);
				$rows = flowview_db_fetch_assoc_prepared($sql, $args);

				if (!is_array($rows)) {
					throw new RuntimeException('Cannot read connection page');
				}

				if (cacti_sizeof($rows) > $limit) {
					$exhausted = false;
					$last      = array_pop($rows);
					$bound     = fv_nbar_decimal_add($bound, $last['bytes']);
				}

				foreach ($rows as $row) {
					$candidates[fv_nbar_connection_key($row, $names, $segment['listener'])] = $row;
				}
			}

			if (!$candidates) {
				flowview_db_execute('COMMIT');

				return [];
			}

			// Sparse histories produce different candidates for each day. Bound
			// cross-day probes before they cost more than one grouped SQL query.
			if (cacti_sizeof($candidates) >= 1024) {
				break;
			}
			// Look up every candidate in every day, including small contributions
			// outside each day's top list. The final totals are exact, not sampled.
			$totals = [];

			foreach ($candidates as $key => $row) {
				$tuple       = json_decode($key, true);
				$row['name'] = null;

				foreach ($nameRows as $name) {
					if ((int) $name['name_id'] === $tuple[1]) {
						$row['name'] = $name['name'];

						break;
					}
				}
				$row['first_seen']    = null;
				$row['last_seen']     = null;
				$row['_seen_missing'] = false;
				$row['bytes']         = '0';
				$row['packets']       = '0';
				$row['records']       = '0';
				$totals[$key]         = $row;
			}
			$tuples = [];

			foreach ($candidates as $row) {
				$tuples[json_encode([$row['router'] ?? '', $row['app_id'], $row['src_hex'], $row['dst_hex'], $row['sport'], $row['dport'], $row['protocol']])] = $row;
			}

			foreach ($segments as $segment) {
				foreach (array_chunk(array_values($tuples), 400) as $chunk) {
					$lookup = [];
					$values = [];

					foreach ($chunk as $row) {
						$lookup[] = '(?,?,UNHEX(?),UNHEX(?),?,?,?)';
						array_push($values, $row['router'] ?? '', $row['app_id'], $row['src_hex'], $row['dst_hex'], $row['sport'], $row['dport'], $row['protocol']);
					}
					$sql  = 'SELECT ' . $fields . ',' . ($segment['daily'] ? $counters : $sum) . ' FROM ' . $segment['source'] . ' d WHERE ' . $where . ' AND (' . $group . ') IN (' . implode(',', $lookup) . ')' . ($segment['daily'] ? '' : ' GROUP BY ' . $group);
					$rows = flowview_db_fetch_assoc_prepared($sql, array_merge($args, $values));

					if (!is_array($rows)) {
						throw new RuntimeException('Cannot total connection page');
					}

					foreach ($rows as $row) {
						$key = fv_nbar_connection_key($row, $names, $segment['listener']);

						if (!isset($totals[$key])) {
							continue;
						}
						fv_nbar_seen_merge($totals[$key], $row);

						foreach (['bytes', 'packets', 'records'] as $counter) {
							$totals[$key][$counter] = fv_nbar_decimal_add($totals[$key][$counter], $row[$counter]);
						}
					}
				}
			}
			$ranked = [];

			foreach ($totals as $key => $row) {
				if ($row['_seen_missing']) {
					$row['first_seen'] = null;
					$row['last_seen']  = null;
				} unset($row['_seen_missing']);
				$row['_key'] = json_decode($key, true);
				$ranked[]    = $row;
			}
			usort($ranked, 'fv_nbar_connection_compare');

			// Any unseen connection has at most $bound bytes across all segments.
			// Strict inequality also protects deterministic ties at page boundaries.
			if ($exhausted || (cacti_sizeof($ranked) >= $need && fv_nbar_decimal_compare($ranked[$need - 1]['bytes'], $bound) > 0)) {
				$result = array_slice($ranked, $offset, $size);

				foreach ($result as &$row) {
					$row['src'] = inet_ntop((string) hex2bin($row['src_hex']));
					$row['dst'] = inet_ntop((string) hex2bin($row['dst_hex']));
					unset($row['_key'],$row['src_hex'],$row['dst_hex']);
				} unset($row);
				flowview_db_execute('COMMIT');

				return $result;
			}
		}
		// Exact fallback for exceptionally deep pages or a flat traffic distribution.
		$source = '(' . implode(' UNION ALL ', array_map(static function (array $segment): string {return 'SELECT * FROM ' . $segment['source'] . ' s'; }, $segments)) . ')';
		$result = flowview_db_fetch_assoc_prepared(str_replace(' LIMIT 101 OFFSET ', ' LIMIT ' . $size . ' OFFSET ', fv_nbar_connections_sql($source, $where, $page)), $args);

		if (!is_array($result)) {
			throw new RuntimeException('Cannot read connection page');
		}
		flowview_db_execute('COMMIT');

		return $result;
	} catch (Throwable $e) {
		flowview_db_execute('ROLLBACK');

		throw $e;
	}
}

// Cache requested pages, never an entire report. Authorization is in the key.
/**
 * Cache only requested connection pages within the current account.
 *
 * @param array  $listeners Selected capture identifiers.
 * @param int    $start     Inclusive range start in Unix seconds.
 * @param int    $end       Exclusive range end in Unix seconds.
 * @param string $where     Validated filter predicate.
 * @param array  $args      Prepared statement values.
 * @param int    $page      One-based connection page.
 *
 * @return array
 */
function fv_nbar_connections_cached(array $listeners, int $start, int $end, string $where, array $args, int $page): array {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		return fv_nbar_connections_direct($listeners, $start, $end, $where, $args, $page);
	}
	$block = max(1, (int) $page);
	$slice = 0;
	$key   = hash('sha256', serialize(['requested-page-seen-v3', $_SESSION['sess_user_id'] ?? 0, $listeners, $start, $end, $where, $args, $block]));
	$cache = $_SESSION['fv_nbar_connection_pages'] ?? [];

	foreach ($cache as $id => $entry) {
		if ($entry['until'] <= time()) {
			unset($cache[$id]);
		}
	}

	if (isset($cache[$key])) {
		$_SESSION['fv_nbar_connection_pages'] = $cache;

		return array_slice($cache[$key]['rows'], $slice, 101);
	}
	$rows = fv_nbar_connections_direct($listeners, $start, $end, $where, $args, $block, 101);

	if (strlen(serialize($rows)) <= 524288) {
		while (cacti_sizeof($cache) >= 3) {
			array_shift($cache);
		} $cache[$key] = ['until' => time() + ($end < time() - 300 ? 120 : 30), 'rows' => $rows];
	}
	$_SESSION['fv_nbar_connection_pages'] = $cache;

	return array_slice($rows, $slice, 101);
}
