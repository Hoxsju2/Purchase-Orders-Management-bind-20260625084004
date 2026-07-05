<?php
if (!defined('ABSPATH')) exit;

class WCSOM_Frontend {

    public function init() {
        add_shortcode('wcsom_supplier_portal', array($this, 'render_supplier_portal'));
        add_shortcode('wcsom_admin_portal', array($this, 'render_admin_portal'));
        
        add_action('template_redirect', array($this, 'handle_frontend_routes'));
        add_action('admin_post_wcsom_supplier_update_po', array($this, 'handle_supplier_update'));
        add_action('admin_post_nopriv_wcsom_supplier_update_po', array($this, 'handle_supplier_update'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_admin_assets'));

        // OTP Handlers
        add_action('wp_ajax_nopriv_wcsom_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_nopriv_wcsom_verify_otp', array($this, 'ajax_verify_otp'));
    }

    public function enqueue_frontend_admin_assets() {
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'wcsom_admin_portal') && wcsom_is_staff()) {
            wp_enqueue_media(); 
            wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css');
            wp_enqueue_script('selectWoo', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);

            wp_enqueue_style('wcsom-admin-css', WCSOM_PLUGIN_URL . 'assets/admin.css', array(), WCSOM_VERSION);
            wp_enqueue_script('wcsom-admin-js', WCSOM_PLUGIN_URL . 'assets/admin.js', array('jquery', 'selectWoo'), WCSOM_VERSION, true);

            wp_localize_script('wcsom-admin-js', 'wcsom_ajax', array(
                'ajax_url'   => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('wcsom_admin_nonce'),
                'url_orders' => wcsom_get_tab_url('orders'),
                'url_bulk'   => wcsom_get_tab_url('bulk-orders')
            ));
        }
    }

    public function handle_frontend_routes() {
        // Individual PO Print
        if (isset($_GET['wcsom_print']) && isset($_GET['po_id'])) {
            $po_id = intval($_GET['po_id']);
            $order = get_post($po_id);
            if (!$order || $order->post_type !== 'wcsom_order') wp_die('Invalid Purchase Order.');

            $supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
            $sec_code = get_post_meta($po_id, '_wcsom_security_code', true);
            $is_authorized = false;
            
            if (wcsom_is_staff() || (is_user_logged_in() && get_current_user_id() == $supplier_id)) {
                $is_authorized = true;
            } elseif (isset($_GET['sec_code']) && $_GET['sec_code'] === $sec_code) {
                $is_authorized = true;
            } elseif (isset($_COOKIE['wcsom_code_' . $po_id]) && $_COOKIE['wcsom_code_' . $po_id] === $sec_code) {
                $is_authorized = true;
            }

            if (!$is_authorized) wp_die('You do not have permission to view this order.');

            include WCSOM_PLUGIN_DIR . 'includes/views/po-print.php';
            exit;
        }

        // Internal Admin PDF Print
        if (isset($_GET['wcsom_admin_print']) && isset($_GET['po_id'])) {
            if (!wcsom_is_staff()) wp_die('Unauthorized. Only staff can access the internal admin PDF.');
            $po_id = intval($_GET['po_id']);
            $order = get_post($po_id);
            if (!$order || $order->post_type !== 'wcsom_order') wp_die('Invalid Purchase Order.');
            
            include WCSOM_PLUGIN_DIR . 'includes/views/po-print-admin.php';
            exit;
        }

        // Bulk Order Print
        if (isset($_GET['wcsom_bulk_print']) && isset($_GET['bulk_id'])) {
            if (!wcsom_is_staff()) wp_die('Unauthorized. Only staff can print bulk orders.');
            $bulk_id = intval($_GET['bulk_id']);
            include WCSOM_PLUGIN_DIR . 'includes/views/bulk-po-print.php';
            exit;
        }

        // Standalone Portal
        if (isset($_GET['wcsom_portal']) && isset($_GET['po_id'])) {
            include WCSOM_PLUGIN_DIR . 'includes/views/standalone-portal.php';
            exit;
        }
    }

    public function render_supplier_portal($atts) {
        if (!is_user_logged_in()) {
            return '<div class="wcsom-notice">Please log in to view your orders.</div>';
        }
        ob_start();
        include WCSOM_PLUGIN_DIR . 'includes/views/shortcode-portal.php';
        return ob_get_clean();
    }

    public function render_admin_portal($atts) {
        ob_start();
        include WCSOM_PLUGIN_DIR . 'includes/views/shortcode-admin-portal.php';
        return ob_get_clean();
    }

    // --- OTP Login Handlers ---
    public function ajax_send_otp() {
        $email = sanitize_email($_POST['email']);
        $user = get_user_by('email', $email);

        if (!$user || (!in_array('wcsom_staff', (array)$user->roles) && !in_array('administrator', (array)$user->roles))) {
            wp_send_json_error('This email is not authorized for staff access.');
        }

        $otp = sprintf("%06d", mt_rand(1, 999999));
        update_user_meta($user->ID, '_wcsom_login_otp', $otp);
        update_user_meta($user->ID, '_wcsom_login_otp_time', time());

        $subject = 'Your Secure Login OTP - ' . get_bloginfo('name');
        $message = "Hello,\n\nYour secure login code for the Purchase Orders Management portal is: $otp\n\nThis code will expire in 15 minutes.\n\nThank you.";
        
        wp_mail($email, $subject, $message);
        wp_send_json_success('OTP Sent.');
    }

    public function ajax_verify_otp() {
        $email = sanitize_email($_POST['email']);
        $otp = sanitize_text_field($_POST['otp']);
        
        $user = get_user_by('email', $email);
        if (!$user) wp_send_json_error('User not found.');

        $saved_otp = get_user_meta($user->ID, '_wcsom_login_otp', true);
        $otp_time = get_user_meta($user->ID, '_wcsom_login_otp_time', true);

        if ($saved_otp !== $otp || (time() - $otp_time > 900)) {
            wp_send_json_error('Invalid or expired OTP code.');
        }

        delete_user_meta($user->ID, '_wcsom_login_otp');
        delete_user_meta($user->ID, '_wcsom_login_otp_time');

        wp_set_auth_cookie($user->ID, true);
        wp_send_json_success('Authenticated successfully.');
    }

    public function handle_supplier_update() {
        if (!isset($_POST['po_id']) || !isset($_POST['wcsom_nonce'])) wp_die('Missing required form parameters.');
        if (!wp_verify_nonce($_POST['wcsom_nonce'], 'wcsom_update_po')) wp_die('Security check failed.');

        $po_id = intval($_POST['po_id']);
        $order = get_post($po_id);
        if (!$order || $order->post_type !== 'wcsom_order') wp_die('Invalid PO.');

        $supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
        $sec_code = get_post_meta($po_id, '_wcsom_security_code', true);
        $is_authorized = false;
        
        if (is_user_logged_in() && get_current_user_id() == $supplier_id) {
            $is_authorized = true;
        } elseif (isset($_POST['wcsom_verify_code'])) {
            $cleaned_input = preg_replace('/\s+/', '', sanitize_text_field($_POST['wcsom_verify_code']));
            if ($cleaned_input === $sec_code) $is_authorized = true;
        } elseif (isset($_COOKIE['wcsom_code_' . $po_id]) && $_COOKIE['wcsom_code_' . $po_id] === $sec_code) {
            $is_authorized = true;
        }

        if (!$is_authorized) wp_die('Unauthorized.');

        $status = get_post_meta($po_id, '_wcsom_status', true);
        if (!in_array($status, ['draft', 'waiting_for_quote'])) {
            wp_die('This order has already been submitted and can no longer be edited by the supplier.');
        }

        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $notes = isset($_POST['po_notes']) ? sanitize_textarea_field($_POST['po_notes']) : '';
        $incoterm = isset($_POST['wcsom_incoterm']) ? sanitize_text_field($_POST['wcsom_incoterm']) : 'EXW';
        $fob_port = isset($_POST['wcsom_fob_port']) ? sanitize_text_field($_POST['wcsom_fob_port']) : '';
        $allow_supp_qty = get_post_meta($po_id, '_wcsom_allow_supp_qty', true) === 'yes';
        
        $old_items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
        $order_items = [];
        $total_qty = 0;
        $total_amt = 0;

        foreach ($old_items as $oi) {
            $product_id = intval($oi['product_id']);
            $qty = intval($oi['qty']); 
            
            // Allow supplier to override quantity if the setting is ON
            if ($allow_supp_qty && isset($items[$product_id]['qty']) && trim($items[$product_id]['qty']) !== '') {
                $posted_qty = intval($items[$product_id]['qty']);
                if ($posted_qty >= 0) {
                    $qty = $posted_qty;
                }
            }

            $added_by_supp = !empty($oi['added_by_supplier']);
            $orig_price = isset($oi['orig_price']) ? floatval($oi['orig_price']) : floatval(get_post_meta($product_id, '_wcsom_supplier_price', true));

            $price = floatval($oi['price']);
            if (isset($items[$product_id]) && isset($items[$product_id]['price']) && trim($items[$product_id]['price']) !== '') {
                $price = floatval($items[$product_id]['price']);
            }

            if ($qty > 0) {
                $total_qty += $qty;
                $total_amt += ($qty * $price);
                $order_items[] = array(
                    'product_id' => $product_id,
                    'qty'        => $qty,
                    'price'      => $price,
                    'orig_price' => $orig_price, 
                    'added_by_supplier' => $added_by_supp
                );
            }
        }

        update_post_meta($po_id, '_wcsom_items', $order_items);
        update_post_meta($po_id, '_wcsom_total_qty', $total_qty);
        update_post_meta($po_id, '_wcsom_total_amount', $total_amt);
        update_post_meta($po_id, '_wcsom_notes', $notes);
        update_post_meta($po_id, '_wcsom_incoterm', $incoterm);
        update_post_meta($po_id, '_wcsom_fob_port', $fob_port);
        update_post_meta($po_id, '_wcsom_status', 'pending');

        $payments = get_post_meta($po_id, '_wcsom_payments', true);
        if (!empty($payments) && is_array($payments)) {
            foreach ($payments as &$p) {
                if (isset($p['percent']) && $p['percent'] > 0) {
                    $p['amount'] = (floatval($p['percent']) / 100) * $total_amt;
                }
            }
            update_post_meta($po_id, '_wcsom_payments', $payments);
        }

        if (in_array($status, ['draft', 'waiting_for_quote']) && isset($_FILES['wcsom_attachments']) && isset($_FILES['wcsom_attachments']['name']) && !empty($_FILES['wcsom_attachments']['name'][0])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $files = $_FILES['wcsom_attachments'];
            $existing_attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];
            $original_files = $_FILES;

            $file_count = count($files['name']);
            for ($i = 0; $i < $file_count; $i++) {
                if (!empty($files['name'][$i])) {
                    $file_data = array(
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i]
                    );
                    
                    $_FILES = array('upload_file' => $file_data);
                    $attach_id = media_handle_upload('upload_file', $po_id);
                    
                    if (!is_wp_error($attach_id)) {
                        $existing_attachments[] = array(
                            'url' => wp_get_attachment_url($attach_id),
                            'name' => $file_data['name'],
                            'type' => get_post_mime_type($attach_id),
                            'uploaded_by' => 'Supplier',
                            'date' => current_time('mysql')
                        );
                    }
                }
            }
            $_FILES = $original_files;
            update_post_meta($po_id, '_wcsom_attachments', $existing_attachments);
        }

        $redirect_url = add_query_arg(array('wcsom_portal' => '1', 'po_id' => $po_id, 'updated' => '1'), home_url('/'));
        wp_redirect($redirect_url);
        exit;
    }
}
