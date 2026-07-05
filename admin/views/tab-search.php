<?php
// Fetch WooCommerce Categories for Filter
$categories = get_terms(array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
));
$can_edit = wcsom_can_edit();
?>
<div class="wcsom-card">
    <div class="wcsom-card-header">
        <h3 class="wcsom-card-title">Global Product Search</h3>
        <p class="wcsom-card-desc">Filter products by category, status, or search by name and SKU to see assignments. You can quickly assign, unassign, duplicate, or bulk edit products directly from this view.</p>
    </div>
    
    <?php if ($can_edit): ?>
    <div class="wcsom-bulk-actions wcsom-mb-4" style="display:flex; gap:12px; align-items:center; background: #f8fafc; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <select id="wcsom-search-bulk-action" class="wcsom-input" style="width: 200px; font-weight: 500;">
            <option value="">-- Bulk Actions --</option>
            <option value="edit">Bulk Edit Selected</option>
        </select>
        <button id="wcsom-btn-apply-search-bulk" class="wcsom-btn wcsom-btn-primary" style="padding: 10px 20px;">Apply</button>
        <span id="wcsom-search-bulk-count" style="margin-left:auto; font-size:13px; color:#4f46e5; font-weight:700; background: #e0e7ff; padding: 4px 10px; border-radius: 6px;">0 selected</span>
    </div>
    <?php endif; ?>

    <div class="wcsom-search-bar wcsom-mb-4" style="background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        
        <div style="flex: 1; min-width: 200px;">
            <select id="wcsom-filter-category" class="wcsom-input">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex: 1; min-width: 150px;">
            <select id="wcsom-filter-assignment" class="wcsom-input">
                <option value="">All Assignment Statuses</option>
                <option value="assigned">Assigned to Supplier</option>
                <option value="unassigned">Unassigned</option>
            </select>
        </div>

        <div class="wcsom-search-wrapper" style="flex: 2; min-width: 300px; display: flex; gap: 10px;">
            <div style="position:relative; flex-grow: 1;">
                <span class="dashicons dashicons-search wcsom-search-icon"></span>
                <input type="text" id="wcsom-global-search-input" class="wcsom-input wcsom-input-with-icon" placeholder="Search by Product Name, SKU, or Tags...">
            </div>
            <button id="wcsom-btn-global-search" class="wcsom-btn wcsom-btn-primary">Filter</button>
        </div>
    </div>

    <div class="wcsom-table-responsive">
        <table class="wcsom-table">
            <thead>
                <tr>
                    <?php if ($can_edit): ?><th style="width: 40px;"><input type="checkbox" id="wcsom-search-select-all"></th><?php endif; ?>
                    <th>Product Details</th>
                    <th>Category</th>
                    <th>SKU / HS</th>
                    <th>WP Status</th>
                    <th>Assignment Status</th>
                    <th style="width:230px;">Action</th>
                </tr>
            </thead>
            <tbody id="wcsom-global-search-results">
                <tr>
                    <td colspan="<?php echo $can_edit ? '7' : '6'; ?>" class="wcsom-empty-cell">Use the filters above to load products.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>