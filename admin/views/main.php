<div class="wrap wcsom-wrap">
    <h1>WooCommerce Supplier Orders Manager</h1>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=wcsom-dashboard&tab=suppliers" class="nav-tab <?php echo $active_tab == 'suppliers' ? 'nav-tab-active' : ''; ?>">Suppliers Directory</a>
        <a href="?page=wcsom-dashboard&tab=search" class="nav-tab <?php echo $active_tab == 'search' ? 'nav-tab-active' : ''; ?>">Global Product Search</a>
        <a href="?page=wcsom-dashboard&tab=orders" class="nav-tab <?php echo $active_tab == 'orders' ? 'nav-tab-active' : ''; ?>">Purchase Orders</a>
    </h2>

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
