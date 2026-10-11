package main

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/DATA-DOG/go-sqlmock"
)

func TestHealthIsOpen(t *testing.T) {
	srv := &Server{eng: NewEngine(nil, nil, nil, defaultSettings()), authToken: "tok"}
	w := httptest.NewRecorder()
	srv.handleHealth(w, newRequest("GET", "/health", ""))
	if w.Code != http.StatusOK {
		t.Fatalf("health status = %d, want 200", w.Code)
	}
}

func TestGuardRejectsNonPost(t *testing.T) {
	srv := &Server{}
	w := httptest.NewRecorder()
	if srv.guard(w, newRequest("GET", "/run", "")) {
		t.Fatalf("guard should reject GET")
	}
	if w.Code != http.StatusMethodNotAllowed {
		t.Fatalf("status = %d, want 405", w.Code)
	}
}

func TestGuardAllowsPostWhenNoToken(t *testing.T) {
	srv := &Server{}
	w := httptest.NewRecorder()
	if !srv.guard(w, newRequest("POST", "/run", "")) {
		t.Fatalf("guard should allow POST when no token configured")
	}
}

func TestGuardRequiresToken(t *testing.T) {
	srv := &Server{authToken: "sekret"}

	w := httptest.NewRecorder()
	if srv.guard(w, newRequest("POST", "/run", "")) {
		t.Fatalf("missing token should be rejected")
	}
	if w.Code != http.StatusUnauthorized {
		t.Fatalf("status = %d, want 401", w.Code)
	}

	w = httptest.NewRecorder()
	if srv.guard(w, newRequest("POST", "/run", "Bearer wrong")) {
		t.Fatalf("wrong token should be rejected")
	}

	w = httptest.NewRecorder()
	if !srv.guard(w, newRequest("POST", "/run", "Bearer sekret")) {
		t.Fatalf("correct token should be accepted")
	}
}

func TestHandleRunBadJSON(t *testing.T) {
	srv := &Server{}
	w := httptest.NewRecorder()
	r := httptest.NewRequest("POST", "/run", http.NoBody)
	srv.handleRun(w, r)
	if w.Code != http.StatusBadRequest {
		t.Fatalf("bad body status = %d, want 400", w.Code)
	}
}

func TestHandleMaintenanceRequiresPost(t *testing.T) {
	srv := &Server{}
	w := httptest.NewRecorder()
	srv.handleMaintenance(w, newRequest("GET", "/maintenance", ""))
	if w.Code != http.StatusMethodNotAllowed {
		t.Fatalf("status = %d, want 405", w.Code)
	}
}

func TestHandleInvalidate(t *testing.T) {
	flow, mock, cleanup := newMock(t)
	defer cleanup()
	eng := NewEngine(flow, nil, nil, defaultSettings())
	srv := &Server{eng: eng, sched: &Scheduler{eng: eng}}

	mock.ExpectExec("DELETE FROM parallel_database_query_shard_cache WHERE md5sum").
		WithArgs("abc").
		WillReturnResult(sqlmock.NewResult(0, 2))

	w := httptest.NewRecorder()
	srv.handleInvalidate(w, newRequest("POST", "/cache/invalidate?md5sum=abc", ""))
	if w.Code != http.StatusOK {
		t.Fatalf("invalidate status = %d, want 200", w.Code)
	}
}
