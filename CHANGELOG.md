# Changelog

--- 3.4 ---

* issue#66: Cacti flowview collector errors
* issue#71: Flowview no data were displayed
* issue#73: Timespan never saved when editing a filter
* issue#75: Cacti PHP Errors when select report filter Source AS
* issue#80: Error messages when generating a report with the flowview plugin
* issue#83: Cacti 1.2.23 and Flowview dev 3.3
* issue#84: Support more than one router per FlowView listener
* issue#85: Report Filter Listener Print All Rows Not Working
* issue#88: Create new Custom Flow Viewer Report
* issue#95: Multiple Deprecation errors associated with PHP8.x
* issue#100: Change the name of config.php to something like config.php.template
* issue#106: Properly detect the Cacti base on Ubuntu and Debian
* issue#118: Protocol filter not working
* issue#121: When you return to the FlowView tab, your navigation will be broken till the first click
* issue: Flowview DNS Setting removed by accident
* issue: When changing the partition type, it does not take from the GUI right away
* issue: Sort field was being lost between selections
* issue: Dont fail to find flowview_connect() if the plugin is disabled
* issue: Make Domains only Domains/Hostnames only to remove confusion
* issue: Rework the SystemD startup process to use Cactis registration and unregistration functions
* feature: Make it optional to query Arin to track Big Tech
* feature: Allow Flowview to remember your last filter when re-entering the FlowView page
* feature: Allow selection of the listener on the FlowView filter
* feature: Redesign flow_collectors.php for formalize support for IPFIX in Flowview
* feature: Store peers that connect to listeners for review and tagging
* feature: Inform the user of how many listener streams are present and their status
* feature: Track detected template definitions in the database and provide a way to view them
* feature: Add color to Bar Charts
* feature: Disable Charts when viewing printed reports
* feature: Show visual indicator that the filter has been saved
* feature#9: Support Pie Charts Instead of Bar
* feature#97: Add FreeBSD service control
* feature#112: Provide a means to view the DNS Cache and Prune/Edit Entries
* feature#117: Allow guest account access
* feature#120: Save Charting Options when you Save a Filter
* feature#122: Charts should have a Graph Title that Matches the Report Type
* feature#123: Support Setting the Default Chart Height
* feature#124: Support Treemap Charts instead of Bar
* feature#125: Perform Schema Upgrades in the Background
* feature#126: Cache ARIN Responses for reference
* feature#127: Show Stream Fowview Versions being Received by the Receiver 
* feature#128: Rename the flowview settings table for more clarity


--- 3.3 ---

* issue#61: IPFIX Errors in Cacti Log
* issue#62: Problem with ` symbol in SQL queries
* issue: Upgrade was running repeatedly due upgrade using legacy variable
* feature: Update FlowView to use Bulletin Board Charts
* feature: Minimum Cacti requirement 1.2.17 for Bulletin Board

--- 3.2 ---

* issue: Flowview raw table has invalid unique key that blocks the insert of
  data

--- 3.1 ---

* issue#58: Flowview cannot enable in Cacti
* issue#59: Flowview Data wont display in CACTI
* issue: Results Cache not working
* feature: Support Cacti Format Files in FlowView

--- 3.0 ---

* issue#31: In systemctl status : CMDPHP: ERROR: A DB Exec Failed!, Error: Unknown column 'INF' in 'field list'
* issue#32: Netflow v9 - Netflow source not providing either prefix or nexthop information
* issue#33: cannot view flows
* issue#34: recurring updates for plugin_flowview_ports
* issue#35: Error when flowview raw tables do not exist
* issue#36: function flowview_get_owner_from_arin should be split for ipv4/ipv6
* issue#39: Using v9 Netflow source, flowview reports errors
* issue#40: Creating Flowview filters fails
* issue#41: Enhancement - Unnecessary Dialog Box: "Opeartion Successful - Select a filter to display data."
* issue#42: Firewall opened, Listener reports Down, tcpdump showing data, Systemd service running
* issue#43: NaN and Division by Zero errors
* issue#44: Error after install 'flowview': sizeof()
* issue#45: FlowView tab missing
* issue#46: Schedules dont work
* issue#47: flowData not showing data
* issue#48: Missing Code in ip_filter function
* issue#49: Flowview IPFIX throwing errors
* issue#50: Flowview errors cause it to become automatically disabled
* issue#51: FlowView Throws Errors When No Filters Exist
* issue#52: init.d script kills poller
* issue#53: Division by zero errors viewing tables
* issue#55: Netflow V9 Errors on CACTI
* issue#56: Sort Fields for Filters Not Saved Correctly
* issue: Partition tables were not being pruned.
* issue: Make the flow_collector.php resilient to loss of the database server.
* feature: New database design.  Support for v5, v9, IPFIX flows transparently
* feature: Remove Open Flash Charts and use C3 Charts instead
* feature: Reworked user interface
* feature: Units files for systemd systems for flow-capture service
* feature: Remove use of Flow Tools
* feature: Support IPv4 and IPv6
* feature: Support daily and hourly partitioning
* feature: Run Schedules in Background
* feature: Allow Saving of some Filter Information from Flowview

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

-----------------------------------------------
Copyright (c) 2004-2024 - The Cacti Group, Inc.
