<?php
/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

/**
 * Observable delivery for FlowView on both supported Cacti versions.
 * False certifies a failed lookup before any notification or log write.
 * Null holds uncertain/partial delivery for repair; true permits completion.
 * Do not delegate to core's void helper or add a separate preflight lookup:
 * neither exposes the outcome of the lookup actually used for delivery.
 */
function flowview_reports_log_and_notify($id, $start_time, $report_type, $source, $source_id, $subject, &$raw_data, &$oput_raw, &$oput_html, &$oput_text, $attachments = [], $headers = []): ?bool {
	$report = db_fetch_row_prepared('SELECT * FROM reports_queued WHERE id = ?', [$id]);
	if (!$report) {
		cacti_log('FlowView notification queue lookup failed before delivery; retaining the occurrence for retry.', false, 'FLOWVIEW');
		return false;
	}
	$notifications = $report['notification'] === '' ? [] : json_decode($report['notification'], true);
	if (!is_array($notifications)) {
		return flowview_delivery_unresolved($id);
	}
	$from = [
		'email' => read_config_option('settings_from_email') ?: 'cacti@cacti.net',
		'name' => read_config_option('settings_from_name') ?: 'Cacti ' . ucfirst($source)
	];
	$oput_text ??= '';
	foreach ($notifications as $type => $data) {
		if (!is_array($data)) {
			return flowview_delivery_unresolved($id);
		}
		$html = $oput_html;
		if ($type === 'email' && (!empty($data['to_email']) || !empty($data['cc_email']) || !empty($data['bcc_email']))) {
			$to = $data['to_email'] ?? '';
			$cc = $data['cc_email'] ?? '';
			$bcc = $data['bcc_email'] ?? '';
			$reply = $data['reply_to'] ?? '';
		} elseif ($type === 'notification_list' && !empty($data['id'])) {
			$list = db_fetch_row_prepared('SELECT * FROM plugin_notification_lists WHERE id = ?', [$data['id']]);
			if (!$list || (empty($list['emails']) && empty($list['cc_emails']) && empty($list['bcc_emails']))) {
				return flowview_delivery_unresolved($id);
			}
			$output = '';
			$tag = false;
			$theme = 'modern';
			if (reports_load_format_file(($list['format_file'] ?? '') ?: 'default', $output, $tag, $theme)) {
				$html = $tag ? str_replace('<REPORT>', $oput_raw, $output) : $output . PHP_EOL . $oput_raw;
			} else {
				$html = $oput_raw;
			}
			$to = $list['emails'] ?? '';
			$cc = $list['cc_emails'] ?? '';
			$bcc = $list['bcc_emails'] ?? '';
			$reply = $list['reply_to'] ?? ($data['reply_to'] ?? '');
		} else {
			return flowview_delivery_unresolved($id);
		}
		// Cacti's mailer returns an empty error string on success. An error
		// can follow partial delivery, so it must never produce a safe retry.
		$error = mailer($data['from'] ?? $from, $to, $cc, $bcc, $reply, $subject, $html, $oput_text, $attachments, $headers);
		if ($error !== '') {
			return flowview_delivery_unresolved($id);
		}
	}
	$save = [
		'id' => 0, 'name' => $report['name'], 'source' => $source, 'source_id' => $source_id,
		'report_output_type' => $report_type, 'report_raw_data' => json_encode($raw_data),
		'report_raw_output' => $oput_raw, 'report_html_output' => $oput_html, 'report_txt_output' => $oput_text,
		'send_type' => $report['request_type'], 'send_time' => date('Y-m-d H:i:s'),
		'run_time' => microtime(true) - $start_time, 'sent_by' => $report['requested_by'],
		'sent_id' => $report['requested_id'], 'notification' => $report['notification']
	];
	if (!sql_save($save, 'reports_log')) {
		return flowview_delivery_unresolved($id);
	}
	return true;
}

function flowview_delivery_unresolved($id): ?bool {
	cacti_log('FlowView occurrence ' . $id . ' notification or report-log failure; delivery may be partial. Hold for repair and verify delivery before resending.', false, 'FLOWVIEW');
	return null;
}
