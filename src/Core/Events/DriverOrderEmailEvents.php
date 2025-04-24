<?php

namespace Bidfood\Core\Events;

use Bidfood\Core\UserManagement\UserDriverManager;

class DriverOrderEmailEvents
{
    public function __construct()
    {
        // Event: WH Order Status Changed
        add_action('wh_order_status_changed', [$this, 'send_wh_order_status_emails'], 10, 2);

        // Event: Driver Order Status Changed
        add_action('driver_order_status_changed', [$this, 'send_driver_order_status_emails'], 10, 2);

        // Event: Driver Order Skip Request Approved
        add_action('driver_order_skip_request_approved', [$this, 'send_driver_order_skip_request_approved_emails']);

        // Event: Driver Order Skip Request Rejected
        add_action('driver_order_skip_request_rejected', [$this, 'send_driver_order_skip_request_rejected_emails']);
    }

    public static function init()
    {
        return new self();
    }

    // Email Notifications
    public static function send_wh_order_status_emails($order_id, $status)
    {
        self::send_admin_wh_order_status_email($order_id, $status);
        if ($status == 'Dispatched' || $status == 'Delivered') {
            self::send_customer_wh_order_status_email($order_id, $status);
        }
        if ($status == 'Assigned to Driver') {
            self::send_driver_wh_order_status_email($order_id, $status);
        }
    }

    public static function send_driver_order_status_emails($order_id, $status)
    {
        self::send_admin_driver_order_status_email($order_id, $status);
        if (!$status == 'Skipped' || !$status == 'Skipped by WH') {
            self::send_customer_driver_order_status_email($order_id, $status);
        }
        if ($status == 'Skipped' || $status == 'Skipped by WH' || $status == 'Cancelled') {
            self::send_driver_driver_order_status_email($order_id, $status);
        }
    }

    public static function send_driver_order_skip_request_approved_emails($request_id)
    {
        self::send_admin_driver_order_skip_request_approved_email($request_id);
        self::send_driver_driver_order_skip_request_approved_email($request_id);
    }

    public static function send_driver_order_skip_request_rejected_emails($request_id)
    {
        self::send_admin_driver_order_skip_request_rejected_email($request_id);
        self::send_driver_driver_order_skip_request_rejected_email($request_id);
    }

    private static function create_email_message($title, $greeting, $content)
    {
        // Attempt to retrieve the custom logo, fallback to site icon if not set
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = '';
        if ($custom_logo_id) {
            // Get the URL of the custom logo
            $logo_url = wp_get_attachment_image_src($custom_logo_id, 'full')[0];
        } elseif (has_site_icon()) {
            // Use the site icon as a fallback if no custom logo is set
            $logo_url = get_site_icon_url();
        }

        // Create the email message with HTML and inline CSS
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">' . esc_html($title) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">' . esc_html($greeting) . '</p>';
        $message .= $content;

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Best regards,<br>Bidfood System</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        return $message;
    }

    public static function send_admin_wh_order_status_email($order_id, $status)
    {
        // Get admin emails
        $admin_emails = UserDriverManager::get_admins_emails();
        // $admin_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];
        $title = "WH Order Status Changed";
        $greeting = "Dear Admin,";
        $content = "<p style='font-size: 16px; color: #333;'>The status of WH order #$order_id has been changed to $status.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_emails, $title, $message, $headers);
    }

    public static function send_customer_wh_order_status_email($order_id, $status)
    {
        $customer_email = self::get_customer_email_by_order($order_id);
        $title = "Your BF Order Status Changed";
        $greeting = "Dear Customer,";
        $content = "<p style='font-size: 16px; color: #333;'>The status of your BF order #$order_id has been changed to $status.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($customer_email, $title, $message, $headers);
    }

    public static function send_driver_wh_order_status_email($order_id, $status)
    {
        $driver_id = self::get_driver_id_by_wh_order($order_id);
        $driver_email = UserDriverManager::get_driver_email($driver_id);
        $title = "WH Order Status Changed";
        $greeting = "Dear Driver,";
        $content = "<p style='font-size: 16px; color: #333;'>You have been assigned to deliver order: #$order_id from BF Warehouse.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($driver_email, $title, $message, $headers);
    }

    public static function send_admin_driver_order_status_email($order_id, $status)
    {
        // Get admin emails
        $admin_emails = UserDriverManager::get_admins_emails();
        // $admin_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];   
        $title = "Driver Order Status Changed";
        $greeting = "Dear Admin,";
        $content = "<p style='font-size: 16px; color: #333;'>The status of Driver order #$order_id has been changed to $status.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_emails, $title, $message, $headers);
    }

    public static function send_customer_driver_order_status_email($order_id, $status)
    {
        $customer_email = self::get_customer_email_by_order($order_id);
        $title = "Your Order Status Changed";
        $greeting = "Dear Customer,";
        $content = "<p style='font-size: 16px; color: #333;'>The status of your BF order #$order_id has been changed to $status.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($customer_email, $title, $message, $headers);
    }

    public static function send_driver_driver_order_status_email($order_id, $status)
    {
        $driver_id = self::get_driver_id_by_driver_order($order_id);
        $driver_email = UserDriverManager::get_driver_email($driver_id);
        $title = "Order Status Changed";
        $greeting = "Dear Driver,";
        $content = "<p style='font-size: 16px; color: #333;'>The status of your assigned order #$order_id has been changed to $status.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($driver_email, $title, $message, $headers);
    }

    public static function send_admin_driver_order_skip_request_approved_email($request_id)
    {
        // Get admin emails
        $admin_emails = UserDriverManager::get_admins_emails();
        // $admin_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];
        $title = "Driver Order Skip Request Approved";
        $greeting = "Dear Admin,";
        $content = "<p style='font-size: 16px; color: #333;'>The driver order skip request #$request_id has been approved.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');


        wp_mail($admin_emails, $title, $message, $headers);
    }

    public static function send_driver_driver_order_skip_request_approved_email($request_id)
    {
        $driver_id = self::get_driver_id_by_skip_request($request_id);
        $driver_email = UserDriverManager::get_driver_email($driver_id);
        $title = "Order Skip Request Approved";
        $greeting = "Dear Driver,";
        $content = "<p style='font-size: 16px; color: #333;'>Your order skip request #$request_id has been approved.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($driver_email, $title, $message, $headers);
    }

    public static function send_admin_driver_order_skip_request_rejected_email($request_id)
    {
        // Get admin emails
        $admin_emails = UserDriverManager::get_admins_emails();
        // $admin_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];
        $title = "Driver Order Skip Request Rejected";
        $greeting = "Dear Admin,";
        $content = "<p style='font-size: 16px; color: #333;'>The driver order skip request #$request_id has been rejected.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_emails, $title, $message, $headers);
    }

    public static function send_driver_driver_order_skip_request_rejected_email($request_id)
    {
        $driver_id = self::get_driver_id_by_skip_request($request_id);
        $driver_email = UserDriverManager::get_driver_email($driver_id);
        $title = "Order Skip Request Rejected";
        $greeting = "Dear Driver,";
        $content = "<p style='font-size: 16px; color: #333;'>Your order skip request #$request_id has been rejected.</p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($driver_email, $title, $message, $headers);
    }

    // Helper Functions
    private static function get_customer_email_by_order($order_id)
    {
        $order = wc_get_order($order_id);
        return $order ? $order->get_billing_email() : '';
    }

    private static function get_driver_id_by_wh_order($wh_order_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT driver_id FROM {$wpdb->prefix}neom_driver_orders WHERE wh_order_id = %d",
            $wh_order_id
        ));
    }

    private static function get_driver_id_by_driver_order($driver_order_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT driver_id FROM {$wpdb->prefix}neom_driver_orders WHERE driver_order_id = %d",
            $driver_order_id
        ));
    }

    private static function get_driver_id_by_skip_request($request_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT driver_id FROM {$wpdb->prefix}neom_skip_order_requests WHERE id = %d",
            $request_id
        ));
    }
}
