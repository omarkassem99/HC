jQuery(document).ready(function ($) {
    // Check if we are on the Supplier Requests page
    if ($('#supplier-requests-container').length) {
        let currentPage = 1;
        let currentRequestType = 'price'; // Default tab is "Price Update"

        /**
         * Load requests via AJAX.
         * @param {number} page - The current page number.
         */
        function loadRequests(page) {
            currentPage = page;
            const perPage = $('#requests-per-page').val();
            const status = $('#request-status-filter').val();

            // AJAX request to fetch supplier requests
            $.ajax({
                url: supplierRequestsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'fetch_supplier_requests',
                    security: supplierRequestsData.nonce,
                    page: page,
                    per_page: perPage,
                    status: status,
                    request_type: currentRequestType, // Pass the current tab type
                },
                beforeSend: function () {
                    $('#requests-table-body').html('<tr><td colspan="9">Loading...</td></tr>');
                },
                success: function (response) {
                    if (response.success) {
                        $('#requests-table-body').html(response.data.rows);
                        $('#supplier-pagination-container').html(response.data.pagination);
                    } else {
                        $('#requests-table-body').html('<tr><td colspan="9">' + response.data.message + '</td></tr>');
                    }
                },
                error: function () {
                    $('#requests-table-body').html('<tr><td colspan="9">An error occurred while loading the data.</td></tr>');
                },
            });
        }

        /**
         * Update table columns visibility based on the selected tab.
         */
        function updateTableColumns() {
            if (currentRequestType === 'delist') {
                // Hide "Current Price" and "New Price" columns for "Delisting"
                $('.current-price-column, .new-price-column').hide();
            } else {
                // Show all columns for "Price Update"
                $('.current-price-column, .new-price-column').show();
            }
        }

        /**
         * Handle tab switching.
         */
        $('.supplier-tabs-modern .tab-modern').on('click', function () {
            $('.supplier-tabs-modern .tab-modern').removeClass('active');
            $(this).addClass('active');
            currentRequestType = $(this).data('type'); // Update request type based on the tab
            updateTableColumns(); // Adjust columns visibility
            loadRequests(1); // Load the first page of the selected tab
        });

        /**
         * Handle pagination click.
         */
        $(document).on('click', '.pagination-link-modern', function (e) {
            e.preventDefault();
            const page = $(this).data('page');
            loadRequests(page);
        });

        /**
         * Handle filter and per-page dropdown changes.
         */
        $('#request-status-filter, #requests-per-page').on('change', function () {
            loadRequests(1); // Reload data starting from page 1
        });

        /**
         * Handle cancel request button click.
         */
        $(document).on('click', '.cancel-request-button', function () {
            const requestId = $(this).data('request-id');
            const requestType = $(this).data('type');

            if (!confirm('Are you sure you want to cancel this request?')) {
                return;
            }

            $.ajax({
                url: supplierRequestsData.ajax_url,
                type: 'POST',
                data: {
                    action: 'cancel_supplier_request',
                    security: supplierRequestsData.nonce,
                    request_id: requestId,
                    type: requestType, // Include the request type
                },
                success: function (response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        loadRequests(currentPage); // Reload the current page
                    } else {
                        showToast(response.data.message || 'Failed to cancel the request.', 'error');
                    }
                },
                error: function () {
                    showToast('An error occurred while canceling the request.', 'error');
                },
            });
        });

        // Initial setup and load
        updateTableColumns(); // Set initial column visibility
        loadRequests(1); // Load initial data
    }
});
