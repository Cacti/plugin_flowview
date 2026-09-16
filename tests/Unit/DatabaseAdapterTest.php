<?php

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn']     = 'flowview-connection';
	$GLOBALS['config']['poller_id'] = 1;
	$GLOBALS['local_db_cnn_id']  = null;
	$GLOBALS['remote_db_cnn_id'] = null;
});

it('forwards prepared parameters and the FlowView connection', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', array('service' => 'https'));
	$row = flowview_db_fetch_row_prepared('SELECT service FROM ports WHERE port = ?', array(443));

	expect($row)->toBe(array('service' => 'https'))
		->and($GLOBALS['__test_db_calls'])->toHaveCount(1)
		->and($GLOBALS['__test_db_calls'][0]['params'])->toBe(array(443))
		->and($GLOBALS['__test_db_calls'][0]['conn'])->toBe('flowview-connection');
});

it('honors an explicit connection override', function () {
	plugin_test_queue_db_result('db_fetch_cell_prepared', 7);
	expect(flowview_db_fetch_cell_prepared('SELECT ?', array(7), '', true, 'replica'))->toBe(7)
		->and($GLOBALS['__test_db_calls'][0]['conn'])->toBe('replica');
});

it('accepts simple optionally-qualified table identifiers', function () {
	plugin_test_queue_db_result('db_fetch_cell', 1);
	expect(flowview_db_table_exists('flowdb.plugin_flowview_raw_2024000'))->toBeTrue()
		->and($GLOBALS['__test_db_calls'][0]['sql'])->toBe("SHOW TABLES LIKE 'plugin\\_flowview\\_raw\\_2024000'");

});

it('rejects an unsafe or incomplete table identifier', function ($table) {
	expect(flowview_db_table_exists($table))->toBeFalse()
		->and($GLOBALS['__test_db_calls'])->toBe(array());
})->with(array(
	'raw; DROP TABLE users',
	'raw table',
	'raw`table',
	'raw%table',
	'',
));

it('generates table DDL with primary, regular, and unique indexes', function () {
	plugin_test_queue_db_result('db_fetch_assoc', array());
	$data = array(
		'columns' => array(
			array('name' => 'id', 'type' => 'int(11)', 'unsigned' => true, 'NULL' => false, 'auto_increment' => true),
			array('name' => 'name', 'type' => 'varchar(64)', 'NULL' => false, 'default' => ''),
		),
		'primary' => 'id',
		'keys' => array(array('name' => 'name_idx', 'columns' => array('name'))),
		'unique_keys' => array(array('name' => 'name_unique', 'columns' => array('name'))),
		'type' => 'InnoDB',
		'charset' => 'utf8mb4',
	);

	flowview_db_table_create('plugin_flowview_test_ddl', $data);
	$calls = array_values(array_filter($GLOBALS['__test_db_calls'], function ($call) {
		return $call['fn'] === 'db_execute';
	}));

	expect($calls)->toHaveCount(1)
		->and($calls[0]['sql'])->toContain('CREATE TABLE IF NOT EXISTS `plugin_flowview_test_ddl`')
		->and($calls[0]['sql'])->toContain('PRIMARY KEY (`id`)')
		->and($calls[0]['sql'])->toContain('INDEX `name_idx` (`name`)')
		->and($calls[0]['sql'])->toContain('UNIQUE INDEX `name_unique` (`name`)')
		->and($calls[0]['sql'])->toContain('ENGINE = InnoDB DEFAULT CHARSET = utf8mb4');
});

it('forwards the FlowView connection and log flag to db_check_reconnect by reference', function () {
	$result = flowview_db_check_reconnect(false);

	expect($result)->toBeTrue()
		->and($GLOBALS['__test_db_calls'])->toHaveCount(1)
		->and($GLOBALS['__test_db_calls'][0]['fn'])->toBe('db_check_reconnect')
		->and($GLOBALS['__test_db_calls'][0]['conn'])->toBe('flowview-connection')
		->and($GLOBALS['__test_db_calls'][0]['log'])->toBeFalse();
});

it('retains a replacement connection returned by a successful reconnect', function () {
	plugin_test_queue_db_result('db_check_reconnect', 'flowview-connection-2');

	$result = flowview_db_check_reconnect();

	expect($result)->toBeTrue()
		->and($GLOBALS['flowview_cnn'])->toBe('flowview-connection-2');
});

it('propagates a reconnected shared Cacti connection back to the aliased local/remote handle', function ($poller_id, $handle) {
	$GLOBALS['config']['poller_id'] = $poller_id;
	$GLOBALS[$handle]               = 'flowview-connection';

	plugin_test_queue_db_result('db_check_reconnect', 'flowview-connection-2');

	flowview_db_check_reconnect();

	expect($GLOBALS['flowview_cnn'])->toBe('flowview-connection-2')
		->and($GLOBALS[$handle])->toBe('flowview-connection-2');
})->with(array(
	array(1, 'local_db_cnn_id'),
	array(2, 'remote_db_cnn_id'),
));

it('does not touch local/remote connection handles when FlowView uses a dedicated database', function () {
	$GLOBALS['local_db_cnn_id']  = 'cacti-connection';
	$GLOBALS['remote_db_cnn_id'] = null;

	plugin_test_queue_db_result('db_check_reconnect', 'flowview-connection-2');

	flowview_db_check_reconnect();

	expect($GLOBALS['flowview_cnn'])->toBe('flowview-connection-2')
		->and($GLOBALS['local_db_cnn_id'])->toBe('cacti-connection');
});
