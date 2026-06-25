<div class="wcsom-card">
    <div class="wcsom-flex-between wcsom-mb-4">
        <div>
            <h3 class="wcsom-card-title">Purchase Orders Directory</h3>
            <p class="wcsom-card-desc">View and manage all generated supplier purchase orders. Click on any reference to edit.</p>
        </div>
    </div>
    
    <div class="wcsom-table-responsive">
        <table class="wcsom-table">
            <thead>
                <tr>
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
                $args = array(
                    'post_type'      => 'wcsom_order',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                );
                $orders = get_posts($args);

                if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="wcsom-empty-cell">No purchase orders found yet.</td>
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
                        
                        $edit_url = '?page=wcsom-dashboard&tab=edit-po&id=' . $order->ID;
                        $print_url = home_url('/?wcsom_print=1&po_id=' . $order->ID);
                ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>" class="wcsom-order-link" style="font-size: 14px; font-weight: 600;"><?php echo esc_html($order->post_title); ?></a>
                        </td>
                        <td><?php echo $supplier_display; ?></td>
                        <td><span class="wcsom-qty-pill"><?php echo esc_html($total_qty); ?> Units</span></td>
                        <td><strong><?php echo wcsom_format_usd($total_amt); ?></strong></td>
                        <td style="color: #64748b; font-size: 13px;"><?php echo date('M d, Y', strtotime($order->post_date)); ?></td>
                        <td><span class="wcsom-badge wcsom-badge-<?php echo strtolower(str_replace('_', '-', $status)); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <a href="<?php echo esc_url($edit_url); ?>" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;">Edit</a>
                                <a href="<?php echo esc_url($print_url); ?>" target="_blank" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;">
                                    <span class="dashicons dashicons-pdf" style="font-size: 14px; margin-top:2px;"></span> PDF
                                </a>
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
