document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.tab-btn');
    const tableBody = document.querySelector('#supplier-update-requests-container tbody');
    const paginationControls = document.getElementById('pagination-controls');
    const modal = document.getElementById('request-modal');
    const overlay = document.getElementById('modal-overlay');
    const supplierNotes = document.getElementById('supplier-notes');
    const adminNotes = document.getElementById('admin-notes');
    const approveBtn = document.getElementById('approve-btn');
    const rejectBtn = document.getElementById('reject-btn');
    const closeModal = document.getElementById('close-modal');
    let currentTab = 'price';
    let currentRequestId = null;


    // Set active tab
    document.querySelector(`.tab-btn[data-type="${currentTab}"]`).classList.add('active');

    // Filter requests by status
    document.getElementById('status-filter').addEventListener('change', function () {
        fetchRequests(1, this.value, null, currentTab);
    });

    // Tab switching logic
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            currentTab = this.dataset.type;
            tabs.forEach(tab => tab.classList.remove('active'));
            this.classList.add('active');

            // Toggle headers based on the tab
            toggleHeaders(currentTab === 'price');

            fetchRequests(1, null, null, currentTab);
        });
    });

    function toggleHeaders(isPriceTab) {
        const oldValueColumn = document.getElementById('old-value-column');
        const newValueColumn = document.getElementById('new-value-column');

        if (isPriceTab) {
            oldValueColumn.style.display = '';
            newValueColumn.style.display = '';
        } else {
            oldValueColumn.style.display = 'none';
            newValueColumn.style.display = 'none';
        }
    }

    // Fetch requests dynamically based on tab selection
    function fetchRequests(page = 1, status = null, supplierId = null, requestType = null) {
        const formData = new FormData();
        formData.append('action', 'fetch_supplier_update_requests');
        formData.append('security', supplierRequestsData.nonce);
        formData.append('page', page);
        if (status) formData.append('status', status);
        if (supplierId) formData.append('supplier_id', supplierId);
        if (requestType) formData.append('request_type', requestType);

        fetch(supplierRequestsData.ajax_url, {
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
                fetchRequests(page, null, null, currentTab);
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
        formData.append('action', 'process_supplier_request');
        formData.append('security', supplierRequestsData.nonce);
        formData.append('request_id', currentRequestId);
        formData.append('status', status);
        formData.append('admin_notes', adminNotes.value);

        fetch(supplierRequestsData.ajax_url, {
            method: 'POST',
            body: formData,
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Request processed successfully.', 'success', 5000);
                    fetchRequests(1, null, null, currentTab);
                    closeModalHandler();
                } else {
                    showToast(data.data.message || 'An error occurred.', 'error', 5000);
                }
            })
            .catch(error => console.error('Error processing request:', error));
    }

    // Attach modal event listeners
    overlay.addEventListener('click', closeModalHandler);
    approveBtn.addEventListener('click', () => processRequest('approved'));
    rejectBtn.addEventListener('click', () => processRequest('rejected'));
    closeModal.addEventListener('click', closeModalHandler);

    // Initial fetch for default tab
    fetchRequests(1, null, null, currentTab);
});
