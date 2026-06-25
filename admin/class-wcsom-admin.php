<?php
if (!defined('ABSPATH')) exit;

class WCSOM_Admin {

    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // AJAX Actions
        add_action('wp_ajax_wcsom_get_supplier_products', array($this, 'ajax_get_supplier_products'));
        add_action('wp_ajax_wcsom_search_wc_products', array($this, 'ajax_search_wc_products'));
        add_action('wp_ajax_wcsom_assign_product', array($this, 'ajax_assign_product'));
        add_action('wp_ajax_wcsom_create_po', array($this, 'ajax_create_po'));
        add_action('wp_ajax_wcsom_global_product_search', array($this, 'ajax_global_product_search'));
    }

    public function add_admin_menus() {
        add_menu_page(
            'Supplier Orders',
            'Supplier Orders',
            'manage_woocommerce',
            'wcsom-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-clipboard',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wcsom-dashboard') return;

        wp_enqueue_style('wcsom-admin-css', WCSOM_PLUGIN_URL . 'assets/admin.css', array(), WCSOM_VERSION);
        wp_enqueue_script('wcsom-admin-js', WCSOM_PLUGIN_URL . 'assets/admin.js', array('jquery'), WCSOM_VERSION, true);

        wp_localize_script('wcsom-admin-js', 'wcsom_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wcsom_admin_nonce')
        ));
    }

    public function render_dashboard() {
        // Fetch suppliers (users with company_name or supplier_code)
        $suppliers = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'company_name',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key'     => 'supplier_code',
                    'compare' => 'EXISTS'
                )
            )
        ));

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'suppliers';

        include WCSOM_PLUGIN_DIR . 'admin/views/main.php';
    }

    // --- AJAX Handlers ---

    public function ajax_get_supplier_products() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $supplier_id = intval($_POST['supplier_id']);

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any', // Include draft/pending
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'   => '_wcsom_supplier_id',
                    'value' => $supplier_id,
                )
            )
        );

        $products = get_posts($args);
        $html = '';

        if (empty($products)) {
            wp_send_json_success('<p>No products assigned to this supplier yet.</p>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : 'N/A';
            $price = $product->get_price();

            $html .= '<tr>';
            $html .= '<td><input type="checkbox" class="wcsom-po-select" value="' . $post->ID . '" data-price="'.$price.'" data-name="'.esc_attr($product->get_name()).'"></td>';
            $html .= '<td>' . $img . '</td>';
            $html .= '<td>' . esc_html($product->get_name()) . '</td>';
            $html .= '<td>' . esc_html($sku) . '</td>';
            $html .= '<td>' . wc_price($price) . '</td>';
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }

    public function ajax_search_wc_products() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $keyword = sanitize_text_field($_POST['keyword']);

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            's'              => $keyword,
            'posts_per_page' => 10,
        );

        $products = get_posts($args);
        $result = array();

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $assigned_supplier = get_post_meta($post->ID, '_wcsom_supplier_id', true);
            if (!$assigned_supplier) {
                $result[] = array(
                    'id'   => $post->ID,
                    'name' => $product->get_name() . ' (' . $product->get_sku() . ')'
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_assign_product() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $supplier_id = intval($_POST['supplier_id']);
        $product_id = intval($_POST['product_id']);

        if ($supplier_id && $product_id) {
            update_post_meta($product_id, '_wcsom_supplier_id', $supplier_id);
            wp_send_json_success('Product assigned successfully.');
        }
        wp_send_json_error('Missing data.');
    }

    public function ajax_create_po() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $supplier_id = intval($_POST['supplier_id']);
        $items = isset($_POST['items']) ? $_POST['items'] : array(); // Array of {id, qty, price}

        if (!$supplier_id || empty($items)) {
            wp_send_json_error('Missing supplier or items.');
        }

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        foreach ($items as $item) {
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $total_qty += $qty;
            $total_amount += ($qty * $price);
            
            $order_items[] = array(
                'product_id' => intval($item['id']),
                'qty'        => $qty,
                'price'      => $price
            );
        }

        // Create PO Post
        $post_id = wp_insert_post(array(
            'post_title'  => 'PO-' . date('Ymd') . '-' . rand(1000, 9999),
            'post_type'   => 'wcsom_order',
            'post_status' => 'publish',
        ));

        if ($post_id) {
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_total_qty', $total_qty);
            update_post_meta($post_id, '_wcsom_total_amount', $total_amount);
            update_post_meta($post_id, '_wcsom_items', $order_items);
            update_post_meta($post_id, '_wcsom_status', 'pending');
            
            wp_send_json_success('Purchase order created successfully!');
        }

        wp_send_json_error('Failed to create order.');
    }

    public function ajax_global_product_search() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $keyword = sanitize_text_field($_POST['keyword']);

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            's'              => $keyword,
            'posts_per_page' => 20,
        );

        $products = get_posts($args);
        $html = '';

        if (empty($products)) {
            wp_send_json_success('<tr><td colspan="4">No products found.</td></tr>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $supplier_id = get_post_meta($post->ID, '_wcsom_supplier_id', true);
            $supplier_name = 'Unassigned';
            
            if ($supplier_id) {
                $supplier_name = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'supplier_code', true);
                if (!$supplier_name) {
                    $user_info = get_userdata($supplier_id);
                    $supplier_name = $user_info ? $user_info->display_name : 'Unknown';
                }
            }

            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : 'N/A';

            $html .= '<tr>';
            $html .= '<td>' . $img . ' ' . esc_html($product->get_name()) . '</td>';
            $html .= '<td>' . esc_html($sku) . '</td>';
            $html .= '<td>' . esc_html($supplier_name) . '</td>';
            $html .= '<td><button class="button action-add-to-po" data-id="'.$post->ID.'" data-supplier="'.$supplier_id.'">Add to PO</button></td>';
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }
}
