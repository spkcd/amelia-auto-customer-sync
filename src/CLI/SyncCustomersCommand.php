<?php

namespace AmeliaAutoCustomerSync\CLI;

use AmeliaAutoCustomerSync\Services\CustomerSyncService;
use WP_CLI;

/**
 * WP-CLI command for syncing customers with Amelia
 *
 * @package AmeliaAutoCustomerSync\CLI
 */
class SyncCustomersCommand
{
    /**
     * Sync WordPress users with supported roles to Amelia customers
     *
     * ## OPTIONS
     *
     * [--batch-size=<size>]
     * : Number of users to process per batch. Default: 50
     *
     * [--roles=<roles>]
     * : Comma-separated list of roles to sync. Default: uses current roles from amelia_auto_sync_roles option
     *
     * [--dry-run]
     * : Show what would be synced without actually creating customers
     *
     * ## EXAMPLES
     *
     *     # Sync all configured roles (from amelia_auto_sync_roles option)
     *     wp amelia sync-customers
     *
     *     # Sync only customers
     *     wp amelia sync-customers --roles=customer
     *
     *     # Sync customers and admins with smaller batch size
     *     wp amelia sync-customers --roles=customer,administrator --batch-size=25
     *
     *     # Preview what would be synced
     *     wp amelia sync-customers --dry-run
     *
     * @param array $args Command arguments
     * @param array $assoc_args Associative arguments
     */
    public function __invoke($args, $assoc_args)
    {
        $batch_size = isset($assoc_args['batch-size']) ? (int) $assoc_args['batch-size'] : 50;
        $dry_run = isset($assoc_args['dry-run']);
        // Get roles from service if not specified
        if (isset($assoc_args['roles'])) {
            $roles_input = $assoc_args['roles'];
        } else {
            // Use current supported roles from service (dynamic from options)
            $tempService = new CustomerSyncService();
            $currentRoles = $tempService->getSupportedRoles();
            $roles_input = implode(',', $currentRoles);
        }
        
        // Parse and validate roles
        $roles = array_map('trim', explode(',', $roles_input));
        
        // Get supported roles from service (with filtering support)
        $customerSyncService = new CustomerSyncService();
        $supported_roles = $customerSyncService->getSupportedRoles();
        $invalid_roles = array_diff($roles, $supported_roles);
        
        if (!empty($invalid_roles)) {
            WP_CLI::error('Invalid roles: ' . implode(', ', $invalid_roles) . '. Supported roles: ' . implode(', ', $supported_roles));
        }
        
        // Validate batch size
        if ($batch_size < 1 || $batch_size > 1000) {
            WP_CLI::error('Batch size must be between 1 and 1000.');
        }
        
        WP_CLI::line('Starting Amelia customer sync...');
        WP_CLI::line('Roles to sync: ' . implode(', ', $roles));
        
        if ($dry_run) {
            WP_CLI::line('DRY RUN MODE - No customers will be created');
        }
        
        // Check if Amelia is available
        if (!$this->isAmeliaAvailable()) {
            WP_CLI::error('Amelia plugin is not installed, active, or properly configured.');
        }
        
        // Get all users with supported roles
        $total_users = $this->getTotalUsersWithRoles($roles);
        
        if ($total_users === 0) {
            WP_CLI::success('No users with specified roles found: ' . implode(', ', $roles));
            return;
        }
        
        WP_CLI::line("Found {$total_users} users with roles: " . implode(', ', $roles));
        
        // Initialize counters
        $processed = 0;
        $created = 0;
        $skipped = 0;
        $errors = 0;
        
        // Create progress bar
        $progress = WP_CLI\Utils\make_progress_bar("Processing users", $total_users);
        
        // Note: customerSyncService already initialized above for role validation
        
        // Process users in batches
        $offset = 0;
        while ($offset < $total_users) {
            $users = $this->getUsersBatchWithRoles($roles, $batch_size, $offset);
            
            foreach ($users as $user) {
                $processed++;
                
                try {
                    if ($dry_run) {
                        // In dry run, just check if customer would be created
                        $result = $this->wouldCreateCustomer($user);
                        if ($result) {
                            $created++;
                            WP_CLI::debug("Would create customer for: {$user->user_login} ({$user->user_email})");
                        } else {
                            $skipped++;
                            WP_CLI::debug("Would skip (already exists): {$user->user_login} ({$user->user_email})");
                        }
                    } else {
                        // Actually sync the customer
                        $result = $customerSyncService->syncCustomer($user, 'cli-bulk');
                        if ($result) {
                            // Check if customer was created or already existed
                            if ($this->wasCustomerJustCreated($user)) {
                                $created++;
                                WP_CLI::debug("Created customer for: {$user->user_login} ({$user->user_email})");
                            } else {
                                $skipped++;
                                WP_CLI::debug("Already exists: {$user->user_login} ({$user->user_email})");
                            }
                        } else {
                            $errors++;
                            WP_CLI::warning("Failed to sync: {$user->user_login} ({$user->user_email})");
                        }
                    }
                } catch (\Exception $e) {
                    $errors++;
                    WP_CLI::warning("Error processing {$user->user_login}: " . $e->getMessage());
                }
                
                $progress->tick();
            }
            
            $offset += $batch_size;
            
            // Show batch progress
            if ($offset < $total_users) {
                WP_CLI::debug("Completed batch. Processed: {$processed}/{$total_users}");
            }
            
            // Brief pause to prevent overwhelming the system
            usleep(100000); // 0.1 seconds
        }
        
        $progress->finish();
        
        // Show final results
        WP_CLI::line('');
        WP_CLI::line('Sync completed!');
        WP_CLI::line("Total processed: {$processed}");
        
        if ($dry_run) {
            WP_CLI::line("Would create: {$created}");
            WP_CLI::line("Would skip (existing): {$skipped}");
        } else {
            WP_CLI::line("Created: {$created}");
            WP_CLI::line("Skipped (existing): {$skipped}");
        }
        
        if ($errors > 0) {
            WP_CLI::line("Errors: {$errors}");
            WP_CLI::warning("Sync completed with {$errors} errors. Check debug output for details.");
        } else {
            WP_CLI::success('All customers synced successfully!');
        }
    }
    
    /**
     * Check if Amelia is available
     *
     * @return bool
     */
    private function isAmeliaAvailable(): bool
    {
        if (!is_plugin_active('ameliabooking/ameliabooking.php')) {
            return false;
        }
        
        if (!class_exists('\AmeliaBooking\Infrastructure\Container')) {
            return false;
        }
        
        try {
            $container = \AmeliaBooking\Infrastructure\Container::getInstance();
            return $container !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get total count of users with specified roles
     *
     * @param array $roles Array of roles to count
     * @return int
     */
    private function getTotalUsersWithRoles(array $roles): int
    {
        $user_query = new \WP_User_Query([
            'role__in' => $roles,
            'count_total' => true,
            'fields' => 'ID'
        ]);
        
        return $user_query->get_total();
    }
    
    /**
     * Get batch of users with specified roles
     *
     * @param array $roles Array of roles to retrieve
     * @param int $batch_size Number of users to retrieve
     * @param int $offset Offset for pagination
     * @return \WP_User[]
     */
    private function getUsersBatchWithRoles(array $roles, int $batch_size, int $offset): array
    {
        $user_query = new \WP_User_Query([
            'role__in' => $roles,
            'number' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        return $user_query->get_results();
    }
    
    /**
     * Check if customer would be created (for dry run)
     *
     * @param \WP_User $user
     * @return bool
     */
    private function wouldCreateCustomer(\WP_User $user): bool
    {
        try {
            $container = \AmeliaBooking\Infrastructure\Container::getInstance();
            if (!$container) {
                return false;
            }
            
            $customerRepository = $container->get('domain.users.customers.repository');
            if (!$customerRepository) {
                return false;
            }
            
            $customer = $customerRepository->getByEmail($user->user_email);
            return $customer === null; // Would create if customer doesn't exist
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if customer was just created (not existing)
     *
     * @param \WP_User $user
     * @return bool
     */
    private function wasCustomerJustCreated(\WP_User $user): bool
    {
        // Check if the user meta for Amelia customer ID was just set
        // This is a simple heuristic - in a more complex scenario you might
        // want to track this more precisely
        $amelia_id = get_user_meta($user->ID, '_amelia_customer_id', true);
        return !empty($amelia_id);
    }
} 