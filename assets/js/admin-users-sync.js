/**
 * Amelia Auto Customer Sync - Users Table Column JavaScript
 * 
 * Handles AJAX sync functionality for the custom "Amelia Sync" column
 * in the WordPress Users admin table.
 */
(function($) {
    'use strict';

    /**
     * Initialize the sync functionality when DOM is ready
     */
    $(document).ready(function() {
        initializeSyncButtons();
    });

    /**
     * Initialize sync button event handlers
     */
    function initializeSyncButtons() {
        // Use event delegation to handle dynamically added buttons
        $(document).on('click', '.amelia-sync-btn', handleSyncButtonClick);
    }

    /**
     * Handle sync button click events
     * 
     * @param {Event} e - Click event
     */
    function handleSyncButtonClick(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $wrapper = $button.closest('.amelia-sync-wrapper');
        var userId = $button.data('user-id');
        var nonce = $button.data('nonce');
        
        // Validate required data
        if (!userId || !nonce) {
            showError($wrapper, ameliaUsersSync.messages.invalidData);
            return;
        }
        
        // Start sync process
        startSync($button, $wrapper, userId, nonce);
    }

    /**
     * Start the sync process
     * 
     * @param {jQuery} $button - The sync button
     * @param {jQuery} $wrapper - The wrapper element
     * @param {number} userId - User ID to sync
     * @param {string} nonce - Security nonce
     */
    function startSync($button, $wrapper, userId, nonce) {
        // Show loading state
        showLoading($button, $wrapper);
        
        // Prepare AJAX data
        var ajaxData = {
            action: ameliaUsersSync.action,
            user_id: userId,
            nonce: nonce
        };
        
        // Send AJAX request
        $.post(ameliaUsersSync.ajaxUrl, ajaxData)
            .done(function(response) {
                handleSyncResponse(response, $button, $wrapper);
            })
            .fail(function(jqXHR, textStatus, errorThrown) {
                handleSyncError(jqXHR, textStatus, errorThrown, $button, $wrapper);
            });
    }

    /**
     * Handle successful AJAX response
     * 
     * @param {Object} response - AJAX response
     * @param {jQuery} $button - The sync button
     * @param {jQuery} $wrapper - The wrapper element
     */
    function handleSyncResponse(response, $button, $wrapper) {
        hideLoading($wrapper);
        
        if (response.success) {
            handleSyncSuccess(response.data, $button, $wrapper);
        } else {
            var message = response.data && response.data.message 
                ? response.data.message 
                : ameliaUsersSync.messages.unknownError;
            showError($wrapper, message);
        }
        
        // Re-enable button
        $button.prop('disabled', false);
    }

    /**
     * Handle AJAX request failure
     * 
     * @param {Object} jqXHR - jQuery XHR object
     * @param {string} textStatus - Error status text
     * @param {string} errorThrown - Error thrown
     * @param {jQuery} $button - The sync button
     * @param {jQuery} $wrapper - The wrapper element
     */
    function handleSyncError(jqXHR, textStatus, errorThrown, $button, $wrapper) {
        hideLoading($wrapper);
        
        var message = ameliaUsersSync.messages.networkError;
        
        // Try to get more specific error message
        if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
            message = jqXHR.responseJSON.data.message;
        } else if (jqXHR.status === 0) {
            message = ameliaUsersSync.messages.connectionError;
        } else if (jqXHR.status >= 500) {
            message = ameliaUsersSync.messages.serverError;
        }
        
        showError($wrapper, message);
        $button.prop('disabled', false);
    }

    /**
     * Handle successful sync
     * 
     * @param {Object} data - Response data
     * @param {jQuery} $button - The sync button
     * @param {jQuery} $wrapper - The wrapper element
     */
    function handleSyncSuccess(data, $button, $wrapper) {
        var message = data.message || ameliaUsersSync.messages.syncSuccess;
        var customerId = data.customer_id || '';
        var wasExisting = data.was_existing || false;
        
        // Show success message
        showSuccess($wrapper, message);
        
        // Update button and status if this was a first-time sync
        if (!wasExisting && customerId) {
            updateButtonToResync($button, customerId, $wrapper);
        }
        
        // Hide success message after delay
        setTimeout(function() {
            hideFeedback($wrapper);
        }, 3000);
    }

    /**
     * Show loading state
     * 
     * @param {jQuery} $button - The sync button
     * @param {jQuery} $wrapper - The wrapper element
     */
    function showLoading($button, $wrapper) {
        // Disable button
        $button.prop('disabled', true);
        
        // Show/create feedback area
        var $feedback = getFeedbackArea($wrapper);
        $feedback.show();
        
        // Show spinner, hide other states
        $feedback.find('.amelia-sync-spinner').show();
        $feedback.find('.amelia-sync-success, .amelia-sync-error').hide();
    }

    /**
     * Hide loading state
     * 
     * @param {jQuery} $wrapper - The wrapper element
     */
    function hideLoading($wrapper) {
        var $feedback = $wrapper.find('.amelia-sync-feedback');
        $feedback.find('.amelia-sync-spinner').hide();
    }

    /**
     * Show success message
     * 
     * @param {jQuery} $wrapper - The wrapper element
     * @param {string} message - Success message
     */
    function showSuccess($wrapper, message) {
        var $feedback = getFeedbackArea($wrapper);
        var $success = $feedback.find('.amelia-sync-success');
        
        $success.find('.message').text(message);
        $success.show();
        $feedback.find('.amelia-sync-error').hide();
    }

    /**
     * Show error message
     * 
     * @param {jQuery} $wrapper - The wrapper element
     * @param {string} message - Error message
     */
    function showError($wrapper, message) {
        var $feedback = getFeedbackArea($wrapper);
        var $error = $feedback.find('.amelia-sync-error');
        
        $error.find('.message').text(message);
        $error.show();
        $feedback.find('.amelia-sync-success').hide();
    }

    /**
     * Hide all feedback messages
     * 
     * @param {jQuery} $wrapper - The wrapper element
     */
    function hideFeedback($wrapper) {
        var $feedback = $wrapper.find('.amelia-sync-feedback');
        $feedback.hide();
        $feedback.find('.amelia-sync-success, .amelia-sync-error, .amelia-sync-spinner').hide();
    }

    /**
     * Get or create feedback area
     * 
     * @param {jQuery} $wrapper - The wrapper element
     * @return {jQuery} Feedback area element
     */
    function getFeedbackArea($wrapper) {
        var $feedback = $wrapper.find('.amelia-sync-feedback');
        
        // Create feedback area if it doesn't exist
        if ($feedback.length === 0) {
            $feedback = $('<div class="amelia-sync-feedback" style="display: none;">' +
                '<span class="amelia-sync-spinner" style="display: none;">' +
                    '<span class="spinner" style="float: none; visibility: visible;"></span>' +
                    ameliaUsersSync.messages.syncing +
                '</span>' +
                '<span class="amelia-sync-success" style="display: none; color: green;">' +
                    '✓ <span class="message"></span>' +
                '</span>' +
                '<span class="amelia-sync-error" style="display: none; color: red;">' +
                    '✗ <span class="message"></span>' +
                '</span>' +
            '</div>');
            
            $wrapper.append($feedback);
        }
        
        return $feedback;
    }

    /**
     * Update button to re-sync state after successful first sync
     * 
     * @param {jQuery} $button - The sync button
     * @param {string} customerId - Amelia customer ID
     * @param {jQuery} $wrapper - The wrapper element
     */
    function updateButtonToResync($button, customerId, $wrapper) {
        // Change button from primary to secondary style
        $button.removeClass('button-primary').addClass('button-secondary');
        $button.text(ameliaUsersSync.messages.resync);
        $button.attr('title', ameliaUsersSync.messages.resyncTitle);
        
        // Add or update synced status
        var $existingStatus = $wrapper.find('.amelia-sync-status');
        var statusHtml = '<span class="amelia-sync-status amelia-synced">' +
            '✓ ' + ameliaUsersSync.messages.synced + 
            ' <small>(ID: ' + customerId + ')</small>' +
        '</span>';
        
        if ($existingStatus.length > 0) {
            $existingStatus.replaceWith(statusHtml);
        } else {
            $wrapper.prepend(statusHtml);
        }
    }

})(jQuery); 