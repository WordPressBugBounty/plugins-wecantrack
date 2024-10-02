<?php

// todo look into multisite implementation

/**
 * Class WecantrackAdmin
 *
 * Admin class
 */
class WecantrackAdmin {

    const CURL_TIMEOUT = 5;

    /**
     * WecantrackAdmin constructor.
     */
    public function __construct()
    {
        $this->check_migrations();
        $this->load_hooks();

        $version = get_option('wecantrack_version');
        if ($api_key = get_option('wecantrack_api_key')) {
            if (empty($version) || $version !== WECANTRACK_VERSION) {
                $domainURL = home_url();

                try {
                    WecantrackHelper::update_tracking_code($api_key, $domainURL);
                } catch (\Exception $e) {}

                WecantrackHelper::update_user_website_information($api_key, $domainURL);
                update_option('wecantrack_version', WECANTRACK_VERSION);
            }
        }
    }

    public function load_hooks()
    {
        add_action('admin_menu', array($this, 'admin_menu'));

        //when a form is submitted to admin-ajax.php
        add_action('wp_ajax_wecantrack_form_response', array($this, 'the_form_response'));
        add_action('wp_ajax_wecantrack_redirect_page_form_response', array($this, 'redirect_page_form_response'));
        add_action('wp_ajax_wecantrack_get_snippet', array($this, 'get_snippet'));
        add_action('wp_ajax_wecantrack_advanced_settings_response', array($this, 'advanced_settings_response'));

        if (!empty($_GET['page']) && in_array(sanitize_text_field($_GET['page']), ['wecantrack', 'wecantrack-redirect-page', 'wecantrack-advanced-settings'])) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        }
    }

    /**
     * Because WP doesn't have a updating hook we have to check on every admin load to see if our options are available.
     */
    public function check_migrations() {
        if(!get_option('wecantrack_custom_redirect_html')) {
            add_option('wecantrack_custom_redirect_html');
        }
        if(!get_option('wecantrack_redirect_options')) {
            add_option('wecantrack_redirect_options');
        }
        if(!get_option('wecantrack_website_options')) {
            add_option('wecantrack_website_options');
        }
        if(!get_option('wecantrack_version')) {
            add_option('wecantrack_version');
        }
        if(!get_option('wecantrack_referrer_cookie_status')) {
            add_option('wecantrack_referrer_cookie_status', 1);
        }
        if(!get_option('wecantrack_storage')) {
            add_option('wecantrack_storage');
        }
    }

    /**
     *  AJAX form response
     */
    public function the_form_response()
    {
        WecantrackHelper::nonce_check($_POST['wecantrack_form_nonce']);

        $api_key = sanitize_text_field($_POST['wecantrack_api_key']);
        $data = self::get_user_information($api_key);

        if (!empty($data['error'])) {
            echo json_encode($data);
            wp_die();
        }

        $domainURL = home_url();

        try {
            WecantrackHelper::update_tracking_code($api_key, $domainURL);
            $data['has_website'] = true;
        } catch (\Exception $e) {
            $data['has_website'] = false;
            error_log('WCT Plugin: the_form_response() e_msg:'.$e->getMessage());
        }

        WecantrackHelper::update_user_website_information($api_key, $domainURL);

        if (sanitize_text_field($_POST['wecantrack_submit_type']) == 'verify') {// store just api key
            update_option('wecantrack_api_key', $api_key);
        } else {// store everything
            // strip slashes to unescape to get valid JS
            update_option('wecantrack_plugin_status', sanitize_text_field($_POST['wecantrack_plugin_status']));
            update_option('wecantrack_session_enabler', sanitize_text_field($_POST['wecantrack_session_enabler']));
        }

        echo json_encode($data);
        wp_die();
    }


    /**
     *  AJAX form response
     */
    public function advanced_settings_response() {
        WecantrackHelper::nonce_check($_POST['wecantrack_form_nonce']);
        $storage = json_decode(get_option('wecantrack_storage'), true);
        if (!$storage) {
            $storage = [];
        }

        $referrer_cookie_status = sanitize_text_field($_POST['wecantrack_referrer_cookie_status']);
        $ssl_status = sanitize_text_field($_POST['wecantrack_ssl_status']);
        $include_script = sanitize_text_field($_POST['wecantrack_include_script']);
        $can_redirect_through_parameter = sanitize_text_field($_POST['wecantrack_can_redirect_through_parameter']);

        if ($ssl_status == 0) {
            $storage['disable_ssl'] = true;
        } else if ($ssl_status == 1) {
            unset($storage['disable_ssl']);
        }

        if ($include_script == 1) {
            $storage['include_script'] = true;
        } else if ($include_script == 0) {
            $storage['include_script'] = false;
        }

        $storage['can_redirect_through_parameter'] = $can_redirect_through_parameter == 1 ? 1 : 0;

        update_option('wecantrack_storage', json_encode($storage));

        if ($referrer_cookie_status == 0 || $referrer_cookie_status == 1) {
            update_option('wecantrack_referrer_cookie_status', $referrer_cookie_status);
        }

        echo json_encode(['msg'=>'ok']);
        wp_die();
    }

    /**
     * AJAX form redirect page
     */
    public function redirect_page_form_response() {
        WecantrackHelper::nonce_check($_POST['wecantrack_form_nonce']);

        $options = unserialize(get_option('wecantrack_redirect_options'));
        if (isset($_POST['wecantrack_redirect_status']) && sanitize_text_field($_POST['wecantrack_redirect_status']) == 1) {
            $options['status'] = 1;
        } else {
            $options['status'] = 0;
        }

        if (isset($_POST['wecantrack_redirect_delay'])) {
            if ($_POST['wecantrack_redirect_delay'] == 0 && $_POST['wecantrack_redirect_delay'] != '') {
                $options['delay'] = 0;
            } else if ($_POST['wecantrack_redirect_delay'] < 0) {
                echo json_encode(array('error' => esc_html__('Delay value can not be negative')));
                wp_die();
            } else if ($_POST['wecantrack_redirect_delay'] > 0) {
                $options['delay'] = sanitize_text_field($_POST['wecantrack_redirect_delay']);
            } else {
                //default 2 seconds
                $options['delay'] = 2;
            }
        } else {
            //default 2 seconds
            $options['delay'] = 2;
        }

        if (isset($_POST['url_contains'])) {
            $options['url_contains'] = sanitize_text_field($_POST['url_contains']);
        } else {
            $options['url_contains'] = null;
        }

        //no need to sanitize, users can add divs styles etc to the redirect text
        if (!empty($_POST['redirect_text'])) {
            $options['redirect_text'] = stripslashes($_POST['redirect_text']);
        } else {
            echo json_encode(array('error' => esc_html__('Redirect text can not be empty, if you want to have no text then add an empty space \' \' to the field.')));
            wp_die();
        }

        // do not sanitize, because we need to paste the exact html code the user inputs
        update_option('wecantrack_custom_redirect_html', stripslashes($_POST['wecantrack_custom_redirect_html']));
        update_option('wecantrack_redirect_options', serialize($options));

        echo json_encode([]);
        wp_die();
    }

    public function admin_menu()
    {
        add_menu_page(
            'WeCanTrack > Settings',
            'WeCanTrack',
            'manage_options',
            'wecantrack',
            array($this, 'settings'),
            WECANTRACK_URL . '/images/favicon.png',
            99
        );
        add_submenu_page('wecantrack', 'WeCanTrack > Redirect Page', 'Redirect Page',
            'manage_options', 'wecantrack-redirect-page', array($this, 'redirect_page'));

        add_submenu_page('wecantrack', 'WeCanTrack > Advanced Settings', 'Settings',
            'manage_options', 'wecantrack-advanced-settings', array($this, 'advanced_settings'));
    }

    public function advanced_settings()
    {
        require_once WECANTRACK_PATH . '/views/advanced_settings.php';
    }

    public function settings()
    {
        require_once WECANTRACK_PATH . '/views/settings.php';
    }

    public function redirect_page()
    {
        require_once WECANTRACK_PATH . '/views/redirect_page.php';
    }

    /**
     *  Load in css and js for admin page and all the translations
     */
    public function enqueue_scripts()
    {
        $site_url = home_url();

        $params = array (
            'ajaxurl' => admin_url('admin-ajax.php'),
            'site_url' => $site_url,
            'lang_request_wrong' => esc_html__('Something went wrong with the request', 'wecantrack'),
            'lang_added_one_active_network' => esc_html__('Added at least 1 active network account', 'wecantrack'),
            'lang_not_added_one_active_network' => esc_html__('You have not added at least 1 active network account. To add a network, click here.', 'wecantrack'),
            'lang_website_added' => esc_html__(sprintf('Website %s added', $site_url), 'wecantrack'),
            'lang_website_not_added' => esc_html__(sprintf('You have not added the website %s to our platform. To add the website, click here.', $site_url), 'wecantrack'),
            'lang_verified' => esc_html__('verified', 'wecantrack'),
            'lang_invalid_api_key' => esc_html__('Invalid API Key', 'wecantrack'),
            'lang_invalid_request' => esc_html__('Invalid Request', 'wecantrack'),
            'lang_valid_api_key' => esc_html__('Valid API Key', 'wecantrack'),
            'lang_changes_saved' => esc_html__('Your changes have been saved', 'wecantrack'),
            'lang_something_went_wrong' => esc_html__('Something went wrong.', 'wecantrack'),
        );

        wp_register_style('wecantrack_admin_css', WECANTRACK_URL.'/css/admin.css', array(), WECANTRACK_VERSION);
        wp_enqueue_style('wecantrack_admin_css');

        if ($_GET['page'] === 'wecantrack') {
            wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/admin.js', array( 'jquery' ), WECANTRACK_VERSION, false);
            wp_localize_script( 'wecantrack_admin_js', 'params', $params);
        } else if ($_GET['page'] === 'wecantrack-redirect-page') {
            wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/redirect_page.js', array( 'jquery' ), WECANTRACK_VERSION, false);
            wp_localize_script( 'wecantrack_admin_js', 'params', $params);
        } else if ($_GET['page'] === 'wecantrack-advanced-settings') {
            wp_enqueue_script( 'wecantrack_admin_js', WECANTRACK_URL.'/js/advanced_settings.js', array( 'jquery' ), WECANTRACK_VERSION, false);
            wp_localize_script( 'wecantrack_admin_js', 'params', $params);
        }
    }

    /**
     * Get information about the user on the wct platform in order to see where the user currently is on the on-boarding process.
     *
     * @param $api_key
     * @return array|mixed
     */
    public static function get_user_information($api_key)
    {
        try {
            $api_url = WECANTRACK_API_BASE_URL . '/api/v1/user/information';
            $response = wp_remote_get($api_url, array(
                'timeout' => 10,
                'headers' => array(
                    'x-api-key' => $api_key,
                    'Content-Type' => 'application/json',
                    'x-wp-version' => WECANTRACK_VERSION
                ),
                'sslverify' => WecantrackHelper::get_sslverify_option()
            ));

            $response = wp_remote_retrieve_body($response);
            $response = json_decode($response, true);

            if (!empty($response['error'])) {
                throw new \Exception(json_encode($response));
            }

            return $response;
        } catch (\Exception $e) {
            return array('error' => $e->getMessage());
        }
    }

}