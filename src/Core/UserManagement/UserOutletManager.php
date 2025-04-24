<?php

namespace Bidfood\Core\UserManagement;

use WP_Error;

class UserOutletManager
{

    /**
     * Check if a user is assigned to an outlet.
     *
     * @param int $user_id - The user ID.
     * @param int $outlet_id - The outlet ID.
     * @return bool - True if the user is assigned to the outlet, false otherwise.
     */
    public static function is_user_assigned_to_outlet($user_id, $outlet_id)
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}neom_ch_outlet_users WHERE user_id = %d AND outlet_id = %d",
            $user_id,
            $outlet_id
        ));

        return $exists > 0;
    }

    /**
     * Get all users assigned to an outlet.
     *
     * @param int $outlet_id - The outlet ID.
     * @return array|WP_Error - Array of user IDs or WP_Error on failure.
     */
    public static function get_users_by_outlet($outlet_id)
    {
        global $wpdb;

        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}neom_ch_outlet_users WHERE outlet_id = %d",
            $outlet_id
        ));

        return !empty($user_ids) ? $user_ids : [];
    }

    /**
     * Assign users to an outlet.
     *
     * @param int $outlet_id - The outlet ID.
     * @param array $user_ids - Array of user IDs to assign.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_users_to_outlet($outlet_id, $user_ids)
    {
        global $wpdb;

        // Fetch currently assigned users for the outlet
        $current_users = self::get_users_by_outlet($outlet_id);

        // Assign new users
        foreach ($user_ids as $user_id) {
            if (!in_array($user_id, $current_users)) {
                $result = $wpdb->insert(
                    "{$wpdb->prefix}neom_ch_outlet_users",
                    [
                        'outlet_id' => $outlet_id,
                        'user_id' => $user_id,
                    ],
                    ['%d', '%d']
                );

                if ($result === false) {
                    return new WP_Error('db_error', __('Failed to assign user to outlet.', 'bidfood'));
                }
            }
        }

        // Remove unselected users
        foreach ($current_users as $user_id) {
            if (!in_array($user_id, $user_ids)) {
                $wpdb->delete(
                    "{$wpdb->prefix}neom_ch_outlet_users",
                    [
                        'outlet_id' => $outlet_id,
                        'user_id' => $user_id,
                    ],
                    ['%d', '%d']
                );
            }
        }

        return true;
    }

    /**
     * Get a group by its ID.
     *
     * @param string $field - The field to search by.
     * @param int $value - The value to search for.
     * @return string|WP_Error - Group ID or WP_Error if not found.
     */
    public static function get_group($field, $value){
        global $wpdb;

        $group_id = $wpdb->get_var($wpdb->prepare(
            "SELECT group_id FROM wp_neom_ch_groups WHERE $field = %d",
            $value
        ));

        if (empty($group_id)) {
            return new WP_Error('group_not_found', __('Group not found.', 'bidfood'));
        }

        return $group_id;
    }
    /**
     * Check if a group is assigned to an outlet.
     *
     * @param string $group_id - The group ID.
     * @return bool - True if the group is assigned to an outlet, false otherwise.
     */
    public static function is_group_outlet($group_id)
    {
        global $wpdb;

        $outlet_id = $wpdb->get_var($wpdb->prepare(
            "SELECT outlet_id FROM wp_neom_ch_outlets WHERE group_id = %d",
            $group_id
        ));

        return !empty($outlet_id);
    }
    /**
     * Get the outlet associated with a group.
     *
     * @param string $group_id - The group ID.
     * @return string|WP_Error - Outlet ID or WP_Error if not found.
     */
    public static function get_outlet_by_group($group_id)
    {
        global $wpdb;

        $outlet_id = $wpdb->get_var($wpdb->prepare(
            "SELECT outlet_id FROM wp_neom_ch_outlets WHERE group_id = %d",
            $group_id
        ));

        if (empty($outlet_id)) {
            return new WP_Error('no_group', __('No outlets found for this group.', 'bidfood'));
        }

        return $outlet_id;
    }
    /**
     * Assign a group to an outlet.
     *
     * @param int $outlet_id - The outlet ID.
     * @param int $group_id - The group ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function assign_group_to_outlet($outlet_id, $group_id)
    {
        // Check if the group exists
        if (!self::get_group('group_id', $group_id) && $group_id != 0) {
            return new WP_Error('group_not_found', __('group not found.', 'bidfood'));
        }

        global $wpdb;

        $result = $wpdb->update(
            "wp_neom_ch_outlets",
            [
                'group_id' => $group_id,
            ],
            [
                'outlet_id' => $outlet_id, // Correct WHERE clause
            ],
            [
                '%d', // Format for data being updated
            ],
            [
                '%d', // Format for WHERE clause
            ]
        );


        if ($result === false) {
            return new WP_Error('db_error', __('Failed to assign group to outlet.', 'bidfood'));
        }

        return true;
    }
    /**
     * Get all groups assigned to a outlet.
     *
     * @param string $outlet_id - The outlet ID.
     * @return array|WP_Error - Array of group IDs or WP_Error on failure.
     */
    public static function get_group_by_outlet($outlet_id)
    {
        global $wpdb;

        $group_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT group_id FROM wp_neom_ch_outlets WHERE outlet_id = %d",
            $outlet_id
        ));

        if (empty($group_ids)) {
            return new WP_Error('no_groups', __('No groups found for this outlet.', 'bidfood'));
        }

        return $group_ids;
    }

    /**
     * Get all groups.
     *
     * @return array|WP_Error - Array of group objects or WP_Error on failure.
     */
     public static function get_all_groups(){
        global $wpdb;

        $groups = $wpdb->get_results(
            "SELECT group_id, group_name FROM wp_neom_ch_groups"
        );
        if (is_wp_error($groups)) {
            return new WP_Error('db_error', __('Failed to get groups.', 'bidfood'));
        }
    
        return $groups;

     }
    /**
     * Remove the group assignment from an outlet.
     *
     * @param int $outlet_id - The outlet ID.
     * @return bool|WP_Error - True on success, WP_Error on failure.
     */
    public static function remove_group_from_outlet($outlet_id)
    {
        global $wpdb;

        $result = $wpdb->update(
            "wp_neom_ch_outlets",
            ['group_id' => null],
            ['outlet_id' => $outlet_id],
            ['%d'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', __('Failed to remove group from outlet.', 'bidfood'));
        }

        return true;
    }
}
