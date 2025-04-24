<?php

namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Core\UserManagement\UserDriverManager;

class DriverOrderSkipRequests
{
    public function __construct()
    {
        // Enqueue styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Register AJAX actions
        add_action('wp_ajax_fetch_skip_order_requests', [$this, 'fetch_skip_order_requests']);
        add_action('wp_ajax_process_skip_order_request', [$this, 'process_skip_order_request']);
    }

    public static function init()
    {
        return new self();
    }

    // Enqueue styles and scripts
    public function enqueue_assets()
    {
        wp_enqueue_style('admin-skip-order-requests-css', plugins_url('/assets/css/driverRequests/skip-order-requests.css', dirname(__FILE__, 4)));
        wp_enqueue_script('admin-skip-order-requests-js', plugins_url('/assets/js/driverRequests/skip-order-requests.js', dirname(__FILE__, 4)), ['jquery'], null, true);

        // Localize script with nonce and AJAX URL
        wp_localize_script('admin-skip-order-requests-js', 'skipOrderRequestsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('skip_order_requests_nonce'),
        ]);
    }

    public static function render()
    {
?>
        <style>
            /* Add this CSS to your theme or plugin stylesheet */
            #loader-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255, 255, 255, 0.8);
                z-index: 9999;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            #loader-spinner {
                border: 16px solid #f3f3f3;
                /* Light grey */
                border-top: 16px solid #3498db;
                /* Blue */
                border-radius: 50%;
                width: 120px;
                height: 120px;
                animation: spin 2s linear infinite;
            }

            @keyframes spin {
                0% {
                    transform: rotate(0deg);
                }

                100% {
                    transform: rotate(360deg);
                }
            }
        </style>
        <!-- Loader Overlay -->
        <div id="loader-overlay" style="display:none;">
            <div id="loader-spinner"></div>
        </div>
        <h2><?php esc_html_e('Driver Skip Order Requests', 'bidfood'); ?></h2>
        <div id="skip-order-requests-filters" style="margin-bottom: 20px;">
            <label for="status-filter"><?php esc_html_e('Filter by Status:', 'bidfood'); ?></label>
            <select id="status-filter">
                <option value=""><?php esc_html_e('All', 'bidfood'); ?></option>
                <option value="Pending"><?php esc_html_e('Pending', 'bidfood'); ?></option>
                <option value="Accepted"><?php esc_html_e('Accepted', 'bidfood'); ?></option>
                <option value="Rejected"><?php esc_html_e('Rejected', 'bidfood'); ?></option>
            </select>
        </div>
        <div id="skip-order-requests-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Request ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('WH Order ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Driver Order ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Driver Name', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Reason', 'bidfood'); ?></th>
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
                <p><strong><?php esc_html_e('Driver Reason:', 'bidfood'); ?></strong></p>
                <p id="driver-reason"></p>
                <p><strong><?php esc_html_e('Admin Reply:', 'bidfood'); ?></strong></p>
                <textarea id="admin-reply" rows="4" style="width:100%;"></textarea>
                <div class="modal-actions">
                    <button id="accept-btn" class="button button-primary"><?php esc_html_e('Accept', 'bidfood'); ?></button>
                    <button id="reject-btn" class="button button-secondary"><?php esc_html_e('Reject', 'bidfood'); ?></button>
                    <button id="close-modal" class="button"><?php esc_html_e('Close', 'bidfood'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    public function fetch_skip_order_requests()
    {
        check_ajax_referer('skip_order_requests_nonce', 'security');

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : null;

        $requests = UserDriverManager::get_skip_order_requests($page, 10, $status);
        if (empty($requests)) {
            wp_send_json_error(['message' => __('No requests found.', 'bidfood')]);
        }

        ob_start();

        foreach ($requests['results'] as $request) {
            $driver = UserDriverManager::get_driver_details($request['driver_id']);
            $wh_order = UserDriverManager::get_wh_order_by_driver_order_id($request['driver_order_id']);
            $wh_order_id = $wh_order ? $wh_order['id'] : '';
            $customer_order_id = $wh_order ? $wh_order['order_id'] : '';
            $driver_name = $driver ? $driver['name'] : '';

        ?>
            <tr>
                <td><?php echo esc_html($request['id']); ?></td>
                <td>
                    
                <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details&order_id=' . esc_attr($customer_order_id)); ?>">
                <?php echo esc_html($wh_order_id); ?>
                   
                </a>
                </td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=drivers&drivers_tab=driver-order-details&order_id=' . esc_attr($request['driver_order_id'])); ?>">
                        <?php echo esc_html($request['driver_order_id']); ?>
                    </a>
                </td>
                <td><?php echo esc_html($driver_name); ?></td>
                <td><?php echo esc_html($request['reason']); ?></td>
                <td><?php echo esc_html($request['status']); ?></td>
                <td>
                    <button class="view-btn" data-id="<?php echo esc_attr($request['id']); ?>"
                        data-status="<?php echo esc_attr($request['status']); ?>"
                        data-driver-reason="<?php echo esc_attr($request['reason']); ?>"
                        data-admin-reply="<?php echo esc_attr($request['admin_reply'] ?? ''); ?>">
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

    public function process_skip_order_request()
    {
        check_ajax_referer('skip_order_requests_nonce', 'security');

        $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $admin_reply = isset($_POST['admin_reply']) ? sanitize_textarea_field($_POST['admin_reply']) : null;

        if (!$request_id || !in_array($status, ['Accepted', 'Rejected'], true)) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }

        $result = UserDriverManager::update_skip_order_request_status($request_id, $status, $admin_reply);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Request processed successfully.', 'bidfood')]);
    }
}
