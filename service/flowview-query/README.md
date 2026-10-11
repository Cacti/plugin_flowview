# FlowView Parallel Query Service

A small Go web service that runs FlowView's parallel (map-reduce) netflow
queries in-process, replacing the PHP fork/exec + database-polling path that is
the main source of query latency today.

It listens on **localhost on an available port**, accepts a list of
already-planned query ids (the rows the PHP side inserts), fans the map phase
out across a bounded worker pool, reuses the existing shard cache so a cold
partition is read **once**, runs the reduce, and writes the same result set the
PHP `results` column holds today. A background scheduler goroutine performs the
cache/partition maintenance that PHP otherwise does on the request's critical
path.

Selecting it is a one-click setting (**FlowView → Parallel Queries → Parallel
Query Backend**); the default remains the Legacy PHP Runner.

---

## 1. Analysis of today's PHP parallel query path

The current parallel engine is a map-reduce over the netflow partition tables,
coordinated entirely through database tables:

| Table | Role |
| ----- | ---- |
| `parallel_database_query` | one row per query (signature, map/reduce SQL, status, `results`) |
| `parallel_database_query_shard` | one row per partition ("shard") to scan |
| `parallel_database_query_shard_cache` | cached map output per `(md5sum, partition)` for full-scan shards |

Flow of a request:

1. **Plan** — `parallel_database_query_request()` identifies the partition
   tables in the time range, computes two md5s (the whole query, and the map
   query *without* its time range → the "tables" signature used for caching),
   and inserts one `parallel_database_query` row plus one
   `parallel_database_query_shard` row per partition. A partition fully covered
   by the time range is flagged `full_scan` (cacheable).
2. **Run** — `parallel_database_query_run()`:
   - `exec_background()` launches **`flowview_runner.php` as a new PHP CLI
     process** per request;
   - then **polls** `parallel_database_query` in a `while (usleep(5000))` loop
     until `status = complete`.
3. **Parent runner** — `parallel_database_parent_runner()` launches up to
   `flowview_parallel_threads` **child PHP processes** (one per shard, again via
   `exec_background`), polls the shard rows until all are `finished`, then runs
   the **reduce** query over the intermediary map table and writes `results`.
4. **Child runner** — `parallel_database_child_runner()` per shard: if
   `full_scan` and a cache row exists → reuse it; else run the map query against
   the partition (optionally over a MaxScale read/write-split port) and cache
   the result; insert the shard's rows into the intermediary table; mark
   `finished`.

**The caching insight** (which this service preserves): once a given map
signature has been run against a partition, its result is cached and
**that partition is never read again** for that signature — only the (small)
reduce runs.

**Where the latency comes from**

- **PHP process spawn** — one `flowview_runner.php` per request, then one more
  child PHP process per shard. Interpreter startup + `cli_check.php` bootstrap
  dominates small/cached queries.
- **Database polling** — the parent and the caller both busy-wait on table
  status with `usleep`, adding fixed latency even when the work is already done.
- **Process bookkeeping** — `register_process_start`/`unregister_process`,
  `processes` table rows, signal handling, per-shard `UPDATE`s.

For a cached query the *useful* work is a single reduce, but the fixed cost of
spawning processes and polling tables can be seconds.

## 2. What this service changes

- **No process spawning.** The map phase is a **goroutine worker pool** capped
  at `flowview_parallel_threads` (one shard per partition). No PHP CLI startup,
  no `processes` table churn.
- **No polling.** The caller makes one blocking HTTP call and gets the result
  when it is ready (bounded by `flowview_parallel_runlimit`).
- **Same cache, same semantics.** It reads/writes the existing
  `parallel_database_query_shard_cache` table with the **same key**
  `(md5sum, map_table, map_partition)`, so it interoperates with the PHP path
  and a cold partition is still read only once.
- **Same reduce, same output.** The reduce runs in SQL over a temporary table
  built from the shard output (mirroring the PHP intermediary map table), so the
  returned rows match the PHP `results` exactly, in column order.
- **Thread count & MaxScale honoured.** `flowview_parallel_threads` bounds the
  pool; when `flowview_use_maxscale` is on, map (read) queries are routed through
  the MaxScale read/write-split port so shards round-robin across backends.
- **Maintenance off the critical path.** A scheduler goroutine expires queries
  past their `time_to_live`, ages out cached partitions, and drops cache rows for
  partitions that have been rolled off — the work PHP does inline today.

### Map-reduce reduction (unchanged contract)

```
request ──► plan shards (one per partition)
            │
            ├─ shard full_scan & cached?  ── yes ─► reuse cached map output
            │                              no  ─► run map query, cache it
            │   (N shards run concurrently, capped at `threads`)
            ▼
       stage all shard rows in a TEMPORARY table
            ▼
       run the reduce query ──► results (same shape as PHP `results`)
```

## 3. Compilation & install

Requires the Go toolchain (1.21+). On the Cacti host:

```sh
# 1. Compile
cd plugins/flowview/service/flowview-query
go build -o flowview-query .

# 2. Verify it runs with no database (in-memory map-reduce + HTTP bind + health)
./flowview-query -selftest

# 3. Install the binary, config and service unit
sudo install -m 0755 flowview-query /usr/local/bin/flowview-query
sudo install -d -o root -g flowview -m 0750 /etc/flowview
sudo install -m 0640 config.sample.json /etc/flowview/flowview-query.json
sudoedit /etc/flowview/flowview-query.json          # fill in DB host/user/password (see §4)
sudo install -m 0644 flowview-query.service /etc/systemd/system/
```

Cross-compiling from a build host (no Go needed on the Cacti server):

```sh
GOOS=linux GOARCH=amd64 CGO_ENABLED=0 go build -o flowview-query .
# copy the static binary to the Cacti host and continue from step 3 above
```

Then create the service user and start it (see §5).

## 4. Database credentials

The service needs two logical databases (which may be the same server):

- **Cacti control DB** — reads the `settings` table for the live tuning values
  (`flowview_parallel_threads`, `flowview_parallel_runlimit`,
  `flowview_parallel_time_to_live`, `flowview_use_maxscale`).
- **FlowView data DB** — the flow partition tables and the `parallel_*` tables.
  When FlowView shares the Cacti database (`"use_cacti_db": true`, the default),
  this is the same connection.

Create a dedicated, least-privilege user. The service only needs: read the flow
partitions, read/write the three `parallel_*` tables, create/drop its own
`TEMPORARY`/intermediary tables, and read `settings` + `information_schema`.

```sql
-- Dedicated service account (adjust host/password/schemas to your deployment)
CREATE USER 'flowview_svc'@'127.0.0.1' IDENTIFIED BY 'STRONG_PASSWORD';

-- Live tuning values from Cacti
GRANT SELECT ON cacti.settings TO 'flowview_svc'@'127.0.0.1';

-- Flow data + the parallel query tables (read flows, read/write parallel_*,
-- create/drop the intermediary + temporary reduce tables).
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, CREATE TEMPORARY TABLES
      ON flowview.* TO 'flowview_svc'@'127.0.0.1';

FLUSH PRIVILEGES;
```

If FlowView shares the Cacti schema, grant the data privileges on `cacti.*`
instead of `flowview.*` and set `"use_cacti_db": true`.

If you enable MaxScale, point the service at the **read/write-split listener**
(e.g. port 3307/3308 from `maxscale/maxscale.cnf`) and ensure the service user
exists on all backend servers.

Store the credentials in the config file and keep it `root:flowview 0640`:

```sh
sudo install -d -o root -g flowview -m 0750 /etc/flowview
sudo install -m 0640 config.sample.json /etc/flowview/flowview-query.json
sudoedit /etc/flowview/flowview-query.json   # fill in host/user/password/database
```

Credentials may also be supplied/overridden by environment variables
(useful with systemd `EnvironmentFile=` or a secrets manager):
`CACTI_DB_HOST/PORT/USER/PASS/NAME` and `FLOWVIEW_DB_HOST/PORT/USER/PASS/NAME`.

## 5. Run

```sh
sudo useradd --system --no-create-home --shell /usr/sbin/nologin flowview
sudo install -m 0644 flowview-query.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now flowview-query
journalctl -u flowview-query -f
```

On start the service logs the bound address and writes it to `port_file`
(default `/var/run/flowview/flowview-query.port`) so the PHP side can discover
the ephemeral port.

## 6. PHP integration

Integration is **selectable from the FlowView settings page** — no code change
is required to switch:

> **Console → Configuration → Settings → FlowView → Parallel Queries →
> Parallel Query Backend**
>
> - **Legacy PHP Runner** (default) — forks `flowview_runner.php` per query/shard.
> - **FlowView Query Service** — dispatches to this service over localhost.

When the service backend is selected, the parent runner (`flowview_runner.php`)
delegates the whole query to the service via `POST /run` **instead of forking a
PHP worker per shard**. `parallel_database_query_run()` is unchanged: it still
launches `flowview_runner.php`, which now returns as soon as the service has
read the query's shard rows (`parallel_database_query` +
`parallel_database_query_shard`), run the same cache-aware, thread-capped
map-reduce, and written `results` + `status = 'complete'` back. If the service
cannot be reached, the parent runner falls back to the legacy in-PHP
`parallel_database_parent_runner()`.

Endpoint discovery: set **Query Service URL** explicitly (e.g.
`http://127.0.0.1:8699`), or leave it blank and set **Query Service Port File**
to the path the service writes its bound address to (default
`/var/run/flowview/flowview-query.port`).

Request body (`POST /run`):

```json
{ "query_ids": [ 1337, 1338 ] }
```

Response:

```json
{ "completed": [1337, 1338], "stats": { "1337": { "threads": 4, "shards": 4, "cached": 3 } } }
```

Because the service shares the `parallel_database_query_shard_cache` table with
the legacy path, a partition cached by one backend is reused by the other.

## 7. Endpoints

| Method & path | Purpose |
| ------------- | ------- |
| `POST /run` | Run the given `{"query_ids":[...]}` to completion; returns per-query stats. |
| `GET  /health` | Liveness + DB ping + current thread setting. |
| `POST /maintenance` | Run one scheduler pass now (expiry/cleanup). |
| `POST /cache/invalidate?md5sum=<sig>` | Drop cached shards for a deleted query/filter signature. |

## 8. Scheduler

A goroutine runs every `scheduler_interval` (and once at startup):

- expires `parallel_database_query` rows past `time_to_live` and drops their
  intermediary map tables;
- ages out `parallel_database_query_shard_cache` rows older than
  `flowview_parallel_time_to_live`;
- drops cache rows whose partition table no longer exists (rolled-off data).

Deleting a saved query or filter should call `POST /cache/invalidate` with that
signature so its cache does not linger until TTL.

## Files

| File | Purpose |
| ---- | ------- |
| `main.go` | flags, config/DB wiring, listener, `-selftest` |
| `types.go` | request/response + ordered-row JSON |
| `config.go` | config load, DB pools, live Cacti settings |
| `query.go` | map-reduce engine, worker pool, shard cache, reduce |
| `scheduler.go` | background cache/partition maintenance |
| `server.go` | HTTP handlers |
| `demo.go` | in-memory self-test |
| `config.sample.json` | sample configuration |
| `flowview-query.service` | systemd unit |
