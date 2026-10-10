package main

import (
	"context"
	"database/sql"
	"fmt"
	"time"
)

// Scheduler performs background cache/partition maintenance that the PHP path
// does inline (and thus on the request's critical path): expiring query
// results and aged partition cache, and dropping orphaned intermediary tables.
type Scheduler struct {
	eng      *Engine
	interval time.Duration
}

// Run ticks until the context is cancelled, performing one maintenance pass
// per interval.
func (s *Scheduler) Run(ctx context.Context) {
	// One pass at startup so a restart immediately reclaims stale state.
	s.RunOnce(ctx)

	t := time.NewTicker(s.interval)
	defer t.Stop()

	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
			s.RunOnce(ctx)
		}
	}
}

// RunOnce performs a single maintenance pass. Safe to call from the /maintenance
// endpoint as well as the ticker.
func (s *Scheduler) RunOnce(ctx context.Context) {
	if err := s.expireQueries(ctx); err != nil {
		logf("scheduler: expire queries: %v", err)
	}
	if err := s.expireShardCache(ctx); err != nil {
		logf("scheduler: expire shard cache: %v", err)
	}
	if err := s.dropOrphanCache(ctx); err != nil {
		logf("scheduler: drop orphan cache: %v", err)
	}
}

// expireQueries removes completed/stale parallel_database_query rows whose TTL
// has passed and drops their intermediary map tables.
func (s *Scheduler) expireQueries(ctx context.Context) error {
	rows, err := s.eng.flow.QueryContext(ctx,
		`SELECT id, map_table FROM parallel_database_query
		 WHERE time_to_live > 0 AND time_to_live < UNIX_TIMESTAMP()`)
	if err != nil {
		return err
	}

	type q struct {
		id    int64
		table string
	}
	var expired []q
	for rows.Next() {
		var item q
		var tbl sql.NullString
		if err := rows.Scan(&item.id, &tbl); err != nil {
			rows.Close()
			return err
		}
		item.table = tbl.String
		expired = append(expired, item)
	}
	rows.Close()
	if err := rows.Err(); err != nil {
		return err
	}

	for _, item := range expired {
		if item.table != "" {
			if _, err := s.eng.flow.ExecContext(ctx, "DROP TABLE IF EXISTS "+quoteTable(item.table)); err != nil {
				logf("scheduler: drop map table %s: %v", item.table, err)
			}
		}
		if _, err := s.eng.flow.ExecContext(ctx,
			"DELETE FROM parallel_database_query_shard WHERE query_id = ?", item.id); err != nil {
			logf("scheduler: delete shards %d: %v", item.id, err)
		}
		if _, err := s.eng.flow.ExecContext(ctx,
			"DELETE FROM parallel_database_query WHERE id = ?", item.id); err != nil {
			logf("scheduler: delete query %d: %v", item.id, err)
		}
	}

	if len(expired) > 0 {
		logf("scheduler: expired %d queries", len(expired))
	}
	return nil
}

// expireShardCache ages out cached partition results older than the configured
// time-to-live so hot partitions stay cached while cold data is reclaimed.
func (s *Scheduler) expireShardCache(ctx context.Context) error {
	ttl := int64(s.eng.settings.TimeToLive().Seconds())
	if ttl <= 0 {
		return nil
	}

	res, err := s.eng.flow.ExecContext(ctx,
		`DELETE FROM parallel_database_query_shard_cache
		 WHERE date_created < (NOW() - INTERVAL ? SECOND)`, ttl)
	if err != nil {
		return err
	}
	if n, _ := res.RowsAffected(); n > 0 {
		logf("scheduler: expired %d cached shards", n)
	}
	return nil
}

// dropOrphanCache removes cache rows whose underlying partition table no longer
// exists (e.g. a raw partition that has been rolled off).
func (s *Scheduler) dropOrphanCache(ctx context.Context) error {
	rows, err := s.eng.flow.QueryContext(ctx,
		`SELECT DISTINCT c.map_table
		 FROM parallel_database_query_shard_cache c
		 LEFT JOIN information_schema.TABLES t
		   ON t.table_schema = DATABASE() AND t.table_name = c.map_table
		 WHERE t.table_name IS NULL`)
	if err != nil {
		return err
	}

	var orphans []string
	for rows.Next() {
		var tbl string
		if err := rows.Scan(&tbl); err != nil {
			rows.Close()
			return err
		}
		orphans = append(orphans, tbl)
	}
	rows.Close()
	if err := rows.Err(); err != nil {
		return err
	}

	for _, tbl := range orphans {
		if _, err := s.eng.flow.ExecContext(ctx,
			"DELETE FROM parallel_database_query_shard_cache WHERE map_table = ?", tbl); err != nil {
			logf("scheduler: delete orphan cache %s: %v", tbl, err)
		}
	}

	if len(orphans) > 0 {
		logf("scheduler: dropped cache for %d missing partitions", len(orphans))
	}
	return nil
}

// InvalidateCache removes cached shard results for a query/filter signature.
// Called when a saved query or filter is deleted so its cache does not linger.
func (e *Engine) InvalidateCache(ctx context.Context, md5sum string) (int64, error) {
	if md5sum == "" {
		return 0, fmt.Errorf("md5sum required")
	}
	res, err := e.flow.ExecContext(ctx,
		"DELETE FROM parallel_database_query_shard_cache WHERE md5sum = ?", md5sum)
	if err != nil {
		return 0, err
	}
	n, _ := res.RowsAffected()
	return n, nil
}
