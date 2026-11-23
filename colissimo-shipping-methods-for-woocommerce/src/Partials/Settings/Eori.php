<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');

?>
<tr class="lpc_eori_info">
	<td class="forminp forminp-eori">
		<p>
			<?php echo esc_html__('The EORI number for a French company consists of the code “FR” followed by the 9-digit SIREN number.',
			                      'colissimo-shipping-methods-for-woocommerce'); ?>
		</p>
		<p>
			<?php printf(
			// translators: %s is a link whose text is "click here".
					esc_html__('To get your EORI SIREN number: %s', 'colissimo-shipping-methods-for-woocommerce'),
					'<a target="_blank" href="https://www.douane.gouv.fr/service-en-ligne/demande-dautorisation-douaniere-et-fiscale-soprano">' . esc_html__('click here',
					                                                                                                                                         'colissimo-shipping-methods-for-woocommerce') . '</a>'
			); ?>
		</p>
		<p>
			<?php echo esc_html__('The EORI SIREN number for the shipper is required:', 'colissimo-shipping-methods-for-woocommerce'); ?>
		</p>
		<p>
		<ul>
			<li>
				<?php echo esc_html__('for international exports using DDP.', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</li>
			<li>
				<?php echo esc_html__('for DAP and DDP to the United States.', 'colissimo-shipping-methods-for-woocommerce'); ?>
			</li>
			<li>
				<?php echo esc_html__('to overseas territories for packages valued at 1,000€ or more; the recipient\'s EORI number is required for imports into overseas territories.',
				                      'colissimo-shipping-methods-for-woocommerce'); ?>
			</li>
		</ul>
		</p>
		<p>
			<?php printf(
			// translators: %1$s and %2$s are links whose text is "here".
					esc_html__('More regulatory information %1$s and %2$s.', 'colissimo-shipping-methods-for-woocommerce'),
					'<a target="_blank" href="https://www.douane.gouv.fr/vers-un-eori-siren-unique-et-un-portail-europeen-pour-les-autorisations-douanieres">' . esc_html__('here',
					                                                                                                                                                        'colissimo-shipping-methods-for-woocommerce') . '</a>',
					'<a target="_blank" href="https://www.economie.gouv.fr/entreprises/developper-son-entreprise/importer-et-exporter-linternational/numero-eori-lidentifiant-indispensable-pour-vos-formalites-douanieres">' . esc_html__('here',
					                                                                                                                                                                                                                       'colissimo-shipping-methods-for-woocommerce') . '</a>'
			); ?>
		</p>
	</td>
</tr>
