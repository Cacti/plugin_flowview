<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('listener port security regression wiring', function () {
	it('does not leave raw row port interpolation in shell_exec calls', function () {
		$path = realpath(__DIR__ . '/../../flowview_devices.php');
		expect($path)->not->toBeFalse();

		$contents = file_get_contents($path);
		expect($contents)->not->toBeFalse();

		expect($contents)->not->toContain("shell_exec(\"netstat -an | grep '.\" . \$row['port'] . \" '\")");
		expect($contents)->not->toContain("shell_exec(\"ss -lntu | grep ':\" . \$row['port'] . \" '\")");
		expect($contents)->toContain("\$status_cmd = flowview_build_listener_status_command(\$os, \$row['port'])");
	});
});
