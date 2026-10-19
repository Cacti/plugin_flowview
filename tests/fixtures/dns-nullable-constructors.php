<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
// Exercise the real bundled classes independently of the NBAR resolver double.
if (PHP_SAPI !== 'cli' || count($argv) !== 2) {
	exit(1);
}
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
set_include_path(dirname(__DIR__, 2) . PATH_SEPARATOR . get_include_path());
$driver = function_exists('xdebug_start_code_coverage');
if ($driver) {
	xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
}
require dirname(__DIR__, 2) . '/Net/DNS2.php';
$classes = [
	'Net_DNS2', 'Net_DNS2_Resolver', 'Net_DNS2_Updater', 'Net_DNS2_Notifier',
	'Net_DNS2_Exception', 'Net_DNS2_Header', 'Net_DNS2_Question',
	'Net_DNS2_RR', 'Net_DNS2_RR_OPT', 'Net_DNS2_RR_CERT'
];
$nullable = [];
foreach ($classes as $class) {
	foreach ((new ReflectionClass($class))->getConstructor()->getParameters() as $parameter) {
		if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === null && $parameter->hasType()) {
			$nullable[$class . ':' . $parameter->getName()] = $parameter->allowsNull();
		}
	}
}
$packet = null;
$objects = [
	new Net_DNS2(), new Net_DNS2(null), new Net_DNS2(['timeout' => 2]),
	new Net_DNS2_Resolver(), new Net_DNS2_Resolver(null),
	new Net_DNS2_Updater('example.invalid', null),
	new Net_DNS2_Notifier('example.invalid', null),
	new Net_DNS2_Exception('fixture', 0, null, null, null),
	new Net_DNS2_Header($packet), new Net_DNS2_Question($packet),
	new Net_DNS2_RR_OPT($packet, null), new Net_DNS2_RR_CERT($packet, null)
];
$result = ['nullable' => $nullable, 'objects' => count($objects), 'timeout' => $objects[2]->timeout, 'coverage' => []];
if ($driver) {
	$result['coverage'] = xdebug_get_code_coverage();
	xdebug_stop_code_coverage();
}
file_put_contents($argv[1], json_encode($result, JSON_THROW_ON_ERROR));
