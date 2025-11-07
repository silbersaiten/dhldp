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

$(function(){
    $('a.requestDHLDPDhlLabelData').click(function(evt){
        evt.preventDefault();

        var link = $(this);

        $.fancybox.open({
            href: link.attr('href'),
            type: 'iframe'
        });

        return false;
    });

    $(document).on('click', '#showAllDHLDPDhlLabels', function(e){
        if ($('#sectionAllDHLDPDhlLabels').is(":visible") ) {
            $('#sectionAllDHLDPDhlLabels').hide();
        } else {
            $('#sectionAllDHLDPDhlLabels').show();
        }
        dhldp_admin_order.updateButtonIcon($('#sectionAllDHLDPDhlLabels'), $(this));
    });

    var dhldp_admin_order = {
        cur_dhl_product_params: null,
        init: function() {
            var self = this;

            if (!is177) {
                $('[data-toggle="popover"]').popover();
            }

            dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_update_address'), $('#submitDHLDPDhlUpdateAddress'));
            dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_addit_services'), $('#submitDHLDPDhlAdditServices'));
            dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_export_documents'), $('#submitDHLDPDhlExportDocuments'));

            //init
            if (typeof dhldp_dhl_products_params != 'undefined') {
                self.updateProduct();
            }

            //on change
            $(document).on('change', '#dhldp_dhl_product_code', function() {
                self.updateProduct();
            });

            $(document).on('click', '#submitDHLDPDhlUpdateAddress', function(e) {
                if(!$('#dhldp_dhl_update_address').is(':visible')) {
                    $('#dhldp_dhl_update_address').find('input#show_update_address').val(1);
                    $('#dhldp_dhl_update_address').show(400, () => {
                        offset = $('.page-head').length?$('.page-head').outerHeight():0;
                        offset += $('.navbar-header').length?$('.navbar-header').outerHeight():0;
                        offset += $('div.toolbarBox').length?$('div.toolbarBox').outerHeight():0;
                        $('html, body').animate({
                            scrollTop: $("#dhldp_dhl_update_address").offset().top - offset
                        }, 500);
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_update_address'), $(this));
                    });
                } else {
                    $('#dhldp_dhl_update_address').find('input#show_update_address').val('');
                    $('#dhldp_dhl_update_address').hide(400, () => {
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_update_address'), $(this));
                    });
                }
            });

            $(document).on('click', '#collapseDHLDPDhlUpdateAddress', function(e) {
                $('#submitDHLDPDhlUpdateAddress').trigger('click');
            });

            $(document).on('click', '#submitDHLDPDhlAdditServices', function(e) {
                if(!$('#dhldp_dhl_addit_services').is(':visible')) {
                    $('#dhldp_dhl_addit_services').find('input#show_dhl_additional_services').val(1);
                    $('#dhldp_dhl_addit_services').show(400, () => {
                        offset = $('.page-head').length?$('.page-head').outerHeight():0;
                        offset += $('.navbar-header').length?$('.navbar-header').outerHeight():0;
                        offset += $('div.toolbarBox').length?$('div.toolbarBox').outerHeight():0;
                        $('html, body').animate({
                            scrollTop: $("#dhldp_dhl_addit_services").offset().top - offset
                        }, 500);
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_addit_services'), $(this));
                    });
                } else {
                    $('#dhldp_dhl_addit_services').find('input#show_dhl_additional_services').val('');
                    $('#dhldp_dhl_addit_services').hide(400, () => {
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_addit_services'), $(this));
                    });
                }
            });

            $(document).on('click', '#collapseDHLDPDhlAdditServices', function(e) {
                $('#submitDHLDPDhlAdditServices').trigger('click');
            });

            $(document).on('click', '#submitDHLDPDhlExportDocuments', function(e) {
                var button = $(this);
                if(!$('#dhldp_dhl_export_documents').is(':visible')) {
                    $('#dhldp_dhl_export_documents').find('input#show_dhl_export_documents').val(1);
                    $('#dhldp_dhl_export_documents').show(400, () => {
                        offset = $('.page-head').length?$('.page-head').outerHeight():0;
                        offset += $('.navbar-header').length?$('.navbar-header').outerHeight():0;
                        offset += $('div.toolbarBox').length?$('div.toolbarBox').outerHeight():0;
                        $('html, body').animate({
                            scrollTop: $("#dhldp_dhl_export_documents").offset().top - offset
                        }, 500);
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_export_documents'), button);
                    });

                } else {
                    $('#dhldp_dhl_export_documents').find('input#show_dhl_export_documents').val('');
                    $('#dhldp_dhl_export_documents').hide(400, function() {
                        dhldp_admin_order.updateButtonIcon($('#dhldp_dhl_export_documents'), button);
                    });
                }

            });

            $(document).on('click', '#collapseDHLDPDhlExportDocuments', function(e) {
                $('#submitDHLDPDhlExportDocuments').trigger('click');
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
                    self.showAddressTypeInputs(this)
                }
            });

            $(document).on('click', "#submitDHLDPDhlCreateLabelNoValidation", function(e) {
                $('#validation_of_dhl_label_creation').val('true');
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
            });

            $(document).on('click', '.btn-scroll-to-dhldp-block', function () {
                var offset = $('.page-head').length ? $('.page-head').outerHeight() : 0;
                $('html, body').animate({
                    scrollTop: $('.dhldp_dhl_order_block').offset().top - offset
                }, 500);
            });
        },
        recalculateParcelWeight: function(element) {
            var parcelWeight = 0;
            if ($(element).parents('.export_doc_position').parent().find('.export_doc_position').length) {
                for (block of $(element).parents('.export_doc_position').parent().find('.export_doc_position')) {
                    var amount = $(block).find('.position_amount').val();
                    var unit_weight = $(block).find('.position_unit_weight').val();

                    parcelWeight += amount * unit_weight;
                }
                $(element).parents('.dhl_order_panel').find('input.parcel_weight').val(Math.round(parcelWeight*100)/100);
            }
        },
        updateButtonIcon: function(section, button) {
            if(section.is(':visible')) {
                console.log('Visible');
                if (is177) {
                    console.log(button.data('icon-less'));
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
        updateProduct: function() {
            var self = this;
            $.each(dhldp_dhl_products_params, function(index, value ) {
                if ($('#dhldp_dhl_product_code').val() == value.fullcode) {
                    self.cur_dhl_product_params = value;
                    self.showParamsHelpBlocks();
                    self.updateExportDocumentsButton();
                    self.updateAdditionalServicesInputs();
                    if (window.dhlProductDimensions && window.dhlProductDimensions[value.fullcode]) {
                        var dims = window.dhlProductDimensions[value.fullcode];
                        $('#dhl_weight_package').val(dims.weight);
                        $('#dhl_length').val(dims.length);
                        $('#dhl_width').val(dims.width);
                        $('#dhl_height').val(dims.height);
                    } else {
                        var selectedProduct = $('#dhldp_dhl_product_code').val();
                        var urlParts = window.location.pathname.split('/').filter(Boolean);
                        var orderId = urlParts[urlParts.length - 2];


                        if (!selectedProduct) {
                            $('#dhl_length, #dhl_width, #dhl_height, #dhl_weight_package').val('');
                            return;
                        }

                        $.ajax({
                            type: 'POST',
                            url: dhldp_ajax_path,
                            async: false,
                            cache: false,
                            data: {
                                ajax: 1,
                                action: 'getDhlProductDimensions',
                                dhl_product_dimension: selectedProduct,
                                orderId:  orderId
                            },
                            success: function(response) {
                                if (typeof response === 'string') {
                                    try {
                                        response = JSON.parse(response);
                                    } catch (e) {
                                        console.error('error JSON:', e);
                                        return;
                                    }
                                }

                                if (response.success) {
                                    $('#dhl_length').val(response.dimensions.LENGTH);
                                    $('#dhl_width').val(response.dimensions.WIDTH);
                                    $('#dhl_height').val(response.dimensions.HEIGHT);
                                    $('#dhl_weight_package').val(response.dimensions.WEIGHT);
                                } else {
                                    $('#dhl_length, #dhl_width, #dhl_height, #dhl_weight_package').val('');
                                }
                            },
                            error: function() {
                                $('#dhl_length, #dhl_width, #dhl_height, #dhl_weight_package').val('');
                            }
                        });
                    }
                }
            });
        },
        updateAdditionalServicesInputs: function() {
            var self = this;
            if (typeof self.cur_dhl_product_params.definition.services != 'undefined') {
                var blocks = ['#dhldp_dhl_addit_services', '.dhldp_dhl_addit_services_inline'];
                for (var i = 0; i < blocks.length; i++) {
                    $(blocks[i] +' input[type=checkbox], ' +
                        blocks[i] + ' input[type=text], ' +
                        blocks[i] + ' textarea, ' +
                        blocks[i] + ' select').each(function (index) {

                        var name = $(this).attr('name').split('][')[1].replace(']', '')
                        if ($.inArray(name, self.cur_dhl_product_params.definition.services) != -1) {
                            $(this).closest('.form-group-flex').show();
                        } else {
                            $(this).closest('.form-group-flex').hide();
                        }
                    });
                }
            }
        },
        updateExportDocumentsButton: function() {
            if (typeof this.cur_dhl_product_params.definition.export_documents != 'undefined' && this.cur_dhl_product_params.definition.export_documents == 1) {
                $('#submitDHLDPDhlExportDocuments').show();
            } else {
                $('#submitDHLDPDhlExportDocuments').hide();
            }
        },
        showParamsHelpBlocks: function() {
            var params = this.cur_dhl_product_params.definition.params;
            $('#dhldp_dhl_params #dhl_height').closest('div.col-lg-3').find('span.help-block').text('max. '+params.height.max+' '+params.height.unit);
            $('#dhldp_dhl_params #dhl_length').closest('div.col-lg-3').find('span.help-block').text('max. '+params.length.max+' '+params.length.unit);
            $('#dhldp_dhl_params #dhl_width').closest('div.col-lg-3').find('span.help-block').text('max. '+params.width.max+' '+params.width.unit);
            $('#dhldp_dhl_params #dhl_weight_package').closest('div.col-lg-3').find('span.help-block').text('max. '+params.weight_package.max+' '+params.weight_package.unit);
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
        IsNumeric: function(input) {
            var RE = /^-{0,1}\d*\.{0,1}\d+$/;
            return (RE.test(input));
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
    }
    dhldp_admin_order.init();
});