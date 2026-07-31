<?php
if (!defined('ABSPATH')) { exit; }
//nonce
$wecantrack_nonce = wp_create_nonce('wecantrack_form_nonce');

//plugins status
$wecantrack_plugin_status = get_option('wecantrack_plugin_status') ? true : false;

$wecantrack_api_key = get_option('wecantrack_api_key');
$wecantrack_website_options = get_option('wecantrack_website_options');
$wecantrack_website_url = is_array($wecantrack_website_options) ? ($wecantrack_website_options['url'] ?? '') : '';
$wecantrack_property_id = is_array($wecantrack_website_options) ? ($wecantrack_website_options['property_id'] ?? '') : '';
$wecantrack_script_version = is_array($wecantrack_website_options) ? (int) ($wecantrack_website_options['script_version'] ?? 0) : 0;

// "Connected" means we have a key and previously resolved a website with it.
$wecantrack_connected = !empty($wecantrack_api_key) && !empty($wecantrack_website_url);
$wecantrack_tracking_enabled = $wecantrack_plugin_status;

// Offer the script upgrade when the resolved website is still on the legacy (v1) script.
$wecantrack_show_script_upgrade = $wecantrack_script_version === 1 && $wecantrack_api_key;

$wecantrack_masked_key = $wecantrack_api_key
    ? substr($wecantrack_api_key, 0, 4) . str_repeat('•', 8) . substr($wecantrack_api_key, -4)
    : '';
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <?php require WECANTRACK_PATH . '/views/partials/header.php'; ?>

        <?php if (class_exists('ThirstyAffiliates')) : ?>
            <div class="wecantrack-inline-note">
                <?php echo esc_html__('If you\'re making use of Thirsty Affiliates, please make sure to deactivate “Enable Enhanced Javascript Redirect on Frontend” under Link Appearance.', 'wecantrack'); ?>
            </div>
        <?php endif; ?>

        <form id="wecantrack_ajax_form" data-ajax="true" method="post">
            <input type="hidden" name="action" value="wecantrack_form_response">
            <input type="hidden" id="wecantrack_submit_type" name="wecantrack_submit_type" value="verify">
            <input type="hidden" name="wecantrack_form_nonce" value="<?php echo esc_attr($wecantrack_nonce) ?>">

            <!-- ── Connection card ──────────────────────────────── -->
            <div class="wecantrack-connection-card">
                <h2 class="wecantrack-card-title"><?php echo esc_html__('Connection', 'wecantrack'); ?></h2>

                <div id="wecantrack_connection_summary" class="wecantrack-connection-summary <?php echo $wecantrack_connected ? '' : 'hidden'; ?>">
                    <div class="wecantrack-connection-row">
                        <span class="wecantrack-connection-label"><?php echo esc_html__('Website', 'wecantrack'); ?></span>
                        <span id="wecantrack_connected_website"><?php echo esc_html($wecantrack_website_url); ?></span>
                        <code id="wecantrack_property_id" class="wecantrack-property-id <?php echo $wecantrack_property_id ? '' : 'hidden'; ?>"><?php echo esc_html($wecantrack_property_id); ?></code>
                    </div>
                    <div class="wecantrack-connection-row" id="wecantrack_api_key_row">
                        <span class="wecantrack-connection-label"><?php echo esc_html__('API key', 'wecantrack'); ?></span>
                        <code id="wecantrack_masked_key"><?php echo esc_html($wecantrack_masked_key); ?></code>
                        <a href="#" id="wecantrack_change_key"><?php echo esc_html__('Change', 'wecantrack'); ?></a>
                    </div>
                </div>

                <div id="wecantrack_connection_setup" class="wecantrack-connection-setup <?php echo $wecantrack_connected ? 'hidden' : ''; ?>">
                    <label for="wecantrack_api_key" class="wecantrack-connection-label"><?php echo esc_html__('API Key', 'wecantrack'); ?></label>
                    <div class="wecantrack-connection-controls">
                        <input id="wecantrack_api_key" name="wecantrack_api_key" type="text" placeholder="<?php echo esc_html__('Enter API Key', 'wecantrack') ?>" value="<?php echo esc_attr($wecantrack_api_key) ?>" required="" autocomplete="off" autocorrect="off" spellcheck="false">
                        <input type="submit" name="submit" id="submit-verify" class="button button-primary" value="<?php echo esc_html__('Verify key', 'wecantrack') ?>">
                    </div>
                    <p class="description">
                        <a target="_blank" href="https://app.wecantrack.com/user/integrations/wecantrack/api">
                            <?php echo esc_html__('Retrieve API Key from your wecantrack account', 'wecantrack'); ?>
                        </a>
                        &nbsp;·&nbsp;
                        <a target="_blank" href="https://app.wecantrack.com/register">
                            <?php echo esc_html__('No account yet? Create one here', 'wecantrack'); ?>
                        </a>
                    </p>
                </div>

                <div class="wecantrack-prerequisites hidden">
                    <p class="wecantrack-preq-network-account"><i class="dashicons dashicons-no"></i> <span></span></p>
                    <p class="wecantrack-preq-feature"><i class="dashicons dashicons-no"></i> <span></span></p>
                </div>

                <div class="wecantrack-website-override hidden">
                    <label for="wecantrack_website_override" class="wecantrack-connection-label"><?php echo esc_html__('Select your website', 'wecantrack'); ?></label>
                    <select id="wecantrack_website_override" name="wecantrack_website_override" data-selected="<?php echo esc_attr(get_option('wecantrack_website_override')); ?>"></select>
                    <p class="description">
                        <?php echo esc_html__('Your site domain was not found in your account. Choose which wecantrack website to use for this install (useful for staging environments).', 'wecantrack'); ?>
                    </p>
                </div>

                <div id="wecantrack_verify_feedback" class="wecantrack-feedback"></div>
            </div>

            <!-- ── Settings (locked until the connection is verified) ── -->
            <fieldset id="wecantrack_settings_fieldset" class="wecantrack-settings-fieldset" <?php echo $wecantrack_connected ? '' : 'disabled'; ?>>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr class="wecantrack-plugin-status">
                        <th scope="row"><?php echo esc_html__('Tracking', 'wecantrack'); ?></th>
                        <td>
                            <label class="wecantrack-toggle">
                                <input type="hidden" name="wecantrack_plugin_status" value="0">
                                <input type="checkbox" id="wecantrack_plugin_status" name="wecantrack_plugin_status" value="1" <?php checked($wecantrack_plugin_status, true); ?>>
                                <span class="wecantrack-toggle-track" aria-hidden="true"></span>
                                <span class="wecantrack-toggle-text"><?php echo esc_html__('Enable tracking on this website', 'wecantrack'); ?></span>
                            </label>
                            <p class="description">
                                <?php echo esc_html__('Enable or disable the tracking plugin entirely.', 'wecantrack'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr class="wecantrack-session-enabler <?php echo $wecantrack_plugin_status ? 'hidden' : ''; ?>">
                        <th scope="row">
                            <label for="wecantrack_session_enabler"><?php echo esc_html__('Enable plugin when URL contains', 'wecantrack'); ?></label>
                        </th>
                        <td>
                            <input name="wecantrack_session_enabler" type="text" id="wecantrack_session_enabler" placeholder="<?php echo esc_html__('e.g. ?wct=on', 'wecantrack'); ?>" value="<?php echo esc_attr(get_option('wecantrack_session_enabler')) ?>" style="width:300px;" autocomplete="off">
                            <p class="description">
                                <?php echo esc_html__('Place a URL, slug or URL parameter for which our plugin will be functional for the user browser session only.', 'wecantrack'); ?>
                                <br />
                                <?php echo esc_html__('This works with sessions when it detects the value in the URL, use of sessions may or may not conflict with server based cache services.') ?>
                            </p>
                        </td>
                    </tr>
                    </tbody>
                </table>

                <p class="submit wecantrack-submit-row">
                    <input type="submit" name="submit" id="submit-verified" class="button button-primary" value="<?php echo esc_html__('Update & Save', 'wecantrack'); ?>">
                    <span id="wecantrack_save_feedback" class="wecantrack-feedback wecantrack-feedback-inline"></span>
                </p>
            </fieldset>
        </form>

        <?php if ($wecantrack_show_script_upgrade) : ?>
        <div class="wecantrack-script-version-card" id="wecantrack_script_upgrade_panel">
            <h2>
                <?php echo esc_html__('A new tracking script is available', 'wecantrack'); ?>
                <span class="wecantrack-badge wecantrack-badge-legacy"><?php echo esc_html__('v1 · legacy', 'wecantrack'); ?></span>
            </h2>
            <p>
                <?php echo esc_html__('This website still uses the legacy wecantrack script. The new script (v2) is faster and more reliable. Switching is instant and you can revert at any time under wecantrack > Advanced.', 'wecantrack'); ?>
            </p>
            <p class="wecantrack-script-version-actions">
                <button type="button" class="button button-primary" id="wecantrack_script_version_button" data-version="2" data-nonce="<?php echo esc_attr($wecantrack_nonce); ?>">
                    <?php echo esc_html__('Upgrade to the new script', 'wecantrack'); ?>
                </button>
            </p>
            <div id="wecantrack_script_version_feedback" class="wecantrack-script-version-feedback"></div>
        </div>
        <?php endif; ?>

        <p class="wecantrack-footer-note"><b>If you enjoy using our software, could you leave us a rating and a review <a target="_blank" href="https://wordpress.org/support/plugin/wecantrack/reviews/?filter=5#new-post">here</a>? This would really be helpful for us! :)</b></p>
        <p class="wecantrack-footer-note">
            <?php echo esc_html__("If you're experiencing any bugs caused by this plugin, disable the plugin and contact us at support@wecantrack.com", 'wecantrack'); ?>
        </p>

        <?php require WECANTRACK_PATH . '/views/partials/footer.php'; ?>
    </div>
</div>
