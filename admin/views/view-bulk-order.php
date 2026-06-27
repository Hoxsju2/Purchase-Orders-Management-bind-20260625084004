<?php
if (!defined('ABSPATH')) exit;

$bulk_id = intval($_GET['id']);
$bulk = get_post($bulk_id);
if (!$bulk || $bulk->post_type !== 'wcsom_bulk_order') {
    echo '<div class="wcsom-card"><h3>Bulk Order not found.</h3></div>';
    return;
}

$po_ids = get_post_meta($bulk_id, '_wcsom_po_ids', true) ?: [];

// Calculation variables
$total_bulk_qty = 0;
$total_bulk_amount = 0;
$total_bulk_paid = 0;

?>
<div class="wcsom-flex-between wcsom-mb-4">
    <a href="?page=wcsom-dashboard&tab=bulk-orders" class="wcsom-btn wcsom-btn-outline">&larr; Back to Bulk Directory</a>
    <a href="<?php echo esc_url(home_url('/?wcsom_bulk_print=1&bulk_id=' . $bulk_id)); ?>" target="_blank" class="wcsom-btn wcsom-btn-primary">
        <span class="dashicons dashicons-pdf" style="margin-top:2px;"></span> Download Combined PDF
    </a>
</div>

<div class="wcsom-card">
    <h3 class="wcsom-card-title">Bulk Document: <?php echo esc_html($bulk->post_title); ?></h3>
    <p class="wcsom-card-desc wcsom-mb-4">This view aggregates all items, payments, and statuses dynamically based on the current data of the linked Purchase Orders.</p>

    <div class="wcsom-table-responsive" style="border: none; box-shadow: none;">
        <table class="wcsom-table" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th style="width:100px; text-align:center;">Qty</th>
                    <th style="text-align:right;">Unit Price</th>
                    <th style="text-align:right;">Subtotal</th>
                </tr>
            </thead>
            <?php 
            foreach($po_ids as $po_id): 
                $po_order = get_post($po_id);
                if (!$po_order || $po_order->post_status === 'trash') continue;
                
                $supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
                $code = get_user_meta($supplier_id, 'supplier_code', true);
                $company = get_user_meta($supplier_id, 'company_name', true) ?: get_user_meta($supplier_id, 'display_name', true);
                $supplier_str = ($code ? '['.$code.'] ' : '') . $company;
                
                $items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
                $payments = get_post_meta($po_id, '_wcsom_payments', true) ?: [];
                $status = get_post_meta($po_id, '_wcsom_status', true) ?: 'waiting';
                
                // Track payments for this PO
                foreach ($payments as $p) {
                    if ($p['status'] === 'paid') {
                        $total_bulk_paid += floatval($p['amount']);
                    }
                }
            ?>
            <tbody style="border-top: 4px solid #e2e8f0;">
                <tr style="background: #f8fafc;">
                    <td colspan="5" style="padding: 12px 16px;">
                        <div style="display:flex; justify-content: space-between; align-items:center;">
                            <span style="font-size: 15px; font-weight: 700; color: #3730a3;">Supplier: <?php echo esc_html($supplier_str); ?> | PO Reference: <?php echo esc_html($po_order->post_title); ?></span>
                            <span class="wcsom-badge wcsom-badge-<?php echo esc_attr(strtolower(str_replace('_', '-', $status))); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></span>
                        </div>
                    </td>
                </tr>
                <?php 
                if (empty($items)): ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8; font-size:13px;">No items attached.</td></tr>
                <?php else:
                    $po_subtotal = 0;
                    foreach ($items as $item): 
                        $product = wc_get_product($item['product_id']);
                        if (!$product) continue;
                        $qty = intval($item['qty']);
                        $price = floatval($item['price']);
                        $line_total = $qty * $price;
                        
                        $total_bulk_qty += $qty;
                        $po_subtotal += $line_total;
                        $total_bulk_amount += $line_total;
                ?>
                    <tr>
                        <td style="font-weight: 500; color: #0f172a;"><?php echo esc_html($product->get_name()); ?></td>
                        <td style="color: #64748b; font-size: 13px;"><?php echo esc_html($product->get_sku()); ?></td>
                        <td style="text-align:center; font-weight: 600;"><?php echo $qty; ?></td>
                        <td style="text-align:right;"><?php echo wcsom_format_usd($price); ?></td>
                        <td style="text-align:right; font-weight: 600; color: #0f172a;"><?php echo wcsom_format_usd($line_total); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr style="background: #fff;">
                    <td colspan="4" style="text-align:right; font-size: 12px; color: #64748b; text-transform:uppercase; font-weight: 700;">Order Subtotal</td>
                    <td style="text-align:right; font-size: 14px; font-weight: 800; color: #4f46e5;"><?php echo wcsom_format_usd($po_subtotal); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Final Summary Box -->
    <div style="margin-top: 30px; display: flex; justify-content: flex-end;">
        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 24px; width: 380px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; margin-bottom: 12px; font-size: 14px; color: #475569;">
                <span>Total Combined Items:</span>
                <span style="font-weight: 700; color: #0f172a;"><?php echo $total_bulk_qty; ?> units</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 12px; font-size: 14px; color: #475569;">
                <span>Total Amount:</span>
                <span style="font-weight: 700; color: #0f172a;"><?php echo wcsom_format_usd($total_bulk_amount); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 16px; font-size: 14px; color: #16a34a; border-bottom: 1px solid #e2e8f0; padding-bottom: 16px;">
                <span>Total Amount Paid:</span>
                <span style="font-weight: 700;"><?php echo wcsom_format_usd($total_bulk_paid); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size: 18px; font-weight: 800; color: #dc2626;">
                <span>Remaining Balance:</span>
                <span><?php echo wcsom_format_usd($total_bulk_amount - $total_bulk_paid); ?></span>
            </div>
        </div>
    </div>
</div>
