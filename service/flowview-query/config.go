package main

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"os"
	"strconv"
	"sync"
	"time"

	"github.com/go-sql-driver/mysql"
)

// DBConfig holds the credentials for one database endpoint.
type DBConfig struct {
	Host     string `json:"host"`
	Port     int    `json:"port"`
	User     string `json:"user"`
	Password string `json:"password"`
	Database string `json:"database"`
	TLS      string `json:"tls"` // "", "true", "skip-verify", or a registered name
}

// Config is the service configuration, loaded from a JSON file and overridable
// by environment variables. It mirrors Cacti's config.php / flowview
// config.php.dist split: a Cacti control database (settings, processes) and a
// FlowView data database (flow partitions + the parallel_* tables), which may
// be the same server or separate.
type Config struct {
	// Listen address. Host is forced to loopback; Port 0 selects a free port.
	ListenHost string `json:"listen_host"`
	ListenPort int    `json:"listen_port"`

	// PortFile, when set, receives the chosen "host:port" so the PHP side can
	// discover the endpoint (the service binds an ephemeral port by default).
	PortFile string `json:"port_file"`

	// Cacti is the Cacti control DB (settings, processes tables).
	Cacti DBConfig `json:"cacti"`

	// FlowView is the flow data DB. When UseCactiDB is true it is ignored and
	// the Cacti connection is reused (matching $flowview_use_cacti_db).
	UseCactiDB bool     `json:"use_cacti_db"`
	FlowView   DBConfig `json:"flowview"`

	// MaxScale is the optional read/write-split endpoint used to round-robin
	// the map (read) queries across MariaDB backends. When Enabled is false
	// the FlowView connection is used for reads too.
	MaxScale struct {
		Enabled bool   `json:"enabled"`
		Host    string `json:"host"`
		Port    int    `json:"port"`
	} `json:"maxscale"`

	// SchedulerInterval controls how often the maintenance goroutine runs.
	SchedulerInterval Duration `json:"scheduler_interval"`

	// SettingsRefresh controls how often live Cacti settings are re-read.
	SettingsRefresh Duration `json:"settings_refresh"`
}

// Duration is a JSON-friendly time.Duration ("30s", "5m").
type Duration time.Duration

func (d *Duration) UnmarshalJSON(b []byte) error {
	var s string
	if err := json.Unmarshal(b, &s); err != nil {
		return err
	}
	v, err := time.ParseDuration(s)
	if err != nil {
		return err
	}
	*d = Duration(v)
	return nil
}

func (d Duration) D() time.Duration { return time.Duration(d) }

// LoadConfig reads the JSON config file and applies environment overrides.
func LoadConfig(path string) (*Config, error) {
	c := &Config{
		ListenHost:        "127.0.0.1",
		SchedulerInterval: Duration(60 * time.Second),
		SettingsRefresh:   Duration(60 * time.Second),
	}

	if path != "" {
		raw, err := os.ReadFile(path)
		if err != nil {
			return nil, fmt.Errorf("read config: %w", err)
		}
		if err := json.Unmarshal(raw, c); err != nil {
			return nil, fmt.Errorf("parse config: %w", err)
		}
	}

	applyEnvDB("CACTI", &c.Cacti)
	applyEnvDB("FLOWVIEW", &c.FlowView)

	if c.ListenHost == "" {
		c.ListenHost = "127.0.0.1"
	}
	if c.SchedulerInterval.D() == 0 {
		c.SchedulerInterval = Duration(60 * time.Second)
	}
	if c.SettingsRefresh.D() == 0 {
		c.SettingsRefresh = Duration(60 * time.Second)
	}

	return c, nil
}

func applyEnvDB(prefix string, db *DBConfig) {
	if v := os.Getenv(prefix + "_DB_HOST"); v != "" {
		db.Host = v
	}
	if v := os.Getenv(prefix + "_DB_PORT"); v != "" {
		if p, err := strconv.Atoi(v); err == nil {
			db.Port = p
		}
	}
	if v := os.Getenv(prefix + "_DB_USER"); v != "" {
		db.User = v
	}
	if v := os.Getenv(prefix + "_DB_PASS"); v != "" {
		db.Password = v
	}
	if v := os.Getenv(prefix + "_DB_NAME"); v != "" {
		db.Database = v
	}
}

func (db DBConfig) dsn() string {
	port := db.Port
	if port == 0 {
		port = 3306
	}
	cfg := mysql.NewConfig()
	cfg.User = db.User
	cfg.Passwd = db.Password
	cfg.Net = "tcp"
	cfg.Addr = fmt.Sprintf("%s:%d", db.Host, port)
	cfg.DBName = db.Database
	cfg.ParseTime = true
	cfg.Loc = time.Local
	cfg.Params = map[string]string{"charset": "utf8mb4"}
	if db.TLS != "" {
		cfg.TLSConfig = db.TLS
	}
	return cfg.FormatDSN()
}

// open opens and verifies a pooled connection.
func (db DBConfig) open(maxOpen int) (*sql.DB, error) {
	h, err := sql.Open("mysql", db.dsn())
	if err != nil {
		return nil, err
	}
	h.SetMaxOpenConns(maxOpen)
	h.SetMaxIdleConns(maxOpen)
	h.SetConnMaxLifetime(5 * time.Minute)
	return h, h.Ping()
}

// Settings holds the live Cacti settings the engine honours, read from the
// Cacti `settings` table (read_config_option equivalents).
type Settings struct {
	mu          sync.RWMutex
	threads     int
	runLimit    time.Duration
	timeToLive  time.Duration
	useMaxScale bool
}

func defaultSettings() *Settings {
	return &Settings{
		threads:    4,
		runLimit:   300 * time.Second,
		timeToLive: 21600 * time.Second,
	}
}

func (s *Settings) Threads() int {
	s.mu.RLock()
	defer s.mu.RUnlock()
	if s.threads < 1 {
		return 1
	}
	return s.threads
}

func (s *Settings) TimeToLive() time.Duration {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.timeToLive
}

func (s *Settings) RunLimit() time.Duration {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.runLimit
}

func (s *Settings) UseMaxScale() bool {
	s.mu.RLock()
	defer s.mu.RUnlock()
	return s.useMaxScale
}

// refresh re-reads the relevant options from the Cacti settings table.
func (s *Settings) refresh(cacti *sql.DB) {
	get := func(name string, def int) int {
		var v sql.NullString
		err := cacti.QueryRow("SELECT value FROM settings WHERE name = ?", name).Scan(&v)
		if err != nil || !v.Valid || v.String == "" {
			return def
		}
		n, err := strconv.Atoi(v.String)
		if err != nil {
			return def
		}
		return n
	}

	threads := get("flowview_parallel_threads", 4)
	runLimit := get("flowview_parallel_runlimit", 300)
	ttl := get("flowview_parallel_time_to_live", 21600)

	var ms sql.NullString
	_ = cacti.QueryRow("SELECT value FROM settings WHERE name = ?", "flowview_use_maxscale").Scan(&ms)

	s.mu.Lock()
	s.threads = threads
	s.runLimit = time.Duration(runLimit) * time.Second
	s.timeToLive = time.Duration(ttl) * time.Second
	s.useMaxScale = ms.Valid && ms.String == "on"
	s.mu.Unlock()
}
