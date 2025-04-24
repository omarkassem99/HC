jQuery(document).ready(function($) {
     // Handle the "Mark as Sent to Supplier" button click
     $(document).on('click', '.mark-as-sent-button', function(e) {
        e.preventDefault();
        const poId = $(this).data('po-id'); // Get the PO ID from the button's data attribute
        $.ajax({
            url: supplierPoData.ajax_url,
            method: 'POST',
            data: {
                action: 'mark_supplier_po_sent',
                security: supplierPoData.mark_as_sent_nonce,
                po_id: poId,
            },
            success: function(response) {
                if (response.success) {
                    // Update the status in the UI
                    $('#po-status').text('Status: ' + response.data.new_status);

                    // Optionally, remove the button after successful update
                    $(`.mark-as-sent-button[data-po-id="${poId}"]`).remove();

                    showToast(response.data.message, 'success', 5000); // Display success message
                } else {
                    showToast(response.data.message, 'error', 5000); // Display error message
                }
            },
            error: function() {
                showToast('An error occurred. Please try again.', 'error', 5000);
            }
        });
    });
    window.updateAdminItemStatus = function(itemId, orderId) {
        var status = $('#status_' + orderId + '_' + itemId).val();
        var adminNotes = $('#admin_notes_' + orderId + '_' + itemId).val().trim();

        $.ajax({
            url: ajaxurl,  // Use 'ajaxurl' for admin AJAX calls
            method: 'POST',
            data: {
                action: 'update_admin_item_status',
                item_id: itemId,
                order_id: orderId,
                status: status,
                admin_notes: adminNotes,
                security: supplierPoData.nonce  // Assuming supplierPoData.nonce is localized correctly
            },
            success: function(response) {
                if (response.success) {
                    showToast(response.data.message, 'success', 5000);  // Show success message
                } else {
                    showToast(response.data.message, 'error', 5000);  // Show error message
                }
            }
        });
    }
});
