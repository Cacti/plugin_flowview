<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */
beforeEach(function () { plugin_test_reset(); });

it('distinguishes notification delivery and log persistence outcomes', function ($payload, $list, $mailError, $saved, $expected, $format = true, $tag = true) {
	plugin_test_queue_db_result('db_fetch_row_prepared', [
		'name' => 'Fixture', 'notification' => $payload, 'request_type' => 'scheduled',
		'requested_by' => 'fixture', 'requested_id' => 1
	]);
	plugin_test_queue_db_result('db_fetch_row_prepared', $list);
	plugin_test_queue_db_result('mailer', $mailError);
	plugin_test_queue_db_result('sql_save', $saved);
	plugin_test_queue_db_result('delivery_format_ok', $format);
	plugin_test_queue_db_result('delivery_format_tag', $tag);
	$data = [['bytes' => 42]];
	$raw = '<p>Fixture</p>';
	$html = '<html>Fixture</html>';
	$text = null;
	expect(flowview_reports_log_and_notify(51, microtime(true), 'html', 'flowview', 7, 'Fixture', $data, $raw, $html, $text))->toBe($expected);
	$mail = array_values(array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'mailer'));
	if ($expected === true) {
		expect($GLOBALS['__test_saved_rows'])->toHaveCount(1)
			->and($GLOBALS['__test_saved_rows'][0]['table'])->toBe('reports_log');
	} elseif ($mailError !== '' || $saved === false) {
		expect($mail)->toHaveCount(1);
	} else {
		expect($mail)->toBe([]);
	}
})->with([
	['', [], '', 1, true],
	['{invalid', [], '', 1, null],
	['null', [], '', 1, null],
	['{"email":false}', [], '', 1, null],
	['{"email":{}}', [], '', 1, null],
	['{"unknown":{}}', [], '', 1, null],
	['{"notification_list":{}}', [], '', 1, null],
	['{"notification_list":{"id":1}}', false, '', 1, null],
	['{"notification_list":{"id":1}}', [], '', 1, null],
	['{"notification_list":{"id":1}}', ['emails' => ''], '', 1, null],
	['{"email":{"to_email":"to@example.invalid"}}', [], '', 1, true],
	['{"email":{"to_email":"to@example.invalid","cc_email":"cc@example.invalid","bcc_email":"bcc@example.invalid","reply_to":"reply@example.invalid","from":{"email":"from@example.invalid","name":"Fixture"}}}', [], '', 1, true],
	['{"email":{"to_email":"to@example.invalid"}}', [], 'SMTP failed', 1, null],
	['{"email":{"to_email":"to@example.invalid"}}', [], '', false, null],
	['{"notification_list":{"id":1}}', ['emails' => 'to@example.invalid', 'format_file' => ''], '', 1, true],
	['{"notification_list":{"id":1,"reply_to":"fallback@example.invalid"}}', ['emails' => 'to@example.invalid', 'format_file' => 'default', 'cc_emails' => 'cc@example.invalid', 'bcc_emails' => 'bcc@example.invalid', 'reply_to' => 'reply@example.invalid'], '', 1, true, true, false],
	['{"notification_list":{"id":1}}', ['emails' => 'to@example.invalid', 'format_file' => 'default'], '', 1, true, false],
	['{"notification_list":{"id":1}}', ['emails' => 'to@example.invalid', 'format_file' => 'default'], 'SMTP failed', 1, null],
]);

it('keeps a partial multi-notification delivery unresolved without writing a success log', function () {
	plugin_test_queue_db_result('db_fetch_row_prepared', ['notification' => '{"email":{"to_email":"to@example.invalid"},"notification_list":{"id":1}}']);
	plugin_test_queue_db_result('db_fetch_row_prepared', ['emails' => 'list@example.invalid', 'format_file' => 'default']);
	plugin_test_queue_db_result('mailer', '');
	plugin_test_queue_db_result('mailer', 'SMTP failed after first notification');
	$data = []; $raw = ''; $html = ''; $text = '';
	expect(flowview_reports_log_and_notify(51, microtime(true), 'html', 'flowview', 7, 'Fixture', $data, $raw, $html, $text))->toBeNull();
	$mail = array_filter($GLOBALS['__test_db_calls'], fn ($c) => $c['fn'] === 'mailer');
	expect($mail)->toHaveCount(2)->and($GLOBALS['__test_saved_rows'])->toBe([]);
});


it('preserves the compatibility entrypoint outcome without hiding lookup failures', function ($report, $expected) {
	plugin_test_queue_db_result('db_fetch_row_prepared', $report);
	plugin_test_queue_db_result('sql_save', 1);
	$data = []; $raw = ''; $html = ''; $text = '';
	expect(reports_log_and_notify(51, microtime(true), 'html', 'flowview', 7, 'Fixture', $data, $raw, $html, $text))->toBe($expected);
})->with([
	[false, false], [[], false],
	[['notification' => '', 'name' => 'Fixture', 'request_type' => 'scheduled', 'requested_by' => 'fixture', 'requested_id' => 1], true]
]);
