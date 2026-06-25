<div class="wcsom-card">
    <div class="wcsom-card-header">
        <h3 class="wcsom-card-title">Global Product Search</h3>
        <p class="wcsom-card-desc">Search for any product to see which supplier it's assigned to and quickly add it to an order.</p>
    </div>
    
    <div class="wcsom-search-bar wcsom-mb-4">
        <div class="wcsom-search-wrapper" style="max-width: 500px; display: flex; gap: 10px;">
            <div style="position:relative; flex-grow: 1;">
                <span class="dashicons dashicons-search wcsom-search-icon"></span>
                <input type="text" id="wcsom-global-search-input" class="wcsom-input wcsom-input-with-icon" placeholder="Search by Product Name or SKU...">
            </div>
            <button id="wcsom-btn-global-search" class="wcsom-btn wcsom-btn-primary">Search</button>
        </div>
    </div>

    <div class="wcsom-table-responsive">
        <table class="wcsom-table">
            <thead>
                <tr>
                    <th>Product Details</th>
                    <th>SKU</th>
                    <th>Assigned Supplier</th>
                    <th style="width:150px;">Action</th>
                </tr>
            </thead>
            <tbody id="wcsom-global-search-results">
                <tr>
                    <td colspan="4" class="wcsom-empty-cell">Enter a search term above to find products.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
