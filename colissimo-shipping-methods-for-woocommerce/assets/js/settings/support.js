jQuery(function ($) {
    // Reload the logs displayed in the support modal without reloading the page
    $(document).on('click', '#lpc_logs_refresh', function () {
        const $button = $(this);
        const $logs = $('#lpc_logs_content');

        $button.prop('disabled', true);

        $.get(lpcSupportLogs.ajaxURL).done(function (response) {
            response = JSON.parse(response);
            if ('success' === response.type) {
                $logs.html(response.logs);
            }
        }).always(function () {
            $button.prop('disabled', false);
        });
    });
});
