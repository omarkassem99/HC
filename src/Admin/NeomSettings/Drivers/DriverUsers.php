<?php

namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Core\UserManagement\UserDriverManager;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class DriverUsers
{
    public function __construct()
    {
        // Enqueue styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    public static function init()
    {
        return new self;
    }
      // Enqueue styles and scripts
      public function enqueue_assets()
      {
          wp_enqueue_style('admin-driver-users-css', plugins_url('/assets/css/driverRequests/driver-users.css', dirname(__FILE__, 4)));
          wp_enqueue_script('admin-driver-users-js', plugins_url('/assets/js/driverRequests/driver-users.js', dirname(__FILE__, 4)), ['jquery'], null, true);
  
          // Localize script with nonce and AJAX URL
          wp_localize_script('admin-driver-users-js', 'driverUserData', [
              'ajax_url' => admin_url('admin-ajax.php'),
              'nonce' => wp_create_nonce('driver-users_nonce'),
          ]);
      }

    public static function render()
    {
        self::handle_post_requests();
        self::render_ui();
    }

    /**
     * Handles various POST actions for drivers.
     */
    private static function handle_post_requests()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Add driver
            if (isset($_POST['add_driver'])) {
                self::handle_add_driver();
            }

            // Edit driver
            if (isset($_POST['edit_driver'])) {
                self::handle_edit_driver();
            }

            // Delete driver
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                self::handle_delete_driver();
            }
        }
    }

    /**
     * Handle Add Driver action.
     */
    private static function handle_add_driver()
    {
        check_admin_referer('driver_action');

        // Sanitize and validate inputs
        $driver_data = [
            'email' => sanitize_email($_POST['add_email']),
            'password' => sanitize_text_field($_POST['add_password'])
        ];

        $driver_info_data = [
            'first_name' => sanitize_text_field($_POST['add_first_name']),
            'last_name' => sanitize_text_field($_POST['add_last_name']),
            'phone' => sanitize_text_field($_POST['add_phone']),
            'address' => sanitize_text_field($_POST['add_address']),
            'driving_license_number' => sanitize_text_field($_POST['add_driving_license_number']),
            'driving_license_expiry_date' => sanitize_text_field($_POST['add_driving_license_expiry_date']),
            'vehicle_number' => sanitize_text_field($_POST['add_vehicle_number'])
        ];

        // Add driver via UserDriverManager
        $driver_id = UserDriverManager::addDriver($driver_data, $driver_info_data);

        // Handle errors or success
        if (is_wp_error($driver_id)) {
            if ($driver_id->get_error_code() == 'email_exists') {
                ToastHelper::add_toast_notice(__('This email is already in use by another driver.', 'bidfood'), 'error');
            } elseif ($driver_id->get_error_code() == 'phone_exists') {
                ToastHelper::add_toast_notice(__('This phone number is already in use by another driver.', 'bidfood'), 'error');
            } else {
                ToastHelper::add_toast_notice($driver_id->get_error_message(), 'error');
            }
        } else {
            ToastHelper::add_toast_notice(__('Driver added successfully.', 'bidfood'), 'success');
        }

        wp_safe_redirect(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers'));
        exit;
    }

    /**
     * Handle Edit Driver action.
     */
    private static function handle_edit_driver()
    {
        check_admin_referer('driver_action', '_wpnonce_edit');
    
        $driver_id = intval($_POST['id']);
    
        // Sanitize and validate inputs
        $driver_data = [
            'email' => sanitize_email($_POST['edit_email']),
        ];
    
        // Handle password validation
        $password = sanitize_text_field($_POST['edit_password']);
        if (!empty($password) && strlen(trim($password)) >= 8) {
            $driver_data['password'] = wp_hash_password($password);
        } elseif (!empty($password) && strlen(trim($password)) < 8) {
            ToastHelper::add_toast_notice(__('Password must be at least 8 characters long.', 'bidfood'), 'error');
            wp_safe_redirect(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers'));
            exit;
        }
    
        $driver_info_data = [
            'first_name' => sanitize_text_field($_POST['edit_first_name']),
            'last_name' => sanitize_text_field($_POST['edit_last_name']),
            'phone' => sanitize_text_field($_POST['edit_phone']),
            'address' => sanitize_text_field($_POST['edit_address']),
            'driving_license_number' => sanitize_text_field($_POST['edit_driving_license_number']),
            'driving_license_expiry_date' => sanitize_text_field($_POST['edit_driving_license_expiry_date']),
            'vehicle_number' => sanitize_text_field($_POST['edit_vehicle_number']),
            'status' => sanitize_text_field($_POST['status'])
        ];
    
        // Update driver via UserDriverManager
        $result = UserDriverManager::updateDriver($driver_id, $driver_data, $driver_info_data);
    
        // Handle errors or success
        if (is_wp_error($result)) {
            if ($result->get_error_code() == 'email_exists') {
                ToastHelper::add_toast_notice(__('This email is already in use by another driver.', 'bidfood'), 'error');
            } elseif ($result->get_error_code() == 'phone_exists') {
                ToastHelper::add_toast_notice(__('This phone number is already in use by another driver.', 'bidfood'), 'error');
            } else {
                ToastHelper::add_toast_notice($result->get_error_message(), 'error');
            }
        } else {
            ToastHelper::add_toast_notice(__('Driver updated successfully.', 'bidfood'), 'success');
        }
    
        wp_safe_redirect(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers'));
        exit;
    }

    /**
     * Handle Delete Driver action.
     */
    private static function handle_delete_driver()
    {
        check_admin_referer('delete_action', '_wpnonce_delete');

        $driver_id = intval($_POST['entity_id']);
        $result = UserDriverManager::deleteDriver($driver_id);

        // Handle errors or success
        if (is_wp_error($result)) {
            ToastHelper::add_toast_notice($result->get_error_message(), 'error');
        } else {
            ToastHelper::add_toast_notice(__('Driver deleted successfully.', 'bidfood'), 'success');
        }

        wp_safe_redirect(admin_url('admin.php?page=bidfood-neom-settings&tab=drivers'));
        exit;
    }

    /**
     * Render the Driver Users UI.
     */
    private static function render_ui()
    {
        $drivers = UserDriverManager::getDrivers();

?>
        <div class="wrap">
            <h1><?php _e('All BF Drivers', 'bidfood'); ?></h1>

            <!-- Button to trigger Add New Driver modal -->
            <a href="#" class="button button-primary open-modal"
                data-modal="add-driver-modal"
                data-entity="driver"
                data-action="add"
                style="margin-bottom: 10px;">
                <?php _e('Add New Driver', 'bidfood'); ?>
            </a>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Driver ID', 'bidfood'); ?></th>
                        <th><?php _e('Name', 'bidfood'); ?></th>
                        <th><?php _e('Email', 'bidfood'); ?></th>
                        <th><?php _e('Phone', 'bidfood'); ?></th>
                        <th><?php _e('Status', 'bidfood'); ?></th>
                        <th><?php _e('Actions', 'bidfood'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($drivers)): ?>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?php echo esc_html($driver['id']); ?></td>
                                <td><?php echo esc_html($driver['first_name'] . ' ' . $driver['last_name']); ?></td>
                                <td><?php echo esc_html($driver['email']); ?></td>
                                <td><?php echo esc_html($driver['phone']); ?></td>
                                <td><?php echo esc_html($driver['status']); ?></td>
                                <td>
                                    <!-- Edit button -->
                                    <a href="#" class="button open-modal" data-modal="edit-driver-modal"
                                        data-entity="driver"
                                        data-action="edit"
                                        data-field_id="<?php echo esc_attr($driver['id']); ?>"
                                        data-field_edit_email="<?php echo esc_attr($driver['email']); ?>"
                                        data-field_edit_first_name="<?php echo esc_attr($driver['first_name']); ?>"
                                        data-field_edit_last_name="<?php echo esc_attr($driver['last_name']); ?>"
                                        data-field_edit_phone="<?php echo esc_attr($driver['phone']); ?>"
                                        data-field_edit_address="<?php echo esc_attr($driver['address']); ?>"
                                        data-field_edit_driving_license_number="<?php echo esc_attr($driver['driving_license_number']); ?>"
                                        data-field_edit_driving_license_expiry_date="<?php echo esc_attr($driver['driving_license_expiry_date']); ?>"
                                        data-field_edit_vehicle_number="<?php echo esc_attr($driver['vehicle_number']); ?>"
                                        data-field_status="<?php echo esc_attr($driver['status']); ?>"
                                        >
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>
                                    <!-- Delete button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-entity="driver"
                                        data-id="<?php echo esc_attr($driver['id']); ?>">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6"><?php _e('No drivers found.', 'bidfood'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
<?php

        // Render Modals
        $add_fields = [
            ['name' => 'add_email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
            ['name' => 'add_password', 'label' => 'Password', 'type' => 'password', 'required' => true],
            ['name' => 'add_first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
            ['name' => 'add_last_name', 'label' => 'Last Name', 'type' => 'text', 'required' => true],
            ['name' => 'add_phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'add_address', 'label' => 'Address', 'type' => 'text'],
            ['name' => 'add_driving_license_number', 'label' => 'License Number', 'type' => 'text'],
            ['name' => 'add_driving_license_expiry_date', 'label' => 'License Expiry Date', 'type' => 'date'],
            ['name' => 'add_vehicle_number', 'label' => 'Vehicle Number', 'type' => 'text']
        ];

        $edit_fields = [
            ['name' => 'id', 'label' => 'Driver ID', 'type' => 'number', 'readonly' => true],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Available for Delivery' => 'Available for Delivery', 'On Delivery' => 'On Delivery']],
            ['name' => 'edit_email', 'label' => 'E-mail', 'type' => 'email', 'required' => true],
            ['name' => 'edit_first_name', 'label' => 'First Name', 'type' => 'text', 'required' => true],
            ['name' => 'edit_last_name', 'label' => 'Last Name', 'type' => 'text', 'required' => true],
            ['name' => 'edit_phone', 'label' => 'Phone', 'type' => 'text'],
            ['name' => 'edit_address', 'label' => 'Address', 'type' => 'text'],
            ['name' => 'edit_driving_license_number', 'label' => 'License Number', 'type' => 'text'],
            ['name' => 'edit_driving_license_expiry_date', 'label' => 'License Expiry Date', 'type' => 'date'],
            ['name' => 'edit_vehicle_number', 'label' => 'Vehicle Number', 'type' => 'text'],
            ['name' => 'edit_password', 'label' => 'Password (leave blank to keep it unchanged)', 'type' => 'password', 'required' => false, 'help_text' => '<span class="password-notice"></span>'],

        ];

        ModalHelper::render_modal('add-driver-modal', 'driver', $add_fields, 'add');
        ModalHelper::render_modal('edit-driver-modal', 'driver', $edit_fields, 'edit', '_wpnonce_edit');
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'driver');
    }
}
