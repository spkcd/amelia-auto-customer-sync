<?php

namespace AmeliaAutoCustomerSync\Admin;

/**
 * Admin Settings Page
 *
 * Handles the admin settings page for Amelia Auto Customer Sync
 *
 * @package AmeliaAutoCustomerSync\Admin
 */
class SettingsPage
{
    /**
     * Option name for storing selected roles
     */
    const OPTION_NAME = 'amelia_auto_sync_roles';
    
    /**
     * Settings page slug
     */
    const PAGE_SLUG = 'amelia-auto-sync-settings';
    
    /**
     * Nonce action for form security
     */
    const NONCE_ACTION = 'amelia_auto_sync_save_settings';
    
    /**
     * Available roles that can be configured
     *
     * @var array
     */
    private $availableRoles = [
        'customer' => 'Customer',
        'administrator' => 'Administrator', 
        'editor' => 'Editor'
    ];

    /**
     * Initialize the settings page
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'handleFormSubmission']);
        add_action('wp_ajax_amelia_bulk_sync', [$this, 'handleBulkSyncAjax']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    /**
     * Add settings page to WordPress admin menu
     */
    public function addSettingsPage(): void
    {
        add_options_page(
            __('Amelia Auto Sync Settings', 'amelia-auto-customer-sync'),
            __('Amelia Auto Sync', 'amelia-auto-customer-sync'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Handle form submission
     */
    public function handleFormSubmission(): void
    {
        // Only process on our settings page
        if (!isset($_GET['page']) || $_GET['page'] !== self::PAGE_SLUG) {
            return;
        }

        // Only process POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], self::NONCE_ACTION)) {
            wp_die(__('Security check failed. Please try again.', 'amelia-auto-customer-sync'));
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'amelia-auto-customer-sync'));
        }

        // Process the form data
        $this->saveSettings();
    }

    /**
     * Save settings to WordPress options
     */
    private function saveSettings(): void
    {
        $selectedRoles = [];
        
        // Check which roles were selected
        foreach ($this->availableRoles as $roleKey => $roleLabel) {
            if (isset($_POST['amelia_sync_roles']) && 
                is_array($_POST['amelia_sync_roles']) && 
                in_array($roleKey, $_POST['amelia_sync_roles'])) {
                $selectedRoles[] = $roleKey;
            }
        }

        // Save to options table
        update_option(self::OPTION_NAME, $selectedRoles);

        // Add success notice
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>' . esc_html__('Settings saved successfully!', 'amelia-auto-customer-sync') . '</p>';
            echo '</div>';
        });
    }

    /**
     * Enqueue scripts and styles for the settings page
     */
    public function enqueueScripts($hook): void
    {
        // Only load on our settings page
        if ($hook !== 'settings_page_amelia-auto-customer-sync') {
            return;
        }

        $plugin_url = plugin_dir_url(AMELIA_AUTO_CUSTOMER_SYNC_PLUGIN_FILE);
        
        wp_enqueue_script(
            'amelia-bulk-sync',
            $plugin_url . 'assets/js/admin-bulk-sync.js',
            ['jquery'],
            AMELIA_AUTO_CUSTOMER_SYNC_VERSION,
            true
        );

        wp_enqueue_style(
            'amelia-bulk-sync',
            $plugin_url . 'assets/css/admin-bulk-sync.css',
            [],
            AMELIA_AUTO_CUSTOMER_SYNC_VERSION
        );

        wp_localize_script('amelia-bulk-sync', 'ameliaBulkSync', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('amelia_bulk_sync_nonce'),
            'strings' => [
                'starting' => __('Starting bulk sync...', 'amelia-auto-customer-sync'),
                'processing' => __('Processing users...', 'amelia-auto-customer-sync'),
                'completed' => __('Bulk sync completed!', 'amelia-auto-customer-sync'),
                'error' => __('An error occurred during sync.', 'amelia-auto-customer-sync'),
                'confirm' => __('This will sync all users with selected roles to Amelia. Continue?', 'amelia-auto-customer-sync'),
            ]
        ]);
    }

    /**
     * Handle AJAX bulk sync request
     */
    public function handleBulkSyncAjax(): void
    {
        error_log('Amelia Bulk Sync: AJAX handler called');
        error_log('Amelia Bulk Sync: POST data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'amelia_bulk_sync_nonce')) {
            error_log('Amelia Bulk Sync: Nonce verification failed');
            wp_send_json_error(['message' => 'Invalid security token']);
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            error_log('Amelia Bulk Sync: Permission check failed');
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        error_log('Amelia Bulk Sync: Security checks passed');

        $batch_size = intval($_POST['batch_size'] ?? 10);
        $offset = intval($_POST['offset'] ?? 0);
        $total_processed = intval($_POST['total_processed'] ?? 0);

        // Get users for current batch
        $users = $this->getUsersForSync($batch_size, $offset);
        $total_users = $this->getTotalUsersForSync();

        if (empty($users)) {
            wp_send_json_success([
                'completed' => true,
                'total_processed' => $total_processed,
                'total_users' => $total_users,
                'message' => sprintf(__('Bulk sync completed! Processed %d users.', 'amelia-auto-customer-sync'), $total_processed)
            ]);
        }

        // Process current batch
        $sync_service = new \AmeliaAutoCustomerSync\Services\CustomerSyncService();
        $batch_results = [];
        $batch_processed = 0;

        foreach ($users as $user) {
            try {
                $result = $sync_service->syncCustomer($user, 'bulk-admin');
                $batch_results[] = [
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'success' => $result,
                    'message' => $result ? 'Synced successfully' : 'Already exists or failed'
                ];
                if ($result) {
                    $batch_processed++;
                }
            } catch (\Exception $e) {
                $batch_results[] = [
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        $new_total_processed = $total_processed + $batch_processed;
        $progress = $total_users > 0 ? round(($offset + count($users)) / $total_users * 100) : 100;

        wp_send_json_success([
            'completed' => false,
            'batch_results' => $batch_results,
            'batch_processed' => $batch_processed,
            'total_processed' => $new_total_processed,
            'total_users' => $total_users,
            'progress' => $progress,
            'next_offset' => $offset + $batch_size,
            'message' => sprintf(__('Processed batch of %d users (%d%% complete)', 'amelia-auto-customer-sync'), count($users), $progress)
        ]);
    }

    /**
     * Get users for sync in batches
     */
    private function getUsersForSync(int $limit, int $offset): array
    {
        $enabled_roles = $this->getSavedRoles();
        
        if (empty($enabled_roles)) {
            return [];
        }

        $args = [
            'role__in' => $enabled_roles,
            'number' => $limit,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'all'
        ];

        return get_users($args);
    }

    /**
     * Get total count of users that will be synced
     */
    private function getTotalUsersForSync(): int
    {
        $enabled_roles = $this->getSavedRoles();
        
        if (empty($enabled_roles)) {
            return 0;
        }

        $args = [
            'role__in' => $enabled_roles,
            'count_total' => true,
            'fields' => 'ID'
        ];

        $user_query = new \WP_User_Query($args);
        return $user_query->get_total();
    }

    /**
     * Get saved role settings
     *
     * @return array Array of enabled role names
     */
    public function getSavedRoles(): array
    {
        $saved = get_option(self::OPTION_NAME, ['customer']);
        
        // Ensure we return an array
        return is_array($saved) ? $saved : ['customer'];
    }

    /**
     * Render the settings page
     */
    public function renderSettingsPage(): void
    {
        $savedRoles = $this->getSavedRoles();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="card" style="max-width: 600px;">
                <h2><?php esc_html_e('Role Synchronization Settings', 'amelia-auto-customer-sync'); ?></h2>
                <p><?php esc_html_e('Select which WordPress user roles should be automatically synchronized to Amelia customers when users log in or register.', 'amelia-auto-customer-sync'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field(self::NONCE_ACTION); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Sync Roles', 'amelia-auto-customer-sync'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text">
                                        <span><?php esc_html_e('Select roles to sync', 'amelia-auto-customer-sync'); ?></span>
                                    </legend>
                                    
                                    <?php foreach ($this->availableRoles as $roleKey => $roleLabel): ?>
                                        <label for="role_<?php echo esc_attr($roleKey); ?>">
                                            <input 
                                                type="checkbox" 
                                                id="role_<?php echo esc_attr($roleKey); ?>"
                                                name="amelia_sync_roles[]" 
                                                value="<?php echo esc_attr($roleKey); ?>"
                                                <?php checked(in_array($roleKey, $savedRoles)); ?>
                                            />
                                            <?php echo esc_html($roleLabel); ?>
                                        </label><br/>
                                    <?php endforeach; ?>
                                    
                                    <p class="description">
                                        <?php esc_html_e('Users with the selected roles will be automatically synced to Amelia when they log in or register.', 'amelia-auto-customer-sync'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Save Settings', 'amelia-auto-customer-sync')); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3><?php esc_html_e('Current Status', 'amelia-auto-customer-sync'); ?></h3>
                
                <?php 
                $ameliaActive = $this->isAmeliaActive();
                $currentRoles = $this->getSavedRoles();
                ?>
                
                <table class="widefat striped">
                    <tbody>
                        <tr>
                            <td><strong><?php esc_html_e('Amelia Plugin Status', 'amelia-auto-customer-sync'); ?></strong></td>
                            <td>
                                <?php if ($ameliaActive): ?>
                                    <span style="color: green;">✓ <?php esc_html_e('Active', 'amelia-auto-customer-sync'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php esc_html_e('Not Active', 'amelia-auto-customer-sync'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Enabled Roles', 'amelia-auto-customer-sync'); ?></strong></td>
                            <td>
                                <?php if (!empty($currentRoles)): ?>
                                    <?php echo esc_html(implode(', ', $currentRoles)); ?>
                                <?php else: ?>
                                    <em><?php esc_html_e('No roles selected', 'amelia-auto-customer-sync'); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php esc_html_e('Sync Triggers', 'amelia-auto-customer-sync'); ?></strong></td>
                            <td><?php esc_html_e('User Login, User Registration', 'amelia-auto-customer-sync'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 600px; margin-top: 20px;">
                <h3><?php esc_html_e('Bulk Sync Users', 'amelia-auto-customer-sync'); ?></h3>
                <p><?php esc_html_e('Sync all existing users with the selected roles to Amelia customers. This will process users in batches to avoid timeouts.', 'amelia-auto-customer-sync'); ?></p>
                
                <?php 
                $total_users = $this->getTotalUsersForSync();
                $enabled_roles = $this->getSavedRoles();
                ?>
                
                <?php if ($ameliaActive && !empty($enabled_roles)): ?>
                    <div class="bulk-sync-info">
                        <p><strong><?php printf(esc_html__('Users to sync: %d', 'amelia-auto-customer-sync'), $total_users); ?></strong></p>
                        <p><em><?php printf(esc_html__('Roles: %s', 'amelia-auto-customer-sync'), esc_html(implode(', ', $enabled_roles))); ?></em></p>
                    </div>
                    
                    <?php if ($total_users > 0): ?>
                        <div class="bulk-sync-controls">
                            <button type="button" id="start-bulk-sync" class="button button-secondary">
                                <?php esc_html_e('Start Bulk Sync', 'amelia-auto-customer-sync'); ?>
                            </button>
                            <button type="button" id="stop-bulk-sync" class="button button-secondary" style="display: none;">
                                <?php esc_html_e('Stop Sync', 'amelia-auto-customer-sync'); ?>
                            </button>
                        </div>
                        
                        <div id="bulk-sync-progress" style="display: none; margin-top: 15px;">
                            <div class="progress-bar-container">
                                <div class="progress-bar">
                                    <div class="progress-bar-fill" style="width: 0%;"></div>
                                </div>
                                <span class="progress-text">0%</span>
                            </div>
                            <div id="sync-status" class="sync-status"></div>
                            <div id="sync-results" class="sync-results"></div>
                        </div>
                    <?php else: ?>
                        <p><em><?php esc_html_e('No users found with the selected roles.', 'amelia-auto-customer-sync'); ?></em></p>
                    <?php endif; ?>
                    
                <?php elseif (!$ameliaActive): ?>
                    <div class="notice notice-warning inline">
                        <p><?php esc_html_e('Amelia plugin must be active to use bulk sync.', 'amelia-auto-customer-sync'); ?></p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-info inline">
                        <p><?php esc_html_e('Please select at least one role to enable bulk sync.', 'amelia-auto-customer-sync'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Check if Amelia is active using the same enhanced detection as the main plugin
     *
     * @return bool
     */
    private function isAmeliaActive(): bool
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
        foreach ($possible_amelia_paths as $path) {
            if (is_plugin_active($path)) {
                $amelia_active = true;
                break;
            }
        }

        if (!$amelia_active) {
            return false;
        }

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
            try {
                $container = \AmeliaBooking\Infrastructure\Container::getInstance();
                if ($container !== null) {
                    return true;
                }
            } catch (\Exception $e) {
                // Continue to other methods
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
                return true;
            }
        }

        // Method 3: Check for Amelia database tables
        $table_name = $this->getAmeliaUsersTableName();
        if ($table_name) {
            return true;
        }

        // Method 4: Check for Amelia constants
        if (defined('AMELIA_VERSION') || defined('AMELIA_URL')) {
            return true;
        }

        return false;
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
                return $table;
            }
        }
        
        return null;
    }
} 