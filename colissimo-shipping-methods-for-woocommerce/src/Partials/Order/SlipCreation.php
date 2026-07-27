<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

defined('ABSPATH') || die('Restricted Access');
$lpc_orders_table_today = $args['table_today'] ?? [];
$lpc_orders_table_all   = $args['table_all'] ?? [];
$get_args               = $args['request'] ?? [];
require_once __DIR__ . DS . 'ListingTabs.php';
?>

<div class="wrap">
    <?php
    $lpc_orders_table_today->prepare_items($get_args);
    $lpc_orders_table_all->prepare_items($get_args);
    $lpc_orders_table_today->displayHeaders();
    ?>
	<form method="get">
        <?php
        if (isset($get_args['page'])) {
            ?>
			<input
					type="hidden"
					name="page"
					value="<?php echo esc_attr(sanitize_text_field(wp_unslash($get_args['page']))); ?>" />
        <?php } ?>
	</form>
	<form method="post">
		<input type="hidden" name="action">
        <?php
        if (isset($get_args['page'])) {
            ?>
			<input
					type="hidden"
					name="page"
					value="<?php echo esc_attr(sanitize_text_field(wp_unslash($get_args['page']))); ?>" />
            <?php
        }
        ?>
		<div class="lpc_slip_creation_table_container">
			<div class="lpc_slip_creation_table_header">
				<h2><?php esc_html_e("Today's parcels", 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="lpc_slip_creation_table_listing" id="lpc_slip_creation_table_listing_today">
                <?php $lpc_orders_table_today->display(); ?>
			</div>
		</div>
		<div class="lpc_slip_creation_table_container">
			<div class="lpc_slip_creation_table_header">
				<h2><?php esc_html_e('Other parcels', 'colissimo-shipping-methods-for-woocommerce'); ?></h2>
				<span class="dashicons dashicons-arrow-down-alt2"></span>
			</div>
			<div class="lpc_slip_creation_table_listing" id="lpc_slip_creation_table_listing_other">
                <?php $lpc_orders_table_all->display(); ?>
			</div>
		</div>
	</form>
</div>
