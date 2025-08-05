<?php
/**
 * Order meta and WooCommerce API extension
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Order_Meta {
    
    public function __construct() {
        // Extend WooCommerce REST API
        add_filter('woocommerce_rest_prepare_shop_order_object', array($this, 'add_minecraft_data_to_api'), 10, 3);
        
        // Add order meta when order is created (after checkout meta is saved)
        add_action('woocommerce_checkout_update_order_meta', array($this, 'add_order_minecraft_meta'), 20);
        
        // Add custom order status for Minecraft processing
        add_action('init', array($this, 'register_custom_order_status'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_status'));
        
        // Add bulk actions for Minecraft orders
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Add order list column
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column'), 10, 2);
        
        // Add order meta box
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // Hook to update order status when commands are executed
        add_action('mws_command_status_updated', array($this, 'maybe_update_order_status'), 10, 2);
        
        // Core status protection system
        add_action('woocommerce_order_status_changed', array($this, 'prevent_automatic_status_changes'), 1, 4);
        add_action('woocommerce_before_order_object_save', array($this, 'prevent_status_override_before_save'), 10, 2);
        add_filter('woocommerce_order_get_status', array($this, 'override_status_getter'), 10, 2);
        
        // Mark custom statuses as paid to prevent payment gateway interference
        add_filter('woocommerce_order_is_paid_statuses', array($this, 'add_paid_statuses'));
        add_filter('woocommerce_valid_order_statuses_for_payment', array($this, 'add_minecraft_statuses_for_payment'), 10, 2);
        add_filter('woocommerce_order_needs_payment', array($this, 'minecraft_order_needs_payment'), 10, 3);
        
        // Debug tracking (can be removed in production)
        add_action('woocommerce_order_status_changed', array($this, 'debug_status_changes'), 999, 4);
    }
    
    public function add_minecraft_data_to_api($response, $order, $request) {
        $order_id = $order->get_id();
        $player_name = $order->get_meta('_minecraft_player_name');
        
        if ($player_name) {
            $minecraft_data = array(
                'mc_player' => $player_name,
                'mc_items' => array()
            );
            
            // Process each order item
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                
                $config = MWS_Product_Fields::get_minecraft_config($product_id, $variation_id);
                
                if (!empty($config['server_id']) && !empty($config['commands'])) {
                    $server = MWS_Server_Manager::get_server_by_id($config['server_id']);
                    
                    $minecraft_data['mc_items'][] = array(
                        'item_id' => $item_id,
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'quantity' => $quantity,
                        'mc_server' => $server ? $server->server_name : '',
                        'mc_server_id' => $config['server_id'],
                        'mc_commands' => $this->process_commands($config['commands'], $config['commands_run_modes'], $player_name, $quantity),
                        'mc_command_delay' => $config['command_delay'],
                        'mc_status' => $order->get_meta("_mc_status_{$item_id}") ?: 'pending'
                    );
                }
            }
            
            // Add to response data
            $response->data['minecraft'] = $minecraft_data;
            
            // Also add individual fields for backward compatibility
            $response->data['mc_player'] = $player_name;
            $response->data['mc_items_count'] = count($minecraft_data['mc_items']);
            $response->data['mc_order_status'] = $order->get_meta('_mc_order_status') ?: 'pending';
        }
        
        return $response;
    }
    
    public function add_order_minecraft_meta($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $player_name = $order->get_meta('_minecraft_player_name');
        if (empty($player_name)) {
            // Player name might not be saved yet, try to get from POST data
            // Note: WooCommerce checkout already handles nonce verification for checkout process
            $nonce = isset($_POST['woocommerce-process-checkout-nonce']) ? 
                sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])) : '';
            if ($nonce && wp_verify_nonce($nonce, 'woocommerce-process_checkout')) {
                $player_name = isset($_POST['minecraft_player_name']) ? 
                    sanitize_text_field(wp_unslash($_POST['minecraft_player_name'])) : '';
            }
        }
        
        $has_minecraft_items = false;
        $minecraft_items = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            $config = MWS_Product_Fields::get_minecraft_config($product_id, $variation_id);
            
            if (!empty($config['server_id']) && !empty($config['commands'])) {
                $has_minecraft_items = true;
                $server = MWS_Server_Manager::get_server_by_id($config['server_id']);
                
                // Create minecraft item data structure
                $minecraft_item = array(
                    'item_id' => $item_id,
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'quantity' => $quantity,
                    'mc_server' => $server ? $server->server_name : '',
                    'mc_server_id' => $config['server_id'],
                    'mc_commands' => $player_name ? $this->process_commands($config['commands'], $config['commands_run_modes'], $player_name, $quantity) : array(),
                    'mc_command_delay' => $config['command_delay'],
                    'mc_status' => 'pending'
                );
                
                $minecraft_items[] = $minecraft_item;
                $order->update_meta_data("_mc_status_{$item_id}", 'pending');
            }
        }
        
        if ($has_minecraft_items) {
            // Store the complete minecraft items data as order meta
            $order->update_meta_data('_minecraft_items', json_encode($minecraft_items));
            $order->update_meta_data('_mc_order_status', 'pending');
            $order->update_meta_data('_mc_has_items', 'yes');
            
            // Auto-set order to Minecraft Processing if it's currently in a standard processing state
            $current_status = $order->get_status();
            if (in_array($current_status, array('processing', 'on-hold'))) {
                $order->set_status('wc-mws-processing');
                $order->add_order_note(__('Order contains Minecraft items - status set to Minecraft Processing', 'minewebstore'));
            }
            
            $order->save();
        }
    }
    
    public function register_custom_order_status() {
        register_post_status('wc-mws-processing', array(
            'label' => _x('Minecraft Processing', 'Order status', 'minewebstore'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            // translators: %s is the count of orders in Minecraft Processing status
            'label_count' => _n_noop(
                'Minecraft Processing <span class="count">(%s)</span>',
                'Minecraft Processing <span class="count">(%s)</span>',
                'minewebstore'
            )
        ));
        
        register_post_status('wc-mws-completed', array(
            'label' => _x('Minecraft Completed', 'Order status', 'minewebstore'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            // translators: %s is the count of orders in Minecraft Completed status
            'label_count' => _n_noop(
                'Minecraft Completed <span class="count">(%s)</span>',
                'Minecraft Completed <span class="count">(%s)</span>',
                'minewebstore'
            )
        ));
        
        // Mark custom statuses as "paid" statuses to prevent automatic reversion
        add_filter('woocommerce_order_is_paid_statuses', array($this, 'add_paid_statuses'));
    }
    
    public function add_custom_order_status($order_statuses) {
        $order_statuses['wc-mws-processing'] = _x('Minecraft Processing', 'Order status', 'minewebstore');
        $order_statuses['wc-mws-completed'] = _x('Minecraft Completed', 'Order status', 'minewebstore');
        return $order_statuses;
    }
    
    public function add_paid_statuses($statuses) {
        $statuses[] = 'wc-mws-processing';
        $statuses[] = 'wc-mws-completed';
        return $statuses;
    }
    
    /**
     * Prevent automatic status changes for orders with Minecraft items
     */
    public function prevent_automatic_status_changes($order_id, $old_status, $new_status, $order) {
        // Check if this order has Minecraft items
        $has_minecraft = $order->get_meta('_mc_has_items');
        if ($has_minecraft !== 'yes') {
            return; // Not a Minecraft order, allow normal processing
        }
        
        // Prevent reverting from Minecraft statuses to standard statuses automatically
        $minecraft_statuses = array('wc-mws-processing', 'wc-mws-completed');
        $problematic_reversions = array('pending', 'pending-payment');
        
        if (in_array($old_status, $minecraft_statuses) && in_array($new_status, $problematic_reversions)) {
            // This is likely an automatic reversion - block it
            
            // Revert back to the Minecraft status
            remove_action('woocommerce_order_status_changed', array($this, 'prevent_automatic_status_changes'), 1, 4);
            $order->set_status($old_status);
            $order->add_order_note(sprintf(
                // translators: %1$s is old status, %2$s is attempted new status
                __('Prevented automatic status change from %1$s to %2$s (Minecraft order protection)', 'minewebstore'),
                $old_status,
                $new_status
            ));
            $order->save();
            add_action('woocommerce_order_status_changed', array($this, 'prevent_automatic_status_changes'), 1, 4);
        }
    }
    
    /**
     * Add Minecraft statuses as valid statuses for payment
     */
    public function add_minecraft_statuses_for_payment($statuses, $order) {
        $statuses[] = 'wc-mws-processing';
        $statuses[] = 'wc-mws-completed';
        return $statuses;
    }
    
    /**
     * Prevent Minecraft orders from being considered as needing payment
     */
    public function minecraft_order_needs_payment($needs_payment, $order, $valid_statuses) {
        if (!$order instanceof WC_Order) {
            return $needs_payment;
        }
        
        // Check if this order has Minecraft items
        $has_minecraft = $order->get_meta('_mc_has_items');
        if ($has_minecraft !== 'yes') {
            return $needs_payment; // Not a Minecraft order, allow normal processing
        }
        
        // If order is in Minecraft status, it doesn't need payment
        $current_status = $order->get_status();
        if (in_array($current_status, array('wc-mws-processing', 'wc-mws-completed'))) {
            return false; // Order doesn't need payment
        }
        
        return $needs_payment;
    }
    
    /**
     * Debug method to track status changes
     */
    public function debug_status_changes($order_id, $old_status, $new_status, $order) {
        // Debug tracking removed for production
    }
    
    /**
     * Override status getter to maintain Minecraft statuses
     */
    public function override_status_getter($status, $order) {
        if (!$order instanceof WC_Order) {
            return $status;
        }
        
        // Check if this order has Minecraft items and a protected status
        $has_minecraft = $order->get_meta('_mc_has_items');
        if ($has_minecraft === 'yes') {
            $protected_status = $order->get_meta('_mws_protected_status');
            if ($protected_status && in_array($protected_status, array('wc-mws-processing', 'wc-mws-completed'))) {
                return $protected_status;
            }
        }
        
        return $status;
    }
    
    /**
     * Prevent status override before order save
     */
    public function prevent_status_override_before_save($order, $data_store) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Check if this order has Minecraft items
        $has_minecraft = $order->get_meta('_mc_has_items');
        if ($has_minecraft !== 'yes') {
            return;
        }
        
        $current_status = $order->get_status();
        
        // If trying to save with a Minecraft status, protect it
        if (in_array($current_status, array('wc-mws-processing', 'wc-mws-completed'))) {
            $order->update_meta_data('_mws_protected_status', $current_status);
        }
        
        // If trying to save as pending-payment but we have a protected status, revert
        if ($current_status === 'pending-payment') {
            $protected_status = $order->get_meta('_mws_protected_status');
            if ($protected_status && in_array($protected_status, array('wc-mws-processing', 'wc-mws-completed'))) {
                $order->set_status($protected_status);
                $order->add_order_note(sprintf(
                    // translators: %s is the protected status
                    __('Reverted automatic status change - maintaining %s', 'minewebstore'), 
                    $protected_status
                ));
            }
        }
    }
    
    public function add_bulk_actions($actions) {
        $actions['mc_reset_status'] = __('Reset Minecraft Status', 'minewebstore');
        return $actions;
    }
    
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'mc_reset_status') {
            return $redirect_to;
        }
        
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if (!$order) continue;
            
            foreach ($order->get_items() as $item_id => $item) {
                $order->delete_meta_data("_mc_status_{$item_id}");
            }
            
            $order->delete_meta_data('_mc_order_status');
            $order->add_order_note(__('Minecraft status reset by admin', 'minewebstore'));
            $order->save();
        }
        
        $redirect_to = add_query_arg('mc_reset', count($post_ids), $redirect_to);
        return $redirect_to;
    }
    
    public function add_order_column($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ($key === 'order_status') {
                $new_columns['minecraft_status'] = __('Minecraft', 'minewebstore');
            }
        }
        
        return $new_columns;
    }
    
    public function display_order_column($column, $post_id) {
        if ($column === 'minecraft_status') {
            $order = wc_get_order($post_id);
            $has_minecraft = $order ? $order->get_meta('_mc_has_items') : '';
            
            if ($has_minecraft === 'yes') {
                $status = $order->get_meta('_mc_order_status') ?: 'pending';
                $player_name = $order->get_meta('_minecraft_player_name');
                
                $status_colors = array(
                    'pending' => '#ffba00',
                    'processing' => '#00a0d2',
                    'completed' => '#7ad03a',
                    'failed' => '#a00'
                );
                
                $color = $status_colors[$status] ?? '#666';
                
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">';
                echo esc_html(ucfirst($status));
                echo '</span>';
                
                if ($player_name) {
                    echo '<br><small>' . esc_html($player_name) . '</small>';
                }
            } else {
                echo '<span style="color: #ccc;">—</span>';
            }
        }
    }
    
    public function add_order_meta_box() {
        add_meta_box(
            'minecraft-order-details',
            __('Minecraft Details', 'minewebstore'),
            array($this, 'display_order_meta_box'),
            'shop_order',
            'normal',
            'default'
        );
    }
    
    public function display_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        $player_name = get_post_meta($post->ID, '_minecraft_player_name', true);
        $has_minecraft = get_post_meta($post->ID, '_mc_has_items', true);
        
        if ($has_minecraft !== 'yes') {
            echo '<p>' . esc_html__('This order does not contain any Minecraft products.', 'minewebstore') . '</p>';
            return;
        }
        
        echo '<div class="minecraft-order-meta">';
        
        if ($player_name) {
            echo '<p><strong>' . esc_html__('Player Name:', 'minewebstore') . '</strong> ' . esc_html($player_name) . '</p>';
        }
        
        echo '<h4>' . esc_html__('Minecraft Items:', 'minewebstore') . '</h4>';
        echo '<table class="widefat">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product', 'minewebstore') . '</th>';
        echo '<th>' . esc_html__('Server', 'minewebstore') . '</th>';
        echo '<th>' . esc_html__('Commands', 'minewebstore') . '</th>';
        echo '<th>' . esc_html__('Status', 'minewebstore') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $quantity = $item->get_quantity();
            
            $config = MWS_Product_Fields::get_minecraft_config($product_id, $variation_id);
            
            if (!empty($config['server_id']) && !empty($config['commands'])) {
                $server = MWS_Server_Manager::get_server_by_id($config['server_id']);
                $status = $order->get_meta("_mc_status_{$item_id}") ?: 'pending';
                
                echo '<tr>';
                echo '<td>' . esc_html($item->get_name()) . '</td>';
                echo '<td>' . esc_html($server ? $server->server_name : 'Unknown') . '</td>';
                echo '<td><code>' . esc_html(substr($config['commands'], 0, 50)) . '...</code></td>';
                echo '<td><span class="mc-status-' . esc_attr($status) . '">' . esc_html(ucfirst($status)) . '</span></td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Update WooCommerce order status based on Minecraft command completion
     */
    public function maybe_update_order_status($command_id, $new_status) {
        global $wpdb;
        
        // Get the command and its order ID
        $command = $wpdb->get_row($wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}mws_pending_commands WHERE id = %d",
            $command_id
        ));
        
        if (!$command || !$command->order_id) {
            return;
        }
        
        $order = wc_get_order($command->order_id);
        if (!$order) {
            return;
        }
        
        // Check if this order has Minecraft items
        $has_minecraft = $order->get_meta('_mc_has_items');
        if ($has_minecraft !== 'yes') {
            return;
        }
        
        // Get all Minecraft commands for this order
        $all_commands = $wpdb->get_results($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}mws_pending_commands WHERE order_id = %d",
            $command->order_id
        ));
        
        if (empty($all_commands)) {
            return;
        }
        
        // Count command statuses
        $status_counts = array(
            'pending' => 0,
            'read' => 0,
            'executed' => 0,
            'failed' => 0
        );
        
        foreach ($all_commands as $cmd) {
            if (isset($status_counts[$cmd->status])) {
                $status_counts[$cmd->status]++;
            }
        }
        
        $total_commands = count($all_commands);
        $completed_commands = $status_counts['executed'];
        $failed_commands = $status_counts['failed'];
        $pending_commands = $status_counts['pending'] + $status_counts['read'];
        
        // Determine new order status based on command completion
        $current_order_status = $order->get_status();
        $new_order_status = null;
        $mc_order_status = null;
        
        // Log current command status for debugging
        
        if ($completed_commands === $total_commands && $total_commands > 0) {
            // All commands executed successfully - Completed
            if ($current_order_status !== 'wc-mws-completed') {
                $new_order_status = 'wc-mws-completed';
            }
            $mc_order_status = 'completed';
        } elseif ($completed_commands > 0 && $pending_commands > 0) {
            // Some commands executed, some still pending - Processing
            if ($current_order_status !== 'wc-mws-processing') {
                $new_order_status = 'wc-mws-processing';
            }
            $mc_order_status = 'processing';
        } elseif ($completed_commands === 0 && $pending_commands > 0) {
            // No commands completed yet, but some are pending/processing - set to Processing
            if (!in_array($current_order_status, array('wc-mws-processing', 'wc-mws-completed'))) {
                $new_order_status = 'wc-mws-processing';
            }
            $mc_order_status = 'processing';
        } elseif ($failed_commands > 0 && $pending_commands === 0) {
            // Some/all commands failed, none pending - Failed/Completed based on any success
            if ($completed_commands > 0) {
                // Some succeeded, some failed - mark as completed but log the failures
                if ($current_order_status !== 'wc-mws-completed') {
                    $new_order_status = 'wc-mws-completed';
                }
                $mc_order_status = 'completed';
            } else {
                // All failed - mark as failed
                $mc_order_status = 'failed';
            }
        }
        
        // Update order status if needed
        if ($new_order_status && $new_order_status !== $current_order_status) {
            // Temporarily remove our protection to allow legitimate status updates
            remove_action('woocommerce_order_status_changed', array($this, 'prevent_automatic_status_changes'), 1, 4);
            
            $order->set_status($new_order_status);
            
            // Set protected status to prevent future reverts
            $order->update_meta_data('_mws_protected_status', $new_order_status);
            
            $order->add_order_note(
                sprintf(
                    // translators: %1$d is completed commands, %2$d is total commands
                    __('Minecraft status updated: %1$d of %2$d commands completed', 'minewebstore'),
                    $completed_commands,
                    $total_commands
                )
            );
            
            // Re-add our protection
            add_action('woocommerce_order_status_changed', array($this, 'prevent_automatic_status_changes'), 1, 4);
        }
        
        // Update Minecraft order status metadata
        if ($mc_order_status) {
            $order->update_meta_data('_mc_order_status', $mc_order_status);
        }
        
        $order->save();
    }
    
    private function process_commands($commands, $commands_run_modes, $player_name, $quantity) {
        $command_lines = array_filter(array_map('trim', explode("\n", $commands)));
        $run_modes_lines = array_filter(array_map('trim', explode("\n", $commands_run_modes)));
        $processed_commands = array();
        
        // Ensure run_modes_lines has the same length as command_lines
        while (count($run_modes_lines) < count($command_lines)) {
            $run_modes_lines[] = 'online';
        }
        
        // Always run commands according to quantity (no execute_once restriction)
        for ($i = 0; $i < $quantity; $i++) {
            foreach ($command_lines as $cmd_index => $command) {
                $processed_command = str_replace('%player%', $player_name, $command);
                $run_mode = isset($run_modes_lines[$cmd_index]) ? $run_modes_lines[$cmd_index] : 'online';
                
                $processed_commands[] = array(
                    'command' => $processed_command,
                    'run_mode' => $run_mode
                );
            }
        }
        
        return $processed_commands;
    }
}
