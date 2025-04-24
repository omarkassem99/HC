<?php

namespace Bidfood\Core\WooCommerce\WhOrders;

use Bidfood\Core\OrderManagement\WhOrderManager;
use Bidfood\Core\WooCommerce\Product\ProductQueryManager;
use Bidfood\Core\UserManagement\UserSupplierManager;
class WhOrderConverter
{
    public function __construct()
    {
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // Register AJAX actions
        add_action('wp_ajax_convert_to_draft_wh_order', [$this, 'convert_to_draft_wh_order_handler']);

        // Add "Convert to Draft" button to the WooCommerce order list table
        add_action('woocommerce_order_list_table_extra_tablenav', [self::class, 'add_convert_to_draft_wh_order_button'], 50);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets()
    {
        // Enqueue styles
        wp_enqueue_style(
            'admin-wh-convert-orders-css',
            plugins_url('/assets/css/Orders/wh-convert-orders.css', dirname(__FILE__, 4))
        );

        // Enqueue JavaScript for converting orders to draft WH orders
        wp_enqueue_script(
            'admin-wh-convert-orders-js',
            plugins_url('/assets/js/Orders/wh-convert-orders.js', dirname(__FILE__, 4)),
            ['jquery'],
            null,
            true
        );

        // Pass AJAX data for converting orders
        wp_localize_script('admin-wh-convert-orders-js', 'bidfoodWhOrdersData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('convert_to_wh_order_nonce'),
        ]);
    }

    public static function init()
    {
        
        return new self();
        
    }
    /**
     * Add a button to the WooCommerce orders list table for converting orders to draft WH orders
     */
    public static function add_convert_to_draft_wh_order_button()
    {
        echo '<button type="button" id="convert-draft-wh-order" class="button">Convert to Draft WH Orders</button>';
        // var_dump( wc_get_order(the_order: 2641)->get_items());
    }


    /**
     * Handle AJAX request to convert orders to warehouse draft orders
     */
    public static function convert_to_draft_wh_order_handler()
    {
        
        if (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
            $order_ids = array_map('intval', $_POST['order_ids']); // Ensure IDs are integers

            $result = self::convert_orders_to_wh_orders($order_ids);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            wp_send_json_success(__('WH Orders created successfully.', 'bidfood'));
        } else {
            wp_send_json_error(__('No orders selected.', 'bidfood'));
        }

        wp_die();
    }

    /**
     * Converts orders to warehouse orders
     *
     * @param array $order_ids Array of WooCommerce order IDs
     * @return true|\WP_Error
     */
    public static function convert_orders_to_wh_orders($order_ids)
    {
        $errors = [];

        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);

            // Check if order exists and has the correct status
            if (!$order || $order->get_status() !== 'received-at-bf-wh') {
                $errors[] = __("Order #{$order_id} has invalid status to convert into a WH Order.", 'bidfood');
                continue;
            }

            // Check if the order is already stored in the warehouse table
            if (WhOrderManager::wh_order_exists($order_id)) {
                $errors[] = __("Order #{$order_id} is already converted to a WH Order.", 'bidfood');
                continue;
            }

            // Prepare data for the warehouse order
            $wh_order_data = [
                'order_id' => $order_id,
                'user_id' => $order->get_user_id(),
                'wh_order_status' => 'Draft',
                'wh_order_note' => '',
            ];

            $po_details = UserSupplierManager::get_po_details_for_customer_order($order_id);

            // Create the warehouse order
            $wh_order_id = WhOrderManager::create_wh_order($wh_order_data);
            if (is_wp_error($wh_order_id)) {
                $errors[] = __("Failed to create WH Order for Order #{$order_id}: " . $wh_order_id->get_error_message(), 'bidfood');
                continue;
            }

            // Prepare and store items
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);

                // Skip if product is invalid
                if (!$product) {
                    $errors[] = __("Product not found for Item ID: {$product_id} in Order #{$order_id}.", 'bidfood');
                    continue;
                }

                $product_sku = $product->get_sku();

                // Match po item with order item
                $po_item_data = array_filter($po_details, function ($po_item) use ($product_sku) {
                    return $po_item['item_id'] == $product_sku;
                });

                $po_item_data = reset($po_item_data);

                if ($po_item_data) {
                    $supplier_id = $po_item_data['supplier_id'] ? $po_item_data['supplier_id'] : null;
                    $po_id = $po_item_data['po_id'] ? $po_item_data['po_id'] : null;
                    $customer_delivery_date = $po_item_data['customer_delivery_date'] ? $po_item_data['customer_delivery_date'] : null;
                    $supplier_delivery_date = $po_item_data['supplier_delivery_date'] ? $po_item_data['supplier_delivery_date'] : null;
                    $expected_delivery_date = $po_item_data['expected_delivery_date'] ? $po_item_data['expected_delivery_date'] : null;
                } else {
                    $supplier_id = null;
                    $po_id = null;
                    $customer_delivery_date = null;
                    $supplier_delivery_date = null;
                    $expected_delivery_date = null;
                }

                // Retrieve metadata and other properties
                $uom = ProductQueryManager::get_product_uom($product_id);
                $uom_id = $uom ? $uom->uom_id : null;
                $customer_requested_amount = $item->get_quantity();

                $customer_notes = $item->get_meta('item_note', true) ?: '';
                $wh_manager_note = null;

                // Add item to warehouse order
                $item_data = [
                    'wh_order_id' => $wh_order_id,
                    'item_id' => $product_id,
                    'po_id' => $po_id,
                    'supplier_id' => $supplier_id,
                    'uom_id' => $uom_id,
                    'customer_requested_amount' => $customer_requested_amount,
                    'customer_delivery_date' => $customer_delivery_date,
                    'supplier_delivery_date' => $supplier_delivery_date,
                    'expected_delivery_date' => $expected_delivery_date,
                    'wh_confirmed_amount' => 0,
                    'customer_notes' => $customer_notes,
                    'wh_manager_note' => $wh_manager_note,
                ];

                $result = WhOrderManager::add_items_to_wh_order($item_data);

                if (is_wp_error($result)) {
                    $errors[] = __("Failed to add items to WH Order for Order #{$order_id}: " . $result->get_error_message(), 'bidfood');
                }
            }
        }


        if (!empty($errors)) {
            return new \WP_Error('conversion_error', implode('<br>', $errors));
        }

        return true;
    }
}
