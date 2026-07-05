<div class="wcsom-card relative">
    <div class="wcsom-flex-between wcsom-mb-4">
        <div>
            <h3 class="wcsom-card-title">Bulk Orders Directory</h3>
            <p class="wcsom-card-desc">Combined purchase orders for unified downloading and tracking.</p>
        </div>
        <a href="<?php echo esc_url(wcsom_get_tab_url('orders')); ?>" class="wcsom-btn wcsom-btn-outline">Create from Orders Tab</a>
    </div>

    <div class="wcsom-table-responsive">
        <table class="wcsom-table">
            <thead>
                <tr>
                    <th>Bulk Reference Name</th>
                    <th>Linked Orders</th>
                    <th>Date Combined</th>
                    <th style="width: 250px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $args = array(
                    'post_type'      => 'wcsom_bulk_order',
                    'posts_per_page' => -1,
                    'post_status'    => 'publish',
                );
                $bulks = get_posts($args);

                if (empty($bulks)): ?>
                    <tr>
                        <td colspan="4" class="wcsom-empty-cell">No bulk orders found. Select multiple POs in the Orders tab to combine them.</td>
                    </tr>
                <?php else:
                    foreach ($bulks as $bulk):
                        $po_ids = get_post_meta($bulk->ID, '_wcsom_po_ids', true) ?: [];
                        $view_url = wcsom_get_tab_url('view-bulk', ['id' => $bulk->ID]);
                        $print_url = home_url('/?wcsom_bulk_print=1&bulk_id=' . $bulk->ID);
                ?>
                    <tr id="wcsom-bulk-row-<?php echo $bulk->ID; ?>">
                        <td>
                            <a href="<?php echo esc_url($view_url); ?>" class="wcsom-order-link" style="font-size: 15px; font-weight: 600;"><?php echo esc_html($bulk->post_title); ?></a>
                        </td>
                        <td><span class="wcsom-qty-pill"><?php echo count($po_ids); ?> Orders</span></td>
                        <td style="color: #64748b; font-size: 13px;"><?php echo date('M d, Y', strtotime($bulk->post_date)); ?></td>
                        <td>
                            <div style="display:flex; gap:8px;">
                                <a href="<?php echo esc_url($view_url); ?>" class="wcsom-btn wcsom-btn-outline" style="padding: 6px 12px; font-size:12px;">View Details</a>
                                <a href="<?php echo esc_url($print_url); ?>" target="_blank" class="wcsom-btn wcsom-btn-primary" style="padding: 6px 12px; font-size:12px;">
                                    <span class="dashicons dashicons-pdf" style="font-size: 14px; margin-top:2px;"></span> Download
                                </a>
                                <button class="wcsom-btn wcsom-btn-outline wcsom-delete-bulk" data-id="<?php echo $bulk->ID; ?>" style="padding: 6px 12px; font-size:12px; color: #dc2626; border-color: #fca5a5;">Delete</button>
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