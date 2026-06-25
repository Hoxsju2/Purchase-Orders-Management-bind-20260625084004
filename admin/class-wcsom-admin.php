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
        add_action('wp_ajax_wcsom_save_po_edit', array($this, 'ajax_save_po_edit'));
        add_action('wp_ajax_wcsom_global_product_search', array($this, 'ajax_global_product_search'));
        add_action('wp_ajax_wcsom_search_all_wc_products', array($this, 'ajax_search_all_wc_products'));
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

        wp_enqueue_media(); // Required for admin file attachments
        wp_enqueue_style('select2');
        wp_enqueue_script('selectWoo');

        wp_enqueue_style('wcsom-admin-css', WCSOM_PLUGIN_URL . 'assets/admin.css', array(), WCSOM_VERSION);
        wp_enqueue_script('wcsom-admin-js', WCSOM_PLUGIN_URL . 'assets/admin.js', array('jquery', 'selectWoo'), WCSOM_VERSION, true);

        wp_localize_script('wcsom-admin-js', 'wcsom_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('wcsom_admin_nonce')
        ));
    }

    public function render_dashboard() {
        $suppliers = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => 'company_name', 'compare' => 'EXISTS'),
                array('key' => 'supplier_code', 'compare' => 'EXISTS')
            )
        ));

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'suppliers';

        include WCSOM_PLUGIN_DIR . 'admin/views/main.php';
    }

    // --- AJAX Handlers ---

    public function ajax_get_supplier_products() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $supplier_id = intval($_POST['supplier_id']);
        $exclude_ids = isset($_POST['exclude_ids']) ? array_map('intval', (array)$_POST['exclude_ids']) : [];
        $return_json = isset($_POST['return_json']) ? true : false; 

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'post__not_in'   => $exclude_ids,
            'meta_query'     => array(
                array(
                    'key'   => '_wcsom_supplier_id',
                    'value' => $supplier_id,
                )
            )
        );

        $products = get_posts($args);
        
        if ($return_json) {
            $data = [];
            foreach ($products as $post) {
                $product = wc_get_product($post->ID);
                $data[] = array(
                    'id' => $post->ID,
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price()
                );
            }
            wp_send_json_success($data);
        }

        $html = '';
        if (empty($products)) {
            wp_send_json_success('<tr><td colspan="5" style="text-align:center; padding: 30px; color: #6b7280;">No products available.</td></tr>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';
            $price = $product->get_price();

            $html .= '<tr>';
            $html .= '<td class="wcsom-td-check"><input type="checkbox" class="wcsom-po-select" value="' . $post->ID . '" data-price="'.$price.'" data-name="'.esc_attr($product->get_name()).'"></td>';
            $html .= '<td class="wcsom-td-img">' . $img . '</td>';
            $html .= '<td><strong>' . esc_html($product->get_name()) . '</strong></td>';
            $html .= '<td>' . $sku . '</td>';
            $html .= '<td><strong>' . wcsom_format_usd($price) . '</strong></td>';
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
                $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
                $result[] = array(
                    'id'   => $post->ID,
                    'name' => $product->get_name() . $sku_text
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_search_all_wc_products() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $keyword = sanitize_text_field($_GET['keyword']);

        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            's'              => $keyword,
            'posts_per_page' => 20,
        );

        $products = get_posts($args);
        $result = array();

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
            $result[] = array(
                'id'    => $post->ID,
                'text'  => $product->get_name() . $sku_text,
                'price' => $product->get_price(),
                'sku'   => $product->get_sku(),
                'name'  => $product->get_name()
            );
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
        $order_ref = isset($_POST['order_ref']) && !empty(trim($_POST['order_ref'])) ? sanitize_text_field(trim($_POST['order_ref'])) : 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $items = isset($_POST['items']) ? $_POST['items'] : array(); 

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
                'price'      => $price,
                'added_by_supplier' => false
            );
        }

        $post_id = wp_insert_post(array(
            'post_title'  => $order_ref,
            'post_type'   => 'wcsom_order',
            'post_status' => 'publish',
        ));

        if ($post_id) {
            $sec_code = mt_rand(1000000, 9999999); // Generate 7 digit security code
            
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_total_qty', $total_qty);
            update_post_meta($post_id, '_wcsom_total_amount', $total_amount);
            update_post_meta($post_id, '_wcsom_items', $order_items);
            update_post_meta($post_id, '_wcsom_status', 'waiting_for_quote');
            update_post_meta($post_id, '_wcsom_notes', '');
            update_post_meta($post_id, '_wcsom_security_code', $sec_code);
            
            // Empty payments and attachments arrays initially
            update_post_meta($post_id, '_wcsom_payments', array());
            update_post_meta($post_id, '_wcsom_attachments', array());
            
            wp_send_json_success('Purchase order created successfully!');
        }

        wp_send_json_error('Failed to create order.');
    }

    public function ajax_save_po_edit() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        $po_id = intval($_POST['po_id']);
        $order_ref = sanitize_text_field($_POST['order_ref']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        $payments = isset($_POST['payments']) ? $_POST['payments'] : array();
        $attachments = isset($_POST['attachments']) ? $_POST['attachments'] : array();

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        foreach ($items as $item) {
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $added = !empty($item['added']) ? true : false;
            
            if ($qty > 0) {
                $total_qty += $qty;
                $total_amount += ($qty * $price);
                $order_items[] = array(
                    'product_id' => intval($item['id']),
                    'qty'        => $qty,
                    'price'      => $price,
                    'added_by_supplier' => $added
                );
            }
        }

        // Sanitize and format payments
        $formatted_payments = [];
        if (!empty($payments)) {
            foreach($payments as $p) {
                $formatted_payments[] = array(
                    'title' => sanitize_text_field($p['title']),
                    'percent' => floatval($p['percent']),
                    'amount' => floatval($p['amount']),
                    'status' => sanitize_text_field($p['status'])
                );
            }
        }

        // Validate attachments structure
        $formatted_attachments = [];
        if (!empty($attachments)) {
            foreach($attachments as $a) {
                $formatted_attachments[] = array(
                    'url' => esc_url_raw($a['url']),
                    'name' => sanitize_text_field($a['name']),
                    'type' => sanitize_text_field($a['type']),
                    'uploaded_by' => sanitize_text_field($a['uploaded_by']),
                    'date' => sanitize_text_field($a['date'])
                );
            }
        }

        if (!empty($order_ref)) {
            wp_update_post(array(
                'ID'         => $po_id,
                'post_title' => $order_ref
            ));
        }

        update_post_meta($po_id, '_wcsom_items', $order_items);
        update_post_meta($po_id, '_wcsom_total_qty', $total_qty);
        update_post_meta($po_id, '_wcsom_total_amount', $total_amount);
        update_post_meta($po_id, '_wcsom_status', $status);
        update_post_meta($po_id, '_wcsom_notes', $notes);
        update_post_meta($po_id, '_wcsom_payments', $formatted_payments);
        update_post_meta($po_id, '_wcsom_attachments', $formatted_attachments);

        wp_send_json_success('Purchase order updated successfully!');
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
            wp_send_json_success('<tr><td colspan="4" style="text-align:center; padding: 20px;">No products found.</td></tr>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            $supplier_id = get_post_meta($post->ID, '_wcsom_supplier_id', true);
            
            $supplier_display = '<span style="color:#9ca3af; font-style:italic;">Unassigned</span>';
            
            if ($supplier_id) {
                $code = get_user_meta($supplier_id, 'supplier_code', true);
                $company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
                
                if (!trim($company)) {
                    $user_info = get_userdata($supplier_id);
                    $company = $user_info ? $user_info->display_name : 'Unknown';
                }
                
                $supplier_display = '<strong>' . ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company) . '</strong>';
            }

            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';

            $html .= '<tr>';
            $html .= '<td style="display:flex; align-items:center; gap:12px;">' . $img . ' <strong>' . esc_html($product->get_name()) . '</strong></td>';
            $html .= '<td>' . $sku . '</td>';
            $html .= '<td>' . $supplier_display . '</td>';
            $html .= '<td><button class="wcsom-btn wcsom-btn-outline" onclick="window.location.href=\'?page=wcsom-dashboard&tab=suppliers\'">Go to Suppliers</button></td>';
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }
}
