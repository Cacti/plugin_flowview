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
**ALWAYS use the `flowview_db_*` wrapper functions with prepared statements** for anything involving variable input, never raw string concatenation:

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

## References

- [Cacti main repo](https://github.com/Cacti/cacti/tree/1.2.x)
- [Cacti Documentation](https://www.github.com/Cacti/documentation)
- `README.md` for feature descriptions
- `CHANGELOG.md` for version history
