<?php

namespace Bidfood\Admin\NeomSettings\SupplierRequests;

use Bidfood\Admin\NeomSettings\SupplierRequests\AdminNewItemRequests;
use Bidfood\Admin\NeomSettings\SupplierRequests\SupplierUpdateRequests;

class SupplierRequestsPage
{
    public static function init()
    {
        return new self();
    }

    public static function render()
    {
?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=bidfood-neom-settings&tab=supplier_requests&supplier_requests_tab=supplier_update_requests" class="nav-tab <?php echo self::get_active_tab('supplier_update_requests', 'supplier_requests_tab'); ?>">
                <?php _e('Supplier Update Requests', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=supplier_requests&supplier_requests_tab=supplier_add_item_requests" class="nav-tab <?php echo self::get_active_tab('supplier_add_item_requests', 'supplier_requests_tab'); ?>">
                <?php _e('Supplier New Items Requests', 'bidfood'); ?>
            </a>
        </h2>

<?php
        $supplier_requests_tab = isset($_GET['supplier_requests_tab']) ? sanitize_text_field($_GET['supplier_requests_tab']) : 'supplier_update_requests';

        switch ($supplier_requests_tab) {
            case 'supplier_update_requests':
                SupplierUpdateRequests::render();
                break;
            case 'supplier_add_item_requests':
                AdminNewItemRequests::render();
                break;
            default:
                SupplierUpdateRequests::render();
                break;
        }
    }

    private static function get_active_tab($tab, $query_var = 'tab')
    {
        return isset($_GET[$query_var]) && $_GET[$query_var] === $tab ? 'nav-tab-active' : '';
    }
}
