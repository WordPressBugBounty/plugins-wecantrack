<?php
/**
 * @package Wecantrack
 */
/*
Plugin Name: WeCanTrack
Plugin URI: https://wecantrack.com/wordpress
Description: Integrate all you affiliate sales in Google Analytics, Google Ads, Facebook, Data Studio and more!
Version: 1.5.1
Author: wecantrack.com
Author URI: https://wecantrack.com
Requires PHP: 7.4
License: GPLv3
Text Domain: wecantrack
*/

if(!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

define('WECANTRACK_VERSION', '1.5.1');
define('WECANTRACK_PLUGIN_NAME', 'wecantrack');
define('WECANTRACK_PATH', WP_PLUGIN_DIR.'/'.WECANTRACK_PLUGIN_NAME);
define('WECANTRACK_URL', plugins_url($path = '/'.WECANTRACK_PLUGIN_NAME));
define('WECANTRACK_API_BASE_URL', 'https://api.wecantrack.com');

require_once(WECANTRACK_PATH . '/WecantrackHelper.php');

if (is_admin() || defined('WP_CLI')) {
    require_once(WECANTRACK_PATH . '/WecantrackAdmin.php');
    new WecantrackAdmin();
} else if ((! defined('DOING_CRON') || ! DOING_CRON) && filter_input(INPUT_SERVER, 'REQUEST_URI') !== '/wp-login.php') {
    // Do not enqueue our JS scripts in Thrive Architect's iframe.
    $thriveIsActive = defined('TVE_PLUGIN_FILE') && strpos(filter_input(INPUT_SERVER, 'REQUEST_URI'), 'tve=true') !== false;
    $elementorIsActive = defined('ELEMENTOR_VERSION') && strpos(filter_input(INPUT_SERVER, 'REQUEST_URI'), 'elementor-preview=') !== false;
    $diviIsActive = defined('ET_CORE_VERSION') && strpos(filter_input(INPUT_SERVER, 'REQUEST_URI'), 'et_fb=') !== false;

    if (! $thriveIsActive && ! $elementorIsActive && ! $diviIsActive) {
        require_once(WECANTRACK_PATH . '/WecantrackApp.php');
        require_once(WECANTRACK_PATH . '/WecantrackAppRedirectPage.php');
        new WecantrackApp();
    }
}

/**
 * Installation/Uninstall process
 */

register_activation_hook(__FILE__, 'wecantrack_plugin_activation');
register_deactivation_hook(__FILE__, 'wecantrack_plugin_deactivation');
register_uninstall_hook(__FILE__, 'wecantrack_plugin_uninstall');
add_action('upgrader_process_complete', 'wecantrack_plugin_upgraded', 10, 2);

function wecantrack_plugin_activation()
{
    add_option('wecantrack_api_key', null, null);
    add_option('wecantrack_plugin_status', 0, null);
    add_option('wecantrack_fetch_expiration', null, null);
    add_option('wecantrack_snippet', null, null);
    add_option('wecantrack_session_enabler', null, null);
    add_option('wecantrack_snippet_version', null, null);
    add_option('wecantrack_domain_patterns', null, null);
    add_option('wecantrack_custom_redirect_html', null, null);
    add_option('wecantrack_redirect_options', null, null);
    add_option('wecantrack_website_options', null, null);
    add_option('wecantrack_version', null, null);
    add_option('wecantrack_storage', null, null);
    add_option('wecantrack_referrer_cookie_status', 0, null);
}

function wecantrack_plugin_deactivation()
{
    update_option('wecantrack_plugin_status', 0);
}

function wecantrack_plugin_uninstall()
{
    delete_option('wecantrack_api_key');
    delete_option('wecantrack_plugin_status');
    delete_option('wecantrack_fetch_expiration');
    delete_option('wecantrack_snippet');
    delete_option('wecantrack_session_enabler');
    delete_option('wecantrack_snippet_version');
    delete_option('wecantrack_domain_patterns');
    delete_option('wecantrack_custom_redirect_html');
    delete_option('wecantrack_redirect_options');
    delete_option('wecantrack_website_options');
    delete_option('wecantrack_version');
    delete_option('wecantrack_storage');
    delete_option('wecantrack_referrer_cookie_status');
}

function wecantrack_plugin_upgraded($upgrader_object, $options) {
    $current_plugin_path_name = plugin_basename( __FILE__ );
    
    if ($options['action'] == 'upgrade' && $options['type'] == 'plugin') {
       foreach($options['plugins'] as $each_plugin) {
          if ($each_plugin == $current_plugin_path_name) {
            $api_key = get_option('wecantrack_api_key');

            if (empty($api_key)) {
                return;
            }

            // refetch the wecantrack script
            $domainURL = home_url();
            try {
                WecantrackHelper::update_tracking_code($api_key, $domainURL);
                WecantrackHelper::update_user_website_information($api_key, $domainURL);
            } catch (\Exception $e) {
                error_log('WCT Plugin: Error occurred during plugin upgrade. Message: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            }

            // set can_redirect_through_parameter to true if include_script is false and can_redirect_through_parameter is not set
            $wecantrack_storage = json_decode(get_option('wecantrack_storage'), true);

            if (!isset($wecantrack_storage['can_redirect_through_parameter']) || $wecantrack_storage['can_redirect_through_parameter'] === null) {
                if (isset($wecantrack_storage['include_script']) && $wecantrack_storage['include_script'] == false) {
                    $wecantrack_storage['can_redirect_through_parameter'] = true;
                    update_option('wecantrack_storage', json_encode($wecantrack_storage));    
                }
            }
          }
       }
    }
}
