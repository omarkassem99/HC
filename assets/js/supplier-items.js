jQuery(document).ready(function ($) {
    if ($('#supplier-items-container').length) {
        // Variable to store the current page
        let currentPage = 1;

        // Variable to store the current item ID
        let currentItemId = null;
        let currentProductSKU = null;
        let currentPrice = null;

        // Load items on initial load
        loadItems(1);

        // Handle pagination controls
        $(document).on('click', '.pagination-link', function (e) {
            e.preventDefault();
            const page = $(this).data('page');
            loadItems(page);
        });

        // Handle search and items-per-page change
        $('#search-items, #items-per-page').on('input change', function () {
            loadItems(1);
        });

        // Function to load items via AJAX
        function loadItems(page) {
            currentPage = page;
            const perPage = $('#items-per-page').val() || 10;
            const search = $('#search-items').val();

            $.ajax({
                url: supplierItemsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'fetch_supplier_items',
                    security: supplierItemsData.nonce,
                    page: page,
                    per_page: perPage,
                    search: search,
                },
                beforeSend: function () {
                    $('#items-table-body').html('<tr><td colspan="7">Loading...</td></tr>');
                },
                success: function (response) {
                    if (response.success) {
                        $('#items-table-body').html(response.data.rows);
                        $('#supplier-pagination-container').html(response.data.pagination);
                    } else {
                        $('#items-table-body').html('<tr><td colspan="7">' + response.data.message + '</td></tr>');
                    }
                },
                error: function () {
                    $('#items-table-body').html('<tr><td colspan="7">An error occurred.</td></tr>');
                },
            });
        }

        // Handle Save Button
        $(document).on('click', '.save-item-btn', function () {
            const row = $(this).closest('tr');
            const itemId = row.data('item-id');

            const price = row.find('[data-field="price"]').val();
            const moq = row.find('[data-field="pa_moq"]').val();

            // Validate price and moq as numbers
            if (isNaN(price) || isNaN(moq)) {
                showToast('Price and MOQ must be valid numbers.', 'error', 5000);
                return;
            }

            const data = {
                price: price,
                stock_status: row.find('[data-field="stock_status"]').val(),
                pa_moq: moq,
                pa_uom: row.find('[data-field="pa_uom"]').val(),
            };

            updateItem(itemId, data);
        });

        // Handle Save All Button
        $(document).on('click', '.save-all-btn', function () {
            const rows = $('#items-table-body tr');
            let hasError = false;

            rows.each(function () {
                const row = $(this);
                const itemId = row.data('item-id');

                const price = row.find('[data-field="price"]').val();
                const moq = row.find('[data-field="pa_moq"]').val();

                if (isNaN(price) || isNaN(moq)) {
                    showToast('Price and MOQ must be valid numbers.', 'error', 5000);
                    hasError = true;
                    return false; // Break the loop
                }

                const data = {
                    price: price,
                    stock_status: row.find('[data-field="stock_status"]').val(),
                    pa_moq: moq,
                    pa_uom: row.find('[data-field="pa_uom"]').val(),
                };

                updateItem(itemId, data, true);
            });

            if (!hasError) {
                showToast('All items updated successfully.', 'success', 5000);
            }
        });

        function updateItem(itemId, data, isSilent = false) {
            $.ajax({
                url: supplierItemsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_supplier_item',
                    security: supplierItemsData.nonce,
                    item_id: itemId,
                    field: 'multiple',
                    data: data,
                },
                success: function (response) {
                    if (!isSilent) {
                        if (response.success) {
                            showToast(response.data.message, 'success', 5000);
                        } else {
                            showToast(response.data.message, 'error', 5000);
                        }
                    }
                },
                error: function () {
                    showToast('An error occurred while updating the item.', 'error', 5000);
                },
            });
        }

        // Handle image upload when clicking on the image
        $(document).on('click', '.image-upload-container img', function () {
            const itemId = $(this).closest('tr').data('item-id');

            const fileInput = $('<input type="file" accept="image/*" style="display:none;">');
            $('body').append(fileInput); // Add the file input to the body

            // Trigger file selection dialog
            fileInput.trigger('click');

            // Handle file selection
            fileInput.on('change', function () {
                const file = this.files[0];
                if (!file) {
                    showToast('No file selected.');
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'upload_product_image');
                formData.append('security', supplierItemsData.nonce);
                formData.append('item_id', itemId);
                formData.append('file', file);

                // AJAX request to upload image
                $.ajax({
                    url: supplierItemsData.ajax_url,
                    type: 'POST',
                    processData: false,
                    contentType: false,
                    data: formData,
                    success: function (response) {
                        if (response.success) {
                            loadItems(currentPage); // Reload items
                            showToast(response.data.message, 'success', 5000); // Show success message
                        } else {
                            showToast(response.data.message, 'error', 5000); // Show error if upload failed
                        }
                    },
                    error: function () {
                        showToast('An error occurred while uploading the image.', 'error', 5000);
                    },
                });

                // Remove the file input after it's used
                fileInput.remove();
            });
        });

        // Handle removing the current product image
        $(document).on('click', '.remove-image-btn', function (e) {
            e.preventDefault();

            const itemId = $(this).data('item-id');

            $.ajax({
                url: supplierItemsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'remove_product_image',
                    security: supplierItemsData.nonce,
                    item_id: itemId,
                },
                success: function (response) {
                    if (response.success) {
                        loadItems(currentPage);
                        showToast(response.data.message, 'success', 5000);
                    } else {
                        showToast(response.data.message, 'error', 5000);
                    }
                },
                error: function () {
                    showToast('An error occurred while removing the image.', 'error', 5000);
                },
            });
        });

        // Handle removing a product
        $(document).on('click', '.remove-product-btn', function (e) {
            e.preventDefault();

            const itemId = $(this).data('item-id');
            if (confirm('Are you sure you want to delete this product?')) {
                $.ajax({
                    url: supplierItemsData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'remove_product',
                        security: supplierItemsData.nonce,
                        item_id: itemId,
                    },
                    success: function (response) {
                        if (response.success) {
                            $(`[data-item-id="${itemId}"]`).remove();
                            showToast(response.data.message, 'success', 5000);
                        } else {
                            showToast(response.data.message, 'error', 5000);
                        }
                    },
                    error: function () {
                        showToast('An error occurred while removing the product.', 'error', 5000);
                    },
                });
            }
        });


        // Open Request Modal
        $(document).on('click', '.request-action-btn', function () {
            // clear fields
            $('#request-notes').val('');
            $('#new-price').val('');

            currentItemId = $(this).data('item-id');
            currentProductSKU = $(this).data('product-sku');
            currentPrice = $(this).data('current-price');

            $('#current-price').val(currentPrice);
            $('#request-type').val('price').trigger('change');
            $('#supplier-request-modal').fadeIn();
        });

        // Close Modal
        $('#close-modal').on('click', function () {
            $('#supplier-request-modal').fadeOut();
            $('#request-notes').val('');
            $('#new-price').val('');
        });

        $(document).on('click', function (event) {
            if (
                $(event.target).is('#supplier-request-modal') &&
                !$(event.target).closest('.modal-content').length
            ) {
                $('#supplier-request-modal').fadeOut();
            }
        });    

        // Toggle Fields Based on Request Type
        $('#request-type').on('change', function () {
            const type = $(this).val();
            if (type === 'price') {
                $('#price-fields').show();
            } else {
                $('#price-fields').hide();
            }
        });

        // Submit Request
        $('#submit-request').on('click', function () {
            const requestType = $('#request-type').val();
            const notes = $('#request-notes').val();
            const newPrice = $('#new-price').val();

            const data = {
                action: 'submit_supplier_request',
                security: supplierItemsData.nonce,
                item_id: currentProductSKU,
                request_type: requestType,
                notes: notes,
                new_price: requestType === 'price' ? newPrice : null,
            };

            $.ajax({
                url: supplierItemsData.ajax_url,
                type: 'POST',
                data: data,
                success: function (response) {
                    if (response.success) {
                        showToast(response.data.message, 'success', 5000);
                        $('#supplier-request-modal').fadeOut();
                    } else {
                        showToast(response.data.message, 'error', 5000);
                    }
                },
                error: function () {
                    showToast('An error occurred while submitting the request.', 'error', 5000);
                },
            });
        });
    }
});