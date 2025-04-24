<?php

namespace Bidfood\Admin\NeomSettings\Orders;

use Bidfood\Admin\NeomSettings\Drivers\DriverAuth;
use Bidfood\Core\OrderManagement\DriverOrderManager;
use Bidfood\Core\OrderManagement\CustomerOrderManager;
use Bidfood\Core\OrderManagement\WhOrderManager;
use Bidfood\Core\Events\EmailEvents;
use Bidfood\Core\UserManagement\UserDriverManager;

class DriverOrdersAPI
{
    public function __construct()
    {
        add_action('rest_api_init', array($this, 'orders_rest_routes'));
    }

    public static function init()
    {
        return new self();
    }

    public function orders_rest_routes()
    {
        register_rest_route('bidfoodme/v1', '/driver/orders/driver-orders', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_driver_orders_list'),
            'permission_callback' => 'is_driver_logged_in', // Adjust permissions as needed
        ));

        // New endpoint to get order details and items
        register_rest_route('bidfoodme/v1', '/driver/orders/(?P<driver_order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_order_details'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint to update order status
        register_rest_route('bidfoodme/v1', '/driver/orders/update-status/(?P<driver_order_id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_order_status'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint to submit skip order request
        register_rest_route('bidfoodme/v1', '/driver/orders/skip-request', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_skip_order_request'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint to get skip requests by driver ID
        register_rest_route('bidfoodme/v1', '/driver/orders/skip-requests', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_skip_requests'),
            'permission_callback' => 'is_driver_logged_in' // Adjust permissions as needed
        ));

        // New endpoint to generate and send OTP
        register_rest_route('bidfoodme/v1', '/driver/orders/send-otp', array(
            'methods' => 'POST',
            'callback' => array($this, 'send_otp'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint to validate OTP
        register_rest_route('bidfoodme/v1', '/driver/orders/validate-otp', array(
            'methods' => 'POST',
            'callback' => array($this, 'validate_otp'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint to handle order confirmation
        register_rest_route('bidfoodme/v1', '/driver/orders/confirm', array(
            'methods' => 'POST',
            'callback' => array($this, 'confirm_order_items'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));


        // New endpoint for driver to confirm order
        register_rest_route('bidfoodme/v1', '/driver/orders/confirm-order', array(
            'methods' => 'POST',
            'callback' => array($this, 'confirm_driver_order'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));

        // New endpoint for driver to reconfirm items
        register_rest_route('bidfoodme/v1', '/driver/orders/reconfirm-items', array(
            'methods' => 'POST',
            'callback' => array($this, 'reconfirm_order_items'),
            'permission_callback' => function($request) {
                $driver_order_id = (int) $request['driver_order_id'];
                return DriverAuth::is_permitted($driver_order_id);
            }, // Adjust permissions as needed
        ));
    }

    public function get_driver_orders_list()
    {
        $driver_id = get_current_driver_id();

        if (empty($driver_id)) {
            return new \WP_Error('missing_driver_id', __('Driver ID is required.', 'bidfood'), array('status' => 400));
        }

        $orders = DriverOrderManager::get_orders_by_driver_id($driver_id);

        if (empty($orders)) {
            return new \WP_Error('no_orders', __('No orders found for this driver.', 'bidfood'), array('status' => 404));
        }

        return new \WP_REST_Response($orders, 200);
    }

    // New method to get order details and items
    public function get_order_details($request)
    {
        $driver_order_id = (int) $request['driver_order_id'];

        // Get order items using the driver_order_items table
        $order_items = DriverOrderManager::get_driver_order_items($driver_order_id);
        if (is_wp_error($order_items)) {
            return $order_items;
        }

        if (empty($order_items)) {
            return new \WP_Error('no_items', __('No items found for this order.', 'bidfood'), array('status' => 404));
        }

        return new \WP_REST_Response(array(
            'items' => $order_items,
        ), 200);
    }

    // New method to update order status
    public function update_order_status($request)
    {
        $driver_order_id = (int) $request['driver_order_id'];
        $wh_order_id = (int) $request['wh_order_id'];
        $status = sanitize_text_field($request->get_param('status'));

        // Validate input
        if (empty($status)) {
            return new \WP_Error('missing_status', __('Status is required.', 'bidfood'), array('status' => 400));
        }
        $old_status = DriverOrderManager::get_driver_order_status($driver_order_id);

        if ($old_status === 'Delivered') {
            return new \WP_Error('order_delivered', __('Order has already been delivered.', 'bidfood'), array('status' => 400));
        }elseif ($old_status === 'Dispatched') {
            return new \WP_Error('order_dispatched', __('Order is Dispatched already.', 'bidfood'), array('status' => 400));
        }elseif ($old_status === 'Pending') {

            // Update the order status
            $result = DriverOrderManager::update_driver_order_status($driver_order_id, $status);
            WhOrderManager::update_wh_order_status($wh_order_id, 'Dispatched');
            if (is_wp_error($result)) {
                return new \WP_Error($result->get_error_code(), $result->get_error_message(), array('status' => 400));
            }

        }elseif ($old_status === 'Skipped' || $old_status === 'Cancelled' || $old_status === 'Skipped by WH') {
            return new \WP_Error('order_skipped', __('Order has been skipped.', 'bidfood'), array('status' => 400));
        }
      

        // Update the start time if the status is 'Dispatched'
        if ($status === 'Dispatched') {
            $start_time_result = DriverOrderManager::update_driver_order_start_time($driver_order_id);
            if (is_wp_error($start_time_result)) {
                return new \WP_Error($start_time_result->get_error_code(), $start_time_result->get_error_message(), array('status' => 400));
            }
        }

        // Update the end time if the status is 'Delivered'
        if ($status === 'Delivered') {
            $end_time_result = DriverOrderManager::update_driver_order_end_time($driver_order_id);
            if (is_wp_error($end_time_result)) {
                return new \WP_Error($end_time_result->get_error_code(), $end_time_result->get_error_message(), array('status' => 400));
            }
        }

        return new \WP_REST_Response(array('message' => __('Order status updated successfully.', 'bidfood')), 200);
    }

    // New method to submit skip order request
    public function submit_skip_order_request($request)
    {
        $driver_order_id = (int) $request->get_param('driver_order_id');
        $reason = sanitize_textarea_field($request->get_param('reason'));

        $driver_id = get_current_driver_id(); // Assuming you have a function to get the current driver ID

        // Validate input
        if (empty($reason)) {
            return new \WP_Error('missing_reason', __('Reason is required.', 'bidfood'), array('status' => 400));
        }

        // Use the handler function to insert the skip order request
        $result = DriverOrderManager::insert_skip_order_request($driver_order_id, $driver_id, $reason);

        if (is_wp_error($result)) {
            return $result; // Return the error from the handler method
        }

        return new \WP_REST_Response(array('message' => __('Skip order request submitted successfully.', 'bidfood')), 200);
    }

    // New method to get skip requests
    public function get_skip_requests()
    {
        $driver_id = get_current_driver_id();

        if (empty($driver_id)) {
            return new \WP_Error('missing_driver_id', __('Driver ID is required.', 'bidfood'), array('status' => 400));
        }

        $skip_requests = DriverOrderManager::get_skip_requests_by_driver_id($driver_id);

        if (is_wp_error($skip_requests)) {
            return $skip_requests; // Return the error from the manager method
        }

        if (empty($skip_requests)) {
            return new \WP_Error('no_requests', __('No skip requests found for this driver.', 'bidfood'), array('status' => 404));
        }

        return new \WP_REST_Response($skip_requests, 200);
    }
    // New method to handle OTP sending
    public function send_otp($request)
    {
        $driver_order_id = sanitize_text_field($request->get_param('driver_order_id'));
        $wh_order = UserDriverManager::get_wh_order_by_driver_order_id($driver_order_id);
        $customer_id = $wh_order['user_id'];
        $driver_id = get_current_driver_id(); // Assuming you have a function to get the current driver ID
        // Validate customer_id
        if (empty($customer_id)) {
            return new \WP_Error('missing_customer_id', __('Customer ID is required.', 'bidfood'), array('status' => 400));
        }

        // Save OTP to database and get customer email
        $result = DriverOrderManager::save_customer_otp($customer_id, $driver_id, $driver_order_id);

        if (is_wp_error($result)) {
            return new \WP_Error('otp_error', $result->get_error_message(), array('status' => 500));
        }

        // Send OTP via email
        $email_sent = EmailEvents::send_otp_email($result['customer_email'], $result['generated_otp']);

        if (is_wp_error($email_sent)) {
            return new \WP_Error(
                'email_error',
                __('OTP saved but failed to send email.', 'bidfood'),
                array('status' => 500)
            );
        }

        return new \WP_REST_Response(array(
            'message' => __('OTP generated and sent successfully.', 'bidfood'),
        ), 200);
    }

    /**
     * Validates an OTP for a customer
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function validate_otp($request)
    {
        $driver_order_id = sanitize_text_field($request->get_param('driver_order_id'));
        $wh_order = UserDriverManager::get_wh_order_by_driver_order_id($driver_order_id);
        $customer_id = $wh_order['user_id'];
        $otp = sanitize_text_field($request->get_param('otp'));

        // Validate required parameters
        if (empty($customer_id) || empty($otp)) {
            return new \WP_Error(
                'missing_parameters',
                __('Customer ID and OTP are required.', 'bidfood'),
                array('status' => 400)
            );
        }

        // Validate OTP
        $result = DriverOrderManager::validate_customer_otp($customer_id, $otp);

        if (is_wp_error($result)) {
            return new \WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                array('status' => 400)
            );
        }

        return new \WP_REST_Response(array(
            'message' => __('OTP validated successfully.', 'bidfood'),
            'success' => true
        ), 200);
    }
    public function confirm_order_items($request)
    {
        // Extract parameters from the request
        $driver_order_id = (int) $request->get_param('driver_order_id');
        $item_statuses = $request->get_param('item_status');
        $customer_confirmed_amounts = $request->get_param('customer_confirmed_amount');

        // Validate input
        if (empty($driver_order_id) || empty($item_statuses)) {
            return new \WP_Error('invalid_input', __('Order ID and item statuses are required.', 'bidfood'), array('status' => 400));
        }
        $old_status = DriverOrderManager::get_driver_order_status($driver_order_id);
        $is_customer_confirmed = DriverOrderManager::is_customer_confirmed($driver_order_id);


        if( $old_status ==="Delivered"){
            return new \WP_Error('order_delivered', __('Order has already been delivered.', 'bidfood'), array('status' => 400));
        }elseif ($old_status === 'Dispatched') {
        if ($is_customer_confirmed) {
            return new \WP_Error('error', __('Order items have already been confirmed.', 'bidfood'));
        }
        // Call the method to update order items confirmation
        $result = CustomerOrderManager::update_order_items_confirmation($driver_order_id, $item_statuses, $customer_confirmed_amounts);
        if (is_wp_error($result)) {
            return $result; // Return the error from the update method
        }

        // Update the customer confirmation status
        $customer_confirmation_result = DriverOrderManager::update_customer_confirmation($driver_order_id, true);
        if (is_wp_error($customer_confirmation_result)) {
            return $customer_confirmation_result; // Return the error if updating customer confirmation fails
        }

        return new \WP_REST_Response(array('message' => __('Order items confirmed successfully.', 'bidfood')), 200);
    }
    }

    public function confirm_driver_order($request)
    {
        $driver_order_id = (int) $request->get_param('driver_order_id');
        $wh_order_id = (int) $request->get_param('wh_order_id');
        $is_driver_confirmed = DriverOrderManager::is_driver_confirmed($driver_order_id);
        
        // Validate 
        if ($is_driver_confirmed) {
            return new \WP_Error('error', __('Driver has already confirmed.', 'bidfood'));
        }
        // Check if the customer has also confirmed the order
        $is_customer_confirmed = DriverOrderManager::is_customer_confirmed($driver_order_id);
        if ($is_customer_confirmed) {
            // Update the driver order confirmation status
            $status_update_result = DriverOrderManager::update_driver_order_confirmation($driver_order_id, true);
            if (is_wp_error($status_update_result)) {
                return $status_update_result;
            }
            // Update the order status to Delivered if both driver and customer have confirmed
            $status_update_result = DriverOrderManager::update_driver_order_status($driver_order_id, 'Delivered');
            // Update the end time if the status is 'Delivered'
            $end_time_result = DriverOrderManager::update_driver_order_end_time($driver_order_id);
            if (is_wp_error($end_time_result)) {
                return new \WP_Error($end_time_result->get_error_code(), $end_time_result->get_error_message(), array('status' => 400));
            }
            
            WhOrderManager::update_wh_order_status($wh_order_id, 'Delivered');

            if (is_wp_error($status_update_result)) {
                return $status_update_result;
            }
        } else {
            // If only the driver confirmed, keep the status as Dispatched
            $status_update_result = DriverOrderManager::update_driver_order_status($driver_order_id, 'Dispatched');
            WhOrderManager::update_wh_order_status($wh_order_id, 'Dispatched');

            return new \WP_Error('error', __('Driver can not confirm. Waiting for customer confirmation.', 'bidfood'));
        }

        return new \WP_REST_Response(array('message' => __('Driver confirmation recorded successfully.', 'bidfood')), 200);
    }

    public function reconfirm_order_items($request)
    {
        $driver_order_id = (int) $request->get_param('driver_order_id');
        $wh_order=UserDriverManager::get_wh_order_by_driver_order_id($driver_order_id);
        $wh_order_id = $wh_order['wh_order_id'];
        $order_items = DriverOrderManager::get_driver_order_items($driver_order_id);
        $item_statuses = array() . $order_items['status'];
        $customer_confirmed_amounts = array() . $order_items['customer_confirmed_amount'];

        // Validate input
        if (empty($driver_order_id) || empty($item_statuses)) {
            return new \WP_Error('invalid_input', __('Order ID and item statuses are required.', 'bidfood'), array('status' => 400));
        }
        $is_customer_confirmed = DriverOrderManager::is_customer_confirmed($driver_order_id);
        if (!$is_customer_confirmed) {
            return new \WP_Error('error', __('customer have to confirm items. Waiting for customer confirmation.', 'bidfood'));
        }

        $old_status = DriverOrderManager::get_driver_order_status($driver_order_id);
        if( $old_status ==="Delivered"){
            return new \WP_Error('order_delivered', __('Order has already been delivered.', 'bidfood'), array('status' => 400));
        }elseif ($old_status === 'Dispatched') {

        // Call the method to update order items confirmation
        $result = CustomerOrderManager::update_order_items_confirmation($driver_order_id, $item_statuses, $customer_confirmed_amounts);
        if (is_wp_error($result)) {
            return $result;
        }

        // Reset the confirmation statuses
        $reset_customer_confirmation_result = DriverOrderManager::update_customer_confirmation($driver_order_id, false);
        if (is_wp_error($reset_customer_confirmation_result)) {
            return $reset_customer_confirmation_result;
        }

        $reset_driver_confirmation_result = DriverOrderManager::update_driver_order_confirmation($driver_order_id, false);
        if (is_wp_error($reset_driver_confirmation_result)) {
            return $reset_driver_confirmation_result;
        }

        // Update the order status to Dispatched
        $status_update_result = DriverOrderManager::update_driver_order_status($driver_order_id, 'Dispatched');
        if (is_wp_error($status_update_result)) {
            return $status_update_result;
        }

        $wh_status_update_result = WhOrderManager::update_wh_order_status($wh_order_id, 'Dispatched');
        if (is_wp_error($wh_status_update_result)) {
            return $wh_status_update_result;
        }

        return new \WP_REST_Response(array('message' => __('Order items reconfirmed successfully. Waiting for customer confirmation.', 'bidfood')), 200);
    }
}
}
