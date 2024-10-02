<?php
//nonce
$wecantrack_nonce = wp_create_nonce('wecantrack_nonce');
$wecantrack_storage = json_decode(get_option('wecantrack_storage'), true);

//plugins status
$wecantrack_referrer_cookie_enabled = $wecantrack_referrer_cookie_disabled = '';

if (get_option('wecantrack_referrer_cookie_status')) {
    $wecantrack_referrer_cookie_enabled = 'checked="checked"';
} else {
    $wecantrack_referrer_cookie_disabled = 'checked="checked"';
}

$wecantrack_ssl_status_enabled = empty($wecantrack_storage['disable_ssl']) ? 'checked="checked"' : null;
$wecantrack_ssl_status_disabled = !empty($wecantrack_storage['disable_ssl']) ? 'checked="checked"' : null;

if (! isset($wecantrack_storage['can_redirect_through_parameter']) || $wecantrack_storage['can_redirect_through_parameter'] == true) {
    $wecantrack_can_redirect_through_parameter_enabled = 'checked="checked"';
    $wecantrack_can_redirect_through_parameter_disabled = null;
} else {
    $wecantrack_can_redirect_through_parameter_enabled = null;
    $wecantrack_can_redirect_through_parameter_disabled = 'checked="checked"';
}

if (isset($wecantrack_storage['include_script'])) {
    if ($wecantrack_storage['include_script'] == true) {
        $wecantrack_include_script_enabled = 'checked="checked"';
        $wecantrack_include_script_disabled = null;
    } else {
        $wecantrack_include_script_enabled = null;
        $wecantrack_include_script_disabled = 'checked="checked"';
    }
} else {
    $wecantrack_include_script_enabled = 'checked="checked"';
    $wecantrack_include_script_disabled = null;
}
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <div class="wecantrack_hero_image">
            <img src="<?php echo WECANTRACK_URL . '/images/wct-logo-normal.svg' ?>" alt="wct-logo">
        </div>
        <h1>WeCanTrack > Settings</h1>

        <form id="wecantrack_ajax_form" action="<?php echo WECANTRACK_PATH . '.php' ?>" method="post">
            <input type="hidden" name="action" value="wecantrack_advanced_settings_response">
            <input type="hidden" name="wecantrack_form_nonce" value="<?php echo $wecantrack_nonce ?>">

            <table class="form-table" role="presentation">
                <tbody>

                <tr class="wecantrack-include-script">
                    <th scope="row">
                        <label for=""><?php echo esc_html__('Include WCT Script', 'wecantrack'); ?></label>
                    </th>

                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_include_script" type="radio" value="1" <?php echo $wecantrack_include_script_enabled ?>>
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                &nbsp;
                                <label>
                                    <input name="wecantrack_include_script" type="radio" value="0" <?php echo $wecantrack_include_script_disabled ?>>
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>

                        <p class="description">The wct.js file will be included automatically by the plugin, or you can choose to disable this option and include it yourself.</b></p>
                    </td>
                </tr>

                <tr class="wecantrack-plugin-status">
                    <th scope="row">
                        <label for=""><?php echo esc_html__('Use WCT referrer cookie', 'wecantrack'); ?></label>
                    </th>

                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_referrer_cookie_status" type="radio" value="1" <?php echo $wecantrack_referrer_cookie_enabled ?>>
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                &nbsp;
                                <label>
                                    <input name="wecantrack_referrer_cookie_status" type="radio" value="0" <?php echo $wecantrack_referrer_cookie_disabled ?>>
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>

                        <p class="description">We use the cookie `_wct_http_referrer_1` and `_wct_http_referrer_2` to increase the coverage for populating the Clickout URL. <b>Note: This cookie gets set on the server side, if you have caching in place that checks on cookie values please filter these cookies out or disable this setting.</b></p>
                    </td>
                </tr>

                <tr class="wecantrack-plugin-status">
                    <th scope="row">
                        <label for=""><?php echo esc_html__('Verify SSL', 'wecantrack'); ?></label>
                    </th>

                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_ssl_status" type="radio" value="1" <?php echo $wecantrack_ssl_status_enabled ?>>
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                &nbsp;
                                <label>
                                    <input name="wecantrack_ssl_status" type="radio" value="0" <?php echo $wecantrack_ssl_status_disabled ?>>
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>

                        <p class="description">Verify SSL when making WCT API Request in the backend. If you have certification issues that you or your host is not able to solve you may set this to disable as a workaround.</p>
                    </td>
                </tr>

                <tr class="wecantrack-plugin-can-redirect-through-parameter">
                    <th scope="row">
                        <label for=""><?php echo esc_html__('Redirect Through Parameter (afflink)', 'wecantrack'); ?></label>
                    </th>

                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_can_redirect_through_parameter" type="radio" value="1" <?php echo $wecantrack_can_redirect_through_parameter_enabled ?>>
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                &nbsp;
                                <label>
                                    <input name="wecantrack_can_redirect_through_parameter" type="radio" value="0" <?php echo $wecantrack_can_redirect_through_parameter_disabled ?>>
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>

                        <p class="description">With auto-tagging enabled, the need to redirect through the 'afflink' parameter may become unnecessary, allowing you to disable this feature from your website.</p>
                    </td>
                </tr>

                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="submit" id="submit-verified" class="button button-primary" value="<?php echo esc_html__('Update & Save', 'wecantrack'); ?>">
            </p>

            <div id="wecantrack_form_feedback_top"></div>
        </form>
    </div>
</div>