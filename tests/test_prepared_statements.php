<?php

function assert_contains($haystack, $needle, $message) {
	if (strpos($haystack, $needle) === false) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function assert_not_contains($haystack, $needle, $message) {
	if (strpos($haystack, $needle) !== false) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function assert_regex($pattern, $subject, $message) {
	if (!preg_match($pattern, $subject)) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

function assert_not_regex($pattern, $subject, $message) {
	if (preg_match($pattern, $subject)) {
		fwrite(STDERR, $message . PHP_EOL);
		exit(1);
	}
}

$devices = file_get_contents(__DIR__ . '/../flowview_devices.php');
if ($devices === false) {
	fwrite(STDERR, "Unable to read flowview_devices.php\n");
	exit(1);
}

assert_contains(
	$devices,
	"db_fetch_cell_prepared('SELECT pid",
	'Expected flowview_devices.php process lookup to use db_fetch_cell_prepared().'
);

assert_not_regex(
	"/db_fetch_cell\\s*\\(\\s*['\\\"]SELECT\\s+pid\\s+FROM\\s+processes\\s+WHERE\\s+tasktype\\s*=\\s*['\\\"]flowview['\\\"]\\s+AND\\s+taskname\\s*=\\s*['\\\"]master['\\\"]/is",
	$devices,
	'Raw process lookup should not remain in flowview_devices.php.'
);

$setup = file_get_contents(__DIR__ . '/../setup.php');
if ($setup === false) {
	fwrite(STDERR, "Unable to read setup.php\n");
	exit(1);
}

assert_contains(
	$setup,
	"db_fetch_cell_prepared('SELECT version",
	'Expected setup.php plugin version lookup to use db_fetch_cell_prepared().'
);

assert_regex(
	"/WHERE\\s+directory\\s*=\\s*\\?\\s*'?,\\s*array\\(\\s*'flowview'\\s*\\)\\s*\\)/s",
	$setup,
	'Expected setup.php version lookup to bind flowview directory via placeholder.'
);

assert_not_contains(
	$setup,
	'db_fetch_cell(\'SELECT version',
	'Raw version lookup should not remain in setup.php.'
);

assert_not_regex(
	"/db_fetch_cell\\s*\\(\\s*['\\\"]SELECT\\s+pid\\s+FROM\\s+processes\\s+WHERE\\s+tasktype\\s*=\\s*['\\\"]flowview['\\\"]\\s+AND\\s+taskname\\s*=\\s*['\\\"]master['\\\"]/is",
	$setup,
	'Raw process lookup should not remain in setup.php.'
);

echo "OK\n";
