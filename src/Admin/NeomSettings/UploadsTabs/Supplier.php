<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\UserManagement\UserSupplierManager; // Include the UserSupplierManager class
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class Supplier
{

    public static function render()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_supplier';

        // Define expected columns for the Excel upload
        $expected_columns = [
            'Supplier ID' => 'supplier_id',
            'Supplier Name' => 'supplier_name',
            'Type' => 'type',
            'Lead Time (Days)' => 'lead_time_days',
            'Contact Name' => 'contact_name',
            'Contact Email' => 'contact_email',
            'Contact Number' => 'contact_number',
            'Secondary Contact Name' => 'contact_name_2',
            'Secondary Contact Email' => 'contact_email_2',
            'Secondary Contact Number' => 'contact_number_2',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete, assign user)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for actions
            if (isset($_POST['add_supplier']) || isset($_POST['edit_supplier']) || isset($_POST['assign_user'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'supplier_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Supplier
            if (isset($_POST['add_supplier'])) {
                $data = [
                    'supplier_id' => sanitize_text_field($_POST['supplier_id']),
                    'supplier_name' => sanitize_text_field($_POST['supplier_name']),
                    'type' => sanitize_text_field($_POST['type']),
                    'lead_time_days' => sanitize_text_field($_POST['lead_time_days']),
                    'contact_name' => sanitize_text_field($_POST['contact_name']),
                    'contact_email' => sanitize_email($_POST['contact_email']),
                    'contact_number' => sanitize_text_field($_POST['contact_number']),
                    'contact_name_2' => sanitize_text_field($_POST['contact_name_2']),
                    'contact_email_2' => sanitize_email($_POST['contact_email_2']),
                    'contact_number_2' => sanitize_text_field($_POST['contact_number_2']),
                ];
                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Supplier added successfully.', 'bidfood'), 'success');
                }

                // Handle Edit Supplier
            } elseif (isset($_POST['edit_supplier'])) {
                $data = [
                    'supplier_name' => sanitize_text_field($_POST['supplier_name']),
                    'type' => sanitize_text_field($_POST['type']),
                    'lead_time_days' => sanitize_text_field($_POST['lead_time_days']),
                    'contact_name' => sanitize_text_field($_POST['contact_name']),
                    'contact_email' => sanitize_email($_POST['contact_email']),
                    'contact_number' => sanitize_text_field($_POST['contact_number']),
                    'contact_name_2' => sanitize_text_field($_POST['contact_name_2']),
                    'contact_email_2' => sanitize_email($_POST['contact_email_2']),
                    'contact_number_2' => sanitize_text_field($_POST['contact_number_2']),
                ];
                $where = ['supplier_id' => sanitize_text_field($_POST['supplier_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Supplier updated successfully.', 'bidfood'), 'success');
                }
            } else if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                $relation_table = $wpdb->prefix . 'neom_user_supplier_relation';

                $supplier_id = sanitize_text_field($_POST['entity_id']);
                $where = ['supplier_id' => $supplier_id];
                $wpdb->delete($relation_table, ['supplier_id' => $supplier_id]);

                $result = CRUD::delete_record($table_name, $where);

                error_log(print_r($result, true));
                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Supplier deleted successfully.', 'bidfood'), 'success');
                }
            }

            // Handle User Assignment to Supplier
            if (isset($_POST['assign_user'])) {
                $supplier_id = sanitize_text_field($_POST['supplier_id']);
                $user_id = intval($_POST['assigned_user_id']);

                // Assign user to supplier
                $result = UserSupplierManager::assign_user_to_supplier($user_id, $supplier_id);
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('User assigned to supplier successfully.', 'bidfood'), 'success');
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
                            'supplier_id' => sanitize_text_field($row['supplier_id']),
                            'supplier_name' => sanitize_text_field($row['supplier_name'] ?? null),
                            'type' => sanitize_text_field($row['type'] ?? null),
                            'lead_time_days' => sanitize_text_field($row['lead_time_days'] ?? null),
                            'contact_name' => sanitize_text_field($row['contact_name'] ?? null),
                            'contact_email' => sanitize_email($row['contact_email'] ?? null),
                            'contact_number' => sanitize_text_field($row['contact_number'] ?? null),
                            'contact_name_2' => sanitize_text_field($row['contact_name_2'] ?? null),
                            'contact_email_2' => sanitize_email($row['contact_email_2'] ?? null),
                            'contact_number_2' => sanitize_text_field($row['contact_number_2'] ?? null),
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                $data[$key] = null;
                            }
                        }

                        // Check if the supplier_id already exists
                        $where = ['supplier_id' => $data['supplier_id']];
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
            if (isset($_POST['download_template'])) {
                // Generate the Excel template
                $template_name = 'supplier';
                $file_url = $excel_handler->generate_excel_template($expected_columns, $template_name);

                // Redirect to the file URL to trigger the download
                if ($file_url) {
                    wp_redirect($file_url);
                    exit;
                } else {
                    ToastHelper::add_toast_notice(__('Failed to generate Excel template.', 'bidfood'), 'error');
                }
            }
        }

        // Fetch existing suppliers
        $results = CRUD::fetch_records($table_name);
        $all_users = get_users(['fields' => ['ID', 'display_name']]);

?>
        <div class="wrap">

            <div>
                <h1 class="wp-heading-inline"><?php _e('Suppliers', 'bidfood'); ?></h1>
            </div>

            <!-- Button to trigger Add New Supplier modal -->
            <a href="#" class="button button-primary open-modal align-supplier-button"
                data-modal="add-supplier-modal"
                data-entity="supplier"
                data-action="add"
                style="margin-top: 10px; color: white;">
                <?php _e('Add New Supplier', 'bidfood'); ?>
            </a>
            <!-- Button to download Excel template -->
            <?php $excel_handler->render_download_template_button(); ?>

            <!-- Render the Excel upload button and modal for column mapping -->
            <?php $excel_handler->render_upload_button($expected_columns); ?>

            <!-- Display Suppliers in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Supplier List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Supplier ID', 'bidfood'); ?></th>
                            <th><?php _e('Supplier Name', 'bidfood'); ?></th>
                            <th><?php _e('Assigned User', 'bidfood'); ?></th>
                            <th><?php _e('Type', 'bidfood'); ?></th>
                            <th><?php _e('Lead Time (Days)', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row):
                            $assigned_user_id = UserSupplierManager::get_users_by_supplier($row->supplier_id);
                            $assigned_user_display_name = !is_wp_error($assigned_user_id) ? get_user_by('ID', $assigned_user_id[0])->display_name : __('No user assigned', 'bidfood');
                        ?>
                            <tr>
                                <td><?php echo esc_html($row->supplier_id); ?></td>
                                <td><?php echo esc_html($row->supplier_name); ?></td>
                                <td><?php echo esc_html($assigned_user_display_name); ?></td>
                                <td><?php echo esc_html($row->type); ?></td>
                                <td><?php echo esc_html($row->lead_time_days); ?></td>
                                <td>
                                    <!-- Form to assign user -->
                                    <form method="post">
                                        <?php wp_nonce_field('supplier_action', '_wpnonce'); ?>
                                        <input type="hidden" name="supplier_id" value="<?php echo esc_attr($row->supplier_id); ?>">
                                        <select name="assigned_user_id" class="user-select2">
                                            <option value=""><?php _e('Select User', 'bidfood'); ?></option>
                                            <?php foreach ($all_users as $user): ?>
                                                <option value="<?php echo esc_attr($user->ID); ?>"
                                                    <?php echo (!is_wp_error($assigned_user_id) && $assigned_user_id[0] == $user->ID) ? 'selected' : ''; ?>>
                                                    <?php echo esc_html($user->display_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_user" class="button"><?php _e('Assign', 'bidfood'); ?></button>
                                    </form>

                                    <!-- Edit Supplier Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-supplier-modal"
                                        data-entity="supplier"
                                        data-action="edit"
                                        data-field_supplier_id="<?php echo esc_attr($row->supplier_id); ?>"
                                        data-field_supplier_name="<?php echo esc_attr($row->supplier_name); ?>"
                                        data-field_type="<?php echo esc_attr($row->type); ?>"
                                        data-field_lead_time_days="<?php echo esc_attr($row->lead_time_days); ?>"
                                        data-field_contact_name="<?php echo esc_attr($row->contact_name); ?>"
                                        data-field_contact_email="<?php echo esc_attr($row->contact_email); ?>"
                                        data-field_contact_number="<?php echo esc_attr($row->contact_number); ?>"
                                        data-field_contact_name_2="<?php echo esc_attr($row->contact_name_2); ?>"
                                        data-field_contact_email_2="<?php echo esc_attr($row->contact_email_2); ?>"
                                        data-field_contact_number_2="<?php echo esc_attr($row->contact_number_2); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Supplier Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-id="<?php echo esc_attr($row->supplier_id); ?>"
                                        data-entity="supplier">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p><?php _e('No suppliers found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

<?php
        // Define the fields for the Add Supplier modal
        $add_fields = [
            ['name' => 'supplier_id', 'label' => 'Supplier ID', 'type' => 'text', 'required' => true],
            ['name' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text', 'required' => false],
            ['name' => 'type', 'label' => 'Type', 'type' => 'text', 'required' => false],
            ['name' => 'lead_time_days', 'label' => 'Lead Time (Days)', 'type' => 'number', 'required' => false],
            ['name' => 'contact_name', 'label' => 'Contact Name', 'type' => 'text', 'required' => false],
            ['name' => 'contact_email', 'label' => 'Contact Email', 'type' => 'email', 'required' => false],
            ['name' => 'contact_number', 'label' => 'Contact Number', 'type' => 'text', 'required' => false],
            ['name' => 'contact_name_2', 'label' => 'Secondary Contact Name', 'type' => 'text', 'required' => false],
            ['name' => 'contact_email_2', 'label' => 'Secondary Contact Email', 'type' => 'email', 'required' => false],
            ['name' => 'contact_number_2', 'label' => 'Secondary Contact Number', 'type' => 'text', 'required' => false],
        ];

        // Define the fields for the Edit Supplier modal (with readonly supplier_id)
        $edit_fields = [
            ['name' => 'supplier_id', 'label' => 'Supplier ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'supplier_name', 'label' => 'Supplier Name', 'type' => 'text', 'required' => false],
            ['name' => 'type', 'label' => 'Type', 'type' => 'text', 'required' => false],
            ['name' => 'lead_time_days', 'label' => 'Lead Time (Days)', 'type' => 'number', 'required' => false],
            ['name' => 'contact_name', 'label' => 'Contact Name', 'type' => 'text', 'required' => false],
            ['name' => 'contact_email', 'label' => 'Contact Email', 'type' => 'email', 'required' => false],
            ['name' => 'contact_number', 'label' => 'Contact Number', 'type' => 'text', 'required' => false],
            ['name' => 'contact_name_2', 'label' => 'Secondary Contact Name', 'type' => 'text', 'required' => false],
            ['name' => 'contact_email_2', 'label' => 'Secondary Contact Email', 'type' => 'email', 'required' => false],
            ['name' => 'contact_number_2', 'label' => 'Secondary Contact Number', 'type' => 'text', 'required' => false],
        ];

        // Render the Add Supplier modal
        ModalHelper::render_modal('add-supplier-modal', 'supplier', $add_fields, 'add');

        // Render the Edit Supplier modal
        ModalHelper::render_modal('edit-supplier-modal', 'supplier', $edit_fields, 'edit');

        // Render the Delete Supplier confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'supplier');
    }
}
