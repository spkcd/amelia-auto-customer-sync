<?php

namespace AmeliaAutoCustomerSync\Utils;

/**
 * Amelia Helper Class
 *
 * Provides safe integration with Amelia plugin services
 *
 * @package AmeliaAutoCustomerSync\Utils
 */
class AmeliaHelper
{
    /**
     * Check if Amelia plugin is active and available
     *
     * @return bool
     */
    public function isAmeliaActive(): bool
    {
        // Check if Amelia plugin is active
        if (!is_plugin_active('ameliabooking/ameliabooking.php')) {
            return false;
        }

        // Check if Amelia classes are available
        return class_exists('AmeliaBooking\Infrastructure\WP\Integrations\ThriveAutomator\DataFields\Customer\CustomerEmail');
    }

    /**
     * Check if Amelia customer exists with given email
     *
     * @param string $email Customer email
     * @return bool
     */
    public function customerExists(string $email): bool
    {
        try {
            // Get Amelia's container if available
            $container = $this->getAmeliaContainer();
            if (!$container) {
                return false;
            }

            // Get customer repository
            $customerRepository = $container->get('domain.users.customers.repository');
            if (!$customerRepository) {
                return false;
            }

            // Search for customer by email
            $customer = $customerRepository->getByEmail($email);
            
            return $customer !== null;
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error checking customer existence - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create Amelia customer
     *
     * @param array $customerData Customer data
     * @return int|null Customer ID if successful, null if failed
     */
    public function createCustomer(array $customerData): ?int
    {
        try {
            // Get Amelia's container
            $container = $this->getAmeliaContainer();
            if (!$container) {
                return null;
            }

            // Get required services
            $customerRepository = $container->get('domain.users.customers.repository');
            $userRepository = $container->get('domain.users.repository');
            
            if (!$customerRepository || !$userRepository) {
                return null;
            }

            // Create customer entity
            $customerClass = $container->get('domain.users.customers.customer');
            if (!$customerClass) {
                // Fallback: try to create customer directly
                return $this->createCustomerFallback($customerData);
            }

            // Prepare customer object
            $customer = new $customerClass();
            $customer->setFirstName($customerData['firstName']);
            $customer->setLastName($customerData['lastName']);
            $customer->setEmail($customerData['email']);
            
            if (!empty($customerData['phone'])) {
                $customer->setPhone($customerData['phone']);
            }
            
            if (!empty($customerData['note'])) {
                $customer->setNote($customerData['note']);
            }
            
            if (!empty($customerData['externalId'])) {
                $customer->setExternalId($customerData['externalId']);
            }

            // Set customer status to active
            $customer->setStatus('visible');
            
            // Save customer
            $customerId = $customerRepository->add($customer);
            
            return $customerId;
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error creating customer - ' . $e->getMessage());
            return $this->createCustomerFallback($customerData);
        }
    }

    /**
     * Get Amelia's dependency injection container
     *
     * @return mixed|null
     */
    private function getAmeliaContainer()
    {
        try {
            // Check different ways Amelia might expose its container
            global $ameliaContainer;
            if (isset($ameliaContainer)) {
                return $ameliaContainer;
            }

            // Try to get container through Amelia's main class
            if (class_exists('AmeliaBooking\Plugin')) {
                $plugin = \AmeliaBooking\Plugin::getInstance();
                if (method_exists($plugin, 'getContainer')) {
                    return $plugin->getContainer();
                }
            }

            // Try alternative approaches
            if (function_exists('ameliaGetContainer')) {
                return ameliaGetContainer();
            }

            return null;
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error getting container - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback method to create customer using direct database insertion
     *
     * @param array $customerData Customer data
     * @return int|null Customer ID if successful, null if failed
     */
    private function createCustomerFallback(array $customerData): ?int
    {
        try {
            global $wpdb;
            
            // Get Amelia's customer table name
            $tableName = $wpdb->prefix . 'amelia_users';
            
            // Check if table exists
            $tableExists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $tableName
            ));
            
            if (!$tableExists) {
                error_log('Amelia Auto Customer Sync: Amelia users table does not exist');
                return null;
            }

            // Prepare data for insertion
            $data = [
                'firstName' => $customerData['firstName'],
                'lastName' => $customerData['lastName'],
                'email' => $customerData['email'],
                'phone' => $customerData['phone'] ?? '',
                'note' => $customerData['note'] ?? '',
                'externalId' => $customerData['externalId'] ?? null,
                'status' => 'visible',
                'type' => 'customer'
            ];

            // Insert customer
            $result = $wpdb->insert($tableName, $data);
            
            if ($result === false) {
                error_log('Amelia Auto Customer Sync: Failed to insert customer - ' . $wpdb->last_error);
                return null;
            }
            
            return $wpdb->insert_id;
            
        } catch (\Exception $e) {
            error_log('Amelia Auto Customer Sync: Error in fallback customer creation - ' . $e->getMessage());
            return null;
        }
    }
} 