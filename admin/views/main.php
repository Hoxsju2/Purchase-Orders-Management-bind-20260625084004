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
