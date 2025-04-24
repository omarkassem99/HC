<?php

namespace Bidfood\Admin\NeomSettings\Orders;
use Bidfood\Core\OrderManagement\WhOrderManager;
use Bidfood\Core\UserManagement\UserDriverManager;
class WhReceivedOrderItems
{
    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_update_wh_order_item', [$this, 'update_wh_order_item_handler']);
        add_action('wp_ajax_save_wh_order_note', [$this, 'save_wh_order_note_handler']);
        add_action('wp_ajax_update_wh_order_status', [$this, 'update_wh_order_status_handler']);
        add_action('wp_ajax_convert_to_driver_order', [$this, 'convert_to_driver_order_handler']);
        add_action('wp_ajax_change_driver_assignment', [$this, 'change_driver_assignment_handler']);
        add_action('wp_ajax_remove_driver_assignment', [$this, 'remove_driver_assignment_handler']);
    }
    public static function init()
    {
        return new self;
    }
    public function enqueue_assets()
    {
        // Enqueue styles
        wp_enqueue_style(
            'admin-wh-received-order-items-css',
            plugins_url('/assets/css/Orders/wh-received-order-items.css', dirname(__FILE__, 4))
        );

        // Enqueue JavaScript for converting orders to driver orders
        wp_enqueue_script(
            'admin-wh-received-order-items-js',
            plugins_url('/assets/js/Orders/wh-received-order-items.js', dirname(__FILE__, 4)),
            ['jquery'],
            null,
            true
        );

        // Pass AJAX data for various actions
        wp_localize_script('admin-wh-received-order-items-js', 'bidfoodWhOrderItemsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'update_item_nonce' => wp_create_nonce('update_wh_order_item_nonce'),
            'save_note_nonce' => wp_create_nonce('save_wh_order_note_nonce'),
            'update_status_nonce' => wp_create_nonce('update_wh_order_status_nonce'),
            'convert_order_nonce' => wp_create_nonce('convert_to_driver_order_action'),
            'change_driver_nonce' => wp_create_nonce('change_driver_assignment_nonce'),
            'remove_driver_nonce' => wp_create_nonce('remove_driver_assignment_nonce'),
            'base_url' => admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details'),
            'order_id' => isset($_GET['order_id']) ? intval($_GET['order_id']) : 0,
            'confirm_change_driver' => __('Are you sure you want to change the driver for this order?', 'bidfood'),
            'confirm_remove_driver' => __('Are you sure you want to remove the driver from this order?', 'bidfood'),
        ]);
    }
    public static function update_wh_order_item_handler()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_wh_order_item_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
        }

        // Validate and sanitize input
        $wh_item_id = isset($_POST['wh_item_id']) ? intval($_POST['wh_item_id']) : 0;
        $wh_confirmed_amount = isset($_POST['wh_confirmed_amount']) ? floatval($_POST['wh_confirmed_amount']) : 0;
        $wh_manager_note = isset($_POST['wh_manager_note']) ? sanitize_text_field($_POST['wh_manager_note']) : '';

        if (!$wh_item_id) {
            wp_send_json_error(['message' => __('Invalid item ID.', 'bidfood')]);
        }
        // Backend validation: prevent negative numbers
        if ($wh_confirmed_amount < 0) {
            wp_send_json_error(['message' => __('Confirmed amount cannot be negative.', 'bidfood')]);
        }

        // Update database using WhOrderManager
        $result = WhOrderManager::update_wh_order_item($wh_item_id, $wh_confirmed_amount, $wh_manager_note);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Item updated successfully.', 'bidfood')]);
    }

    public static function save_wh_order_note_handler()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'save_wh_order_note_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
        }

        // Validate and sanitize inputs
        $wh_order_id = isset($_POST['wh_order_id']) ? intval($_POST['wh_order_id']) : 0;
        $wh_order_note = isset($_POST['wh_order_note']) ? sanitize_text_field($_POST['wh_order_note']) : '';

        if (!$wh_order_id) {
            wp_send_json_error(['message' => __('Invalid WH Order ID.', 'bidfood')]);
        }

        // Update the WH order note in the database
        $result = WhOrderManager::update_wh_order_note($wh_order_id, sanitize_text_field($wh_order_note));

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Warehouse order note updated successfully.', 'bidfood')]);
    }

    public static function update_wh_order_status_handler()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_wh_order_status_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
        }

        $wh_order_id = isset($_POST['wh_order_id']) ? intval($_POST['wh_order_id']) : 0;
        $wh_order_status = isset($_POST['wh_order_status']) ? sanitize_text_field($_POST['wh_order_status']) : '';

        if (!$wh_order_id || !$wh_order_status) {
            wp_send_json_error(['message' => __('Invalid WH Order ID or Status.', 'bidfood')]);
        }

        // Get current status
        global $wpdb;
        $current_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wh_order_status FROM {$wpdb->prefix}neom_wh_order WHERE id = %d",
                $wh_order_id
            )
        );

        if ($current_status === $wh_order_status) {
            wp_send_json_error([
                'message' => sprintf(__('Order is already in %s status.', 'bidfood'), $wh_order_status),
                'already_assigned' => true
            ]);
            return;
        }
      
        $result = WhOrderManager::update_wh_order_status($wh_order_id, $wh_order_status);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        wp_send_json_success(['message' => __('Warehouse order status updated successfully.', 'bidfood')]);
    }

    public static function convert_to_driver_order_handler()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'convert_to_driver_order_action')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
            return;
        }

        $wh_order_id = isset($_POST['wh_order_id']) ? intval($_POST['wh_order_id']) : 0;
        $driver_id = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;

        if (!$wh_order_id) {
            wp_send_json_error(['message' => __('Invalid WH Order ID.', 'bidfood')]);
            return;
        }

        // Fetch the current driver ID
        $current_driver_id = WhOrderManager::get_assigned_driver_id($wh_order_id);

        // Check if the new driver is the same as the current driver
        if ($current_driver_id == $driver_id) {
            wp_send_json_error(['message' => __('The new driver is the same as the current driver. Please select a different driver.', 'bidfood')]);
            return;
        }

        // If driver_id is empty (0), it means we want to remove the driver
        if ($driver_id === 0) {
            $result = WhOrderManager::remove_driver_assignment($wh_order_id);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
                return;
            }
            wp_send_json_success(['message' => __('Driver removed successfully.', 'bidfood')]);
            return;
        }

        // Otherwise, assign the driver
        $result = WhOrderManager::convert_to_driver_orders([$wh_order_id], $driver_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        // Trigger email notifications for driver order status change
        do_action('driver_order_status_changed', $wh_order_id, 'Pending');

        wp_send_json_success(['message' => __('Driver assigned successfully.', 'bidfood')]);
    }

    public static function change_driver_assignment_handler()
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'change_driver_assignment_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
        }

        $wh_order_id = isset($_POST['wh_order_id']) ? intval($_POST['wh_order_id']) : 0;
        $new_driver_id = isset($_POST['new_driver_id']) ? intval($_POST['new_driver_id']) : 0;

        if (!$wh_order_id || !$new_driver_id) {
            wp_send_json_error(['message' => __('Invalid WH Order ID or Driver ID.', 'bidfood')]);
        }

        // Fetch the current driver ID
        $current_driver_id = WhOrderManager::get_assigned_driver_id($wh_order_id);

        // Check if the new driver is the same as the current driver
        if ($current_driver_id == $new_driver_id) {
            wp_send_json_error(['message' => __('The new driver is the same as the current driver. Please select a different driver.', 'bidfood')]);
            return;
        }

        $result = WhOrderManager::change_driver_assignment($wh_order_id, $new_driver_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        // Trigger email notifications for driver order status change
        do_action('driver_order_status_changed', $wh_order_id, 'Pending');
        wp_send_json_success(['message' => __('Driver changed successfully.', 'bidfood')]);
    }
    public static function remove_driver_assignment_handler()
    {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'remove_driver_assignment_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce.', 'bidfood')]);
        }

        $wh_order_id = isset($_POST['wh_order_id']) ? intval($_POST['wh_order_id']) : 0;

        if (!$wh_order_id) {
            wp_send_json_error(['message' => __('Invalid WH Order ID.', 'bidfood')]);
        }

        $result = WhOrderManager::remove_driver_assignment($wh_order_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Trigger email notifications for WH order status change
        do_action('wh_order_status_changed', $wh_order_id, 'Ready for Driver Assignment');
        wp_send_json_success(['message' => __('Driver removed successfully.', 'bidfood')]);
    }

    public static function render_order_details()
    {
        // Check if the user has the required capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You are not allowed to access this page.', 'bidfood'));
        }

        // Get the order ID from the URL query parameters
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if (!$order_id) {
            echo '<div class="notice notice-error"><p>' . __('Invalid order ID.', 'bidfood') . '</p></div>';
            return;
        }

        // Fetch WooCommerce order data
        $wcOrder = wc_get_order($order_id);

        if (!$wcOrder) {
            echo '<div class="notice notice-error"><p>' . __('Order not found.', 'bidfood') . '</p></div>';
            return;
        }

        // Fetch WH order data
        $whOrder = WhOrderManager::get_order_by_wc_order_id($order_id);

        // Fetch WH order items
        $whOrderItems = [];
        $totalItems = 1;
        if ($whOrder) {
            $wh_order_id = isset($whOrder['id']) ? intval($whOrder['id']) : 0;
            $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
            $limit = isset($_GET['items_per_page']) ? intval($_GET['items_per_page']) : 3; // Items per page

            // Fetch items and count
            $whOrderItems = WhOrderManager::get_paginated_wh_order_items($wh_order_id, $offset, $limit, $search);
            $totalItems = WhOrderManager::count_wh_order_items($wh_order_id, $search);
            // Construct URL with items_per_page
            $baseUrl = admin_url('admin.php?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_order_details');
            $baseUrlWithItemsPerPage = add_query_arg('items_per_page', $limit, $baseUrl);
        }

        // Fetch assigned driver
        $assignedDriverId = 0;
        if ($whOrder) {
            $assignedDriverId = WhOrderManager::get_assigned_driver_id($whOrder['id']);
        }

        // Map WooCommerce order items for quick lookup
        $wcOrderItems = [];
        foreach ($wcOrder->get_items() as $item_id => $item) {
            $wcOrderItems[$item_id] = [
                'name' => $item->get_name(),
                'total' => $item->get_total(),
                'quantity' => $item->get_quantity(),
            ];
        }
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
                border-top: 16px solid #3498db;
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

        <div class="wrap">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1><?php _e('Order Details', 'bidfood'); ?></h1>
                <?php if ($whOrder && $whOrder['wh_order_status'] !== 'Draft') : ?>
                    <?php self::render_driver_assignment_form($whOrder, $order_id); ?>
                <?php endif; ?>
                <div id="wh_order_note_div" style="display:flex; gap: 10px;">
                    <form action="" style="display: flex; gap: 10px; align-items: center;">
                        <label for="wh_order_note">WH Order Note:</label>
                        <textarea id="wh_order_note" name="wh_order_note" style="height: fit-content;"><?php echo esc_html($whOrder['wh_order_note']); ?></textarea>
                        <input type="hidden" name="wh_order_id" value="<?php echo esc_attr($whOrder['id'] ?? ''); ?>">
                        <button class="button button-primary save-wh-order-note" id="save-wh-order-note">Submit Note</button>
                    </form>
                </div>

                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <?php if ($whOrder) : ?>
                        <form method="post" id="update-wh-order-status-form">
                            <label for="wh_order_status"><?php _e('Change WH Order Status:', 'bidfood'); ?></label>

                            <select name="wh_order_status" id="wh_order_status" style="width: 100px;" <?php echo $whOrder['wh_order_status'] === 'Delivered' ? 'disabled' : ''; ?>>
                                <option value="Draft" <?php selected($whOrder['wh_order_status'], 'Draft'); ?>><?php _e('Draft', 'bidfood'); ?></option>
                                <option value="Ready for Driver Assignment" <?php selected($whOrder['wh_order_status'], 'Ready for Driver Assignment'); ?>><?php _e('Ready for Driver Assignment', 'bidfood'); ?></option>
                                <option value="Assigned to Driver" <?php selected($whOrder['wh_order_status'], 'Assigned to Driver'); ?>><?php _e('Assigned to Driver', 'bidfood'); ?></option>
                                <option value="Dispatched" <?php selected($whOrder['wh_order_status'], 'Dispatched'); ?>><?php _e('Dispatched', 'bidfood'); ?></option>
                                <option value="Delivered" <?php selected($whOrder['wh_order_status'], 'Delivered'); ?>><?php _e('Delivered', 'bidfood'); ?></option>
                            </select>
                            <input type="hidden" id="wh_order_id" value="<?php echo esc_attr($whOrder['id'] ?? 0); ?>">
                            <?php if ($whOrder['wh_order_status'] != 'Delivered') { ?>
                                <button type="button" class="button button-primary" id="update-wh-order-status"><?php _e('Update Status', 'bidfood'); ?></button>
                            <?php } ?>
                        </form>
                    <?php else : ?>
                        <div class="notice notice-error">
                            <p> <?php _e('No warehouse order associated with this WooCommerce order.', 'bidfood'); ?> </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top:24px">
                <h2><?php _e('Order Information', 'bidfood'); ?></h2>
                <?php if ($whOrder && $whOrder['wh_order_status'] !== 'Draft') : ?>
                    <?php self::render_driver_order_details_button($whOrder, $order_id); ?>
                <?php endif; ?>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <tr>
                    <th><?php _e('Order ID', 'bidfood'); ?></th>
                    <td><?php echo esc_html($wcOrder->get_id()); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Customer Email', 'bidfood'); ?></th>
                    <td><?php echo esc_html($wcOrder->get_billing_email()); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Total', 'bidfood'); ?></th>
                    <td><?php echo wc_price($wcOrder->get_total()); ?></td>
                </tr>
                <tr>
                    <th><?php _e('Customer Order Status', 'bidfood'); ?></th>
                    <td><?php echo esc_html(wc_get_order_status_name($wcOrder->get_status())); ?></td>
                </tr>
            </table>

            <div class="order-items-section" style="display: flex; flex-direction:row; justify-content: space-between; align-items: center; margin-bottom:0px; margin-top:32px">
                <h2 style="font-size:18px; font-weight:bold;">
                    <?php _e('Order Items', 'bidfood'); ?>
                </h2>
                <div class="order-items-search" style="display:flex; flex-direction:row; gap:10px;">
                    <input type="text" id="search" value="<?php echo esc_attr($search); ?>" placeholder="Search...">
                    <button id="search-btn" class="button button-primary">Search</button>
                        <select id="items-per-page" class="items-per-page">
                            <option value="3" <?php selected($limit, 3); ?>>3</option>
                            <option value="6" <?php selected($limit, 6); ?>>6</option>
                            <option value="9" <?php selected($limit, 9); ?>>9</option>
                            <option value="12" <?php selected($limit, 12); ?>>12</option>
                            <option value="15" <?php selected($limit, 15); ?>>15</option>
                        </select>
                    </div>
                </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('PO ID', 'bidfood'); ?></th>
                        <th><?php _e('Product SKU', 'bidfood'); ?></th>
                        <th><?php _e('Product Name', 'bidfood'); ?></th>
                        <th><?php _e('Requested Quantity', 'bidfood'); ?></th>
                        <th><?php _e('Customer Notes', 'bidfood'); ?></th>
                        <th><?php _e('Customer Delivery Date', 'bidfood'); ?></th>
                        <th><?php _e('Supplier Delivery Date', 'bidfood'); ?></th>
                        <th><?php _e('Expected Delivery Date', 'bidfood'); ?></th>
                        <th><?php _e('Total', 'bidfood'); ?></th>
                        <th><?php _e('Confirmed Quantity', 'bidfood'); ?></th>
                        <th><?php _e('Manager Note', 'bidfood'); ?></th>
                        <th><?php _e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($whOrderItems)) : ?>
                        <?php foreach ($whOrderItems as $whItem) : ?>
                            <?php
                            $product_id = intval($whItem['item_id']);
                            $product = wc_get_product($product_id); // Get the product object
                            $product_sku = $product ? $product->get_sku() : __('N/A', 'bidfood');
                            $product_name = $product->get_name() ? $product->get_name() : __('N/A', 'bidfood');
                            $total = $wcOrderItems[$product_id]['total'] ?? __('N/A', 'bidfood');
                            ?>
                            <tr>
                                <td id='po'><?php echo esc_html($whItem['po_id'] ? $whItem['po_id'] : __('N/A', 'bidfood')); ?></td>
                                <td id='product'><?php echo esc_html($product_sku ? $product_sku : __('N/A', 'bidfood')); ?></td>
                                <td><?php echo esc_html($product_name ? $product_name : __('N/A', 'bidfood')); ?></td>
                                <td>
                                    <input type="hidden" style="width:70px" name="customer_requested_amount_<?php echo $whItem['id']; ?>" value="<?php echo esc_attr($whItem['customer_requested_amount']); ?>" class="customer_requested_amount">
                                    <?php echo esc_html($whItem['customer_requested_amount'] ? $whItem['customer_requested_amount'] : __('N/A', 'bidfood')); ?>
                                </td>
                                <td><?php echo esc_html($whItem['customer_notes'] ? $whItem['customer_notes'] : __('N/A', 'bidfood')) ?></td>
                                <td><?php echo esc_html($whItem['customer_delivery_date'] ? $whItem['customer_delivery_date'] : __('N/A', 'bidfood')); ?></td>
                                <td><?php echo esc_html($whItem['supplier_delivery_date'] ? $whItem['supplier_delivery_date'] : __('N/A', 'bidfood')); ?></td>
                                <td><?php echo esc_html($whItem['expected_delivery_date'] ? $whItem['expected_delivery_date'] : __('N/A', 'bidfood')); ?></td>
                                <td><?php echo wc_price($total) ? wc_price($total) : __('N/A', 'bidfood'); ?></td>
                                <td>
                                    <input type="number"
                                        style="width:70px"
                                        name="wh_confirmed_amount_<?php echo $whItem['id']; ?>"
                                        value="<?php echo esc_attr($whItem['wh_confirmed_amount']); ?>"
                                        class="wh_confirmed_amount" <?php echo $whOrder['wh_order_status'] === 'Delivered' || $whOrder['wh_order_status'] === 'Assigned to Driver' || $whOrder['wh_order_status'] === 'Dispatched'  ? 'disabled' : ''; ?>>
                                </td>
                                <td>
                                    <textarea name="wh_manager_note_<?php echo $whItem['id']; ?>"
                                        class="wh_manager_note" <?php echo $whOrder['wh_order_status'] === 'Delivered' ? 'disabled' : '';  ?>><?php echo esc_html($whItem['wh_manager_note']); ?></textarea>
                                </td>
                                <td>
                                    <button class="button button-primary update-wh-item"
                                        data-wh-item-id="<?php echo esc_attr($whItem['id']); ?>" <?php echo $whOrder['wh_order_status'] === 'Delivered' ? 'disabled' : ''; ?>>
                                        <?php _e('Update', 'bidfood'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="12"><?php _e('No items found.', 'bidfood'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination-section" style="display: flex; justify-content: center; gap: 10px; margin-top: 20px;">
                <?php
                $totalPages = ceil($totalItems / $limit); // Calculate total pages
                if ($totalPages > 1) {
                    for ($i = 0; $i < $totalPages; $i++) {
                        $pageOffset = $i * $limit; // Calculate offset for each page
                        $pageLink = add_query_arg([
                            'offset' => $pageOffset,
                            'search' => $search, // Preserve search query
                            'order_id' => $order_id, // Preserve order ID
                        ], $baseUrlWithItemsPerPage);
                        $isActive = ($offset == $pageOffset) ? 'active' : ''; // Highlight active page
                        echo '<a href="' . esc_url($pageLink) . '" class="button primary-button pagination-link ' . esc_attr($isActive) . '" data-offset="' . esc_attr($pageOffset) . '">' . ($i + 1) . '</a>';
                    }
                }
                ?>
            </div>

            <h2 style="margin-top:0px;"><?php _e('Warehouse Information', 'bidfood'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <tr>
                    <th><?php _e('WH Order ID', 'bidfood'); ?></th>
                    <td><?php echo esc_html($whOrder['id'] ?? __('N/A', 'bidfood')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WH Order Status', 'bidfood'); ?></th>
                    <td><?php echo esc_html($whOrder['wh_order_status'] ?? __('N/A', 'bidfood')); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WH Manager Note', 'bidfood'); ?></th>
                    <td><?php echo esc_html($whOrder['wh_order_note']) ? esc_html($whOrder['wh_order_note']) : __('N/A', 'bidfood') ?></td>
                </tr>
            </table>
        </div>
    <?php
    }

    private static function render_driver_assignment_form($whOrder, $order_id)
    {
        // Get active driver order
        $activeDriverOrder = WhOrderManager::get_active_driver_order($whOrder['id']);
        $assignedDriverId = $activeDriverOrder ? $activeDriverOrder['driver_id'] : 0;
    ?>
        <div>
            <form method="post" id="convert-to-driver-order-form">
                <?php wp_nonce_field('convert_to_driver_order_action', '_wpnonce', true); ?>
                <input type="hidden" name="wh_order_id" id="wh_order_id" value="<?php echo esc_attr($whOrder['id']); ?>">
                <input type="hidden" name="wc_order_id" id="wc_order_id" value="<?php echo esc_attr($order_id); ?>">
                <input type="hidden" name="current_driver_id" id="current_driver_id" value="<?php echo esc_attr($assignedDriverId); ?>">
                <label for="driver_id"><?php _e('Select Driver:', 'bidfood'); ?></label>
                <select name="driver_id" id="driver_id" style="width: 200px;" <?php echo $whOrder['wh_order_status'] === 'Delivered' ? 'disabled' : ''; ?>>
                    <option value=""><?php echo $assignedDriverId ? __('Remove Driver', 'bidfood') : __('Select a driver', 'bidfood'); ?></option>
                    <?php
                    // Fetch drivers
                    $drivers = UserDriverManager::getDrivers();
                    foreach ($drivers as $driver) :
                    ?>
                        <option value="<?php echo esc_attr($driver['id']); ?>" <?php selected($driver['id'], $assignedDriverId); ?>>
                            <?php echo esc_html($driver['first_name'] . ' ' . $driver['last_name'] . ' (' . $driver['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($whOrder['wh_order_status'] != 'Delivered') { ?>
                    <button type="submit" class="button button-primary"><?php _e('Convert to Driver Order', 'bidfood'); ?></button>
                <?php } ?>
            </form>
            <div id="loader-overlay" style="display:none;">
                <div id="loader-spinner"></div>
            </div>
        </div>
        <?php
    }

    private static function render_driver_order_details_button($whOrder, $order_id)
    {
        $activeDriverOrder = WhOrderManager::get_active_driver_order($whOrder['id']);
        $assignedDriverId = $activeDriverOrder ? $activeDriverOrder['driver_id'] : 0;
        $driverOrderId = $activeDriverOrder ? $activeDriverOrder['driver_order_id'] : 0;
        if ($assignedDriverId && $driverOrderId) {
        ?>
            <div>
                <a class="button button-warning" href="<?php echo esc_url(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers&drivers_tab=driver-order-details&order_id=' . $driverOrderId)); ?>">
                    <?php _e('View Driver Order', 'bidfood'); ?>
                </a>
            </div>
<?php
        }
    }
}
