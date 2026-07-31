<?php
if (!defined('ABSPATH')) { exit; }
$wecantrack_storage = json_decode(get_option('wecantrack_storage'), true);

$wecantrack_referrer_cookie_status = get_option('wecantrack_referrer_cookie_status') ? true : false;
$wecantrack_ssl_verify = empty($wecantrack_storage['disable_ssl']);
$wecantrack_include_script = !isset($wecantrack_storage['include_script']) || $wecantrack_storage['include_script'] == true;

$wecantrack_api_key = get_option('wecantrack_api_key');
$wecantrack_website_options = get_option('wecantrack_website_options');
$wecantrack_connected = !empty($wecantrack_api_key) && is_array($wecantrack_website_options) && !empty($wecantrack_website_options['url']);
$wecantrack_tracking_enabled = get_option('wecantrack_plugin_status') ? true : false;

// Current script version of the resolved website (0 = unknown / not resolved yet).
$wecantrack_script_version = is_array($wecantrack_website_options) ? (int) ($wecantrack_website_options['script_version'] ?? 0) : 0;
$wecantrack_show_script_version = in_array($wecantrack_script_version, [1, 2], true) && $wecantrack_api_key;
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <?php require WECANTRACK_PATH . '/views/partials/header.php'; ?>

        <h1><?php echo esc_html__('Advanced', 'wecantrack'); ?></h1>

        <form id="wecantrack_ajax_form" data-ajax="true" method="post">
            <input type="hidden" name="action" value="wecantrack_advanced_settings_response">
            <input type="hidden" name="wecantrack_form_nonce" value="<?php echo esc_attr(wp_create_nonce('wecantrack_form_nonce')); ?>">

            <table class="form-table" role="presentation">
                <tbody>

                <tr class="wecantrack-include-script">
                    <th scope="row"><?php echo esc_html__('Include WCT Script', 'wecantrack'); ?></th>
                    <td>
                        <label class="wecantrack-toggle">
                            <input type="hidden" name="wecantrack_include_script" value="0">
                            <input type="checkbox" name="wecantrack_include_script" value="1" <?php checked($wecantrack_include_script, true) ?>>
                            <span class="wecantrack-toggle-track" aria-hidden="true"></span>
                            <span class="wecantrack-toggle-text"><?php echo esc_html__('Include the wct.js script automatically', 'wecantrack'); ?></span>
                        </label>
                        <p class="description"><?php echo esc_html__('The wct.js file will be included automatically by the plugin, or you can choose to disable this option and include it yourself.', 'wecantrack'); ?></p>
                    </td>
                </tr>

                <tr class="wecantrack-referrer-cookie">
                    <th scope="row"><?php echo esc_html__('Use WCT referrer cookie', 'wecantrack'); ?></th>
                    <td>
                        <label class="wecantrack-toggle">
                            <input type="hidden" name="wecantrack_referrer_cookie_status" value="0">
                            <input type="checkbox" name="wecantrack_referrer_cookie_status" value="1" <?php checked($wecantrack_referrer_cookie_status, true) ?>>
                            <span class="wecantrack-toggle-track" aria-hidden="true"></span>
                            <span class="wecantrack-toggle-text"><?php echo esc_html__('Set referrer cookies to improve Clickout URL coverage', 'wecantrack'); ?></span>
                        </label>
                        <p class="description">We use the cookie `_wct_http_referrer_1` and `_wct_http_referrer_2` to increase the coverage for populating the Clickout URL. <b>Note: This cookie gets set on the server side, if you have caching in place that checks on cookie values please filter these cookies out or disable this setting. This setting is only useful for specific use cases. Please only use it if your Clickout URL coverage is low. When in doubt please reach out to our support.</b></p>
                    </td>
                </tr>

                <tr class="wecantrack-verify-ssl">
                    <th scope="row"><?php echo esc_html__('Verify SSL', 'wecantrack'); ?></th>
                    <td>
                        <label class="wecantrack-toggle">
                            <?php // Checked = verify SSL = disable_ssl 0. PHP keeps the last posted value, so the hidden "1" only wins when the checkbox is off. ?>
                            <input type="hidden" name="wecantrack_ssl_disabled" value="1">
                            <input type="checkbox" name="wecantrack_ssl_disabled" value="0" <?php checked($wecantrack_ssl_verify, true) ?>>
                            <span class="wecantrack-toggle-track" aria-hidden="true"></span>
                            <span class="wecantrack-toggle-text"><?php echo esc_html__('Verify SSL certificates on API requests', 'wecantrack'); ?></span>
                        </label>
                        <p class="description"><?php echo esc_html__('Verify SSL when making WCT API Request in the backend. If you have certification issues that you or your host is not able to solve you may set this to disable as a workaround.', 'wecantrack'); ?></p>
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit wecantrack-submit-row">
                <input type="submit" name="submit" id="submit-verified" class="button button-primary" value="<?php echo esc_html__('Update & Save', 'wecantrack'); ?>">
                <span id="wecantrack_save_feedback" class="wecantrack-feedback wecantrack-feedback-inline"></span>
            </p>
        </form>

        <?php if ($wecantrack_show_script_version) : ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr class="wecantrack-script-version">
                <th scope="row">
                    <label for=""><?php echo esc_html__('Tracking script version', 'wecantrack'); ?></label>
                </th>

                <td>
                    <p class="wecantrack-script-version-status">
                        <?php if ($wecantrack_script_version === 2) : ?>
                            <span class="wecantrack-badge wecantrack-badge-new"><?php echo esc_html__('v2 · latest', 'wecantrack'); ?></span>
                            <?php echo esc_html__('You are using the new wecantrack script.', 'wecantrack'); ?>
                        <?php else : ?>
                            <span class="wecantrack-badge wecantrack-badge-legacy"><?php echo esc_html__('v1 · legacy', 'wecantrack'); ?></span>
                            <?php echo esc_html__('You are using the legacy wecantrack script.', 'wecantrack'); ?>
                        <?php endif; ?>
                    </p>
                    <p class="wecantrack-script-version-actions">
                        <button type="button" class="button <?php echo $wecantrack_script_version === 1 ? 'button-primary' : ''; ?>" id="wecantrack_script_version_button"
                                data-version="<?php echo $wecantrack_script_version === 2 ? 1 : 2; ?>"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('wecantrack_form_nonce')); ?>">
                            <?php echo $wecantrack_script_version === 2
                                ? esc_html__('Revert to the legacy script (v1)', 'wecantrack')
                                : esc_html__('Upgrade to the new script (v2)', 'wecantrack'); ?>
                        </button>
                    </p>
                    <p class="description"><?php echo esc_html__('This changes the tracking script version for this website in your wecantrack account and takes effect immediately. If you run into issues with the new script, revert here and contact support@wecantrack.com.', 'wecantrack'); ?></p>
                    <div id="wecantrack_script_version_feedback" class="wecantrack-script-version-feedback"></div>
                </td>
            </tr>
            </tbody>
        </table>
        <?php endif; ?>

        <p class="wecantrack-footer-note">
            <?php echo esc_html__('The deprecated afflink redirect-through parameter is permanently disabled for security reasons. Contact support@wecantrack.com for more information.', 'wecantrack'); ?>
        </p>

        <?php require WECANTRACK_PATH . '/views/partials/footer.php'; ?>
    </div>
</div>
