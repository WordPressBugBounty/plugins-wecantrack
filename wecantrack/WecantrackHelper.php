<?php
if (!defined('ABSPATH')) { exit; }

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
     * Performs the raw GET request to the WeCanTrack `/user/websites` endpoint.
     *
     * Unlike update_user_website_information(), this never throws: it returns the HTTP
     * status code and the decoded body so callers can loop over several candidate URLs
     * cheaply (e.g. home_url() then a stored override).
     *
     * @param string $api_key   The user's WeCanTrack API key.
     * @param string $site_url  The full URL of the user's website.
     * @return array{code:int, data:array|null}
     */
    private static function fetch_website_information($api_key, $site_url)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/websites?site_url=' . urlencode($site_url);
        $response = wp_remote_get($api_url, [
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'x-wp-version' => WECANTRACK_VERSION
            ],
            'sslverify' => self::get_sslverify_option()
        ]);

        $code = wp_remote_retrieve_response_code($response);
        $data = $code === 200 ? json_decode(wp_remote_retrieve_body($response), true) : null;

        return ['code' => (int) $code, 'data' => $data];
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
        $result = self::fetch_website_information($api_key, $site_url);

        if ($result['code'] === 404) {
            throw new \UnexpectedValueException(
                sprintf(
                    // translators: %s is the website URL or identifier.
                    esc_html__('Website `%s` not found in your wecantrack account', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        } else if ($result['code'] !== 200) {
            throw new \RuntimeException(
                sprintf(
                    // translators: %s is the full error message or error code from the request.
                    esc_html__('Bad request when updating website information %s', 'wecantrack'),
                    esc_url($site_url)
                )
            );
        }

        $data = $result['data'];

        if (!empty($data) && empty($data['error'])) {
            update_option('wecantrack_website_options', $data);
        }
    }

    /**
     * Builds the ordered list of site URLs to try when resolving which WeCanTrack website
     * this install maps to.
     *
     * Priority (highest first): home_url() so production self-heals after a staging deploy,
     * then a freshly posted override, then a previously stored override, then — when the
     * account has exactly one website — that website (zero-UI auto-select). The websites
     * list is only passed on the interactive verify path, so background refreshes fall back
     * to [home_url(), stored override].
     *
     * @param string|null $posted_override Optional URL submitted from the website dropdown.
     * @param array|null  $websites        Optional list of the user's websites (each with a 'url' key).
     * @return string[] De-duplicated, non-empty candidate URLs in priority order.
     */
    public static function get_candidate_site_urls($posted_override = null, $websites = null)
    {
        $candidates = [home_url()];

        if (!empty($posted_override)) {
            $candidates[] = $posted_override;
        }

        $stored_override = get_option('wecantrack_website_override');
        if (!empty($stored_override)) {
            $candidates[] = $stored_override;
        }

        // Auto-select when the account has exactly one website.
        if (is_array($websites) && count($websites) === 1 && !empty($websites[0]['url'])) {
            $candidates[] = $websites[0]['url'];
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        return apply_filters('wecantrack_candidate_site_urls', $candidates);
    }

    /**
     * Resolves which candidate URL maps to a WeCanTrack website, stores its options and
     * persists (or clears) the override accordingly.
     *
     * Tries each candidate against the `/user/websites` endpoint and uses the first that
     * returns a valid 200. If the match is home_url() the stored override is cleared
     * (self-heal on production); otherwise the matched URL is stored as the override so
     * subsequent refreshes keep using it.
     *
     * @param string   $api_key    The user's WeCanTrack API key.
     * @param string[] $candidates Ordered candidate site URLs (see get_candidate_site_urls()).
     * @return string The candidate URL that matched.
     * @throws \UnexpectedValueException If no candidate matches a website.
     */
    public static function resolve_and_store_website($api_key, array $candidates)
    {
        $home_url = home_url();

        foreach ($candidates as $candidate) {
            $result = self::fetch_website_information($api_key, $candidate);

            if ($result['code'] === 200 && !empty($result['data']) && empty($result['data']['error'])) {
                update_option('wecantrack_website_options', $result['data']);

                if ($candidate === $home_url) {
                    delete_option('wecantrack_website_override');
                } else {
                    update_option('wecantrack_website_override', $candidate);
                }

                return $candidate;
            }
        }

        throw new \UnexpectedValueException(
            sprintf(
                // translators: %s is the website URL or identifier.
                esc_html__('Website `%s` not found in your wecantrack account', 'wecantrack'),
                esc_url($home_url)
            )
        );
    }

    /**
     * Resolves the effective site URL from the given candidates and refreshes both the
     * stored website options and the tracking-code snippet for it.
     *
     * Centralises the website-info + tracking-code fetch so every call site (verify,
     * version change, upgrade, cron, debug refresh) shares the same override-aware logic.
     * Website info is resolved first because it determines the matched URL that the
     * tracking-code fetch then uses.
     *
     * @param string   $api_key    The user's WeCanTrack API key.
     * @param string[] $candidates Ordered candidate site URLs (see get_candidate_site_urls()).
     * @return string The matched site URL.
     * @throws \Exception If no candidate matches a website.
     */
    public static function refresh_config_with_candidates($api_key, array $candidates)
    {
        $matched = self::resolve_and_store_website($api_key, $candidates);
        self::update_tracking_code($api_key, $matched);

        return $matched;
    }

    /**
     * Updates the script version of the given website in the user's WeCanTrack account.
     *
     * Sends a PATCH request to the `/websites` endpoint. On the WeCanTrack side this is
     * recorded as a deliberate user choice, so bulk script-version migrations skip the
     * website afterwards.
     *
     * @param string $api_key        The user's WeCanTrack API key.
     * @param string $site_url       The website URL as registered in the WeCanTrack account.
     * @param int    $script_version The script version to switch to (1 = legacy, 2 = new).
     * @throws \RuntimeException     If the request fails.
     */
    public static function update_script_version($api_key, $site_url, $script_version)
    {
        $api_url = WECANTRACK_API_BASE_URL . '/api/v1/websites?url=' . urlencode($site_url);
        $response = wp_remote_request($api_url, [
            'method' => 'PATCH',
            'timeout' => 10,
            'headers' => [
                'x-api-key' => $api_key,
                'x-wp-version' => WECANTRACK_VERSION,
            ],
            // Form-encoded on purpose: the endpoint reads PHP-parsed body params, not JSON.
            'body' => ['script_version' => (int) $script_version],
            'sslverify' => self::get_sslverify_option()
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new \RuntimeException(
                sprintf(
                    // translators: %s is the website URL.
                    esc_html__('Could not update the tracking script version for %s', 'wecantrack'),
                    esc_url($site_url)
                )
            );
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
                    esc_html__('Website `%s` not found in your wecantrack account', 'wecantrack'),
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
        if (!is_string($user_agent)) {
            return false;
        }

        $delimiter = '~';

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

        $pattern = implode('|', array_map(function ($bot) use ($delimiter) {
            return preg_quote($bot, $delimiter);
        }, $bots));

        return preg_match("{$delimiter}({$pattern}){$delimiter}i", $user_agent) === 1;
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