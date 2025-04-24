<?php

namespace Bidfood\Admin\NeomSettings\Drivers;

use Bidfood\Admin\NeomSettings\Drivers\DriverOrderItems;

class DriversPage
{
    public static function init()
    {
        return new self();
    }

    public static function render()
    {
?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=bidfood-neom-settings&tab=drivers&drivers_tab=all-drivers" class="nav-tab <?php echo self::get_active_tab('all-drivers', 'drivers_tab'); ?>">
                <?php _e('All Drivers', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=drivers&drivers_tab=all-driver-orders" class="nav-tab <?php echo self::get_active_tab('all-driver-orders', 'drivers_tab'); ?>">
                <?php _e('Driver Orders', 'bidfood'); ?>
            </a>
            <a href="?page=bidfood-neom-settings&tab=drivers&drivers_tab=driver-order-skip-requests" class="nav-tab <?php echo self::get_active_tab('driver-order-skip-requests', 'drivers_tab'); ?>">
                <?php _e('Driver Order Skip Requests', 'bidfood'); ?>
            </a>
        </h2>

<?php
        $drivers_tab = isset($_GET['drivers_tab']) ? sanitize_text_field($_GET['drivers_tab']) : 'all-drivers';

        switch ($drivers_tab) {
            case 'all-drivers':
                DriverUsers::render();
                break;
            case 'all-driver-orders':
                DriverOrders::render();
                break;
            case 'driver-order-details':
                DriverOrderItems::render_order_details();
                break;
            case 'driver-order-skip-requests':
                DriverOrderSkipRequests::render();
                break;
            default:
                DriverUsers::render();
                break;
        }
    }

    private static function get_active_tab($tab, $query_var = 'tab')
    {
        return isset($_GET[$query_var]) && $_GET[$query_var] === $tab ? 'nav-tab-active' : '';
    }
}
