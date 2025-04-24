<?php

namespace Bidfood\Core\WooCommerce;
class CustomOrderStatuses {

    public function __construct() {
        // Register custom order statuses
        add_action( 'init', array( $this, 'register_custom_order_statuses' ) );
        // Add custom order statuses to WooCommerce order list
        add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses_to_order_list' ) );
        // Add custom styles to admin for order statuses
        add_action( 'admin_head', array( $this, 'add_custom_status_colors' ) );
        // Treat specific custom statuses as paid
        add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'mark_custom_statuses_as_paid' ) );
        // Set Placed as the default order status after checkout
        add_action( 'woocommerce_default_order_status', array( $this, 'set_default_order_status' ) );
    }

    public static function init() {
        return new self();
    }

    // Register custom order statuses
    public static function register_custom_order_statuses() {
        $statuses = array(
            'wc-placed'                => 'Placed',
            'wc-sent-to-suppliers'     => 'Sent to Suppliers',
            'wc-received-at-bf-wh'     => 'Received at BF WH',
            'wc-ready-for-deliver'     => 'Ready for Delivery',
            'wc-delivered'              => 'Delivered',
            'wc-on-route'              => 'On Route',
            'wc-arrived-at-neom'       => 'Arrived at Neom'
        );

        foreach ( $statuses as $status_slug => $status_label ) {
            register_post_status( $status_slug, array(
                'label'                     => $status_label,
                'public'                    => true,
                'exclude_from_search'        => false,
                'show_in_admin_all_list'     => true,
                'show_in_admin_status_list'  => true,
                'label_count'               => _n_noop( $status_label . ' <span class="count">(%s)</span>', $status_label . ' <span class="count">(%s)</span>' )
            ) );
        }
    }

    // Add custom order statuses to the WooCommerce order list
    public static function add_custom_statuses_to_order_list( $order_statuses ) {
        $custom_statuses = array(
            'wc-placed'                => 'Placed',
            'wc-sent-to-suppliers'     => 'Sent to Suppliers',
            'wc-received-at-bf-wh'     => 'Received at BF WH',
            'wc-ready-for-deliver'     => 'Ready for Delivery',
            'wc-delivered'              => 'Delivered',
            'wc-on-route'              => 'On Route',
            'wc-arrived-at-neom'       => 'Arrived at Neom'
        );

        return array_merge( $order_statuses, $custom_statuses );
    }

    // Add custom color styling for the custom statuses in the admin
    public static function add_custom_status_colors() {
        echo '<style>
            .order-status.status-placed { background: #d1ecf1; color: #0c5460; }  /* Cyan */
            .order-status.status-sent-to-suppliers { background: #fff3cd; color: #856404; }  /* Yellow */
            .order-status.status-received-at-bf-wh { background: #d4edda; color: #155724; }  /* Green */
            .order-status.status-ready-for-deliver { background: #f8e71c; color: #212529; }  /* Orange */
            .order-status.status-delivered { background: #fff59d; color: #6c757d; }  /* Light Yellow */
            .order-status.status-on-route { background: #cce5ff; color: #004085; }  /* Blue */
            .order-status.status-arrived-at-neom { background: #cfe2f3; color: #2a3f54; }  /* Light Blue */
        </style>';
    }

    // Treat certain custom order statuses as "paid"
    public static function mark_custom_statuses_as_paid( $paid_statuses ) {
        $paid_statuses[] = 'wc-placed';
        $paid_statuses[] = 'wc-sent-to-suppliers';
        $paid_statuses[] = 'wc-received-at-bf-wh';
        $paid_statuses[] = 'wc-ready-for-deliver';
        $paid_statuses[] = 'wc-delivered';
        $paid_statuses[] = 'wc-on-route';
        $paid_statuses[] = 'wc-arrived-at-neom';

        return $paid_statuses;
    }

    // Set Placed as the default order status after checkout
    public static function set_default_order_status( $status ) {
        return 'wc-placed';
    }
}