<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Category {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_category';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Category ID' => 'category_id',
            'Category Name' => 'category_name',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_category']) || isset($_POST['edit_category'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'category_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Category
            if (isset($_POST['add_category'])) {
                $data = [
                    'category_id' => sanitize_text_field($_POST['category_id']),
                    'category_name' => sanitize_text_field($_POST['category_name']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Category added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit Category
            } elseif (isset($_POST['edit_category'])) {
                $data = [
                    'category_name' => sanitize_text_field($_POST['category_name']),
                ];
                $where = ['category_id' => sanitize_text_field($_POST['category_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Category updated successfully.', 'bidfood'), 'success');
                }
            }

            // Handle Excel Data Insertion
            if (isset($_POST['parse_excel'])) {
                $parsed_data = $excel_handler->handle_excel_parsing(); // Get parsed data

                if ($parsed_data && !is_wp_error($parsed_data)) {
                    // Loop through the parsed data and insert each row into the database
                    $error_list = [];
                    foreach ($parsed_data as $row) {
                        $data = [
                            'category_id' => sanitize_text_field($row['category_id']),
                            'category_name' => sanitize_text_field($row['category_name'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the category_id already exists
                        $where = ['category_id' => $data['category_id']];
                        $result = CRUD::find_record($table_name, $where);

                        // if it exists, update the record if not add a new record
                        if (is_wp_error($result) || empty($result) || $result === null) {
                            $result = CRUD::add_record($table_name, $data);
                        } else {
                            $result = CRUD::update_record($table_name, $data, $where);
                        }

                        // Check for errors during insertion
                        if (is_wp_error($result)) {
                            $error_list[] = $result->get_error_message();
                        }
                    }

                    // Display a toast notification
                    if (!empty($error_list)) {
                        foreach ($error_list as $error) {
                            ToastHelper::add_toast_notice($error, 'error', 0);
                        }
                    } else {
                        ToastHelper::add_toast_notice(__('Excel data inserted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle parsing error
                    ToastHelper::add_toast_notice($parsed_data->get_error_message(), 'error', 0);
                }
            }

            // Check for nonce validation for delete action
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                if (!isset($_POST['_wpnonce_delete']) || !wp_verify_nonce($_POST['_wpnonce_delete'], 'delete_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }

                // Process the deletion
                $category_id = sanitize_text_field($_POST['entity_id']);
                if ($category_id) {
                    $where = ['category_id' => $category_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_category', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Category deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where category_id is missing or invalid
                    wp_die(__('Invalid category ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing categories
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Categories', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Category modal -->
            <a href="#" class="button button-primary open-modal align-category-button"
               data-modal="add-category-modal"
               data-entity="category"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New Category', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Button to convert categories to WooCommerce -->
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('convert_categories_to_woocommerce_action', 'convert_categories_to_woocommerce_nonce'); ?>
                <input type="submit" name="convert_categories_to_woocommerce" value="<?php _e('Convert Categories to WooCommerce', 'bidfood'); ?>" class="button" style=" width:fit-content; margin-top: 10px;">
            </form>

            <!-- Display Categories in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Category List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Category ID', 'bidfood'); ?></th>
                            <th><?php _e('Category Name', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->category_id); ?></td>
                                <td><?php echo esc_html($row->category_name); ?></td>
                                <td>
                                    <!-- Edit Category Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-category-modal"
                                        data-entity="category"
                                        data-action="edit"
                                        data-field_category_id="<?php echo esc_attr($row->category_id); ?>"
                                        data-field_category_name="<?php echo esc_attr($row->category_name); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Category Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->category_id); ?>"
                                       data-entity="category">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No categories found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Category modal
        $add_fields = [
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => true],
            ['name' => 'category_name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit Category modal (with readonly category_id)
        $edit_fields = [
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'category_name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
        ];

        // Render the Add Category modal
        ModalHelper::render_modal('add-category-modal', 'category', $add_fields, 'add');
        
        // Render the Edit Category modal
        ModalHelper::render_modal('edit-category-modal', 'category', $edit_fields, 'edit');
        
        // Render the Delete Category confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'category');
    }

    public function convert_categories_to_woocommerce_categories() {
        global $wpdb;
    
        // Fetch all categories from the custom table
        $categories = CRUD::fetch_records($wpdb->prefix . 'neom_category', '', 10000);
    
        // Loop through each category and create or update a WooCommerce category
        foreach ($categories as $category_item) {
            // Check if a category with the same slug already exists
            $existing_category_id = term_exists($category_item->category_id, 'product_cat');
    
            if ($existing_category_id) {
                // Update existing category
                wp_update_term($existing_category_id['term_id'], 'product_cat', [
                    'name' => $category_item->category_name,
                    'slug' => sanitize_title($category_item->category_id),
                ]);
            } else {
                // Create new category if it doesn't exist
                wp_insert_term($category_item->category_name, 'product_cat', [
                    'slug' => sanitize_title($category_item->category_id),
                ]);
            }
        }
    }

    public static function handle_woocommerce_category_conversion() {
        if (isset($_POST['convert_categories_to_woocommerce'])) {
            // Verify nonce for security
            if (!isset($_POST['convert_categories_to_woocommerce_nonce']) || !wp_verify_nonce($_POST['convert_categories_to_woocommerce_nonce'], 'convert_categories_to_woocommerce_action')) {
                wp_die(__('Security check failed.', 'bidfood'));
            }
    
            // Call the function to convert categories to WooCommerce categories
            $instance = new self();
            $instance->convert_categories_to_woocommerce_categories();
    
            // Optionally, show an admin notice after conversion
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Categories successfully converted to WooCommerce categories.', 'bidfood') . '</p></div>';
            });
        }
    }
}
