<?php
/**
 * Plugin Name: Amelia Auto Customer Sync
 * Plugin URI: https://sparkwebstudio.com/plugins/amelia-auto-customer-sync
 * Description: Automatically creates Amelia customers when WordPress users with supported roles log in or register. Features admin settings, manual sync interface, and WP-CLI support.
 * Version: 1.2.0
 * Author: SPARKWEB Studio
 * Author URI: https://sparkwebstudio.com
 * Text Domain: amelia-auto-customer-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package AmeliaAutoCustomerSync
 * @author SPARKWEB Studio
 * @copyright 2024 SPARKWEB Studio
 * @license GPL-2.0-or-later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AMELIA_AUTO_CUSTOMER_SYNC_VERSION', '1.2.0');
define('AMELIA_AUTO_CUSTOMER_SYNC_PLUGIN_FILE', __FILE__);
define('AMELIA_AUTO_CUSTOMER_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AMELIA_AUTO_CUSTOMER_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

// PSR-4 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'AmeliaAutoCustomerSync\\';
    $base_dir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    \AmeliaAutoCustomerSync\Plugin::getInstance();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    \AmeliaAutoCustomerSync\Plugin::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    \AmeliaAutoCustomerSync\Plugin::deactivate();
}); 