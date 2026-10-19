<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

it('loads real DNS constructors without deprecations and preserves nullable options', function () {
	$directory = sys_get_temp_dir() . '/flowview-dns-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	try {
		$command = [PHP_BINARY, '-d', 'xdebug.mode=coverage'];
		$extension = getenv('FLOWVIEW_TEST_COVERAGE_ZEND_EXTENSION');
		if ($extension !== false && $extension !== '') {
			array_push($command, '-d', 'zend_extension=' . $extension);
		}
		array_push($command, dirname(__DIR__) . '/fixtures/dns-nullable-constructors.php', $directory . '/result.json');
		$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
		expect(is_resource($process))->toBeTrue();
		fclose($pipes[0]);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		expect(proc_close($process))->toBe(0, $stderr);
		expect($stdout)->toBe('')->and($stderr)->toBe('');
		$result = json_decode(file_get_contents($directory . '/result.json'), true, 512, JSON_THROW_ON_ERROR);
		expect($result['objects'])->toBe(12)->and($result['timeout'])->toBe(2);
		expect($result['nullable'])->not->toBeEmpty();
		foreach ($result['nullable'] as $name => $allowsNull) {
			expect($allowsNull)->toBeTrue($name);
		}
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
});
