<?php

beforeEach(function () {
	plugin_test_reset();
	$GLOBALS['flowview_cnn'] = 'flowview-connection';
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
