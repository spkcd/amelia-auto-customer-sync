<?php

namespace AmeliaAutoCustomerSync\Services;

/**
 * Customer Sync Service
 *
 * Handles the synchronization of WordPress users with Amelia customers
 * Uses \AmeliaBooking\Infrastructure\Container for direct integration
 *
 * @package AmeliaAutoCustomerSync\Services
 */
class CustomerSyncService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // No dependencies needed - using Container directly
    }

    /**
     * Get supported roles for Amelia customer sync
     *
     * Returns the list of WordPress roles that should be synced to Amelia customers.
     * Uses dynamic list from options table with filter support.
     *
     * @return array Array of supported role names
     */
    public function getSupportedRoles(): array
    {
        // Get roles from options table with default fallback
        $roles = get_option('amelia_auto_sync_roles', ['customer']);
        
        // Ensure we have an array
        if (!is_array($roles)) {
            $roles = ['customer'];
        }
        
        /**
         * Filter the roles that should be synced to Amelia customers
         *
         * @since 1.0.0
         *
         * @param array $roles Array of role names to sync
         */
        return apply_filters('amelia_auto_sync_roles', $roles);
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
            $this->syncCustomer($user);
        } catch (\Exception $e) {
            // Fail silently but log the error
            error_log('Amelia Auto Customer Sync Error: ' . $e->getMessage());
        }
    }

    /**
     * Sync customer - main idempotent method
     *
     * Accepts a WP_User object, validates role, checks for existing customer,
     * and creates new Amelia customer if needed. Logic is consistent between
     * login and registration triggers.
     *
     * @param \WP_User $user The WordPress user object
     * @param string $context Context for logging (e.g., 'login', 'registration')
     * @return bool True if customer was created or already exists, false on failure
     */
    public function syncCustomer(\WP_User $user, string $context = 'sync'): bool
    {
        try {
            // Step 1: Validate WP_User object
            if (!$user instanceof \WP_User || !$user->exists()) {
                $this->logDebug("Invalid or non-existent user object - Context: {$context}");
                return false;
            }

            // Step 2: Validate user role against supported roles
            $userRole = $this->validateAndGetUserRole($user);
            if (!$userRole) {
                $supportedRoles = $this->getSupportedRoles();
                $this->logDebug("User {$user->user_login} does not have any supported role (" . implode(', ', $supportedRoles) . ") - Context: {$context}");
                return false;
            }

            // Step 3: Check if Amelia is active and loaded
            if (!$this->isAmeliaActiveAndLoaded()) {
                $this->logDebug("Amelia plugin is not active or loaded - Context: {$context}");
                return false;
            }

            // Step 4: Check if Amelia customer already exists via email (idempotent)
            if ($this->customerExistsInAmelia($user->user_email)) {
                $this->logDebug("Customer already exists for email: {$user->user_email} (Role: {$userRole}) - Context: {$context}");
                return true; // Customer exists, operation successful
            }

            // Step 5: Create new Amelia customer with validated data
            $customerId = $this->createAmeliaCustomer($user, $userRole, $context);
            
            if ($customerId) {
                $this->logDebug("Successfully created Amelia customer (ID: {$customerId}) for user: {$user->user_login} (Role: {$userRole}) - Context: {$context}");
                return true;
            } else {
                $this->logDebug("Failed to create Amelia customer for user: {$user->user_login} (Role: {$userRole}) - Context: {$context}");
                return false;
            }

        } catch (\Exception $e) {
            error_log("Amelia Auto Customer Sync Error in syncCustomer ({$context}): " . $e->getMessage());
            return false;
        }
    }



    /**
     * Validate user role and return the primary supported role
     *
     * Checks if user has any of the allowed roles from getSupportedRoles()
     *
     * @param \WP_User $user The user object
     * @return string|null The primary supported role or null if none found
     */
    private function validateAndGetUserRole(\WP_User $user): ?string
    {
        // Ensure user has roles array
        if (empty($user->roles) || !is_array($user->roles)) {
            return null;
        }

        // Get supported roles with filtering support
        $supportedRoles = $this->getSupportedRoles();
        
        // Check for supported roles in the order they appear in the supported roles array
        foreach ($supportedRoles as $role) {
            if (in_array($role, $user->roles, true)) {
                return $role;
            }
        }
        
        return null;
    }

    /**
     * Check if user has any of the supported roles
     *
     * @param \WP_User $user The user object
     * @return bool
     */
    private function userHasSupportedRole(\WP_User $user): bool
    {
        return $this->validateAndGetUserRole($user) !== null;
    }

    /**
     * Check if Amelia is active and loaded
     *
     * @return bool
     */
    private function isAmeliaActiveAndLoaded(): bool
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
        global $wpdb;
        $table_name = $wpdb->prefix . 'amelia_users';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if ($table_exists) {
            return true;
        }

        // Method 4: Check for Amelia constants
        if (defined('AMELIA_VERSION') || defined('AMELIA_URL')) {
            return true;
        }

        return false;
    }

    /**
     * Check if customer exists in Amelia using the Container or fallback methods
     *
     * @param string $email Customer email
     * @return bool
     */
    private function customerExistsInAmelia(string $email): bool
    {
        try {
            // Method 1: Try using Container (preferred)
            if (class_exists('\AmeliaBooking\Infrastructure\Container')) {
                try {
                    $container = \AmeliaBooking\Infrastructure\Container::getInstance();
                    if ($container) {
                        $customerRepository = $container->get('domain.users.customers.repository');
                        if ($customerRepository) {
                            $customer = $customerRepository->getByEmail($email);
                            return $customer !== null;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue to fallback method
                }
            }

            // Method 2: Fallback - Direct database query
            return $this->customerExistsInAmeliaDatabase($email);
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error checking customer existence - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if customer exists in Amelia database directly
     *
     * @param string $email Customer email
     * @return bool
     */
    private function customerExistsInAmeliaDatabase(string $email): bool
    {
        global $wpdb;
        
        // Try to find the correct Amelia users table
        $table_name = $this->getAmeliaUsersTableName();
        if (!$table_name) {
            return false;
        }
        
        // Query for customer by email
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE email = %s AND type = 'customer'",
            $email
        ));
        
        return $customer !== null;
    }

    /**
     * Create Amelia customer from WordPress user using Container or fallback methods
     *
     * @param \WP_User $user The WordPress user
     * @param string $userRole The user's role for context
     * @param string $context The sync context (login, registration, etc.)
     * @return int|null Customer ID if successful, null if failed
     */
    private function createAmeliaCustomer(\WP_User $user, string $userRole, string $context): ?int
    {
        try {
            // Prepare customer data with validation and fallbacks
            $customerData = $this->prepareCustomerData($user, $userRole, $context);
            
            // Validate required fields
            if (empty($customerData['firstName']) || empty($customerData['email'])) {
                error_log("Amelia Auto Customer Sync: Missing required fields for user {$user->user_login}");
                return null;
            }

            // Method 1: Try using Container (preferred)
            if (class_exists('\AmeliaBooking\Infrastructure\Container')) {
                try {
                    $customerId = $this->createAmeliaCustomerViaContainer($customerData, $user);
                    if ($customerId) {
                        return $customerId;
                    }
                } catch (\Exception $e) {
                    error_log('Amelia Auto Customer Sync: Container method failed, trying fallback - ' . $e->getMessage());
                }
            }

            // Method 2: Fallback - Direct database insertion
            return $this->createAmeliaCustomerViaDatabase($customerData, $user);
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error creating customer - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Amelia customer using the Container method
     *
     * @param array $customerData Prepared customer data
     * @param \WP_User $user WordPress user
     * @return int|null Customer ID if successful, null if failed
     */
    private function createAmeliaCustomerViaContainer(array $customerData, \WP_User $user): ?int
    {
        $container = \AmeliaBooking\Infrastructure\Container::getInstance();
        if (!$container) {
            return null;
        }

        // Get customer repository
        $customerRepository = $container->get('domain.users.customers.repository');
        if (!$customerRepository) {
            return null;
        }

        // Create customer entity
        $customerEntity = $container->get('domain.users.customers.customer');
        if (!$customerEntity) {
            return null;
        }

        // Set customer properties with validated data
        $customerEntity->setFirstName($customerData['firstName']);
        if (!empty($customerData['lastName'])) {
            $customerEntity->setLastName($customerData['lastName']);
        }
        $customerEntity->setEmail($customerData['email']);
        $customerEntity->setExternalId($customerData['userId']);
        $customerEntity->setStatus('visible');
        
        // Add contextual note about the user's role and sync context
        if (!empty($customerData['note'])) {
            $customerEntity->setNote($customerData['note']);
        }
        
        // Save customer
        $customerId = $customerRepository->add($customerEntity);
        
        if ($customerId) {
            // Store the Amelia customer ID in user meta for future reference
            update_user_meta($user->ID, '_amelia_customer_id', $customerId);
            return $customerId;
        }
        
        return null;
    }

    /**
     * Create Amelia customer using direct database insertion
     *
     * @param array $customerData Prepared customer data
     * @param \WP_User $user WordPress user
     * @return int|null Customer ID if successful, null if failed
     */
    private function createAmeliaCustomerViaDatabase(array $customerData, \WP_User $user): ?int
    {
        global $wpdb;
        
        // Try to find the correct Amelia users table
        $table_name = $this->getAmeliaUsersTableName();
        if (!$table_name) {
            return null;
        }

        // Prepare data for insertion
        $insert_data = [
            'type' => 'customer',
            'status' => 'visible',
            'externalId' => $customerData['userId'],
            'firstName' => $customerData['firstName'],
            'lastName' => $customerData['lastName'],
            'email' => $customerData['email'],
            'note' => $customerData['note'],
            'phone' => '', // Default empty phone
            'gender' => '', // Default empty gender
            'birthday' => null, // Default null birthday
            'timeZone' => '', // Default empty timezone
            'translations' => null, // Default null translations
            'countryPhoneIso' => '', // Default empty country code
        ];

        $insert_format = [
            '%s', // type
            '%s', // status
            '%d', // externalId
            '%s', // firstName
            '%s', // lastName
            '%s', // email
            '%s', // note
            '%s', // phone
            '%s', // gender
            '%s', // birthday
            '%s', // timeZone
            '%s', // translations
            '%s', // countryPhoneIso
        ];

        $result = $wpdb->insert($table_name, $insert_data, $insert_format);
        
        if ($result !== false) {
            $customerId = $wpdb->insert_id;
            // Store the Amelia customer ID in user meta for future reference
            update_user_meta($user->ID, '_amelia_customer_id', $customerId);
            return $customerId;
        }
        
        return null;
    }

    /**
     * Prepare customer data from WordPress user with validation and fallbacks
     *
     * Handles optional fields: if first_name or last_name are missing, 
     * falls back to display_name. Consistent logic for all triggers.
     *
     * @param \WP_User $user The WordPress user
     * @param string $userRole The user's validated role (customer, administrator, editor)
     * @param string $context The sync context for logging
     * @return array Customer data array with validated fields
     */
    private function prepareCustomerData(\WP_User $user, string $userRole, string $context): array
    {
        // Get name fields with fallback logic
        $nameData = $this->extractNameFields($user);
        
        // Validate email
        $email = sanitize_email($user->user_email);
        if (empty($email)) {
            throw new \InvalidArgumentException("User {$user->user_login} has no valid email address");
        }

        // Create contextual note based on user role and sync context
        $roleContext = $this->getRoleDisplayName($userRole);
        $contextNote = $this->generateSyncNote($roleContext, $user, $context);

        return [
            'firstName' => sanitize_text_field($nameData['firstName']),
            'lastName' => sanitize_text_field($nameData['lastName']), // Optional - can be empty
            'email' => $email,
            'userId' => $user->ID, // WordPress user ID for linking
            'note' => $contextNote
        ];
    }

    /**
     * Extract name fields with comprehensive fallback logic
     *
     * @param \WP_User $user The WordPress user
     * @return array Array with firstName and lastName
     */
    private function extractNameFields(\WP_User $user): array
    {
        // Get meta fields
        $firstName = trim(get_user_meta($user->ID, 'first_name', true) ?: '');
        $lastName = trim(get_user_meta($user->ID, 'last_name', true) ?: '');
        
        // If no first/last name, try to split display_name
        if (empty($firstName) && empty($lastName) && !empty($user->display_name)) {
            $displayName = trim($user->display_name);
            $nameParts = preg_split('/\s+/', $displayName, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
        }
        
        // If still no first name, try user_nicename
        if (empty($firstName) && !empty($user->user_nicename)) {
            $firstName = str_replace(['-', '_'], ' ', $user->user_nicename);
        }
        
        // Final fallback to username
        if (empty($firstName)) {
            $firstName = $user->user_login;
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName
        ];
    }

    /**
     * Get display name for role
     *
     * @param string $userRole The user role
     * @return string Display name for the role
     */
    private function getRoleDisplayName(string $userRole): string
    {
        return match ($userRole) {
            'administrator' => 'WordPress Administrator',
            'editor' => 'WordPress Editor', 
            'customer' => 'WordPress Customer',
            default => 'WordPress User'
        };
    }

    /**
     * Generate sync note with context
     *
     * @param string $roleContext Role display name
     * @param \WP_User $user WordPress user
     * @param string $context Sync context
     * @return string Generated note
     */
    private function generateSyncNote(string $roleContext, \WP_User $user, string $context): string
    {
        $contextDisplay = match ($context) {
            'login' => 'user login',
            'registration' => 'user registration',
            'cli-bulk' => 'CLI bulk sync',
            default => 'sync operation'
        };

        return sprintf(
            __('Auto-synced from %s during %s: %s (%s)', 'amelia-auto-customer-sync'),
            $roleContext,
            $contextDisplay,
            $user->user_login,
            $user->user_email
        );
    }

    /**
     * Log debug message
     *
     * @param string $message The message to log
     */
    private function logDebug(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Amelia Auto Customer Sync: ' . $message);
        }
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