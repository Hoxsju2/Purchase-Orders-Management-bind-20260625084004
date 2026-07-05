<?php
if (!defined('ABSPATH')) exit;

$po_id = intval($_GET['po_id']);
$order = get_post($po_id);

if (!$order || $order->post_type !== 'wcsom_order') {
    wp_die('Invalid Order Request.');
}

// Generate Security code if not exists
$sec_code = get_post_meta($po_id, '_wcsom_security_code', true);
if (empty($sec_code)) {
    $sec_code = mt_rand(1000000, 9999999);
    update_post_meta($po_id, '_wcsom_security_code', $sec_code);
}

$supplier_id = get_post_meta($po_id, '_wcsom_supplier_id', true);
$is_authorized = false;
$error_msg = '';

// Check Authorization Methods
if (is_user_logged_in() && get_current_user_id() == $supplier_id) {
    // 1. Logged in and is the exact supplier
    $is_authorized = true;
} else {
    // 2. Guest Code Access check - Strip spaces entirely to ensure PDF copies work smoothly
    if (isset($_POST['wcsom_verify_code'])) {
        $input_code = preg_replace('/\\s+/', '', sanitize_text_field($_POST['wcsom_verify_code']));
        if ($input_code === $sec_code) {
            $is_authorized = true;
            // Set cookie so they can refresh the page without re-entering
            setcookie('wcsom_code_' . $po_id, $sec_code, time() + 86400, '/');
            $_COOKIE['wcsom_code_' . $po_id] = $sec_code; // Populate for current load
        } else {
            $error_msg = 'Incorrect Security Code. Please check the PDF and try again.';
        }
    } elseif (isset($_COOKIE['wcsom_code_' . $po_id]) && $_COOKIE['wcsom_code_' . $po_id] === $sec_code) {
        $is_authorized = true;
    }
}

// Order Data
$status = get_post_meta($po_id, '_wcsom_status', true) ?: 'waiting_for_quote';
$items = get_post_meta($po_id, '_wcsom_items', true) ?: [];
$notes = get_post_meta($po_id, '_wcsom_notes', true) ?: '';
$payments = get_post_meta($po_id, '_wcsom_payments', true) ?: [];
$attachments = get_post_meta($po_id, '_wcsom_attachments', true) ?: [];
$incoterm = get_post_meta($po_id, '_wcsom_incoterm', true) ?: 'EXW';
$fob_port = get_post_meta($po_id, '_wcsom_fob_port', true) ?: '';
$allow_supp_qty = get_post_meta($po_id, '_wcsom_allow_supp_qty', true) === 'yes';

// STRICT: Only editable if draft or waiting_for_quote. 'Pending' completely locks it out.
$is_editable = in_array($status, ['draft', 'waiting_for_quote']);
$can_upload_files = in_array($status, ['draft', 'waiting_for_quote']);

// Logo for header
$logo_id = get_theme_mod('custom_logo');
$logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
$site_name = get_bloginfo('name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Portal - <?php echo esc_html($order->post_title); ?></title>
    <!-- Use Tailwind CSS CDN for a completely standalone, modern app aesthetic -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="min-h-screen text-slate-800 flex flex-col">

    <!-- Header -->
    <header class="bg-white border-b border-slate-200 shadow-sm sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <?php if($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" class="h-8 object-contain">
                <?php else: ?>
                    <span class="text-xl font-bold text-slate-900"><?php echo esc_html($site_name); ?></span>
                <?php endif; ?>
                <div class="h-5 w-px bg-slate-300 mx-2"></div>
                <span class="text-slate-500 font-medium">Supplier Portal</span>
            </div>
            
            <?php if($is_authorized): ?>
                <div class="flex gap-2">
                    <a href="<?php echo esc_url(home_url('/?wcsom_print=1&po_id=' . $po_id . '&sec_code=' . $sec_code . '&action=print')); ?>" target="_blank" class="text-sm font-medium text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 px-4 py-2 rounded-lg transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                        Print PO
                    </a>
                    <a href="<?php echo esc_url(home_url('/?wcsom_print=1&po_id=' . $po_id . '&sec_code=' . $sec_code . '&action=pdf')); ?>" target="_blank" class="text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 px-4 py-2 rounded-lg transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download PDF
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <main class="flex-grow p-6 flex flex-col items-center">
        <?php if (!$is_authorized): ?>
            <!-- AUTHENTICATION SCREEN -->
            <div class="w-full max-w-md mt-20 bg-white p-8 rounded-2xl shadow-lg border border-slate-100 fade-in text-center">
                <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8V7a4 4 0 00-8 0v4h8z"></path></svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 mb-2">Secure PO Access</h2>
                <p class="text-sm text-slate-500 mb-6">Enter the 7-digit security code found on your PDF document to view and edit this specific order.</p>
                
                <?php if ($error_msg): ?>
                    <div class="bg-red-50 text-red-700 text-sm p-3 rounded-lg mb-4 border border-red-100">
                        <?php echo esc_html($error_msg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- The oninput JS guarantees instant space removal on paste to prevent UI errors -->
                    <input type="text" name="wcsom_verify_code" required placeholder="e.g. 1234567" oninput="this.value = this.value.replace(/\s+/g, '')" class="w-full text-center text-2xl tracking-widest font-mono p-4 border border-slate-300 rounded-xl focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/20 outline-none transition mb-4">
                    <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl transition shadow-md hover:shadow-lg">
                        Access Order
                    </button>
                </form>

                <p class="mt-6 text-xs text-slate-400">If you are a registered supplier, you can also <a href="<?php echo esc_url(wp_login_url()); ?>" class="text-indigo-500 hover:underline">log in to your account</a> to see all your orders at once.</p>
            </div>

        <?php else: ?>
            <!-- MAIN INTERACTIVE PORTAL -->
            <div class="w-full max-w-5xl fade-in mt-6">
                
                <?php if (isset($_GET['updated'])): ?>
                    <div class="bg-green-50 border border-green-200 text-green-800 p-4 rounded-xl mb-6 flex items-center gap-3">
                        <svg class="w-6 h-6 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                        <strong>Order successfully updated! The admin has been notified.</strong>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-8 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                                <?php echo esc_html($order->post_title); ?>
                                <span class="px-3 py-1 bg-slate-100 text-slate-600 text-sm font-semibold rounded-full tracking-wide border border-slate-200">
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?>
                                </span>
                            </h1>
                            <p class="text-slate-500 mt-2">
                                <?php if ($is_editable): ?>
                                    This order requires your input. Please fill out your prices and select terms below. <?php echo $allow_supp_qty ? 'You can also adjust the quantities if needed.' : 'Quantities are locked by the admin.'; ?>
                                <?php else: ?>
                                    This order is finalized and locked for editing.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST" enctype="multipart/form-data" class="p-8" id="portal-form">
                        <input type="hidden" name="action" value="wcsom_supplier_update_po">
                        <input type="hidden" name="po_id" value="<?php echo esc_attr($po_id); ?>">
                        <input type="hidden" name="wcsom_verify_code" value="<?php echo esc_attr($sec_code); ?>">
                        <?php wp_nonce_field('wcsom_update_po', 'wcsom_nonce'); ?>

                        <!-- Items Table -->
                        <div class="overflow-x-auto rounded-xl border border-slate-200 mb-8">
                            <table class="w-full text-left border-collapse" id="po-table">
                                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider font-semibold">
                                    <tr>
                                        <th class="p-4 border-b border-slate-200">Product</th>
                                        <th class="p-4 border-b border-slate-200 w-40">Unit Price (USD)</th>
                                        <th class="p-4 border-b border-slate-200 w-24 text-center">Qty</th>
                                        <th class="p-4 border-b border-slate-200 text-right w-40">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php 
                                    $grand_total = 0;
                                    foreach ($items as $item): 
                                        $product = wc_get_product($item['product_id']);
                                        if (!$product) continue;
                                        $line_total = $item['qty'] * $item['price'];
                                        $grand_total += $line_total;
                                        $is_added = !empty($item['added_by_supplier']);
                                        $supp_model = isset($item['supplier_model']) ? $item['supplier_model'] : get_post_meta($item['product_id'], '_wcsom_supplier_model', true);
                                        $hs_code = get_post_meta($item['product_id'], '_wcsom_hs_code', true);
                                        
                                        // Prevent showing 0.00 if it was freshly added
                                        $display_price = $item['price'] > 0 ? esc_attr($item['price']) : '';
                                    ?>
                                    <tr class="hover:bg-slate-50/50 transition">
                                        <td class="p-4">
                                            <p class="font-semibold text-slate-800 <?php if($is_added) echo 'text-red-600'; ?>"><?php echo esc_html($product->get_name()); ?></p>
                                            <?php if($supp_model): ?>
                                                <p class="text-xs text-slate-500 mt-0.5">Model: <?php echo esc_html($supp_model); ?></p>
                                            <?php endif; ?>
                                            <p class="text-xs text-slate-400 mt-0.5">
                                                SKU: <?php echo esc_html($product->get_sku()); ?>
                                                <?php if($hs_code) echo ' | HS Code: ' . esc_html($hs_code); ?>
                                            </p>
                                            
                                            <?php if($is_added): ?>
                                                <span class="inline-block mt-1 text-xs font-semibold bg-red-50 text-red-600 px-2 py-0.5 rounded border border-red-100">Added by You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4">
                                            <?php if ($is_editable): ?>
                                                <div class="relative">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">$</span>
                                                    <input type="number" step="0.01" name="items[<?php echo $item['product_id']; ?>][price]" value="<?php echo $display_price; ?>" class="w-full pl-7 pr-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition val-price" placeholder="0.00">
                                                </div>
                                            <?php else: ?>
                                                <span class="font-medium"><?php echo wcsom_format_usd($item['price']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-center">
                                            <?php if ($is_editable && $allow_supp_qty): ?>
                                                <input type="number" min="0" name="items[<?php echo $item['product_id']; ?>][qty]" value="<?php echo esc_attr($item['qty']); ?>" class="w-20 px-2 py-1 text-center border border-slate-300 rounded-md focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition val-qty">
                                            <?php else: ?>
                                                <input type="hidden" name="items[<?php echo $item['product_id']; ?>][qty]" class="val-qty" value="<?php echo esc_attr($item['qty']); ?>">
                                                <span class="font-bold text-slate-600 bg-slate-100 py-1 px-3 rounded-md"><?php echo esc_html($item['qty']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="p-4 text-right font-bold text-slate-800 val-subtotal">
                                            <?php echo wcsom_format_usd($line_total); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex justify-end border-t border-slate-200 pt-6 mb-10">
                            <div class="text-right">
                                <p class="text-sm font-bold text-slate-400 uppercase tracking-wider mb-1">Grand Total (<span id="grand-total-incoterm"><?php echo esc_html($incoterm); ?></span>)</p>
                                <p class="text-4xl font-extrabold text-slate-900" id="grand-total-display"><?php echo wcsom_format_usd($grand_total); ?></p>
                                
                                <?php if ($is_editable): ?>
                                    <div class="mt-4 flex items-center justify-end gap-5 bg-slate-50 px-4 py-3 rounded-lg border border-slate-200 shadow-sm">
                                        <span class="text-sm font-bold text-slate-700">Shipping Terms:</span>
                                        <label class="inline-flex items-center cursor-pointer group">
                                            <input type="radio" name="wcsom_incoterm" value="EXW" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer" <?php checked($incoterm, 'EXW'); ?>>
                                            <span class="ml-2 text-slate-700 font-bold text-sm group-hover:text-indigo-600 transition">EXW</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer group">
                                            <input type="radio" name="wcsom_incoterm" value="FOB" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500 cursor-pointer" <?php checked($incoterm, 'FOB'); ?>>
                                            <span class="ml-2 text-slate-700 font-bold text-sm group-hover:text-indigo-600 transition">FOB</span>
                                        </label>
                                    </div>
                                    <div id="wcsom-fob-port-wrapper" class="mt-3 flex items-center justify-end gap-3" style="display: <?php echo $incoterm === 'FOB' ? 'flex' : 'none'; ?>;">
                                        <span class="text-sm font-bold text-slate-700">Port Name:</span>
                                        <input type="text" name="wcsom_fob_port" id="wcsom-fob-port-input" value="<?php echo esc_attr($fob_port); ?>" placeholder="e.g. Shenzhen" class="p-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none w-48">
                                    </div>
                                <?php else: ?>
                                    <input type="hidden" name="wcsom_incoterm" value="<?php echo esc_attr($incoterm); ?>">
                                    <input type="hidden" name="wcsom_fob_port" value="<?php echo esc_attr($fob_port); ?>">
                                    <?php if($incoterm === 'FOB'): ?>
                                    <div class="mt-3 flex items-center justify-end gap-3">
                                        <span class="text-sm font-bold text-slate-700">Port Name:</span>
                                        <span class="font-bold text-slate-800"><?php echo esc_html($fob_port ?: 'Not Specified'); ?></span>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                            <!-- Notes Section -->
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Order Notes</label>
                                <?php if ($is_editable): ?>
                                    <textarea name="po_notes" rows="5" placeholder="Add any special instructions or lead time details..." class="w-full p-4 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 outline-none transition resize-y"><?php echo esc_textarea($notes); ?></textarea>
                                <?php else: ?>
                                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-slate-600 min-h-[120px]">
                                        <?php echo !empty($notes) ? nl2br(esc_html($notes)) : '<em>No notes added.</em>'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Attachments Section -->
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Files & Documents</label>
                                
                                <?php if (!empty($attachments)): ?>
                                    <div class="space-y-3 mb-4">
                                        <?php foreach($attachments as $att): ?>
                                            <div class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-lg hover:border-indigo-300 transition group">
                                                <div class="flex items-center gap-3 overflow-hidden">
                                                    <svg class="w-8 h-8 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                    <div class="truncate">
                                                        <a href="<?php echo esc_url($att['url']); ?>" target="_blank" class="font-semibold text-slate-800 group-hover:text-indigo-600 truncate block"><?php echo esc_html($att['name']); ?></a>
                                                        <span class="text-xs text-slate-400">By: <?php echo esc_html($att['uploaded_by']); ?></span>
                                                    </div>
                                                </div>
                                                <a href="<?php echo esc_url($att['url']); ?>" target="_blank" class="text-sm font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md transition shrink-0">Open</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($can_upload_files): ?>
                                    <div class="border-2 border-dashed border-slate-300 bg-slate-50 rounded-xl p-6 text-center hover:bg-slate-100 hover:border-indigo-400 transition relative">
                                        <svg class="mx-auto h-8 w-8 text-slate-400 mb-2" stroke="currentColor" fill="none" viewBox="0 0 48 48"><path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                        <div class="text-sm text-slate-600">
                                            <label class="relative cursor-pointer bg-transparent rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                                <span>Upload files</span>
                                                <input type="file" id="wcsom-file-input" name="wcsom_attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="sr-only">
                                            </label>
                                            <p class="pl-1 inline">or click to browse</p>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">PDF, PNG, JPG, XLS up to 10MB</p>
                                    </div>
                                    
                                    <!-- Container for showing selected files instantly via JS before form submit -->
                                    <div id="wcsom-selected-files" class="mt-3 space-y-2"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($payments)): ?>
                            <div class="mb-10">
                                <h3 class="text-lg font-bold text-slate-800 mb-4">Payment Schedule</h3>
                                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
                                    <table class="w-full text-left text-sm" id="payments-table">
                                        <thead class="bg-slate-50 text-slate-500 border-b border-slate-200">
                                            <tr>
                                                <th class="p-3">Description</th>
                                                <th class="p-3">Percentage</th>
                                                <th class="p-3">Amount</th>
                                                <th class="p-3 text-right">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <?php foreach($payments as $p): ?>
                                            <tr class="payment-row" data-percent="<?php echo esc_attr($p['percent']); ?>">
                                                <td class="p-3 font-semibold text-slate-700"><?php echo esc_html($p['title']); ?></td>
                                                <td class="p-3 font-medium text-slate-500"><?php echo esc_html($p['percent']); ?>%</td>
                                                <td class="p-3 font-bold text-slate-900 val-pay-amount"><?php echo wcsom_format_usd($p['amount']); ?></td>
                                                <td class="p-3 text-right font-bold uppercase text-xs tracking-wider <?php echo $p['status'] === 'paid' ? 'text-green-600' : 'text-slate-500'; ?>">
                                                    <?php echo esc_html(str_replace('_', ' ', $p['status'])); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if($is_editable): ?>
                                    <p class="text-xs text-slate-500 mt-2 italic">Note: Payment amounts update automatically as you adjust item prices or quantities.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($is_editable): ?>
                            <div class="mt-8 pt-8 border-t border-slate-200 flex justify-end">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl shadow-lg hover:shadow-xl transition flex items-center gap-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                    Submit Quote & Save
                                </button>
                            </div>
                        <?php endif; ?>

                    </form>
                </div>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const tableBody = document.querySelector('#po-table tbody');
                    const grandTotalDisplay = document.getElementById('grand-total-display');
                    const paymentRows = document.querySelectorAll('.payment-row');
                    const fileInput = document.getElementById('wcsom-file-input');
                    const fileList = document.getElementById('wcsom-selected-files');
                    
                    const incotermRadios = document.querySelectorAll('input[name="wcsom_incoterm"]');
                    const incotermDisplay = document.getElementById('grand-total-incoterm');
                    const fobWrapper = document.getElementById('wcsom-fob-port-wrapper');
                    const fobInput = document.getElementById('wcsom-fob-port-input');

                    // 1. Format Currency Helper
                    function formatMoney(amount) {
                        return '$' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    }

                    // 2. Real-time Calculation for Prices and Payments
                    function updateTotals() {
                        let grandTotal = 0;
                        const rows = tableBody.querySelectorAll('tr');
                        
                        // Calculate Grand Total from Items
                        rows.forEach(row => {
                            const priceInput = row.querySelector('.val-price');
                            const qtyInput = row.querySelector('.val-qty');
                            const subtotalCell = row.querySelector('.val-subtotal');
                            
                            if (priceInput && qtyInput && subtotalCell) {
                                const price = parseFloat(priceInput.value) || 0;
                                const qty = parseInt(qtyInput.value) || 0;
                                const sub = price * qty;
                                
                                subtotalCell.innerText = formatMoney(sub);
                                grandTotal += sub;
                            }
                        });
                        
                        grandTotalDisplay.innerText = formatMoney(grandTotal);

                        // Recalculate Payment Schedule rows automatically
                        paymentRows.forEach(row => {
                            const pct = parseFloat(row.getAttribute('data-percent')) || 0;
                            const amtCell = row.querySelector('.val-pay-amount');
                            
                            if (pct > 0 && amtCell) {
                                const newAmount = (pct / 100) * grandTotal;
                                amtCell.innerText = formatMoney(newAmount);
                            }
                        });
                    }

                    // Attach input listeners for price changes
                    if (tableBody) {
                        tableBody.addEventListener('input', function(e) {
                            if (e.target.classList.contains('val-price') || e.target.classList.contains('val-qty')) {
                                updateTotals();
                            }
                        });
                    }
                    
                    // Attach listener to update Incoterm text display instantly and toggle FOB Port field
                    if (incotermRadios) {
                        incotermRadios.forEach(radio => {
                            radio.addEventListener('change', function() {
                                if (incotermDisplay) incotermDisplay.innerText = this.value;
                                if (this.value === 'FOB') {
                                    if(fobWrapper) fobWrapper.style.display = 'flex';
                                } else {
                                    if(fobWrapper) fobWrapper.style.display = 'none';
                                    if(fobInput) fobInput.value = ''; // clear when hiding
                                }
                            });
                        });
                    }

                    // 3. Real-time Visual Feedback for Selected Files
                    if (fileInput && fileList) {
                        fileInput.addEventListener('change', function() {
                            fileList.innerHTML = ''; // Clear previous preview
                            
                            if (this.files.length > 0) {
                                let html = '<p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 mt-2">Files ready to upload:</p>';
                                
                                Array.from(this.files).forEach(file => {
                                    const fileSizeMB = (file.size / 1024 / 1024).toFixed(2);
                                    html += `
                                    <div class="flex items-center gap-2 p-2.5 bg-indigo-50 border border-indigo-100 rounded-lg text-indigo-700 text-sm shadow-sm transition-all duration-200 fade-in">
                                        <svg class="w-5 h-5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                        <span class="truncate font-medium flex-grow">${file.name}</span>
                                        <span class="text-indigo-400 text-xs font-semibold px-2 py-1 bg-white rounded-md">${fileSizeMB} MB</span>
                                    </div>`;
                                });
                                
                                fileList.innerHTML = html;
                            }
                        });
                    }
                });
            </script>

        <?php endif; ?>
    </main>
</body>
</html>
