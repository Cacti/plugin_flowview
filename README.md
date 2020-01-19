# flowview

This plugin allows you to see reports based off the data in your Netflow flows.

# Features

Fully customizable reports

# Installation

## Required:

First, make sure you have the plugin architecture installed.

Then, Install just like any other plugin, just copy it into the plugin directory,
and Use Console -> Plugin Mangement to Install and Enable.

This version of the flowview plugin no longer requires flowtools or 
OpenFlashCharts.  In fact the entire import and reporting process is 
handled through php and uses JavaScript based charting already available in 
Cacti.

After installing, you should set your partitioning and retention settings from 
Console > Configuration > Settings > Misc.  There is a flowview section there 
that you can customize.

Next you have to setup your Cacti server as a FlowView sink from your various 
sources.  Then, from FlowView -> Listeners, you must add the various listeners 
for all your flow-capture sources.  It's critical that you specify the correct 
port, and if there is to be any filtering, having a value other than 0 for
the allowed devices.

You must then setup the init.d or systemd service to receive captured data
and transfer into the Cacti database.  Check the README.md in the service
folder to describe this process.  Any time you add a new listener, you must
restart this service.

## Automatic Flow Version Detection:

The new Cacti based flow-capture script will auto-detect either V5, V9 or V10
flows automatically.  So, can dynamically switch these streams versions without
issue.  However, we recommend you have one receiver per flow source, and that
you feed multiple streams to the same receiver port.  The flow-capture script
also detects IPv4 and IPv6 addresses automatically.

## Automatic Domain Resolution:

The flow-capture script will receive the flow data, and attempt to resolve
the domain names of the flows.  In the case that an IP Address does not
properly resolve to a domain, Cacti automatically queries IANA to find the
owner and assigned as 'assumed' domain for those flows.  If your Cacti
server can not reach IANA, then those IP's will simply not be resolved.

## Upgrading from Prior Versions

Since the current release does not leverage flow-capture, you will need
to migrate your existing flow data into the Cacti database.  Before you
perform this migration, ensure that your Cacti system, has enough space
to handle all the flow data.  You should check the size of your existing
flows, and then verify that you have enough space to handle the data
inside of a MySQL database.

Once you have done this, simply run the 'import_flows.php' script and
all your legacy flow data will be imported.  Remember, it's important
that you define your partitioning scheme ahead of time, especially if
you have large quantities of flow data streaming into the Cacti server.

# Possible Bugs?

If you figure out this problem, goto GitHub and create a pulls request or open an issue.

# Future Changes

Got any ideas or complaints, please see the Cacti forums or GitHub for a resolution.

# Changelog

--- 2.1 ---
* issue: Prepare for sunrise theme in 1.1.17
* issue: Clean up the filter logic to preserve values
* issue: Make the graph size auto-detect screen size
* issue: Make reports sort properly

--- 2.0 ---
* feature: Support for Cacti 1.0
* feature: Support for Ugroup Plugin
* feature: Use either the OS' DNS or Alternate
* feature: Add strip domain capabilities
* issue#5: division by zero in flowview_devices.php
* issue#7: init script not functional
* issue#11: Increase memory limit for flowview_process.php
* issue: Not supporting Protocols correctly and Prefix/Suffix
* issue: Some W3C Validation Changes
* issue: Table plugin_flowview_devices wrong engine
* issue: Correcting issues with the flow-capture script
* issue: Update text domains for i18n

--- 1.1 ---
* issue: FlowView Settings were hidden for some reason
* issue: flow-capture script incomplete

--- 1.0 ---
* compat: Making compatible with 0.8.7g
* feature: Allow sending emails on demand
* feature: Add SaveAs, Delete, Update to UI
* feature: Add a Veiwer Only Permission Level
* feature: Add a Title for Scheduled Reports
* feature; Re-tool many reports into pure HTML
* feature: Add Graphs for Flows, Bytes, and Packets
* feature: Support sortable tables
* feature: Support excluding outliers from report
* issue: Rename 'View' tab to 'Filter'
* issue: Rename 'Devices' to 'Listeners'

--- 0.6 ---
* compat: Now only PA 2.0 compatible
* issue: Fix for IE and saving Queries
* issue: Fix for Error when no devices

--- 0.5 ---
* feature: Add flow-tools replacement startup script to allow launching of multiple processes based upon devices added
* feature: Add Saved Queries
* feature: Change Sort field to be drop downs with column names
* feature: Add ability to schedule and email out Netflow Scans
* issue: Fix issue with start and stop times close to midnight not loading the proper days data

--- 0.4 ---
* issue: Minor fix for when using flow path "/"
* issue: Fix Cacti 0.8.7 Compatibility

--- 0.3 ---
* feature: Add time support for relative times (NOW, -1 HOUR, -2 DAYS, -10 MINTUES) Must leave Date blank for these to work properly
* feature: Add device name to path if present

--- 0.2 ---
* feature: Add DNS Support

--- 0.1 ---
* Initial release

