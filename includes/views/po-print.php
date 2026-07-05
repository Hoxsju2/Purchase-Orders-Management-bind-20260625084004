<?php
if (!defined('ABSPATH')) exit;
$supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
$status = get_post_meta($po_id, '_wcsom_status', true) ?: 'waiting_for_quote';
$items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
$notes = get_post_meta($po_id, '_wcsom_notes', true) ?: '';
$payments = get_post_meta($po_id, '_wcsom_payments', true) ?: [];
$attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];
$incoterm = get_post_meta($po_id, '_wcsom_incoterm', true) ?: 'EXW';
$fob_port = get_post_meta($po_id, '_wcsom_fob_port', true) ?: '';

// Generate/Fetch Security Code
$sec_code = get_post_meta($po_id, '_wcsom_security_code', true);
if (empty($sec_code)) {
    $sec_code = mt_rand(1000000, 9999999);
    update_post_meta($po_id, '_wcsom_security_code', $sec_code);
}

// Get Site Details
$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
$site_name = get_bloginfo('name');
$store_address = get_option('woocommerce_store_address', '') . '<br>' . get_option('woocommerce_store_city', '') . ', ' . get_option('woocommerce_store_postcode', '');

// Get Supplier Details
$supplier_info = get_userdata($supplier_id);
$supplier_company = get_user_meta($supplier_id, 'company_name', true) ?: $supplier_info->display_name;
$supplier_code = get_user_meta($supplier_id, 'supplier_code', true);
$supplier_email = $supplier_info->user_email;
$supplier_phone = get_user_meta($supplier_id, 'billing_phone', true);
$supplier_address = get_user_meta($supplier_id, 'billing_address_1', true) . '<br>' . get_user_meta($supplier_id, 'billing_city', true);

// Unified routing interceptor link
$portal_link = home_url('/?wcsom_portal=1&po_id=' . $po_id);
// Generate QR Code URL via free reliable API
$qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=' . urlencode($portal_link);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order - <?php echo esc_html($order->post_title); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; margin: 0; padding: 20px; background: #f8fafc; }
        .po-container { width: 100%; max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); box-sizing: border-box; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #e2e8f0; padding-bottom: 24px; margin-bottom: 24px; }
        .logo { max-height: 80px; max-width: 250px; object-fit: contain; }
        .po-title { text-align: right; }
        .po-title h1 { margin: 0; color: #4f46e5; font-size: 32px; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; }
        .po-title p { margin: 6px 0 0 0; font-size: 14px; color: #475569; }
        
        .addresses { display: flex; justify-content: space-between; margin-bottom: 30px; gap: 30px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .address-block { flex: 1; }
        .address-block h3 { margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; font-weight: 700; }
        .address-block p { margin: 4px 0; font-size: 14px; line-height: 1.5; color: #334155; }
        
        .table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .table th { background: #f1f5f9; padding: 12px 14px; text-align: left; font-size: 12px; text-transform: uppercase; color: #475569; border-bottom: 2px solid #e2e8f0; font-weight: 700; letter-spacing: 0.025em; }
        .table td { padding: 14px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; color: #0f172a; font-size: 14px; }
        
        .thumb-wrapper { width: 44px; height: 44px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0; display: inline-block; background-color: #f8fafc; }
        .thumb { width: 100%; height: 100%; object-fit: cover; }
        .prod-link { color: inherit; text-decoration: none; transition: color 0.2s; }
        
        .section-box { margin-top: 20px; padding: 20px; border-radius: 8px; font-size: 14px; }
        .notes-section { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .notes-section h4 { margin: 0 0 8px 0; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em; }
        
        .totals { display: flex; justify-content: flex-end; margin-top: 20px; }
        .totals-box { width: 320px; background: #f8fafc; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; color: #475569; align-items:center; }
        .total-row.grand { font-size: 22px; font-weight: 800; color: #4f46e5; margin-top: 14px; border-top: 2px solid #e2e8f0; padding-top: 14px; }
        .incoterm-badge { font-weight: 700; color: #4f46e5; background: #eef2ff; padding: 4px 8px; border-radius: 4px; border: 1px solid #c7d2fe; font-size: 12px; }
        
        .extra-tables { margin-top: 30px; }
        .extra-tables h4 { margin: 0 0 10px 0; font-size: 14px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;}
        .extra-tables table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .extra-tables th { text-align: left; padding: 8px; border-bottom: 1px solid #cbd5e1; color: #64748b; }
        .extra-tables td { padding: 8px; border-bottom: 1px solid #e2e8f0; }

        /* Compact Portal Box Design */
        .portal-compact {
            border: 2px dashed #a5b4fc;
            background: #eef2ff;
            border-radius: 8px;
            padding: 16px;
            margin-top: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        .portal-compact-icon {
            color: #4f46e5;
            flex-shrink: 0;
        }
        .portal-compact-info {
            flex-grow: 1;
        }
        .portal-compact-title {
            font-size: 15px;
            font-weight: 700;
            color: #3730a3;
            margin: 0 0 4px 0;
        }
        .portal-compact-sub {
            font-size: 13px;
            color: #475569;
            margin: 0;
        }
        .portal-compact-link {
            text-align: right;
            flex-shrink: 0;
        }
        .portal-compact-link a {
            display: inline-block;
            background: #4f46e5;
            color: #fff;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .portal-compact-code {
            font-size: 12px;
            color: #334155;
            margin: 0;
        }

        .controls { display: flex; justify-content: center; gap: 16px; margin-bottom: 30px; }
        .btn-action { color: #fff; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); transition: all 0.2s; }
        .btn-print { background: #334155; }
        .btn-print:hover { background: #1e293b; }
        .btn-pdf { background: #4f46e5; }
        .btn-pdf:hover { background: #4338ca; }
        
        @media print {
            body { background: #fff; padding: 0; margin: 0; }
            .po-container { box-shadow: none; padding: 0; margin: 0; width: 100%; max-width: 100%; border: none; }
            .controls { display: none; }
            .addresses, .totals-box, .notes-section { border: none; background: transparent; padding: 0; }
            .portal-compact { border: 1px solid #cbd5e1; background: transparent; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
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
            
            <div style="display: flex; gap: 24px; align-items: flex-start; justify-content: flex-end;">
                <div class="po-title">
                    <h1>Purchase Order</h1>
                    <p><strong>PO Number:</strong> <?php echo esc_html($order->post_title); ?></p>
                    <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($order->post_date)); ?></p>
                    <p><strong>Status:</strong> <span style="text-transform: capitalize; font-weight: 600; color: #0f172a;"><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></span></p>
                </div>
                <div>
                    <img src="<?php echo esc_url($qr_url); ?>" alt="Scan to open PO" style="width: 80px; height: 80px; border: 1px solid #cbd5e1; padding: 4px; border-radius: 6px; background: white; margin-top: 4px;">
                </div>
            </div>
        </div>

        <div class="addresses">
            <div class="address-block">
                <h3>Vendor / Supplier</h3>
                <?php if ($supplier_code): ?>
                    <p style="color: #4f46e5; font-weight: 700; font-size: 13px; margin-bottom: 6px;">Code: [<?php echo esc_html($supplier_code); ?>]</p>
                <?php endif; ?>
                <p style="font-size: 16px; font-weight: 700; margin-bottom: 6px;"><?php echo esc_html($supplier_company); ?></p>
                <?php if ($supplier_address) echo '<p>' . wp_kses_post($supplier_address) . '</p>'; ?>
                <?php if ($supplier_email) echo '<p><strong>Email:</strong> ' . esc_html($supplier_email) . '</p>'; ?>
                <?php if ($supplier_phone) echo '<p><strong>Phone:</strong> ' . esc_html($supplier_phone) . '</p>'; ?>
            </div>
            <div class="address-block">
                <h3>Ship To / Company Info</h3>
                <p style="font-size: 16px; font-weight: 700; margin-bottom: 6px;"><?php echo esc_html($site_name); ?></p>
                <?php if ($store_address) echo '<p>' . wp_kses_post($store_address) . '</p>'; ?>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50px;">Image</th>
                    <th>Product Description</th>
                    <th>SKU / HS</th>
                    <th style="text-align: center;">Qty</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_qty = 0;
                $grand_total = 0;
                foreach ($items as $item): 
                    $product = wc_get_product($item['product_id']);
                    if (!$product) continue;
                    $qty = intval($item['qty']);
                    $price = floatval($item['price']);
                    $line_total = $qty * $price;
                    $grand_qty += $qty;
                    $grand_total += $line_total;
                    
                    $img = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail');
                    $sku = $product->get_sku() ?: 'N/A';
                    
                    $is_published = ($product->get_status() === 'publish');
                    $is_added_by_supplier = !empty($item['added_by_supplier']);
                    $supp_model = isset($item['supplier_model']) ? $item['supplier_model'] : get_post_meta($product->get_id(), '_wcsom_supplier_model', true);
                    $hs_code = get_post_meta($product->get_id(), '_wcsom_hs_code', true);
                    $permalink = get_permalink($product->get_id());
                ?>
                <tr>
                    <td>
                        <div class="thumb-wrapper">
                            <?php if ($img): ?>
                                <?php if ($is_published): ?><a href="<?php echo esc_url($permalink); ?>" target="_blank"><?php endif; ?>
                                <img src="<?php echo esc_url($img); ?>" class="thumb" alt="Product Image">
                                <?php if ($is_published): ?></a><?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($is_published): ?><a href="<?php echo esc_url($permalink); ?>" target="_blank" class="prod-link"><?php endif; ?>
                        <strong style="font-size: 15px; <?php echo $is_added_by_supplier ? 'color: #dc2626;' : ''; ?>"><?php echo esc_html($product->get_name()); ?></strong>
                        <?php if ($is_published): ?></a><?php endif; ?>
                        
                        <?php if($supp_model): ?>
                            <br><span style="font-size: 11px; color: #64748b;">Model: <?php echo esc_html($supp_model); ?></span>
                        <?php endif; ?>
                        
                        <?php if($is_added_by_supplier): ?>
                            <br><span style="font-size: 11px; font-weight:600; color: #dc2626; font-style:italic;">(Added by Supplier)</span>
                        <?php endif; ?>
                    </td>
                    <td style="color: #64748b; font-size: 13px;">
                        <?php echo esc_html($sku); ?>
                        <?php if($hs_code): ?>
                            <br><span style="font-size:11px;">HS: <?php echo esc_html($hs_code); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center; font-weight: 600;"><?php echo $qty; ?></td>
                    <td style="text-align: right;"><?php echo wcsom_format_usd($price); ?></td>
                    <td style="text-align: right;"><strong style="font-size: 15px;"><?php echo wcsom_format_usd($line_total); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <div class="totals-box">
                <div class="total-row">
                    <span>Total Items:</span>
                    <span style="font-weight: 600; color: #0f172a;"><?php echo $grand_qty; ?> units</span>
                </div>
                <div class="total-row">
                    <span>Terms / Incoterm:</span>
                    <?php 
                        $display_term = ($incoterm === 'FOB' && $fob_port) ? 'FOB (Port: ' . esc_html($fob_port) . ')' : esc_html($incoterm);
                    ?>
                    <span class="incoterm-badge"><?php echo $display_term; ?></span>
                </div>
                <div class="total-row grand">
                    <span>Grand Total:</span>
                    <span><?php echo wcsom_format_usd($grand_total); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($payments) || !empty($attachments)): ?>
            <div class="extra-tables">
                <?php if(!empty($payments)): ?>
                    <h4>Payment Schedule</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Percentage</th>
                                <th>Amount</th>
                                <th style="text-align:right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($payments as $p): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($p['title']); ?></strong></td>
                                    <td><?php echo esc_html($p['percent']); ?>%</td>
                                    <td><?php echo wcsom_format_usd($p['amount']); ?></td>
                                    <td style="text-align:right; font-weight:bold; color:<?php echo $p['status'] === 'paid' ? '#16a34a' : '#64748b'; ?>;">
                                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $p['status']))); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if(!empty($attachments)): ?>
                    <h4>Included Files & Documents</h4>
                    <table>
                        <thead>
                            <tr>
                                <th>File Name</th>
                                <th>Type</th>
                                <th>Uploaded By</th>
                                <th style="text-align:right;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($attachments as $att): ?>
                                <tr>
                                    <td><a href="<?php echo esc_url($att['url']); ?>" target="_blank" style="color:#4f46e5; text-decoration:none; font-weight:600;"><?php echo esc_html($att['name']); ?></a></td>
                                    <td style="color:#64748b;"><?php echo esc_html(strtoupper(str_replace('application/', '', $att['type']))); ?></td>
                                    <td><?php echo esc_html($att['uploaded_by']); ?></td>
                                    <td style="text-align:right; color:#64748b;"><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty(trim($notes))): ?>
            <div class="section-box notes-section">
                <h4>Order Notes</h4>
                <?php echo nl2br(esc_html($notes)); ?>
            </div>
        <?php endif; ?>

        <!-- COMPACT PORTAL LINK WITH CHINESE & SECURITY CODE -->
        <div class="portal-compact">
            <div class="portal-compact-icon">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </div>
            <div class="portal-compact-info">
                <p class="portal-compact-title">Fill out this Purchase Order Online</p>
                <p class="portal-compact-sub">您可以在线填写此采购订单 / Access our web portal to enter prices</p>
            </div>
            <div class="portal-compact-link">
                <a href="<?php echo esc_url($portal_link); ?>" target="_blank">Open Online Portal</a>
                <p class="portal-compact-code">Security Code / 安全码: <strong><?php echo esc_html($sec_code); ?></strong></p>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            var element = document.getElementById('po-content');
            var opt = {
                margin:       [0.4, 0.15, 0.4, 0.15], 
                filename:     '<?php echo esc_js($order->post_title); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            
            // Clean, native save without DOM hacks or page number additions
            html2pdf().set(opt).from(element).save();
        }
        
        // Automation check based on URL parameters
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_GET['action']) && $_GET['action'] === 'print'): ?>
                setTimeout(function() { window.print(); }, 800);
            <?php elseif (isset($_GET['action']) && $_GET['action'] === 'pdf'): ?>
                setTimeout(function() { downloadPDF(); }, 800);
            <?php endif; ?>
        });
    </script>
</body>
</html>
