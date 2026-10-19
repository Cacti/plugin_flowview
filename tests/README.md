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

Security tests validate PHP 8.2 syntax compatibility and the plugin's
install/version/uninstall structure without executing plugin source.

The filter lifecycle regressions also launch the actual web and upgrade entrypoints
in subprocesses with isolated core API fixtures. They verify that all three web
pages do not load the schema-mutation helper and defer repair, including
same-version installations, and that the
background worker registers before attempting repair. The queue regression uses
an in-memory SQLite database to check persisted ordering across multiple passes:
a full failed batch remains pending while healthy rows advance on the next pass.
This queue test requires PDO SQLite and skips explicitly when it is unavailable;
it does not validate MariaDB schema syntax or metadata locking.


Timeout regressions load Cacti's actual process helper and persist queue state in
SQLite while a worker writes stderr and completes after the parent timeout. Both
legacy and native entrypoints must avoid requeueing and reconcile late completion
without a second delivery. Separate actual-child tests cover durable outcome
markers, PID-scoped shutdown cleanup, deferred preparation and timestamp-write
failure after completion. Fixtures adapt database APIs and worker delivery; they
do not test SMTP or replace live MariaDB concurrency validation.

Successful-dispatch doubles explicitly supply recorded child completion; an exit
code alone never synthesizes that outcome. Regressions exercise an actual child
connection failure that exits zero before its outcome callback, and a persistent
queue with 100 undeletable completed rows followed by executable work.
