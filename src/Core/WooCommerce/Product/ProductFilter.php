<?php

namespace Bidfood\Core\WooCommerce\Product;

use Bidfood\Core\Database\CRUD;

class ProductFilter {
    public function __construct() {
        // Hook to initialize the taxonomies
        add_action('init', [$this, 'register_custom_taxonomies']);

        // Hook to update product taxonomies on save
        add_action('woocommerce_process_product_meta', [$this, 'update_product_taxonomies'], 10, 1);

        // Hooks to update product attributes on taxonomy change
        add_action('set_object_terms', [$this, 'custom_taxonomy_update_checker'], 10, 4);

        /* Admin Panel */
        add_action('restrict_manage_posts', [$this, 'add_custom_taxonomy_filters_to_products']);
        add_action('pre_get_posts', [$this, 'apply_custom_taxonomy_filters_to_query']);

        /* User Panel */
        add_action('woocommerce_before_shop_loop', [$this, 'add_custom_filters_to_shop_page'], 15);
        add_action('pre_get_posts', [$this, 'filter_products_by_custom_taxonomies']);
        add_action('woocommerce_product_query', [$this, 'modify_shop_page_query']);
    }

    public static function init() {
        return new self();
    }

    public static function register_custom_taxonomies() {
        // List of taxonomies to register
        $taxonomies = [
            'pa_preferred_supplier_id' => __('Preferred Supplier ID', 'woocommerce'),
            'pa_alternative_supplier_id' => __('Alternative Supplier ID', 'woocommerce'),
            'pa_temperature' => __('Temperature', 'woocommerce'),
            'pwb-brand' => __('Brand', 'woocommerce'),
            'pa_country' => __('Country', 'woocommerce'),
            'pa_uom' => __('Unit of Measure', 'woocommerce'),
            'pa_moq' => __('Minimum Order Quantity', 'woocommerce'),
        ];
    
        // Register taxonomies if they don't already exist
        foreach ($taxonomies as $taxonomy => $label) {
            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy(
                    $taxonomy,
                    'product',
                    [
                        'label'        => $label,
                        'public'       => true, // Not accessible publicly
                        'hierarchical' => false,
                        'show_ui'      => true, // Visible in the admin UI
                        'show_admin_column' => true, // Add taxonomy to product admin table filters
                        'show_in_menu' => true, // Show in the admin menu
                        'show_in_nav_menus' => true, // Hide from navigation menus
                        'show_in_quick_edit' => false, // Hide from quick edit
                        'rewrite'      => false, // Disable rewrites
                        'capabilities' => [
                            'manage_terms' => 'manage_options',
                            'edit_terms'   => 'manage_options',
                            'delete_terms' => 'manage_options',
                            'assign_terms' => 'edit_products',
                        ],
                    ]
                );
            }
        }
    }
    public static function update_taxonomies($object_id, $terms_data) {
        // Update terms for the given object
        foreach ($terms_data as $taxonomy => $new_term) {
            if (!taxonomy_exists($taxonomy)) {
                error_log("Taxonomy {$taxonomy} does not exist.");
                continue;
            }
    
            // Retrieve existing terms for the object
            $existing_terms = wp_get_object_terms($object_id, $taxonomy, ['fields' => 'names']);
    
            // Check for WP_Error and log it
            if (is_wp_error($existing_terms)) {
                error_log('Error retrieving terms for taxonomy ' . $taxonomy . ': ' . $existing_terms->get_error_message());
                continue;
            }
    
            // Check if the new term is different from the existing ones
            if (!in_array($new_term, $existing_terms, true)) {
                // Remove all existing terms
                wp_set_object_terms($object_id, null, $taxonomy);
    
                // Assign the new term
                wp_set_object_terms($object_id, $new_term, $taxonomy);
            }
        }
    }
    
    public function update_product_taxonomies($post_id) {
        $terms_data = [
            'pa_preferred_supplier_id' => sanitize_text_field($_POST['preferred_supplier_id'] ?? ''),
            'pa_alternative_supplier_id' => sanitize_text_field($_POST['alternative_supplier_id'] ?? ''),
            'pa_temperature' => sanitize_text_field($_POST['temperature'] ?? ''),
            'pwb-brand' => sanitize_text_field($_POST['brand'] ?? ''),
            'pa_country' => sanitize_text_field($_POST['country'] ?? ''),
        ];
    
        // Call the function to register and update taxonomies
        $this::update_taxonomies($post_id, $terms_data);
    }

    public static function update_product_attributes_on_taxonomy_change($object_id, $terms, $tt_ids, $taxonomy) {
        // Mapping of taxonomies to product attributes
        $taxonomy_to_attribute = [
            'pa_temperature' => 'temperature',
            'pa_uom' => 'uom',
            'pa_country' => 'country',
            'pa_moq' => 'moq',
            'pa_preferred_supplier_id' => 'preferred_supplier_id',
            'pa_alternative_supplier_id' => 'alternative_supplier_id',
        ];
    
        // Check if the taxonomy corresponds to a product attribute
        if (!array_key_exists($taxonomy, $taxonomy_to_attribute)) {
            return;
        }
    
        $attribute_name = $taxonomy_to_attribute[$taxonomy];
    
        // Load the product
        $product = wc_get_product($object_id);
        if (!$product) {
            error_log("Product with ID {$object_id} not found.");
            return;
        }
    
        $attributes = $product->get_attributes();
    
        // If no terms are assigned, remove the attribute
        if (empty($terms)) {
            unset($attributes[$attribute_name]);
        } else {
            // Set or update the attribute
            $attribute = new \WC_Product_Attribute();
            $attribute->set_name($attribute_name);
            $attribute->set_options(array_map('strval', $terms)); // Convert term IDs to strings
            $attribute->set_visible(false);
            $attribute->set_variation(false);
    
            $attributes[$attribute_name] = $attribute;
        }
    
        // Update product attributes
        $product->set_attributes($attributes);
        $product->save();
    }
    

    public static function custom_taxonomy_update_checker($object_id, $terms, $tt_ids, $taxonomy){
        if (in_array($taxonomy, ['pa_temperature', 'pa_uom', 'pa_country', 'pa_moq', 'pa_preferred_supplier_id', 'pa_alternative_supplier_id'], true)) {
            // Update the product attributes
            self::update_product_attributes_on_taxonomy_change($object_id, $terms, $tt_ids, $taxonomy);
        }
    }
    
    /* Admin panel */
    public static function add_custom_taxonomy_filters_to_products() {
        global $typenow;

        // Only add filters on the products page
        if ($typenow !== 'product') {
            return;
        }

        // List of remaining taxonomies to filter
        $taxonomies = [
            'pa_preferred_supplier_id' => __('Preferred Supplier ID', 'woocommerce'),
            'pa_alternative_supplier_id' => __('Alternative Supplier ID', 'woocommerce'),
            'pa_temperature' => __('Temperature', 'woocommerce'),
            'pwb-brand' => __('Brand', 'woocommerce'),
            'pa_country' => __('Country', 'woocommerce'),
        ];

        foreach ($taxonomies as $taxonomy => $label) {
            // Ensure taxonomy exists
            if (taxonomy_exists($taxonomy)) {
                // Get all terms for the taxonomy
                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                ]);

                if (!is_wp_error($terms) && !empty($terms)) {
                    // Output the dropdown filter
                    echo '<select name="' . esc_attr($taxonomy) . '" id="' . esc_attr($taxonomy) . '" class="postform">';
                    echo '<option value="">' . esc_html($label) . '</option>';

                    foreach ($terms as $term) {
                        $selected = isset($_GET[$taxonomy]) && $_GET[$taxonomy] == $term->slug ? ' selected="selected"' : '';
                        echo '<option value="' . esc_attr($term->slug) . '"' . $selected . '>' . esc_html($term->name) . '</option>';
                    }

                    echo '</select>';
                }
            }
        }
    }

    public static function apply_custom_taxonomy_filters_to_query($query) {
        global $pagenow, $typenow;

        // Ensure we're filtering products in the admin
        if ($pagenow === 'edit.php' && $typenow === 'product') {
            $taxonomies = [
                'pa_preferred_supplier_id',
                'pa_alternative_supplier_id',
                'pa_temperature',
                'pwb-brand',
                'pa_country',
            ];

            foreach ($taxonomies as $taxonomy) {
                if (!empty($_GET[$taxonomy])) {
                    $query->query_vars[$taxonomy] = sanitize_text_field($_GET[$taxonomy]);
                }
            }
        }
    }

    /* User panel */
    public static function add_custom_filters_to_shop_page() {
        global $wpdb;
    
        if (!is_shop() && !is_product_category() && !is_product_taxonomy()) {
            return;
        }
    
        // List of taxonomies to filter
        $taxonomies = [
            'pa_supplier' => __('Supplier', 'woocommerce'), // Unified supplier filter
            'pa_temperature' => __('Temperature', 'woocommerce'),
            'pwb-brand' => __('Brand', 'woocommerce'),
            'pa_country' => __('Country', 'woocommerce'),
        ];
    
        // Database table names
        $temperature_table_name = $wpdb->prefix . 'neom_temperature';
        $supplier_table_name = $wpdb->prefix . 'neom_supplier';
    
        echo '<div class="custom-shop-filters" style="margin-bottom: 20px;">';
    
        foreach ($taxonomies as $taxonomy => $label) {
            if ($taxonomy === 'pa_supplier') {
                // Unified Supplier Filter
                $preferred_terms = taxonomy_exists('pa_preferred_supplier_id') ? get_terms([
                    'taxonomy' => 'pa_preferred_supplier_id',
                    'hide_empty' => true,
                ]) : [];
    
                $alternative_terms = taxonomy_exists('pa_alternative_supplier_id') ? get_terms([
                    'taxonomy' => 'pa_alternative_supplier_id',
                    'hide_empty' => true,
                ]) : [];
    
                // Merge terms and fetch supplier names
                $all_terms = array_merge($preferred_terms, $alternative_terms);
    
                $unique_terms = [];
                foreach ($all_terms as $term) {
                    if (!isset($unique_terms[$term->slug])) {
                        // Fetch supplier name
                        $where = ['supplier_id' => $term->slug];
                        $supplier_record = CRUD::find_record($supplier_table_name, $where);
    
                        $unique_terms[$term->slug] = $supplier_record ? $supplier_record->supplier_name : $term->name;
                    }
                }
    
                if (!empty($unique_terms)) {
                    echo '<select name="pa_supplier" id="pa_supplier" class="woocommerce-product-filter">';
                    echo '<option value="">' . esc_html($label) . '</option>';
    
                    foreach ($unique_terms as $slug => $name) {
                        $selected = isset($_GET['pa_supplier']) && $_GET['pa_supplier'] == $slug ? ' selected="selected"' : '';
                        echo '<option value="' . esc_attr($slug) . '"' . $selected . '>' . esc_html($name) . '</option>';
                    }
    
                    echo '</select>';
                }
            } elseif ($taxonomy === 'pa_temperature') {
                // Temperature Filter
                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'hide_empty' => true,
                ]);
    
                if (!is_wp_error($terms) && !empty($terms)) {
                    echo '<select name="pa_temperature" id="pa_temperature" class="woocommerce-product-filter">';
                    echo '<option value="">' . esc_html($label) . '</option>';
    
                    foreach ($terms as $term) {
                        // Fetch temperature description
                        $where = ['temperature_id' => $term->slug];
                        $temperature_record = CRUD::find_record($temperature_table_name, $where);
    
                        $description = $temperature_record ? $temperature_record->temperature_description : $term->name;
    
                        $selected = isset($_GET['pa_temperature']) && $_GET['pa_temperature'] == $term->slug ? ' selected="selected"' : '';
                        echo '<option value="' . esc_attr($term->slug) . '"' . $selected . '>' . esc_html($description) . '</option>';
                    }
    
                    echo '</select>';
                }
            } elseif ($taxonomy === 'pwb-brand') {
                // Unit of Measure Filter
                $terms = get_terms([
                    'taxonomy' => $taxonomy,
                    'hide_empty' => true,
                ]);
    
                if (!is_wp_error($terms) && !empty($terms)) {
                    echo '<select name="pwb-brand" id="pwb-brand" class="woocommerce-product-filter">';
                    echo '<option value="">' . esc_html($label) . '</option>';
    
                    foreach ($terms as $term) {
                        $description = $term->name;
    
                        $selected = isset($_GET['pwb-brand']) && $_GET['pwb-brand'] == $term->slug ? ' selected="selected"' : '';
                        echo '<option value="' . esc_attr($term->slug) . '"' . $selected . '>' . esc_html($description) . '</option>';
                    }
    
                    echo '</select>';
                }
            } else {
                // Default handling for other taxonomies
                if (taxonomy_exists($taxonomy)) {
                    $terms = get_terms([
                        'taxonomy' => $taxonomy,
                        'hide_empty' => true,
                    ]);
    
                    if (!is_wp_error($terms) && !empty($terms)) {
                        echo '<select name="' . esc_attr($taxonomy) . '" id="' . esc_attr($taxonomy) . '" class="woocommerce-product-filter">';
                        echo '<option value="">' . esc_html($label) . '</option>';
    
                        foreach ($terms as $term) {
                            $selected = isset($_GET[$taxonomy]) && $_GET[$taxonomy] == $term->slug ? ' selected="selected"' : '';
                            echo '<option value="' . esc_attr($term->slug) . '"' . $selected . '>' . esc_html($term->name) . '</option>';
                        }
    
                        echo '</select>';
                    }
                }
            }
        }
    
        // Add "Apply Filters" and "Clear Filters" buttons
        echo '<div class="custom-filters-container">';
        echo '<button id="apply-filters" class="button">' . __('Apply Filters', 'woocommerce') . '</button>';
        echo '<button id="clear-filters" class="button">' . __('Clear Filters', 'woocommerce') . '</button>';
        echo '</div>';
        
    
        echo '</div>';
    
        // Add JavaScript for filter functionality
        ?>
        <script>
            document.getElementById('apply-filters').addEventListener('click', function() {
                let params = new URLSearchParams(window.location.search);
                document.querySelectorAll('.woocommerce-product-filter').forEach(function(filter) {
                    const value = filter.value;
                    if (value) {
                        params.set(filter.name, value);
                    } else {
                        params.delete(filter.name);
                    }
                });
                window.location.search = params.toString();
            });
    
            document.getElementById('clear-filters').addEventListener('click', function() {
                // Clear only custom taxonomy filters
                let params = new URLSearchParams(window.location.search);

                // List of custom taxonomies to clear
                const taxonomies = [
                    'pa_supplier',
                    'pa_temperature',
                    'pwb-brand',
                    'pa_country',
                ];

                taxonomies.forEach(function(taxonomy) {
                    params.delete(taxonomy);
                });

                window.location.search = params.toString();
            });
        </script>
        <?php
    }
    
    
    public static function filter_products_by_custom_taxonomies($query) {
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_taxonomy())) {
            $taxonomies = [
                'pa_temperature' => 'pa_temperature',
                'pwb-brand' => 'pwb-brand',
                'pa_country' => 'pa_country',
            ];
    
            $tax_query = [];
    
            // Add other taxonomy filters
            foreach ($taxonomies as $key => $taxonomy) {
                if (!empty($_GET[$key])) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field'    => 'slug',
                        'terms'    => sanitize_text_field($_GET[$key]),
                    ];
                }
            }
    
            // Handle unified supplier filter
            if (isset($_GET['pa_supplier']) && !empty($_GET['pa_supplier'])) {
                $supplier_filter = sanitize_text_field($_GET['pa_supplier']);
    
                // Add conditions for both preferred and alternative suppliers
                $tax_query[] = [
                    'relation' => 'OR',
                    [
                        'taxonomy' => 'pa_preferred_supplier_id',
                        'field'    => 'slug',
                        'terms'    => $supplier_filter,
                    ],
                    [
                        'taxonomy' => 'pa_alternative_supplier_id',
                        'field'    => 'slug',
                        'terms'    => $supplier_filter,
                    ],
                ];
            }
    
            // Add the relation between all filters (AND)
            if (!empty($tax_query)) {
                $query->set('tax_query', [
                    'relation' => 'AND', // Ensures all filters are applied together
                    ...$tax_query,
                ]);
            }
        }
    }
    
    public static function modify_shop_page_query($query) {
        if (!is_admin() && $query->is_main_query() && (is_shop() || is_product_category() || is_product_taxonomy())) {
            $tax_query = $query->get('tax_query', []);
    
            // Handle unified supplier filter
            if (isset($_GET['pa_supplier']) && !empty($_GET['pa_supplier'])) {
                $supplier_filter = sanitize_text_field($_GET['pa_supplier']);
    
                // Add conditions for both preferred and alternative suppliers
                $tax_query[] = [
                    'relation' => 'OR',
                    [
                        'taxonomy' => 'pa_preferred_supplier_id',
                        'field'    => 'slug',
                        'terms'    => $supplier_filter,
                    ],
                    [
                        'taxonomy' => 'pa_alternative_supplier_id',
                        'field'    => 'slug',
                        'terms'    => $supplier_filter,
                    ],
                ];
            }
    
            // Add the relation between all filters (AND)
            $existing_tax_query = $query->get('tax_query', []);
            $query->set('tax_query', [
                'relation' => 'AND',
                ...$existing_tax_query,
                ...$tax_query,
            ]);
        }
    }

}