<div class="wcsom-grid">
    <!-- Supplier Selection -->
    <div class="wcsom-sidebar">
        <div class="wcsom-card">
            <h3 class="wcsom-card-title">Select a Supplier</h3>
            <p class="wcsom-card-desc">Search by code, name, email, or phone.</p>
            
            <select id="wcsom-supplier-select" class="wcsom-input">
                <option value=""></option>
                <?php foreach ($suppliers as $supplier): 
                    $code = get_user_meta($supplier->ID, 'supplier_code', true);
                    $company = get_user_meta($supplier->ID, 'company_name', true) ?: get_user_meta($supplier->ID, 'first_name', true) . ' ' . get_user_meta($supplier->ID, 'last_name', true);
                    $phone = get_user_meta($supplier->ID, 'billing_phone', true);
                    $email = $supplier->user_email;

                    if (!trim($company)) $company = $supplier->display_name;

                    $display_parts = [];
                    if ($code) $display_parts[] = '[' . $code . ']';
                    $display_parts[] = $company;
                    
                    $contact_info = [];
                    if ($email) $contact_info[] = $email;
                    if ($phone) $contact_info[] = $phone;

                    $final_display = implode(' ', $display_parts);
                    if (!empty($contact_info)) {
                        $final_display .= ' - ' . implode(' | ', $contact_info);
                    }
                ?>
                    <option value="<?php echo esc_attr($supplier->ID); ?>">
                        <?php echo esc_html($final_display); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="wcsom-assign-product-box" class="wcsom-card mt-4" style="display:none;">
            <h4 class="wcsom-card-title">Assign New Product</h4>
            <p class="wcsom-card-desc">Search WooCommerce products to assign.</p>
            
            <div class="wcsom-search-wrapper">
                <span class="dashicons dashicons-search wcsom-search-icon"></span>
                <input type="text" id="wcsom-search-assign-input" class="wcsom-input wcsom-input-with-icon" placeholder="Type product name or SKU...">
            </div>
            <ul id="wcsom-search-assign-results" class="wcsom-autocomplete"></ul>
        </div>
    </div>

    <!-- Supplier Products & PO Builder -->
    <div class="wcsom-main-content">
        <div id="wcsom-supplier-placeholder" class="wcsom-empty-state wcsom-card">
            <div class="wcsom-empty-icon">
                <span class="dashicons dashicons-store"></span>
            </div>
            <h3>No Supplier Selected</h3>
            <p>Please select a supplier from the left sidebar to view their products and create orders.</p>
        </div>

        <div id="wcsom-supplier-data" class="wcsom-card" style="display:none;">
            <div class="wcsom-flex-between wcsom-mb-4">
                <div>
                    <h3 class="wcsom-card-title">Assigned Products</h3>
                    <p class="wcsom-card-desc">Select products below to create a new purchase order.</p>
                </div>
                <button id="wcsom-trigger-create-po" class="wcsom-btn wcsom-btn-primary" disabled>
                    <span class="dashicons dashicons-plus-alt2"></span> Create Purchase Order
                </button>
            </div>
            
            <div class="wcsom-table-responsive">
                <table class="wcsom-table">
                    <thead>
                        <tr>
                            <th class="wcsom-th-check"><input type="checkbox" id="wcsom-select-all"></th>
                            <th style="width: 60px;">Image</th>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Price (USD)</th>
                        </tr>
                    </thead>
                    <tbody id="wcsom-supplier-products-body">
                        <!-- Loaded via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for PO Creation -->
<div id="wcsom-po-modal" class="wcsom-modal">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content">
        <div class="wcsom-modal-header">
            <h3>Configure Purchase Order</h3>
            <button class="wcsom-modal-close" id="wcsom-close-po"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body">
            <div class="wcsom-mb-4">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px;">Order Reference (Optional)</label>
                <input type="text" id="wcsom-po-ref" class="wcsom-input" placeholder="e.g. INV-1004 (Leave blank to auto-generate)">
            </div>
            <p class="wcsom-card-desc mb-4">Set the quantities for the selected products.</p>
            <div id="wcsom-po-items-list" class="wcsom-po-items"></div>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-outline" id="wcsom-cancel-po">Cancel</button>
            <button class="wcsom-btn wcsom-btn-primary" id="wcsom-confirm-po">Confirm & Create PO</button>
        </div>
    </div>
</div>
