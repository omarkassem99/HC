<?php

namespace Bidfood\Core\Events;

use Bidfood\Core\UserManagement\UserSupplierManager;
class POEvents {

    public function __construct() {
        /* ------------------- Actions ------------------- */
        // Action on PO Initiated
        add_action('bidfood_po_initiated', [$this, 'po_initiated']);

        // Action on PO Submitted
        add_action('bidfood_po_submitted', [$this, 'po_submitted']);
    }

    public static function init() {
        return new self();
    }

    // Action on PO Initiated
    public static function po_initiated($po_id) {
        // Change the status of the PO orders to 'sent-to-suppliers'
        self::change_po_orders_status($po_id, 'sent-to-suppliers');

        // Call the email event to send the PO initiated email
        do_action('bidfood_po_initiated_emails', $po_id);
    }

    // Action on PO Submitted
    public static function po_submitted($po_id) {
        // Call the action to set the final expected delivery date to po items
        do_action('bidfood_po_items_set_expected_delivery_date', $po_id);
        // Call the action to add the expected delivery date to the order items meta
        do_action('bidfood_po_submitted_add_order_expected_delivery_date', $po_id);
        // Call the email event to send the PO submitted email
        do_action('bidfood_po_submitted_emails', $po_id);
    }

    private static function get_all_po_orders($po_id) {
        return UserSupplierManager::get_supplier_po_orders($po_id);
    }

    private static function change_po_orders_status($po_id, $status) {
        $orders_ids = self::get_all_po_orders($po_id);

        foreach ($orders_ids as $order_id) {
            $wc_order = wc_get_order($order_id);
            
            if ($wc_order->get_status() !== $status) {
                $wc_order->update_status($status);
            }
        }
    }
}