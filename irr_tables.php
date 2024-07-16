<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2024 The Cacti Group                                 |
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

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_route` (
	`route` varchar(40) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`origin` varchar(20) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`mnt_routes` varchar(32) NOT NULL DEFAULT '',
	`aggr_bndry` varchar(20) NOT NULL DEFAULT '',
	`aggr_mtd` varchar(20) NOT NULL DEFAULT '',
	`holes` varchar(64) NOT NULL DEFAULT '',
	`status` varchar(20) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`member_of` varchar(30) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`geoidx` varchar(20) NOT NULL DEFAULT '',
	`roa_uri` varchar(128) NOT NULL DEFAULT '',
	`export_comps` varchar(30) NOT NULL DEFAULT '',
	`components` varchar(30) NOT NULL DEFAULT '',
	`pingable` varchar(32) NOT NULL DEFAULT '',
	`ping_hdl` varchar(20) NOT NULL DEFAULT '',
	`inject` varchar(32) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`route`,`source`,`origin`),
	KEY `route` (`route`),
	KEY `source` (`source`),
	KEY `origin` (`origin`))
	ENGINE=Aria
	COMMENT='Holds the basic whois database from RADB'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_as_block` (
	`as_block` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`type` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`as_block`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_aut_num` (
	`aut_num` varchar(20) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`as_name` varchar(32) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`status` varchar(20) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`mnt_routes` varchar(32) NOT NULL DEFAULT '',
	`export` varchar(128) NOT NULL DEFAULT '',
	`import` varchar(128) NOT NULL DEFAULT '',
	`mnt_irt` varchar(20) NOT NULL DEFAULT '',
	`mp_export` varchar(128) NOT NULL DEFAULT '',
	`mp_import` varchar(128) NOT NULL DEFAULT '',
	`member_of` varchar(64) NOT NULL DEFAULT '',
	`default` varchar(64) NOT NULL DEFAULT '',
	`mp_default` varchar(64) NOT NULL DEFAULT '',
	`upd_to` varchar(32) NOT NULL DEFAULT '',
	`mnt_nfy` varchar(32) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`auth` varchar(128) NOT NULL DEFAULT '',
	`import_via` varchar(128) NOT NULL DEFAULT '',
	`export_via` varchar(128) NOT NULL DEFAULT '',
	`sponsoring_org` varchar(20) NOT NULL DEFAULT '',
	`abuse_c` varchar(30) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`aut_num`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_inetnum` (
	`inetnum` varchar(40) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`netname` varchar(32) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`status` varchar(20) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`mnt_routes` varchar(32) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`mnt_domains` varchar(32) NOT NULL DEFAULT '',
	`mnt_irt` varchar(20) NOT NULL DEFAULT '',
	`abuse_c` varchar(30) NOT NULL DEFAULT '',
	`sponsoring_org` varchar(20) NOT NULL DEFAULT '',
	`geoloc` varchar(32) NOT NULL DEFAULT '',
	`geofeed` varchar(128) NOT NULL DEFAULT '',
	`language` varchar(5) NOT NULL DEFAULT '',
	`assignment_size` int unsigned NOT NULL DEFAULT '0',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`inetnum`,`source`),
	KEY `inetnum` (`inetnum`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_mntner` (
	`mntner` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`upd_to` varchar(32) NOT NULL DEFAULT '',
	`auth` varchar(128) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_nfy` varchar(32) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`abuse_mailbox` varchar(32) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`referral_by` varchar(32) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`mntner`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_organisation` (
	`organisation` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`org_name` varchar(64) NOT NULL DEFAULT '',
	`org_type` varchar(20) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`address` varchar(255) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`e_mail` varchar(32) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_ref` varchar(20) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`phone` varchar(32) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`fax_no` varchar(32) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`abuse_mailbox` varchar(32) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`abuse_c` varchar(30) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`organisation`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_person` (
	`person` varchar(96) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`address` varchar(255) NOT NULL DEFAULT '',
	`phone` varchar(32) NOT NULL DEFAULT '',
	`e_mail` varchar(32) NOT NULL DEFAULT '',
	`nic_hdl` varchar(20) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`fax_no` varchar(32) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`abuse_mailbox` varchar(32) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`person`,`source`,`nic_hdl`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_role` (
	`role` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`address` varchar(255) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`phone` varchar(32) NOT NULL DEFAULT '',
	`fax_no` varchar(32) NOT NULL DEFAULT '',
	`e_mail` varchar(32) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`nic_hdl` varchar(20) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`abuse_mailbox` varchar(32) NOT NULL DEFAULT '',
	`trouble` text NOT NULL DEFAULT '',
	`mnt_ref` varchar(20) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`role`,`source`,`address`,`nic_hdl`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_domain` (
	`domain` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`sub_dom` varchar(64) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`nserver` varchar(64) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`zone_c` varchar(20) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`ds_rdata` varchar(128) NOT NULL DEFAULT '',
	`dom_net` varchar(32) NOT NULL DEFAULT '',
	`refer` varchar(32) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`domain`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_as_set` (
	`as_set` varchar(40) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`members` longtext NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`mbrs_by_ref` varchar(30) NOT NULL DEFAULT '',
	`upd_to` varchar(32) NOT NULL DEFAULT '',
	`mnt_nfy` varchar(32) NOT NULL DEFAULT '',
	`country` varchar(20) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`as_set`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_irt` (
	`irt` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`address` varchar(255) NOT NULL DEFAULT '',
	`e_mail` varchar(32) NOT NULL DEFAULT '',
	`abuse_mailbox` varchar(32) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`auth` varchar(128) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`phone` varchar(32) NOT NULL DEFAULT '',
	`irt_nfy` varchar(32) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`fax_no` varchar(32) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`signature` varchar(32) NOT NULL DEFAULT '',
	`encryption` varchar(32) NOT NULL DEFAULT '',
	`mnt_ref` varchar(20) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`irt`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_route_set` (
	`route_set` varchar(40) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`members` longtext NOT NULL DEFAULT '',
	`mbrs_by_ref` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`mp_members` longtext NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`route_set`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_filter_set` (
	`filter_set` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`filter` text NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`mp_filter` text NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`org` varchar(64) NOT NULL DEFAULT '',
	`present` tinyint(3) unsigned not null default '1',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	PRIMARY KEY (`filter_set`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_peering_set` (
	`peering_set` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`peering` varchar(64) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`mp_peering` varchar(64) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`peering_set`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_inet_rtr` (
	`inet_rtr` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`local_as` varchar(20) NOT NULL DEFAULT '',
	`ifaddr` varchar(30) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`peer` varchar(64) NOT NULL DEFAULT '',
	`rs_in` varchar(64) NOT NULL DEFAULT '',
	`rs_out` varchar(64) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`alias` varchar(64) NOT NULL DEFAULT '',
	`member_of` varchar(64) NOT NULL DEFAULT '',
	`interface` varchar(32) NOT NULL DEFAULT '',
	`mp_peer` varchar(64) NOT NULL DEFAULT '',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`inet_rtr`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_rtr_set` (
	`rtr_set` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`tech_c` varchar(30) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`changed` varchar(128) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`mbrs_by_ref` varchar(30) NOT NULL DEFAULT '',
	`mp_members` longtext NOT NULL DEFAULT '',
	`members` longtext NOT NULL DEFAULT '',
	`org` varchar(64) NOT NULL DEFAULT '',
	`mnt_lower` varchar(30) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`rtr_set`,`source`),
	KEY `source` (`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from Various internet registries'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_poetic_form` (
	`poetic_form` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`poetic_form`,`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from ripe'");

flowview_db_execute("CREATE TABLE IF NOT EXISTS `plugin_flowview_irr_poem` (
	`poem` varchar(64) NOT NULL DEFAULT '',
	`source` varchar(20) NOT NULL DEFAULT '',
	`descr` varchar(128) NOT NULL DEFAULT '',
	`text` text NOT NULL DEFAULT '',
	`form` varchar(128) NOT NULL DEFAULT '',
	`admin_c` varchar(30) NOT NULL DEFAULT '',
	`author` varchar(64) NOT NULL DEFAULT '',
	`remarks` text NOT NULL DEFAULT '',
	`mnt_by` varchar(40) NOT NULL DEFAULT '',
	`notify` varchar(64) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`present` tinyint(3) unsigned not null default '1',
	`last_modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	PRIMARY KEY (`poem`,`source`))
	ENGINE=Aria
	ROW_FORMAT=Dynamic
	COMMENT='Contains information from ripe'");

