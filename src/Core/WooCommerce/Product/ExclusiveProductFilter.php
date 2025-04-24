<?php

namespace Bidfood\Core\WooCommerce\Product;

class ExclusiveProductFilter
{
    /**
     * Initialize hooks for the custom restrictions.
     */
    public static function init()
    {
        // Restrict access to exclusive products details page
        add_action('pre_get_posts', [__CLASS__, 'filter_exclusive_products']);

        // Prevent exclusive products from being added to the cart
        add_filter('woocommerce_add_to_cart_validation', [__CLASS__, 'block_add_to_cart'], 10, 2);

        // Prevent exclusive products from being processed during checkout
        add_action('woocommerce_check_cart_items', [__CLASS__, 'block_checkout_items']);

        // Remove exclusive products from Fibonacci Search Page results
        add_filter( 'dgwt/wcas/tnt/search_results/ids', [__CLASS__, 'filter_fibo_search_results'], 10, 2 );

        // Set custom products search endpoint
        add_filter ( 'dgwt/wcas/scripts/localize', [__CLASS__, 'custom_search_url'], 10, 1 );
    }

    /**
     * Restrict access to exclusive products.
     */
    public static function filter_exclusive_products($query)
    {
        if (!is_user_logged_in()) {
            return; // Show only non-exclusive products for non-logged-in users
        }

        $user_id = get_current_user_id();

        // Exclude exclusive products that the user doesn't have access to
        $query->set('meta_query', [
            'relation' => 'OR',
            [
                'key'     => '_exclusive_user_ids',
                'value'   => sprintf('s:%d:"%d";', strlen((string) $user_id), $user_id),
                'compare' => 'LIKE',
            ],
            [
                'key'     => '_exclusive_user_ids',
                'compare' => 'NOT EXISTS',
            ],
        ]);
    }

    /**
     * Prevent exclusive products from being added to the cart.
     */
    public static function block_add_to_cart($passed, $product_id)
    {
        // Check if the product is exclusive
        $allowed_users = get_post_meta($product_id, '_exclusive_user_ids', true);
        if ($allowed_users && is_array($allowed_users)) {
            $user_id = get_current_user_id();
            if (!in_array($user_id, $allowed_users)) {
                wc_add_notice(__('You cannot add this product to your cart.', 'woocommerce'), 'error');
                return false;
            }
        }

        return $passed;
    }

    /**
     * Prevent exclusive products from being in the checkout.
     */
    public static function block_checkout_items()
    {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = wc_get_product($cart_item['product_id']);

            // Check if the product is exclusive
            $allowed_users = get_post_meta($product->get_id(), '_exclusive_user_ids', true);
            if ($allowed_users && is_array($allowed_users)) {
                $user_id = get_current_user_id();
                if (!in_array($user_id, $allowed_users)) {
                    wc_add_notice(__('One or more products in your cart are not available for purchase.', 'woocommerce'), 'error');
                    WC()->cart->remove_cart_item($cart_item_key);
                }
            }
        }
    }


    /**
     * Remove exclusive products from Fibonacci Search results
     */
    public static function filter_fibo_search_results( $ids ) {
        $user_id = get_current_user_id();
        $filtered_ids = array();
    
        // Preload meta for all posts to avoid individual queries.
        update_meta_cache('post', $ids);
    
        foreach ( $ids as $id ) {
            // Check if the item is marked as exclusive.
            $is_exclusive = get_post_meta( $id, '_is_exclusive_item', true );
            if ( ! $is_exclusive ) {
                // Not exclusive; allow it without further checks.
                $filtered_ids[] = $id;
                continue;
            }
    
            // For exclusive items, retrieve allowed user IDs.
            $allowed_users = get_post_meta( $id, '_exclusive_user_ids', true );
    
            // If there is no allowed user list or if the current user is in it, allow the item.
            if ( ! $allowed_users || ( is_array( $allowed_users ) && in_array( $user_id, $allowed_users, true ) ) ) {
                $filtered_ids[] = $id;
            }
        }
    
        return $filtered_ids;
    }

    public static function custom_search_url($localized_data) {
        $localized_data['ajax_search_endpoint'] = \Bidfood\Core\WooCommerce\Product\SearchAPI::get_search_endpoint();
        return $localized_data;
    }
}
