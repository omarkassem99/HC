<?php

namespace Bidfood\Core\Permissions;

class CapabilityManager {
    /**
     * Check if the user has the necessary capability
     */
    public static function user_has_capability($user_id, $capability) {
        return user_can($user_id, $capability);
    }

    /**
     * Add capabilities to roles
     */
    public static function add_custom_capabilities() {
        $roles = ['venue_manager', 'operator_manager'];

        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->add_cap('place_orders');
                $role->add_cap('view_orders');
                if ($role_name == 'operator_manager') {
                    $role->add_cap('place_orders_for_venues');
                }
            }
        }
    }
}
