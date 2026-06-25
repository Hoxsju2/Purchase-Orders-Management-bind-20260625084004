jQuery(document).ready(function($) {

    let currentSupplierId = null;

    // Initialize SelectWoo for a modern searchable dropdown
    if($.fn.selectWoo) {
        $('#wcsom-supplier-select').selectWoo({
            placeholder: 'Search by code, name, email or phone...',
            allowClear: true,
            width: '100%'
        });
    }

    // 1. Load Supplier Products
    $('#wcsom-supplier-select').on('change', function() {
        let supplierId = $(this).val();
        currentSupplierId = supplierId;

        if (supplierId) {
            $('#wcsom-supplier-placeholder').hide();
            $('#wcsom-supplier-data').fadeIn(200);
            $('#wcsom-assign-product-box').fadeIn(200);
            loadSupplierProducts(supplierId);
        } else {
            $('#wcsom-supplier-placeholder').show();
            $('#wcsom-supplier-data').hide();
            $('#wcsom-assign-product-box').hide();
        }
    });

    function loadSupplierProducts(supplierId) {
        $('#wcsom-supplier-products-body').html('<tr><td colspan="5" style="text-align:center; padding: 40px;"><span class="dashicons dashicons-update dashicons-spin" style="color:#9ca3af;"></span> Loading products...</td></tr>');
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
                        html += `<li data-id="${item.id}"><strong>${item.name}</strong></li>`;
                    });
                    if (html === '') html = '<li style="color:#6b7280;">No unassigned products found</li>';
                    $('#wcsom-search-assign-results').html(html);
                }
            });
        }, 500);
    });

    // Assign product on click
    $(document).on('click', '#wcsom-search-assign-results li[data-id]', function() {
        let productId = $(this).data('id');
        if (!currentSupplierId) return;

        let originalText = $(this).html();
        $(this).html('<span class="dashicons dashicons-update dashicons-spin"></span> Assigning...');

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
            } else {
                $(this).html(originalText);
            }
        });
    });

    // 3. Create PO Modal Logic
    function bindCheckboxes() {
        $('.wcsom-po-select, #wcsom-select-all').off('change').on('change', function() {
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
                <strong>${name}</strong>
                <div class="qty-wrapper">
                    <span>Qty:</span>
                    <input type="number" class="wcsom-po-qty wcsom-input" value="1" min="1">
                </div>
            </div>`;
        });
        $('#wcsom-po-items-list').html(html);
        $('#wcsom-po-modal').fadeIn(200);
    });

    $('#wcsom-cancel-po, #wcsom-close-po, .wcsom-modal-overlay').on('click', function() {
        $('#wcsom-po-modal').fadeOut(200);
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

        let $btn = $(this);
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Processing...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_create_po',
            nonce: wcsom_ajax.nonce,
            supplier_id: currentSupplierId,
            items: items
        }, function(response) {
            if (response.success) {
                window.location.href = '?page=wcsom-dashboard&tab=orders';
            } else {
                alert('Error creating order.');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // 4. Global Search Logic
    $('#wcsom-btn-global-search').on('click', function() {
        let keyword = $('#wcsom-global-search-input').val();
        if (keyword.length < 2) return;

        let $btn = $(this);
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('Searching...');
        $('#wcsom-global-search-results').html('<tr><td colspan="4" class="wcsom-empty-cell"><span class="dashicons dashicons-update dashicons-spin"></span> Loading...</td></tr>');
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_global_product_search',
            nonce: wcsom_ajax.nonce,
            keyword: keyword
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                $('#wcsom-global-search-results').html(response.data);
            }
        });
    });

    $('#wcsom-global-search-input').on('keypress', function(e) {
        if(e.which == 13) {
            $('#wcsom-btn-global-search').trigger('click');
        }
    });

    $(document).on('click', '.action-add-to-po', function() {
        alert("This will open the Add to existing/new PO modal in the next iteration.");
    });
});
