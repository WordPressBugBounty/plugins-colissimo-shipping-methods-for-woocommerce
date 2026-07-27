<?php
defined('ABSPATH') || die('Restricted Access');
?>
<div class="notice lpc-notice is-dismissible notice-<?php echo esc_attr($args['type']); ?>">
	<p>
        <?php
        echo wp_kses(
            $args['message'],
            [
                'a'  => [
                    'href' => [],
                    'target' => [],
                ],
                'br' => [],
            ]
        );
        ?>
	</p>
</div>
