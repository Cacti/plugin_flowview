<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

it('rejects direct requests without the Cacti bootstrap', function ($scenario, $expected) {
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
		array_push($command, dirname(__DIR__) . '/fixtures/nbar-direct-request.php', $scenario, $directory);
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
		expect($stdout)->toBe('');
		expect($result['status'])->toBe($expected);
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
	['database', false], ['common', false], ['connections', false], ['query', false],
	['daily', false], ['seen', false], ['summary', false], ['timespan', false],
	['preferences', 403], ['guide', 403], ['report', false], ['collector', false]
]);
