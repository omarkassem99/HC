<?php

namespace Bidfood\Core\UserManagement;

use Exception;
use WP_Error;

class UserDriverManager
{
    private static $wpdb;
    private static $driverUserTable;
    private static $driverInfoTable;
    private static $driverOrderTable;
    private static $whOrderTable;

    // Class constants for status mappings
    private const STATUS_MAPPINGS = [
        'Assigned to Driver' => 'Pending',
        'Dispatched' => 'Dispatched',
        'Delivered' => 'Delivered'
    ];

    // Error messages
    private const ERROR_MESSAGES = [
        'db_error' => 'Database operation failed: %s',
        'invalid_status' => 'Invalid status provided: %s',
        'missing_data' => 'Required data missing: %s',
        'not_found' => 'Record not found: %s'
    ];

    /**
     * Initialize class variables and set table names.
     */
    public static function init()
    {
        global $wpdb;
        self::$wpdb = $wpdb;

        self::$driverUserTable = $wpdb->prefix . 'neom_driver_users';
        self::$driverInfoTable = $wpdb->prefix . 'neom_driver_info';
        self::$driverOrderTable = $wpdb->prefix . 'neom_driver_orders';
        self::$whOrderTable = $wpdb->prefix . 'neom_wh_order';
    }

    /**
     * Add a new driver and their information.
     *
     * @param array $driverData Driver data (email, password, etc.).
     * @param array $infoData Driver information (name, vehicle, etc.).
     * @return int|WP_Error Driver ID on success, WP_Error on failure.
     */
    public static function addDriver($driverData, $infoData)
    {
        self::init();

        // Validate required fields
        if (empty($driverData['email']) || empty($driverData['password'])) {
            return new WP_Error('missing_data', 'Email and Password are required.');
        }

        if (!is_email($driverData['email'])) {
            return new WP_Error('invalid_email', 'Invalid email format.');
        }

        // Check if email already exists
        $emailExists = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$driverUserTable . " WHERE email = %s",
            $driverData['email']
        ));

        if ($emailExists) {
            return new WP_Error('email_exists', 'Driver with this email already exists.');
        }

        // Check if phone number already exists
        $phoneExists = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$driverInfoTable . " WHERE phone = %s",
            $infoData['phone']
        ));

        if ($phoneExists) {
            return new WP_Error('phone_exists', 'Driver with this phone number already exists.');
        }

        // Hash password
        $driverData['password'] = wp_hash_password($driverData['password']);
        $driverData['created_at'] = current_time('mysql');

        // Begin transaction
        self::$wpdb->query('START TRANSACTION');

        // Insert into driver_users table
        $inserted = self::$wpdb->insert(self::$driverUserTable, $driverData);

        if ($inserted === false) {
            self::$wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Failed to create driver: ' . self::$wpdb->last_error);
        }

        $driver_id = self::$wpdb->insert_id; // Retrieve the driver ID

        // Add driver_id to infoData
        $infoData['driver_id'] = $driver_id;

        // Insert into driver_info table
        $infoInserted = self::$wpdb->insert(self::$driverInfoTable, $infoData);

        if ($infoInserted === false) {
            self::$wpdb->query('ROLLBACK');
            return new WP_Error('db_error', 'Failed to add driver info: ' . self::$wpdb->last_error);
        }

        // Commit transaction
        self::$wpdb->query('COMMIT');

        return $driver_id;
    }

    /**
     * Update an existing driver's details.
     *
     * @param int $driver_id Driver ID.
     * @param array $driverData Driver data to update.
     * @param array $infoData Driver information to update.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function updateDriver($driver_id, $driverData = [], $infoData = [])
    {
        self::init();

        // Check if the email already exists for another driver
        if (!empty($driverData['email'])) {
            $emailExists = self::$wpdb->get_var(self::$wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::$driverUserTable . " WHERE email = %s AND id != %d",
                $driverData['email'],
                $driver_id
            ));

            if ($emailExists) {
                return new WP_Error('email_exists', 'Driver with this email already exists.');
            }
        }

        // Check if the phone number already exists for another driver
        if (!empty($infoData['phone'])) {
            $phoneExists = self::$wpdb->get_var(self::$wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::$driverInfoTable . " WHERE phone = %s AND driver_id != %d",
                $infoData['phone'],
                $driver_id
            ));

            if ($phoneExists) {
                return new WP_Error('phone_exists', 'Driver with this phone number already exists.');
            }
        }

        if (!empty($driverData['password'])) {
                $driverData['password'] = $driverData['password'];      
    }
        // Update driver_user table if needed
        if (!empty($driverData)) {
            $updated = self::$wpdb->update(
                self::$driverUserTable,
                $driverData,
                ['id' => $driver_id],
                array_fill(0, count($driverData), '%s'),
                ['%d']
            );

            if ($updated === false) {
                return new WP_Error('db_error', 'Failed to update driver: ' . self::$wpdb->last_error);
            }
        }

        // Update driver_info table if needed
        if (!empty($infoData)) {
            // Check if record exists
            $infoExists = self::$wpdb->get_var(
                self::$wpdb->prepare(
                    "SELECT COUNT(*) FROM " . self::$driverInfoTable . " WHERE driver_id = %d",
                    $driver_id
                )
            );

            if (!$infoExists) {
                return new WP_Error('info_not_found', 'Driver info record not found for driver ID: ' . $driver_id);
            }

            // Proceed with update
            $updated = self::$wpdb->update(
                self::$driverInfoTable,
                $infoData,
                ['driver_id' => $driver_id],
                array_fill(0, count($infoData), '%s'),
                ['%d']
            );

            if ($updated === false) {
                return new WP_Error('db_error', 'Failed to update driver info: ' . self::$wpdb->last_error);
            }
        }

        return true;
    }

    /**
     * Delete a driver and their related records.
     *
     * @param int $driver_id Driver ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function deleteDriver($driver_id)
    {
        self::init();

        // Delete from driver_users table
        $deleted = self::$wpdb->query(self::$wpdb->prepare("DELETE FROM " . self::$driverUserTable . " WHERE id = %d", $driver_id));

        if ($deleted === false) {
            return new WP_Error('db_error', 'Failed to delete driver: ' . self::$wpdb->last_error);
        }

        return true;
    }

    /**
     * Get a list of all drivers, optionally filtered by status and paginated.
     *
     * @param string|null $is_active Filter drivers by active status.
     * @param int $limit Number of results to return (default: 20).
     * @param int $offset Offset for pagination (default: 0).
     * @return array List of drivers with their info.
     */
    public static function getDrivers($is_active = true, $limit = 20, $offset = 0)
    {
        self::init();

        $query = "SELECT u.id, u.email, u.is_active, i.* 
                  FROM " . self::$driverUserTable . " u 
                  LEFT JOIN " . self::$driverInfoTable . " i ON u.id = i.driver_id";

        $params = [];
        if ($is_active !== null) {
            $query .= " WHERE u.is_active = %s";
            $params[] = $is_active ? '1' : '0';
        }

        $query .= " ORDER BY u.id DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return self::$wpdb->get_results(self::$wpdb->prepare($query, $params), ARRAY_A);
    }

    /**
     * Get a single driver's details by ID.
     *
     * @param int $driver_id Driver ID.
     * @return mixed Driver details if found, false otherwise.
     * 
     */
    public static function getDriverById($driver_id)
    {
        self::init();
        $query = "SELECT u.id, u.email, u.is_active, i.* 
                  FROM " . self::$driverUserTable . " u 
                  LEFT JOIN " . self::$driverInfoTable . " i ON u.id = i.driver_id
                  WHERE u.id = %d";
        return self::$wpdb->get_row(self::$wpdb->prepare($query, $driver_id), ARRAY_A);
    }

    /**
     * Soft delete a driver by marking their status as inactive.
     *
     * @param int $driver_id Driver ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function softDeleteDriver($driver_id)
    {
        self::init();

        // Set driver status to "inactive"
        $updated = self::$wpdb->update(
            self::$driverUserTable,
            ['status' => 'inactive'],
            ['id' => $driver_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to deactivate driver: ' . self::$wpdb->last_error);
        }

        return true;
    }

    // ===== DRIVER ORDERS MANAGEMENT =====

    /**
     * Assign a driver to a warehouse order.
     *
     * @param int $wh_order_id Warehouse order ID.
     * @param int $driver_id Driver ID.
     * @param array $attributes Additional attributes for the order.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function assignDriverToOrder($wh_order_id, $driver_id, $attributes = [])
    {
        self::init();
        // Insert driver order
        $driverOrder = [
            'wh_order_id' => $wh_order_id,
            'driver_id' => $driver_id,
            'delivery_status' => 'Pending',
            'created_at' => current_time('mysql'),
        ];

        if (!empty($attributes)) {
            $driverOrder = array_merge($driverOrder, $attributes);
        }

        $orderInserted = self::$wpdb->insert(self::$driverOrderTable, $driverOrder);

        if ($orderInserted === false) {
            return new WP_Error('db_error', 'Failed to create driver order: ' . self::$wpdb->last_error);
        }

        return true;
    }

    /**
     * Get a list of all driver orders.
     *
     * @param int $limit Number of results to return (default: 10)
     * @param int $offset Offset for pagination (default: 0)
     * @param array $filters Optional filters for the query
     * @return array|WP_Error List of driver orders or error
     */
    public static function getDriverOrders($limit = 10, $offset = 0, $filters = [])
    {
        self::init();

        try {
            $query = "SELECT do.*, wo.* 
                      FROM " . self::$driverOrderTable . " do
                      LEFT JOIN " . self::$whOrderTable . " wo ON do.wh_order_id = wo.id";

            $whereConditions = [];
            $queryParams = [];

            // Apply filters
            if (!empty($filters['driver_id'])) {
                $whereConditions[] = "do.driver_id = %d";
                $queryParams[] = $filters['driver_id'];
            }

            if (!empty($filters['status'])) {
                if (!array_key_exists($filters['status'], self::STATUS_MAPPINGS)) {
                    return new WP_Error(
                        'invalid_status',
                        sprintf(self::ERROR_MESSAGES['invalid_status'], $filters['status'])
                    );
                }
                $whereConditions[] = "do.status = %s";
                $queryParams[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "do.created_at >= %s";
                $queryParams[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "do.created_at <= %s";
                $queryParams[] = $filters['date_to'];
            }

            // Add WHERE clause if conditions exist
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(' AND ', $whereConditions);
            }

            // Add pagination
            $query .= " ORDER BY do.driver_order_id DESC LIMIT %d OFFSET %d";
            $queryParams[] = $limit;
            $queryParams[] = $offset;

            $results = self::$wpdb->get_results(
                self::$wpdb->prepare($query, $queryParams),
                ARRAY_A
            );

            if ($results === null) {
                throw new Exception(self::$wpdb->last_error);
            }

            return $results;

        } catch (Exception $e) {
            return new WP_Error(
                'db_error',
                sprintf(self::ERROR_MESSAGES['db_error'], $e->getMessage())
            );
        }
    }

    /**
     * Get the count of driver orders based on filters
     *
     * @param array $filters Optional filters for the count
     * @return int|WP_Error Count of orders or error
     */
    public static function getDriverOrdersCount($filters = [])
    {
        self::init();

        try {
            $query = "SELECT COUNT(do.driver_order_id) FROM " . self::$driverOrderTable . " do";
            $whereConditions = [];
            $queryParams = [];

            // Apply the same filters as getDriverOrders
            if (!empty($filters['driver_id'])) {
                $whereConditions[] = "do.driver_id = %d";
                $queryParams[] = $filters['driver_id'];
            }

            if (!empty($filters['status'])) {
                if (!array_key_exists($filters['status'], self::STATUS_MAPPINGS)) {
                    return new WP_Error(
                        'invalid_status',
                        sprintf(self::ERROR_MESSAGES['invalid_status'], $filters['status'])
                    );
                }
                $whereConditions[] = "do.status = %s";
                $queryParams[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $whereConditions[] = "do.created_at >= %s";
                $queryParams[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = "do.created_at <= %s";
                $queryParams[] = $filters['date_to'];
            }

            // Add WHERE clause if conditions exist
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(' AND ', $whereConditions);
            }

            // Only prepare if we have parameters
            $count = !empty($queryParams) 
                ? self::$wpdb->get_var(self::$wpdb->prepare($query, $queryParams))
                : self::$wpdb->get_var($query);

            if ($count === null) {
                throw new Exception(self::$wpdb->last_error);
            }

            return (int) $count;

        } catch (Exception $e) {
            return new WP_Error(
                'db_error',
                sprintf(self::ERROR_MESSAGES['db_error'], $e->getMessage())
            );
        }
    }

    /**
     * Get a list of all driver orders for a specific driver.
     *
     * @param int $driver_id Driver ID.
     * @return array List of driver orders.
     */
    public static function getDriverOrdersByDriver($driver_id)
    {
        self::init();
        $query = "SELECT * FROM " . self::$driverOrderTable . " WHERE driver_id = %d";
        return self::$wpdb->get_results(self::$wpdb->prepare($query, $driver_id), ARRAY_A);
    }

    /**
     * Get a list of all driver orders for a specific warehouse order.
     *
     * @param int $wh_order_id Warehouse order ID.
     * @return array List of driver orders.
     */
    public static function getDriverOrdersByWHOrder($wh_order_id)
    {
        self::init();
        $query = "SELECT * FROM " . self::$driverOrderTable . " WHERE wh_order_id = %d AND status NOT IN ('Skipped', 'Skipped by WH', 'Cancelled', 'Deliverd')";
        return self::$wpdb->get_row(self::$wpdb->prepare($query, $wh_order_id), ARRAY_A);
    }

    /**
     * Update a driver order.
     * 
     * @param int $driver_order_id Driver Order ID.
     * @param array $attributes Attributes to update.
     * @return bool|WP_Error True on success, WP_Error on failure.
     * 
     */
    public static function updateDriverOrder($driver_order_id, $attributes = [])
    {
        self::init();

        if (empty($attributes)) {
            return new WP_Error('db_error', 'No attributes provided for update.');
        }

        $placeholders = array_map(
            fn($val) => is_int($val) ? '%d' : '%s',
            array_values($attributes)
        );

        $updated = self::$wpdb->update(
            self::$driverOrderTable,
            $attributes,
            ['driver_order_id' => $driver_order_id],
            $placeholders,
            ['%d'] // Format for driver_order_id
        );

        if ($updated === false) {
            return new WP_Error('db_error', 'Failed to update driver order: ' . self::$wpdb->last_error);
        }

        // Update WH order status to "Assigned to Driver"
        if ($attributes['status'] == 'Dispatched') {
            $wh_order = self::$wpdb->get_row(
                self::$wpdb->prepare(
                    "SELECT * FROM " . self::$whOrderTable . " WHERE id = %d",
                    $attributes['wh_order_id']
                ),
                ARRAY_A
            );

            $update_result = self::$wpdb->update(
                self::$whOrderTable,
                ['wh_order_status' => 'Dispatched'], // Data to update
                ['id' => $attributes['wh_order_id']], // Where clause
                ['%s'], // Format for data to update
                ['%d'] // Format for where clause
            );

            if ($update_result === false) {
                return new WP_Error('status_update_error', sprintf(__('Failed to update status for WH Order ID %d.', 'bidfood'), $wh_order['id']));
            }

            // Sync WooCommerce order status
            if ($wh_order && !empty($wh_order['order_id'])) {
                error_log("Syncing WC order status from updateDriverOrder - WC Order ID: " . $wh_order['order_id']);
                $order = wc_get_order($wh_order['order_id']);
                if ($order) {
                    $order->update_status('on-route', __('Order is on route', 'bidfood'));
                }
            }
        } elseif ($attributes['status'] == 'Delivered') {
            $wh_order = self::$wpdb->get_row(
                self::$wpdb->prepare(
                    "SELECT * FROM " . self::$whOrderTable . " WHERE id = %d",
                    $attributes['wh_order_id']
                ),
                ARRAY_A
            );

            $update_result = self::$wpdb->update(
                self::$whOrderTable,
                ['wh_order_status' => 'Delivered'], // Data to update
                ['id' => $attributes['wh_order_id']], // Where clause
                ['%s'], // Format for data to update
                ['%d'] // Format for where clause
            );

            if ($update_result === false) {
                return new WP_Error('status_update_error', sprintf(__('Failed to update status for WH Order ID %d.', 'bidfood'), $wh_order['id']));
            }

            // Sync WooCommerce order status
            if ($wh_order && !empty($wh_order['order_id'])) {
                error_log("Syncing WC order status from updateDriverOrder - WC Order ID: " . $wh_order['order_id']);
                $order = wc_get_order($wh_order['order_id']);
                if ($order) {
                    $order->update_status('delivered', __('Order has been delivered', 'bidfood'));
                }
            }
        } elseif ($attributes['status'] == 'Pending') {
            $wh_order = self::$wpdb->get_row(
                self::$wpdb->prepare(
                    "SELECT * FROM " . self::$whOrderTable . " WHERE id = %d",
                    $attributes['wh_order_id']
                ),
                ARRAY_A
            );

            $update_result = self::$wpdb->update(
                self::$whOrderTable,
                ['wh_order_status' => 'Assigned to Driver'], // Data to update
                ['id' => $attributes['wh_order_id']], // Where clause
                ['%s'], // Format for data to update
                ['%d'] // Format for where clause
            );

            if ($update_result === false) {
                return new WP_Error('status_update_error', sprintf(__('Failed to update status for WH Order ID %d.', 'bidfood'), $wh_order['id']));
            }

            // Sync WooCommerce order status
            if ($wh_order && !empty($wh_order['order_id'])) {
                error_log("Syncing WC order status from updateDriverOrder - WC Order ID: " . $wh_order['order_id']);
                $order = wc_get_order($wh_order['order_id']);
                if ($order) {
                    $order->update_status('ready-for-deliver', __('Order is on route', 'bidfood'));
                }
            }
        }else if($attributes['status'] == 'Skipped'||$attributes['status'] == 'Skipped by WH' || $attributes['status'] == 'Cancelled') {
            $wh_order = self::$wpdb->get_row(
                self::$wpdb->prepare(
                    "SELECT * FROM " . self::$whOrderTable . " WHERE id = %d",
                    $attributes['wh_order_id']
                ),
                ARRAY_A
            );

            $update_result = self::$wpdb->update(
                self::$whOrderTable,
                ['wh_order_status' => 'Ready for Driver Assignment'], // Data to update
                ['id' => $attributes['wh_order_id']], // Where clause
                ['%s'], // Format for data to update
                ['%d'] // Format for where clause
            );

            if ($update_result === false) {
                return new WP_Error('status_update_error', sprintf(__('Failed to update status for WH Order ID %d.', 'bidfood'), $wh_order['id']));
            }

            // Sync WooCommerce order status
            if ($wh_order && !empty($wh_order['order_id'])) {
                error_log("Syncing WC order status from updateDriverOrder - WC Order ID: " . $wh_order['order_id']);
                $order = wc_get_order($wh_order['order_id']);
                if ($order) {
                    $order->update_status('received-at-bf-wh', __('Order has been skipped', 'bidfood'));
                }
            }
        }

        return true;
    }

    /**
     * Update driver order status and related WH order status
     *
     * @param int $driver_order_id Driver order ID
     * @param string $status New status
     * @param array $additional_data Additional data to update
     * @return bool|WP_Error True on success, WP_Error on failure
     */
   public static function updateDriverOrderStatus($driver_order_id, $status, $additional_data = [])
{
    self::init();

    if (!array_key_exists($status, self::STATUS_MAPPINGS)) {
        return new WP_Error(
            'invalid_status',
            sprintf(self::ERROR_MESSAGES['invalid_status'], $status)
        );
    }

    try {
        self::$wpdb->query('START TRANSACTION');

        // Get driver ID from driver order
        $driver_id = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT driver_id FROM " . self::$driverOrderTable . " WHERE driver_order_id = %d",
            $driver_order_id
        ));

        if (!$driver_id) {
            throw new Exception("Driver order not found");
        }

        // Check if driver exists in driver_info table
        $driver_exists = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::$driverInfoTable . " WHERE driver_id = %d",
            $driver_id
        ));

        if (!$driver_exists) {
            error_log("Driver not found in driver_info table: " . $driver_id);
            throw new Exception("Driver not found in driver_info table");
        }

        // Get current driver order status
        $current_driver_status = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT status FROM " . self::$driverOrderTable . " WHERE driver_order_id = %d",
            $driver_order_id
        ));

        error_log("Driver Order ID: $driver_order_id, Current Status: $current_driver_status, New Status: $status");

        // Only update to Dispatched if current status is Pending
        if ($status == 'Dispatched' && $current_driver_status != 'Pending') {
            throw new Exception("Cannot update status to Dispatched unless current status is Pending");
        }

        // Only update to Delivered if current status is Dispatched
        if ($status == 'Delivered' && $current_driver_status != 'Dispatched') {
            throw new Exception("Cannot update status to Delivered unless current status is Dispatched");
        }

        // Update driver order status
        $update_data = array_merge(['status' => $status], $additional_data);
        $updated = self::$wpdb->update(
            self::$driverOrderTable,
            $update_data,
            ['driver_order_id' => $driver_order_id],
            array_fill(0, count($update_data), '%s'),
            ['%d']
        );

        if ($updated === false) {
            throw new Exception(self::$wpdb->last_error);
        }

        // Get WH order ID
        $wh_order_id = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT wh_order_id FROM " . self::$driverOrderTable . " WHERE driver_order_id = %d",
            $driver_order_id
        ));

        if ($wh_order_id) {
            // Update WH order status based on driver order status
            $wh_status = self::STATUS_MAPPINGS[$status];
            $wh_updated = self::$wpdb->update(
                self::$whOrderTable,
                ['wh_order_status' => $wh_status],
                ['id' => $wh_order_id],
                ['%s'],
                ['%d']
            );

            if ($wh_updated === false) {
                throw new Exception(self::$wpdb->last_error);
            }

            // Sync WooCommerce order status
            $wh_order = self::$wpdb->get_row(
                self::$wpdb->prepare(
                    "SELECT * FROM " . self::$whOrderTable . " WHERE id = %d",
                    $wh_order_id
                ),
                ARRAY_A
            );

            if ($wh_order && !empty($wh_order['order_id'])) {
                error_log("Syncing WC order status from updateDriverOrderStatus - WC Order ID: " . $wh_order['order_id']);
                $order = wc_get_order($wh_order['order_id']);
                if ($order) {
                    if ($status == 'Dispatched') {
                        $order->update_status('on-route', __('Order is on route', 'bidfood'));
                    } elseif ($status == 'Delivered') {
                        $order->update_status('delivered', __('Order has been delivered', 'bidfood'));
                    } elseif ($status == 'Pending') {
                        $order->update_status('ready-for-deliver', __('Order is ready for delivery', 'bidfood'));
                    } else {
                        $order->update_status('received-at-bf-wh', __('Order is pending', 'bidfood'));
                    }
                }
            }
        }

        do_action('driver_order_status_changed', $driver_order_id, $status);
        self::$wpdb->query('COMMIT');
        return true;

    } catch (Exception $e) {
        self::$wpdb->query('ROLLBACK');
        error_log("Error in updateDriverOrderStatus: " . $e->getMessage());
        return new WP_Error(
            'update_failed',
            sprintf(self::ERROR_MESSAGES['db_error'], $e->getMessage())
        );
    }
}
    /**
     * Delete a driver order.
     *
     * @param int $driver_order_id Driver Order ID.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function deleteDriverOrder($driver_order_id)
    {
        self::init();

        $deleted = self::$wpdb->delete(self::$driverOrderTable, ['driver_order_id' => $driver_order_id]);

        if ($deleted === false) {
            return new WP_Error('db_error', 'Failed to delete driver order: ' . self::$wpdb->last_error);
        }

        return true;
    }

    /**
     * Get driver order details by driver order ID.
     *
     * @param int $driver_order_id Driver order ID.
     * @return array|null Driver order details.
     */
    public static function get_driver_order_details($driver_order_id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ), ARRAY_A);
    }

    /**
     * Get driver order items by driver order ID.
     *
     * @param int $driver_order_id Driver order ID.
     * @return array Driver order items.
     */
    public static function get_driver_order_items($driver_order_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_driver_order_items WHERE driver_order_id = %d",
            $driver_order_id
        ), ARRAY_A);
    }

    /**
     * Get driver details by driver ID.
     *
     * @param int $driver_id Driver ID.
     * @return array|null Driver details.
     */
    public static function get_driver_details($driver_id)
    {
        $driver = UserDriverManager::getDriverById($driver_id);
        if (!$driver) {
            return null;
        }

        return [
            'id' => $driver['id'],
            'email' => $driver['email'],
            'phone' => $driver['phone'],
            'name' => $driver['first_name'] . ' ' . $driver['last_name'],
        ];
    }

    /**
     * Get item details by item ID.
     *
     * @param int $item_id Item ID.
     * @return array|null Item details.
     */
    public static function get_item_details($item_id)
    {
        $product = wc_get_product($item_id);
        if (!$product) {
            return null;
        }

        return [
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
        ];
    }
    
    // Get skip order requests

    /**
     * Get skip order requests
     * 
     * @param int $page
     * @param int $per_page
     * @param string|null $status
     * @return array
     */
    public static function get_skip_order_requests($page = 1, $per_page = 10, $status = null)
{
    global $wpdb;

    $offset = ($page - 1) * $per_page;

    $where_clauses = [];
    if ($status) {
        $where_clauses[] = $wpdb->prepare('status = %s', $status);
    }

    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT *
        FROM {$wpdb->prefix}neom_skip_order_requests
        $where_sql
        ORDER BY created_at DESC
        LIMIT %d, %d
    ", $offset, $per_page), ARRAY_A);
    $total = $wpdb->get_var("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}neom_skip_order_requests
        $where_sql
    ");

    return [
        'results' => $results,
        'total_pages' => ceil($total / $per_page),
        'total_items' => $total,
    ];
}

    /**
     * Update skip order request status
     * 
     * @param int $request_id
     * @param string $new_status
     * @param string|null $admin_reply
     * @return bool|WP_Error
     */
    public static function update_skip_order_request_status($request_id, $new_status, $admin_reply = null)
    {
        global $wpdb;

        if (!in_array($new_status, ['Pending', 'Accepted', 'Rejected'], true)) {
            return new WP_Error('invalid_status', __('Invalid status.', 'bidfood'));
        }

        $result = $wpdb->update(
            "{$wpdb->prefix}neom_skip_order_requests",
            [
                'status' => $new_status,
                'admin_reply' => $admin_reply ? sanitize_textarea_field($admin_reply) : null
            ],
            ['id' => $request_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to update request status.', 'bidfood'));
        }

        if ($new_status === 'Accepted') {
            do_action('driver_order_skip_request_approved', $request_id);
        } elseif ($new_status === 'Rejected') {
            do_action('driver_order_skip_request_rejected', $request_id);
        }

        return true;
    }
    /**
     * Handle skip request approval
     * 
     * @param int $request_id
     */
    public static function handle_skip_request_approved($request_id)
    {
        global $wpdb;

        // Get the driver order ID associated with the skip request
        $driver_order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT driver_order_id FROM {$wpdb->prefix}neom_skip_order_requests WHERE id = %d",
                $request_id
            )
        );

        if (!$driver_order_id) {
            error_log("Driver order not found for skip request ID: $request_id");
            return;
        }

        // Update driver order status to Skipped
        $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders",
            ['status' => 'Skipped'],
            ['driver_order_id' => $driver_order_id],
            ['%s'],
            ['%d']
        );

        // Get WH order ID associated with the driver order
        $wh_order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT wh_order_id FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
                $driver_order_id
            )
        );

        if (!$wh_order_id) {
            error_log("WH order not found for driver order ID: $driver_order_id");
            return;
        }

        // Update WH order status to Ready for Driver Assignment
        $wpdb->update(
            "{$wpdb->prefix}neom_wh_order",
            ['wh_order_status' => 'Ready for Driver Assignment'],
            ['id' => $wh_order_id],
            ['%s'],
            ['%d']
        );

        // Get WC order ID associated with the WH order
        $wc_order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT order_id FROM {$wpdb->prefix}neom_wh_order WHERE id = %d",
                $wh_order_id
            )
        );

        if ($wc_order_id) {
            // Update WooCommerce order status to received at BF WH
            $order = wc_get_order($wc_order_id);
            if ($order) {
                $order->update_status('received-at-bf-wh', __('Order received at BF warehouse', 'bidfood'));
            }
        }
    }

    /**
     * Get driver email by driver ID.
     *
     * @param int $driver_id Driver ID.
     * @return string|null Driver email.
     */
    public static function get_driver_email($driver_id)
    {
        self::init();
        $email = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT email FROM " . self::$driverUserTable . " WHERE id = %d",
            $driver_id
        ));

        return $email ? $email : null;
    }
    
    /**
     * Get admins emails.
     *
     * @return array Admins emails.
     */
    public static function get_admins_emails()
    {
        $admins_emails = array();
        $users = get_users(array('role' => 'administrator'));

        foreach ($users as $user) {
            $admins_emails[] = $user->user_email;
        }

        return $admins_emails;
    }
     /**
     * Get a driver by email.
     *
     * @param string $email - Driver email.
     * @return array|WP_Error - Driver data on success, WP_Error on failure.
     */
    public static function get_driver_by_email($email)
    {
        self::init();

        // Validate email format
        if (!is_email($email)) {
            return new WP_Error('invalid_email', 'Invalid email format.');
        }

        // Check if driver exists by email
        $driver = self::$wpdb->get_row(self::$wpdb->prepare(
            "SELECT u.id, u.email, u.is_active, u.password 
            FROM " . self::$driverUserTable . " u 
            WHERE u.email = %s",
            $email
        ), ARRAY_A);

        if (!$driver) {
            return new WP_Error('driver_not_found', 'Driver not found.');
        }

        return $driver;
    }

    public static function get_wh_order_by_driver_order_id($driver_order_id){
        self::init();
        $wh_order_id = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT wh_order_id FROM ". self::$driverOrderTable. " WHERE driver_order_id = %d",
            $driver_order_id
        ));
        $wh_order = self::$wpdb->get_row(self::$wpdb->prepare(
            "SELECT * FROM ". self::$whOrderTable. " WHERE id = %d",
            $wh_order_id
        ), ARRAY_A);
        return $wh_order;
    }
    

}
