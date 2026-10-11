package main

import (
	"context"
	"os"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
)

func TestDemoMapReduceRuns(t *testing.T) {
	// Smoke-test the in-memory self-test path so a DB-less host can validate
	// the binary; it must not panic.
	demoMapReduce(2)
}

func TestMaxInt(t *testing.T) {
	if maxInt(3, 7) != 7 || maxInt(9, 2) != 9 || maxInt(4, 4) != 4 {
		t.Fatalf("maxInt wrong")
	}
}

func TestWritePortFile(t *testing.T) {
	if err := writePortFile("", "ignored"); err != nil {
		t.Fatalf("empty path should be a no-op, got %v", err)
	}

	path := t.TempDir() + "/port"
	if err := writePortFile(path, "127.0.0.1:9999"); err != nil {
		t.Fatalf("writePortFile: %v", err)
	}
	b, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("read: %v", err)
	}
	if string(b) != "127.0.0.1:9999\n" {
		t.Fatalf("port file content = %q", b)
	}
}

func TestInvalidateCacheRequiresMD5(t *testing.T) {
	eng := NewEngine(nil, nil, nil, defaultSettings())
	if _, err := eng.InvalidateCache(context.Background(), ""); err == nil {
		t.Fatalf("empty md5sum should error")
	}
}

func TestSchedulerRunOnce(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	s := &Scheduler{eng: eng}

	// expireQueries: no expired rows
	mock.ExpectQuery("FROM parallel_database_query\\s+WHERE time_to_live").
		WillReturnRows(sqlmock.NewRows([]string{"id", "map_table"}))
	// expireShardCache
	mock.ExpectExec("DELETE FROM parallel_database_query_shard_cache").
		WillReturnResult(sqlmock.NewResult(0, 0))
	// dropOrphanCache: nothing orphaned
	mock.ExpectQuery("information_schema.TABLES").
		WillReturnRows(sqlmock.NewRows([]string{"map_table"}))

	s.RunOnce(context.Background())
}
