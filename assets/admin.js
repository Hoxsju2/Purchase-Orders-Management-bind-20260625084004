jQuery(document).ready(function($) {

    let currentSupplierId = null;

    // 1. Load Supplier Products
    $('#wcsom-supplier-select').on('change', function() {
        let supplierId = $(this).val();
        currentSupplierId = supplierId;

        if (supplierId) {
            $('#wcsom-supplier-placeholder').hide();
            $('#wcsom-supplier-data').show();
            $('#wcsom-assign-product-box').show();
            loadSupplierProducts(supplierId);
        } else {
            $('#wcsom-supplier-placeholder').show();
            $('#wcsom-supplier-data').hide();
            $('#wcsom-assign-product-box').hide();
        }
    });

    function loadSupplierProducts(supplierId) {
        $('#wcsom-supplier-products-body').html('<tr><td colspan="5">Loading products...</td></tr>');
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_get_supplier_products',
            nonce: wcsom_ajax.nonce,
            supplier_id: supplierId
        }, function(response) {
            if (response.success) {
                $('#wcsom-supplier-products-body').html(response.data);
                bindCheckboxes();
            }
        });
    }

    // 2. Assign New Product Search
    let searchTimeout;
    $('#wcsom-search-assign-input').on('keyup', function() {
        let keyword = $(this).val();
        clearTimeout(searchTimeout);

        if (keyword.length < 3) {
            $('#wcsom-search-assign-results').empty();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_search_wc_products',
                nonce: wcsom_ajax.nonce,
                keyword: keyword
            }, function(response) {
                if (response.success) {
                    let html = '';
                    response.data.forEach(function(item) {
                        html += `<li data-id="${item.id}">${item.name}</li>`;
                    });
                    if (html === '') html = '<li>No unassigned products found</li>';
                    $('#wcsom-search-assign-results').html(html);
                }
            });
        }, 500);
    });

    // Assign product on click
    $(document).on('click', '#wcsom-search-assign-results li[data-id]', function() {
        let productId = $(this).data('id');
        if (!currentSupplierId) return;

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_assign_product',
            nonce: wcsom_ajax.nonce,
            supplier_id: currentSupplierId,
            product_id: productId
        }, function(response) {
            if (response.success) {
                $('#wcsom-search-assign-input').val('');
                $('#wcsom-search-assign-results').empty();
                loadSupplierProducts(currentSupplierId);
            }
        });
    });

    // 3. Create PO Modal Logic
    function bindCheckboxes() {
        $('.wcsom-po-select, #wcsom-select-all').on('change', function() {
            if ($(this).attr('id') === 'wcsom-select-all') {
                $('.wcsom-po-select').prop('checked', $(this).prop('checked'));
            }
            let checkedCount = $('.wcsom-po-select:checked').length;
            $('#wcsom-trigger-create-po').prop('disabled', checkedCount === 0);
        });
    }

    $('#wcsom-trigger-create-po').on('click', function() {
        let html = '';
        $('.wcsom-po-select:checked').each(function() {
            let id = $(this).val();
            let name = $(this).data('name');
            let price = $(this).data('price') || 0;
            
            html += `
            <div class="wcsom-po-item-row" data-id="${id}" data-price="${price}">
                <span>${name}</span>
                <span>
                    Qty: <input type="number" class="wcsom-po-qty wcsom-input" value="1" min="1">
                </span>
            </div>`;
        });
        $('#wcsom-po-items-list').html(html);
        $('#wcsom-po-modal').show();
    });

    $('#wcsom-cancel-po').on('click', function() {
        $('#wcsom-po-modal').hide();
    });

    $('#wcsom-confirm-po').on('click', function() {
        let items = [];
        $('.wcsom-po-item-row').each(function() {
            items.push({
                id: $(this).data('id'),
                price: $(this).data('price'),
                qty: $(this).find('.wcsom-po-qty').val()
            });
        });

        $(this).prop('disabled', true).text('Creating...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_create_po',
            nonce: wcsom_ajax.nonce,
            supplier_id: currentSupplierId,
            items: items
        }, function(response) {
            if (response.success) {
                alert('Purchase order successfully created!');
                window.location.href = '?page=wcsom-dashboard&tab=orders';
            } else {
                alert('Error creating order.');
                $('#wcsom-confirm-po').prop('disabled', false).text('Confirm & Create PO');
            }
        });
    });

    // 4. Global Search Logic
    $('#wcsom-btn-global-search').on('click', function() {
        let keyword = $('#wcsom-global-search-input').val();
        if (keyword.length < 2) return;

        $('#wcsom-global-search-results').html('<tr><td colspan="4">Searching...</td></tr>');
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_global_product_search',
            nonce: wcsom_ajax.nonce,
            keyword: keyword
        }, function(response) {
            if (response.success) {
                $('#wcsom-global-search-results').html(response.data);
            }
        });
    });

    $(document).on('click', '.action-add-to-po', function() {
        alert("This will open the Add to existing/new PO modal in the next iteration.");
    });
});
