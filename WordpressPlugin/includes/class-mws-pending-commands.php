<?php
/**
 * Pending Commands Manager for MineWebStore
 * 
 * This class handles the creation, retrieval, and management of pending Minecraft commands
 * generated from WooCommerce orders. It provides backward compatibility for both
 * server ID and server name storage formats.
 * 
 * Key Features:
 * - Automatic command creation when orders are completed
 * - Server-specific command filtering with backward compatibility
 * - Command status tracking (pending, read, executed, failed)
 * - Robust error handling and logging
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Pending_Commands {
    
    private static $table_name;
    
    public static function init() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'mws_pending_commands';
        
        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'handle_order_status_change'), 10, 4);
        
        // Verify table exists (for debugging)
        add_action('admin_init', array(__CLASS__, 'verify_table_exists'));
    }
    
    public static function verify_table_exists() {
        // Only run this check for admin users and not on every page load
        if (!current_user_can('manage_options') || !is_admin()) {
            return;
        }
        
        global $wpdb;
        
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", self::$table_name)) === self::$table_name;
        
        if (!$table_exists) {
            // Try to create it
            $plugin_instance = MineWebStore::get_instance();
            if (method_exists($plugin_instance, 'activate')) {
                $plugin_instance->activate();
            }
        }
    }
    
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        // Only process when order changes to completed or processing
        if (!in_array($new_status, array('completed', 'processing'))) {
            return;
        }
        
        // Avoid duplicate processing
        if (self::commands_exist_for_order($order_id)) {
            return;
        }
        
        // Get order details
        if (!$order) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            return;
        }
        
        // Get player name from order meta
        $player_name = $order->get_meta('_minecraft_player_name', true);
        if (empty($player_name)) {
            return;
        }
        
        // Validate player name
        if (!preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player_name)) {
            return;
        }
        
        $total_commands_created = 0;
        
        // Process each item in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }
            
            // Get Minecraft configuration for this product
            $config = MWS_Product_Fields::get_minecraft_config($product->get_id());
            
            if (empty($config['commands'])) {
                continue;
            }
            
            // Parse commands and run modes
            $command_lines = array_filter(array_map('trim', explode("\n", $config['commands'])));
            $run_modes_lines = !empty($config['commands_run_modes']) ? 
                explode("\n", $config['commands_run_modes']) : array();
            
            $quantity = $item->get_quantity();
            
            // Create commands for each quantity
            for ($i = 0; $i < $quantity; $i++) {
                foreach ($command_lines as $index => $command) {
                    if (empty($command)) {
                        continue;
                    }
                    
                    // Get run mode for this command (default to 'online' if not specified)
                    $run_mode = isset($run_modes_lines[$index]) ? $run_modes_lines[$index] : 'online';
                    if (!in_array($run_mode, array('always', 'online'))) {
                        $run_mode = 'online';
                    }
                    
                    // Replace placeholders (support both %player% and {player} formats)
                    $command = str_replace(array('{player}', '%player%'), $player_name, $command);
                    
                    // Create pending command with proper server name
                    $server_name = self::resolve_server_name($config['server_id']);
                    $command_id = self::create_pending_command($order_id, $product->get_id(), $player_name, $command, $run_mode, $server_name);
                    
                    if ($command_id) {
                        $total_commands_created++;
                    }
                }
            }
        }
        
        // Log the command creation summary (removed debug logging)
    }
    
    public static function create_pending_command($order_id, $product_id, $player_name, $command, $run_mode = 'online', $server_name = null) {
        global $wpdb;
        
        // Validate run_mode
        if (!in_array($run_mode, array('always', 'online'))) {
            $run_mode = 'online';
        }
        
        $result = $wpdb->insert(
            self::$table_name,
            array(
                'order_id' => $order_id,
                'product_id' => $product_id,
                'player_name' => $player_name,
                'command_text' => $command,
                'run_mode' => $run_mode,
                'server_name' => $server_name,
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    public static function commands_exist_for_order($order_id) {
        global $wpdb;
        
        $table_name = self::$table_name;
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $order_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Resolve server ID to server name
     * 
     * @param mixed $server_id Server ID or name
     * @return string|null Server name or null if not found
     */
    private static function resolve_server_name($server_id) {
        if (empty($server_id)) {
            return null;
        }
        
        // If it's already a string (server name), validate it exists
        if (!is_numeric($server_id)) {
            $server = MWS_Server_Manager::get_server_by_name($server_id);
            return $server ? $server->server_name : null;
        }
        
        // If it's numeric (server ID), convert to server name
        $server = MWS_Server_Manager::get_server_by_id((int)$server_id);
        return $server ? $server->server_name : null;
    }
    
    /**
     * Validate if a command can be executed by a specific server
     * 
     * @param object $command Command object from database
     * @param string $server_name Server name requesting the command
     * @return bool True if server can execute this command
     */
    public static function can_server_execute_command($command, $server_name) {
        if (!$command || !$server_name) {
            return false;
        }
        
        // Unassigned commands can be executed by any server
        if (empty($command->server_name)) {
            return true;
        }
        
        // Check if command is assigned to this server (by name)
        if ($command->server_name === $server_name) {
            return true;
        }
        
        // Check if command is assigned to this server (by ID for backward compatibility)
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        if ($server && $command->server_name === (string)$server->id) {
            return true;
        }
        
        return false;
    }
    
    public static function get_pending_commands($server_name = null, $limit = 50) {
        global $wpdb;
        
        $where_clause = "WHERE status = 'pending'";
        $params = array();
        
        if ($server_name) {
            // Get server by name to find its ID for backward compatibility
            $server = MWS_Server_Manager::get_server_by_name($server_name);
            
            if ($server) {
                // Include commands assigned to this server (by name or ID) or unassigned commands
                $where_clause .= " AND (server_name IS NULL OR server_name = %s OR server_name = %s)";
                $params[] = $server_name;        // Match by server name
                $params[] = (string)$server->id; // Match by server ID (for backward compatibility)
            } else {
                // Server not found, only return unassigned commands
                $where_clause .= " AND server_name IS NULL";
            }
        }
        
        $base_sql = "SELECT * FROM `" . self::$table_name . "` " . $where_clause . " ORDER BY created_at ASC LIMIT %d";
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare($base_sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
    
    public static function mark_commands_as_read($command_ids, $server_name = null) {
        global $wpdb;
        
        if (empty($command_ids) || !is_array($command_ids)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($command_ids), '%d'));
        $params = $command_ids;
        
        $where_clause = "id IN ($placeholders)";
        
        if ($server_name) {
            $server = MWS_Server_Manager::get_server_by_name($server_name);
            if ($server) {
                // Include both server name and ID for backward compatibility
                $where_clause .= " AND (server_name IS NULL OR server_name = %s OR server_name = %s)";
                $params[] = $server_name;
                $params[] = (string)$server->id;
            } else {
                // Server not found, only update unassigned commands
                $where_clause .= " AND server_name IS NULL";
            }
        }
        
        $base_sql = "UPDATE `" . self::$table_name . "` SET status = 'read', read_at = %s WHERE " . $where_clause;
        array_unshift($params, current_time('mysql'));
        
        return $wpdb->query($wpdb->prepare($base_sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }
    
    public static function update_command_status($command_id, $status, $message = null, $server_name = null) {
        global $wpdb;
        
        if (!in_array($status, array('executed', 'failed'))) {
            return false;
        }
        
        $update_data = array(
            'status' => $status,
            'execution_message' => $message,
            'executed_at' => current_time('mysql')
        );
        
        $where_clause = array('id' => $command_id);
        
        // If server name is provided, ensure the command belongs to this server
        if ($server_name) {
            $server = MWS_Server_Manager::get_server_by_name($server_name);
            if ($server) {
                // Build a more complex WHERE clause to match server name or ID
                $sql = "UPDATE `" . self::$table_name . "` SET status = %s, execution_message = %s, executed_at = %s WHERE id = %d AND (server_name IS NULL OR server_name = %s OR server_name = %s)";
                $result = $wpdb->query($wpdb->prepare(
                    $sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                    $status,
                    $message,
                    current_time('mysql'),
                    $command_id,
                    $server_name,
                    (string)$server->id
                ));
                
                // Trigger hook for order status updates
                if ($result !== false && $result > 0) {
                    do_action('mws_command_status_updated', $command_id, $status);
                }
                
                return $result;
            }
        }
        
        // Fallback to simple update if no server filtering needed
        $result = $wpdb->update(
            self::$table_name,
            $update_data,
            $where_clause,
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        // Trigger hook for order status updates
        if ($result !== false && $result > 0) {
            do_action('mws_command_status_updated', $command_id, $status);
        }
        
        return $result;
    }
    
    public static function get_commands_for_admin($limit = 100, $offset = 0, $filter_status = null) {
        global $wpdb;
        
        // Ensure table is initialized
        if (!self::$table_name) {
            self::$table_name = $wpdb->prefix . 'mws_pending_commands';
        }
        
        $where_clause = "1=1";
        $params = array();
        
        if ($filter_status && in_array($filter_status, array('pending', 'read', 'executed', 'failed'))) {
            $where_clause .= " AND status = %s";
            $params[] = $filter_status;
        }
        
        // First, let's just get the basic commands without complex joins
        $base_sql = "SELECT * FROM `" . self::$table_name . "` WHERE " . $where_clause . " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $results = $wpdb->get_results($wpdb->prepare($base_sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            
            // Now enrich the results with product and order information
            if (!empty($results)) {
                foreach ($results as $result) {
                    // Add default values
                    $result->order_key = 'Order #' . $result->order_id;
                    $result->order_status = 'unknown';
                    $result->product_name = 'Unknown Product';
                    
                        // Try to get product name
                        if ($result->product_id) {
                            $product_title = $wpdb->get_var($wpdb->prepare(
                                "SELECT post_title FROM `{$wpdb->prefix}posts` WHERE ID = %d AND post_type IN ('product', 'product_variation')",
                                $result->product_id
                            ));
                            if ($product_title) {
                                $result->product_name = $product_title;
                            }
                        }                    // Try to get order information - check both posts and HPOS tables
                    if ($result->order_id) {
                        // Check posts table first (legacy orders)
                        $order_data = $wpdb->get_row($wpdb->prepare(
                            "SELECT post_title, post_status FROM {$wpdb->prefix}posts WHERE ID = %d AND post_type = 'shop_order'",
                            $result->order_id
                        ));
                        
                        if ($order_data) {
                            $result->order_key = $order_data->post_title ?: ('Order #' . $result->order_id);
                            $result->order_status = $order_data->post_status;
                        } else {
                            // Check HPOS table if posts table didn't have the order
                            $hpos_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wc_orders'") === $wpdb->prefix . 'wc_orders';
                            if ($hpos_exists) {
                                $hpos_data = $wpdb->get_row($wpdb->prepare(
                                    "SELECT order_key, status FROM {$wpdb->prefix}wc_orders WHERE id = %d",
                                    $result->order_id
                                ));
                                if ($hpos_data) {
                                    $result->order_key = $hpos_data->order_key ?: ('Order #' . $result->order_id);
                                    $result->order_status = $hpos_data->status;
                                }
                            }
                        }
                    }
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    public static function get_commands_count($filter_status = null) {
        global $wpdb;
        
        // Ensure table is initialized
        if (!self::$table_name) {
            self::$table_name = $wpdb->prefix . 'mws_pending_commands';
        }
        
        $where_clause = "1=1";
        $params = array();
        
        if ($filter_status && in_array($filter_status, array('pending', 'read', 'executed', 'failed'))) {
            $where_clause .= " AND status = %s";
            $params[] = $filter_status;
        }
        
        $base_sql = "SELECT COUNT(*) FROM `" . self::$table_name . "` WHERE " . $where_clause;
        
        try {
            if (empty($params)) {
                $result = $wpdb->get_var($base_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            } else {
                $result = $wpdb->get_var($wpdb->prepare($base_sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            }
            
            return intval($result);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public static function get_order_completion_status($order_id) {
        global $wpdb;
        
        $table_name = self::$table_name;
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM `{$table_name}` WHERE order_id = %d GROUP BY status", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $order_id
        ));
        
        $status_counts = array();
        foreach ($results as $result) {
            $status_counts[$result->status] = $result->count;
        }
        
        $total = array_sum($status_counts);
        $executed = isset($status_counts['executed']) ? $status_counts['executed'] : 0;
        $failed = isset($status_counts['failed']) ? $status_counts['failed'] : 0;
        
        if ($total === 0) {
            return 'no_commands';
        }
        
        if ($executed === $total) {
            return 'completed';
        }
        
        if ($failed > 0) {
            return 'partial_failure';
        }
        
        return 'in_progress';
    }
}
