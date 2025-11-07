/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2025 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.0.0
 * @link      http://www.silbersaiten.de
 */
$(document).ready(function() {
    function loadDhlProductDimensions() {
        var selectedProduct = $('#dhlcp_product_dimension').val();
        
        if (!selectedProduct) {
            $('#defaultLength, #defaultWidth, #defaultHeight, #defaultWeight').val('');
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
            },
            success: function (response) {
                try {
                    if (typeof response === 'object' && response !== null) {
                        var data = response;
                    } else {
                        var data = JSON.parse(response);
                    }

                    if (data.success) {
                        $('#defaultLength').val(data.dimensions.LENGTH);
                        $('#defaultWidth').val(data.dimensions.WIDTH);
                        $('#defaultHeight').val(data.dimensions.HEIGHT);
                        $('#defaultWeight').val(data.dimensions.WEIGHT);
                    } else {
                        $('#defaultLength, #defaultWidth, #defaultHeight, #defaultWeight').val('');
                        console.error('Server returned error:', data.message || 'Unknown error');
                    }
                } catch (e) {
                    console.error('Error parsing response:', e);
                    console.log('Raw response:', response);
                    $('#defaultLength, #defaultWidth, #defaultHeight, #defaultWeight').val('');
                }
            },
            error: function (xhr, status, error) {
                console.error('Ajax error:', status, error);
                console.log('Response:', xhr.responseText);
                $('#defaultLength, #defaultWidth, #defaultHeight, #defaultWeight').val('');
            }
        });
    }

    $('#dhlcp_product_dimension').change(function () {
        loadDhlProductDimensions();
    });

    $('#saveDhlProductDimension').click(function() {
        const selectedProduct = $('#dhlcp_product_dimension').val();
        var $messageBox = $('#dhl-message-box');
        $messageBox.removeClass('alert-success alert-danger').hide().text('');
        if (!selectedProduct) {
            showErrorMessage('Please select a product');
            return;
        }

        const length = $('#defaultLength').val();
        const width = $('#defaultWidth').val();
        const height = $('#defaultHeight').val();
        const weight = $('#defaultWeight').val();

        $.ajax({
            type: 'POST',
            url: dhldp_ajax_path,
            async: false,
            cache: false,
            data: {
                ajax: 1,
                action: 'updateDhlProductDimensions',
                dhl_product_dimension: selectedProduct,
                defaultLength: length,
                defaultWidth: width,
                defaultHeight: height,
                defaultWeight: weight
            },
            success: function(response) {
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Raw response:', response);
                        showErrorMessage('Server returned invalid JSON.');
                        return;
                    }
                }

                if (response.success) {
                    showSuccessMessage(response.message || 'Saved successfully');
                } else {
                    showErrorMessage(response.message || 'An error occurred');
                }
            },
            error: function() {
                showErrorMessage('Error contacting server. Try again later.');
            }
        });
    });

    $('#cancelDhlProductDimension').click(function () {
        loadDhlProductDimensions();
    });

    function showSuccessMessage(message) {
        $.growl.notice({ title: '', message: message });
    }

    function showErrorMessage(message) {
        $.growl.error({ title: '', message: message });
    }
}); 