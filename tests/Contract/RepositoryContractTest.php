<?php

it('declares the tables required by collection, reporting, and caching', function () {
	$source = plugin_test_read_source('setup.php');
	foreach (array(
		'plugin_flowview_devices',
		'plugin_flowview_device_streams',
		'plugin_flowview_device_templates',
		'plugin_flowview_queries',
		'plugin_flowview_schedules',
		'plugin_flowview_ports',
		'parallel_database_query',
		'parallel_database_query_shard',
		'parallel_database_query_shard_cache',
	) as $table) {
		expect($source)->toContain("CREATE TABLE IF NOT EXISTS `$table`");
	}
});

it('ships a nonempty and parseable service-port seed', function () {
	$sql = trim(file_get_contents(dirname(__DIR__, 2) . '/plugin_flowview_ports.sql'));
	expect($sql)->toStartWith('INSERT INTO `plugin_flowview_ports` VALUES (')
		->and($sql)->toEndWith(';')
		->and(substr_count($sql, '),('))->toBeGreaterThan(1000)
		->and($sql)->toContain("'tcp'")
		->and($sql)->toContain("'udp'");
});
