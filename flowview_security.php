<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

function flowview_normalize_listener_port($value) {
	if (is_int($value)) {
		$port = $value;
	} elseif (is_string($value) && preg_match('/^[0-9]+$/', $value)) {
		$port = (int) $value;
	} else {
		return false;
	}

	if ($port < 1 || $port > 65535) {
		return false;
	}

	return $port;
}

function flowview_build_listener_status_command($os, $port, $use_fallback = false) {
	$port = flowview_normalize_listener_port($port);

	if ($port === false) {
		return false;
	}

	if ($os == 'freebsd') {
		return "netstat -an | grep '." . $port . " '";
	}

	if ($use_fallback) {
		return "netstat -an | grep ':" . $port . " '";
	}

	return "ss -lntu | grep ':" . $port . " '";
}
