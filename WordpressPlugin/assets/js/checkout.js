/**
 * MineWebStore Integration - Checkout JavaScript
 */

jQuery(document).ready(function($) {
    
    var validationTimeout;
    var lastPlayerName = '';
    
    // Player name field validation
    $('#minecraft_player_name').on('input', function() {
        var playerName = $(this).val().trim();
        var $message = $('#player-validation-message');
        
        // Clear previous timeout
        if (validationTimeout) {
            clearTimeout(validationTimeout);
        }
        
        // Hide message if field is empty
        if (playerName === '') {
            $message.hide();
            return;
        }
        
        // Don't validate if name hasn't changed
        if (playerName === lastPlayerName) {
            return;
        }
        
        // Validate format first
        if (!/^[a-zA-Z0-9_]{3,16}$/.test(playerName)) {
            showValidationMessage(mws_checkout.messages.format_error || 'Invalid player name format. Must be 3-16 characters, letters, numbers, and underscores only.', 'error');
            return;
        }
        
        // Show validating message
        showValidationMessage(mws_checkout.messages.validating, 'info');
        
        // Debounce validation
        validationTimeout = setTimeout(function() {
            validatePlayer(playerName);
        }, 1000);
    });
    
    // Validate player against server
    function validatePlayer(playerName) {
        lastPlayerName = playerName;
        
        $.ajax({
            url: mws_checkout.ajax_url,
            type: 'POST',
            data: {
                action: 'validate_minecraft_player',
                player_name: playerName,
                nonce: mws_checkout.nonce
            },
            success: function(response) {
                if (response.success) {
                    showValidationMessage(response.data.message, 'success');
                } else {
                    showValidationMessage(response.data.message, 'error');
                }
            },
            error: function() {
                showValidationMessage(mws_checkout.messages.error, 'error');
            }
        });
    }
    
    // Show validation message
    function showValidationMessage(message, type) {
        var $message = $('#player-validation-message');
        
        // Remove all existing classes
        $message.removeClass('woocommerce-info woocommerce-message woocommerce-error success error loading');
        
        switch (type) {
            case 'success':
                $message.addClass('woocommerce-message success');
                break;
            case 'error':
                $message.addClass('woocommerce-error error');
                break;
            case 'info':
            case 'validating':
                $message.addClass('woocommerce-info loading');
                break;
            default:
                $message.addClass('woocommerce-info');
                break;
        }
        
        $message.text(message).show();
        
        // Auto-hide success messages after 4 seconds
        if (type === 'success') {
            setTimeout(function() {
                $message.fadeOut();
            }, 4000);
        }
    }
    
    // Add real-time character counter with theme styling
    $('#minecraft_player_name').after('<div id="mc-char-counter"></div>');
    
    $('#minecraft_player_name').on('input', function() {
        var length = $(this).val().length;
        var counter = $('#mc-char-counter');
        var text = length + '/16 ' + mws_checkout.messages.characters;
        
        if (length < 3) {
            text += ' (' + mws_checkout.messages.minimum + ' 3)';
            counter.css('color', 'var(--error-color, #ff6b6b)');
        } else if (length > 16) {
            text += ' (' + mws_checkout.messages.maximum + ' 16)';
            counter.css('color', 'var(--error-color, #ff6b6b)');
        } else {
            counter.css('color', 'var(--minecraft-green, #4CAF50)');
        }
        
        counter.text(text);
    });
    
    // Format player name input (remove invalid characters)
    $('#minecraft_player_name').on('input', function() {
        var value = $(this).val();
        var cleaned = value.replace(/[^a-zA-Z0-9_]/g, '');
        
        if (value !== cleaned) {
            $(this).val(cleaned);
        }
    });
    
    // Prevent form submission if player validation is pending
    $('form.checkout').on('submit', function(e) {
        var playerName = $('#minecraft_player_name').val().trim();
        var $message = $('#player-validation-message');
        
        if (playerName && $message.hasClass('woocommerce-info') && $message.text() === mws_checkout.messages.validating) {
            e.preventDefault();
            alert(mws_checkout.messages.wait_validation || 'Please wait for player name validation to complete.');
            return false;
        }
    });
    
    // Show/hide Minecraft field based on cart contents
    function toggleMinecraftField() {
        // This would be implemented server-side, but we can add some client-side enhancements
        var $field = $('#minecraft_player_field');
        
        if ($field.length && $field.is(':visible')) {
            // Add some visual enhancements
            $field.css({
                'border': '2px solid #00a32a',
                'border-radius': '5px',
                'padding': '15px',
                'background-color': '#f0f8f0',
                'margin': '20px 0'
            });
            
            // Add Minecraft icon (if you have one)
            $field.find('h3').prepend('<span style="color: #00a32a; margin-right: 10px;">🎮</span>');
        }
    }
    
    // Initialize enhancements
    toggleMinecraftField();
    
    // Trigger initial character counter
    $('#minecraft_player_name').trigger('input');
});
