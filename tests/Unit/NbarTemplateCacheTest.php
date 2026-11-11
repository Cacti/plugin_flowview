<?php
// Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later.
beforeEach(function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_collector.php');
});
it('bounds source-ID churn and keeps the most recently used templates', function () {
	$cache    = new FlowviewNbarTemplateCache();
	$template = [[[95, 4]], 4, false];

	for ($i = 0; $i < 64; $i++) {
		expect($cache->store('192.0.2.1', (string) $i, 256, $template, 100))->toBeTrue();
	}
	expect($cache->get('192.0.2.1', '0', 256, 101))->toBe($template);
	$cache->store('192.0.2.1', '64', 256, $template, 101);
	expect($cache->get('192.0.2.1', '1', 256, 101))->toBeNull()->and($cache->get('192.0.2.1', '0', 256, 101))->toBe($template);

	for ($i = 65; $i < 500; $i++) {
		$cache->store('192.0.2.1', (string) $i, 256, $template, 101);
	}
	expect($cache->usage('192.0.2.1')['exporter_templates'])->toBe(64);
});
it('bounds global template count independently of per-exporter churn', function () {
	$cache    = new FlowviewNbarTemplateCache();
	$template = [[[95, 4]], 4, false];

	for ($i = 0; $i < 300; $i++) {
		$cache->store('192.0.2.' . ($i % 5), (string) $i, 256, $template, 100);
	}
	expect($cache->usage('192.0.2.0')['templates'])->toBe(256);
	expect($cache->get('192.0.2.0', '0', 256, 101))->toBeNull();
});
it('bounds retained field definitions globally and per exporter and rejects oversized templates', function () {
	$cache = new FlowviewNbarTemplateCache();
	$large = [array_fill(0, 2048, [1, 1]), 2048, false];
	$cache->store('192.0.2.1', 'a', 256, $large, 100);
	$cache->store('192.0.2.1', 'b', 256, $large, 100);
	expect($cache->get('192.0.2.1', 'a', 256, 101))->toBeNull();

	for ($i = 2; $i < 7; $i++) {
		$cache->store('192.0.2.' . $i, 'a', 256, $large, 100);
	}
	expect($cache->usage('192.0.2.6')['fields'])->toBe(8192)->and($cache->usage('192.0.2.6')['exporter_fields'])->toBe(2048);
	expect($cache->store('192.0.2.6', 'a', 256, [array_fill(0, 2049, [1, 1]), 2049, false], 101))->toBeFalse();
	expect($cache->get('192.0.2.6', 'a', 256, 102))->toBeNull();
});
it('expires inactive templates and refreshes activity on successful use', function () {
	$cache    = new FlowviewNbarTemplateCache();
	$template = [[[95, 4]], 4, false];
	$cache->store('192.0.2.1', 'a', 256, $template, 100);
	$cache->store('192.0.2.1', 'b', 256, $template, 100);
	$cache->get('192.0.2.1', 'a', 256, 1800);
	$cache->prune(1900);
	expect($cache->usage('192.0.2.1')['templates'])->toBe(1);
	expect($cache->get('192.0.2.1','a',256,3600))->toBeNull();
});
it('rejects numeric values wider than unsigned 64-bit before conversion',function () {
	expect(fn () => fv_nbar_uint(str_repeat('x',4096)))->toThrow(InvalidArgumentException::class);
});
