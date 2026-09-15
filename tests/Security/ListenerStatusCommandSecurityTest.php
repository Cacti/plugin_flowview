<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

describe('listener status command security', function () {
	it('validates ports before saving and before shell execution', function () {
		$path = realpath(__DIR__ . '/../../flowview_devices.php');
		expect($path)->not->toBeFalse();

		$contents = file_get_contents($path);
		expect($contents)->not->toBeFalse();

		expect($contents)->toContain("include_once(\$config['base_path'] . '/plugins/flowview/flowview_security.php');");
		expect($contents)->toContain("\$save['port']         = flowview_normalize_listener_port(get_nfilter_request_var('port'));");
		expect($contents)->toContain("if (\$save['port'] === false)");
		expect($contents)->toContain("flowview_build_listener_status_command(\$os, \$row['port'])");
		expect($contents)->toContain("flowview_build_listener_status_command(\$os, \$row['port'], true)");
	});
});
