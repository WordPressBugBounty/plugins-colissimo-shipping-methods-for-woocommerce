jQuery(function ($) {
    $(document).on('click', '#lpc_check_db_button', function () {
        const $button = $(this);
        const $spinner = $('#lpc_check_db_spinner');
        const $result = $('#lpc_check_db_result');

        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.empty();

        $.get(lpcCheckDatabase.ajaxURL).done(function (response) {
            response = JSON.parse(response);
            $result.text('success' === response.type ? response.message : lpcCheckDatabase.error);
        }).fail(function () {
            $result.text(lpcCheckDatabase.error);
        }).always(function () {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
        });
    });
});
