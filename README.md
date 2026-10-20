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

Once you have done this, simply run the 'import_flows.php' script and all your
legacy flow data will be imported.  Remember, it's important that you define
your partitioning scheme ahead of time, especially if you have large quantities
of flow data streaming into the Cacti server.

# Possible Bugs?

If you figure out this problem, goto GitHub and create a pulls request or open
an issue.

# Future Changes

Got any ideas or complaints, please see the Cacti forums or GitHub for a
resolution.


## Cisco Apps (NBAR)

Cisco Apps is a separate application report within FlowView. The current NBAR
integration reads application IDs and names from NetFlow v9 data and options
templates, including IPv4 and IPv6 records. IPFIX application decoding is not
implemented by this integration. Existing FlowView reports retain their normal
collector and report paths.

Configure a FlowView listener for each export stream and configure the router to
export application-bearing records to it. The report's Setup guide contains a
Cisco 1100 Series example with placeholders for the collector, UDP port and router
interfaces. Check Cisco documentation for the exact platform and IOS XE release.
Creating a listener alone does not enable NBAR on the router.

The report offers Top applications, Conversations and Connections. Filter by IP
matches either endpoint. Conversations requires the account's Internal Networks
settings to identify hosts and upload/download direction. Combine Options accepts
two or three captures; leave them all unselected to save networks without combining.
Choose one observation point per traffic path: overlapping LAN/WAN captures add
both copies and can overcount traffic.

STATUS describes recent retained application records for each selected capture,
independently of historical report filters. A quiet capture can show no recent
NBAR despite a working exporter. It is not a connectivity test.

First seen and Last seen are the earliest/latest retained observation minutes in
the selected period. They do not prove continuous activity, or give exact connection
start/end times. Missing dates are shown as Unavailable. Average bandwidth divides
data volume by the full selected period. Column sorting applies to the displayed
page; resizing is saved in that browser.

NBAR History Retention is separate from the existing FlowView Data Retention Policy.
It offers 1, 3, 7, 14 or 30 days, with a seven-day default. Timespan choices and custom
ranges respect this setting. The existing Cacti poller maintains summaries and
performs bounded cleanup; no extra scheduled task is required. A shorter retention
setting cannot be undone to recover deleted history. If polling stops, maintenance
and cleanup stop too. Shared maintenance locks are scoped to the FlowView database.

## Cisco Apps (NBAR) historical Connections

Connections reads the automatically maintained daily history for each selected capture.
Opening a report or changing its IP filter does not queue a new complete connection build.
The normal FlowView poller maintains this history; no additional cron job is required.

Pages contain up to 100 connections, ordered by total data volume across the selected
captures and period. Indexed candidates are checked against every selected daily segment
so byte, packet and record totals remain exact. Partial days use hourly or raw data.
Previous, Next and a numeric page field provide navigation without a large dropdown or
an expensive full-result count. Very deep pages or broadly tied results can still require
more work; a shorter period or IP filter reduces that work.

A small account-scoped session cache holds nearby pages for 120 seconds for past periods
and 30 seconds for periods including current data. This is separate from retained history.
The existing authenticated FlowView realm controls access; capture selections and filters are included in cache keys.

## Reusable NBAR daily summaries

Top Applications, Conversations and Connections share daily summaries built automatically
per capture, independently of report filters and Cacti accounts. Calendar days run from
local midnight to the following midnight in the Cacti server timezone, including daylight
saving changes. Completed hours are added to today's summary in bounded transactions.
Completed days are reused; late exports invalidate affected summaries and trigger a rebuild.
Partial days and unpublished or changed intervals fall back to hourly summaries or raw records.
IP, application and IP-version filters are applied to the preserved summary fields.

Daily history follows Cisco Apps (NBAR) History Retention, currently selectable up to
30 days. Retention is a rolling data window, not a new retention period beginning each time
a report is opened. Daily summaries are shared source data and are reused for different
periods, filters and users, subject to listener permissions. Connections reads pages from
this history rather than preparing another full copy for every selection.

-----------------------------------------------
Copyright (c) 2004-2026 - The Cacti Group, Inc.

NBAR daily summary materialization and observation-date backfill require MariaDB statement deadlines. On MySQL 8, these workers safely defer their DML rather than running without a bound; reports continue through hourly/raw sources. Exact counts use the MySQL SELECT execution-time hint.

New NBAR records retain the actual exporter address through hourly and daily summaries. Legacy rows keep their counters with an unknown exporter; an exact single-address listener may still resolve their names, but earlier multi-exporter rows cannot be attributed retrospectively.

NBAR maintenance is launched independently of the poller. A registered supervisor runs the PHP maintenance child with a 90-second process deadline. MariaDB hourly mutations also set and restore a verified 20-second statement deadline. MySQL hourly builds remain available only inside the supervised child; raw records remain the fallback until a checkpoint is published.

NBAR timespan requests keep their session state separate from Flow Reports. Conversation totals exclude IPv4 and IPv6 multicast endpoints, and feed lookup failures are shown separately from empty captures. Retention maintenance drains additional batches across raw, hourly and daily tables within a shared ten-second cleanup budget, under the supervised worker deadline.

Template decoding rejects zero-length fields and invalidates the affected cached template until a valid refresh arrives. Chart fallback labels are grouped by their complete expression, so applications without learned names keep separate slices.

The NBAR template cache retains at most 256 templates / 8,192 field definitions in total and 64 templates / 2,048 fields per exporter, across all source IDs. Least recently used entries are evicted and inactive templates expire after 30 minutes (removed lazily on packet activity). Evicted templates are learned again from the next exporter refresh. Numeric data fields must fit unsigned 64-bit values; options scope fields are decoded separately and are not treated as counters.

NBAR maintenance supervision uses PHP's direct argv-array process API rather than Cacti's version-dependent timeout command interface, so startup and hard child termination work on the minimum supported Cacti 1.2.29 and newer releases. Both output pipes are drained in bounded chunks.
