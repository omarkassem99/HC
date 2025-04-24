<?php

use Chef\UI\Toast\ToastHelper;
use Chef\UI\Toast\ToastAssets;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue styles and scripts
function bidfood_admin_enqueue_assets() {
    wp_enqueue_script('modal-handler-js', plugin_dir_url(__FILE__) . 'assets/js/modal-handler.js', ['jquery'], null, true);

    // Localize the script with a nonce and AJAX URL
    wp_localize_script('modal-handler-js', 'modal_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('generate_nonce') // Nonce to secure the AJAX call
    ));
}
add_action('admin_enqueue_scripts', 'bidfood_admin_enqueue_assets');

// Initialize the plugin components
add_action('plugins_loaded', function() {
    Bidfood\Admin\Admin::init();
    Bidfood\Admin\NeomSettings\DeliveryDates::init();
    Bidfood\Admin\NeomSettings\SupplierRequests\SupplierRequestsPage::init();
    Bidfood\Admin\NeomSettings\SupplierRequests\SupplierUpdateRequests::init();
    Bidfood\Admin\NeomSettings\SupplierRequests\AdminNewItemRequests::init();
    Bidfood\Admin\NeomSettings\SupplierProducts\ProductsPage::init();
    Bidfood\Admin\NeomSettings\SupplierProducts\ProductsExclusivity::init();
    Bidfood\Admin\NeomSettings\SupplierProducts\ProductsImages::init();
    Bidfood\Admin\NeomSettings\Orders\OrdersPage::init();
    Bidfood\Admin\NeomSettings\Orders\WhReceivedOrders::init();
    Bidfood\Admin\NeomSettings\Orders\WhReceivedOrderItems::init();
    Bidfood\Admin\NeomSettings\Drivers\DriversPage::init();
    Bidfood\Admin\NeomSettings\Drivers\DriverUsers::init();
    Bidfood\Admin\NeomSettings\Drivers\DriverOrders::init();
    Bidfood\Admin\NeomSettings\Drivers\DriverOrderItems::init();
    Bidfood\Admin\NeomSettings\Drivers\DriverOrderSkipRequests::init();
    Bidfood\Admin\NeomSettings\Drivers\DriverAPI::init(); // Driver API
    Bidfood\Admin\NeomSettings\Orders\DriverOrdersAPI::init();
    Bidfood\Core\Events\POEvents::init();
    Bidfood\Core\Events\OrderEvents::init();
    Bidfood\Core\Events\EmailEvents::init();
    Bidfood\Core\Events\DriverOrderEmailEvents::init();
    // Bidfood\Core\Events\SupplierRequestEvents::init();
    Bidfood\Core\WooCommerce\OrderManager::init();
    Bidfood\Core\WooCommerce\WhOrders\WhOrderConverter::init();
    Bidfood\Core\WooCommerce\CustomOrderStatuses::init();
    Bidfood\Core\WooCommerce\SupplierUserPoPage::init();
    Bidfood\Core\WooCommerce\SupplierPoConverter::init();
    Bidfood\Core\WooCommerce\SupplierPoPage::init();
    Bidfood\Core\WooCommerce\SupplierPoDetailsPage::init();
    Bidfood\Core\WooCommerce\SupplierCurrentItemsPage::init();
    Bidfood\Core\WooCommerce\SupplierRequests\SupplierNewItemRequestPage::init();
    Bidfood\Core\WooCommerce\SupplierRequests\SupplierUserRequestsPage::init();
    Bidfood\Core\WooCommerce\Checkout\DeliveryDate::init();
    Bidfood\Core\WooCommerce\Checkout\CheckoutVenue::init();
    Bidfood\Core\WooCommerce\Product\SingleProductInfoPage::init();
    Bidfood\Core\WooCommerce\Product\CustomProductSearch::init();
    Bidfood\Core\WooCommerce\Product\ProductQueryManager::init();
    Bidfood\Core\WooCommerce\Product\ProductFilter::init();
    Bidfood\Core\WooCommerce\Product\ExclusiveProductFilter::init();
    Bidfood\Core\WooCommerce\Product\SearchAPI::init();
    Bidfood\Core\OrderManagement\DriverOrderManager::init();
    Bidfood\Core\OrderManagement\CustomerOrderManager::init();
    Bidfood\Admin\NeomSettings\UploadsTabs\OrderingItemMaster::init();
});

// Hook to output the toast notices in the footer
add_action('wp_footer', [ToastHelper::class, 'output_toast_notices']);
add_action('admin_footer', [ToastHelper::class, 'output_toast_notices']);

// Hook to enqueue global toast assets (CSS & JS) in the footer
add_action('wp_enqueue_scripts', [ToastAssets::class, 'enqueue_global_toast_assets']);
add_action('admin_enqueue_scripts', [ToastAssets::class, 'enqueue_global_toast_assets']);


// function to redirect to home page after login
function redirect_to_home_page() {
    wp_redirect(home_url());
    exit;
}

add_action('woocommerce_login_redirect', 'redirect_to_home_page');

// Remove address book, downloads from my account navigation
function remove_my_account_links($items) {
    unset($items['downloads']);
    unset($items['edit-address']);

    return $items;
}
add_filter('woocommerce_account_menu_items', 'remove_my_account_links', 10, 1);


add_action('admin_menu', function() {
    add_menu_page(
        'All Taxonomies',
        'Taxonomies',
        'manage_options',
        'all-taxonomies',
        'display_all_taxonomies'
    );
});

function display_all_taxonomies() {
    $taxonomies = get_taxonomies([], 'objects');
    echo '<h1>All Registered Taxonomies</h1>';
    echo '<ul>';
    foreach ($taxonomies as $taxonomy) {
        echo '<li>' . $taxonomy->name . ': ' . $taxonomy->label . '</li>';
    }
    echo '</ul>';
}

// Suppress unwanted warnings
add_filter('doing_it_wrong_trigger_error', function($trigger, $function_name) {
    if ($function_name === '_load_textdomain_just_in_time') {
        return false; // Suppress the notice
    }
    return $trigger;
}, 10, 2);

// Handle user php sessions
add_action('init', function() {
    if (!session_id()) {
        session_start();
    }

    // Check if the user is logged in
    if (is_user_logged_in()) {
        // Get the user ID
        $user_id = get_current_user_id();

        if ($user_id && !isset($_SESSION['user_id'])) {
            $_SESSION['user_id'] = $user_id;
        }
    }
});