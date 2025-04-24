<?php
/**
 * Plugin Name: Hello Chef
 * Description: A plugin to manage hello chef functionalities.
 * Version: 1.0
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// require_once __DIR__ . '/composer/vendor/autoload.php';

define('CHEF_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CHEF_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload classes using PSR-4 standard
spl_autoload_register(function($class) {
    // Base namespace
    $prefix = 'Chef\\';

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/src/';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, and
    // append with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Hook to run on plugin activation
register_activation_hook(__FILE__, ['Chef\Core\Database\DatabaseManager', 'create_tables']);

// Include functions.php (for actions and custom functions)
require_once plugin_dir_path(__FILE__) . 'functions.php';