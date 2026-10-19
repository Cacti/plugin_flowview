/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

$(function () {
	var prefs = window.fvNbarDisplay;
	var combineDialog = document.getElementById('nbarCombineDialog'),
		combineSnapshot;
	$('#nbarCombineOptions').on('click', function () {
		combineSnapshot = {
			prefixes: $('#nbarPrefixes').val(),
			picks: $('#nbarCombineDialog input[type=checkbox]')
				.map(function () {
					return this.checked;
				})
				.get(),
		};
		if (!combineDialog.open) combineDialog.showModal();
	});
	$('#nbarCombineClose,#nbarCombineCancel').on('click', function () {
		combineDialog.close();
	});
	$(combineDialog).on('close', function () {
		if (!combineSnapshot) return;
		$('#nbarPrefixes').val(combineSnapshot.prefixes);
		$('#nbarCombineDialog input[type=checkbox]').each(function (i) {
			this.checked = combineSnapshot.picks[i];
		});
		updateRouterSelection();
	});

	var routerPicks = $('#nbarPreferences input[name="site_listeners[]"]');
	function updateRouterSelection() {
		var count = routerPicks.filter(':checked').length;
		routerPicks.not(':checked').prop('disabled', count >= 3);
		$('#nbarRouterSelectionCount').text(
			prefs.labels.selection.replace('{count}', count) +
				(count === 1 ? prefs.labels.single : count === 0 ? prefs.labels.off : ''),
		);
		$('#nbarCombineSave').prop('disabled', count === 1 || count > 3);
	}
	$('#nbarCombineSave').on('click', function () {
		var count = routerPicks.filter(':checked').length;
		if (count === 1 || count > 3) return false;
		if (count >= 2) $('#nbar_filter [name=listener]').val('0');
		$('<input>', { type: 'hidden', name: 'combine_apply', value: '1' }).appendTo('#nbar_filter');
	});
	routerPicks.on('change', updateRouterSelection);
	updateRouterSelection();
	$('#nbarTable,#nbarConversationTable').each(function () {
		var table = $(this);
		table.find('thead th').each(function (index) {
			var th = $(this),
				label = th.text(),
				data = th.attr('data-nbar-unit') === 'data',
				rate = th.attr('data-nbar-unit') === 'rate',
				dns = th.attr('data-nbar-unit') === 'dns';
			if (dns && prefs.dns === '0') {
				th.hide();
				table.find('tbody tr').each(function () {
					$(this).children().eq(index).hide();
				});
			}
			if (!data && !rate) return;
			var choice = data ? prefs.data : prefs.rate;
			th.text(
				label.replace(data ? 'GB' : 'Mbps', choice === 'Auto' ? (data ? 'MB / GB' : 'Kbps / Mbps') : choice),
			);
			th.attr('title', rate ? prefs.labels.rate : prefs.labels.data);
			table.find('tbody tr').each(function () {
				var cell = $(this).children().eq(index),
					value = Number(cell.attr('data-text'));
				if (!cell.is('[data-text]') || !Number.isFinite(value) || value < 0) return;
				var unit = choice,
					result = value;
				if (data) {
					if (choice === 'Auto') unit = value >= 1 ? 'GB' : 'MB';
					if (unit === 'MB') result = value * 1000;
				} else {
					if (choice === 'Auto') unit = value >= 1 ? 'Mbps' : 'Kbps';
					if (unit === 'Kbps') result = value * 1000;
				}
				var precision = choice === 'Auto' ? 2 : data ? 4 : 3,
					threshold = Math.pow(10, -precision);
				var text =
					result > 0 && result < threshold
						? '<' + threshold.toFixed(precision)
						: result.toLocaleString(undefined, {
								minimumFractionDigits: precision,
								maximumFractionDigits: precision,
							});
				cell.text(text + (choice === 'Auto' ? ' ' + unit : ''));
				cell.attr('title', data ? (value * 1e9).toLocaleString() + ' bytes' : value.toLocaleString() + ' Mbps');
			});
		});
		if (prefs.dns === '0') table.find('tbody td small').hide();
	});
});
