<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.

require_once(dirname(__DIR__) . '/helpers/request.php');

it('renders report boundaries without leaking unsafe parameters', function ($scenario, $expected) {
	$directory = sys_get_temp_dir() . '/flowview-report-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);

	try {
		[$stdout, $result] = flowview_test_request(dirname(__DIR__) . '/fixtures/nbar-report-request.php', $scenario, $directory);

		if (str_starts_with($scenario, 'nav-redirect')) {
			expect($stdout)->toBe('');
			expect($result['footer'])->toBeFalse();
			parse_str(parse_url($result['redirect'], PHP_URL_QUERY), $redirect);
			expect($redirect['section'])->toBe($scenario === 'nav-redirect-fragment-flows' ? 'flows' : 'nbar');

			if (str_contains($scenario, 'fragment')) {
				expect($redirect['header'])->toBe('false');
			} else {
				expect($redirect)->not->toHaveKey('header');
			}

			if (in_array($scenario, ['nav-redirect-header', 'nav-redirect-fragment-header'], true)) {
				expect($redirect['headercontent'])->toBe('true');
			}
		} elseif ($scenario === 'csrf') {
			expect($stdout)->toBe('');
			expect($result['status'])->toBe(403);
		} else {
			expect($stdout)->toContain($expected);
			expect($stdout)->not->toContain('injected', 'not-an-ip', 'nested', '<scriptnonce');
			expect($result['footer'])->toBeTrue();
		}
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
})->with([
	['nav-redirect', ''], ['nav-redirect-header', ''], ['nav-redirect-fragment', ''], ['nav-redirect-fragment-header', ''], ['nav-redirect-fragment-flows', ''], ['nav-select', 'Cisco Apps'], ['combine', 'Combined routers'],
	['default', 'Last Half Hour'], ['invalid', 'Fixture router'], ['retention', 'outside the 7-day'],
	['fallback-span', 'Last Hour'], ['all-history', 'Last 7 Days'], ['clamped', 'Dates adjusted'],
	['legacy', 'Last Hour'], ['drilldown', 'Back to conversations'], ['conversations', 'Internal Host'],
	['invalid-mode', 'Internal Host'], ['no-combined', 'Fixture router'], ['unknown-context', 'Cisco Apps'], ['more-pages', 'Next'],
	['apps-failure', 'Unable to load applications'], ['conversation-failure', 'Unable to load conversations'], ['failure', 'Unable to load connections'], ['upgrade', 'history is being upgraded'], ['csrf', '']
]);
