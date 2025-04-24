<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\Core\Database\CRUD;
class SupplierCurrentItemsPage {

    public function __construct() {
        // Hook to add the menu item to "My Account"
        add_filter('woocommerce_account_menu_items', [$this, 'add_supplier_items_menu_item']);
        // Hook to add endpoint for the new page
        add_action('init', [$this, 'add_supplier_items_endpoint']);
        // Handle the endpoint content
        add_action('woocommerce_account_supplier-items_endpoint', [$this, 'supplier_items_page_content']);
        // Enqueue scripts for AJAX
        add_action('wp_enqueue_scripts', [$this, 'enqueue_ajax_scripts']);
        // AJAX actions for pagination, item editing, and image upload
        add_action('wp_ajax_fetch_supplier_items', [$this, 'ajax_fetch_supplier_items']);
        add_action('wp_ajax_update_supplier_item', [$this, 'ajax_update_supplier_item']);
        add_action('wp_ajax_upload_product_image', [$this, 'ajax_upload_product_image']);
        add_action('wp_ajax_remove_product_image', [$this, 'ajax_remove_product_image']);
        add_action('wp_ajax_remove_product', [$this, 'ajax_remove_product']);

        // AJAX action for submitting supplier requests
        add_action('wp_ajax_submit_supplier_request', [$this, 'ajax_submit_supplier_request']);
    }

    public static function init() {
        return new self();
    }

    public function add_supplier_items_menu_item($items) {
        if (UserSupplierManager::is_user_supplier(get_current_user_id())) {
            $items['supplier-items'] = __('Supplier Items', 'bidfood');
        }
        return $items;
    }

    public function add_supplier_items_endpoint() {
        add_rewrite_endpoint('supplier-items', EP_ROOT | EP_PAGES);
    }

    public function enqueue_ajax_scripts() {
        wp_enqueue_script(
            'supplier-items-ajax',
            plugins_url('/assets/js/supplier-items.js', dirname(__FILE__, 3)),
            ['jquery'],
            null,
            true
        );

        wp_localize_script('supplier-items-ajax', 'supplierItemsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_items_nonce'),
            'current_user_id' => get_current_user_id(), // Pass current user ID to JS
        ]);
    }
    
    public function supplier_items_page_content() {
        ?>
        <h3><?php esc_html_e('Manage Your Items', 'bidfood'); ?></h3>
        <div id="supplier-items-container">
           
            <div style="text-align: end;">
                <input type="text" id="search-items" placeholder="<?php esc_attr_e('Search items...', 'bidfood'); ?>">
                <select id="items-per-page">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="20">20</option>
                </select>
                <a href="<?php echo esc_url(wc_get_endpoint_url('add-new-item')); ?>" class="button">
                <?php esc_html_e('Add New Item', 'bidfood'); ?>
            </a>
            </div>
            <table class="supplier-items-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Image', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Name', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Price', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Stock Status', 'bidfood'); ?></th>
                        <th><?php esc_html_e('MOQ', 'bidfood'); ?></th>
                        <th><?php esc_html_e('UOM', 'bidfood'); ?></th>
                        <th><?php esc_html_e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody id="items-table-body"></tbody>
            </table>
            <div id="supplier-pagination-container"></div>
        </div>
    
        <!-- Request Modal -->
        <div class="supplier-request-modal" name="supplier-request-modal" id="supplier-request-modal" style="display:none;">
            <div class="modal-content">
                <h3><?php esc_html_e('Submit Request', 'bidfood'); ?></h3>
                <div class="modal-body">
                    <label for="request-type"><strong><?php esc_html_e('Request Type:', 'bidfood'); ?></strong></label>
                    <select id="request-type">
                        <option value="price"><?php esc_html_e('Update Price', 'bidfood'); ?></option>
                        <option value="delist"><?php esc_html_e('Delist Product', 'bidfood'); ?></option>
                    </select>

                    <div id="price-fields" style="display:none; margin-top: 15px;">
                        <label for="current-price"><strong><?php esc_html_e('Current Price:', 'bidfood'); ?></strong></label>
                        <input type="text" id="current-price" disabled>
                        <label for="new-price" style="margin-top: 10px;"><strong><?php esc_html_e('New Price:', 'bidfood'); ?></strong></label>
                        <input type="number" id="new-price">
                    </div>

                    <label for="request-notes" style="margin-top: 15px;"><strong><?php esc_html_e('Notes:', 'bidfood'); ?></strong></label>
                    <textarea id="request-notes" rows="4" style="width:100%;"></textarea>
                </div>
                <div class="modal-actions">
                    <button id="submit-request" class="button button-primary">
                        <?php esc_html_e('Submit', 'bidfood'); ?>
                    </button>
                    <button id="close-modal" class="button"><?php esc_html_e('Close', 'bidfood'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_submit_supplier_request() {
        check_ajax_referer('supplier_items_nonce', 'security');
    
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }
    
        $item_id = sanitize_text_field($_POST['item_id']) ? sanitize_text_field($_POST['item_id']) : 0;
        $request_type = isset($_POST['request_type']) ? sanitize_text_field($_POST['request_type']) : '';
        $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
        $new_price = isset($_POST['new_price']) ? floatval($_POST['new_price']) : null;
    
        $product_id = wc_get_product_id_by_sku($item_id);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'bidfood')]);
        }

        if ($request_type === 'price' && $new_price !== null) {    
            $current_price = $product->get_price();
            if ($new_price <= 0 || $new_price === $current_price) {
                wp_send_json_error(['message' => __('Invalid price value.', 'bidfood')]);
            }
    
            $request_id = UserSupplierManager::submit_supplier_request($supplier_id, $item_id, 'price', $current_price, $new_price, $notes);

            if (is_wp_error($request_id)) {
                wp_send_json_error(['message' => $request_id->get_error_message()]);
            }

            // Trigger the supplier request initiated event
            do_action('bidfood_supplier_request_initiated', $request_id);

            wp_send_json_success(['message' => __('Price update request submitted.', 'bidfood')]);
        } elseif ($request_type === 'delist') {
            $request_id = UserSupplierManager::submit_supplier_request($supplier_id, $item_id, 'delist', null, null, $notes);

            if (is_wp_error($request_id)) {
                wp_send_json_error(['message' => $request_id->get_error_message()]);
            }

            // Trigger the supplier request initiated event
            do_action('bidfood_supplier_request_initiated', $request_id);

            wp_send_json_success(['message' => __('Delist request submitted.', 'bidfood')]);
        } else {
            wp_send_json_error(['message' => __('Invalid request type.', 'bidfood')]);
        }
    }
    
    public function ajax_fetch_supplier_items() {
        check_ajax_referer('supplier_items_nonce', 'security');

        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            wp_send_json_error(['message' => __('Supplier not found.', 'bidfood')]);
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';

        $query_args = [
            'post_type' => 'product',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'tax_query' => [
                [
                    'taxonomy' => 'pa_preferred_supplier_id',
                    'field' => 'slug',
                    'terms' => $supplier_id,
                ],
            ],
            's' => $search,
        ];
        $query = new \WP_Query($query_args);

        if (!$query->have_posts()) {
            wp_send_json_error(['message' => __('No items found.', 'bidfood')]);
        }

        ob_start();
        foreach ($query->posts as $post) {
            $this->render_item_row($post);
        }
        $table_rows = ob_get_clean();

        ob_start();
        $this->render_pagination($page, $query->max_num_pages);
        $pagination = ob_get_clean();

        wp_send_json_success(['rows' => $table_rows, 'pagination' => $pagination]);
    }

    public function ajax_update_supplier_item() {
        check_ajax_referer('supplier_items_nonce', 'security');
    
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $data = isset($_POST['data']) ? $_POST['data'] : [];
    
        if (!$item_id || empty($data)) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }
    
        // Load the product object
        $product = wc_get_product($item_id);
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found.', 'bidfood')]);
        }
    
        // Process updates
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'stock_status':
                    // Validate stock status values
                    $valid_statuses = ['instock', 'outofstock'];
                    if (in_array($value, $valid_statuses, true)) {
                        $product->set_stock_status($value);
                        // Set stock quantity to 0 if out of stock
                        if ($value === 'outofstock') {
                            $product->set_stock_quantity(0);
                        } else if ($value === 'instock') {
                            $product->set_stock_quantity(1000000); // Set a default stock quantity
                        }
                    } else {
                        wp_send_json_error(['message' => __('Invalid stock status value.', 'bidfood')]);
                    }
                    break;
    
                case 'pa_moq':
                case 'pa_uom':
                    // Handle product attributes (ensure terms exist)
                    $taxonomy = $field;
                    $term = sanitize_text_field($value);
    
                    if ($term && !term_exists($term, $taxonomy)) {
                        wp_insert_term($term, $taxonomy);
                    }
    
                    // Set the term for the product
                    wp_set_object_terms($item_id, $term, $taxonomy, false);
                    break;
    
                default:
                    // Skip unknown fields
                    continue 2;
            }
        }
    
        // Save the product after updates
        $product->save();
    
        wp_send_json_success(['message' => __('Item updated successfully.', 'bidfood')]);
    }
    
    public function ajax_upload_product_image() {
        check_ajax_referer('supplier_items_nonce', 'security');
    
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }
    
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded.', 'bidfood')]);
        }
    
        $file = $_FILES['file'];
    
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(['message' => __('Invalid file type. Only JPEG, PNG, and GIF are allowed.', 'bidfood')]);
        }
    
        // Validate file size (e.g., max 10MB)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => __('File is too large. Maximum size is 10MB.', 'bidfood')]);
        }
    
        // Sanitize and move the file
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/supplier_uploads/';
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
    
        $file_name = wp_unique_filename($target_dir, sanitize_file_name($file['name']));
        $file_path = $target_dir . $file_name;
    
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            wp_send_json_error(['message' => __('Failed to upload the file.', 'bidfood')]);
        }
    
        // Attach the file to the product
        $attachment = [
            'guid' => $upload_dir['baseurl'] . '/supplier_uploads/' . $file_name,
            'post_mime_type' => $file['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $file_name),
            'post_content' => '',
            'post_status' => 'inherit',
        ];
        $attachment_id = wp_insert_attachment($attachment, $file_path, $item_id);
    
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(['message' => __('Failed to save the file.', 'bidfood')]);
        }
    
        // Generate attachment metadata
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
    
        // Set as product's featured image
        set_post_thumbnail($item_id, $attachment_id);
    
        wp_send_json_success([
            'message' => __('Image uploaded successfully.', 'bidfood'),
            'image_url' => wp_get_attachment_url($attachment_id),
        ]);
    } 

    // Remove product image
    public function ajax_remove_product_image() {
        check_ajax_referer('supplier_items_nonce', 'security');

        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }

        // Check if the product has a featured image
        if (!has_post_thumbnail($item_id)) {
            wp_send_json_error(['message' => __('No image to remove.', 'bidfood')]);
        }

        // Remove the product's featured image
        delete_post_thumbnail($item_id);

        wp_send_json_success(['message' => __('Product image removed successfully.', 'bidfood')]);
    }

    // Remove product
    public function ajax_remove_product() {
        check_ajax_referer('supplier_items_nonce', 'security');
    
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
    
        if (!$item_id) {
            wp_send_json_error(['message' => __('Invalid request.', 'bidfood')]);
        }
    
        // Verify that the current user is authorized to delete this product
        $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());
        if (is_wp_error($supplier_id)) {
            wp_send_json_error(['message' => __('You are not authorized to modify this product.', 'bidfood')]);
        }
    
        // Verify the product belongs to the supplier
        if (!has_term($supplier_id, 'pa_preferred_supplier_id', $item_id)) {
            wp_send_json_error(['message' => __('You are not authorized to modify this product.', 'bidfood')]);
        }
    
        // Change the product status to 'draft' to delist it (you can also use 'private' or other statuses)
        $updated = wp_update_post([
            'ID' => $item_id,
            'post_status' => 'draft'  // Change this to 'private' if you want to hide the product
        ]);
    
        if ($updated) {
            wp_send_json_success(['message' => __('Product delisted successfully.', 'bidfood')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delist the product.', 'bidfood')]);
        }
    }
    
    private function render_item_row($post) {
        global $wpdb;
    
        $product = wc_get_product($post->ID);

        if (!$product) {
            return;
        }

        $product_sku = $product->get_sku();

        $price = $product->get_price();
        $stock_status = $product->get_stock_status();
        $image_url = get_the_post_thumbnail_url($post->ID, 'thumbnail') ?: wc_placeholder_img_src(); // Default placeholder if no image
    
        $moq = wp_get_post_terms($post->ID, 'pa_moq', ['fields' => 'names']);
        $uom_terms = wp_get_post_terms($post->ID, 'pa_uom', ['fields' => 'names']);
        $uom_selected = $uom_terms[0] ?? ''; // Use the first term as the selected UOM
    
        // Fetch all possible UOMs from the database using CRUD
        $uom_table_name = $wpdb->prefix . 'neom_uom';
        $uom_records = CRUD::fetch_records($uom_table_name, '', 1000);

        ?>
        <tr data-item-id="<?php echo esc_attr($post->ID); ?>">
            <td>
                <div class="image-upload-wrapper">
                    <!-- Image container -->
                    <div class="image-upload-container">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php esc_attr_e('Product Image', 'bidfood'); ?>" width="50">
                    </div>
                    <!-- Remove Image Button -->
                    <?php if (has_post_thumbnail($post->ID)): ?>
                        <button class="button remove-image-btn" data-item-id="<?php echo esc_attr($post->ID); ?>">
                            <?php esc_html_e('X', 'bidfood'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </td>
            <td><?php echo esc_html($post->post_title); ?></td>
            <td><input type="number" style="width:70px" class="editable-field" data-field="price" value="<?php echo esc_attr($price); ?>" disabled></td>
            <td>
                <select class="editable-field" data-field="stock_status">
                    <option value="instock" <?php selected($stock_status, 'instock'); ?>><?php esc_html_e('In Stock', 'bidfood'); ?></option>
                    <option value="outofstock" <?php selected($stock_status, 'outofstock'); ?>><?php esc_html_e('Out of Stock', 'bidfood'); ?></option>
                </select>
            </td>
            <td><input type="number" style="width:70px" class="editable-field" data-field="pa_moq" value="<?php echo esc_attr($moq[0] ?? ''); ?>" min="0" step="1"></td>
            <td>
                <select class="editable-field" data-field="pa_uom">
                    <option value=""><?php esc_html_e('Select UOM', 'bidfood'); ?></option>
                    <?php if ($uom_records): ?>
                        <?php foreach ($uom_records as $uom): ?>
                            <?php
                            // Use the provided logic to fetch the UOM description
                            $description = $uom->uom_description ?? $uom->name;
                            ?>
                            <option value="<?php echo esc_attr($uom->uom_id); ?>" <?php selected($uom_selected, $uom->uom_id); ?>>
                                <?php echo esc_html($description); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </td>
            <td>
                <!-- Action buttons for saving or removing items -->
                <div class="actions-container">
                    <button class="button save-item-btn" data-item-id="<?php echo esc_attr($post->ID); ?>">
                        <?php esc_html_e('Save', 'bidfood'); ?>
                    </button>
                    <button href="supplier-request-modal" class="button request-action-btn" data-item-id="<?php echo esc_attr($post->ID); ?>" data-product-sku="<?php echo esc_attr($product_sku); ?>" data-current-price="<?php echo esc_attr($price); ?>">
                        <?php esc_html_e('Requests', 'bidfood'); ?>
                    </button>
                </div>
            </td>
        </tr>
        <?php
    }
    
    private function render_pagination($current_page, $total_pages) {
        ?>
        <div class="supplier-pagination-container">
            <span id="supplier-pagination-info">
                <?php printf(esc_html__('Page %d of %d', 'bidfood'), $current_page, $total_pages); ?>
            </span>
            <div class="supplier-pagination-controls">
                <?php if ($current_page > 1): ?>
                    <a href="#" class="pagination-link" data-page="<?php echo esc_attr($current_page - 1); ?>">&laquo; <?php esc_html_e('Previous', 'bidfood'); ?></a>
                <?php endif; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="#" class="pagination-link" data-page="<?php echo esc_attr($current_page + 1); ?>"><?php esc_html_e('Next', 'bidfood'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
