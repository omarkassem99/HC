<?php

namespace Bidfood\Admin\NeomSettings\SupplierRequests;

use Bidfood\Core\UserManagement\UserSupplierManager;

class AdminNewItemRequests
{
    public function __construct()
    {
        // Enqueue styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'bidfood_admin_enqueue_assets']);

        // Register AJAX actions
        add_action('wp_ajax_fetch_supplier_add_item_requests', [$this, 'fetch_supplier_add_item_requests']);
        add_action('wp_ajax_process_supplier_add_item_request', [$this, 'process_supplier_add_item_request']);
    }

    public static function init()
    {
        return new self();
    }

    // Enqueue styles and scripts
    public function bidfood_admin_enqueue_assets()
    {
        wp_enqueue_style('admin-new-item-requests-css', plugins_url('/assets/css/newItemsRequest/admin-new-item-requests.css', dirname(__FILE__, 4)));
        wp_enqueue_script('admin-new-item-requests-js', plugins_url('/assets/js/newItemsRequest/admin-new-item-requests.js', dirname(__FILE__, 4)), ['jquery'], null, true);

        // Localize script with nonce and AJAX URL
        wp_localize_script('admin-new-item-requests-js', 'supplierAddItemRequestsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_add_item_requests_nonce'),
        ]);
    }

    public static function render()
    {
?>
        <h2><?php esc_html_e('Supplier New Items Requests', 'bidfood'); ?></h2>
        <div id="supplier-update-requests-filters">
            <label for="status-filter"><?php esc_html_e('Filter by Status:', 'bidfood'); ?></label>
            <select id="status-filter">
                <option value=""><?php esc_html_e('All', 'bidfood'); ?></option>
                <option value="pending"><?php esc_html_e('Pending', 'bidfood'); ?></option>
                <option value="approved"><?php esc_html_e('Approved', 'bidfood'); ?></option>
                <option value="rejected"><?php esc_html_e('Rejected', 'bidfood'); ?></option>
                <option value="cancelled"><?php esc_html_e('Cancelled', 'bidfood'); ?></option>
            </select>
        </div>
        <div id="supplier-add-item-requests-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Request ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Supplier ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Country', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Product Name', 'bidfood'); ?></th>
                        <th><?php esc_html_e('UOM', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Packing', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Brand', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
            <div id="pagination-controls"></div>
        </div>

        <!-- Modal -->
        <div id="modal-overlay" class="modal-overlay" style="display: none;"></div>
        <div id="request-modal" style="display: none;">
            <div class="modal-content">
                <h3 id="modal-title"><?php esc_html_e('Request Details', 'bidfood'); ?></h3>
                <p><strong><?php esc_html_e('Supplier Notes:', 'bidfood'); ?></strong></p>
                <p id="supplier-notes"></p>
                <p><strong><?php esc_html_e('Admin Notes:', 'bidfood'); ?></strong></p>
                <textarea id="admin-notes" rows="4" style="width:100%;"></textarea>
                <div class="modal-actions">
                    <button id="approve-btn" class="button button-primary"><?php esc_html_e('Approve', 'bidfood'); ?></button>
                    <button id="reject-btn" class="button button-secondary"><?php esc_html_e('Reject', 'bidfood'); ?></button>
                    <button id="close-modal" class="button"><?php esc_html_e('Close', 'bidfood'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    public function fetch_supplier_add_item_requests()
    {
        check_ajax_referer('supplier_add_item_requests_nonce', 'security');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;
        $supplier_id = isset($_POST['supplier_id']) ? sanitize_text_field($_POST['supplier_id']) : null;

        $requests = UserSupplierManager::get_supplier_add_item_requests($page, 10, $supplier_id, $status);

        if (empty($requests)) {
            wp_send_json_error(['message' => __('No requests found.', 'bidfood')]);
        }

        ob_start();

        foreach ($requests['results'] as $request) {
        ?>
            <tr>
                <td><?php echo esc_html($request['id']); ?></td>
                <td><?php echo esc_html($request['supplier_id']); ?></td>
                <td><?php echo esc_html($request['country'] ?? 'N/A'); ?></td>
                <td><?php echo esc_html($request['item_description'] ?? 'N/A'); ?></td>
                <td><?php echo esc_html($request['uom_id'] ?? 'N/A'); ?></td>
                <td><?php echo esc_html($request['packing'] ?? 'N/A'); ?></td>
                <td><?php echo esc_html($request['brand']); ?></td>
                <td><?php echo esc_html($request['status']); ?></td>
                <td>
                    <button class="view-btn" data-id="<?php echo esc_attr($request['id']); ?>"
                        data-status="<?php echo esc_attr($request['status']); ?>"
                        data-supplier-notes="<?php echo esc_attr($request['supplier_notes'] ?? ''); ?>"
                        data-admin-notes="<?php echo esc_attr($request['admin_notes'] ?? ''); ?>">
                        <?php esc_html_e('View', 'bidfood'); ?>
                    </button>
                </td>
            </tr>
        <?php
        }

        $rows_html = ob_get_clean();
        wp_send_json_success([
            'html' => $rows_html,
            'pagination' => $this->generate_pagination($requests['total_pages'], $page)
        ]);
    }

    private function generate_pagination($total_pages, $current_page)
    {
        ob_start();
        for ($i = 1; $i <= $total_pages; $i++) {
        ?>
            <a href="#" class="<?php echo $i === $current_page ? 'active' : ''; ?>" data-page="<?php echo esc_attr($i); ?>">
                <?php echo esc_html($i); ?>
            </a>
<?php
        }
        return ob_get_clean();
    }

    public function process_supplier_add_item_request()
    {
        check_ajax_referer('supplier_add_item_requests_nonce', 'security');

        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : null;

        if (!$request_id || !in_array($status, ['approved', 'rejected'], true)) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }

        $result = UserSupplierManager::update_supplier_add_item_request_status($request_id, $status, $admin_notes);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        switch ($status) {
            case 'approved':
                // Trigger the supplier request approved event
                do_action('bidfood_supplier_add_item_request_approved', $request_id);
                break;
            case 'rejected':
                // Trigger the supplier request rejected event
                do_action('bidfood_supplier_add_item_request_rejected', $request_id);
                break;
        }

        wp_send_json_success(['message' => __('Request processed successfully.', 'bidfood')]);
    }
}
