<?php

use Colissimo\Helpers\Helper;

defined('ABSPATH') || die('Restricted Access');
?>
<?php wp_head(); ?>
<div class="wrap">
	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">
            <?php Helper::renderPartial($args['name'], $args['args']); ?>
		</main>
	</div>
</div>
<?php wp_footer(); ?>
