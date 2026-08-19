<?php

use Colissimo\Init\InitAdmin;

defined('ABSPATH') || die('Restricted Access');
?>
<style>
	#<?php echo esc_attr($args['elementId']); ?>{
		display: none;
	}

	.lpc-modal .lpc-lib-modal .lpc-lib-modal-content{
		max-width: 500px !important;
		min-width: 300px !important;
		max-height: 200px;
	}

	#feedback_prompt_container{
		text-align: center;
	}

	#feedback_prompt_message{
		text-align: left;
		margin-bottom: 2rem;
	}

	#lpc-feedback-close-button{
		margin-right: 1rem;
	}
</style>

<script>
    jQuery(function () {
        setTimeout(function () {
            const nonceParam = <?php echo wp_json_encode(InitAdmin::NONCE_DISMISS_FEEDBACK); ?>;
            const nonce = <?php echo wp_json_encode(wp_create_nonce(InitAdmin::NONCE_NAME_DISMISS_FEEDBACK)); ?>;

            jQuery('#<?php echo esc_attr($args['elementId']); ?>').trigger('click');
            jQuery('#lpc-feedback-close-button').on('click', function () {
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'lpc_feedback_dismissed',
                        [nonceParam]: nonce
                    }
                });
                jQuery('.modal-close').trigger('click');
            });
        }, 1000);
    });
</script>
