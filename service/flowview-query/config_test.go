package main

import (
	"os"
	"strings"
	"testing"
	"time"
)

func TestDSNDisablesParseTime(t *testing.T) {
	db := DBConfig{Host: "127.0.0.1", Port: 3306, User: "u", Password: "p", Database: "d"}
	dsn := db.dsn()
	if strings.Contains(dsn, "parseTime=true") {
		t.Fatalf("parseTime must be disabled so DATETIME arrives as a SQL string: %s", dsn)
	}
	if !strings.Contains(dsn, "charset=utf8mb4") {
		t.Fatalf("charset missing from dsn: %s", dsn)
	}
}

func TestDSNDefaultsPort(t *testing.T) {
	db := DBConfig{Host: "db", User: "u"}
	if !strings.Contains(db.dsn(), "db:3306") {
		t.Fatalf("expected default port 3306: %s", db.dsn())
	}
}

func TestLoadConfigDefaults(t *testing.T) {
	c, err := LoadConfig("")
	if err != nil {
		t.Fatalf("LoadConfig: %v", err)
	}
	if c.ListenHost != "127.0.0.1" {
		t.Fatalf("default listen host should be loopback, got %q", c.ListenHost)
	}
	if c.SchedulerInterval.D() != 60*time.Second || c.SettingsRefresh.D() != 60*time.Second {
		t.Fatalf("default intervals wrong: %v / %v", c.SchedulerInterval.D(), c.SettingsRefresh.D())
	}
}

func TestLoadConfigTokenEnvOverride(t *testing.T) {
	t.Setenv("FLOWVIEW_QUERY_TOKEN", "s3cret")
	c, err := LoadConfig("")
	if err != nil {
		t.Fatalf("LoadConfig: %v", err)
	}
	if c.AuthToken != "s3cret" {
		t.Fatalf("env token not applied: %q", c.AuthToken)
	}
}

func TestLoadConfigFromFile(t *testing.T) {
	dir := t.TempDir()
	path := dir + "/cfg.json"
	body := `{"listen_host":"127.0.0.1","listen_port":9123,"scheduler_interval":"30s","auth_token":"file-tok"}`
	if err := os.WriteFile(path, []byte(body), 0o600); err != nil {
		t.Fatalf("write: %v", err)
	}
	c, err := LoadConfig(path)
	if err != nil {
		t.Fatalf("LoadConfig: %v", err)
	}
	if c.ListenPort != 9123 || c.AuthToken != "file-tok" || c.SchedulerInterval.D() != 30*time.Second {
		t.Fatalf("file config not applied: %#v", c)
	}
}

func TestDurationUnmarshal(t *testing.T) {
	var d Duration
	if err := d.UnmarshalJSON([]byte(`"2m"`)); err != nil {
		t.Fatalf("unmarshal: %v", err)
	}
	if d.D() != 2*time.Minute {
		t.Fatalf("want 2m, got %v", d.D())
	}
	if err := d.UnmarshalJSON([]byte(`"not-a-duration"`)); err == nil {
		t.Fatalf("expected error for bad duration")
	}
}

func TestSettingsRefresh(t *testing.T) {
	cacti, mock, cleanup := newMock(t)
	defer cleanup()

	mock.ExpectQuery("SELECT value FROM settings").
		WithArgs("flowview_parallel_threads").
		WillReturnRows(mockSetting("8"))
	mock.ExpectQuery("SELECT value FROM settings").
		WithArgs("flowview_parallel_runlimit").
		WillReturnRows(mockSetting("120"))
	mock.ExpectQuery("SELECT value FROM settings").
		WithArgs("flowview_parallel_time_to_live").
		WillReturnRows(mockSetting("600"))
	mock.ExpectQuery("SELECT value FROM settings").
		WithArgs("flowview_use_maxscale").
		WillReturnRows(mockSetting("on"))

	s := defaultSettings()
	s.refresh(cacti)

	if s.Threads() != 8 {
		t.Fatalf("threads = %d, want 8", s.Threads())
	}
	if s.RunLimit() != 120*time.Second {
		t.Fatalf("runlimit = %v, want 120s", s.RunLimit())
	}
	if s.TimeToLive() != 600*time.Second {
		t.Fatalf("ttl = %v, want 600s", s.TimeToLive())
	}
	if !s.UseMaxScale() {
		t.Fatalf("maxscale should be on")
	}
}

func TestSettingsThreadsFloor(t *testing.T) {
	s := &Settings{threads: 0}
	if s.Threads() != 1 {
		t.Fatalf("Threads() should floor at 1, got %d", s.Threads())
	}
}
