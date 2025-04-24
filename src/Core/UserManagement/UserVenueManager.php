<?php

namespace Bidfood\Core\UserManagement;

use WP_Error;

class UserVenueManager {

    /**
     * Check if a user is assigned to a venue.
     *
     * @param int $user_id - The user ID.
     * @return bool - True if the user is assigned to a venue, false otherwise.
     */
    public static function is_user_venue($user_id) {
        global $wpdb;

        $venue_id = $wpdb->get_var($wpdb->prepare(
            "SELECT venue_id FROM {$wpdb->prefix}neom_user_venue_relation WHERE user_id = %d",
            $user_id
        ));

        return !empty($venue_id);
    }

    /**
     * Get the venue associated with a user.
     *
     * @param int $user_id - The user ID.
     * @return string|WP_Error - Venue ID or WP_Error if not found.
     */
    public static function get_venue_by_user($user_id) {
        global $wpdb;

        $venue_id = $wpdb->get_var($wpdb->prepare(
            "SELECT venue_id FROM {$wpdb->prefix}neom_user_venue_relation WHERE user_id = %d",
            $user_id
        ));

        if (empty($venue_id)) {
            return new WP_Error('no_venue', __('No venue found for this user.', 'bidfood'));
        }

        return $venue_id;
    }

    /**
     * Assign a user to a venue.
     *
     * @param int $user_id - The user ID.
     * @param string $venue_id - The venue ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_user_to_venue($user_id, $venue_id) {

        // Check if the user exists
        if (!get_user_by('ID', $user_id) && $user_id != 0) {
            return new WP_Error('user_not_found', __('User not found.', 'bidfood'));
        }

        // Check if there is a current user assigned to the venue
        $current_user = self::get_users_by_venue($venue_id);

        if (!is_wp_error($current_user) && $current_user[0] == $user_id) {
            return new WP_Error('user_venue_exists', __('User is already assigned to this venue.', 'bidfood'));
        }

        // Check if the user is already assigned to a venue
        if (self::is_user_venue($user_id)) {
            return new WP_Error('user_venue_exists', __('User is already assigned to a venue.', 'bidfood'));
        }
        
        if (!is_wp_error($current_user) && !empty($current_user) && $current_user[0] != $user_id) {
            $result = self::remove_user_from_venue($current_user[0]);
            if (is_wp_error($result)) {
                return $result;
            }

            if ($user_id == 0) {
                return true;
            }
        }

        global $wpdb;

        $result = $wpdb->insert(
            "{$wpdb->prefix}neom_user_venue_relation",
            [
                'user_id' => $user_id,
                'venue_id' => $venue_id,
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to assign user to venue.', 'bidfood'));
        }

        return true;
    }

    /**
     * Remove a user from a venue.
     *
     * @param int $user_id - The user ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function remove_user_from_venue($user_id) {
        global $wpdb;

        $result = $wpdb->delete(
            "{$wpdb->prefix}neom_user_venue_relation",
            ['user_id' => $user_id],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove user from venue.', 'bidfood'));
        }

        return true;
    }

    /**
     * Get all users assigned to a venue.
     *
     * @param string $venue_id - The venue ID.
     * @return array|WP_Error - Array of user IDs or WP_Error on failure.
     */
    public static function get_users_by_venue($venue_id) {
        global $wpdb;

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}neom_user_venue_relation WHERE venue_id = %s",
            $venue_id
        ));

        if (empty($user_ids)) {
            return new WP_Error('no_users', __('No users found for this venue.', 'bidfood'));
        }

        return $user_ids;
    }
}
