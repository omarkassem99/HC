<?php
namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Core\OrderManagement\DriverOrderManager;
use WP_Error;
use Bidfood\Core\UserManagement\UserDriverManager;

class DriverAuth
{
    /**
     * Authenticate driver by email and password.
     *
     * @param string $email
     * @param string $password
     * @return array|WP_Error - User data on success, WP_Error on failure.
     */
    public static function authenticate($email, $password)
    {
        $driver = UserDriverManager::get_driver_by_email($email);
        if (is_wp_error($driver)) {
            return new WP_Error('authentication_failed', 'Invalid email or password.');
        }

        if (!wp_check_password($password, $driver['password'])) {
            return new WP_Error('authentication_failed', 'Invalid email or password.');
        }

        // Start session if not already started
        self::start_session();

        // Store user data in session
        $_SESSION['driver_id'] = $driver['id'];
        $_SESSION['driver_email'] = $driver['email'];

        // Remove sensitive data from the user array
        unset($user['password']);

        // Fetch additional driver info
        $driver_info = UserDriverManager::getDriverById($driver['id']);
        if (is_wp_error($driver_info)) {
            return new WP_Error('driver_info_failed', 'Failed to retrieve driver information.');
        }

        // Combine user data with driver info
        $driver_data = array_merge($driver, [
            'first_name' => $driver_info['first_name'],
            'last_name' => $driver_info['last_name'],
            'phone' => $driver_info['phone'],
        ]);

        return $driver_data; // Return combined user data
    }

    /**
     * Check if the user is logged in.
     *
     * @return bool
     */
    public static function is_logged_in()
    {
        self::start_session();
        return isset($_SESSION['driver_id']);
    }


    /**
     * Check if the current driver is permitted to perform an action.
     *
     * @param int $driver_order_id
     * @return bool|WP_Error - True if permitted, WP_Error if not.
     */
    public static function is_permitted($driver_order_id)
    {
        self::start_session();
        
        // Check if driver is logged in
        if (!self::is_logged_in()) {
            return new WP_Error('not_logged_in', 'You are not logged in.', array('status' => 401));
        }

        // Get the current driver ID from the session
        $driver_id = get_current_driver_id();

        // Retrieve the driver order details
        $driver_order = DriverOrderManager::get_driver_order_by_driver_order_id($driver_order_id);

        // Check if the retrieved order belongs to the current driver
        if (!is_wp_error($driver_order) && $driver_order['driver_id'] == $driver_id) {
            return true; // Permission granted
        }

        return new WP_Error('permission_denied', 'You do not have permission to perform this action.', array('status' => 403));
    }
    /**
     * Logout the user.
     */
    public static function logout()
    {
        // Start session if not already started
        self::start_session();

        // Clear session data for the user
        unset($_SESSION['driver_id'], $_SESSION['driver_email']);

        // Optional: Clear the PHP session entirely if it is only used for this user
        session_write_close();
        return true;
    }

    /**
     * Start session if not already started.
     */
    public static function start_session()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

// Start session on init
add_action('init', function () {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}, 1);
