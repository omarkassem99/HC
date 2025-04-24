<?php

namespace Bidfood\Admin\NeomSettings\SupplierProducts;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;
use wpdb;

class ProductsExclusivity
{
    public static function init()
    {
        // add_action('woocommerce_product_query', [self::class, 'filter_products_by_outlet']);
        // add_filter('woocommerce_product_is_visible', [self::class, 'filter_product_visibility'], 10, 2);
    }

    public static function filter_products_by_outlet($query)
    {
        return $query;
        if (is_user_logged_in() && !is_admin()) {
            $user_id = get_current_user_id();

            // Fetch all product IDs the user has access to
            global $wpdb;
            $user_outlets = $wpdb->get_col($wpdb->prepare(
                "SELECT outlet_id FROM {$wpdb->prefix}neom_ch_outlet_users WHERE user_id = %d",
                $user_id
            ));

            if (!empty($user_outlets)) {
                $product_ids = $wpdb->get_col($wpdb->prepare(
                    "
                    SELECT DISTINCT item_id 
                    FROM {$wpdb->prefix}neom_ch_outlet_items 
                    WHERE outlet_id IN (" . implode(',', array_map('intval', $user_outlets)) . ")
                    "
                ));

                error_log('Product IDs: ' . print_r($product_ids, true));
                $query->set('post__in', $product_ids);
            } else {
                // $query->set('post__in', [0]); // No access
            }
        }

        return $query;
    }
    // Filter to make private products visible only to specific users
    public static function filter_product_visibility($visible, $product_id)
    {

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $exclusive_users = get_post_meta($product_id, '_exclusive_users', true);

            if (!empty($exclusive_users) && is_array($exclusive_users)) {
                return in_array($user_id, $exclusive_users);
            }
        }

        return $visible;
    }

    public static function render()
    {
        global $wpdb;

        // Define expected columns for Excel upload
        $expected_columns = [
            'Item ID' => 'item_id',
            'Outlet SKU' => 'outlet_id',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle Excel Data Parsing and Updating WooCommerce Product Meta
        if (isset($_POST['parse_excel'])) {
            self::handle_excel_upload($excel_handler, $expected_columns);
        }

        // Handle POST requests for various actions
        self::handle_post_requests($expected_columns);

        // Fetch all relationships from the outlet items table
        $table_outlet_items = $wpdb->prefix . 'neom_ch_outlet_items';
        $table_outlets = $wpdb->prefix . 'neom_ch_outlets';

        // Fetch available outlets
        $available_outlets = $wpdb->get_results("SELECT outlet_id, outlet_name FROM {$table_outlets}", ARRAY_A);

        $results = $wpdb->get_results(
            "
            SELECT  
                items.id,
                items.item_id, 
                meta.meta_value AS product_sku, 
                outlets.outlet_id, 
                p.post_title AS product_name, 
                p.post_content AS product_description, 
                outlets.outlet_name 
            FROM {$table_outlet_items} AS items
            INNER JOIN {$wpdb->postmeta} AS meta ON items.item_id = meta.post_id AND meta.meta_key = '_sku'
            INNER JOIN {$wpdb->posts} AS p ON items.item_id = p.ID
            INNER JOIN {$table_outlets} AS outlets ON items.outlet_id = outlets.outlet_id
            WHERE p.post_type = 'product'
            ORDER BY items.item_id, items.outlet_id
            ",
            ARRAY_A
        );

        // Render UI
        self::render_ui($excel_handler, $expected_columns, $results, $available_outlets);
    }

    private static function handle_excel_upload($excel_handler, $expected_columns)
    {
        global $wpdb;

        $parsed_data = $excel_handler->handle_excel_parsing();

        if ($parsed_data && !is_wp_error($parsed_data)) {
            $error_list = [];
            $processed_rows = [];
            foreach ($parsed_data as $row) {
                $item_id = sanitize_text_field($row['item_id'] ?? null);
                $outlet_id = intval($row['outlet_id'] ?? null);

                if (!$item_id || !$outlet_id) {
                    $error_list[] = sprintf(__('Invalid data for Item ID: %s.', 'bidfood'), $item_id);
                    continue;
                }

                $product_id = wc_get_product_id_by_sku($item_id);
                if (!$product_id || !wc_get_product($product_id)) {
                    $error_list[] = sprintf(__('Product with SKU: %s not found.', 'bidfood'), $item_id);
                    continue;
                }

                $table_name = $wpdb->prefix . 'neom_ch_outlet_items';
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE outlet_id = %d AND item_id = %d",
                    $outlet_id,
                    $product_id
                ));

                if (!$exists) {
                    $insert_result = $wpdb->insert(
                        $table_name,
                        ['outlet_id' => $outlet_id, 'item_id' => $product_id],
                        ['%d', '%d']
                    );

                    if ($insert_result === false) {
                        $error_list[] = sprintf(
                            __('Failed to insert outlet-product relation for SKU: %s and Outlet ID: %d.', 'bidfood'),
                            $item_id,
                            $outlet_id
                        );
                        continue;
                    }
                }

                $processed_rows[] = ['item_id' => $item_id, 'outlet_id' => $outlet_id];
            }

            // Display notifications
            self::display_notifications($error_list, $processed_rows);
        } else {
            ToastHelper::add_toast_notice(__('Error parsing the file.', 'bidfood'), 'error');
        }
    }

    private static function handle_post_requests($expected_columns)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_ch_outlet_items';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Add exclusive product
            if (isset($_POST['add_product'])) {
                self::add_exclusive_product($table_name);
            }

            // Edit exclusive product
            if (isset($_POST['edit_product'])) {
                self::edit_exclusive_product($table_name);
            }

            // Delete exclusive product
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                self::delete_exclusive_product($table_name);
            }

            // Convert exclusive products to WooCommerce private products
            if (isset($_POST['convert_exclusive_product_to_woocommerce'])) {
                self::convert_to_woocommerce_private();
            }

            // Handle Excel Template Download
            if (isset($_POST['download_template'])) {
                self::handle_download_template($expected_columns);
            }
        }
    }

    private static function add_exclusive_product($table_name)
    {
        global $wpdb;
        check_admin_referer('product_action');
        $product_sku = sanitize_text_field($_POST['product_sku']);
        $item_id = intval(wc_get_product_id_by_sku($product_sku));
        $outlet_id = intval($_POST['outlet_id']);

        if (!$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE ID = %d AND post_type = 'product'",
            $item_id
        ))) {
            ToastHelper::add_toast_notice(__('Invalid Product ID.', 'bidfood'), 'error');
            return;
        }

        if (!$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_ch_outlets WHERE outlet_id = %d",
            $outlet_id
        ))) {
            ToastHelper::add_toast_notice(__('Invalid Outlet ID.', 'bidfood'), 'error');
            return;
        }

        if ($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE outlet_id = %d AND item_id = %d",
            $outlet_id,
            $item_id
        ))) {
            ToastHelper::add_toast_notice(__('Product is already in the list.', 'bidfood'), 'error');
            return;
        }

        $result = CRUD::add_record($table_name, ['item_id' => $item_id, 'outlet_id' => $outlet_id]);

        if ($result !== false) {
            ToastHelper::add_toast_notice(__('Exclusive Product added successfully.', 'bidfood'), 'success');
        } else {
            ToastHelper::add_toast_notice(__('Failed to add Exclusive Product.', 'bidfood'), 'error');
        }
    }

    private static function edit_exclusive_product($table_name)
    {
        check_admin_referer('product_action', '_wpnonce_edit');
        $id = intval($_POST['id']);
        $item_id = intval($_POST['item_id']);
        $outlet_id = intval($_POST['outlet_id']);
        $result = CRUD::update_record($table_name, ['item_id' => $item_id, 'outlet_id' => $outlet_id], ['id' => $id]);

        if ($result !== false) {
            ToastHelper::add_toast_notice(__('Exclusive Product updated successfully.', 'bidfood'), 'success');
        } else {
            error_log($result);
            ToastHelper::add_toast_notice(__('Failed to update Exclusive Product.', 'bidfood'), 'error');
        }
    }

    private static function delete_exclusive_product($table_name)
    {
        check_admin_referer('delete_action', '_wpnonce_delete');

        $result = CRUD::delete_record($table_name, ['id' => intval($_POST['entity_id'])]);

        if ($result !== false) {
            error_log($result);
            ToastHelper::add_toast_notice(__('Exclusive Product Deleted Successfully.', 'bidfood'), 'success');
        } else {
            ToastHelper::add_toast_notice(__('Failed to delete Exclusive Product.', 'bidfood'), 'error');
        }
    }

    public static function convert_to_woocommerce_private()
    {
        global $wpdb;

        check_admin_referer('convert_exclusive_product_to_woocommerce_action', 'convert_exclusive_product_to_woocommerce_nonce');

        $outlet_items_table = $wpdb->prefix . 'neom_ch_outlet_items';
        $outlet_users_table = $wpdb->prefix . 'neom_ch_outlet_users';

        // Fetch all outlet-item relationships
        $outlet_items = $wpdb->get_results("SELECT * FROM {$outlet_items_table}", ARRAY_A);

        // Remove the current products from the exclusive list
        self::remove_exclusive_products_metadata();

        if (!empty($outlet_items)) {
            foreach ($outlet_items as $item) {
                $outlet_id = intval($item['outlet_id']);
                $product_id = intval($item['item_id']);

                $product = wc_get_product($product_id);

                // Get users associated with this outlet
                $users = $wpdb->get_col($wpdb->prepare(
                    "SELECT user_id FROM {$outlet_users_table} WHERE outlet_id = %d",
                    $outlet_id
                ));

                if (!empty($users)) {
                    // Update product to the custom 'wc-exclusive' status
                    $product->set_status('publish');
                    $product->save();

                    // Update meta for exclusive user access
                    $existing_exclusive_user_ids = get_post_meta($product_id, '_exclusive_user_ids', true);

                    // Ensure meta is an array
                    if (!is_array($existing_exclusive_user_ids)) {
                        $existing_exclusive_user_ids = [];
                    }

                    // Merge new users and ensure no duplicates
                    $users = array_unique(array_merge($existing_exclusive_user_ids, $users));
                }

                // Save the updated exclusive user IDs
                update_post_meta($product_id, '_exclusive_user_ids', $users);

                // Set product metadata as exclusive item
                update_post_meta($product_id, '_is_exclusive_item', true);
            }
            ToastHelper::add_toast_notice(__('Exclusive Products converted successfully.', 'bidfood'), 'success');
        } else {
            ToastHelper::add_toast_notice(__('No exclusive products found.', 'bidfood'), 'error');
        }
    }

    public static function remove_exclusive_products_metadata()
    {
        global $wpdb;
        $product_table = $wpdb->prefix . 'posts';

        // Fetch all exclusive products
        $product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$product_table} WHERE post_type = 'product' AND post_status = 'publish' AND meta_key = '_is_exclusive_item'"
        ));

        if (!empty($product_ids)) {
            // Delete exclusive user IDs from meta
            foreach ($product_ids as $product_id) {
                delete_post_meta($product_id, '_exclusive_user_ids');
                delete_post_meta($product_id, '_is_exclusive_item');
            }
        }

        return count($product_ids);
    }

    private static function render_ui($excel_handler, $expected_columns, $results, $available_outlets)
    {
        // UI rendering
?>
        <div class="wrap">
            <h1><?php _e('Exclusive Products', 'bidfood'); ?></h1>
            <?php $excel_handler->render_upload_button($expected_columns); ?>
            <!-- Button to trigger Add New product modal -->
            <a href="#" class="button button-primary open-modal align-group-button" data-modal="add-product-modal"
                data-entity="product" data-action="add" style="margin-top: 10px; color: white;">
                <?php _e('Add New Exclusive Product', 'bidfood'); ?>
            </a>
            <!-- Button to convert products to WooCommerce -->
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('convert_exclusive_product_to_woocommerce_action', 'convert_exclusive_product_to_woocommerce_nonce'); ?>
                <input type="submit" name="convert_exclusive_product_to_woocommerce" value="<?php _e('Convert Exclusive Products', 'bidfood'); ?>" class="button button-secondary" style="margin-top: 10px; width:fit-content">
            </form>

            <h2><?php _e('Exclusive Products List', 'bidfood'); ?></h2>
            <table class="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th><?php _e('Product SKU', 'bidfood'); ?></th>
                        <th><?php _e('Product Name', 'bidfood'); ?></th>
                        <th><?php _e('Product Description', 'bidfood'); ?></th>
                        <th><?php _e('Outlet Name', 'bidfood'); ?></th>
                        <th><?php _e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($results)) {
                        foreach ($results as $row) {
                    ?>
                            <tr>

                                <td><?php echo esc_html($row['product_sku']); ?></td>
                                <td><?php echo esc_html($row['product_name']); ?></td>
                                <td><?php echo esc_html($row['product_description']); ?></td>
                                <td><?php echo esc_html($row['outlet_name']); ?></td>
                                <td>
                                    <!-- Edit outlet Button -->
                                    <a href="#" class="button open-modal" data-modal="edit-product-modal" data-entity="product"
                                        data-action="edit" data-field_outlet_id="<?php echo esc_attr($row['outlet_id']); ?>"
                                        data-field_item_id="<?php echo esc_attr($row['item_id']); ?>"
                                        data-field_id="<?php echo esc_attr($row['id']); ?>"
                                        data-field_product_sku="<?php echo esc_attr($row['product_sku']); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-id="<?php echo esc_attr($row['id']); ?>"
                                        data-entity="product">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>

                            </tr>
                        <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="5"><?php _e('No exclusive products found.', 'bidfood'); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
<?php

        // Prepare outlet options for the select element
        $outlet_options = [];
        foreach ($available_outlets as $outlet) {
            $outlet_options[$outlet['outlet_id']] = $outlet['outlet_name'];
        }

        // Define the fields for the Add exclusive_product modal
        $add_fields = [
            ['name' => 'outlet_id', 'label' => 'Outlet Name', 'type' => 'select', 'options' => $outlet_options, 'required' => true],
            ['name' => 'product_sku', 'label' => 'Product SKU', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit exclusive_product modal (with readonly product_id)
        $edit_fields = [
            ['name' => 'id', 'type' => 'hidden', 'required' => true, 'readonly' => true],
            ['name' => 'item_id', 'label' => 'Product ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'product_sku', 'label' => 'Product SKU', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'outlet_id', 'label' => 'Outlet Name', 'type' => 'select', 'options' => $outlet_options, 'required' => true]
        ];

        // Render the Add exclusive_product modal
        ModalHelper::render_modal('add-product-modal', 'product', $add_fields, 'add');

        // Render the Edit exclusive_product modal
        ModalHelper::render_modal('edit-product-modal', 'product', $edit_fields, 'edit', '_wpnonce_edit');

        // Render the Delete exclusive_product confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'product');
    }

    private static function display_notifications($error_list, $processed_rows)
    {
        if (!empty($error_list)) {
            foreach ($error_list as $error) {
                ToastHelper::add_toast_notice($error, 'error', 0);
            }
        }

        if (!empty($processed_rows)) {
            ToastHelper::add_toast_notice(__('Exclusive product data updated successfully.', 'bidfood'), 'success');
        } else {
            ToastHelper::add_toast_notice(__('No valid rows were processed.', 'bidfood'), 'error');
        }
    }

    private static function handle_download_template($expected_columns)
    {
        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Generate the Excel template
        $file_url = $excel_handler->generate_excel_template($expected_columns);

        // Redirect to the file URL to trigger the download
        if ($file_url) {
            wp_redirect($file_url);
            exit;
        } else {
            ToastHelper::add_toast_notice(__('Failed to generate Excel template.', 'bidfood'), 'error');
        }
    }
}
