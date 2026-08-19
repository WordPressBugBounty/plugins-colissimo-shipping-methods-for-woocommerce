jQuery(function ($) {
    const $form = $('#lpc-simulation-form');
    if (!$form.length) {
        return;
    }

    const $optionsContainer = $('#lpc-simulation-options');
    const $result = $('#lpc-simulation-result');
    const optionTemplate = $.trim($('#lpc-simulation-option-template').html());

    // Translated option labels, indexed by option code, taken from the option select of the form
    const optionLabels = {};
    $(optionTemplate).find('.lpc-option-code option').each(function () {
        optionLabels[this.value] = $(this).text();
    });

    // Show the relevant extra field depending on the selected option
    function refreshOptionRow($row) {
        const code = $row.find('.lpc-option-code').val();
        $row.find('.lpc-option-insured-value').toggle('VALEUR_ASSUREE' === code);
        $row.find('.lpc-option-recommendation').toggle('AVIS_RECEPTION' === code);
    }

    $('#lpc-simulation-add-option').on('click', function () {
        const $row = $(optionTemplate);
        $optionsContainer.append($row);
        refreshOptionRow($row);
    });

    $optionsContainer.on('change', '.lpc-option-code', function () {
        refreshOptionRow($(this).closest('.lpc-option-row'));
    });

    $optionsContainer.on('click', '.lpc-option-remove', function () {
        $(this).closest('.lpc-option-row').remove();
    });

    // Collect the valued options into the payload structure expected by the web service
    function collectOptions() {
        const options = [];

        $optionsContainer.find('.lpc-option-row').each(function () {
            const $row = $(this);
            const code = $row.find('.lpc-option-code').val();
            if (!code) {
                return;
            }

            let choix = '';
            if ('VALEUR_ASSUREE' === code) {
                choix = $row.find('.lpc-option-insured-value').val();
            } else if ('AVIS_RECEPTION' === code) {
                choix = $row.find('.lpc-option-recommendation').val();
            }

            options.push({
                codeOption: code,
                choix: choix
            });
        });

        return options;
    }

    function esc(value) {
        return $('<div>').text(null === value || undefined === value ? '' : value).html();
    }

    // Format an amount as a localized currency value; returns '' when there is nothing to show
    function formatAmount(value) {
        if (null === value || undefined === value || '' === value) {
            return '';
        }

        const amount = parseFloat(value);
        if (isNaN(amount)) {
            return '';
        }

        return amount.toLocaleString(
            undefined,
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }
        ) + ' €';
    }

    // A table row for a named amount, skipped when the amount is not provided
    function amountRow(label, value, extraClass) {
        const formatted = formatAmount(value);
        if ('' === formatted) {
            return '';
        }

        return '<tr class="' + (extraClass || '') + '"><th>' + esc(label) + '</th><td>' + esc(formatted) + '</td></tr>';
    }

    // Detail rows for an array of supplements / options returned by the web service
    function detailRows(list) {
        if (!Array.isArray(list) || !list.length) {
            return '';
        }

        let rows = '';
        list.forEach(function (item) {
            if (item && 'object' === typeof item) {
                // The option details are nested in an "option" sub-object: {option: {codeOption, choix}, montantHT}
                const option = item.option || {};
                let label = item.libelle || '';
                if ('' === label) {
                    const code = item.codeOption || option.codeOption || item.codeMajoration || item.code || '';
                    label = optionLabels[code] || code;
                    if (option.choix && 'true' !== option.choix) {
                        label += ' (' + option.choix + ')';
                    }
                }
                let amount = item.montant;
                if (undefined === amount) {
                    amount = item.tarif;
                }
                if (undefined === amount) {
                    amount = item.montantHT;
                }
                const value = (undefined !== amount && null !== amount) ? formatAmount(amount) : JSON.stringify(item);
                rows += '<tr class="lpc-simulation-detail"><th>↳ ' + esc(label) + '</th><td>' + esc(value) + '</td></tr>';
            } else {
                rows += '<tr class="lpc-simulation-detail"><td colspan="2">' + esc(String(item)) + '</td></tr>';
            }
        });

        return rows;
    }

    // Render the calculated delivery cost with its breakdown
    function renderResult(result) {
        // Only errorType other than INFO are real errors; INFO messages (e.g. "RETURN_OK") are not shown
        const realErrors = (result.errors || []).filter(function (error) {
            return error.errorType && 'INFO' !== error.errorType;
        });

        if (realErrors.length) {
            showMessage(realErrors.map(function (error) {
                return error.errorMessage || error.errorCode;
            }).join(' '));
            return;
        }

        const options = result.montantDesOptions || {};

        let rows = '';
        rows += amountRow(lpcSimulation.i18n.transportCost, result.montantTransportHT);
        rows += amountRow(lpcSimulation.i18n.optionsTotal, options.montantTotalOptions);
        rows += detailRows(options.tarifDesOptions);
        rows += amountRow(lpcSimulation.i18n.supplements, result.montantSupplements);
        rows += detailRows(result.supplementsEventuels);
        rows += amountRow(lpcSimulation.i18n.returnCost, result.montantRetourHT);
        rows += amountRow(lpcSimulation.i18n.totalCost, result.montantTotalHT, 'lpc-simulation-total');

        if ('' === rows) {
            showMessage(lpcSimulation.i18n.noResult);
            return;
        }

        $result
            .html('<h2>' + esc(lpcSimulation.i18n.resultText) + '</h2><table class="widefat striped"><tbody>' + rows + '</tbody></table>')
            .show();
    }

    function showMessage(message) {
        $result.html('<p>' + $('<div>').text(message).html() + '</p>').show();
    }

    // Required fields the user must fill before a rate can be requested
    const requiredFields = [
        'recipientCountry',
        'recipientPostcode',
        'weight'
    ];

    function fieldLabel(id) {
        const label = $.trim($('label[for="' + id + '"]').text());
        return label || id;
    }

    function findMissingFields() {
        const missing = [];

        requiredFields.forEach(function (id) {
            const $field = $('#' + id);
            $field.removeClass('lpc-field-error');
            if ('' === $.trim($field.val())) {
                $field.addClass('lpc-field-error');
                missing.push(fieldLabel(id));
            }
        });

        return missing;
    }

    // Clear the error highlight as soon as the user edits a field
    $form.on('input change', '.lpc-field-error', function () {
        $(this).removeClass('lpc-field-error');
    });

    $form.on('submit', function (event) {
        event.preventDefault();

        const missing = findMissingFields();
        if (missing.length) {
            showMessage(lpcSimulation.i18n.missingFields + ' ' + missing.join(', '));
            return;
        }

        const $submit = $('#lpc-simulation-submit');
        $submit.prop('disabled', true);
        showMessage(lpcSimulation.i18n.loading);

        $.post(lpcSimulation.ajaxURL, {
            recipientCountry: $('#recipientCountry').val(),
            recipientPostcode: $('#recipientPostcode').val(),
            deliveryMode: $('#deliveryMode').val(),
            weight: $('#weight').val(),
            typeTarif: $('#typeTarif').val(),
            optionsValorisees: collectOptions()
        }).done(function (response) {
            response = JSON.parse(response);
            if (response.result) {
                renderResult(response.result);
            } else if (response.message) {
                showMessage(response.message);
            } else {
                showMessage(lpcSimulation.i18n.noResult);
            }
        }).fail(function () {
            showMessage(lpcSimulation.i18n.error);
        }).always(function () {
            $submit.prop('disabled', false);
        });
    });
});
