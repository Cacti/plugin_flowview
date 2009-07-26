<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


function plugin_flowview_run_schedule($id) {
	global $config;

	$schedule = db_fetch_row("SELECT * FROM plugin_flowview_schedules WHERE id = $id");
	$query = db_fetch_row("SELECT * FROM plugin_flowview_queries WHERE id = " . $schedule['savedquery']);

	$fromname = read_config_option('settings_from_name');
	if (strlen($fromname) < 1)
		$fromname = 'Cacti Flowview';

	$from= read_config_option('settings_from_email');
	if (strlen($from) < 1)
		$from = 'cacti@cactiusers.org';

	$subject = 'Netflow - ' . $query['name'];

	$_REQUEST['schedule'] = $id;
	$_REQUEST['query'] = $schedule['savedquery'];
	$_REQUEST['action'] = 'loadquery';
	include($config['base_path'] . '/plugins/flowview/variables.php');

	$message = createfilter ();
	send_mail($schedule['email'], $from, $subject, $message, ' ', '', $fromname);
}

function createfilter () {
	global $config;
	include($config['base_path'] . '/plugins/flowview/variables.php');

	flowview_setup_table ();

	$filter = '';

	$flowbin = read_config_option('path_flowtools');
	if ($flowbin == '')
		$flowbin = '/usr/bin';
	if (substr($flowbin, -1 , 1) == '/')
		$flowbin = substr($flowbin, 0, -1);

	$workdir = read_config_option('path_flowtools_workdir');
	if ($workdir == '')
		$workdir = '/tmp';
	if (substr($workdir, -1 , 1) == '/')
		$workdir = substr($workdir, 0, -1);

	$pathstructure = '';
	if ($device != '') {
		$pathstructure = db_fetch_cell("SELECT nesting FROM plugin_flowview_devices WHERE folder = '$device'");
	}
	if ($pathstructure == '')
		$pathstructure = 0;

	$time = time();
	$filterfile = "$workdir/FlowViewer_filter_" . time();

	$start = strtotime($start_date . ' ' . $start_time);
	$end = strtotime($end_date . ' ' . $end_time);

	$flow_cat_command = "$flowbin/flow-cat -t \"" . date("m/d/Y H:i:s", $start) . '" -T "' . date("m/d/Y H:i:s", $end) . '" ';
	$flow_cat_command .= getfolderpath($pathstructure, $device, $start, $end);
	$flownfilter_command = "$flowbin/flow-nfilter -f $filterfile -FFlowViewer_filter";

	$flowstat = $flowbin . '/flow-stat';
	$flowstat_command = '';

	$flow_command = "$flow_cat_command | $flownfilter_command";
	if ($stat_report != 0) {
		if ($stat_report == 99) {
			$flowstat_command = "$flowbin/flow-stat -S" . $sort_field;
		} else {
			$flowstat_command = "$flowbin/flow-stat -f" . $stat_report . " -S" . ($sort_field-1);
		}
		$flow_command .= " | $flowstat_command";
	}
	if ($print_report != 0) {
		$flow_command .= " | $flowbin/flow-print -f" . $print_report;
	}

	// Check to see if the flowtools binaries exists

	if (!is_file("$flowbin/flow-cat"))
		return "Can not find the '<strong>flow-cat</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Flowtools Path Setting</a>!";
	if (!is_file("$flowbin/flow-nfilter"))
		return "Can not find the '<strong>flow-nfilter</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Flowtools Path Setting</a>!";
	if (!is_file("$flowbin/flow-stat"))
		return "Can not find the '<strong>flow-stat</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Flowtools Path Setting</a>!";
	if (!is_file("$flowbin/flow-print"))
		return "Can not find the '<strong>flow-print</strong>' binary at '<strong>$flowbin</strong>', please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Flowtools Path Setting</a>!";

	// Create Filters
	$filter .= flowview_create_ip_filter ($source_address, 'source');
	$filter .= flowview_create_if_filter ($source_if, 'source');
	$filter .= flowview_create_port_filter ($source_port, 'source');
	$filter .= flowview_create_as_filter ($source_as, 'source');
	$filter .= flowview_create_ip_filter ($dest_address, 'dest');
	$filter .= flowview_create_if_filter ($dest_if, 'dest');
	$filter .= flowview_create_port_filter ($dest_port, 'dest');
	$filter .= flowview_create_as_filter ($dest_as, 'dest');
	$filter .= flowview_create_protocol_filter ($protocols);
	$filter .= flowview_create_tcp_flag_filter ($tcp_flags);
	$filter .= flowview_create_tos_field_filter ($tos_fields);
	$filter .= flowview_create_time_filter($start, $end);
	$filter .= flowview_create_flowview_filter();

	// Write filters to file
	$f = @fopen($filterfile, 'w');
	if (!$f) {
		clearstatcache();
		if (!is_dir($workdir))
			return "<strong>Flow Tools Work directory ($workdir) does not exist!, please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Settings</a></strong>";
		return "<strong>Flow Tools Work directory ($workdir) is not writable!, please check your <a href='" . $config['url_path'] . "settings.php?tab=path'>Settings</a></strong>";
	}
	@fputs($f, $filter);
	@fclose($f);

	// Run the command
	$output = shell_exec($flow_command);
	unlink($filterfile);

	if ($stat_report != 0) {
		$output = parsestatoutput($output);
	}

	if ($print_report != 0) {
		$output = parseprintoutput($output);
	}

	return $output;
}

function parsestatoutput($output) {
	global $config;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (!isset($stat_columns_array[$stat_report]))
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";

	$output = explode("\n", $output);

	$o = '<table cellspacing=1 cellpadding=3 border=0 bgcolor="#00438C"><tr class="textHeaderDark" align=center>';

	$clines = $stat_columns_array[$stat_report][0];
	$octect_col = $stat_columns_array[$stat_report][1];
	$proto_col = $stat_columns_array[$stat_report][3];

	$ip_col = $stat_columns_array[$stat_report][2];
	$ip_col = explode(',',$ip_col);

	$columns = $stat_columns_array[$stat_report];
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);

	$x = 1;
	foreach ($columns as $column) {
		if (isset($_REQUEST['schedule']))
			$o .= "<td><font color=white><b>$column</b></font></td>";
		else
			$o .= "<td><a href='javascript:Sort($x);'><font color=white><b>$column</b></font></a></td>";
		$x++;
	}
	$o .= "</tr>\n";
	$cut = 1;

	$dns = '';

	if ($resolve_addresses == 'Y' && isset($ip_col[0]) && $ip_col[0] != '') {
		$dns = read_config_option('flowview_dns');
		if ($dns == '') {
			$dns = read_config_option('discovery_dns');
			if ($dns == '') {
				$dns = read_config_option('mt_dns_primary');
			}
		}
	}

	$i = 0;
	foreach ($output as $out) {
		if (substr($out, 0, 1) != '#' && $out != '') {
			$out = trim($out);
			while (strpos($out, '  ')) {
				$out = str_replace('  ', ' ', $out);
			}
			$out = explode(' ', $out);
			if ($octect_col == '' || $cutoff_octets == '' || $out[$octect_col] > $cutoff_octets-1) {
				$o .= '<tr align=right bgcolor="' . flowview_altcolor($i) . '">';
				$c = 0;
				foreach ($out as $out2) {
					if ($out2 != '') {
						if ($dns != '' && in_array($c, $ip_col))
							$out2 = flowview_get_dns_from_ip($out2, $dns);
						if ($c == $octect_col && $octect_col != '')
							 $out2 = plugin_flowview_formatoctet($out2);
						if ($c == $proto_col && $proto_col != '') {
							 $out2 = plugin_flowview_get_protocol($out2);
						}
						$o .= "<td>$out2</td>";
						$c++;
					}
				}
				$o .= "</tr>\n";
				$cut++;
			}
		}
		if ($cutoff_lines < $cut)
			break;
		$i++;
	}

	$o .= '</table>';
	return $o;
}

function plugin_flowview_get_protocol ($prot) {
	global $config;
	include($config['base_path'] . '/plugins/flowview/arrays.php');
	if (isset($ip_protocols_array[$prot]))
		return $ip_protocols_array[$prot];
	return $prot;
}

function plugin_flowview_formatoctet($size, $div = 1024) {
	$x=0;
	$tag = array('Bytes', 'KB', 'MB', 'GB', 'TB');
	while ($size > $div) {
		$size = $size / $div;
		$x++;
	}
	return round($size, 2) . ' ' . $tag[$x];
}

function flowview_altcolor($i) {
	global $colors;
	if ($i/2 == intval($i/2)) {
		return '#' . $colors["light"];
	} else {
		return '#' . $colors["alternate"];
	}
}


function parseprintoutput($output) {
	global $config, $colors;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if (!isset($print_columns_array[$print_report]))
		return "<table><tr><td><font size=+1><pre>$output</pre></font></td></tr></table>";

	$output = explode("\n", $output);

	$o = '<table cellspacing=1 cellpadding=3 border=0 bgcolor="#00438C"><tr class="textHeaderDark" align=center>';

	$clines = $print_columns_array[$print_report][0];
	$octect_col = $print_columns_array[$print_report][1];
	$proto_col = $print_columns_array[$print_report][3];

	$ip_col = $print_columns_array[$print_report][2];
	$ip_col = explode(',',$ip_col);

	$columns = $print_columns_array[$print_report];
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);
	array_shift($columns);

	foreach ($columns as $column) {
		$o .= "<td><b>$column</b></td>";
	}
	$o .= "</tr>\n";
	$cut = 1;

	$dns = '';

	if ($resolve_addresses == 'Y' && isset($ip_col[0]) && $ip_col[0] != '') {
		$dns = read_config_option('flowview_dns');
		if ($dns == '') {
			$dns = read_config_option('discovery_dns');
			if ($dns == '') {
				$dns = read_config_option('mt_dns_primary');
			}
		}
	}

	$i = 0;
	$firstline = true;
	foreach ($output as $out) {
		if (substr($out, 0, 1) != '#' && $out != '' && $firstline == false) {
			$out = trim($out);
			while (strpos($out, '  ')) {
				$out = str_replace('  ', ' ', $out);
			}
			$out = explode(' ', $out);

			if ($octect_col == '' || $cutoff_octets == '' || $out[$octect_col] > $cutoff_octets-1) {
				$o .= '<tr align=right bgcolor="' . flowview_altcolor($i) . '">';
				$c = 0;
				foreach ($out as $out2) {
					if ($out2 != '') {
						if ($dns != '' && in_array($c, $ip_col))
							$out2 = flowview_get_dns_from_ip($out2, $dns);
						if ($c == $octect_col && $octect_col != '')
							 $out2 = plugin_flowview_formatoctet($out2);
						if ($c == $proto_col && $proto_col != '')
							 $out2 = plugin_flowview_get_protocol($out2);
						$o .= "<td>$out2</td>";
						$c++;
					}
				}
				$o .= "</tr>\n";
				$cut++;
			}
		}
		if ($cutoff_lines < $cut)
			break;
		$firstline = false;
		$i++;
	}

	$o .= '</table>';
	return $o;
}

function flowview_create_flowview_filter() {
	global $config;
	include($config['base_path'] . '/plugins/flowview/variables.php');
	$filter = "filter-definition FlowViewer_filter\n";
	if ($source_address != "")
       	$filter .= "  match ip-source-address source_address\n";
	if ($source_if != "")
		$filter .= "  match input-interface source_if\n";
	if ($source_port != "")
		$filter .= "  match ip-source-port source_port\n";
	if ($source_as != "")
		$filter .= "  match source-as source_as\n";
	if ($dest_address != "")
       	$filter .= "  match ip-destination-address dest_address\n";
	if ($dest_if != "")
       	$filter .= "  match output-interface dest_if\n";
	if ($dest_port != "")
       	$filter .= "  match ip-destination-port dest_port\n";
	if ($dest_as != "")
       	$filter .= "  match destination-as dest_as\n";
	if ($protocols != "")
       	$filter .= "  match ip-protocol protocol\n";
	if ($tcp_flags != "")
       	$filter .= "  match ip-tcp-flags tcp_flag\n";
	if ($tos_fields != "")
       	$filter .= "  match ip-tos tos_field\n";
	switch ($flow_select) {
		case 1:
			$filter .= "  match end-time start_flows\n";
			$filter .= "  match start-time end_flows\n";
			break;
		case 2:
			$filter .= "  match end-time start_flows\n";
			$filter .= "  match end-time end_flows\n";
			break;
		case 3:
			$filter .= "  match start-time start_flows\n";
			$filter .= "  match start-time end_flows\n";
			break;
		case 4:
			$filter .= "  match start-time start_flows\n";
			$filter .= "  match end-time end_flows\n";
			break;
	}
	return $filter;
}

function flowview_create_time_filter($start, $end) {
	$filter = "filter-primitive start_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit ge " . date("F j, Y H:i:s ", $start) . "\n";
	$filter .= "   default deny\n";
	$filter .= "filter-primitive end_flows\n";
	$filter .= "   type time-date\n";
	$filter .= "   permit lt " . date("F j, Y H:i:s ", $end) . "\n";
	$filter .= "   default deny\n\n";
	return $filter;
}
function flowview_create_tos_field_filter ($tos_fields) {
	if ($tos_fields == '')
		return '';
	$filter  = "filter-primitive tos_field\n";
	$filter .= "   type ip-tos\n";
	$tos_fields = str_replace(' ', '', $tos_fields);
	$s_if = explode(',', $tos_fields);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$s = explode('/', $s);
				if (isset($s[1]))
					$filter .= "   mask " . $s[1] . "\n";
				$filter .= "   deny " . $s[0] . "\n";
				$excluded = true;
		} else {
			$s = explode('/', $s);
			if (isset($s[1]))
				$filter .= "   mask " . $s[1] . "\n";
			$filter .= "   permit " . $s[0] . "\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_tcp_flag_filter ($tcp_flags) {
	if ($tcp_flags == '')
		return '';
	$filter  = "filter-primitive tcp_flag\n";
	$filter .= "   type ip-tcp-flag\n";
	$tcp_flags = str_replace(' ', '', $tcp_flags);
	$s_if = explode(',',$tcp_flags);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$s = explode('/', $s);
				if (isset($s[1]))
					$filter .= "   mask " . $s[1] . "\n";
				$filter .= "   deny " . $s[0] . "\n";
				$excluded = true;
		} else {
			$s = explode('/', $s);
			if (isset($s[1]))
				$filter .= "   mask " . $s[1] . "\n";
			$filter .= "   permit " . $s[0] . "\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_protocol_filter ($protocols) {
	if ($protocols == '')
		return '';
	$filter  = "filter-primitive protocol\n";
	$filter .= "   type ip-protocol\n";
	$protocols = str_replace(' ', '', $protocols);
	$s_if = explode(',',$protocols);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_as_filter ($as, $type) {
	if ($as == '')
		return '';
	$filter  = "filter-primitive $type" . "_as\n";
	$filter .= "   type as\n";
	$as = str_replace(' ', '', $as);
	$s_if = explode(',',$as);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_port_filter ($port, $type) {
	if ($port == '')
		return '';
	$filter  = "filter-primitive $type" . "_port\n";
	$filter .= "   type ip-port\n";
	$port = str_replace(' ', '', $port);
	$s_if = explode(',',$port);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_if_filter ($source_if, $type) {
	if ($source_if == '')
		return '';
	$filter  = "filter-primitive $type" . "_if\n";
	$filter .= "   type ifindex\n";
	$source_if = str_replace(' ', '', $source_if);
	$s_if = explode(',',$source_if);
	$excluded = false;
	foreach ($s_if as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";	
	return $filter;
}

function flowview_create_ip_filter ($source_address, $type) {
	if ($source_address == '')
		return;
	$filter  = "filter-primitive $type" . "_address\n";
	$filter .= "   type ip-address-prefix\n";
	$exclude = false;
	$s_a = explode(',', $source_address);
	$excluded = false;
	foreach ($s_a as $s) {
		if (substr($s, 0, 1) == '-') {
				$s = substr($s, 1);
				$filter .= "   deny $s\n";
				$excluded = true;
		} else {
			$filter .= "   permit $s\n";
		}
	}
	if ($excluded)
		$filter .= "   default permit\n";
	else
		$filter .= "   default deny\n";
	return $filter;
}

function getfolderpath($n, $device, $start, $end) {

	$folderpath = '';

	// Add Flow Interval plus 1
	$end = $end + 300 + 1;

	$dir = read_config_option('path_flows_dir');
	if ($dir == '')
		$dir = '/var/netflow/flows/completed';
	if (substr($dir, -1 , 1) == '/')
		$dir = substr($dir, 0, -1);

	if ($device != '')
		$dir .= "/$device";

	switch ($n) {
		case -2:
 		case -1:
		case 0:
		case 1:
		case 2:
		case -3:
		case 3:
 			$start = strtotime(date('m/d/Y', $start));
 			break;
 		case 4:
 			$start = strtotime(date('m/d/Y G:00', $start));
  			break;
 	}

	while ($start < $end) {
		$y = date("Y", $start);
		$m = date("m", $start);
		$d = date("d", $start);
		$h = date("G", $start);
		$folderpath .= $dir;
		switch ($n) {
			case -2:
				$folderpath .= "/$y-$m/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case -1:
				$folderpath .= "/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case 0:
				$folderpath .= ' ';
				$start = $start + 86400;
				break;
			case 1:
				$folderpath .= "/$y ";
				$start = $start + 86400;
				break;
			case 2:
				$folderpath .= "/$y/$y-$m ";
				$start = $start + 86400;
				break;
			case -3:
			case 3:
				$folderpath .= "/$y/$y-$m/$y-$m-$d ";
				$start = $start + 86400;
				break;
			case 4:
				$folderpath .= "/$y-$m-$d-$h ";
				$start = $start + 3600;
				break;
		}
	}
	return $folderpath;
}


function flowview_check_fields () {
	global $config;

	include($config['base_path'] . '/plugins/flowview/variables.php');
	include($config['base_path'] . '/plugins/flowview/arrays.php');

	if ($stat_report == 0 && $print_report == 0)
		return "You must select a Statistics Report or Printed Report!";

	if ($stat_report > 0 && $print_report > 0)
		return "You must select only a Statistics Report or a Printed Report (not both)!";

	if (!ereg("^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$", $start_date) && $start_date)
		return "Invalid start date!<br>Must be in the format (mm/dd/yyyy)";

	if (!ereg("^[0-9]{1,2}/[0-9]{1,2}/[0-9]{4}$", $end_date) && $end_date != '')
		return "Invalid end date!<br>Must be in the format (mm/dd/yyyy)";

	if (!ereg("^[0-9]{1,2}:[0-9]{2}(:[0-9]{2}){0,1}$", $start_time) && !ereg("^[-]{1,1}[0-9]{1,3}[ ]{0,1}(HOUR|HOURS|DAY|DAYS|MINUTE|MINUTES)$", $start_time))
		return "Invalid start time!<br>Must be in the format (hh:mm or hh:mm:ss)";

	if (!ereg("^[0-9]{1,2}:[0-9]{2}(:[0-9]{2}){0,1}$", $end_time) && $end_time != 'NOW')
		return "Invalid end time!<br>Must be in the format (hh:mm or hh:mm:ss)";

	if (strtotime($start_date . ' ' . $start_time) > strtotime($end_date . ' ' . $end_time))
		return "Invalid dates, End Date/Time is earlier than Start Date/Time!";

	if ($source_address != '') {
		$a = explode(',',$source_address);
		foreach ($a as $source_a) {
			$s = explode('/',$source_a);
			$source_ip = $s[0];
			if (!ereg("^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $source_ip)) {
				return "Invalid IP for the Source Address!<br>(Must be in the form of '192.168.0.1')";
			}
			$subs = explode('.', $source_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return "Invalid IP for the Source Address!<br>(Must be in the form of '192.168.0.1')";
			}
			if (isset($s[1])) {
				$subnet = $s[1];
				if (!ereg("^[0-9]{1,3}$", $subnet)) {
					if (!ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $subnet)) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
					$subs = explode('.', $subnet);
					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return "Invalid subnet for the Source Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				}
			}
		}
	}

	if ($dest_address != '') {
		$a = explode(',',$dest_address);
		foreach ($a as $dest_a) {
			$s = explode('/',$dest_a);
			$dest_ip = $s[0];
			if (!ereg("^[-]{0,1}[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $dest_ip)) {
				return "Invalid IP for the Destination Address!<br>(Must be in the form of '192.168.0.1')";
			}
			$subs = explode('.', $dest_ip);
			if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
				return "Invalid IP for the Destination Address!<br>(Must be in the form of '192.168.0.1')";
			}
			if (isset($s[1])) {
				$subnet = $s[1];
				if (!ereg("^[0-9]{1,3}$", $subnet)) {
					if (!ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$", $subnet)) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
					$subs = explode('.', $subnet);
					if ((!isset($subs[0]) || $subs[0] > 255) || (!isset($subs[1]) || $subs[1] > 255) || (!isset($subs[2]) || $subs[2] > 255) || (!isset($subs[3]) || $subs[3] > 255)) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				} else {
					if ($subnet < 0 || $subnet > 32) {
						return "Invalid subnet for the Destination Address!<br>(Must be in the form of '192.168.0.1/255.255.255.0' or '192.168.0.1/24')";
					}
				}
			}
		}
	}

	if ($source_if != '') {
		$source_if = str_replace(' ', '', $source_if);
		$s_if = explode(',',$source_if);
		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 999 || !is_numeric($s))
					return "Invalid value for Source Interface!";
		}
	}

	if ($source_port != '') {
		$source_port = str_replace(' ', '', $source_port);
		$s_port = explode(',',$source_port);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Source Port! (0 - 65535)";
		}
	}

	if ($source_as != '') {
		$source_as = str_replace(' ', '', $source_as);
		$s_as = explode(',',$source_as);
		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Source AS! (0 - 65535)";
		}
	}

	if ($dest_if != '') {
		$dest_if = str_replace(' ', '', $dest_if);
		$s_if = explode(',',$dest_if);
		foreach ($s_if as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 999 || !is_numeric($s))
					return "Invalid value for Destination Interface!";
		}
	}

	if ($dest_port != '') {
		$dest_port = str_replace(' ', '', $dest_port);
		$s_port = explode(',',$dest_port);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Destination Port! (0 - 65535)";
		}
	}

	if ($dest_as != '') {
		$dest_as = str_replace(' ', '', $dest_as);
		$s_as = explode(',',$dest_as);
		foreach ($s_as as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 65535 || $s < 0 || !is_numeric($s))
					return "Invalid value for Destination AS! (0 - 65535)";
		}
	}

	if ($protocols != '') {
		$protocols = str_replace(' ', '', $protocols);
		$s_port = explode(',', $protocols);
		foreach ($s_port as $s) {
			if (substr($s, 0,1) == '-')
				$s = substr($s, 1);
			if ($s > 255 || $s < 0 || !is_numeric($s))
					return "Invalid value for Portocol! (1 - 255)";
		}
	}

	if ($tcp_flags != '') {
		$tcp_flags = str_replace(' ', '', $tcp_flags);
		$tcp_flag = explode(',', $tcp_flags);
		foreach ($tcp_flag as $t) {
			if (!ereg("^[-]{0,1}((0x[0-9a-zA-Z]{1,3})|([0-9a-zA-Z]{1,3}))(/[0-9a-zA-Z]{1,3}){0,1}$", $t)) {
					return "Invalid value for TCP Flag! (ex: 0x1b or 0x1b/SA or SA/SA)";
			}
		}
	}
	if ($cutoff_octets != '' && ($cutoff_octets < 0 || $cutoff_octets > 99999999999999999 || !is_numeric($cutoff_octets)))
		return "Invalid value for Cutoff Octets!";

	if ($cutoff_lines != '' && ($cutoff_lines < 0 || $cutoff_lines > 999999 || !is_numeric($cutoff_lines)))
		return "Invalid value for Cutoff Lines!";

	if ($sort_field != '' && ($sort_field < 0 || $sort_field > 99 || !is_numeric($sort_field)))
		return "Invalid value for Sort Field!";

}


/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function flowview_get_dns_from_ip($ip, $dns, $timeout = 1000) {

	// First check to see if its in the cache

	$cache = db_fetch_assoc("SELECT * from plugin_flowview_dnscache where ip = '$ip'");

	if (isset($cache[0]['host']))
		return $cache[0]['host'];

	$time = time();

	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode(".", $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return "ERROR";

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return "ERROR";
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info["timed_out"]) {
		db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
		return $ip;
	}

	/* more error handling */
	if ($response == "") { 
		db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
		return $ip;
	}

	/* parse the response and find the response type */
	$type = @unpack("s", substr($response, $requestsize+2));

	if ($type[1] == 0x0C00) {
		/* set up our variables */
		$host = "";
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack("c", substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '" . substr($host, 0, strlen($host) -1) . "', '$time')");
				return substr($host, 0, strlen($host) -1);
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . ".";

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
		return $ip;
	}

	/* error - return the hostname */
	db_execute("insert into plugin_flowview_dnscache (ip, host, time) values ('$ip', '$ip', '" . ($time - 3540) . "')");
	return $ip;
}

?>
