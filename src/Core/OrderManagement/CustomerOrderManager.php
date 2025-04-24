<?php
namespace Bidfood\Core\OrderManagement;

class CustomerOrderManager{
    public static function init(){
        return new self();
    }
    
    /**
     * Updates the order items confirmation status and notes.
     *
     * @param int $order_id
     * @param array $item_statuses
     * @param array $item_notes
     * @return bool|WP_Error
     */
    public static function update_order_items_confirmation($order_id, $item_statuses, $customer_confirmed_amounts) {
        global $wpdb;
        
        if (!$order_id || empty($item_statuses)) {
            return new \WP_Error('invalid_input', __('Invalid input data.', 'bidfood'));
        }

        $accepted_items = 0;
        $rejected_items = 0;
    
         // Process each item
        foreach ($item_statuses as $item_id => $status) {
            // Check if the status is valid and not empty
            if (empty($status) || !in_array($status, ['Confirmed', 'Rejected'])) {
                $status = 'Pending'; // Set status to Pending if it is invalid or empty
            }

            // Prepare the data for the update
            $data = [
                'status' => $status,
                'customer_confirmed_amount' => ($status === 'Confirmed') ? 
                    (int) ($customer_confirmed_amounts[$item_id] ?? 0) : 0 // Use the confirmed amount from input
            ];
 
            // Update driver order items status
            $result = $wpdb->update(
                $wpdb->prefix . 'neom_driver_order_items', // Updated table name
                $data,
                ['id' => $item_id],
                ['%s', '%f'],
                ['%d']
            );

            if ($result === false) {
                return new \WP_Error('db_error', __('Failed to update item status.', 'bidfood'));
            }

            // Count statuses
            if ($status === 'Confirmed') {
                $accepted_items++;
            } else if ($status === 'Rejected') {
                $rejected_items++;
            }
        }

        return true;
    }

    /**
     * Handles the order items confirmation form submission.
     *
     * @return void
     */
    public static function handle_order_items_confirmation() {
        if (!isset($_POST['save_confirmation']) || !isset($_POST['confirm_order_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['confirm_order_nonce'], 'confirm_order_items')) {
            wc_add_notice(__('Security check failed.', 'bidfood'), 'error');
            return;
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $item_statuses = isset($_POST['item_status']) ? (array) $_POST['item_status'] : array();
        $customer_confirmed_amounts = isset($_POST['customer_confirmed_amount']) ? (array) $_POST['customer_confirmed_amount'] : array();

        $result = self::update_order_items_confirmation($order_id, $item_statuses, $customer_confirmed_amounts);
        error_log(print_r($result,true));

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
        } else {
            wc_add_notice(__('Order items confirmed successfully.', 'bidfood'), 'success');
        }

        wp_safe_redirect(wc_get_endpoint_url('orders', '', wc_get_page_permalink('myaccount')));
        exit;
    }
}
?>