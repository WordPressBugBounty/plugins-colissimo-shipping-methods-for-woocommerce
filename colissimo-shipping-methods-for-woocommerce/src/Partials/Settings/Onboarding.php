<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
$faciliteUrl      = 'https://www.colissimo.entreprise.laposte.fr/contrat-facilite';
$privilegeUrl     = 'https://www.colissimo.entreprise.laposte.fr/contrat-privilege';
$colissimoWebsite = 'https://www.colissimo.entreprise.laposte.fr/';

$originAddress     = admin_url('admin.php?page=wc-settings&tab=lpc&section=main#lpc_pwd_webservices');
$labelFormat       = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_calculate_shipping_before_taxes');
$printerConnection = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_zpldpl_labels_port');
$packagingWeight   = admin_url('admin.php?page=wc-settings&tab=lpc&section=label#lpc_using_insurance_inward');
$customsOptions    = admin_url('admin.php?page=wc-settings&tab=lpc&section=custom');
$shopAddress       = admin_url('admin.php?page=wc-settings&tab=general');
$shippingTab       = admin_url('admin.php?page=wc-settings&tab=shipping');
$colissimoListing  = admin_url('admin.php?page=wc_colissimo_view');
$videoTutorials    = admin_url('admin.php?page=wc-settings&tab=lpc&section=video');
?>
<tr>
	<td class="forminp forminp-onboarding">
		<p>
            <?php echo wp_kses_post(__('<u>Not yet registered on Colissimo?</u> Here is how to do it:', 'colissimo-shipping-methods-for-woocommerce')); ?>
		</p>
		<p>
			- <?php
            printf(
                // translators: %s is a link to the contract.
                esc_html__('Choose %s best matching your needs.', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($args['contractTypes']) . '">' . esc_html__('the contract', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>
			- <?php
            printf(
                // translators: %1$s is the Colissimo Facilité option name, %2$s is a link to the commercial contact form.
                esc_html__('To register for the %1$s option, fill %2$s to create an account and receive your ids within a few days.', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($faciliteUrl) . '">Colissimo Facilité</a>',
                '<a target="_blank" href="' . esc_url($args['faciliteForm']) . '">' . esc_html__('this commercial contact form', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>
			- <?php
            printf(
                // translators: %1$s is the Colissimo Privilège option name, %2$s is a link to the commercial contact form.
                esc_html__('To register for the %1$s option, fill %2$s to be contacted by a sales representative.', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($privilegeUrl) . '">Colissimo Privilège</a>',
                '<a target="_blank" href="' . esc_url($args['privilegeForm']) . '">' . esc_html__('this commercial contact form', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<br>
		<p>
            <?php echo wp_kses_post(__('<u>Already registered on Colissimo?</u> Here is how to configure your plugin:', 'colissimo-shipping-methods-for-woocommerce')); ?>
		</p>
		<br>
		<b>1. <?php esc_html_e('I associate my account to the Colissimo plugin', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p>
            <?php
            esc_html_e('To connect the plugin, you must enter your Colissimo application key in the "General" tab of this plugin.', 'colissimo-shipping-methods-for-woocommerce');
            echo '<br />';
            printf(
                // translators: %s is a link to the Colissimo Box website.
                esc_html__('This application key must be generated from your account on %s on your user edit page.', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($colissimoWebsite) . '">Colissimo Box</a>'
            );
            ?>
		</p>
		<br>
		<br>
		<b>2. <?php esc_html_e('I configure my settings to simplify my shipping preparations thanks to Colissimo features', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p>- <?php
            printf(
                // translators: %s is a link to your origin address settings.
                esc_html__('Enter %s to be able to generate labels', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($originAddress) . '">' . esc_html__('your origin address', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                // translators: %s is a link to choose the label format.
                esc_html__('%s of your labels (either PDF or format for thermal printing)', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($labelFormat) . '">' . esc_html__('Choose the format', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                // translators: %s is a link to configure the connection.
                esc_html__('In case of thermal printing, %s to your printer', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($printerConnection) . '">' . esc_html__('configure the connection', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                // translators: %s is a link to the packaging weight setting.
                esc_html__('Enter %s for your parcels', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($packagingWeight) . '">' . esc_html__('the packaging weight', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>- <?php
            printf(
                // translators: %s is a link to your customs formalities.
                esc_html__('Enter %s for your shipments outside the European Union', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($customsOptions) . '">' . esc_html__('your customs formalities', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<br>
		<br>
		<b>3. <?php esc_html_e('I make sure the WooCommerce settings are complete', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p>- <?php
            printf(
                // translators: %s is a link to the shop address settings.
                esc_html__('%s must be complete', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($shopAddress) . '">' . esc_html__('The shop address', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>- <?php esc_html_e('All the products must have a weight under their "Shipping" section', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<p>- <?php esc_html_e('The products may have a custom HS code and country of manufacture under their "Attributes" section', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<br>
		<br>
		<b>4. <?php esc_html_e('I configure my shipping rates', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p>- <?php
            printf(
                // translators: %s is a link to the "Shipping" tab.
                esc_html__('Head to %s', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($shippingTab) . '">' . esc_html__('the "Shipping" tab', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		<p>- <?php esc_html_e('The Colissimo plugin automatically creates its zones based on shipping costs rules', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<p>- <?php esc_html_e('Activate the shipping methods you want to show to your customers', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<p>- <?php esc_html_e('You can customize the titles and shipping prices of the delivery solutions for your customers', 'colissimo-shipping-methods-for-woocommerce'); ?></p>
		<br>
		<p>
            <?php
            esc_html_e(
                'Beware: Colissimo doesn\'t handle parcels over 30kg, including packaging weight. Be careful about the weight unit and when you modify the weight ranges.',
                'colissimo-shipping-methods-for-woocommerce'
            );
            ?>
		</p>
		<br>
		<br>
		<b><?php esc_html_e('Everything is ready!', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p><?php
            printf(
                // translators: %s is a link to the WooCommerce > Colissimo orders page.
                esc_html__('We have created a special page just for you, to view your new orders in %s', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($colissimoListing) . '">WooCommerce > Colissimo</a>'
            );
            ?>
			<br>
			<br>
			<b><?php esc_html_e('Need help?', 'colissimo-shipping-methods-for-woocommerce'); ?></b>
		<p>
            <?php
            printf(
                // translators: %s is a link to the video tutorials page.
                esc_html__('You will find video tutorials on %s.', 'colissimo-shipping-methods-for-woocommerce'),
                '<a target="_blank" href="' . esc_url($videoTutorials) . '">' . esc_html__('this page', 'colissimo-shipping-methods-for-woocommerce') . '</a>'
            );
            ?>
		</p>
		<p>
            <?php
            printf(
                // translators: %1$s is the support phone number link, %2$s is the technical support email link.
                esc_html__('You can also contact the Colissimo support by phone at %1$s or the technical support by email at %2$s', 'colissimo-shipping-methods-for-woocommerce'),
                '<a href="tel:' . esc_attr(LPC_CONTACT_PHONE) . '">' . esc_html(LPC_CONTACT_PHONE) . '</a>',
                '<a href="mailto:' . esc_attr(LPC_CONTACT_EMAIL) . '">' . esc_html(LPC_CONTACT_EMAIL) . '</a>'
            );
            ?>
		</p>
	</td>
</tr>
