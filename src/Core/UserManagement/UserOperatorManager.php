<?php

namespace Bidfood\Core\UserManagement;

use WP_Error;

class UserOperatorManager {

    /**
     * Check if a user is assigned to an operator.
     *
     * @param int $user_id - The user ID.
     * @return bool - True if the user is an operator, false otherwise.
     */
    public static function is_user_operator($user_id) {
        global $wpdb;

        $operator_id = $wpdb->get_var($wpdb->prepare(
            "SELECT operator_id FROM {$wpdb->prefix}neom_user_operator_relation WHERE user_id = %d",
            $user_id
        ));

        return !empty($operator_id);
    }

    /**
     * Get the operator associated with a user.
     *
     * @param int $user_id - The user ID.
     * @return string|WP_Error - Operator ID or WP_Error if not found.
     */
    public static function get_operator_by_user($user_id) {
        global $wpdb;

        $operator_id = $wpdb->get_var($wpdb->prepare(
            "SELECT operator_id FROM {$wpdb->prefix}neom_user_operator_relation WHERE user_id = %d",
            $user_id
        ));

        if (empty($operator_id)) {
            return new WP_Error('no_operator', __('No operator found for this user.', 'bidfood'));
        }

        return $operator_id;
    }

    /**
     * Assign a user to an operator.
     *
     * @param int $user_id - The user ID.
     * @param string $operator_id - The operator ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_user_to_operator($user_id, $operator_id) {
        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_user_operator_relation",
            [
                'user_id' => $user_id,
                'operator_id' => $operator_id,
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to assign user to operator.', 'bidfood'));
        }

        return true;
    }

    /**
     * Remove a user from an operator.
     *
     * @param int $user_id - The user ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function remove_user_from_operator($user_id) {
        global $wpdb;

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_user_operator_relation",
            ['user_id' => $user_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove user from operator.', 'bidfood'));
        }

        return true;
    }

    /**
     * Get all users assigned to an operator.
     *
     * @param string $operator_id - The operator ID.
     * @return array|WP_Error - Array of user IDs or WP_Error on failure.
     */
    public static function get_users_by_operator($operator_id) {
        global $wpdb;

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}neom_user_operator_relation WHERE operator_id = %s",
            $operator_id
        ));

        if (empty($user_ids)) {
            return new WP_Error('no_users', __('No users found for this operator.', 'bidfood'));
        }

        return $user_ids;
    }

    /**
     * Get venues managed by an operator.
     * 
     * @param string $operator_id - The operator ID.
     * @return array - Array of venue IDs.
     */
    public static function get_venues_by_operator($operator_id) {
        global $wpdb;

        $venues = $wpdb->get_results($wpdb->prepare(
            "SELECT venue_id FROM {$wpdb->prefix}neom_venue_operator_relation WHERE operator_id = %s",
            $operator_id
        ));

        return $venues;
    }
    
}
