<?php
//nonce
$wecantrack_nonce = wp_create_nonce('wecantrack_nonce');

//plugins status
$wecantrack_plugin_status_enabled = '';
$wecantrack_plugin_status_disabled = '';
if (get_option('wecantrack_plugin_status')) {
    $wecantrack_plugin_status_enabled = 'checked="checked"';
} else {
    $wecantrack_plugin_status_disabled = 'checked="checked"';
}
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <div class="wecantrack_hero_image">
            <img src="<?php echo WECANTRACK_URL . '/images/wct-logo-normal.svg' ?>" alt="wct-logo">
        </div>
        <h1>WeCanTrack</h1>

        <ul style="list-style: inherit; padding-left:20px;">
            <?php if (class_exists('ThirstyAffiliates')) : ?>
                <li><?php echo esc_html__('If you\'re making use of Thirsty Affiliates, please make sure to deactivate “Enable Enhanced Javascript Redirect on Frontend” under Link Appearance.', 'wecantrack'); ?></li>
            <?php endif; ?>

            <!-- 'If you are making use of Caching plugins, please make sure to exclude your redirect URLs from caching.' -->
        </ul>

        <form id="wecantrack_ajax_form" action="<?php echo WECANTRACK_PATH . '.php' ?>" method="post">
            <input type="hidden" name="action" value="wecantrack_form_response">
            <input type="hidden" id="wecantrack_submit_type" name="wecantrack_submit_type" value="verify">

            <input type="hidden" name="wecantrack_form_nonce" value="<?php echo $wecantrack_nonce ?>">

            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="wecantrack_api_key"><?php echo esc_html__('API Key', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <input name="wecantrack_api_key" type="text" id="wecantrack_api_key" placeholder="<?php esc_html__('Enter API Key', 'wecantrack') ?>" value="<?php echo get_option('wecantrack_api_key') ?>" style="width:300px;" required="" autocomplete="off">
                        <input type="submit" name="submit" id="submit-verify" class="button button-primary" value="<?php echo esc_html__('Verify key', 'wecantrack') ?>">
                        <span class="hidden dashicons dashicons-update animated-spin wecantrack_animation_rotate" style="margin-top:5px;"></span>
                        <p class="description">
                            <a target="_blank" href="https://app.wecantrack.com/user/integrations/wecantrack/api">
                                <?php echo esc_html__('Retrieve API Key from your wecantrack account', 'wecantrack'); ?>
                            </a>
                            <br />
                            <a target="_blank" href="https://app.wecantrack.com/register">
                                <?php echo esc_html__('No account yet? Create one here', 'wecantrack'); ?>
                            </a>
                        </p>
                    </td>
                </tr>

                <tr class="wecantrack-prerequisites hidden">
                    <th scope="row">
                        <label for="wecantrack_api_key"><?php echo esc_html__('Requirements', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <p class="wecantrack-preq-network-account"><i class="dashicons"></i> <span></span></p>
                        <p class="wecantrack-preq-feature"><i class="dashicons"></i> <span></span></p>
                        <p class="description">
                            <?php echo esc_html__('In order to continue with the setup all requirements have to be met', 'wecantrack'); ?>
                        </p>
                    </td>
                </tr>

                <tr class="wecantrack-plugin-status hidden">
                    <th scope="row"><?php echo esc_html__('Plugin status', 'wecantrack'); ?></th>
                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_plugin_status" type="radio" value="1" <?php echo $wecantrack_plugin_status_enabled ?>>
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                <br />
                                <label>
                                    <input name="wecantrack_plugin_status" type="radio" value="0" <?php echo $wecantrack_plugin_status_disabled ?>>
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <tr class="wecantrack-session-enabler hidden">
                    <th scope="row">
                        <label for="wecantrack_session_enabler"><?php echo esc_html__('Enable plugin when URL contains', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <input name="wecantrack_session_enabler" type="text" id="wecantrack_session_enabler" placeholder="<?php echo esc_html__('e.g. ?wct=on', 'wecantrack'); ?>" value="<?php echo get_option('wecantrack_session_enabler') ?>" style="width:300px;" autocomplete="off">
                        <p class="description">
                            <?php echo esc_html__('Place a URL, slug or URL parameter for which our plugin will be functional for the user browser session only.', 'wecantrack'); ?>
                            <br />
                            <?php echo esc_html__('This works with sessions when it detects the value in the URL, use of sessions may or may not conflict with server based cache services.') ?>
                        </p>
                    </td>
                </tr>

                </tbody>
            </table>

            <p class="submit hidden">
                <input type="submit" name="submit" id="submit-verified" class="button button-primary" value="<?php echo esc_html__('Update & Save', 'wecantrack'); ?>">
            </p>

            <div id="wecantrack_form_feedback_top"></div>
        </form>
        <b style="padding-left: 10px">If you enjoy using our software, could you leave us a rating and a review <a target="_blank" href="https://wordpress.org/support/plugin/wecantrack/reviews/?filter=5#new-post">here</a>? This would really be helpful for us! :)</b>
        <table class="form-table" role="presentation">
            <tbody>
                <tr class="wecantrack-plugin-status hidden">
                    <td>
                        <p class="description">
                            <?php echo esc_html__("If you're experiencing any bugs caused by this plugin, disable the plugin and contact us at support@wecantrack.com", 'wecantrack'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>