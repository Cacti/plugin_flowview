<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/**
 * Validates and normalizes a listener port value (accepting an int or a
 * purely-numeric string) to an integer within the valid TCP/UDP port
 * range. Called from flowview_build_listener_status_command() before
 * building a shell command that embeds the port value, to prevent
 * command injection via an unvalidated port.
 * not a valid port.
 *
 * @param mixed $value
 *
 * @return mixed
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

/**
 * Builds an OS-appropriate shell command string to check whether a
 * given port has an active listener, validating the port first to
 * ensure it can't be used for shell command injection. Called from
 * device/collector connectivity checks before checking whether the
 * flow collector's listener port is active.
 * FreeBSD-style netstat filter, otherwise a
 * Linux-style command is used.
 * flowview_normalize_listener_port()).
 * command instead of the preferred `ss`
 * command (non-FreeBSD only).
 * the port is invalid.
 *
 * @param mixed $os
 * @param mixed $port
 * @param bool $use_fallback
 *
 * @return mixed
 */
function flowview_build_listener_status_command($os, $port, bool $use_fallback = false) {
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
