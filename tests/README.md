# FlowView test harness

The harness separates fast deterministic checks from connector-backed database
tests.

## Local unit and contract tests

```sh
composer install
composer test:unit
composer test:syntax
```

Unit tests load the shipped `functions.php` and `database.php` through a small,
stateful Cacti API double. Database responses can be queued with
`plugin_test_queue_db_result()`, and every SQL call is recorded in
`$GLOBALS['__test_db_calls']` for parameter and connection assertions.

## Database integration tests

Start a disposable MariaDB or MySQL database, then run:

```sh
FLOWVIEW_TEST_DB_DSN='mysql:host=127.0.0.1;port=3306;dbname=flowview_test' \
FLOWVIEW_TEST_DB_USER=root \
FLOWVIEW_TEST_DB_PASSWORD=flowview \
composer test:integration
```

The integration suite creates and removes only `plugin_flowview_test_raw`. It
round-trips IPv4 and IPv6 addresses, microsecond timestamps, counters, prepared
parameters, and report aggregates through the real PDO MySQL connector. CI runs
the same contract against MariaDB 10.6 and MySQL 8.4.

`composer test` runs every test; without `FLOWVIEW_TEST_DB_DSN`, integration
tests are explicitly skipped rather than silently simulated.
