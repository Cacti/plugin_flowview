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

assert_not_contains(
	$devices,
	'db_fetch_cell(\'SELECT pid FROM processes WHERE tasktype="flowview" AND taskname="master"\')',
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

assert_contains(
	$setup,
	"WHERE directory = ?', array('flowview'))",
	'Expected setup.php version lookup to bind flowview directory via placeholder.'
);

assert_not_contains(
	$setup,
	'db_fetch_cell(\'SELECT version',
	'Raw version lookup should not remain in setup.php.'
);

assert_not_contains(
	$setup,
	'db_fetch_cell(\'SELECT pid FROM processes WHERE tasktype="flowview" AND taskname="master"\')',
	'Raw process lookup should not remain in setup.php.'
);

echo "OK\n";
