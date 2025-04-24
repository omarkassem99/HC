<?php

namespace Bidfood\Core\UserManagement;

use WP_Error;

class UserSupplierManager
{

    /**
     * Check if a user is assigned to a supplier.
     *
     * @param int $user_id - The user ID.
     * @return bool - True if the user is assigned to a supplier, false otherwise.
     */
    public static function is_user_supplier($user_id)
    {
        global $wpdb;

        $supplier_id = $wpdb->get_var($wpdb->prepare(
            "SELECT supplier_id FROM {$wpdb->prefix}neom_user_supplier_relation WHERE user_id = %d",
            $user_id
        ));

        return !empty($supplier_id);
    }

    /**
     * Get the supplier associated with a user.
     *
     * @param int $user_id - The user ID.
     * @return string|WP_Error - Supplier ID or WP_Error if not found.
     */
    public static function get_supplier_by_user($user_id)
    {
        global $wpdb;

        $supplier_id = $wpdb->get_var($wpdb->prepare(
            "SELECT supplier_id FROM {$wpdb->prefix}neom_user_supplier_relation WHERE user_id = %d",
            $user_id
        ));

        if (empty($supplier_id)) {
            return new WP_Error('no_supplier', __('No supplier found for this user.', 'bidfood'));
        }

        return $supplier_id;
    }

    /**
     * Assign a user to a supplier.
     *
     * @param int $user_id - The user ID.
     * @param string $supplier_id - The supplier ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_user_to_supplier($user_id, $supplier_id)
    {

        // Check if the user exists
        if (!get_user_by('ID', $user_id) && $user_id != 0) {
            return new WP_Error('user_not_found', __('User not found.', 'bidfood'));
        }

        // Check if there is a current user assigned to the supplier
        $current_user = self::get_users_by_supplier($supplier_id);

        if (!is_wp_error($current_user) && $current_user[0] == $user_id) {
            return new WP_Error('user_supplier_exists', __('User is already assigned to this supplier.', 'bidfood'));
        }

        // Check if the user is already assigned to a supplier
        if (self::is_user_supplier($user_id)) {
            return new WP_Error('user_supplier_exists', __('User is already assigned to a supplier.', 'bidfood'));
        }

        if (!is_wp_error($current_user) && !empty($current_user) && $current_user[0] != $user_id) {
            $result = self::remove_user_from_supplier($current_user[0]);
            if (is_wp_error($result)) {
                return $result;
            }

            if ($user_id == 0) {
                return true;
            }
        }

        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_user_supplier_relation",
            [
                'user_id' => $user_id,
                'supplier_id' => $supplier_id,
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to assign user to supplier.', 'bidfood'));
        }

        return true;
    }

    /**
     * Remove a user from a supplier.
     *
     * @param int $user_id - The user ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function remove_user_from_supplier($user_id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_user_supplier_relation",
            ['user_id' => $user_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove user from supplier.', 'bidfood'));
        }

        return true;
    }

    /**
     * Get all users assigned to a supplier.
     *
     * @param string $supplier_id - The supplier ID.
     * @return array|WP_Error - Array of user IDs or WP_Error on failure.
     */
    public static function get_users_by_supplier($supplier_id)
    {
        global $wpdb;

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}neom_user_supplier_relation WHERE supplier_id = %s",
            $supplier_id
        ));

        if (empty($user_ids)) {
            return new WP_Error('no_users', __('No users found for this supplier.', 'bidfood'));
        }

        return $user_ids;
    }

    /**
     * Assign a supplier to an item in an order.
     * 
     * @param string $item_id - The ID of the item.
     * @param int $order_id - The ID of the order.
     * @param string $supplier_id - The supplier ID to assign.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_item_to_supplier($item_id, $order_id, $supplier_id, $customer_delivery_date)
    {
        global $wpdb;

        // Check if this item is already assigned
        $existing_assignment = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}neom_order_item_supplier_relation WHERE item_id = %s AND order_id = %d",
            $item_id,
            $order_id
        ));

        if ($existing_assignment) {
            // Update the supplier for the existing item assignment
            $result = $wpdb->update(
                "{$wpdb->prefix}neom_order_item_supplier_relation",
                ['supplier_id' => $supplier_id, 'customer_delivery_date' => $customer_delivery_date],
                ['id' => $existing_assignment],
                ['%s', '%s'],
                ['%d']
            );
        } else {
            // Insert a new record for this item-supplier assignment
            $result = $wpdb->insert(
                "{$wpdb->prefix}neom_order_item_supplier_relation",
                [
                    'item_id' => $item_id,
                    'order_id' => $order_id,
                    'supplier_id' => $supplier_id,
                    'status' => 'pending supplier',
                    'customer_delivery_date' => $customer_delivery_date,
                ],
                ['%s', '%d', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to assign item to supplier.', 'bidfood'));
        }

        return true;
    }

    /**
     * Update the status of a supplier order.
     *
     * @param string $item_id - The item ID.
     * @param string $order_id - The order ID.
     * @param string $new_status - The new status.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function update_supplier_order_status($item_id, $order_id, $new_status)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_order_item_supplier_relation",
            [
                'status' => $new_status
            ],
            [
                'item_id' => $item_id,
                'order_id' => $order_id,
            ],
            [
                '%s'
            ],
            [
                '%s',
                '%s'
            ]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update supplier order status.', 'bidfood'));
        }

        return true;
    }

    /**
     * Reassign an item to a different supplier.
     *
     * @param string $item_id - The item ID.
     * @param string $order_id - The order ID.
     * @param string $new_supplier_id - The new supplier ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function reassign_item_to_supplier($item_id, $order_id, $new_supplier_id)
    {
        global $wpdb;

        // Update the item-supplier relation in the database
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_order_item_supplier_relation",
            [
                'supplier_id' => $new_supplier_id
            ],
            [
                'item_id' => $item_id,
                'order_id' => $order_id,
            ],
            [
                '%s'
            ],
            [
                '%s',
                '%s'
            ]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to reassign item to a new supplier.', 'bidfood'));
        }

        return true;
    }

    /**
     * Get all suppliers with assigned users.
     *
     * @return array - Array of suppliers with assigned users.
     */
    public static function get_all_assigned_suppliers()
    {
        global $wpdb;

        $suppliers = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}neom_supplier WHERE supplier_id IN (SELECT supplier_id FROM {$wpdb->prefix}neom_user_supplier_relation)",
            ARRAY_A
        );

        return $suppliers;
    }


    /**
     * Update the status of an item in an order.
     * 
     * @param string $item_id - The ID of the item.
     * @param int $order_id - The ID of the order.
     * @param string $status - The new status ('pending supplier', 'supplier approved', 'supplier canceled').
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function update_item_status($item_id, $order_id, $status)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['status' => $status],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update item status.', 'bidfood'));
        }

        return true;
    }


    public static function get_item_supplier_assignment($item_id, $order_id)
    {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT supplier_id, status FROM {$wpdb->prefix}neom_order_item_supplier_relation WHERE item_id = %s AND order_id = %d",
            $item_id,
            $order_id
        );

        $assignment = $wpdb->get_row($query, ARRAY_A);

        if (empty($assignment)) {
            return new WP_Error('no_assignment', __('No supplier assignment found for this item.', 'bidfood'));
        }

        return $assignment;
    }

    /**
     * Get all suppliers assigned to the order.
     * 
     * @param int $order_id - The order ID.
     * @return array - Array of suppliers assigned to items in the order.
     */
    public static function get_order_suppliers($order_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT supplier_id, item_id, status FROM {$wpdb->prefix}neom_order_item_supplier_relation WHERE order_id = %d",
            $order_id
        ), ARRAY_A);
    }

    // Function to fetch all suppliers as before...
    public static function get_all_suppliers()
    {
        global $wpdb;
        return $wpdb->get_results("SELECT supplier_id, supplier_name FROM {$wpdb->prefix}neom_supplier", ARRAY_A);
    }

    // Find supplier for item using neom_ordering_item_master table
    public static function get_preferred_supplier_by_item($item_id)
    {
        global $wpdb;

        $supplier_id = $wpdb->get_var($wpdb->prepare(
            "SELECT preferred_supplier_id FROM {$wpdb->prefix}neom_ordering_item_master WHERE item_id = %s",
            $item_id
        ));

        return $supplier_id;
    }

    public static function remove_item_supplier_assignment($product_sku, $order_id)
    {
        global $wpdb;

        $item_id = $wpdb->get_var($wpdb->prepare(
            "SELECT item_id FROM {$wpdb->prefix}neom_ordering_item_master WHERE item_id = %s",
            $product_sku
        ));

        if (empty($item_id)) {
            return new WP_Error('no_item', __('No item found for this product.', 'bidfood'));
        }

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove supplier assignment for this item.', 'bidfood'));
        }

        return true;
    }

    // Method to fetch all orders assigned to the supplier
    public static function get_supplier_orders($supplier_id)
    {
        global $wpdb;

        $order_ids = $wpdb->get_col(
            $wpdb->prepare("
            SELECT DISTINCT order_id 
            FROM {$wpdb->prefix}neom_order_item_supplier_relation 
            WHERE supplier_id = %s", $supplier_id)
        );

        return $order_ids;
    }

    // Method to get all items assigned to the supplier for an order
    public static function get_supplier_order_assigned_items($order_id, $supplier_id)
    {
        global $wpdb;

        $item_ids = $wpdb->get_col(
            $wpdb->prepare("
            SELECT item_id 
            FROM {$wpdb->prefix}neom_order_item_supplier_relation 
            WHERE order_id = %d AND supplier_id = %s", $order_id, $supplier_id)
        );

        if (empty($item_ids)) {
            return [];
        }

        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $assigned_items = [];

        foreach ($items as $item) {
            $product_sku = $item->get_product()->get_sku();
            if (in_array($product_sku, $item_ids)) {
                $assigned_items[] = $item;
            }
        }

        return $assigned_items;
    }

    // Method to create a new supplier PO
    public static function create_supplier_po($supplier_id, $orders)
    {
        // make use of neom_supplier_po and neom_supplier_po_order tables
        // Create transaction for this

        global $wpdb;

        $wpdb->query('START TRANSACTION');

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_supplier_po",
            [
                'supplier_id' => $supplier_id,
            ],
            [
                '%s',
            ]
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', __('Failed to create supplier PO.', 'bidfood'));
        }

        $po_id = $wpdb->insert_id;

        foreach ($orders as $order_id) {
            $result = $wpdb->insert(
                "{$wpdb->prefix}neom_supplier_po_order",
                [
                    'supplier_po_id' => $po_id,
                    'order_id' => $order_id,
                ],
                [
                    '%d',
                    '%d',
                ]
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', __('Failed to create supplier PO order.', 'bidfood'));
            }
        }

        $wpdb->query('COMMIT');

        return $po_id;
    }

    // Method to update status of supplier PO
    public static function update_supplier_po_status($po_id, $status)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_supplier_po",
            ['status' => $status],
            ['id' => $po_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update supplier PO status.', 'bidfood'));
        }

        return true;
    }

    // Delete supplier PO
    public static function delete_supplier_po($po_id)
    {
        global $wpdb;

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_supplier_po",
            ['id' => $po_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to delete supplier PO.', 'bidfood'));
        }

        return true;
    }

    // Method to get all supplier POs
    public static function get_supplier_pos_paginated($page = 1, $search = '', $per_page = 15)
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        $search_sql = $search ? $wpdb->prepare(
            "AND (id LIKE %s OR supplier_id LIKE %s OR status LIKE %s OR created_at LIKE %s OR updated_at LIKE %s)",
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        ) : '';

        $results = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}neom_supplier_po WHERE 1=1 $search_sql ORDER BY created_at DESC LIMIT $offset, $per_page",
            ARRAY_A
        );

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}neom_supplier_po WHERE 1=1 $search_sql");

        return [
            'results' => $results,
            'total_pages' => ceil($total / $per_page),
            'total_items' => $total,
        ];
    }



    // Method to get all orders in a supplier PO
    public static function get_supplier_po_orders($po_id)
    {
        global $wpdb;

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}neom_supplier_po_order WHERE supplier_po_id = %d",
            $po_id
        ));

        return $order_ids;
    }

    // Method to get pos for an order
    public static function get_all_supplier_pos_by_order_id($order_id)
    {
        global $wpdb;

        //wp_neom_supplier_po_order
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_supplier_po_order WHERE order_id = %d",
            $order_id
        ), ARRAY_A);
    }

    // Method to get all items in a supplier PO
    public static function get_supplier_po_items($po_id)
    {
        global $wpdb;

        $supplier_id = $wpdb->get_var($wpdb->prepare(
            "SELECT supplier_id FROM {$wpdb->prefix}neom_supplier_po WHERE id = %d",
            $po_id
        ));

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}neom_supplier_po_order WHERE supplier_po_id = %d",
            $po_id
        ));

        $items = [];
        foreach ($order_ids as $order_id) {
            $order_items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}neom_order_item_supplier_relation WHERE order_id = %d AND supplier_id = %s",
                $order_id,
                $supplier_id
            ), ARRAY_A);

            $order = wc_get_order($order_id);
            $woo_order_items = $order->get_items();

            foreach ($order_items as $db_order_item) {
                $order_item = [];

                foreach ($woo_order_items as $woo_item) {
                    if ($woo_item->get_product()->get_sku() == $db_order_item['item_id']) {
                        $order_item['order_id'] = $order_id;
                        $order_item['item_id'] = $db_order_item['item_id'];
                        $order_item['product_name'] = $woo_item->get_name();
                        $order_item['quantity'] = $woo_item->get_quantity();
                        $order_item['status'] = $db_order_item['status'];
                        $order_item['customer_delivery_date'] = $db_order_item['customer_delivery_date'];
                        $order_item['supplier_delivery_date'] = $db_order_item['supplier_delivery_date'];
                        $order_item['supplier_notes'] = $db_order_item['supplier_notes'];
                        $order_item['admin_notes'] = $db_order_item['admin_notes'];
                        $order_item['created_at'] = $db_order_item['created_at'];
                        $order_item['updated_at'] = $db_order_item['updated_at'];
                        $order_item['expected_delivery_date'] = $db_order_item['expected_delivery_date'];
                        $order_item['customer_item_note'] = $woo_item->get_meta('item_note');
                        break;
                    }
                }

                $items[] = $order_item;
            }
        }

        // Sort items by 'item id'
        usort($items, function ($a, $b) {
            return $a['item_id'] <=> $b['item_id'];
        });

        return $items;
    }

    public static function get_po_details_for_customer_order($order_id)
    {
        global $wpdb;
        $items = [];

        $order_items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_order_item_supplier_relation WHERE order_id = %d",
            $order_id
        ), ARRAY_A);

        $order = wc_get_order($order_id);
        $woo_order_items = $order->get_items();

        foreach ($order_items as $db_order_item) {
            $order_item = [];

            foreach ($woo_order_items as $woo_item) {
                if ($woo_item->get_product()->get_sku() == $db_order_item['item_id']) {

                    // Find po id
                    $query = $wpdb->prepare(
                        "SELECT spo.id
                         FROM {$wpdb->prefix}neom_supplier_po AS spo
                         INNER JOIN {$wpdb->prefix}neom_supplier_po_order AS spo_order
                         ON spo.id = spo_order.supplier_po_id
                         WHERE spo.supplier_id = %s
                         AND spo_order.order_id = %d",
                        $db_order_item['supplier_id'],
                        $order_id
                    );

                    $result = $wpdb->get_var($query);

                    $order_item['po_id'] = $result ? $result : null;
                    $order_item['supplier_id'] = $db_order_item['supplier_id'];
                    $order_item['item_id'] = $db_order_item['item_id'];
                    $order_item['product_name'] = $woo_item->get_name();
                    $order_item['quantity'] = $woo_item->get_quantity();
                    $order_item['status'] = $db_order_item['status'];
                    $order_item['customer_delivery_date'] = $db_order_item['customer_delivery_date'];
                    $order_item['supplier_delivery_date'] = $db_order_item['supplier_delivery_date'];
                    $order_item['supplier_notes'] = $db_order_item['supplier_notes'];
                    $order_item['admin_notes'] = $db_order_item['admin_notes'];
                    $order_item['created_at'] = $db_order_item['created_at'];
                    $order_item['updated_at'] = $db_order_item['updated_at'];
                    $order_item['expected_delivery_date'] = $db_order_item['expected_delivery_date'];
                    $order_item['customer_item_note'] = $woo_item->get_meta('item_note');
                    break;
                }
            }

            $items[] = $order_item;
        }


        // Sort items by 'item id'
        usort($items, function ($a, $b) {
            return $a['item_id'] <=> $b['item_id'];
        });

        return $items;
    }

    // method to delete an order from a supplier PO
    public static function delete_order_from_supplier_po($po_id, $order_id)
    {
        global $wpdb;

        // start transaction
        $wpdb->query('START TRANSACTION');

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_supplier_po_order",
            ['supplier_po_id' => $po_id, 'order_id' => $order_id],
            ['%d', '%d']
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', __('Failed to delete order from supplier PO.', 'bidfood'));
        }

        // Delete the order items from the supplier PO
        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['order_id' => $order_id],
            ['%d']
        );

        if ($result === false) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('db_error', __('Failed to delete order items from supplier PO.', 'bidfood'));
        }

        // Check if there are any more orders in the PO
        $remaining_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_supplier_po_order WHERE supplier_po_id = %d",
            $po_id
        ));

        if ($remaining_orders == 0) {
            $result = $wpdb->delete(
                "{$wpdb->prefix}neom_supplier_po",
                ['id' => $po_id],
                ['%d']
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('db_error', __('Failed to delete supplier PO.', 'bidfood'));
            }
        }

        $wpdb->query('COMMIT');

        return true;
    }

    // Method to get the supplier PO details
    public static function get_supplier_po($po_id, $is_user_called = false)
    {
        global $wpdb;

        if ($is_user_called) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}neom_supplier_po WHERE id = %d",
                $po_id
            ), ARRAY_A);
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_supplier_po WHERE id = %d",
            $po_id
        ), ARRAY_A);
    }

    // Method to get paginated supplier POs for a specific supplier
    public static function get_supplier_pos_by_supplier($supplier_id, $is_user_called = false, $page = 1, $per_page = 15)
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;
        $status_condition = $is_user_called ? "AND status != 'draft'" : '';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_supplier_po 
            WHERE supplier_id = %s $status_condition 
            ORDER BY created_at DESC 
            LIMIT %d, %d",
            $supplier_id,
            $offset,
            $per_page
        ), ARRAY_A);

        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_supplier_po 
            WHERE supplier_id = %s $status_condition",
            $supplier_id
        ));

        return [
            'results' => $results,
            'total_pages' => ceil($total / $per_page),
            'total_items' => $total,
        ];
    }


    // Method to update supplier PO item status
    public static function update_supplier_po_item_status($item_id, $order_id, $new_status)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['status' => $new_status],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update supplier PO item status.', 'bidfood'));
        }

        return true;
    }

    // Method to add a supplier note to an item in a supplier PO
    public static function add_supplier_notes_to_po_item($item_id, $order_id, $note)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['supplier_notes' => $note],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add supplier note to supplier PO item.', 'bidfood'));
        }

        return true;
    }

    // Method to add an admin note to an item in a supplier PO
    public static function add_admin_notes_to_po_item($item_id, $order_id, $note)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['admin_notes' => $note],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add admin note to supplier PO item.', 'bidfood'));
        }

        return true;
    }

    // Method to add a supplier delivery date to an item in a supplier PO
    public static function add_supplier_delivery_date_to_po_item($item_id, $order_id, $date)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['supplier_delivery_date' => $date],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add supplier delivery date to supplier PO item.', 'bidfood'));
        }

        return true;
    }

    // Method to add expected delivery date to an item in a supplier PO
    public static function add_expected_delivery_date_to_po_item($item_id, $order_id, $date)
    {
        global $wpdb;

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_order_item_supplier_relation",
            ['expected_delivery_date' => $date],
            ['item_id' => $item_id, 'order_id' => $order_id],
            ['%s'],
            ['%s', '%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to add expected delivery date to supplier PO item.', 'bidfood'));
        }

        return true;
    }

    // Method to check if all items in a supplier PO are of a certain status
    public static function are_all_po_items_of_status($po_id, $statuses)
    {
        // Get supplier po items
        $supplier_po_items = self::get_supplier_po_items($po_id);

        foreach ($supplier_po_items as $item) {
            if (!in_array($item['status'], $statuses)) {
                return false;
            }
        }

        return true;
    }


    ############################## Supplier Update Requests ##############################

    public static function get_supplier_requests($page = 1, $per_page = 10, $supplier_id = null, $status = null, $request_type = null)
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;

        $where_clauses = [];
        if ($supplier_id) {
            $where_clauses[] = $wpdb->prepare('supplier_id = %s', $supplier_id);
        }

        if ($status) {
            $where_clauses[] = $wpdb->prepare('status = %s', $status);
        }

        if ($request_type) {
            if ($request_type == 'items') {
                return self::get_supplier_add_item_requests($page, $per_page, $supplier_id, $status);
            } else {
                $where_clauses[] = $wpdb->prepare('field = %s', $request_type);
            }
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $results = $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}neom_supplier_update_requests
                $where_sql
                ORDER BY created_at DESC
                LIMIT %d, %d
            ", $offset, $per_page), ARRAY_A);

        $total = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}neom_supplier_update_requests
                $where_sql
            ");

        return [
            'results' => $results,
            'total_pages' => ceil($total / $per_page),
            'total_items' => $total,
        ];
    }

    public static function get_supplier_request_details($request_id)
    {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}neom_supplier_update_requests
                WHERE id = %d
            ", $request_id), ARRAY_A);

        if (!$request) {
            return new WP_Error('not_found', __('Request Update not found.', 'bidfood'));
        }

        return $request;
    }

    public static function update_supplier_request_status($request_id, $new_status, $request_type, $admin_notes = null)
    {
        global $wpdb;
        if (!in_array($new_status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'bidfood'));
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_supplier_update_requests",
            [
                'status' => $new_status,
                'admin_notes' => $admin_notes ? sanitize_textarea_field($admin_notes) : null
            ],
            ['id' => $request_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update request status.', 'bidfood'));
        }

        return true;
    }

    public static function submit_supplier_request($supplier_id, $product_id = null, $field = null, $old_value = null, $new_value = null, $supplier_notes = null)
    {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_supplier_update_requests",
            [
                'supplier_id' => sanitize_text_field($supplier_id),
                'product_id' => $product_id ? sanitize_text_field($product_id) : null,
                'field' => $field ? sanitize_text_field($field) : null,
                'old_value' => $old_value ? sanitize_textarea_field($old_value) : null,
                'new_value' => $new_value ? sanitize_textarea_field($new_value) : null,
                'supplier_notes' => $supplier_notes ? sanitize_textarea_field($supplier_notes) : null
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to submit request.', 'bidfood'));
        }

        return $wpdb->insert_id;
    }

    public static function get_supplier_requests_by_product($product_id, $status = null)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}neom_supplier_update_requests
                WHERE product_id = %s
                AND status = %s
            ", $product_id, $status), ARRAY_A);
    }

    ########################### Supplier Adding Item Requests ################################

    public static function get_supplier_add_item_requests($page = 1, $per_page = 10, $supplier_id = null, $status = null)
    {
        global $wpdb;

        $offset = ($page - 1) * $per_page;

        $where_clauses = [];
        if ($supplier_id) {
            $where_clauses[] = $wpdb->prepare('supplier_id = %s', $supplier_id);
        }

        if ($status) {
            $where_clauses[] = $wpdb->prepare('status = %s', $status);
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $results = $wpdb->get_results($wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}neom_items_requests
                $where_sql
                ORDER BY created_at DESC
                LIMIT %d, %d
            ", $offset, $per_page), ARRAY_A);

        $total = $wpdb->get_var("
                SELECT COUNT(*)
                FROM {$wpdb->prefix}neom_items_requests
                $where_sql
            ");

        return [
            'results' => $results,
            'total_pages' => ceil($total / $per_page),
            'total_items' => $total,
        ];
    }

    public static function update_supplier_add_item_request_status($request_id, $new_status, $admin_notes = null)
    {
        global $wpdb;

        if (!in_array($new_status, ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'bidfood'));
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_items_requests",
            [
                'status' => $new_status,
                'admin_notes' => $admin_notes ? sanitize_textarea_field($admin_notes) : null
            ],
            ['id' => $request_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update request status.', 'bidfood'));
        }

        return true;
    }

    public static function get_supplier_add_item_request_details($request_id)
    {
        global $wpdb;

        $request = $wpdb->get_row($wpdb->prepare("
                SELECT *
                FROM {$wpdb->prefix}neom_items_requests
                WHERE id = %d
            ", $request_id), ARRAY_A);

        if (!$request) {
            return new WP_Error('not_found', __('Request Add new item not found.', 'bidfood'));
        }

        return $request;
    }
    // Method to submit new item request from supplier
    public static function submit_supplier_new_item_request(
        $supplier_id,
        $item_description,
        $category_id,
        $sub_category_id,
        $country,
        $uom_id,
        $packing,
        $brand,
        $supplier_notes = null
    ) {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_items_requests",
            [
                'supplier_id' => $supplier_id,
                'item_description' => $item_description,
                'category_id' => $category_id,
                'sub_category_id' => $sub_category_id,
                'country' => $country,
                'uom_id' => $uom_id,
                'packing' => $packing,
                'brand' => $brand,
                'supplier_notes' => $supplier_notes,
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to submit request.', 'bidfood'));
        }

        return $wpdb->insert_id;
    }
    // Method to get supplier by id
    public static function get_supplier_by_id($supplier_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_supplier WHERE supplier_id = %s",
            $supplier_id
        ), ARRAY_A);
    }
    /**
     * Get Order by Po ID
     * @param int $po_id - The po ID.
     * @return array - Array of order id.
     */
    public static function get_order_by_po($po_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            //select last order id from neom_supplier_po_order where supplier_po_id = $po_id
            "SELECT order_id FROM {$wpdb->prefix}neom_supplier_po_order WHERE supplier_po_id = %d ORDER BY order_id DESC LIMIT 1",
            $po_id
        ), ARRAY_A);
    }
    /**
     * Get PO ID by Order ID
     * @param int $order_id - The Order ID.
     * @return int
     *
     **/
    public static function get_po_id_by_order($order_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT supplier_po_id FROM {$wpdb->prefix}neom_supplier_po_order WHERE order_id = %d",
            $order_id
        ));
    }
    /**
     * Get customer data from PO by Po ID
     * @param int $po_id - The po ID.
     * @return array - Array of customer details.
     */
    public static function get_customer_data_by_po($po_id)
    {
        global $wpdb;
        $order_id = self::get_order_by_po($po_id);
        if (!$order_id) {
            return false;
        }
        $order = wc_get_order($order_id['order_id']);
        if (!$order) {
            return false;
        }
        return $order->get_data();
    }
    /**
     * Check User Has Access to Download Invoice or Not
     * @param int $user_id - The user ID.
     * @param int $po_id - The po ID.
     * @param string $type - The type of user.
     * @return boolean - True if user has access to download invoice, false otherwise.
     */
    public static function verify_invoice_access($user_id, $po_id, $type)
    {
        $po = self::get_supplier_po($po_id);

        if (!$po) {
            return false;
        }
        if ($type === 'supplier') {
            return $po['supplier_id'] === self::get_supplier_by_user($user_id);
        }

        if ($type === 'customer') {
            $order_id = self::get_order_by_po($po_id);
            if (!$order_id) {
                return false;
            }
            $po = wc_get_order($order_id['order_id']);
            if (!$po) {
                return false;
            }
            return $po->get_customer_id() === $user_id;
        }

        return false;
    }
    /**
     * Check supplier_po status is submitted or not submitted
     * @param int $po_id - The po ID.
     * @return boolean
     */
    public static function is_po_submitted($po_id)
    {
        $po = self::get_supplier_po($po_id);
        
        if (!$po) {
            return false;
        }

        return $po['status'] === 'supplier submitted';
    }
}
