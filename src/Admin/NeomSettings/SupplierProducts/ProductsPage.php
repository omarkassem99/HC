<?php

namespace Bidfood\Admin\NeomSettings\SupplierProducts;

class ProductsPage
{
    public static function init()
    {
        return new self();
    }
    public static function render()
    {
    ?>
    <h2 class="nav-tab-wrapper">
        <a href="?page=bidfood-neom-settings&tab=supplier_products&products_tab=products_exclusivity" class="nav-tab <?php echo self::get_active_tab('products_exclusivity', 'products_tab'); ?>">
            <?php _e('Products Exclusivity', 'bidfood'); ?>
        </a>
        <a href="?page=bidfood-neom-settings&tab=supplier_products&products_tab=products_images" class="nav-tab <?php echo self::get_active_tab('products_images', 'products_tab'); ?>">
            <?php _e('Products Images', 'bidfood'); ?>
        </a>
    </h2>

    <?php
            $products_tab = isset($_GET['products_tab']) ? sanitize_text_field($_GET['products_tab']) : 'products_exclusivity';
             switch ($products_tab) {
            case 'products_exclusivity':
                ProductsExclusivity::render();
                break;
                case 'products_images':
                    ProductsImages::render();
                    break;
                default:
                ProductsExclusivity::render();
                break;
            }

    }
    private static function get_active_tab($tab, $query_var = 'tab')
    {
        return isset($_GET[$query_var]) && $_GET[$query_var] === $tab ? 'nav-tab-active' : '';
    }
}
