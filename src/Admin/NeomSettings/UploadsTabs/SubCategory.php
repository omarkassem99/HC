<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class SubCategory {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_sub_category';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'SubCategory ID' => 'sub_category_id',
            'SubCategory Name' => 'sub_category_name',
            'Category ID' => 'category_id',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_sub_category']) || isset($_POST['edit_sub_category'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sub_category_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add SubCategory
            if (isset($_POST['add_sub_category'])) {
                $data = [
                    'sub_category_id' => sanitize_text_field($_POST['sub_category_id']),
                    'sub_category_name' => sanitize_text_field($_POST['sub_category_name']),
                    'category_id' => sanitize_text_field($_POST['category_id']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('SubCategory added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit SubCategory
            } elseif (isset($_POST['edit_sub_category'])) {
                $data = [
                    'sub_category_name' => sanitize_text_field($_POST['sub_category_name']),
                    'category_id' => sanitize_text_field($_POST['category_id']),
                ];
                $where = ['sub_category_id' => sanitize_text_field($_POST['sub_category_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('SubCategory updated successfully.', 'bidfood'), 'success');
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
                            'sub_category_id' => sanitize_text_field($row['sub_category_id']),
                            'sub_category_name' => sanitize_text_field($row['sub_category_name'] ?? null),
                            'category_id' => sanitize_text_field($row['category_id'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the sub_category_id already exists
                        $where = ['sub_category_id' => $data['sub_category_id']];
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
                $sub_category_id = sanitize_text_field($_POST['entity_id']);
                if ($sub_category_id) {
                    $where = ['sub_category_id' => $sub_category_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_sub_category', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('SubCategory deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where sub_category_id is missing or invalid
                    wp_die(__('Invalid SubCategory ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing subcategories
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('SubCategories', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New SubCategory modal -->
            <a href="#" class="button button-primary open-modal align-sub-category-button"
               data-modal="add-sub-category-modal"
               data-entity="sub_category"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New SubCategory', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Button to convert subcategories to WooCommerce -->
            <form method="post" style="display:inline;">
                <?php wp_nonce_field('convert_subcategories_to_woocommerce_action', 'convert_subcategories_to_woocommerce_nonce'); ?>
                <input type="submit" name="convert_subcategories_to_woocommerce" value="<?php _e('Convert Subcategories to WooCommerce', 'bidfood'); ?>" class="button" style="width:fit-content;margin-top: 10px;">
            </form>

            <!-- Display SubCategories in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('SubCategory List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('SubCategory ID', 'bidfood'); ?></th>
                            <th><?php _e('SubCategory Name', 'bidfood'); ?></th>
                            <th><?php _e('Category ID', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->sub_category_id); ?></td>
                                <td><?php echo esc_html($row->sub_category_name); ?></td>
                                <td><?php echo esc_html($row->category_id); ?></td>
                                <td>
                                    <!-- Edit SubCategory Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-sub-category-modal"
                                        data-entity="sub_category"
                                        data-action="edit"
                                        data-field_sub_category_id="<?php echo esc_attr($row->sub_category_id); ?>"
                                        data-field_sub_category_name="<?php echo esc_attr($row->sub_category_name); ?>"
                                        data-field_category_id="<?php echo esc_attr($row->category_id); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete SubCategory Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->sub_category_id); ?>"
                                       data-entity="sub_category">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No subcategories found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add SubCategory modal
        $add_fields = [
            ['name' => 'sub_category_id', 'label' => 'SubCategory ID', 'type' => 'text', 'required' => true],
            ['name' => 'sub_category_name', 'label' => 'SubCategory Name', 'type' => 'text', 'required' => true],
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit SubCategory modal (with readonly sub_category_id)
        $edit_fields = [
            ['name' => 'sub_category_id', 'label' => 'SubCategory ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'sub_category_name', 'label' => 'SubCategory Name', 'type' => 'text', 'required' => true],
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => true],
        ];

        // Render the Add SubCategory modal
        ModalHelper::render_modal('add-sub-category-modal', 'sub_category', $add_fields, 'add');
        
        // Render the Edit SubCategory modal
        ModalHelper::render_modal('edit-sub-category-modal', 'sub_category', $edit_fields, 'edit');
        
        // Render the Delete SubCategory confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'sub_category');
    }

    public function convert_subcategories_to_woocommerce_subcategories() {
        global $wpdb;
    
        // Fetch all subcategories from the custom table
        $subcategories = CRUD::fetch_records($wpdb->prefix . 'neom_sub_category', '', 10000);
    
        // Loop through each subcategory and create or update a WooCommerce subcategory
        foreach ($subcategories as $subcategory_item) {
            // Fetch the parent category ID (assumed to be available in subcategory_item)
            $parent_category_id = term_exists($subcategory_item->category_id, 'product_cat');
    
            if (!$parent_category_id) {
                error_log('Parent category not found for subcategory: ' . $subcategory_item->sub_category_name);
                // If parent category doesn't exist, skip the subcategory
                continue;
            }
    
            // Check if a subcategory with the same slug already exists
            $existing_subcategory_id = term_exists($subcategory_item->sub_category_id, 'product_cat');
    
            if ($existing_subcategory_id) {
                // Update existing subcategory
                wp_update_term($existing_subcategory_id['term_id'], 'product_cat', [
                    'name' => $subcategory_item->sub_category_name,
                    'slug' => sanitize_title($subcategory_item->sub_category_id),
                    'parent' => $parent_category_id['term_id']
                ]);
            } else {
                // Create new subcategory if it doesn't exist
                wp_insert_term($subcategory_item->sub_category_name, 'product_cat', [
                    'slug' => sanitize_title($subcategory_item->sub_category_id),
                    'parent' => $parent_category_id['term_id']
                ]);
            }
        }
    }

    public static function handle_woocommerce_subcategory_conversion() {
        if (isset($_POST['convert_subcategories_to_woocommerce'])) {
            // Verify nonce for security
            if (!isset($_POST['convert_subcategories_to_woocommerce_nonce']) || !wp_verify_nonce($_POST['convert_subcategories_to_woocommerce_nonce'], 'convert_subcategories_to_woocommerce_action')) {
                wp_die(__('Security check failed.', 'bidfood'));
            }
    
            // Call the function to convert subcategories to WooCommerce subcategories
            $instance = new self();
            $instance->convert_subcategories_to_woocommerce_subcategories();
    
            // Optionally, show an admin notice after conversion
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Subcategories successfully converted to WooCommerce subcategories.', 'bidfood') . '</p></div>';
            });
        }
    }
    
}
