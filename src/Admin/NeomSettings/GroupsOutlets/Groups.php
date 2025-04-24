<?php

namespace Bidfood\Admin\NeomSettings\GroupsOutlets;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Groups
{

    public static function render()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_ch_groups';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Group ID' => 'group_id',
            'Group Name' => 'group_name',
            'Group Description' => 'group_description',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_group']) || isset($_POST['edit_group'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'group_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Operator
            if (isset($_POST['add_group'])) {
                $data = [
                    'group_name' => sanitize_text_field($_POST['group_name']),
                    'group_description' => sanitize_text_field($_POST['group_description']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Group added successfully.', 'bidfood'), 'success');
                }
            }

            // Handle Edit Operator
            elseif (isset($_POST['edit_group'])) {
                $data = [
                    'group_name' => sanitize_text_field($_POST['group_name']),
                    'group_description' => sanitize_text_field($_POST['group_description']),
                ];
                $where = ['group_id' => intval($_POST['group_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('group updated successfully.', 'bidfood'), 'success');
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
                            'group_id' => intval($row['group_id']),
                            'group_name' => sanitize_text_field($row['group_name'] ?? null),
                            'group_description' => sanitize_text_field($row['group_description'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the group_id already exists
                        $where = ['group_id' => $data['group_id']];
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
                $group_id = intval($_POST['entity_id']);
                if ($group_id) {
                    $where = ['group_id' => $group_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_ch_groups', $where);

                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('group deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where group_id is missing or invalid
                    wp_die(__('Invalid group ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing groups
        $results = CRUD::fetch_records($table_name);
        ?>
        <div class="wrap">
    
            <div>
                <h1 class="wp-heading-inline"><?php _e('Groups', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New group modal -->
            <a href="#" class="button button-primary open-modal align-group-button" data-modal="add-group-modal"
                data-entity="group" data-action="add" style="margin-top: 10px; color: white;">
                <?php _e('Add New Group', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display groups in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Group List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Group ID', 'bidfood'); ?></th>
                            <th><?php _e('Group Name', 'bidfood'); ?></th>
                            <th><?php _e('Group Description', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->group_id); ?></td>
                                <td><?php echo esc_html(sanitize_text_field($row->group_name)); ?></td>
                                <td><?php echo sanitize_text_field($row->group_description); ?></td>
                                <td>
                                    <!-- Edit group Button -->
                                    <a href="#" class="button open-modal" data-modal="edit-group-modal" data-entity="group"
                                        data-action="edit" data-field_group_id="<?php echo esc_attr($row->group_id); ?>"
                                        data-field_group_name="<?php echo esc_attr($row->group_name); ?>"
                                        data-field_group_description="<?php echo esc_attr($row->group_description); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete group Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger" data-modal="confirmation-modal"
                                        data-id="<?php echo esc_attr(intval($row->group_id)); ?>" data-entity="group">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No Groups Found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add group modal
        $add_fields = [
            ['name' => 'group_name', 'label' => 'Group Name', 'type' => 'text', 'required' => true],
            ['name' => 'group_description', 'label' => 'Group Description', 'type' => 'textarea', 'required' => false],
        ];

        // Define the fields for the Edit group modal (with readonly group_id)
        $edit_fields = [
            ['name' => 'group_id', 'label' => 'group ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'group_name', 'label' => 'group Name', 'type' => 'text', 'required' => true],
            ['name' => 'group_description', 'label' => 'Group Description', 'type' => 'textarea', 'required' => false],
        ];

        // Render the Add group modal
        ModalHelper::render_modal('add-group-modal', 'group', $add_fields, 'add');

        // Render the Edit group modal
        ModalHelper::render_modal('edit-group-modal', 'group', $edit_fields, 'edit');

        // Render the Delete group confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'group');
    }
}
