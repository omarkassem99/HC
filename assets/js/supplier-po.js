jQuery(document).ready(function ($) {
    // Attach updateItemStatus to the window object to make it globally accessible
    // Update all items statuses
    window.updateAllItemsStatus = async function(poId, status) {
        const rows = document.querySelectorAll('#po-item-table tbody tr');
        let is_error = false;

        // If action is reject all, validate notes first
        if (status === 'reject') {
            for (let row of rows) {
                console.log(row);
                const orderId = row.querySelector('td:nth-child(1)').textContent.trim();
                const itemId = row.querySelector('td:nth-child(2)').textContent.trim();
                const supplierNotes = row.querySelector(`textarea[name="supplier_notes_${orderId}_${itemId}"]`)?.value?.trim();
                const itemStatus = row.querySelector(`input[name="item_status_${orderId}_${itemId}"]`)?.value;

                // Skip already confirmed or rejected items
                if (itemStatus !== 'pending supplier') {
                    continue;
                }

                if (!supplierNotes) {
                    showToast('Please fill in all supplier notes before rejecting.', 'error', 5000);
                    return;
                }
            }
        }

        // Process all items
        for (let row of rows) {
            const orderId = row.querySelector('td:nth-child(1)').textContent.trim();
            const itemId = row.querySelector('td:nth-child(2)').textContent.trim();
            const supplierNotes = row.querySelector(`textarea[name="supplier_notes_${orderId}_${itemId}"]`)?.value?.trim();
            const itemStatus = row.querySelector(`input[name="item_status_${orderId}_${itemId}"]`)?.value;
            
            // Skip already confirmed or rejected items
            if (itemStatus !== 'pending supplier') {
                continue;
            }

            try {
                console.log(`Processing item ${itemId} with notes: ${supplierNotes}`);
                const response = await updateItemStatus(itemId, orderId, poId, status, false);
                
                if (!response) {
                    is_error = true;
                    console.error(`Failed to update item ${itemId}`);
                }
            } catch (error) {
                is_error = true;
                console.error(`Error updating item ${itemId}:`, error);
            }
        }

        // Show appropriate message based on result
        if (!is_error) {
            showToast('All items have been updated successfully.', 'success', 5000);
        } else {
            showToast('There was an error updating some items. Please try again.', 'error', 5000);
        }

        // Refresh the items display
        fetchUpdatedItems(poId);
    };

    // Update single item status (referenced by updateAllItemsStatus)
    window.updateItemStatus = function(itemId, orderId, poId, status, show_message = true) {
        return new Promise(function(resolve, reject) {
            const supplierNotes = document.querySelector(`textarea[name="supplier_notes_${orderId}_${itemId}"]`)?.value?.trim();
            const supplierDeliveryDate = document.querySelector(`input[name="supplier_delivery_date_${orderId}_${itemId}"]`)?.value;

            console.log(`Updating item ${itemId} (Order: ${orderId}):`, {
                notes: supplierNotes,
                date: supplierDeliveryDate,
                status: status
            });

            jQuery.ajax({
                url: supplierPoData.ajax_url,
                method: 'POST',
                data: {
                    action: 'update_po_item_status',
                    security: supplierPoData.nonce,
                    item_id: itemId,
                    order_id: orderId,
                    supplier_notes: supplierNotes,
                    status: status,
                    supplier_delivery_date: supplierDeliveryDate
                },
                success: function(response) {
                    if (show_message) {
                        if (response.success) {
                            showToast(response.data.message, 'success', 5000);
                            // Update UI elements
                            // Disable the textarea
                            const textarea = document.querySelector(`textarea[name="supplier_notes_${orderId}_${itemId}"]`);
                            if (textarea) textarea.readOnly = true;
                            
                            // Disable the date input
                            const dateInput = document.querySelector(`input[name="supplier_delivery_date_${orderId}_${itemId}"]`);
                            if (dateInput) dateInput.readOnly = true;

                            // Change the status input value
                            const statusInput = document.querySelector(`input[name="item_status_${orderId}_${itemId}"]`);
                            if (statusInput) statusInput.value = status;
                            
                            // Remove action buttons
                            const actionButtons = document.querySelectorAll(`button[onclick*="updateItemStatus('${itemId}"]`);
                            actionButtons.forEach(button => button.remove());
                        } else {
                            showToast(response.data.message, 'error', 5000);
                        }
                    }
                    resolve(response.success);
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    if (show_message) {
                        showToast('An error occurred while updating the item.', 'error', 5000);
                    }
                    reject(error);
                }
            });
        });
    };

    window.updateSingleItemStatus = async function(itemId, orderId, poId, status) {
        await updateItemStatus(itemId, orderId, poId, status);

        // Fetch updated item data after submission
        fetchUpdatedItems(poId);
    };

    // Fetch updated items after submission
    function fetchUpdatedItems(poId) {
        $.ajax({
            url: supplierPoData.ajax_url,
            method: 'POST',
            data: {
                action: 'fetch_updated_po_items', // We need to handle this AJAX action in PHP
                po_id: poId,
                security: supplierPoData.nonce
            },
            success: function (response) {
                if (response.success) {
                    console.log(response.data);
                    // Update the HTML content of the table with the new data
                    $('#po-item-table tbody').html(response.data.html);  // This updates the table's body with new data
                    // Check if all items are confirmed then show the submit button
                    if (response.data.all_items_confirmed) {
                        console.log('All items are confirmed');
                        $('#submit-po-btn').show();
                        $('#confirm-all-btn').hide();
                        $('#reject-all-btn').hide();
                        $('#global-date-picker').hide();
                        $('#apply-global-date-btn').hide();
                    } else {
                        console.log('Not all items are confirmed');
                        $('#submit-po-btn').hide();
                    }
                }
            }
        });
    }
    // Submit PO event
    $('#submit-po-btn').on('click', function () {
        var poId = $('#po-item-form').data('po-id');

        $.ajax({
            url: supplierPoData.ajax_url,
            method: 'POST',
            data: {
                action: 'submit_supplier_po',
                security: supplierPoData.nonce,
                po_id: poId
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success', 5000);
                    // Fetch updated item data after submission
                    $('#submit-po-btn').hide();
                } else {
                    showToast(response.data.message, 'error', 5000);
                }
            }
        });
    });
    // Apply global date to all items
    window.applyGlobalDateToItems = function () {
        const globalDatePicker = document.getElementById('global-date-picker');
        const selectedDate = globalDatePicker.value;

        // Validate if date is selected
        if (!selectedDate) {
            showToast('Please select a date first', 'error', 5000);
            return;
        }

        // Get minimum allowed date from the global date picker
        const minDate = globalDatePicker.getAttribute('min');

        // Validate selected date against minimum date
        if (selectedDate < minDate) {
            showToast(`Selected date must be on or after ${minDate}`, 'error', 5000);
            return;
        }

        // Find all date input fields in the PO items table
        const dateInputs = document.querySelectorAll('#po-item-table tbody tr td:nth-child(8) input[type="date"]');

        let updatedCount = 0;
        dateInputs.forEach(input => {
            // Only update if the input is not readonly (item is still editable)
            if (!input.hasAttribute('readonly')) {
                input.value = selectedDate;
                updatedCount++;
            }
        });

        if (updatedCount > 0) {
            showToast(`Delivery date set for ${updatedCount} item(s)`, 'success', 5000);
        } else {
            showToast('No editable items found to update', 'warning', 5000);
        }
    };

    // Add event listener for the global date picker to validate date selection
    document.getElementById('global-date-picker')?.addEventListener('change', function () {
        const minDate = this.getAttribute('min');
        const selectedDate = this.value;

        if (selectedDate < minDate) {
            this.value = minDate;
            showToast(`Selected date must be on or after ${minDate}`, 'error', 5000);
        }
    });
    $(document).on('click', '.user-po-pagination-container .page-number, .user-po-pagination-container .arrow', function (e) {
        e.preventDefault();
        // Check if the clicked element is disabled
        if ($(this).hasClass('disabled')) {
            return;
        }
        // Get the page number from the clicked link
        const page = $(this).data('page');
        // Send AJAX request to fetch the paginated data
        $.post(supplierPoData.ajax_url, {
            action: 'fetch_supplier_pos',
            page: page,
            security: supplierPoData.nonce
        }, function (response) {
            if (response.success) {
                // Update the table with the new data
                $('.my_account_orders').html(response.data.html);
                // Update the pagination HTML
                $('.user-po-pagination-container').html(response.data.pagination);
                // Highlight the current page dynamically
                $('.user-po-pagination-container .page-number').removeClass('current');
                $(`.user-po-pagination-container .page-number[data-page="${page}"]`).addClass('current');
            } else {
                showToast(response.data.message, 'error', 5000);
            }
        });
    });
});