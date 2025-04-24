<?php

namespace Bidfood\Admin\NeomSettings\SupplierProducts;

use Bidfood\UI\Toast\ToastHelper;

class ProductsImages
{
    public function __construct()
    {
        // Enqueue styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'bidfood_admin_enqueue_assets']);
        add_action('wp_ajax_handle_product_images_upload', [__CLASS__, 'handle_product_images_upload']);
    }

    public static function init()
    {
        return new self();
    }

    // Enqueue styles and scripts
    public function bidfood_admin_enqueue_assets()
    {
        // Enqueue styles
        wp_enqueue_style('admin-products-images-css', plugins_url('/assets/css/Products/products-images.css', dirname(__FILE__, 4)));
        // Enqueue scripts
        wp_enqueue_script('admin-products-images-js', plugins_url('/assets/js/Products/products-images.js', dirname(__FILE__, 4)), ['jquery'], null, true);

        // Localize script with nonce and AJAX URL
        wp_localize_script('admin-products-images-js', 'ProductsImagesData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('products_images_nonce'),
        ]);
    }

    public static function render()
    {
        self::render_ui();
    }

    private static function render_ui()
    {
?>
        <div class="wrap">
            <h1 style="text-align: center;"><?php _e('Upload Product Images', 'bidfood'); ?></h1>
            <p style="text-align: center;"><?php _e('Upload images for your products. The images should be named with the product SKU.', 'bidfood'); ?></p>
            <form style="text-align: center;" id="upload-form" method="post" enctype="multipart/form-data">
                <input type="file" name="product_images[]" id="product-images-input" multiple style="width:fit-content" />
                <input type="submit" id="upload-product-images-btn" name="upload_product_images" value="<?php _e('Upload Images', 'bidfood'); ?>" style="width:fit-content" class="button button-primary" disabled />
                <?php wp_nonce_field('upload_product_images_nonce', 'upload_product_images_nonce_field'); ?>
            </form>
            <div id="progress-container" style="height: fit-content; width: 100%; background: #e0e0e0; border-radius: 5px; margin-top: 20px; display: none; position: relative;">
                <div id="progress-bar" style="width: 0%; height: 30px; background: #6ABCEA; border-radius: 5px;"></div>
                <span id="progress-text" style="position: absolute; width: 100%; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; font-weight: bold; color: white;">0 / 0</span>
            </div>

            <div id="error-messages-container" style="max-height: 300px; overflow-y: auto; background: #f8d7da; color: #721c24; padding: 10px; margin-top: 20px; border: 1px solid #f5c6cb; border-radius: 5px; display: none; position: relative;">
                <button id="close-error-messages" style="position: absolute; top: 5px; right: 10px; background: transparent; border: none; font-size: 16px; font-weight: bold; cursor: pointer; color: #721c24;">X</button>
                <strong>Uploads Errors:</strong>
                <ul id="error-messages-list" style="list-style-type: none; padding: 0; margin: 0;"></ul>
            </div>


        </div>
<?php
    }

    public static function handle_product_images_upload()
    {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'products_images_nonce')) {
            wp_send_json_error(['message' => __('Invalid nonce verification', 'bidfood')]);
        }

        // Check for uploaded files
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'bidfood')]);
        }

        $file = $_FILES['file'];
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        $filename = $file['name'];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            wp_send_json_error([
                'message' => sprintf(__('Invalid file type for file: %s. Allowed types: jpg, jpeg, png, gif', 'bidfood'), $filename)
            ]);
        }

        $sku = pathinfo($filename, PATHINFO_FILENAME);
        $tmp_name = $file['tmp_name'];

        // Get product ID by SKU
        $product_id = wc_get_product_id_by_sku($sku);
        if (!$product_id || !wc_get_product($product_id)) {
            wp_send_json_error(
                [
                    'message' => sprintf(__('No product found with SKU: %s', 'bidfood'), $sku)
                ]
            );
        }

        // Move the file to the uploads directory
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['path'] . '/' . basename($filename);
        if (!move_uploaded_file($tmp_name, $file_path)) {
            wp_send_json_error([
                'message' => sprintf(__('Failed to upload file: %s', 'bidfood'), $filename)
            ]);
        }

        // Attach the image to the product
        $attachment_id = self::attach_image_to_product($file_path, $product_id);
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(
                [
                    'message' => sprintf(__('Failed to attach image to product with SKU: %s', 'bidfood'), $sku)
                ]
            );
        }

        wp_send_json_success([
            'message' => sprintf(__('Image with SKU: %s successfully uploaded and attached.', 'bidfood'), $sku),
            'success' => 1

        ]);
    }

    private static function attach_image_to_product($image_path, $product_id)
    {
        // Upload the image and attach it
        $filetype = wp_check_filetype($image_path);
        $attachment_data = [
            'post_mime_type' => $filetype['type'],
            'post_title' => basename($image_path),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $image_path, $product_id);
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate image metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_metadata = wp_generate_attachment_metadata($attachment_id, $image_path);
        wp_update_attachment_metadata($attachment_id, $attachment_metadata);

        // Set as product's featured image
        update_post_meta($product_id, '_thumbnail_id', $attachment_id);

        return $attachment_id;
    }
}
