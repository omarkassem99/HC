<?php

namespace Bidfood\Core\Database;

class DatabaseManager
{

    public static function create_tables()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // SQL statements for each table
        $tables = [
            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_operator (
            //     operator_id VARCHAR(10) PRIMARY KEY,
            //     operator_name VARCHAR(255),
            //     description VARCHAR(255)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_venue (
            //     venue_id VARCHAR(10) PRIMARY KEY,
            //     operator_id VARCHAR(10),
            //     venue_name VARCHAR(255),
            //     FOREIGN KEY (operator_id) REFERENCES {$wpdb->prefix}neom_operator(operator_id)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_supplier (
            //     supplier_id VARCHAR(10) PRIMARY KEY,
            //     supplier_name VARCHAR(255),
            //     type VARCHAR(50),
            //     lead_time_days INT NOT NULL DEFAULT 0,
            //     contact_name VARCHAR(255) NULL,
            //     contact_email VARCHAR(255) NULL,
            //     contact_number VARCHAR(20) NULL,
            //     contact_name_2 VARCHAR(255) NULL,
            //     contact_email_2 VARCHAR(255) NULL,
            //     contact_number_2 VARCHAR(20) NULL
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_category (
            //     category_id VARCHAR(10) PRIMARY KEY,
            //     category_name VARCHAR(255)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_sub_category (
            //     sub_category_id VARCHAR(10) PRIMARY KEY,
            //     sub_category_name VARCHAR(255),
            //     category_id VARCHAR(10),
            //     FOREIGN KEY (category_id) REFERENCES {$wpdb->prefix}neom_category(category_id)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_temperature (
            //     temperature_id VARCHAR(10) PRIMARY KEY,
            //     temperature_description VARCHAR(255)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_uom (
            //     uom_id VARCHAR(10) PRIMARY KEY,
            //     uom_description VARCHAR(255)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_item_parent (
            //     item_parent_id VARCHAR(10) PRIMARY KEY,
            //     description VARCHAR(255),
            //     uom_id VARCHAR(10),
            //     FOREIGN KEY (uom_id) REFERENCES {$wpdb->prefix}neom_uom(uom_id)
            // ) $charset_collate;",

            // "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}neom_ordering_item_master (
            //     item_id VARCHAR(10) PRIMARY KEY,
            //     item_parent_id VARCHAR(10),
            //     venue_id VARCHAR(10),
            //     description VARCHAR(255) NOT NULL DEFAULT '',
            //     category_id VARCHAR(10),
            //     sub_category_id VARCHAR(10),
            //     temperature_id VARCHAR(10),
            //     uom_id VARCHAR(10),
            //     preferred_brand VARCHAR(255) NOT NULL DEFAULT '',
            //     alternative_brand VARCHAR(255) NOT NULL DEFAULT '',
            //     preferred_supplier_id VARCHAR(10) NULL,
            //     alternative_supplier_id VARCHAR(10) NULL,
            //     barcode VARCHAR(255) NULL,
            //     supplier_code VARCHAR(255) NULL,
            //     additional_info TEXT NULL,
            //     packaging_per_uom varchar(255) NULL,
            //     country VARCHAR(255) NULL,
            //     moq DECIMAL(10,2) NULL,
            //     status VARCHAR(20) NOT NULL DEFAULT 'active',
            //     FOREIGN KEY (item_parent_id) REFERENCES {$wpdb->prefix}neom_item_parent(item_parent_id),
            //     FOREIGN KEY (venue_id) REFERENCES {$wpdb->prefix}neom_venue(venue_id),
            //     FOREIGN KEY (category_id) REFERENCES {$wpdb->prefix}neom_category(category_id),
            //     FOREIGN KEY (sub_category_id) REFERENCES {$wpdb->prefix}neom_sub_category(sub_category_id),
            //     FOREIGN KEY (temperature_id) REFERENCES {$wpdb->prefix}neom_temperature(temperature_id),
            //     FOREIGN KEY (uom_id) REFERENCES {$wpdb->prefix}neom_uom(uom_id),
            //     FOREIGN KEY (preferred_supplier_id) REFERENCES {$wpdb->prefix}neom_supplier(supplier_id),
            //     FOREIGN KEY (alternative_supplier_id) REFERENCES {$wpdb->prefix}neom_supplier(supplier_id)
            // ) $charset_collate;",
        ];

        // Execute each SQL statement
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }
}
