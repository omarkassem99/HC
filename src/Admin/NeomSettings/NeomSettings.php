<?php

namespace Bidfood\Admin\NeomSettings;

use Bidfood\Admin\NeomSettings\Drivers\DriversPage;
use Bidfood\Admin\NeomSettings\Orders\OrdersPage;
use Bidfood\Admin\NeomSettings\SupplierProducts\ProductsPage;

class NeomSettings
{

    public static function render()
    {
?>
        <div class="wrap">
            <h1><?php _e('Neom Settings', 'bidfood'); ?></h1>

            <!-- Primary Horizontal Tabs -->
            <h2 class="nav-tab-wrapper">
                <a href="?page=bidfood-neom-settings&tab=uploads" class="nav-tab <?php echo self::get_active_tab('uploads'); ?>">
                    <?php _e('Uploads', 'bidfood'); ?>
                </a>
                <a href="?page=bidfood-neom-settings&tab=delivery_dates" class="nav-tab <?php echo self::get_active_tab('delivery_dates'); ?>">
                    <?php _e('Delivery Dates', 'bidfood'); ?>
                </a>
                <a href="?page=bidfood-neom-settings&tab=supplier_requests" class="nav-tab <?php echo self::get_active_tab('supplier_requests'); ?>">
                    <?php _e('Supplier Requests', 'bidfood'); ?>
                </a>
                <a href="?page=bidfood-neom-settings&tab=supplier_products" class="nav-tab <?php echo self::get_active_tab('supplier_products'); ?>">
                    <?php _e('Products', 'bidfood'); ?>
                </a>
                <a href="?page=bidfood-neom-settings&tab=orders" class="nav-tab <?php echo self::get_active_tab('orders'); ?>">
                    <?php _e('Orders', 'bidfood'); ?>
                </a>
                <a href="?page=bidfood-neom-settings&tab=drivers" class="nav-tab <?php echo self::get_active_tab('drivers');?>">
                    <?php _e('Drivers', 'bidfood'); ?>
                </a>
            </h2>

            <!-- Content Based on Selected Tab -->
            <div class="tab-content">
                <?php
                $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'uploads';

                switch ($tab) {
                    case 'uploads':
                        self::render_uploads_tabs();
                        break;
                    case 'delivery_dates':
                        DeliveryDates::render();
                        break;
                    case 'supplier_requests':
                        SupplierRequests\SupplierRequestsPage::render();
                        break;
                    case 'supplier_products':
                        ProductsPage::render();
                        break;
                    case 'orders':
                        OrdersPage::render();
                        break;
                        case 'drivers':
                            DriversPage::render();
                            break;
                    default:
                        self::render_uploads_tabs();
                        break;
                }
                ?>
            </div>
        </div>
    <?php
    }

    // Render Uploads sub-tabs
    private static function render_uploads_tabs()
    {
    ?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=operator" class="nav-tab <?php echo self::get_active_tab('operator', 'uploads_tab'); ?>">
                <?php _e('Operator', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=venue" class="nav-tab <?php echo self::get_active_tab('venue', 'uploads_tab'); ?>">
                <?php _e('Venue', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=supplier" class="nav-tab <?php echo self::get_active_tab('supplier', 'uploads_tab'); ?>">
                <?php _e('Supplier', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=category" class="nav-tab <?php echo self::get_active_tab('category', 'uploads_tab'); ?>">
                <?php _e('Category', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=sub_category" class="nav-tab <?php echo self::get_active_tab('sub_category', 'uploads_tab'); ?>">
                <?php _e('Sub Category', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=temperature" class="nav-tab <?php echo self::get_active_tab('temperature', 'uploads_tab'); ?>">
                <?php _e('Temperature', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=uom" class="nav-tab <?php echo self::get_active_tab('uom', 'uploads_tab'); ?>">
                <?php _e('UOM', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=item_parent" class="nav-tab <?php echo self::get_active_tab('item_parent', 'uploads_tab'); ?>">
                <?php _e('Item Parent', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=ordering_item_master" class="nav-tab <?php echo self::get_active_tab('ordering_item_master', 'uploads_tab'); ?>">
                <?php _e('Ordering Item Master', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=groups" class="nav-tab <?php echo self::get_active_tab('groups', 'uploads_tab'); ?>">
                <?php _e('Groups', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=uploads&uploads_tab=outlets" class="nav-tab <?php echo self::get_active_tab('outlets', 'uploads_tab'); ?>">
                <?php _e('Outlets', 'bidfood'); ?>
            </a>
        </h2>

<?php
        // Render the content for the active Uploads sub-tab
        $uploads_tab = isset($_GET['uploads_tab']) ? sanitize_text_field($_GET['uploads_tab']) : 'operator';

        switch ($uploads_tab) {
            case 'operator':
                UploadsTabs\Operator::render();
                break;
            case 'venue':
                UploadsTabs\Venue::render();
                break;
            case 'supplier':
                UploadsTabs\Supplier::render();
                break;
            case 'category':
                UploadsTabs\Category::render();
                break;
            case 'sub_category':
                UploadsTabs\SubCategory::render();
                break;
            case 'temperature':
                UploadsTabs\Temperature::render();
                break;
            case 'uom':
                UploadsTabs\UOM::render();
                break;
            case 'item_parent':
                UploadsTabs\ItemParent::render();
                break;
            case 'ordering_item_master':
                UploadsTabs\OrderingItemMaster::render();
                break;
            case 'groups':
                GroupsOutlets\Groups::render();
                break;
            case 'outlets':
                GroupsOutlets\Outlets::render();
                break;
            default:
                UploadsTabs\Operator::render();
                break;
        }
    }

    // Helper function to set active tab
    private static function get_active_tab($tab, $query_var = 'tab')
    {
        return isset($_GET[$query_var]) && $_GET[$query_var] === $tab ? 'nav-tab-active' : '';
    }
}
