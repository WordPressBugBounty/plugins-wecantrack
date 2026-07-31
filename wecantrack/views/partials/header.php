<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared page header: logo + version tag on the left, connection pill on the right.
 * Expects $wecantrack_connected (bool) to be set by the including view, and optionally
 * $wecantrack_tracking_enabled (bool, default true) to distinguish a verified connection
 * with tracking switched off from a fully active one.
 */
$wecantrack_connected = isset($wecantrack_connected) ? (bool) $wecantrack_connected : false;
$wecantrack_tracking_enabled = isset($wecantrack_tracking_enabled) ? (bool) $wecantrack_tracking_enabled : true;

if (!$wecantrack_connected) {
    $wecantrack_pill_class = 'wecantrack-pill-disconnected';
    $wecantrack_pill_text = __('Not connected', 'wecantrack');
} elseif (!$wecantrack_tracking_enabled) {
    $wecantrack_pill_class = 'wecantrack-pill-paused';
    $wecantrack_pill_text = __('Connected · tracking off', 'wecantrack');
} else {
    $wecantrack_pill_class = 'wecantrack-pill-connected';
    $wecantrack_pill_text = __('Connected', 'wecantrack');
}
?>
<div class="wecantrack-header">
    <div class="wecantrack-header-brand">
        <img src="<?php echo esc_url(WECANTRACK_URL . '/images/wct-logo-normal.svg') ?>" alt="wecantrack">
    </div>
    <span id="wecantrack_connection_pill"
          class="wecantrack-pill <?php echo esc_attr($wecantrack_pill_class); ?>"
          data-lang-connected="<?php echo esc_attr__('Connected', 'wecantrack'); ?>"
          data-lang-disconnected="<?php echo esc_attr__('Not connected', 'wecantrack'); ?>"
          data-lang-tracking-off="<?php echo esc_attr__('Connected · tracking off', 'wecantrack'); ?>">
        <?php echo esc_html($wecantrack_pill_text); ?>
    </span>
</div>
