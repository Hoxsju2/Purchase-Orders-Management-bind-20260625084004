<?php
if (!defined('ABSPATH')) exit;

class WCSOM_Setup {

    public function init() {
        add_action('init', array($this, 'register_post_types'));
    }

    public static function activate() {
        // Register custom post type on activation so rewrite rules can be flushed
        $setup = new self();
        $setup->register_post_types();
        flush_rewrite_rules();
    }

    public function register_post_types() {
        // Register Purchase Order CPT
        $labels = array(
            'name'                  => _x('Supplier POs', 'Post Type General Name', 'wcsom'),
            'singular_name'         => _x('Supplier PO', 'Post Type Singular Name', 'wcsom'),
            'menu_name'             => __('Supplier POs', 'wcsom'),
            'all_items'             => __('All POs', 'wcsom'),
            'add_new_item'          => __('Add New PO', 'wcsom'),
            'edit_item'             => __('Edit PO', 'wcsom'),
            'view_item'             => __('View PO', 'wcsom'),
        );
        $args = array(
            'label'                 => __('Supplier PO', 'wcsom'),
            'labels'                => $labels,
            'supports'              => array('title'),
            'hierarchical'          => false,
            'public'                => false, // Keep it private/admin only
            'show_ui'               => false, // We will use our custom UI
            'show_in_menu'          => false,
            'menu_position'         => 56,
            'show_in_admin_bar'     => false,
            'show_in_nav_menus'     => false,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
        );
        register_post_type('wcsom_order', $args);
    }
}
