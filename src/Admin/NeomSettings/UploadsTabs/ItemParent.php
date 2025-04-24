<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class ItemParent {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_item_parent';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Item Parent ID' => 'item_parent_id',
            'Description' => 'description',
            'UOM ID' => 'uom_id',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_item_parent']) || isset($_POST['edit_item_parent'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'item_parent_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Item Parent
            if (isset($_POST['add_item_parent'])) {
                $data = [
                    'item_parent_id' => sanitize_text_field($_POST['item_parent_id']),
                    'description' => sanitize_text_field($_POST['description']),
                    'uom_id' => sanitize_text_field($_POST['uom_id']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Item Parent added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit Item Parent
            } elseif (isset($_POST['edit_item_parent'])) {
                $data = [
                    'description' => sanitize_text_field($_POST['description']),
                    'uom_id' => sanitize_text_field($_POST['uom_id']),
                ];
                $where = ['item_parent_id' => sanitize_text_field($_POST['item_parent_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Item Parent updated successfully.', 'bidfood'), 'success');
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
                            'item_parent_id' => sanitize_text_field($row['item_parent_id']),
                            'description' => sanitize_text_field($row['description'] ?? null),
                            'uom_id' => sanitize_text_field($row['uom_id'] ?? null),
                        ];
                        
                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the item_parent_id already exists
                        $where = ['item_parent_id' => $data['item_parent_id']];
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
                $item_parent_id = sanitize_text_field($_POST['entity_id']);
                if ($item_parent_id) {
                    $where = ['item_parent_id' => $item_parent_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_item_parent', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Item Parent deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where item_parent_id is missing or invalid
                    wp_die(__('Invalid Item Parent ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing Item Parents
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Item Parents', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Item Parent modal -->
            <a href="#" class="button button-primary open-modal align-item-parent-button"
               data-modal="add-item-parent-modal"
               data-entity="item_parent"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New Item Parent', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display Item Parents in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Item Parent List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Item Parent ID', 'bidfood'); ?></th>
                            <th><?php _e('Description', 'bidfood'); ?></th>
                            <th><?php _e('UOM ID', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->item_parent_id); ?></td>
                                <td><?php echo esc_html($row->description); ?></td>
                                <td><?php echo esc_html($row->uom_id); ?></td>
                                <td>
                                    <!-- Edit Item Parent Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-item-parent-modal"
                                        data-entity="item_parent"
                                        data-action="edit"
                                        data-field_item_parent_id="<?php echo esc_attr($row->item_parent_id); ?>"
                                        data-field_description="<?php echo esc_attr($row->description); ?>"
                                        data-field_uom_id="<?php echo esc_attr($row->uom_id); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Item Parent Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->item_parent_id); ?>"
                                       data-entity="item_parent">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No Item Parents found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Item Parent modal
        $add_fields = [
            ['name' => 'item_parent_id', 'label' => 'Item Parent ID', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit Item Parent modal (with readonly item_parent_id)
        $edit_fields = [
            ['name' => 'item_parent_id', 'label' => 'Item Parent ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => true],
        ];

        // Render the Add Item Parent modal
        ModalHelper::render_modal('add-item-parent-modal', 'item_parent', $add_fields, 'add');
        
        // Render the Edit Item Parent modal
        ModalHelper::render_modal('edit-item-parent-modal', 'item_parent', $edit_fields, 'edit');
        
        // Render the Delete Item Parent confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'item_parent');
    }
}
