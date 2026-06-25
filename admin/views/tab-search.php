<div class="wcsom-panel">
    <h3>Global Product Search</h3>
    <p>Search for any product to see which supplier it's assigned to and quickly add it to an order.</p>
    
    <div class="wcsom-search-bar">
        <input type="text" id="wcsom-global-search-input" class="wcsom-input" placeholder="Search by Product Name or SKU..." style="max-width: 400px; display:inline-block;">
        <button id="wcsom-btn-global-search" class="button button-primary">Search</button>
    </div>

    <table class="wp-list-table widefat fixed striped wcsom-table mt-4">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Assigned Supplier</th>
                <th style="width:150px;">Action</th>
            </tr>
        </thead>
        <tbody id="wcsom-global-search-results">
            <tr>
                <td colspan="4">Enter a search term to find products.</td>
            </tr>
        </tbody>
    </table>
</div>
