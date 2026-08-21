<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Class WecantrackApp
 *
 * Handles the public-facing functionality of the Wecantrack plugin.
 * Loaded on non-admin (public) pages of the WordPress site.
 *
 * @package Wecantrack
 */
class WecantrackApp {
    const CURL_TIMEOUT_S = 5, FETCH_DOMAIN_PATTERN_IN_HOURS = 3, WCT_SCRIPT_DOMAIN = 'wct-3.com';
    const DEFAULT_CLICK_ID_PLACEHOLDER = '{wct_click_id}';
    const WCT_SCRIPT_FILENAME = 'wct.js';

    // Opt-out attributes for optimizers without usable PHP hooks: Cloudflare Rocket
    // Loader runs at the CDN edge and cannot be detected from PHP, data-no-optimize
    // is the generic "leave me alone" convention (FlyingPress and others), and
    // data-nowprocket is WP Rocket's documented tag-level exclusion.
    const OPTIMIZER_OPT_OUT_ATTRS = ' data-cfasync="false" data-no-optimize="1" data-nowprocket';

    private $api_key, $drop_referrer_cookie;

    protected ?array $options_storage;
    protected ?string $snippet;

    /**
     * WecantrackApp constructor.
     */
    public function __construct() {
        try {
            self::if_debug_show_plugin_config();

            $this->drop_referrer_cookie = get_option('wecantrack_referrer_cookie_status');
            if ($this->drop_referrer_cookie === null) {
                $this->drop_referrer_cookie = 1;
            }

            // abort if there's no api key
            $api_key = get_option('wecantrack_api_key');
            if (!$api_key) {
                return;
            }
            $this->api_key = $api_key;

            if (!get_option('wecantrack_plugin_status')) {
                if (!$this->session_enabler_is_turned_on()) {
                    return;
                }
            }

            $this->options_storage = json_decode(get_option('wecantrack_storage'), true);
            $this->snippet = get_option('wecantrack_snippet');

            $this->load_hooks();

            if ($this->drop_referrer_cookie) {
                $this->set_http_referrer();
            }
        } catch (Exception $e) {
            error_log('[WeCanTrack] init error: ' . $e->getMessage());
            return;
        }
    }

    /**
     * Outputs plugin configuration and status in JSON format for debugging purposes.
     *
     * This method is triggered when the `_wct_config` GET parameter matches the current date's MD5 hash.
     * Primarily intended for internal diagnostic or developer use.
     *
     * @return void This method exits execution after echoing JSON output.
     */
    private static function if_debug_show_plugin_config() {
        $api_key = get_option('wecantrack_api_key');
        $expected = $api_key ? hash_hmac('sha256', gmdate('Y-m-d'), $api_key) : null;

        if ($expected && isset($_GET['_wct_config']) && hash_equals($expected, $_GET['_wct_config'])) {
            header('X-Robots-Tag: noindex', true);
            header('Content-Type: application/json', true);

            $refreshed = 0;
            $extra = [];

            $domainURL = home_url();
            $extra['home_url'] = $domainURL;

            if (isset($_GET['refresh'])) {
                if (!get_transient('wecantrack_lock_cache_refresh')) {
                    $api_key = get_option('wecantrack_api_key');
                    require_once(WECANTRACK_PATH . '/WecantrackAdmin.php');
                    $data = WecantrackAdmin::get_user_information($api_key);

                    if (!empty($data['error'])) {
                        wp_die();
                    }

                    try {
                        WecantrackHelper::refresh_config_with_candidates($api_key, WecantrackHelper::get_candidate_site_urls());
                        $extra['update_tracking_code'] = true;

                        WecantrackApp::wecantrack_get_domain_patterns($api_key, true);
                    } catch (\Exception $e) {
                        $extra['update_tracking_code'] = false;
                    }

                    $refreshed = 1;
                }
                set_transient('wecantrack_lock_cache_refresh', 1, 60);
            }

            echo json_encode([
                'v' => WECANTRACK_VERSION,
                'status' => get_option('wecantrack_plugin_status'),
                'r_status' => get_option('wecantrack_redirect_status'),
                'r_options' => maybe_unserialize(get_option('wecantrack_redirect_options')),
                'f_exp' => get_option('wecantrack_fetch_expiration'),
                'sess_e' => get_option('wecantrack_session_enabler'),
                'snippet_v' => get_option('wecantrack_snippet_version'),
                'snippet' => get_option('wecantrack_snippet'),
                'refreshed' => $refreshed,
                'patterns' => maybe_unserialize(get_option('wecantrack_domain_patterns')),
                'extra' => $extra
            ]);

            exit;
        }

    }

    /**
     * Returns the current full URL or just the base site URL, depending on the parameter.
     *
     * Constructs the URL using the server's `HTTPS`, `SERVER_NAME`, and optionally `REQUEST_URI`.
     * Useful for generating absolute URLs in a variety of contexts.
     *
     * @param bool $without_uri Optional. If true, returns only the scheme and domain (e.g., https://example.com). 
     *                          If false, includes the full request URI. Default false.
     *
     * @return string The constructed current URL.
     */
    public static function current_url($without_uri = false) {
        if ($without_uri) {
            return sprintf(
                "%s://%s",
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                $_SERVER['SERVER_NAME']
            );
        } else {
            return sprintf(
                "%s://%s%s",
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
                $_SERVER['SERVER_NAME'],
                $_SERVER['REQUEST_URI']
            );
        }
    }

    /**
     * Sends HTTP headers to disable caching of the current response.
     *
     * Applies both HTTP/1.0 and HTTP/1.1 headers to prevent the browser and intermediaries from caching the response.
     *
     * @return void
     */
    public static function set_no_cache_headers() {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, max-age=0');//HTTP 1.1
        header('Pragma: no-cache');//HTTP 1.0
    }

    /**
     * Registers WordPress hooks used by the plugin.
     *
     * - Adds a filter to intercept redirects via `wp_redirect`.
     * - Optionally adds the JavaScript snippet to the page head if `include_script` is enabled in options.
     *
     * @return void
     */
    public function load_hooks() {
        add_filter('wp_redirect', [$this, 'redirect_default'], 99);

        $this->exclude_from_optimizer_plugins();

        if (!isset($this->options_storage['include_script']) || $this->options_storage['include_script'] == true) {
            add_action('wp_head', [$this, 'insert_snippet']);
        }
    }

    /**
     * Registers exclusion hooks so page-optimizer plugins do not minify, combine,
     * defer, or delay the wct.js tracking script.
     *
     * wct.js is a dynamic per-property build: optimizers that fetch it, strip its
     * query string, and re-host a static copy end up serving an empty script.
     *
     * Each optimizer has its own matching semantics, so the values differ per hook:
     * - WP Rocket excludes external scripts from minify/combine by host, and
     *   delay/defer exclusions are regex fragments matched against the tag.
     * - LiteSpeed Cache, WP-Optimize, and Perfmatters match plain URL substrings.
     * - Autoptimize matches substrings in a comma-separated string.
     * - W3 Total Cache passes each script tag through a boolean filter.
     * - SiteGround Optimizer's "Combine JavaScript Files" parses raw script tags
     *   from the HTML, downloads external scripts, and inlines their content into
     *   a combined bundle (freezing wct.js's per-request anti-bot token), and it
     *   ignores the opt-out attributes. Its handle-based filter cannot match a raw
     *   tag, but the external-paths filter (external srcs) and inline-content
     *   filter (the legacy inline snippet) match plain substrings.
     *
     * Optimizers without usable hooks (Cloudflare Rocket Loader, FlyingPress, ...)
     * are covered by OPTIMIZER_OPT_OUT_ATTRS on the script tag instead.
     *
     * @return void
     */
    private function exclude_from_optimizer_plugins() {
        // WP Rocket
        add_filter('rocket_minify_excluded_external_js', [$this, 'add_script_hosts_exclusion']);
        add_filter('rocket_delay_js_exclusions', [$this, 'add_script_pattern_exclusion']);
        add_filter('rocket_exclude_defer_js', [$this, 'add_script_pattern_exclusion']);

        // LiteSpeed Cache
        add_filter('litespeed_optimize_js_excludes', [$this, 'add_script_substring_exclusion']);
        add_filter('litespeed_optm_js_defer_exc', [$this, 'add_script_substring_exclusion']);

        // WP-Optimize
        add_filter('wp-optimize-minify-default-exclusions', [$this, 'add_script_substring_exclusion']);

        // Perfmatters
        add_filter('perfmatters_delay_js_exclusions', [$this, 'add_script_substring_exclusion']);
        add_filter('perfmatters_defer_js_exclusions', [$this, 'add_script_substring_exclusion']);

        // Autoptimize
        add_filter('autoptimize_filter_js_exclude', [$this, 'add_autoptimize_exclusion']);

        // SiteGround Optimizer
        add_filter('sgo_javascript_combine_excluded_external_paths', [$this, 'add_script_substring_exclusion']);
        add_filter('sgo_javascript_combine_excluded_inline_content', [$this, 'add_script_substring_exclusion']);

        // W3 Total Cache
        add_filter('w3tc_minify_js_do_tag_minification', [$this, 'skip_w3tc_tag_minification'], 10, 3);
    }

    /**
     * Adds the wct.js filename as a plain-substring exclusion (no regex, no wildcards).
     *
     * @param array|string|null $excludes The optimizer's current exclusion list.
     * @return array The exclusion list including wct.js.
     */
    public function add_script_substring_exclusion($excludes) {
        $excludes = self::ensure_exclusion_array($excludes);
        $excludes[] = self::WCT_SCRIPT_FILENAME;
        return $excludes;
    }

    /**
     * Adds wct.js as a regex-fragment exclusion (WP Rocket delay/defer lists).
     *
     * @param array|string|null $excludes The optimizer's current exclusion list.
     * @return array The exclusion list including the wct.js pattern.
     */
    public function add_script_pattern_exclusion($excludes) {
        $excludes = self::ensure_exclusion_array($excludes);
        $excludes[] = 'wct\.js';
        return $excludes;
    }

    /**
     * Adds the wct.js script hosts (default domain and, when configured in the
     * website form, the custom proxy domain) to a host-based exclusion list.
     *
     * @param array|string|null $hosts The optimizer's current host exclusion list.
     * @return array The host list including the wct.js domains.
     */
    public function add_script_hosts_exclusion($hosts) {
        $hosts = self::ensure_exclusion_array($hosts);
        $hosts[] = self::WCT_SCRIPT_DOMAIN;

        $proxy_host = self::get_script_proxy_host();
        if ($proxy_host) {
            $hosts[] = $proxy_host;
        }

        return $hosts;
    }

    /**
     * Adds wct.js to Autoptimize's comma-separated exclusion string.
     *
     * @param string|mixed $exclude The current comma-separated exclusion string.
     * @return string The exclusion string including wct.js.
     */
    public function add_autoptimize_exclusion($exclude) {
        $exclude = is_string($exclude) ? $exclude : '';
        return $exclude === '' ? self::WCT_SCRIPT_FILENAME : $exclude . ', ' . self::WCT_SCRIPT_FILENAME;
    }

    /**
     * Tells W3 Total Cache to skip minification for the wct.js script tag.
     *
     * @param bool   $do_tag_minification Whether W3TC intends to minify this tag.
     * @param string $script_tag          The full script tag being processed.
     * @param string $file                The script URL.
     * @return bool False for wct.js, otherwise the incoming value.
     */
    public function skip_w3tc_tag_minification($do_tag_minification, $script_tag, $file) {
        if (is_string($file) && strpos($file, self::WCT_SCRIPT_FILENAME) !== false) {
            return false;
        }
        return $do_tag_minification;
    }

    /**
     * Normalizes an exclusion-filter value to an array without discarding entries
     * another plugin may have registered as a scalar. Empty values are dropped:
     * an empty-string entry would substring-match every script in strpos-based
     * optimizers and exclude everything.
     *
     * @param array|string|null $value The incoming filter value.
     * @return array The value as an array.
     */
    private static function ensure_exclusion_array($value) {
        if (is_array($value)) {
            return $value;
        }
        return $value === null || $value === '' ? [] : [$value];
    }

    /**
     * Returns the host of the custom script proxy domain configured in the
     * wecantrack website form, or null when no proxy is set.
     *
     * @return string|null The proxy host, e.g. `proxy.example.com`.
     */
    public static function get_script_proxy_host() {
        $website_options = self::get_website_options();
        $proxy = is_array($website_options) ? ($website_options['proxy'] ?? '') : '';

        if (!is_string($proxy) || $proxy === '') {
            return null;
        }

        $host = wp_parse_url($proxy, PHP_URL_HOST);
        return $host ?: null;
    }

    /**
     * Returns the decoded wecantrack website form options, or null when unset.
     *
     * @return array|null The website options.
     */
    private static function get_website_options() {
        $raw = get_option('wecantrack_website_options');
        $website_options = is_array($raw) ? $raw : json_decode((string) $raw, true);
        return is_array($website_options) ? $website_options : null;
    }

    /**
     * Default redirect handler that processes affiliate links before redirection.
     *
     * For example, it hooks on redirects from Pretty Link WP Plugin. 
     * The functionality modifies the URL to add tracking decoration to the URL before,
     * allowing the visitor to proceed to the intended link.
     *
     * @param string $location The original redirect URL.
     * @return string The modified or original URL to be used for the redirect.
     */
    public function redirect_default($location) {
        self::delete_http_referrer_where_site_url(self::current_url());

        $placeholder_url = $this->replace_click_id_placeholder($location);
        if ($placeholder_url !== null) {
            return $placeholder_url;
        }

        if (!self::is_affiliate_link($this->api_key, $location)) {
            return $location;
        }

        $modified_url = self::get_modified_affiliate_url($location, $this->api_key, ['ignore_current_clickout_url' => true]);
        $location = $location != $modified_url ? $modified_url : $location;

        return $location;
    }

    /**
     * Replaces the click ID placeholder in a redirect target with a locally generated click ID.
     *
     * Mirrors replaceClickIdPlaceholderInAnchor() in the wct.js auto-tagging module, but for
     * cloaked URLs whose affiliate target never appears in the page HTML: the placeholder (raw
     * or URL-encoded) is swapped for a click ID at redirect time and the click is registered
     * with the Clickout API via user_click_reference in a non-blocking request.
     *
     * @param string $location The redirect target URL.
     * @return string|null The URL with the placeholder replaced, or null when it contains no placeholder.
     */
    private function replace_click_id_placeholder($location) {
        $placeholder = self::get_click_id_placeholder();
        $tokens = [$placeholder, rawurlencode($placeholder)];

        // Forgiving fallback: when no custom placeholder is configured, also accept the
        // default written without braces. Listed last so the braced forms are consumed
        // first and only genuinely bare occurrences remain to match.
        if ($placeholder === self::DEFAULT_CLICK_ID_PLACEHOLDER) {
            $tokens[] = 'wct_click_id';
        }

        if (str_replace($tokens, '', $location) === $location) {
            return null;
        }

        self::set_no_cache_headers();

        $click_id = self::generate_click_id();
        $modified_url = str_replace($tokens, $click_id, $location);

        // bots get a clean URL, but their clicks are not registered
        if (isset($_SERVER['HTTP_USER_AGENT']) && !WecantrackHelper::useragent_is_bot($_SERVER['HTTP_USER_AGENT'])) {
            $this->register_clickout_reference($modified_url, $click_id);
        }

        return $modified_url;
    }

    /**
     * Returns the click ID placeholder configured for this website in the wecantrack website
     * form, falling back to the same default the wct.js auto-tagging module uses.
     *
     * @return string The placeholder string, e.g. `{wct_click_id}`.
     */
    public static function get_click_id_placeholder() {
        $website_options = self::get_website_options();
        $placeholder = is_array($website_options) ? ($website_options['click_id_placeholder'] ?? '') : '';

        return is_string($placeholder) && $placeholder !== '' ? $placeholder : self::DEFAULT_CLICK_ID_PLACEHOLDER;
    }

    /**
     * Generates a click ID in the same format as _wct.generateClickID() in the wct.js
     * auto-tagging module: `wct` + UTC ymdHis + 5 random alphanumerics.
     *
     * @return string The generated click ID.
     */
    public static function generate_click_id() {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= $characters[wp_rand(0, strlen($characters) - 1)];
        }

        return 'wct' . gmdate('ymdHis') . $suffix;
    }

    /**
     * Registers a click with the Clickout API under a locally generated click reference.
     *
     * The redirect URL already carries the click ID, so no response is needed: the request
     * is fire-and-forget (non-blocking), same as the sendBeacon call in the wct.js module.
     *
     * @param string $affiliate_url The affiliate URL with the click ID already injected.
     * @param string $click_id      The locally generated click ID.
     * @return void
     */
    private function register_clickout_reference($affiliate_url, $click_id) {
        try {
            $wctCookie = !empty($_COOKIE['_wctrck']) ? sanitize_text_field($_COOKIE['_wctrck']) : null;
            $wctCookie = !$wctCookie && !empty($_GET['data']) && strlen($_GET['data']) > 50
                ? sanitize_text_field($_GET['data']) : $wctCookie;

            $post_data = [
                'affiliate_url' => rawurlencode($affiliate_url),
                'user_click_reference' => $click_id,
                'clickout_url' => self::get_clickout_url(),
                'redirect_url' => self::current_url(),
                '_ga' => !empty($_COOKIE['_ga']) ? sanitize_text_field($_COOKIE['_ga']) : null,
                '_wctrck' => $wctCookie,
                'ua' => sanitize_text_field($_SERVER['HTTP_USER_AGENT']),
                'ip' => self::get_user_real_ip(),
            ];

            wp_remote_post(WECANTRACK_API_BASE_URL . '/api/v1/clickout', [
                'timeout' => self::CURL_TIMEOUT_S,
                'blocking' => false,
                'headers' => [
                    'x-api-key' => $this->api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($post_data),
                'sslverify' => WecantrackHelper::get_sslverify_option()
            ]);
        } catch (Exception $e) {
            error_log('[WeCanTrack] Clickout reference register exception: ' . $e->getMessage());
        }
    }

    /**
     * Inserts the WCT Snippet with preload tag.
     *
     * @return void
     */
    public function insert_snippet() {
        $website_options = self::get_website_options();

        if (!empty($website_options) && ($website_options['script_version'] ?? null) == 2) {
            $property_id = $website_options['property_id'] ?? null;

            if (!empty($property_id)) {
                $base = !empty($website_options['proxy']) ? $website_options['proxy'] : 'https://' . self::WCT_SCRIPT_DOMAIN;
                $src = $base . '/wct.js?property_id=' . urlencode($property_id);
                $extra_attrs = self::OPTIMIZER_OPT_OUT_ATTRS;
                if (($website_options['cookie_consent_provider'] ?? null) === 'cookiebot') {
                    $extra_attrs .= ' data-cookieconsent="ignore"';
                }
                echo '<script src="' . esc_url($src) . '" async' . $extra_attrs . '></script>';

                if (!empty($website_options['monetisation_enabled']) && empty($website_options['monetisation_bundled'])) {
                    $monetisation_src = $base . '/wct.js?property_id=' . urlencode($property_id) . '&standalone=monetisation';
                    echo '<script src="' . esc_url($monetisation_src) . '" async' . $extra_attrs . '></script>';
                }
            }
            return;
        }

        if (empty($this->snippet)) {
            return;
        }

        preg_match('/s\.src ?= ?\'([^\']+)/', $this->snippet, $scriptSrcStringmatch);

        if (!empty($scriptSrcStringmatch[1])) {
            echo '<link rel="preload" href="'.esc_url($scriptSrcStringmatch[1]).'" as="script">';
            echo '<script type="text/javascript" data-ezscrex="false"' . self::OPTIMIZER_OPT_OUT_ATTRS . ' async>'.$this->snippet.'</script>';
        }
    }

    /**
     * Determines whether the given URL is an affiliate link based on known domain patterns.
     *
     * @param string $api_key      The API key used to retrieve domain patterns from the WeCanTrack API.
     * @param string $original_url The URL to check against known affiliate domains and patterns.
     *
     * @return bool True if the URL matches a known affiliate domain or pattern; false otherwise.
     */
    public static function is_affiliate_link($api_key, $original_url) {
        $patterns = self::wecantrack_get_domain_patterns($api_key);
        if (!$patterns) return false; // do not perform Clickout api if the pattern isn't in yet

        if (!isset($patterns['origins'])) return false;

        preg_match('~^(https?:\/\/)([^?\&\/\ ]+)~', $original_url, $matches);

        if (empty($matches[1])) {
            // relative URLs are not faulty but are not affiliate links
            if (ltrim($original_url)[0] !== '/') {
                error_log('[WeCanTrack] tried to parse a faulty URL: '.$original_url);
                return false;
            }
        }

        if (!empty($matches[2])) {
            $matches[2] = "//{$matches[2]}";
            // search if domain key matches to the origin keys
            if (isset($patterns['origins'][$matches[2]])) {
                return true;
            }
            // backup for www prefixes
            if (isset($patterns['origins'][str_replace('www.', '', $matches[2])])) {
                return true;
            }
        }

        // check if the full url matches to any regex patterns
        foreach($patterns['regexOrigins'] as $pattern) {
            if (preg_match("~{$pattern}~", $original_url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the full site URL of the current request.
     *
     * Combines WordPress's `home_url()` with the current request URI to produce
     * the full URL of the page being accessed.
     *
     * @return string The full site URL of the current request.
     */
    private static function get_site_url() {
        return home_url().$_SERVER['REQUEST_URI'];
    }

    /**
     * Modifies the original affiliate URL by sending tracking data to the WeCanTrack API.
     *
     * @param string $original_affiliate_url The original affiliate URL to be potentially modified.
     * @param string $api_key                The API key used to authenticate with the WeCanTrack API.
     * @param array  $options                Optional. Additional options for future use (currently unused).
     *
     * @return string The modified affiliate URL returned by the API, or the original URL on failure.
     */
    private static function get_modified_affiliate_url($original_affiliate_url, $api_key, $options = [])
    {
        try {
            self::set_no_cache_headers();

            // wecantrack will not process bots
            if (!isset($_SERVER['HTTP_USER_AGENT']) || WecantrackHelper::useragent_is_bot($_SERVER['HTTP_USER_AGENT'])) {
                return $original_affiliate_url;
            }

            $wctCookie = !empty($_COOKIE['_wctrck']) ? sanitize_text_field($_COOKIE['_wctrck']) : null;
            $wctCookie = !$wctCookie && !empty($_GET['data']) && strlen($_GET['data']) > 50
                ? sanitize_text_field($_GET['data']) : $wctCookie;

            $post_data = [
                'affiliate_url' => rawurlencode($original_affiliate_url),
                'clickout_url' => self::get_clickout_url(),
                'redirect_url' => self::current_url(),
                '_ga' => !empty($_COOKIE['_ga']) ? sanitize_text_field($_COOKIE['_ga']) : null,
                '_wctrck' => $wctCookie,
                'ua' => sanitize_text_field($_SERVER['HTTP_USER_AGENT']),
                'ip' => self::get_user_real_ip(),
            ];

            $response = wp_remote_post(WECANTRACK_API_BASE_URL . '/api/v1/clickout', [
                'timeout' => self::CURL_TIMEOUT_S,
                'headers' => [
                    'x-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($post_data),
                'sslverify' => WecantrackHelper::get_sslverify_option()
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code != 200) {
                throw new Exception('wecantrack request did not return status 200');
            }
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response);

            if (empty($response)) {
                throw new Exception('Empty response received from the API');
            }

            if ($response->affiliate_url) {
                return rawurldecode($response->affiliate_url);
            }

        } catch (Exception $e) {
            if (empty($post_data)) {
                $post_data = [];
            }

            if (empty($response)) {
                $response = null;
            }

            $error_msg = [
                'e_msg' => $e->getMessage(),
                'post_data' => $post_data,
                'response' => $response
            ];

            error_log('[WeCanTrack] Clickout API exception: '.json_encode($error_msg));
        }

        return $original_affiliate_url;
    }

    /**
     * Retrieves the most relevant referrer URL for clickout tracking.
     *
     * @param bool $check_referrer_cookie Optional. Whether to check referrer cookies as a fallback. Default true.
     * @return string|null The resolved clickout URL, or null if none found.
     */
    private static function get_clickout_url($check_referrer_cookie = true) {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            if (preg_match("~^https?:\/\/[^.]+\.(?:facebook|youtube)\.com~i", $_SERVER['HTTP_REFERER'])) {
                return self::get_site_url();
            } else {
                return $_SERVER['HTTP_REFERER'];
            }
        } else {
            if ($check_referrer_cookie) {
                if (!empty($_COOKIE['_wct_http_referrer_1'])) {
                    return urldecode($_COOKIE['_wct_http_referrer_1']);
                } else if (!empty($_COOKIE['_wct_http_referrer_2'])) {
                    return urldecode($_COOKIE['_wct_http_referrer_2']);
                }
            }
        }
        return null;
    }

    /**
     * Gets the real user IP
     *
     * @return string
     */
    private static function get_user_real_ip()
    {
        $ip_headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($ip_headers as $header) {
            if (array_key_exists($header, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$header])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Retrieves domain patterns from the WeCanTrack API or cache.
     * @param string  $api_key       The API key used to authenticate with the WeCanTrack API.
     * @param bool    $forceRefresh  Optional. Whether to force a refresh from the API regardless of cache. Default false.
     *
     * @return array|false Returns an associative array containing domain patterns (must include `origins` key) on success,
     *                     or false on failure.
     */
    private static function wecantrack_get_domain_patterns($api_key, $forceRefresh = false) {
        try {
            $domain_patterns = maybe_unserialize(get_option('wecantrack_domain_patterns'));
            $wecantrack_fetch_expiration = (int) get_option('wecantrack_fetch_expiration');

            $expired = !$wecantrack_fetch_expiration || time() > $wecantrack_fetch_expiration;

            if ($expired || !isset($domain_patterns['origins']) || $forceRefresh) {
                $response = wp_remote_get(WECANTRACK_API_BASE_URL . '/api/v1/domain_patterns', [
                    'headers' => [
                        'x-api-key' => $api_key,
                    ],
                    'sslverify' => WecantrackHelper::get_sslverify_option()
                ]);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $status = wp_remote_retrieve_response_code($response);
                if ($status == 200) {
                    $domain_patterns = json_decode(wp_remote_retrieve_body($response), true);
                    if (!isset($domain_patterns['origins'])) {
                        throw new Exception('Response missing data');
                    }
                    update_option('wecantrack_domain_patterns', serialize($domain_patterns));
                    update_option('wecantrack_fetch_expiration', strtotime("+".self::FETCH_DOMAIN_PATTERN_IN_HOURS." hours"));
                } else {
                    throw new Exception('Invalid response');
                }
            } else {
                return $domain_patterns;
            }

        } catch (Exception $e) {
            $error_msg = [
                'e_msg' => $e->getMessage()
            ];
            error_log('[WeCanTrack] wecantrack_update_data_fetch() exception: ' . json_encode($error_msg));
            update_option('wecantrack_domain_patterns', NULL);// maybe something went wrong with maybe_unserialize(), so clear it
            return false;
        }

        return $domain_patterns;
    }

    /**
     * Determines whether the session enabler feature is active.
     * 
     * If active: Enables plugin functionality for this session if the user visits a URL containing a special keyword.
     *
     * This method checks the `wecantrack_session_enabler` option from the database.
     * If it's set and the current request URI contains the configured test URL, the plugin
     * sets a session variable to enable tracking for the current session.
     *
     * Starts the PHP session if it's not already active.
     *
     * @return bool True if session enabler is active for the current session; false otherwise.
     */
    private function session_enabler_is_turned_on()
    {
        // check if session enabler is on
        if (!$test_url = get_option('wecantrack_session_enabler')) {
            return false;
        }

        // debugging ON (performance hit) - this only happens when the plugin is turned off and session enabler is on
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        // session enabler is the "turn on plugin for session if url contains x"
        $has_session_enabler = !empty($_SESSION['wecantrack_session_enabler']) && $_SESSION['wecantrack_session_enabler'] === 'on';
        if ($has_session_enabler) {
            return true;
        }

        if (strpos($_SERVER['REQUEST_URI'], $test_url) !== false) {
            $_SESSION['wecantrack_session_enabler'] = 'on';
            return true;
        }

        return false;
    }

    /**
     * Sets or updates the HTTP referrer cookies.
     *
     * If `$this->drop_referrer_cookie` is true, this method:
     * - Copies the current value of `_wct_http_referrer_1` into `_wct_http_referrer_2` (as a backup).
     * - Sets `_wct_http_referrer_1` to the current URL, valid for 4 hours.
     *
     * This is used to track the referrer chain across page visits.
     *
     * @return void
     */
    private function set_http_referrer()
    {
        if ($this->drop_referrer_cookie) {
            $four_hours_from_now = time() + 14400;

            if (!empty($_COOKIE['_wct_http_referrer_1'])) {
                $_COOKIE['_wct_http_referrer_2'] = $_COOKIE['_wct_http_referrer_1'];
                setcookie(
                    '_wct_http_referrer_2', 
                    $_COOKIE['_wct_http_referrer_1'], $four_hours_from_now, 
                    '/'
                );
            }
            $_COOKIE['_wct_http_referrer_1'] = self::current_url();
            setcookie(
                '_wct_http_referrer_1', 
                $_COOKIE['_wct_http_referrer_1'],
                $four_hours_from_now,
                '/'
            );
        }
    }

    /**
     * Restores the primary HTTP referrer cookie using the secondary referrer value.
     *
     * If the `$drop_referrer_cookie` flag is true and the `_wct_http_referrer_2` cookie is set,
     * this method sets the `_wct_http_referrer_1` cookie to the same value, with a 4-hour expiry.
     *
     * @param bool $drop_referrer_cookie Whether the referrer cookie logic should be executed.
     * @return void
     */
    public static function revert_http_referrer($drop_referrer_cookie = true)
    {
        if ($drop_referrer_cookie) {
            if (!empty($_COOKIE['_wct_http_referrer_2'])) {
                setcookie('_wct_http_referrer_1', $_COOKIE['_wct_http_referrer_2'], time()+60*60*4, '/');
            }
        }
    }

    /**
     * Deletes HTTP referrer cookies if they match the given site URL.
     *
     * This method checks if the `_wct_http_referrer_1` or `_wct_http_referrer_2` cookies 
     * are set and match the provided `$site_url`. If so, it clears those cookies.
     * 
     * If `_wct_http_referrer_2` does not match but `_wct_http_referrer_1` is already unset,
     * it calls `revertHttpReferrer()` as a fallback mechanism.
     *
     * @param string $site_url The site URL to compare against stored referrer cookies.
     * @return void
     */
    private function delete_http_referrer_where_site_url($site_url)
    {
        if ($this->drop_referrer_cookie) {
            if (!empty($_COOKIE['_wct_http_referrer_1']) && $_COOKIE['_wct_http_referrer_1'] == $site_url) {
                $_COOKIE['_wct_http_referrer_1'] = null;
                setcookie('_wct_http_referrer_1', '', time() - 3600);
            }

            if (!empty($_COOKIE['_wct_http_referrer_2']) && $_COOKIE['_wct_http_referrer_2'] == $site_url) {
                $_COOKIE['_wct_http_referrer_2'] = null;
                setcookie('_wct_http_referrer_2', '', time() - 3600);
            } else {
                if (empty($_COOKIE['_wct_http_referrer_1'])) {
                    self::revert_http_referrer();
                }
            }
        }
    }
}