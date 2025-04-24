<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;

class OrderManager {

    public function __construct() {
        // Add action to add the expected delivery date to the order items
        add_action("bidfood_po_submitted_add_order_expected_delivery_date", [$this, 'add_expected_delivery_date_to_order_items']);

        // Hook to display the expected delivery date in the admin order panel
        add_action('woocommerce_admin_order_item_headers', [$this, 'add_expected_delivery_date_column_header']);
        add_action('woocommerce_admin_order_item_values', [$this, 'add_expected_delivery_date_column_value'], 10, 3);

        add_action('woocommerce_admin_order_item_headers', [$this, 'add_order_item_supplier_status_header']);
        add_action('woocommerce_admin_order_item_values', [$this, 'add_supplier_status_column_value'], 10, 3);
        
        add_filter('woocommerce_order_item_display_meta_key', [$this, 'hide_order_item_meta'], 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this,'hide_order_item_meta_data'], 10, 3);

        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_cart_item_notes_to_order'], 10, 4);
        add_action('woocommerce_order_item_meta_end', [$this, 'display_item_notes_in_admin'], 10, 3);

        // Add action to display the item note in the admin order panel
        add_action('woocommerce_admin_order_item_headers', [$this, 'add_order_item_note_header']);
        add_action('woocommerce_admin_order_item_values', [$this, 'add_item_note_column_value'], 10, 3);
    }
    
    public static function init() {
        return new self();
    }

    /* Function to add the expected delivery date to the order items */
    public function add_expected_delivery_date_to_order_items($po_id) {
        // Get all the orders related to the PO
        $orders_ids = UserSupplierManager::get_supplier_po_orders($po_id);

        // an object with keys (order_id, item_id(sku), expected_delivery_date)
        $po_items = UserSupplierManager::get_supplier_po_items($po_id);

        // Loop through each order and add the metadata to each item if it exists in po_items
        foreach ($orders_ids as $order_id) {
            $wc_order = wc_get_order($order_id);
            $order_items = $wc_order->get_items();

            foreach ($order_items as $index => $item) {
                $sku = $item->get_product()->get_sku();
                
                // check if sku equals to item_id and order_id equal to order_id
                foreach ($po_items as $po_item) {
                    if ($po_item['item_id'] === $sku && $po_item['order_id'] === $order_id) {
                        if ($po_item['status'] === 'supplier confirmed') {
                            $item->update_meta_data('expected_delivery_date', $po_item['expected_delivery_date']);
                        }

                        $item->update_meta_data('supplier_status', $po_item['status']);
                        $item->save();
                    }

                    // Remove the item from the po_items array to reduce the number of iterations
                    unset($po_items[$index]);
                }
            }
        }
    }

    /* Add a new column header for Expected Delivery Date in the admin order items table */
    public function add_expected_delivery_date_column_header() {
        echo '<th class="expected-delivery-date">' . esc_html__('Expected Delivery Date', 'woocommerce') . '</th>';
    }

    /* Add a new column header for Supplier Status in the admin order items table */
    public function add_order_item_supplier_status_header() {
        echo '<th class="supplier_status">' . esc_html__('Supplier Status', 'woocommerce') . '</th>';
    }

    /* Add a new column header for Item Note in the admin order items table */
    public function add_order_item_note_header() {
        echo '<th class="item_note">' . esc_html__('Item Note', 'woocommerce') . '</th>';
    }

    /* Display the expected delivery date for each order item in the admin order items table */
    public function add_expected_delivery_date_column_value($product, $item, $item_id) {
        $expected_delivery_date = $item->get_meta('expected_delivery_date');
    
        echo '<td class="expected-delivery-date">';
        if ($expected_delivery_date) {
            echo esc_html(date_i18n(get_option('date_format'), strtotime($expected_delivery_date)));
        } else {
            echo esc_html__('N/A', 'woocommerce');
        }
        echo '</td>';
    }

    /* Display the supplier status of the order item */
    public function add_supplier_status_column_value($product, $item, $item_id) {
        $supplier_status = $item->get_meta('supplier_status');
    
        echo '<td class="supplier_status">';
        if ($supplier_status) {
            echo esc_html($supplier_status);
        } else {
            echo esc_html__('N/A', 'woocommerce');
        }
        echo '</td>';
    }

    /* Display the item note of the order item */
    public function add_item_note_column_value($product, $item, $item_id) {
        $item_note = $item->get_meta('item_note');
    
        echo '<td class="item_note">';
        if ($item_note) {
            echo esc_html($item_note);
        } else {
            echo esc_html__('N/A', 'woocommerce');
        }
        echo '</td>';
    }

    public function hide_order_item_meta($display_key, $meta, $item) {
        if ($meta->key === 'expected_delivery_date'
        || $meta->key === 'supplier_status'
        || $meta->key === 'item_note'
        ) {
            return null; // Hide it
        }
        return $display_key;
    }

    public function hide_order_item_meta_data($formatted_meta, $item) {
        foreach ($formatted_meta as $key => $meta) {
            if ($meta->key === 'expected_delivery_date' 
            || $meta->key === 'supplier_status'
            || $meta->key === 'item_note'
            ) {
                unset($formatted_meta[$key]);
            }
        }
        return $formatted_meta;
    }

    function save_cart_item_notes_to_order( $item, $cart_item_key, $values, $order ) {
        if ( isset( $_POST['cart_item_notes'][ $cart_item_key ] ) ) {
            $item->add_meta_data( 'item_note', sanitize_text_field( $_POST['cart_item_notes'][ $cart_item_key ] ) );
        }
    }

    function display_item_notes_in_admin( $item_id, $item, $order ) {
        if ( $note = $item->get_meta( 'item_note' ) ) {
            echo '<p><strong>' . esc_html__( 'Item Note:', 'woocommerce' ) . '</strong> ' . esc_html( $note ) . '</p>';
        }
    }
}
