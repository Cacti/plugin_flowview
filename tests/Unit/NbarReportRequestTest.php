<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

it('renders report boundaries without leaking unsafe parameters', function ($scenario, $expected) {
	$directory = sys_get_temp_dir() . '/flowview-report-' . bin2hex(random_bytes(8));
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
		array_push($command, dirname(__DIR__) . '/fixtures/nbar-report-request.php', $scenario, $directory);
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		expect(is_resource($process))->toBeTrue();
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		expect(proc_close($process))->toBe(0);
		expect($stderr)->toBe('');
		$result = json_decode(file_get_contents($directory . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
		if (str_starts_with($scenario, 'nav-redirect')) {
			expect($stdout)->toBe('');
			expect($result['footer'])->toBeFalse();
		} elseif ($scenario === 'csrf') {
			expect($stdout)->toBe('');
			expect($result['status'])->toBe(403);
		} else {
			expect($stdout)->toContain($expected);
			expect($stdout)->not->toContain('injected', 'not-an-ip', 'nested', '<scriptnonce');
			expect($result['footer'])->toBeTrue();
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
	['nav-redirect', ''], ['nav-redirect-header', ''], ['nav-select', 'Cisco Apps'], ['combine', 'Combined routers'],
	['default', 'Last Half Hour'], ['invalid', 'Fixture router'], ['retention', 'outside the 7-day'],
	['fallback-span', 'Last Hour'], ['all-history', 'Last 7 Days'], ['clamped', 'Dates adjusted'],
	['legacy', 'Last Hour'], ['drilldown', 'Back to conversations'], ['conversations', 'Internal Host'],
	['invalid-mode', 'Internal Host'], ['no-combined', 'Fixture router'], ['unknown-context', 'Cisco Apps'], ['more-pages', 'Next'],
	['failure', 'Unable to load connections'], ['upgrade', 'history is being upgraded'], ['csrf', '']
]);
