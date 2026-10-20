# Changelog

--- develop ---

* bug: Never cache the partition that is still receiving flows.  A full run included it, and as its flows ended before the end of the range it was scanned as a full scan and its result was cached, which was wrong as soon as more flows arrived, and counted it as materialized.  Whether a shard is a full scan is now decided in flowview_is_full_scan(), which refuses the newest partition, so a report that covers it cannot cache it either, and it is scanned for the range as it is asked for.  cli/materialize_queries.php only materializes the partitions that have rotated, and removes what was cached for the live partition before, so NAT Shards and Non-NAT Shards can no longer equal the number of Partitions

* bug: Make the earliest partition, and the partitions that rotate, get cached by a materialization.  A shard is only cached when the range starts before the earliest flow in the partition, and a flow can start before the window of the partition it was written to, but the range was padded by only a second, so the oldest partition, or a rotated one on its own, was never cached and its Filter stayed Required.  The start is now padded by a day, which only adds partitions that already exist and are cached, and the summary now shows the first and last partition instead of the padded range.  Run the materializer again to cache what was missed

* feature: Say which partitions a Filter is not materialized for, with NAT and without, in the tip of its Required and Running pill

* bug: Fix the Filters page failing with "3 arguments are required, 2 given" once a query was running.  The Running: N pill and its tip, and the Parallel Query timeout message, passed the text domain where Cacti's __() expects the values to put in the text, so the tip crashed, the pill would have read "Running: flowview", and the timeout would have said 0 seconds.  The values now come before the domain, as elsewhere in the plugin

* bug: Stop cli/materialize_queries.php from reporting each ALTER TABLE twice, once from its own progress line and once from the log message that is also printed.  The alter is still written to the Cacti log, and the schema upgrade, which has no other report, still shows it.  The logged text no longer has the extra space in it

* feature: Show the number of queries of each Filter that are running now in a second pill, in the style of a disabled device and reading Running: N, beside the status pill on the Filters page, with how many are with NAT and without in its tip.  The process table only has the id of a running request, so a running query is matched to a Filter by the cache key its map query is stored under, which is worked out from the Filter, and there is no pill when none are running

* feature: Add --status to cli/materialize_queries.php to list the parallel queries that are running and the Filter each one is for, with its progress

* bug: Show the Running status on the Filters page while a parallel query is still working, not only while cli/materialize_queries.php itself is registered.  The query keeps going after the script has ended, for instance when it timed out, so the status now comes from the runner and shard worker (db_query_<id> and db_shard_<id>) registrations in the process table, whoever started them.  A registration counts while it is recent, or when its process is still alive, so a row left behind by a killed process does not.  A new materialization is not started while one is running

* bug: Make the Parallel Query timeout actually stop the runner and shard workers.  They register as db_query_<id> and db_shard_<id>, but the cleanup, and the runner's own termination handler, looked for db_query<id> and db_shard<id>, so they were never found: a timed out query left its processes running and a terminated one left its row in the process table

* bug: Stop the poller from starting the same ALTER TABLE ENGINE conversion on a partition every cycle.  Until a long conversion finishes the partition still reports its old engine, so each cycle started another that waited for the first and then rebuilt the table again, which also held up every query scanning that partition.  The conversion is skipped while an ALTER TABLE is already running on the partition, and cli/materialize_queries.php checks a partition again just before altering it, so it is never rebuilt twice

* bug: Make a Parallel Query that reaches the Maximum Run Time report it in words when run from a script, such as cli/materialize_queries.php, instead of printing the JavaScript meant for the web page; the message is also written to the Cacti log, a script now exits with a failure, and the web page is unchanged

* feature: Have cli/materialize_queries.php get the partitions ready before it starts the Parallel Query.  It waits for an ALTER TABLE that is already running on a partition it needs, such as from the schema upgrade, since a query waits for it and then ends at the Maximum Run Time, and then does the alters that are still due itself, one partition at a time with progress shown.  The partition still receiving flows is left alone.  It also notes the partitions that have no NAT columns, as they have no NAT data.  What a partition needs altered is now decided in one place, flowview_get_partition_alter(), which the schema upgrade also uses, so the ALTER it issues is unchanged

* feature: Materialize the partitions that have gone cold automatically.  Each poller cycle flowview_process.php records the newest partition, which is the one still receiving flows, and starts cli/materialize_queries.php in the background for the partitions that have rotated since the previous cycle, for every Filter with NAT on and off.  Partitions that are already materialized and accurate are skipped.  When a materialization is already running, or the Parallel Query is not enabled, the record is left alone so a later cycle catches up.  The first cycle only records the newest partition, so older partitions are still materialized with cli/materialize_queries.php.  The script gained a --partitions option for this

* feature: Add a Materialization column, to the right of the Filter Name and its ID on the Filters page, with a tip explaining what materialization is, showing the automatic materialization status as a pill in the form of the device status: Required (down) when rotated partitions are not materialized yet, Running (recovering) while a materialization is in progress, and Up to date (up) once every cold partition is materialized with NAT on and off.  The ID column now follows the Filter Name

* feature: Show the number of partitions each Filter has stored in the Parallel Query cache, with NAT and without, as the NAT Shards and Non-NAT Shards columns next to the ID on the Filters page, along with a Partitions column holding the total number of flow partitions so the progress of a materialization can be followed; the cache keys are worked out from the Filter without running it (run_flow_query() gained a plan-only mode, and the map query hashing is shared with the parallel query request)

* feature: Add a 'Materialize View' action to the Filters page that, after confirmation, starts cli/materialize_queries.php in the background for the selected Filters (--query-id now accepts a comma separated list), and tells you when a materialization is already running

* feature: Recommend a forced re-materialization when a Filter is saved, from either the Filter form or the report page, with a change that alters what it materializes (report type, device, exporter, template, or any address, port, interface, AS, protocol, TCP flag or TOS restriction, plus the sort field of printed reports); display settings, the time range, cutoffs and NAT mode do not count

* feature: Add cli/materialize_queries.php, which runs every saved FlowView query through the parallel query for the entire range of flow partitions, once with NAT on and once with NAT off (--query-id, --nat, --dry-run and --force are optional), so the partition results are already cached when a report is opened; it registers with register_process_start() for 8 hours so only one run is active at a time, reuses cached partitions that still match their partition's data bounds, rebuilds the ones that have since gained flows or been dropped, and runs each query and NAT mode in its own process so one that reaches the Maximum Run Time does not end the rest

* dev: Move fixprivate.php, import_flows.php and repair_tables.php into the plugin's cli/ directory (listed as tombstones in manifest.json so upgrades remove the old copies); they now locate the Cacti root relative to their own location

* bug: Fix "Uncaught ReferenceError: timeshiftFilterLeft is not defined" when using the Shift Time Backward/Forward icons on the flow report filter; the plugin relied on undefined global functions, so the icons now call a plugin-owned timeshiftFilter() that shifts the From/To range by the selected interval through a new `timeshift` action and reloads the report

* fix: Make bundled Net_DNS2 constructor nullability explicit for PHP 8.4 without changing accepted arguments

* issue: Shorten the graph drilldown button title from 'View NetFlow Traffic In Range' to 'NetFlow' so it reads cleanly as a label in Cacti's graph action menu
* dev: Create the plugin's config.php from config.php.dist for the flowview install step in the integration-test workflow (removed immediately afterwards so the unit tests still run with no config file), so the install no longer fails with "needs configuration"
* feature: Add an optional FlowView Query Service (a long-running Go service under service/flowview-query) as a selectable Parallel Query Backend under FlowView Settings (default Legacy PHP Runner); when enabled, the parallel query parent runner (flowview_runner.php) delegates the whole query to the service over localhost instead of forking a PHP worker per shard, running the same cache-aware map-reduce (sharing parallel_database_query_shard_cache) and writing results back, with automatic fallback to the legacy runner when the service is unreachable

* bug: Load the pre-1.3 report compatibility shims (reports_run/reports_queue/reports_log_and_notify) only when the running Cacti is older than 1.3, decided with cacti_version_compare(CACTI_VERSION, '1.3', ...) rather than a function_exists() probe; because the plugin's functions.php is included during plugin init before core loads lib/reports.php, the function_exists() check ran while the now-native helpers were still absent, declared the shims, and core then fatally redeclared reports_log_and_notify() on 1.3.0 develop builds (falls back to function_exists() only when the Cacti version helpers are unavailable, e.g. the unit tests)
* security: Move the plugin's remaining inline event handlers to CSP-safe bindings so the pages no longer trip Cacti's Content-Security-Policy script-src-attr directive: confirmation Cancel buttons use the `cactiReturnTo` class, the database/filter selects are bound via change() in their ready blocks, and the main filter's sort/report selects and time-shift icons are bound in flowview_display_filter's ready block
* dev: Replace the translation-template CI check that regenerated locales/po/cacti.pot and compared it with a diff-based gate (tests/bin/check-i18n-pot.php) requiring cacti.pot to be updated only when a pull request adds, removes, or modifies an i18n function call
* issue: Prevent flow-capture from fatally erroring (TypeError in spl_object_hash) when flowview_db_check_reconnect() held a null shared-database handle; normalize a non-object connection to false so db_check_reconnect() checks and reconnects the default connection instead
* dev: Measure CI coverage with xdebug instead of pcov so the plugin's own sources are instrumented (pcov auto-scopes to the Composer root and skipped cacti/plugins/, leaving the patch-coverage gate with nothing to measure)
* dev: Enforce patch coverage of changed lines in CI and remove the inert COMPOSER_ROOT_VERSION env from the Pest step
* dev: Pin the CI database image and shared test dependencies, disable persisted checkout credentials, and limit workflow permissions and runtime
* issue#110: Add NAT (postNAT source/destination IP and port) support to the core raw flow schema, filters, and DNS resolution; see flowview_upgrade_nat_columns.php to backfill existing partitions
* security: Add a version-safe CSP nonce (`plugin_flowview_csp_nonce()`) to every inline `<script>` tag so pages stay compatible with Cacti's Content-Security-Policy nonce enforcement, while falling back cleanly on older Cacti releases that lack the `CactiSecureHeaders` class
* chore: Harmonize CI workflow, issue/PR templates, and PHP-compatibility test structure with the shared Cacti plugin baseline
* issue#261: Cacti 1.2.x flowview 5.0 update of 16.09 error on call db_check_reconnect inside flow_capture
* issue#240: Call to db_table_exists is incorrect in some cases
* issue: Killing processes does not occur when they are in D-State
* chore: Bring all first-party PHP files to PHPStan level 8 with zero errors and add native parameter/return type declarations throughout (behavior-preserving)
* fix: run_flow_query() used isset($sql_inner) as the statistical-vs-printed discriminator, but $sql_inner is always initialized so the check was always true and printed reports skipped the issue#110 NAT-safe SQL cleanup; corrected to a value check so printed reports now apply the NAT toggle
* fix: Flow Collector "unable to open port" FATAL log always showed an empty error because $errno/$errstr were never populated; now sourced from socket_last_error()/socket_strerror()
* fix: IRR database refresh no longer runs the present=0 cleanup DELETE when a database file download fails (the $files_broken guard was initialized but never engaged)
* fix: Correct a duplicate 'src_rdomain' array key in the Source/Destination Root Domain report column map that overwrote the Destination Domain column
* fix: Correct a stray "$" in the Source AS filter that read get_request_var() through an undefined variable-variable
* fix: TCP flag validation regex used an unescaped delimiter making it always invalid, rejecting every TCP flag filter value
* fix: load_data_for_filter()'s $start/$end were typed bool during the PHPStan pass, coercing the Unix timestamps passed by plugin_flowview_run_schedule() to true so scheduled reports queried around epoch second 1; restored the int|false contract
* fix: The DNS Cache list (flowview_databases.php) and Schedules list (flowview_schedules.php) passed include_form=true to html_header_sort_checkbox() while their own chk form was already open, emitting nested forms that could misdirect checkbox submissions and bulk deletes; reverted to false
* fix: IRR database refresh persisted the per-source serial after each individual file downloaded, so a later file failing left the refresh skipped on the next run (matching serial) with failed sections stuck at present=0; the serial is now advanced only after every file imports cleanly and every cleanup DELETE succeeds, batch insert failures are propagated through flowview_insert_irr_sections()/flowview_update_database(), and empty files are treated as broken

--- 5.0 ---

* issue#233: Colum Size Issues and Update Copyrights
* issue: Fix FreeBSD netstat detection
* issue: Running irr_tables.php from the UI cause plguin to disable
* feature: Add bind address option
* feature: Make downloading from the Internet Route Registry optional

--- 4.9 ---

* issue#216: Reports dont trigger when flowview is in a separate database
* issue#217: Update flow-capture script for Debian/Ubuntu
* issue#218: Changes in one or more IRR Database Causing Errors in FlowView
* issue#220: Cant dowload the external DB
* issue#223: Version requested for ORDER BY NATURAL_SORT_KEY(key)
* issue#229: Access to the wrong database for report_log and reports_queued
* issue: Unable to install when there is no config.php or config_local.php
* issue: When vendor does not follow the IPFIX specification, use the vendors length value
* feature: Support for Cacti 1.3

--- 4.8 ---

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
* issue#132: The CIDR syntax filtering does not return correct results
* issue#139: Remove the source port from the ex_addr as it breaks multiple things
* issue#141: Using specific DNS servers results in unpack errors.
* issue: Flowview DNS Setting removed by accident
* issue: When changing the partition type, it does not take from the GUI right away
* issue: Sort field was being lost between selections
* issue: Dont fail to find flowview_connect() if the plugin is disabled
* issue: Make Domains only Domains/Hostnames only to remove confusion
* issue: Rework the SystemD startup process to use Cacti registration and unregistration functions
* issue: Allowed incoming address range was not working
* feature: Make it optional to query Arin to track Big Tech
* feature: Allow FlowView to remember your last filter when re-entering the FlowView page
* feature: Allow selection of the listener on the FlowView filter
* feature: Redesign flow_collectors.php for formalize support for IPFIX in FlowView
* feature: Store peers that connect to listeners for review and tagging
* feature: Inform the user of how many listener streams are present and their status
* feature: Track detected template definitions in the database and provide a way to view them
* feature: Add color to Bar Charts
* feature: Disable Charts when viewing printed reports
* feature: Show visual indicator that the filter has been saved
* feature: Add SIGHUP signal handling to flow-capture and flow_collector.php
* feature: Add support for Net/DNS2 to improve alternate DNS resolution support
* feature: Allow DNS Cache to be purged with a single button press.
* feature: Append local domain to locally resolved addresses from the system DNS
* feature: Show supported or not supported status when viewing Flow Templates
* feature: Import all Internet Route Registry databases when they change
* feature: Support Notification Lists for Email
* feature: Support a Report Generation Timeout
* feature: Support running Email Reports in Parallel
* feature#9: Support Pie Charts Instead of Bar
* feature#97: Add FreeBSD service control
* feature#111: Add a Template Export Button to make sharing Template information with the Cacti Group Easy
* feature#112: Provide a means to view the DNS Cache and Prune/Edit Entries
* feature#113: Provide buttons 'New', 'Rename', 'Delete' from the FlowView Tab
* feature#114: Provide an External Address dropdown in the Filter Definition
* feature#116: Have flow-capture automatically restart collectors that have crashed
* feature#117: Allow guest account access
* feature#120: Save Charting Options when you Save a Filter
* feature#122: Charts should have a Graph Title that Matches the Report Type
* feature#123: Support Setting the Default Chart Height
* feature#124: Support Treemap Charts instead of Bar
* feature#125: Perform Schema Upgrades in the Background
* feature#126: Cache ARIN Responses for reference
* feature#127: Show stream FlowView versions being ingested
* feature#128: Flowview attempts to import all streams even those that are reporting other information that is not supported by flowview
* feature#143: Support tcp listener
* feature#144: Allow mapping of a local subnet to a named private domain for home users
* feature#145: Add the V9/IPFIX Template IDs to the flowview raw tables
* feature#146: Use prepared statements for Flowview Filters to protect against SQL injections
* feature#149: Expose the ex_addr as a filter option and resolve it through DNS
* feature#150: Introduce Parallel Query - Map Reduce to FlowView
* feature#154: Support Parallel Queries through a MariaDB or MySQL proxy such as MaxScale
* feature#155: Allow Choosing your Raw Storage Engine Format
* feature#156: Create Persistent Shard Result Cache
* feature#160: Use whois providers such as radb.net to find origin AS when Arin does not provide it
* feature#163: Restructure the DNS Cache page to include: ASNs, People, Routes, etc. per the Internet Routing Registry format RSP
* feature#164: Only update the Routes databases if they have changed as recommened at radb.net
* feature#179: Provide Link in Cactis Graph View to Flowview
* feature#180: Allow Report Raw Data to be Downloaded
* feature#181: Allow Raw Report Data to be Exported from FlowView Page
* feature#191: Make it possible to disable a Listener Service
* feature#192: Allow an Administrator to observe the backlog on their Flowview Ports to know of overload

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
Copyright (c) 2004-2026 - The Cacti Group, Inc.
