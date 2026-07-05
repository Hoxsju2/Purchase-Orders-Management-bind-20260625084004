<?php
$can_edit = wcsom_can_edit();
$visible_suppliers = wcsom_get_visible_suppliers();
?>
<div class="wcsom-card relative">
    <div class="wcsom-flex-between wcsom-mb-4">
        <div>
            <h3 class="wcsom-card-title">Purchase Orders Directory</h3>
            <p class="wcsom-card-desc">View and manage all generated supplier purchase orders.</p>
        </div>
        <?php if ($can_edit): ?>
        <button id="wcsom-btn-new-po-from-dir" class="wcsom-btn wcsom-btn-primary">
            <span class="dashicons dashicons-plus-alt2" style="margin-top:2px;"></span> Create Purchase Order
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($can_edit): ?>
    <!-- Permanent Bulk Action Bar -->
    <div class="wcsom-bulk-actions wcsom-mb-4" style="display:flex; gap:12px; align-items:center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <select id="wcsom-bulk-action-dropdown" class="wcsom-input" style="width: 230px; font-weight: 500;">
            <option value="">-- Select Bulk Action --</option>
            <option value="combine">Combine into Bulk Order</option>
            <option value="add_tags">Add Tags to Selected</option>
            <option value="delete">Delete Selected</option>
        </select>
        
        <input type="text" id="wcsom-bulk-tags-input" class="wcsom-input" style="width: 250px; display: none;" placeholder="e.g. Shipment A, Urgent (comma separated)">
        
        <button id="wcsom-btn-apply-bulk" class="wcsom-btn wcsom-btn-primary" style="padding: 10px 20px;">Apply Action</button>
        <span id="wcsom-bulk-count-display" style="margin-left:auto; font-size:13px; color:#4f46e5; font-weight:700; background: #e0e7ff; padding: 4px 10px; border-radius: 6px;">0 selected</span>
    </div>
    <?php endif; ?>

    <div class="wcsom-table-responsive" style="margin-top: 0;">
        <table class="wcsom-table">
            <thead>
                <tr>
                    <?php if ($can_edit): ?><th style="width: 40px;"><input type="checkbox" id="wcsom-bulk-select-all"></th><?php endif; ?>
                    <th>Order Reference</th>
                    <th>Supplier Details</th>
                    <th>Total Items</th>
                    <th>Total Amount</th>
                    <th>Date Created</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch orders strictly belonging to visible suppliers.
                $args = array(
                    'post_type'      => 'wcsom_order',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                );
                
                if (!current_user_can('manage_options') && !empty($visible_suppliers)) {
                    $args['meta_query'] = array(
                        array(
                            'key'     => '_wcsom_supplier_id',
                            'value'   => $visible_suppliers,
                            'compare' => 'IN'
                        )
                    );
                } elseif (!current_user_can('manage_options') && empty($visible_suppliers)) {
                    // Force no results if no suppliers are authorized for staff.
                    $args['post__in'] = array(0); 
                }

                $orders = get_posts($args);

                if (empty($orders)): ?>
                    <tr>
                        <td colspan="<?php echo $can_edit ? '8' : '7'; ?>" class="wcsom-empty-cell">No purchase orders found.</td>
                    </tr>
                <?php else:
                    foreach ($orders as $order):
                        $supplier_id = get_post_meta($order->ID, '_wcsom_supplier_id', true);
                        $supplier_display = '<span style="color:#9ca3af; font-style:italic;">Unknown</span>';
                        
                        if ($supplier_id) {
                            $code = get_user_meta($supplier_id, 'supplier_code', true);
                            $company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'first_name', true) . ' ' . get_user_meta($supplier_id, 'last_name', true);
                            
                            if (!trim($company)) {
                                $user_info = get_userdata($supplier_id);
                                $company = $user_info ? $user_info->display_name : 'Unknown';
                            }
                            
                            $supplier_display = '<div class="wcsom-supplier-badge"><strong>' . ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company) . '</strong></div>';
                        }
                        
                        $total_qty = get_post_meta($order->ID, '_wcsom_total_qty', true);
                        $total_amt = get_post_meta($order->ID, '_wcsom_total_amount', true);
                        $status = get_post_meta($order->ID, '_wcsom_status', true) ?: 'waiting_for_quote';
                        $tags = get_post_meta($order->ID, '_wcsom_tags', true) ?: [];
                        
                        $edit_url = wcsom_get_tab_url('edit-po', ['id' => $order->ID]);
                        $print_url = home_url('/?wcsom_print=1&po_id=' . $order->ID);
                ?>
                    <tr>
                        <?php if ($can_edit): ?><td><input type="checkbox" class="wcsom-bulk-select" value="<?php echo esc_attr($order->ID); ?>"></td><?php endif; ?>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="wcsom-order-link" style="font-size: 14px; font-weight: 600; display:block; margin-bottom: 6px;"><?php echo esc_html($order->post_title); ?></a>
                            <?php if(!empty($tags)): ?>
                                <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                    <?php foreach($tags as $t): ?>
                                        <span class="wcsom-tag-pill"><?php echo esc_html($t); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $supplier_display; ?></td>
                        <td><span class="wcsom-qty-pill"><?php echo esc_html($total_qty); ?> Units</span></td>
                        <td><strong><?php echo wcsom_format_usd($total_amt); ?></strong></td>
                        <td style="color: #64748b; font-size: 13px;"><?php echo date('M d, Y', strtotime($order->post_date)); ?></td>
                        <td><span class="wcsom-badge wcsom-badge-<?php echo strtolower(str_replace('_', '-', $status)); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <a href="<?php echo esc_url($edit_url); ?>" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;"><?php echo $can_edit ? 'Edit' : 'View'; ?></a>
                                <a href="<?php echo esc_url($print_url); ?>" target="_blank" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;">
                                    <span class="dashicons dashicons-pdf" style="font-size: 14px; margin-top:2px;"></span> PDF
                                </a>
                                <?php if ($can_edit): ?>
                                <button class="wcsom-btn wcsom-btn-outline wcsom-delete-po-btn" data-id="<?php echo esc_attr($order->ID); ?>" style="padding: 6px 12px; font-size:12px; color:#ef4444; border-color:#fca5a5;">
                                    Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php 
                    endforeach;
                endif; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($can_edit): ?>
<!-- Modal for Quick Select Supplier from Orders Directory -->
<div id="wcsom-new-po-supplier-modal" class="wcsom-modal" style="display:none;">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content" style="width: 440px;">
        <div class="wcsom-modal-header">
            <h3>Select Supplier</h3>
            <button class="wcsom-modal-close" id="wcsom-close-new-po-supplier"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body" style="padding-bottom: 30px;">
            <p class="wcsom-card-desc wcsom-mb-4">Choose a supplier to navigate to their profile and begin creating a purchase order.</p>
            <label style="display:block; font-size:13px; font-weight:600; margin-bottom:8px; color:#475569;">Supplier Directory</label>
            <select id="wcsom-new-po-supplier-select" class="wcsom-input" style="width:100%;">
                <option value="">-- Search & Select Supplier --</option>
                <?php foreach($visible_suppliers as $sid): 
                    $c = get_user_meta($sid, 'supplier_code', true);
                    $n = get_user_meta($sid, 'company_name', true) ?: get_userdata($sid)->display_name;
                ?>
                    <option value="<?php echo esc_attr($sid); ?>"><?php echo esc_html(($c ? "[$c] " : "") . $n); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-primary" id="wcsom-confirm-new-po-supplier" style="width:100%; justify-content:center;">Continue &rarr;</button>
        </div>
    </div>
</div>
<?php endif; ?>