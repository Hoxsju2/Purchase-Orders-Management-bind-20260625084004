<?php
$categories = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
));

$suppliers = get_users(array(
    'meta_query' => array(
        'relation' => 'OR',
        array('key' => 'company_name', 'compare' => 'EXISTS'),
        array('key' => 'supplier_code', 'compare' => 'EXISTS')
    )
));
?>
<div class="wcsom-grid">
    <div class="wcsom-main-content" style="max-width: 800px;">
        
        <!-- Duplicate Entry Point 2: Search and Auto-fill -->
        <div class="wcsom-card wcsom-mb-4" style="background:#f8fafc; border: 1px dashed #cbd5e1;">
            <label style="display:block; font-size:14px; font-weight:700; margin-bottom:6px; color:#4f46e5;">
                <span class="dashicons dashicons-admin-page"></span> Duplicate an Existing Product
            </label>
            <p class="wcsom-card-desc mb-2" style="font-size:13px; margin-bottom:12px;">Search for any product in your store to instantly auto-fill the form below with its details.</p>
            <select id="wcsom-qc-duplicate-search" class="wcsom-input" style="width: 100%;"></select>
        </div>

        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Quick Create Product (Accessories / PO Items)</h3>
            <p class="wcsom-card-desc wcsom-mb-4">Use this tool to quickly add products to WooCommerce that you need for Purchase Orders but might not sell directly online. Products are created with a <strong>Pending</strong> status.</p>
            
            <form id="wcsom-quick-create-form">
                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 2;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Product Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="wcsom-qc-title" class="wcsom-input" required placeholder="e.g., Premium Carry Bag">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">SKU</label>
                        <input type="text" id="wcsom-qc-sku" class="wcsom-input" placeholder="e.g., ACC-001">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">HS Code</label>
                        <input type="text" id="wcsom-qc-hs-code" class="wcsom-input" placeholder="e.g. 8504.40">
                    </div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Price (USD) <span style="color:#ef4444;">*</span></label>
                        <input type="number" step="0.01" id="wcsom-qc-price" class="wcsom-input" placeholder="0.00">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Model Number</label>
                        <input type="text" id="wcsom-qc-model" class="wcsom-input" placeholder="e.g. M-808 (Optional)">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Price Type</label>
                        <select id="wcsom-qc-price-type" class="wcsom-input">
                            <option value="EXW">EXW (Ex Works)</option>
                            <option value="FOB">FOB (Free On Board)</option>
                            <option value="CIF">CIF (Cost, Insurance, Freight)</option>
                        </select>
                    </div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Category</label>
                        <select id="wcsom-qc-category" class="wcsom-input">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Directly Assign to Supplier</label>
                        <select id="wcsom-qc-supplier" class="wcsom-input">
                            <option value="">-- Leave Unassigned --</option>
                            <?php foreach ($suppliers as $supplier): 
                                $code = get_user_meta($supplier->ID, 'supplier_code', true);
                                $company = get_user_meta($supplier->ID, 'company_name', true) ?: $supplier->display_name;
                                $display = ($code ? '[' . $code . '] ' : '') . $company;
                            ?>
                                <option value="<?php echo esc_attr($supplier->ID); ?>"><?php echo esc_html($display); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="wcsom-mb-4">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Product Image</label>
                    <div style="display:flex; align-items:center; gap: 15px;">
                        <div id="wcsom-qc-img-preview" style="width: 80px; height: 80px; border: 1px dashed #cbd5e1; border-radius: 8px; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden;">
                            <span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div>
                            <button type="button" id="wcsom-qc-btn-image" class="wcsom-btn wcsom-btn-outline">Select / Upload Image</button>
                            <button type="button" id="wcsom-qc-btn-remove-image" class="wcsom-btn wcsom-btn-outline" style="color:#ef4444; border-color:#fca5a5; display:none;">Remove</button>
                            <input type="hidden" id="wcsom-qc-image-id" value="">
                        </div>
                    </div>
                </div>

                <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; text-align: right;">
                    <button type="submit" id="wcsom-qc-submit" class="wcsom-btn wcsom-btn-primary" style="padding: 12px 24px; font-size: 15px;">Create Pending Product</button>
                </div>
            </form>
        </div>
    </div>
</div>