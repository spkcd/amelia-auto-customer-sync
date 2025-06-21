<?php

namespace AmeliaAutoCustomerSync\Admin;

use AmeliaAutoCustomerSync\Services\CustomerSyncService;

/**
 * Users Table Column Handler
 *
 * Adds custom "Amelia Sync" column to WordPress Users admin table
 *
 * @package AmeliaAutoCustomerSync\Admin
 */
class UsersTableColumn
{
    /**
     * AJAX action name
     */
    const AJAX_ACTION = 'amelia_manual_sync';
    
    /**
     * Nonce action
     */
    const NONCE_ACTION = 'amelia_manual_sync_nonce';
    
    /**
     * CustomerSyncService instance
     *
     * @var CustomerSyncService
     */
    private $customerSyncService;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->customerSyncService = new CustomerSyncService();
    }
    
    /**
     * Initialize the users table column functionality
     */
    public function init(): void
    {
        // Add custom column to users table
        add_filter('manage_users_columns', [$this, 'addAmeliaSyncColumn']);
        add_filter('manage_users_custom_column', [$this, 'renderAmeliaSyncColumn'], 10, 3);
        
        // Register AJAX handlers
        add_action('wp_ajax_' . self::AJAX_ACTION, [$this, 'handleManualSync']);
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }
    
    /**
     * Add Amelia Sync column to users table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function addAmeliaSyncColumn(array $columns): array
    {
        // Add our column before the posts column (or at the end if posts doesn't exist)
        $new_columns = [];
        
        foreach ($columns as $key => $title) {
            $new_columns[$key] = $title;
            
            // Insert our column before the posts column
            if ($key === 'posts') {
                $new_columns['amelia_sync'] = __('Amelia Sync', 'amelia-auto-customer-sync');
            }
        }
        
        // If posts column doesn't exist, add at the end
        if (!isset($new_columns['amelia_sync'])) {
            $new_columns['amelia_sync'] = __('Amelia Sync', 'amelia-auto-customer-sync');
        }
        
        return $new_columns;
    }
    
    /**
     * Render content for Amelia Sync column
     *
     * @param string $output Current output
     * @param string $column_name Column name
     * @param int $user_id User ID
     * @return string Column content
     */
    public function renderAmeliaSyncColumn(string $output, string $column_name, int $user_id): string
    {
        if ($column_name !== 'amelia_sync') {
            return $output;
        }
        
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return '<span class="amelia-sync-na">' . __('N/A', 'amelia-auto-customer-sync') . '</span>';
        }
        
        // Check if user has an allowed role
        if (!$this->userHasAllowedRole($user)) {
            return '<span class="amelia-sync-na">' . __('Role not enabled', 'amelia-auto-customer-sync') . '</span>';
        }
        
        // Check if already synced
        $ameliaCustomerId = get_user_meta($user_id, '_amelia_customer_id', true);
        $alreadySynced = !empty($ameliaCustomerId);
        
        $nonce = wp_create_nonce(self::NONCE_ACTION);
        
        ob_start();
        ?>
        <div class="amelia-sync-wrapper" data-user-id="<?php echo esc_attr($user_id); ?>">
            <?php if ($alreadySynced): ?>
                <span class="amelia-sync-status amelia-synced">
                    ✓ <?php esc_html_e('Synced', 'amelia-auto-customer-sync'); ?>
                    <small>(ID: <?php echo esc_html($ameliaCustomerId); ?>)</small>
                </span>
                <button 
                    type="button" 
                    class="button button-small amelia-sync-btn" 
                    data-user-id="<?php echo esc_attr($user_id); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    title="<?php esc_attr_e('Re-sync user', 'amelia-auto-customer-sync'); ?>"
                >
                    <?php esc_html_e('Re-sync', 'amelia-auto-customer-sync'); ?>
                </button>
            <?php else: ?>
                <button 
                    type="button" 
                    class="button button-primary button-small amelia-sync-btn" 
                    data-user-id="<?php echo esc_attr($user_id); ?>"
                    data-nonce="<?php echo esc_attr($nonce); ?>"
                    title="<?php esc_attr_e('Sync user to Amelia', 'amelia-auto-customer-sync'); ?>"
                >
                    <?php esc_html_e('Sync', 'amelia-auto-customer-sync'); ?>
                </button>
            <?php endif; ?>
            
            <div class="amelia-sync-feedback" style="display: none;">
                <span class="amelia-sync-spinner" style="display: none;">
                    <span class="spinner" style="float: none; visibility: visible;"></span>
                    <?php esc_html_e('Syncing...', 'amelia-auto-customer-sync'); ?>
                </span>
                <span class="amelia-sync-success" style="display: none; color: green;">
                    ✓ <span class="message"></span>
                </span>
                <span class="amelia-sync-error" style="display: none; color: red;">
                    ✗ <span class="message"></span>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Check if user has an allowed role for sync
     *
     * @param \WP_User $user User object
     * @return bool
     */
    private function userHasAllowedRole(\WP_User $user): bool
    {
        $allowedRoles = $this->customerSyncService->getSupportedRoles();
        
        foreach ($allowedRoles as $role) {
            if (in_array($role, $user->roles, true)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Handle manual sync AJAX request
     */
    public function handleManualSync(): void
    {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_ACTION)) {
                wp_send_json_error(['message' => __('Security check failed.', 'amelia-auto-customer-sync')]);
            }
            
            // Check user permissions
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions.', 'amelia-auto-customer-sync')]);
            }
            
            // Get and validate user ID
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
            if (!$user_id) {
                wp_send_json_error(['message' => __('Invalid user ID.', 'amelia-auto-customer-sync')]);
            }
            
            $user = get_user_by('ID', $user_id);
            if (!$user) {
                wp_send_json_error(['message' => __('User not found.', 'amelia-auto-customer-sync')]);
            }
            
            // Check if user has allowed role
            if (!$this->userHasAllowedRole($user)) {
                wp_send_json_error(['message' => __('User role is not enabled for sync.', 'amelia-auto-customer-sync')]);
            }
            
            // Check if already synced
            $existingCustomerId = get_user_meta($user_id, '_amelia_customer_id', true);
            $wasAlreadySynced = !empty($existingCustomerId);
            
            // Perform sync
            $result = $this->customerSyncService->syncCustomer($user, 'manual-admin');
            
            if ($result) {
                $newCustomerId = get_user_meta($user_id, '_amelia_customer_id', true);
                
                if ($wasAlreadySynced) {
                    wp_send_json_success([
                        'message' => __('User re-synced successfully.', 'amelia-auto-customer-sync'),
                        'customer_id' => $newCustomerId,
                        'was_existing' => true
                    ]);
                } else {
                    wp_send_json_success([
                        'message' => __('User synced successfully.', 'amelia-auto-customer-sync'),
                        'customer_id' => $newCustomerId,
                        'was_existing' => false
                    ]);
                }
            } else {
                wp_send_json_error(['message' => __('Sync failed. Check error logs for details.', 'amelia-auto-customer-sync')]);
            }
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync - Manual sync error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred during sync.', 'amelia-auto-customer-sync')]);
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on users.php page
        if ($hook !== 'users.php') {
            return;
        }
        
        // Get plugin URL for assets
        $plugin_url = plugin_dir_url(dirname(__DIR__, 2));
        
        // Enqueue CSS
        wp_enqueue_style(
            'amelia-users-sync-css',
            $plugin_url . 'assets/css/admin-users-sync.css',
            [],
            '1.1.0'
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'amelia-users-sync-js',
            $plugin_url . 'assets/js/admin-users-sync.js',
            ['jquery'],
            '1.1.0',
            true
        );
        
        // Localize script with AJAX data and translatable strings
        wp_localize_script('amelia-users-sync-js', 'ameliaUsersSync', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => self::AJAX_ACTION,
            'messages' => [
                'syncing' => __('Syncing...', 'amelia-auto-customer-sync'),
                'syncSuccess' => __('User synced successfully.', 'amelia-auto-customer-sync'),
                'resync' => __('Re-sync', 'amelia-auto-customer-sync'),
                'resyncTitle' => __('Re-sync user', 'amelia-auto-customer-sync'),
                'synced' => __('Synced', 'amelia-auto-customer-sync'),
                'networkError' => __('Network error occurred.', 'amelia-auto-customer-sync'),
                'connectionError' => __('Connection failed. Please try again.', 'amelia-auto-customer-sync'),
                'serverError' => __('Server error occurred. Please try again later.', 'amelia-auto-customer-sync'),
                'unknownError' => __('An unknown error occurred.', 'amelia-auto-customer-sync'),
                'invalidData' => __('Invalid data provided.', 'amelia-auto-customer-sync'),
            ]
        ]);
    }
} 