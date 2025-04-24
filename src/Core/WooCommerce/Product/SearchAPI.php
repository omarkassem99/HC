<?php
namespace Bidfood\Core\WooCommerce\Product;

use DgoraWcas\Helpers;
use DgoraWcas\Engines\TNTSearchMySQL\Indexer\Builder;
use DgoraWcas\Engines\TNTSearchMySQL\SearchQuery\AjaxQuery;
use DgoraWcas\Multilingual;

class SearchAPI {

    /**
     * Base namespace for the endpoint.
     */
    private const NAMESPACE = 'bidfoodme/v1';

    /**
     * Route path for product search.
     */
    private const BASE_PATH = '/product/search';


    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));

        if ( ! defined( 'DGWT_SEARCH_START' ) ) {
            define( 'DGWT_SEARCH_START', microtime( true ) );
        }

        if ( ! defined( 'DGWT_WCAS_DOING_SEARCH' ) ) {
            define( 'DGWT_WCAS_DOING_SEARCH', true );
        }
    }

    public static function init() {
        return new self();
    }

    public static function get_search_endpoint(){
        return '/wp-json/'. self::NAMESPACE. self::BASE_PATH;
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE, self::BASE_PATH, array(
            'methods' => 'GET',
            'callback' => array($this, 'handle_search_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_search_request(\WP_REST_Request $request) {
        $phrase = sanitize_text_field($request->get_param('s') ?? '');
        
        if (empty($phrase)) {
            return new \WP_REST_Response(['error' => 'Search term cannot be empty'], 400);
        }

        $lang = sanitize_text_field($request->get_param('l'));
        $lang = ! empty( $lang ) && Multilingual::isLangCode( $lang ) ? $lang : '';

        // Send empty response if language is invalid
        $languages = Builder::getInfo( 'languages' );
        if ( ( ! empty( $languages ) && ! in_array( $lang, $languages ) ) || ( empty( $languages ) && ! empty( $lang ) ) ) {
            $l = isset( $languages[0] ) ? $languages[0] : '';
            if ( Builder::getInfo( 'status' ) !== 'completed' || ! Builder::isIndexValid( $l ) ) {
                AjaxQuery::sendEmptyResponse( 'free' );
            } else {
                AjaxQuery::sendEmptyResponse( 'pro' );
            }
        }

        // Initialize this object early to load user files with custom code snippets, eg. filters.
        $query = new AjaxQuery();

        // Break early if keyword contains blacklisted phrase.
        if ( Helpers::phraseContainsBlacklistedTerm( $phrase ) ) {
            AjaxQuery::sendEmptyResponse( 'pro' );
        }

        if ( ! Builder::searchableCacheExists( $lang ) ) {
            add_filter( 'dgwt/wcas/tnt/search_cache', '__return_false', PHP_INT_MAX - 5 );
        }

        if ( empty( $phrase ) ) {
            AjaxQuery::sendEmptyResponse();
        }

        $query->setPhrase( $phrase );

        if ( ! empty( $lang ) ) {
            $query->setLang( $lang );
        }

        $query->searchProducts();
        $query->searchPosts();
        $query->searchTaxonomy();
        $query->searchVendors();

        if ( ! $query->hasResults() ) {
            do_action( 'dgwt/wcas/analytics/after_searching', $query->getPhrase(), 0, $query->getLang() );
            AjaxQuery::sendEmptyResponse();
        }

        $query->sendResults();
    }
}
