<?php
namespace Bidfood\Core\OrderManagement;

class DriverOrderManager{
    public static function init(){
        return new self();
    }


    // orders 

    /**
     * Retrieves all warehouse orders assigned to a specific driver with user information.
     *
     * @param int $driver_id
     * @return array|null
     */
    public static function get_orders_by_driver_id($driver_id){
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT 
                do.*,
                wo.order_id,
                wo.user_id,
                wo.wh_order_note
            FROM {$wpdb->prefix}neom_driver_orders do
            LEFT JOIN {$wpdb->prefix}neom_wh_order wo 
                ON do.wh_order_id = wo.id
            WHERE do.driver_id = %d ORDER BY updated_at DESC",
            $driver_id
        );

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Retrieve a driver order by its ID.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @return array|WP_Error The driver order data or WP_Error on failure.
     */
    public static function get_driver_order_by_driver_order_id($driver_order_id) {
        global $wpdb;

        // Validate input
        if (empty($driver_order_id)) {
            return new \WP_Error('invalid_order_id', __('Invalid driver order ID.', 'bidfood'));
        }

        // Prepare the query
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d AND status NOT IN ('Skipped', 'Skipped by WH', 'Cancelled', 'Deliverd') ",
            $driver_order_id
        );

        // Execute the query
        $result = $wpdb->get_row($query, ARRAY_A);
        error_log(print_r($result,true));
        // Check if the order was found
        if (!$result) {
            return new \WP_Error('order_not_found', __('Driver order not found.', 'bidfood'));
        }

        return $result; // Return the driver order data
    }

    /**
     * Update the status of a driver order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @param string $status The new status to set for the order.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_driver_order_status($driver_order_id, $status) {
        global $wpdb;

        // Validate input
        if (!$driver_order_id || empty($status)) {
            return new \WP_Error('invalid_data', __('Invalid order ID or status.', 'bidfood'));
        }

        // Check if the order exists
        $order_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ));

        if ($order_exists == 0) {
            return new \WP_Error('order_not_found', __('Order not found.', 'bidfood'));
        }

        // Prepare the update query
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders", // Correct table name
            ['status' => $status], // Data to update
            ['driver_order_id' => $driver_order_id] // Where clause
        );

        // Check for errors
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update order status.', 'bidfood'));
        }

        return true; // Return true on success
    }

    /**
     * Gets the driver order items for a specific order using driver_order_id.
     *
     * @param int $driver_order_id The driver order ID
     * @return array|WP_Error Array of items or WP_Error on failure
     */
    public static function get_driver_order_items($driver_order_id) {
        global $wpdb;

        $query = $wpdb->prepare("
            SELECT 
                doi.*,
                p.post_title as product_name
            FROM {$wpdb->prefix}neom_driver_order_items as doi
            LEFT JOIN {$wpdb->posts} as p ON doi.item_id = p.ID
            WHERE doi.driver_order_id = %d
        ", $driver_order_id);

        error_log($query);

        $items = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            return new \WP_Error('db_error', __('Failed to fetch order items.', 'bidfood'));
        }

        return $items;
    }


    // skip order request 
    /**
     * Inserts a skip order request into the database.
     *
     * @param int $wh_order_id
     * @param int $wc_order_id
     * @param int $driver_id
     * @param string $reason
     * @return true|WP_Error
     */
    public static function insert_skip_order_request($driver_order_id, $driver_id, $reason){
        global $wpdb;
    
        // Check if the order exists
        $order_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ));

        if ($order_exists == 0) {
            return new \WP_Error('order_not_found', __('Order not found.', 'bidfood'));
        }
        // Insert the skip order request into the database
        $result = $wpdb->insert("{$wpdb->prefix}neom_skip_order_requests", [
            'driver_order_id' => $driver_order_id,
            'driver_id' => $driver_id,
            'reason' => $reason,
            'status' => 'pending', // Default to 'pending'
        ]);
    
        if ($result === false) {
            return new \WP_Error('db_insert_error', __('Failed to insert skip order request.', 'bidfood'));
        }
    
        return true;
    }

    /**
     * Retrieves all skip order requests for a specific driver.
     *
     * @param int $driver_id
     * @return array|WP_Error
     */
    public static function get_skip_requests_by_driver_id($driver_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_skip_order_requests WHERE driver_id = %d ORDER BY updated_at DESC",
            $driver_id
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        if ($wpdb->last_error) {
            return new \WP_Error('db_error', __('Failed to fetch skip requests.', 'bidfood'));
        }

        return $results;
    }


    /**
     * Updates the is_otp_confirmed status for a driver order
     *
     * @param int $driver_order_id The ID of the driver order
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function update_driver_order_confirmation_status($driver_order_id) {
        global $wpdb;

        $query = $wpdb->prepare(
            "UPDATE {$wpdb->prefix}neom_driver_orders 
            SET is_otp_confirmed = TRUE 
            WHERE driver_order_id = %d",
            $driver_order_id
        );

        $result = $wpdb->query($query);

        if ($result === false) {
            return new \WP_Error(
                'db_error', 
                __('Failed to update order confirmation status.', 'bidfood')
            );
        }

        return true;
    }

    
    // OTP 
    /**
     * Generates a 6-digit OTP.
     *
     * @return string
     */
    public static function generate_otp() {
        return sprintf('%06d', mt_rand(0, 999999));
    }

    /**
     * Saves a generated OTP for a customer in the database and returns their email.
     *
     * @param int $customer_id The ID of the customer
     * @param string $otp The generated OTP
     * @param int $expiry_minutes Minutes until OTP expires (default: 5)
     * @return array|WP_Error Array with success status and customer email on success, WP_Error on failure
     */
    public static function save_customer_otp($customer_id, $driver_id, $driver_order_id, $expiry_minutes = 5) {
        global $wpdb;

        // Validate inputs
        if (empty($customer_id)) {
            return new \WP_Error('invalid_input', 'Customer ID is required.');
        }

        // Check if the driver exists
        $driver_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_driver_users WHERE id = %d",
            $driver_id
        ));
        error_log($driver_exists);

        if ($driver_exists == 0) {
            return new \WP_Error('driver_not_found', 'Driver not found.');
        }

        // Check if the order exists and get its confirmation status
        $order = $wpdb->get_row($wpdb->prepare(
            "SELECT is_otp_confirmed FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d AND driver_id = %d",
            $driver_order_id,
            $driver_id
        ));

        error_log(print_r($order,true));
        if (!$order) {
            return new \WP_Error('order_not_found', __('Order not found for this driver.', 'bidfood'));
        }

        // Check if the order is already confirmed
        if ($order->is_otp_confirmed) {
            return new \WP_Error('order_already_confirmed', __('Order is already confirmed. OTP cannot be generated.', 'bidfood'));
        }

        // Check for existing skip request that is not expired and not used
        $existing_skip_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_customer_order_delivery_verfication_otp 
             WHERE generated_by = %d AND generated_for = %d AND is_used = 0 AND expires_at > NOW()",
            $driver_id,
            $driver_order_id
        ));

        // If an existing skip request is found, delete it
        if ($existing_skip_request) {
            $wpdb->delete(
                "{$wpdb->prefix}neom_customer_order_delivery_verfication_otp",
                array('id' => $existing_skip_request->id),
                array('%d')
            );
        }

        $user_data = get_userdata($customer_id);

        if (!$user_data) {
            return new \WP_Error('invalid_customer', 'Customer not found.');
        }

        // Generate OTP
        $otp = self::generate_otp();

        // Calculate expiration time
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));

        // Insert the OTP record
        $result = $wpdb->insert(
            $wpdb->prefix . 'neom_customer_order_delivery_verfication_otp',
            array(
                'customer_id' => $customer_id,
                'generated_by' => $driver_id,
                'generated_for' => $driver_order_id,
                'otp' => $otp,
                'expires_at' => $expires_at,
                'is_used' => false
            ),
            array(
                '%d',  // customer_id
                '%s',  // otp
                '%s',  // expires_at
                '%d'   // is_used
            )
        );

        if ($result === false) {
            return new \WP_Error('db_error', 'Failed to save OTP in database.');
        }

        return array(
            'success' => true,
            'generated_otp' => $otp,
            'customer_email' => $user_data->user_email,
        );
    }
    

    /**
     * Validates a customer's OTP and deletes it after successful validation.
     *
     * @param int $customer_id The ID of the customer
     * @param string $otp The OTP to validate
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public static function validate_customer_otp($customer_id, $otp) {
        global $wpdb;

        // Prepare the query to check for a valid OTP
        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}neom_customer_order_delivery_verfication_otp 
            WHERE customer_id = %d 
            AND otp = %s 
            AND is_used = 0", // Check if the OTP has not expired
            $customer_id,
            $otp
        );

        $result = $wpdb->get_row($query);
        if (!$result) {
            return new \WP_Error('invalid_otp', 'Invalid or expired OTP.');
        }

        // Delete the OTP record
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'neom_customer_order_delivery_verfication_otp',
            array('id' => $result->id),
            array('%d')
        );

        if ($deleted === false) {
            return new \WP_Error('delete_error', 'Failed to delete OTP record.');
        }

        // Update the driver order confirmation status
        $driver_order_id = $result->generated_for; // Assuming you have a driver_order_id in the OTP record
        $confirmation_result = self::update_driver_order_confirmation_status($driver_order_id);

        if (is_wp_error($confirmation_result)) {
            return $confirmation_result; // Return the error if the update fails
        }

        return true; // Return true if everything is successful
    }

    /**
     * Update the delivery start time for a driver order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @param string|null $start_time The start time to set for the order. If null, current time will be used.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_driver_order_start_time($driver_order_id) {
        global $wpdb;

        $start_time = current_time('mysql');
        

        // Validate input
        if (!$driver_order_id) {
            return new \WP_Error('invalid_data', __('Invalid order ID.', 'bidfood'));
        }

        // Prepare the update query
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders", // Correct table name
            ['delivery_start_time' => $start_time], // Data to update
            ['driver_order_id' => $driver_order_id] // Where clause
        );

        // Check for errors
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update start time.', 'bidfood'));
        }

        return true; // Return true on success
    }

    /**
     * Update the delivery end time for a driver order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @param string|null $end_time The end time to set for the order. If null, current time will be used.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_driver_order_end_time($driver_order_id) {
        global $wpdb;

        $end_time = current_time('mysql');

        // Validate input
        if (!$driver_order_id) {
            return new \WP_Error('invalid_data', __('Invalid order ID.', 'bidfood'));
        }

        // Prepare the update query
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders", // Correct table name
            ['delivery_end_time' => $end_time], // Data to update
            ['driver_order_id' => $driver_order_id] // Where clause
        );

        // Check for errors
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update end time.', 'bidfood'));
        }

        return true; // Return true on success
    }

      /**
     * Updates the driver order confirmation status.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @param bool $is_driver_confirmed The confirmation status.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_driver_order_confirmation($driver_order_id, $is_driver_confirmed) {
        global $wpdb;
    
        // Prepare the update query
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders", // Correct table name
            ['is_driver_confirmed' => $is_driver_confirmed], // Data to update
            ['driver_order_id' => $driver_order_id] // Where clause
        );
    
        // Check for errors
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update driver confirmation status.', 'bidfood'));
        }
    
        return true; // Return true on success
    }

    /**
     * Updates the customer confirmation status for a driver order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @param bool $is_customer_confirmed The confirmation status.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function update_customer_confirmation($driver_order_id, $is_customer_confirmed) {
        global $wpdb;

        // Prepare the update query
        $result = $wpdb->update(
            "{$wpdb->prefix}neom_driver_orders", // Correct table name
            ['is_customer_confirmed' => $is_customer_confirmed], // Data to update
            ['driver_order_id' => $driver_order_id] // Where clause
        );

        // Check for errors
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to update customer confirmation status.', 'bidfood'));
        }

        return true; // Return true on success
    }

    /**
     * Checks if the driver has confirmed the order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @return bool True if the driver has confirmed, false otherwise.
     */
    public static function is_driver_confirmed($driver_order_id) {
        global $wpdb;

        // Query to check driver confirmation status
        $is_driver_confirmed = $wpdb->get_var($wpdb->prepare(
            "SELECT is_driver_confirmed FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ));

        return $is_driver_confirmed == 1; // Return true if confirmed, false otherwise
    }

    /**
     * Checks if the customer has confirmed the order.
     *
     * @param int $driver_order_id The ID of the driver order.
     * @return bool True if the customer has confirmed, false otherwise.
     */
    public static function is_customer_confirmed($driver_order_id) {
        global $wpdb;

        // Query to check customer confirmation status
        $is_customer_confirmed = $wpdb->get_var($wpdb->prepare(
            "SELECT is_customer_confirmed FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ));

        return $is_customer_confirmed == 1; // Return true if confirmed, false otherwise
    }
    public static function get_driver_order_status($driver_order_id){
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        );

        return $wpdb->get_var($query);
    }

}
?>