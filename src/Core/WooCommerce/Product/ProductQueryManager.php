<?php

namespace Bidfood\Core\WooCommerce\Product;

use Bidfood\Core\Database\CRUD;
class ProductQueryManager {

    public function __construct() {
        return;
    }
    
    public static function init() {
        return new self();
    }

    /**
     * Get product UOM
     * @param int $product_id
     */
    public static function get_product_uom($product_id) {
        global $wpdb;

        $uom_table_name = $wpdb->prefix . 'neom_uom';

        $product = wc_get_product($product_id);

        if (!$product) {
            return null;
        }

        $attributes = $product->get_attributes();

        if (empty($attributes)) {
            return null;
        }

        $uom = null;
        if ( isset( $attributes['uom'] ) ) {
            $uom = $attributes['uom'];
            
            // Get the attribute value
            $uom_value = $uom->get_options()[0];

            // Crud object to fetch uom details
            $uom = CRUD::find_record( $uom_table_name, ['uom_id' => $uom_value] );
        }

        return $uom;
    }

}
