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

var dhldp_private = {


    initAction: function() {
        var self = this;

        if ($('.delivery_option_radio:checked').length) {
            var el = '.delivery_option_radio:checked';
        } else {
            var el = 'input[name^="delivery_option"]:checked';
        }
        if ($(el).length > 0) {
            var aval = $(el).val().split(',');
            if (aval.length > 0 && $.isArray(dhldp_carriers)) {
                if ($.inArray(parseInt(aval[0]), dhldp_carriers) != -1) {
                    $(document).scrollTop($(".dhldp_permission_private").offset().top);
                    $('.dhldp_permission_private').slideDown('fast');
                } else {
                    $('.dhldp_permission_private').slideUp('fast');
                }
            } else {
                $('.dhldp_permission_private').slideUp('fast');
            }
        }
    },
    init: function() {
        var self = this;

        if ($('.delivery-options').length)
            $('.dhldp_permission_private').insertAfter('.delivery-options');

        if ($('.delivery_options_address').length)
            $('.dhldp_permission_private').insertAfter('.delivery_options_address');

        self.initAction();

        if ($(".delivery_options").length) {
            var el_parent = document;
        } else {
            var el_parent = ".delivery-options";
        }

        if ($(".delivery_option_radio").length) {
            var el = ".delivery_option_radio";
        } else {
            var el = 'input[name^="delivery_option"]';
        }

        $(el_parent).on('click', el, function(e){
            if ($(this).prop("checked")) {
                var aval = $(this).val().split(',');
                if (aval.length > 0 && $.isArray(dhldp_carriers)) {
                    if ($.inArray(parseInt(aval[0]), dhldp_carriers) != -1) {
                        $(document).scrollTop($(".dhldp_permission_private").offset().top);
                        $('.dhldp_permission_private').slideDown('fast');
                    } else {
                        $('.dhldp_permission_private').slideUp('fast');
                    }
                } else {
                    $('.dhldp_permission_private').slideUp('fast');
                }
            }
        });

        self.getPermission();

        $('.dhldp_permission_private').on('click', 'input[name="dhldp_permission_private"]', function(e) {
            if ($(this).is(':checked')) {
                self.assignPermission(1);
            } else {
                self.assignPermission(0);
            }
        });
    },
    assignPermission: function(value) {
        $.ajax({
            type: "POST",
            url: dhldp_ajax,
            headers: { "cache-control": "no-cache" },
            cache: false,
            async: true,
            data: "action=setprivate&permission=" + value,
            success: function(data){
            }
        });
    },
    getPermission: function() {
        $.ajax({
            dataType: 'json',
            type: "POST",
            url: dhldp_ajax,
            headers: { "cache-control": "no-cache" },
            cache: false,
            async: true,
            data: "action=getprivate",
            success: function(data) {
                if (typeof data.permission != undefined) {
                    if (data.permission == 1) {
                        $('input[name="dhldp_permission_private"]').prop('checked', true);
                    } else {
                        $('input[name="dhldp_permission_private"]').prop('checked', false);
                    }
                } else {
                    $('input[name="dhldp_permission_private"]').prop('checked', false);
                }
            }
        });
    },
}

$(document).ready(function(){
    if (typeof dhldp_ajax != 'undefined' && typeof dhldp_carriers != 'undefined') {
        dhldp_private.init();
    }
});
