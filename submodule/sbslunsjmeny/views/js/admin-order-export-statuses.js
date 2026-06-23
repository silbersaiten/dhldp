var sbslunsjmenyOrderExportStatuses = {
    labels: {
        exported: 'Fully exported',
        partial: 'More products to export'
    },

    init: function () {
        if (typeof sbslunsjmenyOrderExportStatusesLabels === 'object') {
            this.labels = $.extend({}, this.labels, sbslunsjmenyOrderExportStatusesLabels);
        }

        this.loadCurrentPageStatuses();
    },

    loadCurrentPageStatuses: function () {
        var orderList = this.getCurrentPageOrderIds();
        if (!orderList.length || typeof sbslunsjmenyOrderExportStatusesAjaxUrl !== 'string') {
            return;
        }

        $.post(sbslunsjmenyOrderExportStatusesAjaxUrl, {
            orderList: orderList,
            action: 'getOrderExportStatuses',
            ajax: 1
        }, function (data) {
            if (data && data.hasOwnProperty('orderStatuses')) {
                sbslunsjmenyOrderExportStatuses.createList(data.orderStatuses);
            }
        }, 'json');
    },

    getCurrentPageOrderIds: function () {
        var orderList = [];

        /** For PS until 1.7.6.9 */
        $('body.adminorders table input[name="orderBox[]"]').each(function () {
            sbslunsjmenyOrderExportStatuses.pushUniqueOrderId(orderList, $(this).val());
        });

        /** For PS since 1.7.7.0 */
        $('body.adminorders table input[name="order_orders_bulk[]"]').each(function () {
            sbslunsjmenyOrderExportStatuses.pushUniqueOrderId(orderList, $(this).val());
        });

        return orderList;
    },

    pushUniqueOrderId: function (orderList, value) {
        var idOrder = parseInt(value, 10);
        if (!Number.isInteger(idOrder) || idOrder <= 0 || orderList.indexOf(idOrder) !== -1) {
            return;
        }

        orderList.push(idOrder);
    },

    createList: function (orderStatuses) {
        var parent = $('body.adminorders table.table.order');

        if (!parent.length) {
            parent = $('body.adminorders table#order_grid_table');
        }

        if (!parent.length) {
            return;
        }

        var statusMap = this.buildStatusMap(orderStatuses);
        parent.find('tbody tr').each(function () {
            var row = $(this);
            var idOrder = sbslunsjmenyOrderExportStatuses.getRowOrderId(row);
            if (!idOrder || !statusMap.hasOwnProperty(idOrder)) {
                return;
            }

            sbslunsjmenyOrderExportStatuses.addBadge(row, statusMap[idOrder]);
        });
    },

    buildStatusMap: function (orderStatuses) {
        var statusMap = {};

        $.each(orderStatuses, function () {
            var idOrder = parseInt(this.id_order, 10);
            var status = typeof this.status === 'string' ? this.status : '';
            if (idOrder > 0 && (status === 'exported' || status === 'partial')) {
                statusMap[idOrder] = status;
            }
        });

        return statusMap;
    },

    getRowOrderId: function (row) {
        var checkbox = row.find('td:first input[type=checkbox]');
        if (checkbox.length > 0) {
            return parseInt(checkbox.attr('value'), 10) || 0;
        }

        return parseInt(row.find('td:first').html(), 10) || 0;
    },

    addBadge: function (row, status) {
        if (row.find('.sbslunsjmeny-order-export-status-badge').length) {
            return;
        }

        var targetCell = row.find('td:nth-child(3)');
        if (!targetCell.length) {
            targetCell = row.find('td:first');
        }

        $('<span/>', {
            'class': 'sbslunsjmeny-order-export-status-badge sbslunsjmeny-order-export-status-badge-' + status
        }).text(this.labels[status]).appendTo(targetCell);
    }
};

$(document).ready(function () {
    sbslunsjmenyOrderExportStatuses.init();
});
