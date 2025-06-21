jQuery(document).ready(function($) {
    console.log('Bulk sync script loaded');
    console.log('ameliaBulkSync object:', typeof ameliaBulkSync !== 'undefined' ? ameliaBulkSync : 'undefined');
    
    let bulkSyncInProgress = false;
    let bulkSyncStopped = false;
    
    const $startButton = $('#start-bulk-sync');
    const $stopButton = $('#stop-bulk-sync');
    const $progressContainer = $('#bulk-sync-progress');
    const $progressBar = $('.progress-bar-fill');
    const $progressText = $('.progress-text');
    const $syncStatus = $('#sync-status');
    const $syncResults = $('#sync-results');

    console.log('Start button found:', $startButton.length);
    console.log('Stop button found:', $stopButton.length);
    console.log('Progress container found:', $progressContainer.length);
    
    // Alternative selector test
    if ($startButton.length === 0) {
        console.error('Start button not found with selector #start-bulk-sync');
        console.log('Available buttons:', $('button').map(function() { return this.id; }).get());
    }

    // Check if ameliaBulkSync is available
    if (typeof ameliaBulkSync === 'undefined') {
        console.error('ameliaBulkSync object not found. Script localization may have failed.');
        return;
    }

    // Start bulk sync
    $startButton.on('click', function(e) {
        e.preventDefault();
        console.log('Start button clicked');
        
        // Test alert to verify button click is working
        alert('Button clicked! Check console for debug info.');
        
        if (typeof ameliaBulkSync === 'undefined') {
            alert('ameliaBulkSync object is undefined! Script localization failed.');
            return;
        }
        
        if (!confirm(ameliaBulkSync.strings.confirm)) {
            console.log('User cancelled confirmation');
            return;
        }

        console.log('Starting bulk sync...');
        startBulkSync();
    });

    // Stop bulk sync
    $stopButton.on('click', function() {
        stopBulkSync();
    });

    function startBulkSync() {
        console.log('startBulkSync function called');
        bulkSyncInProgress = true;
        bulkSyncStopped = false;
        
        // Update UI
        console.log('Updating UI elements...');
        $startButton.prop('disabled', true).text('Syncing...');
        $stopButton.show();
        $progressContainer.show();
        $syncResults.empty();
        
        updateProgress(0, ameliaBulkSync.strings.starting);
        
        // Start processing from offset 0
        console.log('Starting batch processing...');
        processBatch(0, 0);
    }

    function stopBulkSync() {
        bulkSyncStopped = true;
        bulkSyncInProgress = false;
        
        // Update UI
        $startButton.prop('disabled', false).text('Start Bulk Sync');
        $stopButton.hide();
        
        updateStatus('Bulk sync stopped by user.', 'warning');
    }

    function processBatch(offset, totalProcessed) {
        console.log('processBatch called with offset:', offset, 'totalProcessed:', totalProcessed);
        
        if (bulkSyncStopped) {
            console.log('Bulk sync stopped, returning');
            return;
        }

        const data = {
            action: 'amelia_bulk_sync',
            nonce: ameliaBulkSync.nonce,
            batch_size: 10, // Process 10 users per batch
            offset: offset,
            total_processed: totalProcessed
        };

        console.log('Making AJAX request with data:', data);
        console.log('AJAX URL:', ameliaBulkSync.ajaxUrl);

        $.ajax({
            url: ameliaBulkSync.ajaxUrl,
            type: 'POST',
            data: data,
            timeout: 30000, // 30 second timeout
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Update progress
                    updateProgress(data.progress || 0, data.message);
                    
                    // Add batch results
                    if (data.batch_results && data.batch_results.length > 0) {
                        addBatchResults(data.batch_results);
                    }
                    
                    if (data.completed) {
                        // Sync completed
                        completeBulkSync(data.total_processed, data.total_users);
                    } else {
                        // Continue with next batch
                        setTimeout(() => {
                            processBatch(data.next_offset, data.total_processed);
                        }, 500); // Small delay between batches
                    }
                } else {
                    handleError(response.data.message || ameliaBulkSync.strings.error);
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = ameliaBulkSync.strings.error;
                
                if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                }
                
                handleError(errorMessage);
            }
        });
    }

    function updateProgress(percentage, message) {
        $progressBar.css('width', percentage + '%');
        $progressText.text(percentage + '%');
        $syncStatus.html('<p>' + message + '</p>');
    }

    function updateStatus(message, type = 'info') {
        const statusClass = 'notice notice-' + type + ' inline';
        $syncStatus.html('<div class="' + statusClass + '"><p>' + message + '</p></div>');
    }

    function addBatchResults(results) {
        let html = '<div class="batch-results">';
        
        results.forEach(function(result) {
            const statusClass = result.success ? 'success' : 'error';
            const statusIcon = result.success ? '✓' : '✗';
            
            html += '<div class="result-item ' + statusClass + '">';
            html += '<span class="result-icon">' + statusIcon + '</span>';
            html += '<span class="result-user">' + result.user_login + '</span>';
            html += '<span class="result-message">' + result.message + '</span>';
            html += '</div>';
        });
        
        html += '</div>';
        
        $syncResults.append(html);
        
        // Auto-scroll to bottom
        $syncResults.scrollTop($syncResults[0].scrollHeight);
    }

    function completeBulkSync(totalProcessed, totalUsers) {
        bulkSyncInProgress = false;
        
        // Update UI
        $startButton.prop('disabled', false).text('Start Bulk Sync');
        $stopButton.hide();
        
        updateProgress(100, ameliaBulkSync.strings.completed);
        updateStatus('Bulk sync completed successfully! Processed ' + totalProcessed + ' out of ' + totalUsers + ' users.', 'success');
    }

    function handleError(message) {
        bulkSyncInProgress = false;
        
        // Update UI
        $startButton.prop('disabled', false).text('Start Bulk Sync');
        $stopButton.hide();
        
        updateStatus(message, 'error');
    }
}); 