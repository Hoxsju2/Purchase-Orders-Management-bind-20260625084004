jQuery(document).ready(function($) {

    let currentSupplierId = null;

    if($.fn.selectWoo) {
        // Initialize dropdowns in Quick Create tab
        $('#wcsom-qc-category, #wcsom-qc-supplier').selectWoo({
            width: '100%'
        });
        
        // Initialize dropdown for modal if exists
        if ($('#wcsom-new-po-supplier-select').length) {
            $('#wcsom-new-po-supplier-select').selectWoo({ width: '100%' });
        }
    }

    // --- Automatic Supplier Navigation (from Orders tab modal) ---
    const urlParams = new URLSearchParams(window.location.search);
    const openSupplier = urlParams.get('open_supplier');
    if (openSupplier) {
        setTimeout(function() {
            let supplierCard = $('.wcsom-supplier-card[data-id="' + openSupplier + '"]');
            if (supplierCard.length) {
                supplierCard.click();
                // Remove parameter from URL to clean it up
                window.history.replaceState({}, document.title, window.location.pathname + "?page=wcsom-dashboard&tab=suppliers");
            }
        }, 500);
    }

    // --- Orders Tab: Create PO Modal ---
    $(document).on('click', '#wcsom-btn-new-po-from-dir', function(e) {
        e.preventDefault();
        if($.fn.selectWoo) $('#wcsom-new-po-supplier-select').val('').trigger('change');
        $('#wcsom-new-po-supplier-modal').fadeIn(200);
    });

    $(document).on('click', '#wcsom-close-new-po-supplier, #wcsom-new-po-supplier-modal .wcsom-modal-overlay', function(e) {
        e.preventDefault();
        $('#wcsom-new-po-supplier-modal').fadeOut(200);
    });

    $(document).on('click', '#wcsom-confirm-new-po-supplier', function(e) {
        e.preventDefault();
        let sid = $('#wcsom-new-po-supplier-select').val();
        if (!sid) { 
            alert('Please select a supplier first.'); 
            return; 
        }
        window.location.href = wcsom_ajax.url_suppliers + '&open_supplier=' + sid;
    });
    
    // --- Create Supplier Modal Logic ---
    $(document).on('click', '#wcsom-btn-create-supplier', function(e) {
        e.preventDefault();
        $('#wcsom-create-supplier-modal').fadeIn(200);
    });

    $(document).on('click', '#wcsom-close-create-supplier, #wcsom-cancel-create-supplier, #wcsom-create-supplier-modal .wcsom-modal-overlay', function(e) {
        e.preventDefault();
        $('#wcsom-create-supplier-modal').fadeOut(200);
    });

    $(document).on('click', '#wcsom-confirm-create-supplier', function(e) {
        e.preventDefault();
        let company_name = $('#wcsom-cs-company').val().trim();
        let supplier_code = $('#wcsom-cs-code').val().trim();
        let email = $('#wcsom-cs-email').val().trim();
        let phone = $('#wcsom-cs-phone').val().trim();
        let address = $('#wcsom-cs-address').val().trim();
        let city = $('#wcsom-cs-city').val().trim();
        let send_invite = $('#wcsom-cs-invite').is(':checked') ? '1' : '0';

        if (!company_name) {
            alert('Company Name is required to create a supplier.');
            return;
        }
        
        if (send_invite === '1' && !email) {
            alert('An Email Address is required to send an invitation.');
            return;
        }

        let $btn = $(this);
        let ogText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Creating...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_create_supplier',
            nonce: wcsom_ajax.nonce,
            company_name: company_name,
            supplier_code: supplier_code,
            email: email,
            phone: phone,
            address: address,
            city: city,
            send_invite: send_invite
        }, function(response) {
            if (response.success) {
                // Reload to refresh the grid
                window.location.reload();
            } else {
                alert('Error: ' + response.data);
                $btn.prop('disabled', false).html(ogText);
            }
        });
    });

    // --- Suppliers Grid & Detail Logic ---
    
    $('#wcsom-filter-suppliers').on('keyup', function() {
        let val = $(this).val().toLowerCase();
        $('.wcsom-supplier-card').each(function() {
            let searchData = $(this).data('search');
            if(searchData.includes(val)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('.wcsom-supplier-card').on('click', function() {
        let sid = $(this).data('id');
        let name = $(this).data('name');
        let code = $(this).data('code');
        
        currentSupplierId = sid;

        let displayCode = code ? `<span style="font-size:12px; font-weight:700; color:#64748b; background:#f1f5f9; padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:8px;">[${code}]</span>` : '';
        $('#wcsom-detail-sidebar-info').html(`
            ${displayCode}
            <div style="font-size:16px; font-weight:700; color:#0f172a; margin-bottom:12px;">${name}</div>
            <p style="font-size:13px; color:#64748b;">You are currently managing products and purchase orders specifically for this supplier.</p>
        `);

        $('#wcsom-detail-title').text('Assigned Products: ' + name);

        $('#wcsom-suppliers-grid-view').hide();
        $('#wcsom-supplier-detail-view').fadeIn(300);
        
        loadSupplierProducts(sid);
        loadSupplierPackingLists(sid);
    });

    $('#wcsom-btn-back-grid').on('click', function() {
        currentSupplierId = null;
        $('#wcsom-supplier-detail-view').hide();
        $('#wcsom-suppliers-grid-view').fadeIn(300);
        $('#wcsom-search-assign-input').val('');
        $('#wcsom-search-assign-results').empty();
    });

    function loadSupplierProducts(supplierId) {
        $('#wcsom-supplier-products-body').html('<tr><td colspan="7" style="text-align:center; padding: 40px;"><span class="dashicons dashicons-update dashicons-spin" style="color:#9ca3af;"></span> Loading products...</td></tr>');
        let orderby = $('#wcsom-sort-supplier-products').val() || 'date_desc';

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_get_supplier_products',
            nonce: wcsom_ajax.nonce,
            supplier_id: supplierId,
            orderby: orderby
        }, function(response) {
            if (response.success) {
                $('#wcsom-supplier-products-body').html(response.data);
                bindCheckboxes();
            }
        });
    }

    $('#wcsom-sort-supplier-products').on('change', function() {
        if (currentSupplierId) {
            loadSupplierProducts(currentSupplierId);
        }
    });

    function loadSupplierPackingLists(supplierId) {
        $('#wcsom-detail-packing-lists').html('<span class="dashicons dashicons-update dashicons-spin" style="color:#9ca3af;"></span> Fetching records...');
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_get_supplier_packing_lists',
            nonce: wcsom_ajax.nonce,
            supplier_id: supplierId
        }, function(response) {
            if (response.success) {
                $('#wcsom-detail-packing-lists').html(response.data);
            }
        });
    }
    
    $(document).on('click', '.wcsom-save-supp-price-btn', function() {
        let btn = $(this);
        let pid = btn.data('id');
        let price = btn.siblings('div').find('.wcsom-inline-supp-price').val();
        let model = btn.siblings('.wcsom-inline-supp-model').val();
        
        let ogText = btn.text();
        btn.prop('disabled', true).text('...');
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_update_supplier_price_inline',
            nonce: wcsom_ajax.nonce,
            product_id: pid,
            supplier_price: price,
            supplier_model: model
        }, function(res) {
            btn.prop('disabled', false).text(ogText);
            if(res.success) {
                btn.css({'background':'#16a34a', 'color':'#fff', 'border-color':'#16a34a'}).text('Saved!');
                let cb = btn.closest('tr').find('.wcsom-po-select');
                cb.data('orig-price', price).data('price', price).data('model', model);
                setTimeout(() => btn.css({'background':'', 'color':'', 'border-color':''}).text('Save Details'), 2000);
            } else {
                alert('Error saving details.');
            }
        });
    });

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
                    if (html === '') html = '<li style="color:#6b7280;">No products found</li>';
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
                // Ensure default sort shows newest assigned at top
                $('#wcsom-sort-supplier-products').val('date_desc');
                loadSupplierProducts(currentSupplierId);
            } else {
                $(this).html(originalText);
            }
        });
    });

    $(document).on('click', '.wcsom-unassign-btn', function() {
        if(!confirm("Are you sure you want to unassign this product from the supplier?")) return;
        
        let btn = $(this);
        let pid = btn.data('id');
        let originalText = btn.text();
        
        btn.prop('disabled', true).text('Removing...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_unassign_product',
            nonce: wcsom_ajax.nonce,
            product_id: pid
        }, function(res) {
            if (res.success) {
                $('#wcsom-row-' + pid).fadeOut(300, function(){ $(this).remove(); bindCheckboxes(); });
            } else {
                alert("Error unassigning product.");
                btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // --- PO Builder Logic ---
    function bindCheckboxes() {
        $('.wcsom-po-select, #wcsom-select-all').off('change').on('change', function() {
            if ($(this).attr('id') === 'wcsom-select-all') {
                $('.wcsom-po-select').prop('checked', $(this).prop('checked'));
            }
            // Logic to disable button has been deliberately removed so it can always be clicked.
        });
    }

    $('#wcsom-trigger-create-po').on('click', function() {
        let html = '';
        let checkedCount = $('.wcsom-po-select:checked').length;

        if (checkedCount === 0) {
            html = `<div style="padding:16px; background:#f8fafc; border-radius:8px; color:#64748b; font-size:13px; border:2px dashed #cbd5e1; text-align:center; font-weight:500;">
                        No products selected. An empty purchase order will be generated. <br>You can easily add assigned products or global products inside the PO editor.
                    </div>`;
        } else {
            $('.wcsom-po-select:checked').each(function() {
                let id = $(this).val();
                let name = $(this).data('name');
                let origPrice = parseFloat($(this).data('orig-price')) || 0;
                let model = $(this).data('model') || '';
                
                let modelHtml = model ? `<div style="font-size:11px; color:#64748b; margin-top:2px;">Model: ${model}</div>` : '';

                // We purposefully leave data-price empty here so it gets populated as an empty value
                html += `
                <div class="wcsom-po-item-row" data-id="${id}" data-price="" data-orig-price="${origPrice}" data-model="${model}">
                    <div>
                        <strong>${name}</strong>
                        ${modelHtml}
                    </div>
                    <div class="qty-wrapper">
                        <span>Qty:</span>
                        <input type="number" class="wcsom-po-qty wcsom-input" value="1" min="1" style="width:70px; padding:6px;">
                    </div>
                </div>`;
            });
        }

        $('#wcsom-po-items-list').html(html);
        $('#wcsom-po-ref').val('');
        $('#wcsom-po-modal').fadeIn(200);
    });

    // Close logic specifically for PO modal inside Suppliers Directory
    $(document).on('click', '#wcsom-cancel-po, #wcsom-close-po, #wcsom-po-modal .wcsom-modal-overlay', function(e) {
        e.preventDefault();
        $('#wcsom-po-modal').fadeOut(200);
    });

    $(document).on('click', '#wcsom-confirm-po', function(e) {
        e.preventDefault();
        let items = [];
        $('.wcsom-po-item-row').each(function() {
            items.push({
                id: $(this).data('id'),
                price: $(this).data('price') || 0, // Fallback to 0 if left entirely blank
                orig_price: $(this).data('orig-price'),
                supplier_model: $(this).data('model'),
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
                window.location.href = wcsom_ajax.url_orders;
            } else {
                alert('Error creating order.');
                $btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // --- Single / Bulk Delete POs ---
    $(document).on('click', '.wcsom-delete-po-btn', function(e) {
        e.preventDefault();
        if(!confirm("Are you sure you want to delete this purchase order? This action cannot be undone.")) return;
        
        let btn = $(this);
        let id = btn.data('id');
        let originalText = btn.html();
        
        btn.prop('disabled', true).text('Deleting...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_delete_po',
            nonce: wcsom_ajax.nonce,
            po_id: id
        }, function(res) {
            if (res.success) {
                btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
            } else {
                alert("Error deleting Purchase Order.");
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('#wcsom-btn-delete-po-edit').on('click', function(e) {
        e.preventDefault();
        if(!confirm("Are you sure you want to permanently delete this purchase order?")) return;
        
        let btn = $(this);
        let id = btn.data('po');
        btn.prop('disabled', true).text('Deleting...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_delete_po',
            nonce: wcsom_ajax.nonce,
            po_id: id
        }, function(res) {
            if (res.success) {
                window.location.href = wcsom_ajax.url_orders;
            } else {
                alert("Error deleting Purchase Order.");
                btn.prop('disabled', false).text('Delete Purchase Order');
            }
        });
    });


    // --- Global Search Filtering & Reassignment ---
    function triggerGlobalSearch() {
        let keyword = $('#wcsom-global-search-input').val();
        let category = $('#wcsom-filter-category').val();
        let assignment = $('#wcsom-filter-assignment').val();

        let $btn = $('#wcsom-btn-global-search');
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('Filtering...');
        $('#wcsom-global-search-results').html('<tr><td colspan="6" class="wcsom-empty-cell"><span class="dashicons dashicons-update dashicons-spin"></span> Searching products...</td></tr>');
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_global_product_search',
            nonce: wcsom_ajax.nonce,
            keyword: keyword,
            category: category,
            assignment: assignment
        }, function(response) {
            $btn.prop('disabled', false).html(originalText);
            if (response.success) {
                $('#wcsom-global-search-results').html(response.data);
            }
        });
    }

    $('#wcsom-btn-global-search').on('click', function() { triggerGlobalSearch(); });
    $('#wcsom-global-search-input').on('keypress', function(e) { if(e.which == 13) triggerGlobalSearch(); });
    $('#wcsom-filter-category, #wcsom-filter-assignment').on('change', function() { triggerGlobalSearch(); });
    if ($('#wcsom-global-search-results').length) triggerGlobalSearch();

    $(document).on('click', '.wcsom-unassign-global-btn', function() {
        if(!confirm("Unassign this product? You can assign it to someone else afterwards.")) return;
        let btn = $(this);
        let pid = btn.data('id');
        btn.prop('disabled', true).text('Processing...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_unassign_product',
            nonce: wcsom_ajax.nonce,
            product_id: pid
        }, function(res) {
            if (res.success) {
                triggerGlobalSearch();
            } else {
                alert("Error unassigning product.");
                btn.prop('disabled', false).text('Unassign');
            }
        });
    });

    $(document).on('click', '.wcsom-quick-assign-btn', function() {
        let btn = $(this);
        let pid = btn.data('id');
        let dropdown = btn.siblings('.wcsom-quick-assign-sel');
        let sid = dropdown.val();

        if(!sid) {
            alert("Please select a supplier from the dropdown first.");
            return;
        }
        btn.prop('disabled', true).text('...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_assign_product',
            nonce: wcsom_ajax.nonce,
            supplier_id: sid,
            product_id: pid
        }, function(res) {
            if (res.success) {
                triggerGlobalSearch();
            } else {
                alert("Error assigning product.");
                btn.prop('disabled', false).text('Assign');
            }
        });
    });

    // --- Quick Create Product Logic ---
    let product_image_frame;
    $('#wcsom-qc-btn-image').on('click', function(e) {
        e.preventDefault();
        if (product_image_frame) {
            product_image_frame.open();
            return;
        }
        product_image_frame = wp.media({
            title: 'Select Product Image',
            button: { text: 'Use this image' },
            multiple: false
        });
        product_image_frame.on('select', function() {
            let attachment = product_image_frame.state().get('selection').first().toJSON();
            $('#wcsom-qc-image-id').val(attachment.id);
            $('#wcsom-qc-img-preview').html(`<img src="${attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url}" style="width:100%; height:100%; object-fit:cover;">`);
            $('#wcsom-qc-btn-remove-image').show();
        });
        product_image_frame.open();
    });

    $('#wcsom-qc-btn-remove-image').on('click', function() {
        $('#wcsom-qc-image-id').val('');
        $('#wcsom-qc-img-preview').html('<span class="dashicons dashicons-format-image" style="color:#94a3b8; font-size:24px; width:24px; height:24px;"></span>');
        $(this).hide();
    });

    $('#wcsom-quick-create-form').on('submit', function(e) {
        e.preventDefault();
        let $btn = $('#wcsom-qc-submit');
        let originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin"></span> Creating...');

        let data = {
            action: 'wcsom_quick_create_product',
            nonce: wcsom_ajax.nonce,
            title: $('#wcsom-qc-title').val(),
            sku: $('#wcsom-qc-sku').val(),
            price: $('#wcsom-qc-price').val(),
            model_number: $('#wcsom-qc-model').val(),
            price_type: $('#wcsom-qc-price-type').val(),
            category_id: $('#wcsom-qc-category').val(),
            supplier_id: $('#wcsom-qc-supplier').val(),
            image_id: $('#wcsom-qc-image-id').val()
        };

        $.post(wcsom_ajax.ajax_url, data, function(res) {
            $btn.prop('disabled', false).html(originalText);
            if (res.success) {
                alert('Product created successfully!');
                $('#wcsom-quick-create-form')[0].reset();
                if($.fn.selectWoo) {
                    $('#wcsom-qc-category').val('').trigger('change');
                    $('#wcsom-qc-supplier').val('').trigger('change');
                }
                $('#wcsom-qc-btn-remove-image').trigger('click');
            } else {
                alert('Error: ' + res.data);
            }
        });
    });

    // Bulk Order Actions
    $(document).on('change', '.wcsom-bulk-select, #wcsom-bulk-select-all', function() {
        if ($(this).attr('id') === 'wcsom-bulk-select-all') {
            $('.wcsom-bulk-select').prop('checked', $(this).prop('checked'));
        }
        let count = $('.wcsom-bulk-select:checked').length;
        $('#wcsom-bulk-count-display').text(count + ' selected');
    });

    $('#wcsom-bulk-action-dropdown').on('change', function() {
        if ($(this).val() === 'add_tags') {
            $('#wcsom-bulk-tags-input').show();
        } else {
            $('#wcsom-bulk-tags-input').hide();
        }
    });

    $('#wcsom-btn-apply-bulk').on('click', function() {
        let ids = [];
        $('.wcsom-bulk-select:checked').each(function() { ids.push($(this).val()); });
        if (ids.length === 0) { alert('Please select at least one order.'); return; }

        let action = $('#wcsom-bulk-action-dropdown').val();

        if (action === 'combine') {
            let d = new Date();
            let defaultName = "Bulk Order " + d.getFullYear() + "-" + (d.getMonth() + 1).toString().padStart(2, '0') + "-" + d.getDate().toString().padStart(2, '0');
            let title = prompt("Enter a title/reference for this Combined Bulk Order:", defaultName);

            if (title) {
                let $btn = $(this);
                let og = $btn.html();
                $btn.prop('disabled', true).text('Processing...');

                $.post(wcsom_ajax.ajax_url, {
                    action: 'wcsom_create_bulk_order',
                    nonce: wcsom_ajax.nonce,
                    po_ids: ids,
                    title: title
                }, function(res) {
                    if (res.success) window.location.href = wcsom_ajax.url_bulk;
                    else { alert('Error creating bulk order.'); $btn.prop('disabled', false).html(og); }
                });
            }
        } else if (action === 'add_tags') {
            let tags = $('#wcsom-bulk-tags-input').val().trim();
            if (!tags) { alert('Please enter tags to apply.'); return; }

            let $btn = $(this);
            let og = $btn.html();
            $btn.prop('disabled', true).text('Processing...');

            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_bulk_add_tags',
                nonce: wcsom_ajax.nonce,
                po_ids: ids,
                tags: tags
            }, function(res) {
                if (res.success) window.location.reload();
                else { alert('Error applying tags.'); $btn.prop('disabled', false).html(og); }
            });
        } else if (action === 'delete') {
            if (!confirm("Are you sure you want to entirely delete the selected purchase orders? This cannot be undone.")) return;

            let $btn = $(this);
            let og = $btn.html();
            $btn.prop('disabled', true).text('Deleting...');

            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_bulk_delete_pos',
                nonce: wcsom_ajax.nonce,
                po_ids: ids
            }, function(res) {
                if (res.success) window.location.reload();
                else { alert('Error deleting orders.'); $btn.prop('disabled', false).html(og); }
            });
        } else {
            alert('Please select a valid bulk action.');
        }
    });
    
    $('.wcsom-delete-bulk').on('click', function() {
        if(confirm("Are you sure you want to delete this bulk view? (The individual POs will not be deleted)")) {
            let id = $(this).data('id');
            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_delete_bulk_order',
                nonce: wcsom_ajax.nonce,
                bulk_id: id
            }, function(res) {
                if (res.success) $('#wcsom-bulk-row-' + id).fadeOut(300, function() { $(this).remove(); });
            });
        }
    });

    // --- Staff Management & Visibility Logic ---
    $('.wcsom-visibility-toggle').on('change', function(e) {
        let isChecked = $(this).is(':checked') ? '1' : '0';
        let sid = $(this).data('id');
        let $el = $(this);
        
        $el.prop('disabled', true);
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_toggle_supplier_visibility',
            nonce: wcsom_ajax.nonce,
            supplier_id: sid,
            status: isChecked
        }, function(res) {
            $el.prop('disabled', false);
            if (!res.success) {
                alert('Error updating visibility.');
                $el.prop('checked', !isChecked);
            }
        });
    });

    $('.wcsom-staff-role-select').on('change', function() {
        let level = $(this).val();
        let uid = $(this).data('id');
        let $el = $(this);
        
        $el.prop('disabled', true);
        
        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_update_staff_access',
            nonce: wcsom_ajax.nonce,
            user_id: uid,
            level: level
        }, function(res) {
            $el.prop('disabled', false);
            if(res.success) {
                $el.css('background-color', '#d1fae5');
                setTimeout(() => $el.css('background-color', ''), 1000);
            } else {
                alert('Error updating role.');
            }
        });
    });

    $('#wcsom-btn-add-staff').on('click', function() {
        let email = $('#wcsom-new-staff-email').val();
        if(!email) return;

        let $btn = $(this);
        let og = $btn.html();
        $btn.prop('disabled', true).text('Adding...');

        $.post(wcsom_ajax.ajax_url, {
            action: 'wcsom_add_staff',
            nonce: wcsom_ajax.nonce,
            email: email
        }, function(res) {
            if (res.success) window.location.reload();
            else { alert(res.data); $btn.prop('disabled', false).html(og); }
        });
    });

    $('.wcsom-remove-staff').on('click', function() {
        if(confirm("Remove this user's dashboard access?")) {
            let id = $(this).data('id');
            $.post(wcsom_ajax.ajax_url, {
                action: 'wcsom_remove_staff',
                nonce: wcsom_ajax.nonce,
                user_id: id
            }, function(res) {
                if (res.success) $('#wcsom-staff-' + id).fadeOut(300, function() { $(this).remove(); });
            });
        }
    });

    // Edit PO Logic
    if ($('#wcsom-edit-po-table').length) {
        
        // Show/Hide FOB port input on Radio toggle
        $(document).on('change', 'input[name="wcsom_incoterm"]', function() {
            if($(this).val() === 'FOB') {
                $('#wcsom-fob-port-wrapper').css('display', 'flex');
            } else {
                $('#wcsom-fob-port-wrapper').hide();
                $('#wcsom-fob-port-input').val('');
            }
        });

        function recalculateTotals() {
            let total = 0;
            $('.wcsom-edit-row').each(function() {
                let poPrice = parseFloat($(this).find('.wcsom-edit-price').val()) || 0;
                let qty = parseInt($(this).find('.wcsom-edit-qty').val()) || 0;
                let origPrice = parseFloat($(this).find('.wcsom-orig-price').val()) || 0;
                
                let sub = poPrice * qty;
                total += sub;
                $(this).find('.wcsom-row-subtotal strong').text('$' + sub.toFixed(2));
                
                let diffEl = $(this).find('.wcsom-price-diff');
                if (origPrice > 0) {
                    let diff = ((poPrice - origPrice) / origPrice) * 100;
                    if (diff > 0) diffEl.html('<span style="color:#ef4444; font-weight:700;">+' + diff.toFixed(1) + '%</span>');
                    else if (diff < 0) diffEl.html('<span style="color:#16a34a; font-weight:700;">' + diff.toFixed(1) + '%</span>');
                    else diffEl.html('<span style="color:#64748b; font-weight:500;">0%</span>');
                } else {
                    diffEl.html('<span style="color:#94a3b8; font-size:11px;">N/A</span>');
                }
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
            
            $('.wcsom-pay-status').each(function() {
                if($(this).val() === 'paid') $(this).css({'color': '#16a34a', 'font-weight': 'bold', 'background-color': '#f0fdf4'});
                else $(this).css({'color': '', 'font-weight': '', 'background-color': ''});
            });
        }

        $(document).on('input', '.wcsom-edit-price, .wcsom-edit-qty', recalculateTotals);
        $(document).on('input', '.wcsom-pay-percent', recalculatePayments);
        $(document).on('change', '.wcsom-pay-status', function() {
            if($(this).val() === 'paid') $(this).css({'color': '#16a34a', 'font-weight': 'bold', 'background-color': '#f0fdf4'});
            else $(this).css({'color': '', 'font-weight': '', 'background-color': ''});
        });
        
        $(document).on('click', '.wcsom-remove-row', function() {
            $(this).closest('tr').remove();
            recalculateTotals();
        });

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
        
        $(document).on('click', '.wcsom-remove-payment', function() { $(this).closest('tr').remove(); });

        let file_frame;
        $('#wcsom-btn-add-attachment').on('click', function(e) {
            e.preventDefault();
            if (file_frame) { file_frame.open(); return; }
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
        
        $(document).on('click', '.wcsom-remove-attachment', function() { $(this).closest('.wcsom-attachment-item').remove(); });

        function injectRow(id, name, sku, price, origPrice, model, is_added_by_supp = false) {
            if ($(`.wcsom-edit-row[data-id="${id}"]`).length > 0) {
                alert('Product is already in the order.');
                return;
            }
            
            let nameStyle = is_added_by_supp ? 'color: #dc2626;' : '';
            let suppNote = is_added_by_supp ? '<span style="font-size: 11px; color:#dc2626; display:block;">(Added by Supplier)</span>' : '';
            let addedFlag = is_added_by_supp ? '1' : '0';
            let modelHtml = model ? `<br><span style="font-size: 11px; color:#64748b;">Model: ${model}</span>` : '';

            // Ensure the value field is totally empty instead of 0 for new additions
            let tr = `
            <tr class="wcsom-edit-row" data-id="${id}" data-added="${addedFlag}" data-model="${model}">
                <td>
                    <strong style="${nameStyle}">${name}</strong>
                    ${modelHtml}
                    ${suppNote}
                </td>
                <td style="color:#64748b; font-size:13px;">${sku}</td>
                <td>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:4px; background:#f8fafc; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;">
                        <span class="wcsom-orig-price-display" style="font-weight:600; color:#475569; font-size:13px;">$${parseFloat(origPrice).toFixed(2)}</span>
                        <input type="hidden" class="wcsom-orig-price" value="${origPrice}">
                        <button type="button" title="Sync PO Price to Supplier Profile" class="wcsom-sync-price-btn" style="background:none; border:none; cursor:pointer; color:#4f46e5; padding:0; display:flex; align-items:center;"><span class="dashicons dashicons-update" style="font-size:14px; width:14px; height:14px;"></span></button>
                    </div>
                </td>
                <td><input type="number" step="0.01" class="wcsom-input wcsom-edit-price" value="" placeholder="$${parseFloat(origPrice).toFixed(2)}"></td>
                <td class="wcsom-price-diff" style="text-align:center; font-size:13px;">--</td>
                <td><input type="number" min="0" class="wcsom-input wcsom-edit-qty" value="1"></td>
                <td style="text-align:right;" class="wcsom-row-subtotal"><strong>$0.00</strong></td>
                <td><button class="wcsom-btn wcsom-btn-outline wcsom-remove-row" style="color:#ef4444; border-color:#fca5a5; padding:6px 10px; border-radius:6px;">&times;</button></td>
            </tr>`;
            
            $('#wcsom-edit-po-table tbody').append(tr);
            recalculateTotals();
        }

        $('#wcsom-btn-add-assigned').on('click', function() {
            let sel = $('#wcsom-add-assigned-product');
            let id = sel.val();
            if(!id) return;
            
            let opt = sel.find('option:selected');
            let name = opt.text();
            let price = parseFloat(opt.data('price')) || 0;
            let origPrice = parseFloat(opt.data('orig-price')) || 0;
            let sku = opt.data('sku') || 'N/A';
            let model = opt.data('model') || '';
            
            injectRow(id, name, sku, price, origPrice, model, false); 
            sel.val('');
        });

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
                injectRow(prod.id, prod.name, prod.sku ? prod.sku : 'N/A', prod.price || 0, prod.orig_price || 0, prod.model || '', false);
                $(this).val(null).trigger('change');
            });
        }
        
        $(document).on('click', '.wcsom-sync-price-btn', function() {
            let row = $(this).closest('tr');
            let pid = row.data('id');
            let poPrice = row.find('.wcsom-edit-price').val();
            let poModel = row.data('model') || '';
            
            if(confirm("Update the Original Supplier Base Price for this product to $" + poPrice + "? This will be saved in their profile.")) {
                let btn = $(this);
                btn.css('opacity', '0.5');
                
                $.post(wcsom_ajax.ajax_url, {
                    action: 'wcsom_update_supplier_price_inline',
                    nonce: wcsom_ajax.nonce,
                    product_id: pid,
                    supplier_price: poPrice,
                    supplier_model: poModel
                }, function(res) {
                    btn.css('opacity', '1');
                    if (res.success) {
                        row.find('.wcsom-orig-price').val(poPrice);
                        row.find('.wcsom-orig-price-display').text('$' + parseFloat(poPrice).toFixed(2));
                        recalculateTotals();
                        row.find('.wcsom-orig-price-display').css('color', '#16a34a');
                        setTimeout(() => row.find('.wcsom-orig-price-display').css('color', '#475569'), 1000);
                    } else {
                        alert('Error updating price.');
                    }
                });
            }
        });

        $('#wcsom-btn-save-po').on('click', function() {
            let po_id = $(this).data('po');
            let order_ref = $('#wcsom-edit-ref').val();
            let status = $('#wcsom-edit-status').val();
            let notes = $('#wcsom-edit-notes').val();
            let tags = $('#wcsom-edit-tags').val(); 
            let incoterm = $('input[name="wcsom_incoterm"]:checked').val() || $('input[name="wcsom_incoterm"]').val() || 'EXW';
            let fob_port = $('#wcsom-fob-port-input').val() || '';
            
            let items = [];
            $('.wcsom-edit-row').each(function() {
                items.push({
                    id: $(this).data('id'),
                    price: $(this).find('.wcsom-edit-price').val(),
                    orig_price: $(this).find('.wcsom-orig-price').val(),
                    supplier_model: $(this).data('model') || '',
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
                incoterm: incoterm,
                fob_port: fob_port,
                tags: tags,
                items: items,
                payments: payments,
                attachments: attachments
            }, function(response) {
                if(response.success) alert('Order updated successfully!');
                else alert('Error saving order.');
                $btn.prop('disabled', false).html(og);
            });
        });
        
        recalculateTotals();
    }
});
