package main

import (
	"context"
	"encoding/json"
	"net/http"
	"time"
)

// Server exposes the engine over localhost HTTP.
type Server struct {
	eng   *Engine
	sched *Scheduler
}

func (s *Server) routes() http.Handler {
	mux := http.NewServeMux()
	mux.HandleFunc("/health", s.handleHealth)
	mux.HandleFunc("/run", s.handleRun)
	mux.HandleFunc("/maintenance", s.handleMaintenance)
	mux.HandleFunc("/cache/invalidate", s.handleInvalidate)
	return mux
}

func (s *Server) handleHealth(w http.ResponseWriter, r *http.Request) {
	status := "ok"
	code := http.StatusOK

	if s.eng.flow != nil {
		ctx, cancel := context.WithTimeout(r.Context(), 3*time.Second)
		defer cancel()
		if err := s.eng.flow.PingContext(ctx); err != nil {
			status = "degraded: " + err.Error()
			code = http.StatusServiceUnavailable
		}
	}

	writeJSON(w, code, map[string]any{
		"status":  status,
		"threads": s.eng.settings.Threads(),
	})
}

func (s *Server) handleRun(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Error(w, "POST required", http.StatusMethodNotAllowed)
		return
	}

	var req RunRequest
	if err := json.NewDecoder(r.Body).Decode(&req); err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": "bad request: " + err.Error()})
		return
	}

	ctx, cancel := context.WithTimeout(r.Context(), s.eng.settings.RunLimit())
	defer cancel()

	resp, err := s.eng.RunQueries(ctx, req.QueryIDs)
	if err != nil {
		writeJSON(w, http.StatusInternalServerError, map[string]any{"error": err.Error(), "completed": resp.Completed})
		return
	}

	writeJSON(w, http.StatusOK, resp)
}

func (s *Server) handleMaintenance(w http.ResponseWriter, r *http.Request) {
	s.sched.RunOnce(r.Context())
	writeJSON(w, http.StatusOK, map[string]any{"status": "ok"})
}

func (s *Server) handleInvalidate(w http.ResponseWriter, r *http.Request) {
	md5sum := r.URL.Query().Get("md5sum")
	n, err := s.eng.InvalidateCache(r.Context(), md5sum)
	if err != nil {
		writeJSON(w, http.StatusBadRequest, map[string]any{"error": err.Error()})
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"invalidated": n})
}

func writeJSON(w http.ResponseWriter, code int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(code)
	enc := json.NewEncoder(w)
	_ = enc.Encode(v)
}
