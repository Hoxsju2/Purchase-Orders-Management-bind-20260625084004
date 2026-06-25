<div class="wrap wcsom-wrap">
    <div class="wcsom-header">
        <h1>Purchase Orders Management</h1>
        <p class="wcsom-subtitle">Manage your suppliers, assign inventory, and generate purchase orders seamlessly.</p>
    </div>
    
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

    <div class="wcsom-tab-content">
        <?php
        if ($active_tab == 'suppliers') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-suppliers.php';
        } elseif ($active_tab == 'search') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-search.php';
        } elseif ($active_tab == 'orders') {
            include WCSOM_PLUGIN_DIR . 'admin/views/tab-orders.php';
        }
        ?>
    </div>
</div>
