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
        let format = $(this).attr('data-format');

        if ('ZPL' === format || 'DPL' === format) {
            let lpc_thermal_labels_infos = [
                {
                    lpc_tracking_number: trackingNumber
                }
            ];

            // The invoice and the possible CN23 are still printed as PDF, but only once the thermal labels have been sent
            printThermal(lpc_thermal_labels_infos, function () {
                printPDF(specificAction);
            });

            return;
        }

        if ('PDF' === format) {
            printPDF(specificAction);
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

    // onDone runs once the labels have been handed over to the printer, whatever the outcome
    function printThermal(thermalLabelsInfos, onDone) {
        let pending = onDone;
        const done = function () {
            if (pending) {
                const callback = pending;
                pending = null;
                callback();
            }
        };

        $.ajax({
            type: 'POST',
            url: lpcLabelsActions.thermalLabelPrintActionUrl,
            data: {lpc_thermal_labels_infos: thermalLabelsInfos},
            dataType: 'json'
        }).done(function (response) {
            const labels = JSON.parse(response);
            if (!labels || 0 === labels.length) {
                done();
                return;
            }

            printThermalLabels(labels, done);
        }).fail(function (error) {
            console.error(error);
            done();
        });
    }

    function isQzConfigured() {
        return 'undefined' !== typeof lpcQz && lpcQz.isAvailable() && (lpcQz.isSigned() || !!lpcLabelsActions.qzPrinter);
    }

    // Print the thermal labels through QZ Tray, falling back to the legacy print kit URLs
    function printThermalLabels(labels, onSent) {
        const done = onSent || function () {};

        if (!isQzConfigured()) {
            printThermalLegacy(labels);
            done();

            return;
        }

        lpcQz.connect().then(
            function () {
                return getQzConfig().then(function (config) {
                    return printRawSequentially(config, labels);
                });
            },
            function (connectError) {
                // Could not reach QZ Tray: fall back to the legacy print kit so existing setups keep working
                console.warn('QZ Tray unavailable, falling back to the legacy print kit', connectError);
                printThermalLegacy(labels);
            }
        ).then(done, function (error) {
            console.error(error);
            if ($('#lpc_thermal_print_error_message').length === 0) {
                displayErrors(lpcLabelsActions.errorMsgPrintThermal + ' ' + error);
            }
            done();
        });
    }

    let qzConfigPromise = null;

    // The printer lookup is a signed call, so it costs a round trip to the website: do it once per page
    function getQzConfig() {
        if (null === qzConfigPromise) {
            const printerName = lpcLabelsActions.qzPrinter;
            const printerPromise = printerName ? qz.printers.find(printerName) : qz.printers.getDefault();

            qzConfigPromise = printerPromise.then(function (printer) {
                return qz.configs.create(printer);
            }).catch(function (error) {
                qzConfigPromise = null;

                throw error;
            });
        }

        return qzConfigPromise;
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
            }).fail(function (xhr, status, error) {
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

        printThermal(thermalPrintInfo, function () {
            printPDF(url);
        });
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
