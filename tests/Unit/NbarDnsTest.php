<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

// Resolver double: these tests never send DNS packets.
if (!class_exists('Net_DNS2_Resolver')) {
	class Net_DNS2_Resolver
	{
		public $timeout;
		public static array $results = [];
		public static array $queries = [];
		public function __construct($options) {
		}
		public function query($ip, $type) {
			self::$queries[] = [$ip, $type];
			$result = array_shift(self::$results);
			if ($result instanceof Throwable) {
				throw $result;
			}
			return (object) ['answer' => $result];
		}
	}
}

it('uses persistent DNS results and bounded positive and negative resolver caches', function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/setup.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_common.php');
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_dns.php');
	$GLOBALS['fv_nbar_preferences'] = ['dns' => '1'];
	plugin_test_queue_db_result('db_fetch_row_prepared', ['host' => 'cached.example']);
	expect(fv_nbar_dns('192.0.2.201'))->toBe('cached.example');
	plugin_test_queue_db_result('db_fetch_row_prepared', ['host' => '192.0.2.202']);
	expect(fv_nbar_dns('192.0.2.202'))->toBe('No reverse DNS');
	$_SESSION['fv_nbar_dns_negative']['192.0.2.203'] = ['until' => time() + 30, 'name' => 'Not resolved'];
	expect(fv_nbar_dns('192.0.2.203'))->toBe('Not resolved');
	Net_DNS2_Resolver::$results = [
		[(object) ['ptrdname' => 'resolved.example.']],
		[],
		new Exception('NXDOMAIN', 3),
		new Exception('Timeout', 1),
	];
	expect(fv_nbar_dns('192.0.2.204'))->toBe('resolved.example');
	expect(fv_nbar_dns('192.0.2.204'))->toBe('resolved.example');
	expect(fv_nbar_dns('192.0.2.205'))->toBe('No reverse DNS');
	expect(fv_nbar_dns('192.0.2.206'))->toBe('No reverse DNS');
	expect(fv_nbar_dns('192.0.2.207'))->toBe('Not resolved');
	expect(Net_DNS2_Resolver::$queries)->toHaveCount(4);
	expect($_SESSION['fv_nbar_dns_negative']['192.0.2.206']['until'])->toBeGreaterThan(time() + 290);
	expect($_SESSION['fv_nbar_dns_negative']['192.0.2.207']['until'])->toBeLessThanOrEqual(time() + 30);
	$calls = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'db_execute_prepared');
	expect(array_values($calls)[0]['params'][1])->toBe('resolved.example');
});

it('expires negative DNS entries and retains at most 512 addresses', function () {
	plugin_test_reset();
	flowview_test_load(dirname(__DIR__, 2) . '/includes/nbar_dns.php');
	$_SESSION['fv_nbar_dns_negative']['old'] = ['until' => time() - 1, 'name' => 'Old'];
	foreach (range(1, 512) as $i) {
		$_SESSION['fv_nbar_dns_negative']['host' . $i] = ['until' => time() + 100, 'name' => 'Not resolved'];
	}
	expect(fv_nbar_dns_negative('192.0.2.208', 'Not resolved', 30))->toBe('Not resolved');
	expect($_SESSION['fv_nbar_dns_negative'])->toHaveCount(512);
	expect($_SESSION['fv_nbar_dns_negative'])->not->toHaveKey('old')->not->toHaveKey('host1');
	expect($_SESSION['fv_nbar_dns_negative'])->toHaveKey('192.0.2.208');
});

require_once(dirname(__DIR__) . '/helpers/request.php');
it('stops further DNS lookups after the report lookup budget is exhausted', function () {
	$directory = sys_get_temp_dir() . '/flowview-dns-' . bin2hex(random_bytes(8));
	mkdir($directory, 0700);
	try {
		[$stdout] = flowview_test_request(dirname(__DIR__) . '/fixtures/nbar-dns-budget.php', 'budget', $directory);
		expect(json_decode($stdout, true, 512, JSON_THROW_ON_ERROR))->toBe(['name' => 'Not resolved', 'queries' => 1]);
	} finally {
		foreach (glob($directory . '/*') as $file) {
			unlink($file);
		}
		rmdir($directory);
	}
});
