<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Class WecantrackPermissions
 *
 * Handles permission and security-related checks for the Wecantrack plugin.
 *
 * @package Wecantrack
 */
class WecantrackPermissions {
    const NONCE_FIELD = 'wecantrack_form_nonce';

    public function require_admin_access() {
        if (!$this->current_user_can_manage_options()) {
            wp_send_json_error([
                'error' => esc_html__('You do not have the required unfiltered HTML permissions.', 'wecantrack')
            ], 403);
        }
    }

    public function require_unfiltered_html_access() {
        if (!$this->current_user_can_manage_options() || !current_user_can('unfiltered_html')) {
            wp_send_json_error([
                'error' => esc_html__('You do not have the required unfiltered HTML permissions.', 'wecantrack')
            ], 403);
        }
    }

    public function nonce_check($nonce_field = null)
    {
        $nonce_field = $nonce_field ?? self::NONCE_FIELD;
        if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_field)) {
            wp_send_json_error([
                'error' => esc_html__('The request could not be validated. Please refresh the page and try again.', 'wecantrack')
            ], 403);
        }
    }

    public function current_user_can_manage_options()
    {
        return is_multisite() ? is_super_admin() : current_user_can('manage_options');
    }
}