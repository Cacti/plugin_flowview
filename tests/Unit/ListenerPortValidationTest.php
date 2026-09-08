<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

require_once __DIR__ . '/../../flowview_security.php';

describe('listener port normalization', function () {
	it('accepts valid numeric ports', function () {
		expect(flowview_normalize_listener_port('2055'))->toBe(2055);
		expect(flowview_normalize_listener_port(9995))->toBe(9995);
	});

	it('rejects mixed or out of range ports', function () {
		expect(flowview_normalize_listener_port('2055;id'))->toBeFalse();
		expect(flowview_normalize_listener_port('0'))->toBeFalse();
		expect(flowview_normalize_listener_port('65536'))->toBeFalse();
		expect(flowview_normalize_listener_port('-1'))->toBeFalse();
	});

	it('builds listener status commands only for validated ports', function () {
		expect(flowview_build_listener_status_command('linux', '2055'))->toBe("ss -lntu | grep ':2055 '");
		expect(flowview_build_listener_status_command('linux', '2055', true))->toBe("netstat -an | grep ':2055 '");
		expect(flowview_build_listener_status_command('freebsd', '2055'))->toBe("netstat -an | grep '.2055 '");
		expect(flowview_build_listener_status_command('linux', '2055;id'))->toBeFalse();
	});
});
