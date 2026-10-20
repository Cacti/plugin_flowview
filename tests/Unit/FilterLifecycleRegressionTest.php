<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
beforeEach(function () {
	$this->fixtureRoot = sys_get_temp_dir() . '/flowview-lifecycle-' . bin2hex(random_bytes(8));
	mkdir($this->fixtureRoot . '/plugins/flowview', 0700, true);
	mkdir($this->fixtureRoot . '/include');
	mkdir($this->fixtureRoot . '/lib');
	$plugin = dirname(__DIR__, 2);
	foreach (['setup.php', 'includes', 'flowview.php', 'flowview_filters.php', 'flowview_schedules.php'] as $file) {
		symlink($plugin . '/' . $file, $this->fixtureRoot . '/plugins/flowview/' . $file);
	}
	foreach (['flowview_upgrade.php', 'run_schedule.php'] as $file) {
		copy($plugin . '/' . $file, $this->fixtureRoot . '/plugins/flowview/' . $file);
	}
	file_put_contents($this->fixtureRoot . '/plugins/flowview/INFO', "[info]\nversion=fixture-version\n");
	foreach (['auth.php', 'cli_check.php'] as $file) {
		copy(dirname(__DIR__) . '/fixtures/filter-lifecycle.php', $this->fixtureRoot . '/include/' . $file);
	}
	foreach (['poller.php', 'time.php', 'timespan_settings.php'] as $file) {
		file_put_contents($this->fixtureRoot . '/lib/' . $file, '<?php');
	}
	$this->runFixture = function ($command, $mode = 'pending') {
		$env = getenv();
		$env['FLOWVIEW_FIXTURE_MODE'] = $mode;
		$env['FLOWVIEW_FIXTURE_RESULT'] = $this->fixtureRoot . '/result.json';
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->fixtureRoot . '/plugins/flowview', $env);
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]);
		$error = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$exit = proc_close($process);
		expect($error)->toBe('');
		return [$exit, $output];
	};
});
afterEach(function () {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->fixtureRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $file) {
		if ($file->isDir() && !$file->isLink()) {
			rmdir($file->getPathname());
		} else {
			unlink($file->getPathname());
		}
	}
	rmdir($this->fixtureRoot);
});
it('serves a retry response without DDL from every guarded web entrypoint', function ($page, $ready) {
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/' . $page], $ready ? 'ready' : 'pending');
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	$actions = array_column($result['calls'], 0);
	expect($exit)->toBe(0)->and($actions)->not->toContain('ddl')
		->and($result['repair_loaded'])->toBeFalse();
	if ($ready) {
		expect($output)->toBe('')->and($actions)->toContain('page-ready')->not->toContain('background');
	} else {
		expect($result['status'])->toBe(503)->and($output)->toContain('schema upgrade is pending')
			->and($actions)->not->toContain('page-ready');
		$background = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'background'));
		expect($background)->toHaveCount(1)->and($background[0][1][1])->toContain('/plugins/flowview/flowview_upgrade.php');
	}
})->with(['flowview.php', 'flowview_filters.php', 'flowview_schedules.php'])->with([false, true]);
it('repairs same-version schemas only after background registration and reports repair failure', function ($mode, $code, $writes, $force = false) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/flowview_upgrade.php'];
	if ($force) {
		$command[] = '--forcever=fixture-version';
	}
	[$exit, $output] = ($this->runFixture)($command, $mode);
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($result)->toBeArray($output);
	$actions = array_column($result['calls'], 0);
	expect($exit)->toBe($code)->and($result['repair_loaded'])->toBeTrue()
		->and($actions[0])->toBe('register')
		->and(array_filter($actions, fn ($action) => $action === 'ddl'))->toHaveCount($writes);
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($released)->toBe($mode === 'busy' ? [] : [['unregister', ['flowview', 'upgrade', 1, $result['pid']]]]);
})->with([['pending', 0, 1], ['failed', 1, 1], ['ready', 0, 0], ['busy', 1, 0], ['pending', 0, 1, true], ['busy', 0, 1, true]]);
it('retains a full failing snapshot while advancing to healthy rows on the next pass', function () {
	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('PDO SQLite is needed for the persisted queue regression');
	}
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/filter-queue.php', dirname(__DIR__, 2), $this->fixtureRoot]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['first'])->toBe(0)->and($result['second'])->toBe(2)->and($result['third'])->toBe(0)
		->and($result['attempts'])->toBe([1, 2, 3, 4, 1, 2, 6])
		->and($result['retryRace'])->toBeFalse()->and($result['raceStatus'])->toBe(['status' => 'running'])
		->and($result['lostClaim'])->toBeFalse()->and($result['claimedStatus'])->toBe(['status' => 'running'])
		->and($result['afterFirst'])->toHaveCount(4)
		->and($result['remaining'])->toBe([['id' => 1, 'status' => 'pending'], ['id' => 2, 'status' => 'pending']]);
});

it('preserves late schema failures through the real child and parent completion path', function ($native, $retry) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) {
		$command[] = '--queue-id=51';
	}
	[$exit, $output] = ($this->runFixture)($command, $retry ? 'late' : 'child-disabled');
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	$writes = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'write'));
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($output)->toBe('')->and($exit)->toBe($retry ? 75 : 0)
		->and($released)->toBe([['unregister', ['flowsched', 'child_7', 0, $result['pid']]]])
		->and($writes)->toHaveCount($retry ? 1 : 2);
	expect(end($writes)[2])->toBe([$retry ? 'retry' : 'complete', 51, 'flowview', 'running']);
	if (!$retry) {
		expect($writes[0][1])->toContain('UPDATE plugin_flowview_schedules');
	}
	global $config;
	$oldBase = $config['base_path'];
	$config['base_path'] = $this->fixtureRoot;
	try {
		plugin_test_reset();
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
		plugin_test_queue_db_result('exec_with_timeout', $exit);
		plugin_test_queue_db_result('report_outcome', ['status' => $exit === 0 ? 'complete' : 'retry']);
		expect(flowview_reports_run(51))->toBe(!$retry);
		$deletes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE FROM reports_queued')));
		expect($deletes)->toHaveCount($retry ? 0 : 1);
	} finally {
		$config['base_path'] = $oldBase;
	}
})->with([false, true])->with([false, true]);

it('returns retry from the real child when a queue, schedule or filter lookup fails', function ($mode, $native) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) {
		$command[] = '--queue-id=51';
	}
	[$exit] = ($this->runFixture)($command, $mode);
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($exit)->toBe(75);
	$actions = array_column($result['calls'], 0);
	$writes = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'write'));
	expect($writes)->toHaveCount(1);
	if ($writes) {
		expect($writes[0][2])->toBe(['retry', 51, 'flowview', 'running']);
	}
	if ($mode !== 'report-read-failure') {
		$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
		expect($released)->toBe([['unregister', ['flowsched', 'child_7', 0, $result['pid']]]]);
	}
})->with(['report-read-failure', 'schedule-read-failure', 'filter-read-failure'])->with([false, true]);

it('retains a timed out parallel report through the actual child and parent', function ($native) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) {
		$command[] = '--queue-id=51';
	}
	[$exit, $output] = ($this->runFixture)($command, 'parallel-timeout');
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($exit)->toBe(75)->and($output)->toBe('');
	$writes = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'write'));
	expect(array_filter($writes, fn ($c) => str_contains($c[1], 'UPDATE plugin_flowview_schedules')))->toBe([]);
	$cleanup = array_values(array_filter($writes, fn ($c) => str_starts_with($c[1], 'DELETE FROM parallel_database_query_shard')));
	expect(array_column($cleanup, 2))->toBe([[71]]);
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($released)->toBe([['unregister', ['flowsched', 'child_7', 0, $result['pid']]]]);
	global $config;
	$oldBase = $config['base_path'];
	$config['base_path'] = $this->fixtureRoot;
	try {
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 60]);
		plugin_test_queue_db_result('exec_with_timeout', $exit);
		plugin_test_queue_db_result('report_outcome', ['status' => $exit === 0 ? 'complete' : 'retry']);
		expect(flowview_reports_run(51))->toBeFalse();
		$deletes = array_filter($GLOBALS['__test_db_calls'], fn ($c) => str_starts_with($c['sql'], 'DELETE FROM reports_queued'));
		expect($deletes)->toBe([]);
	} finally {
		$config['base_path'] = $oldBase;
	}
})->with([false, true]);

it('recreates shards and launches the same query after a parallel timeout', function () {
	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('PDO SQLite is needed for the persisted parallel retry regression');
	}
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/parallel-retry.php', dirname(__DIR__, 2), $this->fixtureRoot]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['timeout'])->toBeFalse()
		->and($result['remaining'])->toBe(0)->and($result['remainingShards'])->toBe(0)
		->and($result['retry'])->not->toBe($result['first'])->and($result['retryShards'])->toBe(1)
		->and($result['result'])->toBe([])->and($result['launches'])->toBe([$result['first'], $result['retry']])
		->and($result['cached'])->toBe($result['retry'])->and($result['cachedResult'])->toBe([]);
});

it('completes an exit-zero report with stderr using Cactis actual process helper', function ($runner) {
	global $config;
	file_put_contents($this->fixtureRoot . '/warning.php', '<?php fwrite(STDERR, "nonfatal fixture warning\n"); echo "delivered\n"; exit(0);');
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/report-stderr.php', $config['base_path'], dirname(__DIR__, 2), $this->fixtureRoot, $runner]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['helperResult'])->toBeFalse()->and($result['status'])->toBe(0)
		->and($result['success'])->toBeTrue()->and($result['writes'])->toHaveCount(2)
		->and($result['writes'][1]['sql'])->toStartWith('DELETE FROM reports_queued');
})->with(['native', 'legacy']);

it('reconciles a timeout with stderr and late completion without resending on either dispatcher', function ($runner) {
	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('PDO SQLite is needed for the persisted timeout regression');
	}
	global $config;
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/report-timeout.php', $config['base_path'], dirname(__DIR__, 2), $this->fixtureRoot, $runner]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['remaining'])->toBe([])->and($result['deliveries'])->toHaveCount(1);
	$resets = array_filter($result['writes'], fn ($c) => ($c[1][0] ?? '') === 'pending');
	expect($resets)->toBe([]);
	$claims = array_filter($result['writes'], fn ($c) => str_contains($c[0], 'start_time=?'));
	expect($claims)->toHaveCount(1);
})->with(['native', 'legacy']);

it('holds a delivered occurrence when schedule timestamp persistence fails', function ($native) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) { $command[] = '--queue-id=51'; }
	[$exit] = ($this->runFixture)($command, 'timestamp-failure');
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($exit)->toBe(76);
	$outcomes = array_filter($result['calls'], fn ($c) => $c[0] === 'write' && str_contains($c[1], 'UPDATE reports_queued'));
	expect($outcomes)->toBe([]);
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($released)->toBe([['unregister', ['flowsched', 'child_7', 0, $result['pid']]]]);
})->with([false, true]);

it('releases only an acquired scheduler master registration at shutdown', function ($mode, $force) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--scheduled'];
	if ($force) { $command[] = '--force'; }
	[$exit] = ($this->runFixture)($command, $mode);
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($exit)->toBe(0)->and($released)->toBe($mode === 'master-busy' || $force ? [] : [['unregister', ['flowsched', 'master', 0, $result['pid']]]]);
})->with([['ready', false], ['master-busy', false], ['ready', true]]);

it('retains a child connection failure even when the actual entrypoint exits zero', function ($native) {
	file_put_contents($this->fixtureRoot . '/connection.php', '<?php $flowview_use_cacti_db=false; $flowviewdb_hostname="fixture"; $flowviewdb_username="fixture"; $flowviewdb_password=""; $flowviewdb_default="fixture"; $flowviewdb_type="mysql";');
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) { $command[] = '--queue-id=51'; }
	[$exit] = ($this->runFixture)($command, 'connection-failure');
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($exit)->toBe(0)->and(array_column($result['calls'], 0))->toContain('connect')->not->toContain('write', 'register');
	global $config;
	$oldBase = $config['base_path'];
	$config['base_path'] = $this->fixtureRoot;
	try {
		plugin_test_reset();
		plugin_test_queue_db_result('db_fetch_row', ['Field' => 'enabled']);
		plugin_test_queue_db_result('db_fetch_row_prepared', ['id' => 51, 'source' => 'flowview', 'source_id' => 7, 'run_command' => 'fixture', 'run_timeout' => 1]);
		plugin_test_queue_db_result('exec_with_timeout', $exit);
		expect($native ? flowview_reports_run(51) : reports_run(51))->toBeFalse();
		$writes = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared'));
		expect($writes)->toHaveCount(1)->and($writes[0]['params'][0])->toBe('running');
	} finally { $config['base_path'] = $oldBase; }
})->with([false, true]);

it('runs pending work beyond a full snapshot of undeletable completed occurrences', function () {
	if (!extension_loaded('pdo_sqlite')) { $this->markTestSkipped('PDO SQLite is needed for the bounded cleanup regression'); }
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/completion-budget.php', dirname(__DIR__, 2), $this->fixtureRoot]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['done'])->toBe(1)->and($result['attempts'])->toBe([101])
		->and($result['deletes'])->toHaveCount(201)->and($result['remaining'])->toHaveCount(100)
		->and(array_unique(array_column($result['remaining'], 'status')))->toBe(['complete']);
});


it('retries only a pre-delivery notification lookup failure in the actual child', function ($native, $mode) {
	$command = [PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/run_schedule.php', '--report-id=' . ($native ? 7 : 51)];
	if ($native) { $command[] = '--queue-id=51'; }
	// The post-delivery fixture deliberately throws; suppress its expected fatal diagnostic.
	if ($mode === 'notification-after-delivery') {
		array_splice($command, 1, 0, ['-d', 'display_errors=0', '-d', 'log_errors=0']);
	}
	[$exit] = ($this->runFixture)($command, $mode);
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	$writes = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'write'));
	$actions = array_column($result['calls'], 0);
	$queueReads = array_filter($result['calls'], fn ($c) => $c[0] === 'row' && str_contains($c[1], 'reports_queued'));
	expect($queueReads)->toHaveCount(2);
	if ($mode === 'notification-after-delivery') {
		expect($exit)->toBe(255, json_encode($result))->and($actions)->toContain('delivery')->and($writes)->toBe([]);
	} else {
		expect($exit)->toBe(75)->and($actions)->not->toContain('delivery', 'save-log')
			->and($writes)->toHaveCount(1)->and($writes[0][2])->toBe(['retry', 51, 'flowview', 'running']);
	}
	$released = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'unregister'));
	expect($released)->toBe([['unregister', ['flowsched', 'child_7', 0, $result['pid']]]]);
})->with([false, true])->with(['notification-read-failure', 'notification-missing', 'notification-after-delivery']);
