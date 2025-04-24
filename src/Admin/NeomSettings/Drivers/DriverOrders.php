<?php

namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Core\UserManagement\UserDriverManager;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class DriverOrders
{
    public static function init()
    {
        return new self;
    }

    public static function render()
    {
        self::handle_post_requests();
        self::render_ui();
    }

    /**
     * Handles various POST actions for driver orders.
     */
    private static function handle_post_requests()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Delete driver order
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                self::handle_delete_driver();
            }
        }
    }

    /**
     * Handle Delete Driver action.
     */
    private static function handle_delete_driver()
    {
        check_admin_referer('delete_action', '_wpnonce_delete');

        $driver_order_id = intval($_POST['entity_id']);
        $result = UserDriverManager::deleteDriverOrder($driver_order_id);

        // Handle errors or success
        if (is_wp_error($result)) {
            ToastHelper::add_toast_notice($result->get_error_message(), 'error');
        } else {
            ToastHelper::add_toast_notice(__('Driver Order deleted successfully.', 'bidfood'), 'success');
        }

        wp_safe_redirect(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers&drivers_tab=all-driver-orders'));
        exit;
    }

    /**
     * Render the Driver Users UI.
     */
    private static function render_ui()
    {
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1; // Get the current page from the URL
        $limit = 10; // Number of items per page
        $offset = ($page - 1) * $limit;

        // Fetch paginated results
        $driverOrders = UserDriverManager::getDriverOrders($limit, $offset);
        $totalOrders = UserDriverManager::getDriverOrdersCount();
        $totalPages = ceil($totalOrders / $limit);
?>

        <div class="wrap">
            <h1><?php _e('All BF Driver Orders', 'bidfood'); ?></h1>

            <table class="wp-list-table widefat fixed striped" style="margin: 16px;">
                <thead>
                    <tr>
                        <th><?php _e('Driver Order ID', 'bidfood'); ?></th>
                        <th><?php _e('WH Order ID', 'bidfood'); ?></th>
                        <th><?php _e('Driver Name', 'bidfood'); ?></th>
                        <th><?php _e('Driver Email', 'bidfood'); ?></th>
                        <th><?php _e('Driver Phone', 'bidfood'); ?></th>
                        <th><?php _e('Driver Note', 'bidfood'); ?></th>
                        <th><?php _e('Status', 'bidfood'); ?></th>
                        <th><?php _e('Delivery Started At', 'bidfood'); ?></th>
                        <th><?php _e('Delivery Completed At', 'bidfood'); ?></th>
                        <th><?php _e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($driverOrders)): ?>
                        <?php foreach ($driverOrders as $driverOrder): 
                            $wh_order = UserDriverManager::get_wh_order_by_driver_order_id($driverOrder['driver_order_id']);
                            $customer_order_id = $wh_order ? $wh_order['order_id'] : '';
                            ?>
                            <?php
                            $driver = UserDriverManager::getDriverById($driverOrder['driver_id']);
                            ?>
                            <tr>
                                <td><?php echo esc_html($driverOrder['driver_order_id']); ?></td>
                                <td>

                                <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details&order_id=' . esc_attr($customer_order_id)); ?>">
                                   <?php echo esc_html($driverOrder['wh_order_id']); ?>
                   
                                </a>

                                </td>
                                <td><?php echo esc_html($driver['first_name'] . ' ' . $driver['last_name'] ? $driver['first_name'] . ' ' . $driver['last_name'] : 'N/A'); ?></td>
                                <td><?php echo esc_html($driver['email'] ? $driver['email'] : 'N/A'); ?></td>
                                <td><?php echo esc_html($driver['phone'] ? $driver['phone'] : 'N/A'); ?></td>
                                <td><?php echo esc_html($driverOrder['driver_notes'] ? $driverOrder['driver_notes'] : 'N/A'); ?></td>
                                <td><?php echo esc_html($driverOrder['status']); ?></td>
                                <td><?php echo esc_html($driverOrder['delivery_start_time'] ? $driverOrder['delivery_start_time'] : 'N/A'); ?></td>
                                <td><?php echo esc_html($driverOrder['delivery_end_time'] ? $driverOrder['delivery_end_time'] : 'N/A'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=drivers&drivers_tab=driver-order-details&order_id=' . esc_attr($driverOrder['driver_order_id'])); ?>"
                                        class="button button-primary">
                                        <?php _e('View', 'bidfood'); ?>
                                    </a>
                                    <!-- Delete button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-entity="driver-order"
                                        data-id="<?php echo esc_attr($driverOrder['driver_order_id']); ?>">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10"><?php _e('No Driver Order Found.', 'bidfood'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php
            // Pagination
            if ($totalPages > 1): ?>
                <div class="pagination" style="margin-top: 16px; text-align:center">
                    <?php
                    for ($i = 1; $i <= $totalPages; $i++) {
                        $url = add_query_arg('paged', $i, admin_url('admin.php?page=bidfood-neom-settings&tab=drivers&drivers_tab=all-driver-orders'));
                        $class = $i == $page ? 'current' : '';
                        echo '<a href="' . esc_url($url) . '" class="button button-primary' . $class . '" style="margin:5px;" >' . $i . '</a>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
<?php
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'driver-order');
    }
}
