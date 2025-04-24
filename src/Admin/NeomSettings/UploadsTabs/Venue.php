<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\UserManagement\UserVenueManager;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Venue {

    public static function render() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_venue';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Venue ID' => 'venue_id',
            'Venue Name' => 'venue_name',
            'Operator ID' => 'operator_id'
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete, assign user to venue)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_venue']) || isset($_POST['edit_venue']) || isset($_POST['assign_user'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'venue_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Venue
            if (isset($_POST['add_venue'])) {
                $data = [
                    'venue_id' => sanitize_text_field($_POST['venue_id']),
                    'venue_name' => sanitize_text_field($_POST['venue_name']),
                    'operator_id' => sanitize_text_field($_POST['operator_id']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Venue added successfully.', 'bidfood'), 'success');
                }

            // Handle Edit Venue
            } elseif (isset($_POST['edit_venue'])) {
                $data = [
                    'venue_name' => sanitize_text_field($_POST['venue_name']),
                    'operator_id' => sanitize_text_field($_POST['operator_id']),
                ];
                $where = ['venue_id' => sanitize_text_field($_POST['venue_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Venue updated successfully.', 'bidfood'), 'success');
                }
            }

            // Handle User Assignment to Venue
            if (isset($_POST['assign_user'])) {
                $venue_id = sanitize_text_field($_POST['venue_id']);
                $user_id = intval($_POST['assigned_user_id']);

                // Assign user to venue
                $result = UserVenueManager::assign_user_to_venue($user_id, $venue_id);
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('User assigned to venue successfully.', 'bidfood'), 'success');
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
                            'venue_id' => sanitize_text_field($row['venue_id']),
                            'venue_name' => sanitize_text_field($row['venue_name'] ?? null),
                            'operator_id' => sanitize_text_field($row['operator_id'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the venue_id already exists
                        $where = ['venue_id' => $data['venue_id']];
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
                $venue_id = sanitize_text_field($_POST['entity_id']);
                if ($venue_id) {
                    $where = ['venue_id' => $venue_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_venue', $where);
                    
                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Venue deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where venue_id is missing or invalid
                    wp_die(__('Invalid venue ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing venues
        $results = CRUD::fetch_records($table_name);

        // Fetch all WordPress users for user assignment select box
        $all_users = get_users(['fields' => ['ID', 'display_name']]);

        // Include Select2 CSS and JS
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js', ['jquery'], null, true);

        // Initialize Select2 for the user select box
        wp_add_inline_script('select2-js', 'jQuery(document).ready(function($){ $(".user-select").select2(); });');
        ?>
        <div class="wrap">

            <div>
            <h1 class="wp-heading-inline"><?php _e('Venues', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Venue modal -->
            <a href="#" class="button button-primary open-modal align-venue-button"
               data-modal="add-venue-modal"
               data-entity="venue"
               data-action="add"
               style="margin-top: 10px; color: white;">
               <?php _e('Add New Venue', 'bidfood'); ?>
            </a>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display Venues in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Venue List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Venue ID', 'bidfood'); ?></th>
                            <th><?php _e('Venue Name', 'bidfood'); ?></th>
                            <th><?php _e('Operator ID', 'bidfood'); ?></th>
                            <th><?php _e('Assigned User', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->venue_id); ?></td>
                                <td><?php echo esc_html($row->venue_name); ?></td>
                                <td><?php echo esc_html($row->operator_id); ?></td>
                                <td>
                                    <!-- Select box to assign user to venue -->
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field('venue_action', '_wpnonce'); ?>
                                        <input type="hidden" name="venue_id" value="<?php echo esc_attr($row->venue_id); ?>">
                                        <select name="assigned_user_id" class="user-select">
                                            <option value=""><?php _e('Select User', 'bidfood'); ?></option>
                                            <?php foreach ($all_users as $user): ?>
                                                <option value="<?php echo esc_attr($user->ID); ?>" 
                                                    <?php echo UserVenueManager::is_user_venue($user->ID) && UserVenueManager::get_venue_by_user($user->ID) === $row->venue_id ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($user->display_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_user" class="button"><?php _e('Assign', 'bidfood'); ?></button>
                                    </form>
                                </td>
                                <td>
                                    <!-- Edit Venue Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                       data-modal="confirmation-modal"
                                       data-id="<?php echo esc_attr($row->venue_id); ?>"
                                       data-entity="venue">
                                       <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No venues found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Venue modal
        $add_fields = [
            ['name' => 'venue_id', 'label' => 'Venue ID', 'type' => 'text', 'required' => true],
            ['name' => 'venue_name', 'label' => 'Venue Name', 'type' => 'text', 'required' => true],
            ['name' => 'operator_id', 'label' => 'Operator ID', 'type' => 'text', 'required' => true],
        ];

        // Define the fields for the Edit Venue modal (with readonly venue_id)
        $edit_fields = [
            ['name' => 'venue_id', 'label' => 'Venue ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'venue_name', 'label' => 'Venue Name', 'type' => 'text', 'required' => true],
            ['name' => 'operator_id', 'label' => 'Operator ID', 'type' => 'text', 'required' => true],
        ];

        // Render the Add Venue modal
        ModalHelper::render_modal('add-venue-modal', 'venue', $add_fields, 'add');
        
        // Render the Edit Venue modal
        ModalHelper::render_modal('edit-venue-modal', 'venue', $edit_fields, 'edit');
        
        // Render the Delete Venue confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'venue');
    }
}
