<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

require_once dirname(__DIR__) . '/helpers/request.php';

it('rejects direct requests without the Cacti bootstrap', function ($scenario, $expected) {
	$directory = sys_get_temp_dir() . '/flowview-report-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	try {
		[$stdout, $result] = flowview_test_request(dirname(__DIR__) . '/fixtures/nbar-direct-request.php', $scenario, $directory);
		expect($stdout)->toBe('');
		expect($result['status'])->toBe($expected);

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
