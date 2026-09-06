<?php

beforeEach(function () {
	plugin_test_reset();
	date_default_timezone_set('UTC');
});

it('binds every numeric filter value instead of interpolating it', function () {
	$params = array('existing');
	$where = get_numeric_filter('WHERE `listener_id` = ?', $params, array('6', ' 17 ', 'invalid'), 'protocol');

	expect($where)->toBe('WHERE `listener_id` = ? AND `protocol` IN (?, ?)')
		->and($params)->toBe(array('existing', '6', '17'))
		->and($where)->not->toContain('17');
});

it('does not modify an empty numeric filter and fails closed on invalid input', function () {
	$params = array();
	expect(get_numeric_filter('', $params, '', 'protocol'))->toBe('')
		->and(get_numeric_filter('', $params, 'invalid,also-invalid', 'protocol'))->toBe('WHERE 1 = 0')
		->and(get_numeric_filter('WHERE `listener_id` = ?', $params, 'invalid', 'protocol'))->toBe('WHERE `listener_id` = ? AND 1 = 0')
		->and($params)->toBe(array());
});

it('builds address predicates with bound IPv4 and IPv6 values', function () {
	$params = array();
	$where = get_ip_filter('', $params, '192.0.2.1,2001:db8::1', 'src_addr');

	expect($where)->toBe('WHERE ((`src_addr` = INET6_ATON(?)) OR (`src_addr` = INET6_ATON(?)))')
		->and($params)->toBe(array('192.0.2.1', '2001:db8::1'));
});

it('rejects invalid addresses with a fail-closed predicate', function () {
	$params = array();
	expect(get_ip_filter('', $params, 'not-an-address', 'src_addr'))->toBe('WHERE 1 = 0')
		->and(get_ip_filter('WHERE `listener_id` = ?', $params, 'also-invalid', 'src_addr'))->toBe('WHERE `listener_id` = ? AND 1 = 0')
		->and($params)->toBe(array())
		->and($GLOBALS['__test_messages'])->toHaveCount(2);
});

it('keeps valid addresses when another list entry is invalid', function () {
	$params = array();
	$where = get_ip_filter('', $params, '192.0.2.1,bogus', 'src_addr');

	expect($where)->toBe('WHERE ((`src_addr` = INET6_ATON(?)))')
		->and($params)->toBe(array('192.0.2.1'))
		->and($GLOBALS['__test_messages'])->toHaveCount(1);
});

it('fails closed when a CIDR prefix is invalid', function () {
	$params = array();

	expect(get_ip_filter('', $params, '192.0.2.0/99', 'src_addr'))->toBe('WHERE 1 = 0')
		->and($params)->toBe(array())
		->and($GLOBALS['__test_messages'])->toHaveCount(1);
});

it('binds CIDR masks and network addresses', function () {
	$params = array();
	$where = get_ip_filter('', $params, '192.0.2.0/24', 'dst_addr');

	expect($where)->toContain('OCTET_LENGTH(`dst_addr`) = OCTET_LENGTH(INET6_ATON(?))')
		->and($where)->toContain('`dst_addr` BETWEEN INET6_ATON(?) AND INET6_ATON(?)')
		->and($params)->toBe(array('192.0.2.0', '192.0.2.0', '192.0.2.255'));
});

it('computes the full IPv6 CIDR range', function () {
	$params = array();
	get_ip_filter('', $params, '2001:db8::/64', 'dst_addr');

	expect($params)->toBe(array('2001:db8::', '2001:db8::', '2001:db8::ffff:ffff:ffff:ffff'));
});

it('builds each supported temporal overlap contract', function ($rangeType, $expectedSql, $expectedCount) {
	$params = array();
	$sql = get_date_filter('', $params, 1704067200, 1704070800, $rangeType);

	expect($sql)->toBe($expectedSql)
		->and($params)->toHaveCount($expectedCount)
		->and($params[0])->toBe('2024-01-01 00:00:00')
		->and($params[1])->toBe('2024-01-01 01:00:00');
})->with(array(
	array(1, '(`start_time` BETWEEN ? AND ? OR `end_time` BETWEEN ? AND ?)', 4),
	array(2, '(`end_time` BETWEEN ? AND ?)', 2),
	array(3, '(`start_time` BETWEEN ? AND ?)', 2),
	array(4, '(`start_time` BETWEEN ? AND ? AND `end_time` BETWEEN ? AND ?)', 4),
));
