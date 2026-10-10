package main

import (
	"bytes"
	"context"
	"database/sql"
	"encoding/json"
	"fmt"
	"math"
	"strings"
	"sync"
)

// Engine runs parallel map-reduce queries against the FlowView database,
// mirroring parallel_database_parent_runner / parallel_database_child_runner.
type Engine struct {
	flow     *sql.DB // primary flow connection (intermediary + cache writes, reduce)
	read     *sql.DB // map read connection (MaxScale RW-split when enabled, else == flow)
	cacti    *sql.DB // Cacti control DB (settings)
	settings *Settings
}

// RunQueries executes each requested query to completion (writing results and
// marking it complete), returning per-query stats.
func (e *Engine) RunQueries(ctx context.Context, ids []int64) (*RunResponse, error) {
	resp := &RunResponse{Stats: map[string]Stats{}}

	for _, id := range ids {
		st, err := e.RunQuery(ctx, id)
		if err != nil {
			return resp, fmt.Errorf("query %d: %w", id, err)
		}
		resp.Completed = append(resp.Completed, id)
		resp.Stats[fmt.Sprintf("%d", id)] = st
	}

	return resp, nil
}

// RunQuery runs one query_id: the map phase fans out across a bounded worker
// pool (one shard per partition, cache-aware), then the reduce phase writes
// the final result set and marks the query complete.
func (e *Engine) RunQuery(ctx context.Context, id int64) (Stats, error) {
	threads := e.settings.Threads()

	q, err := e.fetchQuery(ctx, id)
	if err != nil {
		return Stats{}, err
	}

	shards, err := e.fetchShards(ctx, id)
	if err != nil {
		return Stats{}, err
	}

	sem := make(chan struct{}, threads)

	var (
		wg       sync.WaitGroup
		mu       sync.Mutex
		cached   int
		firstErr error
	)

	for i := range shards {
		s := shards[i]

		wg.Add(1)
		sem <- struct{}{}

		go func(s shardRow) {
			defer wg.Done()
			defer func() { <-sem }()

			wasCached, err := e.runShard(ctx, q, s)

			mu.Lock()
			defer mu.Unlock()
			if err != nil {
				if firstErr == nil {
					firstErr = err
				}
				return
			}
			if wasCached {
				cached++
			}
		}(s)
	}

	wg.Wait()

	if firstErr != nil {
		return Stats{}, firstErr
	}

	if err := e.reduce(ctx, q); err != nil {
		return Stats{}, err
	}

	return Stats{Threads: threads, Shards: len(shards), Cached: cached}, nil
}

// runShard runs (or cache-serves) one partition's map query and stages the
// output in the query's intermediary map table.
func (e *Engine) runShard(ctx context.Context, q *queryRow, s shardRow) (bool, error) {
	if _, err := e.flow.ExecContext(ctx,
		`UPDATE parallel_database_query_shard SET status = 'running' WHERE query_id = ? AND shard_id = ?`,
		q.ID, s.ShardID); err != nil {
		return false, err
	}

	rows, cached, err := e.mapShard(ctx, q, s)
	if err != nil {
		return false, fmt.Errorf("map shard %s: %w", s.MapTable, err)
	}

	if len(rows) > 0 {
		if err := insertRows(ctx, e.flow, q.MapTable, rows); err != nil {
			return false, fmt.Errorf("stage shard %s: %w", s.MapTable, err)
		}
	}

	if _, err := e.flow.ExecContext(ctx,
		`UPDATE parallel_database_query_shard SET status = 'finished', completed = CURRENT_TIMESTAMP WHERE query_id = ? AND shard_id = ?`,
		q.ID, s.ShardID); err != nil {
		return false, err
	}

	if _, err := e.flow.ExecContext(ctx,
		`UPDATE parallel_database_query SET finished_shards = finished_shards + 1 WHERE id = ?`, q.ID); err != nil {
		return false, err
	}

	return cached, nil
}

// mapShard returns the shard's map output, serving a full-scan shard from the
// shared cache when present (and populating it on a miss).
func (e *Engine) mapShard(ctx context.Context, q *queryRow, s shardRow) ([]*OrderedRow, bool, error) {
	if s.FullScan {
		if rows, ok, err := e.cacheGet(ctx, q.MD5SumTables, s.MapTable, s.MapPartition); err != nil {
			return nil, false, err
		} else if ok {
			if _, uerr := e.flow.ExecContext(ctx,
				`UPDATE parallel_database_query SET cached_shards = cached_shards + 1 WHERE id = ?`, q.ID); uerr != nil {
				return nil, false, uerr
			}
			return rows, true, nil
		}
	}

	rows, err := e.queryDB(ctx, e.read, s.MapQuery, decodeParams(s.MapParams))
	if err != nil {
		return nil, false, err
	}

	if s.FullScan {
		if err := e.cachePut(ctx, q.MD5SumTables, s, rows); err != nil {
			logf("cache write %s/%s failed: %v", s.MapTable, s.MapPartition, err)
		}
	}

	return rows, false, nil
}

// reduce runs the reduce query over the intermediary map table, stores the
// results and marks the query complete (then clears its shards).
func (e *Engine) reduce(ctx context.Context, q *queryRow) error {
	var stru reduceStruct
	if err := json.Unmarshal(q.ReduceQuery, &stru); err != nil {
		return fmt.Errorf("decode reduce_query: %w", err)
	}

	var b strings.Builder
	b.WriteString(stru.SQLQuery)
	b.WriteString(" FROM ")
	b.WriteString(quoteTable(q.MapTable))
	appendClause(&b, stru.SQLWhere)
	appendClause(&b, stru.SQLGroupBy)
	appendClause(&b, stru.SQLHaving)
	appendClause(&b, stru.SQLOrder)
	appendClause(&b, stru.SQLLimit)

	data, err := e.queryDB(ctx, e.flow, b.String(), stru.SQLParams)
	if err != nil {
		return fmt.Errorf("reduce: %w", err)
	}

	blob, err := json.Marshal(data)
	if err != nil {
		return err
	}

	if _, err := e.flow.ExecContext(ctx,
		`UPDATE parallel_database_query SET results = ?, status = 'complete' WHERE id = ?`, blob, q.ID); err != nil {
		return err
	}

	if _, err := e.flow.ExecContext(ctx,
		`DELETE FROM parallel_database_query_shard WHERE query_id = ?`, q.ID); err != nil {
		return err
	}

	return nil
}

func (e *Engine) fetchQuery(ctx context.Context, id int64) (*queryRow, error) {
	q := &queryRow{}
	err := e.flow.QueryRowContext(ctx,
		`SELECT id, md5sum_tables, map_table, reduce_query FROM parallel_database_query WHERE id = ?`, id).
		Scan(&q.ID, &q.MD5SumTables, &q.MapTable, &q.ReduceQuery)
	if err != nil {
		return nil, err
	}
	return q, nil
}

func (e *Engine) fetchShards(ctx context.Context, id int64) ([]shardRow, error) {
	rows, err := e.flow.QueryContext(ctx,
		`SELECT shard_id, full_scan, map_table, map_partition, map_query, map_params
		 FROM parallel_database_query_shard WHERE query_id = ? ORDER BY shard_id`, id)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []shardRow
	for rows.Next() {
		var s shardRow
		var full int
		if err := rows.Scan(&s.ShardID, &full, &s.MapTable, &s.MapPartition, &s.MapQuery, &s.MapParams); err != nil {
			return nil, err
		}
		s.FullScan = full != 0
		out = append(out, s)
	}
	return out, rows.Err()
}

// cacheGet looks up a cached full-scan shard result.
func (e *Engine) cacheGet(ctx context.Context, md5sum, table, partition string) ([]*OrderedRow, bool, error) {
	var blob []byte
	err := e.flow.QueryRowContext(ctx,
		`SELECT results FROM parallel_database_query_shard_cache
		 WHERE md5sum = ? AND map_table = ? AND map_partition = ?`,
		md5sum, table, partition).Scan(&blob)

	if err == sql.ErrNoRows {
		return nil, false, nil
	}
	if err != nil {
		return nil, false, err
	}

	rows, derr := decodeRows(blob)
	if derr != nil {
		return nil, false, nil // corrupt cache row -> treat as miss
	}
	return rows, true, nil
}

// cachePut stores a full-scan shard result keyed exactly like the PHP cache.
func (e *Engine) cachePut(ctx context.Context, md5sum string, s shardRow, rows []*OrderedRow) error {
	var mn, mx sql.NullString
	_ = e.flow.QueryRowContext(ctx,
		fmt.Sprintf("SELECT MIN(start_time), MAX(end_time) FROM %s", quoteTable(s.MapTable))).Scan(&mn, &mx)

	minDate := mn.String
	maxDate := mx.String
	if minDate == "" {
		minDate = "0000-00-00 00:00:00"
	}
	if maxDate == "" {
		maxDate = minDate
	}

	blob, err := json.Marshal(rows)
	if err != nil {
		return err
	}

	_, err = e.flow.ExecContext(ctx,
		`INSERT INTO parallel_database_query_shard_cache
			(md5sum, map_table, map_partition, min_date, max_date, results)
		 VALUES (?, ?, ?, ?, ?, ?)
		 ON DUPLICATE KEY UPDATE results = VALUES(results), date_created = CURRENT_TIMESTAMP`,
		md5sum, s.MapTable, s.MapPartition, minDate, maxDate, blob)

	return err
}

func (e *Engine) queryDB(ctx context.Context, db *sql.DB, query string, params []any) ([]*OrderedRow, error) {
	rows, err := db.QueryContext(ctx, query, normalizeParams(params)...)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	return scanRows(rows)
}

// --- helpers ---

func appendClause(b *strings.Builder, clause string) {
	if clause != "" {
		b.WriteString(" ")
		b.WriteString(clause)
	}
}

func scanRows(rows *sql.Rows) ([]*OrderedRow, error) {
	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}

	out := make([]*OrderedRow, 0, 64)

	for rows.Next() {
		vals := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range vals {
			ptrs[i] = &vals[i]
		}
		if err := rows.Scan(ptrs...); err != nil {
			return nil, err
		}

		row := &OrderedRow{Cols: append([]string(nil), cols...), Vals: make(map[string]any, len(cols))}
		for i, c := range cols {
			row.Vals[c] = normalizeVal(vals[i])
		}
		out = append(out, row)
	}

	return out, rows.Err()
}

func insertRows(ctx context.Context, db *sql.DB, table string, rows []*OrderedRow) error {
	if len(rows) == 0 {
		return nil
	}

	cols := rows[0].Cols
	const batch = 500

	for i := 0; i < len(rows); i += batch {
		end := i + batch
		if end > len(rows) {
			end = len(rows)
		}
		chunk := rows[i:end]

		var b strings.Builder
		b.WriteString("INSERT INTO ")
		b.WriteString(quoteTable(table))
		b.WriteString(" (")
		for j, c := range cols {
			if j > 0 {
				b.WriteString(", ")
			}
			b.WriteString("`")
			b.WriteString(c)
			b.WriteString("`")
		}
		b.WriteString(") VALUES ")

		params := make([]any, 0, len(chunk)*len(cols))
		for r, row := range chunk {
			if r > 0 {
				b.WriteString(", ")
			}
			b.WriteString("(")
			for j, c := range cols {
				if j > 0 {
					b.WriteString(", ")
				}
				b.WriteString("?")
				params = append(params, normalizeParam(row.Vals[c]))
			}
			b.WriteString(")")
		}

		if _, err := db.ExecContext(ctx, b.String(), params...); err != nil {
			return err
		}
	}

	return nil
}

func decodeParams(raw []byte) []any {
	if len(bytes.TrimSpace(raw)) == 0 {
		return nil
	}
	var out []any
	if err := json.Unmarshal(raw, &out); err != nil {
		return nil
	}
	return out
}

func decodeRows(blob []byte) ([]*OrderedRow, error) {
	if len(bytes.TrimSpace(blob)) == 0 {
		return nil, nil
	}

	dec := json.NewDecoder(bytes.NewReader(blob))
	dec.UseNumber()

	tok, err := dec.Token()
	if err != nil {
		return nil, err
	}
	if d, ok := tok.(json.Delim); !ok || d != '[' {
		return nil, nil
	}

	var out []*OrderedRow
	for dec.More() {
		row, err := decodeObject(dec)
		if err != nil {
			return nil, err
		}
		out = append(out, row)
	}
	return out, nil
}

func decodeObject(dec *json.Decoder) (*OrderedRow, error) {
	tok, err := dec.Token()
	if err != nil {
		return nil, err
	}
	if d, ok := tok.(json.Delim); !ok || d != '{' {
		return nil, fmt.Errorf("expected object")
	}

	row := &OrderedRow{Vals: map[string]any{}}
	for dec.More() {
		keyTok, err := dec.Token()
		if err != nil {
			return nil, err
		}
		key, _ := keyTok.(string)

		var val any
		if err := dec.Decode(&val); err != nil {
			return nil, err
		}
		row.Cols = append(row.Cols, key)
		row.Vals[key] = val
	}
	_, _ = dec.Token() // consume '}'

	return row, nil
}

func normalizeVal(v any) any {
	switch t := v.(type) {
	case []byte:
		return string(t)
	default:
		return v
	}
}

func normalizeParam(v any) any {
	switch n := v.(type) {
	case float64:
		if n == math.Trunc(n) && !math.IsInf(n, 0) {
			return int64(n)
		}
		return n
	case json.Number:
		if i, err := n.Int64(); err == nil {
			return i
		}
		if f, err := n.Float64(); err == nil {
			return f
		}
		return n.String()
	default:
		return v
	}
}

func normalizeParams(in []any) []any {
	out := make([]any, len(in))
	for i, v := range in {
		out[i] = normalizeParam(v)
	}
	return out
}

// quoteTable backtick-quotes a (possibly schema-qualified) table identifier.
func quoteTable(name string) string {
	parts := strings.Split(name, ".")
	for i, p := range parts {
		parts[i] = "`" + strings.ReplaceAll(p, "`", "``") + "`"
	}
	return strings.Join(parts, ".")
}
