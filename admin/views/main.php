<div class="wrap wcsom-wrap">
    <div class="wcsom-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Purchase Orders Management</h1>
            <p class="wcsom-subtitle">Manage your suppliers, assign inventory, and generate purchase orders seamlessly.</p>
        </div>
        <div>
            <p class="wcsom-subtitle" style="text-align:right;">Provide suppliers this shortcode to view their portal:<br><code style="background:#fff; padding:4px 8px; border-radius:4px; border:1px solid #e5e7eb; color:#4f46e5; font-weight:bold;">[wcsom_supplier_portal]</code></p>
        </div>
    </div>
    
    <?php if ($active_tab !== 'edit-po'): ?>
    <div class="wcsom-tabs">
        <a href="?page=wcsom-dashboard&tab=suppliers" class="wcsom-tab <?php echo $active_tab == 'suppliers' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-groups"></span> Suppliers Directory
        </a>
        <a href="?page=wcsom-dashboard&tab=search" class="wcsom-tab <?php echo $active_tab == 'search' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-search"></span> Global Product Search
        </a>
        <a href="?page=wcsom-dashboard&tab=orders" class="wcsom-tab <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
            <span class="dashicons dashicons-clipboard"></span> Purchase Orders
        </a>
    </div>
    <?php endif; ?>

    <div class="wcsom-tab-content">
        <?php
        if ($active_tab == 'suppliers') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-suppliers.php';
        } elseif ($active_tab == 'search') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-search.php';
        } elseif ($active_tab == 'orders') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-orders.php';
        } elseif ($active_tab == 'edit-po' && isset($_GET['id'])) {
            include WCSOM_PLUGIN_DIR . 'admin/views/view-edit-po.php';
        }
        ?>
    </div>
</div>
