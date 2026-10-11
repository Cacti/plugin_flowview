package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"io"
	"log"
	"net"
	"net/http"
	"os"
	"os/signal"
	"strconv"
	"syscall"
	"time"

	_ "github.com/go-sql-driver/mysql"
)

var logger = log.New(os.Stderr, "flowview-query: ", log.LstdFlags|log.Lmsgprefix)

func logf(format string, args ...any) { logger.Printf(format, args...) }

func main() {
	var (
		configPath = flag.String("config", "", "path to the JSON config file")
		selftest   = flag.Bool("selftest", false, "run an in-memory self-test (no database) and exit")
		addr       = flag.String("addr", "", "override listen address host:port (port 0 selects a free port)")
	)
	flag.Parse()

	cfg, err := LoadConfig(*configPath)
	if err != nil {
		logger.Fatalf("config: %v", err)
	}

	if *addr != "" {
		host, port, perr := net.SplitHostPort(*addr)
		if perr != nil {
			logger.Fatalf("addr: %v", perr)
		}
		cfg.ListenHost = host
		if p, cerr := strconv.Atoi(port); cerr == nil {
			cfg.ListenPort = p
		}
	}

	if *selftest {
		if err := runSelftest(cfg); err != nil {
			logger.Fatalf("selftest: %v", err)
		}
		return
	}

	if err := runService(cfg); err != nil {
		logger.Fatalf("%v", err)
	}
}

// runService opens the databases, wires the engine/scheduler/server, binds a
// loopback port and serves until signalled.
func runService(cfg *Config) error {
	settings := defaultSettings()

	cacti, err := cfg.Cacti.open(8)
	if err != nil {
		return fmt.Errorf("connect cacti db: %w", err)
	}
	defer cacti.Close()

	settings.refresh(cacti)

	var flow *sql.DB
	if cfg.UseCactiDB {
		flow = cacti
	} else {
		flow, err = cfg.FlowView.open(16)
		if err != nil {
			return fmt.Errorf("connect flowview db: %w", err)
		}
		defer flow.Close()
	}

	var maxscale *sql.DB
	if cfg.MaxScale.Enabled {
		ms := cfg.FlowView
		if cfg.UseCactiDB {
			ms = cfg.Cacti
		}
		if cfg.MaxScale.Host != "" {
			ms.Host = cfg.MaxScale.Host
		}
		if cfg.MaxScale.Port != 0 {
			ms.Port = cfg.MaxScale.Port
		}
		maxscale, err = ms.open(maxInt(settings.Threads(), 4))
		if err != nil {
			return fmt.Errorf("connect maxscale: %w", err)
		}
		defer maxscale.Close()
		logf("MaxScale pool ready at %s:%d (map reads follow the live flowview_use_maxscale setting)", ms.Host, ms.Port)
	}

	eng := NewEngine(flow, maxscale, cacti, settings)
	sched := &Scheduler{eng: eng, interval: cfg.SchedulerInterval.D()}
	srv := &Server{eng: eng, sched: sched, authToken: cfg.AuthToken}

	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	// Live-refresh Cacti settings in the background.
	go func() {
		t := time.NewTicker(cfg.SettingsRefresh.D())
		defer t.Stop()
		for {
			select {
			case <-ctx.Done():
				return
			case <-t.C:
				settings.refresh(cacti)
			}
		}
	}()

	// Scheduler goroutine for cache/partition maintenance.
	go sched.Run(ctx)

	if !isLoopbackHost(cfg.ListenHost) {
		return fmt.Errorf("listen_host %q is not a loopback address: the query service is unauthenticated by default and must bind to loopback (use a reverse proxy or set auth_token for remote access)", cfg.ListenHost)
	}

	ln, err := net.Listen("tcp", net.JoinHostPort(cfg.ListenHost, strconv.Itoa(cfg.ListenPort)))
	if err != nil {
		return fmt.Errorf("listen: %w", err)
	}

	bound := ln.Addr().String()
	logf("listening on http://%s (threads=%d)", bound, settings.Threads())
	if err := writePortFile(cfg.PortFile, bound); err != nil {
		logf("warning: could not write port file: %v", err)
	}

	httpSrv := &http.Server{Handler: srv.routes()}

	go func() {
		<-ctx.Done()
		shutCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()
		_ = httpSrv.Shutdown(shutCtx)
	}()

	if err := httpSrv.Serve(ln); err != nil && err != http.ErrServerClosed {
		return err
	}
	logf("stopped")
	return nil
}

// runSelftest demonstrates the service without a database: it exercises the
// map-reduce/worker-pool/cache mechanics in memory and verifies the HTTP
// listener binds a free loopback port and answers /health.
func runSelftest(cfg *Config) error {
	demoMapReduce(4)

	eng := NewEngine(nil, nil, nil, defaultSettings())
	srv := &Server{eng: eng, sched: &Scheduler{eng: eng}}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		return err
	}
	defer ln.Close()

	httpSrv := &http.Server{Handler: srv.routes()}
	go httpSrv.Serve(ln)
	defer httpSrv.Close()

	base := "http://" + ln.Addr().String()
	fmt.Printf("\nHTTP listener bound at %s\n", base)

	resp, err := http.Get(base + "/health")
	if err != nil {
		return err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(resp.Body)
	fmt.Printf("GET /health -> %d %s", resp.StatusCode, string(body))

	fmt.Println("Self-test OK")
	return nil
}

func writePortFile(path, addr string) error {
	if path == "" {
		return nil
	}
	return os.WriteFile(path, []byte(addr+"\n"), 0o640)
}

func maxInt(a, b int) int {
	if a > b {
		return a
	}
	return b
}
