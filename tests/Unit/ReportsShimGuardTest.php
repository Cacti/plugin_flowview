<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression guard for the pre-1.3 report-shim gate in includes/functions.php.
 *
 * reports_run()/reports_queue()/reports_log_and_notify() became native in Cacti
 * 1.3 (declared unguarded in core lib/reports.php). functions.php is included
 * during plugin init BEFORE core loads lib/reports.php, so a function_exists()
 * probe would miss the now-native helpers, load the shims, and core would then
 * fatally redeclare them. The gate therefore decides from the Cacti version
 * (cacti_version_compare(CACTI_VERSION, '1.3', '<')) and only falls back to
 * function_exists() when the version helpers are unavailable.
 *
 * Each case loads the real functions.php in a clean child process with a
 * different preamble, then reports whether the shim was loaded (and from where)
 * via markers so the three branches of the gate are covered independently.
 */

/**
 * Runs includes/functions.php in a child process after $preamble and returns
 * its stdout, with markers describing the resulting report-helper state.
 */
function flowview_run_shim_child(string $preamble): string {
	$functions = dirname(__DIR__, 2) . '/includes/functions.php';

	expect(is_readable($functions))->toBeTrue();

	$script = <<<CHILD
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$preamble

require \$argv[1];

echo '<<<LOADED_OK>>>';
echo 'HAS_RLAN=' . (function_exists('reports_log_and_notify') ? '1' : '0') . ';';
if (function_exists('reports_log_and_notify')) {
	\$rf = new ReflectionFunction('reports_log_and_notify');
	echo 'RLAN_FILE=' . basename((string) \$rf->getFileName()) . ';';
}
CHILD;

	$tmp = tempnam(sys_get_temp_dir(), 'pre13_');
	expect($tmp)->not->toBeFalse();

	try {
		file_put_contents($tmp, $script);

		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' ' . escapeshellarg($functions);

		return (string) shell_exec($command);
	} finally {
		@unlink($tmp);
	}
}

it('does not redeclare natives when the Cacti version helpers are unavailable (fallback path)', function () {
	// No CACTI_VERSION / cacti_version_compare(): the gate falls back to
	// function_exists(). The helpers are defined up front (as on native Cacti),
	// so the shim must not be loaded and nothing may be redeclared.
	$preamble = <<<'PRE'
function reports_run($id) { return true; }
function reports_queue($name, $request_type, $source, $source_id, $command, $notification) {}
function reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, &$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = [], $headers = []) {}
PRE;

	$output = flowview_run_shim_child($preamble);

	expect($output)->toContain('<<<LOADED_OK>>>');
	expect($output)->not->toContain('redeclare');
	// The surviving declaration is the pre-defined native one, not the shim file.
	expect($output)->not->toContain('RLAN_FILE=functions-pre13.php');
});

it('keeps the shim unloaded on a 1.3.0 develop build even when the natives are not yet defined', function () {
	// The version helper reports 1.3.0 (CACTI_VERSION resolves to '1.3.0' on a
	// develop build) and the natives are absent, mirroring plugin init running
	// before core loads lib/reports.php. The gate must skip the shim from the
	// version alone, so reports_log_and_notify() must NOT exist afterwards.
	$preamble = <<<'PRE'
define('CACTI_VERSION', '1.3.0');
// Mirrors Cacti: a 1.3.0 develop build is treated as >= 1.3.
function cacti_version_compare($v1, $v2, $op = '>') { return version_compare($v1, $v2, $op); }
PRE;

	$output = flowview_run_shim_child($preamble);

	expect($output)->toContain('<<<LOADED_OK>>>');
	expect($output)->not->toContain('redeclare');
	expect($output)->toContain('HAS_RLAN=0;');
});

it('loads the shim on a genuine pre-1.3 release', function () {
	// The version helper reports 1.2.29 (the plugin's declared compat floor) and
	// the natives are absent, so the shim must load from functions-pre13.php.
	$preamble = <<<'PRE'
define('CACTI_VERSION', '1.2.29');
function cacti_version_compare($v1, $v2, $op = '>') { return version_compare($v1, $v2, $op); }
PRE;

	$output = flowview_run_shim_child($preamble);

	expect($output)->toContain('<<<LOADED_OK>>>');
	expect($output)->not->toContain('redeclare');
	expect($output)->toContain('HAS_RLAN=1;');
	expect($output)->toContain('RLAN_FILE=functions-pre13.php;');
});
