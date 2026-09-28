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
 *
 * @param mixed $value The port value to validate/normalize.
 *
 * @return int|false The normalized port number, or false if $value is
 *                   not a valid port.
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
 *
 * @param string $os           The target OS: 'freebsd' uses a
 *                             FreeBSD-style netstat filter, otherwise a
 *                             Linux-style command is used.
 * @param mixed  $port         The port to check (validated via
 *                             flowview_normalize_listener_port()).
 * @param bool   $use_fallback Whether to use the netstat-based fallback
 *                             command instead of the preferred `ss`
 *                             command (non-FreeBSD only).
 *
 * @return string|false The shell command string to run, or false if
 *                      the port is invalid.
 */
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
