# GitHub Copilot Instructions

## Priority Guidelines

When generating code for this repository:

1. **Version Compatibility**: This is a Cacti plugin (`flowview`, version 5.0) targeting Cacti 1.2.28+
2. **Context Files**: Prioritize patterns and standards defined in this file (`.github/copilot-instructions.md`)
3. **Codebase Patterns**: When context files don't provide specific guidance, scan the codebase for established patterns
4. **Architectural Consistency**: Maintain plugin-based architecture extending Cacti core
5. **Code Quality**: Prioritize security, maintainability, and compatibility in all generated code

## Technology Stack

### Core Technologies
- **PHP**: Compatible with Cacti 1.2.x supported versions
- **Platform**: Cacti Plugin Architecture (Cacti 1.2.28+)
- **Database**: MySQL/MariaDB; supports a **dedicated flowview database** in addition to the main Cacti database (see `config.php.dist`/`database.php`), similar in spirit to other dual-database Cacti plugins
- **NetFlow**: Ingests NetFlow v5/v9/v10 (IPFIX) via `flow_collector.php`

### Key Dependencies
- Cacti core framework (`api_plugin_*`, `db_*`, `read_config_option()`)
- Optional MariaDB MaxScale for shard/horizontal scaling (`maxscale/`)
- `Net/` vendored network/IP helper classes; `service/` process-management scripts

## Project Structure

```
flowview/                     # Repository root (install to plugins/flowview/ in Cacti)
├── maxscale/                  # MariaDB MaxScale integration assets
├── Net/                        # Vendored IP/network helper classes
├── service/                     # systemd/service unit assets for flow_collector
├── themes/                       # CSS theme overlays
├── arrays.php                     # Configuration arrays
├── config.php.dist                 # Template for dedicated flowview database config
├── database.php                     # flowview_db_* wrapper (dual-database support)
├── flowview.php                      # Main NetFlow viewer UI
├── flowview_devices.php               # Device/exporter administration
├── flowview_filters.php                # Saved filter administration
├── flowview_databases.php               # Database/sharding administration
├── flowview_schedules.php                # Scheduled report administration
├── flowview_process.php                   # CLI: parallel query execution / report runner
├── flowview_cleanup.php                    # CLI: data retention cleanup
├── flowview_upgrade.php                     # Background schema upgrade runner
├── flow_collector.php                        # NetFlow v5/v9/v10 collector daemon
├── functions.php / functions-pre13.php         # Core UI + protocol decode helpers
├── import_flows.php                             # CLI flow file importer
├── run_schedule.php                              # CLI scheduled-report executor
├── INFO                                            # Plugin metadata (name, version, compat)
├── README.md
└── setup.php                                        # Plugin install/uninstall/upgrade hooks
```

## Naming Conventions

### Function Names
- **Plugin lifecycle/hook-registration functions** MUST be prefixed `plugin_flowview_`: `plugin_flowview_install()`, `plugin_flowview_check_upgrade()`, `plugin_flowview_version()`.
- **New plugin-owned functions** SHOULD use the `flowview_` prefix: `flowview_config_arrays()`, `flowview_setup_table()`, `flowview_connect()`. Preserve established subsystem prefixes such as `process_fv*`, `parallel_database_*`, and `display_*` when extending those APIs.
- Match the existing prefix used by the function you are editing; do not introduce a third naming scheme.

### Database Tables
All plugin tables match `plugin_flowview%` or `parallel_database%` (see `plugin_flowview_uninstall()`, which discovers them dynamically via `information_schema.TABLES` rather than a hardcoded `DROP TABLE` list).

## Code Style

### Indentation and Formatting
- **Tabs**: Use tabs (not spaces) for indentation throughout all PHP files.
- **Braces**: Opening brace on the same line for functions and control structures.
- **Spacing**: Space after control structure keywords (`if`, `foreach`, `while`).

### File Headers
Plugin-maintained PHP files MUST include the standard GPL v2 license header used throughout this repository (see `setup.php`), crediting "The Cacti Group"; preserve the original license headers for vendored dependencies such as `Net/`.

## Security Standards

### SQL Query Security
**ALWAYS use the `flowview_db_*` wrapper functions and prepared statements for variable values; never interpolate untrusted values into SQL. Dynamic identifiers (such as raw table names) cannot be bound, so validate or whitelist them before constructing the query.**

```php
// CORRECT
flowview_db_fetch_assoc_prepared('SELECT * FROM plugin_flowview_devices WHERE id = ?', array($id));

// WRONG - never do this
flowview_db_fetch_assoc("SELECT * FROM plugin_flowview_devices WHERE id = $id");
```

### Dedicated Database Awareness
Because flowview can point at a separate database connection, always use the `flowview_db_*`/`flowview_connect()` wrapper layer instead of core Cacti `db_*` functions for flowview's own tables — core `db_*` calls will silently target the wrong database when a dedicated flowview DB is configured.

### Input Validation
Use `get_filter_request_var()` / `get_nfilter_request_var()` for request input; never read `$_GET`/`$_POST` directly.

`get_filter_request_var()` (and its `gfrv()` shorthand) called with only the `$name` argument
(no regex/filter as the 2nd/3rd argument) already validates the value as numeric and returns it
as a **string** — it does not return an int, and it halts execution (raises an error/exits)
if the request value is not numeric. Because of this, do NOT cast its output to `(int)` when the
result is only used for string output (e.g. `print`/`echo`, string concatenation, embedding in
HTML/JS); the cast is redundant. Only cast when the value is genuinely used in an integer/numeric
context (e.g. arithmetic, strict `===` type comparisons).

## Database Operations

### Table Creation
Use `flowview_setup_table()`/`flowview_drop_table()` helpers in `setup.php` rather than ad hoc `CREATE TABLE`/`DROP TABLE` calls scattered across pages.

### Upgrade Handling
Schema upgrades run in the **background** via `flowview_upgrade.php` (spawned with `exec_background()` from `plugin_flowview_check_upgrade()`), since flow tables can be very large:

```php
function plugin_flowview_check_upgrade($force = false) {
	global $config;

	$info    = plugin_flowview_version();
	$current = $info['version'];
	$old     = db_fetch_cell('SELECT version FROM plugin_config WHERE directory="flowview"');

	if ($current != $old || $force) {
		$php_binary = read_config_option('path_php_binary');
		exec_background($php_binary, '-q ' . $config['base_path'] . '/plugins/flowview/flowview_upgrade.php');
		raise_message('flowview_upgrade', __('Please be advised the Flowview plugins Tables are being upgraded in the background...', 'flowview'), MESSAGE_LEVEL_INFO);
	}
}
```

## Galera/Aria Replication Compatibility Tracking

FlowView's raw flow tables use the Aria storage engine. MariaDB Galera Cluster does not fully
support replicating non-InnoDB storage engines out of the box (see
[Cacti/plugin_flowview#253](https://github.com/Cacti/plugin_flowview/issues/253)); Aria
replication is currently only reachable via the **experimental** `wsrep_mode=REPLICATE_ARIA`
option.

**Recurring check**: On any pull request opened at least one month after the previous pull
request, check the current MariaDB Galera Cluster documentation (the `wsrep_mode` system
variable and Galera known-limitations pages at https://mariadb.com/docs/galera-cluster/) for
whether Aria table replication has become officially/production supported and, if so, in which
MariaDB version(s). Update the "Current status" note below with the finding, the MariaDB
version(s) involved, and the date checked.

**Once confirmed supported** (MariaDB documentation no longer marks Aria replication under
Galera as experimental): add an application note to `README.md` stating the minimum MariaDB
version and any required configuration (e.g. `wsrep_mode=REPLICATE_ARIA`), then delete this
"Galera/Aria Replication Compatibility Tracking" section entirely — no further periodic checks
are needed once the support note lives in `README.md`.

**Current status (checked 2026-09-16)**: Still experimental. `wsrep_mode=REPLICATE_ARIA`
(introduced in MariaDB 10.6.0) allows DML updates on Aria tables to replicate, but MariaDB's own
documentation states this "is experimental and should not be relied upon in production systems."
No MariaDB version currently offers production-supported Aria replication under Galera.

## Internationalization

ALL user-facing strings MUST use `__()` with the `'flowview'` text domain.

## Plugin Architecture

### Plugin Hooks
Register all plugin hooks in `plugin_flowview_install()` (`setup.php`):

```php
api_plugin_register_hook('flowview', 'config_arrays',          'flowview_config_arrays',          'setup.php');
api_plugin_register_hook('flowview', 'draw_navigation_text',   'flowview_draw_navigation_text',   'setup.php');
api_plugin_register_hook('flowview', 'config_settings',        'flowview_config_settings',        'setup.php');
api_plugin_register_hook('flowview', 'poller_bottom',          'flowview_poller_bottom',          'setup.php');
api_plugin_register_hook('flowview', 'top_header_tabs',        'flowview_show_tab',               'setup.php');
api_plugin_register_hook('flowview', 'top_graph_header_tabs',  'flowview_show_tab',               'setup.php');
api_plugin_register_hook('flowview', 'page_head',              'flowview_page_head',              'setup.php');
api_plugin_register_hook('flowview', 'global_settings_update', 'flowview_global_settings_update', 'setup.php');
api_plugin_register_hook('flowview', 'graph_buttons',            'flowview_graph_button', 'setup.php');
api_plugin_register_hook('flowview', 'graph_buttons_thumbnails', 'flowview_graph_button', 'setup.php');

api_plugin_register_realm('flowview', 'flowview.php', __('NetFlow User', 'flowview'), 1);
api_plugin_register_realm('flowview', 'flowview_devices.php,flowview_schedules.php,flowview_filters.php,flowview_databases.php', __('NetFlow Admin', 'flowview'), 1);
```

### Required Configuration Before Install
`plugin_flowview_install()`/`plugin_flowview_check_config()` require either `config.php` or `config_local.php` to exist (derived from `config.php.dist`) before the database is set up; otherwise installation is blocked with `raise_message(..., MESSAGE_LEVEL_ERROR)`.

### Flow Collector Service
`flow_collector.php` runs as a long-lived daemon (see `service/`) that decodes NetFlow v5/v9/v10 packets and writes them via the `flowview_db_*` layer; be mindful of performance (avoid per-packet queries) when touching this file.

## Best Practices

1. Use `flowview_db_*` wrappers exclusively for this plugin's tables.
2. Keep collector/decode paths (`flow_collector.php`, `process_fv5/9/10()`) allocation- and query-light; they run per-packet.
3. Route schema changes through the background upgrade runner, not inline on a web request.
4. Wrap all user-facing strings with `__('text', 'flowview')`.

## Common Pitfalls to Avoid

```php
// WRONG - core db_* on flowview tables (breaks with a dedicated flowview DB)
db_fetch_assoc('SELECT * FROM plugin_flowview_devices');

// CORRECT
flowview_db_fetch_assoc('SELECT * FROM plugin_flowview_devices');
```

## Version Control

Document all changes in `CHANGELOG.md`; use descriptive commit messages referencing issue/PR numbers when applicable.

## CI & Dependency Baselines

- Do not commit a `composer.json` or `composer.lock` in this plugin's own repo root — the shared CI workflow installs Pest/dev dependencies into Cacti's own Composer-managed vendor tree (checked out alongside the plugin). Use Cacti's `composer.json`, not a plugin-local one.
- Do not add a plugin-local `.phpstan.neon`/`phpstan.neon` or `.php-cs-fixer.php`/`.php-cs-fixer.dist.php` — lint/static-analysis steps run against Cacti's own config from the Cacti core checkout, targeting this plugin's directory. Use the Cacti version, not a plugin-local config.
- Prefer Cacti's `cacti_count()`/`cacti_sizeof()` wrappers over the raw `count()`/`sizeof()` builtins in new or edited code.

## Internationalization (i18n)

- Translatable strings are managed with GNU gettext via `locales/build_gettext.sh`. `locales/po/cacti.pot` is the source template; Weblate owns syncing the per-language `.po`/`.mo` files from it.
- When a pull request adds or changes a string wrapped in `__()`/`__n()`/`__esc()`/`__x()`/`__xn()`/`__gettext()`, run `locales/build_gettext.sh` before pushing and add the resulting change to `locales/po/cacti.pot` only. Do not commit the regenerated per-language `.po`/`.mo` files in the same PR — Weblate takes care of the rest.

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history

## Security & Quality Conventions

These conventions apply across the Cacti plugin fleet and should be followed whenever touching
existing code or adding new code, not just in dedicated cleanup passes:

- **No hardcoded third-party hosts.** Never hardcode a third-party IP address, hostname, or URL
  in plugin code (even for tooling/download helpers). Expose it as a plugin setting instead, with
  secure-by-default values (e.g. an SSL-verification setting that defaults to verify-on).
- **Prepared statements over `db_qstr()`.** Build dynamic `WHERE` clauses using the
  `$sql_where`/`$sql_params` prepared-statement pattern, not string concatenation via `db_qstr()`.
- **Use `html_escape_request_var()`.** Prefer it over the `html_escape(get_request_var(...))` call
  chain.
- **Harden `unserialize()`.** Always pass `['allow_classes' => false]` as the second argument.
- **i18n text domain.** Every `__()`/`__esc()` call must include this plugin's text domain as the
  final argument, except when deliberately comparing against a literal, untranslated Cacti-core
  label.
- **Plugin table-creation API.** Use `api_plugin_db_table_create()`/`api_plugin_db_add_column()`
  (from Cacti core's `lib/plugins.php`) instead of raw `CREATE TABLE`/`ALTER TABLE ... ADD COLUMN`.
  Both are idempotent (safe no-ops when already applied), so the same call can run unconditionally
  from both the install AND upgrade paths.
- **PHPDoc shape.** Every function gets a PHPDoc block: a one-line description, a blank comment
  line, `@param` lines, a blank comment line, then `@return`. Infer parameter/return types from
  actual usage; don't change the function's real type-hints in the same pass (let static analysis
  flag mismatches separately). Skip vendored third-party library files.
