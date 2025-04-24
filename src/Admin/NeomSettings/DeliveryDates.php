<?php

namespace Bidfood\Admin\NeomSettings;

class DeliveryDates {
    public function __construct() {
        // Register AJAX action
        add_action('wp_ajax_save_delivery_dates', [$this, 'save_delivery_dates']);
    }

    public static function init() {
        return new self();
    }

    public static function render() {
        // Fetch current option values
        $customer_lead_days = get_option("bidfood_customer_lead_days");
        $customer_order_cutoff_hour = get_option("bidfood_customer_order_cutoff_hour");
        $supplier_lead_days = get_option("bidfood_supplier_lead_days");
        $supplier_cutoff_hour = get_option("bidfood_supplier_cutoff_hour");
        $extra_days_for_delivery = get_option("bidfood_extra_days_for_delivery");

        ?>
        <h2>Delivery Dates Settings</h2>
        <form id="delivery-dates-form">
            <div class="form-group">
                <label>Customer Lead Days:</label>
                <input type="number" name="customer_lead_days" value="<?php echo esc_attr($customer_lead_days); ?>" required>
            </div>
            <div class="form-group">
                <label>Customer Order Cutoff Hour:</label>
                <input type="number" name="customer_order_cutoff_hour" value="<?php echo esc_attr($customer_order_cutoff_hour); ?>" required>
            </div>
            <div class="form-group">
                <label>Supplier Lead Days:</label>
                <input type="number" name="supplier_lead_days" value="<?php echo esc_attr($supplier_lead_days); ?>" required>
            </div>
            <div class="form-group">
                <label>Supplier Cutoff Hour:</label>
                <input type="number" name="supplier_cutoff_hour" value="<?php echo esc_attr($supplier_cutoff_hour); ?>" required>
            </div>
            <div class="form-group">
                <label>Extra Days for Delivery:</label>
                <input type="number" name="extra_days_for_delivery" value="<?php echo esc_attr($extra_days_for_delivery); ?>" required>
            </div>
            <button type="button" class="button-primary" onclick="saveDeliveryDates()">Save Settings</button>
        </form>
        <style>
            #delivery-dates-form {
                max-width: 500px;
                background: #f9f9f9;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                font-family: Arial, sans-serif;
            }
            .form-group {
                margin-bottom: 15px;
            }
            .form-group label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .form-group input {
                width: 100%;
                padding: 8px;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .button-primary {
                background-color: #007cba;
                color: #fff;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            .button-primary:hover {
                background-color: #005fa3;
            }
        </style>
        <script>
            function saveDeliveryDates() {
                let formData = new FormData(document.getElementById('delivery-dates-form'));
                formData.append('action', 'save_delivery_dates');
                formData.append('security', '<?php echo wp_create_nonce('delivery_dates_nonce'); ?>');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Settings saved successfully!', 'success', 5000);
                    } else {
                        showToast(data.data.message || 'An error occurred.', 'error', 5000);
                    }
                })
                .catch(error => {
                    showToast('An error occurred while saving settings.', 'error', 5000);
                    console.error('Error:', error);
                });
            }
        </script>
        <?php
    }

    // Save form data via AJAX
    public function save_delivery_dates() {
        check_ajax_referer('delivery_dates_nonce', 'security');

        $fields = [
            'customer_lead_days' => 'bidfood_customer_lead_days',
            'customer_order_cutoff_hour' => 'bidfood_customer_order_cutoff_hour',
            'supplier_lead_days' => 'bidfood_supplier_lead_days',
            'supplier_cutoff_hour' => 'bidfood_supplier_cutoff_hour',
            'extra_days_for_delivery' => 'bidfood_extra_days_for_delivery',
        ];

        foreach ($fields as $field => $option_name) {
            if (isset($_POST[$field])) {
                update_option($option_name, intval($_POST[$field]));
            }
        }

        wp_send_json_success(['message' => __('Settings saved successfully!', 'bidfood')]);
    }
}