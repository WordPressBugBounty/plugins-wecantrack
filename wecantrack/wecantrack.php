<?php
/**
 * Plugin Name:       WeCanTrack
 * Plugin URI:        https://wecantrack.com/wordpress
 * Description:       Integrate all your affiliate sales into Google Analytics, Google Ads, Facebook, Data Studio, and more!
 * Version:           2.0.3
 * Author:            WeCanTrack
 * Author URI:        https://wecantrack.com
 * Requires PHP:      7.4
 * Requires at least: 5.0
 * Tested up to:      6.8
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       wecantrack
 * Domain Path:       /languages
 */

if (!defined('ABSPATH')) { die('You are not allowed to call this page directly.'); }

define('WECANTRACK_VERSION', '2.0.3');
define('WECANTRACK_PLUGIN_NAME', 'wecantrack');
define('WECANTRACK_PATH', plugin_dir_path(__FILE__));
define('WECANTRACK_URL', plugin_dir_url(__FILE__));
define('WECANTRACK_API_BASE_URL', 'https://api.wecantrack.com');

// Load core classes
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

        if (! empty($_GET['afflink']) && ! empty($_GET['data'])) {
            add_action('template_redirect', 'wecantrack_handle_deprecated_go_redirect');
        }

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

if (!function_exists('wecantrack_plugin_activation')) {
    /**
     * Runs on plugin activation.
     *
     * Registers default plugin options in the WordPress database.
     *
     * @return void
     */
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
}

if (!function_exists('wecantrack_plugin_deactivation')) {
    /**
     * Runs on plugin deactivation.
     *
     * Disables plugin functionality without deleting saved options.
     *
     * @return void
     */
    function wecantrack_plugin_deactivation()
    {
        update_option('wecantrack_plugin_status', 0);
    }
}

if (!function_exists('wecantrack_plugin_uninstall')) {
    /**
     * Runs on plugin uninstall.
     *
     * Deletes all plugin options from the WordPress database.
     * This is a full cleanup to ensure no leftover data remains.
     *
     * @return void
     */
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
}

if (!function_exists('wecantrack_plugin_upgraded')) {
    /**
     * Callback triggered after plugin is updated.
     *
     * If this plugin was updated, it refreshes the WeCanTrack tracking code
     * and website information, then clears all known cache layers.
     *
     * @param WP_Upgrader $upgrader_object The upgrader instance.
     * @param array       $options         Array of options for the upgrade action.
     *
     * @return void
     */
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
                    error_log('[WeCanTrack] Error occurred during plugin upgrade. Message: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
                }
    
                wecantrack_clear_all_known_caches();
              }
           }
        }
    }
}

if (!function_exists('wecantrack_clear_all_known_caches')) {
    /**
     * Clears cache from known WordPress caching plugins and platforms.
     *
     * Attempts to programmatically flush caches for:
     * - WP Super Cache
     * - W3 Total Cache
     * - WP Rocket
     * - LiteSpeed
     * - SiteGround
     * - Autoptimize
     * - Swift Performance
     * - Hummingbird
     * - Comet Cache
     * - Breeze
     * - WP Fastest Cache
     * - WP-Optimize
     * - Cache Enabler
     * - Kinsta
     * - Hyper Cache
     * - Simple Cache
     * - Cachify
     *
     * @return void
     */
    function wecantrack_clear_all_known_caches() {
        // WP Super Cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
    
        // W3 Total Cache
        if (class_exists('W3_Plugin_TotalCacheAdmin')) {
            if (function_exists('w3_instance')) {
                $w3_plugin_totalcacheadmin = w3_instance('W3_Plugin_TotalCacheAdmin');
                if (method_exists($w3_plugin_totalcacheadmin, 'flush_all')) {
                    $w3_plugin_totalcacheadmin->flush_all();
                }
            }
        }
    
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    
        // LiteSpeed Cache
        do_action('litespeed_purge_all');
    
        // SiteGround Optimizer
        if (class_exists('SG_CachePress_Supercacher')) {
            $sg_cache = new SG_CachePress_Supercacher();
            $sg_cache->purge_cache();
        }
    
        // Autoptimize (Clears only its own cache)
        if (class_exists('autoptimizeCache')) {
            autoptimizeCache::clearall();
        }
    
        // Swift Performance
        if (class_exists('Swift_Performance_Cache')) {
            Swift_Performance_Cache::clear_all_cache();
        }
    
        // Hummingbird (by WPMU DEV)
        do_action('wphb_clear_cache');
    
        // Comet Cache (Zencache)
        if (class_exists('comet_cache')) {
            comet_cache::clear();
        }
    
        // Breeze (by Cloudways)
        if (function_exists('breeze_clear_cache')) {
            breeze_clear_cache();
        }
    
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            // true = clear minified CSS/JS as well; omit/false if you only need the page cache.
            wpfc_clear_all_cache(true);
        }
    
        // WP-Optimize
        if (function_exists('wpo_cache_flush')) {
            wpo_cache_flush();
        }
    
        // Cache Enabler
        if (function_exists('cache_enabler_clear_total_cache')) {
            cache_enabler_clear_total_cache();
        }
    
        // Kinsta MU Cache
        do_action('kinsta_cache_purge_all');
    
        // Hyper Cache
        if (function_exists('hyper_cache_clean')) {
            hyper_cache_clean();
        }
    
        // Simple Cache
        if (function_exists('sc_clear_cache')) {
            sc_clear_cache();
        }
    
        // Cachify
        if (function_exists('cachify_flush_total_cache')) {
            cachify_flush_total_cache();
        }
    }
}

if (!function_exists('wecantrack_handle_deprecated_go_redirect')) {
    /**
     * Handles deprecated /go redirects.
     * This function sends a 410 Gone status and displays a message indicating that the redirect method is no longer supported.
     * Additionally, it optionally emails the admin once per hour if the deprecated link is accessed.
     * 
     * @return void Terminates script execution with exit().
     */
    function wecantrack_handle_deprecated_go_redirect() {
        status_header(410); // Gone
        header('Content-Type: text/html; charset=utf-8');

        echo '<html>';
        echo '<head>';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<meta charset="UTF-8">';
        echo '<title>Redirect Not Available</title>';
        echo '<style>body{font-family:sans-serif;padding:2em;max-width:600px;margin:auto;}</style>';
        echo '</head>';

        echo '<body>';
        echo '<h1>Link no longer available</h1>';
        echo '<p>This affiliate link redirect method is no longer supported.</p>';
        echo '<p>If you are the site admin, please clear your website and CDN caches to resolve issues with outdated or broken outgoing links.</p>';
        if (!empty($_GET['afflink'])) {
            echo '<p><a href="' . esc_url_raw($_GET['afflink']) . '">Go to offer</a></p>';
        }
        echo '<p><a href="' . esc_url(home_url()) . '">Go back to homepage</a></p>';

        echo '</body>';
        echo '</html>';
        exit;
    }
}