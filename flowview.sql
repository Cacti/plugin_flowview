
CREATE TABLE plugin_flowview_dnscache (
  ip varchar(32) NOT NULL default '',
  host varchar(255) NOT NULL default '',
  `time` int(20) NOT NULL default '0',
  KEY ip (ip)
) TYPE=HEAP;

CREATE TABLE `plugin_flowview_devices` (
  `id` int(12) NOT NULL auto_increment,
  `name` varchar(64) NOT NULL,
  `folder` varchar(64) NOT NULL,
  `from` varchar(32) NOT NULL default '0',
  `port` int(12) NOT NULL,
  `nesting` varchar(4) NOT NULL default '-1',
  `version` varchar(12) NOT NULL default '5',
  `rotation` int(12) NOT NULL default '1',
  `expire` int(3) NOT NULL default '7',
  PRIMARY KEY  (`id`),
  KEY `n` (`nesting`),
  KEY `folder` (`folder`)
) TYPE=MyISAM;

INSERT INTO `plugin_flowview_devices` VALUES (1, 'Defualt', 'Default', '0', 2055, '-1', '5', 1, 7);