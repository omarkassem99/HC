<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\Invoice\InvoiceGenerator;
use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\Core\WooCommerce\Product\ProductQueryManager;
use Bidfood\Core\WooCommerce\Checkout\DeliveryDate;
class SupplierUserPoPage {

    public function __construct() {
        // Hook to add the menu item to "My Account"
        add_filter('woocommerce_account_menu_items', [$this, 'add_supplier_pos_menu_item']);
        // Hook to add endpoint to handle supplier POs
        add_action('init', [$this, 'add_supplier_pos_endpoint']);
        // Hook to handle endpoint content
        add_action('woocommerce_account_supplier-pos_endpoint', [$this, 'supplier_pos_page_content']);
        // Handle the sub-page for PO details
        add_action('woocommerce_account_supplier-po-details_endpoint', [$this, 'supplier_po_details_page_content']);
        // Enqueue scripts for AJAX calls
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ajax_scripts']);
        // AJAX actions for saving item notes and status
        add_action('wp_ajax_update_po_item_status', [$this, 'ajax_update_po_item_status']);
        add_action('wp_ajax_submit_supplier_po', [$this, 'ajax_submit_supplier_po']);
        add_action('wp_ajax_fetch_updated_po_items', [$this, 'ajax_fetch_updated_po_items']);
        add_action('wp_ajax_fetch_supplier_pos', [$this, 'ajax_fetch_supplier_pos']);
        // Add action to handle invoice download
        add_action('template_redirect', [$this, 'handle_invoice_download']);
    }

    public static function init() {
        return new self();
    }

    // Add "Supplier POs" menu item to "My Account"
    public function add_supplier_pos_menu_item($items) {
        // Check if the current user is a supplier
        if (UserSupplierManager::is_user_supplier(get_current_user_id())) {
            // Create a new array to hold the reordered menu items
            $new_items = [];

            foreach ($items as $key => $label) {
                // Add the dashboard item to the new menu
                $new_items[$key] = $label;

                // Add the supplier-pos item after the dashboard
                if ($key === 'orders') {
                    $new_items['supplier-pos'] = __('Supplier POs', 'bidfood');
                }
            }

            return $new_items;
        }

        return $items; // Return the original items for non-supplier users
    }

    // Register new endpoint for supplier POs
    public function add_supplier_pos_endpoint() {
        add_rewrite_endpoint('supplier-pos', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('supplier-po-details', EP_ROOT | EP_PAGES);
    }

    // Enqueue AJAX script
    public function enqueue_ajax_scripts() {
        wp_enqueue_script('supplier-po-ajax', plugins_url('/assets/js/supplier-po.js', dirname(__FILE__, 3)), ['jquery'], null, true);

        wp_localize_script('supplier-po-ajax', 'supplierPoData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_po_nonce'),
        ]);
    }

    // Supplier POs page content
    public function supplier_pos_page_content() {
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            $this->render_no_pos_message();
            return;
        }

        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $per_page = 5;

        $pos_data = UserSupplierManager::get_supplier_pos_by_supplier($supplier_id, true, $page, $per_page);
        if (empty($pos_data['results'])) {
            $this->render_no_pos_message();
            return;
        }

        ?>
        <h3><?php esc_html_e('Assigned Purchase Orders', 'bidfood'); ?></h3>
        <?php

        $this->render_pos_table($pos_data['results'], $page, $pos_data['total_pages']);

        // Pagination
        $current_page = $page;
        $total_pages = $pos_data['total_pages'];
        
        ?>
        <div class="user-po-pagination-container">
            <?php 
            $visible_pages = 5; // Limit the number of visible pages
            $start_page = max(1, $current_page - floor($visible_pages / 2));
            $end_page = min($total_pages, $start_page + $visible_pages - 1);
            $start_page = max(1, $end_page - $visible_pages + 1);

            // Previous arrow
            if ($current_page > 1): ?>
                <a href="#" class="arrow" data-page="<?php echo $current_page - 1; ?>">&laquo;</a>
            <?php else: ?>
                <span class="arrow disabled">&laquo;</span>
            <?php endif;

            // Page numbers
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="#" class="page-number <?php echo ($i === $current_page) ? 'current' : ''; ?>" data-page="<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor;

            // Next arrow
            if ($current_page < $total_pages): ?>
                <a href="#" class="arrow" data-page="<?php echo $current_page + 1; ?>">&raquo;</a>
            <?php else: ?>
                <span class="arrow disabled">&raquo;</span>
            <?php endif; ?>
        </div>
        <?php
    }


    // Supplier PO details page content
    public function supplier_po_details_page_content() {
        $po_id = get_query_var('supplier-po-details');
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());

        $po = UserSupplierManager::get_supplier_po($po_id, true);
        if (!$po || $po['supplier_id'] !== $supplier_id || $po['status'] === 'draft') {
            $this->render_po_not_found_message();
            return;
        }

        $items = UserSupplierManager::get_supplier_po_items($po_id);
        if (empty($items)) {
            $this->render_no_items_message();
            return;
        }

        $this->render_po_details($po, $items);
    }

    // Render POs table
    private function render_pos_table($pos, $current_page, $total_pages) {
        ?>
        <table class="shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th><?php esc_html_e('PO ID', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Date', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pos as $po): ?>
                    <tr>
                        <td><?php echo esc_html($po['id']); ?></td>
                        <td><?php echo esc_html($po['created_at']); ?></td>
                        <td><?php echo esc_html($po['status']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(wc_get_endpoint_url('supplier-po-details', $po['id'], wc_get_page_permalink('myaccount'))); ?>" class="button">
                                <?php esc_html_e('View', 'bidfood'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function ajax_fetch_supplier_pos() {
        check_ajax_referer('supplier_po_nonce', 'security');
    
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }
    
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 5;
    
        $pos_data = UserSupplierManager::get_supplier_pos_by_supplier($supplier_id, true, $page, $per_page);
        if (empty($pos_data['results'])) {
            wp_send_json_error(['message' => __('No purchase orders found.', 'bidfood')]);
        }
    
        ob_start();
        $this->render_pos_table($pos_data['results'], $page, $pos_data['total_pages']);
        $table_html = ob_get_clean();
    
        ob_start();
        $this->render_pagination($page, $pos_data['total_pages']);
        $pagination_html = ob_get_clean();
    
        wp_send_json_success([
            'html' => $table_html,
            'pagination' => $pagination_html,
        ]);
    }
    
    private function render_pagination($current_page, $total_pages) {
        ?>
        <div class="user-po-pagination-container">
            <?php 
            $visible_pages = 5; // Number of visible pages at a time
            $start_page = max(1, $current_page - floor($visible_pages / 2));
            $end_page = min($total_pages, $start_page + $visible_pages - 1);
    
            // Adjust start and end pages if at the boundaries
            if ($end_page - $start_page + 1 < $visible_pages) {
                $start_page = max(1, $end_page - $visible_pages + 1);
            }
    
            // Previous arrow
            if ($current_page > 1): ?>
                <a href="#" class="arrow" data-page="<?php echo $current_page - 1; ?>">&laquo;</a>
            <?php else: ?>
                <span class="arrow disabled">&laquo;</span>
            <?php endif;
    
            // Page numbers
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="#" class="page-number <?php echo ($i === $current_page) ? 'current' : ''; ?>" data-page="<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor;
    
            // Next arrow
            if ($current_page < $total_pages): ?>
                <a href="#" class="arrow" data-page="<?php echo $current_page + 1; ?>">&raquo;</a>
            <?php else: ?>
                <span class="arrow disabled">&raquo;</span>
            <?php endif; ?>
        </div>
        <?php
    }
    
    
    
    // Render PO details and items with editable notes and status
    private function render_po_details($po, $items) {
        // Check if all items are already confirmed on page load
        $all_items_confirmed = UserSupplierManager::are_all_po_items_of_status($po['id'], ['supplier confirmed', 'supplier rejected']);
        $is_po_submitted=UserSupplierManager::is_po_submitted($po['id']);
       
        // Add download links
        if ($is_po_submitted) {
            echo '<div class="invoice-downloads">';
            echo '<h4>' . __('Download Invoices', 'bidfood') . '</h4>';

            // Supplier invoice link
            if (UserSupplierManager::verify_invoice_access(get_current_user_id(), $po['id'], 'supplier')) {
                echo '<p><a href="' . esc_url(wp_nonce_url(
                    add_query_arg([
                        'bidfood_invoice' => 1,
                        'type' => 'supplier',
                        'po_id' => $po['id']
                    ]), 
                    'download_invoice'
                )) . '" target="_blank">' . __('Download Supplier Copy', 'bidfood') . '</a></p>';
            }
            
            echo '</div>';
        }
        ?>
        <h3><?php esc_html_e('PO Details for PO #', 'bidfood'); ?><?php echo esc_html($po['id']); ?></h3>
        <form id="po-item-form" data-po-id="<?php echo esc_attr($po['id']); ?>">
            <div style="overflow-x: auto;">
            <table id="po-item-table" class="shop_table shop_table_responsive my_account_orders">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Order ID', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Item ID', 'bidfood'); ?></th>
                        <th style="width: 175px;"><?php esc_html_e('Product Name', 'bidfood'); ?></th>
                        <th><?php esc_html_e('UOM', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Quantity', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Customer Notes', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Notes', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Expected Delivery Date', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Action', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $this->render_po_item_rows($items, $po); ?>
                </tbody>
            </table>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 20px; margin-top: 20px;">
                <input type="date" id="global-date-picker" 
                    min="<?php echo esc_attr(DeliveryDate::calculate_supplier_earliest_delivery_date()); ?>"
                    value="<?php echo esc_attr(DeliveryDate::calculate_supplier_earliest_delivery_date()); ?>"
                    style="display: <?php echo (!$all_items_confirmed && $po['status'] == 'sent to supplier') ? 'block' : 'none'; ?>">
                <button type="button" class="button button-primary" id="apply-global-date-btn" onclick="applyGlobalDateToItems()"
                    style="display: <?php echo (!$all_items_confirmed && $po['status'] == 'sent to supplier') ? 'block' : 'none'; ?>">
                    <?php esc_html_e('Set all dates', 'bidfood'); ?>
                </button>
            </div>
    
            <!-- New buttons for Confirm All and Reject All -->
            <div class="action-buttons" style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="button button-primary" id="confirm-all-btn" 
                        style="display: <?php echo (!$all_items_confirmed && $po['status'] == 'sent to supplier') ? 'block' : 'none'; ?>" 
                        onclick="updateAllItemsStatus('<?php echo esc_js($po['id']); ?>', 'confirm')">
                    <?php esc_html_e('Confirm All', 'bidfood'); ?>
                </button>
                
                <button type="button" class="button button-secondary" id="reject-all-btn" 
                        style="background-color: red; color: white; display: <?php echo (!$all_items_confirmed && $po['status'] == 'sent to supplier') ? 'block' : 'none'; ?>" 
                        onclick="updateAllItemsStatus('<?php echo esc_js($po['id']); ?>', 'reject')">
                    <?php esc_html_e('Reject All', 'bidfood'); ?>
                </button>
            </div>

            <button type="button" class="button button-primary" id="submit-po-btn" style="display: <?php echo ($all_items_confirmed && $po['status'] == 'sent to supplier') ? 'block' : 'none'; ?>">
                <?php esc_html_e('Submit PO', 'bidfood'); ?>
            </button>
        </form>
        <?php
    }    

    // Render PO item rows (this will be reused in AJAX and initial rendering)
    private function render_po_item_rows($items, $po) {
        foreach ($items as $item): 
            // Get product using item SKU and retrieve UOM
            $product_id = wc_get_product_id_by_sku($item['item_id']);
            $uom = ProductQueryManager::get_product_uom($product_id);
    
            // Get the earliest delivery date for supplier
            $earliest_delivery_date = DeliveryDate::calculate_supplier_earliest_delivery_date();
            $supplier_delivery_date = $item['supplier_delivery_date'] ?? ''; // Fetch from item if available
            $is_editable = ($item['status'] === 'pending supplier'); // Check if item is editable
    
            ?>
            <tr>
                <td><?php echo esc_html($item['order_id']); ?></td>
                <td><?php echo esc_html($item['item_id']); ?></td>
                <td style="width: 175px;"><?php echo esc_html($item['product_name']); ?></td>
                <td><?php echo $uom ? esc_html($uom->uom_description) : ''; ?></td>
                <td><?php echo esc_html($item['quantity']); ?></td>
                <td><?php echo esc_html($item['customer_item_note']); ?></td>
                <td style="width: 250px;"> <!-- Increased width for Notes column -->
                <textarea name="supplier_notes_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>"
                    id="supplier_notes_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>"
                    rows="3" style="width: 150px; height: 100px;" <?php echo (!$is_editable) ? 'readonly' : ''; ?>><?php echo esc_html($item['supplier_notes']); ?></textarea>
            </td>
                <td>
                    <!-- Conditionally display date or date picker -->
                    <?php if ($is_editable): ?>
                        <input type="date" name="supplier_delivery_date_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>" 
                               id="supplier_delivery_date_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>"
                               min="<?php echo esc_attr($earliest_delivery_date); ?>" 
                               value="<?php echo esc_attr($earliest_delivery_date); ?>">
                    <?php else: ?>
                        <?php echo !empty($supplier_delivery_date) ? esc_html(date_i18n(get_option('date_format'), strtotime($supplier_delivery_date))) : esc_html__('N/A', 'woocommerce'); ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_editable): ?>
                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="button button-primary" 
                                    onclick="updateSingleItemStatus('<?php echo esc_attr($item['item_id']); ?>', <?php echo esc_attr($item['order_id']); ?>, <?php echo esc_attr($po['id']); ?>, 'confirm')">
                                <?php esc_html_e('Confirm', 'bidfood'); ?>
                            </button>
                            <button type="button" class="button button-secondary" style="background-color: red; color: white;"
                                    onclick="updateSingleItemStatus('<?php echo esc_attr($item['item_id']); ?>', <?php echo esc_attr($item['order_id']); ?>, <?php echo esc_attr($po['id']); ?>, 'reject')">
                                <?php esc_html_e('Reject', 'bidfood'); ?>
                            </button>
                        </div>
                    <?php else: ?>
                        <p><?php esc_html_e('Status:', 'bidfood'); ?> <?php echo esc_html($item['status']); ?></p>
                    <?php endif; ?>
                    
                    <!-- Hidden input for item status -->
                    <input type="hidden" name="item_status_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>"
                        id="item_status_<?php echo esc_attr($item['order_id']); ?>_<?php echo esc_attr($item['item_id']); ?>"
                        value="<?php echo esc_attr($item['status']); ?>">
                </td>
            </tr>
        <?php endforeach;
    }
    
    
    // Handle AJAX request to fetch updated PO items
    public function ajax_fetch_updated_po_items() {
        check_ajax_referer('supplier_po_nonce', 'security');

        $po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
        if (empty($po_id)) {
            wp_send_json_error(['message' => __('Invalid PO.', 'bidfood')]);
        }

        // Fetch updated items for the PO
        $items = UserSupplierManager::get_supplier_po_items($po_id);
        if (empty($items)) {
            wp_send_json_error(['message' => __('No items found for this PO.', 'bidfood')]);
        }

        // Generate the updated HTML for the items table
        ob_start();
        $this->render_po_item_rows($items, ['id' => $po_id]);
        $html = ob_get_clean();

        // Check if all items are confirmed
        $all_items_confirmed = UserSupplierManager::are_all_po_items_of_status($po_id, ['supplier confirmed', 'supplier rejected']);

        wp_send_json_success([
            'html' => $html,
            'all_items_confirmed' => $all_items_confirmed
        ]);
    }


    // Handle AJAX request to update the item status
    public function ajax_update_po_item_status() {
        check_ajax_referer('supplier_po_nonce', 'security');
        $allowed_statuses = ['confirm', 'reject'];

        $item_id = isset($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : '';
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $supplier_notes = isset($_POST['supplier_notes']) ? sanitize_text_field($_POST['supplier_notes']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        $supplier_delivery_date = isset($_POST['supplier_delivery_date']) ? sanitize_text_field($_POST['supplier_delivery_date']) : '';

        if (empty($item_id) || empty($order_id)) {
            wp_send_json_error(['message' => __('Invalid item or order.', 'bidfood')]);
        }

        if (empty($status)) {
            wp_send_json_error(['message' => __('Status is required.', 'bidfood')]);
        }       

        // Check if status is within a given list confirm | reject
        if (!in_array($status, $allowed_statuses)) {
            wp_send_json_error(['message'=> __('Invalid status.', 'bidfood')]);
        }

        // force supplier notes on reject
        if ($status == 'reject' && empty($supplier_notes)) {
            wp_send_json_error(['message' => __('Please leave a note when rejecting an item.', 'bidfood')]);
        }

        // check if supplier delivery date is empty
        if (empty($supplier_delivery_date) && $status == 'confirm') {
            wp_send_json_error(['message' => __('Please provide a delivery date.', 'bidfood')]);
        }

        // Set delivery date as null if the status is reject
        if ($status == 'reject') {
            $supplier_delivery_date = null;
        }

        // Validate the supplier delivery date
        if ($status == 'confirm') {
            $result = DeliveryDate::validate_supplier_expected_delivery_date($supplier_delivery_date);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }
        }

        if ($status == 'confirm') {
            $status = 'supplier confirmed';
        } else if ($status == 'reject') {
            $status = 'supplier rejected';
        } else {
            wp_send_json_error(['message' => __('Invalid status.', 'bidfood')]);
        }


        // Check if the item is assigned to the supplier
        // get supplier id
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());

        if (is_wp_error($supplier_id) || empty($supplier_id)) {
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }

        // check if the item is assigned to the supplier
        $get_item_supplier_assignment = UserSupplierManager::get_item_supplier_assignment($item_id, $order_id);

        if (empty($get_item_supplier_assignment) || $get_item_supplier_assignment['supplier_id'] !== $supplier_id) {
            wp_send_json_error(['message' => __('Item not found or unauthorized access.', 'bidfood')]);
        }

        // Update the supplier note
        if (!empty($supplier_notes) || !isset($get_item_supplier_assignment['supplier_notes']) || $get_item_supplier_assignment['supplier_notes'] !== $supplier_notes) {
            $note_result = UserSupplierManager::add_supplier_notes_to_po_item($item_id, $order_id, $supplier_notes);
            if (is_wp_error($note_result)) {
                wp_send_json_error(['message' => $note_result->get_error_message()]);
            }
        }

        // Update supplier delivery date
        $date_result = UserSupplierManager::add_supplier_delivery_date_to_po_item($item_id, $order_id, $supplier_delivery_date);
        if (is_wp_error($date_result)) {
            wp_send_json_error(['message' => $date_result->get_error_message()]);
        }

        // Update the item status to 'supplier-responded'
        $status_result = UserSupplierManager::update_supplier_po_item_status($item_id, $order_id, $status);
        if (is_wp_error($status_result)) {
            wp_send_json_error(['message' => $status_result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Item updated successfully!', 'bidfood')]);
    }

    // Handle AJAX request to submit the PO
    public function ajax_submit_supplier_po() {
        check_ajax_referer('supplier_po_nonce', 'security');

        $po_id = isset($_POST['po_id']) ? intval($_POST['po_id']) : 0;
        if (empty($po_id)) {
            wp_send_json_error(['message' => __('Invalid PO.', 'bidfood')]);
        }

        // Check if all items are confirmed
        if (!UserSupplierManager::are_all_po_items_of_status($po_id, ['supplier confirmed', 'supplier rejected'])) {
            wp_send_json_error(['message' => __('Not all items have been confirmed.', 'bidfood')]);
        }

        // Check if the PO is in the correct status
        $po = UserSupplierManager::get_supplier_po($po_id);
        if ($po['status'] !== 'sent to supplier') {
            wp_send_json_error(['message' => __('PO is not in the correct status.', 'bidfood')]);
        }

        // Trigger the PO submitted event
        do_action('bidfood_po_submitted', $po_id);

        // Update the PO status
        $result = UserSupplierManager::update_supplier_po_status($po_id, 'supplier submitted');
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('PO submitted successfully!', 'bidfood')]);
         // Generate invoices
         try {
            $customer_invoice = $this->generate_customer_invoice($po_id);
            $supplier_invoice = $this->generate_supplier_invoice($po_id);
        } catch (\Exception $e) {
            error_log('Invoice generation failed: ' . $e->getMessage());
        }
    }

    
    // Render message when no POs are found
    private function render_no_pos_message() {
        ?>
        <p><?php esc_html_e('No purchase orders assigned to this supplier.', 'bidfood'); ?></p>
        <?php
    }

    // Render message when no items are assigned in the PO
    private function render_no_items_message() {
        ?>
        <p><?php esc_html_e('No items assigned in this PO.', 'bidfood'); ?></p>
        <?php
    }

    // Render message when the PO is not found or unauthorized access
    private function render_po_not_found_message() {
        ?>
        <p><?php esc_html_e('Purchase order not found or you do not have access.', 'bidfood'); ?></p>
        <?php
    }
        // Handle invoice download
        public function handle_invoice_download() {
            if (!isset($_GET['bidfood_invoice']) || !wp_verify_nonce($_GET['_wpnonce'], 'download_invoice')) {
                return;
            }
    
            $po_id = intval($_GET['po_id']);
            $type = sanitize_text_field($_GET['type']);
            $user_id = get_current_user_id();
    
            // Verify access rights
            if (!UserSupplierManager::verify_invoice_access($user_id, $po_id, $type)) {
                wp_die(__('You do not have permission to view this invoice.', 'bidfood'));
            }
    
            // Generate PDF on the fly
            try {
                if ($type === 'customer') {
                    $this->generate_customer_invoice($po_id);
                } else {
                    $this->generate_supplier_invoice($po_id);
                }
            } catch(\Exception $e) {
                wp_die(__('Error generating invoice: ', 'bidfood') . $e->getMessage());
            }
        }
    
        private function generate_customer_invoice($po_id) {
            $po = UserSupplierManager::get_supplier_po($po_id, true);
            $items = UserSupplierManager::get_supplier_po_items($po_id);
            $customer_obj = UserSupplierManager::get_customer_data_by_po($po_id);
            $order_id = UserSupplierManager::get_order_by_po($po_id);
            $order_details = wc_get_order($order_id['order_id']);
            $customer_id=$customer_obj['customer_id'];
            $customer = get_userdata($customer_id);
            if (!$customer) {
                throw new \Exception(__('Customer not found.', 'bidfood'));
            }
        
            ob_start();
            include plugin_dir_path(__FILE__) . '../../../templates/invoice-customer.php';
            $html = ob_get_clean();
            
            InvoiceGenerator::generate($html, "customer-invoice-{$po_id}.pdf");
        }
    
        private function generate_supplier_invoice($po_id) {
            $po = UserSupplierManager::get_supplier_po($po_id, true);
            $items = UserSupplierManager::get_supplier_po_items($po_id);
            $supplier_id = UserSupplierManager::get_users_by_supplier($po['supplier_id']);
            $supplier = get_userdata($supplier_id[0]);
            $order_id = UserSupplierManager::get_order_by_po($po_id);
            $order_details = wc_get_order($order_id['order_id']);
            if (!$supplier) {
                throw new \Exception(__('Supplier not found.', 'bidfood'));
            }
        
            ob_start();
            include plugin_dir_path(__FILE__) . '../../../templates/invoice-supplier.php';
            $html = ob_get_clean();
     
            InvoiceGenerator::generate($html, "supplier-invoice-{$po_id}.pdf");
            // Trigger email notifications
            do_action('supplier_po_submitted', $po_id);
        }
}

