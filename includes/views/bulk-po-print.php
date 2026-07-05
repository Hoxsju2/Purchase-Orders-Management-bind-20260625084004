<?php
if (!defined('ABSPATH')) exit;

$bulk = get_post($bulk_id);
if (!$bulk || $bulk->post_type !== 'wcsom_bulk_order') {
    wp_die('Invalid Bulk Order request.');
}

$po_ids = get_post_meta($bulk_id, '_wcsom_po_ids', true) ?: [];

$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
$site_name = get_bloginfo('name');
$store_address = get_option('woocommerce_store_address', '') . '<br>' . get_option('woocommerce_store_city', '') . ', ' . get_option('woocommerce_store_postcode', '');

$total_bulk_qty = 0;
$total_bulk_amount = 0;
$total_bulk_paid = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bulk Order - <?php echo esc_html($bulk->post_title); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; margin: 0; padding: 20px; background: #f8fafc; }
        .po-container { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 24px; margin-bottom: 24px; }
        .logo { max-height: 80px; max-width: 250px; object-fit: contain; }
        .po-title { text-align: right; }
        .po-title h1 { margin: 0; color: #4f46e5; font-size: 32px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; }
        .po-title p { margin: 6px 0 0 0; font-size: 14px; color: #475569; }
        
        .addresses { margin-bottom: 30px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .address-block h3 { margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; font-weight: 700; }
        .address-block p { margin: 4px 0; font-size: 14px; line-height: 1.5; color: #334155; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th { background: #f1f5f9; padding: 12px 14px; text-align: left; font-size: 12px; text-transform: uppercase; color: #475569; border-bottom: 2px solid #e2e8f0; font-weight: 700; letter-spacing: 0.025em; }
        .table td { padding: 12px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; color: #0f172a; font-size: 13px; }
        
        tbody.group { border-top: 4px solid #cbd5e1; }
        .group-header td { background: #f8fafc; padding: 12px 14px; }
        .group-title { font-size: 15px; font-weight: 700; color: #3730a3; }
        .group-term { font-size: 12px; background:#e0e7ff; color:#4338ca; padding:3px 8px; border-radius:4px; font-weight:600; border:1px solid #c7d2fe; margin-left:10px; }
        
        .totals { display: flex; justify-content: flex-end; margin-top: 30px; page-break-inside: avoid; }
        .totals-box { width: 340px; background: #f8fafc; padding: 24px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #475569; }
        .total-row.grand { font-size: 20px; font-weight: 800; color: #4f46e5; margin-top: 14px; border-top: 2px solid #e2e8f0; padding-top: 14px; }
        .total-row.paid { color: #16a34a; font-weight: 700; border-bottom: 1px solid #e2e8f0; padding-bottom: 14px; margin-bottom: 14px;}
        .total-row.balance { font-size: 18px; font-weight: 800; color: #dc2626; }

        .controls { display: flex; justify-content: center; gap: 16px; margin-bottom: 30px; }
        .btn-action { color: #fff; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: all 0.2s; }
        .btn-print { background: #334155; }
        .btn-print:hover { background: #1e293b; }
        .btn-pdf { background: #4f46e5; }
        .btn-pdf:hover { background: #4338ca; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0;}
            .po-container { box-shadow: none; padding: 0; width: 100%; max-width: 100%; margin: 0; border: none; }
            .controls { display: none; }
            .addresses, .totals-box { border: none; background: transparent; padding: 0; }
            .group-header td { background: #f8fafc !important; -webkit-print-color-adjust: exact; }
            .table th { background: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="controls" data-html2canvas-ignore="true">
        <button class="btn-action btn-print" onclick="window.print();">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Print
        </button>
        <button class="btn-action btn-pdf" onclick="downloadPDF();">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
            Save as PDF
        </button>
    </div>

    <div class="po-container" id="po-content">
        <div class="header">
            <div>
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Company Logo" class="logo">
                <?php else: ?>
                    <h2 style="margin:0; font-size: 28px; font-weight: 800;"><?php echo esc_html($site_name); ?></h2>
                <?php endif; ?>
            </div>
            
            <div class="po-title">
                <h1>Bulk Orders</h1>
                <p><strong>Reference:</strong> <?php echo esc_html($bulk->post_title); ?></p>
                <p><strong>Date Combined:</strong> <?php echo date('F j, Y', strtotime($bulk->post_date)); ?></p>
            </div>
        </div>

        <div class="addresses">
            <div class="address-block" style="text-align: center;">
                <h3>Internal Document: Combined Purchase Orders</h3>
                <p style="font-size: 13px;">This document aggregates multiple purchase orders for ease of processing.<br>It includes all items, grouped by their respective supplier and original PO reference.</p>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU / HS</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Subtotal</th>
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
                $incoterm = get_post_meta($po_id, '_wcsom_incoterm', true) ?: 'EXW';
                $fob_port = get_post_meta($po_id, '_wcsom_fob_port', true) ?: '';
                
                $display_term = ($incoterm === 'FOB' && $fob_port) ? 'FOB (Port: ' . esc_html($fob_port) . ')' : esc_html($incoterm);

                foreach ($payments as $p) {
                    if ($p['status'] === 'paid') {
                        $total_bulk_paid += floatval($p['amount']);
                    }
                }
            ?>
            <tbody class="group">
                <tr class="group-header">
                    <td colspan="5">
                        <div style="display:flex; justify-content: space-between; align-items:center;">
                            <div>
                                <span class="group-title">Supplier: <?php echo esc_html($supplier_str); ?> | PO: <?php echo esc_html($po_order->post_title); ?></span>
                                <span class="group-term">Terms: <?php echo esc_html($display_term); ?></span>
                            </div>
                            <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase;">[<?php echo esc_html(str_replace('_', ' ', $status)); ?>]</span>
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
                        
                        $supp_model = isset($item['supplier_model']) ? $item['supplier_model'] : get_post_meta($product->get_id(), '_wcsom_supplier_model', true);
                        $hs_code = get_post_meta($product->get_id(), '_wcsom_hs_code', true);
                ?>
                    <tr>
                        <td>
                            <strong style="font-size: 13px; font-weight: 600; color: #0f172a;"><?php echo esc_html($product->get_name()); ?></strong>
                            <?php if($supp_model) echo '<br><span style="font-size: 11px; color:#64748b;">Model: '.esc_html($supp_model).'</span>'; ?>
                        </td>
                        <td style="color: #64748b; font-size: 12px;">
                            <?php echo esc_html($product->get_sku()); ?>
                            <?php if($hs_code): ?>
                                <br><span style="font-size:11px;">HS: <?php echo esc_html($hs_code); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center; font-weight: 600;"><?php echo $qty; ?></td>
                        <td style="text-align:right;"><?php echo wcsom_format_usd($price); ?></td>
                        <td style="text-align:right; font-weight: 600; color: #0f172a;"><?php echo wcsom_format_usd($line_total); ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td colspan="4" style="text-align:right; font-size: 11px; color: #64748b; text-transform:uppercase; font-weight: 700; border-bottom: none;">PO Subtotal</td>
                    <td style="text-align:right; font-size: 13px; font-weight: 800; color: #4f46e5; border-bottom: none;"><?php echo wcsom_format_usd($po_subtotal); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
            <?php endforeach; ?>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="total-row">
                    <span>Total Combined Items:</span>
                    <span style="font-weight: 700; color: #0f172a;"><?php echo $total_bulk_qty; ?> units</span>
                </div>
                <div class="total-row grand">
                    <span>Total Amount:</span>
                    <span><?php echo wcsom_format_usd($total_bulk_amount); ?></span>
                </div>
                <div class="total-row paid">
                    <span>Total Amount Paid:</span>
                    <span><?php echo wcsom_format_usd($total_bulk_paid); ?></span>
                </div>
                <div class="total-row balance">
                    <span>Remaining Balance:</span>
                    <span><?php echo wcsom_format_usd($total_bulk_amount - $total_bulk_paid); ?></span>
                </div>
            </div>
        </div>

    </div>

    <script>
        function downloadPDF() {
            var element = document.getElementById('po-content');
            var opt = {
                margin:       [0.4, 0.15, 0.4, 0.15], 
                filename:     '<?php echo esc_js($bulk->post_title); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            
            // Clean, native save without DOM hacks or page number additions
            html2pdf().set(opt).from(element).save();
        }

        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_GET['action']) && $_GET['action'] === 'pdf'): ?>
                setTimeout(function() { downloadPDF(); }, 800);
            <?php endif; ?>
        });
    </script>
</body>
</html>