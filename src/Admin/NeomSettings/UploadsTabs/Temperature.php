<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Temperature {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_temperature';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Temperature ID' => 'temperature_id',
            'Temperature Description' => 'temperature_description',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_temperature']) || isset($_POST['edit_temperature'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'temperature_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Temperature
            if (isset($_POST['add_temperature'])) {
                $data = [
                    'temperature_id' => sanitize_text_field($_POST['temperature_id']),
                    'temperature_description' => sanitize_text_field($_POST['temperature_description']),
                ];
                
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Temperature added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit Temperature
            } elseif (isset($_POST['edit_temperature'])) {
                $data = [
                    'temperature_description' => sanitize_text_field($_POST['temperature_description']),
                ];
                $where = ['temperature_id' => sanitize_text_field($_POST['temperature_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Temperature updated successfully.', 'bidfood'), 'success');
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
                            'temperature_id' => sanitize_text_field($row['temperature_id']),
                            'temperature_description' => sanitize_text_field($row['temperature_description'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the temperature_id already exists
                        $where = ['temperature_id' => $data['temperature_id']];
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
                $temperature_id = sanitize_text_field($_POST['entity_id']);
                if ($temperature_id) {
                    $where = ['temperature_id' => $temperature_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_temperature', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Temperature deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where temperature_id is missing or invalid
                    wp_die(__('Invalid temperature ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing temperatures
        $results = CRUD::fetch_records($table_name);

        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Temperatures', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Temperature modal -->
            <a href="#" class="button button-primary open-modal align-temperature-button"
               data-modal="add-temperature-modal"
               data-entity="temperature"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New Temperature', 'bidfood'); ?>
            </a>

                <!-- Render the Excel upload button and modal for column mapping -->
                <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display Temperatures in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Temperature List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Temperature ID', 'bidfood'); ?></th>
                            <th><?php _e('Temperature Description', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->temperature_id); ?></td>
                                <td><?php echo esc_html($row->temperature_description); ?></td>
                                <td>
                                    <!-- Edit Temperature Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-temperature-modal"
                                        data-entity="temperature"
                                        data-action="edit"
                                        data-field_temperature_id="<?php echo esc_attr($row->temperature_id); ?>"
                                        data-field_temperature_description="<?php echo esc_attr($row->temperature_description); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Temperature Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->temperature_id); ?>"
                                       data-entity="temperature">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No temperatures found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Temperature modal
        $add_fields = [
            ['name' => 'temperature_id', 'label' => 'Temperature ID', 'type' => 'text', 'required' => true],
            ['name' => 'temperature_description', 'label' => 'Temperature Description', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit Temperature modal (with readonly temperature_id)
        $edit_fields = [
            ['name' => 'temperature_id', 'label' => 'Temperature ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'temperature_description', 'label' => 'Temperature Description', 'type' => 'text', 'required' => true],
        ];

        // Render the Add Temperature modal
        ModalHelper::render_modal('add-temperature-modal', 'temperature', $add_fields, 'add');
        
        // Render the Edit Temperature modal
        ModalHelper::render_modal('edit-temperature-modal', 'temperature', $edit_fields, 'edit');
        
        // Render the Delete Temperature confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'temperature');
    }
}
