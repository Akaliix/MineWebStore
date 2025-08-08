/**
 * MineWebStore - Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Copy secret key to clipboard
    $('.copy-key').on('click', function() {
        var key = $(this).data('key');
        var $button = $(this);
        var $input = $('#secret-key-input');
        
        // Try modern clipboard API first
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(key).then(function() {
                showCopySuccess($button);
            }).catch(function() {
                fallbackCopy(key, $button, $input);
            });
        } else {
            fallbackCopy(key, $button, $input);
        }
    });
    
    // Fallback copy method
    function fallbackCopy(key, $button, $input) {
        try {
            // Select the input field
            $input.select();
            $input[0].setSelectionRange(0, 99999); // For mobile devices
            
            document.execCommand('copy');
            showCopySuccess($button);
        } catch (err) {
            console.error('Failed to copy to clipboard:', err);
            alert('Failed to copy to clipboard. Please copy manually: ' + key);
        }
    }
    
    // Show copy success feedback
    function showCopySuccess($button) {
        var originalText = $button.text();
        $button.text('Copied!').addClass('copied');
        
        setTimeout(function() {
            $button.text(originalText).removeClass('copied');
        }, 2000);
    }
    
    // Regenerate secret key
    $('.regenerate-key').on('click', function() {
        if (!confirm('Are you sure you want to regenerate the secret key? All registered servers will need to re-register.')) {
            return;
        }
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('Regenerating...').prop('disabled', true);
        
        $.ajax({
            url: mws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'regenerate_secret_key',
                nonce: mws_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the secret key display and copy button
                    var newKey = response.data.new_key;
                    $('#secret-key-input').val(newKey);
                    $('.copy-key').data('key', newKey);
                    
                    // Show success message
                    $('<div class="notice notice-success is-dismissible"><p>Secret key regenerated successfully!</p></div>')
                        .insertAfter('.mws-admin-page h1').delay(3000).fadeOut();
                } else {
                    alert('Failed to regenerate secret key: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to regenerate secret key. Please try again.');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // View players modal
    $('.view-players').on('click', function() {
        var serverId = $(this).data('server-id');
        loadPlayerList(serverId);
        $('#player-list-modal').show();
    });
    
    // Close modal
    $('.mws-modal-close, .mws-modal').on('click', function(e) {
        if (e.target === this) {
            $('.mws-modal').hide();
        }
    });
    
    // Remove server
    $('.remove-server').on('click', function() {
        var serverId = $(this).data('server-id');
        var $row = $(this).closest('tr');
        
        if (!confirm('Are you sure you want to remove this server? This will also delete all associated player data.')) {
            return;
        }
        
        $.ajax({
            url: mws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'remove_server',
                server_id: serverId,
                nonce: mws_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $row.remove();
                    });
                } else {
                    alert('Failed to remove server: ' + response.data.message);
                }
            },
            error: function() {
                alert('Failed to remove server. Please try again.');
            }
        });
    });
    
    // Load player list for a server
    function loadPlayerList(serverId) {
        var $content = $('#player-list-content');
        $content.html('<p>Loading players...</p>');
        
        $.ajax({
            url: mws_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_server_players',
                server_id: serverId,
                nonce: mws_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    var players = response.data.players;
                    var html = '';
                    
                    if (players.length === 0) {
                        html = '<p>No players found on this server.</p>';
                    } else {
                        html = '<ul class="player-list">';
                        players.forEach(function(player) {
                            html += '<li>';
                            html += '<span class="player-name">' + escapeHtml(player.player_name) + '</span>';
                            html += '<span class="player-last-seen">Last seen: ' + player.last_seen + '</span>';
                            html += '</li>';
                        });
                        html += '</ul>';
                    }
                    
                    $content.html(html);
                } else {
                    $content.html('<p>Failed to load players: ' + response.data.message + '</p>');
                }
            },
            error: function() {
                $content.html('<p>Failed to load players. Please try again.</p>');
            }
        });
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Store initial form values to detect changes
    var initialFormValues = {};
    $('.mws-section input[type="text"], .mws-section textarea').each(function() {
        initialFormValues[$(this).attr('name')] = $(this).val();
    });

    // Add auto-refresh status indicator
    var $refreshIndicator = $('<div id="auto-refresh-status" style="position: fixed; top: 50px; right: 20px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 4px; padding: 8px 12px; font-size: 12px; z-index: 9999; display: none;"><span class="dashicons dashicons-update" style="margin-right: 5px;"></span><span class="text">Auto-refresh enabled</span></div>');
    $('body').append($refreshIndicator);

    // Add visual feedback for edited fields
    $('.mws-section input[type="text"], .mws-section textarea').on('input', function() {
        var $this = $(this);
        var fieldName = $this.attr('name');
        var initialValue = initialFormValues[fieldName] || '';
        var currentValue = $this.val();
        
        if (currentValue !== initialValue) {
            $this.addClass('modified');
        } else {
            $this.removeClass('modified');
        }
        
        updateRefreshStatus();
    });

    // Show refresh status on focus
    $('.mws-section input[type="text"], .mws-section textarea').on('focus', function() {
        updateRefreshStatus();
        $refreshIndicator.fadeIn();
    });

    $('.mws-section input[type="text"], .mws-section textarea').on('blur', function() {
        setTimeout(function() {
            if (!$('.mws-section input[type="text"]:focus, .mws-section textarea:focus').length) {
                $refreshIndicator.fadeOut();
            }
        }, 100);
    });

    function updateRefreshStatus() {
        var hasChanges = $('.mws-section input[type="text"].modified, .mws-section textarea.modified').length > 0;
        var $indicator = $('#auto-refresh-status');
        
        if (hasChanges) {
            $indicator.find('.dashicons').removeClass('dashicons-update').addClass('dashicons-shield');
            $indicator.find('.text').text('Auto-refresh paused (unsaved changes)');
            $indicator.css('background', '#fff2e6').css('border-color', '#f56e28');
        } else {
            $indicator.find('.dashicons').removeClass('dashicons-shield').addClass('dashicons-update');
            $indicator.find('.text').text('Auto-refresh enabled');
            $indicator.css('background', '#f0f0f1').css('border-color', '#c3c4c7');
        }
    }

    // Auto-refresh server status every 30 seconds (only if not editing text settings)
    setInterval(function() {
        // Don't refresh if user is actively editing text customization
        var $textInputs = $('.mws-section input[type="text"], .mws-section textarea');
        var isEditing = false;
        
        $textInputs.each(function() {
            var currentValue = $(this).val();
            var fieldName = $(this).attr('name');
            var initialValue = initialFormValues[fieldName] || '';
            
            if ($(this).is(':focus') || currentValue !== initialValue) {
                isEditing = true;
                return false; // break
            }
        });
        
        // Also check if there's a visible notice (settings saved)
        if ($('.notice').is(':visible')) {
            isEditing = true;
        }
        
        if (!isEditing) {
            location.reload();
        }
    }, 30000);
});
