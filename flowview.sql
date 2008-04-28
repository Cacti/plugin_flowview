
CREATE TABLE plugin_flowview_dnscache (
  ip varchar(32) NOT NULL default '',
  host varchar(255) NOT NULL default '',
  `time` int(20) NOT NULL default '0',
  KEY ip (ip)
) TYPE=HEAP;

CREATE TABLE plugin_flowview_devices (
				  id int(12) NOT NULL auto_increment,
				  name varchar(64) NOT NULL,
				  folder varchar(64) NOT NULL,
				  allowfrom varchar(32) NOT NULL default '0',
				  port int(12) NOT NULL,
				  nesting varchar(4) NOT NULL default '-1',
				  version varchar(12) NOT NULL default '5',
				  rotation int(12) NOT NULL default '1439',
				  expire int(3) NOT NULL default '7',
				  compression int(1) NOT NULL default '0',
				  PRIMARY KEY  (id),
				  KEY folder (folder)
				) TYPE=MyISAM;

INSERT INTO plugin_flowview_devices (name, folder, port) VALUES ('Default', 'Router', 2055);

CREATE TABLE `plugin_flowview_queries` (
				  `id` int(12) NOT NULL auto_increment,
				  `name` varchar(255) NOT NULL,
				  `device` varchar(32) NOT NULL,
				  `startdate` varchar(32) NOT NULL,
				  `starttime` varchar(32) NOT NULL,
				  `enddate` varchar(32) NOT NULL,
				  `endtime` varchar(32) NOT NULL,
				  `tosfields` varchar(32) NOT NULL,
				  `tcpflags` varchar(32) NOT NULL,
				  `protocols` varchar(8) NOT NULL,
				  `sourceip` varchar(255) NOT NULL,
				  `sourceport` varchar(255) NOT NULL,
				  `sourceinterface` varchar(64) NOT NULL,
				  `sourceas` varchar(64) NOT NULL,
				  `destip` varchar(255) NOT NULL,
				  `destport` varchar(255) NOT NULL,
				  `destinterface` varchar(64) NOT NULL,
				  `destas` varchar(64) NOT NULL,
				  `statistics` int(3) NOT NULL,
				  `printed` int(3) NOT NULL,
				  `includeif` int(2) NOT NULL,
				  `sortfield` int(2) NOT NULL,
				  `cutofflines` int(4) NOT NULL,
				  `cutoffoctets` varchar(8) NOT NULL,
				  `resolve` varchar(2) NOT NULL,
				  PRIMARY KEY  (`id`),
				  KEY `name` (`name`)
				) TYPE=MyISAM;

CREATE TABLE `plugin_flowview_schedules` (
  `id` int(12) NOT NULL auto_increment,
  `enabled` varchar(3) NOT NULL default 'on',
  `sendinterval` int(20) NOT NULL,
  `lastsent` int(20) NOT NULL,
  `start` datetime NOT NULL,
  `email` text NOT NULL,
  `savedquery` int(12) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `savedquery` (`savedquery`)
) TYPE=MyISAM;

