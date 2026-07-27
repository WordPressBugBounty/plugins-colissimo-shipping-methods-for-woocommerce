<?php
defined('ABSPATH') || die('Restricted Access');
?>
<tr valign="top">
	<th scope="row" class="titledesc">
        <?php
        // Dynamic option
        // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
        esc_html_e($args['title'], 'colissimo-shipping-methods-for-woocommerce');
        ?>
	</th>
	<td class="forminp forminp-<?php echo esc_attr($args['type']); ?>">
        <?php $args['modal']->echo_modalAndButton($args['text']); ?>
	</td>
</tr>
