<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

defined('ABSPATH') || die('Restricted Access');

?>
<table cellpadding="0" cellspacing="0">
    <?php
    foreach ($args['openingDays'] as $day => $oneDay) {
        if ('00:00-00:00 00:00-00:00' === $args['relay'][$oneDay]) {
            continue;
        }
        ?>
		<tr>
			<td>
                <?php
                // Dynamic day to avoid code duplication
                // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                echo esc_html(ucfirst(__($day, 'colissimo-shipping-methods-for-woocommerce')));
                ?>
			</td>
			<td class="opening_hours">
                <?php
                echo esc_html(
                    str_replace(
                        [' ', ' - 00:00-00:00'],
                        [' - ', ''],
                        $args['relay'][$oneDay]
                    )
                );
                ?>
			</td>
		</tr>
    <?php } ?>
</table>
