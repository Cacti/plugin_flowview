package main

import (
	"database/sql"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
)

// newRequest builds an *http.Request with an optional Authorization header.
func newRequest(method, target, auth string) *http.Request {
	r := httptest.NewRequest(method, target, nil)
	if auth != "" {
		r.Header.Set("Authorization", auth)
	}
	return r
}

// newMock returns a *sql.DB backed by sqlmock with unordered expectation
// matching (the engine fans shards out across goroutines) plus a cleanup
// function that asserts every expectation was met.
func newMock(t *testing.T) (*sql.DB, sqlmock.Sqlmock, func()) {
	t.Helper()
	conn, mock, err := sqlmock.New()
	if err != nil {
		t.Fatalf("sqlmock.New: %v", err)
	}
	mock.MatchExpectationsInOrder(false)
	cleanup := func() {
		if err := mock.ExpectationsWereMet(); err != nil {
			t.Errorf("unmet sqlmock expectations: %v", err)
		}
		conn.Close()
	}
	return conn, mock, cleanup
}

// mockSetting builds a single-column "value" result row for a settings lookup.
func mockSetting(value string) *sqlmock.Rows {
	return sqlmock.NewRows([]string{"value"}).AddRow(value)
}
