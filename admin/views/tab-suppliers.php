<div class="wcsom-grid">
    <!-- Supplier Selection -->
    <div class="wcsom-panel wcsom-sidebar">
        <h3>Select a Supplier</h3>
        <select id="wcsom-supplier-select" class="wcsom-input">
            <option value="">-- Choose Supplier --</option>
            <?php foreach ($suppliers as $supplier): 
                $name = get_user_meta($supplier->ID, 'company_name', true) ?: get_user_meta($supplier->ID, 'supplier_code', true);
                if (!$name) $name = $supplier->display_name;
            ?>
                <option value="<?php echo esc_attr($supplier->ID); ?>"><?php echo esc_html($name); ?> (ID: <?php echo $supplier->ID; ?>)</option>
            <?php endforeach; ?>
        </select>

        <div id="wcsom-assign-product-box" style="display:none; margin-top:20px;">
            <h4>Assign New Product</h4>
            <input type="text" id="wcsom-search-assign-input" class="wcsom-input" placeholder="Type product name/SKU...">
            <ul id="wcsom-search-assign-results" class="wcsom-autocomplete"></ul>
        </div>
    </div>

    <!-- Supplier Products & PO Builder -->
    <div class="wcsom-panel wcsom-main-content">
        <div id="wcsom-supplier-placeholder">
            <p>Please select a supplier from the left to view their products and create orders.</p>
        </div>

        <div id="wcsom-supplier-data" style="display:none;">
            <div class="wcsom-flex-between">
                <h3>Assigned Products</h3>
                <button id="wcsom-trigger-create-po" class="button button-primary" disabled>Create Purchase Order</button>
            </div>
            
            <table class="wp-list-table widefat fixed striped wcsom-table mt-4">
                <thead>
                    <tr>
                        <td class="manage-column check-column"><input type="checkbox" id="wcsom-select-all"></td>
                        <th style="width: 80px;">Image</th>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody id="wcsom-supplier-products-body">
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for PO Creation -->
<div id="wcsom-po-modal" class="wcsom-modal">
    <div class="wcsom-modal-content">
        <h3>New Purchase Order</h3>
        <div id="wcsom-po-items-list"></div>
        <div class="wcsom-modal-footer">
            <button class="button" id="wcsom-cancel-po">Cancel</button>
            <button class="button button-primary" id="wcsom-confirm-po">Confirm & Create PO</button>
        </div>
    </div>
</div>
