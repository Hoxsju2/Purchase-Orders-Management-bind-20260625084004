<div class="wcsom-panel">
    <h3>Purchase Orders Directory</h3>
    
    <table class="wp-list-table widefat fixed striped wcsom-table mt-4">
        <thead>
            <tr>
                <th>Order Number</th>
                <th>Supplier Name</th>
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
                    <td colspan="6">No purchase orders found.</td>
                </tr>
            <?php else:
                foreach ($orders as $order):
                    $supplier_id = get_post_meta($order->ID, '_wcsom_supplier_id', true);
                    $supplier_name = 'Unknown';
                    if ($supplier_id) {
                        $supplier_name = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'supplier_code', true);
                    }
                    
                    $total_qty = get_post_meta($order->ID, '_wcsom_total_qty', true);
                    $total_amt = get_post_meta($order->ID, '_wcsom_total_amount', true);
                    $status = get_post_meta($order->ID, '_wcsom_status', true) ?: 'Pending';
            ?>
                <tr>
                    <td><strong><?php echo esc_html($order->post_title); ?></strong></td>
                    <td><?php echo esc_html($supplier_name); ?></td>
                    <td><?php echo esc_html($total_qty); ?></td>
                    <td><?php echo wc_price($total_amt); ?></td>
                    <td><?php echo date('M d, Y', strtotime($order->post_date)); ?></td>
                    <td><span class="wcsom-badge wcsom-badge-<?php echo strtolower($status); ?>"><?php echo esc_html(ucfirst($status)); ?></span></td>
                </tr>
            <?php 
                endforeach;
            endif; 
            ?>
        </tbody>
    </table>
</div>
