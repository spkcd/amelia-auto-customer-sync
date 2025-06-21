# Changelog

All notable changes to the Amelia Auto Customer Sync plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2024-12-19

### Added
- **Bulk Sync Interface**: Added comprehensive bulk sync functionality to the admin settings page
- **AJAX Progress Bar**: Real-time progress tracking with visual progress bar during bulk operations
- **Batch Processing**: Users are processed in batches of 10 to prevent timeouts and server overload
- **Dynamic Table Detection**: Enhanced Amelia database table detection to work with custom table names
- **Sync Results Display**: Live results showing success/failure status for each user during bulk sync
- **Stop/Resume Functionality**: Ability to stop bulk sync operations mid-process
- **Professional UI Elements**:
  - Animated progress bar with percentage display
  - Real-time status updates and messages
  - Scrollable results panel with individual user feedback
  - Responsive design for mobile compatibility

### Enhanced
- **Amelia Detection**: Improved plugin detection with multiple fallback methods for various Amelia table naming conventions
- **Database Compatibility**: Added support for custom Amelia table names (e.g., `ameliabooking_users`, `amelia_customers`)
- **Error Handling**: Enhanced error handling and user feedback throughout the bulk sync process
- **User Interface**: Professional progress indicators and status messages for better user experience
- **Security**: Comprehensive nonce verification and permission checks for bulk operations

### Fixed
- **Table Name Issues**: Resolved hardcoded table name problems that prevented plugin from working with custom Amelia installations
- **Database Compatibility**: Fixed compatibility issues with different Amelia database configurations
- **Plugin Detection**: Fixed false negatives in Amelia plugin detection due to timing and naming variations

### Technical Improvements
- **Dynamic Table Discovery**: Automatic detection of Amelia users table regardless of naming convention
- **Column Validation**: Verifies table structure before attempting operations
- **Batch Processing**: Efficient memory usage and timeout prevention through batched operations
- **AJAX Architecture**: Clean separation of frontend and backend processing
- **Asset Management**: Dedicated CSS/JS files for bulk sync functionality

## [1.1.0] - 2024-12-19

### Added
- **Manual Sync Interface**: Custom "Amelia Sync" column in WordPress Users admin table
- **AJAX Sync Buttons**: Real-time sync functionality with loading indicators
- **Enhanced User Experience**: 
  - Loading spinners during sync operations
  - Success messages with green checkmarks (✓)
  - Error messages with red X marks (✗)
  - Auto-hiding feedback messages after 3 seconds
- **Smart Status Display**: 
  - Shows sync status and Amelia customer IDs
  - Role-based button visibility
  - Dynamic button text updates (Sync → Re-sync)
- **Dedicated Asset Files**:
  - External JavaScript file (`assets/js/admin-users-sync.js`)
  - External CSS file (`assets/css/admin-users-sync.css`)
  - Proper WordPress asset enqueuing with versioning
- **Enhanced Security**: 
  - Nonce verification for AJAX requests
  - Permission checks (`manage_options` capability)
  - Input validation and sanitization
- **Improved Error Handling**:
  - Specific error messages for different failure scenarios
  - Network error detection and reporting
  - Server error handling with user-friendly messages
- **Internationalization**: All user-facing strings are translatable
- **Responsive Design**: Mobile-friendly interface for admin tables

### Changed
- **Plugin Description**: Updated to reflect new manual sync capabilities
- **Asset Management**: Moved from inline scripts/styles to external files
- **Code Organization**: Better separation of concerns between PHP, JavaScript, and CSS
- **User Interface**: Enhanced visual feedback and status indicators
- **Documentation**: Comprehensive updates to README with new features

### Technical Improvements
- **JavaScript Architecture**: Clean, modular JavaScript with proper event delegation
- **CSS Standards**: WordPress admin color scheme compliance
- **Performance**: External assets can be cached by browsers
- **Maintainability**: Easier to update and maintain individual components
- **Code Quality**: Comprehensive code comments and documentation

## [1.0.0] - 2024-11-15

### Added
- **Initial Release**: Core functionality for automatic customer synchronization
- **Multi-Trigger Sync**: Automatic sync on both user login (`wp_login`) and registration (`user_register`)
- **Multi-Role Support**: Configurable support for customer, administrator, and editor roles
- **Admin Settings Page**: User-friendly settings interface at Settings > Amelia Auto Sync
- **Role Configuration**: Enable/disable sync per user role with checkboxes
- **Safe Amelia Integration**: 
  - Uses Amelia's internal services via dependency injection container
  - Fallback database insertion method when container services unavailable
  - Silent failure when Amelia plugin not installed/active
- **Customer Data Mapping**:
  - WordPress user data to Amelia customer fields
  - First name, last name, email, phone (if available)
  - External ID linking for WordPress user association
  - Automatic notes indicating WordPress origin
- **Duplicate Prevention**: Checks existing customers before creating new ones
- **User Linking**: Links WordPress users to Amelia customers via user meta
- **Contextual Logging**: Different contexts for login, registration, and CLI operations
- **WP-CLI Support**: 
  - `wp amelia sync-customers` command for bulk operations
  - Batch processing with configurable batch sizes
  - Dry-run mode for testing
  - Progress bars and detailed output
  - Error handling and memory efficiency
- **Advanced Role Management**:
  - Filterable role system via `amelia_auto_sync_roles` hook
  - Dynamic option-based role configuration
  - Developer customization capabilities
- **Admin Notices**: Smart notifications for missing Amelia dependency
- **Security Features**:
  - WordPress nonces for form security
  - Capability checks for admin access
  - Input sanitization and validation
- **Code Architecture**:
  - PSR-4 compliant autoloading
  - Singleton pattern for main plugin class
  - Service-oriented architecture
  - Clean separation of concerns

### Technical Features
- **WordPress Standards**: Follows WordPress coding standards and best practices
- **Error Handling**: Comprehensive error handling with graceful failures
- **Debugging Support**: Debug logging when `WP_DEBUG_LOG` enabled
- **Extensibility**: Hook system for developers to extend functionality
- **Database Integration**: Safe database operations using WordPress functions
- **Multisite Compatibility**: Proper handling of multisite environments

---

## Development Notes

### Version Numbering
- **Major versions** (x.0.0): Significant new features or breaking changes
- **Minor versions** (x.y.0): New features, enhancements, backward compatible
- **Patch versions** (x.y.z): Bug fixes, security updates, minor improvements

### Plugin Standards
This plugin follows WordPress Plugin Development Standards:
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Plugin Security Guidelines](https://developer.wordpress.org/plugins/security/)
- [Internationalization Best Practices](https://developer.wordpress.org/plugins/internationalization/)
- [WordPress Plugin API](https://developer.wordpress.org/plugins/hooks/)

### Testing
Each release is tested with:
- WordPress versions 5.0+ through latest
- PHP versions 7.4+ through 8.3
- Popular WordPress themes and plugins
- Multisite and single-site installations
- Various Amelia plugin versions

### Support
For support, feature requests, or bug reports:
- Visit: [https://sparkwebstudio.com](https://sparkwebstudio.com)
- Email: support@sparkwebstudio.com 