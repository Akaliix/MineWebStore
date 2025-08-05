<?php
/**
 * Plugin Name: MineWebStore Integration
 * Plugin URI: https://github.com/Akaliix/MineWebStore
 * Description: Integrates WooCommerce with Minecraft servers to execute commands when products are purchased. Compatible with High-Performance Order Storage (HPOS).
 * Version: 1.0.0
 * Author: Akaliix
 * License: GPL v2 or later
 * Text Domain: minewebstore
 * Requires at least: 6.0
 * Tested up to: 6.8.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 10.0.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MWS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MWS_VERSION', '1.0.0');

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('MineWebStore Integration requires WooCommerce to be installed and activated.', 'minewebstore') . 
             '</p></div>';
    });
    return;
}

// Main plugin class
class MineWebStore {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=minewebstore') . '">' . __('Settings', 'minewebstore') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function init() {
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', function() {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
            }
        });
        
        // Load plugin files
        $this->load_dependencies();
        
        // Initialize components
        new MWS_Admin();
        new MWS_API();
        new MWS_Product_Fields();
        new MWS_Checkout_Fields();
        new MWS_Order_Meta();
        
        // Initialize pending commands manager
        MWS_Pending_Commands::init();
    }
    
    private function load_dependencies() {
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-admin.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-api.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-product-fields.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-checkout-fields.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-order-meta.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-server-manager.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-player-cache.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-text-settings.php';
        require_once MWS_PLUGIN_PATH . 'includes/class-mws-pending-commands.php';
    }
    
    public function activate() {
        // Create database tables first
        $this->create_tables();
        
        // Generate a proper secret key on activation
        $secret_key = wp_generate_password(32, false);
        update_option('mws_secret_key', $secret_key);
        
        // Flush rewrite rules for API endpoints
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Servers table
        $servers_table = $wpdb->prefix . 'mws_servers';
        $servers_sql = "CREATE TABLE {$servers_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            server_name varchar(255) NOT NULL,
            server_key varchar(255) NOT NULL,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            status enum('active', 'inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY server_name (server_name)
        ) {$charset_collate};";
        
        // Players cache table - stores all players who have ever joined
        $players_table = $wpdb->prefix . 'mws_players';
        $players_sql = "CREATE TABLE {$players_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            server_id int(11) NOT NULL,
            player_name varchar(16) NOT NULL,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY server_id (server_id),
            UNIQUE KEY server_player (server_id, player_name)
        ) {$charset_collate};";
        
        // Pending commands table - stores commands to be executed
        $commands_table = $wpdb->prefix . 'mws_pending_commands';
        $commands_sql = "CREATE TABLE {$commands_table} (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) NOT NULL,
            product_id int(11) NOT NULL,
            player_name varchar(16) NOT NULL,
            command_text text NOT NULL,
            run_mode enum('always', 'online') DEFAULT 'online',
            server_name varchar(50) DEFAULT NULL,
            status enum('pending', 'read', 'executed', 'failed') DEFAULT 'pending',
            execution_message text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            read_at datetime DEFAULT NULL,
            executed_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY status (status),
            KEY server_name (server_name),
            KEY player_name (player_name),
            KEY run_mode (run_mode)
        ) {$charset_collate};";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create tables
        dbDelta($servers_sql);
        dbDelta($players_sql);
        dbDelta($commands_sql);
        
        // Verify tables were created successfully
        $servers_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $servers_table)) === $servers_table;
        $players_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $players_table)) === $players_table;
        $commands_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $commands_table)) === $commands_table;
    }
}

// Initialize the plugin
$minewebstore = MineWebStore::get_instance();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array($minewebstore, 'activate'));
register_deactivation_hook(__FILE__, array($minewebstore, 'deactivate'));
