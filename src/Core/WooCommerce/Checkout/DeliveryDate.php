<?php

namespace Bidfood\Core\WooCommerce\Checkout;

use Bidfood\Core\UserManagement\UserSupplierManager;

class DeliveryDate
{
    private static $customer_lead_days;
    private static $customer_order_cutoff_hour;
    private static $supplier_lead_days;
    private static $supplier_cutoff_hour;
    private static $extra_days_for_delivery;

    public function __construct()
    {
        self::$customer_lead_days = get_option("bidfood_customer_lead_days", 7);
        self::$customer_order_cutoff_hour = get_option("bidfood_customer_order_cutoff_hour", 12);
        self::$supplier_lead_days = get_option("bidfood_supplier_lead_days", 2);
        self::$supplier_cutoff_hour = get_option("bidfood_supplier_cutoff_hour", 12);
        self::$extra_days_for_delivery = get_option("bidfood_extra_days_for_delivery", 5);

        // Add the custom field to checkout, order summary, and admin order page
        add_action('bidfood_custom_delivery_field', [__CLASS__, 'add_customer_requested_delivery_date_field']);
        add_action('woocommerce_checkout_process', [__CLASS__, 'validate_customer_requested_delivery_date']);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_customer_requested_delivery_date']);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'display_customer_requested_delivery_date_admin']);

        // Add custom action to set the expected delivery date to the PO items
        add_action('bidfood_po_items_set_expected_delivery_date', [__CLASS__, 'set_po_items_expected_delivery_date']);
    }

    public static function init()
    {
        return new self();
    }

    /* Customer requested delivery date */
    public static function add_customer_requested_delivery_date_field($checkout)
    {
        woocommerce_form_field('order_requested_delivery_date', [
            'type'        => 'date',
            'class'       => ['form-row-first'],
            'label'       => __('Requested Delivery Date', 'woocommerce'),
            'required'    => true,
            'custom_attributes' => [
                'min' => self::calculate_customer_earliest_delivery_date(), // Set minimum date based on lead days and cutoff time
                'style' => 'width: 27%; height: 40px;',
            ],
            'default'     => self::calculate_customer_earliest_delivery_date(), // Set default date to the earliest delivery date
        ], $checkout->get_value('order_requested_delivery_date'));
    }

    private static function calculate_customer_earliest_delivery_date()
    {
        // Set timezone to Riyadh
        $timezone = new \DateTimeZone('Asia/Riyadh');
        $today = new \DateTime('now', $timezone);
        $current_hour_riyadh = (int) $today->format('G');
        // Determine lead days based on the cutoff time
        $lead_days = self::$customer_lead_days + ($current_hour_riyadh >= self::$customer_order_cutoff_hour ? 1 : 0);

        // Calculate the earliest delivery date
        $today->modify("+$lead_days days");
        return $today->format('Y-m-d');
    }

    public static function validate_customer_requested_delivery_date()
    {
        $earliest_date = self::calculate_customer_earliest_delivery_date();
        $requested_date = $_POST['order_requested_delivery_date'] ?? null;

        if ($requested_date) {
            $request_date_obj = \DateTime::createFromFormat('Y-m-d', $requested_date);
            $earliest_date_obj = \DateTime::createFromFormat('Y-m-d', $earliest_date);

            if ($request_date_obj < $earliest_date_obj) {
                wc_add_notice(sprintf(__('The requested delivery date must be on or after %s.', 'woocommerce'), $earliest_date), 'error');
            }
        } else {
            wc_add_notice(__('Please select a requested delivery date.', 'woocommerce'), 'error');
        }
    }

    public static function save_customer_requested_delivery_date($order_id)
    {
        if (!empty($_POST['order_requested_delivery_date'])) {
            update_post_meta($order_id, 'order_requested_delivery_date', sanitize_text_field($_POST['order_requested_delivery_date']));
        }
    }

    public static function display_customer_requested_delivery_date_admin($order)
    {
        $requested_delivery_date = get_post_meta($order->get_id(), 'order_requested_delivery_date', true);
        if ($requested_delivery_date) {
            echo '<p><strong>' . __('Requested Delivery Date:', 'woocommerce') . '</strong> ' . esc_html(date_i18n(get_option('date_format'), strtotime($requested_delivery_date))) . '</p>';
        }
    }

    /* Supplier expected delivery date */
    public static function calculate_supplier_earliest_delivery_date()
    {
        $current_date = new \DateTime();
        $current_hour_Riyadh = (int) $current_date->format('G');

        // Determine lead days based on the cutoff time
        $lead_days = self::$supplier_lead_days + ($current_hour_Riyadh >= self::$supplier_cutoff_hour ? 1 : 0);

        // Calculate the earliest delivery date
        $current_date->modify("+$lead_days days");
        return $current_date->format('Y-m-d');
    }

    public static function validate_supplier_expected_delivery_date($expected_delivery_date)
    {
        $earliest_date = self::calculate_supplier_earliest_delivery_date();
        $expected_date_obj = \DateTime::createFromFormat('Y-m-d', $expected_delivery_date);
        $earliest_date_obj = \DateTime::createFromFormat('Y-m-d', $earliest_date);

        if ($expected_date_obj < $earliest_date_obj) {
            return new \WP_Error('invalid_date', sprintf(__('The expected delivery date must be on or after %s.', 'woocommerce'), $earliest_date));
        } else {
            return true;
        }
    }

    /* Extra days for delivery */
    public static function calculate_final_expected_delivery_date($expected_delivery_date)
    {
        $expected_date_obj = \DateTime::createFromFormat('Y-m-d', $expected_delivery_date);
        $expected_date_obj->modify("+" . self::$extra_days_for_delivery . " days");
        return $expected_date_obj->format('Y-m-d');
    }

    public static function set_po_items_expected_delivery_date($po_id)
    {
        $po_items = UserSupplierManager::get_supplier_po_items($po_id);

        foreach ($po_items as $po_item) {
            if ($po_item['status'] !== 'supplier confirmed') {
                continue;
            }

            $expected_delivery_date = self::calculate_final_expected_delivery_date($po_item['supplier_delivery_date']);
            UserSupplierManager::add_expected_delivery_date_to_po_item($po_item['item_id'], $po_item['order_id'], $expected_delivery_date);
        }
    }
}
