<?php

namespace Bidfood\Core\WooCommerce\Product;

use Bidfood\Core\Database\CRUD;
class SingleProductInfoPage {

    public function __construct() {
        // Add custom attribute to single product page
        add_action( 'woocommerce_product_meta_end', array( $this, 'display_custom_attribute' ), 25 );
    }

    public static function init() {
        return new self();
    }

    public static function display_custom_attribute() {
        global $product;
        global $wpdb;
    
        $temperature_table_name = $wpdb->prefix . 'neom_temperature';
        $uom_table_name = $wpdb->prefix . 'neom_uom';
    
        $attributes = $product->get_attributes();
    
        // temperature, uom, preferred_brand, alternative_brand
        
        $brands = [];

        // Retrieve product brand terms directly
        $brand_terms = wp_get_post_terms( $product->get_id(), 'pwb-brand' );

        if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
            $brands = [];

            foreach ( $brand_terms as $brand_term ) {
                // Create a clickable link for each brand
                $brands[] = '<a href="' . esc_url( get_term_link( $brand_term ) ) . '">' . esc_html( $brand_term->name ) . '</a>';
            }
        }

        // Display the brands if available
        if ( ! empty( $brands ) ) {
            echo '<div class="posted_in">Brand: ' . implode( ', ', $brands ) . '</div>';
        }

        // Check if temperature is available as an attribute
        if ( isset( $attributes['temperature'] ) ) {
            $temperature = $attributes['temperature'];
            
            // Get the attribute value
            $temperature_value = $temperature->get_options()[0];
    
            // Crud object to fetch temperature details
            $fetched_temperature = CRUD::find_record( $temperature_table_name, ['temperature_id' => $temperature_value] );
    
            if ( ! empty( $fetched_temperature ) ) {
                echo '<div class="posted_in">Temperature: <a>' . $fetched_temperature->temperature_description . '</a></div>';
            }
        }
    
        // Check if UOM is available as an attribute
        if ( isset( $attributes['uom'] ) ) {
            $uom = $attributes['uom'];
            
            // Get the attribute value
            $uom_value = $uom->get_options()[0];
    
            // Crud object to fetch uom details
            $fetched_uom = CRUD::find_record( $uom_table_name, ['uom_id' => $uom_value] );
        
            if ( ! empty( $fetched_uom ) ) {
                echo '<div class="posted_in">UOM: <a>' . $fetched_uom->uom_description . '</a></div>';
            }
        }

        // Check if packaging per uom is available as an attribute
        if ( isset( $attributes['packaging_per_uom'] ) ) {
            $packaging = $attributes['packaging_per_uom'];
            
            // Get the attribute value
            $packaging_value = $packaging->get_options()[0];
    
            if ( ! empty( $packaging_value ) ) {
                echo '<div class="posted_in">Packaging: <a>' . $packaging_value . '</a></div>';
            }
        }

        // Check if country is available as an attribute
        if ( isset( $attributes['country'] ) ) {
            $country = $attributes['country'];
            
            // Get the attribute value
            $country_value = $country->get_options()[0];
    
            if ( ! empty( $country_value ) ) {
                echo '<div class="posted_in">Country: <a>' . $country_value . '</a></div>';
            }
        }
    }
    

}