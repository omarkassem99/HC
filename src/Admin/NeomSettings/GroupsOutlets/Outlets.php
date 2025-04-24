<?php

namespace Bidfood\Admin\NeomSettings\GroupsOutlets;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\UserManagement\UserOutletManager;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Outlets
{

    public static function render()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_ch_outlets';
        // Define expected columns for the Excel upload
        $expected_columns = [
            'Outlet ID' => 'outlet_id',
            'Outlet Name' => 'outlet_name',
            'Outlet Description' => 'outlet_description',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle Excel Data Insertion
        if (isset($_POST['parse_excel'])) {
            $parsed_data = $excel_handler->handle_excel_parsing(); // Get parsed data

            if ($parsed_data && !is_wp_error($parsed_data)) {
                // Loop through the parsed data and insert each row into the database
                $error_list = [];
                foreach ($parsed_data as $row) {
                    $data = [
                        'outlet_id' => intval($row['outlet_id'] ?? null),
                        'outlet_name' => sanitize_text_field($row['outlet_name'] ?? null),
                        'outlet_description' => sanitize_text_field($row['outlet_description'] ?? null),
                    ];

                    // Validate each column and collect errors
                    $validation_errors = [];

                    // Check for errors in outlet_id
                    if (empty($data['outlet_id'])) {
                        $validation_errors[] = 'Outlet ID is required.';
                    } elseif (!is_numeric($data['outlet_id'])) {
                        $validation_errors[] = 'Outlet ID must be a number.';
                    }

                    // Check for errors in outlet_name
                    if (empty($data['outlet_name'])) {
                        $validation_errors[] = 'Outlet Name is required.';
                    }

                    // If there are validation errors, skip the row and add errors to the error list
                    if (!empty($validation_errors)) {
                        foreach ($validation_errors as $error) {
                            $error_list[] = "Row " . ($row['row_index'] ?? 'unknown') . ": " . $error; // Include the row number or a placeholder
                        }
                        continue; // Skip this row if there are validation errors
                    }

                    // If all fields are valid, proceed to check if the outlet_id already exists
                    $where = ['outlet_id' => $data['outlet_id']];
                    $result = CRUD::find_record($table_name, $where);

                    // Update or insert the record
                    if (is_wp_error($result) || empty($result) || $result === null) {
                        $result = CRUD::add_record($table_name, $data);
                    } else {
                        $result = CRUD::update_record($table_name, $data, $where);
                    }

                    // Check for errors during insertion
                    if (is_wp_error($result)) {
                        $error_list[] = "Row " . ($row['row_index'] ?? 'unknown') . ": " . $result->get_error_message();
                    }
                }

                // Display a toast notification for all the errors
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


        // Handle form actions (add, edit, delete, assign user to outlet)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_outlet']) || isset($_POST['edit_outlet']) || isset($_POST['assign_user']) || isset($_POST['assign_group'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'outlet_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Outlet
            if (isset($_POST['add_outlet'])) {

                $data = [
                    'outlet_name' => sanitize_text_field($_POST['outlet_name']),
                    'outlet_description' => sanitize_text_field($_POST['outlet_description']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Outlet added successfully.', 'bidfood'), 'success');
                }

                // Handle Edit outlet
            } elseif (isset($_POST['edit_outlet'])) {
                $data = [
                    'outlet_name' => sanitize_text_field($_POST['outlet_name']),
                    'outlet_description' => sanitize_text_field($_POST['outlet_description']),
                ];
                $where = ['outlet_id' => intval($_POST['outlet_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Outlet updated successfully.', 'bidfood'), 'success');
                }
            }
            // Check for nonce validation for delete action
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                if (!isset($_POST['_wpnonce_delete']) || !wp_verify_nonce($_POST['_wpnonce_delete'], 'delete_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }

                // Process the deletion
                $outlet_id = intval($_POST['entity_id']);
                if ($outlet_id) {
                    $where = ['outlet_id' => $outlet_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_ch_outlets', $where);

                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Outlet Deleted Successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where outlet_id is missing or invalid
                    wp_die(__('Invalid Outlet ID.', 'bidfood'));
                }
            }
        }

        // Fetch all WordPress users for user assignment select box
        $all_users = get_users(['fields' => ['ID', 'display_name']]);
        // Handle User Assignment to Outlet
        if (isset($_POST['assign_users'])) {
            $outlet_id = intval($_POST['outlet_id']);
            $assigned_user_ids = isset($_POST['assigned_user_ids']) ? array_filter(array_map('intval', $_POST['assigned_user_ids'])) : [];

            global $wpdb;

            if (empty($assigned_user_ids)) {
                // If no users are selected, remove all users from this outlet
                $result = $wpdb->delete(
                    "{$wpdb->prefix}neom_ch_outlet_users",
                    ['outlet_id' => $outlet_id],
                    ['%d']
                );

                if ($result === false) {
                    ToastHelper::add_toast_notice(__('Failed to remove users from outlet.', 'bidfood'), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('All users removed from outlet.', 'bidfood'), 'success');
                }
            } else {
                // Assign selected users to the outlet
                $result = UserOutletManager::assign_users_to_outlet($outlet_id, $assigned_user_ids);

                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Users updated for outlet successfully.', 'bidfood'), 'success');
                }
            }
        }



        // Fetch all groups for group assignment select box
        $all_groups = UserOutletManager::get_all_groups();
        // Handle Group Assignment to Outlet
        if (isset($_POST['assign_group'])) {
            $outlet_id = intval($_POST['outlet_group_id']);
            $group_id = isset($_POST['assigned_group_id']) && $_POST['assigned_group_id'] !== ""
                ? intval($_POST['assigned_group_id'])
                : null;

            // Assign group to outlet
            $result = UserOutletManager::assign_group_to_outlet($outlet_id, $group_id);
            if (is_wp_error($result)) {
                ToastHelper::add_toast_notice($result->get_error_message(), 'error', 3000);
            } else {
                ToastHelper::add_toast_notice(__('Group assigned to outlet successfully.', 'bidfood'), 'success');
            }
        }
        // Fetch existing outlets
        $results = CRUD::fetch_records($table_name);
?>
        <div class="wrap">

            <div>
                <h1 class="wp-heading-inline"><?php _e('Outlets', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New outlet modal -->
            <a href="#" class="button button-primary open-modal align-group-button" data-modal="add-outlet-modal"
                data-entity="outlet" data-action="add" style="margin-top: 10px; color: white;">
                <?php _e('Add New Outlet', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->

            <?php $excel_handler->render_upload_button($expected_columns); ?>
       


            <!-- Display Outlet List -->
            <h2><?php _e('Outlet List', 'bidfood'); ?></h2>

            <?php if ($results): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Outlet ID', 'bidfood'); ?></th>
                            <th><?php _e('Outlet Name', 'bidfood'); ?></th>
                            <th><?php _e('Outlet Description', 'bidfood'); ?></th>
                            <th><?php _e('Group Assigned', 'bidfood'); ?></th>
                            <th><?php _e('Assigned User', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->outlet_id) ?></td>
                                <td><?php echo esc_html($row->outlet_name); ?></td>
                                <?php if ($row->outlet_description) { ?>
                                    <td><?php echo esc_html($row->outlet_description); ?></td>
                                <?php } else { ?>
                                    <td><?php _e('N/A', 'bidfood'); ?></td>
                                <?php } ?>

                                <td>
                                    <!-- Select box to assign group to outlet -->
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('outlet_action', '_wpnonce'); ?>
                                        <input type="hidden" name="outlet_group_id" value="<?php echo esc_attr($row->outlet_id); ?>">
                                        <select name="assigned_group_id" class="group-select">
                                            <option value=""><?php _e('Select Group', 'bidfood'); ?></option>
                                            <?php foreach ($all_groups as $group): ?>
                                                <option value="<?php echo esc_attr($group->group_id); ?>" <?php echo ($row->group_id == $group->group_id) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($group->group_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_group" class="button"><?php _e('Assign Group', 'bidfood'); ?></button>
                                    </form>
                                </td>

                                <td>
                                    <!-- Select box to assign user to outlet -->
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('outlet_action', '_wpnonce'); ?>
                                        <input type="hidden" name="outlet_id" value="<?php echo esc_attr($row->outlet_id); ?>">
                                        <select name="assigned_user_ids[]" class="user-select2" multiple>
                                            <option value=""><?php _e('Select Users', 'bidfood'); ?></option>
                                            <?php foreach ($all_users as $user): ?>
                                                <option value="<?php echo esc_attr($user->ID); ?>"
                                                    <?php echo UserOutletManager::is_user_assigned_to_outlet($user->ID, $row->outlet_id) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($user->display_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_users" class="button"><?php _e('Assign Users', 'bidfood'); ?></button>
                                    </form>
                                </td>

                                <td>
                                    <!-- Edit outlet Button -->
                                    <a href="#" class="button open-modal" data-modal="edit-outlet-modal" data-entity="outlet"
                                        data-action="edit" data-field_outlet_id="<?php echo esc_attr($row->outlet_id); ?>"
                                        data-field_outlet_name="<?php echo esc_attr($row->outlet_name); ?>"
                                        data-field_outlet_description="<?php echo esc_attr($row->outlet_description); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-id="<?php echo esc_attr($row->outlet_id); ?>"
                                        data-entity="outlet">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No Outlets Found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

<?php
        // Define the fields for the Add outlet modal
        $add_fields = [
            ['name' => 'outlet_name', 'label' => 'Outlet Name', 'type' => 'text', 'required' => true],
            ['name' => 'outlet_description', 'label' => 'Outlet Description', 'type' => 'textarea'],
        ];

        // Define the fields for the Edit outlet modal (with readonly outlet_id)
        $edit_fields = [
            ['name' => 'outlet_id', 'label' => 'Outlet ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'outlet_name', 'label' => 'Outlet Name', 'type' => 'text', 'required' => true],
            ['name' => 'outlet_description', 'label' => 'Outlet Description', 'type' => 'textarea'],
        ];

        // Render the Add outlet modal
        ModalHelper::render_modal('add-outlet-modal', 'outlet', $add_fields, 'add');

        // Render the Edit outlet modal
        ModalHelper::render_modal('edit-outlet-modal', 'outlet', $edit_fields, 'edit');

        // Render the Delete outlet confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'outlet');
    }
}
