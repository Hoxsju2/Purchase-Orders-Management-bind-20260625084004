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
        add_action('wp_ajax_wcsom_unassign_product', array($this, 'ajax_unassign_product'));
        add_action('wp_ajax_wcsom_update_supplier_price_inline', array($this, 'ajax_update_supplier_price_inline'));
        add_action('wp_ajax_wcsom_get_supplier_packing_lists', array($this, 'ajax_get_supplier_packing_lists'));
        
        add_action('wp_ajax_wcsom_create_po', array($this, 'ajax_create_po'));
        add_action('wp_ajax_wcsom_save_po_edit', array($this, 'ajax_save_po_edit'));
        add_action('wp_ajax_wcsom_delete_po', array($this, 'ajax_delete_po'));
        
        // Global Search
        add_action('wp_ajax_wcsom_global_product_search', array($this, 'ajax_global_product_search'));
        add_action('wp_ajax_wcsom_search_all_wc_products', array($this, 'ajax_search_all_wc_products'));
        
        // Quick Create
        add_action('wp_ajax_wcsom_quick_create_product', array($this, 'ajax_quick_create_product'));
        
        // Bulk Actions
        add_action('wp_ajax_wcsom_create_bulk_order', array($this, 'ajax_create_bulk_order'));
        add_action('wp_ajax_wcsom_delete_bulk_order', array($this, 'ajax_delete_bulk_order'));
        add_action('wp_ajax_wcsom_bulk_add_tags', array($this, 'ajax_bulk_add_tags'));
        add_action('wp_ajax_wcsom_bulk_delete_pos', array($this, 'ajax_bulk_delete_pos'));

        // Staff & Visibility Management
        add_action('wp_ajax_wcsom_add_staff', array($this, 'ajax_add_staff'));
        add_action('wp_ajax_wcsom_remove_staff', array($this, 'ajax_remove_staff'));
        add_action('wp_ajax_wcsom_update_staff_access', array($this, 'ajax_update_staff_access'));
        add_action('wp_ajax_wcsom_toggle_supplier_visibility', array($this, 'ajax_toggle_supplier_visibility'));

        // Create Supplier
        add_action('wp_ajax_wcsom_create_supplier', array($this, 'ajax_create_supplier'));
    }

    public function add_admin_menus() {
        add_menu_page(
            'Supplier Orders',
            'Supplier Orders',
            'manage_options',
            'wcsom-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-clipboard',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wcsom-dashboard') return;

        wp_enqueue_media(); 
        wp_enqueue_style('select2');
        wp_enqueue_script('selectWoo');

        wp_enqueue_style('wcsom-admin-css', WCSOM_PLUGIN_URL . 'assets/admin.css', array(), WCSOM_VERSION);
        wp_enqueue_script('wcsom-admin-js', WCSOM_PLUGIN_URL . 'assets/admin.js', array('jquery', 'selectWoo'), WCSOM_VERSION, true);

        wp_localize_script('wcsom-admin-js', 'wcsom_ajax', array(
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('wcsom_admin_nonce'),
            'url_suppliers'  => wcsom_get_tab_url('suppliers'),
            'url_orders'     => wcsom_get_tab_url('orders'),
            'url_bulk'       => wcsom_get_tab_url('bulk-orders')
        ));
    }

    public function render_dashboard() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'suppliers';
        include WCSOM_PLUGIN_DIR . 'admin/views/main.php';
    }

    // --- Helpers ---
    private function verify_access() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!wcsom_is_staff()) wp_send_json_error('Unauthorized Access');
    }

    private function verify_edit_access() {
        $this->verify_access();
        if (!wcsom_can_edit()) wp_send_json_error('Action restricted. You have View-Only permissions.');
    }

    // --- AJAX Handlers ---

    public function ajax_add_staff() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Only administrators can add staff.');
        
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) wp_send_json_error('Invalid email address.');

        $user = get_user_by('email', $email);
        if (!$user) {
            $password = wp_generate_password();
            $user_id = wp_create_user($email, $password, $email);
            if (is_wp_error($user_id)) wp_send_json_error($user_id->get_error_message());
            $user = get_user_by('id', $user_id);
        }

        $user->add_role('wcsom_staff');
        update_user_meta($user->ID, '_wcsom_access_level', 'full'); // Default to full access
        wp_send_json_success('Staff added successfully.');
    }

    public function ajax_remove_staff() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $user_id = intval($_POST['user_id']);
        if (!$user_id) wp_send_json_error('Invalid user.');

        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->remove_role('wcsom_staff');
            delete_user_meta($user->ID, '_wcsom_access_level');
            wp_send_json_success('Staff access removed.');
        }
        wp_send_json_error('Failed to remove staff.');
    }

    public function ajax_update_staff_access() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $user_id = intval($_POST['user_id']);
        $level = sanitize_text_field($_POST['level']);
        
        if ($user_id && in_array($level, ['full', 'view_only'])) {
            update_user_meta($user_id, '_wcsom_access_level', $level);
            wp_send_json_success('Access level updated.');
        }
        wp_send_json_error('Invalid data.');
    }

    public function ajax_toggle_supplier_visibility() {
        check_ajax_referer('wcsom_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error('Unauthorized.');
        
        $supplier_id = intval($_POST['supplier_id']);
        $status = sanitize_text_field($_POST['status']); // '1' or '0'
        
        if ($supplier_id) {
            update_user_meta($supplier_id, '_wcsom_staff_visible', $status);
            wp_send_json_success('Visibility updated.');
        }
        wp_send_json_error('Invalid supplier.');
    }

    public function ajax_create_supplier() {
        $this->verify_edit_access();

        $company_name = sanitize_text_field($_POST['company_name']);
        $supplier_code = sanitize_text_field($_POST['supplier_code']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $address = sanitize_text_field($_POST['address']);
        $city = sanitize_text_field($_POST['city']);
        $send_invite = isset($_POST['send_invite']) && $_POST['send_invite'] === '1';

        if (empty($company_name)) {
            wp_send_json_error('Company name is required.');
        }

        // Validate unique email if provided
        if (!empty($email) && email_exists($email)) {
            wp_send_json_error('This email is already registered to another user.');
        }

        // Generate a valid username
        $base_username = !empty($supplier_code) ? sanitize_user(strtolower($supplier_code)) : sanitize_user(strtolower(str_replace(' ', '', $company_name)));
        if (empty($base_username)) $base_username = 'supplier';
        
        $username = $base_username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base_username . $i;
            $i++;
        }

        $password = wp_generate_password();
        
        $userdata = array(
            'user_login' => $username,
            'user_pass'  => $password,
            'role'       => 'subscriber' 
        );
        
        if (!empty($email)) {
            $userdata['user_email'] = $email;
        }

        $user_id = wp_insert_user($userdata);

        if (is_wp_error($user_id)) {
            wp_send_json_error($user_id->get_error_message());
        }

        // Standard WP & WooCommerce Meta (Maps exactly to Packing List plugin needs)
        update_user_meta($user_id, 'company_name', $company_name);
        if (!empty($supplier_code)) update_user_meta($user_id, 'supplier_code', $supplier_code);
        if (!empty($phone)) update_user_meta($user_id, 'billing_phone', $phone);
        if (!empty($address)) update_user_meta($user_id, 'billing_address_1', $address);
        if (!empty($city)) update_user_meta($user_id, 'billing_city', $city);
        
        // Ensure new suppliers created by staff are instantly visible to them
        update_user_meta($user_id, '_wcsom_staff_visible', '1');

        // Send Email Invitation
        if ($send_invite && !empty($email)) {
            wp_new_user_notification($user_id, null, 'user');
        }

        wp_send_json_success('Supplier created successfully!');
    }

    public function ajax_get_supplier_products() {
        $this->verify_access();
        $supplier_id = intval($_POST['supplier_id']);
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'date_desc';
        
        // Ensure they have access to this supplier
        if (!in_array($supplier_id, wcsom_get_visible_suppliers())) {
            wp_send_json_error('Unauthorized Supplier Access.');
        }
        
        $exclude_ids = isset($_POST['exclude_ids']) ? array_map('intval', (array)$_POST['exclude_ids']) : [];
        $return_json = isset($_POST['return_json']) ? true : false; 
        $can_edit = wcsom_can_edit();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft'), 
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
        
        // Retrieve and format data for intelligent sorting
        $products_data = [];
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;
            
            // Fallback to creation date if assigned date meta doesn't exist yet
            $assigned_date = get_post_meta($post->ID, '_wcsom_assigned_date', true);
            if (!$assigned_date) $assigned_date = $post->post_date;

            $supp_price = get_post_meta($post->ID, '_wcsom_supplier_price', true);
            $supp_model = get_post_meta($post->ID, '_wcsom_supplier_model', true);
            
            $products_data[] = array(
                'post' => $post,
                'product' => $product,
                'assigned_date' => strtotime($assigned_date),
                'name' => strtolower($product->get_name()),
                'sku' => strtolower($product->get_sku() ?: 'zzzzzz'), // Send to bottom if no SKU
                'supp_price' => $supp_price,
                'supp_model' => $supp_model
            );
        }

        // Apply Sorting
        usort($products_data, function($a, $b) use ($orderby) {
            if ($orderby === 'name_asc') {
                return strnatcasecmp($a['name'], $b['name']);
            } elseif ($orderby === 'sku_asc') {
                return strnatcasecmp($a['sku'], $b['sku']);
            } else {
                return $b['assigned_date'] <=> $a['assigned_date']; // Newest first
            }
        });

        if ($return_json) {
            $data = [];
            foreach ($products_data as $pd) {
                $post = $pd['post'];
                $product = $pd['product'];
                $data[] = array(
                    'id' => $post->ID,
                    'name' => $product->get_name(),
                    'sku' => $product->get_sku(),
                    'price' => $pd['supp_price'] > 0 ? floatval($pd['supp_price']) : $product->get_price(),
                    'orig_price' => $pd['supp_price'] ? floatval($pd['supp_price']) : 0,
                    'supplier_model' => $pd['supp_model']
                );
            }
            wp_send_json_success($data);
        }

        $html = '';
        if (empty($products_data)) {
            wp_send_json_success('<tr><td colspan="7" style="text-align:center; padding: 30px; color: #6b7280;">No products assigned to this supplier yet.</td></tr>');
        }

        foreach ($products_data as $pd) {
            $post = $pd['post'];
            $product = $pd['product'];
            $supp_price = $pd['supp_price'];
            $supp_model = $pd['supp_model'];
            
            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';
            $wc_price = $product->get_price();
            $po_default_price = $supp_price > 0 ? floatval($supp_price) : $wc_price;

            $html .= '<tr id="wcsom-row-'.$post->ID.'">';
            
            if ($can_edit) {
                $html .= '<td class="wcsom-td-check"><input type="checkbox" class="wcsom-po-select" value="' . $post->ID . '" data-price="'.$po_default_price.'" data-orig-price="'.floatval($supp_price).'" data-name="'.esc_attr($product->get_name()).'" data-model="'.esc_attr($supp_model).'"></td>';
            } else {
                $html .= '<td class="wcsom-td-check"><span class="dashicons dashicons-lock" style="color:#cbd5e1; font-size:16px;"></span></td>';
            }
            
            $html .= '<td class="wcsom-td-img">' . $img . '</td>';
            $html .= '<td><strong>' . esc_html($product->get_name()) . '</strong>';
            if ($supp_model) {
                $html .= '<br><span style="font-size:11px; color:#64748b;">Model: '.esc_html($supp_model).'</span>';
            }
            $html .= '</td>';
            $html .= '<td>' . $sku . '</td>';
            $html .= '<td>' . wcsom_format_usd($wc_price) . '</td>';
            
            if ($can_edit) {
                $html .= '<td>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <div style="display:flex; gap:6px; align-items:center;">
                            <span style="color:#94a3b8; font-weight:600; font-size:13px;">$</span>
                            <input type="number" step="0.01" class="wcsom-input wcsom-inline-supp-price" value="'.esc_attr($supp_price).'" placeholder="0.00" style="width:90px; padding:6px 10px;">
                        </div>
                        <input type="text" class="wcsom-input wcsom-inline-supp-model" value="'.esc_attr($supp_model).'" placeholder="Supplier Model" style="width:115px; padding:6px 10px; font-size:11px;">
                        <button type="button" class="wcsom-btn wcsom-btn-outline wcsom-save-supp-price-btn" data-id="'.$post->ID.'" style="padding:4px 10px; font-size:11px; width:fit-content;">Save Details</button>
                    </div>
                </td>';
                $html .= '<td style="vertical-align:top;"><button type="button" class="wcsom-btn wcsom-btn-outline wcsom-unassign-btn" data-id="'.$post->ID.'" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; font-size:12px;">Unassign</button></td>';
            } else {
                $html .= '<td>
                    <span style="font-weight:600; color:#475569; display:block;">' . wcsom_format_usd($supp_price) . '</span>
                    <span style="font-size:11px; color:#64748b;">Mdl: ' . ($supp_model ? esc_html($supp_model) : 'N/A') . '</span>
                </td>';
                $html .= '<td><span class="wcsom-badge" style="background:#f1f5f9; color:#94a3b8;">Locked</span></td>';
            }
            
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }

    public function ajax_unassign_product() {
        $this->verify_edit_access();
        $product_id = intval($_POST['product_id']);

        if ($product_id) {
            delete_post_meta($product_id, '_wcsom_supplier_id');
            delete_post_meta($product_id, '_wcsom_assigned_date'); // Clean up meta
            wp_send_json_success('Product unassigned.');
        }
        wp_send_json_error('Missing product ID.');
    }

    public function ajax_update_supplier_price_inline() {
        $this->verify_edit_access();
        $product_id = intval($_POST['product_id']);
        $price = floatval($_POST['supplier_price']);
        $model = isset($_POST['supplier_model']) ? sanitize_text_field($_POST['supplier_model']) : '';
        
        if ($product_id) {
            update_post_meta($product_id, '_wcsom_supplier_price', $price);
            update_post_meta($product_id, '_wcsom_supplier_model', $model);
            wp_send_json_success('Details updated successfully.');
        }
        wp_send_json_error('Missing data.');
    }

    public function ajax_search_wc_products() {
        $this->verify_access();
        $keyword = sanitize_text_field($_POST['keyword']);

        global $wpdb;
        $search_query = $wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
            LIMIT 15
        ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

        $product_ids = $wpdb->get_col($search_query);
        $result = array();

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                
                $assigned_supplier = get_post_meta($pid, '_wcsom_supplier_id', true);
                $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
                
                $assign_text = '';
                if ($assigned_supplier) {
                    $code = get_user_meta($assigned_supplier, 'supplier_code', true);
                    $company = get_user_meta($assigned_supplier, 'company_name', true) ?: 'Assigned';
                    $assign_text = ' <span style="color:#dc2626; font-size:11px;">[Currently: ' . ($code ? $code : $company) . ']</span>';
                }

                $result[] = array(
                    'id'   => $pid,
                    'name' => $product->get_name() . $sku_text . $assign_text
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_search_all_wc_products() {
        $this->verify_access();
        $keyword = sanitize_text_field($_GET['keyword']);

        global $wpdb;
        $search_query = $wpdb->prepare("
            SELECT DISTINCT p.ID FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku'
            WHERE p.post_type = 'product' 
            AND p.post_status IN ('publish', 'pending', 'draft')
            AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)
            LIMIT 20
        ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

        $product_ids = $wpdb->get_col($search_query);
        $result = array();

        if (!empty($product_ids)) {
            foreach ($product_ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) continue;
                
                $sku_text = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
                $supp_price = get_post_meta($pid, '_wcsom_supplier_price', true);
                $supp_model = get_post_meta($pid, '_wcsom_supplier_model', true);
                
                $result[] = array(
                    'id'         => $pid,
                    'text'       => $product->get_name() . $sku_text,
                    'price'      => $supp_price > 0 ? floatval($supp_price) : $product->get_price(),
                    'orig_price' => $supp_price ? floatval($supp_price) : 0,
                    'sku'        => $product->get_sku(),
                    'name'       => $product->get_name(),
                    'model'      => $supp_model
                );
            }
        }

        wp_send_json_success($result);
    }

    public function ajax_assign_product() {
        $this->verify_edit_access();
        $supplier_id = intval($_POST['supplier_id']);
        $product_id = intval($_POST['product_id']);

        if ($supplier_id && $product_id) {
            update_post_meta($product_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($product_id, '_wcsom_assigned_date', current_time('mysql')); // Log date for sorting
            wp_send_json_success('Product assigned successfully.');
        }
        wp_send_json_error('Missing data.');
    }

    public function ajax_create_po() {
        $this->verify_edit_access();
        $supplier_id = intval($_POST['supplier_id']);
        $order_ref = isset($_POST['order_ref']) && !empty(trim($_POST['order_ref'])) ? sanitize_text_field(trim($_POST['order_ref'])) : 'PO-' . date('Ymd') . '-' . rand(1000, 9999);
        $items = isset($_POST['items']) ? $_POST['items'] : array(); 

        if (!$supplier_id) {
            wp_send_json_error('Missing supplier.');
        }

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        if (!empty($items)) {
            foreach ($items as $item) {
                $qty = intval($item['qty']);
                $price = floatval($item['price']);
                $orig_price = isset($item['orig_price']) ? floatval($item['orig_price']) : 0;
                $supp_model = isset($item['supplier_model']) ? sanitize_text_field($item['supplier_model']) : '';
                
                $total_qty += $qty;
                $total_amount += ($qty * $price);
                
                $order_items[] = array(
                    'product_id'        => intval($item['id']),
                    'qty'               => $qty,
                    'price'             => $price,
                    'orig_price'        => $orig_price,
                    'supplier_model'    => $supp_model,
                    'added_by_supplier' => false
                );
            }
        }

        $post_id = wp_insert_post(array(
            'post_title'  => $order_ref,
            'post_type'   => 'wcsom_order',
            'post_status' => 'publish',
        ));

        if ($post_id) {
            $sec_code = mt_rand(1000000, 9999999); 
            
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_total_qty', $total_qty);
            update_post_meta($post_id, '_wcsom_total_amount', $total_amount);
            update_post_meta($post_id, '_wcsom_items', $order_items);
            update_post_meta($post_id, '_wcsom_status', 'waiting_for_quote');
            update_post_meta($post_id, '_wcsom_notes', '');
            update_post_meta($post_id, '_wcsom_tags', []); 
            update_post_meta($post_id, '_wcsom_security_code', $sec_code);
            update_post_meta($post_id, '_wcsom_payments', array());
            update_post_meta($post_id, '_wcsom_attachments', array());
            update_post_meta($post_id, '_wcsom_incoterm', 'EXW'); // Set default incoterm
            update_post_meta($post_id, '_wcsom_fob_port', ''); // Init empty port
            
            wp_send_json_success('Purchase order created successfully!');
        }

        wp_send_json_error('Failed to create order.');
    }

    public function ajax_save_po_edit() {
        $this->verify_edit_access();
        $po_id = intval($_POST['po_id']);
        $order_ref = sanitize_text_field($_POST['order_ref']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);
        $incoterm = sanitize_text_field($_POST['incoterm']);
        $fob_port = sanitize_text_field($_POST['fob_port']);
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        
        $tags_raw = sanitize_text_field($_POST['tags']);
        $tags_array = array_filter(array_map('trim', explode(',', $tags_raw)));

        $payments = isset($_POST['payments']) ? $_POST['payments'] : array();
        $attachments = isset($_POST['attachments']) ? $_POST['attachments'] : array();

        $total_qty = 0;
        $total_amount = 0;
        $order_items = array();

        foreach ($items as $item) {
            $qty = intval($item['qty']);
            $price = floatval($item['price']);
            $orig_price = isset($item['orig_price']) ? floatval($item['orig_price']) : 0;
            $supp_model = isset($item['supplier_model']) ? sanitize_text_field($item['supplier_model']) : '';
            $added = !empty($item['added']) ? true : false;
            
            if ($qty > 0) {
                $total_qty += $qty;
                $total_amount += ($qty * $price);
                $order_items[] = array(
                    'product_id'        => intval($item['id']),
                    'qty'               => $qty,
                    'price'             => $price,
                    'orig_price'        => $orig_price,
                    'supplier_model'    => $supp_model,
                    'added_by_supplier' => $added
                );
            }
        }

        $formatted_payments = [];
        if (!empty($payments)) {
            foreach($payments as $p) {
                $percent = floatval($p['percent']);
                $amount = ($percent > 0) ? ($percent / 100) * $total_amount : floatval($p['amount']);
                
                $formatted_payments[] = array(
                    'title'   => sanitize_text_field($p['title']),
                    'percent' => $percent,
                    'amount'  => $amount,
                    'status'  => sanitize_text_field($p['status'])
                );
            }
        }

        $formatted_attachments = [];
        if (!empty($attachments)) {
            foreach($attachments as $a) {
                $formatted_attachments[] = array(
                    'url'         => esc_url_raw($a['url']),
                    'name'        => sanitize_text_field($a['name']),
                    'type'        => sanitize_text_field($a['type']),
                    'uploaded_by' => sanitize_text_field($a['uploaded_by']),
                    'date'        => sanitize_text_field($a['date'])
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
        update_post_meta($po_id, '_wcsom_tags', $tags_array);
        update_post_meta($po_id, '_wcsom_incoterm', $incoterm);
        update_post_meta($po_id, '_wcsom_fob_port', $fob_port);
        update_post_meta($po_id, '_wcsom_payments', $formatted_payments);
        update_post_meta($po_id, '_wcsom_attachments', $formatted_attachments);

        wp_send_json_success('Purchase order updated successfully!');
    }

    public function ajax_delete_po() {
        $this->verify_edit_access();
        $po_id = intval($_POST['po_id']);
        
        if ($po_id) {
            wp_delete_post($po_id, true);
            wp_send_json_success('Purchase Order deleted successfully.');
        }
        wp_send_json_error('Failed to delete Purchase Order.');
    }

    public function ajax_bulk_delete_pos() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];

        if (empty($po_ids)) {
            wp_send_json_error('No orders selected.');
        }

        foreach ($po_ids as $po_id) {
            wp_delete_post($po_id, true);
        }

        wp_send_json_success('Selected orders deleted successfully.');
    }

    public function ajax_global_product_search() {
        $this->verify_access();
        
        $keyword = isset($_POST['keyword']) ? sanitize_text_field($_POST['keyword']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $assignment = isset($_POST['assignment']) ? sanitize_text_field($_POST['assignment']) : '';
        $can_edit = wcsom_can_edit();

        $visible_supplier_ids = wcsom_get_visible_suppliers();

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'pending', 'draft'),
            'posts_per_page' => 50, 
        );

        if (!empty($category)) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }

        if ($assignment === 'unassigned') {
            $args['meta_query'] = array(
                array(
                    'key'     => '_wcsom_supplier_id',
                    'compare' => 'NOT EXISTS'
                )
            );
        } elseif ($assignment === 'assigned') {
             $args['meta_query'] = array(
                array(
                    'key'     => '_wcsom_supplier_id',
                    'compare' => 'EXISTS'
                )
            );
        }

        if (!empty($keyword)) {
            global $wpdb;
            
            $tax_join = '';
            $tax_where = '';
            if (!empty($category)) {
                $term = get_term_by('slug', $category, 'product_cat');
                if ($term) {
                    $tax_join = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
                    $tax_where = "AND tr.term_taxonomy_id = " . intval($term->term_taxonomy_id);
                }
            }

            $meta_join = "LEFT JOIN {$wpdb->postmeta} pm_sku ON p.ID = pm_sku.post_id AND pm_sku.meta_key = '_sku'";
            $assignment_where = '';
            
            if ($assignment === 'unassigned') {
                $meta_join .= " LEFT JOIN {$wpdb->postmeta} pm_supp ON p.ID = pm_supp.post_id AND pm_supp.meta_key = '_wcsom_supplier_id'";
                $assignment_where = "AND pm_supp.meta_id IS NULL";
            } elseif ($assignment === 'assigned') {
                $meta_join .= " INNER JOIN {$wpdb->postmeta} pm_supp ON p.ID = pm_supp.post_id AND pm_supp.meta_key = '_wcsom_supplier_id'";
            }

            $search_query = $wpdb->prepare("
                SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                {$tax_join}
                {$meta_join}
                LEFT JOIN {$wpdb->term_relationships} tr_tags ON p.ID = tr_tags.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt_tags ON tr_tags.term_taxonomy_id = tt_tags.term_taxonomy_id AND tt_tags.taxonomy = 'product_tag'
                LEFT JOIN {$wpdb->terms} t_tags ON tt_tags.term_id = t_tags.term_id
                WHERE p.post_type = 'product' 
                AND p.post_status IN ('publish', 'pending', 'draft')
                {$tax_where}
                {$assignment_where}
                AND (
                    p.post_title LIKE %s 
                    OR pm_sku.meta_value LIKE %s
                    OR t_tags.name LIKE %s
                )
                LIMIT 50
            ", '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%', '%' . $wpdb->esc_like($keyword) . '%');

            $product_ids = $wpdb->get_col($search_query);
            $products = array();
            if (!empty($product_ids)) {
                $args_override = array(
                    'post_type' => 'product',
                    'post_status' => array('publish', 'pending', 'draft'),
                    'post__in' => $product_ids,
                    'posts_per_page' => -1
                );
                $products = get_posts($args_override);
            }
        } else {
            $products = get_posts($args);
        }

        $html = '';

        if (empty($products)) {
            wp_send_json_success('<tr><td colspan="6" style="text-align:center; padding: 40px; color:#64748b;">No products match your filters.</td></tr>');
        }

        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $supplier_id = get_post_meta($post->ID, '_wcsom_supplier_id', true);
            $supplier_display = '<span class="wcsom-badge" style="background:#f1f5f9; color:#64748b;">Unassigned</span>';
            $action_html = '';
            
            if ($supplier_id) {
                // If it is assigned but the supplier isn't in our visible list, restrict view details.
                if (!in_array($supplier_id, $visible_supplier_ids) && !current_user_can('manage_options')) {
                    $supplier_display = '<div style="display:flex; align-items:center; gap:8px;"><span class="wcsom-badge" style="background:#eef2ff; color:#4f46e5; border:1px solid #c7d2fe;">Assigned (Hidden Supplier)</span></div>';
                    $action_html = '<span style="color:#94a3b8; font-size:12px;">Locked</span>';
                } else {
                    $code = get_user_meta($supplier_id, 'supplier_code', true);
                    $company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
                    if (!trim($company)) {
                        $user_info = get_userdata($supplier_id);
                        $company = $user_info ? $user_info->display_name : 'Unknown';
                    }
                    
                    $supplier_display = '<div style="display:flex; align-items:center; gap:8px;"><span class="wcsom-badge" style="background:#eef2ff; color:#4f46e5; border:1px solid #c7d2fe;">Assigned: ' . ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company) . '</span>';
                    
                    if ($can_edit) {
                        $supplier_display .= '<button type="button" class="wcsom-btn wcsom-unassign-global-btn" data-id="'.$post->ID.'" style="padding:4px 8px; font-size:11px; background:none; border:none; color:#ef4444; text-decoration:underline; cursor:pointer;">Unassign</button>';
                        $action_html = '<span style="color:#94a3b8; font-size:12px;">Unassign to modify</span>';
                    } else {
                        $action_html = '<span style="color:#94a3b8; font-size:12px;">Read Only</span>';
                    }
                    $supplier_display .= '</div>';
                }
            } else {
                if ($can_edit) {
                    $action_html = '<div style="display:flex; gap:6px;">';
                    $action_html .= '<select class="wcsom-input wcsom-quick-assign-sel" style="width:140px; padding:4px 8px; font-size:12px;">';
                    $action_html .= '<option value="">Select Supplier...</option>';
                    foreach($visible_supplier_ids as $sid) {
                        $c = get_user_meta($sid, 'supplier_code', true);
                        $n = get_user_meta($sid, 'company_name', true) ?: get_userdata($sid)->display_name;
                        $action_html .= '<option value="'.$sid.'">'.esc_html(($c ? "[$c] " : "") . $n).'</option>';
                    }
                    $action_html .= '</select>';
                    $action_html .= '<button type="button" class="wcsom-btn wcsom-btn-primary wcsom-quick-assign-btn" data-id="'.$post->ID.'" style="padding:4px 10px; font-size:12px;">Assign</button>';
                    $action_html .= '</div>';
                } else {
                    $action_html = '<span style="color:#94a3b8; font-size:12px;">Read Only</span>';
                }
            }

            $img = $product->get_image('thumbnail', array('class' => 'wcsom-thumb'));
            $sku = $product->get_sku() ? $product->get_sku() : '<span style="color:#9ca3af;">N/A</span>';
            $supp_model = get_post_meta($post->ID, '_wcsom_supplier_model', true);
            
            $cats = wc_get_product_category_list($post->ID, ', ');
            $cats = $cats ? strip_tags($cats) : '<span style="color:#9ca3af;">None</span>';
            
            $wp_status = $post->post_status;
            $status_color = $wp_status === 'publish' ? '#16a34a' : ($wp_status === 'pending' ? '#d97706' : '#64748b');

            $html .= '<tr id="wcsom-global-row-'.$post->ID.'">';
            $html .= '<td style="display:flex; align-items:center; gap:12px;">' . $img . ' <div><strong><a href="'.get_edit_post_link($post->ID).'" target="_blank" style="text-decoration:none; color:inherit;">' . esc_html($product->get_name()) . '</a></strong>'.($supp_model ? '<br><span style="font-size:11px; color:#64748b;">Model: '.esc_html($supp_model).'</span>' : '').'</div></td>';
            $html .= '<td style="font-size:13px; color:#475569;">' . $cats . '</td>';
            $html .= '<td>' . $sku . '</td>';
            $html .= '<td><span style="font-weight:600; font-size:12px; color:'.$status_color.'; text-transform:uppercase;">' . esc_html($wp_status) . '</span></td>';
            $html .= '<td>' . $supplier_display . '</td>';
            $html .= '<td>' . $action_html . '</td>';
            $html .= '</tr>';
        }

        wp_send_json_success($html);
    }

    public function ajax_quick_create_product() {
        $this->verify_edit_access();

        $title = sanitize_text_field($_POST['title']);
        $sku = sanitize_text_field($_POST['sku']);
        $price = floatval($_POST['price']);
        $price_type = sanitize_text_field($_POST['price_type']);
        $category_id = intval($_POST['category_id']);
        $supplier_id = intval($_POST['supplier_id']);
        $image_id = intval($_POST['image_id']);
        $model_number = isset($_POST['model_number']) ? sanitize_text_field($_POST['model_number']) : '';

        if (empty($title) || empty($price)) {
            wp_send_json_error('Title and Price are required.');
        }

        $post_id = wp_insert_post(array(
            'post_title'   => $title,
            'post_type'    => 'product',
            'post_status'  => 'pending',
        ));

        if (is_wp_error($post_id)) {
            wp_send_json_error('Failed to create product.');
        }

        update_post_meta($post_id, '_sku', $sku);
        update_post_meta($post_id, '_regular_price', $price);
        update_post_meta($post_id, '_price', $price);
        update_post_meta($post_id, '_wcsom_supplier_price', $price);
        if ($model_number) update_post_meta($post_id, '_wcsom_supplier_model', $model_number);
        wp_set_object_terms($post_id, 'simple', 'product_type');

        if ($category_id) wp_set_object_terms($post_id, $category_id, 'product_cat');
        if ($image_id) set_post_thumbnail($post_id, $image_id);
        
        update_post_meta($post_id, '_wcsom_price_type', $price_type);
        if ($supplier_id) {
            update_post_meta($post_id, '_wcsom_supplier_id', $supplier_id);
            update_post_meta($post_id, '_wcsom_assigned_date', current_time('mysql')); // Log date for sorting
        }

        wp_send_json_success('Product created successfully and set to Pending.');
    }

    public function ajax_create_bulk_order() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];
        $title = sanitize_text_field($_POST['title']);

        if (empty($po_ids) || empty($title)) wp_send_json_error('Missing data.');

        $bulk_id = wp_insert_post(array(
            'post_title'  => $title,
            'post_type'   => 'wcsom_bulk_order',
            'post_status' => 'publish',
        ));

        if ($bulk_id) {
            update_post_meta($bulk_id, '_wcsom_po_ids', $po_ids);
            wp_send_json_success('Bulk Order created!');
        }
        wp_send_json_error('Failed to create Bulk Order.');
    }

    public function ajax_delete_bulk_order() {
        $this->verify_edit_access();
        $bulk_id = intval($_POST['bulk_id']);

        if ($bulk_id) {
            wp_delete_post($bulk_id, true);
            wp_send_json_success('Bulk Order deleted.');
        }
        wp_send_json_error('Failed to delete Bulk Order.');
    }

    public function ajax_bulk_add_tags() {
        $this->verify_edit_access();
        $po_ids = isset($_POST['po_ids']) ? array_map('intval', $_POST['po_ids']) : [];
        $tags_raw = sanitize_text_field($_POST['tags']);
        $new_tags = array_filter(array_map('trim', explode(',', $tags_raw)));

        if (empty($po_ids) || empty($new_tags)) wp_send_json_error('Missing data.');

        foreach ($po_ids as $po_id) {
            $existing_tags = get_post_meta($po_id, '_wcsom_tags', true) ?: [];
            $combined_tags = array_unique(array_merge($existing_tags, $new_tags));
            update_post_meta($po_id, '_wcsom_tags', array_values($combined_tags));
        }

        wp_send_json_success('Tags added successfully!');
    }

    public function ajax_get_supplier_packing_lists() {
        $this->verify_access();
        $supplier_id = intval($_POST['supplier_id']);
        if (!$supplier_id) wp_send_json_error('Missing supplier ID');

        $packing_lists = wcsom_get_supplier_packing_lists($supplier_id);
        
        $html = '';
        if (empty($packing_lists)) {
            $html = '<div style="color:#94a3b8; font-size:13px; font-style:italic;">No packing lists found for this supplier.</div>';
        } else {
            $html .= '<ul style="margin:0; padding:0; list-style:none;">';
            foreach ($packing_lists as $pl) {
                $edit_url = get_edit_post_link($pl->ID);
                $html .= '<li style="margin-bottom:8px; padding:8px; background:#fff; border:1px solid #e2e8f0; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">';
                $html .= '<div><a href="'.esc_url($edit_url).'" target="_blank" style="font-weight:600; font-size:13px; color:#4f46e5; text-decoration:none;">'.esc_html($pl->post_title).'</a>';
                $html .= '<div style="font-size:11px; color:#64748b;">'.date('M d, Y', strtotime($pl->post_date)).'</div></div>';
                $html .= '<a href="'.esc_url($edit_url).'" target="_blank" class="wcsom-btn wcsom-btn-outline" style="padding:4px 8px; font-size:11px;">View</a>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        wp_send_json_success($html);
    }
}
