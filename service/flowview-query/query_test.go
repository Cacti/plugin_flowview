package main

import (
	"context"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
)

func TestRunQuerySingleShardNoCache(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()

	settings := defaultSettings()
	settings.threads = 1
	eng := NewEngine(flow, nil, nil, settings)

	// fetchQuery
	reduce := `{"sql_query":"SELECT a, b","sql_params":[]}`
	mock.ExpectQuery("SELECT id, md5sum_tables").
		WithArgs(int64(5)).
		WillReturnRows(sqlmock.NewRows([]string{"id", "md5sum_tables", "map_table", "reduce_query"}).
			AddRow(int64(5), "sig", "map_5", []byte(reduce)))

	// fetchShards: one non-full-scan shard
	mock.ExpectQuery("FROM parallel_database_query_shard WHERE query_id").
		WithArgs(int64(5)).
		WillReturnRows(sqlmock.NewRows([]string{"shard_id", "full_scan", "map_table", "map_partition", "map_query", "map_params"}).
			AddRow(int64(1), 0, "map_5", "part0", "SELECT x FROM part0", []byte("[]")))

	// runShard: status=running
	mock.ExpectExec("SET status = 'running'").
		WithArgs(int64(5), int64(1)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	// mapShard: the map query (non-full-scan, no cache)
	mock.ExpectQuery("SELECT x FROM part0").
		WillReturnRows(sqlmock.NewRows([]string{"x"}).AddRow(int64(11)))

	// insertRows into the intermediary table
	mock.ExpectExec("INSERT INTO .map_5.").
		WithArgs(int64(11)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	// status=finished
	mock.ExpectExec("SET status = 'finished'").
		WithArgs(int64(5), int64(1)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	// finished_shards++
	mock.ExpectExec("finished_shards = finished_shards").
		WithArgs(int64(5)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	// reduce query
	mock.ExpectQuery("SELECT a, b FROM .map_5.").
		WillReturnRows(sqlmock.NewRows([]string{"a", "b"}).AddRow(int64(1), "y"))

	// store results + mark complete
	mock.ExpectExec("SET results = \\?, status = 'complete'").
		WillReturnResult(sqlmock.NewResult(0, 1))

	// clear shards
	mock.ExpectExec("DELETE FROM parallel_database_query_shard WHERE query_id").
		WithArgs(int64(5)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	st, err := eng.RunQuery(context.Background(), 5)
	if err != nil {
		t.Fatalf("RunQuery: %v", err)
	}
	if st.Shards != 1 || st.Cached != 0 || st.Threads != 1 {
		t.Fatalf("unexpected stats: %#v", st)
	}
}

func TestRunQueriesWrapsStats(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()

	settings := defaultSettings()
	settings.threads = 1
	eng := NewEngine(flow, nil, nil, settings)

	reduce := `{"sql_query":"SELECT a","sql_params":[]}`
	mock.ExpectQuery("SELECT id, md5sum_tables").
		WithArgs(int64(9)).
		WillReturnRows(sqlmock.NewRows([]string{"id", "md5sum_tables", "map_table", "reduce_query"}).
			AddRow(int64(9), "sig", "map_9", []byte(reduce)))
	// No shards.
	mock.ExpectQuery("FROM parallel_database_query_shard WHERE query_id").
		WithArgs(int64(9)).
		WillReturnRows(sqlmock.NewRows([]string{"shard_id", "full_scan", "map_table", "map_partition", "map_query", "map_params"}))
	mock.ExpectQuery("SELECT a FROM .map_9.").
		WillReturnRows(sqlmock.NewRows([]string{"a"}).AddRow(int64(1)))
	mock.ExpectExec("SET results = \\?, status = 'complete'").
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectExec("DELETE FROM parallel_database_query_shard WHERE query_id").
		WithArgs(int64(9)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	resp, err := eng.RunQueries(context.Background(), []int64{9})
	if err != nil {
		t.Fatalf("RunQueries: %v", err)
	}
	if len(resp.Completed) != 1 || resp.Completed[0] != 9 {
		t.Fatalf("completed mismatch: %#v", resp.Completed)
	}
	if _, ok := resp.Stats["9"]; !ok {
		t.Fatalf("stats missing key: %#v", resp.Stats)
	}
}

func TestMapShardCacheHit(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())

	q := &queryRow{ID: 3, MD5SumTables: "sig", MapTable: "map_3"}
	s := shardRow{ShardID: 1, FullScan: true, MapTable: "map_3", MapPartition: "p0", MapQuery: "SELECT 1"}

	cached := `[{"x":7}]`
	mock.ExpectQuery("FROM parallel_database_query_shard_cache").
		WithArgs("sig", "map_3", "p0").
		WillReturnRows(sqlmock.NewRows([]string{"results"}).AddRow([]byte(cached)))
	mock.ExpectExec("cached_shards = cached_shards").
		WithArgs(int64(3)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	rows, wasCached, err := eng.mapShard(context.Background(), q, s)
	if err != nil {
		t.Fatalf("mapShard: %v", err)
	}
	if !wasCached {
		t.Fatalf("expected cache hit")
	}
	if len(rows) != 1 || rows[0].Vals["x"] == nil {
		t.Fatalf("unexpected cached rows: %#v", rows)
	}
}

func TestMapShardCacheMissPopulates(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())

	q := &queryRow{ID: 3, MD5SumTables: "sig", MapTable: "map_3"}
	s := shardRow{ShardID: 1, FullScan: true, MapTable: "map_3", MapPartition: "p0", MapQuery: "SELECT col FROM raw"}

	// cache miss
	mock.ExpectQuery("FROM parallel_database_query_shard_cache").
		WithArgs("sig", "map_3", "p0").
		WillReturnRows(sqlmock.NewRows([]string{"results"}))
	// map query
	mock.ExpectQuery("SELECT col FROM raw").
		WillReturnRows(sqlmock.NewRows([]string{"col"}).AddRow(int64(42)))
	// cachePut: MIN/MAX then INSERT
	mock.ExpectQuery("SELECT MIN\\(start_time\\), MAX\\(end_time\\)").
		WillReturnRows(sqlmock.NewRows([]string{"mn", "mx"}).AddRow("2024-01-01 00:00:00", "2024-01-02 00:00:00"))
	mock.ExpectExec("INSERT INTO parallel_database_query_shard_cache").
		WillReturnResult(sqlmock.NewResult(0, 1))

	rows, wasCached, err := eng.mapShard(context.Background(), q, s)
	if err != nil {
		t.Fatalf("mapShard: %v", err)
	}
	if wasCached {
		t.Fatalf("expected cache miss")
	}
	if len(rows) != 1 {
		t.Fatalf("expected one mapped row, got %d", len(rows))
	}
}

func TestReadPoolHonoursLiveSetting(t *testing.T) {
	flow, _, cleanupF := newMock(t)
	defer cleanupF()
	ms, _, cleanupM := newMock(t)
	defer cleanupM()

	settings := defaultSettings()
	eng := NewEngine(flow, ms, nil, settings)

	if eng.readPool() != flow {
		t.Fatalf("maxscale off -> should read from flow pool")
	}

	settings.mu.Lock()
	settings.useMaxScale = true
	settings.mu.Unlock()
	if eng.readPool() != ms {
		t.Fatalf("maxscale on -> should read from maxscale pool")
	}

	// No maxscale pool configured -> always flow, even if the setting is on.
	eng2 := NewEngine(flow, nil, nil, settings)
	if eng2.readPool() != flow {
		t.Fatalf("no maxscale pool -> must fall back to flow")
	}
}
