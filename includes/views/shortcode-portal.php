<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    
    .wcsom-portal { font-family: 'Inter', -apple-system, sans-serif; color: #0f172a; max-width: 1000px; margin: 0 auto; line-height: 1.5; }
    .wcsom-portal .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 24px; }
    .wcsom-portal table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .wcsom-portal th { text-align: left; padding: 16px; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; background: #f8fafc; }
    .wcsom-portal td { padding: 16px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; font-size: 15px; }
    .wcsom-portal .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #334155; padding: 6px 16px; text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 500; display: inline-block; transition: all 0.2s;}
    .wcsom-portal .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
    .wcsom-portal .badge { padding: 6px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; background: #f1f5f9; color: #475569; letter-spacing: 0.025em; display: inline-block; text-transform: capitalize;}
    .wcsom-portal .badge.draft { background: #f1f5f9; color: #475569; }
    .wcsom-portal .badge.waiting-for-quote { background: #fce7f3; color: #be185d; }
    .wcsom-portal .badge.pending { background: #fef3c7; color: #92400e; }
    .wcsom-portal .badge.completed, .wcsom-portal .badge.delivered, .wcsom-portal .badge.finished { background: #d1fae5; color: #065f46; }
    .wcsom-portal .badge.in-progress { background: #dbeafe; color: #1e40af; }
</style>

<div class="wcsom-portal">
    <?php
    $current_user_id = get_current_user_id();
    
    // We strictly use the shortcode just to list all the supplier's POs.
    // The interactive view for each PO will be handed off to the beautiful standalone template.
    
    $args = array(
        'post_type'      => 'wcsom_order',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => array(
            array(
                'key'   => '_wcsom_supplier_id',
                'value' => $current_user_id
            )
        )
    );
    $orders = get_posts($args);
    ?>
    <div class="card">
        <h2 style="margin-top: 0; font-size: 24px; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px;">Your Purchase Orders Directory</h2>
        <?php if (empty($orders)): ?>
            <p style="color: #64748b; padding: 20px 0;">You have no purchase orders at this time.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $status = get_post_meta($order->ID, '_wcsom_status', true) ?: 'waiting_for_quote';
                        $total = get_post_meta($order->ID, '_wcsom_total_amount', true);
                    ?>
                    <tr>
                        <td><strong style="font-size: 16px;"><?php echo esc_html($order->post_title); ?></strong></td>
                        <td style="color: #475569;"><?php echo date('M d, Y', strtotime($order->post_date)); ?></td>
                        <td><strong style="color: #0f172a;"><?php echo wcsom_format_usd($total); ?></strong></td>
                        <td><span class="badge <?php echo esc_attr(strtolower(str_replace('_', '-', $status))); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></span></td>
                        <td style="text-align: right;">
                            <a href="<?php echo esc_url(home_url('/?wcsom_portal=1&po_id=' . $order->ID)); ?>" class="btn-outline">Open Ticket &rarr;</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
