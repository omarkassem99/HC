// Convert the Order from supplier to Warehouse Order
jQuery(document).ready(function ($) {
    $('#convert-draft-wh-order').on('click', function () {
        let selectedCheckboxes = $('input[name="id[]"]:checked');
        let selectedValues = selectedCheckboxes.map(function () {
            return this.value;
        }).get();

        if (selectedValues.length > 0) {
            $.ajax({
                url: bidfoodWhOrdersData.ajax_url,
                type: 'POST',
                data: {
                    action: 'convert_to_draft_wh_order',
                    order_ids: selectedValues,
                    nonce: bidfoodWhOrdersData.nonce,
                },
                beforeSend: function () {
                    showToast('Processing your request...', 'info', 2000);
                },
                success: function (response) {
                    if (response.success) {
                        showToast(response.data, 'success', 5000);
                        location.reload(); // Reload to reflect changes
                    } else {
                        showToast(response.data || 'An error occurred.', 'error', 5000);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', xhr, status, error);
                    showToast('Server error: ' + xhr.responseText, 'error', 5000);
                },
            });
        } else {
            showToast('No orders selected.', 'error', 5000);
        }
    });
});
