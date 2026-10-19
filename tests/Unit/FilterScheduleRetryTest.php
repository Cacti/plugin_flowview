<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['__test_cacti_log'] = [];
});
it('resolves native queue IDs separately from schedule IDs and accepts legacy dispatch', function ($options, $expected) {
	expect(flowview_schedule_queue_id($options))->toBe($expected);
})->with([
	[['report-id' => 7, 'queue-id' => 51], 51], [['report-id' => 51], 51],
	[['report-id' => 7, 'queue-id' => false], 7], [['report-id' => 0], false],
	[['report-id' => 7, 'queue-id' => 'invalid'], false], [[], false],
]);
it('keeps a pending occurrence untouched before either runner claims it', function ($legacy) {
	if ($legacy) {
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview']);
	}
	expect($legacy ? reports_run(51) : flowview_reports_run(51))->toBeFalse();
	expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('db_execute_prepared', 'exec_with_timeout');
})->with([false, true]);
it('retains the original on deferred or failed children and only deletes successful occurrences', function ($status, $reset, $legacy, $startup, $warning = false) {
	global $config;
	$old = $config['base_path'];
	$root = sys_get_temp_dir() . '/flowview-shim-' . bin2hex(random_bytes(8));
	mkdir($root . '/lib', 0700, true);
	file_put_contents($root . '/lib/poller.php', '<?php // Core helpers are supplied by the bootstrap.');
	try {
		$config['base_path'] = $root;
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'name' => 'Fixture', 'source_id' => 7, 'run_command' => 'fixture-command', 'run_timeout' => $status === 1 ? 0 : 60]);
		plugin_test_queue_db_result('exec_with_timeout', $startup ? null : $status);
		plugin_test_queue_db_result('exec_with_timeout_result', ($startup || $warning) ? false : null);
		plugin_test_queue_db_result('db_execute_prepared', true);
		plugin_test_queue_db_result('db_execute_prepared', $reset);
		$success = $status === 0 && !$startup && $reset;
		expect($legacy ? reports_run(51) : flowview_reports_run(51))->toBe($success);
		$calls = $GLOBALS['__test_db_calls'];
		$sql = implode(' ', array_column($calls, 'sql'));
		if ($status !== 0 || $startup) {
			if (!$reset) {
				expect(implode(' ', $GLOBALS['__test_cacti_log']))->toContain('queue repair is required');
			}
			expect($sql)->not->toContain('DELETE FROM reports_queued');
			expect($sql)->toContain('UPDATE reports_queued SET status');
		} else {
			expect($sql)->toContain('DELETE FROM reports_queued');
			$writes = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'db_execute_prepared'));
			expect($writes)->toHaveCount(2)->and($writes[1]['sql'])->toStartWith('DELETE FROM reports_queued');
		}
		if (!$legacy) {
			$exec = array_values(array_filter($calls, fn ($c) => $c['fn'] === 'exec_with_timeout'))[0];
			expect($exec['params'][0])->toContain('--report-id=7 --queue-id=51');
			expect($exec['params'][1])->toBe($status === 1 ? 300 : 60);
		}
		expect($GLOBALS['__test_saved_rows'])->toBe([]);
	} finally {
		$config['base_path'] = $old;
		unlink($root . '/lib/poller.php');
		rmdir($root . '/lib');
		rmdir($root);
	}
})->with([[75, true, false, false], [75, false, false, false], [1, true, false, false], [0, true, false, true], [0, true, false, false], [75, true, true, false], [75, false, true, false], [0, true, true, false], [0, true, true, true], [0, true, false, false, true], [0, true, true, false, true]]);
it('rejects missing queue rows or failed claims without running a child', function ($missing) {
	global $config;
	plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
	plugin_test_queue_db_result('db_fetch_row_prepared', $missing ? [] : ['id' => 51, 'source' => 'flowview']);
	if (!$missing) {
		// A previously included harmless fixture path satisfies require_once.
		$old = $config['base_path'];
		$root = sys_get_temp_dir() . '/flowview-claim-' . bin2hex(random_bytes(8));
		mkdir($root . '/lib', 0700, true);
		file_put_contents($root . '/lib/poller.php', '<?php');
		$config['base_path'] = $root;
		plugin_test_queue_db_result('db_execute_prepared', false);
	}
	try {
		expect(flowview_reports_run(51))->toBeFalse();
		expect(array_column($GLOBALS['__test_db_calls'], 'fn'))->not->toContain('exec_with_timeout');
	} finally {
		if (!$missing) {
			$config['base_path'] = $old;
			unlink($root . '/lib/poller.php');
			rmdir($root . '/lib');
			rmdir($root);
		}
	}
})->with([true, false]);

it('runs the actual child entrypoint with native and legacy dispatch IDs', function ($native, $ready) {
	$root = sys_get_temp_dir() . '/flowview-entrypoint-' . bin2hex(random_bytes(8));
	mkdir($root . '/plugins/flowview/includes', 0700, true);
	mkdir($root . '/include');
	mkdir($root . '/lib');
	$plugin = dirname(__DIR__, 2);
	copy($plugin . '/run_schedule.php', $root . '/plugins/flowview/run_schedule.php');
	foreach (['database.php', 'functions.php'] as $file) {
		symlink($plugin . '/includes/' . $file, $root . '/plugins/flowview/includes/' . $file);
	}
	file_put_contents($root . '/plugins/flowview/setup.php', '<?php function flowview_connect() {}');
	foreach (['poller.php', 'time.php'] as $file) {
		file_put_contents($root . '/lib/' . $file, '<?php');
	}
	$bootstrap = var_export(dirname(__DIR__) . '/bootstrap-unit.php', true);
	$result = var_export($root . '/result.json', true);
	$core = var_export($root, true);
	$readyCode = $ready ? 'plugin_test_queue_db_result("db_fetch_row", ["Field"=>"enabled"]);plugin_test_queue_db_result("register_process_start", false);' : '';
	file_put_contents($root . '/include/cli_check.php', '<?php require ' . $bootstrap . '; $config["base_path"]=' . $core . '; ' . $readyCode . ' plugin_test_queue_db_result("db_fetch_row_prepared", ["id"=>51,"source"=>"flowview","source_id"=>7]); register_shutdown_function(function(){file_put_contents(' . $result . ',json_encode($GLOBALS["__test_db_calls"]));});');
	try {
		$command = [PHP_BINARY, $root . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
		if ($native) {
			$command[] = '--queue-id=51';
		}
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);
		expect($error)->toBe('')->and($output)->toBe('')->and($exit)->toBe(75);
		$calls = json_decode(file_get_contents($root . '/result.json'), true);
		expect($calls)->toHaveCount($ready ? 3 : 2)->and($calls[0]['params'])->toBe(['flowview', 51]);
		expect(array_column($calls, 'fn'))->not->toContain('db_execute_prepared');
		if ($ready) {
			global $config;
			$oldBase = $config['base_path'];
			$config['base_path'] = $root;
			try {
				plugin_test_reset();
				plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
				plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
				plugin_test_queue_db_result('exec_with_timeout', $exit);
				expect(flowview_reports_run(51))->toBeFalse();
				expect(implode(' ', array_column($GLOBALS['__test_db_calls'], 'sql')))->not->toContain('DELETE FROM reports_queued');
			} finally {
				$config['base_path'] = $oldBase;
			}
		}
	} finally {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($iterator as $file) {
			if ($file->isDir() && !$file->isLink()) {
				rmdir($file->getPathname());
			} else {
				unlink($file->getPathname());
			}
		}
		rmdir($root);
	}
})->with([[false, false], [true, false], [false, true], [true, true]]);
