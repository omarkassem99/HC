<?php

namespace Bidfood\Admin\NeomSettings\UploadsTabs;

use Bidfood\Core\Database\CRUD;
use Bidfood\Core\FileUploader\ExcelFileHandler;
use Bidfood\Core\WooCommerce\Product\ProductFilter;
use Bidfood\UI\Modal\ModalHelper;
use Bidfood\UI\Toast\ToastHelper;

class OrderingItemMaster
{
    const ITEMS_PER_PAGE = 20;
    public function __construct()
    {
        add_action('wp_ajax_process_conversion_batch', [__CLASS__, 'handle_ajax_conversion']);
    }
    public static function init()
    {
        return new self();
    }

    public static function fetch_records($table_name, $search = '', $limit = 100, $offset = 0)
    {
        global $wpdb;

        $base_query = "SELECT * FROM $table_name";
        $params = array();

        if (!empty($search)) {
            // Use proper column names for OrderingItemMaster
            $base_query .= " WHERE item_id LIKE %s OR description LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params = array($search_term, $search_term);
        }

        // Add pagination to base query
        $base_query .= " LIMIT %d OFFSET %d";
        array_push($params, $limit, $offset);

        // Prepare and execute the query
        $prepared_query = $wpdb->prepare($base_query, $params);
        return $wpdb->get_results($prepared_query);
    }
    // count all records
    public static function count_records($table_name, $search = '')
    {
        global $wpdb;

        $base_query = "SELECT COUNT(*) FROM $table_name";

        if (!empty($search)) {
            $base_query .= " WHERE item_id LIKE %s OR description LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $sql = $wpdb->prepare($base_query, $search_term, $search_term);
        } else {
            $sql = $base_query;
        }
        
        return $wpdb->get_var($sql);
    }
    public static function render()
    {
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        global $wpdb;

        $table_name = $wpdb->prefix . 'neom_ordering_item_master';
        // Handle search
        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Pagination
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Fetch paginated results
        $results = self::fetch_records(
            $table_name,
            $search_term,
            self::ITEMS_PER_PAGE,
            ($current_page - 1) * self::ITEMS_PER_PAGE
        );
        $total_items = self::count_records($table_name, $search_term);
        $total_pages = ceil($total_items / self::ITEMS_PER_PAGE);
        // Define expected columns for the Excel upload
        $expected_columns = [
            'Item ID' => 'item_id',
            'Item Parent ID' => 'item_parent_id',
            'Venue ID' => 'venue_id',
            'Description' => 'description',
            'Category ID' => 'category_id',
            'SubCategory ID' => 'sub_category_id',
            'Temperature ID' => 'temperature_id',
            'MOQ' => 'moq',
            'UOM ID' => 'uom_id',
            'Preferred Brand' => 'preferred_brand',
            'Alternative Brand' => 'alternative_brand',
            'Preferred Supplier ID' => 'preferred_supplier_id',
            'Alternative Supplier ID' => 'alternative_supplier_id',
            'barcode' => 'barcode',
            'supplier_code' => 'supplier_code',
            'additional_info' => 'additional_info',
            'packaging_per_uom' => 'packaging_per_uom',
            'country' => 'country',
            'status' => 'status',
        ];

        // Instantiate Excel file handler
        $excel_handler = new ExcelFileHandler();

        // Handle form actions (add, edit, delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify nonce for add/edit actions
            if (isset($_POST['add_ordering_item_master']) || isset($_POST['edit_ordering_item_master'])) {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ordering_item_master_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }
            }

            // Handle Add Ordering Item Master
            if (isset($_POST['add_ordering_item_master'])) {
                $data = [
                    'item_id' => sanitize_text_field($_POST['item_id']),
                    'item_parent_id' => sanitize_text_field($_POST['item_parent_id']),
                    'venue_id' => sanitize_text_field($_POST['venue_id']),
                    'description' => sanitize_text_field($_POST['description']),
                    'category_id' => sanitize_text_field($_POST['category_id']),
                    'sub_category_id' => sanitize_text_field($_POST['sub_category_id']),
                    'temperature_id' => sanitize_text_field($_POST['temperature_id']),
                    'moq' => sanitize_text_field($_POST['moq']),
                    'uom_id' => sanitize_text_field($_POST['uom_id']),
                    'preferred_brand' => sanitize_text_field($_POST['preferred_brand']),
                    'alternative_brand' => sanitize_text_field($_POST['alternative_brand']),
                    'preferred_supplier_id' => sanitize_text_field($_POST['preferred_supplier_id']),
                    'alternative_supplier_id' => sanitize_text_field($_POST['alternative_supplier_id']),
                    'barcode' => sanitize_text_field($_POST['barcode']),
                    'supplier_code' => sanitize_text_field($_POST['supplier_code']),
                    'additional_info' => sanitize_text_field($_POST['additional_info']),
                    'packaging_per_uom' => sanitize_text_field($_POST['packaging_per_uom']),
                    'country' => sanitize_text_field($_POST['country']),
                    'status' => sanitize_text_field($_POST['status']),
                ];

                $result = CRUD::add_record($table_name, $data);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Ordering Item Master added successfully.', 'bidfood'), 'success');
                }

                // Handle Edit Ordering Item Master
            } elseif (isset($_POST['edit_ordering_item_master'])) {
                $data = [
                    'item_parent_id' => sanitize_text_field($_POST['item_parent_id']),
                    'venue_id' => sanitize_text_field($_POST['venue_id']),
                    'description' => sanitize_text_field($_POST['description']),
                    'category_id' => sanitize_text_field($_POST['category_id']),
                    'sub_category_id' => sanitize_text_field($_POST['sub_category_id']),
                    'temperature_id' => sanitize_text_field($_POST['temperature_id']),
                    'moq' => sanitize_text_field($_POST['moq']),
                    'uom_id' => sanitize_text_field($_POST['uom_id']),
                    'preferred_brand' => sanitize_text_field($_POST['preferred_brand']),
                    'alternative_brand' => sanitize_text_field($_POST['alternative_brand']),
                    'preferred_supplier_id' => sanitize_text_field($_POST['preferred_supplier_id']),
                    'alternative_supplier_id' => sanitize_text_field($_POST['alternative_supplier_id']),
                    'barcode' => sanitize_text_field($_POST['barcode']),
                    'supplier_code' => sanitize_text_field($_POST['supplier_code']),
                    'additional_info' => sanitize_text_field($_POST['additional_info']),
                    'packaging_per_uom' => sanitize_text_field($_POST['packaging_per_uom']),
                    'country' => sanitize_text_field($_POST['country']),
                    'status' => sanitize_text_field($_POST['status']),
                ];

                $where = ['item_id' => sanitize_text_field($_POST['item_id'])];

                $result = CRUD::update_record($table_name, $data, $where);

                // Display a toast notification
                if (is_wp_error($result)) {
                    ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                } else {
                    ToastHelper::add_toast_notice(__('Ordering Item Master updated successfully.', 'bidfood'), 'success');
                }
            }

            // Handle Excel Data Insertion
            if (isset($_POST['parse_excel'])) {
                $parsed_data = $excel_handler->handle_excel_parsing(); // Get parsed data

                $is_duplicates = false;
                if ($parsed_data && !is_wp_error($parsed_data)) {
                    // check for duplicates
                    $is_duplicates = self::check_for_duplicates_in_excel($parsed_data);

                    // Loop through the parsed data and insert each row into the database
                    $error_list = [];
                    foreach ($parsed_data as $row) {
                        $data = [
                            'item_id' => isset($row['item_id']) && !empty($row['item_id']) ? sanitize_text_field($row['item_id']) : null,
                            'item_parent_id' => isset($row['item_parent_id']) && !empty($row['item_parent_id']) ? sanitize_text_field($row['item_parent_id']) : null,
                            'venue_id' => isset($row['venue_id']) && !empty($row['venue_id']) ? sanitize_text_field($row['venue_id']) : null,
                            'description' => isset($row['description']) && !empty($row['description']) ? sanitize_text_field($row['description']) : null,
                            'category_id' => isset($row['category_id']) && !empty($row['category_id']) ? sanitize_text_field($row['category_id']) : null,
                            'sub_category_id' => isset($row['sub_category_id']) && !empty($row['sub_category_id']) ? sanitize_text_field($row['sub_category_id']) : null,
                            'temperature_id' => isset($row['temperature_id']) && !empty($row['temperature_id']) ? sanitize_text_field($row['temperature_id']) : null,
                            'moq' => isset($row['moq']) && !empty($row['moq']) ? sanitize_text_field($row['moq']) : null,
                            'uom_id' => isset($row['uom_id']) && !empty($row['uom_id']) ? sanitize_text_field($row['uom_id']) : null,
                            'preferred_brand' => isset($row['preferred_brand']) && !empty($row['preferred_brand']) ? sanitize_text_field($row['preferred_brand']) : null,
                            'alternative_brand' => isset($row['alternative_brand']) && !empty($row['alternative_brand']) ? sanitize_text_field($row['alternative_brand']) : null,
                            'preferred_supplier_id' => isset($row['preferred_supplier_id']) && !empty($row['preferred_supplier_id']) ? sanitize_text_field($row['preferred_supplier_id']) : null,
                            'alternative_supplier_id' => isset($row['alternative_supplier_id']) && !empty($row['alternative_supplier_id']) ? sanitize_text_field($row['alternative_supplier_id']) : null,
                            'barcode' => isset($row['barcode']) && !empty($row['barcode']) ? sanitize_text_field($row['barcode']) : null,
                            'supplier_code' => isset($row['supplier_code']) && !empty($row['supplier_code']) ? sanitize_text_field($row['supplier_code']) : null,
                            'additional_info' => isset($row['additional_info']) && !empty($row['additional_info']) ? sanitize_text_field($row['additional_info']) : '',
                            'packaging_per_uom' => isset($row['packaging_per_uom']) && !empty($row['packaging_per_uom']) ? sanitize_text_field($row['packaging_per_uom']) : null,
                            'country' => isset($row['country']) && !empty($row['country']) ? sanitize_text_field($row['country']) : null,
                            'status' => isset($row['status']) && !empty($row['status']) ? sanitize_text_field($row['status']) : 'active',
                        ];

                        // If any of the data is empty change it to null
                        foreach ($data as $key => $value) {
                            if (empty($value)) {
                                error_log('Empty value found');
                                $data[$key] = null;
                            }
                        }

                        // Check if the item_id already exists
                        $where = ['item_id' => $data['item_id']];
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

                // Redirect to the same page to trigger the notice display
                wp_safe_redirect($_SERVER['REQUEST_URI']);
                exit;
            }

            // Check for nonce validation for delete action
            if (isset($_POST['action_type']) && $_POST['action_type'] === 'delete') {
                if (!isset($_POST['_wpnonce_delete']) || !wp_verify_nonce($_POST['_wpnonce_delete'], 'delete_action')) {
                    wp_die(__('Security check failed.', 'bidfood'));
                }

                // Process the deletion
                $item_id = sanitize_text_field($_POST['entity_id']);
                if ($item_id) {
                    $where = ['item_id' => $item_id];
                    $result = CRUD::delete_record($wpdb->prefix . 'neom_ordering_item_master', $where);

                    // Display a toast notification
                    if (is_wp_error($result)) {
                        ToastHelper::add_toast_notice($result->get_error_message(), 'error', 0);
                    } else {
                        ToastHelper::add_toast_notice(__('Ordering Item Master deleted successfully.', 'bidfood'), 'success');
                    }
                } else {
                    // Handle case where item_id is missing or invalid
                    wp_die(__('Invalid Item ID.', 'bidfood'));
                }
            }
        }

        // Fetch existing Ordering Items
        $results = self::fetch_records(
            $table_name,
            $search_term,
            self::ITEMS_PER_PAGE,
            ($current_page - 1) * self::ITEMS_PER_PAGE
        );

?>
        <div class="wrap">
            <div>
                <h1 class="wp-heading-inline"><?php _e('Ordering Item Masters', 'bidfood'); ?></h1>
            </div>

            <!-- Conversion Progress Modal -->
            <div id="conversion-progress-modal" style="display:none; padding:20px;">
                <h3><?php _e('Converting Items to Products', 'bidfood'); ?></h3>
                <div class="progress-bar" style="background:#f1f1f1; height:30px; border-radius:3px;">
                    <div class="progress" style="background:#0073aa; height:100%; width:0%; transition:width 0.3s;"></div>
                </div>
                <p class="progress-text" style="text-align:center; margin:10px 0;">0% Complete</p>
            </div>
            <div style="
                        display: flex; 
                        flex-wrap: wrap; 
                        justify-content: space-between; 
                        gap: 15px;
                        align-items: center;
                    ">
                <div style="
                            display: flex; 
                            align-items: center;
                        ">
                    <!-- Add New Ordering Item Master Button -->
                    <div style="
                                height: 40px; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center;
                                text-align: center;
                                min-width: 150px;
                            ">
                        <a href="#" class="button button-primary open-modal align-ordering-item-master-button"
                            data-modal="add-ordering-item-master-modal"
                            data-entity="ordering_item_master"
                            data-action="add">
                            <?php _e('Add New Ordering Item Master', 'bidfood'); ?>
                        </a>
                    </div>

                    <!-- Render the Excel upload button and modal for column mapping -->
                    <div style="
                                height: 40px; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center;
                                text-align: center;
                                min-width: 150px;
                                margin-bottom: 8px;
                            ">
                        <?php $excel_handler->render_upload_button($expected_columns); ?>
                    </div>
                    <!-- Convert Button with Progress -->

                    <div style="
                                height: 40px; 
                                display: flex; 
                                align-items: sta; 
                                justify-content: center;
                                min-width: 150px;
                                margin-bottom: 16px;
                                ">
                        <form id="convert-items-form" method="post" style="display: flex; align-items: center;">
                            <?php wp_nonce_field('convert_items_to_products_action', 'convert_items_to_products_nonce'); ?>
                            <button type="button" id="start-conversion" class="button"
                                onclick="startConversionProcess()">
                                <?php _e('Convert Items to WooCommerce Products', 'bidfood'); ?>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Search Form -->
                <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>">
                    <input type="hidden" name="tab" value="uploads">
                    <input type="hidden" name="uploads_tab" value="ordering_item_master">
                    <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>"
                        placeholder="<?php _e('Search items...', 'bidfood'); ?>"
                        style="
                                height: 40px; 
                                padding: 0 10px; 
                                border: 1px solid #ccc;
                                min-width: 200px;
                            ">
                    <input type="submit" class="button" value="<?php _e('Search', 'bidfood'); ?>"
                        style="
                                height: 40px; 
                                padding: 0 15px; 
                                display: flex; 
                                align-items: center; 
                                justify-content: center;
                                min-width: 100px;
                            ">
                </form>
            </div>


            <!-- Display Ordering Item Masters in a Table -->
            <?php if (!empty($results)): ?>
                <h2><?php _e('Ordering Item Master List', 'bidfood'); ?></h2>
                <table class="wp-list-table widefat fixed striped table-view-list">
                    <thead>
                        <tr>
                            <th><?php _e('Item ID', 'bidfood'); ?></th>
                            <th><?php _e('Item Parent ID', 'bidfood'); ?></th>
                            <th><?php _e('Venue ID', 'bidfood'); ?></th>
                            <th><?php _e('Description', 'bidfood'); ?></th>
                            <th><?php _e('Actions', 'bidfood'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo esc_html($row->item_id); ?></td>
                                <td><?php echo esc_html($row->item_parent_id); ?></td>
                                <td><?php echo esc_html($row->venue_id); ?></td>
                                <td><?php echo esc_html($row->description); ?></td>
                                <td>
                                    <!-- Edit Ordering Item Master Button -->
                                    <a href="#" class="button open-modal"
                                        data-modal="edit-ordering-item-master-modal"
                                        data-entity="ordering_item_master"
                                        data-action="edit"
                                        data-field_item_id="<?php echo esc_attr($row->item_id); ?>"
                                        data-field_item_parent_id="<?php echo esc_attr($row->item_parent_id); ?>"
                                        data-field_venue_id="<?php echo esc_attr($row->venue_id); ?>"
                                        data-field_description="<?php echo esc_attr($row->description); ?>"
                                        data-field_category_id="<?php echo esc_attr($row->category_id); ?>"
                                        data-field_sub_category_id="<?php echo esc_attr($row->sub_category_id); ?>"
                                        data-field_temperature_id="<?php echo esc_attr($row->temperature_id); ?>"
                                        data-field_moq="<?php echo esc_attr($row->moq); ?>"
                                        data-field_uom_id="<?php echo esc_attr($row->uom_id); ?>"
                                        data-field_preferred_brand="<?php echo esc_attr($row->preferred_brand); ?>"
                                        data-field_alternative_brand="<?php echo esc_attr($row->alternative_brand); ?>"
                                        data-field_preferred_supplier_id="<?php echo esc_attr($row->preferred_supplier_id); ?>"
                                        data-field_alternative_supplier_id="<?php echo esc_attr($row->alternative_supplier_id); ?>"
                                        data-field_barcode="<?php echo esc_attr($row->barcode); ?>"
                                        data-field_supplier_code="<?php echo esc_attr($row->supplier_code); ?>"
                                        data-field_additional_info="<?php echo esc_attr($row->additional_info); ?>"
                                        data-field_packaging_per_uom="<?php echo esc_attr($row->packaging_per_uom); ?>"
                                        data-field_country="<?php echo esc_attr($row->country); ?>"
                                        data-field_status="<?php echo esc_attr($row->status); ?>">
                                        <?php _e('Edit', 'bidfood'); ?>
                                    </a>

                                    <!-- Delete Ordering Item Master Button -->
                                    <a href="#" class="button button-danger delete-modal-trigger"
                                        data-modal="confirmation-modal"
                                        data-id="<?php echo esc_attr($row->item_id); ?>"
                                        data-entity="ordering_item_master">
                                        <?php _e('Delete', 'bidfood'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div id="toast-container" style="position: fixed; bottom:50px; left:900px; z-index: 9999; max-width: 500px;"></div>

                <!-- Pagination -->
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo; Previous'),
                            'next_text' => __('Next &raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page,
                            'add_args' => [
                                's' => $search_term,
                                'tab' => 'uploads',
                                'uploads_tab' => 'ordering_item_master'
                            ]
                        ]);
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <p><?php _e('No Ordering Item Masters found.', 'bidfood'); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Define the fields for the Add Ordering Item Master modal
        $add_fields = [
            ['name' => 'item_id', 'label' => 'Item ID', 'type' => 'text', 'required' => true],
            ['name' => 'item_parent_id', 'label' => 'Item Parent ID', 'type' => 'text', 'required' => true],
            ['name' => 'venue_id', 'label' => 'Venue ID', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => false],
            ['name' => 'sub_category_id', 'label' => 'Sub Category ID', 'type' => 'text', 'required' => false],
            ['name' => 'temperature_id', 'label' => 'Temperature ID', 'type' => 'text', 'required' => false],
            ['name' => 'moq', 'label' => 'MOQ', 'type' => 'text', 'required' => false],
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => false],
            ['name' => 'preferred_brand', 'label' => 'Preferred Brand', 'type' => 'text', 'required' => false],
            ['name' => 'alternative_brand', 'label' => 'Alternative Brand', 'type' => 'text', 'required' => false],
            ['name' => 'preferred_supplier_id', 'label' => 'Preferred Supplier ID', 'type' => 'text', 'required' => false],
            ['name' => 'alternative_supplier_id', 'label' => 'Alternative Supplier ID', 'type' => 'text', 'required' => false],
            ['name' => 'barcode', 'label' => 'Barcode', 'type' => 'text', 'required' => false],
            ['name' => 'supplier_code', 'label' => 'Supplier Code', 'type' => 'text', 'required' => false],
            ['name' => 'additional_info', 'label' => 'Additional Info', 'type' => 'text', 'required' => false],
            ['name' => 'packaging_per_uom', 'label' => 'Packaging Per UOM', 'type' => 'text', 'required' => false],
            ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'required' => false],
            ['name' => 'status', 'label' => 'Status', 'type' => 'text', 'required' => false]
        ];

        // Define the fields for the Edit Ordering Item Master modal (with readonly item_id)
        $edit_fields = [
            ['name' => 'item_id', 'label' => 'Item ID', 'type' => 'text', 'required' => true, 'readonly' => true],
            ['name' => 'item_parent_id', 'label' => 'Item Parent ID', 'type' => 'text', 'required' => true],
            ['name' => 'venue_id', 'label' => 'Venue ID', 'type' => 'text', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'required' => true],
            ['name' => 'category_id', 'label' => 'Category ID', 'type' => 'text', 'required' => false],
            ['name' => 'sub_category_id', 'label' => 'Sub Category ID', 'type' => 'text', 'required' => false],
            ['name' => 'temperature_id', 'label' => 'Temperature ID', 'type' => 'text', 'required' => false],
            ['name' => 'moq', 'label' => 'MOQ', 'type' => 'text', 'required' => false],
            ['name' => 'uom_id', 'label' => 'UOM ID', 'type' => 'text', 'required' => false],
            ['name' => 'preferred_brand', 'label' => 'Preferred Brand', 'type' => 'text', 'required' => false],
            ['name' => 'alternative_brand', 'label' => 'Alternative Brand', 'type' => 'text', 'required' => false],
            ['name' => 'preferred_supplier_id', 'label' => 'Preferred Supplier ID', 'type' => 'text', 'required' => false],
            ['name' => 'alternative_supplier_id', 'label' => 'Alternative Supplier ID', 'type' => 'text', 'required' => false],
            ['name' => 'barcode', 'label' => 'Barcode', 'type' => 'text', 'required' => false],
            ['name' => 'supplier_code', 'label' => 'Supplier Code', 'type' => 'text', 'required' => false],
            ['name' => 'additional_info', 'label' => 'Additional Info', 'type' => 'text', 'required' => false],
            ['name' => 'packaging_per_uom', 'label' => 'Packaging Per UOM', 'type' => 'text', 'required' => false],
            ['name' => 'country', 'label' => 'Country', 'type' => 'text', 'required' => false],
            ['name' => 'status', 'label' => 'Status', 'type' => 'text', 'required' => false]
        ];

        // Render the Add Ordering Item Master modal
        ModalHelper::render_modal('add-ordering-item-master-modal', 'ordering_item_master', $add_fields, 'add');

        // Render the Edit Ordering Item Master modal
        ModalHelper::render_modal('edit-ordering-item-master-modal', 'ordering_item_master', $edit_fields, 'edit');

        // Render the Delete Ordering Item Master confirmation modal
        ModalHelper::render_delete_confirmation_modal('confirmation-modal', 'ordering_item_master');
        ?>
        <script>
            function startConversionProcess() {
                var modal = jQuery('#conversion-progress-modal');
                var progressBar = modal.find('.progress');
                var progressText = modal.find('.progress-text');
                var button = jQuery('#start-conversion');

                // Get total items from PHP
                var totalItems = <?php echo $total_items; ?>;

                // Check if there are items to convert
                if (totalItems <= 0) {
                    ToastHelper.showToast('<?php _e("No items found to convert!", "bidfood"); ?>', 'warning');
                    return;
                }

                // Disable button and show modal
                button.prop('disabled', true);
                modal.dialog({
                    title: '<?php _e("Conversion Progress", "bidfood"); ?>',
                    modal: true,
                    closeOnEscape: false,
                    dialogClass: 'no-close',
                    width: 500,
                    buttons: []
                });

                function processBatch(offset) {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'process_conversion_batch',
                            offset: offset,
                            nonce: '<?php echo wp_create_nonce('conversion_batch_nonce'); ?>',
                            items_per_batch: itemsPerBatch
                        },
                        success: function(response) {
                            if (response.success) {
                                currentProgress += response.data.processed;
                                var percent = Math.min(Math.round((currentProgress / totalItems) * 100), 100);

                                progressBar.css('width', percent + '%');
                                progressText.text(percent + '% Complete');

                                if (currentProgress < totalItems) {
                                    processBatch(offset + itemsPerBatch);
                                } else {
                                    modal.dialog('close');
                                    button.prop('disabled', false);
                                    ToastHelper.showToast('<?php _e("Conversion completed successfully!", "bidfood"); ?>', 'success');
                                    window.location.reload();
                                }
                            } else {
                                modal.dialog('close');
                                button.prop('disabled', false);
                                ToastHelper.showToast('<?php _e("Error occurred during conversion: ", "bidfood"); ?>' + response.data, 'error');
                            }
                        },
                        error: function(xhr) {
                            modal.dialog('close');
                            button.prop('disabled', false);
                            ToastHelper.showToast('<?php _e("Request failed: ", "bidfood"); ?>' + xhr.statusText, 'error');
                        }
                    });
                }

                // Initialize progress
                var currentProgress = 0;
                var itemsPerBatch = 10;

                // Start first batch
                processBatch(0);
            }

            // Add this toast helper script
            var ToastHelper = {
                showToast: function(message, type) {
                    var container = jQuery('#toast-container');
                    var toast = jQuery('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

                    toast.hide().appendTo(container).fadeIn();
                    setTimeout(function() {
                        toast.fadeOut(function() {
                            jQuery(this).remove();
                        });
                    }, 10000);
                }
            };
        </script>
        <?php
    }

    public function convert_items_to_woocommerce_products()
    {
        global $wpdb;

        // Fetch all ordering items
        $ordering_items = CRUD::fetch_records($wpdb->prefix . 'neom_ordering_item_master', '', 100000);

        $failed_items = [];
        // Loop through each ordering item and create or update a WooCommerce product
        // show item index and item object
        foreach ($ordering_items as $item) {
            try {
                $this->convert_item_to_woocommerce_product($item);
            } catch (\Throwable $e) {
                $failed_items[] = array('failed_item' => $item, 'error' => $e->getMessage());
            }
        }

        // Optionally, show a list of failed items
        if (!empty($failed_items)) {
            foreach ($failed_items as $failed_item) {
                error_log('Failed item: ' . $failed_item['failed_item']->item_id . ' - ' . $failed_item['error']);
            }
        }
    }

    public function convert_item_to_woocommerce_product($item)
    {
        try {
            // Check if a product with the same SKU already exists
            $existing_product_id = wc_get_product_id_by_sku($item->item_id);

            if ($existing_product_id) {
                // Load the existing product
                $product = wc_get_product($existing_product_id);
            } else {
                // Create a new WooCommerce product if it doesn't exist
                $product = new \WC_Product();
            }

            // Set the product title (catch potential NULL)
            $product->set_name(!is_null($item->description) ? $item->description : 'Unnamed Product');

            // Set the product description (catch potential NULL)
            $product->set_description(!is_null($item->description) ? $item->description : '');

            // Set the product price
            $product->set_regular_price(0);

            // Set the product SKU
            $product->set_sku($item->item_id);

            // Set the product slug
            $product->set_slug($item->item_id);

            // Convert category ID and subcategory ID slugs to terms
            $category_ids = [];

            // Convert category slug to category ID
            $category = get_term_by('slug', $item->category_id, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $category_ids[] = $category->term_id;
            }

            // Convert subcategory slug to subcategory ID
            $subcategory = get_term_by('slug', $item->sub_category_id, 'product_cat');
            if ($subcategory && !is_wp_error($subcategory)) {
                $category_ids[] = $subcategory->term_id;
            }

            // Set both category and subcategory IDs
            $product->set_category_ids($category_ids);

            // Set the stock
            $product->set_manage_stock(true);
            $product->set_stock_quantity(1000000);
            $product->set_stock_status('instock');

            // Set product attributes
            $attributes = [];

            if ($item->temperature_id) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('temperature');
                $attribute->set_options([$item->temperature_id]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->moq) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('moq');
                $attribute->set_options([$item->moq]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->uom_id) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('uom');
                $attribute->set_options([$item->uom_id]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->barcode) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('barcode');
                $attribute->set_options([$item->barcode]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->supplier_code) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('supplier_code');
                $attribute->set_options([$item->supplier_code]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->additional_info) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('additional_info');
                $attribute->set_options([$item->additional_info]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->packaging_per_uom) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('packaging_per_uom');
                $attribute->set_options([$item->packaging_per_uom]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->country) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('country');
                $attribute->set_options([$item->country]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->preferred_supplier_id) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('preferred_supplier_id');
                $attribute->set_options([$item->preferred_supplier_id]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            if ($item->alternative_supplier_id) {
                $attribute = new \WC_Product_Attribute();
                $attribute->set_name('alternative_supplier_id');
                $attribute->set_options([$item->alternative_supplier_id]);
                $attribute->set_visible(false);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }

            // Set attributes to the product
            $product->set_attributes($attributes);

            // Assign product brands
            if ($item->preferred_brand) {
                $brand_term = $this->create_brand_if_not_exists($item->preferred_brand, 'pwb-brand');
                if ($brand_term && !is_wp_error($brand_term)) {
                    wp_set_object_terms($product->get_id(), $brand_term->term_id, 'pwb-brand', true);
                }
            }

            if ($item->alternative_brand) {
                $brand_term = $this->create_brand_if_not_exists($item->alternative_brand, 'pwb-brand');
                if ($brand_term && !is_wp_error($brand_term)) {
                    wp_set_object_terms($product->get_id(), $brand_term->term_id, 'pwb-brand', true);
                }
            }

            $terms_data = [
                'pa_preferred_supplier_id' => $item->preferred_supplier_id,
                'pa_alternative_supplier_id' => $item->alternative_supplier_id,
                'pa_temperature' => $item->temperature_id,
                'pa_moq' => $item->moq,
                'pa_uom' => $item->uom_id,
                'pa_country' => $item->country,
            ];

            // Call the function to update taxonomies
            ProductFilter::update_taxonomies($product->get_id(), $terms_data);

            // Set product status
            if ($item->status) {
                if (strtolower($item->status) === 'active') {
                    $product->set_status('publish');
                } else if (strtolower($item->status === 'inactive')) {
                    $product->set_status('draft');
                }
            }

            // Save the product (either updating or creating a new one)
            $product->save();
        } catch (\Throwable $e) {
            // Log the error without throwing
            $error_message = sprintf(
                'Error processing item ID %s (Description: %s) - Error: %s',
                $item->item_id,
                $item->description,
                $e->getMessage()
            );

            // Log detailed error message with item information
            error_log($error_message);
        }
    }

    public function create_brand_if_not_exists($brand_name, $taxonomy)
    {
        $term = get_term_by('name', $brand_name, $taxonomy);

        if (!$term) {
            $result = wp_insert_term($brand_name, $taxonomy);

            // Check if wp_insert_term returned an error
            if (is_wp_error($result)) {
                error_log('Error creating brand term: ' . $result->get_error_message());
                return null;
            }

            // Convert the array result from wp_insert_term to a WP_Term object
            $term = get_term($result['term_id'], $taxonomy);
        }

        return $term;
    }

    public static function handle_woocommerce_product_conversion()
    {
        if (isset($_POST['convert_items_to_products'])) {
            // Verify nonce for security
            if (!isset($_POST['convert_items_to_products_nonce']) || !wp_verify_nonce($_POST['convert_items_to_products_nonce'], 'convert_items_to_products_action')) {
                wp_die(__('Security check failed.', 'bidfood'));
            }

            // Call the function to convert items to WooCommerce products
            $instance = new self();
            $instance->convert_items_to_woocommerce_products();

            // Optionally, show an admin notice after conversion
            add_action('admin_notices', function () {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Items successfully converted to WooCommerce products.', 'bidfood') . '</p></div>';
            });
        }
    }

    public static function check_for_duplicates_in_excel($parsed_data)
    {
        $duplicate_item_ids = [];
        $item_id_counts = array_count_values(array_column($parsed_data, 'item_id'));

        // Identify duplicate item IDs
        foreach ($item_id_counts as $item_id => $count) {
            if ($count > 1) {
                $duplicate_item_ids[] = $item_id;
            }
        }

        if (!empty($duplicate_item_ids)) {
            // Prepare duplicate rows for download
            $duplicates_data = array_filter($parsed_data, function ($row) use ($duplicate_item_ids) {
                return in_array($row['item_id'], $duplicate_item_ids);
            });

            // Generate Excel file with duplicates
            $excel_handler = new ExcelFileHandler();
            $duplicate_file_url = $excel_handler->generate_excel_download($duplicates_data, 'duplicate_items.xlsx');

            if ($duplicate_file_url) {
                // Store the URL for a notice if file creation succeeded
                set_transient('duplicate_items_notice', esc_url($duplicate_file_url), 300); // Valid for 30 seconds
            }

            return true; // Stop further processing
        }

        return false; // No duplicates, continue with insertion
    }

    public static function display_duplicate_items_notice()
    {
        // Check if the transient exists
        $duplicate_file_url = get_transient('duplicate_items_notice');

        if ($duplicate_file_url) {
            error_log('duplicate_file_url: ' . $duplicate_file_url);

            // Display the WordPress admin notice
        ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php echo sprintf(
                        __('Duplicates found in the uploaded file. <a href="%s" download>Click here to download the duplicate list.</a>', 'bidfood'),
                        esc_url($duplicate_file_url)
                    ); ?>
                </p>
            </div>
<?php
            // Delete the transient after displaying the notice
            delete_transient('duplicate_items_notice');
        }
    }
    // New AJAX handler for batch processing
    public static function handle_ajax_conversion()
    {
        try {
            check_ajax_referer('conversion_batch_nonce', 'nonce');

            // Verify user capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('Unauthorized access', 'bidfood'));
            }

            $offset = isset($_POST['offset']) ? absint($_POST['offset']) : 0;
            $items_per_batch = isset($_POST['items_per_batch']) ? absint($_POST['items_per_batch']) : 10;

            global $wpdb;
            $table_name = $wpdb->prefix . 'neom_ordering_item_master';
            $items = CRUD::fetch_records($table_name, '', $items_per_batch, $offset);

            $processed = 0;
            $instance = new self();

            foreach ($items as $item) {
                try {
                    $instance->convert_item_to_woocommerce_product($item);
                    $processed++;
                } catch (\Throwable $e) {
                    // Log error but continue processing
                    error_log('Conversion error: ' . $e->getMessage());
                }
            }

            wp_send_json_success(['processed' => $processed]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
