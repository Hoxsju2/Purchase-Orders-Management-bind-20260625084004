<?php
/**
 * Plugin Name: WooCommerce Supplier Orders Manager
 * Description: Manage suppliers, assign products, and create purchase orders seamlessly.
 * Version: 1.0.7
 * Author: Bind AI
 * License: GPL v2 or later
 * Text Domain: wcsom
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WCSOM_VERSION', '1.0.7');
define('WCSOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSOM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Global Helper to enforce USD format across the plugin
if (!function_exists('wcsom_format_usd')) {
    function wcsom_format_usd($amount) {
        return '$' . number_format((float)$amount, 2);
    }
}

// Require classes globally
require_once WCSOM_PLUGIN_DIR . 'includes/class-wcsom-setup.php';
require_once WCSOM_PLUGIN_DIR . 'includes/class-wcsom-frontend.php';
require_once WCSOM_PLUGIN_DIR . 'admin/class-wcsom-admin.php';
require_once WCSOM_PLUGIN_DIR . 'includes/class-wcsom-github-updater.php';

// Check if WooCommerce is active
if (!function_exists('wcsom_check_woocommerce_active')) {
    function wcsom_check_woocommerce_active() {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return true;
        }
        return false;
    }
}

// Initialization
if (!function_exists('wcsom_init')) {
    function wcsom_init() {
        if (!wcsom_check_woocommerce_active()) {
            add_action('admin_notices', 'wcsom_woocommerce_missing_notice');
            return;
        }

        $setup = new WCSOM_Setup();
        $setup->init();

        $frontend = new WCSOM_Frontend();
        $frontend->init();

        if (is_admin()) {
            $admin = new WCSOM_Admin();
            $admin->init();
            
            // Initialize GitHub Auto-Updater
            new WCSOM_GitHub_Updater(__FILE__, 'Hoxsju2', 'Purchase-Orders-Management-bind-20260625084004');
        }
    }
}
add_action('plugins_loaded', 'wcsom_init');

if (!function_exists('wcsom_woocommerce_missing_notice')) {
    function wcsom_woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>WooCommerce Supplier Orders Manager</strong> requires WooCommerce to be installed and active.</p></div>';
    }
}

// Activation hook
register_activation_hook(__FILE__, array('WCSOM_Setup', 'activate'));
