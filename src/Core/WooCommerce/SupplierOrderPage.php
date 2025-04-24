<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;
use WC_Order;

class SupplierOrderPage {

    public function __construct() {
        // Hook to add the menu item to "My Account"
        add_filter('woocommerce_account_menu_items', [$this, 'add_supplier_orders_menu_item']);
        // Hook to add endpoint to handle supplier orders
        add_action('init', [$this, 'add_supplier_orders_endpoint']);
        // Hook to handle endpoint content
        add_action('woocommerce_account_supplier-orders_endpoint', [$this, 'supplier_orders_page_content']);
        // Handle the sub-page for order details
        add_action('woocommerce_account_supplier-order-details_endpoint', [$this, 'supplier_order_details_page_content']);
        // Enqueue scripts for item status change
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        // Handle AJAX for changing item status
        add_action('wp_ajax_change_item_status', [$this, 'change_item_status']);
    }

    public static function init() {
        return new self();
    }

    // Add "Supplier Orders" menu item to "My Account" and place it at the top
    public function add_supplier_orders_menu_item($items) {
        // Add the new menu item only for supplier users
        if (UserSupplierManager::is_user_supplier(get_current_user_id())) {
            // Create a new array with "Supplier Orders" at the top
            $new_items = ['supplier-orders' => __('Supplier Orders', 'bidfood')];
            // Merge with the rest of the items
            $items = array_merge($new_items, $items);
        }
        return $items;
    }

    // Register new endpoint for supplier orders
    public function add_supplier_orders_endpoint() {
        add_rewrite_endpoint('supplier-orders', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('supplier-order-details', EP_ROOT | EP_PAGES);
    }

    // Supplier orders page content
    public function supplier_orders_page_content() {
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());

        if (is_wp_error($supplier_id)) {
            $this->render_no_orders_message();
            return;
        }

        $orders = UserSupplierManager::get_supplier_orders($supplier_id);

        if (empty($orders)) {
            $this->render_no_orders_message();
            return;
        }

        // Render orders table
        $this->render_orders_table($orders);
    }

    // Supplier order details page content
    public function supplier_order_details_page_content() {
        $order_id = get_query_var('supplier-order-details');
        $order = wc_get_order($order_id);

        if (!$order) {
            $this->render_order_not_found_message();
            return;
        }

        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            $this->render_no_orders_message();
            return;
        }

        $items = UserSupplierManager::get_supplier_order_assigned_items($order_id, $supplier_id);

        if (empty($items)) {
            $this->render_no_items_message();
            return;
        }
        
        // Render order details
        $this->render_order_details($order, $items);
    }

    // Enqueue necessary scripts for AJAX item status changes
    public function enqueue_scripts() {
        // Enqueue the main WooCommerce frontend script, which you are extending
        wp_enqueue_script('jquery'); // Ensure jQuery is available
    
        // Register a custom script for the supplier orders page
        wp_register_script('supplier-orders-script', false); // No external file needed, inline script
    
        // Localize script to pass PHP data to JavaScript
        wp_localize_script('supplier-orders-script', 'supplierOrders', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('change_item_status_nonce'),
        ));
    
        // Enqueue the script with localized data
        wp_enqueue_script('supplier-orders-script');
    
        // Inline JavaScript
        ?>
        <script type="text/javascript">
            function changeItemStatus(itemId, orderId) {
                var status = jQuery('#item_status_' + itemId).val();
                var data = {
                    action: 'change_item_status',
                    item_id: itemId,
                    order_id: orderId,
                    status: status,
                    security: supplierOrders.nonce // Use the localized nonce
                };
                jQuery.post(supplierOrders.ajaxurl, data, function(response) {
                    if (response.success) {
                        showToast('Status updated successfully!', 'success', 5000);
                    } else {
                        showToast(response.data, 'error', 5000);
                    }
                    // reload the page after status update
                    location.reload();
                });
            }
        </script>
        <?php
    }
    
    

    // Render message when no orders are found
    private function render_no_orders_message() {
        ?>
        <p><?php esc_html_e('No orders assigned to this supplier.', 'bidfood'); ?></p>
        <?php
    }

    // Render message when no items are assigned in the order
    private function render_no_items_message() {
        ?>
        <p><?php esc_html_e('No items assigned in this order.', 'bidfood'); ?></p>
        <?php
    }

    // Render message when the order is not found
    private function render_order_not_found_message() {
        ?>
        <p><?php esc_html_e('Order not found.', 'bidfood'); ?></p>
        <?php
    }

    // Render orders table
    private function render_orders_table($orders) {
        ?>
        <h3><?php esc_html_e('Assigned Orders', 'bidfood'); ?></h3>
        <table class="shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th><?php esc_html_e('Order', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Date', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order_id): ?>
                    <?php $order = wc_get_order($order_id); ?>
                    <tr>
                        <td><?php echo esc_html($order->get_order_number()); ?></td>
                        <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                        <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wc_get_endpoint_url('supplier-order-details', $order_id, wc_get_page_permalink('myaccount'))); ?>" class="button">
                                <?php esc_html_e('View', 'bidfood'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // Render order details and items assigned to the supplier
    private function render_order_details($order, $items) {
        ?>
        <h3><?php esc_html_e('Order Details for Order #', 'bidfood'); ?><?php echo esc_html($order->get_order_number()); ?></h3>
        <table class="shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th><?php esc_html_e('Item ID', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Item', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Quantity', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $product = $item->get_product();
                    $product_sku = $product ? $product->get_sku() : '';
                    $supplier_item = UserSupplierManager::get_item_supplier_assignment($product_sku, $order->get_id());
                    $status = !is_wp_error($supplier_item) ? $supplier_item['status'] : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html($product_sku); ?></td>
                        <td><?php echo esc_html($product->get_name()); ?></td>
                        <td><?php echo esc_html($item->get_quantity()); ?></td>
                        <td>
                            <select id="item_status_<?php echo esc_attr($product_sku); ?>" name="item_status">
                                <option value="pending supplier" <?php selected($status, 'pending supplier'); ?>><?php esc_html_e('Pending Supplier', 'bidfood'); ?></option>
                                <option value="supplier approved" <?php selected($status, 'supplier approved'); ?>><?php esc_html_e('Supplier Approved', 'bidfood'); ?></option>
                                <option value="supplier canceled" <?php selected($status, 'supplier canceled'); ?>><?php esc_html_e('Supplier Canceled', 'bidfood'); ?></option>
                            </select>
                        </td>
                        <td>
                            <button class="button button-primary" onclick="changeItemStatus(<?php echo esc_attr($product_sku); ?>, <?php echo esc_attr($order->get_id()); ?>)">
                                <?php esc_html_e('Update Status', 'bidfood'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    // Handle AJAX request for changing item status
    public function change_item_status() {
        check_ajax_referer('change_item_status_nonce', 'security');

        $item_id = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : '';
        $order_id = isset($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$item_id || !$order_id || !$status) {
            wp_send_json_error(__('Invalid data.', 'bidfood'));
        }

        $result = UserSupplierManager::update_item_status($item_id, $order_id, $status);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(__('Status updated successfully.', 'bidfood'));
    }
}