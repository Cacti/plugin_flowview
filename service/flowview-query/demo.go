package main

import (
	"fmt"
	"sort"
	"sync"
	"sync/atomic"
	"time"
)

// demoMapReduce exercises the engine's concurrency + partition-cache design
// entirely in memory (no database) so the service can be verified end-to-end on
// a host without MariaDB. It mirrors the real flow: one shard per partition, a
// bounded worker pool, a full-scan partition cache (cold -> cached), and a
// reduce that re-aggregates the per-shard group-by output.
func demoMapReduce(threads int) {
	partitions := map[string][]demoFlow{
		"plugin_flowview_arin_2026010100": {{"10.0.0.1", 1200}, {"10.0.0.2", 300}, {"10.0.0.1", 800}},
		"plugin_flowview_arin_2026010101": {{"10.0.0.2", 500}, {"10.0.0.3", 900}, {"10.0.0.1", 100}},
		"plugin_flowview_arin_2026010102": {{"10.0.0.3", 150}, {"10.0.0.1", 50}, {"10.0.0.2", 700}},
		"plugin_flowview_arin_2026010103": {{"10.0.0.1", 2000}, {"10.0.0.3", 300}},
	}

	cache := &demoCache{m: map[string][]demoAgg{}}

	run := func(label string) {
		start := time.Now()
		var cachedShards int64

		names := make([]string, 0, len(partitions))
		for n := range partitions {
			names = append(names, n)
		}
		sort.Strings(names)

		sem := make(chan struct{}, threads)
		var wg sync.WaitGroup
		var mu sync.Mutex
		combined := map[string]int64{}

		for _, name := range names {
			wg.Add(1)
			sem <- struct{}{}

			go func(name string) {
				defer wg.Done()
				defer func() { <-sem }()

				agg, hit := cache.get(name)
				if hit {
					atomic.AddInt64(&cachedShards, 1)
				} else {
					agg = mapPartition(partitions[name]) // the map phase
					cache.put(name, agg)
				}

				mu.Lock()
				for _, a := range agg { // partial reduce merge
					combined[a.key] += a.bytes
				}
				mu.Unlock()
			}(name)
		}
		wg.Wait()

		type kv struct {
			key   string
			bytes int64
		}
		final := make([]kv, 0, len(combined))
		for k, v := range combined {
			final = append(final, kv{k, v})
		}
		sort.Slice(final, func(i, j int) bool { return final[i].bytes > final[j].bytes })

		fmt.Printf("  [%s] threads=%d shards=%d cached=%d elapsed=%s\n",
			label, threads, len(names), cachedShards, time.Since(start).Round(time.Microsecond))
		for _, f := range final {
			fmt.Printf("     %-12s %6d bytes\n", f.key, f.bytes)
		}
	}

	fmt.Println("Self-test map-reduce (in-memory, no database):")
	run("cold run  ")
	run("warm run  ") // partition cache hit: every shard served from cache
}

type demoFlow struct {
	src   string
	bytes int64
}

type demoAgg struct {
	key   string
	bytes int64
}

func mapPartition(rows []demoFlow) []demoAgg {
	time.Sleep(10 * time.Millisecond) // simulate the cost of scanning a partition
	m := map[string]int64{}
	for _, r := range rows {
		m[r.src] += r.bytes
	}
	out := make([]demoAgg, 0, len(m))
	for k, v := range m {
		out = append(out, demoAgg{k, v})
	}
	return out
}

type demoCache struct {
	mu sync.Mutex
	m  map[string][]demoAgg
}

func (c *demoCache) get(k string) ([]demoAgg, bool) {
	c.mu.Lock()
	defer c.mu.Unlock()
	v, ok := c.m[k]
	return v, ok
}

func (c *demoCache) put(k string, v []demoAgg) {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.m[k] = v
}
