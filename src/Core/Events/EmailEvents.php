<?php

namespace Bidfood\Core\Events;

use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\Core\WooCommerce\Product\ProductQueryManager;

class EmailEvents
{

    public function __construct()
    {
        /* ------------------- Actions ------------------- */
        // Action on PO Initiated
        add_action('bidfood_po_initiated_emails', [$this, 'send_po_initiated_emails']);

        // Action on PO Submitted
        add_action('bidfood_po_submitted_emails', [$this, 'send_po_submitted_emails']);

        // Action on Supplier Request Initiated
        add_action('bidfood_supplier_request_initiated_emails', [$this, 'send_supplier_request_initiated_emails']);
        // Action on Supplier add item Request Initiated
        add_action('bidfood_supplier_add_item_request_initiated_emails', [$this, 'send_supplier_add_item_request_initiated_emails']);
     
        // Action on Supplier Request Approved
        add_action('bidfood_supplier_request_approved_emails', [$this, 'send_supplier_request_approved_emails']);
        // Action on Supplier add item Request Approved
        add_action('bidfood_supplier_add_item_request_approved_emails', [$this, 'send_supplier_add_item_request_approved_emails']);
        // Action on Supplier Request Rejected
        add_action('bidfood_supplier_request_rejected_emails', [$this, 'send_supplier_request_rejected_emails']);
        // Action on Supplier add item Request Rejected
        add_action('bidfood_supplier_add_item_request_rejected_emails', [$this, 'send_supplier_add_item_request_rejected_emails']);
        // Action on Supplier Request Cancelled
        add_action('bidfood_supplier_request_cancelled_emails', [$this, 'send_supplier_request_cancelled_emails']);
    }

    public static function init()
    {
        return new self();
    }


    /* ------------------- Email Notifications ------------------- */

    /* ------------------- Events ------------------- */

    // Event: PO Initiated
    public static function send_po_initiated_emails($po_id)
    {
        self::send_admin_po_initiated_email($po_id);
        self::send_supplier_po_initiated_email($po_id);
    }

    // Event: PO Submitted
    public static function send_po_submitted_emails($po_id)
    {
        self::send_admin_po_submitted_email($po_id);
        self::send_supplier_po_submitted_email($po_id);
        self::send_customer_po_submitted_emails($po_id);
    }

    // Event: Supplier Request Initiated
    public static function send_supplier_request_initiated_emails($supplier_request_id)
    {
        self::send_admin_supplier_request_initiated_email($supplier_request_id);
        self::send_supplier_supplier_request_initiated_email($supplier_request_id);
    }
    // Event: Supplier add item Request Initiated
    public static function send_supplier_add_item_request_initiated_emails($supplier_request_id)
    {
        self::send_admin_supplier_add_item_request_initiated_email($supplier_request_id);
        self::send_supplier_supplier_add_item_request_initiated_email($supplier_request_id);
    }

    // Event: Supplier Request Approved
    public static function send_supplier_request_approved_emails($supplier_request_id)
    {
        self::send_supplier_supplier_request_approved_email($supplier_request_id);
    }
    // Event: Supplier add item Request Approved
    public static function send_supplier_add_item_request_approved_emails($supplier_request_id)
    {
        self::send_supplier_supplier_add_item_request_approved_email($supplier_request_id);
    }
    // Event: Supplier Request Rejected
    public static function send_supplier_request_rejected_emails($supplier_request_id)
    {
        self::send_supplier_supplier_request_rejected_email($supplier_request_id);
    }
    // Event: Supplier add item Request Rejected
    public static function send_supplier_add_item_request_rejected_emails($supplier_request_id)
    {
        self::send_supplier_supplier_add_item_request_rejected_email($supplier_request_id);
    }
    // Event: Supplier Request Cancelled
    public static function send_supplier_request_cancelled_emails($supplier_request_id)
    {
        self::send_supplier_supplier_request_cancelled_email($supplier_request_id);
    }

    /* ------------------- Order Placed ------------------- */
    public static function send_customer_order_placed_email($order_id)
    {
        // Get the WooCommerce order object
        $order = wc_get_order($order_id);
        // Get the items in the order
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $packaging_per_uom = $product->get_attribute('packaging_per_uom');

            $items[] = array(
                'item_id' => $product->get_sku(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity()
            );
        }
        // Get the customer email and name
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Create the URL for the customer to view their order details
        $order_details_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

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

        // Create the email content with inline CSS
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">Your Order #' . esc_html($order_id) . ' Has Been Placed</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear ' . esc_html($customer_name ?? 'Customer') . ',</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Thank you for your order, kindly find the details of your purchase below.</p>';

        // Order number
        $message .= '<p style="font-size: 16px; color: #333; font-weight: bold;">Order ID: ' . $order_id . '</p>';

        // Placement date
        $message .= '<p style="font-size: 16px; color: #333;">Placed on: ' . $order->get_date_created()->date('d/m/Y') . '</p>';
        // Start the table with centered content
        $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $message .= '<tr style="background-color: #10014b; color: #ffffff;">';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Packaging</th>';

        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Quantity</th>';
        $message .= '</tr>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($packaging_per_uom) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '</tr>';
        }
        $message .= '</table>';
        // Add a link to view the order details
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($order_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Order Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your order,<br>Bidfood Team</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the customer
        wp_mail($customer_email, 'Your Order #' . $order_id . ' has been received!', $message, $headers);
    }

    public static function send_admin_order_placed_email($order_id)
    {
        // Get the admin email(s)
        $admins_emails = self::get_admins_emails();
        // $admins_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];

        // Get the WooCommerce order object
        $order = wc_get_order($order_id);

        // Get the items in the order
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = array(
                'item_id' => $product->get_sku(),
                'product_name' => $product->get_name(),
                'quantity' => $item->get_quantity()
            );
        }

        // Create the URL to view the order details page in the admin
        $order_details_url = admin_url('post.php?post=' . $order_id . '&action=edit');

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

        // Create the email message with HTML
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">New Order Placed - Order #' . $order_id . '</h2>';

        // Opening Greeting
        $message .= '<p style="color: #333;">Dear Admin,</p>';
        $message .= '<p style="color: #333;">A new order has been placed with the following details:</p>';
        $message .= '<p style="color: #333; font-weight: bold;">Order ID: ' . $order_id . '</p>';
        $message .= '<p style="color: #333;">Items:</p>';

        // Start the table with centered content
        $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $message .= '<tr style="background-color: #10014b; color: #ffffff;">';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Quantity</th>';
        $message .= '</tr>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '</tr>';
        }

        $message .= '</table>';

        // Add a centered button to navigate to the order details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($order_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Order Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="color: #333; margin-top: 20px;">Thank you,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the admin
        wp_mail($admins_emails, 'New Order Placed - Order #' . $order_id, $message, $headers);
    }


    /* ------------------- PO Initiated ------------------- */
    public static function send_admin_po_initiated_email($po_id)
    {
        // Get the admin email(s)
        $admins_emails = self::get_admins_emails();
        // $admins_emails = ['momenk208@gmail.com','omarabuelkhier@gmail.com'];


        // Get the items in this supplier PO
        $items = self::get_po_items($po_id);

        // Create the URL to view the supplier PO details page
        $po_details_url = admin_url('admin.php?page=supplier-po-details&po_id=' . $po_id);

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

        // Create the email message with HTML
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier PO Initiated - PO #' . $po_id . '</h2>';

        // Opening Greeting
        $message .= '<p style="color: #333;">Dear Admin,</p>';
        $message .= '<p style="color: #333;">A new Supplier PO has been initiated with the following details:</p>';
        $message .= '<p style="color: #333; font-weight: bold;">PO ID: ' . $po_id . '</p>';
        $message .= '<p style="color: #333;">Items:</p>';

        // Start the table with centered content
        $message .= '<table border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $message .= '<tr style="background-color: #10014b; color: #ffffff;">';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Quantity</th>';
        $message .= '</tr>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '</tr>';
        }

        $message .= '</table>';

        // Add a centered button to navigate to the PO details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($po_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View PO Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="color: #333; margin-top: 20px;">Thank you,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the admin
        wp_mail($admins_emails, 'New Supplier PO Initiated - PO #' . $po_id, $message, $headers);
    }

    public static function send_supplier_po_initiated_email($po_id)
    {
        // Get the items and supplier email
        $items = self::get_po_items($po_id);
        $user = self::get_supplier_po_user($po_id);
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier PO details page
        $po_details_url = wc_get_endpoint_url('supplier-po-details', $po_id, wc_get_page_permalink('myaccount'));

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

        // Create the email message with HTML
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">Supplier PO #' . esc_html($po_id) . ' Assigned</h2>';

        // Opening Greeting
        $message .= '<p style="color: #333;">Dear Supplier,</p>';
        $message .= '<p style="color: #333;">You have been assigned the following items from Supplier PO #' . $po_id . ':</p>';

        // Start the table with centered content
        $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
        $message .= '<tr style="background-color: #10014b; color: #ffffff;">';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center; border-bottom: 2px solid #f2f2f2;">Quantity</th>';
        $message .= '</tr>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '</tr>';
        }

        $message .= '</table>';

        // Add a centered button to navigate to the PO details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($po_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View PO Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="color: #333; margin-top: 20px;">Thank you,<br>Bidfood Team</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email
        wp_mail($supplier_email, 'New Supplier PO Assigned - PO #' . $po_id, $message, $headers);
    }

    public static function send_customer_po_initiated_email($order_id)
    {
        // Get the WooCommerce order object
        $order = wc_get_order($order_id);

        // Get the customer email and name
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

        // Create the URL for the customer to view their order details
        $order_details_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

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

        // Create the email content with inline CSS
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">Your Order #' . esc_html($order_id) . ' Has Been Sent to the Suppliers</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear ' . esc_html($customer_name) . ',</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your order has been sent to the suppliers. You will receive an email once the suppliers have responded to your order.</p>';

        // Add a link to view the order details
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($order_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Order Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your order,<br>Bidfood Team</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the customer
        wp_mail($customer_email, 'Your Order #' . $order_id . ' Has Been Sent to the Suppliers', $message, $headers);
    }


    /* ------------------- PO Submitted ------------------- */
    public static function send_admin_po_submitted_email($po_id)
    {
        // Get the admin email(s)
        $admin_email = get_option('admin_email'); // This gets the WordPress admin email

        // Get the items in this supplier PO
        $items = self::get_po_items($po_id);

        // Create the URL to view the supplier PO details page
        $po_details_url = admin_url('admin.php?page=supplier-po-details&po_id=' . $po_id);

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
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier PO Submission - PO #' . esc_html($po_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Admin,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">A new submission for Supplier PO #' . $po_id . ' has been received, containing the following items:</p>';

        // Start the table with inline styles
        $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $message .= '<thead style="background-color: #10014b; color: #ffffff;">';
        $message .= '<tr>';
        $message .= '<th style="padding: 10px; text-align: center;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Quantity</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Status</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Supplier Notes</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Expected Delivery Date</th>';
        $message .= '</tr>';
        $message .= '</thead>';
        $message .= '<tbody>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['status']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['supplier_notes']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($item['expected_delivery_date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($item['expected_delivery_date']))) : 'N/A') . '</td>';
            $message .= '</tr>';
        }

        $message .= '</tbody>';
        $message .= '</table>';

        // Add a centered button to navigate to the PO details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($po_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View PO Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Best regards,<br>Bidfood System</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the admin
        wp_mail($admin_email, 'New PO Submission Received - PO #' . $po_id, $message, $headers);
    }

    public static function send_supplier_po_submitted_email($po_id)
    {
        // Get the items in this supplier PO
        $items = self::get_po_items($po_id);
        $user = self::get_supplier_po_user($po_id);
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier PO details page
        $po_details_url = wc_get_endpoint_url('supplier-po-details', $po_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier PO Submission - PO #' . esc_html($po_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">We have received your submission for Supplier PO #' . $po_id . ' containing the following items:</p>';

        // Start the table with inline styles
        $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
        $message .= '<thead style="background-color: #10014b; color: #ffffff;">';
        $message .= '<tr>';
        $message .= '<th style="padding: 10px; text-align: center;">Item ID</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Product Name</th>';
        $message .= '<th style="padding: 10px; text-align: center;">UOM</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Quantity</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Status</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Supplier Notes</th>';
        $message .= '<th style="padding: 10px; text-align: center;">Expected Delivery Date</th>';
        $message .= '</tr>';
        $message .= '</thead>';
        $message .= '<tbody>';

        // Loop through the items and add them to the table
        foreach ($items as $item) {
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);

            $message .= '<tr style="background-color: #f9f9f9;">';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['status']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['supplier_notes']) . '</td>';
            $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($item['expected_delivery_date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($item['expected_delivery_date']))) : 'N/A') . '</td>';
            $message .= '</tr>';
        }

        $message .= '</tbody>';
        $message .= '</table>';

        // Add a centered button to navigate to the PO details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($po_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View PO Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your submission,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email
        wp_mail($supplier_email, 'Supplier PO Submission - PO #' . $po_id, $message, $headers);
    }

    public static function send_customer_po_submitted_emails($po_id)
    {
        // Get the items in this supplier PO
        $items = self::get_po_items($po_id);

        // Get the WooCommerce order IDs in this supplier PO
        $order_ids = UserSupplierManager::get_supplier_po_orders($po_id);

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

        // Loop through each order to find the customer and their corresponding items
        foreach ($order_ids as $order_id) {
            // Get the WooCommerce order object
            $order = wc_get_order($order_id);
            if (!$order) {
                continue;  // Skip if the order does not exist
            }

            // Get the customer email and name
            $customer_email = $order->get_billing_email();
            $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

            // Find all items in this order that are assigned to this customer
            $customer_items = array_filter($items, function ($item) use ($order_id) {
                return $item['order_id'] == $order_id;
            });

            // If there are no items for this customer, skip
            if (empty($customer_items)) {
                continue;
            }

            // Create the URL for the customer to view their order details
            $order_details_url = wc_get_endpoint_url('view-order', $order_id, wc_get_page_permalink('myaccount'));

            // Create the email content with inline CSS
            $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
            $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

            // Include the logo if available
            if ($logo_url) {
                $message .= '<div style="text-align: center;">';
                $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
                $message .= '</div>';
            }

            // Email Header
            $message .= '<h2 style="text-align: center; color: #333;">Supplier Response for Your Order #' . esc_html($order_id) . '</h2>';

            // Greeting
            $message .= '<p style="font-size: 16px; color: #333;">Dear ' . esc_html($customer_name) . ',</p>';
            $message .= '<p style="font-size: 16px; color: #333;">The supplier has responded to the following items for your order #' . esc_html($order_id) . ':</p>';

            // Start the table with inline styles
            $message .= '<table border="0" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-top: 20px;">';
            $message .= '<thead style="background-color: #10014b; color: #ffffff;">';
            $message .= '<tr>';
            $message .= '<th style="padding: 10px; text-align: center;">Item ID</th>';
            $message .= '<th style="padding: 10px; text-align: center;">Product Name</th>';
            $message .= '<th style="padding: 10px; text-align: center;">UOM</th>';
            $message .= '<th style="padding: 10px; text-align: center;">Quantity</th>';
            $message .= '<th style="padding: 10px; text-align: center;">Status</th>';
            $message .= '<th style="padding: 10px; text-align: center;">Supplier Notes</th>';
            $message .= '<th style="padding: 10px; text-align: center;">Expected Delivery Date</th>';
            $message .= '</tr>';
            $message .= '</thead>';
            $message .= '<tbody>';

            // Loop through the customer's items and add them to the table
            foreach ($customer_items as $item) {
                $product_id = wc_get_product_id_by_sku($item['item_id']);
                $uom = ProductQueryManager::get_product_uom($product_id);

                $message .= '<tr style="background-color: #f9f9f9;">';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['item_id']) . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['product_name']) . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($uom ? esc_html($uom->uom_description) : 'N/A') . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['quantity']) . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['status']) . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . esc_html($item['supplier_notes']) . '</td>';
                $message .= '<td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">' . ($item['expected_delivery_date'] ? esc_html(date_i18n(get_option('date_format'), strtotime($item['expected_delivery_date']))) : 'N/A') . '</td>';
                $message .= '</tr>';
            }

            $message .= '</tbody>';
            $message .= '</table>';

            // Add a link to view the order details
            $message .= '<div style="text-align: center; margin-top: 30px;">';
            $message .= '<a href="' . esc_url($order_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Order Details</a>';
            $message .= '</div>';

            // Closing Note
            $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you,<br>Bidfood Team</p>';
            $message .= '</div>';
            $message .= '</body></html>';

            // Set email headers to handle HTML content
            $headers = array('Content-Type: text/html; charset=UTF-8');

            // Send the email to the customer
            wp_mail($customer_email, 'Supplier Response for Your Order #' . $order_id, $message, $headers);
        }
    }

    /* ------------------- Supplier Request Initiated ------------------- */

    public static function send_admin_supplier_request_initiated_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_request_details($supplier_request_id);

        // Get the admin email(s)
        $admin_email = self::get_admins_emails(); // This gets the WordPress admin email

        // Create the URL to view the supplier request details page
        $request_details_url = admin_url('admin.php?page=bidfood-neom-settings&tab=supplier_requests&supplier_requests_tab=supplier_update_requests');

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
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier Request Initiated - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Admin,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">A new supplier request has been initiated with the following details:</p>';

        // Request Details
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Supplier ID:</strong> ' . esc_html($request_details['supplier_id']) . '</p>';
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Request Type:</strong> ' . esc_html($request_details['field']) . '</p>';
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Supplier Notes:</strong> ' . esc_html($request_details['supplier_notes'] ? $request_details['supplier_notes'] : 'N/A') . '</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Best regards,<br>Bidfood System</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the admin
        wp_mail($admin_email, 'New Supplier Request Initiated - Request #' . $supplier_request_id, $message, $headers);
    }

    public static function send_supplier_supplier_request_initiated_email($supplier_request_id)
    {
        $user = self::get_supplier_request_user($supplier_request_id);
        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier Request Initiated - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">You have initiated a new request #' . esc_html($supplier_request_id) . ' </p>';

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">We have received your request and will review it shortly. You will receive an email once the request has been processed.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'New Supplier Request Initiated - Request #' . $supplier_request_id, $message, $headers);
    }

    public static function send_admin_supplier_add_item_request_initiated_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_add_item_request_details($supplier_request_id);
    
        // Get the admin email(s)
        $admin_email = self::get_admins_emails(); // This gets the WordPress admin email
    
        // Create the URL to view the supplier request details page
        $request_details_url = admin_url('admin.php?page=bidfood-neom-settings&tab=supplier_requests&supplier_requests_tab=supplier_add_item_requests');
    
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
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier Add Item Request Initiated - Request #' . esc_html($supplier_request_id) . '</h2>';
    
        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Admin,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">A new supplier add item request has been initiated with the following details:</p>';
    
        // Request Details
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Supplier ID:</strong> ' . esc_html($request_details['supplier_id']) . '</p>';
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Request Type:</strong> Add new Item </p>';
        $message .= '<p style="font-size: 16px; color: #333;"><strong>Supplier Notes:</strong> ' . esc_html($request_details['supplier_notes'] ? $request_details['supplier_notes'] : 'N/A') . '</p>';
    
        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';
    
        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Best regards,<br>Bidfood System</p>';
        $message .= '</div>';
        $message .= '</body></html>';
    
        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');
    
        // Send the email to the admin
        wp_mail($admin_email, 'New Supplier Add Item Request Initiated - Request #' . $supplier_request_id, $message, $headers);
    } // 

    public static function send_supplier_supplier_add_item_request_initiated_email($supplier_request_id)
    {
        $user = self::get_supplier_request_user($supplier_request_id,'supplier_add_item');
        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">New Supplier Add Item Request Initiated - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">You have initiated a new item request #' . esc_html($supplier_request_id) . ' </p>';

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">We have received your request and will review it shortly. You will receive an email once the request has been processed.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'New Supplier Add Item Request Initiated - Request #' . $supplier_request_id, $message, $headers);
    }

    /* ------------------- Supplier Request Approved ------------------- */

    public static function send_supplier_supplier_request_approved_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_request_details($supplier_request_id);
        $user = self::get_supplier_request_user($supplier_request_id);
        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier Request Approved - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your request #' . esc_html($supplier_request_id) . ' has been approved.</p>';

        // Admin Notes (if available)
        $admin_notes = $request_details['admin_notes'];
        if ($admin_notes) {
            $message .= '<p style="font-size: 16px; color: #333;"><strong>Bidfood Notes:</strong> ' . esc_html($admin_notes) . '</p>';
        }

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">You can view the details of the approved request by clicking the button below.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'Supplier Request Approved - Request #' . $supplier_request_id, $message, $headers);
    }

    /* ------------------- Supplier add item Request Approved ------------------- */
    public static function send_supplier_supplier_add_item_request_approved_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_add_item_request_details($supplier_request_id);
        $user = self::get_supplier_request_user($supplier_request_id, 'supplier_add_item');

        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }

        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier Add Item Request Approved - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your request #' . esc_html($supplier_request_id) . ' has been approved.</p>';

        // Admin Notes (if available)
        $admin_notes = $request_details['admin_notes'];
        if ($admin_notes) {
            $message .= '<p style="font-size: 16px; color: #333;"><strong>Bidfood Notes:</strong> ' . esc_html($admin_notes) . '</p>';
        }

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">You can view the details of the approved request by clicking the button below.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'Supplier Add Item Request Approved - Request #' . $supplier_request_id, $message, $headers);
    }

    /* ------------------- Supplier Request Rejected ------------------- */

    public static function send_supplier_supplier_request_rejected_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_request_details($supplier_request_id);
        $user = self::get_supplier_request_user($supplier_request_id);
        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier Request Rejected - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your request #' . esc_html($supplier_request_id) . ' has been rejected.</p>';

        // Admin Notes (if available)
        $admin_notes = $request_details['admin_notes'];
        if ($admin_notes) {
            $message .= '<p style="font-size: 16px; color: #333;"><strong>Bidfood Notes:</strong> ' . esc_html($admin_notes) . '</p>';
        }

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">You can view the details of the rejected request by clicking the button below.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'Supplier Request Rejected - Request #' . $supplier_request_id, $message, $headers);
    }

    public static function send_supplier_supplier_add_item_request_rejected_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_add_item_request_details($supplier_request_id);
        $user = self::get_supplier_request_user($supplier_request_id, 'supplier_add_item');
        if (is_wp_error($user)) {
            error_log($user->get_error_message());
            return;
        }
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier Add Item Request Rejected - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your request for adding items to the inventory has been rejected.</p>';

        // Admin Notes (if available)
        $admin_notes = $request_details['admin_notes'];
        if ($admin_notes) {
            $message .= '<p style="font-size: 16px; color: #333;"><strong>Bidfood Notes:</strong> ' . esc_html($admin_notes) . '</p>';
        }

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">You can view the details of the rejected request by clicking the button below.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'Supplier Add Item Request Rejected - Request #' . $supplier_request_id, $message, $headers);
    }
    /* ------------------- Supplier Request Cancelled ------------------- */
    public static function send_supplier_supplier_request_cancelled_email($supplier_request_id)
    {
        $request_details = UserSupplierManager::get_supplier_request_details($supplier_request_id);
        $user = self::get_supplier_request_user($supplier_request_id);
        $supplier_email = $user->user_email;

        // Create the URL to view the supplier request details page
        $request_details_url = wc_get_endpoint_url('supplier-requests', $supplier_request_id, wc_get_page_permalink('myaccount'));

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
        $message .= '<h2 style="text-align: center; color: #333;">Supplier Request Cancelled - Request #' . esc_html($supplier_request_id) . '</h2>';

        // Greeting
        $message .= '<p style="font-size: 16px; color: #333;">Dear Supplier,</p>';
        $message .= '<p style="font-size: 16px; color: #333;">Your request #' . esc_html($supplier_request_id) . ' has been cancelled.</p>';

        // Admin Notes (if available)
        $admin_notes = $request_details['admin_notes'];
        if ($admin_notes) {
            $message .= '<p style="font-size: 16px; color: #333;"><strong>Bidfood Notes:</strong> ' . esc_html($admin_notes) . '</p>';
        }

        // Message
        $message .= '<p style="font-size: 16px; color: #333;">You can view the details of the cancelled request by clicking the button below.</p>';

        // Add a centered button to navigate to the request details page
        $message .= '<div style="text-align: center; margin-top: 30px;">';
        $message .= '<a href="' . esc_url($request_details_url) . '" style="display: inline-block; padding: 12px 25px; font-size: 16px; color: #fff; background-color: #10014b; text-decoration: none; border-radius: 5px;">View Request Details</a>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Thank you for your request,<br>Bidfood</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Set email headers to handle HTML
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email to the supplier
        wp_mail($supplier_email, 'Supplier Request Cancelled - Request #' . $supplier_request_id, $message, $headers);
    }

    /*-------------------- Send OTP to Customer --------------- */

    public static function send_otp_email($customer_email, $otp)
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

        // Prepare the email content
        $subject = 'Your OTP Code';
        $message = '<html><body style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 20px;">';
        $message .= '<div style="max-width: 800px; margin: 0 auto; background-color: #ffffff; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">';

        // Include the logo if available
        if ($logo_url) {
            $message .= '<div style="text-align: center;">';
            $message .= '<img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">';
            $message .= '</div>';
        }

        // Email Header
        $message .= '<h2 style="text-align: center; color: #333;">Your One-Time Password (OTP)</h2>';

        // OTP Content
        $message .= '<p style="font-size: 16px; color: #333; text-align: center;">Use the code below to complete your verification process. This code is valid for 5 minutes.</p>';
        $message .= '<div style="text-align: center; margin-top: 20px; margin-bottom: 20px;">';
        $message .= '<span style="display: inline-block; font-size: 24px; font-weight: bold; color: #10014b; padding: 10px 20px; background-color: #f0f0f0; border-radius: 5px;">' . esc_html($otp) . '</span>';
        $message .= '</div>';

        // Closing Note
        $message .= '<p style="font-size: 16px; color: #333;">If you did not request this code, please ignore this email or contact support.</p>';
        $message .= '<p style="font-size: 16px; color: #333; margin-top: 20px;">Best regards,<br>Bidfood Team</p>';
        $message .= '</div>';
        $message .= '</body></html>';

        // Email headers
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send the email
        if (wp_mail($customer_email, $subject, $message, $headers)) {
            // Store the OTP in the session or database for verification later
            set_transient('otp_' . md5($customer_email), $otp, 300); // Store for 5 minutes
            return true;
        } else {
            return new \WP_Error('email_error', __('Failed to send OTP email.', 'bidfood'));
        }
    }



    /* ------------------- Helper Functions ------------------- */

    public static function get_admins_emails()
    {
        $admins_emails = array();
        $users = get_users(array('role' => 'administrator'));

        foreach ($users as $user) {
            $admins_emails[] = $user->user_email;
        }

        return $admins_emails;
    }

    private static function get_po_items($po_id)
    {
        return UserSupplierManager::get_supplier_po_items($po_id);
    }

    private static function get_supplier_po_user($po_id)
    {
        $supplier_po = UserSupplierManager::get_supplier_po($po_id);
        $users = UserSupplierManager::get_users_by_supplier($supplier_po['supplier_id']);

        $wp_user = !empty($users) ? get_user_by('id', $users[0]) : null;
        return $wp_user;
    }

    private static function get_supplier_request_user($supplier_request_id, $request_type = 'update')
    {
        if ($request_type == 'update') {
            $supplier_request = UserSupplierManager::get_supplier_request_details($supplier_request_id);
            if (is_wp_error($supplier_request)) {
                return $supplier_request;
            }

            $users = UserSupplierManager::get_users_by_supplier($supplier_request['supplier_id']);
            if (is_wp_error($users)) {
                return $users;
            }

            $wp_user = !empty($users) ? get_user_by('id', $users[0]) : null;
            return $wp_user ? $wp_user : new \WP_Error('no_user_found', __('No user found for this supplier request.', 'bidfood'));
        } else {
            $supplier_request = UserSupplierManager::get_supplier_add_item_request_details($supplier_request_id);
            if (is_wp_error($supplier_request)) {
                return $supplier_request;
            }

            $users = UserSupplierManager::get_users_by_supplier($supplier_request['supplier_id']);
            if (is_wp_error($users)) {
                return $users;
            }

            $wp_user = !empty($users) ? get_user_by('id', $users[0]) : null;
            return $wp_user ? $wp_user : new \WP_Error('no_user_found', __('No user found for this supplier request.', 'bidfood'));
        }
    }
}
