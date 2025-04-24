<?php

namespace Bidfood\Core\Events;

use Bidfood\Core\UserManagement\UserSupplierManager;

class SupplierPoEmailEvents
{
    public function __construct()
    {
        // Event: Supplier PO Submitted
        add_action('supplier_po_submitted', [$this, 'send_po_submission_emails'], 10, 1);
    }

    public static function init()
    {
        return new self();
    }

    // Email Notifications
    public static function send_po_submission_emails($po_id)
    {
        self::send_admin_po_submission_email($po_id);
        self::send_supplier_po_submission_email($po_id);
        self::send_customer_po_submission_email($po_id);
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

    public static function send_admin_po_submission_email($po_id)
    {
        // Get admin emails
        $admin_emails = EmailEvents::get_admins_emails();
        $title = "Supplier PO Submitted";
        $greeting = "Dear Admin,";
        $content = "<p style='font-size: 16px; color: #333;'>A supplier has submitted a PO #$po_id. Please review the details and take necessary actions.</p>";
        $content .= "<p><a href='" . esc_url(wp_nonce_url(add_query_arg(['bidfood_invoice' => 1, 'type' => 'admin', 'po_id' => $po_id], home_url()), 'download_invoice')) . "' class='button'>Download PDF Invoice</a></p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail($admin_emails, $title, $message, $headers);
    }

    public static function send_supplier_po_submission_email($po_id)
    {
        $supplier_id = UserSupplierManager::get_users_by_supplier($po_id);
        $supplier = get_userdata($supplier_id[0]);
        $supplier_email = $supplier ? $supplier->user_email : '';
        $title = "Your PO Submission Received";
        $greeting = "Dear Supplier,";
        $content = "<p style='font-size: 16px; color: #333;'>Thank you for submitting your PO #$po_id. Please find the details and download the invoice below.</p>";
        $content .= "<p><a href='" . esc_url(wp_nonce_url(add_query_arg(['bidfood_invoice' => 1, 'type' => 'supplier', 'po_id' => $po_id], home_url()), 'download_invoice')) . "' class='button'>Download PDF Invoice</a></p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($supplier_email, $title, $message, $headers);
    }

    public static function send_customer_po_submission_email($po_id)
    {
        $customer_obj = UserSupplierManager::get_customer_data_by_po($po_id);
        $customer_id = $customer_obj['customer_id'];
        $customer = get_userdata($customer_id);
        $customer_email = $customer ? $customer->user_email : '';
        $title = "Order Update: PO Submitted";
        $greeting = "Dear Customer,";
        $content = "<p style='font-size: 16px; color: #333;'>Your order's PO #$po_id has been submitted by the supplier. You can download the invoice below.</p>";
        $content .= "<p><a href='" . esc_url(wp_nonce_url(add_query_arg(['bidfood_invoice' => 1, 'type' => 'customer', 'po_id' => $po_id], home_url()), 'download_invoice')) . "' class='button'>Download PDF Invoice</a></p>";
        $message = self::create_email_message($title, $greeting, $content);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($customer_email, $title, $message, $headers);
    }
}