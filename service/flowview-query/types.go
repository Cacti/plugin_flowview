// Package main implements the FlowView parallel query service.
//
// It is a drop-in replacement for the PHP fork/exec + database-polling
// parallel query path (flowview_runner.php ->
// parallel_database_parent_runner / parallel_database_child_runner). It:
//
//   - listens on localhost on an available port,
//   - accepts a list of already-planned query ids (the rows the PHP side
//     inserts into parallel_database_query / parallel_database_query_shard),
//   - fans the map phase out across a bounded worker pool (one shard per
//     flow partition table, capped by flowview_parallel_threads),
//   - reuses the shared parallel_database_query_shard_cache so a cold
//     partition is read once and never re-read for the same query signature,
//   - runs the reduce phase and writes the same "results" the PHP runner
//     writes today, then marks the query complete.
//
// Because it reads and writes the same tables, the PHP side only needs to
// replace its exec_background() launch with one HTTP call; the existing
// result retrieval (and multi-request UNION reduce) is unchanged.
//
// A separate scheduler goroutine performs cache/partition maintenance
// (TTL expiry, aged-out partitions, query/filter deletion).
package main

import "encoding/json"

// RunRequest is the payload POSTed to /run: the ids of the
// parallel_database_query rows to execute.
type RunRequest struct {
	QueryIDs []int64 `json:"query_ids"`
}

// RunResponse is returned by /run.
type RunResponse struct {
	Completed []int64          `json:"completed"`
	Stats     map[string]Stats `json:"stats"`
}

// Stats mirrors the PARALLEL STATS the PHP runner logs.
type Stats struct {
	Threads int `json:"threads"`
	Shards  int `json:"shards"`
	Cached  int `json:"cached"`
}

// reduceStruct is the "outer" (reduce phase) query definition decoded from the
// parallel_database_query.reduce_query column ($stru_outer on the PHP side).
type reduceStruct struct {
	SQLQuery   string `json:"sql_query"`
	SQLWhere   string `json:"sql_where"`
	SQLHaving  string `json:"sql_having"`
	SQLGroupBy string `json:"sql_groupby"`
	SQLOrder   string `json:"sql_order"`
	SQLLimit   string `json:"sql_limit"`
	SQLParams  []any  `json:"sql_params"`
}

// queryRow is one parallel_database_query row.
type queryRow struct {
	ID           int64
	MD5SumTables string
	MapTable     string
	ReduceQuery  []byte
}

// shardRow is one parallel_database_query_shard row.
type shardRow struct {
	ShardID      int64
	FullScan     bool
	MapTable     string
	MapPartition string
	MapQuery     string
	MapParams    []byte
}

// OrderedRow is one result row that marshals to a JSON object preserving the
// SQL column order (Go maps would otherwise sort keys, changing column order
// relative to the PHP assoc-array output).
type OrderedRow struct {
	Cols []string
	Vals map[string]any
}

// MarshalJSON renders the row as an ordered JSON object.
func (r *OrderedRow) MarshalJSON() ([]byte, error) {
	buf := []byte{'{'}

	for i, c := range r.Cols {
		if i > 0 {
			buf = append(buf, ',')
		}

		key, err := json.Marshal(c)
		if err != nil {
			return nil, err
		}

		val, err := json.Marshal(r.Vals[c])
		if err != nil {
			return nil, err
		}

		buf = append(buf, key...)
		buf = append(buf, ':')
		buf = append(buf, val...)
	}

	return append(buf, '}'), nil
}
