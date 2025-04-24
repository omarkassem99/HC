<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\Core\WooCommerce\Product\ProductQueryManager;

class SupplierPoDetailsPage {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_supplier_po_details_menu']);
        add_action('wp_ajax_update_admin_item_status', [$this, 'ajax_update_admin_item_status']);
        add_action('wp_ajax_mark_supplier_po_sent', [$this, 'mark_supplier_po_as_sent']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_ajax_scripts']);
    }

    public static function init() {
        return new self();
    }

    public function add_supplier_po_details_menu() {
        add_menu_page(
            __('Supplier PO Details', 'bidfood'),
            '',  // Empty menu title to hide the page in the menu
            'manage_woocommerce',
            'supplier-po-details',
            [$this, 'render_supplier_po_details_page']
        );        
    }

    public function render_supplier_po_details_page() {
        // Get the supplier PO ID from the URL
        $po_id = isset($_GET['po_id']) ? intval($_GET['po_id']) : 0;
        if (!$po_id) {
            return;
        }

        global $wpdb;

        // Get the supplier PO details
        $supplier_po_items = UserSupplierManager::get_supplier_po_items($po_id);
        $supplier_po = UserSupplierManager::get_supplier_po($po_id);

        // Get the first user by supplier
        $users = UserSupplierManager::get_users_by_supplier($supplier_po['supplier_id']);
        $first_user = !empty($users) ? get_user_by('id', $users[0]) : null;

        // Render the details and the Mark as Sent button
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Supplier PO Details', 'bidfood'); ?></h1>

            <h2><?php esc_html_e('PO ID: ', 'bidfood'); echo esc_html($po_id); ?></h2>
            <h2><?php esc_html_e('Supplier ID: ', 'bidfood'); echo esc_html($supplier_po['supplier_id']); ?></h2>
            <h2 id="po-status"><?php esc_html_e('Status: ', 'bidfood'); echo esc_html($supplier_po['status']); ?></h2>
            <?php if ($first_user): ?>
                <h2><?php esc_html_e('Supplier User: ', 'bidfood'); echo esc_html($first_user->display_name); ?></h2>
            <?php endif; ?>

            <?php if ($supplier_po['status'] === 'draft'): ?>
                <button class="button button-primary mark-as-sent-button" data-po-id="<?php echo esc_attr($po_id); ?>">
                    <?php esc_html_e('Mark as Sent to Supplier', 'bidfood'); ?>
                </button>
            <?php endif; ?>

            <!-- Add scrollable container for the table -->
            <div class="table-responsive">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order ID', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Item ID', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Product Name', 'bidfood'); ?></th>
                            <th><?php esc_html_e('UOM', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Quantity', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Customer Notes', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Customer Delivery Date', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Supplier Delivery Date', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Supplier Notes', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Admin Notes', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Expected Delivery Date', 'bidfood'); ?></th>
                            <th><?php esc_html_e('Action', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supplier_po_items as $item) : 
                            // get product using item sku
                            $product_id = wc_get_product_id_by_sku($item['item_id']);
                            $uom = ProductQueryManager::get_product_uom($product_id);
                            ?>
                            <tr>
                                <td><?php echo esc_html($item['order_id']); ?></td>
                                <td><?php echo esc_html($item['item_id']); ?></td>
                                <td><?php echo esc_html($item['product_name']); ?></td>
                                <td><?php echo $uom ? esc_html($uom->uom_description) : ''; ?></td>
                                <td><?php echo esc_html($item['quantity']); ?></td>
                                <td>
                                    <select name="status_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>" id="status_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>">
                                        <option value="pending supplier" <?php selected($item['status'], 'pending supplier'); ?>>Pending Supplier</option>
                                        <option value="supplier confirmed" <?php selected($item['status'], 'supplier confirmed'); ?>>Supplier Confirmed</option>
                                        <option value="supplier rejected" <?php selected($item['status'], 'supplier rejected'); ?>>Supplier Rejected</option>                                
                                        <option value="bidfood approved" <?php selected($item['status'], 'bidfood approved'); ?>>Bidfood Approved</option>
                                        <option value="cancelled" <?php selected($item['status'], 'cancelled'); ?>>Cancelled</option>
                                    </select>
                                </td>
                                <td><?php echo esc_html($item['customer_item_note']) ? esc_html($item['customer_item_note']) : 'N/A'; ?></td>
                                <td><?php echo !empty($item['customer_delivery_date']) ? esc_html(date_i18n(get_option('date_format'), strtotime($item['customer_delivery_date']))) : esc_html__('N/A', 'woocommerce'); ?></td>
                                <td><?php echo !empty($item['supplier_delivery_date']) ? esc_html(date_i18n(get_option('date_format'), strtotime($item['supplier_delivery_date']))) : esc_html__('N/A', 'woocommerce'); ?></td>
                                <td><?php echo esc_html($item['supplier_notes']); ?></td>
                                <td>
                                    <textarea name="admin_notes_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>" 
                                            id="admin_notes_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>" 
                                            rows="3"><?php echo esc_html($item['admin_notes']); ?></textarea>
                                </td>
                                <td><?php echo !empty($item['expected_delivery_date']) ? esc_html(date_i18n(get_option('date_format'), strtotime($item['expected_delivery_date']))) : esc_html__('N/A', 'woocommerce'); ?></td>
                                <td>
                                    <button type="button" class="button button-primary" 
                                            onclick="updateAdminItemStatus('<?php echo esc_attr($item['item_id']); ?>', <?php echo esc_attr($item['order_id']); ?>)">
                                        <?php esc_html_e('Save', 'bidfood'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <style>
                .table-responsive {
                    overflow-x: auto; /* Enable horizontal scroll if table is too wide */
                    margin-bottom: 20px;
                }

                .wp-list-table {
                    min-width: 1200px; /* Set a minimum width for the table */
                }

                .wp-list-table th, .wp-list-table td {
                    padding: 10px;
                    text-align: left;
                    vertical-align: middle;
                    word-wrap: break-word;
                }

                /* Adjusted column widths for optimized layout */
                .wp-list-table th:nth-child(1), .wp-list-table td:nth-child(1) {
                    width: 80px; /* Order ID */
                }

                .wp-list-table th:nth-child(2), .wp-list-table td:nth-child(2) {
                    width: 80px; /* Item ID */
                }

                .wp-list-table th:nth-child(3), .wp-list-table td:nth-child(3) {
                    width: 180px; /* Product Name */
                }

                .wp-list-table th:nth-child(4), .wp-list-table td:nth-child(4) {
                    width: 70px; /* UOM */
                }

                .wp-list-table th:nth-child(5), .wp-list-table td:nth-child(5) {
                    width: 100px; /* Quantity */
                }

                .wp-list-table th:nth-child(6), .wp-list-table td:nth-child(6) {
                    width: 150px; /* Status */
                }

                .wp-list-table th:nth-child(7), .wp-list-table td:nth-child(7) {
                    width: 110px; /* Customer Notes */
                }

                .wp-list-table th:nth-child(8), .wp-list-table td:nth-child(8) {
                    width: 110px; /* Customer Delivery Date */
                }

                .wp-list-table th:nth-child(9), .wp-list-table td:nth-child(9) {
                    width: 110px; /* Supplier Delivery Date */
                }

                .wp-list-table th:nth-child(10), .wp-list-table td:nth-child(10) {
                    width: 150px; /* Supplier Notes */
                }

                .wp-list-table th:nth-child(11), .wp-list-table td:nth-child(11) {
                    width: 150px; /* Admin Notes */
                }

                .wp-list-table th:nth-child(12), .wp-list-table td:nth-child(12) {
                    width: 130px; /* Expected Delivery Date */
                }

                .wp-list-table th:nth-child(13), .wp-list-table td:nth-child(13) {
                    width: 80px; /* Action */
                }

            </style>
        </div>
        <?php
    }

    // Handle AJAX request to update the item status and admin notes
    public function ajax_update_admin_item_status() {
        check_ajax_referer('supplier_po_nonce', 'security');

        $item_id = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : '';
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $admin_notes = isset($_POST['admin_notes']) ? sanitize_textarea_field($_POST['admin_notes']) : '';

        if (empty($item_id) || empty($order_id) || empty($status)) {
            wp_send_json_error(['message' => __('Invalid data provided.', 'bidfood')]);
        }

        // Update the item status
        $status_result = UserSupplierManager::update_supplier_po_item_status($item_id, $order_id, $status);
        if (is_wp_error($status_result)) {
            wp_send_json_error(['message' => $status_result->get_error_message()]);
        }

        // Update the admin notes
        $notes_result = UserSupplierManager::add_admin_notes_to_po_item($item_id, $order_id, $admin_notes);
        if (is_wp_error($notes_result)) {
            wp_send_json_error(['message' => $notes_result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Item updated successfully!', 'bidfood')]);
    }
    public function mark_supplier_po_as_sent() {
        // Verify nonce for security
        check_ajax_referer('mark_supplier_po_sent_nonce', 'security');

        $po_id = intval($_POST['po_id'] ?? 0);

        if (!$po_id) {
            wp_send_json_error(['message' => __('Invalid PO ID.', 'bidfood')]);
        }

        // Assume $supplier_po is retrieved based on $po_id
        $supplier_po = UserSupplierManager::get_supplier_po($po_id); // Replace this with your actual method to fetch the PO.

        if ($supplier_po['status'] === 'draft') {
            $update_result = UserSupplierManager::update_supplier_po_status($po_id, 'sent to supplier');

            if (!is_wp_error($update_result)) {
                do_action('bidfood_po_initiated', $po_id);
                wp_send_json_success([
                    'message' => __('Supplier PO has been marked as sent and emails were sent successfully.', 'bidfood'),
                    'new_status' => 'sent to supplier',
                ]);
            } else {
                wp_send_json_error(['message' => $update_result->get_error_message()]);
            }
        } else {
            wp_send_json_error(['message' => __('PO status cannot be updated.', 'bidfood')]);
        }
    }

    public function enqueue_ajax_scripts() {
        wp_enqueue_script('admin-supplier-po-ajax', plugins_url('/assets/js/admin-supplier-po.js', dirname(__FILE__, 3)), ['jquery'], null, true);
    
        wp_localize_script('admin-supplier-po-ajax', 'supplierPoData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_po_nonce'),
            'mark_as_sent_nonce' => wp_create_nonce('mark_supplier_po_sent_nonce'),
        ]);
    }

}
