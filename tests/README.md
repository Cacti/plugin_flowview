# FlowView test harness

The harness separates fast deterministic checks (`tests/Security`) from
plugin-logic unit tests (`tests/Unit`), following the same pattern used by
`plugin_evidence`.

Tests do not use a Composer install local to this plugin. Instead, Pest runs
against a working Cacti installation checked out next to this plugin, reusing
Cacti's own Composer-managed vendor tree for Pest/PHPUnit. This is exactly
what `.github/workflows/plugin-ci-workflow.yml` does in CI.

## Running the tests against a Cacti checkout

Clone or symlink this plugin into a Cacti checkout's `plugins/flowview`
directory, install Cacti's dev dependencies once, then run Pest through
Cacti's vendor binary:

```sh
git clone https://github.com/Cacti/cacti.git
git clone https://github.com/Cacti/plugin_flowview.git cacti/plugins/flowview

cd cacti
composer config --no-plugins allow-plugins.pestphp/pest-plugin true
composer require --dev "pestphp/pest:^3" "pestphp/pest-plugin-drift:^3.0"

echo -n "$(cat include/cacti_version)" > plugins/flowview/tests/.cacti-version

include/vendor/bin/pest --configuration=plugins/flowview/phpunit.xml \
	plugins/flowview/tests/Security plugins/flowview/tests/Unit
```

Unit tests load the shipped `functions.php` and `database.php` through a
small, stateful Cacti API double defined in `tests/bootstrap-unit.php`.
Database responses can be queued with `plugin_test_queue_db_result()`, and
every SQL call is recorded in `$GLOBALS['__test_db_calls']` for parameter and
connection assertions.

Security tests validate PHP 7.4 syntax compatibility and the plugin's
install/version/uninstall structure without executing plugin source.

