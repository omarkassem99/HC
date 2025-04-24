jQuery(document).ready(function ($) {
    // Show loader spinner
    function showLoader() {
        $('#loader-overlay').show();
    }

    // Hide loader spinner
    function hideLoader() {
        $('#loader-overlay').hide();
    }

    // Override XMLHttpRequest to show/hide loader
    (function () {
        const originalOpen = XMLHttpRequest.prototype.open;
        const originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            return originalOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function (body) {
            this.addEventListener('loadstart', function () {
                showLoader();
            });
            this.addEventListener('loadend', function () {
                hideLoader();
            });

            return originalSend.apply(this, arguments);
        };
    })();

    // Handle items per page change
    $('#items-per-page').on('change', function () {
        const itemsPerPage = $(this).val(); // Get selected items per page value
        const search = $('#search').val(); // Retain search query
        const offset = 0; // Reset offset
        const baseUrl = bidfoodWhOrderItemsData.base_url;
        const orderId = bidfoodWhOrderItemsData.order_id;

        // Construct URL with items_per_page and search query
        const url = `${baseUrl}&order_id=${orderId}&search=${encodeURIComponent(search)}&offset=${offset}&items_per_page=${itemsPerPage}`;
        window.location.href = url;

    });

    $('#convert-to-driver-order-form').on('submit', function (e) {
        e.preventDefault();
        var selectedDriverId = $('#driver_id').val();
        var currentDriverId = $('#current_driver_id').val();
        var whOrderId = $('#wh_order_id').val();
        var wcOrderId = $('#wc_order_id').val();
        var nonce = $('#_wpnonce').val();

        let confirmMessage;

        if (!selectedDriverId) {
            confirmMessage = 'Are you sure you want to remove the current driver?';
        } else if (currentDriverId != selectedDriverId) {
            confirmMessage = 'Are you sure you want to change the driver?';
        } else {
            confirmMessage = 'Are you sure you want to assign this driver?';
        }

        // Show confirmation dialog
        if (!confirm(confirmMessage)) {
            return; // If user cancels, do nothing
        }

        // Create form data
        var data = {
            action: 'convert_to_driver_order',
            wh_order_id: whOrderId,
            wc_order_id: wcOrderId,
            driver_id: selectedDriverId,
            nonce: nonce // Ensure nonce is included in the data
        };

        $.ajax({
            url: bidfoodWhOrderItemsData.ajax_url,
            type: 'POST',
            data: data,
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message || 'Operation successful', 'success');
                    location.reload();
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

    // Handling update buttons for WH items
    $(document).on('click', '.update-wh-item', function (e) {
        e.preventDefault();

        const button = $(this); // The button that was clicked
        const whItemId = button.data('wh-item-id'); // Get the warehouse item ID
        const row = button.closest('tr'); // Find the current row

        // Get values specific to this row
        const confirmedAmount = row.find('.wh_confirmed_amount').val(); // Confirmed amount
        const customerRequestedAmount = row.find('.customer_requested_amount').val(); // Customer requested amount
        const managerNote = row.find('.wh_manager_note').val(); // Manager note

        // Frontend validation: prevent negative numbers
        if (isNaN(confirmedAmount) || confirmedAmount < 0) {
            showToast('Confirmed amount cannot be negative.', 'error', 5000);
            return;
        }
        if (customerRequestedAmount <= confirmedAmount) {
            showToast('Customer Confirmed amount cannot be greater than customer requested amount.', 'error', 5000);
            return;
        }

        // AJAX request to update the specific row's data
        $.ajax({
            url: bidfoodWhOrderItemsData.ajax_url,
            type: 'POST',
            data: {
                action: 'update_wh_order_item',
                nonce: bidfoodWhOrderItemsData.update_item_nonce,
                wh_item_id: whItemId,
                wh_confirmed_amount: confirmedAmount,
                wh_manager_note: managerNote,
            },
            beforeSend: function () {
                button.prop('disabled', true).text('Updating...');
            },
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success', 5000);
                } else {
                    showToast('Error: ' + response.data.message, 'error', 5000);
                }
            },
            error: function () {
                showToast('An unexpected error occurred.', 'error', 5000);
            },
            complete: function () {
                button.prop('disabled', false).text('Update');
            },
        });
    });

    // Handling save WH order note
    $('#save-wh-order-note').on('click', function (e) {
        e.preventDefault();

        const wh_order_id = $('input[name="wh_order_id"]').val(); // Get the WH Order ID
        const wh_order_note = $('#wh_order_note').val(); // Get the WH Order Note

        $.ajax({
            url: bidfoodWhOrderItemsData.ajax_url,
            method: 'POST',
            data: {
                action: 'save_wh_order_note',
                nonce: bidfoodWhOrderItemsData.save_note_nonce,
                wh_order_id: wh_order_id,
                wh_order_note: wh_order_note,
            },
            beforeSend: function () {
                console.log('Saving note...');
            },
            success(response) {
                if (response.success) {
                    showToast(response.data.message, 'success'); // Notify the user
                } else {
                    showToast(response.data.message || 'An error occurred.', 'error');
                }
            },
            error() {
                showToast('Failed to save the note.', 'error');
            },
            complete() {
                console.log('Note saved.');
            },
        });
    });

    // Handling update WH order status
    $('#update-wh-order-status').on('click', function () {
        const whOrderId = $('#wh_order_id').val();
        const whOrderStatus = $('#wh_order_status').val();

        if (whOrderStatus === 'Assigned to Driver') {
            showToast('You cannot manually select the "Assigned to Driver" status.', 'error');
            return;
        }

        $.ajax({
            url: bidfoodWhOrderItemsData.ajax_url,
            type: 'POST',
            data: {
                action: 'update_wh_order_status',
                nonce: bidfoodWhOrderItemsData.update_status_nonce,
                wh_order_id: whOrderId,
                wh_order_status: whOrderStatus,
            },
            beforeSend: function () {},
            success: function (response) {
                if (response.success) {
                    showToast(response.data.message, 'success', 5000);
                    location.reload();
                } else {
                    if (response.data.already_assigned) {
                        showToast(response.data.message, 'warning', 5000);
                    } else {
                        showToast(response.data.message, 'error', 5000);
                    }
                }
            },
            error: function () {
                showToast('An unexpected error occurred.', 'error', 5000);
            },
            complete: function () {},
        });
    });

    // Handling search functionality
    $('#search-btn').click(function (e) {
        e.preventDefault();

        const search = $('#search').val();
        const offset = 0; // Reset offset
        const baseUrl = bidfoodWhOrderItemsData.base_url;
        const orderId = bidfoodWhOrderItemsData.order_id;
        const itemsPerPage = $('#items-per-page').val(); // Get items per page value

        // Construct URL with search, offset and items_per_page
        const url = `${baseUrl}&order_id=${orderId}&search=${encodeURIComponent(search)}&offset=${offset}&items_per_page=${itemsPerPage}`;
        window.location.href = url;
    });

    // Handling pagination links
    $('.pagination-link').click(function (e) {
        e.preventDefault();

        const search = $('#search').val(); // Retain search query
        const offset = $(this).data('offset'); // Pagination offset
        const baseUrl = bidfoodWhOrderItemsData.base_url;
        const orderId = bidfoodWhOrderItemsData.order_id;
        const itemsPerPage = $('#items-per-page').val(); // Get items per page value

        const url = `${baseUrl}&order_id=${orderId}&search=${encodeURIComponent(search)}&offset=${offset}&items_per_page=${itemsPerPage}`;
        window.location.href = url;
    });

    // Show loader on page load if there are pending AJAX requests
    $(document).ajaxStart(function () {
        showLoader();
    }).ajaxStop(function () {
        hideLoader();
    });

    // Disable the "Assigned to Driver" option
    $('#wh_order_status option[value="Assigned to Driver"]').prop('disabled', true);
});