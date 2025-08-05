<?php
/**
 * Text Settings Helper for MineWebStore Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Text_Settings {
    
    /**
     * Get field label
     */
    public static function get_field_label() {
        return get_option('mws_field_label', __('Minecraft Player Name', 'minewebstore'));
    }
    
    /**
     * Get field placeholder
     */
    public static function get_field_placeholder() {
        return get_option('mws_field_placeholder', __('Enter your Minecraft username', 'minewebstore'));
    }
    
    /**
     * Get field title (tooltip)
     */
    public static function get_field_title() {
        return get_option('mws_field_title', __('Minecraft username must be 3-16 characters, alphanumeric and underscores only', 'minewebstore'));
    }
    
    /**
     * Get validation messages
     */
    public static function get_validation_messages() {
        $default_messages = array(
            'validating' => __('Validating player...', 'minewebstore'),
            'invalid' => __('Player not found on any required servers.', 'minewebstore'),
            'error' => __('Error validating player. Please try again.', 'minewebstore'),
            'characters' => __('characters', 'minewebstore'),
            'minimum' => __('minimum', 'minewebstore'),
            'maximum' => __('maximum', 'minewebstore'),
            'player_found_on' => __('Player found on:', 'minewebstore'),
            'format_error' => __('Invalid player name format. Must be 3-16 characters, letters, numbers, and underscores only.', 'minewebstore'),
            'wait_validation' => __('Please wait for player name validation to complete.', 'minewebstore')
        );
        
        $saved_messages = get_option('mws_validation_messages', array());
        return wp_parse_args($saved_messages, $default_messages);
    }
    
    /**
     * Get checkout validation error messages
     */
    public static function get_checkout_error_messages() {
        $default_messages = array(
            'required' => __('Minecraft Player Name is required for your purchase.', 'minewebstore'),
            'format' => __('Minecraft Player Name must be 3-16 characters, alphanumeric and underscores only.', 'minewebstore'),
            'not_found' => __('The specified Minecraft player was not found on any required servers. Please ensure you have joined the server before making this purchase.', 'minewebstore')
        );
        
        $saved_messages = get_option('mws_checkout_error_messages', array());
        return wp_parse_args($saved_messages, $default_messages);
    }
    
    /**
     * Get order section title
     */
    public static function get_order_section_title() {
        return get_option('mws_order_section_title', __('Minecraft Information', 'minewebstore'));
    }
    
    /**
     * Get order player label
     */
    public static function get_order_label() {
        return get_option('mws_order_label', __('Player Name:', 'minewebstore'));
    }
    
    /**
     * Get all text settings for JavaScript
     */
    public static function get_all_for_js() {
        return array(
            'field_label' => self::get_field_label(),
            'field_placeholder' => self::get_field_placeholder(),
            'field_title' => self::get_field_title(),
            'validation_messages' => self::get_validation_messages(),
            'order_section_title' => self::get_order_section_title(),
            'order_label' => self::get_order_label()
        );
    }
}
