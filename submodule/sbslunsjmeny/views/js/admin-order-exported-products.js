(function () {
    function normalizeShippingDate(value) {
        if (typeof value !== 'string') {
            return '';
        }

        var match = value.match(/^(\d{4}-\d{2}-\d{2})/);
        return match ? match[1] : '';
    }

    function normalizeExportedProduct(product) {
        if (!product || typeof product !== 'object') {
            return null;
        }

        var shippingDate = normalizeShippingDate(String(product.shipping_date || ''));
        if (!shippingDate) {
            return null;
        }

        return {
            idCartDistributionShipping: parseInt(product.id_cart_distribution_shipping, 10) || 0,
            idOrderDetail: parseInt(product.id_order_detail, 10) || 0,
            idProduct: parseInt(product.id_product, 10) || 0,
            shippingDate: shippingDate
        };
    }

    function getExportedProducts() {
        if (!Array.isArray(window.sbslunsjmenyExportedOrderProducts)) {
            return [];
        }

        return window.sbslunsjmenyExportedOrderProducts
            .map(normalizeExportedProduct)
            .filter(function (product) {
                return product !== null;
            });
    }

    function parseDistributionRel(value) {
        if (typeof value !== 'string') {
            return null;
        }

        var match = value.match(/^(\d{4}-\d{2}-\d{2})-(\d+)-/);
        if (!match) {
            return null;
        }

        return {
            shippingDate: match[1],
            idCandidate: parseInt(match[2], 10) || 0
        };
    }

    function buildExportedIndexes(exportedProducts) {
        var indexes = {
            byShippingLineId: {},
            byOrderDetail: {},
            byOrderDetailAndDate: {},
            byProduct: {},
            byProductAndDate: {}
        };

        exportedProducts.forEach(function (product) {
            if (product.idCartDistributionShipping > 0) {
                indexes.byShippingLineId[product.idCartDistributionShipping] = true;
            }

            if (product.idOrderDetail > 0) {
                indexes.byOrderDetail[product.idOrderDetail] = true;
                indexes.byOrderDetailAndDate[product.idOrderDetail + '|' + product.shippingDate] = true;
            }

            if (product.idProduct > 0) {
                indexes.byProduct[product.idProduct] = true;
                indexes.byProductAndDate[product.idProduct + '|' + product.shippingDate] = true;
            }
        });

        return indexes;
    }

    function getShippingLineId(dayWrapper) {
        var candidates = [
            dayWrapper.getAttribute('data-id-cart-distribution-shipping'),
            dayWrapper.getAttribute('data-id_cart_distribution_shipping'),
            dayWrapper.getAttribute('data-distribution-shipping-id')
        ];

        for (var i = 0; i < candidates.length; i += 1) {
            var value = parseInt(candidates[i], 10);
            if (Number.isInteger(value) && value > 0) {
                return value;
            }
        }

        return getPositiveInputValue(dayWrapper, [
            'id_cart_distribution_shipping',
            'cart_distribution_shipping_id',
            'idCartDistributionShipping',
            'distribution_shipping_id'
        ]);
    }

    function shouldMarkDay(dayWrapper, indexes) {
        var shippingLineId = getShippingLineId(dayWrapper);
        if (shippingLineId > 0 && indexes.byShippingLineId[shippingLineId]) {
            return true;
        }

        var inputWrapper = dayWrapper.querySelector('.day-input-wrapper[rel]');
        var relData = inputWrapper ? parseDistributionRel(inputWrapper.getAttribute('rel')) : null;
        if (!relData) {
            return false;
        }

        if (relData.idCandidate <= 0) {
            return false;
        }

        return !!(
            indexes.byOrderDetailAndDate[relData.idCandidate + '|' + relData.shippingDate]
            || indexes.byProductAndDate[relData.idCandidate + '|' + relData.shippingDate]
        );
    }

    function getPositiveInt(value) {
        var parsedValue = parseInt(value, 10);
        return Number.isInteger(parsedValue) && parsedValue > 0 ? parsedValue : 0;
    }

    function getFirstPositiveAttributeValue(element, attributeNames) {
        for (var i = 0; i < attributeNames.length; i += 1) {
            var value = getPositiveInt(element.getAttribute(attributeNames[i]));
            if (value > 0) {
                return value;
            }
        }

        return 0;
    }

    function getPositiveInputValue(row, inputNames) {
        for (var i = 0; i < inputNames.length; i += 1) {
            var input = row.querySelector('input[name="' + inputNames[i] + '"], input[name$="[' + inputNames[i] + ']"], input[name*="[' + inputNames[i] + ']"]');
            var value = input ? getPositiveInt(input.value) : 0;
            if (value > 0) {
                return value;
            }
        }

        return 0;
    }

    function getOrderDetailId(row) {
        var attributeValue = getFirstPositiveAttributeValue(row, [
            'data-id-order-detail',
            'data-id_order_detail',
            'data-order-detail-id',
            'data-id-orderdetail',
            'data-id'
        ]);
        if (attributeValue > 0) {
            return attributeValue;
        }

        return getPositiveInputValue(row, [
            'id_order_detail',
            'order_detail_id',
            'idOrderDetail',
            'product_id_order_detail'
        ]);
    }

    function getProductId(row) {
        var attributeValue = getFirstPositiveAttributeValue(row, [
            'data-id-product',
            'data-id_product',
            'data-product-id'
        ]);
        if (attributeValue > 0) {
            return attributeValue;
        }

        return getPositiveInputValue(row, [
            'id_product',
            'product_id',
            'idProduct'
        ]);
    }

    function getOrderProductRows() {
        var selectors = [
            '#orderProducts tbody tr',
            '#order-products tbody tr',
            '#orderProductsPanel tbody tr',
            '#order-products-panel tbody tr',
            'table.order-products tbody tr',
            'table[data-role="order-products"] tbody tr',
            'tr.product-line-row',
            'tr.product-line'
        ];
        var rows = [];
        var seen = [];

        selectors.forEach(function (selector) {
            Array.prototype.forEach.call(document.querySelectorAll(selector), function (row) {
                if (row.tagName !== 'TR' || seen.indexOf(row) !== -1) {
                    return;
                }

                if (getOrderDetailId(row) > 0 || getProductId(row) > 0) {
                    seen.push(row);
                    rows.push(row);
                }
            });
        });

        return rows;
    }

    function addExportedBadge(row) {
        if (row.querySelector('.sbslunsjmeny-exported-product-badge')) {
            return;
        }

        var firstCell = row.querySelector('td');
        if (!firstCell) {
            return;
        }

        var badge = document.createElement('span');
        badge.className = 'sbslunsjmeny-exported-product-badge';
        badge.textContent = 'Exported';
        firstCell.insertBefore(badge, firstCell.firstChild);
    }

    function shouldMarkProductRow(row, indexes) {
        var idOrderDetail = getOrderDetailId(row);
        if (idOrderDetail > 0 && indexes.byOrderDetail[idOrderDetail]) {
            return true;
        }

        var idProduct = getProductId(row);
        return idProduct > 0 && indexes.byProduct[idProduct];
    }

    function markExportedProducts(indexes) {
        getOrderProductRows().forEach(function (row) {
            if (shouldMarkProductRow(row, indexes)) {
                row.classList.add('sbslunsjmeny-exported-product');
                addExportedBadge(row);
            }
        });
    }

    function markExportedDays(indexes) {
        var dayWrappers = document.querySelectorAll('ul.days-list > li.day-wrapper');
        Array.prototype.forEach.call(dayWrappers, function (dayWrapper) {
            if (shouldMarkDay(dayWrapper, indexes)) {
                dayWrapper.classList.add('sbslunsjmeny-exported-product');
            }
        });
    }

    function markExportedOrderItems() {
        var exportedProducts = getExportedProducts();
        if (!exportedProducts.length) {
            return;
        }

        var indexes = buildExportedIndexes(exportedProducts);
        markExportedDays(indexes);
        markExportedProducts(indexes);
    }

    function observeOrderProductChanges() {
        if (!window.MutationObserver) {
            return;
        }

        var observer = new MutationObserver(function () {
            markExportedOrderItems();
        });
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            markExportedOrderItems();
            observeOrderProductChanges();
        });
    } else {
        markExportedOrderItems();
        observeOrderProductChanges();
    }
})();
