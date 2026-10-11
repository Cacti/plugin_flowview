package main

import (
	"context"
	"errors"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
)

func TestExpireQueriesDropsTableAndRows(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	s := &Scheduler{eng: eng}

	mock.ExpectQuery("FROM parallel_database_query\\s+WHERE time_to_live").
		WillReturnRows(sqlmock.NewRows([]string{"id", "map_table"}).AddRow(int64(1), "map_1"))
	mock.ExpectExec("DROP TABLE IF EXISTS .map_1.").
		WillReturnResult(sqlmock.NewResult(0, 0))
	mock.ExpectExec("DELETE FROM parallel_database_query_shard WHERE query_id").
		WithArgs(int64(1)).
		WillReturnResult(sqlmock.NewResult(0, 1))
	mock.ExpectExec("DELETE FROM parallel_database_query WHERE id").
		WithArgs(int64(1)).
		WillReturnResult(sqlmock.NewResult(0, 1))

	if err := s.expireQueries(context.Background()); err != nil {
		t.Fatalf("expireQueries: %v", err)
	}
}

// When the DROP fails we must keep the query row so a later pass can retry,
// otherwise the intermediary table is orphaned.
func TestExpireQueriesKeepsRowOnDropFailure(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	s := &Scheduler{eng: eng}

	mock.ExpectQuery("FROM parallel_database_query\\s+WHERE time_to_live").
		WillReturnRows(sqlmock.NewRows([]string{"id", "map_table"}).AddRow(int64(2), "map_2"))
	mock.ExpectExec("DROP TABLE IF EXISTS .map_2.").
		WillReturnError(errors.New("boom"))
	// No DELETE expectations: the row must be left in place.

	if err := s.expireQueries(context.Background()); err != nil {
		t.Fatalf("expireQueries: %v", err)
	}
}

func TestExpireShardCache(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	s := &Scheduler{eng: eng}

	mock.ExpectExec("DELETE FROM parallel_database_query_shard_cache").
		WithArgs(int64(eng.settings.TimeToLive().Seconds())).
		WillReturnResult(sqlmock.NewResult(0, 3))

	if err := s.expireShardCache(context.Background()); err != nil {
		t.Fatalf("expireShardCache: %v", err)
	}
}

func TestExpireShardCacheSkippedWhenTTLZero(t *testing.T) {
	flow, _, cleanup := newMock(t)
	defer cleanup()
	settings := defaultSettings()
	settings.timeToLive = 0
	eng := NewEngine(flow, nil, nil, settings)
	s := &Scheduler{eng: eng}

	// No expectations: a zero TTL must skip the delete entirely.
	if err := s.expireShardCache(context.Background()); err != nil {
		t.Fatalf("expireShardCache: %v", err)
	}
}

func TestDropOrphanCache(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	s := &Scheduler{eng: eng}

	mock.ExpectQuery("information_schema.TABLES").
		WillReturnRows(sqlmock.NewRows([]string{"map_table"}).AddRow("orphan_1"))
	mock.ExpectExec("DELETE FROM parallel_database_query_shard_cache WHERE map_table").
		WithArgs("orphan_1").
		WillReturnResult(sqlmock.NewResult(0, 1))

	if err := s.dropOrphanCache(context.Background()); err != nil {
		t.Fatalf("dropOrphanCache: %v", err)
	}
}
