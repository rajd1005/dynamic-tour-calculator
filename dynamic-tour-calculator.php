<?php
/**
 * Plugin Name: Dynamic Multi-Destination Tour Calculator
 * Description: A modular, AJAX-powered tour pricing calculator with front-end GUI settings and role-based permissions. Restricted to logged-in users.
 * Version: 2.4.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) { exit; }

define('DTC_PATH', plugin_dir_path(__FILE__));
define('DTC_URL', plugin_dir_url(__FILE__));

require_once DTC_PATH . 'includes/class-dtc-settings.php';
require_once DTC_PATH . 'includes/class-dtc-ajax.php';
require_once DTC_PATH . 'includes/class-dtc-shortcode.php';

add_action('wp_enqueue_scripts', 'dtc_enqueue_scripts', 999);
function dtc_enqueue_scripts() {
    wp_enqueue_style('dtc-style', DTC_URL . 'assets/css/style.css', [], '2.4.0');
    wp_enqueue_script('dtc-script', DTC_URL . 'assets/js/script.js', ['jquery'], '2.4.0', true);
    
    $config_json = get_option('dtc_config', json_encode(dtc_get_default_config()));
    $config = json_decode($config_json, true);

    wp_localize_script('dtc-script', 'dtc_obj', [
        'ajax_url'     => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('dtc_nonce'),
        'config'       => $config,
        'is_admin'     => dtc_is_privileged_user(),
        'is_logged_in' => is_user_logged_in() // Added to check for logged-in user in JS
    ]);
}