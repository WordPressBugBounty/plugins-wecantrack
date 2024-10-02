<?php

// todo wct.js proxy wp-cron update

/**
 * Class WecantrackApp
 *
 * Public class
 */
class WecantrackApp {
    const CURL_TIMEOUT_S = 5, FETCH_DOMAIN_PATTERN_IN_HOURS = 3;

    private $api_key, $drop_referrer_cookie;
    protected $redirectPageObj;
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

            $this->redirectPageObj = new WecantrackAppRedirectPage($this->drop_referrer_cookie, $this->snippet);

            // link parameter redirect only happens from the RedirectPage class. We do this because we do not want to do another clickout request
            if ($this->redirectPageObj->current_url_is_redirect_page_endpoint() && !empty($_GET['link'])) {
                if ($this->redirectPageObj->redirect_option_status_is_enabled()) {
                    if (self::is_affiliate_link($this->api_key, $_GET['link'])) {
                        WecantrackApp::set_no_cache_headers();
                        header('X-Robots-Tag: noindex', true);
                        self::setRedirectHeader($_GET['link']);
                        exit;
                    }
                }
            }

            if (!empty($_GET['data']) && !empty($_GET['afflink'])) {
                if (! $this->can_redirect_through_parameter()) {
                    return;
                } 

                //simple wct param validation
                if (strlen($_GET['data']) > 50 && substr($_GET['afflink'], 0, 4) === 'http') {
                    $this->parameter_redirect($_GET['afflink']);
                }
            } else {
                $this->load_hooks();
            }

            if ($this->drop_referrer_cookie) {
                $this->setHttpReferrer();
            }
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * Responsible for checking if the website can redirect through &afflink parameter. (afflink is default)
     * Shouldn't be used if auto tagging is enabled.
     * 
     * @return bool 
     */
    private function can_redirect_through_parameter() : bool {
        if (! isset($this->options_storage['can_redirect_through_parameter']) || $this->options_storage['can_redirect_through_parameter'] === 1) {
            return true;
        }

        // if type=session is not found in the snippet, then we have to redirect through parameter
        // else we might break the redirects
        if ($this->snippet && strpos($this->snippet, 'type=session') === false) {
            return true;
        }

        return false;
    }

    private static function if_debug_show_plugin_config() {
        if (isset($_GET['_wct_config']) && $_GET['_wct_config'] === md5(date('Y-m-d'))) {
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
                        WecantrackHelper::update_tracking_code($api_key, $domainURL);
                        $extra['update_tracking_code'] = true;
                    } catch (\Exception $e) {
                        $extra['update_tracking_code'] = false;
                    }

                    WecantrackHelper::update_user_website_information($api_key, $domainURL);
                    WecantrackApp::wecantrack_get_domain_patterns($api_key, true);

                    $refreshed = 1;
                }
                set_transient('wecantrack_lock_cache_refresh', 1, 10);
            }

            echo json_encode([
                'v' => WECANTRACK_VERSION,
                'status' => get_option('wecantrack_plugin_status'),
                'r_status' => get_option('wecantrack_redirect_status'),
                'r_options' => unserialize(get_option('wecantrack_redirect_options')),
                'f_exp' => get_option('wecantrack_fetch_expiration'),
                's_version' => get_option('wecantrack_fetch_expiration'),
                'sess_e' => get_option('wecantrack_session_enabler'),
                'snippet_v' => get_option('wecantrack_snippet_version'),
                'snippet' => get_option('wecantrack_snippet'),
                'refreshed' => $refreshed,
                'patterns' => unserialize(get_option('wecantrack_domain_patterns')),
                'extra' => $extra
            ]);

            exit;
        }

    }

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
     * Redirect to afflink URL if parameters are available
     * @param $link
     */
    public function parameter_redirect($link) {
        if (preg_match("/^https?%3A/", $link)) {
            $link = urldecode($link);
        }
        if (self::is_affiliate_link($this->api_key, $link)) {
            $link = $this->get_modified_affiliate_url($link, $this->api_key); //clickout request
            if ($this->redirectPageObj->redirect_page_is_enabled()) {
                $this->redirectPageObj->redirect_through_page($link);
                exit;
            }
        }

        header('X-Robots-Tag: noindex', true);
        self::setRedirectHeader($link);
        exit;
    }

    private static function setRedirectHeader($link, $code = 301) {
        header('Location: '.$link, true, $code);
    }

    public static function set_no_cache_headers() {
        header('Cache-Control: no-store, no-cache, max-age=0');//HTTP 1.1
        header('Pragma: no-cache');//HTTP 1.0
    }

    public function load_hooks() {
        add_filter('wp_redirect', array($this, 'redirect_default'), 99);

        if (!isset($this->options_storage['include_script']) || $this->options_storage['include_script'] === true) {
            add_action('wp_head', array($this, 'insert_snippet'));
        }
    }

    public function redirect_default($location) {
        self::delete_http_referrer_where_site_url(self::current_url());

        if (!self::is_affiliate_link($this->api_key, $location)) {
            return $location;
        }

        $modified_url = self::get_modified_affiliate_url($location, $this->api_key, ['ignore_current_clickout_url' => true]);
        $location = $location != $modified_url ? $modified_url : $location;

        if ($this->redirectPageObj->redirect_page_is_enabled()) {
            $this->redirectPageObj->redirect_through_page($location);//redirect_page will be used if enabled
            exit;
        }

        return $location;

    }

    /**
     * Inserts the WCT Snippet with preload tag.
     */
    public function insert_snippet() {
        if (! $this->snippet) {
            return;
        }

        preg_match('/s\.src ?= ?\'([^\']+)/', $this->snippet, $scriptSrcStringmatch);
        if (!empty($scriptSrcStringmatch[1])) {
            echo '<link rel="preload" href="'.$scriptSrcStringmatch[1].'" as="script">';
            echo '<script type="text/javascript" data-ezscrex="false" async>'.$this->snippet.'</script>';
        }
    }

    /**
     * Checks if URL is an affiliate link
     *
     * @param $api_key
     * @param $original_url
     * @return bool
     */
    public static function is_affiliate_link($api_key, $original_url) {
        $patterns = self::wecantrack_get_domain_patterns($api_key);
        if (!$patterns) return false; // do not perform Clickout api if the pattern isn't in yet

        if (!isset($patterns['origins'])) return true;

        preg_match('~^(https?:\/\/)([^?\&\/\ ]+)~', $original_url, $matches);

        if (empty($matches[1])) {
            // relative URLs are not faulty but are not affiliate links
            if (ltrim($original_url)[0] !== '/') {
                error_log('WeCanTrack Plugin tried to parse a faulty URL: '.$original_url);
                return false;
            }
        }

        if (!empty($matches[2])) {
            $matches[2] = '//' . $matches[2];
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
            if (preg_match('~'.$pattern.'~', $original_url)) {
                return true;
            }
        }

        return false;
    }

    private static function get_site_url() {
        return home_url().$_SERVER['REQUEST_URI'];
    }

    /**
     * Gets the new tracking URL from wecantrack.com, we will use this link to redirect the user to
     *
     * @param $original_affiliate_url
     * @param $api_key
     * @param array $options
     * @return string
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

            $post_data = array(
                'affiliate_url' => rawurlencode($original_affiliate_url),
                'clickout_url' => self::get_clickout_url(),
                'redirect_url' => self::current_url(),
                '_ga' => !empty($_COOKIE['_ga']) ? sanitize_text_field($_COOKIE['_ga']) : null,
                '_wctrck' => $wctCookie,
                'ua' => $_SERVER['HTTP_USER_AGENT'],
                'ip' => self::get_user_real_ip(),
            );

            $response = wp_remote_post(WECANTRACK_API_BASE_URL . '/api/v1/clickout', array(
                'timeout' => self::CURL_TIMEOUT_S,
                'headers' => array(
                    'x-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($post_data),
                'sslverify' => WecantrackHelper::get_sslverify_option()
            ));

            $code = wp_remote_retrieve_response_code($response);
            if ($code != 200) {
                throw new Exception('wecantrack request did not return status 200');
            }
            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response);

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

            error_log('WeCanTrack Plugin Clickout API exception: '.json_encode($error_msg));
        }

        return $original_affiliate_url;
    }

    /**
     * Get Clickout URL
     *
     * @return string
     */
    private static function get_clickout_url($check_referrer_cookie = true) {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            if (preg_match("~^https?:\/\/[^.]+\.(?:facebook|youtube)\.com~i", $_SERVER['HTTP_REFERER'])) {
                return self::get_site_url(); //todo unsure about this, doesn't redirect_url take care of this?
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
        $ip_headers = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
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
     * We cache the affiliate url patterns so that we do not have to send every URL to the WeCanTrack API
     * @param $api_key
     * @param bool $forceRefresh
     * @return bool|mixed|void|null
     */
    private static function wecantrack_get_domain_patterns($api_key, $forceRefresh = false) {
        try {
            $domain_patterns = unserialize(get_option('wecantrack_domain_patterns'));
            $wecantrack_fetch_expiration = (int) get_option('wecantrack_fetch_expiration');

            $expired = !$wecantrack_fetch_expiration || time() > $wecantrack_fetch_expiration;

            if ($expired || !isset($domain_patterns['origins']) || $forceRefresh) {
                $response = wp_remote_get(WECANTRACK_API_BASE_URL . '/api/v1/domain_patterns?api_key=' . $api_key, array(
                    'sslverify' => WecantrackHelper::get_sslverify_option()
                ));

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
            error_log('WeCanTrack Plugin wecantrack_update_data_fetch() exception: ' . json_encode($error_msg));
            update_option('wecantrack_domain_patterns', NULL);// maybe something went wrong with unserialize(), so clear it
            return false;
        }

        return $domain_patterns;
    }

    private function session_enabler_is_turned_on()
    {
        // check if session enabler is on
        if (!$test_url = get_option('wecantrack_session_enabler')) {
            return false;
        }

        // debugging ON (performance hit) - this only happens when the plugin is turned off and session enabler is on
        if (!session_id()) {
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

    // setHttpReferrer in the cookies,  fallback for users isn't available
    private function setHttpReferrer()
    {
        if ($this->drop_referrer_cookie) {
            if (!empty($_COOKIE['_wct_http_referrer_1'])) {
                $_COOKIE['_wct_http_referrer_2'] = $_COOKIE['_wct_http_referrer_1'];
                setcookie('_wct_http_referrer_2', $_COOKIE['_wct_http_referrer_1'], time()+60*60*4, '/');
            }
            $_COOKIE['_wct_http_referrer_1'] = self::current_url();
            setcookie('_wct_http_referrer_1', $_COOKIE['_wct_http_referrer_1'], time()+60*60*4, '/');
        }
    }

    public static function revertHttpReferrer($drop_referrer_cookie = true)
    {
        if ($drop_referrer_cookie) {
            if (!empty($_COOKIE['_wct_http_referrer_2'])) {
                setcookie('_wct_http_referrer_1', $_COOKIE['_wct_http_referrer_2'], time()+60*60*4, '/');
            }
        }
    }

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
                if (!$_COOKIE['_wct_http_referrer_1']) {
                    self::revertHttpReferrer();
                }
            }
        }
    }
}