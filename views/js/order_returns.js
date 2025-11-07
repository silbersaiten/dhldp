/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.0
 * @link      http://www.silbersaiten.de
 */

var dhldp_order_returns = {
    init: function() {
        var self = this;
        console.log(dhldp_order_returns_items);
        if (dhldp_order_returns_items.length) {
            if ($('table#order-list tbody tr').length) {
                var trs = $('table#order-list tbody tr');
                var ps = '16';
            } else if ($('#order-follow table tbody tr').length) {
                var trs = $('#order-follow table tbody tr');
                var ps = '17';
            }
            if (typeof trs != 'undefined') {
                trs.each(function (index) {
                        if (dhldp_order_returns_items[index].url != '') {
                            if (ps == '17') {
                                $(this).find('td:last').append('&nbsp;&nbsp;<a href="' +
                                    dhldp_order_returns_items[index].url + '" title="' + dhldp_translation.Get_DHL_Return_Label +
                                    '">' +
                                    dhldp_translation.Get_DHL_Return_Label + '</a>');
                            } else {
                                $(this).find('td:last').append('&nbsp;&nbsp;<a class="btn btn-default button button-small" href="' +
                                    dhldp_order_returns_items[index].url + '" title="' + dhldp_translation.Get_DHL_Return_Label +
                                    '"><span><i class="icon-external-link"></i> ' +
                                    dhldp_translation.Get_DHL_Return_Label + '</span></a>');
                            }
                        }
                    }
                );
            }
        }
    }
}
$(function(){
    dhldp_order_returns.init();
});