<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Operator {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_operator';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Operator ID' => 'operator_id',
            'Operator Name' => 'operator_name',
            'Operator Description' => 'description',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_operator']) || isset($_POST['edit_operator'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'operator_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Operator
            if (isset($_POST['add_operator'])) {
                $data = [
                    'operator_id' => sanitize_text_field($_POST['operator_id']),
                    'operator_name' => sanitize_text_field($_POST['operator_name']),
                    'description' => sanitize_text_field($_POST['description']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Operator added successfully.', 'bidfood'), 'success');
                }
            }

            // Handle Edit Operator
            elseif (isset($_POST['edit_operator'])) {
                $data = [
                    'operator_name' => sanitize_text_field($_POST['operator_name']),
                    'description' => sanitize_text_field($_POST['description']),
                ];
                $where = ['operator_id' => sanitize_text_field($_POST['operator_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Operator updated successfully.', 'bidfood'), 'success');
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
                            'operator_id' => sanitize_text_field($row['operator_id']),
                            'operator_name' => sanitize_text_field($row['operator_name'] ?? null),
                            'description' => sanitize_text_field($row['description'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the operator_id already exists
                        $where = ['operator_id' => $data['operator_id']];
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
                    // Handle error parsing
                    if (is_wp_error($parsed_data)) {
                        ToastHelper::add_toast_notice($parsed_data->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('No data found in the Excel file.', 'bidfood'), 'error', 0);
                    }
                }
            }

            // Check for nonce validation for delete action
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                if (!isset($_POST['_wpnonce_delete']) || !wp_verify_nonce($_POST['_wpnonce_delete'], 'delete_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }

                // Process the deletion
                $operator_id = sanitize_text_field($_POST['entity_id']);
                if ($operator_id) {
                    $where = ['operator_id' => $operator_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_operator', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Operator deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where operator_id is missing or invalid
                    wp_die(__('Invalid operator ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing operators
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Operators', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Operator modal -->
            <a href="#" class="button button-primary open-modal align-operator-button"
               data-modal="add-operator-modal"
               data-entity="operator"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New Operator', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display Operators in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Operator List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Operator ID', 'bidfood'); ?></th>
                            <th><?php _e('Operator Name', 'bidfood'); ?></th>
                            <th><?php _e('Description', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->operator_id); ?></td>
                                <td><?php echo esc_html($row->operator_name); ?></td>
                                <td><?php echo esc_html($row->description); ?></td>
                                <td>
                                    <!-- Edit Operator Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-operator-modal"
                                        data-entity="operator"
                                        data-action="edit"
                                        data-field_operator_id="<?php echo esc_attr($row->operator_id); ?>"
                                        data-field_operator_name="<?php echo esc_attr($row->operator_name); ?>"
                                        data-field_description="<?php echo esc_attr($row->description); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Operator Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->operator_id); ?>"
                                       data-entity="operator">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No operators found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Operator modal
        $add_fields = [
            ['name' => 'operator_id', 'label' => 'Operator ID', 'type' => 'text', 'required' => true],
            ['name' => 'operator_name', 'label' => 'Operator Name', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false],
        ];

        // Define the fields for the Edit Operator modal (with readonly operator_id)
        $edit_fields = [
            ['name' => 'operator_id', 'label' => 'Operator ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'operator_name', 'label' => 'Operator Name', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false],
        ];

        // Render the Add Operator modal
        ModalHelper::render_modal('add-operator-modal', 'operator', $add_fields, 'add');
        
        // Render the Edit Operator modal
        ModalHelper::render_modal('edit-operator-modal', 'operator', $edit_fields, 'edit');
        
        // Render the Delete Operator confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'operator');
    }
}
