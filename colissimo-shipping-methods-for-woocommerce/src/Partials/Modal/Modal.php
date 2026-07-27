<?php
// WooCommerce is loaded, using their translation for better accuracy
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<script type="text/template" id="tmpl-<?php echo esc_attr($args['templateId']); ?>">
	<div class="lpc-modal">
		<div class="lpc-lib-modal">
			<div class="lpc-lib-modal-content">
				<section class="lpc-lib-modal-main" role="main">
					<header class="lpc-lib-modal-header">
						<h1>
                            <?php
                            echo wp_kses(
                                $args['title'],
                                [
                                    'img' => [
                                        'style' => [],
                                        'src'   => [],
                                    ],
                                ]
                            );
                            ?>
						</h1>
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text"><?php echo esc_html_e('Close modal panel', 'woocommerce'); ?></span>
						</button>
					</header>
					<article class="lpc-lib-modal-article">
                        <?php echo wp_kses($args['content'], Helper::getModalAllowedHtml()); ?>
					</article>
				</section>
			</div>
			<div class="lpc-lib-modal-backdrop modal-close"></div>
		</div>
	</div>
</script>
