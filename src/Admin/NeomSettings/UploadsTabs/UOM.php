<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class UOM {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_uom';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'UOM ID' => 'uom_id',
            'UOM Description' => 'uom_description',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_uom']) || isset($_POST['edit_uom'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'uom_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add UOM
            if (isset($_POST['add_uom'])) {
                $data = [
                    'uom_id' => sanitize_text_field($_POST['uom_id']),
                    'uom_description' => sanitize_text_field($_POST['uom_description']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('UOM added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit UOM
            } elseif (isset($_POST['edit_uom'])) {
                $data = [
                    'uom_description' => sanitize_text_field($_POST['uom_description']),
                ];
                $where = ['uom_id' => sanitize_text_field($_POST['uom_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('UOM updated successfully.', 'bidfood'), 'success');
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
                            'uom_id' => sanitize_text_field($row['uom_id']),
                            'uom_description' => sanitize_text_field($row['uom_description'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the uom_id already exists
                        $where = ['uom_id' => $data['uom_id']];
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
                $uom_id = sanitize_text_field($_POST['entity_id']);
                if ($uom_id) {
                    $where = ['uom_id' => $uom_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_uom', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('UOM deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where uom_id is missing or invalid
                    wp_die(__('Invalid UOM ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing UOMs
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Units of Measurement (UOM)', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New UOM modal -->
            <a href="#" class="button button-primary open-modal align-uom-button"
               data-modal="add-uom-modal"
               data-entity="uom"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New UOM', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display UOMs in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('UOM List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('UOM ID', 'bidfood'); ?></th>
                            <th><?php _e('UOM Description', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->uom_id); ?></td>
                                <td><?php echo esc_html($row->uom_description); ?></td>
                                <td>
                                    <!-- Edit UOM Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-uom-modal"
                                        data-entity="uom"
                                        data-action="edit"
                                        data-field_uom_id="<?php echo esc_attr($row->uom_id); ?>"
                                        data-field_uom_description="<?php echo esc_attr($row->uom_description); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete UOM Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->uom_id); ?>"
                                       data-entity="uom">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No UOMs found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add UOM modal
        $add_fields = [
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => true],
            ['name' => 'uom_description', 'label' => 'UOM Description', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit UOM modal (with readonly uom_id)
        $edit_fields = [
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'uom_description', 'label' => 'UOM Description', 'type' => 'text', 'required' => true],
        ];

        // Render the Add UOM modal
        ModalHelper::render_modal('add-uom-modal', 'uom', $add_fields, 'add');
        
        // Render the Edit UOM modal
        ModalHelper::render_modal('edit-uom-modal', 'uom', $edit_fields, 'edit');
        
        // Render the Delete UOM confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'uom');
    }
}
