<?php
// Security check - don't load directly
defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html>

<head>
    <title>Invoice #<?php echo esc_html($po['id']); ?></title>
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

        .company-info {
            text-align: center;
        }

        .invoice-info {
            margin-top: 20px;
        }

        .details,
        .billing-address,
        .shipping-address,
        .payment-method {
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

        .notes,
        .payment-instructions,
        .thank-you {
            margin-top: 30px;
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
        <div class="company-info">
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
            <h2><?php bloginfo('name'); ?></h2>
            <p><?php bloginfo('description'); ?></p>
        </div>
        <h1>INVOICE #<?php echo esc_html($po['id']); ?></h1>
    </div>

    <div class="invoice-info">
        <p><strong>Invoice Date:</strong> <?php echo esc_html(current_time('Y-m-d H:i:s')); ?></p>
        <p><strong>Due Date:</strong> <?php echo esc_html(date('Y-m-d H:i:s', strtotime('+7 days'))); ?></p>
    </div>
    <div class="clearfix"></div>

    <div class="details">
        <p><strong>Customer:</strong> <?php echo esc_html($customer->display_name); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($customer->user_email); ?></p>
        <p><strong>Order ID:</strong> <?php echo esc_html($order_details->get_id()); ?></p>
        <p><strong>Order Date:</strong> <?php echo esc_html($order_details->get_date_created()->date('Y-m-d H:i:s')); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Code</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_details->get_items() as $item): ?>
                <tr>
                    <td><?php echo esc_html($item->get_product_id()); ?></td>
                    <td><?php echo esc_html($item->get_name()); ?></td>
                    <td><?php echo esc_html($item->get_quantity()); ?></td>
                    <td><?php echo wc_price($item->get_total() / max(1, $item->get_quantity())); ?></td>
                    <td><?php echo wc_price($item->get_total()); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="total">
        <p><strong>Subtotal:</strong> <?php echo wc_price($order_details->get_subtotal()); ?></p>
        <p><strong>Total Due:</strong> <?php echo wc_price($order_details->get_total()); ?></p>
    </div>

    <div class="shipping-address">
        <h3>Shipping Address</h3>
        <p><?php echo nl2br(esc_html($order_details->get_formatted_shipping_address() ?: 'Not Provided')); ?></p>
    </div>

    <div class="payment-method">
        <h3>Payment Method</h3>
        <p><?php echo esc_html($order_details->get_payment_method_title() ?: 'Not Provided'); ?></p>
    </div>

    <div class="notes">
        <h3>Notes & Terms</h3>
        <p>Thank you for your business! Please make the payment within 7 days.</p>
    </div>

    <div class="payment-instructions">
        <h3>Payment Instructions</h3>
        <p>Please transfer the amount to:</p>
        <p><strong>Bank Name:</strong> Example Bank</p>
        <p><strong>Account Number:</strong> 123-456-789</p>
        <p><strong>IBAN:</strong> XX123456789</p>
        <p><strong>SWIFT Code:</strong> EXAMPLEX</p>
        <p>Or pay via PayPal: <a href="https://paypal.me/example" target="_blank">paypal.me/example</a></p>
    </div>

    <div class="thank-you">
        <h2>Thank You for Your Order!</h2>
        <p>We appreciate your business. If you have any questions, feel free to reach out to us.</p>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> <?php bloginfo('name'); ?>. All Rights Reserved.</p>
        <p>This is a system-generated invoice and does not require a signature.</p>
    </div>

</body>

</html>