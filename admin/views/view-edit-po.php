<?php
if (!defined('ABSPATH')) exit;

$po_id = intval($_GET['id']);
$order = get_post($po_id);
if (!$order || $order->post_type !== 'wcsom_order') {
    echo '<div class="wcsom-card"><h3>Order not found.</h3></div>';
    return;
}

$can_edit = wcsom_can_edit();
$supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
$status = get_post_meta($po_id, '_wcsom_status', true) ?: 'waiting_for_quote';
$items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
$notes = get_post_meta($po_id, '_wcsom_notes', true) ?: '';
$tags = get_post_meta($po_id, '_wcsom_tags', true) ?: [];
$tags_str = implode(', ', $tags);

$incoterm = get_post_meta($po_id, '_wcsom_incoterm', true) ?: 'EXW';
$fob_port = get_post_meta($po_id, '_wcsom_fob_port', true) ?: '';
$allow_supp_qty = get_post_meta($po_id, '_wcsom_allow_supp_qty', true) === 'yes' ? 'yes' : 'no';

$payments = get_post_meta($po_id, '_wcsom_payments', true) ?: [];
$attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];
$sec_code = get_post_meta($po_id, '_wcsom_security_code', true);

// Generate supplier portal link
$portal_link = home_url('/?wcsom_portal=1&po_id=' . $po_id);

$code = get_user_meta($supplier_id, 'supplier_code', true);
$company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
if (!trim($company)) {
    $user_info = get_userdata($supplier_id);
    $company = $user_info ? $user_info->display_name : 'Unknown';
}
$supplier_display = ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company);

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
    <a href="<?php echo esc_url(wcsom_get_tab_url('orders')); ?>" class="wcsom-btn wcsom-btn-outline">&larr; Back to Directory</a>
    <div style="display:flex; gap:12px;">
        <a href="<?php echo esc_url(home_url('/?wcsom_admin_print=1&po_id=' . $po_id)); ?>" target="_blank" class="wcsom-btn" style="background:#0f172a; color:#fff;">
            <span class="dashicons dashicons-lock" style="margin-top:2px;"></span> Internal Admin PDF
        </a>
        <a href="<?php echo esc_url(home_url('/?wcsom_print=1&po_id=' . $po_id)); ?>" target="_blank" class="wcsom-btn wcsom-btn-primary">
            <span class="dashicons dashicons-media-document" style="margin-top:2px;"></span> Standard PDF
        </a>
    </div>
</div>

<?php if (!$can_edit): ?>
<div class="wcsom-mb-4" style="background:#fffbeb; color:#92400e; padding:12px 20px; border-radius:8px; border:1px solid #fde68a;">
    <strong>Read Only Mode:</strong> You do not have permission to modify this purchase order.
</div>
<?php endif; ?>

<div class="wcsom-grid">
    <div class="wcsom-main-content" style="flex: 2;">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Order Items</h3>
            <p class="wcsom-card-desc wcsom-mb-4">View items, quantities, and their difference percentage from your original supplier base price.</p>
            
            <div class="wcsom-table-responsive" style="margin-top: 0; overflow:visible;">
                <table class="wcsom-table" id="wcsom-edit-po-table">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU / HS</th>
                            <th style="width:110px;">Orig. Price</th>
                            <th style="width:110px;">PO Price ($)</th>
                            <th style="width:80px; text-align:center;">Diff %</th>
                            <th style="width:80px;">Qty</th>
                            <th style="text-align:right;">Subtotal</th>
                            <?php if ($can_edit): ?><th></th><?php endif; ?>
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
                            
                            $orig_price = isset($item['orig_price']) ? floatval($item['orig_price']) : floatval(get_post_meta($item['product_id'], '_wcsom_supplier_price', true));
                            $supp_model = isset($item['supplier_model']) ? $item['supplier_model'] : get_post_meta($item['product_id'], '_wcsom_supplier_model', true);
                            $hs_code = get_post_meta($item['product_id'], '_wcsom_hs_code', true);
                            
                            // Prevent pre-filling "0.00" on new items, ensuring it displays fully blank
                            $display_price = $item['price'] > 0 ? esc_attr($item['price']) : '';
                        ?>
                        <tr class="wcsom-edit-row" data-id="<?php echo esc_attr($item['product_id']); ?>" data-added="<?php echo $is_added_by_supplier ? '1' : '0'; ?>" data-model="<?php echo esc_attr($supp_model); ?>">
                            <td>
                                <strong style="<?php echo $is_added_by_supplier ? 'color: #dc2626;' : ''; ?>"><?php echo esc_html($product->get_name()); ?></strong>
                                <?php if($supp_model) echo '<br><span style="font-size: 11px; color:#64748b;">Model: '.esc_html($supp_model).'</span>'; ?>
                                <?php if($is_added_by_supplier) echo '<span style="font-size: 11px; color:#dc2626; display:block;">(Added by Supplier)</span>'; ?>
                            </td>
                            <td style="color:#64748b; font-size: 13px;">
                                <?php echo esc_html($product->get_sku()); ?>
                                <?php if($hs_code) echo '<br><span style="font-size:11px;">HS: '.esc_html($hs_code).'</span>'; ?>
                            </td>
                            <td>
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:4px; background:#f8fafc; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;">
                                    <span class="wcsom-orig-price-display" style="font-weight:600; color:#475569; font-size:13px;"><?php echo wcsom_format_usd($orig_price); ?></span>
                                    <input type="hidden" class="wcsom-orig-price" value="<?php echo esc_attr($orig_price); ?>">
                                    <?php if ($can_edit): ?>
                                    <button type="button" title="Sync PO Price to Supplier Profile" class="wcsom-sync-price-btn" style="background:none; border:none; cursor:pointer; color:#4f46e5; padding:0; display:flex; align-items:center;"><span class="dashicons dashicons-update" style="font-size:14px; width:14px; height:14px;"></span></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <input type="number" step="0.01" class="wcsom-input wcsom-edit-price" value="<?php echo $display_price; ?>" placeholder="$<?php echo number_format($orig_price, 2); ?>">
                                <?php else: ?>
                                    <span style="font-weight:600;"><?php echo wcsom_format_usd($item['price']); ?></span>
                                    <input type="hidden" class="wcsom-edit-price" value="<?php echo esc_attr($item['price']); ?>">
                                <?php endif; ?>
                            </td>
                            <td class="wcsom-price-diff" style="text-align:center; font-size:13px;">--</td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <input type="number" min="0" class="wcsom-input wcsom-edit-qty" value="<?php echo esc_attr($item['qty']); ?>">
                                <?php else: ?>
                                    <span style="font-weight:600;"><?php echo esc_html($item['qty']); ?></span>
                                    <input type="hidden" class="wcsom-edit-qty" value="<?php echo esc_attr($item['qty']); ?>">
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;" class="wcsom-row-subtotal"><strong><?php echo wcsom_format_usd($line_total); ?></strong></td>
                            <?php if ($can_edit): ?>
                                <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-row" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($can_edit): ?>
            <div class="mt-4 wcsom-flex-between" style="border-top:1px solid var(--wcsom-border); padding-top:20px;">
                <div style="flex:1; display: flex; gap: 20px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:12px; font-weight:600; margin-bottom:6px; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">1. Add Assigned Product</label>
                        <div style="display:flex; gap: 8px;">
                            <select id="wcsom-add-assigned-product" class="wcsom-input" style="flex:1;">
                                <option value="">-- Select from Assigned --</option>
                                <?php foreach($assigned_products as $ap): 
                                    $p = wc_get_product($ap->ID); 
                                    $orig = get_post_meta($ap->ID, '_wcsom_supplier_price', true) ?: 0;
                                    $po_def = $orig > 0 ? $orig : $p->get_price();
                                    $orig_model = get_post_meta($ap->ID, '_wcsom_supplier_model', true) ?: '';
                                ?>
                                    <option value="<?php echo $ap->ID; ?>" data-price="<?php echo esc_attr($po_def); ?>" data-orig-price="<?php echo esc_attr($orig); ?>" data-sku="<?php echo esc_attr($p->get_sku()); ?>" data-model="<?php echo esc_attr($orig_model); ?>"><?php echo esc_html($p->get_name()); ?></option>
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
            <?php endif; ?>
            
            <div style="text-align: right; margin-top: 30px; border-top:1px solid var(--wcsom-border); padding-top:20px;">
                <div style="font-size: 13px; color: #64748b; text-transform:uppercase; font-weight:600; letter-spacing:0.5px;">Grand Total</div>
                <div style="font-size: 28px; font-weight: 700; color: var(--wcsom-text-main);" id="wcsom-grand-total" data-total="<?php echo esc_attr($grand_total); ?>">
                    <?php echo wcsom_format_usd($grand_total); ?>
                </div>
                
                <!-- Incoterms Section -->
                <div style="margin-top: 12px; display: flex; justify-content: flex-end; align-items: center; gap: 12px;">
                    <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Terms:</span>
                    <?php if ($can_edit): ?>
                    <div class="wcsom-term-toggle">
                        <input type="radio" id="term_exw" name="wcsom_incoterm" value="EXW" <?php checked($incoterm, 'EXW'); ?>>
                        <label for="term_exw">EXW</label>
                        <input type="radio" id="term_fob" name="wcsom_incoterm" value="FOB" <?php checked($incoterm, 'FOB'); ?>>
                        <label for="term_fob">FOB</label>
                    </div>
                    <?php else: ?>
                    <span style="font-weight:700; color:#4f46e5; background:#eef2ff; padding:4px 10px; border-radius:4px; font-size:13px; border:1px solid #c7d2fe;"><?php echo esc_html($incoterm); ?></span>
                    <input type="hidden" name="wcsom_incoterm" value="<?php echo esc_attr($incoterm); ?>">
                    <?php endif; ?>
                </div>
                
                <!-- FOB Port Input field -->
                <div id="wcsom-fob-port-wrapper" style="margin-top: 12px; display: <?php echo $incoterm === 'FOB' ? 'flex' : 'none'; ?>; justify-content: flex-end; align-items: center; gap: 12px;">
                    <span style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase;">Port Name:</span>
                    <?php if ($can_edit): ?>
                        <input type="text" id="wcsom-fob-port-input" class="wcsom-input" value="<?php echo esc_attr($fob_port); ?>" placeholder="e.g. Shanghai" style="width: 200px;">
                    <?php else: ?>
                        <span style="font-weight:700; color:#0f172a;"><?php echo esc_html($fob_port ?: 'Not Specified'); ?></span>
                        <input type="hidden" id="wcsom-fob-port-input" value="<?php echo esc_attr($fob_port); ?>">
                    <?php endif; ?>
                </div>

            </div>
        </div>
        
        <div class="wcsom-card mt-4">
            <div class="wcsom-flex-between">
                <div>
                    <h3 class="wcsom-card-title">Payment Schedule</h3>
                    <p class="wcsom-card-desc">Assign payments (e.g., Deposit) based on a percentage of the total.</p>
                </div>
                <?php if ($can_edit): ?>
                <button id="wcsom-btn-add-payment" class="wcsom-btn wcsom-btn-outline"><span class="dashicons dashicons-plus-alt2" style="margin-top:4px;"></span> Add Payment</button>
                <?php endif; ?>
            </div>
            
            <div class="wcsom-table-responsive" style="margin-top: 15px;">
                <table class="wcsom-table" id="wcsom-payments-table">
                    <thead>
                        <tr>
                            <th>Payment Title</th>
                            <th style="width:100px;">Percent (%)</th>
                            <th style="text-align:right;">Amount (USD)</th>
                            <th style="width:160px;">Status</th>
                            <?php if ($can_edit): ?><th></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($payments as $p): ?>
                        <tr class="wcsom-pay-row">
                            <td>
                                <?php if ($can_edit): ?>
                                    <input type="text" class="wcsom-input wcsom-pay-title" value="<?php echo esc_attr($p['title']); ?>" placeholder="e.g., 20% Deposit">
                                <?php else: ?>
                                    <span style="font-weight:600;"><?php echo esc_html($p['title']); ?></span>
                                    <input type="hidden" class="wcsom-pay-title" value="<?php echo esc_attr($p['title']); ?>">
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <input type="number" step="0.01" min="0" max="100" class="wcsom-input wcsom-pay-percent" value="<?php echo esc_attr($p['percent']); ?>">
                                <?php else: ?>
                                    <span><?php echo esc_html($p['percent']); ?>%</span>
                                    <input type="hidden" class="wcsom-pay-percent" value="<?php echo esc_attr($p['percent']); ?>">
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; font-weight:600;">
                                <span class="wcsom-pay-amt-display"><?php echo wcsom_format_usd($p['amount']); ?></span>
                                <input type="hidden" class="wcsom-pay-amount" value="<?php echo esc_attr($p['amount']); ?>">
                            </td>
                            <td>
                                <?php if ($can_edit): ?>
                                    <select class="wcsom-input wcsom-pay-status">
                                        <option value="unpaid" <?php selected($p['status'], 'unpaid'); ?>>Unpaid</option>
                                        <option value="paid" <?php selected($p['status'], 'paid'); ?>>Paid</option>
                                        <option value="awaiting_production" <?php selected($p['status'], 'awaiting_production'); ?>>Awaiting Production</option>
                                        <option value="awaiting_delivery" <?php selected($p['status'], 'awaiting_delivery'); ?>>Awaiting Delivery</option>
                                    </select>
                                <?php else: ?>
                                    <span style="text-transform:capitalize; font-weight:600; color:<?php echo $p['status'] === 'paid' ? '#16a34a' : '#64748b'; ?>;"><?php echo esc_html(str_replace('_', ' ', $p['status'])); ?></span>
                                    <input type="hidden" class="wcsom-pay-status" value="<?php echo esc_attr($p['status']); ?>">
                                <?php endif; ?>
                            </td>
                            <?php if ($can_edit): ?>
                            <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-payment" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
                            <?php endif; ?>
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
                <?php if ($can_edit): ?>
                <button id="wcsom-btn-add-attachment" class="wcsom-btn wcsom-btn-outline"><span class="dashicons dashicons-upload" style="margin-top:2px;"></span> Upload File</button>
                <?php endif; ?>
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
                        <?php if ($can_edit): ?>
                        <button class="wcsom-btn wcsom-btn-outline wcsom-remove-attachment" style="padding:4px 8px; font-size:12px; color:#ef4444; border-color:#fca5a5;">Remove</button>
                        <?php endif; ?>
                        <input type="hidden" class="wcsom-att-data" data-url="<?php echo esc_attr($att['url']); ?>" data-name="<?php echo esc_attr($att['name']); ?>" data-type="<?php echo esc_attr($att['type']); ?>" data-by="<?php echo esc_attr($att['uploaded_by']); ?>" data-date="<?php echo esc_attr($att['date']); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="wcsom-card mt-4">
            <h3 class="wcsom-card-title">Order Notes</h3>
            <p class="wcsom-card-desc wcsom-mb-4">Notes left by the supplier or internal admin notes. These will appear on the PDF.</p>
            <?php if ($can_edit): ?>
                <textarea id="wcsom-edit-notes" class="wcsom-input" rows="4" placeholder="Enter any notes or special instructions here..."><?php echo esc_textarea($notes); ?></textarea>
            <?php else: ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; padding:12px; border-radius:8px; min-height:80px;">
                    <?php echo nl2br(esc_html($notes)) ?: '<em style="color:#94a3b8;">No notes added.</em>'; ?>
                </div>
                <input type="hidden" id="wcsom-edit-notes" value="<?php echo esc_attr($notes); ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="wcsom-sidebar" style="flex: 1;">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Order Details</h3>
            
            <div class="wcsom-mb-4 mt-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Order Reference</label>
                <?php if ($can_edit): ?>
                    <input type="text" id="wcsom-edit-ref" class="wcsom-input" value="<?php echo esc_attr($order->post_title); ?>" style="font-size: 15px; font-weight: 600;">
                <?php else: ?>
                    <div style="font-size: 15px; font-weight: 700; color:#0f172a; padding: 10px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html($order->post_title); ?></div>
                    <input type="hidden" id="wcsom-edit-ref" value="<?php echo esc_attr($order->post_title); ?>">
                <?php endif; ?>
            </div>
            
            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Supplier</label>
                <div style="font-size:14px; color:#334155;"><?php echo $supplier_display; ?></div>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Tags / Labels</label>
                <?php if ($can_edit): ?>
                    <input type="text" id="wcsom-edit-tags" class="wcsom-input" value="<?php echo esc_attr($tags_str); ?>" placeholder="e.g. Shipment A, Jan 24">
                    <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Separate tags with commas to group orders.</p>
                <?php else: ?>
                    <div style="font-size:13px; color:#334155; padding: 10px; background:#f8fafc; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html($tags_str) ?: '<em>None</em>'; ?></div>
                    <input type="hidden" id="wcsom-edit-tags" value="<?php echo esc_attr($tags_str); ?>">
                <?php endif; ?>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Supplier Portal Link</label>
                <div style="display:flex; gap:8px;">
                    <input type="text" class="wcsom-input" value="<?php echo esc_url($portal_link); ?>" readonly style="background:#f1f5f9; color:#475569; font-size:12px; cursor:text;" id="wcsom-portal-link-input">
                    <button type="button" class="wcsom-btn wcsom-btn-outline" onclick="navigator.clipboard.writeText(document.getElementById('wcsom-portal-link-input').value); let og = this.innerHTML; this.innerHTML='Copied!'; setTimeout(()=>this.innerHTML=og, 2000);" style="padding: 0 12px; font-size: 12px;">Copy</button>
                </div>
                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Share this link directly with the supplier.</p>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Security Code</label>
                <div style="font-size:16px; font-family:monospace; font-weight:700; color:#0f172a; background:#f1f5f9; padding:8px 12px; border-radius:6px; border:1px dashed #cbd5e1; display:inline-block; letter-spacing: 2px;">
                    <?php echo esc_html($sec_code ?: 'N/A'); ?>
                </div>
                <p style="font-size:11px; color:#94a3b8; margin:4px 0 0 0;">Required by supplier to log in</p>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Status</label>
                <?php if ($can_edit): ?>
                    <select id="wcsom-edit-status" class="wcsom-input">
                        <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
                        <option value="waiting_for_quote" <?php selected($status, 'waiting_for_quote'); ?>>Waiting for Quote</option>
                        <option value="pending" <?php selected($status, 'pending'); ?>>Pending (Supplier Submitted)</option>
                        <option value="in progress" <?php selected($status, 'in progress'); ?>>In Progress</option>
                        <option value="finished" <?php selected($status, 'finished'); ?>>Finished</option>
                        <option value="delivered" <?php selected($status, 'delivered'); ?>>Delivered</option>
                    </select>
                <?php else: ?>
                    <div style="font-weight:700; text-transform:capitalize; padding:10px; background:#f1f5f9; border-radius:6px; border:1px solid #cbd5e1;"><?php echo esc_html(str_replace('_', ' ', $status)); ?></div>
                    <input type="hidden" id="wcsom-edit-status" value="<?php echo esc_attr($status); ?>">
                <?php endif; ?>
            </div>

            <div class="wcsom-mb-4">
                <label style="font-size:12px; font-weight:600; color:#64748b; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">Supplier Quantity Editing</label>
                <?php if ($can_edit): ?>
                    <div style="display:flex; align-items:center; gap: 8px; margin-top:8px;">
                        <label class="wcsom-switch">
                            <input type="checkbox" id="wcsom-edit-allow-supp-qty" <?php checked($allow_supp_qty, 'yes'); ?>>
                            <span class="wcsom-slider round"></span>
                        </label>
                        <span style="font-size:13px; color:#475569; font-weight:500;">Allow supplier to edit quantities</span>
                    </div>
                <?php else: ?>
                    <div style="font-weight:700; padding:10px; background:#f1f5f9; border-radius:6px; border:1px solid #cbd5e1;"><?php echo $allow_supp_qty === 'yes' ? 'Allowed' : 'Not Allowed'; ?></div>
                    <input type="hidden" id="wcsom-edit-allow-supp-qty" value="<?php echo esc_attr($allow_supp_qty); ?>">
                <?php endif; ?>
            </div>

            <?php if ($can_edit): ?>
            <button id="wcsom-btn-save-po" class="wcsom-btn wcsom-btn-primary" style="width: 100%; justify-content: center; padding: 12px 0; font-size:15px; margin-bottom: 12px;" data-po="<?php echo esc_attr($po_id); ?>" data-supplier="<?php echo esc_attr($supplier_id); ?>">
                Save All Changes
            </button>
            <button id="wcsom-btn-delete-po-edit" class="wcsom-btn wcsom-btn-outline" style="width: 100%; justify-content: center; padding: 10px 0; font-size:13px; color:#ef4444; border-color:#fca5a5;" data-po="<?php echo esc_attr($po_id); ?>">
                Delete Purchase Order
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>
