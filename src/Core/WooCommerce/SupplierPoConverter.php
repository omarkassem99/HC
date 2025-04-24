<?php


namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;

class SupplierPoConverter {

    public function __construct() {
        add_action('woocommerce_order_list_table_extra_tablenav', [self::class, 'add_convert_to_draft_suppliers_po_button'], 50);
        add_action('wp_ajax_convert_to_draft_suppliers_po', [self::class, 'convert_to_draft_suppliers_po_handler']);
    }

    public static function init() {
        return new self();
    }

    public static function add_convert_to_draft_suppliers_po_button() {
    
        echo '<button type="button" id="convert-draft-suppliers-po" class="button">Convert to draft suppliers PO</button>';
        
        // Add inline JavaScript for handling the button click
        ?>
        <script type="text/javascript">
            (function($){
                $(document).ready(function() {
                    // Ensure we attach the click event only once
                    $('#convert-draft-suppliers-po').off('click').on('click', function() {
                        // Get all selected checkboxes
                        var selectedCheckboxes = document.querySelectorAll('input[name="id[]"]:checked');
                        var selectedValues = [];
    
                        // Loop through each checkbox and get its value (order ID)
                        selectedCheckboxes.forEach(function(checkbox) {
                            selectedValues.push(checkbox.value);
                        });
    
                        // Send selected order IDs via AJAX
                        if (selectedValues.length > 0) {
                            $.ajax({
                                url: ajaxurl, // WordPress global variable for admin AJAX URL
                                type: 'POST',
                                data: {
                                    action: 'convert_to_draft_suppliers_po',
                                    order_ids: selectedValues
                                },
                                success: function(response) {
                                    showToast(response.data, 'success', 5000);
                                    // reload the page
                                    location.reload();
                                },
                                error: function(xhr, status, error) {
                                    showToast(xhr.responseText, 'error', 5000);
                                }
                            });
                        } else {
                            showToast('No orders selected', 'error', 5000);
                        }  
                    });
                });
            })(jQuery);
        </script>
        <?php
    }

    public static function convert_to_draft_suppliers_po_handler() {
        // Check if order IDs are sent via POST
        if ( isset($_POST['order_ids']) && is_array($_POST['order_ids']) ) {
            $order_ids = $_POST['order_ids'];
    
            $result = self::convert_po_to_supplier_order($order_ids);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            // Send a success response back to the client
            wp_send_json_success('Supplier POs created successfully');
        } else {
            // If no order IDs are received, send an error response
            wp_send_json_error('No selected orders');
        }
        
        wp_die();
    }

    public static function convert_po_to_supplier_order($orders_ids) {

        // Check if all orders statuses are 'placed'
        foreach ($orders_ids as $order_id) {
            $order = wc_get_order($order_id);
            $order_status = $order->get_status();

            if ($order_status !== 'placed') {
                return new \WP_Error('invalid_order_status', 'One or more orders have non-placed status');
            }

            // Check if all order items have suppliers
            $order_items = $order->get_items();
            $order_suppliers = UserSupplierManager::get_order_suppliers($order_id);

            // Compare the count of order items and order suppliers
            if (count($order_items) !== count($order_suppliers)) {
                return new \WP_Error('missing_suppliers', 'Order #' . $order_id . ' has missing suppliers');
            }

            // Check if orders does not exist in an existing supplier PO
            $result = UserSupplierManager::get_all_supplier_pos_by_order_id($order_id);
            if (count($result) > 0) {
                return new \WP_Error('existing_po', 'Order #' . $order_id . ' already exists in a supplier PO');
            }
        }

        $supplier_pos = [];
        foreach ($orders_ids as $order_id) {
            $order_suppliers = UserSupplierManager::get_order_suppliers($order_id);

            foreach ($order_suppliers as $order_supplier) {
                $supplier_id = $order_supplier['supplier_id'];
                $item_id = $order_supplier['item_id'];

                if (!isset($supplier_pos[$supplier_id])) {
                    $supplier_pos[$supplier_id] = [];
                }

                if (!isset($supplier_pos[$supplier_id][$order_id])) {
                    $supplier_pos[$supplier_id][$order_id] = [];
                }

                $supplier_pos[$supplier_id][$order_id][] = $item_id;
            }
        }


        // create_supplier_po takes supplier_id and orders_ids
        $errors = [];
        foreach ($supplier_pos as $supplier_id => $orders) {
            $result = UserSupplierManager::create_supplier_po($supplier_id, array_keys($orders));

            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
            }
        }

        if (!empty($errors)) {
            return new \WP_Error('po_error', implode(', ', $errors));
        }

        return true;
    }
}