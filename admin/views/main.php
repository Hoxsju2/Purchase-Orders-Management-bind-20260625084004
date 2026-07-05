<?php
// Load globals necessary for global modals
$wcsom_categories_global = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
));
$wcsom_suppliers_global = get_users(array(
    'meta_query' => array(
        'relation' => 'OR',
        array('key' => 'company_name', 'compare' => 'EXISTS'),
        array('key' => 'supplier_code', 'compare' => 'EXISTS')
    )
));
?>
<div class="wrap wcsom-wrap">
    <div class="wcsom-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Purchase Orders Management</h1>
            <p class="wcsom-subtitle">Manage your suppliers, assign inventory, and generate purchase orders seamlessly.</p>
        </div>
        <?php if (current_user_can('manage_options')): ?>
        <div>
            <p class="wcsom-subtitle" style="text-align:right;">
                Staff Portal: <code style="background:#fff; padding:4px 8px; border-radius:4px; border:1px solid #e5e7eb; color:#4f46e5; font-weight:bold;">[wcsom_admin_portal]</code><br>
                Supplier Portal: <code style="background:#fff; padding:4px 8px; border-radius:4px; border:1px solid #e5e7eb; color:#4f46e5; font-weight:bold; margin-top:4px; display:inline-block;">[wcsom_supplier_portal]</code>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if ($active_tab !== 'edit-po' && $active_tab !== 'view-bulk'): ?>
    <div class="wcsom-tabs">
        <a href="<?php echo esc_url(wcsom_get_tab_url('suppliers')); ?>" class="wcsom-tab <?php echo $active_tab == 'suppliers' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-groups"></span> Suppliers Directory
        </a>
        <a href="<?php echo esc_url(wcsom_get_tab_url('search')); ?>" class="wcsom-tab <?php echo $active_tab == 'search' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-search"></span> Global Product Search
        </a>
        <?php if (wcsom_can_edit()): ?>
        <a href="<?php echo esc_url(wcsom_get_tab_url('create-product')); ?>" class="wcsom-tab <?php echo $active_tab == 'create-product' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-plus-alt2"></span> Quick Create Product
        </a>
        <a href="<?php echo esc_url(wcsom_get_tab_url('ai-import')); ?>" class="wcsom-tab <?php echo $active_tab == 'ai-import' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-customizer"></span> AI Product Import
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url(wcsom_get_tab_url('orders')); ?>" class="wcsom-tab <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-clipboard"></span> Purchase Orders
        </a>
        <a href="<?php echo esc_url(wcsom_get_tab_url('bulk-orders')); ?>" class="wcsom-tab <?php echo $active_tab == 'bulk-orders' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-portfolio"></span> Bulk Orders
        </a>
        <?php if (current_user_can('manage_options')): ?>
        <a href="<?php echo esc_url(wcsom_get_tab_url('staff')); ?>" class="wcsom-tab <?php echo $active_tab == 'staff' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-admin-users"></span> Staff Access
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="wcsom-tab-content">
        <?php
        if ($active_tab == 'suppliers') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-suppliers.php';
        } elseif ($active_tab == 'search') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-search.php';
        } elseif ($active_tab == 'create-product' && wcsom_can_edit()) {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-create-product.php';
        } elseif ($active_tab == 'ai-import' && wcsom_can_edit()) {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-ai-import.php';
        } elseif ($active_tab == 'orders') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-orders.php';
        } elseif ($active_tab == 'bulk-orders') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-bulk-orders.php';
        } elseif ($active_tab == 'staff' && current_user_can('manage_options')) {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-staff.php';
        } elseif ($active_tab == 'edit-po' && isset($_GET['id'])) {
            include WCSOM_PLUGIN_DIR . 'admin/views/view-edit-po.php';
        } elseif ($active_tab == 'view-bulk' && isset($_GET['id'])) {
            include WCSOM_PLUGIN_DIR . 'admin/views/view-bulk-order.php';
        } else {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-suppliers.php'; // Fallback
        }
        ?>
    </div>
</div>

<!-- Unified Quick Edit Product Modal (Available globally) -->
<?php if (wcsom_can_edit()): ?>
<div id="wcsom-quick-edit-modal" class="wcsom-modal" style="display:none;">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content" style="width: 500px;">
        <div class="wcsom-modal-header">
            <h3>Quick Edit Product</h3>
            <button class="wcsom-modal-close" id="wcsom-close-quick-edit"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body" style="padding-bottom: 30px;">
            <p class="wcsom-card-desc wcsom-mb-4">Update standard product details and supplier-specific information here.</p>
            <input type="hidden" id="wcsom-qe-product-id">

            <div class="wcsom-mb-4">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Product Title</label>
                <input type="text" id="wcsom-qe-title" class="wcsom-input" required>
            </div>
            
            <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">SKU</label>
                    <input type="text" id="wcsom-qe-sku" class="wcsom-input">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">HS Code</label>
                    <input type="text" id="wcsom-qe-hs-code" class="wcsom-input" placeholder="e.g. 8504.40">
                </div>
            </div>

            <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Base Price ($)</label>
                    <input type="number" step="0.01" id="wcsom-qe-price" class="wcsom-input" placeholder="0.00">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Model #</label>
                    <input type="text" id="wcsom-qe-model" class="wcsom-input">
                </div>
            </div>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-outline" id="wcsom-cancel-quick-edit">Cancel</button>
            <button class="wcsom-btn wcsom-btn-primary" id="wcsom-confirm-quick-edit">Save Changes</button>
        </div>
    </div>
</div>

<!-- Duplicate Product Modal -->
<div id="wcsom-duplicate-modal" class="wcsom-modal" style="display:none;">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content" style="width: 750px;">
        <div class="wcsom-modal-header">
            <h3>Duplicate Product</h3>
            <button class="wcsom-modal-close" id="wcsom-close-duplicate-edit"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body" style="padding-bottom: 20px;">
            <p class="wcsom-card-desc wcsom-mb-4">Review the duplicated information below. Submitting this form will create a completely <strong>new Pending product</strong>.</p>
            
            <form id="wcsom-duplicate-form">
                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 2;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Product Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="wcsom-dup-title" class="wcsom-input" required>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">SKU</label>
                        <input type="text" id="wcsom-dup-sku" class="wcsom-input">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">HS Code</label>
                        <input type="text" id="wcsom-dup-hs-code" class="wcsom-input">
                    </div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Price (USD)</label>
                        <input type="number" step="0.01" id="wcsom-dup-price" class="wcsom-input" placeholder="0.00">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Model Number</label>
                        <input type="text" id="wcsom-dup-model" class="wcsom-input">
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Price Type</label>
                        <select id="wcsom-dup-price-type" class="wcsom-input">
                            <option value="EXW">EXW (Ex Works)</option>
                            <option value="FOB">FOB (Free On Board)</option>
                            <option value="CIF">CIF (Cost, Insurance, Freight)</option>
                        </select>
                    </div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Category</label>
                        <select id="wcsom-dup-category" class="wcsom-input" style="width:100%;">
                            <option value="">-- Select Category --</option>
                            <?php foreach ($wcsom_categories_global as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Directly Assign to Supplier</label>
                        <select id="wcsom-dup-supplier" class="wcsom-input" style="width:100%;">
                            <option value="">-- Leave Unassigned --</option>
                            <?php foreach ($wcsom_suppliers_global as $supplier): 
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
                        <div id="wcsom-dup-img-preview" style="width: 80px; height: 80px; border: 1px dashed #cbd5e1; border-radius: 8px; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden;">
                            <span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div>
                            <button type="button" id="wcsom-dup-btn-image" class="wcsom-btn wcsom-btn-outline">Select / Upload Image</button>
                            <button type="button" id="wcsom-dup-btn-remove-image" class="wcsom-btn wcsom-btn-outline" style="color:#ef4444; border-color:#fca5a5; display:none;">Remove</button>
                            <input type="hidden" id="wcsom-dup-image-id" value="">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-outline" id="wcsom-cancel-duplicate-edit">Cancel</button>
            <button type="submit" form="wcsom-duplicate-form" class="wcsom-btn wcsom-btn-primary" id="wcsom-dup-submit">Duplicate as New Pending Product</button>
        </div>
    </div>
</div>

<!-- Bulk Edit Modal -->
<div id="wcsom-bulk-edit-modal" class="wcsom-modal" style="display:none;">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content" style="width: 600px;">
        <div class="wcsom-modal-header">
            <h3>Bulk Edit Products</h3>
            <button class="wcsom-modal-close" id="wcsom-close-bulk-edit"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body" style="padding-bottom: 20px;">
            <div style="background:#fffbeb; color:#92400e; padding:12px 16px; border-radius:8px; border:1px solid #fde68a; margin-bottom:20px; font-size:13px;">
                <strong>Notice:</strong> Leave a field completely empty if you do not want to modify that attribute for the selected products.
            </div>
            
            <form id="wcsom-bulk-edit-form">
                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">SKU</label>
                        <input type="text" id="wcsom-be-sku" class="wcsom-input" placeholder="Leave blank to keep unchanged">
                        <span style="font-size:11px; color:#94a3b8; display:block; margin-top:4px;">Applies exact SKU to all selected.</span>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Tags (Comma Separated)</label>
                        <input type="text" id="wcsom-be-tags" class="wcsom-input" placeholder="e.g. best, featured">
                        <span style="font-size:11px; color:#94a3b8; display:block; margin-top:4px;">Overwrites existing tags.</span>
                    </div>
                </div>

                <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Category</label>
                        <select id="wcsom-be-category" class="wcsom-input" style="width:100%;">
                            <option value="">-- No Change --</option>
                            <?php foreach ($wcsom_categories_global as $cat): ?>
                                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Assignment</label>
                        <select id="wcsom-be-supplier" class="wcsom-input" style="width:100%;">
                            <option value="">-- No Change --</option>
                            <option value="unassign">** Unassign Supplier **</option>
                            <?php foreach ($wcsom_suppliers_global as $supplier): 
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
                        <div id="wcsom-be-img-preview" style="width: 60px; height: 60px; border: 1px dashed #cbd5e1; border-radius: 8px; display:flex; align-items:center; justify-content:center; background:#f8fafc; overflow:hidden;">
                            <span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:20px; width:20px; height:20px;"></span>
                        </div>
                        <div>
                            <button type="button" id="wcsom-be-btn-image" class="wcsom-btn wcsom-btn-outline">Select Bulk Image</button>
                            <button type="button" id="wcsom-be-btn-remove-image" class="wcsom-btn wcsom-btn-outline" style="color:#ef4444; border-color:#fca5a5; display:none;">Clear</button>
                            <input type="hidden" id="wcsom-be-image-id" value="">
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-outline" id="wcsom-cancel-bulk-edit">Cancel</button>
            <button type="submit" form="wcsom-bulk-edit-form" class="wcsom-btn wcsom-btn-primary" id="wcsom-be-submit">Apply Bulk Changes</button>
        </div>
    </div>
</div>
<?php endif; ?>
