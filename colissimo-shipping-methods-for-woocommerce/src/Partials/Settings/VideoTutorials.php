<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Template partial included within Helper::renderPartial(); its variables are local to the include scope, not true globals.
defined('ABSPATH') || die('Restricted Access');
$videos = [
    [
        'link'  => 'fgM73ZOmBrg',
        'title' => __('Plugin overview', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'R_cNuZEdVX0',
        'title' => __('Carrier configuration', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'sPBZk1-9IpE',
        'title' => __('Order postage', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'LhcFeNDckzM',
        'title' => __('PickUp point order', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'u0XqhTMJekg',
        'title' => __('CN23 order', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'CagivW3GyqU',
        'title' => __('Multi-parcels', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'HskD5PoG9zc',
        'title' => __('Multi-parcels OM', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'UaAShJFCkB8',
        'title' => __('Parcel tracking', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => '5Xfaexhdqtg',
        'title' => __('DDP', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => '48eEexMC_Vo',
        'title' => __('Customs 2021', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => 'lfHFKScib3E',
        'title' => __('Deposit of bordereau', 'colissimo-shipping-methods-for-woocommerce'),
    ],
    [
        'link'  => '8ftc0L2s4qg',
        'title' => __('Thermal printing kit', 'colissimo-shipping-methods-for-woocommerce'),
    ],
]
?>
<tr>
	<td colspan="2">
		<style>
			#lpc_videos{
				width: 100%;
				text-align: center;
			}

			.lpc_video_tutorial{
				display: inline-block;
				margin: 10px;
				padding: 1rem;
				background-color: #dfdfdf;
			}

			.lpc_video_tutorial iframe{
				border: none;
			}

			.lpc_video_label{
				text-align: center;
				font-weight: bold;
				padding: 1rem;
			}

			.button-primary.woocommerce-save-button{
				display: none;
			}
		</style>
		<div id="lpc_videos">
            <?php
            $i = 1;
            foreach ($videos as $video) {
                ?>
				<div class="lpc_video_tutorial">
					<iframe width="500"
					        height="280"
					        src="https://www.youtube.com/embed/<?php echo esc_attr($video['link']); ?>"
					        title="<?php echo esc_attr($video['title']); ?>"
					        allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					        allowfullscreen>
					</iframe>
					<div class="lpc_video_label">
                        <?php echo esc_html($i . '. ' . $video['title']); ?>
					</div>
				</div>
                <?php
                $i ++;
            }
            ?>
		</div>
	</td>
</tr>
