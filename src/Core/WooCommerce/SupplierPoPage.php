<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserSupplierManager;
use Bidfood\UI\Toast\ToastHelper;

class SupplierPoPage {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_supplier_po_menu']);
        add_action('wp_ajax_load_supplier_pos', [self::class, 'load_supplier_pos']);
        add_action('wp_ajax_delete_supplier_po', [self::class, 'delete_supplier_po']);
        add_action('woocommerce_order_list_table_extra_tablenav', [self::class, 'add_supplier_po_page_button'], 60);
    }

    public static function init() {
        return new self();
    }

    public function add_supplier_po_menu() {
        add_menu_page(
            __('Supplier POs', 'bidfood'),
            '',
            'manage_woocommerce',
            'supplier-po-page',
            [$this, 'render_supplier_po_page'],
            null,
            null
        );
    }

    public static function add_supplier_po_page_button() {
        $supplier_po_url = admin_url('admin.php?page=supplier-po-page');
        echo '<a href="' . esc_url($supplier_po_url) . '" class="button button-primary">' . esc_html__('Open Supplier POs Page', 'bidfood') . '</a>';
    }

    public function render_supplier_po_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Supplier POs', 'bidfood'); ?></h1>
            <input type="text" id="search_po" class="search-box" placeholder="<?php esc_attr_e('Search...', 'bidfood'); ?>">
            <div id="supplier-po-table-container">
                <?php $this->render_supplier_po_table(); ?>
            </div>
        </div>
        
        <style>
            .search-box {
                width: 300px;
                padding: 8px;
                margin-bottom: 15px;
                font-size: 16px;
            }
            .pagination {
                display: flex;
                align-items: center;
                margin-top: 15px;
            }
            .pagination a, .pagination input {
                margin: 0 5px;
            }
            .pagination .arrow-button {
                background: #0073aa;
                color: #fff;
                padding: 5px 10px;
                text-decoration: none;
                border-radius: 3px;
            }
            .pagination .page-number-input {
                width: 50px;
                text-align: center;
            }
            .table-info {
                margin-bottom: 15px;
                font-size: 14px;
            }
            table.wp-list-table th, table.wp-list-table td {
                text-align: center;
            }
            .view-button {
                background-color: #0073aa;
                color: white;
                border: none;
                padding: 5px 10px;
                text-decoration: none;
                border-radius: 3px;
                cursor: pointer;
                margin-right: 5px;
                transition: background-color 0.3s ease;
            }

            .view-button:hover {
                background-color: #005f8a; /* Slightly darker blue on hover */
                color: white; /* Keeps text color consistent */
            }

            .delete-button {
                background-color: #d9534f;
                color: white;
                border: none;
                padding: 5px 10px;
                text-decoration: none;
                border-radius: 3px;
                cursor: pointer;
                transition: background-color 0.3s ease;
            }

            .delete-button:hover {
                background-color: #c9302c; /* Slightly darker red on hover */
                color: white; /* Keeps text color consistent */
            }
        </style>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                let currentPage = 1;

                function loadSupplierPos(page = 1, search = '') {
                    currentPage = page;
                    $.ajax({
                        url: ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'load_supplier_pos',
                            page: page,
                            search: search
                        },
                        success: function(response) {
                            $('#supplier-po-table-container').html(response);
                            $('.page-number-input').val(currentPage);
                        }
                    });
                }

                $('#search_po').on('keyup', function() {
                    const search = $(this).val();
                    loadSupplierPos(1, search);
                });

                $(document).on('click', '.pagination .arrow-button', function(e) {
                    e.preventDefault();
                    const page = $(this).data('page');
                    const search = $('#search_po').val();
                    loadSupplierPos(page, search);
                });

                $(document).on('change', '.pagination .page-number-input', function() {
                    const page = $(this).val();
                    const search = $('#search_po').val();
                    loadSupplierPos(page, search);
                });

                $(document).on('click', '.delete-button', function(e) {
                    e.preventDefault();
                    const poId = $(this).data('po-id');

                    if (confirm("Are you sure you want to delete this PO?")) {
                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'delete_supplier_po',
                                po_id: poId
                            },
                            success: function(response) {
                                if (response.success) {
                                    loadSupplierPos(currentPage);
                                    showToast(response.data, 'success');
                                } else {
                                    showToast(response.data, 'error');
                                }
                            }
                        });
                    }
                });

                loadSupplierPos();
            });
        </script>
        <?php
    }

    public static function load_supplier_pos() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized access', 'bidfood'));
        }

        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $po_data = UserSupplierManager::get_supplier_pos_paginated($page, $search);

        self::render_supplier_po_table($po_data, $page);
        
        wp_die();
    }

    public static function delete_supplier_po() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Unauthorized access', 'bidfood'));
        }

        if (!isset($_POST['po_id'])) {
            wp_send_json_error(__('Missing PO ID', 'bidfood'));
        }

        $po_id = intval($_POST['po_id']);
        $result = UserSupplierManager::delete_supplier_po($po_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Supplier PO deleted successfully', 'bidfood'));
        }
    }

    private static function render_supplier_po_table($data = null, $current_page = 1) {
        if (!$data) {
            $data = UserSupplierManager::get_supplier_pos_paginated();
        }

        if (is_wp_error($data)) {
            echo '<div class="error"><p>' . $data->get_error_message() . '</p></div>';
            return;
        }

        $total_items = $data['total_items'];
        $total_pages = $data['total_pages'];
        $items_on_page = count($data['results']);
        $start_item = ($current_page - 1) * 10 + 1;
        $end_item = $start_item + $items_on_page - 1;

        ?>
        <div class="table-info">
            <?php printf(
                esc_html__('Showing %d–%d of %d items. Page %d of %d', 'bidfood'),
                $start_item,
                $end_item,
                $total_items,
                $current_page,
                $total_pages
            ); ?>
        </div>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('ID', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Supplier ID', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Status', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Created At', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Updated At', 'bidfood'); ?></th>
                    <th><?php esc_html_e('Action', 'bidfood'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['results'] as $po) : ?>
                    <tr>
                        <td><?php echo esc_html($po['id']); ?></td>
                        <td><?php echo esc_html($po['supplier_id']); ?></td>
                        <td><?php echo esc_html($po['status']); ?></td>
                        <td><?php echo esc_html($po['created_at']); ?></td>
                        <td><?php echo esc_html($po['updated_at']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg(['page' => 'supplier-po-details', 'po_id' => $po['id']], admin_url('admin.php'))); ?>" class="view-button"><?php esc_html_e('View', 'bidfood'); ?></a>
                            <button class="delete-button" data-po-id="<?php echo esc_attr($po['id']); ?>"><?php esc_html_e('Delete', 'bidfood'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <a href="#" data-page="<?php echo max(1, $current_page - 1); ?>" class="arrow-button"><?php esc_html_e('« Prev', 'bidfood'); ?></a>
            <input type="number" class="page-number-input" min="1" max="<?php echo $total_pages; ?>" value="<?php echo $current_page; ?>">
            <a href="#" data-page="<?php echo min($total_pages, $current_page + 1); ?>" class="arrow-button"><?php esc_html_e('Next »', 'bidfood'); ?></a>
        </div>
        <?php
    }
}