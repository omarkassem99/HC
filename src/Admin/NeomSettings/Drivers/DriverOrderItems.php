<?php

namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Core\OrderManagement\WhOrderManager;
use Bidfood\Core\UserManagement\UserDriverManager;
use Bidfood\UI\Toast\ToastHelper;

class DriverOrderItems
{
    public function __construct() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);

    }

    public static function init()
    {
        return new self;
    }

    public static function enqueue_assets()
    {
        // Enqueue styles for the specific admin pages
        $screen = get_current_screen();
        if ($screen->id === 'bidfood_page_bidfood-neom-settings') {
        wp_enqueue_style(
            'admin-driver-order-items-css',
            plugins_url('/assets/css/Orders/driver-order-items.css', dirname(__FILE__, 4))
        );
    }
    }

    public static function render_order_details()
    {

        // Check if the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to access this page.', 'bidfood'));
        }

        // Check for form submission and process it
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['driver_order_id'])) {
            self::process_form_submission();
        }

        // Get the driver order ID from the URL query parameters
        $driver_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if (!$driver_order_id) {
            echo '<div class="notice notice-error"><p>' . __('Invalid order ID.', 'bidfood') . '</p></div>';
            return;
        }

        // Fetch driver order details
        $driver_order = UserDriverManager::get_driver_order_details($driver_order_id);
        if (!$driver_order) {
            echo '<div class="notice notice-error"><p>' . __('Driver order not found.', 'bidfood') . '</p></div>';
            return;
        }

        // Fetch associated items
        $items = UserDriverManager::get_driver_order_items($driver_order_id);

        // Fetch driver details
        $driver_details = UserDriverManager::get_driver_details($driver_order['driver_id']);

        $wh_orders = WhOrderManager::get_wh_orders_by_ids($driver_order['wh_order_id']);
        if (empty($wh_orders)) {
            return ToastHelper::add_toast_notice(__('No WH orders found for the given IDs.', 'bidfood'), 'error');
        }

        // Validate WooCommerce order statuses
        foreach ($wh_orders as $wh_order) {
            if (!isset($wh_order['order_id']) || empty($wh_order['order_id'])) {
                return ToastHelper::add_toast_notice(__('No WooCommerce order ID found for the given WH order.', 'bidfood'), 'error');
            }

            $customer_order_id = $wh_order['order_id'];
        } // WooCommerce order ID
?>
        <div class="wrap">
            <h1><?php _e('Driver Order Details', 'bidfood'); ?></h1>
            <div style="display: flex; justify-content:space-between; align-items:center;">
                <h2><?php _e('Order Information', 'bidfood'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details&order_id=' . esc_attr($customer_order_id)); ?>"
                    class="button button-primary view-order-btn">
                    <?php _e('View WH Order', 'bidfood'); ?>
                </a>
            </div>
            <form method="post">
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <th><?php _e('Driver Order ID', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_order['driver_order_id']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Warehouse Order ID', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_order['wh_order_id']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Driver Name', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_details['name']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Driver Email', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_details['email']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Driver Phone', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_details['phone']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Delivery Started At', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_order['delivery_start_time'] ? $driver_order['delivery_start_time'] : 'Not Started'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Delivery Completed At', 'bidfood'); ?></th>
                        <td><?php echo esc_html($driver_order['delivery_end_time'] ? $driver_order['delivery_end_time'] : 'Not Delivered'); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Status', 'bidfood'); ?></th>
                        <td>
                      <?php echo esc_html($driver_order['status']) ; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Driver Notes', 'bidfood'); ?></th>
                        <td><textarea name="driver_notes" <?php echo $driver_order['status']=='Delivered'?'disabled':''?>><?php echo esc_textarea($driver_order['driver_notes']); ?></textarea></td>
                    </tr>
                </table>
                <input type="hidden" name="driver_order_id" value="<?php echo esc_attr($driver_order['driver_order_id']); ?>">
                <div style="text-align: end;">
                    <button type="submit" class="button button-primary" style="margin-top: 10px; text-align: center;" <?php echo $driver_order['status']=='Delivered'?'disabled':''?>> <?php _e('Update Order', 'bidfood'); ?></button>
                </div>
            </form>

            <h2><?php _e('Order Items', 'bidfood'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Item Name', 'bidfood'); ?></th>
                        <th><?php _e('SKU', 'bidfood'); ?></th>
                        <th><?php _e('UOM', 'bidfood'); ?></th>
                        <th><?php _e('Delivery Date', 'bidfood'); ?></th>
                        <th><?php _e('Expected Date', 'bidfood'); ?></th>
                        <th><?php _e('Customer Requested Amount', 'bidfood'); ?></th>
                        <th><?php _e('WH Confirmed Amount', 'bidfood'); ?></th>
                        <th><?php _e('Customer Confirmed Amount', 'bidfood'); ?></th>
                        <th><?php _e('Price', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($items)) {
                        foreach ($items as $item) {
                            $item_details = UserDriverManager::get_item_details($item['item_id']);
                            echo '<tr>';
                            echo '<td>' . esc_html($item_details['name']) . '</td>';
                            echo '<td>' . esc_html($item_details['sku']) . '</td>';
                            echo '<td>' . esc_html($item['uom_id']) . '</td>';
                            echo '<td>' . esc_html($item['customer_delivery_date']) . '</td>';
                            echo '<td>' . esc_html($item['expected_delivery_date']) . '</td>';
                            echo '<td>' . esc_html($item['customer_requested_amount']) . '</td>';
                            echo '<td>' . esc_html($item['amount']) . '</td>';
                            echo '<td>' . esc_html($item['customer_confirmed_amount']!=0? $item['customer_confirmed_amount']: 'Not Confirmed') . '</td>';
                            echo '<td>' . wc_price($item_details['price']) . '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr>';
                        echo '<td colspan="9">' . __('No items found.', 'bidfood') . '</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
<?php
    }

    public static function process_form_submission()
    {
        if (!isset($_POST['driver_order_id']) || !current_user_can('manage_options')) {
            wp_die(__('Invalid request.', 'bidfood'));
        }

        $driver_order_id = intval($_POST['driver_order_id']);
        $driver_notes = sanitize_textarea_field($_POST['driver_notes']);

        $result = UserDriverManager::updateDriverOrder($driver_order_id, $driver_notes);
        if (is_wp_error($result)) {
            ToastHelper::add_toast_notice($result->get_error_message(), 'error');
        } else {
            ToastHelper::add_toast_notice(__('Order updated successfully.', 'bidfood'), 'success');
        }
    }
}
