<?php
// Security check - don't load directly
defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html>

<head>
    <title>Order Confirmation #<?php echo esc_html($po['id']); ?></title>
    <style>
        html {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
            background-color: #f9f9f9;
        }

        .header {
            border-bottom: 4px solid #0073aa;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .supplier-info {
            text-align: center;
        }

        .details,
        .order-summary,
        .delivery-info {
            margin: 20px 0;
            padding: 15px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #fff;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        th {
            background-color: #0073aa;
            color: #fff;
        }

        .total {
            margin-top: 30px;
            font-size: 1.2em;
            padding: 15px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 0.9em;
            color: #666;
        }

        .clearfix {
            clear: both;
        }
    </style>
</head>

<body>

    <div class="header">
        <?php
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
        // Include the logo if available
        if ($logo_url) {
            echo '<div style="text-align: center;">
                <img src="' . esc_url($logo_url) . '" alt="Website Logo" style="max-width: 75px;">
                </div>';
        }
        ?>
        <div class="supplier-info">
            <h2><?php echo esc_html($supplier->display_name); ?></h2>
            <p>Email: <?php echo esc_html($supplier->user_email ?: 'Not Provided'); ?></p>
            <p>Phone: <?php echo esc_html(get_user_meta($supplier->ID, 'billing_phone', true) ?: 'Not Provided'); ?></p>
        </div>
        <h1>ORDER CONFIRMATION #<?php echo esc_html($po['id']); ?></h1>
    </div>

    <div class="details">
        <p><strong>Order ID:</strong> <?php echo esc_html($order_details->get_id()); ?></p>
        <p><strong>Order Date:</strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($po['created_at']))); ?></p>
        <p><strong>Expected Delivery Date:</strong> <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($items[0]['expected_delivery_date']))); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Supplier Delivery Date</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo esc_html($item['item_id']); ?></td>
                    <td><?php echo esc_html($item['product_name']); ?></td>
                    <td><?php echo esc_html($item['quantity']); ?></td>
                    <td><?php echo esc_html($item['supplier_delivery_date']); ?></td>
                    <td><?php echo esc_html($item['supplier_notes'] ?: 'None'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="delivery-info">
        <h3>Delivery Address</h3>
        <p><?php echo nl2br(esc_html($order_details->get_formatted_shipping_address() ?: 'Not Provided')); ?></p>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> <?php bloginfo('name'); ?>. All Rights Reserved.</p>
        <p>This is a system-generated order confirmation and does not require a signature.</p>
    </div>

</body>

</html>