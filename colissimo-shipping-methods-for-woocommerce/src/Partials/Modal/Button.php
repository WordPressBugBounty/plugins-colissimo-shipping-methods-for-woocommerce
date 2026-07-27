<?php
defined('ABSPATH') || die('Restricted Access');
?>
<button id="<?php echo esc_attr($args['elementId']); ?>"
		data-lpc-template="<?php echo esc_attr($args['templateId']); ?>"
		class="button"
    <?php
    if (!empty($args['callback'])) {
        echo 'data-lpc-callback="' . esc_attr($args['callback']) . '"';
    }
    ?>
>
    <?php echo esc_html($args['content']); ?>
</button>
