<?php
//nonce
$wecantrack_nonce = wp_create_nonce('wecantrack_nonce');

$option = unserialize(get_option('wecantrack_redirect_options'));
$delay = isset($option['delay']) ? $option['delay'] : null;
$url_contains = isset($option['url_contains']) ? $option['url_contains'] : null;
$redirect_text = isset($option['redirect_text']) ? $option['redirect_text'] : 'You are being directed to the merchant, one moment please...';

//plugins status
$wecantrack_redirect_status_enabled = '';
$wecantrack_redirect_status_disabled = '';
if (isset($option['status']) && $option['status'] == 1) {
    $wecantrack_redirect_status_enabled = 'checked="checked"';
} else {
    $wecantrack_redirect_status_disabled = 'checked="checked"';
}
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <div class="wecantrack_hero_image">
            <img src="<?php echo WECANTRACK_URL . '/images/wct-logo-normal.svg' ?>" alt="wct-logo">
        </div>
        <h1>WeCanTrack > Redirect Page</h1>
        <p>This module allows you to choose a page to redirect your users through, this allows you to add any JS tracking on the page.</p>
        <p>With our redirect solution you will be able to lead the users through a redirect link towards an affiliate link or landing page URL. The redirect process is done to collect necessary information to later on integrate conversion data in various tools. Please be aware that some ad platforms do not permit redirect links and might disapprove the ads or even close down the account if they assume the user is acting against their policies. It is your responsibility to make sure you are playing by the rules, we merely deliver a tracking and data integration service. It is forbidden to use redirects as a deceiving mechanismn for the approval process within Ad Platforms. Furthermore, be aware that if you want to use data for remarketing purposes you will need the users’ consent in most cases. Thus, you will need to determine yourself, whether the data integrated by our redirect feature may or may not be used for remarketing / audience creation purposes.</p>

        <p>Please consider the following bullet points before activating the redirect page feature:</p>
        <ul style="list-style: disc; padding-inline-start: 20px;">
            <li>Make sure your partners (such as advertisers or affiliate networks) are fine with you using this method.</li>
            <li>Make sure this method is legitimate within the marketing tool(s) you are using (such as Google Ads, Facebook Ads, Bing Ads, Mailchimp, Sendgrid…).</li>
            <li>Make sure you are not violating any privacy regulations (such as GDPR).</li>
        </ul>
        <p>We do not take responsibility for disapproved/rejected campaigns, accounts and conversions.</p>

        <form id="wecantrack_ajax_form" action="<?php echo WECANTRACK_PATH . '.php' ?>" method="post">
            <input type="hidden" name="action" value="wecantrack_redirect_page_form_response">
            <input type="hidden" name="wecantrack_form_nonce" value="<?php echo $wecantrack_nonce ?>">

            <table class="form-table" role="presentation">
                <tbody>
                <tr class="wecantrack-plugin-status">
                    <th scope="row"><?php echo esc_html__('Redirect page status', 'wecantrack'); ?></th>
                    <td>
                        <fieldset>
                            <p>
                                <label>
                                    <input name="wecantrack_redirect_status" type="radio" value="1" <?php echo $wecantrack_redirect_status_enabled ?> />
                                    <?php echo esc_html__('Enable', 'wecantrack'); ?>
                                </label>
                                <br />
                                <label>
                                    <input name="wecantrack_redirect_status" type="radio" value="0" <?php echo $wecantrack_redirect_status_disabled ?> />
                                    <?php echo esc_html__('Disable', 'wecantrack'); ?>
                                </label>
                            </p>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for=""><?php echo esc_html__('Redirect text', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <input style="width:100%;" type="text" name="redirect_text" id="" value="<?php echo $redirect_text; ?>"
                        placeholder="<?php echo esc_html__('You are being directed to the merchant, one moment please', 'wecantrack'); ?>" />
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wecantrack_custom_redirect_html"><?php echo esc_html__('Insert custom HTML in the header', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <textarea name="wecantrack_custom_redirect_html" id="wecantrack_custom_redirect_html" class="large-text code" rows="10"><?php echo get_option('wecantrack_custom_redirect_html'); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wecantrack_redirect_delay"><?php echo esc_html__('Second delay before redirect', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <input type="number" min="0" name="wecantrack_redirect_delay" id="wecantrack_redirect_delay" value="<?php echo $delay; ?>"/>
                        <p class="description">Useful for letting your scripts load and execute before redirecting</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="url_contains"><?php echo esc_html__('Only use the Redirect Page when URL contains (optional)', 'wecantrack'); ?></label>
                    </th>
                    <td>
                        <input style="width: 100%;" type="text" name="url_contains" id="url_contains" value="<?php echo $url_contains; ?>" />
                    </td>
                </tr>
                </tbody>
            </table>

            <p class="submit">
                <input type="submit" name="submit" class="button button-primary" value="<?php echo esc_html__('Update & Save', 'wecantrack'); ?>">
            </p>

            <div id="wecantrack_form_feedback_top"></div>

        </form>
    </div>
</div>