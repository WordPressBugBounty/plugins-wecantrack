<?php
if (!defined('ABSPATH')) { exit; }

require_once WECANTRACK_PATH . '/includes/WecantrackPermissions.php';

/**
 * Class WecantrackAdmin
 *
 * Handles the admin-side functionality of the Wecantrack plugin.
 * Only instantiated in the WordPress admin environment.
 *
 * @package Wecantrack
 */
class WecantrackAdmin {
    const CURL_TIMEOUT = 5;
    
    protected WecantrackPermissions $wecantrack_permissions;

    /**
     * Initializes the WecantrackAdmin class.
     *
     * - Runs migration checks to ensure required options exist.
     * - Registers WordPress admin hooks and AJAX handlers.
     * - Initializes the WecantrackPermissions handler.
     * - If an API key is present and the plugin version has changed, updates
     *   the user's tracking code and website information.
     *
     * Handles errors silently by logging them to the PHP error log.
     */
    public function __construct()
    {
        $this->check_migrations();
        $this->load_hooks();

        $this->wecantrack_permissions = new WecantrackPermissions();

        $version = get_option('wecantrack_version');
        if ($api_key = get_option('wecantrack_api_key')) {
            if (empty($version) || $version !== WECANTRACK_VERSION) {
                try {
                    WecantrackHelper::refresh_config_with_candidates($api_key, WecantrackHelper::get_candidate_site_urls());
                } catch (\Exception $e) {
                    error_log('WecantrackAdmin update_tracking_code error: ' . $e->getMessage());
                }

                update_option('wecantrack_version', WECANTRACK_VERSION);
            }
        }
    }

    /**
     * Registers admin-related WordPress hooks and AJAX actions.
     *
     * @return void
     */
    public function load_hooks()
    {
        add_action('admin_menu', [$this, 'admin_menu']);

        //when a form is submitted to admin-ajax.php
        add_action('wp_ajax_wecantrack_form_response', [$this, 'the_form_response']);
        add_action('wp_ajax_wecantrack_advanced_settings_response', [$this, 'advanced_settings_response']);

        if (!empty($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['wecantrack', 'wecantrack-redirect-page', 'wecantrack-advanced-settings'])) {
            add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        }
    }

    /**
     * Because WordPress does not provide a built-in hook for detecting plugin updates, we check on each admin load to ensure required options exist and migrations are applied.
     */
    public function check_migrations() {
        $required = [
            'wecantrack_api_key'                => null,
            'wecantrack_plugin_status'          => 0,
            'wecantrack_fetch_expiration'       => null,
            'wecantrack_snippet'                => null,
            'wecantrack_session_enabler'        => null,
            'wecantrack_snippet_version'        => null,
            'wecantrack_domain_patterns'        => null,
            'wecantrack_custom_redirect_html'   => null,
            'wecantrack_redirect_options'       => null,
            'wecantrack_website_options'        => null,
            'wecantrack_version'                => null,
            'wecantrack_storage'                => null,
            'wecantrack_referrer_cookie_status' => 0,
            'wecantrack_website_override'       => null,
        ];

        global $wpdb;
        $existing = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wecantrack_%'"
        );

        $missing = array_diff_key($required, array_flip($existing));

        if (!empty($missing)) {
            foreach ($missing as $option => $default) {
                // Preserve any value still held in object cache (e.g. Redis)
                $cached = get_option($option);
                $value = ($cached !== false) ? $cached : $default;
                wp_cache_delete($option, 'options');
                wp_cache_delete('alloptions', 'options');
                add_option($option, $value);
            }
        }
    }

    /**
     * Handles the AJAX request for the main settings form submission.
     *
     * Validates permissions and nonce, retrieves user data from the Wecantrack API,
     * updates tracking code and website information, and stores plugin settings.
     *
     * Responds with JSON success or error message based on the process result.
     *
     * @return void Outputs JSON response and terminates script execution.
     */
    public function the_form_response()
    {
        $this->wecantrack_permissions->require_admin_access();
        $this->wecantrack_permissions->nonce_check();

        $userInput = wp_unslash($_POST);

        $api_key = sanitize_text_field($userInput['wecantrack_api_key']);
        $data = self::get_user_information($api_key);

        if (!empty($data['error'])) {
            wp_send_json_error($data);
        }

        // Optional website chosen from the dropdown when home_url() isn't registered (e.g. staging).
        $posted_override = isset($userInput['wecantrack_website_override'])
            ? esc_url_raw($userInput['wecantrack_website_override'])
            : null;

        $candidates = WecantrackHelper::get_candidate_site_urls($posted_override, $data['websites'] ?? null);

        try {
            WecantrackHelper::refresh_config_with_candidates($api_key, $candidates);
            $data['has_website'] = true;
        } catch (\Exception $e) {
            $data['has_website'] = false;
            error_log('[WeCanTrack] the_form_response() e_msg:'.$e->getMessage());
        }

        if (sanitize_text_field($userInput['wecantrack_submit_type']) === 'verify') {// store just api key
            update_option('wecantrack_api_key', $api_key);
        } else {// store everything
            // strip slashes to unescape to get valid JS
            update_option('wecantrack_plugin_status', sanitize_text_field($userInput['wecantrack_plugin_status']));
            update_option('wecantrack_session_enabler', sanitize_text_field($userInput['wecantrack_session_enabler']));
        }

        // Clear known caches to ensure the updated JS snippet is served to users immediately
        wecantrack_clear_all_known_caches();

        return wp_send_json_success($data);
    }

    /**
     * Handles the AJAX request for advanced settings form submission.
     *
     * Updates plugin storage options for SSL, script inclusion, and referrer cookie settings.
     * Responds with JSON success message.
     *
     * @return void
     */
    public function advanced_settings_response() {
        $this->wecantrack_permissions->require_admin_access();
        $this->wecantrack_permissions->nonce_check();

        $userInput = wp_unslash($_POST);

        $storage = json_decode(get_option('wecantrack_storage'), true);
        if (!$storage) {
            $storage = [];
        }

        $referrer_cookie_status = filter_var($userInput['wecantrack_referrer_cookie_status'], FILTER_VALIDATE_BOOLEAN);
        $disable_ssl = filter_var($userInput['wecantrack_ssl_disabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $include_script = filter_var($userInput['wecantrack_include_script'], FILTER_VALIDATE_BOOLEAN);

        $storage['disable_ssl'] = $disable_ssl;
        $storage['include_script'] = $include_script;
        $storage['can_redirect_through_parameter'] = false;

        update_option('wecantrack_storage', json_encode($storage));
        update_option('wecantrack_referrer_cookie_status', $referrer_cookie_status);

        return wp_send_json_success(['msg' => 'ok']);
    }

    /**
     * Registers admin menu and submenu pages for the plugin.
     *
     * @return void
     */
    public function admin_menu()
    {
        add_menu_page(
            'WeCanTrack > Settings',
            'WeCanTrack',
            'manage_options',
            'wecantrack',
            [$this, 'settings'],
            WECANTRACK_URL . '/images/favicon.png',
            99
        );

        add_submenu_page(
            'wecantrack',
            'WeCanTrack > Redirect Page',
            'Redirect Page',
            'manage_options',
            'wecantrack-redirect-page',
            [$this, 'redirect_page']
        );
    
        add_submenu_page(
            'wecantrack',
            'WeCanTrack > Advanced Settings',
            'Settings',
            'manage_options',
            'wecantrack-advanced-settings',
            [$this, 'advanced_settings']
        );
    }

    /**
     * Renders the main WeCanTrack settings page in the WordPress admin.
     *
     * Displays the form for entering the API key and enabling plugin features.
     * Prevents access if the current user lacks required capabilities.
     *
     * @return void
     */
    public function settings()
    {
        if (! $this->wecantrack_permissions->current_user_can_manage_options()) {
            require WECANTRACK_PATH . '/views/unauthorized.php';
            return;
        }

        require_once WECANTRACK_PATH . '/views/settings.php';
    }

    /**
     * @deprecated This page will be removed in a future version.
     * 
     * Renders the redirect page configuration view in the WordPress admin.
     *
     * Used to manage and preview redirect behavior for affiliate links.
     * Prevents access if the current user lacks required capabilities.
     *
     * @return void
     */
    public function redirect_page()
    {
        if (! $this->wecantrack_permissions->current_user_can_manage_options()) {
            require WECANTRACK_PATH . '/views/unauthorized.php';
            return;
        }

        // Make $table available in the view
        include WECANTRACK_PATH . '/views/redirect_page.php';
    }

    /**
     * Renders the advanced settings page for the WeCanTrack plugin.
     *
     * Allows configuration of script injection, SSL, redirect parameters,
     * and referrer cookie behavior.
     * Prevents access if the current user lacks required capabilities.
     *
     * @return void
     */
    public function advanced_settings()
    {
        if (! $this->wecantrack_permissions->current_user_can_manage_options()) {
            require WECANTRACK_PATH . '/views/unauthorized.php';
            return;
        }

        require_once WECANTRACK_PATH . '/views/advanced_settings.php';
    }

    /**
     * Validates whether a given domain string is a properly formatted domain name.
     *
     * @param string $domain The domain name to validate.
     * @return bool True if valid, false otherwise.
     */
    public function is_valid_domain($domain) {
        $domain = strtolower(trim($domain));
        // Must contain at least one dot and no scheme
        return preg_match('/^(?!:\/\/)([a-z0-9-]+\.)+[a-z]{2,}$/i', $domain);
    }

    /**
     * Enqueues CSS and JavaScript files for the Wecantrack admin pages.
     *
     * Registers and loads scripts and styles based on the current admin page.
     * Also localizes translation strings and settings for use in JavaScript.
     *
     * @return void
     */
    public function enqueue_scripts()
    {
        $site_url = home_url();
        $wecantrack_version = WECANTRACK_VERSION;

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            $wecantrack_version = time(); // Use current timestamp for dev mode to not cache the assets
        }

        $params = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'site_url' => $site_url,
            'lang_request_wrong' => esc_html__('Something went wrong with the request', 'wecantrack'),
            'lang_added_one_active_network' => esc_html__('Added at least 1 active network account', 'wecantrack'),
            'lang_not_added_one_active_network' => esc_html__('You have not added at least 1 active network account. To add a network, click here.', 'wecantrack'),
            // translators: %s: The website URL
            'lang_website_added' => sprintf(esc_html__('Website %s added', 'wecantrack'), $site_url),
            // translators: %s: The website URL
            'lang_website_not_added' => sprintf(esc_html__('You have not added the website %s to our platform. To add the website, click here.', 'wecantrack'), $site_url),
            'lang_verified' => esc_html__('verified', 'wecantrack'),
            'lang_invalid_api_key' => esc_html__('Invalid API Key', 'wecantrack'),
            'lang_invalid_request' => esc_html__('Invalid Request', 'wecantrack'),
            'lang_valid_api_key' => esc_html__('Valid API Key', 'wecantrack'),
            'lang_changes_saved' => esc_html__('Your changes have been saved', 'wecantrack'),
            'lang_something_went_wrong' => esc_html__('Something went wrong.', 'wecantrack'),
        ];

        wp_register_style('wecantrack_admin_css', WECANTRACK_URL.'/css/admin.css', [], $wecantrack_version);
        wp_enqueue_style('wecantrack_admin_css');

        $page = sanitize_key($_GET['page'] ?? '');
        switch ($page) {
            case 'wecantrack':
                wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/admin.js', [], $wecantrack_version, false);
                wp_localize_script( 'wecantrack_admin_js', 'wecantrackParams', $params);
                break;
            case 'wecantrack-redirect-page':
                wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/redirect_page.js', [], $wecantrack_version, false);
                wp_localize_script( 'wecantrack_admin_js', 'wecantrackParams', $params);
                break;
            case 'wecantrack-advanced-settings':
                wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/advanced_settings.js', [], $wecantrack_version, false);
                wp_localize_script( 'wecantrack_admin_js', 'wecantrackParams', $params);
                break;
        }
    }

    /**
     * Retrieves user information from the Wecantrack API.
     *
     * Sends a GET request to the Wecantrack API to fetch user-related data,
     * which is used to determine the onboarding status and settings.
     *
     * @param string $api_key The Wecantrack API key.
     * @return array Returns an associative array with user information or an 'error' key on failure.
     */
    public static function get_user_information($api_key)
    {
        try {
            $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/information';
            $response = wp_remote_get($api_url, [
                'timeout' => 10,
                'headers' => [
                    'x-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                    'x-wp-version' => WECANTRACK_VERSION
                ],
                'sslverify' => WecantrackHelper::get_sslverify_option()
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $response = wp_remote_retrieve_body($response);
            return json_decode($response, true);
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}