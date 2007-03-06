
CREATE TABLE plugin_flowview_dnscache (
  ip varchar(32) NOT NULL default '',
  host varchar(255) NOT NULL default '',
  `time` int(20) NOT NULL default '0',
  KEY ip (ip)
) TYPE=HEAP;