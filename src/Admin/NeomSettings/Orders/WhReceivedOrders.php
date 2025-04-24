<?php

namespace Bidfood\Admin\NeomSettings\Orders;

use Bidfood\Core\OrderManagement\WhOrderManager;
use Bidfood\Core\UserManagement\UserDriverManager;
use Bidfood\UI\Toast\ToastHelper;

class WhReceivedOrders
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    public static function init()
    {
        return new self;
    }
    public function enqueue_assets()
    {
        // Enqueue styles
        wp_enqueue_style(
            'admin-wh-received-orders-css',
            plugins_url('/assets/css/Orders/wh-received-orders.css', dirname(__FILE__, 4))
        );

        // Enqueue JavaScript for converting orders to draft WH orders
        wp_enqueue_script(
            'admin-wh-received-orders-js',
            plugins_url('/assets/js/Orders/wh-received-orders.js', dirname(__FILE__, 4)),
            ['jquery'],
            null,
            true
        );

        // Pass AJAX data for converting orders
        wp_localize_script('admin-wh-received-orders-js', 'bidfoodWhOrdersData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('update_wh_orders_nonce'),
            'order_id' => isset($_GET['order_id']) ? intval($_GET['order_id']) : 0,
        ]);
    }
    /**
     * Handles POST action for convert_to_driver_orders.
     */
    private static function handle_post_requests()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            //  convert_to_driver_orders
            if (isset($_POST['convert_to_driver_orders'])) {
                self::handle_convert_to_driver_orders();
            }
        }
    }
    /**
     * Handles POST action for convert_to_driver_orders.
     */
    private static function handle_convert_to_driver_orders()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'convert_to_driver_orders_action')) {
            ToastHelper::add_toast_notice(__('Nonce verification failed.', 'bidfood'), 'error');
            return;
        }

        // Fetch the selected WH orders and the driver ID from POST
        $selected_orders = isset($_POST['selected_orders']) ? array_map('intval', $_POST['selected_orders']) : [];
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;

        if (empty($selected_orders) || $driver_id <= 0) {
            ToastHelper::add_toast_notice(__('Please select orders and a valid driver.', 'bidfood'), 'error');
            return;
        }
        // Proceed with conversion if all statuses are valid
        $result = WhOrderManager::convert_to_driver_orders($selected_orders, $driver_id);
        if (is_wp_error($result)) {
            ToastHelper::add_toast_notice($result->get_error_message(), 'error');
        } else {
            ToastHelper::add_toast_notice(__('WH Orders converted successfully to Driver Orders.', 'bidfood'), 'success');
        }
    }
    public static function render()
    {
        self::handle_post_requests();

        // Pagination and search
        $per_page = 10; // Set the number of orders per page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $offset = max(($current_page - 1) * $per_page, 0);

        // Fetch WH Orders with pagination and search
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'All';
        $whOrders = WhOrderManager::get_wh_orders($offset, $per_page, $search_query, $status_filter);
        $total_orders = WhOrderManager::count_wh_orders($search_query, $status_filter);

        // Calculate total pages
        $total_pages = ceil($total_orders / $per_page);

        // Adjust current page if the total orders are less than current_page * per_page
        if ($current_page > $total_pages) {
            $current_page = $total_pages;
            $offset = max(($current_page - 1) * $per_page, 0); // Recalculate offset
            $whOrders = WhOrderManager::get_wh_orders($offset, $per_page, $search_query, $status_filter); // Re-fetch the orders with updated offset
        }

        // Prepare WooCommerce orders data
        $wcOrders = [];
        foreach ($whOrders as $whOrder) {
            $wcOrder = wc_get_order($whOrder['order_id']);
            if ($wcOrder) {
                $wcOrders[$whOrder['order_id']] = $wcOrder;
            }
        }

        // Render the UI with WH and WC orders
        self::render_ui($whOrders, $wcOrders, $current_page, $total_pages, $search_query, $total_orders);
    }

    private static function render_ui($whOrders, $wcOrders, $current_page, $total_pages, $search_query, $total_orders)
    {
        // Get the current status filter
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'All';

        // Get the counts of each status
        $all_count = WhOrderManager::count_orders_by_status('All', $search_query);
        $draft_count = WhOrderManager::count_orders_by_status('Draft', $search_query);
        $ready_count = WhOrderManager::count_orders_by_status('Ready for Driver Assignment', $search_query);
        $assigned_count = WhOrderManager::count_orders_by_status('Assigned to Driver', $search_query);
        $dispatched_count = WhOrderManager::count_orders_by_status('Dispatched', $search_query);
        $delivered_count = WhOrderManager::count_orders_by_status('Delivered', $search_query);
        // Get the list of drivers
        $drivers = UserDriverManager::getDrivers();
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
            border: 16px solid #f3f3f3; /* Light grey */
            border-top: 16px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 120px;
            height: 120px;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
        <!-- Loader Overlay -->
        <div id="loader-overlay" style="display:none;">
            <div id="loader-spinner"></div>
        </div>
        <div class="wrap">
            <h1><?php _e('Received Orders at BF WH', 'bidfood'); ?></h1>
            <div style="display: flex; justify-content:space-between; align-items:center;">

                <div >
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'All'); ?>"
                        class="<?php echo ($current_status == 'All') ? 'active-status' : ''; ?>">
                        <?php _e('All', 'bidfood'); ?> (<?php echo $all_count; ?>)
                    </a> |
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'Draft'); ?>"
                        class="<?php echo ($current_status == 'Draft') ? 'active-status' : ''; ?>">
                        <?php _e('Draft', 'bidfood'); ?> (<?php echo $draft_count; ?>)
                    </a> |
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'Ready for Driver Assignment'); ?>"
                        class="<?php echo ($current_status == 'Ready for Driver Assignment') ? 'active-status' : ''; ?>">
                        <?php _e('Ready for Driver Assignment', 'bidfood'); ?> (<?php echo $ready_count; ?>)
                    </a> |
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'Assigned to Driver'); ?>"
                        class="<?php echo ($current_status == 'Assigned to Driver') ? 'active-status' : ''; ?>">
                        <?php _e('Assigned to Driver', 'bidfood'); ?> (<?php echo $assigned_count; ?>)
                    </a> |
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'Dispatched'); ?>"
                        class="<?php echo ($current_status == 'Dispatched') ? 'active-status' : ''; ?>">
                        <?php _e('Dispatched', 'bidfood'); ?> (<?php echo $dispatched_count; ?>)
                    </a> |
                    <a style="text-decoration: none;" href="<?php echo add_query_arg('status', 'Delivered'); ?>"
                        class="<?php echo ($current_status == 'Delivered') ? 'active-status' : ''; ?>">
                        <?php _e('Delivered', 'bidfood'); ?> (<?php echo $delivered_count; ?>)
                    </a>
                </div>

                <!-- Search Form -->
                 
                <form method="get" action=""style="align-self: center" >
                    <input type="hidden" name="page" value="bidfood-neom-settings">
                    <input type="hidden" name="tab" value="orders">
                    <input style="width: 200px; margin-right: 5px;" type="text" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php _e('Search...', 'bidfood'); ?>">
                    <button type="submit" class="button"><?php _e('Search', 'bidfood'); ?></button>
                </form>
            </div>
            <?php if (!empty($whOrders)) : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('convert_to_driver_orders_action'); ?>
                    <!-- Driver selection dropdown -->
                    <div style="text-align: center; padding-bottom:8px">
                        <label for="driver_id"><?php _e('Select Driver:', 'bidfood'); ?></label>
                        <select name="driver_id" id="driver_id" required>
                            <option value=""><?php _e('Select a driver', 'bidfood'); ?></option>
                            <?php foreach ($drivers as $driver): ?>
                                <option value="<?php echo esc_attr($driver['id']); ?>">
                                    <?php echo esc_html($driver['first_name'] . ' ' . $driver['last_name'] . ' (' . $driver['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <!-- Submit button -->
                        <button type="submit" name="convert_to_driver_orders" class="button button-primary"><?php _e('Convert to Driver Orders', 'bidfood'); ?></button>
                    </div>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="select_all" />
                                </th>
                                <th><?php _e('WH Order ID', 'bidfood'); ?></th>
                                <th><?php _e('Customer Order ID', 'bidfood'); ?></th>
                                <th><?php _e('Customer E-mail', 'bidfood'); ?></th>
                                <th><?php _e('Total', 'bidfood'); ?></th>
                                <th><?php _e('Customer Note', 'bidfood'); ?></th>
                                <th><?php _e('Customer Order Status', 'bidfood'); ?></th>
                                <th><?php _e('WH Order Status', 'bidfood'); ?></th>
                                <th><?php _e('Date Created', 'bidfood'); ?></th>
                                <th><?php _e('Actions', 'bidfood'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($whOrders as $whOrder) : ?>
                                <?php
                                $wh_order_id = $whOrder['id'];
                                $customer_order_id = $whOrder['order_id'];
                                $wcOrder = $wcOrders[$customer_order_id] ?? null;
                                $customer_email = $wcOrder ? $wcOrder->get_billing_email() : __('Unknown E-mail', 'bidfood');
                                $total = $wcOrder ? wc_price($wcOrder->get_total()) : __('N/A', 'bidfood');
                                $whOrderStatus = $whOrder['wh_order_status'];
                                $orderStatus = $wcOrder ? wc_get_order_status_name($wcOrder->get_status()) : __('N/A', 'bidfood');
                                $date_created = $wcOrder ? $wcOrder->get_date_created() : null;
                                $formatted_date = $date_created ? $date_created->date('Y-m-d H:i:s') : __('N/A', 'bidfood');
                                $order_customer_note = $wcOrder->get_customer_note() ? $wcOrder->get_customer_note() : __('N/A', 'bidfood');
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_orders[]" class="select_order" value="<?php echo esc_attr($whOrder['id']); ?>">
                                    </td>
                                    <td><?php echo esc_html($wh_order_id); ?></td>
                                    <td><?php echo esc_html($customer_order_id); ?></td>
                                    <td><?php echo esc_html($customer_email); ?></td>
                                    <td><?php echo $total; ?></td>
                                    <td><?php echo esc_html($order_customer_note); ?></td>
                                    <td><?php echo esc_html($orderStatus); ?></td>
                                    <td><?php echo esc_html($whOrderStatus); ?></td>
                                    <td><?php echo esc_html($formatted_date); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details&order_id=' . esc_attr($customer_order_id)); ?>"
                                            class="button button-primary view-order-btn">
                                            <?php _e('View', 'bidfood'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
                <!-- Pagination (Only Show if Filtered Orders >= 10) -->
                <?php if ($total_orders >= 10) : ?>
                    <div class="pagination-container" style="text-align: center; margin-top: 20px;">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg(['paged' => '%#%', 's' => $search_query, 'status' => $current_status]),
                            'format' => '',
                            'current' => $current_page,
                            'total' => $total_pages,
                            'before_page_number' => '<span class="primary-button" style="color: #fff; background-color: #007cba; padding: 8px 12px; border-radius: 4px; margin: 2px;">',
                            'after_page_number' => '</span>',
                        ]);
                        ?>
                    </div>
                <?php endif; ?>

            <?php else : ?>
                <p><?php _e('No orders found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>
<?php
    }
}
