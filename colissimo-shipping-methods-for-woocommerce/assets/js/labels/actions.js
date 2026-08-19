jQuery(function ($) {
    function isLabelDisabledAction(button) {
        if ($(button).hasClass('lpc_label_action_disabled') && button.hasAttribute('lpc-data-text')) {
            alert($(button).attr('lpc-data-text'));
            return true;
        }
        return false;
    }

    $('.lpc_label_action_download').off('click').on('click', function () {
        if (isLabelDisabledAction(this)) {
            return;
        }
        let specificAction = $(this).attr('data-link');
        if (specificAction !== undefined && specificAction !== '') {
            location.href = specificAction;
        }
    });

    $('.lpc_label_action_print').off('click').on('click', function () {
        if (isLabelDisabledAction(this)) {
            return;
        }
        $(this).addClass('lpc_label_printed');
        let specificAction = $(this).attr('data-link');
        let trackingNumber = $(this).attr('data-tracking-number');
        switch ($(this).attr('data-format')) {
            case 'ZPL':
            case 'DPL':
                let lpc_thermal_labels_infos = [
                    {
                        lpc_tracking_number: trackingNumber
                    }
                ];

                printThermal(lpc_thermal_labels_infos);

            // We don't break here because we want to print in PDF the invoice and maybe the CN23
            case 'PDF':
                printPDF(specificAction);
                break;
        }

    });

    $('.lpc_label_action_send_email').off('click').on('click', function () {
        if (isLabelDisabledAction(this)) {
            return;
        }

        const specificAction = $(this).attr('data-link');
        if (specificAction !== undefined && specificAction !== '') {
            location.href = specificAction;
        }
    });

    $('.lpc_label_action_delete').off('click').on('click', function () {
        let specificAction = $(this).attr('data-link');
        let trackingNumber = $(this).attr('data-tracking-number');
        let labelType = $(this).attr('data-label-type');
        let confirmText;

        switch (labelType) {
            case 'outward':
                confirmText = lpcLabelsActions.deletionConfirmTextOutward;
                break;
            case 'inward':
                confirmText = lpcLabelsActions.deletionConfirmTextInward;
                break;
            case 'bordereau':
                confirmText = lpcLabelsActions.deletionConfirmTextBordereau;
                break;
        }

        if (specificAction !== undefined && specificAction !== '') {
            if (window.confirm(trackingNumber + ' : ' + confirmText)) {
                location.href = specificAction;
            }
        }
    });

    $('.lpc_generate_label').off('click').on('click', function () {
        let icon = $(this).children('.lpc_generate_label_dashicon');
        let specificAction = icon.attr('data-link');
        let labelType = icon.attr('data-label-type');
        let confirmText = labelType === 'outward' ? lpcLabelsActions.generateConfirmTextOutward : lpcLabelsActions.generateConfirmTextInward;
        if (specificAction !== undefined && specificAction !== '') {
            if (window.confirm(confirmText)) {
                location.href = specificAction;
            }
        }
    });

    function printThermal(thermalLabelsInfos) {
        $.ajax({
            type: 'POST',
            url: lpcLabelsActions.thermalLabelPrintActionUrl,
            data: {lpc_thermal_labels_infos: thermalLabelsInfos},
            dataType: 'json'
        }).success(function (response) {
            const labels = JSON.parse(response);
            if (!labels || 0 === labels.length) {
                return;
            }

            printThermalLabels(labels);
        }).error(function (error) {
            console.error(error);
        });
    }

    // Print the thermal labels through QZ Tray, falling back to the legacy print kit URLs
    // when QZ Tray is not installed/running (e.g. merchant updated but hasn't set it up yet).
    function printThermalLabels(labels) {
        if ('undefined' === typeof qz || !qz.websocket) {
            printThermalLegacy(labels);
            return;
        }

        setupQzTray();

        connectQzTray().then(function () {
            getQzConfig()
                .then(function (config) {
                    return printRawSequentially(config, labels);
                })
                .then(function () {
                    disconnectQzTray();
                })
                .catch(function (error) {
                    disconnectQzTray();
                    console.error(error);
                    if ($('#lpc_thermal_print_error_message').length === 0) {
                        displayErrors(lpcLabelsActions.errorMsgPrintThermal + ' ' + error);
                    }
                });
        }, function (connectError) {
            // Could not reach QZ Tray: fall back to the legacy print kit so existing setups keep working
            console.warn('QZ Tray unavailable, falling back to the legacy print kit', connectError);
            printThermalLegacy(labels);
        });
    }

    let qzTrayReady = false;

    function setupQzTray() {
        if (qzTrayReady) {
            return;
        }

        qz.api.setPromiseType(function (resolver) {
            return new Promise(resolver);
        });

        // QZ Tray runs unsigned here: it prompts the operator to allow printing the first time
        // (they can tick "remember"). Signed mode can be wired later through qz.security.* if a
        // certificate is provided.

        qzTrayReady = true;
    }

    function connectQzTray() {
        if (qz.websocket.isActive()) {
            return Promise.resolve();
        }

        return qz.websocket.connect();
    }

    function disconnectQzTray() {
        if (qz.websocket.isActive()) {
            qz.websocket.disconnect();
        }
    }

    function getQzConfig() {
        const printerName = lpcLabelsActions.qzPrinter;
        const printerPromise = printerName ? qz.printers.find(printerName) : qz.printers.getDefault();

        return printerPromise.then(function (printer) {
            return qz.configs.create(printer);
        });
    }

    function printRawSequentially(config, labels) {
        return labels.reduce(function (promise, info) {
            return promise.then(function () {
                if (!info.label) {
                    return null;
                }

                return qz.print(
                    config,
                    [
                        {
                            type: 'raw',
                            format: 'command',
                            flavor: 'base64',
                            data: info.label
                        }
                    ]
                );
            });
        }, Promise.resolve());
    }

    function printThermalLegacy(labels) {
        labels.forEach(function (info) {
            if (!info.legacyUrl || 0 === info.legacyUrl.length) {
                return;
            }

            $.ajax({
                type: 'GET',
                url: info.legacyUrl,
                dataType: 'html'
            }).error(function (xhr, status, error) {
                console.error('error on label ' + info.trackingNumber);
                console.error('Error message: ' + xhr.responseText);
                console.error('Error message: ' + error);
                if ($('#lpc_thermal_print_error_message').length === 0) {
                    displayErrors(lpcLabelsActions.errorMsgPrintThermal + ' ' + xhr.responseText + ' URL: ' + info.legacyUrl);
                }
            });
        });
    }

    function printPDF(specificAction) {
        $('#lpcPrintIframe').remove();
        $('#wpbody-content').append('<iframe type="application/pdf" src="" width="100%" height="100%" id="lpcPrintIframe"></iframe>');

        let ePdf = document.getElementById('lpcPrintIframe');
        if (ePdf && ePdf.tagName === 'IFRAME') {
            if (navigator.userAgent.indexOf('Safari') === -1) {
                ePdf.style.position = 'fixed';
                ePdf.style.right = '0';
                ePdf.style.bottom = '0';
                ePdf.style.width = '0';
                ePdf.style.height = '0';
                ePdf.style.border = '0';
            }

            ePdf.src = specificAction;
            ePdf.onload = function () {
                if ($(ePdf).contents().find('body').html() != 'null') {
                    ePdf.contentWindow.focus();
                    ePdf.contentWindow.print();
                    setTimeout(function () {
                        $(ePdf).css('display', 'none');
                    }, 1000);
                }
            };
        }
    }

    function lpc_print_labels(infos) {
        let url = infos.pdfUrl;
        let trackingNumbers = infos.trackingNumbers;
        let type = infos.labelType;

        let splittedTrackingNumbers = trackingNumbers.split(',');

        let thermalPrintInfo = [];

        splittedTrackingNumbers.forEach(function (trackingNumber) {
            thermalPrintInfo.push({
                lpc_tracking_number: trackingNumber
            });
        });

        // We want the labels to be printed from older to latest
        thermalPrintInfo.reverse();

        printThermal(thermalPrintInfo);
        printPDF(url);
    }

    function displayErrors(errorMessage) {
        let $wpHeaderEnd = $('.wp-header-end');

        if ($wpHeaderEnd.length) {
            $wpHeaderEnd.after('<div class="error" id="lpc_thermal_print_error_message"><p>' + errorMessage + '</p></div>');
        } else {
            alert(errorMessage);
        }
    }

    window.lpc_print_labels = lpc_print_labels;
});
