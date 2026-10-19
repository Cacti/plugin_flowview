/* Copyright (C) 2004-2026 The Cacti Group. GPL-2.0-or-later. */

window.flowviewNbarInitTable = function (spec) {
    $(function () {
        var table = $(spec.table);
        if (!table.length || !$.fn.tablesorter) return;

        var columns = table.find('th').length;
        var firstNumber = spec.conversations ? 3 : columns - 6;
        var headers = {};
        var savedSort = [[firstNumber, 1]];
        var storage;
        try {
            storage = spec.conversations ? localStorage : sessionStorage;
            var saved = JSON.parse(storage.getItem(spec.sortKey));
            if (Array.isArray(saved) && saved.length && saved.every(function (item) {
                return Array.isArray(item) && Number.isInteger(item[0]) &&
                    item[0] >= 0 && item[0] < columns && (item[1] === 0 || item[1] === 1);
            })) savedSort = saved;
        } catch (error) {}

        function saveSort() {
            try {
                if (storage && table[0].config) {
                    storage.setItem(spec.sortKey, JSON.stringify(table[0].config.sortList));
                }
            } catch (error) {}
        }

        for (var index = firstNumber; index < columns; index++) {
            headers[index] = {sorter: 'digit', sortInitialOrder: 'desc'};
        }

        var widths = spec.conversations ? [20, 20, 12, 8, 8, 8, 8, 8, 8] :
            (columns === 14 ? [9, 11, 10, 5, 11, 10, 5, 5, 7, 7, 5, 5, 5, 5] : [28, 14, 18, 10, 10, 10, 10]);
        table.tablesorter({
            headers: headers,
            sortList: savedSort,
            initialized: function (element) {
                // Cacti also initializes jQuery UI handles. Use the table widget's
                // handles consistently so saved widths and Safari dragging agree.
                var config = element.config;
                var widget = $.tablesorter.getWidgetById('resizable');
                if (!widget || !$.tablesorter.resizable) return;
                config.widgetOptions = $.extend({}, widget.options, config.widgetOptions);
                var options = config.widgetOptions;
                // Old saved widths can exceed the saved table width, leaving the
                // last column with no space. Repair them before the widget loads.
                if ($.tablesorter.storage) {
                    try {
                        var stored = $.tablesorter.storage(element, 'tablesorter-resizable');
                        if (Array.isArray(stored) && stored.length) {
                            var valid = stored.length === columns && stored.every(function (width) {
                                return typeof width === 'number' && Number.isFinite(width) && width >= 0;
                            });
                            var safe = valid ? stored.map(function (width, index) {
                                if (!config.$headerIndexed[index].is(':visible')) return 0;
                                return Math.max(index >= columns - 2 ? 136 : 48, width);
                            }) : [];
                            $.tablesorter.storage(element, 'tablesorter-resizable', safe);
                            $.tablesorter.storage(element, 'tablesorter-table-resized-width', safe.length ?
                                Math.max($(element).parent().width(), safe.reduce(function (sum, width) { return sum + width + 8; }, 0)) : 'auto');
                        }
                    } catch (error) {}
                }
                $(element).removeClass('hasResizable');
                $.tablesorter.resizable.init(config, options);
                config.widgets.push('resizable');
                config.widgetInit.resizable = true;
                options.$resizable_container.addClass('flowview-nbar-resize').children()
                    .attr('title', spec.resizeLabel).each(function (index) {
                        var header = $(config.$headerIndexed[index]);
                        $(this).css('color', header.find('.textSubHeaderDark').css('color') || header.css('color'));
                    });
                $(element).trigger('resizableUpdate');
            },
            widgets: ['zebra'],
            widgetOptions: {
                resizable: true,
                storage_tableId: spec.conversations ? 'flowview-nbar-conversation-columns-v2' : 'flowview-nbar-columns-v2-' + columns,
                resizable_widths: widths.map(function (width) { return width + '%'; })
            },
            widgetZebra: {css: ['even', 'odd']},
            headerTemplate: '<div class="textSubHeaderDark">{content} {icon}</div>',
            cssIconAsc: 'fa-sort-up',
            cssIconDesc: 'fa-sort-down',
            cssIconNone: 'fa-sort',
            cssIcon: 'fa'
        }).on('sortEnd', saveSort);

        $('.nbarPageLink').on('click', saveSort);
        $('.nbarPageJump').on('click', function () {
            var input = $(this).closest('.nbarPagination').find('.nbarPageNumber')[0];
            if (input && input.checkValidity()) {
                saveSort();
                location.href = this.dataset.url + '&page=' + Number.parseInt(input.value, 10);
            }
        });
        $('.nbarPageNumber').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $(this).closest('.nbarPagination').find('.nbarPageJump').trigger('click');
            }
        });
    });
};
