<?php

namespace Bidfood\Core\Events;

class OrderEvents {

    public function __construct() {
        // Action on order status change
        add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_change' ), 10, 3 );
        add_action( 'woocommerce_checkout_order_processed', array( $this,'order_status_placed' ), 10, 3 );
    }

    public static function init() {
        return new self();
    }

    // Action on order status change
    public static function order_status_change( $order_id, $old_status, $new_status ) {
        switch ( $new_status ) {
            case 'placed':
                self::order_status_placed( $order_id, $old_status, $new_status );
                break;
            case 'sent-to-suppliers':
                self::order_status_change_to_sent_to_suppliers( $order_id, $old_status, $new_status );
                break;
            
        }
    }

    // Status change to placed
    public static function order_status_placed( $order_id, $old_status, $new_status ) {
        // Send email to admins
        EmailEvents::send_admin_order_placed_email( $order_id );
        
        // Send email to customer
        EmailEvents::send_customer_order_placed_email( $order_id );
    }

    // Status change to sent to supplier
    public static function order_status_change_to_sent_to_suppliers( $order_id, $old_status, $new_status ) {
        EmailEvents::send_customer_po_initiated_email( $order_id );
    }

}