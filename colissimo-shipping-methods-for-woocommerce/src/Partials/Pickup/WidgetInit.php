<?php
defined('ABSPATH') || die('Restricted Access');
?>
<script type="text/javascript">
    window.lpc_widget_info = JSON.parse(<?php echo wp_json_encode($args['widgetInfo']); ?>);
</script>
