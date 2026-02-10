/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.0
 * @link      https://www.silbersaiten.de
 */

var dhldpAdminConfigure = {
    init: function () {
        var self = this;

        $('select.select2').select2({});

        if ($('#dhl_mode_live').is(':checked')) {
            self.showAuthdataLive();
        }

        if ($('#dhl_mode_sbx').is(':checked')) {
            self.showAuthdataSbx();
        }

        $('#dhl_mode_live').click(function (e) {
            self.showAuthdataLive();
        });
        $('#dhl_mode_sbx').click(function (e) {
            self.showAuthdataSbx();
        });

        if ($('#DHLDP_DHL_PFPS_on').is(':checked')) {
            $('.dhl_pfps_search').show();

            if ($('#DHLDP_DHL_PFPS_MAP_on').is(':checked')) {
                $('.dhl_googlemapapikey').show();
            } else {
                $('.dhl_googlemapapikey').hide();
            }
        } else {
            $('.dhl_pfps_search').hide();
            $('.dhl_googlemapapikey').hide();
        }

        $('#DHLDP_DHL_PFPS_on').click(function (e) {
            $('.dhl_pfps_map').show();
        });
        $('#DHLDP_DHL_PFPS_off').click(function (e) {
            $('.dhl_pfps_map').hide();
            $('.dhl_googlemapapikey').hide();
        });

        $('#DHLDP_DHL_PFPS_MAP_on').click(function (e) {
            $('.dhl_googlemapapikey').show();
        });

        $('#DHLDP_DHL_PFPS_MAP_off').click(function (e) {
            $('.dhl_googlemapapikey').hide();
        });

        $('select[name="DHLDP_DHL_SHIPPER_TYPE"]').on('change', function () {
            if ($('select[name="DHLDP_DHL_SHIPPER_TYPE"]').val() != 0) {
                $('.dhl_shipper_by_address').hide();
                $('.dhl_shipper_by_reference').show();
            } else {
                $('.dhl_shipper_by_address').show();
                $('.dhl_shipper_by_reference').hide();
            }
        });

        if ($('select[name="DHLDP_DHL_SHIPPER_TYPE"]').val() != 0) {
            $('.dhl_shipper_by_address').hide();
            $('.dhl_shipper_by_reference').show();
        } else {
            $('.dhl_shipper_by_address').show();
            $('.dhl_shipper_by_reference').hide();
        }

        if ($('input[id="DHLDP_DHL_RETURNS_EXTEND_on"]:checked').length) {
            $('.dhldp_dhl_ra').removeClass('hide');
        } else {
            $('.dhldp_dhl_ra').addClass('hide');
        }

        $('input[name="DHLDP_DHL_RETURNS_EXTEND"]').on('click change', function (e) {
            if ($('#DHLDP_DHL_RETURNS_EXTEND_on').is(':checked')) {
                $('.dhldp_dhl_ra').removeClass('hide');
            } else {
                $('.dhldp_dhl_ra').addClass('hide');
            }
        });

        // init dhl products
        if (defined_dhl_products) {
            defined_dhl_products = JSON.parse(defined_dhl_products);
        }
        $.each(defined_dhl_products, function (key, value) {
            $('#dhl-product-name')
                .append($('<option>', {value: value.code})
                    .text(value.name));
        });
        $.each(defined_dhl_products, function (key, value) {
            if (value.code == $('#dhl-product-name').val()) {
            }
        });

        $('#dhl-product-name').change(function (e) {
            $.each(defined_dhl_products, function (key, value) {
                if (value.code == $('#dhl-product-name').val()) {
                }
            });
        });

        $('#dhl-product-participation').keyup(function (e) {
            value = $(this).val().replace(/[^A-Z0-9]/g, "");
            if (value.length > 2)
                return false;
            $(this).val(value);
        }).blur(function (e) {
            value = $(this).val();
            if (value.length == 0)
                $(this).val('01');
            if (value.length == 1)
                $(this).val('0' + value);
        });

        $('.dhl-products').on('click', '#removeDhlProduct', function (e) {
            e.preventDefault();
            parent = $(this).parent().parent();
            parent.fadeOut('slow', function () {
                $(this).remove();
            });
            self.removeCarrierProducts(parent.find('.added_dhl_products').val());
        });

        $('.dhl-list-carriers').on('click', '.dhlc', function (e) {
            if (!$(this).is(':checked')) {
                $(".dhl-list-carriers #dhlcp_" + $(this).attr('id').split('_')[1] + " option[value='']").attr('selected', 'selected');
            }
        })

        $('#addDhlProduct').click(function (e) {
            e.preventDefault();
            p_n = '';
            error = false;
            $.each(defined_dhl_products, function (key, value) {
                if (value.code == $('#dhl-product-name').val()) {
                    p_n = value.name;
                }
            });
            $('#dhl-product-participation').trigger('blur');

            $('.dhl-products .added_dhl_products').each(function () {
                s = $(this).val().split(':')

                if (s[0] == $('#dhl-product-name').val() && s[1] == $('#dhl-product-participation').val()) {
                    alert(dhl_translation.ExistsParticipation);
                    error = true;
                }

                if ($(this).val() == $('#dhl-product-name').val() +
                    ':' + $('#dhl-product-participation').val()) {
                    alert(dhl_translation.Exists);
                    error = true;
                }
            });

            if (error == false) {
                var item_value = $('#dhl-product-name').val() + ':' + $('#dhl-product-participation').val();
                var item_name = p_n + ' ' + $('#dhl-product-participation').val();
                elem = $('<tr>' +
                    '<td><input type="hidden" class="added_dhl_products" name="added_dhl_products[]" value="' + item_value + '">' + p_n + '</td>' +
                    '<td>' + $('#dhl-product-participation').val() + '</td>' +
                    '<td><input type="button" name="removeDhlProduct" id="removeDhlProduct" class="button btn btn-default" value="' + dhl_translation.Remove + '"/></td>' +
                    '</tr>');
                elem.hide();
                $('.dhl-products table').append(elem);
                elem.fadeIn().css("display", "");
                console.log('add');
                self.addCarrierProducts(item_value, item_name);
            }
        });
        self.initFirstStep();
        self.initSettingsTabs();
    },
    addCarrierProducts: function (item_value, item_name) {
        $('.dhl-list-carriers .dhlcp').each(function (index) {
            var found = false;
            $(this).find('option').each(function (key, value) {
                if (typeof value.value != 'undefined' && value.value == item_value) {
                    found = true;
                    return false;
                }
            });
            if (found === false) {
                $(this).append($('<option>', {value: item_value}).text(item_name));
            }
        });
    },
    removeCarrierProducts: function (value) {
        $(".dhl-list-carriers .dhlcp option[value='" + value + "']").remove();
    },
    initFirstStep: function () {
        $('#DHL_COUNTRY').change(function (e) {
            var sel = $('#DHL_API_VERSION').val();
            $('#DHL_API_VERSION option').remove();
            $.each(defined_dhl_api_versions[$('#DHL_COUNTRY').val()]['api_versions'], function (key, value) {
                $('#DHL_API_VERSION')
                    .append($('<option>', {value: value})
                        .text(value));
            });
            $('#DHL_API_VERSION').val(sel).change();
        });
    },
    showAuthdataLive: function () {
        $('#resetLiveAccount').show();
        if ($('.dhl_authdata_live').length > 0) {
            $('.dhl_authdata_live').show();
            $('.dhl_authdata_livet').show();
            $('.dhl_authdata_sbx').hide();
        } else {
            $('input[name=DHL_LIVE_USER]').parent().show();
            $('input[name=DHL_LIVE_SIGN]').parent().show();
            $('input[name=DHL_LIVE_EKP]').parent().show();
            $('input[name=DHL_LIVE_USER]').parent().prev('label').show();
            $('input[name=DHL_LIVE_SIGN]').parent().prev('label').show();
            $('input[name=DHL_LIVE_EKP]').parent().prev('label').show();
        }
    },
    showAuthdataSbx: function () {
        $('#resetLiveAccount').hide();
        if ($('.dhl_authdata_live').length > 0) {
            $('.dhl_authdata_sbx').slideDown('slow');
            $('.dhl_authdata_live').hide();
            $('.dhl_authdata_livet').hide();
        } else {
            $('input[name=DHL_LIVE_USER]').parent().hide();
            $('input[name=DHL_LIVE_SIGN]').parent().hide();
            $('input[name=DHL_LIVE_EKP]').parent().hide();
            $('input[name=DHL_LIVE_USER]').parent().prev('label').hide();
            $('input[name=DHL_LIVE_SIGN]').parent().prev('label').hide();
            $('input[name=DHL_LIVE_EKP]').parent().prev('label').hide();
        }
    },
    initSettingsTabs: function () {
        var tabSelectors = [
            'button[name="submitSaveAuthOptions"]',
            'button[name="submitSaveProductsOptions"]',
            'button[name="submitSaveMiscOptions"]',
            'button[name="submitSaveAdditionalServicesOptions"]',
            'button[name="submitSaveRetoureOptions"]',
            'button[name="submitSaveAddressOptions"]',
            'button[name="submitSaveBankOptions"]'
        ];

        var panels = [];
        $.each(tabSelectors, function (index, selector) {
            var $form = $(selector).closest('form');
            if (!$form.length) {
                return;
            }

            var $panel = $form.closest('.panel');
            if (!$panel.length) {
                return;
            }

            var isAdded = false;
            $.each(panels, function (i, panel) {
                if (panel[0] === $panel[0]) {
                    isAdded = true;
                    return false;
                }
            });

            if (!isAdded) {
                panels.push($panel);
            }
        });

        if (!panels.length) {
            return;
        }

        var $firstPanel = panels[0];
        var $tabsContainer = $('<div id="dhldp-settings-tabs" class="dhldp-settings-tabs"></div>');
        var $tabsNavigation = $('<ul></ul>');

        $.each(panels, function (index, $panel) {
            var panelId = 'dhldp-settings-tab-' + index;
            var title = $.trim($panel.find('.panel-heading').first().text()) || ('Tab ' + (index + 1));

            $panel.attr('id', panelId).addClass('dhldp-settings-tab-panel');
            $tabsNavigation.append('<li><a href="#' + panelId + '">' + title + '</a></li>');
        });

        $tabsContainer.append($tabsNavigation);
        $firstPanel.before($tabsContainer);

        $.each(panels, function (index, $panel) {
            $tabsContainer.append($panel);
        });

        var activeIndex = 0;
        var $currentForm = $('button[name], input[name][type="submit"]').filter(function () {
            return $(this).attr('name') && $(this).attr('name').indexOf('submitSave') === 0;
        }).closest('form').filter(function () {
            return $(this).find(':focus').length > 0;
        }).first();

        if ($currentForm.length) {
            var currentPanel = $currentForm.closest('.panel');
            $.each(panels, function (index, $panel) {
                if ($panel[0] === currentPanel[0]) {
                    activeIndex = index;
                    return false;
                }
            });
        }

        $tabsContainer.tabs({
            active: activeIndex
        });
    },
}

$(function () {
    dhldpAdminConfigure.init();
});
