<?php
/**
 * Uninstall script for MineWebStore Plugin
 * 
 * This file is executed when the plugin is deleted via WordPress admin
 * It removes all plugin data including database tables
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - make sure this is actually an uninstall
if (!current_user_can('activate_plugins')) {
    exit;
}

global $wpdb;

// Define table names
$servers_table = $wpdb->prefix . 'mws_servers';
$players_table = $wpdb->prefix . 'mws_players';
$commands_table = $wpdb->prefix . 'mws_pending_commands';

// Drop all plugin tables
$wpdb->query("DROP TABLE IF EXISTS {$commands_table}");
$wpdb->query("DROP TABLE IF EXISTS {$players_table}");
$wpdb->query("DROP TABLE IF EXISTS {$servers_table}");

// Clean up options
$options_to_delete = array(
    'mws_secret_key',
    'MWS_VERSION',
    'mws_settings',
    'mws_api_settings',
    'mws_text_settings',
    'mws_checkout_fields',
    'mws_db_version'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Clean up product meta data
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mc_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_minecraft_player_name'");

// Clean up order meta data (both old and new format)
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_minecraft_player_name'");

// If using HPOS, clean up order meta data from HPOS tables as well
if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
    \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
    
    $hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$hpos_meta_table}'") === $hpos_meta_table) {
        $wpdb->query("DELETE FROM {$hpos_meta_table} WHERE meta_key = '_minecraft_player_name'");
        $wpdb->query("DELETE FROM {$hpos_meta_table} WHERE meta_key LIKE '_mc_%'");
    }
}

// Clean up any transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mws_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mws_%'");
?>
