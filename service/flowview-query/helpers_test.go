package main

import (
	"encoding/json"
	"math"
	"strings"
	"testing"
	"time"
)

func TestNormalizeVal(t *testing.T) {
	ts := time.Date(2024, 3, 2, 13, 4, 5, 0, time.UTC)

	cases := []struct {
		name string
		in   any
		want any
	}{
		{"bytes to string", []byte("hello"), "hello"},
		{"time to sql datetime", ts, "2024-03-02 13:04:05"},
		{"int passthrough", int64(7), int64(7)},
		{"nil passthrough", nil, nil},
		{"string passthrough", "x", "x"},
	}

	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			if got := normalizeVal(c.in); got != c.want {
				t.Fatalf("normalizeVal(%v) = %v, want %v", c.in, got, c.want)
			}
		})
	}
}

func TestNormalizeParam(t *testing.T) {
	if got := normalizeParam(float64(42)); got != int64(42) {
		t.Fatalf("whole float64 should become int64, got %v (%T)", got, got)
	}
	if got := normalizeParam(float64(1.5)); got != float64(1.5) {
		t.Fatalf("fractional float64 should stay float, got %v", got)
	}
	if got := normalizeParam(math.Inf(1)); got != math.Inf(1) {
		t.Fatalf("inf float64 should pass through, got %v", got)
	}
	if got := normalizeParam(json.Number("13")); got != int64(13) {
		t.Fatalf("integral json.Number should become int64, got %v (%T)", got, got)
	}
	if got := normalizeParam(json.Number("2.5")); got != float64(2.5) {
		t.Fatalf("decimal json.Number should become float64, got %v (%T)", got, got)
	}
	if got := normalizeParam(json.Number("not-a-number")); got != "not-a-number" {
		t.Fatalf("unparsable json.Number should fall back to its string, got %v", got)
	}
	if got := normalizeParam("keep"); got != "keep" {
		t.Fatalf("string should pass through, got %v", got)
	}
}

func TestNormalizeParams(t *testing.T) {
	out := normalizeParams([]any{float64(3), "a", json.Number("4")})
	if len(out) != 3 || out[0] != int64(3) || out[1] != "a" || out[2] != int64(4) {
		t.Fatalf("normalizeParams unexpected: %#v", out)
	}
}

func TestQuoteTable(t *testing.T) {
	cases := map[string]string{
		"flows":       "`flows`",
		"cacti.flows": "`cacti`.`flows`",
		"we`ird":      "`we``ird`",
		"db.tab`le":   "`db`.`tab``le`",
	}
	for in, want := range cases {
		if got := quoteTable(in); got != want {
			t.Fatalf("quoteTable(%q) = %q, want %q", in, got, want)
		}
	}
}

func TestAppendClause(t *testing.T) {
	var b strings.Builder
	b.WriteString("SELECT 1")
	appendClause(&b, "")
	appendClause(&b, "WHERE x = 1")
	if got := b.String(); got != "SELECT 1 WHERE x = 1" {
		t.Fatalf("appendClause produced %q", got)
	}
}

func TestDecodeParams(t *testing.T) {
	if got := decodeParams(nil); got != nil {
		t.Fatalf("nil input should decode to nil, got %#v", got)
	}
	if got := decodeParams([]byte("   ")); got != nil {
		t.Fatalf("blank input should decode to nil, got %#v", got)
	}
	if got := decodeParams([]byte("not json")); got != nil {
		t.Fatalf("invalid json should decode to nil, got %#v", got)
	}
	got := decodeParams([]byte(`[1,"two",3]`))
	if len(got) != 3 || got[1] != "two" {
		t.Fatalf("decodeParams unexpected: %#v", got)
	}
}

func TestDecodeRows(t *testing.T) {
	if rows, err := decodeRows(nil); err != nil || rows != nil {
		t.Fatalf("empty blob: rows=%v err=%v", rows, err)
	}
	if rows, err := decodeRows([]byte(`{"not":"array"}`)); err != nil || rows != nil {
		t.Fatalf("non-array blob should yield nil rows, got rows=%v err=%v", rows, err)
	}

	rows, err := decodeRows([]byte(`[{"a":1,"b":"x"},{"a":2,"b":"y"}]`))
	if err != nil {
		t.Fatalf("decodeRows error: %v", err)
	}
	if len(rows) != 2 {
		t.Fatalf("want 2 rows, got %d", len(rows))
	}
	// Column order is preserved from the JSON object.
	if got := strings.Join(rows[0].Cols, ","); got != "a,b" {
		t.Fatalf("column order not preserved: %q", got)
	}
	if rows[1].Vals["b"] != "y" {
		t.Fatalf("value mismatch: %#v", rows[1].Vals)
	}
}

func TestOrderedRowMarshalJSON(t *testing.T) {
	row := &OrderedRow{
		Cols: []string{"z", "a"},
		Vals: map[string]any{"z": 1, "a": "x"},
	}
	blob, err := json.Marshal(row)
	if err != nil {
		t.Fatalf("marshal: %v", err)
	}
	// Order must follow Cols, not Go's map key sorting.
	if string(blob) != `{"z":1,"a":"x"}` {
		t.Fatalf("ordered marshal wrong: %s", blob)
	}
}

func TestIsLoopbackHost(t *testing.T) {
	cases := map[string]bool{
		"":            true,
		"localhost":   true,
		"127.0.0.1":   true,
		"::1":         true,
		"0.0.0.0":     false,
		"10.0.0.5":    false,
		"example.com": false,
	}
	for host, want := range cases {
		if got := isLoopbackHost(host); got != want {
			t.Fatalf("isLoopbackHost(%q) = %v, want %v", host, got, want)
		}
	}
}

func TestBearer(t *testing.T) {
	cases := map[string]string{
		"Bearer abc":    "abc",
		"Bearer  abc  ": "abc",
		"bearer abc":    "",
		"Token abc":     "",
		"":              "",
	}
	for header, want := range cases {
		r := newRequest("GET", "/", header)
		if got := bearer(r); got != want {
			t.Fatalf("bearer(%q) = %q, want %q", header, got, want)
		}
	}
}
