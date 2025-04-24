<?php

namespace Bidfood\Core\WooCommerce\Checkout;

class CheckoutVenue {
    public function __construct() {
        // Add the Venue field to checkout, order summary, and admin order page
        add_action('bidfood_custom_venue_field', [__CLASS__, 'add_venue_field_to_checkout']);
        add_action('woocommerce_checkout_process', [__CLASS__, 'validate_venue_field']);
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'save_venue_field']);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'display_venue_field_admin']);
    }

    public static function init() {
        return new self();
    }

    /* Venue Field in Checkout */
    public static function add_venue_field_to_checkout($checkout) {
        woocommerce_form_field('order_venue', [
            'type'        => 'text',
            'class'       => ['form-row-wide'],
            'label'       => __('Venue', 'woocommerce'),
            'placeholder' => __('Enter your venue', 'woocommerce'),
            'required'    => true,
            'custom_attributes' => [
                'style' => 'width: 12.8%;',
            ],
        ], $checkout->get_value('order_venue'));
    }

    public static function validate_venue_field() {
        if (empty($_POST['order_venue'])) {
            wc_add_notice(__('Please enter a venue.', 'woocommerce'), 'error');
        }
    }

    public static function save_venue_field($order_id) {
        if (!empty($_POST['order_venue'])) {
            update_post_meta($order_id, 'order_venue', sanitize_text_field($_POST['order_venue']));
        }
    }

    public static function display_venue_field_admin($order) {
        $venue = get_post_meta($order->get_id(), 'order_venue', true);
        if ($venue) {
            echo '<p><strong>' . __('Venue:', 'woocommerce') . '</strong> ' . esc_html($venue) . '</p>';
        }
    }
}
