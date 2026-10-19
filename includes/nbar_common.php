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

require_once(__DIR__ . '/nbar_seen.php');
require_once(__DIR__ . '/nbar_summary.php');

// Shared NBAR report and retention helpers.

/**
 * Escape report text and quoted HTML attributes.
 *
 * @param mixed $s Input text or network-order bytes.
 *
 * @return string
 */
function fv_nbar_h(mixed $s): string {
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Read the validated history-retention setting with a short cache.
 *
 * @return int
 */
function fv_nbar_retention_days(): int {
	static $days = 7,$checked = 0;

	if (time() - $checked >= 60) {
		$value   = (int) read_config_option('flowview_nbar_retention_days', true);
		$days    = in_array($value, [1, 3, 7, 14, 30], true) ? $value : 7;
		$checked = time();
	}

	return $days;
}
/**
 * Expire raw records and summaries in bounded database batches.
 *
 * @return void
 */
function fv_nbar_cleanup(int|float $budget = 10): void {
	$cutoff  = (int) (floor((time() - fv_nbar_retention_days() * 86400) / 3600) * 3600);
	$queries = [];

	foreach (['apps', 'pairs', 'connections', 'hours'] as $table) {
		$queries[] = ["DELETE FROM plugin_flowview_nbar_$table WHERE hour<$cutoff LIMIT 50000", 50000];
	}
	$queries[] = ['DELETE FROM plugin_flowview_nbar_data WHERE minute < ' . (time() - fv_nbar_retention_days() * 86400) . ' LIMIT 50000', 50000];
	$queries   = array_merge($queries, fv_nbar_daily_cleanup_queries());
	fv_nbar_cleanup_batches($queries, microtime(true) + max(0, (float) $budget));
}

/** Drain every retention level fairly until empty or the shared deadline expires. */
function fv_nbar_cleanup_batches(array $queries, float $deadline): void {
	while ($queries && microtime(true) < $deadline) {
		foreach ($queries as $key => [$sql, $limit]) {
			if (microtime(true) >= $deadline) {
				return;
			}

			if (flowview_db_execute($sql) === false) {
				unset($queries[$key]);

				continue;
			}

			if ((int) flowview_db_affected_rows() < $limit) {
				unset($queries[$key]);
			}
		}
	}
}

/** Establish a verified DML deadline; MySQL SELECT timeouts do not bound INSERT SELECT. */
function fv_nbar_worker_timeout(int $seconds): ?float {
	$old = flowview_db_fetch_cell('SELECT @@session.max_statement_time', '', false);

	if (!is_numeric($old) || (float) $old < 0) {
		return null;
	}

	if (!flowview_db_execute('SET SESSION max_statement_time=' . $seconds, false)) {
		return null;
	}

	return (float) $old;
}

/** Launch maintenance without holding the Cacti poller on aggregate queries. */
function fv_nbar_launch_maintenance(): bool {
	global $config;
	$php = read_config_option('path_php_binary');

	if (!is_string($php) || $php === '') {
		cacti_log('NBAR maintenance requires a configured PHP binary.', false, 'FLOWVIEW');

		return false;
	}
	exec_background($php, '-q ' . escapeshellarg($config['base_path'] . '/plugins/flowview/cli/flowview_upgrade.php') . ' --nbar-worker=supervise');

	return true;
}

/** Run an isolated maintenance child with a hard process deadline. */
function fv_nbar_supervise_maintenance(): void {
	global $config;

	if (!register_process_start('flowview', 'nbar_maintenance', 0, 120)) {
		return;
	}

	try {
		// Invoke PHP directly; older Cacti timeout helpers accept strings only.
		$command = [(string) read_config_option('path_php_binary'), '-q', $config['base_path'] . '/plugins/flowview/cli/flowview_upgrade.php', '--nbar-worker=build'];
		$status  = fv_nbar_run_child($command, 90);

		if ($status !== 0) {
			cacti_log('NBAR maintenance child failed or exceeded its 90-second deadline.', false, 'FLOWVIEW');
		}
	} finally {
		unregister_process('flowview', 'nbar_maintenance', 0);
	}
}

/**
 * Supervise a direct PHP child independently of Cacti's versioned command API.
 *
 * @param list<string> $command Executable followed by its arguments.
 * @param int          $seconds Hard process deadline in seconds.
 *
 * @return int
 */
function fv_nbar_run_child(array $command, int $seconds): int {
	$pipes      = [];
	$process    = false;
	$executable = $command[0] ?? '';

	if (is_string($executable) && $executable !== '' && (strpbrk($executable, '/\\') === false || is_executable($executable))) {
		try {
			$process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
		} catch (Throwable $error) {
			$process = false;
		}
	}

	if (!is_resource($process)) {
		return 125;
	}

	try {
		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$deadline = hrtime(true) + max(1, $seconds) * 1000000000;

		while (true) {
			// Drain both pipes in bounded chunks; child output cannot fill memory.
			stream_get_contents($pipes[1], 8192);
			stream_get_contents($pipes[2], 8192);
			$state = proc_get_status($process);

			if (!$state['running']) {
				return (int) $state['exitcode'];
			}

			if (hrtime(true) >= $deadline) {
				proc_terminate($process, 9);

				return 124;
			}
			usleep(10000);
		}
	} finally {
		foreach ($pipes as $pipe) {
			if (is_resource($pipe)) {
				fclose($pipe);
			}
		}
		proc_close($process);
	}
}
