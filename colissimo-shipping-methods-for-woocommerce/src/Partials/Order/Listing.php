<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

defined('ABSPATH') || die('Restricted Access');
$lpc_orders_table = $args['table'] ?? [];
$get_args         = $args['request'] ?? [];
wp_nonce_field('wc_colissimo_view');
require_once __DIR__ . DS . 'ListingTabs.php';
?>
<div class="wrap">
    <?php
    $lpc_orders_table->prepare_items($get_args);
    $lpc_orders_table->displayHeaders();
    ?>
	<form method="get">
        <?php
        if (isset($get_args['page'])) {
            ?>
			<input
					type="hidden"
					name="page"
					value="<?php echo esc_attr(sanitize_text_field(wp_unslash($get_args['page']))); ?>" />
            <?php
        }
        $lpc_orders_table->search_box('search', 'search_id'); ?>
	</form>
	<form method="post">
        <?php
        if (isset($get_args['page'])) {
            ?>
			<input
					type="hidden"
					name="page"
					value="<?php echo esc_attr(sanitize_text_field(wp_unslash($get_args['page']))); ?>" />
            <?php
        }
        $lpc_orders_table->display();
        ?>
	</form>
</div>
