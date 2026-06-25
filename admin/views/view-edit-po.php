<?php
if (!defined('ABSPATH')) exit;

$po_id = intval($_GET['id']);
$order = get_post($po_id);
if (!$order || $order->post_type !== 'wcsom_order') {
    echo '<div class="wcsom-card"><h3>Order not found.</h3></div>';
    return;
}

$supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
$status = get_post_meta($po_id, '_wcsom_status', true) ?: 'waiting_for_quote';
$items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
$notes = get_post_meta($po_id, '_wcsom_notes', true) ?: '';
$payments = get_post_meta($po_id, '_wcsom_payments', true) ?: [];
$attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];
$sec_code = get_post_meta($po_id, '_wcsom_security_code', true);

// Get Supplier details for display
$code = get_user_meta($supplier_id, 'supplier_code', true);
$company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
if (!trim($company)) {
    $user_info = get_userdata($supplier_id);
    $company = $user_info ? $user_info->display_name : 'Unknown';
}
$supplier_display = ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company);

// Fetch products strictly assigned to this supplier for the quick dropdown
$assigned_args = array(
    'post_type' => 'product',
    'posts_per_page' => -1,
    'meta_query' => array(
        array('key' => '_wcsom_supplier_id', 'value' => $supplier_id)
    )
);
$assigned_products = get_posts($assigned_args);
?>
<div class="wcsom-flex-between wcsom-mb-4">
    <a href="?page=wcsom-dashboard&tab=orders" class="wcsom-btn wcsom-btn-outline">&larr; Back to Directory</a>
    <a href="<?php echo esc_url(home_url('/?wcsom_print=1&po_id=' . $po_id)); ?>" target="_blank" class="wcsom-btn wcsom-btn-primary">
        <span class="dashicons dashicons-media-document" style="margin-top:2px;"></span> Preview / Print / PDF
    </a>
</div>

<div class="wcsom-grid">
    <div class="wcsom-main-content" style="flex: 2;">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Order Items</h3>
            <p class="wcsom-card-desc wcsom-mb-4">Update quantities, adjust prices, or remove items. Set quantity to 0 to remove an item on save.</p>
            
            <div class="wcsom-table-responsive" style="margin-top: 0; overflow:visible;">
                <table class="wcsom-table" id="wcsom-edit-po-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th style="width:120px;">Unit Price ($)</th>
                            <th style="width:100px;">Qty</th>
                            <th style="text-align:right;">Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        foreach ($items as $item): 
                            $product = wc_get_product($item['product_id']);
                            if (!$product) continue;
                            $line_total = $item['qty'] * $item['price'];
                            $grand_total += $line_total;
                            $is_added_by_supplier = !empty($item['added_by_supplier']);
                        ?>
                        <tr class="wcsom-edit-row" data-id="<?php echo esc_attr($item['product_id']); ?>" data-added="<?php echo $is_added_by_supplier ? '1' : '0'; ?>">
                            <td>
                                <strong style="<?php echo $is_added_by_supplier ? 'color: #dc2626;' : ''; ?>"><?php echo esc_html($product->get_name()); ?></strong>
                                <?php if($is_added_by_supplier) echo '<span style="font-size: 11px; color:#dc2626; display:block;">(Added by Supplier)</span>'; ?>
                            </td>
                            <td style="color:#64748b; font-size: 13px;"><?php echo esc_html($product->get_sku()); ?></td>
                            <td><input type="number" step="0.01" class="wcsom-input wcsom-edit-price" value="<?php echo esc_attr($item['price']); ?>"></td>
                            <td><input type="number" min="0" class="wcsom-input wcsom-edit-qty" value="<?php echo esc_attr($item['qty']); ?>"></td>
                            <td style="text-align:right;" class="wcsom-row-subtotal"><strong><?php echo wcsom_format_usd($line_total); ?></strong></td>
                            <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-row" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 wcsom-flex-between" style="border-top:1px solid var(--wcsom-border); padding-top:20px;">
                <div style="flex:1; display: flex; gap: 20px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">1. Add Assigned Product</label>
                        <div style="display:flex; gap: 8px;">
                            <select id="wcsom-add-assigned-product" class="wcsom-input" style="flex:1;">
                                <option value="">-- Select from Assigned --</option>
                                <?php foreach($assigned_products as $ap): $p = wc_get_product($ap->ID); ?>
                                    <option value="<?php echo $ap->ID; ?>" data-price="<?php echo $p->get_price(); ?>" data-sku="<?php echo $p->get_sku(); ?>"><?php echo $p->get_name(); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" id="wcsom-btn-add-assigned" class="wcsom-btn wcsom-btn-outline">Add</button>
                        </div>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">2. Add ANY Product Globally</label>
                        <select id="wcsom-add-product-select" class="wcsom-input" style="width: 100%;"></select>
                    </div>
                </div>
            </div>
            
            <div style="text-align: right; margin-top: 30px; border-top:1px solid var(--wcsom-border); padding-top:20px;">
                <div style="font-size: 13px; color: #64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Grand Total</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--wcsom-text-main);" id="wcsom-grand-total" data-total="<?php echo esc_attr($grand_total); ?>">
                    <?php echo wcsom_format_usd($grand_total); ?>
                </div>
            </div>
        </div>
        
        <div class="wcsom-card mt-4">
            <div class="wcsom-flex-between">
                <div>
                    <h3 class="wcsom-card-title">Payment Schedule</h3>
                    <p class="wcsom-card-desc">Assign payments (e.g., Deposit) based on a percentage of the total.</p>
                </div>
                <button id="wcsom-btn-add-payment" class="wcsom-btn wcsom-btn-outline"><span class="dashicons dashicons-plus-alt2" style="margin-top:4px;"></span> Add Payment</button>
            </div>
            
            <div class="wcsom-table-responsive" style="margin-top: 15px;">
                <table class="wcsom-table" id="wcsom-payments-table">
                    <thead>
                        <tr>
                            <th>Payment Title</th>
                            <th style="width:100px;">Percent (%)</th>
                            <th style="text-align:right;">Amount (USD)</th>
                            <th style="width:160px;">Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr class="wcsom-pay-row">
                            <td><input type="text" class="wcsom-input wcsom-pay-title" value="<?php echo esc_attr($p['title']); ?>" placeholder="e.g., 20% Deposit"></td>
                            <td><input type="number" step="0.01" min="0" max="100" class="wcsom-input wcsom-pay-percent" value="<?php echo esc_attr($p['percent']); ?>"></td>
                            <td style="text-align:right; font-weight:600;">
                                <span class="wcsom-pay-amt-display"><?php echo wcsom_format_usd($p['amount']); ?></span>
                                <input type="hidden" class="wcsom-pay-amount" value="<?php echo esc_attr($p['amount']); ?>">
                            </td>
                            <td>
                                <select class="wcsom-input wcsom-pay-status">
                                    <option value="unpaid" <?php selected($p['status'], 'unpaid'); ?>>Unpaid</option>
                                    <option value="paid" <?php selected($p['status'], 'paid'); ?>>Paid</option>
                                    <option value="awaiting_production" <?php selected($p['status'], 'awaiting_production'); ?>>Awaiting Production</option>
                                    <option value="awaiting_delivery" <?php selected($p['status'], 'awaiting_delivery'); ?>>Awaiting Delivery</option>
                                </select>
                            </td>
                            <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-payment" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wcsom-card mt-4">
            <div class="wcsom-flex-between">
                <div>
                    <h3 class="wcsom-card-title">Attachments & Documents</h3>
                    <p class="wcsom-card-desc">Upload files like PDFs, images, or receipts to keep track of PO documents.</p>
                </div>
                <button id="wcsom-btn-add-attachment" class="wcsom-btn wcsom-btn-outline"><span class="dashicons dashicons-upload" style="margin-top:2px;"></span> Upload File</button>
            </div>
            
            <div class="wcsom-attachments-list" id="wcsom-attachments-list" style="margin-top: 15px; display:flex; flex-direction:column; gap:10px;">
                <?php foreach($attachments as $idx => $att): ?>
                    <div class="wcsom-attachment-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span class="dashicons dashicons-media-document" style="color:#64748b; font-size:24px; width:24px; height:24px;"></span>
                            <div>
                                <a href="<?php echo esc_url($att['url']); ?>" target="_blank" style="font-weight:600; text-decoration:none; color:#4f46e5; font-size:14px;"><?php echo esc_html($att['name']); ?></a>
                                <div style="font-size:12px; color:#64748b; margin-top:2px;">Type: <?php echo esc_html($att['type']); ?> | By: <?php echo esc_html($att['uploaded_by']); ?> | <?php echo date('M d, Y', strtotime($att['date'])); ?></div>
                            </div>
                        </div>
                        <button class="wcsom-btn wcsom-btn-outline wcsom-remove-attachment" style="padding:4px 8px; font-size:12px; color:#ef4444; border-color:#fca5a5;">Remove</button>
                        <input type="hidden" class="wcsom-att-data" data-url="<?php echo esc_attr($att['url']); ?>" data-name="<?php echo esc_attr($att['name']); ?>" data-type="<?php echo esc_attr($att['type']); ?>" data-by="<?php echo esc_attr($att['uploaded_by']); ?>" data-date="<?php echo esc_attr($att['date']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="wcsom-card mt-4">
            <h3 class="wcsom-card-title">Order Notes</h3>
            <p class="wcsom-card-desc wcsom-mb-4">Notes left by the supplier or internal admin notes. These will appear on the PDF.</p>
            <textarea id="wcsom-edit-notes" class="wcsom-input" rows="4" placeholder="Enter any notes or special instructions here..."><?php echo esc_textarea($notes); ?></textarea>
        </div>
    </div>

    <div class="wcsom-sidebar" style="flex: 1;">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Order Details</h3>
            
            <div class="wcsom-mb-4 mt-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Order Reference</label>
                <input type="text" id="wcsom-edit-ref" class="wcsom-input" value="<?php echo esc_attr($order->post_title); ?>" style="font-size: 15px; font-weight: 600;">
            </div>
            
            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Supplier</label>
                <div style="font-size:14px; color:#334155;"><?php echo $supplier_display; ?></div>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Security Code</label>
                <div style="font-size:16px; font-family:monospace; font-weight:700; color:#0f172a; background:#f1f5f9; padding:8px 12px; border-radius:6px; border:1px dashed #cbd5e1; display:inline-block; letter-spacing: 2px;">
                    <?php echo esc_html($sec_code ?: 'N/A'); ?>
                </div>
                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Used by supplier for portal access</p>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Status</label>
                <select id="wcsom-edit-status" class="wcsom-input">
                    <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
                    <option value="waiting_for_quote" <?php selected($status, 'waiting_for_quote'); ?>>Waiting for Quote</option>
                    <option value="pending" <?php selected($status, 'pending'); ?>>Pending (Supplier Submitted)</option>
                    <option value="in progress" <?php selected($status, 'in progress'); ?>>In Progress</option>
                    <option value="finished" <?php selected($status, 'finished'); ?>>Finished</option>
                    <option value="delivered" <?php selected($status, 'delivered'); ?>>Delivered</option>
                </select>
            </div>

            <button id="wcsom-btn-save-po" class="wcsom-btn wcsom-btn-primary" style="width: 100%; justify-content: center; padding: 10px 0; font-size:14px;" data-po="<?php echo esc_attr($po_id); ?>" data-supplier="<?php echo esc_attr($supplier_id); ?>">
                Save All Changes
            </button>
        </div>
    </div>
</div>
