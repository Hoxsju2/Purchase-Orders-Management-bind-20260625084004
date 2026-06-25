<div class="wcsom-card">
    <div class="wcsom-flex-between wcsom-mb-4">
        <div>
            <h3 class="wcsom-card-title">Purchase Orders Directory</h3>
            <p class="wcsom-card-desc">View and manage all generated supplier purchase orders.</p>
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
                        <td colspan="6" class="wcsom-empty-cell">No purchase orders found yet.</td>
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
                            
                            $supplier_display = '<strong>' . ($code ? '[' . esc_html($code) . '] ' : '') . esc_html($company) . '</strong>';
                        }
                        
                        $total_qty = get_post_meta($order->ID, '_wcsom_total_qty', true);
                        $total_amt = get_post_meta($order->ID, '_wcsom_total_amount', true);
                        $status = get_post_meta($order->ID, '_wcsom_status', true) ?: 'Pending';
                ?>
                    <tr>
                        <td>
                            <a href="#" class="wcsom-order-link"><strong><?php echo esc_html($order->post_title); ?></strong></a>
                        </td>
                        <td><?php echo $supplier_display; ?></td>
                        <td><span class="wcsom-qty-pill"><?php echo esc_html($total_qty); ?> Units</span></td>
                        <td><strong><?php echo wc_price($total_amt); ?></strong></td>
                        <td style="color: #6b7280;"><?php echo date('M d, Y', strtotime($order->post_date)); ?></td>
                        <td><span class="wcsom-badge wcsom-badge-<?php echo strtolower($status); ?>"><?php echo esc_html(ucfirst($status)); ?></span></td>
                    </tr>
                <?php 
                    endforeach;
                endif; 
                ?>
            </tbody>
        </table>
    </div>
</div>
