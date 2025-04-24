<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\Core\WooCommerce\Product\ProductQueryManager;

class OrderToSupplierConverter {

    public function __construct() {
        // Hook to add meta box on the order edit page
        add_action('add_meta_boxes', [$this, 'add_supplier_meta_box']);
        // Hook to save the supplier assignment and statuses
        add_action('save_post_shop_order', [$this, 'save_supplier_order'], 10, 1);
        // Hook to save all items to suppliers
        add_action('wp_ajax_save_all_suppliers', [$this, 'save_all_suppliers']);
        // Hook to convert order to supplier order
        add_action('wp_ajax_convert_to_supplier_order', [$this, 'convert_to_supplier_order']);
    }

    // Add a meta box for assigning items to suppliers and setting item statuses
    public function add_supplier_meta_box() {
        add_meta_box(
            'order_to_supplier', 
            __('Convert to Supplier Order', 'bidfood'), 
            [$this, 'render_supplier_meta_box'], 
            'woocommerce_page_wc-orders',
            'normal', 
            'high'
        );
    }

    // Render the meta box to assign suppliers and change statuses for each item
    public function render_supplier_meta_box($post) {
        $order = $post instanceof \WC_Order ? $post : wc_get_order($post->ID);
        global $wpdb;

        if (!$order) {
            return;
        }
    
        $items = $order->get_items();
        if (empty($items)) {
            return;
        }
    
        $suppliers = UserSupplierManager::get_all_assigned_suppliers();
    
        // Start form output
        ?>
        <form method="post" action="">
            <h3><?php esc_html_e('Supplier Assignment', 'bidfood'); ?></h3>
            <p><?php esc_html_e('Assign suppliers and set statuses for the items below:', 'bidfood'); ?></p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Item ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Item Name', 'bidfood'); ?></th>
                        <th><?php esc_html_e('UOM', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Supplier', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Assigned', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $item_id => $item) :
                    $product = $item->get_product();
                    $product_sku = $product ? $product->get_sku() : 'NAN';
                    $assignment = UserSupplierManager::get_item_supplier_assignment($product_sku, $order->get_id());
                    $assigned_supplier = !is_wp_error($assignment) ? $assignment['supplier_id'] : '';
                    $supplier_status = !is_wp_error($assignment) ? $assignment['status'] : 'pending supplier';
                    $preferred_supplier = UserSupplierManager::get_preferred_supplier_by_item($product_sku);
                    $is_assigned = !empty($assigned_supplier) ? __('Yes', 'bidfood') : __('No', 'bidfood');
                    $uom = ProductQueryManager::get_product_uom($product->get_id());
                ?>
                    <tr>
                        <td><?php echo esc_html($product_sku); ?></td>
                        <td><?php echo esc_html($item->get_name()); ?></td>
                        <td><?php echo $uom ? esc_html($uom->uom_description) : ''; ?></td>
                        <td>
                            <select name="supplier_assignment[<?php echo esc_attr($product_sku); ?>]" id="supplier_assignment_<?php echo esc_attr($product_sku); ?>">
                                <option value=""><?php esc_html_e('Select Supplier', 'bidfood'); ?></option>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo esc_attr($supplier['supplier_id']); ?>" 
                                        <?php selected($assigned_supplier ?: $preferred_supplier, $supplier['supplier_id']); ?>>
                                        <?php echo esc_html($supplier['supplier_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select name="supplier_status[<?php echo esc_attr($product_sku); ?>]" id="supplier_status_<?php echo esc_attr($product_sku); ?>">
                                <option value="pending supplier" <?php selected($supplier_status, 'pending supplier'); ?>><?php esc_html_e('Pending Supplier', 'bidfood'); ?></option>
                                <option value="supplier approved" <?php selected($supplier_status, 'supplier approved'); ?>><?php esc_html_e('Supplier Approved', 'bidfood'); ?></option>
                                <option value="supplier canceled" <?php selected($supplier_status, 'supplier canceled'); ?>><?php esc_html_e('Supplier Canceled', 'bidfood'); ?></option>
                            </select>
                        </td>
                        <td>
                            <input type="text" value="<?php echo esc_attr($is_assigned); ?>" disabled>
                        </td>
                        <td>
                            <!-- <button type="button" class="button button-primary" onclick="assignSupplierToItem(<?php echo esc_attr($product_sku); ?>, <?php echo esc_attr($order->get_id()); ?>)">
                                <?php esc_html_e('Assign', 'bidfood'); ?>
                            </button> -->
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <input type="hidden" name="post_ID" value="<?php echo esc_attr($order->get_id()); ?>">
            <button type="submit" name="save_all_suppliers" class="button button-primary" onclick="saveAllSuppliers(<?php echo esc_attr($order->get_id()); ?>)">
                <?php esc_html_e('Save All Suppliers', 'bidfood'); ?>
            </button>
            <!-- <button type="submit" name="convert_to_supplier_order" class="button button-primary" onclick="convertToSupplierOrder(<?php echo esc_attr($order->get_id()); ?>)">
                <?php esc_html_e('Convert to Supplier Order', 'bidfood'); ?>
            </button> -->
        </form>
        <?php
    
        // Add the AJAX script for assigning individual items
        ?>
        <script type="text/javascript">
            function assignSupplierToItem(productSku, orderId) {
                var supplierId = document.getElementById('supplier_assignment_' + productSku).value;
                var status = document.getElementById('supplier_status_' + productSku).value;
                var data = {
                    action: 'assign_item_to_supplier',
                    productSku: productSku,
                    order_id: orderId,
                    supplier_id: supplierId,
                    status: status,
                    security: '<?php echo wp_create_nonce('assign_item_to_supplier_nonce'); ?>'
                };
                jQuery.post(ajaxurl, data, function(response) {
                    console.log(response);
                    if (response.success) {
                        showToast('Supplier assigned successfully', 'success', 5000);
                        location.reload(); // Reload page after successful assignment
                    } else {
                        showToast('Error assigning supplier', 'error', 5000);
                    }
                });
            }

            function saveAllSuppliers(orderId) {
                // data -> order id, products sku, suppliers ids, statuses
                const fields = document.querySelectorAll('select[name^="supplier_assignment"]');

                var data = {
                    action: 'save_all_suppliers',
                    order_id: orderId,
                    fields_data: {},
                    security: '<?php echo wp_create_nonce('save_all_suppliers_nonce'); ?>'
                };

                fields.forEach(field => {
                    const productSku = field.name.replace('supplier_assignment[', '').replace(']', '');
                    const supplierId = field.value;
                    const status = document.getElementById('supplier_status_' + productSku).value;

                    data.fields_data[productSku] = {
                        supplier_id: supplierId,
                        status: status
                    };
                });

                console.log(data);

                jQuery.post(ajaxurl, data, function(response) {
                    console.log(response);
                    if (response.success) {
                        showToast('Order saved successfully', 'success', 5000);
                    } else {
                        showToast('Error saving order', 'error', 5000);
                    }
                    // location.reload(); // Reload page after successful assignment
                });

                return false;
            }
        
            function convertToSupplierOrder(orderId) {
                var data = {
                    action: 'convert_to_supplier_order',
                    order_id: orderId,
                    security: '<?php echo wp_create_nonce('convert_to_supplier_order_nonce'); ?>'
                };
                jQuery.post(ajaxurl, data, function(response) {
                    console.log(response);
                    if (response.success) {
                        showToast('Order converted successfully', 'success', 5000);
                    } else {
                        showToast(response.data, 'error', 5000);
                    }
                    location.reload(); // Reload page after successful assignment
                });
            }
        </script>
        <?php
    }   

    // Function to get all assigned items of an order of a supplier
    public function get_supplier_assigned_items($order_id, $supplier_id) {
        $order = wc_get_order($order_id);
        $items = $order->get_items();
        $assigned_items = [];

        foreach ($items as $item_id => $item) {
            $product = $item->get_product();
            $product_sku = $product ? $product->get_sku() : '';
            $assignment = UserSupplierManager::get_item_supplier_assignment($product_sku, $order_id);

            if (!is_wp_error($assignment) && $assignment['supplier_id'] === $supplier_id) {
                $assigned_items[] = $item;
            }
        }

        return $assigned_items;
    }

    // Assign all items to suppliers via AJAX
    public function save_all_suppliers() {
        // Check if the nonce is valid
        check_ajax_referer('save_all_suppliers_nonce', 'security');
    
        // Retrieve order_id and fields_data from the AJAX request
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $fields_data = isset($_POST['fields_data']) ? $_POST['fields_data'] : [];
    
        // Verify that order_id and fields_data are valid
        if (!$order_id || empty($fields_data)) {
            wp_send_json_error(__('Invalid request data.', 'bidfood'));
        }

        $order = wc_get_order($order_id);
        $order_status = $order->get_status();

        // Order status has to be 'placed'
        if ($order_status !== 'placed') {
            wp_send_json_error(__('Order status has to be set to placed.', 'bidfood'));
        }
    
        // Assign the suppliers to the items
        foreach ($fields_data as $product_sku => $data) {
            $supplier_id = $data['supplier_id'];
            $status = $data['status'];
            $old_assignment = UserSupplierManager::get_item_supplier_assignment($product_sku, $order_id);

            // Remove the supplier assignment if the supplier_id is empty
            if (empty($supplier_id)) {
                $assignment_result = UserSupplierManager::remove_item_supplier_assignment($product_sku, $order_id);
                
                if (is_wp_error($assignment_result)) {
                    wp_send_json_error($assignment_result->get_error_message());
                }

                continue;
            }
            
            // check if the supplier is newly assigned
            elseif (is_wp_error($old_assignment) || (!is_wp_error($old_assignment) && $old_assignment['supplier_id'] !== $supplier_id)) {
                $customer_delivery_date = get_post_meta($order->get_id(), 'order_requested_delivery_date', true);
                $assignment_result = UserSupplierManager::assign_item_to_supplier($product_sku, $order_id, $supplier_id, $customer_delivery_date);
                if (is_wp_error($assignment_result)) {
                    wp_send_json_error($assignment_result->get_error_message());
                }
                
                // Update the item status
                $status_result = UserSupplierManager::update_item_status($product_sku, $order_id, $status);

                if (is_wp_error($status_result)) {
                    wp_send_json_error($status_result->get_error_message());
                }

                // if ($order_status === 'sent-to-suppliers') {
                //     $supplier_user = UserSupplierManager::get_users_by_supplier($supplier_id);
                //     $supplier_user_id = !is_wp_error($supplier_user) ? $supplier_user[0] : '';
                //     $supplier_email = $supplier_user_id ? get_userdata($supplier_user_id)->user_email : '';
                //     $supplier_order_items = $this->get_supplier_assigned_items($order_id, $supplier_id);
                //     $this->send_supplier_email($supplier_email, $order, $supplier_order_items);
                // }
            }
        }

        // Return success response
        wp_send_json_success(__('Suppliers assigned and statuses updated successfully.', 'bidfood'));
    }

    // convert order to supplier order via AJAX
    public function convert_to_supplier_order() {
        // Check if the nonce is valid
        check_ajax_referer('convert_to_supplier_order_nonce', 'security');
    
        // Retrieve order_id from the AJAX request
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    
        // Verify that order_id is valid
        if (!$order_id) {
            wp_send_json_error(__('Invalid request data.', 'bidfood'));
        }

        $order = wc_get_order($order_id);
        $order_status = $order->get_status();
    
        if ($order_status !== 'placed') {
            wp_send_json_error(__('Order status is not valid.', 'bidfood'));
        }

        // Send email to suppliers
        $order_suppliers = UserSupplierManager::get_order_suppliers($order_id);

        if (is_wp_error($order_suppliers)) {
            wp_send_json_error(__('Error fetching suppliers.', 'bidfood'));
        }

        // Remove duplicate suppliers using supplier_id
        foreach ($order_suppliers as $key => $supplier) {
            $supplier_ids[] = $supplier['supplier_id'];
        }

        $supplier_ids = array_unique($supplier_ids);

        // foreach ($supplier_ids as $supplier_id) {
        //     $supplier_user = UserSupplierManager::get_users_by_supplier($supplier_id);
        //     $supplier_user_id = !is_wp_error($supplier_user) ? $supplier_user[0] : '';
        //     $supplier_email = $supplier_user_id ? get_userdata($supplier_user_id)->user_email : '';
        //     $supplier_order_items = $this->get_supplier_assigned_items($order_id, $supplier_id);
        //     $this->send_supplier_email($supplier_email, $order, $supplier_order_items);
        // }

        // Update order status to wc-sent-to-suppliers
        $order->update_status('sent-to-suppliers');
    
        // Return success response
        wp_send_json_success(__('Order sent to suppliers successfully.', 'bidfood'));
    }

}