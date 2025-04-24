document.addEventListener('DOMContentLoaded', function () {
    const tableBody = document.querySelector('#supplier-add-item-requests-container tbody');
    const paginationControls = document.getElementById('pagination-controls');
    const modal = document.getElementById('request-modal');
    const overlay = document.getElementById('modal-overlay');
    const supplierNotes = document.getElementById('supplier-notes');
    const adminNotes = document.getElementById('admin-notes');
    const approveBtn = document.getElementById('approve-btn');
    const rejectBtn = document.getElementById('reject-btn');
    const closeModal = document.getElementById('close-modal');
    let currentRequestId = null;

    // Filter requests by status
    document.getElementById('status-filter').addEventListener('change', function () {
        const status = this.value;
        fetchRequests(1, status, null);
    });

    // Fetch requests dynamically based on tab selection
    function fetchRequests(page = 1, status = null, supplierId = null) {
        const formData = new FormData();
        formData.append('action', 'fetch_supplier_add_item_requests');
        formData.append('security', supplierAddItemRequestsData.nonce);
        formData.append('page', page);
        if (status) formData.append('status', status);
        if (supplierId) formData.append('supplier_id', supplierId);

        fetch(supplierAddItemRequestsData.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => {
                return response.json();
            })
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
                fetchRequests(page, null, null);
            });
        });
    }

    // Open modal and populate details
    function openModal(event) {
        currentRequestId = event.target.dataset.id;
        supplierNotes.textContent = event.target.dataset.supplierNotes || 'No supplier notes.';
        adminNotes.value = event.target.dataset.adminNotes || '';

        const status = event.target.dataset.status;

        // Toggle modal actions based on request status
        if (status !== 'pending') {
            adminNotes.disabled = true;
            approveBtn.style.display = 'none';
            rejectBtn.style.display = 'none';
        } else {
            adminNotes.disabled = false;
            approveBtn.style.display = 'inline-block';
            rejectBtn.style.display = 'inline-block';
        }

        overlay.style.display = 'block';
        modal.style.display = 'block';
    }

    // Close modal
    function closeModalHandler() {
        modal.style.display = 'none';
        overlay.style.display = 'none';
        supplierNotes.textContent = '';
        adminNotes.value = '';
    }

    // Process a supplier request (approve/reject)
    function processRequest(status) {
        const formData = new FormData();
        formData.append('action', 'process_supplier_add_item_request');
        formData.append('security', supplierAddItemRequestsData.nonce);
        formData.append('request_id', currentRequestId);
        formData.append('status', status);
        formData.append('admin_notes', adminNotes.value);

        fetch(supplierAddItemRequestsData.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showToast('Request processed successfully.', 'success', 5000);
                    fetchRequests(1, null, null);
                    closeModalHandler();
                } else {
                    showToast(data.data.message || 'An error occurred.', 'error', 5000);
                }
            })
            .catch(error => showToast(error || 'An error occurred.', 'error', 5000));
        fetchRequests(1, null, null);
        closeModalHandler();
    }

    // Attach modal event listeners
    overlay.addEventListener('click', closeModalHandler);
    approveBtn.addEventListener('click', () => processRequest('approved'));
    rejectBtn.addEventListener('click', () => processRequest('rejected'));
    closeModal.addEventListener('click', closeModalHandler);

    // Initial fetch for default tab
    fetchRequests(1, null, null);
});