<?php

namespace Bidfood\Admin\NeomSettings\Orders;

class OrdersPage
{
    public static function init()
    {
        return new self();
    }

    public static function render()
    {
?>
        <h2 class="nav-tab-wrapper">
            <a href="?page=bidfood-neom-settings&tab=orders&orders_tab=wh_received_orders" class="nav-tab <?php echo self::get_active_tab('wh_received_orders', 'orders_tab'); ?>">
                <?php _e('WH Received Orders', 'bidfood'); ?>
            </a>
        </h2>

<?php
        $orders_tab = isset($_GET['orders_tab']) ? sanitize_text_field($_GET['orders_tab']) : 'wh_received_orders';

        switch ($orders_tab) {
            case 'wh_received_orders':
                WhReceivedOrders::render();
                break;

            case 'wh_received_order_details':
                WhReceivedOrderItems::render_order_details();
                break;

            default:
                WhReceivedOrders::render();
                break;
        }
    }

    private static function get_active_tab($tab, $query_var = 'tab')
    {
        return isset($_GET[$query_var]) && $_GET[$query_var] === $tab ? 'nav-tab-active' : '';
    }
}
