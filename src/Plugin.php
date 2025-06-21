<?php

namespace AmeliaAutoCustomerSync;

use AmeliaAutoCustomerSync\Services\CustomerSyncService;
use AmeliaAutoCustomerSync\Admin\SettingsPage;
use AmeliaAutoCustomerSync\Admin\UsersTableColumn;

/**
 * Main Plugin Class
 *
 * @package AmeliaAutoCustomerSync
 */
class Plugin
{
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Settings page instance
     *
     * @var SettingsPage
     */
    private $settingsPage;

    /**
     * Users table column instance
     *
     * @var UsersTableColumn
     */
    private $usersTableColumn;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialize the plugin
     */
    private function init(): void
    {
        // Initialize settings page
        $this->settingsPage = new SettingsPage();
        $this->settingsPage->init();
        
        // Initialize users table column
        $this->usersTableColumn = new UsersTableColumn();
        $this->usersTableColumn->init();
        
        // Setup hooks
        $this->setupHooks();
        
        // Load textdomain
        add_action('init', [$this, 'loadTextdomain']);
        
        // Check Amelia availability and show admin notices
        // Use a later hook to give Amelia time to initialize
        add_action('admin_notices', [$this, 'checkAmeliaAndShowNotices']);
        add_action('admin_init', [$this, 'recheckAmeliaAfterInit']);
        
        // Add debug action for troubleshooting (remove in production)
        add_action('wp_ajax_amelia_sync_debug', [$this, 'debugAmeliaDetection']);
        
        // Register WP-CLI commands
        $this->registerWPCLICommands();
    }

    /**
     * Setup WordPress hooks
     */
    private function setupHooks(): void
    {
        // Hook into user login and registration for automatic customer sync
        add_action('wp_login', [$this, 'handleUserLogin'], 10, 2);
        add_action('user_register', [$this, 'handleUserRegistration'], 10, 1);
    }

    /**
     * Handle user login event
     *
     * @param string $user_login The user's login username
     * @param \WP_User $user The user object
     */
    public function handleUserLogin(string $user_login, \WP_User $user): void
    {
        try {
            // Get user by username (wp_login provides both username and user object)
            // We'll use the provided user object directly, but could also retrieve by username if needed
            $wpUser = $user;
            
            // Double-check we have a valid user object
            if (!$wpUser instanceof \WP_User) {
                $wpUser = get_user_by('login', $user_login);
            }
            
            if (!$wpUser || !$wpUser->exists()) {
                $this->logDebug("User not found for login: {$user_login}");
                return;
            }
            
            // Instantiate CustomerSyncService and call syncCustomer method
            $customerSyncService = new CustomerSyncService();
            $result = $customerSyncService->syncCustomer($wpUser, 'login');
            
            if ($result) {
                $this->logDebug("Customer sync successful for user: {$user_login} (login)");
            } else {
                $this->logDebug("Customer sync skipped or failed for user: {$user_login} (login)");
            }
            
        } catch (\Exception $e) {
            // Fail silently but log the error
            $this->logDebug('Error in handleUserLogin: ' . $e->getMessage());
        }
    }

    /**
     * Handle user registration event
     *
     * Retrieves WP_User by ID and passes to CustomerSyncService for sync.
     * Uses same logic as wp_login handler. Only runs for non-deleted users with allowed roles.
     *
     * @param int $user_id The newly registered user ID
     */
    public function handleUserRegistration(int $user_id): void
    {
        try {
            // Validate user ID
            if (empty($user_id) || !is_numeric($user_id)) {
                $this->logDebug("Invalid user ID provided for registration: {$user_id}");
                return;
            }

            // Retrieve the WP_User object by ID
            $wpUser = get_user_by('ID', $user_id);
            
            // Validate user exists and is not deleted
            if (!$wpUser || !$wpUser->exists()) {
                $this->logDebug("User not found or deleted for registration ID: {$user_id}");
                return;
            }

            // Additional check for user status (ensure not spam/deleted in multisite)
            if (is_multisite() && is_user_spammy($wpUser)) {
                $this->logDebug("User {$wpUser->user_login} is marked as spam, skipping sync");
                return;
            }

            // Check if user has valid email (additional safety check)
            if (empty($wpUser->user_email) || !is_email($wpUser->user_email)) {
                $this->logDebug("User {$wpUser->user_login} has invalid email, skipping sync");
                return;
            }

            // Double-check we have a valid user object (same validation as login handler)
            if (!$wpUser instanceof \WP_User) {
                $this->logDebug("Retrieved object is not a valid WP_User instance for ID: {$user_id}");
                return;
            }

            // Instantiate CustomerSyncService and call syncCustomer method (same as login handler)
            $customerSyncService = new CustomerSyncService();
            $result = $customerSyncService->syncCustomer($wpUser, 'registration');
            
            // Log results (same pattern as login handler)
            if ($result) {
                $this->logDebug("Customer sync successful for new user: {$wpUser->user_login} (registration)");
            } else {
                $this->logDebug("Customer sync skipped or failed for new user: {$wpUser->user_login} (registration)");
            }
            
        } catch (\Exception $e) {
            // Fail silently but log the error (same as login handler)
            $this->logDebug('Error in handleUserRegistration: ' . $e->getMessage());
        }
    }

    /**
     * Check if Amelia is available and show admin notices
     */
    public function checkAmeliaAndShowNotices(): void
    {
        // Check if we should show the missing Amelia notice
        if (get_transient('amelia_auto_customer_sync_amelia_missing')) {
            delete_transient('amelia_auto_customer_sync_amelia_missing');
            $this->showAmeliaMissingNotice();
            return;
        }
        
        // Regular check for Amelia availability (only on relevant admin pages)
        if ($this->shouldCheckAmelia() && !$this->isAmeliaAvailable()) {
            $this->showAmeliaMissingNotice();
        }
    }

    /**
     * Recheck Amelia availability after WordPress admin init
     * This gives Amelia more time to load its classes
     */
    public function recheckAmeliaAfterInit(): void
    {
        // Only run this check once per request and only if we previously thought Amelia was unavailable
        static $rechecked = false;
        if ($rechecked) {
            return;
        }
        $rechecked = true;

        // If we have a transient saying Amelia is missing, recheck now that plugins have fully loaded
        if (get_transient('amelia_auto_customer_sync_amelia_missing')) {
            if ($this->isAmeliaAvailable()) {
                // Amelia is now available, remove the transient
                delete_transient('amelia_auto_customer_sync_amelia_missing');
                $this->logDebug('Amelia became available after admin_init - removing missing notice');
            }
        }
    }

    /**
     * Check if Amelia plugin is available
     *
     * @return bool
     */
    private function isAmeliaAvailable(): bool
    {
        // Multiple possible Amelia plugin file paths
        $possible_amelia_paths = [
            'ameliabooking/ameliabooking.php',
            'amelia-booking/amelia-booking.php',
            'ameliabooking/amelia-booking.php',
            'amelia/amelia.php'
        ];

        // Check if any Amelia plugin file is active
        $amelia_active = false;
        $found_path = '';
        foreach ($possible_amelia_paths as $path) {
            if (is_plugin_active($path)) {
                $amelia_active = true;
                $found_path = $path;
                break;
            }
        }

        if (!$amelia_active) {
            $this->logDebug('No Amelia plugin found active in expected paths');
            return false;
        }

        $this->logDebug('Found active Amelia plugin at: ' . $found_path);

        // Try multiple approaches to check if Amelia is fully loaded
        return $this->checkAmeliaClasses();
    }

    /**
     * Check if Amelia classes are available with multiple fallback methods
     *
     * @return bool
     */
    private function checkAmeliaClasses(): bool
    {
        // Method 1: Check for Container class (preferred)
        if (class_exists('\AmeliaBooking\Infrastructure\Container')) {
            $this->logDebug('Amelia Container class found');
            try {
                $container = \AmeliaBooking\Infrastructure\Container::getInstance();
                if ($container !== null) {
                    $this->logDebug('Amelia Container instantiation: success');
                    return true;
                }
            } catch (\Exception $e) {
                $this->logDebug('Amelia Container instantiation error: ' . $e->getMessage());
            }
        }

        // Method 2: Check for other core Amelia classes
        $core_classes = [
            '\AmeliaBooking\Domain\Entity\User\Customer',
            '\AmeliaBooking\Domain\Services\User\CustomerService',
            '\AmeliaBooking\Application\Services\User\CustomerApplicationService',
            '\AmeliaBooking\Infrastructure\Repository\User\CustomerRepository'
        ];

        foreach ($core_classes as $class) {
            if (class_exists($class)) {
                $this->logDebug('Found Amelia class: ' . $class);
                return true;
            }
        }

        // Method 3: Check for Amelia database tables
        $table_name = $this->getAmeliaUsersTableName();
        if ($table_name) {
            $this->logDebug('Amelia database table found: ' . $table_name);
            return true;
        }

        // Method 4: Check for Amelia constants or global variables
        if (defined('AMELIA_VERSION') || defined('AMELIA_URL')) {
            $this->logDebug('Amelia constants found');
            return true;
        }

        $this->logDebug('No Amelia classes, tables, or constants found - Amelia may not be fully loaded yet');
        return false;
    }

    /**
     * Determine if we should check for Amelia (only on relevant admin pages)
     *
     * @return bool
     */
    private function shouldCheckAmelia(): bool
    {
        if (!is_admin()) {
            return false;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }

        // Check on plugins page, dashboard, and our plugin's pages
        $relevant_screens = [
            'plugins',
            'dashboard',
            'settings_page_amelia-auto-sync-settings'
        ];

        return in_array($screen->id, $relevant_screens, true);
    }

    /**
     * Show admin notice when Amelia is missing
     */
    private function showAmeliaMissingNotice(): void
    {
        $plugin_name = __('Amelia Auto Customer Sync', 'amelia-auto-customer-sync');
        $amelia_name = __('Amelia Booking', 'amelia-auto-customer-sync');
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>';
        printf(
            /* translators: %1$s: Plugin name, %2$s: Amelia plugin name */
            esc_html__('%1$s requires the %2$s plugin to be installed and active. The plugin will not function until Amelia is available.', 'amelia-auto-customer-sync'),
            '<strong>' . esc_html($plugin_name) . '</strong>',
            '<strong>' . esc_html($amelia_name) . '</strong>'
        );
        echo '</p>';
        
        // Add install/activate link if user has capability
        if (current_user_can('install_plugins')) {
            $plugin_slug = 'ameliabooking';
            $possible_amelia_paths = [
                'ameliabooking/ameliabooking.php',
                'amelia-booking/amelia-booking.php',
                'ameliabooking/amelia-booking.php',
                'amelia/amelia.php'
            ];
            
            // Check if any Amelia plugin file exists but is not active
            $installed_plugin_file = null;
            foreach ($possible_amelia_paths as $path) {
                if (file_exists(WP_PLUGIN_DIR . '/' . $path)) {
                    $installed_plugin_file = $path;
                    break;
                }
            }
            
            if ($installed_plugin_file) {
                // Amelia is installed but not active
                $activate_url = wp_nonce_url(
                    admin_url('plugins.php?action=activate&plugin=' . urlencode($installed_plugin_file)),
                    'activate-plugin_' . $installed_plugin_file
                );
                echo '<p>';
                printf(
                    '<a href="%s" class="button button-primary">%s</a>',
                    esc_url($activate_url),
                    esc_html__('Activate Amelia Booking', 'amelia-auto-customer-sync')
                );
                echo '</p>';
            } else {
                // Amelia is not installed
                $install_url = wp_nonce_url(
                    admin_url('update.php?action=install-plugin&plugin=' . urlencode($plugin_slug)),
                    'install-plugin_' . $plugin_slug
                );
                echo '<p>';
                printf(
                    '<a href="%s" class="button button-primary">%s</a>',
                    esc_url($install_url),
                    esc_html__('Install Amelia Booking', 'amelia-auto-customer-sync')
                );
                echo '</p>';
            }
        }
        
        echo '</div>';
    }

    /**
     * Register WP-CLI commands
     */
    private function registerWPCLICommands(): void
    {
        // Only register WP-CLI commands if WP-CLI is available
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        // Register the sync-customers command
        \WP_CLI::add_command(
            'amelia sync-customers',
            \AmeliaAutoCustomerSync\CLI\SyncCustomersCommand::class
        );
    }

    /**
     * Log debug message only if WP_DEBUG_LOG is enabled
     *
     * @param string $message The message to log
     */
    private function logDebug(string $message): void
    {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('Amelia Auto Customer Sync: ' . $message);
        }
    }

    /**
     * Load plugin textdomain
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'amelia-auto-customer-sync',
            false,
            dirname(plugin_basename(AMELIA_AUTO_CUSTOMER_SYNC_PLUGIN_FILE)) . '/languages'
        );
    }

    /**
     * Get settings page instance
     *
     * @return SettingsPage
     */
    public function getSettingsPage(): SettingsPage
    {
        return $this->settingsPage;
    }

    /**
     * Plugin activation
     */
    public static function activate(): void
    {
        // Create any necessary database tables or options
        add_option('amelia_auto_customer_sync_version', AMELIA_AUTO_CUSTOMER_SYNC_VERSION);
        
        // Set default roles if not already set
        add_option('amelia_auto_sync_roles', ['customer']);
        
        // Check if Amelia is available on activation
        $instance = self::getInstance();
        if (!$instance->isAmeliaAvailable()) {
            // Set a transient to show admin notice
            set_transient('amelia_auto_customer_sync_amelia_missing', true, 60);
        }
        
        // Log activation
        error_log('Amelia Auto Customer Sync: Plugin activated');
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate(): void
    {
        // Clean up if necessary
        error_log('Amelia Auto Customer Sync: Plugin deactivated');
    }

    /**
     * Debug Amelia detection (for troubleshooting)
     * Call via AJAX: /wp-admin/admin-ajax.php?action=amelia_sync_debug
     */
    public function debugAmeliaDetection(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        echo '<h2>Amelia Auto Customer Sync - Debug Information</h2>';
        
        // Get all active plugins
        $active_plugins = get_option('active_plugins', []);
        echo '<h3>Active Plugins:</h3>';
        echo '<ul>';
        foreach ($active_plugins as $plugin) {
            echo '<li>' . esc_html($plugin) . '</li>';
        }
        echo '</ul>';

        // Check possible Amelia paths
        $possible_amelia_paths = [
            'ameliabooking/ameliabooking.php',
            'amelia-booking/amelia-booking.php',
            'ameliabooking/amelia-booking.php',
            'amelia/amelia.php'
        ];

        echo '<h3>Amelia Plugin Detection:</h3>';
        echo '<ul>';
        foreach ($possible_amelia_paths as $path) {
            $exists = file_exists(WP_PLUGIN_DIR . '/' . $path);
            $active = is_plugin_active($path);
            echo '<li>';
            echo esc_html($path) . ' - ';
            echo 'Exists: ' . ($exists ? 'Yes' : 'No') . ', ';
            echo 'Active: ' . ($active ? 'Yes' : 'No');
            echo '</li>';
        }
        echo '</ul>';

        // Check Amelia classes
        echo '<h3>Amelia Classes:</h3>';
        echo '<ul>';
        echo '<li>AmeliaBooking\\Infrastructure\\Container: ' . (class_exists('\AmeliaBooking\Infrastructure\Container') ? 'Found' : 'Not Found') . '</li>';
        
        if (class_exists('\AmeliaBooking\Infrastructure\Container')) {
            try {
                $container = \AmeliaBooking\Infrastructure\Container::getInstance();
                echo '<li>Container Instance: ' . ($container ? 'Success' : 'Failed') . '</li>';
            } catch (\Exception $e) {
                echo '<li>Container Instance Error: ' . esc_html($e->getMessage()) . '</li>';
            }
        }
        echo '</ul>';

        // Overall availability
        echo '<h3>Overall Amelia Availability:</h3>';
        echo '<p><strong>' . ($this->isAmeliaAvailable() ? 'AVAILABLE' : 'NOT AVAILABLE') . '</strong></p>';

        wp_die();
    }

    /**
     * Get the Amelia users table name by searching for possible variations
     *
     * @return string|null Table name if found, null if not found
     */
    private function getAmeliaUsersTableName(): ?string
    {
        global $wpdb;
        
        // Possible table name variations
        $possible_tables = [
            $wpdb->prefix . 'amelia_users',
            $wpdb->prefix . 'ameliabooking_users', 
            $wpdb->prefix . 'amelia_customers',
            $wpdb->prefix . 'ameliabooking_customers',
            $wpdb->prefix . 'amelia_wp_users',
            $wpdb->prefix . 'ameliawp_users'
        ];
        
        foreach ($possible_tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
            if ($exists) {
                // Verify it has the expected columns for Amelia users
                $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table}"));
                $column_names = array_column($columns, 'Field');
                
                // Check for essential Amelia user columns
                if (in_array('email', $column_names) && 
                    in_array('type', $column_names) && 
                    in_array('firstName', $column_names)) {
                    $this->logDebug('Found Amelia users table: ' . $table);
                    return $table;
                }
            }
        }
        
        // If standard names don't work, search for any table with Amelia-like structure
        $all_tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        foreach ($all_tables as $table_row) {
            $table = $table_row[0];
            
            // Skip if doesn't contain 'amelia' in name
            if (stripos($table, 'amelia') === false) {
                continue;
            }
            
            // Check if it has user-like structure
            $columns = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$table}"));
            $column_names = array_column($columns, 'Field');
            
            if (in_array('email', $column_names) && 
                in_array('type', $column_names) && 
                in_array('firstName', $column_names)) {
                $this->logDebug('Found Amelia users table via search: ' . $table);
                return $table;
            }
        }
        
        $this->logDebug('No Amelia users table found');
        return null;
    }
} 