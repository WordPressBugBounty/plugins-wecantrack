<?php

/**
 * Class WecantrackHelper
 *
 * Admin class
 */
class WecantrackHelper {

    /**
     * @param $api_key
     * @param $site_url
     * @throws Exception
     */
    public static function update_tracking_code($api_key, $site_url)
    {
        $tracking_code = stripslashes(self::get_user_tracking_code($api_key, urlencode($site_url)));

        if (!empty($tracking_code) && (!get_option('wecantrack_snippet') || get_option('wecantrack_snippet') != $tracking_code)) {
            update_option('wecantrack_snippet_version', time());
            update_option('wecantrack_snippet', $tracking_code);
        }
    }

    /**
     * @param $api_key
     * @param $site_url
     * @return void
     */
    public static function update_user_website_information($api_key, $site_url)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/websites?site_url=' . urlencode($site_url);
        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'x-wp-version' => WECANTRACK_VERSION
            ),
        ));

        $response = wp_remote_retrieve_body($response);
        $data = json_decode($response, true);

        if (!empty($data) && empty($data['error'])) {
            update_option('wecantrack_website_options', $data);
        }
    }

    /**
     * @param $api_key
     * @param $site_url
     * @return array|string
     */
    public static function get_user_tracking_code($api_key, $site_url)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/tracking_code?site_url=' . $site_url;
        $response = wp_remote_get($api_url, array(
            'timeout' => 10,
            'headers' => array(
                'x-api-key' => $api_key,
                'Content-Type' => 'text/plain',
                'x-wp-version' => WECANTRACK_VERSION,
            ),
            'sslverify' => WecantrackHelper::get_sslverify_option()
        ));

        $code = wp_remote_retrieve_response_code($response);
        $response = wp_remote_retrieve_body($response);

        if ($code === 200) {
            return $response;
        }

        if ($code === 404) {
            throw new \Exception(
                esc_html__(sprintf('Website `%s` not found in your We Can Track account', urldecode($site_url)), 'wecantrack')
            );
        } else {
            throw new \Exception(
                esc_html__(sprintf('Bad request when fetching website %s', urldecode($site_url)), 'wecantrack')
            );
        }
    }

    /**
     * @param $nonce
     * @return bool
     */
    public static function nonce_check($nonce)
    {
        if (isset($_POST['ajaxrequest']) && sanitize_text_field($_POST['ajaxrequest']) === 'true') {
            if (wp_verify_nonce($nonce, 'wecantrack_nonce')) {
                return true;
            }
        }

        echo json_encode(array('error' => 'Invalid nonce', 'nonce' => $nonce));
        wp_die();
    }

    /**
     * Detects if it's a bot depending on user agent
     *
     * @param $user_agent
     *
     * @return bool
     */
    public static function useragent_is_bot($user_agent)
    {
        $bots = ['bot/', 'crawler', 'semrush', 'bot.', ' bot ', '@bot', 'guzzle', 'gachecker', 'cache', 'cloudflare', 'bing'];

        foreach ($bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function get_sslverify_option()
    {
        $storage = json_decode(get_option('wecantrack_storage'), true);
        return !empty($storage['disable_ssl']) ? false : true;
    }
}