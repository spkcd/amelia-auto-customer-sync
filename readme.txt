=== Amelia Auto Customer Sync ===
Contributors: sparkwebstudio
Donate link: https://sparkwebstudio.com/donate
Tags: amelia, booking, customers, sync, automation, users, roles, ajax, cli
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically creates Amelia customers when WordPress users with supported roles log in or register. Features admin settings, manual sync interface, and WP-CLI support.

== Description ==

**Amelia Auto Customer Sync** is a comprehensive WordPress plugin that seamlessly integrates your WordPress user management with the Amelia Booking plugin. It automatically creates Amelia customers when WordPress users with supported roles log in or register, eliminating the need for manual customer creation.

= Key Features =

* **Automatic Sync**: Creates Amelia customers on user login and registration
* **Multi-Role Support**: Configurable support for customer, administrator, and editor roles
* **Admin Settings**: Easy-to-use settings page for role configuration
* **Manual Sync Interface**: Custom column in Users table with AJAX sync buttons
* **WP-CLI Support**: Bulk operations with `wp amelia sync-customers` command
* **Safe Integration**: Uses Amelia's internal services with fallback methods
* **Duplicate Prevention**: Checks for existing customers before creating new ones
* **User Linking**: Links WordPress users to Amelia customers via metadata

= Manual Sync Interface =

The plugin adds a custom "Amelia Sync" column to the WordPress Users admin table, providing:

* **Role-Based Display**: Shows sync buttons only for users with enabled roles
* **Status Indicators**: Displays current sync status and Amelia customer IDs
* **AJAX Interface**: Real-time sync with loading spinners and success/error messages
* **Smart Buttons**: Different button states for synced and unsynced users

= WP-CLI Support =

Powerful command-line tools for bulk operations:

* `wp amelia sync-customers` - Sync all configured roles
* `--roles=customer,admin` - Sync specific roles only
* `--batch-size=50` - Configure batch processing
* `--dry-run` - Preview mode without making changes

= Developer Features =

* **PSR-4 Standards**: Clean, object-oriented code structure
* **Hook System**: Filterable role management via `amelia_auto_sync_roles`
* **Extensible**: Service-oriented architecture for easy customization
* **Debug Support**: Comprehensive logging when `WP_DEBUG_LOG` enabled

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* Amelia Booking plugin (optional - plugin will work but do nothing if Amelia is not installed)

= About SPARKWEB Studio =

SPARKWEB Studio is a web development agency specializing in WordPress solutions, custom plugins, and digital experiences. We create powerful, user-friendly tools that help businesses streamline their operations.

Visit us at [sparkwebstudio.com](https://sparkwebstudio.com) for professional WordPress development services.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/amelia-auto-customer-sync` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to **Settings > Amelia Auto Sync** to configure which user roles should be synced.
4. Select the checkboxes for roles you want to automatically sync to Amelia.
5. Click "Save Settings" to apply your configuration.

== Frequently Asked Questions ==

= Does this plugin require the Amelia Booking plugin? =

While the plugin is designed to work with Amelia, it will not cause errors if Amelia is not installed. It will simply do nothing until Amelia is available. You'll see admin notices if Amelia is missing.

= Which user roles are supported? =

By default, the plugin supports customer, administrator, and editor roles. You can enable/disable each role individually in the settings page. Developers can add custom roles using the `amelia_auto_sync_roles` filter.

= Can I sync existing users manually? =

Yes! The plugin adds a custom "Amelia Sync" column to the Users admin table where you can manually sync individual users with AJAX buttons. You can also use the WP-CLI command for bulk operations.

= Will this create duplicate customers? =

No, the plugin checks for existing Amelia customers with the same email address before creating new ones. It's designed to be idempotent - running it multiple times won't create duplicates.

= Can I customize which user data is synced? =

The plugin maps WordPress user data to Amelia customer fields automatically (first name, last name, email, phone if available). Developers can customize this mapping by modifying the `CustomerSyncService` class.

= Does this work with multisite? =

Yes, the plugin is compatible with WordPress multisite installations and includes proper multisite user validation.

== Screenshots ==

1. **Admin Settings Page** - Configure which user roles should be automatically synced to Amelia
2. **Users Table Column** - Custom "Amelia Sync" column with manual sync buttons and status indicators
3. **AJAX Sync Interface** - Real-time sync with loading spinners and success/error messages
4. **WP-CLI Commands** - Powerful command-line tools for bulk operations with progress bars

== Changelog ==

= 1.1.0 - 2024-12-19 =
* **New**: Manual Sync Interface - Custom "Amelia Sync" column in Users admin table
* **New**: AJAX sync buttons with real-time feedback and loading indicators
* **New**: Smart status display showing sync status and Amelia customer IDs
* **New**: Dedicated JavaScript and CSS files for better performance
* **Enhanced**: Error handling with specific messages for different failure scenarios
* **Enhanced**: Responsive design for mobile-friendly admin interface
* **Enhanced**: Security with nonce verification and permission checks
* **Improved**: Code organization with better separation of concerns
* **Updated**: Plugin description and documentation

= 1.0.0 - 2024-11-15 =
* **Initial Release**: Core functionality for automatic customer synchronization
* **New**: Multi-trigger sync on both user login and registration
* **New**: Multi-role support with admin settings page
* **New**: WP-CLI support with bulk operations and batch processing
* **New**: Safe Amelia integration with fallback methods
* **New**: Duplicate prevention and user linking
* **New**: Contextual logging and debug support
* **New**: PSR-4 compliant code architecture

== Upgrade Notice ==

= 1.1.0 =
Major update with manual sync interface! New Users table column with AJAX buttons for individual user sync. Enhanced UI, better error handling, and improved performance. Recommended for all users.

= 1.0.0 =
Initial release. Install to automatically sync WordPress users to Amelia customers.

== Support ==

For support, feature requests, or bug reports:

* Visit our website: [https://sparkwebstudio.com](https://sparkwebstudio.com)
* Email us: support@sparkwebstudio.com
* Professional support available for all our plugins

== Development ==

This plugin follows WordPress coding standards and best practices:

* PSR-4 autoloading
* Singleton pattern
* Service-oriented architecture
* Comprehensive error handling
* Security best practices
* Internationalization ready

The plugin is actively maintained and regularly updated with new features and improvements. 