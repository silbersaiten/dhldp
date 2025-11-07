/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2022 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.1.0
 * @link      http://www.silbersaiten.de
 */

$(function() {
    var dhldp_admin_orders = {
        cur_dhl_product_params: {},

        init_products: function () {
            var  self = this;
            if (typeof dhl_products_params != 'undefined') {
                $.each(dhl_products_params, function(k, v) {
                    if ($('#ordercarrier-' + k + ' #dhl_product_code').length) {
                        self.updateProduct(k);
                        //on change
                        $(document).on('change', '#ordercarrier-' + k + ' #dhl_product_code', function () {
                            self.updateProduct(k);
                        });
                    }
                });
            }
        },
        init: function () {
            var self = this;

            $('.datepicker input[type="text"]').datepicker();
            
            $('[data-toggle="popover"]').popover();

            self.init_products();
            $('.ordercarrier-panel').each(function() {
                if ($(this).find('#submitDhlUpdateAddress').length) {
                    block = $(this).find('#submitDhlUpdateAddress').closest('table').find('#dhldp_dhl_update_address');
                    dhldp_admin_orders.updateButtonIcon($(block), $(this).find('#submitDhlUpdateAddress'));
                }
                if ($(this).find('#submitDhlAdditServices').length) {
                    block = $(this).find('#submitDhlAdditServices').closest('table').find('#dhldp_dhl_addit_services');
                    dhldp_admin_orders.updateButtonIcon($(block), $(this).find('#submitDhlAdditServices'));
                }
                if ($(this).find('#submitDhlExportDocuments').length) {
                    block = $(this).find('#submitDhlExportDocuments').closest('table').find('#dhldp_dhl_export_documents');
                    dhldp_admin_orders.updateButtonIcon($(block), $(this).find('#submitDhlExportDocuments'));
                }
            });

            $(document).on('click', '#submitDhlUpdateAddress', function(e) {
                block = $(this).closest('table').find('#dhldp_dhl_update_address');
                if(!$(block).is(':visible')) {
                    $(block).find('input#show_update_address').val(1);
                    $(block).show(400, () => {
                        offset = $('.page-head').length?$('.page-head').height():0;
                        offset += $('.navbar-header').length?$('.navbar-header').height():0;
                        $('html, body').animate({
                            scrollTop: $(block).offset().top - offset
                        }, 500);
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });

                } else {
                    $(block).find('input#show_update_address').val('');
                    $(block).hide(400, () => {
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });
                }
            });

            $(document).on('click', '#submitDhlAdditServices', function(e) {
                block = $(this).closest('table').find('#dhldp_dhl_addit_services');
                if(!$(block).is(':visible')) {
                    $(block).find('input#show_dhl_additional_services').val(1);
                    $(block).show(400, () => {
                        offset = $('.page-head').length?$('.page-head').height():0;
                        offset += $('.navbar-header').length?$('.navbar-header').height():0;
                        $('html, body').animate({
                            scrollTop: $(block).offset().top - offset
                        }, 500);
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });

                } else {
                    $(block).find('input#show_dhl_additional_services').val('');
                    $(block).hide(400, () => {
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });
                }
            });

            $(document).on('click', '#submitDhlExportDocuments', function(e) {
                block = $(this).closest('table').find('#dhldp_dhl_export_documents');
                if(!$(block).is(':visible')) {
                    $(block).find('input#show_dhl_export_documents').val(1);
                    $(block).show(400, () => {
                        offset = $('.page-head').length?$('.page-head').height():0;
                        offset += $('.navbar-header').length?$('.navbar-header').height():0;
                        $('html, body').animate({
                            scrollTop: $(block).offset().top - offset
                        }, 500);
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });

                } else {
                    $(block).find('input#show_dhl_export_documents').val('');
                    $(block).hide(400, () => {
                        dhldp_admin_orders.updateButtonIcon($(block), $(this));
                    });
                }
            });



            $(document).on('click', '.collapseDHLDPDhlAdditServices', function(e) {
                $(this).parents('.ordercarrier-panel').find('#submitDhlAdditServices').trigger('click');
            });

            $(document).on('click', '.collapseDHLDPDhlUpdateAddress', function(e) {
                $(this).parents('.ordercarrier-panel').find('#submitDhlUpdateAddress').trigger('click');
            });

            $(document).on('click', '.collapseDHLDPDhlExportDocuments', function(e) {
                $(this).parents('.ordercarrier-panel').find('#submitDhlExportDocuments').trigger('click');
            });


            $(document).on('click', '#toggleAllDHLLabelsForOrder', function(e){
                e.preventDefault();
                $(this).parent().parent().parent().find('.hiddenLabel').toggle();
                dhldp_admin_orders.updateButtonIcon($(this).parent().parent().parent().find('.hiddenLabel'), $(this));
            });

            $("#dhldp_dhl_update_address input[type=radio][name^=address][name*=address_type]:checked").each(function(index) {
                self.showAddressTypeInputs(this)
            });

            $(document).on('click', "#dhldp_dhl_update_address input[type=radio][name^=address][name*=address_type]", function(e) {
                if ($(this).is(':checked')) {
                    self.showAddressTypeInputs(this)
                }
            });

            $(document).on('click', "#dhldp_dhl_update_address input[type=radio][name^=address][name*=receiver_type]", function(e) {
                if ($(this).is(':checked')) {
                    self.showReceiverTypeInputs(this)
                }
            });

            $(document).on('click', '.dhldp-get-tracking-data', function(e){
                e.preventDefault();
                var last_tracking_data_el = $(this).parent().find('.dhldp-last-tracking-data');
                self.getTrackingData($(this).data('tracking-number'), function(data) {
                    last_tracking_data_el.html(data);
                });
            });



            $('.position_amount').on('input',function(e) {
                var amount = parseInt($(this).val()) || 0;
                $(this).val(amount);

                self.recalculateParcelWeight(this);
            });

            $('.position_unit_weight').on('blur', function(e) {
               if ($(this).val() == '') {
                   $(this).val('0');
               }
                self.recalculateParcelWeight(this);
            });

            $('.position_unit_weight').on('input', function(e) {
                var unit_weight_arr = $(this).val().replace(',', '.').split('.', 2);
                for (var i = 0; i < unit_weight_arr.length; i++) {
                    if (i == 0) {
                        unit_weight_arr[i] = unit_weight_arr[i].replace(/\D/g, "");
                    } else {
                        unit_weight_arr[i] = unit_weight_arr[i].replace(/\D/g, "");
                    }
                }
                var unit_weight = unit_weight_arr.join('.');
                if (parseFloat(unit_weight) == 0) {
                    $(this).val('');
                } else {
                    $(this).val(unit_weight);
                }

                self.recalculateParcelWeight(this);
            })
        },
        recalculateParcelWeight: function(element) {
            var parcelWeight = 0;
            if ($(element).parents('.export_doc_position').parent().find('.export_doc_position').length) {
                for (block of $(element).parents('.export_doc_position').parent().find('.export_doc_position')) {
                    var amount = $(block).find('.position_amount').val();
                    var unit_weight = $(block).find('.position_unit_weight').val();

                    parcelWeight += amount * unit_weight;
                }
                $(element).parents('.ordercarrier-panel').find('input.parcel_weight').val(Math.round(parcelWeight*100)/100);
            }
        },
        updateButtonIcon: function(section, button) {
            if(section.is(':visible')) {
                if (is177) {
                    button.find('i').text(button.data('icon-less'));
                } else {
                    button.find('i').removeClass().addClass(button.data('icon-less'));
                }
            } else {
                if (is177) {
                    button.find('i').text(button.data('icon-more'));
                } else {
                    button.find('i').removeClass().addClass(button.data('icon-more'));
                }
            }
        },
        showAddressTypeInputs: function(el) {
            checked_val = $(el).val();
            $(el).closest('#dhldp_dhl_update_address').find('input[type=radio][name^=address][name*=address_type]').each(function(index) {
                if ($(this).val() != checked_val) {
                    $(this).closest('#dhldp_dhl_update_address').find('.address_type_'+$(this).val()).hide();
                } else {
                    $(this).closest('#dhldp_dhl_update_address').find('.address_type_'+$(this).val()).show();
                }
            });
        },
        updateProduct: function(id_order_carrier) {
            var self = this;
            $.each(dhl_products_params[id_order_carrier], function(index, value ) {
                if ($('#ordercarrier-' + id_order_carrier +' #dhl_product_code').val() == value.fullcode) {
                    self.cur_dhl_product_params[id_order_carrier] = value;
                    self.updateExportDocumentsButton(id_order_carrier);
                    self.updateAdditionalServicesInputs(id_order_carrier);
                }
            })
        },
        updateAdditionalServicesInputs: function(id_order_carrier) {
            var self = this;
            if (typeof self.cur_dhl_product_params[id_order_carrier].definition.services != 'undefined') {
                var block_prefix = '#ordercarrier-' + id_order_carrier;
                var blocks = ['#dhldp_dhl_addit_services', '.dhldp_dhl_addit_services_inline'];
                var blocks_ch = ['input[type=checkbox]', 'input[type=text]', 'textarea', 'select'];
                var full_selector = '';
                for (var i = 0; i < blocks.length; i++) {
                    for (var k = 0; k < blocks_ch.length; k++) {
                        full_selector += block_prefix + ' ' + blocks[i] + ' ' + blocks_ch[k] + ',';
                    }
                }
                full_selector = full_selector.slice(0, -1);
                console.log(full_selector);
                //$('#ordercarrier-' + id_order_carrier +' #dhldp_dhl_addit_services input[type=checkbox], #dhldp_dhl_addit_services input[type=text], #dhldp_dhl_addit_services textarea, #dhldp_dhl_addit_services select').each(function(index) {
                $(full_selector).each(function(index) {
                    console.log($(this));
                    var name = $(this).attr('name').split('][')[1].replace(']', '');
                    console.log(name);
                    if ($.inArray(name, self.cur_dhl_product_params[id_order_carrier].definition.services) != -1) {
                        $(this).closest('.form-group-flex').show();
                    } else {
                        $(this).closest('.form-group-flex').hide();
                    }
                });
            }
        },
        updateExportDocumentsButton: function(id_order_carrier) {
            if (typeof this.cur_dhl_product_params[id_order_carrier].definition.export_documents != 'undefined' &&
                this.cur_dhl_product_params[id_order_carrier].definition.export_documents == 1) {
                $('#ordercarrier-' + id_order_carrier +' #submitDhlExportDocuments').show();
            } else {
                $('#ordercarrier-' + id_order_carrier +' #submitDhlExportDocuments').hide();
            }
        },
        getTrackingData: function(shipment_number, callback) {
            $.ajax({
                type: 'POST',
                url: dhldp_ajax_path,
                async: false,
                cache: false,
                dataType: "html",
                data: 'ajax=1&action=getTrackingData&' +
                    '&shipment_number=' + shipment_number,
                success: function (data) {
                    callback(data);
                },
                error: function (XMLHttpRequest, textStatus, errorThrown) {
                    alert('TECHNICAL ERROR Details: ' + XMLHttpRequest.responseText);
                }
            });
        },
    };

    dhldp_admin_orders.init();
});