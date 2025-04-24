document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector('#skip-order-requests-container tbody');
    const paginationControls = document.getElementById('pagination-controls');
    const modal = document.getElementById('request-modal');
    const overlay = document.getElementById('modal-overlay');
    const driverReason = document.getElementById('driver-reason');
    const adminReply = document.getElementById('admin-reply');
    const acceptBtn = document.getElementById('accept-btn');
    const rejectBtn = document.getElementById('reject-btn');
    let currentRequestId = null;

    // Filter requests by status
    const observer = new MutationObserver(function (mutations, observer) {
        const statusFilter = document.getElementById('status-filter');
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                const status = this.value;
                fetchRequests(1, status);
            });
            observer.disconnect();  // Stop observing once found
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });

    // Fetch requests dynamically based on tab selection
    function fetchRequests(page = 1, status = null) {
        const formData = new FormData();
        formData.append('action', 'fetch_skip_order_requests');
        formData.append('security', skipOrderRequestsData.nonce);
        formData.append('page', page);
        if (status) formData.append('status', status);

        fetch(skipOrderRequestsData.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    tableBody.innerHTML = data.data.html; // Replace rows
                    paginationControls.innerHTML = data.data.pagination; // Replace pagination

                    // Re-attach event listeners to new "View" buttons
                    document.querySelectorAll('.view-btn').forEach(button => {
                        button.addEventListener('click', openModal);
                    });

                    attachPaginationListeners();
                } else {
                    showToast(data.data.message || 'An error occurred.', 'error', 5000);
                }
            })
            .catch(error => console.error('Error fetching requests:', error));
    }

    function attachPaginationListeners() {
        paginationControls.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', e => {
                e.preventDefault();
                const page = e.target.dataset.page;
                fetchRequests(page, null);
            });
        });
    }

    // Open modal and populate details
    function openModal(event) {
        currentRequestId = event.target.dataset.id;
        driverReason.textContent = event.target.dataset.driverReason || 'No reason provided.';
        adminReply.value = event.target.dataset.adminReply || '';

        const status = event.target.dataset.status;

        // Toggle modal actions based on request status
        if (status !== 'Pending') {
            adminReply.disabled = true;
            acceptBtn.style.display = 'none';
            rejectBtn.style.display = 'none';
        } else {
            adminReply.disabled = false;
            acceptBtn.style.display = 'inline-block';
            rejectBtn.style.display = 'inline-block';
        }

        overlay.style.display = 'block';
        modal.style.display = 'block';
    }

    // Close modal
    function closeModalHandler() {
        modal.style.display = 'none';
        overlay.style.display = 'none';
        driverReason.textContent = '';
        adminReply.value = '';
    }

    // Process a skip order request (accept/reject)
    function processRequest(status) {
        const formData = new FormData();
        formData.append('action', 'process_skip_order_request');
        formData.append('security', skipOrderRequestsData.nonce);
        formData.append('request_id', currentRequestId);
        formData.append('status', status);
        formData.append('admin_reply', adminReply.value);

        fetch(skipOrderRequestsData.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Request processed successfully.', 'success', 5000);
                    fetchRequests(1, null);
                    closeModalHandler();
                } else {
                    showToast(data.data.message || 'An error occurred.', 'error', 5000);
                }
            })
            .catch(error => showToast(error || 'An error occurred.', 'error', 5000));
    }

    // Attach modal event listeners
    const modalObserver = new MutationObserver(function (mutations, observer) {
        const overlay = document.getElementById('modal-overlay');
        const acceptBtn = document.getElementById('accept-btn');
        const rejectBtn = document.getElementById('reject-btn');
        const closeModal = document.getElementById('close-modal');

        if (overlay && acceptBtn && rejectBtn && closeModal) {
            overlay.addEventListener('click', closeModalHandler);
            acceptBtn.addEventListener('click', () => processRequest('Accepted'));
            rejectBtn.addEventListener('click', () => processRequest('Rejected'));
            closeModal.addEventListener('click', closeModalHandler);
            observer.disconnect();  // Stop observing once elements are found
        }
    });

    modalObserver.observe(document.body, { childList: true, subtree: true });


    // Initial fetch for default tab
    fetchRequests(1, null);
});