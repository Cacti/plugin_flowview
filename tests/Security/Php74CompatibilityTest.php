<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.0+ syntax.
 * Cacti 1.2.x plugins must remain compatible with PHP 7.4.
 */

	// Discovered recursively so new production PHP files are covered automatically.
	$pluginRoot = realpath(__DIR__ . '/../..');
	$files      = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$relativeFile = ltrim(str_replace($pluginRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
		$relativeFile = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFile);

		if (strpos($relativeFile, 'tests/') === 0) {
			continue;
		}

		$files[] = $relativeFile;
	}

	sort($files);

	it('does not use str_contains (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_contains\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_contains() which requires PHP 8.0"
			);
		}
	});

	it('does not use str_starts_with (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_starts_with\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_starts_with() which requires PHP 8.0"
			);
		}
	});

	it('does not use str_ends_with (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\bstr_ends_with\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses str_ends_with() which requires PHP 8.0"
			);
		}
	});

	it('does not use nullsafe operator (PHP 8.0)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);

			if ($path === false) {
			throw new RuntimeException("Unable to resolve required plugin source");
		}

			$contents = file_get_contents($path);

			if ($contents === false) {
			throw new RuntimeException("Unable to read required plugin source");
		}

			expect(preg_match('/\?->/', $contents))->toBe(0,
				"{$relativeFile} uses nullsafe operator which requires PHP 8.0"
			);
		}
	});
