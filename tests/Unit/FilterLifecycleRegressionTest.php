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
	copy($plugin . '/flowview_upgrade.php', $this->fixtureRoot . '/plugins/flowview/flowview_upgrade.php');
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
	expect($exit)->toBe(0)->and($actions)->not->toContain('ddl');
	if ($ready) {
		expect($output)->toBe('')->and($actions)->toContain('page-ready')->not->toContain('background');
	} else {
		expect($result['status'])->toBe(503)->and($output)->toContain('schema upgrade is pending')
			->and($actions)->not->toContain('page-ready');
		$background = array_values(array_filter($result['calls'], fn ($c) => $c[0] === 'background'));
		expect($background)->toHaveCount(1)->and($background[0][1][1])->toContain('/plugins/flowview/flowview_upgrade.php');
	}
})->with(['flowview.php', 'flowview_filters.php', 'flowview_schedules.php'])->with([false, true]);
it('repairs same-version schemas only after background registration and reports repair failure', function ($mode, $code, $writes) {
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, $this->fixtureRoot . '/plugins/flowview/flowview_upgrade.php'], $mode);
	$result = json_decode(file_get_contents($this->fixtureRoot . '/result.json'), true);
	expect($result)->toBeArray($output);
	$actions = array_column($result['calls'], 0);
	expect($exit)->toBe($code)->and($actions[0])->toBe('register')
		->and(array_filter($actions, fn ($action) => $action === 'ddl'))->toHaveCount($writes);
})->with([['pending', 0, 1], ['failed', 1, 1], ['ready', 0, 0], ['busy', 1, 0]]);
it('retains a full failing snapshot while advancing to healthy rows on the next pass', function () {
	if (!extension_loaded('pdo_sqlite')) {
		$this->markTestSkipped('PDO SQLite is needed for the persisted queue regression');
	}
	[$exit, $output] = ($this->runFixture)([PHP_BINARY, dirname(__DIR__) . '/fixtures/filter-queue.php', dirname(__DIR__, 2), $this->fixtureRoot]);
	$result = json_decode($output, true);
	expect($exit)->toBe(0)->and($result['first'])->toBe(0)->and($result['second'])->toBe(2)->and($result['third'])->toBe(0)
		->and($result['attempts'])->toBe([1, 2, 3, 4, 1, 2])
		->and($result['afterFirst'])->toHaveCount(4)
		->and($result['remaining'])->toBe([['id' => 1, 'status' => 'pending'], ['id' => 2, 'status' => 'pending']]);
});
