<?php

/**
 * Class WecantrackHelper
 *
 * Provides utility methods used within the WeCanTrack WordPress plugin.
 */
class WecantrackHelper {

    /**
     * Retrieves and updates the stored tracking code for the given website.
     *
     * This function fetches the latest JavaScript tracking code from the WeCanTrack API
     * using the provided API key and site URL. If the retrieved code is different from
     * the currently stored one (or if none exists), it updates the 'wecantrack_snippet' option
     * with the new code and stores the update timestamp in 'wecantrack_snippet_version'.
     *
     * @param string $api_key   The user's WeCanTrack API key.
     * @param string $site_url  The full URL of the user's website.
     * @throws \Exception       If the tracking code could not be retrieved from the API.
     */
    public static function update_tracking_code($api_key, $site_url)
    {
        $tracking_code = stripslashes(self::get_user_tracking_code($api_key, urlencode($site_url)));

        $snippet = get_option('wecantrack_snippet');

        if (empty($tracking_code)) {
            error_log('[WeCanTrack] Received empty tracking code for ' . $site_url);
        } else if (!$snippet || $snippet !== $tracking_code) {
            update_option('wecantrack_snippet_version', time());
            update_option('wecantrack_snippet', $tracking_code);
        }
    }

    /**
     * Fetches the user's website information from the WeCanTrack API and stores it in a local WordPress option.
     *
     * This function makes a GET request to the WeCanTrack API using the provided API key and site URL.
     * If the response contains valid data without errors, it updates the 'wecantrack_website_options' option
     * with the latest website settings or metadata retrieved from the API.
     *
     * @param string $api_key   The user's WeCanTrack API key.
     * @param string $site_url  The full URL of the user's website.
     * @return void
     */
    public static function update_user_website_information($api_key, $site_url)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/websites?site_url=' . urlencode($site_url);
        $response = wp_remote_get($api_url, [
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'x-wp-version' => WECANTRACK_VERSION
            ],
        ]);

        $code = wp_remote_retrieve_response_code($response);
    
        if ($code === 404) {
            throw new \UnexpectedValueException(
                sprintf(
                    // translators: %s is the website URL or identifier.
                    esc_html__('Website `%s` not found in your We Can Track account', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        } else if ($code !== 200) {
            throw new \RuntimeException(
                sprintf(
                    // translators: %s is the full error message or error code from the request.
                    esc_html__('Bad request when updating website information %s', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        }

        $response = wp_remote_retrieve_body($response);
        $data = json_decode($response, true);

        if (!empty($data) && empty($data['error'])) {
            update_option('wecantrack_website_options', $data);
        }
    }

    /**
     * Retrieves the JavaScript tracking code for the given website from the user's WeCanTrack account.
     *
     * This function sends a GET request to the WeCanTrack API using the provided API key and site URL.
     * If successful (HTTP 200), it returns the raw tracking code as a string.
     * If the website is not found (HTTP 404), or another error occurs, it throws an exception.
     *
     * @param string $api_key   The user's WeCanTrack API key.
     * @param string $site_url  The full URL of the user's website.
     * @return string           The tracking code to embed.
     * @throws \Exception       If the request fails or the website is not found.
     */
    public static function get_user_tracking_code($api_key, $site_url)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/tracking_code?site_url=' . $site_url;
        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'text/plain',
                'x-wp-version' => WECANTRACK_VERSION,
            ],
            'sslverify' => WecantrackHelper::get_sslverify_option()
        ]);

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 404) {
            throw new \Exception(
                sprintf(
                    esc_html__('Website `%s` not found in your We Can Track account', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        } else if ($code !== 200) {
            throw new \Exception(
                sprintf(
                    // translators: %s is the site URL or identifier.
                    esc_html__('Bad request when fetching website %s', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Detects if the request's user agent indicates a bot.
     *
     * @param string $user_agent The user agent string to evaluate.
     * @return bool True if the user agent matches a known bot pattern; false otherwise.
     */
    public static function useragent_is_bot($user_agent)
    {
        $bots = apply_filters('wecantrack_known_bots', [
            'bot/',
            'crawler',
            'semrush',
            'bot.',
            ' bot ',
            '@bot',
            'guzzle',
            'gachecker',
            'cache',
            'cloudflare',
            'bing'
        ]);

        $pattern = implode('|', array_map('preg_quote', $bots));
        return preg_match("/($pattern)/i", $user_agent) === 1;
    }

    /**
     * Returns whether SSL verification should be enabled for remote API requests.
     *
     * By default, SSL verification is enabled (sslverify = true). However, some users may run WordPress on HTTP
     * or have issues with invalid SSL certificates. In such cases, they can set 'disable_ssl' in the
     * 'wecantrack_storage' option to true to bypass SSL verification.
     *
     * Used in wp_remote_get() calls to prevent failures due to certificate issues.
     */
    public static function get_sslverify_option()
    {
        $storage = json_decode(get_option('wecantrack_storage') ?? [], true);
        return !empty($storage['disable_ssl']) ? false : true;
    }
}