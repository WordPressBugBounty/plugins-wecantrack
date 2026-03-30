<?php
if (!defined('ABSPATH')) { exit; }
$option = maybe_unserialize(get_option('wecantrack_redirect_options'));
$delay = $option['delay'] ?? null;
$url_contains = $option['url_contains'] ?? null;
$redirect_text = $option['redirect_text'] ?? 'You are being directed to the merchant, one moment please...';
?>

<div class="wrap">
    <div id="wecantrack_loading"></div>
    <div class="wecantrack_body">
        <div class="wecantrack_hero_image">
            <img src="<?php echo esc_url(WECANTRACK_URL . '/images/wct-logo-normal.svg') ?>" alt="wct-logo">
        </div>
        <h1>WeCanTrack > Redirect Page</h1>

        <div class="notice notice-error">
            <h1>
                <strong>
                    ⚠️ This module is no longer supported or functional for security reasons.
                    Please ensure you back up any custom HTML code you've added if needed, this module will be permanently removed in the next major update.
                    For assistance or more information, contact WeCanTrack Support at 
                    <a href="mailto:support@wecantrack.com" style="color: red; text-decoration: underline;">support@wecantrack.com</a>.
                </strong>
            </h1>
        </div>

        <div style="color: gray; opacity: 50%;">
        <p>This module allows you to choose a page to redirect your users through, this allows you to add any JS tracking on the page.</p>
        <p>With our redirect solution you will be able to lead the users through a redirect link towards an affiliate link or landing page URL. The redirect process is done to collect necessary information to later on integrate conversion data in various tools. Please be aware that some ad platforms do not permit redirect links and might disapprove the ads or even close down the account if they assume the user is acting against their policies. It is your responsibility to make sure you are playing by the rules, we merely deliver a tracking and data integration service. It is forbidden to use redirects as a deceiving mechanismn for the approval process within Ad Platforms. Furthermore, be aware that if you want to use data for remarketing purposes you will need the users’ consent in most cases. Thus, you will need to determine yourself, whether the data integrated by our redirect feature may or may not be used for remarketing / audience creation purposes.</p>

        <p>Please consider the following bullet points before activating the redirect page feature:</p>
        <ul style="list-style: disc; padding-inline-start: 20px;">
            <li>Make sure your partners (such as advertisers or affiliate networks) are fine with you using this method.</li>
            <li>Make sure this method is legitimate within the marketing tool(s) you are using (such as Google Ads, Facebook Ads, Bing Ads, Mailchimp, Sendgrid…).</li>
            <li>Make sure you are not violating any privacy regulations (such as GDPR).</li>
        </ul>
        <p>We do not take responsibility for disapproved/rejected campaigns, accounts and conversions.</p>
        </div>

        <table class="form-table" role="presentation">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="redirect_text"><?php echo esc_html__('Redirect text', 'wecantrack'); ?></label>
                </th>
                <td>
                    <input style="width:100%;" type="text" id="redirect_text" name="redirect_text" value="<?php echo esc_textarea($redirect_text); ?>"
                    placeholder="<?php echo esc_html__('You are being directed to the merchant, one moment please', 'wecantrack'); ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wecantrack_custom_redirect_html"><?php echo esc_html__('Insert custom HTML in the header', 'wecantrack'); ?></label>
                </th>
                <td>
                    <textarea name="wecantrack_custom_redirect_html" id="wecantrack_custom_redirect_html" class="large-text code" rows="10"><?php echo esc_textarea(get_option('wecantrack_custom_redirect_html')); ?></textarea>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wecantrack_redirect_delay"><?php echo esc_html__('Second delay before redirect', 'wecantrack'); ?></label>
                </th>
                <td>
                    <input type="number" min="0" name="wecantrack_redirect_delay" id="wecantrack_redirect_delay" value="<?php echo esc_attr($delay ?? 0); ?>"/>
                    <p class="description">Useful for letting your scripts load and execute before redirecting</p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="url_contains"><?php echo esc_html__('Only use the Redirect Page when URL contains (optional)', 'wecantrack'); ?></label>
                </th>
                <td>
                    <input style="width: 100%;" type="text" name="url_contains" id="url_contains" value="<?php echo esc_url($url_contains); ?>" />
                </td>
            </tr>
            </tbody>
        </table>
    </div>
</div>