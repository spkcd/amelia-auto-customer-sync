# Amelia Auto Customer Sync

**Author:** [SPARKWEB Studio](https://sparkwebstudio.com)  
**Version:** 1.2.0  
**WordPress Compatibility:** 5.0+  
**PHP Compatibility:** 7.4+  

A comprehensive WordPress plugin that automatically creates Amelia customers when WordPress users with supported roles (customer, administrator, editor) log in or register. Features an intuitive admin interface, manual sync capabilities, and powerful WP-CLI tools.

## Features

- **Multi-Trigger Sync**: Automatically creates Amelia customers on both user login and registration
- **Multi-Role Support**: Supports customer, administrator, and editor roles (configurable via admin settings)
- **Admin Settings Page**: Easy-to-use settings page at Settings > Amelia Auto Sync
- **Role Configuration**: Enable/disable sync per user role with checkboxes
- **Safe Integration**: Uses Amelia's internal services via dependency injection container when available
- **Fallback Support**: Includes fallback database insertion method if container services aren't available
- **Silent Failure**: Fails gracefully when Amelia is not installed or active
- **PSR-4 Standards**: Clean, object-oriented code following PSR-4 autoloading standards
- **Duplicate Prevention**: Checks if customer already exists before creating new ones
- **User Linking**: Links WordPress users to Amelia customers via user meta and external ID
- **Contextual Logging**: Different contexts for login, registration, and CLI operations
- **Manual Sync Interface**: Custom "Amelia Sync" column in Users admin table with AJAX sync buttons
- **Bulk Sync Interface**: Comprehensive bulk sync with AJAX progress bar and batch processing
- **Dynamic Table Detection**: Automatically detects Amelia database tables regardless of naming convention

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Amelia Booking plugin (optional - plugin will work but do nothing if Amelia is not installed)

## Installation

1. Download the plugin files
2. Upload the entire `amelia-auto-customer-sync` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure role settings at **Settings > Amelia Auto Sync** in your WordPress admin
5. Select which user roles should be automatically synced to Amelia

## How It Works

1. **Multi-Hook Detection**: The plugin hooks into both `wp_login` and `user_register` actions
2. **Role Verification**: Checks if the user has any supported role (customer, administrator, editor)
3. **Amelia Check**: Verifies that Amelia plugin is active and available
4. **Duplicate Check**: Checks if an Amelia customer already exists with the same email
5. **Customer Creation**: Creates a new Amelia customer with data from the WordPress user
6. **Linking**: Stores the Amelia customer ID in WordPress user meta for future reference
7. **Context Tracking**: Logs whether sync occurred during login, registration, or CLI operation

## Customer Data Mapping

The plugin maps WordPress user data to Amelia customer fields as follows:

- **First Name**: User's `first_name` meta, falls back to first part of `display_name`
- **Last Name**: User's `last_name` meta, falls back to second part of `display_name`
- **Email**: User's email address
- **Phone**: User's `phone` meta (if available)
- **External ID**: WordPress user ID for linking
- **Note**: Automatic note indicating the customer was created from WordPress

## Code Structure

The plugin follows PSR-4 standards with a clean, modular architecture:

```
amelia-auto-customer-sync/
├── amelia-auto-customer-sync.php    # Main plugin file
├── assets/
│   ├── css/
│   │   └── admin-users-sync.css     # Users table column styles
│   └── js/
│       └── admin-users-sync.js      # Users table AJAX functionality
├── src/
│   ├── Admin/
│   │   ├── SettingsPage.php         # Admin settings interface
│   │   └── UsersTableColumn.php     # Users table custom column
│   ├── CLI/
│   │   └── SyncCustomersCommand.php # WP-CLI command
│   ├── Plugin.php                   # Main plugin class
│   ├── Services/
│   │   └── CustomerSyncService.php  # Core sync logic
│   └── Utils/
│       └── AmeliaHelper.php         # Amelia integration helper
└── README.md
```

### Classes

- **`Plugin`**: Main plugin class handling initialization and hooks
- **`CustomerSyncService`**: Core service handling user login events and customer creation
- **`AmeliaHelper`**: Utility class for safe Amelia plugin integration
- **`SettingsPage`**: Admin settings page for role configuration
- **`UsersTableColumn`**: Custom column handler for Users admin table
- **`SyncCustomersCommand`**: WP-CLI command for bulk operations

## WP-CLI Support

The plugin includes WP-CLI commands for bulk operations:

### Sync All Customers

```bash
# Sync all configured roles (from amelia_auto_sync_roles option)
wp amelia sync-customers

# Sync only customers
wp amelia sync-customers --roles=customer

# Sync customers and admins with custom batch size
wp amelia sync-customers --roles=customer,administrator --batch-size=25

# Preview what would be synced without making changes
wp amelia sync-customers --dry-run
```

**Command Options:**
- `--roles=<roles>`: Comma-separated list of roles to sync (default: uses amelia_auto_sync_roles option)
- `--batch-size=<size>`: Number of users to process per batch (default: 50, max: 1000)
- `--dry-run`: Preview mode - shows what would be synced without creating customers

**Features:**
- **Progress Bar**: Shows real-time progress during sync
- **Batch Processing**: Handles large user bases efficiently
- **Error Handling**: Continues processing even if individual users fail
- **Detailed Output**: Shows created, skipped, and error counts
- **Memory Efficient**: Processes users in batches to prevent memory issues

## Manual Sync from Users Table

The plugin adds a custom "Amelia Sync" column to the WordPress Users admin table (`Users > All Users`). This provides a convenient interface for manual customer synchronization:

### Features
- **Role-Based Display**: Shows sync buttons only for users with enabled roles (configured in settings)
- **Status Indicators**: Displays current sync status and Amelia customer ID for synced users
- **AJAX Interface**: Real-time sync with loading spinners and success/error messages
- **Security**: Proper nonce verification and permission checks (`manage_options` capability)
- **Smart Buttons**: 
  - "Sync" (blue) for unsynced users
  - "Re-sync" (gray) for already synced users
  - "Role not enabled" message for users without allowed roles

### How to Use
1. Navigate to **Users > All Users** in WordPress admin
2. Look for the "Amelia Sync" column (added before the Posts column)
3. Click the "Sync" or "Re-sync" button for any user
4. Watch the loading spinner and success/error messages
5. Button text and status update automatically after successful sync

### Column Display Logic
- **Sync Button**: Shown for users with enabled roles who haven't been synced yet
- **Re-sync Button**: Shown for users who are already synced (with customer ID display)
- **"Role not enabled"**: Shown for users whose roles are disabled in plugin settings
- **"N/A"**: Shown if user data cannot be retrieved

## Bulk Sync from Settings Page

The plugin provides a powerful bulk sync interface directly in the admin settings page (`Settings > Amelia Auto Sync`). This allows you to sync all existing users at once with real-time progress tracking.

### Features
- **Batch Processing**: Processes users in batches of 10 to prevent timeouts and server overload
- **AJAX Progress Bar**: Real-time visual progress indicator with percentage display
- **Live Results**: Shows success/failure status for each user as they're processed
- **Stop/Resume**: Ability to stop the sync process mid-operation
- **Smart Filtering**: Only processes users with roles enabled in settings
- **Comprehensive Feedback**: Detailed status messages and error reporting
- **Responsive Design**: Mobile-friendly interface that works on all devices

### How to Use
1. Navigate to **Settings > Amelia Auto Sync**
2. Configure your desired roles and save settings
3. Scroll down to the "Bulk Sync Users" section
4. Review the number of users that will be synced
5. Click "Start Bulk Sync" to begin the process
6. Watch the real-time progress bar and results
7. Optionally click "Stop Sync" to halt the process

### Interface Elements
- **User Count Display**: Shows exactly how many users will be synced
- **Role Summary**: Lists which roles are enabled for sync
- **Progress Bar**: Animated progress indicator with percentage
- **Status Messages**: Real-time updates on current operation
- **Results Panel**: Scrollable list showing individual user sync results
- **Control Buttons**: Start/Stop functionality with proper state management

### Technical Details
- **Batch Size**: 10 users per batch (optimized for performance)
- **Timeout Prevention**: 30-second AJAX timeout with proper error handling
- **Memory Efficient**: Processes users incrementally to avoid memory limits
- **Error Recovery**: Continues processing even if individual users fail
- **Security**: Full nonce verification and permission checks

## Debugging

The plugin includes comprehensive logging:

- Enable `WP_DEBUG` to see detailed debug messages
- Check your WordPress error logs for any issues
- All operations fail silently to prevent disrupting user login experience
- Use `WP_CLI::debug()` output with `--debug` flag for CLI operations

## Customization

The plugin is designed to be extensible. You can:

### Admin Settings

Configure role synchronization through the WordPress admin:

1. Navigate to **Settings > Amelia Auto Sync**
2. Check/uncheck roles you want to sync: Customer, Administrator, Editor
3. Click "Save Settings"

### Advanced Role Management

For developers, you can programmatically customize roles using the filter:

```php
// Customize roles dynamically
add_filter('amelia_auto_sync_roles', function($roles) {
    // Add a custom role
    $roles[] = 'shop_manager';
    
    // Remove a role 
    $roles = array_diff($roles, ['administrator']);
    
    return $roles;
});

// Or directly modify the option
update_option('amelia_auto_sync_roles', ['customer', 'editor', 'shop_manager']);
```

**Default supported roles:** `customer` (expandable via admin settings or filters)

### Other Customizations

- Modify customer data mapping in `CustomerSyncService::prepareCustomerData()`
- Add additional checks in `CustomerSyncService::syncCustomer()`
- Extend role-specific logic using the filterable role system

## Security

- All user input is properly sanitized
- Uses WordPress's built-in functions for database operations
- Fails gracefully without exposing sensitive information
- Follows WordPress security best practices

## Support

This plugin is designed to work with the Amelia Booking plugin. For issues related to:

- **Amelia Integration**: Ensure Amelia plugin is properly installed and activated
- **Customer Creation**: Check WordPress error logs for detailed error messages
- **User Roles**: Verify users have supported roles (customer, administrator, editor) assigned
- **Multi-Hook Sync**: Users are synced on both registration and login for maximum coverage

## About SPARKWEB Studio

[SPARKWEB Studio](https://sparkwebstudio.com) is a web development agency specializing in WordPress solutions, custom plugins, and digital experiences. We create powerful, user-friendly tools that help businesses streamline their operations and enhance their online presence.

### Our Services
- **Custom WordPress Development**: Tailored solutions for unique business needs
- **Plugin Development**: Professional WordPress plugins with enterprise-grade features
- **Website Optimization**: Performance, security, and user experience improvements
- **Digital Strategy**: Comprehensive approaches to online business growth

### Contact Us
- **Website**: [https://sparkwebstudio.com](https://sparkwebstudio.com)
- **Email**: support@sparkwebstudio.com
- **Support**: Professional support available for all our plugins

## License

This plugin is licensed under the GPL v2 or later.

**Copyright 2024 SPARKWEB Studio**

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

## Changelog

### Version 1.2.0
- **NEW: Bulk Sync Interface** - Added comprehensive bulk sync functionality to admin settings page
- **AJAX Progress Bar** - Real-time progress tracking with visual progress bar during bulk operations
- **Batch Processing** - Users processed in batches of 10 to prevent timeouts and server overload
- **Dynamic Table Detection** - Enhanced Amelia database table detection for custom table names
- **Live Results Display** - Shows success/failure status for each user during bulk sync
- **Stop/Resume Functionality** - Ability to stop bulk sync operations mid-process
- **Enhanced Database Compatibility** - Support for various Amelia table naming conventions
- **Improved Error Handling** - Better error feedback and user experience throughout bulk operations

### Version 1.1.0
- **NEW: Manual Sync Interface** - Added custom "Amelia Sync" column to Users admin table
- **AJAX Sync Buttons** - Real-time sync with loading indicators and success/error messages  
- **Smart Status Display** - Shows sync status, customer IDs, and role-based button visibility
- **Enhanced Security** - Proper nonce verification and permission checks for manual sync
- **User Experience** - Automatic button text updates and status changes after sync

### Version 1.0.0
- Initial release with multi-hook and multi-role support
- Automatic customer sync on both user login and registration
- Support for customer, administrator, and editor roles
- **Admin settings page** at Settings > Amelia Auto Sync for role configuration
- **Role management via checkboxes** - enable/disable sync per role
- **Settings persistence** using WordPress options table
- **Filterable role management** via `amelia_auto_sync_roles` hook (still available for developers)
- Central service class with shared sync logic
- PSR-4 compliant code structure
- Safe Amelia integration with fallback support
- Comprehensive error handling and contextual logging
- Admin notices for missing Amelia dependency
- WP-CLI support with `wp amelia sync-customers` command
- Multi-role CLI support with dynamic `--roles` option
- Batch processing with progress indicators
- Dry-run mode for testing bulk operations 