<?php
if (!defined('ABSPATH')) exit;

class WCSOM_Frontend {

    public function init() {
        add_shortcode('wcsom_supplier_portal', array($this, 'render_supplier_portal'));
        add_action('template_redirect', array($this, 'handle_frontend_routes'));
        add_action('admin_post_wcsom_supplier_update_po', array($this, 'handle_supplier_update'));
        add_action('admin_post_nopriv_wcsom_supplier_update_po', array($this, 'handle_supplier_update'));
    }

    public function handle_frontend_routes() {
        // Handle PDF Print
        if (isset($_GET['wcsom_print']) && isset($_GET['po_id'])) {
            $po_id = intval($_GET['po_id']);
            $order = get_post($po_id);
            
            if (!$order || $order->post_type !== 'wcsom_order') {
                wp_die('Invalid Purchase Order.');
            }

            $supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
            if (!current_user_can('manage_options') && get_current_user_id() != $supplier_id) {
                wp_die('You do not have permission to view this order.');
            }

            include WCSOM_PLUGIN_DIR . 'includes/views/po-print.php';
            exit;
        }

        // Handle Standalone Modern Portal (Guest Security Code OR Logged-In)
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

    public function handle_supplier_update() {
        if (isset($_POST['wcsom_supplier_update_po']) && wp_verify_nonce($_POST['wcsom_nonce'], 'wcsom_update_po')) {
            $po_id = intval($_POST['po_id']);
            $order = get_post($po_id);
            
            if (!$order || $order->post_type !== 'wcsom_order') wp_die('Invalid PO.');

            // Verify Access
            $supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
            $sec_code = get_post_meta($po_id, '_wcsom_security_code', true);
            
            $is_authorized = false;
            
            if (is_user_logged_in() && get_current_user_id() == $supplier_id) {
                $is_authorized = true;
            } elseif (isset($_POST['wcsom_verify_code'])) {
                // Strip all spaces automatically on backend just in case
                $cleaned_input = preg_replace('/\s+/', '', sanitize_text_field($_POST['wcsom_verify_code']));
                if ($cleaned_input === $sec_code) {
                    $is_authorized = true;
                }
            } elseif (isset($_COOKIE['wcsom_code_' . $po_id]) && $_COOKIE['wcsom_code_' . $po_id] === $sec_code) {
                $is_authorized = true;
            }

            if (!$is_authorized) {
                wp_die('Unauthorized. Security code mismatch or session expired.');
            }

            $status = get_post_meta($po_id, '_wcsom_status', true);
            
            if (!in_array($status, ['draft', 'waiting_for_quote', 'pending'])) {
                wp_die('This order can no longer be edited.');
            }

            $items = isset($_POST['items']) ? $_POST['items'] : [];
            $notes = isset($_POST['po_notes']) ? sanitize_textarea_field($_POST['po_notes']) : '';
            
            // Map original items, enforcing no newly added products and keeping original quantities intact
            $old_items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
            
            $order_items = [];
            $total_qty = 0;
            $total_amt = 0;

            foreach ($old_items as $oi) {
                $product_id = intval($oi['product_id']);
                
                // FORCE original quantity (suppress edits made through DOM manipulation)
                $qty = intval($oi['qty']); 
                $added_by_supp = !empty($oi['added_by_supplier']);

                // Verify if a price was submitted for this existing item
                $price = floatval($oi['price']);
                if (isset($items[$product_id]) && isset($items[$product_id]['price'])) {
                    $price = floatval($items[$product_id]['price']);
                }

                if ($qty > 0) {
                    $total_qty += $qty;
                    $total_amt += ($qty * $price);
                    $order_items[] = array(
                        'product_id' => $product_id,
                        'qty'        => $qty,
                        'price'      => $price,
                        'added_by_supplier' => $added_by_supp
                    );
                }
            }

            update_post_meta($po_id, '_wcsom_items', $order_items);
            update_post_meta($po_id, '_wcsom_total_qty', $total_qty);
            update_post_meta($po_id, '_wcsom_total_amount', $total_amt);
            update_post_meta($po_id, '_wcsom_notes', $notes);
            update_post_meta($po_id, '_wcsom_status', 'pending');

            // --- RECALCULATE PAYMENTS ---
            // If the supplier changed the prices, automatically calculate the new deposit/payment amounts based on percentage
            $payments = get_post_meta($po_id, '_wcsom_payments', true);
            if (!empty($payments) && is_array($payments)) {
                foreach ($payments as &$p) {
                    if (isset($p['percent']) && $p['percent'] > 0) {
                        $p['amount'] = (floatval($p['percent']) / 100) * $total_amt;
                    }
                }
                update_post_meta($po_id, '_wcsom_payments', $payments);
            }

            // Handle file uploads securely - Added 'isset' checks to prevent White Screen (Fatal Error)
            if (in_array($status, ['draft', 'waiting_for_quote']) && isset($_FILES['wcsom_attachments']) && isset($_FILES['wcsom_attachments']['name']) && !empty($_FILES['wcsom_attachments']['name'][0])) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';

                $files = $_FILES['wcsom_attachments'];
                $existing_attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];

                foreach ($files['name'] as $key => $value) {
                    if ($files['name'][$key]) {
                        $file = array(
                            'name'     => $files['name'][$key],
                            'type'     => $files['type'][$key],
                            'tmp_name' => $files['tmp_name'][$key],
                            'error'    => $files['error'][$key],
                            'size'     => $files['size'][$key]
                        );
                        $_FILES = array('upload_file' => $file);
                        $attach_id = media_handle_upload('upload_file', $po_id);
                        if (!is_wp_error($attach_id)) {
                            $existing_attachments[] = array(
                                'url' => wp_get_attachment_url($attach_id),
                                'name' => $file['name'],
                                'type' => get_post_mime_type($attach_id),
                                'uploaded_by' => 'Supplier',
                                'date' => current_time('mysql')
                            );
                        }
                    }
                }
                update_post_meta($po_id, '_wcsom_attachments', $existing_attachments);
            }

            // Reliable redirect to prevent the blank white page issue
            $redirect_url = add_query_arg(array('wcsom_portal' => '1', 'po_id' => $po_id, 'updated' => '1'), home_url('/'));
            wp_redirect($redirect_url);
            exit;
        }
    }
}
