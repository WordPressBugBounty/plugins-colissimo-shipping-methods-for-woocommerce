<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.

defined('ABSPATH') || die('Restricted Access');
$lpc_slip_table = $args['table'] ?? [];
$get_args       = $args['request'] ?? [];
require_once __DIR__ . DS . 'ListingTabs.php';
?>

<div class="wrap">
    <?php
    $lpc_slip_table->prepare_items($get_args);
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
        $lpc_slip_table->display();
        ?>
	</form>
</div>
