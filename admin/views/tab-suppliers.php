<?php
if (!defined('ABSPATH')) exit;

$can_edit = wcsom_can_edit();
$is_main_admin = current_user_can('manage_options');

// Fetch all suppliers
$suppliers = get_users(array(
    'meta_query' => array(
        'relation' => 'OR',
        array('key' => 'company_name', 'compare' => 'EXISTS'),
        array('key' => 'supplier_code', 'compare' => 'EXISTS')
    )
));

// Calculate stats efficiently for the grid
$supplier_stats = [];

global $wpdb;
$prod_counts = $wpdb->get_results("
    SELECT meta_value as supplier_id, COUNT(post_id) as count 
    FROM {$wpdb->postmeta} pm 
    JOIN {$wpdb->posts} p ON pm.post_id = p.ID
    WHERE pm.meta_key = '_wcsom_supplier_id' AND p.post_type = 'product' AND p.post_status != 'trash'
    GROUP BY meta_value
", ARRAY_A);

foreach ($prod_counts as $row) {
    $supplier_stats[$row['supplier_id']]['products'] = $row['count'];
}

// Get PO counts (Total & Active)
$po_data = $wpdb->get_results("
    SELECT pm_sup.meta_value as supplier_id, pm_status.meta_value as status
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm_sup ON p.ID = pm_sup.post_id AND pm_sup.meta_key = '_wcsom_supplier_id'
    LEFT JOIN {$wpdb->postmeta} pm_status ON p.ID = pm_status.post_id AND pm_status.meta_key = '_wcsom_status'
    WHERE p.post_type = 'wcsom_order' AND p.post_status = 'publish'
", ARRAY_A);

$active_statuses = ['draft', 'waiting_for_quote', 'pending', 'in progress'];

foreach ($po_data as $row) {
    $sid = $row['supplier_id'];
    if (!isset($supplier_stats[$sid])) $supplier_stats[$sid] = ['products' => 0, 'total_po' => 0, 'active_po' => 0];
    if (!isset($supplier_stats[$sid]['total_po'])) $supplier_stats[$sid]['total_po'] = 0;
    if (!isset($supplier_stats[$sid]['active_po'])) $supplier_stats[$sid]['active_po'] = 0;
    
    $supplier_stats[$sid]['total_po']++;
    if (in_array($row['status'], $active_statuses)) {
        $supplier_stats[$sid]['active_po']++;
    }
}
?>

<!-- GRID VIEW -->
<div id="wcsom-suppliers-grid-view">
    <div class="wcsom-flex-between wcsom-mb-4">
        <div>
            <h3 class="wcsom-card-title" style="font-size: 22px;">Suppliers Directory</h3>
            <p class="wcsom-card-desc">Select a supplier to manage their assigned products and build purchase orders.</p>
        </div>
        <div style="display:flex; gap: 12px; align-items:center;">
            <?php if ($can_edit): ?>
            <button id="wcsom-btn-create-supplier" class="wcsom-btn wcsom-btn-outline" style="color: #0f172a; border-color: #cbd5e1; font-weight:600;">
                <span class="dashicons dashicons-plus-alt2" style="margin-top:2px;"></span> Create Supplier
            </button>
            <?php endif; ?>
            <div class="wcsom-search-wrapper" style="width: 250px;">
                <span class="dashicons dashicons-search wcsom-search-icon"></span>
                <input type="text" id="wcsom-filter-suppliers" class="wcsom-input wcsom-input-with-icon" placeholder="Filter by Name or Code...">
            </div>
        </div>
    </div>

    <div class="wcsom-supplier-grid">
        <?php 
        $visible_count = 0;
        foreach ($suppliers as $supplier): 
            $sid = $supplier->ID;
            $is_visible = get_user_meta($sid, '_wcsom_staff_visible', true);
            
            // Only show to staff if explicitly marked visible. Main admin sees all.
            if (!$is_main_admin && $is_visible !== '1') {
                continue; 
            }
            $visible_count++;

            $code = get_user_meta($sid, 'supplier_code', true);
            $company = get_user_meta($sid, 'company_name', true) ?: $supplier->display_name;
            if (!trim($company)) $company = 'Unknown Supplier';

            $prods = isset($supplier_stats[$sid]['products']) ? $supplier_stats[$sid]['products'] : 0;
            $total_po = isset($supplier_stats[$sid]['total_po']) ? $supplier_stats[$sid]['total_po'] : 0;
            $active_po = isset($supplier_stats[$sid]['active_po']) ? $supplier_stats[$sid]['active_po'] : 0;
            
            $search_str = strtolower($company . ' ' . $code);
        ?>
            <div class="wcsom-supplier-card" data-search="<?php echo esc_attr($search_str); ?>" data-id="<?php echo esc_attr($sid); ?>" data-name="<?php echo esc_attr($company); ?>" data-code="<?php echo esc_attr($code); ?>">
                <div class="wcsom-supplier-card-header">
                    <div class="wcsom-supplier-avatar">
                        <?php echo strtoupper(substr($company, 0, 1)); ?>
                    </div>
                    <div class="wcsom-supplier-info">
                        <?php if($code): ?><span class="wcsom-supplier-code">[<?php echo esc_html($code); ?>]</span><?php endif; ?>
                        <h4 class="wcsom-supplier-name"><?php echo esc_html($company); ?></h4>
                    </div>
                </div>
                <div class="wcsom-supplier-stats">
                    <div class="wcsom-stat">
                        <span class="wcsom-stat-val"><?php echo $prods; ?></span>
                        <span class="wcsom-stat-label">Products</span>
                    </div>
                    <div class="wcsom-stat">
                        <span class="wcsom-stat-val"><?php echo $active_po; ?></span>
                        <span class="wcsom-stat-label">Active POs</span>
                    </div>
                    <div class="wcsom-stat">
                        <span class="wcsom-stat-val"><?php echo $total_po; ?></span>
                        <span class="wcsom-stat-label">Total POs</span>
                    </div>
                </div>
                
                <?php if ($is_main_admin): ?>
                <div class="wcsom-supplier-staff-toggle" style="padding: 10px 16px; border-top: 1px solid #f1f5f9; background: #f8fafc; display:flex; justify-content:space-between; align-items:center;" onclick="event.stopPropagation();">
                    <span style="font-size:12px; font-weight:600; color:#475569;">Visible to Staff</span>
                    <label class="wcsom-switch">
                        <input type="checkbox" class="wcsom-visibility-toggle" data-id="<?php echo esc_attr($sid); ?>" <?php checked($is_visible, '1'); ?>>
                        <span class="wcsom-slider round"></span>
                    </label>
                </div>
                <?php endif; ?>

                <div class="wcsom-supplier-card-footer">
                    <span><?php echo $can_edit ? 'Manage Profile &rarr;' : 'View Profile &rarr;'; ?></span>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if($visible_count === 0): ?>
            <div class="wcsom-empty-state wcsom-card" style="grid-column: 1 / -1;">
                <div class="wcsom-empty-icon"><span class="dashicons dashicons-store"></span></div>
                <h3>No Suppliers Available</h3>
                <p>There are no suppliers available for your access level.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- DETAIL VIEW (Hidden by default) -->
<div id="wcsom-supplier-detail-view" style="display:none;">
    
    <div class="wcsom-flex-between wcsom-mb-4">
        <button id="wcsom-btn-back-grid" class="wcsom-btn wcsom-btn-outline">&larr; Back to Suppliers Grid</button>
        <?php if ($can_edit): ?>
        <button id="wcsom-trigger-create-po" class="wcsom-btn wcsom-btn-primary">
            <span class="dashicons dashicons-plus-alt2" style="margin-top:2px;"></span> Build Purchase Order
        </button>
        <?php endif; ?>
    </div>

    <div class="wcsom-grid">
        <div class="wcsom-main-content" style="flex:2;">
            <div id="wcsom-supplier-data" class="wcsom-card">
                <div class="wcsom-flex-between wcsom-mb-4" style="border-bottom: 1px solid var(--wcsom-border); padding-bottom: 16px;">
                    <div>
                        <h3 class="wcsom-card-title" id="wcsom-detail-title">Assigned Products</h3>
                        <p class="wcsom-card-desc">Manage the supplier's base price here, unassign, or select items to create a PO.</p>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <label style="font-size:12px; font-weight:600; color:#64748b; white-space:nowrap;">Sort by:</label>
                        <select id="wcsom-sort-supplier-products" class="wcsom-input" style="width:190px; padding:6px 10px; height:auto;">
                            <option value="date_desc">Date Assigned (Newest)</option>
                            <option value="name_asc">Name (A-Z)</option>
                            <option value="sku_asc">SKU (A-Z)</option>
                        </select>
                    </div>
                </div>
                
                <?php if ($can_edit): ?>
                <div class="wcsom-search-wrapper wcsom-mb-4" style="background:#f8fafc; padding:16px; border-radius:8px; border:1px solid #e2e8f0;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Assign New Products from WooCommerce Database</label>
                    <div style="position:relative;">
                        <span class="dashicons dashicons-search wcsom-search-icon"></span>
                        <input type="text" id="wcsom-search-assign-input" class="wcsom-input wcsom-input-with-icon" placeholder="Search by Product Name or SKU to assign...">
                        <ul id="wcsom-search-assign-results" class="wcsom-autocomplete"></ul>
                    </div>
                </div>
                <?php endif; ?>

                <div class="wcsom-table-responsive" style="margin-top:0;">
                    <table class="wcsom-table">
                        <thead>
                            <tr>
                                <th class="wcsom-th-check" style="width:40px;">
                                    <?php if ($can_edit): ?><input type="checkbox" id="wcsom-select-all"><?php endif; ?>
                                </th>
                                <th style="width: 60px;">Image</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>WC Reg. Price</th>
                                <th>Supplier Price & Model</th>
                                <th style="width: 100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="wcsom-supplier-products-body">
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="wcsom-sidebar" style="flex:1;">
            <div class="wcsom-card" style="background: #f8fafc; margin-bottom: 20px;">
                <h3 class="wcsom-card-title">Supplier Info</h3>
                <div id="wcsom-detail-sidebar-info" style="margin-top: 16px;">
                    <!-- Filled by JS -->
                </div>
            </div>
            
            <div class="wcsom-card" style="background: #f8fafc;">
                <h3 class="wcsom-card-title">Associated Packing Lists</h3>
                <p class="wcsom-card-desc" style="margin-bottom: 12px; font-size:12px;">Synced from your internal packing list system.</p>
                <div id="wcsom-detail-packing-lists">
                    <!-- Filled by JS -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for PO Creation -->
<?php if ($can_edit): ?>
<div id="wcsom-po-modal" class="wcsom-modal" style="display:none;">
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

<!-- Modal for Supplier Creation -->
<div id="wcsom-create-supplier-modal" class="wcsom-modal" style="display:none;">
    <div class="wcsom-modal-overlay"></div>
    <div class="wcsom-modal-content" style="width: 500px;">
        <div class="wcsom-modal-header">
            <h3>Create a Supplier</h3>
            <button class="wcsom-modal-close" id="wcsom-close-create-supplier"><span class="dashicons dashicons-no-alt"></span></button>
        </div>
        <div class="wcsom-modal-body" style="padding-bottom: 30px;">
            <p class="wcsom-card-desc wcsom-mb-4">Fill out the details below. This fully integrates with WooCommerce and your packing list plugin's data fields.</p>
            
            <div class="wcsom-mb-4">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Company Name <span style="color:#ef4444;">*</span></label>
                <input type="text" id="wcsom-cs-company" class="wcsom-input" placeholder="e.g. Global Tech Supplies" required>
            </div>
            
            <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Supplier Code</label>
                    <input type="text" id="wcsom-cs-code" class="wcsom-input" placeholder="e.g. GTS-001">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Mobile Number</label>
                    <input type="text" id="wcsom-cs-phone" class="wcsom-input" placeholder="Phone / Mobile">
                </div>
            </div>

            <div class="wcsom-mb-4">
                <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Email Address</label>
                <input type="email" id="wcsom-cs-email" class="wcsom-input" placeholder="supplier@example.com">
            </div>
            
            <div class="wcsom-mb-4" style="display:flex; gap: 20px;">
                <div style="flex: 2;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">Address</label>
                    <input type="text" id="wcsom-cs-address" class="wcsom-input" placeholder="Street Address">
                </div>
                <div style="flex: 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#475569;">City</label>
                    <input type="text" id="wcsom-cs-city" class="wcsom-input" placeholder="City">
                </div>
            </div>
            
            <div style="margin-top: 15px; padding: 12px; background: #f8fafc; border-radius: 6px; border: 1px solid #e2e8f0; display:flex; align-items:center; gap: 10px;">
                <input type="checkbox" id="wcsom-cs-invite" value="1" style="margin:0;">
                <label for="wcsom-cs-invite" style="font-size:13px; font-weight:600; color:#475569; cursor:pointer;">
                    Send Invitation Email (Requires Email Address)
                </label>
            </div>
        </div>
        <div class="wcsom-modal-footer">
            <button class="wcsom-btn wcsom-btn-outline" id="wcsom-cancel-create-supplier">Cancel</button>
            <button class="wcsom-btn wcsom-btn-primary" id="wcsom-confirm-create-supplier">Create Supplier</button>
        </div>
    </div>
</div>

<?php endif; ?>
