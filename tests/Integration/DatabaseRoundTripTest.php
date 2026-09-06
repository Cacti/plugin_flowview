<?php

uses()->group('integration');

beforeEach(function () {
	if (!getenv('FLOWVIEW_TEST_DB_DSN')) {
		$this->markTestSkipped('FLOWVIEW_TEST_DB_DSN is not configured');
	}
	plugin_test_reset();
	plugin_test_use_database(true);
	$GLOBALS['flowview_cnn'] = plugin_test_pdo();
	flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_test_raw');
	flowview_db_execute('CREATE TABLE plugin_flowview_test_raw (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		src_addr VARBINARY(16) NOT NULL,
		dst_addr VARBINARY(16) NOT NULL,
		protocol INT UNSIGNED NOT NULL,
		start_time TIMESTAMP(6) NOT NULL,
		end_time TIMESTAMP(6) NOT NULL,
		flows BIGINT UNSIGNED NOT NULL,
		packets BIGINT UNSIGNED NOT NULL,
		bytes BIGINT UNSIGNED NOT NULL,
		PRIMARY KEY (id), INDEX report_range (start_time, end_time, protocol)
	) ENGINE=InnoDB');
});

afterEach(function () {
	if (getenv('FLOWVIEW_TEST_DB_DSN')) {
		flowview_db_execute('DROP TABLE IF EXISTS plugin_flowview_test_raw');
		plugin_test_use_database(false);
	}
});

it('returns no rows when a nonempty saved filter is entirely invalid', function () {
	flowview_db_execute_prepared('INSERT INTO plugin_flowview_test_raw
		(src_addr, dst_addr, protocol, start_time, end_time, flows, packets, bytes)
		VALUES (INET6_ATON(?), INET6_ATON(?), ?, ?, ?, ?, ?, ?)',
		array('192.0.2.10', '198.51.100.20', 6, '2024-01-01 00:00:00', '2024-01-01 00:01:00', 1, 10, 1000));
	$params = array();
	$where = get_ip_filter('', $params, 'invalid-saved-address', 'src_addr');

	expect((int) flowview_db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_flowview_test_raw ' . $where, $params))->toBe(0);
});

it('round trips IPv4 and IPv6 flows through prepared adapters', function () {
	$insert = 'INSERT INTO plugin_flowview_test_raw
		(src_addr, dst_addr, protocol, start_time, end_time, flows, packets, bytes)
		VALUES (INET6_ATON(?), INET6_ATON(?), ?, ?, ?, ?, ?, ?)';
	flowview_db_execute_prepared($insert, array('192.0.2.10', '198.51.100.20', 6, '2024-01-01 00:00:00', '2024-01-01 00:01:00', 1, 10, 1000));
	flowview_db_execute_prepared($insert, array('2001:db8::10', '2001:db8::20', 17, '2024-01-01 00:02:00', '2024-01-01 00:03:00', 2, 20, 2000));

	$rows = flowview_db_fetch_assoc_prepared('SELECT INET6_NTOA(src_addr) AS source, protocol, flows, packets, bytes
		FROM plugin_flowview_test_raw WHERE start_time BETWEEN ? AND ? ORDER BY id',
		array('2024-01-01 00:00:00', '2024-01-01 00:05:00'));
	$totals = flowview_db_fetch_row('SELECT SUM(flows) AS flows, SUM(packets) AS packets, SUM(bytes) AS bytes
		FROM plugin_flowview_test_raw');

	expect($rows)->toHaveCount(2)
		->and($rows[0]['source'])->toBe('192.0.2.10')
		->and($rows[1]['source'])->toBe('2001:db8::10')
		->and((int) $totals['flows'])->toBe(3)
		->and((int) $totals['packets'])->toBe(30)
		->and((int) $totals['bytes'])->toBe(3000);
});

it('uses bound filters against the real connector', function () {
	flowview_db_execute_prepared('INSERT INTO plugin_flowview_test_raw
		(src_addr, dst_addr, protocol, start_time, end_time, flows, packets, bytes)
		VALUES (INET6_ATON(?), INET6_ATON(?), ?, ?, ?, ?, ?, ?)',
		array('192.0.2.10', '198.51.100.20', 6, '2024-01-01 00:00:00', '2024-01-01 00:01:00', 1, 10, 1000));
	$params = array();
	$where = get_numeric_filter('', $params, '6', 'protocol');

	expect((int) flowview_db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_flowview_test_raw ' . $where, $params))->toBe(1);
});

it('executes generated IP predicates for exact and CIDR matches', function () {
	$insert = 'INSERT INTO plugin_flowview_test_raw
		(src_addr, dst_addr, protocol, start_time, end_time, flows, packets, bytes)
		VALUES (INET6_ATON(?), INET6_ATON(?), ?, ?, ?, ?, ?, ?)';
	flowview_db_execute_prepared($insert, array('192.0.2.10', '198.51.100.20', 6, '2024-01-01 00:00:00', '2024-01-01 00:01:00', 1, 10, 1000));
	flowview_db_execute_prepared($insert, array('2001:db8::10', '2001:db8::20', 17, '2024-01-01 00:02:00', '2024-01-01 00:03:00', 1, 10, 1000));

	$exactParams = array();
	$exactWhere = get_ip_filter('', $exactParams, '192.0.2.10,2001:db8::10', 'src_addr');
	$cidrParams = array();
	$cidrWhere = get_ip_filter('', $cidrParams, '192.0.2.0/24', 'src_addr');

	expect((int) flowview_db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_flowview_test_raw ' . $exactWhere, $exactParams))->toBe(2)
		->and((int) flowview_db_fetch_cell_prepared('SELECT COUNT(*) FROM plugin_flowview_test_raw ' . $cidrWhere, $cidrParams))->toBe(1)
		->and(flowview_db_table_exists('plugin_flowview_test_raw'))->toBeTrue();
});
