# flowview

This plugin allows you to view NetFlow data from inside of Cacti and 
to generate Email based reports of the data.

# Features

* Ability to view Flow Data in Table Form or three Chart Forms.
* Parallel Query Execution to increase the speed of report generation.
* Support for Shard Query and Horizontal Scaling through use of technologies 
  such as MariaDB MaxScale.
* Tracking of IP addresses that do not resolve through DNS by using ARIN's
  whois service.
* Customizable Scheduled Reports.
* Starting with the 4.x version, the ARIA database format will be the default
  for historical raw tables.  The ARIA format is crash safe, and almost
  5x faster than InnoDB.  The live raw table will always be InnoDB to support
  multiple concurrent inserts, but once that table ages out, it will be
  converted to the ARIA engine by default.
* Starting with the 4.x version, the DNS cache will be manageable.  Therefore,
  if you have intermittent DNS resolution issues, you will be able to view 
  and manage those entries in the cache.
* Starting with the 4.x version, you will be able to define a custom unregistered
  domain for local network IP addresses.  This is intended for home users who
  are just trying to see what type of activity is going on on their home
  network.
* Two partitioning methods for raw tables including Hourly and Daily partitioning.
  For larger commercial type deployments, using Hourly partitioning is going
  to be recommended.
* Starting with the 4.x version, sharding results will be cache to increase
  subsequent queries by various users under various conditions.


# Watch our video on YouTube!

[![Video ](http://img.youtube.com/vi/Kic-nY-RThA/0.jpg)](https://youtu.be/Kic-nY-RThA "Flowview Video")



# Installation

To install the plugin, simply copy the plugin_flowview directory to Cacti's plugins
directory and rename it to simply 'flowview'.

Copy config.php.dist to config.php and set the correct db connection.  The
Flowview plugin supports using the Cacti database or a third party database
as the Flowview database can becomee very large over time and may have
differing scalability requirements.

Once this is complete, go to Cacti's Plugin Management section, and Install 
and Enable the plugin.

There are a number of settings that are important that you can find under
Console > Configuration > Settings > Flowview.

The minimum version of Cacti required for this plugin will be Cacti 1.2.27.
However, you will have to install the patch to Cacti's database API in this
commit: c11405e584c012f675fb420acf78bcecc7d02d9f.

If you are running an older version of Cacti, you may run Flowview, but you
will not be able to leverage the MaxScale Shard Query feature.

If you are wanting to take advantage of the Shard Query implementation of 
Flowview, you must setup your Database so that it replicates itself to
from one to many slave servers.  Then,  you must setup MaxScale.  Note that
MaxScale is under a BSL license.  Therefore, if you wish to scale beyond
two servers, in a commercial setting, you will need to reach out to MariaDB.com
to obtain a license.

You can find real good tutorials at the following links.  Some of the information
may be out-dated in these links.  For example, the backup is super fast when
using the --parallel=X option, but the location of the file with the GTID of
the master has changed, and the format has changed slightly in the 11.x version
of MariaDB.  Additionally, you have to add additional grants for both MaxScale
and MaxScale Monitoring that the tutorials do not fully call out.

If found that the best way to setup a replica was to use mariabackup using the
options that they called out in the documentation.  It really simplifies the
setup.

* MariaDB Replication Setup:
https://mariadb.com/kb/en/setting-up-a-replica-with-mariabackup/
* MariaDB Backup and Restore for Replication Setup:
https://mariadb.com/kb/en/full-backup-and-restore-with-mariabackup/
* MaxScale Setup:
https://mariadb.com/kb/en/maxscale-24-02tutorials/
* MariaDB SSL Setup: (No longer required as of MariaDB 11.3)
https://mariadb.com/resources/blog/mariadb-maxscale-2-1-and-ssl-certificates/

## Required:

Before you start, with this version of Flowview, you have to ensure that you are
at MariaDB 10.5 or above.  Cacti has been shown to support MariaDB upto 11.4.x. 
MySQL 8.0+ is required. Unfortunately, MySQL does not support Aria Tables and will
therefore have lower performance than MariaDB for scanning data.

## Galera Cluster Replication (Aria Tables)

Flowview's raw flow tables use the Aria storage engine. If you replicate your
database with MariaDB Galera Cluster (rather than standard replication/MaxScale
above), be aware that Galera does **not** replicate non-InnoDB storage engines
by default, so Aria tables will silently fail to replicate to other cluster nodes.

MariaDB Galera Cluster (10.6.0+) offers `wsrep_mode=REPLICATE_ARIA`, which enables
replication of DML updates to Aria tables. However, **MariaDB's own documentation
still marks this option as experimental and states it should not be relied upon in
production systems.** There is currently no MariaDB version that officially/fully
supports Aria table replication under Galera. See
[Cacti/plugin_flowview#253](https://github.com/Cacti/plugin_flowview/issues/253)
for background and current tracking status.

Then, Install flowview just like any other plugin, just copy it into the plugin
directory, and Use Console > Plugin Management to Install and Enable.

This version of the flowview plugin no longer requires flowtools or
OpenFlashCharts.  In fact the entire import and reporting process is handled
through php and uses JavaScript based charting already available in Cacti.

Note that additionally, you must install the Linux utility `ss` if it is
not already installed.  Netstat will help Cacti determine if the `flow-capture`
service in question is actually running.

After installing, you should set your partitioning and retention settings from
Console > Configuration > Settings > Flowview.  There is a flowview section there
that you can customize.

Next you have to setup your Cacti server as a Flowview sink from your various
sources.  Then, from Flowview > Listeners, you must add the various listeners
for all your flow-capture sources.  It's critical that you specify the correct
port, and if there is to be any filtering, having a value other than 0 for the
allowed devices.

You must then setup the init.d or systemd service to receive captured data and
transfer into the Cacti database.  Check the README.md in the service folder to
describe this process.  Any time you add a new listener, you must restart this
service.

## Cacti compatibility

If you are running a version of Cacti below 1.2.31, please add the function
below to the `applySkin()` function in `include/layout.js` to enable the Cancel
buttons on forms to work:

```js
$(document).off('click.cactiReturnTo', '.cactiReturnTo')
    .on('click.cactiReturnTo', '.cactiReturnTo', function(event) {
        event.preventDefault();
        cactiReturnTo($(this).attr('data-url'));
    });
```

## Upgrading

After upgrading the plugin (when new plugin files have been copied into place),
restart the flow-capture service so it runs the updated capture/transfer code:

```
sudo systemctl restart flow-capture
```

(or the equivalent `service`/`init.d` command on non-systemd systems).  The
plugin's own database tables are upgraded automatically in the background on the
next Console page load.

## System Tuning

You may be required to increase some system defaults such a max connections
and UDP buffering to achieve high performance.  See your operating systems
documentation on the use of the sysctl to increase somaxconn and your 
max udp buffer space.  You can store your changes in /etc/sysctl.d/cacti.conf. 

## Automatic Flow Version Detection:

The new Cacti based flow-capture script will auto-detect either V5, V9 or IPFIX
flows automatically.  So, can dynamically switch these streams versions without
issue and you can have multiple streams coming into the same port at the same
time.  However, we recommend you have you watch the CPU utilization of the 
flow collector processes, and if they start consuming near 100% utilization,
you should add more listeners to spread the load.

## Automatic Domain Resolution:

The flow-capture script will receive the flow data, and attempt to resolve the
domain names of the flows.  In the case that an IP Address does not properly
resolve to a domain, Cacti automatically queries IANA to find the owner and
assigned as 'assumed' domain for those flows.  If your Cacti server can not
reach IANA, then those IP's will simply not be resolved.

## Upgrading from Prior Versions

Since the current release does not leverage flow-capture, you will need to
migrate your existing flow data into the Cacti database.  Before you perform
this migration, ensure that your Cacti system, has enough space to handle all
the flow data.  You should check the size of your existing flows, and then
verify that you have enough space to handle the data inside of a MySQL database.

Once you have done this, simply run the 'cli/import_flows.php' script and all your
legacy flow data will be imported.  Remember, it's important that you define
your partitioning scheme ahead of time, especially if you have large quantities
of flow data streaming into the Cacti server.

## Pre-Building Query Results

The Parallel Query caches the result of each fully covered partition, so
reports over older data open quickly once a partition has been scanned.  To
build that cache ahead of time, run the following from the plugin's `cli`
directory with the Parallel Query enabled (Max Concurrent Threads of 2 or
more under the FlowView Settings):

```
php cli/materialize_queries.php
```

The script runs every saved query over every flow partition that has rotated,
once with NAT on and once with NAT off.  The partition still receiving flows is
never cached, as a result cached for it would be wrong as soon as more flows
arrive.  Use `--query-id=ID[,ID...]` or
`--nat=on|off` to narrow the run, and `--dry-run` to see what would be run.
Partitions that are already cached and still match their data are skipped,
while partitions that have received more flows since, or have been dropped,
are rebuilt.  Only one run may be active at a time, for up to 8 hours, unless
`--force` is used.  `--status` lists the parallel queries that are running now
and the Filter each one is for, with its progress.

Before it scans anything the script gets the partitions ready.  A query waits
for an ALTER TABLE that is running on the partition it scans, and a long one
ends the query at the Maximum Run Time.  So the script waits for an alter that
is already running on a partition it needs, for instance from the schema
upgrade, and then does the alters that are still due itself, one partition at a
time.  The partition still receiving flows is left alone.  The post-NAT columns
are not added, as that is a deliberate choice for large installs.  The script
notes the partitions that do not have them, and they have no NAT data until
`flowview_upgrade_nat_columns.php` is run.

You can also start it from the Filters page by selecting one or more Filters
and choosing the 'Materialize View' action.  It runs in the background after
you confirm, and the result is written to the Cacti log.  The 'NAT Shards' and
'Non-NAT Shards' columns on that page show how many partitions of each Filter
are already cached, with NAT on and with NAT off, out of the total shown in the
'Partitions' column.

New partitions are materialized automatically.  Each poller cycle records the
newest partition, which is the one still receiving flows, and materializes the
partitions that have rotated since the previous cycle for every Filter, with
NAT on and off, in the background.  The first cycle only records the newest
partition, so run the script once to materialize the existing ones.  The
'Materialization' column on the Filters page shows the result for each Filter,
as Required, Running or Up to date.  The partition still receiving flows is not
materialized automatically, so it does not count.

When you save a
Filter in a way that changes the data it materializes, such as its report,
device, exporter, or any address, port, interface, AS, protocol, flag or TOS
restriction, you are advised to re-materialize it.

# Possible Bugs?

If you figure out this problem, goto GitHub and create a pulls request or open
an issue.

# Future Changes

Got any ideas or complaints, please see the Cacti forums or GitHub for a
resolution.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.
