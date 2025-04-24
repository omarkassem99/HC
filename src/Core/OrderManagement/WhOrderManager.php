<?php

namespace Bidfood\Core\OrderManagement;

use Bidfood\Core\UserManagement\UserDriverManager;
use Elementor\Data\V2\Base\Endpoint\Index;
use WP_Error;

class WhOrderManager
{
    /**
     * Updates the warehouse order status in the database.
     *
     * @param int $wh_order_id
     * @param string $status
     * @return true|WP_Error
     */
    public static function update_wh_order_status($wh_order_id, $status)
    {
        global $wpdb;

        // Check if the current status is the same as the new status
        $current_status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wh_order_status FROM {$wpdb->prefix}neom_wh_order WHERE id = %d",
                $wh_order_id
            )
        );

        if ($current_status === $status) {
            return true; // No need to update if status hasn't changed
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_wh_order",
            ['wh_order_status' => $status],
            ['id' => $wh_order_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update the status.', 'bidfood'));
        }

        // Trigger the action to notify parties about the WH order status change
        do_action('wh_order_status_changed', $wh_order_id, $status);

        // Sync WooCommerce order status
        $wh_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}neom_wh_order WHERE id = %d",
                $wh_order_id
            ),
            ARRAY_A
        );

        if ($wh_order && !empty($wh_order['order_id'])) {
            self::sync_wc_order_status($wh_order['order_id'], $status);
        }

        // Fetch the driver order that is not 'Skipped', 'Skipped by WH', or 'Cancelled'
        $driver_order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT driver_order_id FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d AND status NOT IN ('Skipped', 'Skipped by WH', 'Cancelled')",
                $wh_order_id
            )
        );

        if ($driver_order_id) {
            // Fetch current driver order status
            $current_driver_status = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT status FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
                    $driver_order_id
                )
            );

            if ($status == 'Dispatched' && $current_driver_status == 'Pending') {
                UserDriverManager::updateDriverOrderStatus($driver_order_id, 'Dispatched');
            } elseif ($status == 'Delivered' && $current_driver_status == 'Dispatched') {
                UserDriverManager::updateDriverOrderStatus($driver_order_id, 'Delivered');
            } 
        }

        return true;
    }

    /**
     * Syncs the WooCommerce order status based on warehouse order status.
     *
     * @param int $wc_order_id WooCommerce order ID
     * @param string $wh_status Warehouse order status
     * @return void
     */
    public static function sync_wc_order_status($wc_order_id, $wh_status)
    {        
        $order = wc_get_order($wc_order_id);
        if (!$order) {
            return;
        }
        // Check for status change to Draft or Ready for Driver Assignment
        if ($wh_status === 'Draft' || $wh_status === 'Ready for Driver Assignment') {
            global $wpdb;
            
            // Get the WH order ID first
            $wh_order = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}neom_wh_order WHERE order_id = %d",
                    $wc_order_id
                ),
                ARRAY_A
            );

            if ($wh_order) {
                // Check if there's an existing driver assignment
                $existing_driver_order = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d",
                        $wh_order['id']
                    )
                );

                if ($existing_driver_order > 0) {
                    // Only update driver order status if there was a driver assigned
                    $wpdb->update(
                        "{$wpdb->prefix}neom_driver_orders",
                        ['status' => 'Skipped by WH'],
                        ['wh_order_id' => $wh_order['id']],
                        ['%s'],
                        ['%d']
                    );
                }
            }

            // Update WooCommerce order status to "Received at BF WH"
            $order->update_status('received-at-bf-wh', __('Order received at BF warehouse', 'bidfood'));
            return; // Exit early since we've already updated the status
        }

        switch ($wh_status) {
            case 'Assigned to Driver':
                $order->update_status('ready-for-deliver', __('Order assigned to driver', 'bidfood'));
                break;
            case 'Dispatched':
                $order->update_status('on-route', __('Order is on route', 'bidfood'));
                break;
            case 'Delivered':
                $order->update_status('delivered', __('Order has been delivered', 'bidfood'));
                break;
        }

        error_log("New WC order status: " . $order->get_status());
    }

    /**
     * Checks if a WH Order already exists for the given WooCommerce order ID.
     *
     * @param int $order_id
     * @return bool
     */
    public static function wh_order_exists($order_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_wh_order';
        $query = $wpdb->prepare("SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d", $order_id);

        return $wpdb->get_var($query) > 0;
    }

    /**
     * Retrieves WH orders with pagination and search.
     *
     * @param int $offset
     * @param int $limit
     * @param string $search
     * @return array
     */
    public static function get_wh_orders($offset = 0, $limit = 10, $search = '', $status = 'All')
    {
        global $wpdb;

        $query = "
        SELECT wh_order.*, users.display_name AS user_name, users.user_email
        FROM {$wpdb->prefix}neom_wh_order AS wh_order
        LEFT JOIN {$wpdb->users} AS users ON wh_order.user_id = users.ID
    ";

        $args = [];

        // Add status filter if needed
        if ($status !== 'All') {
            $query .= " WHERE wh_order.wh_order_status = %s";
            $args[] = $status;
        }

        // Add search filter
        if (!empty($search)) {
            $query .= (!empty($args) ? ' AND' : ' WHERE') . "
            (wh_order.order_id LIKE %s OR wh_order.wh_order_status LIKE %s OR users.display_name LIKE %s OR users.user_email LIKE %s)
        ";
            $args = array_merge($args, array_fill(0, 4, '%' . $wpdb->esc_like($search) . '%'));
        }

        $query .= " ORDER BY wh_order.id DESC LIMIT %d OFFSET %d";
        $args[] = $limit;
        $args[] = $offset;

        // Prepare and execute query
        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Counts the total number of WH orders based on search criteria.
     *
     * @param string $search
     * @return int
     */
    public static function count_wh_orders($search = '', $status = 'All')
    {
        global $wpdb;

        $query = "
        SELECT COUNT(*)
        FROM {$wpdb->prefix}neom_wh_order AS wh_order
        LEFT JOIN {$wpdb->users} AS users ON wh_order.user_id = users.ID
    ";

        $args = [];

        // Filter by status if not 'All'
        if ($status !== 'All') {
            $query .= " WHERE wh_order.wh_order_status = %s";
            $args[] = $status;
        }

        // Apply search filter if available
        if (!empty($search)) {
            $query .= (!empty($args) ? ' AND' : ' WHERE') . "
            (wh_order.order_id LIKE %s
            OR wh_order.wh_order_status LIKE %s
            OR users.display_name LIKE %s
            OR users.user_email LIKE %s)
        ";
            $args = array_merge($args, array_fill(0, 4, '%' . $wpdb->esc_like($search) . '%'));
        }

        // Prepare and execute query
        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Counts the number of orders based on status.
     * 
     * @param string $status The status of the orders ('All', 'Draft', 'Ready for Driver Assignment', 'Dispatched', 'Delivered')
     * @param string $search Search keyword.
     * @return int
     */
    public static function count_orders_by_status($status = 'All', $search = '')
    {
        global $wpdb;

        // Build the base query
        $query = "
        SELECT COUNT(*)
        FROM {$wpdb->prefix}neom_wh_order AS wh_order
        LEFT JOIN {$wpdb->users} AS users ON wh_order.user_id = users.ID
    ";

        $args = [];

        // Filter by status if not 'All'
        if ($status !== 'All') {
            $query .= " WHERE wh_order.wh_order_status = %s";
            $args[] = $status;
        }

        // Apply search filter if available
        if (!empty($search)) {
            $query .= (!empty($args) ? ' AND' : ' WHERE') . "
            (wh_order.order_id LIKE %s
            OR wh_order.wh_order_status LIKE %s
            OR users.display_name LIKE %s
            OR users.user_email LIKE %s)
        ";
            $args = array_merge($args, array_fill(0, 4, '%' . $wpdb->esc_like($search) . '%'));
        }

        // Prepare and execute query
        if (!empty($args)) {
            $query = $wpdb->prepare($query, $args);
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Retrieves WH order items based on the given WH order ID.
     *
     * @param int $wh_order_id
     * @return int|null array of WH order items
     */
    public static function count_wh_order_items($wh_order_id, $search = '')
    {
        global $wpdb;

        // Base query
        $query = "
        SELECT COUNT(*)
        FROM {$wpdb->prefix}neom_wh_order_items AS items
        LEFT JOIN {$wpdb->posts} AS products 
            ON items.item_id = products.ID
        LEFT JOIN {$wpdb->postmeta} AS products_meta 
            ON products.ID = products_meta.post_id
            AND products_meta.meta_key = '_sku'
        WHERE items.wh_order_id = %d
    ";

        $args = [$wh_order_id];

        // Search filter
        if (!empty($search)) {
            $query .= " AND (
            items.item_id LIKE %s OR 
            items.po_id LIKE %s OR 
            items.customer_notes LIKE %s OR 
            items.supplier_id LIKE %s OR 
            items.uom_id LIKE %s OR 
            items.wh_manager_note LIKE %s OR 
            items.expected_delivery_date LIKE %s OR 
            products.post_title LIKE %s OR 
            products_meta.meta_value LIKE %s
        )";
            $args = array_merge($args, array_fill(0, 9, '%' . $wpdb->esc_like($search) . '%'));
        }

        return (int) $wpdb->get_var($wpdb->prepare($query, $args));
    }

    /**
     * Retrieves paginated WH order items based on search criteria.
     *
     * @param int $wh_order_id
     * @param int $offset
     * @return array|null array of WH order items based on search criteria
     */
    public static function get_paginated_wh_order_items($wh_order_id, $offset = 0, $limit = 10, $search = '')
    {
        global $wpdb;

        // Base query
        $query = "
        SELECT 
            items.*, 
            products.post_title AS product_name,
            products_meta.meta_value AS product_sku
        FROM {$wpdb->prefix}neom_wh_order_items AS items
        LEFT JOIN {$wpdb->posts} AS products 
            ON items.item_id = products.ID
        LEFT JOIN {$wpdb->postmeta} AS products_meta 
            ON products.ID = products_meta.post_id
            AND products_meta.meta_key = '_sku'
        WHERE items.wh_order_id = %d
    ";

        $args = [$wh_order_id];

        // Search filter
        if (!empty($search)) {
            $query .= " AND (
            items.item_id LIKE %s OR 
            items.po_id LIKE %s OR 
            items.customer_notes LIKE %s OR 
            items.supplier_id LIKE %s OR 
            items.uom_id LIKE %s OR 
            items.wh_manager_note LIKE %s OR 
            items.expected_delivery_date LIKE %s OR 
            products.post_title LIKE %s OR 
            products_meta.meta_value LIKE %s
        )";
            $args = array_merge($args, array_fill(0, 9, '%' . $wpdb->esc_like($search) . '%'));
        }

        // Order and pagination
        $query .= " ORDER BY items.id DESC LIMIT %d OFFSET %d";
        $args[] = $limit;
        $args[] = $offset;
        return $wpdb->get_results($wpdb->prepare($query, $args), ARRAY_A);
    }

    /**
     * Retrieves a warehouse order by its WooCommerce order ID.
     *
     * @param int $wc_order_id
     * @return array|null
     */
    public static function get_order_by_wc_order_id($wc_order_id)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_wh_order';
        $query = $wpdb->prepare("SELECT * FROM {$table_name} WHERE order_id = %d", $wc_order_id);

        if ($wpdb->get_row($query) === null) {
            return wp_send_json(['error' => 'No warehouse order found for the given WooCommerce order ID.']);
        }
        return $wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Retrieves all warehouse orders.
     *
     * @return array|null
     */
    public static function get_all_wh_orders()
    {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}neom_wh_order";

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Creates a warehouse order in the database.
     *
     * @param array $data
     * @return int|WP_Error
     */
    public static function create_wh_order($data)
    {
        global $wpdb;

        $insert_result = $wpdb->insert(
            "{$wpdb->prefix}neom_wh_order",
            [
                'order_id' => $data['order_id'],
                'user_id' => $data['user_id'],
                'wh_order_status' => $data['wh_order_status'],
                'wh_order_note' => $data['wh_order_note'],
            ],
            [
                '%d',
                '%d',
                '%s',
                '%s',
            ]
        );

        if ($insert_result === false) {
            return new WP_Error('db_error', 'Failed to create warehouse order.');
        }

        return $wpdb->insert_id;
    }

    /**
     * Adds an item to a warehouse order.
     *
     * @param array $item_data
     * @return true|WP_Error
     */
    public static function add_items_to_wh_order($item_data)
    {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_wh_order_items",
            [
                'wh_order_id' => $item_data['wh_order_id'],
                'item_id' => $item_data['item_id'],
                'po_id' => $item_data['po_id'],
                'supplier_id' => $item_data['supplier_id'],
                'uom_id' => $item_data['uom_id'],
                'customer_requested_amount' => $item_data['customer_requested_amount'],
                'customer_delivery_date' => $item_data['customer_delivery_date'],
                'supplier_delivery_date' => $item_data['supplier_delivery_date'],
                'expected_delivery_date' => $item_data['expected_delivery_date'],
                'wh_confirmed_amount' => $item_data['wh_confirmed_amount'],
                'customer_notes' => $item_data['customer_notes'],
                'wh_manager_note' => $item_data['wh_manager_note'],
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
            ]
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', __('Failed to insert order item into the database.', 'bidfood'));
        }

        return true;
    }

    /**
     * Updates a warehouse order item in the database.
     *
     * @param int $wh_order_item_id
     * @param float $confirmed_amount
     * @param string $manager_note
     * @return true|WP_Error
     */
    public static function update_wh_order_item($wh_order_item_id, $confirmed_amount, $manager_note)
    {
        if ($confirmed_amount < 0) {
            return new WP_Error('invalid_amount', __('Confirmed amount cannot be negative.', 'bidfood'));
        }

        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_wh_order_items",
            [
                'wh_confirmed_amount' => $confirmed_amount,
                'wh_manager_note' => $manager_note,
            ],
            ['id' => $wh_order_item_id],
            ['%f', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_update_error', __('Failed to update the warehouse order item.', 'bidfood'));
        }

        return true;
    }

    /**
     * Updates the warehouse order note in the database.
     *
     * @param int $wh_order_id
     * @param string $wh_order_note
     * @return true|WP_Error if update was successful or false otherwise
     */
    public static function update_wh_order_note($wh_order_id, $wh_order_note)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_wh_order",
            ['wh_order_note' => $wh_order_note],
            ['id' => $wh_order_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_update_error', __('Failed to update warehouse order note.', 'bidfood'));
        }

        return true;
    }

    /**
     * Retrieves WH orders by their IDs.
     *
     * @param array $wh_order_ids Array of WH order IDs.
     * @return array
     */
    public static function get_wh_orders_by_ids($wh_order_ids)
    {
        global $wpdb;
        // Ensure the input is an array
        if (!is_array($wh_order_ids)) {
            // Attempt to convert a single ID (string or int) into an array
            $wh_order_ids = is_scalar($wh_order_ids) ? [(int) $wh_order_ids] : [];
        }

        // If the array is still empty, return an empty result
        if (empty($wh_order_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($wh_order_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_wh_order WHERE id IN ($placeholders)",
            $wh_order_ids
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Converts WH orders to driver orders.
     *
     * @param array $wh_order_ids Array of WH order IDs.
     * @param int $driver_id Driver ID to assign the orders to.
     * @return true|WP_Error
     */
    public static function convert_to_driver_orders($wh_order_ids, $driver_id)
    {
        global $wpdb;

        // Fetch the selected WH orders
        $wh_orders = self::get_wh_orders_by_ids($wh_order_ids);
        if (empty($wh_orders)) {
            return new WP_Error('no_orders_found', __('No WH orders found for the given IDs.', 'bidfood'));
        }

            // Validate WH order status
            for ( $i = 0; $i < count($wh_orders); $i++) {
            $wh_order = $wh_orders[$i];
            $order_status = sanitize_text_field($wh_order['wh_order_status']); 
            if ($order_status !== 'Ready for Driver Assignment' && $order_status !== 'Assigned to Driver') {
                return new WP_Error(
                    'invalid_wh_status',
                    sprintf(__('WH order #%d is not in the required status "Ready for Driver Assignment".', 'bidfood'), $wh_order['id'])
                );
            }
            }

        // If all validations pass, proceed with conversion
        foreach ($wh_orders as $wh_order) {
            // Fetch the current driver ID
            $current_driver_id = self::get_assigned_driver_id($wh_order['id']);

            // Check if the new driver is the same as the current driver
            if ($current_driver_id == $driver_id) {
                return new WP_Error('duplicate_driver', __('The new driver is the same as the current driver. Please select a different driver.', 'bidfood'));
            }

            // Mark any existing active driver orders as skipped
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}neom_driver_orders 
                 SET status = %s 
                 WHERE wh_order_id = %d 
                   AND status NOT IN (%s, %s)",
                    'Skipped by WH',
                    $wh_order['id'],
                    'Skipped',
                    'Skipped by WH'
                )
            );

            // Create new driver order
            $insert_result = $wpdb->insert(
                "{$wpdb->prefix}neom_driver_orders",
                [
                    'driver_id' => $driver_id,
                    'wh_order_id' => $wh_order['id'],
                    'status' => 'Pending'
                ],
                [
                    '%d',
                    '%d',
                    '%s'
                ]
            );

            if ($insert_result === false) {
                return new WP_Error('db_insert_error', __('Failed to create driver order.', 'bidfood'));
            }

            $driver_order_id = $wpdb->insert_id;

            // Copy items from WH order to driver order
            $wh_order_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}neom_wh_order_items WHERE wh_order_id = %d",
                    $wh_order['id']
                ),
                ARRAY_A
            );

            foreach ($wh_order_items as $item) {
                if ($item['wh_confirmed_amount'] <= 0) {
                    continue;
                }
                $wpdb->insert(
                    "{$wpdb->prefix}neom_driver_order_items",
                    [
                        'driver_order_id' => $driver_order_id,
                        'item_id' => $item['item_id'],
                        'uom_id' => $item['uom_id'],
                        'customer_delivery_date' => $item['customer_delivery_date'],
                        'expected_delivery_date' => $item['expected_delivery_date'],
                        'amount' => $item['wh_confirmed_amount'], // Assuming amount is the confirmed amount
                        'customer_requested_amount' => $item['customer_requested_amount'],
                        'status' => 'Pending'
                    ],
                    [
                        '%d',
                        '%d',
                        '%s',
                        '%s',
                        '%s',
                        '%f',
                        '%f',
                        '%s'
                    ]
                );
            }

            // Update WH order status
            $status_result = self::update_wh_order_status($wh_order['id'], 'Assigned to Driver');
            if (is_wp_error($status_result)) {
                error_log("Failed to update WH order status: " . $status_result->get_error_message());
                return $status_result;
            }

            // Trigger the action to notify parties about the driver order status change
        }
        do_action('driver_order_status_changed', $driver_order_id, 'Pending');

        return true;
    }


    /**
     * Retrieves the assigned driver ID for a given WH order ID.
     *
     * @param int $wh_order_id
     * @return int|null The driver ID if assigned, or null if not assigned.
     */
    public static function get_assigned_driver_id($wh_order_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT driver_id FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d AND status NOT IN ('Skipped', 'Skipped by WH') LIMIT 1",
            $wh_order_id
        );
        error_log("Query: " . $wpdb->get_var($query)); // Log the query for
        return $wpdb->get_var($query);
    }

    /**
     * Changes the assigned driver for a WH order
     *
     * @param int $wh_order_id
     * @param int $new_driver_id
     * @return true|WP_Error
     */
    public static function change_driver_assignment($wh_order_id, $new_driver_id)
    {
        global $wpdb;

        // Get current driver order
        $current_driver_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d",
                $wh_order_id
            ),
            ARRAY_A
        );

        if (!$current_driver_order) {
            return new WP_Error('no_driver_order', __('No driver order found for this WH order.', 'bidfood'));
        }

        // Mark any existing active driver orders as skipped by WH
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}neom_driver_orders 
             SET status = %s 
             WHERE wh_order_id = %d 
               AND status NOT IN (%s, %s)",
                'Skipped by WH',
                $wh_order_id,
                'Skipped',
                'Skipped by WH'
            )
        );
        // Create new driver order
        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_driver_orders",
            [
                'driver_id' => $new_driver_id,
                'wh_order_id' => $wh_order_id,
                'status' => 'Pending'
            ],
            [
                '%d',
                '%d',
                '%s'
            ]
        );

        if ($result === false) {
            return new WP_Error('db_insert_error', __('Failed to create new driver order.', 'bidfood'));
        }

        $new_driver_order_id = $wpdb->insert_id;

        // Copy items from old driver order to new one
        $old_items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}neom_driver_order_items WHERE driver_order_id = %d",
                $current_driver_order['id']
            ),
            ARRAY_A
        );

        foreach ($old_items as $item) {
            $wpdb->insert(
                "{$wpdb->prefix}neom_driver_order_items",
                [
                    'driver_order_id' => $new_driver_order_id,
                    'item_id' => $item['item_id'],
                    'uom_id' => $item['uom_id'],
                    'customer_delivery_date' => $item['customer_delivery_date'],
                    'expected_delivery_date' => $item['expected_delivery_date'],
                    'amount' => $item['amount'],
                    'customer_requested_amount' => $item['customer_requested_amount'],
                    'status' => 'Pending'
                ],
                [
                    '%d',
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%f',
                    '%f',
                    '%s'
                ]
            );
        }

 

        // Update WH order status to "Assigned to Driver"
        self::update_wh_order_status($wh_order_id, 'Assigned to Driver');
      
        return true;
    }

    /**
     * Removes driver assignment from a WH order by marking it as skipped
     *
     * @param int $wh_order_id
     * @return true|WP_Error
     */
    public static function remove_driver_assignment($wh_order_id)
    {
        global $wpdb;

        error_log("Attempting to remove driver assignment for WH Order ID: " . $wh_order_id);

        // Get current driver order without status filter
        $current_driver_order = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT driver_order_id FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d AND status NOT IN ('Skipped', 'Skipped by WH')",
                $wh_order_id
            ),
            ARRAY_A
        );
        error_log("Current driver order: " . print_r($current_driver_order, true));

        if (!$current_driver_order) {
            error_log("No active driver order found for WH Order ID: " . $wh_order_id);
            return new WP_Error('no_driver_order', __('No active driver order found for this WH order.', 'bidfood'));
        }

        // Update driver order status to Skipped by WH
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders",
            ['status' => 'Skipped by WH'],
            ['driver_order_id' => $current_driver_order['driver_order_id']],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            error_log("Error updating driver order status: " . $wpdb->last_error);
            return new WP_Error('update_error', __('Failed to update driver order status.', 'bidfood'));
        }
        // Update WH order status to "Ready for Driver Assignment"
        error_log("Successfully updated driver order status, updating WH order status");
        // Trigger the action to notify parties about the driver order status change
        do_action('driver_order_status_changed', $current_driver_order['driver_order_id'], 'Skipped by WH');
        return self::update_wh_order_status($wh_order_id, 'Ready for Driver Assignment');
    }

    /**
     * Get the active driver order for a WH order
     *
     * @param int $wh_order_id
     * @return array|null Driver order data or null if not found
     */
    public static function get_active_driver_order($wh_order_id)
    {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}neom_driver_orders 
                WHERE wh_order_id = %d 
                AND status NOT IN ('Skipped', 'Skipped by WH')",
                $wh_order_id
            ),
            ARRAY_A
        );
    }
}
