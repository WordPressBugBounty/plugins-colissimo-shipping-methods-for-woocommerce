<?php
defined('ABSPATH') || die('Restricted Access');
?>
<a id="<?php echo esc_attr($args['elementId']); ?>"
   href=""
   data-lpc-template="<?php echo esc_attr($args['templateId']); ?>"
    <?php
    if (!empty($args['callback'])) {
        echo 'data-lpc-callback="' . esc_attr($args['callback']) . '"';
    }
    ?>
>
    <?php echo esc_html($args['content']); ?>
</a>
