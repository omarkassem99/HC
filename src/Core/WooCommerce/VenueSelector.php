<?php

namespace Bidfood\Core\WooCommerce;

use Bidfood\Core\UserManagement\UserOperatorManager;
use Bidfood\Core\Permissions\CapabilityManager;

class VenueSelector {
    /**
     * Add a venue selection dropdown for operators during checkout
     */
    public static function add_venue_dropdown_for_operator($checkout) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;

        if (CapabilityManager::user_has_capability($user_id, 'place_orders_for_venues')) {
            $assigned_operator = UserOperatorManager::get_operator_by_user($user_id);

            if ($assigned_operator) {
                $assigned_venues = UserOperatorManager::get_venues_by_operator($assigned_operator);

                if (!empty($assigned_venues)) {
                    woocommerce_form_field('venue_selection', [
                        'type' => 'select',
                        'class' => ['form-row-wide'],
                        'label' => __('Select the venue for this order'),
                        'required' => true,
                        'options' => array_reduce($assigned_venues, function ($acc, $venue) {
                            $acc[$venue->venue_id] = $venue->venue_name;
                            return $acc;
                        }, []),
                    ], '');
                }
            }
        }
    }

    /**
     * Validate and save the venue selection during checkout
     */
    public static function validate_and_save_venue_selection($order_id) {
        if (isset($_POST['venue_selection'])) {
            update_post_meta($order_id, '_venue_id', sanitize_text_field($_POST['venue_selection']));
        }
    }
}
