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

require_once __DIR__ . "/nbar_common.php";
// Decode application-bearing NetFlow v9 records from exporter templates.
/** Bounded LRU template state, shared across source IDs but limited per exporter. */
class FlowviewNbarTemplateCache
{
	private array $entries = [];
	private int $pruned = 0;

	public function prune(int $now): void {
		if ($now - $this->pruned < 60) {
			return;
		}
		foreach ($this->entries as $key => $entry) {
			if ($entry['used'] <= $now - 1800) {
				unset($this->entries[$key]);
			}
		}
		$this->pruned = $now;
	}

	public function remove(string $router, string $source, int $id): void {
		unset($this->entries[$router . ':' . $source . ':' . $id]);
	}

	public function store(string $router, string $source, int $id, array $template, int $now): bool {
		$this->prune($now);
		$this->remove($router, $source, $id);
		$fields = count($template[0]);
		if ($fields > 2048) {
			return false;
		}
		while (true) {
			$usage = $this->usage($router);
			$local = $usage['exporter_templates'] >= 64 || $usage['exporter_fields'] + $fields > 2048;
			if (!$local && $usage['templates'] < 256 && $usage['fields'] + $fields <= 8192) {
				break;
			}
			foreach ($this->entries as $key => $entry) {
				if (!$local || $entry['router'] === $router) {
					unset($this->entries[$key]);
					break;
				}
			}
		}
		$this->entries[$router . ':' . $source . ':' . $id] = ['router' => $router, 'used' => $now, 'template' => $template];
		return true;
	}

	public function get(string $router, string $source, int $id, int $now): ?array {
		$key = $router . ':' . $source . ':' . $id;
		if (!isset($this->entries[$key])) {
			return null;
		}
		$entry = $this->entries[$key];
		unset($this->entries[$key]);
		if ($entry['used'] <= $now - 1800) {
			return null;
		}
		$entry['used'] = $now;
		$this->entries[$key] = $entry;
		return $entry['template'];
	}

	public function usage(string $router): array {
		$usage = ['templates' => count($this->entries), 'fields' => 0, 'exporter_templates' => 0, 'exporter_fields' => 0];
		foreach ($this->entries as $entry) {
			$fields = count($entry['template'][0]);
			$usage['fields'] += $fields;
			if ($entry['router'] === $router) {
				$usage['exporter_templates']++;
				$usage['exporter_fields'] += $fields;
			}
		}
		return $usage;
	}
}

/**
 * Decode network-order counters without rounding unsigned 64-bit values.
 *
 * @param string $s Input text or network-order bytes.
 *
 * @return int|string
 */
function fv_nbar_uint($s) {
	if (strlen($s) > 8) {
		throw new InvalidArgumentException('NBAR numeric fields must fit an unsigned 64-bit value');
	}
	$n = 0;
	foreach (unpack('C*', $s) as $v) {
		if (is_int($n) && $n <= intdiv(PHP_INT_MAX - $v, 256)) {
			$n = $n * 256 + $v;
			continue;
		}
		$digits = (string) $n;
		$carry = $v;
		$out = '';
		for ($i = strlen($digits) - 1; $i >= 0; $i--) {
			$carry += ((int) $digits[$i]) * 256;
			$out = ($carry % 10) . $out;
			$carry = intdiv($carry, 10);
		}
		$n = ($carry > 0 ? (string) $carry : '') . $out;
	}
	return $n;
}
/**
 * Decode template-defined NetFlow v9 application records and names.
 *
 * @param string $p NetFlow v9 packet bytes.
 * @param string $router Exporter source IP address.
 *
 * @return void
 */
function fv_nbar_packet($p, $router) {
	global $listener_id;
	static $templates = null;
	if (strlen($p) < 20 || fv_nbar_uint(substr($p, 0, 2)) !== 9) {
		return;
	}
	$templates ??= new FlowviewNbarTemplateCache();
	$now = time();
	$templates->prune($now);
	$source = bin2hex(substr($p, 16, 4));
	$unix = fv_nbar_uint(substr($p, 8, 4));
	$uptime = fv_nbar_uint(substr($p, 4, 4));
	$rows = [];
	$args = [];
	$hours = [];
	for ($o = 20;$o + 4 <= strlen($p);) {
		$id = fv_nbar_uint(substr($p, $o, 2));
		$len = fv_nbar_uint(substr($p, $o + 2, 2));
		if ($len < 4 || $o + $len > strlen($p)) {
			break;
		}
		$end = $o + $len;
		$q = $o + 4;
		if ($id === 0 || $id === 1) {
			while ($q + ($id === 0 ? 4 : 6) <= $end) {
				$tid = fv_nbar_uint(substr($p, $q, 2));
				if ($tid < 256) {
					break;
				}
				if ($id === 0) {
					$count = fv_nbar_uint(substr($p, $q + 2, 2));
					$size = $count * 4;
					$q += 4;
				} else {
					$size = fv_nbar_uint(substr($p, $q + 2, 2)) + fv_nbar_uint(substr($p, $q + 4, 2));
					$q += 6;
				}
				if ($size < 4 || $size % 4 || $q + $size > $end) {
					$templates->remove($router, $source, $tid);
					break;
				}
				$fields = [];
				$total = 0;
				$valid = true;
				for ($z = 0;$z < $size;$z += 4) {
					$f = fv_nbar_uint(substr($p, $q + $z, 2));
					$l = fv_nbar_uint(substr($p, $q + $z + 2, 2));
					if ($l === 0 || ($id === 0 && in_array($f, [1, 2, 4, 7, 11, 21], true) && $l > 8)) {
						$valid = false;
						break;
					}
					$fields[] = [$f, $l];
					$total += $l;
				}
				if ($valid && $total > 0 && $total <= 65535) {
					$templates->store($router, $source, $tid, [$fields, $total, $id === 1], $now);
				} else {
					$templates->remove($router, $source, $tid);
				}
				$q += $size;
			}
		} elseif ($id >= 256 && ($template = $templates->get($router, $source, $id, $now)) !== null) {
			list($fields, $total, $options) = $template;
			while ($q + $total <= $end) {
				$data = [];
				foreach ($fields as $f) {
					$data[$f[0]] = substr($p, $q, $f[1]);
					$q += $f[1];
				}
				if (!isset($data[95])) {
					continue;
				}
				$aid = bin2hex($data[95]);
				if ($options) {
					$name = trim($data[96] ?? '', chr(0) . ' ');
					if ($name !== '' && strlen($name) <= 255 && preg_match('/^[a-zA-Z0-9_.: -]+$/D', $name)) {
						flowview_db_execute_prepared('INSERT INTO plugin_flowview_nbar_names VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),updated=VALUES(updated)', [$router, $aid, $name, time()]);
					}
					continue;
				}
				$src = $data[27] ?? $data[8] ?? '';
				$dst = $data[28] ?? $data[12] ?? '';
				if (!in_array(strlen($src), [4, 16], true) || !in_array(strlen($dst), [4, 16], true) || !isset($data[1])) {
					continue;
				}
				$ts = $unix;
				if (isset($data[21])) {
					$age = ($uptime - fv_nbar_uint($data[21]) + 4294967296) % 4294967296;
					$ts = $unix - (int) floor($age / 1000);
				}
				if ($ts < time() - fv_nbar_retention_days() * 86400 || $ts > time() + 300) {
					continue;
				}
				$hours[(int) (floor($ts / 3600) * 3600)] = true;
				$rows[] = '(?,?,?,UNHEX(?),UNHEX(?),?,?,?,?,?,1,?)';
				array_push(
					$args,
					(int) $listener_id,
					(int) (floor($ts / 60) * 60),
					$aid,
					bin2hex($src),
					bin2hex($dst),
					fv_nbar_uint($data[7] ?? chr(0)),
					fv_nbar_uint($data[11] ?? chr(0)),
					fv_nbar_uint($data[4] ?? chr(0)),
					fv_nbar_uint($data[1]),
					fv_nbar_uint($data[2] ?? chr(0)),
					$router
				);
			}
		}
		$o = $end;
	}
	if ($rows && !fv_nbar_summary_store('INSERT INTO plugin_flowview_nbar_data VALUES ' . implode(',', $rows) . ' ON DUPLICATE KEY UPDATE bytes=bytes+VALUES(bytes),packets=packets+VALUES(packets),flows=flows+1', $listener_id, $hours, $args)) {
		cacti_log('WARNING: NBAR record transaction failed', false, 'FLOWVIEW');
	}
}
