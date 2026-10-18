<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

it('handles terminating count requests and preserves bounded account cache state', function ($scenario, $status) {
	$directory = sys_get_temp_dir() . '/flowview-count-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	try {
		$command = [PHP_BINARY, '-d', 'xdebug.mode=coverage'];
		$extension = getenv('FLOWVIEW_TEST_COVERAGE_EXTENSION');
		if ($extension !== false && $extension !== '') {
			array_push($command, '-d', 'extension=' . $extension, '-d', 'pcov.directory=' . dirname(__DIR__, 2));
		}
		$zendExtension = getenv('FLOWVIEW_TEST_COVERAGE_ZEND_EXTENSION');
		if ($zendExtension !== false && $zendExtension !== '') {
			array_push($command, '-d', 'zend_extension=' . $zendExtension);
		}
		array_push($command, dirname(__DIR__) . '/fixtures/nbar-count-request.php', $scenario, $directory);
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		expect(is_resource($process))->toBeTrue();
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		expect(proc_close($process))->toBe(0);
		expect($stderr)->toBe('');
		$payload = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
		$result = json_decode(file_get_contents($directory . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
		expect($payload['status'])->toBe($status);
		$sql = implode(' ', array_column($result['calls'], 'sql'));
		$entry = $result['session']['fv_nbar_counts'][str_repeat('a', 64)] ?? [];
		if (in_array($scenario, ['ready', 'failed', 'background-ready', 'background-failed'], true)) {
			expect($sql)->toContain('GET_LOCK', 'max_statement_time=90', 'RELEASE_LOCK');
			$counts = array_values(array_filter($result['calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));
			expect($counts[0]['params'])->toBe([3]);
			if (str_contains($scenario, 'failed')) {
				expect($entry['failed_until'])->toBeGreaterThan(time());
				expect($entry)->not->toHaveKey('total');
			} else {
				expect($entry['total'])->toBe(12345);
				expect($entry['until'])->toBeGreaterThan(time() + 590);
			}
		} else {
			expect($sql)->not->toContain('max_statement_time=90');
		}
		if ($status === 'ready') {
			expect($payload)->toMatchArray(['pages' => 124, 'total' => 12345]);
		}
		// Child requests terminate with exit. Merge their real line hits so
		// coverage includes endpoint paths under both PCOV and CI's Xdebug.
		$coverage = \PHPUnit\Runner\CodeCoverage::instance();
		if ($result['coverage'] && $coverage->isActive()) {
			$coverage->codeCoverage()->append(\SebastianBergmann\CodeCoverage\Data\RawCodeCoverageData::fromXdebugWithoutPathCoverage($result['coverage']));
		}
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
})->with([
	['malformed', 'expired'], ['missing', 'expired'], ['expired', 'expired'], ['retry', 'unavailable'],
	['cached', 'ready'], ['busy', 'busy'], ['ready', 'ready'], ['failed', 'unavailable'],
	['background-ready', 'busy'], ['background-failed', 'busy']
]);
