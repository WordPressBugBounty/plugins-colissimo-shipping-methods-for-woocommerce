jQuery(function ($) {
    function init() {
        changeCredentialsType();
        securedReturn();
        thermalPrint();
        qzTrayCertificate();
        qzTrayFiles();
        extraCost();
        relays();
        shippingDate();
        ddp();
        doc();
    }

    init();

    function changeCredentialsType() {
        const $apiKeyFields = $('#lpc_apikey, #lpc_contract_number');
        const $accountFields = $('#lpc_id_webservices, #lpc_pwd_webservices');
        const $apiKeyFieldsContainers = $apiKeyFields.closest('tr');
        const $accountFieldsContainers = $accountFields.closest('tr');
        $('#lpc_credentials_type').on('change', function () {
            if ('api_key' === $(this).val()) {
                $accountFieldsContainers.hide();
                $apiKeyFieldsContainers.show();
                $apiKeyFields.attr('required', 'required');
                $accountFields.removeAttr('required');
            } else {
                $accountFieldsContainers.show();
                $apiKeyFieldsContainers.hide();
                $accountFields.attr('required', 'required');
                $apiKeyFields.removeAttr('required');
            }
        }).trigger('change');
    }

    function securedReturn() {
        const $frontReturn = $('#lpc_customers_download_return_label');
        const $securedReturn = $('#lpc_secured_return');
        const $frontReturnDelayContainer = $('.wc-settings-row-lpc_customers_download_return_label_days_container');
        const $securedReturnContainer = $('#lpc_secured_return_container');
        const $balReturnContainer = $('.wc-settings-row-lpc_bal_return_container');
        const $autoReturnContainer = $('.wc-settings-row-lpc_createReturnLabelWithOutward_container');

        const toggleReturnOptions = function () {
            $frontReturnDelayContainer.show();
            $securedReturnContainer.show();
            $balReturnContainer.show();
            $autoReturnContainer.show();

            if ('no' === $frontReturn.val()) {
                $frontReturnDelayContainer.hide();
                $securedReturnContainer.hide();
                $balReturnContainer.hide();
            } else if ($securedReturn.is(':checked')) {
                $balReturnContainer.hide();
                $autoReturnContainer.hide();
            }
        };

        $frontReturn.on('change', toggleReturnOptions).trigger('change');
        $securedReturn.on('change', toggleReturnOptions);
    }

    function qzTrayCertificate() {
        const $generate = $('#lpc_qz_generate_button');
        if (!$generate.length) {
            return;
        }

        const $download = $('#lpc_qz_download_button');
        const $status = $('#lpc_qz_certificate_status');
        const $importRows = $('#lpc_qz_certificate, #lpc_qz_private_key').closest('tr');
        let certificate = 'undefined' === typeof lpcQzSigning ? '' : lpcQzSigning.certificate;

        // The manual import stays available for merchants using their own QZ Tray licence
        $importRows.hide();
        $('#lpc_qz_import_toggle').on('click', function (event) {
            event.preventDefault();
            $importRows.show();
            $(this).closest('p').hide();
        });

        $generate.on('click', function () {
            if (certificate && !window.confirm(lpcThermalSettings.regenerateConfirm)) {
                return;
            }

            $generate.prop('disabled', true);
            $status.text(lpcThermalSettings.generating);

            $.ajax({
                type: 'POST',
                url: $generate.data('url'),
                dataType: 'json'
            }).done(function (response) {
                if (response && 'success' === response.type && response.certificate) {
                    certificate = response.certificate;
                    $status.text(response.info);
                    $download.prop('disabled', false);
                    $generate.text(lpcThermalSettings.regenerate);
                } else {
                    $status.text(response && response.message ? response.message : lpcThermalSettings.generateError);
                }

                $generate.prop('disabled', false);
            }).fail(function () {
                $status.text(lpcThermalSettings.generateError);
                $generate.prop('disabled', false);
            });
        });

        $download.on('click', function () {
            if (!certificate) {
                return;
            }

            const url = URL.createObjectURL(new Blob([certificate], {type: 'application/x-x509-ca-cert'}));
            const $link = $('<a></a>').attr('href', url).attr('download', 'certificate.crt');
            $('body').append($link);
            $link[0].click();
            $link.remove();
            URL.revokeObjectURL(url);
        });
    }

    function qzTrayFiles() {
        $('.lpc_qz_file').on('change', function () {
            const $field = $('#' + $(this).data('target'));
            const $status = $('#' + $(this).data('target') + '_status');
            const file = this.files && this.files[0];

            if (!file) {
                $field.val('');
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                $field.val(event.target.result);
                $status.text(lpcThermalSettings.fileSelected.replace('%s', file.name));
            };

            reader.onerror = function () {
                $field.val('');
                $status.text(lpcThermalSettings.fileError);
            };

            reader.readAsText(file);
        });
    }

    function thermalPrint() {
        const $printerField = $('#lpc_zpldpl_labels_printer');
        if (!$printerField.length) {
            return;
        }

        // Dropdown of printers detected by QZ Tray; selecting one fills the manual field.
        const $select = $('<select id="lpc_zpldpl_labels_printer_select" style="display:block;margin-bottom:6px;"></select>');
        const $status = $('<p class="description" style="margin-top:4px;"></p>');
        $printerField.before($select);
        $printerField.after($status);

        $select.on('change', function () {
            const value = $(this).val();
            if (value) {
                $printerField.val(value);
            }
        });

        if ('undefined' === typeof lpcQz || !lpcQz.isAvailable()) {
            $select.hide();
            $status.text(lpcThermalSettings.unavailable);
            return;
        }

        const $detect = $('<button type="button" class="button" style="display:block;margin-bottom:6px;"></button>')
            .text(lpcThermalSettings.detect);
        $select.before($detect);

        const detectPrinters = function () {
            $detect.prop('disabled', true);
            $select.empty().show();
            $status.text(lpcThermalSettings.detecting);
            $select.append($('<option></option>').val('').text(lpcThermalSettings.select));

            const finish = function () {
                $detect.prop('disabled', false);
                lpcQz.disconnect();
            };

            lpcQz.connect().then(function () {
                return qz.printers.find();
            }).then(function (printers) {
                const list = Array.isArray(printers) ? printers : [printers];
                const current = $printerField.val();

                list.forEach(function (printer) {
                    const $option = $('<option></option>').val(printer).text(printer);
                    if (printer === current) {
                        $option.prop('selected', true);
                    }
                    $select.append($option);
                });

                $status.text('');
                finish();
            }).catch(function (error) {
                console.warn('QZ Tray printer detection failed', error);
                $select.hide();
                $status.text(lpcThermalSettings.unavailable);
                finish();
            });
        };

        $detect.on('click', detectPrinters);

        // Without signing, connecting makes QZ Tray prompt the operator: only do it on demand
        // once a printer has been chosen.
        if (lpcQz.isSigned() || !$printerField.val()) {
            detectPrinters();
        } else {
            $select.hide();
            $status.text(lpcThermalSettings.detectHint);
        }
    }

    function extraCost() {
        const $extraCostOverFreeContainer = $('.wc-settings-row-lpc_extra_cost_over_free_container');
        $('#lpc_extra_cost').on('change', function () {
            if ($(this).val() > 0) {
                $extraCostOverFreeContainer.show();
            } else {
                $extraCostOverFreeContainer.hide();
            }
        }).trigger('change');
    }

    function relays() {
        const $gmapsKeyContainer = $('.wc-settings-row-lpc_gmap_key_container');
        const $mobileMapContainer = $('.wc-settings-row-lpc_show_list_only_mobile_container');
        const $maxPointsContainer = $('.wc-settings-row-lpc_max_relay_point_container');
        const $customizeWidgetContainer = $('.wc-settings-row-lpc_prCustomizeWidget_container');
        const $customizeWidget = $('#lpc_prCustomizeWidget');
        const $addressTextColorContainer = $('.wc-settings-row-lpc_prAddressTextColor_container');
        const $listTextColorContainer = $('.wc-settings-row-lpc_prListTextColor_container');
        const $displayFontContainer = $('.wc-settings-row-lpc_prDisplayFont_container');

        $('#lpc_pickup_map_type').on('change', function () {
            if ('gmaps' === $(this).val()) {
                $gmapsKeyContainer.show();
            } else {
                $gmapsKeyContainer.hide();
            }

            if ('widget' === $(this).val()) {
                $mobileMapContainer.hide();
                $maxPointsContainer.hide();
                $customizeWidgetContainer.show();
                if ($customizeWidget.is(':checked')) {
                    $addressTextColorContainer.show();
                    $listTextColorContainer.show();
                    $displayFontContainer.show();
                } else {
                    $addressTextColorContainer.hide();
                    $listTextColorContainer.hide();
                    $displayFontContainer.hide();
                }
            } else {
                $mobileMapContainer.show();
                $maxPointsContainer.show();
                $customizeWidgetContainer.hide();
                $addressTextColorContainer.hide();
                $listTextColorContainer.hide();
                $displayFontContainer.hide();
            }
        }).trigger('change');

        $customizeWidget.on('change', function () {
            if ($(this).is(':checked')) {
                $addressTextColorContainer.show();
                $listTextColorContainer.show();
                $displayFontContainer.show();
            } else {
                $addressTextColorContainer.hide();
                $listTextColorContainer.hide();
                $displayFontContainer.hide();
            }
        });
    }

    function shippingDate() {
        const $showEstimatedShippingDate = $('#lpc_display_shipping_date');
        const $shippingDateOptionsContainers = $('.wc-settings-row-lpc_delivery_date_container');
        const $depositLocationSelect = $('#lpc_delivery_date_deposit_location');

        if (!$depositLocationSelect.length) {
            $showEstimatedShippingDate.closest('tr').hide();
            $shippingDateOptionsContainers.hide();

            return;
        }

        $showEstimatedShippingDate.on('change', function () {
            if ($(this).is(':checked')) {
                $shippingDateOptionsContainers.show();
            } else {
                $shippingDateOptionsContainers.hide();
            }
        }).trigger('change');
    }

    function ddp() {
        const $ftdExtraCostContainer = $('.wc-settings-row-lpc_extraCostOm');

        $('#lpc_customs_isFtd').on('change', function () {
            if ($(this).is(':checked')) {
                $ftdExtraCostContainer.show();
            } else {
                $ftdExtraCostContainer.hide();
            }
        }).trigger('change');
    }

    function doc() {
        $('#lpc_doc_download').on('click', function () {
            window.open($('#lpc_doc_url').val(), '_blank');
        });
        $('#lpc_doc_EN_download').on('click', function () {
            window.open($('#lpc_doc_EN_url').val(), '_blank');
        });
    }
});
