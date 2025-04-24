<?php

namespace Bidfood\Core\WooCommerce\Product;

use Bidfood\Core\Soundex\I18N_Arabic_Soundex; // Include the Arabic Soundex class

class CustomProductSearch {

    protected $fuzzy_matches = [];

    public function __construct() {
        return;
        // Remove WooCommerce's default product search handling
        remove_action('pre_get_posts', array(WC()->query, 'product_query'));
        remove_action('pre_get_posts', array(WC()->query, 'search_post_excerpt'));

        // Hook into 'pre_get_posts' with a higher priority
        add_action('pre_get_posts', array($this, 'modify_search_query'), 999);
    }

    public static function init() {
        return new self();
    }

    /**
     * Modify the WooCommerce product search query to include fuzzy search logic
     *
     * @param \WP_Query $query
     */
    public function modify_search_query($query) {
        // Ensure it's a frontend search query for products
        if ($query->is_search() && !is_admin() && $query->is_main_query()) {

            // Check if search term exists
            if (isset($query->query_vars['s'])) {
                $search_term = sanitize_text_field($query->query_vars['s']);

                // Perform fuzzy search using Levenshtein, Metaphone, or Arabic Soundex
                $this->fuzzy_matches = $this->fuzzy_search($search_term);

                // If we have fuzzy search results, modify the query
                if (!empty($this->fuzzy_matches)) {
                    // Modify the query to include only the matched product IDs
                    $query->set('post_type', 'product');
                    $query->set('post__in', $this->fuzzy_matches);
                    
                    // Remove the default search query to prevent conflicts
                    $query->set('s', '');
                } else {
                    // If no matches found, set post__in to an empty array to prevent any results
                    $query->set('post__in', array(0));
                }
            }
        }
    }

    /**
     * Perform a fuzzy search using Levenshtein, Metaphone, or Arabic Soundex algorithms
     *
     * @param string $search_term
     * @return array Array of product IDs that match the fuzzy search
     */
    public function fuzzy_search($search_term) {
        global $wpdb;

        // Initialize the Arabic Soundex class
        $arabic_soundex = new I18N_Arabic_Soundex();
        $arabic_soundex->setLang('ar'); // Set to Arabic

        // Fetch all product titles and IDs
        $query = "
            SELECT ID, post_title 
            FROM {$wpdb->posts} 
            WHERE post_type = 'product' 
            AND post_status = 'publish'
        ";
        $products = $wpdb->get_results($query);

        $fuzzy_matches = [];
        $levenshtein_limit = 3; // Define a threshold for Levenshtein distance
        $search_term_lower = mb_strtolower($search_term);

        // Check if the search term contains Arabic characters
        $is_arabic = preg_match('/[\p{Arabic}]/u', $search_term_lower);

        foreach ($products as $product) {
            $product_title = mb_strtolower($product->post_title);
            $title_words = explode(' ', $product_title);
            $found_match = false;

            // **Exact Match Check**: Check if the product title matches the search term exactly
            if ($product_title === $search_term_lower) {
                $fuzzy_matches[] = $product->ID;
                continue; // Move to the next product if exact match is found
            }

            // If search term is Arabic, use Arabic Soundex
            foreach ($title_words as $word) {
                if ($is_arabic) {
                    $word_soundex = $arabic_soundex->soundex($word);
                    $search_soundex = $arabic_soundex->soundex($search_term_lower);

                    // Check if the Soundex codes match
                    if ($word_soundex === $search_soundex) {
                        $fuzzy_matches[] = $product->ID;
                        $found_match = true;
                        break; // Stop checking once a match is found
                    }
                } else {
                    // If the term is English, use Levenshtein and Metaphone
                    if (preg_match('/^[\x00-\x7F]+$/', $search_term_lower) && preg_match('/^[\x00-\x7F]+$/', $word)) {
                        // Calculate Levenshtein distance for English terms
                        $levenshtein_distance = levenshtein($search_term_lower, $word);

                        if ($levenshtein_distance <= $levenshtein_limit) {
                            $fuzzy_matches[] = $product->ID;
                            $found_match = true;
                            break; // Stop checking once a match is found
                        }
                    }

                    // Check using Metaphone for similar sounding English words
                    if (preg_match('/^[\x00-\x7F]+$/', $search_term_lower) && metaphone($search_term_lower) === metaphone($word)) {
                        $fuzzy_matches[] = $product->ID;
                        $found_match = true;
                        break; // Stop checking once a match is found
                    }

                    // Check for partial string match using multibyte strpos
                    if (mb_strpos($word, $search_term_lower) !== false) {
                        $fuzzy_matches[] = $product->ID;
                        $found_match = true;
                        break; // Stop checking once a match is found
                    }
                }
            }

            // If a match is found, continue to the next product
            if ($found_match) {
                continue;
            }
        }

        return $fuzzy_matches;
    }
}
