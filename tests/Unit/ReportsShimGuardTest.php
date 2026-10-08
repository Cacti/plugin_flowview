<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression guard for includes/functions.php. On Cacti 1.3 the report helpers
 * are native, so loading the plugin file must not redeclare them. CI checks out
 * Cacti 1.2.x (helpers absent) and the bootstrap has already required
 * functions.php, so this exercises the develop-only "natives present" path in a
 * clean child process where the three functions are defined up front.
 */

it('loads functions.php without redeclaring the native report helpers', function () {
	$functions = dirname(__DIR__, 2) . '/includes/functions.php';

	expect(is_readable($functions))->toBeTrue();

	$script = <<<'CHILD'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Simulate Cacti 1.3, where these are defined natively before the plugin loads.
function reports_run($id) { return true; }
function reports_queue($name, $request_type, $source, $source_id, $command, $notification) {}
function reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, &$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = [], $headers = []) {}

require $argv[1];

echo '<<<LOADED_OK>>>';
CHILD;

	$tmp = tempnam(sys_get_temp_dir(), 'pre13_');
	expect($tmp)->not->toBeFalse();

	try {
		file_put_contents($tmp, $script);

		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($functions);
		$output  = (string) shell_exec($command);

		expect($output)->toContain('<<<LOADED_OK>>>');
		expect($output)->not->toContain('redeclare');
	} finally {
		@unlink($tmp);
	}
});
