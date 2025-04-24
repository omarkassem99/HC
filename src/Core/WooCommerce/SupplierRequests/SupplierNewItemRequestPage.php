<?php

namespace Bidfood\Core\WooCommerce\SupplierRequests;

use Bidfood\Core\UserManagement\UserSupplierManager;

class SupplierNewItemRequestPage
{
    private $countries;

    public function __construct()
    {
        // Initialize the list of countries
        $this->countries = $this->get_fixed_countries_list();

        // Add menu item to WooCommerce "My Account"
        add_filter('woocommerce_account_menu_items', [$this, 'add_new_item_menu_item']);
        // Register endpoint for the page
        add_action('init', [$this, 'add_new_item_endpoint']);
        // Display content for the endpoint
        add_action('woocommerce_account_add-new-item_endpoint', [$this, 'supplier_item_requests_page_content']);
        // Enqueue necessary scripts and styles conditionally
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        // Handle AJAX request creation
        add_action('wp_ajax_create_supplier_item_request', [$this, 'ajax_create_item_request']);
        // Register AJAX action for fetching subcategories
        add_action('wp_ajax_fetch_subcategories', [$this, 'ajax_fetch_subcategories']);
        add_action('wp_ajax_nopriv_fetch_subcategories', [$this, 'ajax_fetch_subcategories']);
    }

    public static function init()
    {
        return new self();
    }

    public function add_new_item_menu_item($items)
    {
        if (UserSupplierManager::is_user_supplier(get_current_user_id())) {
            $items['add-new-item'] = __('Add New Item', 'bidfood');
        }
        return $items;
    }

    public function add_new_item_endpoint()
    {
        add_rewrite_endpoint('add-new-item', EP_ROOT | EP_PAGES);
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'supplier-add-item-requests-js',
            plugins_url('/assets/js/newItemsRequest/supplier-add-item-requests.js', dirname(__FILE__, 4)),
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'supplier-add-item-requests-css',
            plugins_url('/assets/css/newItemsRequest/supplier-add-item-requests.css', dirname(__FILE__, 4)),
            [],
            null
        );

        wp_localize_script('supplier-add-item-requests-js', 'supplierItemRequestsData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('supplier_item_requests_nonce'),
            'current_user_id' => get_current_user_id(),
        ]);
    }

    public function supplier_item_requests_page_content()
    {
        global $wpdb;
        $uoms = $wpdb->get_results("SELECT DISTINCT * FROM {$wpdb->prefix}neom_uom", ARRAY_A);

        // Fetch dropdown values from the database
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'parent' => 0
        ]);
        $brands = get_terms([
            'taxonomy' => 'pwb-brand',
            'hide_empty' => false,
        ]);
?>
        <h2><?php esc_html_e('Add New Item Request', 'bidfood'); ?></h2>
        <form id="add-item-request-form">
            <table class="form-table">
                <tr>
                    <th><label for="item_description"><?php esc_html_e('Item Description', 'bidfood'); ?> <span>*</span></label>
                    </th>
                    <td><input type="text" id="item_description" name="item_description" required class="regular-text"></td>
                </tr>
                <tr>
                    <th>
                        <label for="category"><?php esc_html_e('Category', 'bidfood'); ?> <span>*</span></label>
                    </th>
                    <td>
                        <select id="category_id" name="category_id" required>
                            <option value="">
                                <?php esc_html_e('Select Category', 'bidfood'); ?>
                            </option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo esc_attr($category->term_taxonomy_id); ?>">
                                    <?php echo esc_html($category->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="sub_category_id"><?php esc_html_e('Subcategory', 'bidfood'); ?> <span>*</span></label></th>
                    <td>
                        <select id="sub_category_id" name="sub_category_id" required>
                            <option value=""><?php esc_html_e('Select Subcategory', 'bidfood'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="country"><?php esc_html_e('Country', 'bidfood'); ?></label></th>
                    <td>
                        <select id="country" name="country" required>
                            <option value="">
                                <?php esc_html_e('Select Country', 'bidfood'); ?>
                            </option>
                            <?php foreach ($this->countries as $country): ?>
                                <option value="<?php echo esc_attr($country); ?>">
                                    <?php echo esc_html($country); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="uom_id"><?php esc_html_e('UOM (Unit of Measure)', 'bidfood'); ?> <span>*</span></label>
                    </th>
                    <td>
                        <select id="uom_id" name="uom_id" required>
                            <option value=""><?php esc_html_e('Select UOM', 'bidfood'); ?></option>
                            <?php foreach ($uoms as $uom): ?>
                                <option value="<?php echo esc_attr($uom['uom_id']); ?>">
                                    <?php echo esc_html($uom['uom_description']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="packing"><?php esc_html_e('Packing', 'bidfood'); ?></label></th>
                    <td><input type="text" id="packing" name="packing" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="brand"><?php esc_html_e('Brand', 'bidfood'); ?></label></th>
                    <td>
                        <select id="brand_id" name="brand" required>
                            <option value="">
                                <?php esc_html_e('Select Brand', 'bidfood'); ?>
                            </option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?php echo esc_attr($brand->name); ?>">
                                    <?php echo esc_html($brand->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="supplier_notes"><?php esc_html_e('Supplier Notes', 'bidfood'); ?></label></th>
                    <td><textarea type="text" id="supplier_notes" name="supplier_notes" class="regular-text"></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <button type="button" id="submit-request-btn" class="button"><?php esc_html_e('Submit Request', 'bidfood'); ?></button>
            </p>
        </form>
<?php
    }

    public function ajax_create_item_request()
{
    // Verify nonce for security
    check_ajax_referer('supplier_item_requests_nonce', 'nonce');

    // Ensure the user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('You must be logged in to submit a request.', 'bidfood')]);
    }

    // Get the current supplier ID
    $supplier_id = UserSupplierManager::get_supplier_by_user(get_current_user_id());

    // Sanitize and validate form inputs
    $item_description = sanitize_text_field($_POST['item_description']);
    $category_id = sanitize_text_field($_POST['category_id']);
    $sub_category_id = sanitize_text_field($_POST['sub_category_id']);
    $country = sanitize_text_field($_POST['country']);
    $uom_id = sanitize_text_field($_POST['uom_id']);
    $packing = sanitize_text_field($_POST['packing']);
    $brand = sanitize_text_field($_POST['brand']);
    $supplier_notes = sanitize_textarea_field($_POST['supplier_notes']);

    if (empty($item_description) || empty($category_id) || empty($sub_category_id) || empty($uom_id)) {
        wp_send_json_error(['message' => __('Please fill in all required fields.', 'bidfood')]);
    }

    $request_id = UserSupplierManager::submit_supplier_new_item_request(
        $supplier_id,
        $item_description,
        $category_id,
        $sub_category_id,
        $country,
        $uom_id,
        $packing,
        $brand,
        $supplier_notes
    );

    if (is_wp_error($request_id)) {
        wp_send_json_error(['message' => $request_id->get_error_message()]);
    }

    // Trigger the supplier new item request initiated action
    do_action('bidfood_supplier_add_item_request_initiated', $request_id);

    // Send success response
    wp_send_json_success(['message' => __('New Item Add request submitted.', 'bidfood')]);
}
    public function ajax_fetch_subcategories()
    {
        // Verify nonce for security
        check_ajax_referer('supplier_item_requests_nonce', 'nonce');

        // Check if category ID is provided
        if (!isset($_POST['category_id']) || empty($_POST['category_id'])) {
            wp_send_json_error(['message' => __('Category ID is required.', 'bidfood')]);
        }

        $category_id = sanitize_text_field($_POST['category_id']);

        // Fetch subcategories for the given category
        $subcategories = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $category_id, // Get child categories of the selected parent
            'hide_empty' => false,
        ]);
        // Prepare response
        if (!empty($subcategories) && !is_wp_error($subcategories)) {
            $response = array_map(function ($subcategory) {
                return [
                    'id' => $subcategory->term_id,
                    'name' => $subcategory->name,
                ];
            }, $subcategories);

            wp_send_json_success(['subcategories' => $response]);
        } else {
            wp_send_json_error(['message' => __('No subcategories found.', 'bidfood')]);
            $subcategories = null;
        }

        wp_die();
    }

    private function get_fixed_countries_list()
    {
        return [
            'Afghanistan',
            'Albania',
            'Algeria',
            'Andorra',
            'Angola',
            'Antigua and Barbuda',
            'Argentina',
            'Armenia',
            'Australia',
            'Austria',
            'Azerbaijan',
            'Bahamas',
            'Bahrain',
            'Bangladesh',
            'Barbados',
            'Belarus',
            'Belgium',
            'Belize',
            'Benin',
            'Bhutan',
            'Bolivia',
            'Bosnia and Herzegovina',
            'Botswana',
            'Brazil',
            'Brunei',
            'Bulgaria',
            'Burkina Faso',
            'Burundi',
            'Cabo Verde',
            'Cambodia',
            'Cameroon',
            'Canada',
            'Central African Republic',
            'Chad',
            'Chile',
            'China',
            'Colombia',
            'Comoros',
            'Congo, Democratic Republic of the',
            'Congo, Republic of the',
            'Costa Rica',
            'Croatia',
            'Cuba',
            'Cyprus',
            'Czech Republic',
            'Denmark',
            'Djibouti',
            'Dominica',
            'Dominican Republic',
            'East Timor',
            'Ecuador',
            'Egypt',
            'El Salvador',
            'Equatorial Guinea',
            'Eritrea',
            'Estonia',
            'Eswatini',
            'Ethiopia',
            'Fiji',
            'Finland',
            'France',
            'Gabon',
            'Gambia',
            'Georgia',
            'Germany',
            'Ghana',
            'Greece',
            'Grenada',
            'Guatemala',
            'Guinea',
            'Guinea-Bissau',
            'Guyana',
            'Haiti',
            'Honduras',
            'Hungary',
            'Iceland',
            'India',
            'Indonesia',
            'Iran',
            'Iraq',
            'Ireland',
            'Israel',
            'Italy',
            'Ivory Coast',
            'Jamaica',
            'Japan',
            'Jordan',
            'Kazakhstan',
            'Kenya',
            'Kiribati',
            'Kosovo',
            'Kuwait',
            'Kyrgyzstan',
            'Laos',
            'Latvia',
            'Lebanon',
            'Lesotho',
            'Liberia',
            'Libya',
            'Liechtenstein',
            'Lithuania',
            'Luxembourg',
            'Madagascar',
            'Malawi',
            'Malaysia',
            'Maldives',
            'Mali',
            'Malta',
            'Marshall Islands',
            'Mauritania',
            'Mauritius',
            'Mexico',
            'Micronesia',
            'Moldova',
            'Monaco',
            'Mongolia',
            'Montenegro',
            'Morocco',
            'Mozambique',
            'Myanmar',
            'Namibia',
            'Nauru',
            'Nepal',
            'Netherlands',
            'New Zealand',
            'Nicaragua',
            'Niger',
            'Nigeria',
            'North Korea',
            'North Macedonia',
            'Norway',
            'Oman',
            'Pakistan',
            'Palau',
            'Palestine',
            'Panama',
            'Papua New Guinea',
            'Paraguay',
            'Peru',
            'Philippines',
            'Poland',
            'Portugal',
            'Qatar',
            'Romania',
            'Russia',
            'Rwanda',
            'Saint Kitts and Nevis',
            'Saint Lucia',
            'Saint Vincent and the Grenadines',
            'Samoa',
            'San Marino',
            'Sao Tome and Principe',
            'Saudi Arabia',
            'Senegal',
            'Serbia',
            'Seychelles',
            'Sierra Leone',
            'Singapore',
            'Slovakia',
            'Slovenia',
            'Solomon Islands',
            'Somalia',
            'South Africa',
            'South Korea',
            'South Sudan',
            'Spain',
            'Sri Lanka',
            'Sudan',
            'Suriname',
            'Sweden',
            'Switzerland',
            'Syria',
            'Taiwan',
            'Tajikistan',
            'Tanzania',
            'Thailand',
            'Togo',
            'Tonga',
            'Trinidad and Tobago',
            'Tunisia',
            'Turkey',
            'Turkmenistan',
            'Tuvalu',
            'Uganda',
            'Ukraine',
            'United Arab Emirates',
            'United Kingdom',
            'United States',
            'Uruguay',
            'Uzbekistan',
            'Vanuatu',
            'Vatican City',
            'Venezuela',
            'Vietnam',
            'Yemen',
            'Zambia',
            'Zimbabwe'
        ];
    }
}
