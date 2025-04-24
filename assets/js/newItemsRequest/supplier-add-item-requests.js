jQuery(document).ready(function ($) {
    $('#submit-request-btn').on('click', function () {
        const formData = {
            action: 'create_supplier_item_request',
            nonce: supplierItemRequestsData.nonce,
            item_description: $('#item_description').val(),
            category_id: $('#category_id').val(),
            sub_category_id: $('#sub_category_id').val(),
            country: $('#country').val(),
            uom_id: $('#uom_id').val(),
            packing: $('#packing').val(),
            brand: $('#brand_id').val(),
            supplier_id: supplierItemRequestsData.current_user_id,
            supplier_notes:$('#supplier_notes').val(),
        };
        $.post(supplierItemRequestsData.ajax_url, formData, function (response) {
            if (response.success) {
                showToast(response.data.message, 'success', 3000);
                $('#add-item-request-form')[0].reset(); // Reset form on success
            } else {
                showToast(response.data.message, 'error', 3000);
            }
        });
    });
});

jQuery(document).ready(function ($) {
    $('#category_id').on('change', function () {
        const categoryId = $(this).val();
        const subCategoryDropdown = $('#sub_category_id');

        // Clear existing subcategories
        subCategoryDropdown.html('<option value=""><?php esc_html_e("Select Subcategory", "bidfood"); ?></option>');

        if (categoryId) {
            // Show loading indicator
            subCategoryDropdown.append('<option value="loading">Loading...</option>');

            // Make AJAX request to fetch subcategories
            $.ajax({
                url: supplierItemRequestsData.ajax_url,
                method: 'POST',
                data: {
                    action: 'fetch_subcategories',
                    category_id: categoryId,
                    nonce: supplierItemRequestsData.nonce,
                },
                success: function (response) {
                    subCategoryDropdown.empty(); // Clear loading indicator
                    if (response.success) {
                        // Populate subcategories
                        response.data.subcategories.forEach(function (subcategory) {
                            subCategoryDropdown.append(
                                `<option value="${subcategory.id}">${subcategory.name}</option>`
                            );
                        });
                    } else {
                        subCategoryDropdown.append(
                            `<option value="null">No subcategories found</option>`
                        );
                        alert(response.data.message || 'Failed to load subcategories.');
                    }
                },
                error: function () {
                    alert('Error fetching subcategories.');
                },
            });
        }
    });

});