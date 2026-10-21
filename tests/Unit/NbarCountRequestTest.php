<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once(dirname(__DIR__) . '/helpers/request.php');

it('handles terminating count requests and preserves bounded account cache state', function ($scenario, $status) {
	$directory = sys_get_temp_dir() . '/flowview-count-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);

	try {
		[$stdout, $result] = flowview_test_request(dirname(__DIR__) . '/fixtures/nbar-count-request.php', $scenario, $directory);
		$payload           = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
		expect($payload['status'])->toBe($status);
		$sql   = implode(' ', array_column($result['calls'], 'sql'));
		$entry = $result['session']['fv_nbar_counts'][str_repeat('a', 64)] ?? [];

		if (in_array($scenario, ['scoped', 'scoped-listing-failed'], true)) {
			expect($sql)->toContain('GET_LOCK', 'RELEASE_LOCK');
			$counts = array_values(array_filter($result['calls'], fn ($c) => $c['fn'] === 'db_fetch_row_prepared'));

			if ($scenario === 'scoped') {
				// The SQL that runs is built from the criteria and the slices that exist now, not the saved text.
				expect($counts)->toHaveCount(1);
				expect($counts[0]['sql'])->toContain('max_statement_time=90', 'COUNT(DISTINCT', 'plugin_flowview_nbar_data_2027')->not->toContain('before_the_slice_existed');
				expect($entry['total'])->toBe(12345);
			} else {
				expect($counts)->toBe([]);
				expect($entry['failed_until'])->toBeGreaterThan(time());
				expect($entry)->not->toHaveKey('total');
			}
		} elseif (in_array($scenario, ['ready', 'failed', 'background-ready', 'background-failed'], true)) {
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
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
})->with([
	['malformed', 'expired'], ['missing', 'expired'], ['expired', 'expired'], ['retry', 'unavailable'],
	['cached', 'ready'], ['busy', 'busy'], ['ready', 'ready'], ['failed', 'unavailable'],
	['background-ready', 'busy'], ['background-failed', 'busy'], ['scoped', 'ready'], ['scoped-listing-failed', 'unavailable']
]);
