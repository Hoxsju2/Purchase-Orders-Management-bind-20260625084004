jQuery(document).ready(function($) {

    let currentSupplierId = null;

    if($.fn.selectWoo) {
        $('#wcsom-supplier-select').selectWoo({
            placeholder: 'Search by code, name, email or phone...',
            allowClear: true,
            width: '100%'
        });
    }

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
        $('#wcsom-po-ref').val('');
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

        let orderRef = $('#wcsom-po-ref').val();
        let $btn = $(this);
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Processing...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_create_po',
            nonce: wcsom_ajax.nonce,
            supplier_id: currentSupplierId,
            order_ref: orderRef,
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

    // 5. Edit PO Logic
    if ($('#wcsom-edit-po-table').length) {
        function recalculateTotals() {
            let total = 0;
            $('.wcsom-edit-row').each(function() {
                let price = parseFloat($(this).find('.wcsom-edit-price').val()) || 0;
                let qty = parseInt($(this).find('.wcsom-edit-qty').val()) || 0;
                let sub = price * qty;
                total += sub;
                $(this).find('.wcsom-row-subtotal strong').text('$' + sub.toFixed(2));
            });
            $('#wcsom-grand-total').text('$' + total.toFixed(2)).data('total', total);
            recalculatePayments();
        }
        
        function recalculatePayments() {
            let grandTotal = parseFloat($('#wcsom-grand-total').data('total')) || 0;
            $('.wcsom-pay-row').each(function() {
                let pct = parseFloat($(this).find('.wcsom-pay-percent').val()) || 0;
                let amt = (pct / 100) * grandTotal;
                $(this).find('.wcsom-pay-amt-display').text('$' + amt.toFixed(2));
                $(this).find('.wcsom-pay-amount').val(amt.toFixed(2));
            });
            
            // Adjust status colors dynamically
            $('.wcsom-pay-status').each(function() {
                if($(this).val() === 'paid') {
                    $(this).css({'color': '#16a34a', 'font-weight': 'bold', 'background-color': '#f0fdf4'});
                } else {
                    $(this).css({'color': '', 'font-weight': '', 'background-color': ''});
                }
            }).trigger('change');
        }

        $(document).on('input', '.wcsom-edit-price, .wcsom-edit-qty', recalculateTotals);
        $(document).on('input', '.wcsom-pay-percent', recalculatePayments);
        $(document).on('change', '.wcsom-pay-status', function() {
            if($(this).val() === 'paid') {
                $(this).css({'color': '#16a34a', 'font-weight': 'bold', 'background-color': '#f0fdf4'});
            } else {
                $(this).css({'color': '', 'font-weight': '', 'background-color': ''});
            }
        });
        
        $(document).on('click', '.wcsom-remove-row', function() {
            $(this).closest('tr').remove();
            recalculateTotals();
        });

        // Add Payment Row
        $('#wcsom-btn-add-payment').on('click', function() {
            let tr = `
            <tr class="wcsom-pay-row">
                <td><input type="text" class="wcsom-input wcsom-pay-title" placeholder="e.g., 20% Deposit"></td>
                <td><input type="number" step="0.01" min="0" max="100" class="wcsom-input wcsom-pay-percent" value="20"></td>
                <td style="text-align:right; font-weight:600;">
                    <span class="wcsom-pay-amt-display">$0.00</span>
                    <input type="hidden" class="wcsom-pay-amount" value="0">
                </td>
                <td>
                    <select class="wcsom-input wcsom-pay-status">
                        <option value="unpaid">Unpaid</option>
                        <option value="paid">Paid</option>
                        <option value="awaiting_production">Awaiting Production</option>
                        <option value="awaiting_delivery">Awaiting Delivery</option>
                    </select>
                </td>
                <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-payment" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
            </tr>`;
            $('#wcsom-payments-table tbody').append(tr);
            recalculatePayments();
        });
        
        $(document).on('click', '.wcsom-remove-payment', function() {
            $(this).closest('tr').remove();
        });

        // Add Media Attachment
        let file_frame;
        $('#wcsom-btn-add-attachment').on('click', function(e) {
            e.preventDefault();
            if (file_frame) {
                file_frame.open();
                return;
            }
            file_frame = wp.media({
                title: 'Select a File to Attach',
                button: { text: 'Attach File' },
                multiple: false
            });
            file_frame.on('select', function() {
                let attachment = file_frame.state().get('selection').first().toJSON();
                let today = new Date().toISOString().slice(0, 10);
                
                let item = `
                <div class="wcsom-attachment-item" style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; border:1px solid #e2e8f0; border-radius:8px; background:#f8fafc;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span class="dashicons dashicons-media-document" style="color:#64748b; font-size:24px; width:24px; height:24px;"></span>
                        <div>
                            <a href="${attachment.url}" target="_blank" style="font-weight:600; text-decoration:none; color:#4f46e5; font-size:14px;">${attachment.filename}</a>
                            <div style="font-size:12px; color:#64748b; margin-top:2px;">Type: ${attachment.mime} | By: Admin | ${today}</div>
                        </div>
                    </div>
                    <button class="wcsom-btn wcsom-btn-outline wcsom-remove-attachment" style="padding:4px 8px; font-size:12px; color:#ef4444; border-color:#fca5a5;">Remove</button>
                    <input type="hidden" class="wcsom-att-data" data-url="${attachment.url}" data-name="${attachment.filename}" data-type="${attachment.mime}" data-by="Admin" data-date="${today}">
                </div>`;
                $('#wcsom-attachments-list').append(item);
            });
            file_frame.open();
        });
        
        $(document).on('click', '.wcsom-remove-attachment', function() {
            $(this).closest('.wcsom-attachment-item').remove();
        });

        // Function to inject row into table
        function injectRow(id, name, sku, price, is_added_by_supp = false) {
            if ($(`.wcsom-edit-row[data-id="${id}"]`).length > 0) {
                alert('Product is already in the order.');
                return;
            }
            
            let nameStyle = is_added_by_supp ? 'color: #dc2626;' : '';
            let suppNote = is_added_by_supp ? '<span style="font-size: 11px; color:#dc2626; display:block;">(Added by Supplier)</span>' : '';
            let addedFlag = is_added_by_supp ? '1' : '0';

            let tr = `
            <tr class="wcsom-edit-row" data-id="${id}" data-added="${addedFlag}">
                <td>
                    <strong style="${nameStyle}">${name}</strong>
                    ${suppNote}
                </td>
                <td style="color:#64748b; font-size:13px;">${sku}</td>
                <td><input type="number" step="0.01" class="wcsom-input wcsom-edit-price" value="${price}"></td>
                <td><input type="number" min="0" class="wcsom-input wcsom-edit-qty" value="1"></td>
                <td style="text-align:right;" class="wcsom-row-subtotal"><strong>$${parseFloat(price).toFixed(2)}</strong></td>
                <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-row" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
            </tr>`;
            
            $('#wcsom-edit-po-table tbody').append(tr);
            recalculateTotals();
        }

        // Add from Assigned Product List
        $('#wcsom-btn-add-assigned').on('click', function() {
            let sel = $('#wcsom-add-assigned-product');
            let id = sel.val();
            if(!id) return;
            
            let opt = sel.find('option:selected');
            let name = opt.text();
            let price = parseFloat(opt.data('price')) || 0;
            let sku = opt.data('sku') || 'N/A';
            
            injectRow(id, name, sku, price, false); 
            sel.val('');
        });

        // Initialize Global Product Search for PO Edit
        if ($.fn.selectWoo) {
            $('#wcsom-add-product-select').selectWoo({
                placeholder: "Search ANY product by name or SKU...",
                allowClear: true,
                ajax: {
                    url: wcsom_ajax.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            action: 'wcsom_search_all_wc_products',
                            nonce: wcsom_ajax.nonce,
                            keyword: params.term
                        };
                    },
                    processResults: function (data) {
                        return { results: data.data };
                    }
                },
                minimumInputLength: 2
            }).on('select2:select', function (e) {
                let prod = e.params.data;
                injectRow(prod.id, prod.name, prod.sku ? prod.sku : 'N/A', prod.price || 0, false);
                $(this).val(null).trigger('change');
            });
        }

        $('#wcsom-btn-save-po').on('click', function() {
            let po_id = $(this).data('po');
            let order_ref = $('#wcsom-edit-ref').val();
            let status = $('#wcsom-edit-status').val();
            let notes = $('#wcsom-edit-notes').val();
            
            let items = [];
            $('.wcsom-edit-row').each(function() {
                items.push({
                    id: $(this).data('id'),
                    price: $(this).find('.wcsom-edit-price').val(),
                    qty: $(this).find('.wcsom-edit-qty').val(),
                    added: $(this).data('added')
                });
            });
            
            let payments = [];
            $('.wcsom-pay-row').each(function() {
                payments.push({
                    title: $(this).find('.wcsom-pay-title').val(),
                    percent: $(this).find('.wcsom-pay-percent').val(),
                    amount: $(this).find('.wcsom-pay-amount').val(),
                    status: $(this).find('.wcsom-pay-status').val()
                });
            });
            
            let attachments = [];
            $('.wcsom-att-data').each(function() {
                attachments.push({
                    url: $(this).data('url'),
                    name: $(this).data('name'),
                    type: $(this).data('type'),
                    uploaded_by: $(this).data('by'),
                    date: $(this).data('date')
                });
            });

            let $btn = $(this);
            let og = $btn.html();
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Saving...');

            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_save_po_edit',
                nonce: wcsom_ajax.nonce,
                po_id: po_id,
                order_ref: order_ref,
                status: status,
                notes: notes,
                items: items,
                payments: payments,
                attachments: attachments
            }, function(response) {
                if(response.success) {
                    alert('Order updated successfully!');
                } else {
                    alert('Error saving order.');
                }
                $btn.prop('disabled', false).html(og);
            });
        });
        
        // Init dynamically colored status on load
        recalculatePayments();
    }
});
