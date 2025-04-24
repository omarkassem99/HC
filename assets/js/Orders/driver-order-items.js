jQuery(document).ready(function ($) {
    $('#update-driver-order-form').on('submit', function (e) {
        e.preventDefault(); // Prevent the default form submission

        var formData = $(this).serializeArray();
        var data = {
            action: 'update_driver_order',
            nonce: bidfoodDriverOrderItemsData.update_item_nonce,
        };

        $.each(formData, function (index, field) {
            data[field.name] = field.value;
        });

        console.log('AJAX Data:', data);

        $.ajax({
            url: bidfoodDriverOrderItemsData.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                console.log('Response:', response);
                if (response.success) {
                    showToast(response.data.message || 'Operation successful', 'success');
                    location.href = response.data.redirect_url;
                } else {
                    showToast(response.data.message || 'An error occurred', 'error');
                }
            },

            error: function (xhr, status, error) {
                console.error('Error:', error);
                showToast('An error occurred while processing your request', 'error');
            }
        });
    });
});