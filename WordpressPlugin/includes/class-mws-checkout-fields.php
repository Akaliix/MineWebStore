<?php
/**
 * Checkout fields for Minecraft player name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Checkout_Fields {
    
    public function __construct() {
        // Add checkout field - using multiple hooks for better theme compatibility
        add_action('woocommerce_after_order_notes', array($this, 'add_checkout_field'));
        add_action('woocommerce_checkout_after_customer_details', array($this, 'add_checkout_field'));
        
        // Validate checkout field
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_field'));
        
        // Save checkout field
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_checkout_field'));
        
        // Display in admin order details
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_admin_order_meta'));
        
        // Display in customer order details
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_customer_order_meta'));
        
        // Add scripts and styles for checkout and order pages
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_scripts'));
        
        // AJAX handler for player validation
        add_action('wp_ajax_validate_minecraft_player', array($this, 'ajax_validate_player'));
        add_action('wp_ajax_nopriv_validate_minecraft_player', array($this, 'ajax_validate_player'));
    }
    
    public function add_checkout_field($checkout = null) {
        // Check if cart contains any Minecraft products
        if (!$this->cart_has_minecraft_products()) {
            return;
        }
        
        // Prevent duplicate field display
        static $field_displayed = false;
        if ($field_displayed) {
            return;
        }
        $field_displayed = true;
        
        // Handle cases where checkout object is not passed or is invalid
        if (!is_object($checkout) || !method_exists($checkout, 'get_value')) {
            $checkout = WC()->checkout();
        }
        
        woocommerce_form_field('minecraft_player_name', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'label' => MWS_Text_Settings::get_field_label(),
            'placeholder' => MWS_Text_Settings::get_field_placeholder(),
            'required' => true,
            'custom_attributes' => array(
                'pattern' => '[a-zA-Z0-9_]{3,16}',
                'title' => MWS_Text_Settings::get_field_title(),
                'maxlength' => '16'
            )
        ), $checkout->get_value('minecraft_player_name'));
        
        echo '<div id="player-validation-message" style="display:none;"></div>';
    }
    
    public function validate_checkout_field() {
        // Check if cart contains any Minecraft products
        if (!$this->cart_has_minecraft_products()) {
            return;
        }
        
        // Verify WooCommerce checkout nonce for security
        if (!isset($_POST['woocommerce-process-checkout-nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
            return;
        }
        
        $player_name = isset($_POST['minecraft_player_name']) ? 
            sanitize_text_field(wp_unslash($_POST['minecraft_player_name'])) : '';
        $error_messages = MWS_Text_Settings::get_checkout_error_messages();
        
        if (empty($player_name)) {
            wc_add_notice($error_messages['required'], 'error');
            return;
        }
        
        // Validate format
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player_name)) {
            wc_add_notice($error_messages['format'], 'error');
            return;
        }
        
        // Validate against server player lists (check if player has ever joined)
        $servers_with_products = $this->get_servers_from_cart();
        $player_found = false;
        
        foreach ($servers_with_products as $server_id) {
            if (MWS_Player_Cache::has_player_joined($server_id, $player_name)) {
                $player_found = true;
                break;
            }
        }
        
        if (!$player_found && !empty($servers_with_products)) {
            $server_names = array();
            foreach ($servers_with_products as $server_id) {
                $server = MWS_Server_Manager::get_server_by_id($server_id);
                if ($server) {
                    $server_names[] = $server->server_name;
                }
            }
            
            wc_add_notice(
                sprintf(
                    /* translators: %1$s: player name, %2$s: comma-separated list of server names */
                    __('Player "%1$s" was not found on the following server(s): %2$s. Please make sure you are online on the correct server.', 'minewebstore'),
                    $player_name,
                    implode(', ', $server_names)
                ),
                'error'
            );
        }
    }
    
    public function save_checkout_field($order_id) {
        // Verify WooCommerce checkout nonce for security
        if (!isset($_POST['woocommerce-process-checkout-nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
            return;
        }
        
        if (isset($_POST['minecraft_player_name']) && !empty($_POST['minecraft_player_name'])) {
            $player_name = sanitize_text_field(wp_unslash($_POST['minecraft_player_name']));
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_meta_data('_minecraft_player_name', $player_name);
                $order->save();
            }
        }
    }
    
    public function display_admin_order_meta($order) {
        $player_name = $order->get_meta('_minecraft_player_name');
        
        if ($player_name) {
            echo '<div class="address">';
            echo '<p><strong>' . esc_html__('Minecraft Player:', 'minewebstore') . '</strong> ' . esc_html($player_name) . '</p>';
            echo '</div>';
        }
    }
    
    public function display_customer_order_meta($order) {
        $player_name = $order->get_meta('_minecraft_player_name');
        
        if ($player_name) {
            echo '<div id="minecraft-order-info" class="minecraft-order-section">';
            echo '<h2 class="woocommerce-order-details__title minecraft-order-title">' . esc_html(MWS_Text_Settings::get_order_section_title()) . '</h2>';
            echo '<table class="woocommerce-table woocommerce-table--customer-details shop_table customer_details minecraft-order-table">';
            echo '<tr><th class="minecraft-order-label">' . esc_html(MWS_Text_Settings::get_order_label()) . '</th><td class="minecraft-order-value">' . esc_html($player_name) . '</td></tr>';
            echo '</table>';
            echo '</div>';
        }
    }
    
    public function enqueue_checkout_scripts() {
        if (is_checkout()) {
            wp_enqueue_script(
                'mws-checkout',
                MWS_PLUGIN_URL . 'assets/js/checkout.js',
                array('jquery'),
                MWS_VERSION,
                true
            );
            
            wp_localize_script('mws-checkout', 'mws_checkout', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mws_checkout_nonce'),
                'messages' => MWS_Text_Settings::get_validation_messages()
            ));
        }
    }
    
    public function ajax_validate_player() {
        check_ajax_referer('mws_checkout_nonce', 'nonce');
        
        $player_name = isset($_POST['player_name']) ? 
            sanitize_text_field(wp_unslash($_POST['player_name'])) : '';
        
        if (empty($player_name)) {
            wp_send_json_error(array('message' => __('Player name is required', 'minewebstore')));
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player_name)) {
            wp_send_json_error(array('message' => __('Invalid player name format', 'minewebstore')));
        }
        
        // Get servers from current cart
        $servers_with_products = $this->get_servers_from_cart();
        $player_found = false;
        $found_servers = array();
        
        foreach ($servers_with_products as $server_id) {
            if (MWS_Player_Cache::has_player_joined($server_id, $player_name)) {
                $player_found = true;
                $server = MWS_Server_Manager::get_server_by_id($server_id);
                if ($server) {
                    $found_servers[] = $server->server_name;
                }
            }
        }
        
        $validation_messages = MWS_Text_Settings::get_validation_messages();
        
        if ($player_found) {
            wp_send_json_success(array(
                'message' => $validation_messages['player_found_on'] . ' ' . implode(', ', $found_servers)
            ));
        } else {
            wp_send_json_error(array(
                'message' => $validation_messages['invalid']
            ));
        }
    }
    
    private function cart_has_minecraft_products() {
        if (!WC()->cart) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? null;
            
            $config = MWS_Product_Fields::get_minecraft_config($product_id, $variation_id);
            if (!empty($config['server_id']) && !empty($config['commands'])) {
                return true;
            }
        }
        
        return false;
    }
    
    private function get_servers_from_cart() {
        $servers = array();
        
        if (!WC()->cart) {
            return $servers;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? null;
            
            $config = MWS_Product_Fields::get_minecraft_config($product_id, $variation_id);
            if (!empty($config['server_id'])) {
                $servers[] = $config['server_id'];
            }
        }
        
        return array_unique($servers);
    }
}
