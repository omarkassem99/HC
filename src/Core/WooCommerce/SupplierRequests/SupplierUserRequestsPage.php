<?php

namespace Bidfood\Core\WooCommerce\SupplierRequests;

use Bidfood\Core\UserManagement\UserSupplierManager;

class SupplierUserRequestsPage {

    public static $request_type;

    public function __construct() {
        // Add menu item to WooCommerce "My Account"
        add_filter('woocommerce_account_menu_items', [$this, 'add_requests_menu_item']);
        // Register endpoint for the page
        add_action('init', [$this, 'add_requests_endpoint']);
        // Display content for the endpoint
        add_action('woocommerce_account_supplier-requests_endpoint', [$this, 'supplier_requests_page_content']);
        // Enqueue necessary scripts and styles conditionally
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        // Handle AJAX pagination and filtering
        add_action('wp_ajax_fetch_supplier_requests', [$this, 'ajax_fetch_requests']);
        // Handle AJAX request cancellation
        add_action('wp_ajax_cancel_supplier_request', [$this, 'ajax_cancel_request']);
    }

    public static function init() {
        return new self();
    }

    public function add_requests_menu_item($items) {
        if (UserSupplierManager::is_user_supplier(get_current_user_id())) {
            $items['supplier-requests'] = __('Supplier Requests', 'bidfood');
        }
        return $items;
    }

    public function add_requests_endpoint() {
        add_rewrite_endpoint('supplier-requests', EP_ROOT | EP_PAGES);
    }

    public function enqueue_scripts() {
        wp_enqueue_script(
            'supplier-requests-js',
            plugins_url('/assets/js/supplier-requests.js', dirname(__FILE__, 4)),
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'supplier-requests-css',
            plugins_url('/assets/css/supplier-requests.css', dirname(__FILE__, 4)),
            [],
            null
        );

        wp_localize_script('supplier-requests-js', 'supplierRequestsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_requests_nonce'),
            'current_user_id' => get_current_user_id(),
        ]);
    }

    public function ajax_cancel_request() {
        check_ajax_referer('supplier_requests_nonce', 'security');
    
        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $request_type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        error_log('Request ID: ' . $request_id);
        error_log('Request Type: ' . $request_type);
        if (!$request_id) {
            error_log('Invalid request ID.');
            wp_send_json_error(['message' => __('Invalid request ID.', 'bidfood')]);
        }
    
        $user_id = get_current_user_id();
        $supplier_id = UserSupplierManager::get_supplier_by_user($user_id);
    
        if (is_wp_error($supplier_id)) {
            error_log('Supplier not found. Error: ' . $supplier_id->get_error_message());
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }
    
        if ($request_type === 'items') {
            $result = UserSupplierManager::update_supplier_add_item_request_status($request_id, 'cancelled', __('Request canceled by supplier.', 'bidfood'));
        } else {
            $result = UserSupplierManager::update_supplier_request_status($request_id, 'cancelled', $request_type, __('Request canceled by supplier.', 'bidfood'));
        }
    
        if (is_wp_error($result)) {
            error_log('Failed to cancel the request. Error: ' . $result->get_error_message());
            wp_send_json_error(['message' => __('Failed to cancel the request.', 'bidfood')]);
        }
    
        // Trigger the event for request cancellation
        do_action('bidfood_supplier_request_cancelled', $request_id);
    
        wp_send_json_success(['message' => __('Request successfully canceled.', 'bidfood')]);
    }

    public function supplier_requests_page_content() {
        ?>
        <h3 class="supplier-requests-title"><?php esc_html_e('Supplier Requests', 'bidfood'); ?></h3>
        <div id="supplier-requests-container">
            <ul class="supplier-tabs-modern">
                <li data-type="price" class="tab-modern active"><?php esc_html_e('Price Update', 'bidfood'); ?></li>
                <li data-type="delist" class="tab-modern"><?php esc_html_e('Delisting', 'bidfood'); ?></li>
                <li data-type="items" class="tab-modern"><?php esc_html_e('New Items', 'bidfood'); ?></li>
            </ul>
            <div class="supplier-controls">
                <select id="request-status-filter" class="supplier-select">
                    <option value=""><?php esc_html_e('All Statuses', 'bidfood'); ?></option>
                    <option value="pending"><?php esc_html_e('Pending', 'bidfood'); ?></option>
                    <option value="approved"><?php esc_html_e('Approved', 'bidfood'); ?></option>
                    <option value="rejected"><?php esc_html_e('Rejected', 'bidfood'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Cancelled', 'bidfood'); ?></option>
                </select>
                <select id="requests-per-page" class="supplier-select">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                </select>
            </div>
            <table class="supplier-requests-modern-table">
                <thead id="requests-table-header"></thead>
                <tbody id="requests-table-body"></tbody>
            </table>
            <div id="supplier-pagination-container"></div>
        </div>
        <?php
    }

    public function ajax_fetch_requests() {
        check_ajax_referer('supplier_requests_nonce', 'security');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $request_type = isset($_POST['request_type']) ? sanitize_text_field($_POST['request_type']) : '';

        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }
        

        // Fetch the correct data based on request type
        if ($request_type === 'items') {
            $result = UserSupplierManager::get_supplier_add_item_requests($page, $per_page, $supplier_id, $status);
        } else {
            $result = UserSupplierManager::get_supplier_requests($page, $per_page, $supplier_id, $status, $request_type);
        }

        if (empty($result['results'])) {
            wp_send_json_error(['message' => __('No requests found.', 'bidfood')]);
        }

        ob_start();
        if ($request_type === 'items') {
            // Render new item requests
            ?>
            <tr>
                <th>
                    <?php esc_html_e('ID', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Item Description', 'bidfood'); ?>
                </th>
              
                <th>
                    <?php esc_html_e('Country', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('UOM', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Brand', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Supplier Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Admin Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Status', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Created At', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Actions', 'bidfood'); ?>
                </th>
            </tr>
            <?php
        } elseif($request_type === 'price') {
            // Render price update requests
            ?>
            <tr>
                <th>
                    <?php esc_html_e('Product ID', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Product Name', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Current Price', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('New Price', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Supplier Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Admin Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Status', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Created At', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Actions', 'bidfood'); ?>
                </th>
            </tr>
            <?php
        } else {

            // Render delisting requests
            ?>
            <tr>
                <th>
                    <?php esc_html_e('Product ID', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Product Name', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Supplier Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Admin Notes', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Status', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Created At', 'bidfood'); ?>
                </th>
                <th>
                    <?php esc_html_e('Actions', 'bidfood'); ?>
                </th>
                <?php } ?>
                <?php

        foreach ($result['results'] as $request) {
            if ($request_type === 'items') {
                // Render new item requests
                ?>
                <tr>
                    <td><?php echo esc_html($request['id']); ?></td>
                    <td><?php echo esc_html($request['item_description']?? '—'); ?></td>
                    <td><?php echo esc_html($request['country']== ' '? '—':$request['country']); ?></td>
                    <td><?php echo esc_html($request['uom_id']?? '—'); ?></td>
                    <td><?php echo esc_html($request['brand']==' '? '—':$request['brand']); ?></td>
                    <td><?php echo esc_html($request['supplier_notes']??'—'); ?></td>
                    <td><?php echo esc_html($request['admin_notes'] ?? '—'); ?></td>
                    <td><?php echo esc_html(ucwords($request['status'])); ?></td>
                    <td><?php echo esc_html(date('d M Y, H:i', strtotime($request['created_at']))); ?></td>
                    <td>
                        <?php if ($request['status'] === 'pending'): 
                            ?>
                            <button class="cancel-request-button" data-type="<?php echo esc_attr($request_type) ?>" data-request-id="<?php echo esc_attr($request['id']); ?>">
                                <?php esc_html_e('Cancel', 'bidfood'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            } else {
                // Render price update or delisting requests
                $product_id = wc_get_product_id_by_sku($request['product_id']);
                if (!$product_id) {
                    continue;
                }

                $product = wc_get_product($product_id);
            
                $hide_price_columns = isset($_POST['request_type']) && (sanitize_text_field($_POST['request_type']) === 'delist');
                ?>
                <tr>
                    <td><?php echo esc_html($request['product_id']); ?></td>
                    <td><?php echo esc_html($product->get_name()); ?></td>
                    <?php if (!$hide_price_columns): ?>
                        <td class="current-price-column"><?php echo esc_html($request['old_value'] ?? '—'); ?></td>
                        <td class="new-price-column"><?php echo esc_html($request['new_value'] ?? '—'); ?></td>
                    <?php endif; ?>
                    <td><?php echo esc_html($request['supplier_notes'] ?? '—'); ?></td>
                    <td><?php echo esc_html($request['admin_notes'] ?? '—'); ?></td>
                    <td><?php echo esc_html(ucwords($request['status'])); ?></td>
                    <td><?php echo esc_html(date('d M Y, H:i', strtotime($request['created_at']))); ?></td>
                    <td>
                        <?php if ($request['status'] === 'pending'): ?>
                            <button class="cancel-request-button" data-type="<?php echo esc_attr($request_type) ?>" data-request-id="<?php echo esc_attr($request['id']); ?>">
                                <?php esc_html_e('Cancel', 'bidfood'); ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
        }

        $table_rows = ob_get_clean();

        ob_start();
        $this->render_pagination($page, $result['total_pages']);
        $pagination = ob_get_clean();

        wp_send_json_success(['rows' => $table_rows, 'pagination' => $pagination]);
    }
    
    private function render_pagination($current_page, $total_pages) {
        ?>
        <div class="supplier-pagination-container-modern">
            <span id="supplier-pagination-info">
                <?php printf(esc_html__('Page %d of %d', 'bidfood'), $current_page, $total_pages); ?>
            </span>
            <div class="supplier-pagination-controls-modern">
                <?php if ($current_page > 1): ?>
                    <a href="#" class="pagination-link-modern" data-page="<?php echo esc_attr($current_page - 1); ?>">&laquo; <?php esc_html_e('Previous', 'bidfood'); ?></a>
                <?php endif; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="#" class="pagination-link-modern" data-page="<?php echo esc_attr($current_page + 1); ?>"><?php esc_html_e('Next', 'bidfood'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}