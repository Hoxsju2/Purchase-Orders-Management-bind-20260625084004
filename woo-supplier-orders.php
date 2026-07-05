<?php
/**
 * Plugin Name: WooCommerce Supplier Orders Manager
 * Description: Manage suppliers, assign products, and create purchase orders seamlessly.
 * Version: 1.4.4
 * Author: Bind AI
 * License: GPL v2 or later
 * Text Domain: wcsom
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WCSOM_VERSION', '1.4.4');
define('WCSOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSOM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Global Helper to enforce USD format across the plugin
if (!function_exists('wcsom_format_usd')) {
    function wcsom_format_usd($amount) {
        return '$' . number_format((float)$amount, 2);
    }
}

// Check if current user is Admin or Staff
if (!function_exists('wcsom_is_staff')) {
    function wcsom_is_staff() {
        if (current_user_can('manage_options')) return true;
        $user = wp_get_current_user();
        if (in_array('wcsom_staff', (array) $user->roles)) return true;
        return false;
    }
}

// Check if current user has EDIT capabilities (Full Access)
if (!function_exists('wcsom_can_edit')) {
    function wcsom_can_edit() {
        if (current_user_can('manage_options')) return true;
        if (!wcsom_is_staff()) return false;
        
        $user_id = get_current_user_id();
        $access_level = get_user_meta($user_id, '_wcsom_access_level', true);
        return $access_level !== 'view_only';
    }
}

// Get array of supplier IDs visible to the current user
if (!function_exists('wcsom_get_visible_suppliers')) {
    function wcsom_get_visible_suppliers() {
        $suppliers = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => 'company_name', 'compare' => 'EXISTS'),
                array('key' => 'supplier_code', 'compare' => 'EXISTS')
            )
        ));
        
        $visible_ids = [];
        $is_main_admin = current_user_can('manage_options');
        
        foreach ($suppliers as $sup) {
            $is_visible = get_user_meta($sup->ID, '_wcsom_staff_visible', true);
            // Admins see all. Staff only see explicitly activated ones.
            if ($is_main_admin || $is_visible == '1') {
                $visible_ids[] = $sup->ID;
            }
        }
        return $visible_ids;
    }
}

// Helper to build URLs that work in both backend and frontend portal
if (!function_exists('wcsom_get_tab_url')) {
    function wcsom_get_tab_url($tab, $extra_args = array()) {
        $base_url = is_admin() ? admin_url('admin.php?page=wcsom-dashboard') : get_permalink();
        $url = add_query_arg('tab', $tab, $base_url);
        if (!empty($extra_args)) {
            $url = add_query_arg($extra_args, $url);
        }
        return $url;
    }
}

// Helper to fetch packing lists associated with a supplier
if (!function_exists('wcsom_get_supplier_packing_lists')) {
    function wcsom_get_supplier_packing_lists($supplier_id) {
        $supplier_code = get_user_meta($supplier_id, 'supplier_code', true);
        $post_types = array('packing_list', 'packing-list', 'wpls_packing_list', 'wc_packing_list', 'packlist');
        
        // 1. Search by Meta matching ID or Supplier Code
        $meta_args = array(
            'post_type'      => $post_types,
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private'),
            'meta_query'     => array(
                'relation' => 'OR',
                array('key' => 'supplier_id', 'value' => $supplier_id),
                array('key' => '_supplier_id', 'value' => $supplier_id),
            )
        );
        if ($supplier_code) {
            $meta_args['meta_query'][] = array('key' => 'supplier_code', 'value' => $supplier_code);
        }
        $meta_posts = get_posts($meta_args);

        // 2. Search by Author mapping
        $author_args = array(
            'post_type'      => $post_types,
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'private'),
            'author'         => $supplier_id
        );
        $author_posts = get_posts($author_args);

        // Merge and ensure uniqueness
        $all = array_merge($meta_posts, $author_posts);
        $unique = [];
        foreach($all as $p) {
            $unique[$p->ID] = $p;
        }
        return array_values($unique);
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

        // Ensure Staff Role exists
        if (!get_role('wcsom_staff')) {
            add_role('wcsom_staff', 'Supplier Orders Staff', array('read' => true, 'upload_files' => true));
        }

        $setup = new WCSOM_Setup();
        $setup->init();

        $frontend = new WCSOM_Frontend();
        $frontend->init();

        $admin = new WCSOM_Admin();
        $admin->init();
            
        if (is_admin()) {
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
