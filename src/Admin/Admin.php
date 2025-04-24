<?php

namespace Bidfood\Admin;

use Bidfood\Admin\NeomSettings\UploadsTabs\OrderingItemMaster;

class Admin {

    public static function init() {
        // Hook into admin menu to create the Neom settings page
        add_action('admin_menu', [__CLASS__, 'create_admin_menu']);
        add_action('admin_init', ['Bidfood\Admin\NeomSettings\UploadsTabs\OrderingItemMaster', 'handle_woocommerce_product_conversion']);
        add_action('admin_init', ['Bidfood\Admin\NeomSettings\UploadsTabs\Category', 'handle_woocommerce_category_conversion']);
        add_action('admin_init', ['Bidfood\Admin\NeomSettings\UploadsTabs\SubCategory', 'handle_woocommerce_subcategory_conversion']);
        add_action('init', function() { new \Bidfood\Core\WooCommerce\OrderToSupplierConverter(); });

        // Hook to display duplicate notices 
        add_action('admin_notices', [OrderingItemMaster::class, 'display_duplicate_items_notice']);
    }

    public static function create_admin_menu() {
        // Add the Neom settings submenu page
        add_menu_page(
            __('Bidfood Settings', 'bidfood'),
            __('Bidfood', 'bidfood'),
            'manage_options',
            'bidfood',
            [__CLASS__, 'settings_page'],
            'dashicons-admin-generic',
            6
        );

        // Add Neom Settings submenu
        add_submenu_page(
            'bidfood',
            __('Neom Settings', 'bidfood'),
            __('Neom Settings', 'bidfood'),
            'manage_options',
            'bidfood-neom-settings',
            [\Bidfood\Admin\NeomSettings\NeomSettings::class, 'render']
        );
    }

    public static function settings_page() {
        echo "<h1>Bidfood Settings</h1>";
    }
}
