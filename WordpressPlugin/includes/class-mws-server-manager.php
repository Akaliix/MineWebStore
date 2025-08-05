<?php
/**
 * Server management functionality
 * 
 * This class handles registration, authentication, and management of Minecraft servers
 * that connect to the WordPress API. Each server has a unique name and API key.
 * 
 * Key Features:
 * - Server registration and authentication
 * - Activity tracking (last seen timestamps)
 * - Server status management (active/inactive)
 * - Secure API key generation and validation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Server_Manager {
    
    public static function register_server($server_name, $server_key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        // Check if server already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE server_name = %s",
            $server_name
        ));
        
        if ($existing) {
            // Update existing server
            $result = $wpdb->update(
                $table_name,
                array(
                    'server_key' => $server_key,
                    'last_seen' => current_time('mysql'),
                    'status' => 'active'
                ),
                array('server_name' => $server_name),
                array('%s', '%s', '%s'),
                array('%s')
            );
            
            if ($result === false) {
                return new WP_Error('update_failed', 'Failed to update server: ' . $wpdb->last_error);
            }
            
            return $existing->id;
        } else {
            // Insert new server
            $result = $wpdb->insert(
                $table_name,
                array(
                    'server_name' => $server_name,
                    'server_key' => $server_key,
                    'last_seen' => current_time('mysql'),
                    'status' => 'active',
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                return new WP_Error('insert_failed', 'Failed to register server: ' . $wpdb->last_error);
            }
            
            return $wpdb->insert_id;
        }
    }
    
    public static function get_server_by_name($server_name) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE server_name = %s",
            $server_name
        ));
    }
    
    public static function get_server_by_id($server_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $server_id
        ));
    }
    
    public static function get_all_servers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        return $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC");
    }
    
    public static function get_active_servers() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE status = %s ORDER BY server_name ASC",
            'active'
        ));
    }
    
    public static function update_last_seen($server_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        return $wpdb->update(
            $table_name,
            array(
                'last_seen' => current_time('mysql'),
                'status' => 'active'
            ),
            array('id' => $server_id),
            array('%s', '%s'),
            array('%d')
        );
    }
    
    public static function set_server_status($server_id, $status) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        
        $valid_statuses = array('active', 'inactive');
        if (!in_array($status, $valid_statuses)) {
            return false;
        }
        
        return $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $server_id),
            array('%s'),
            array('%d')
        );
    }
    
    public static function delete_server($server_id) {
        global $wpdb;
        
        $servers_table = $wpdb->prefix . 'mws_servers';
        $players_table = $wpdb->prefix . 'mws_players';
        
        // Delete associated players first
        $wpdb->delete(
            $players_table,
            array('server_id' => $server_id),
            array('%d')
        );
        
        // Delete server
        return $wpdb->delete(
            $servers_table,
            array('id' => $server_id),
            array('%d')
        );
    }
    
    /**
     * Mark servers as inactive if they haven't been seen for a while
     */
    public static function mark_inactive_servers($hours = 24) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_servers';
        $cutoff_time = gmdate('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} SET status = 'inactive' WHERE last_seen < %s AND status = 'active'",
            $cutoff_time
        ));
    }
    
    /**
     * Get server statistics
     */
    public static function get_server_stats($server_id) {
        global $wpdb;
        
        $players_table = $wpdb->prefix . 'mws_players';
        
        $stats = array(
            'total_players' => 0,
            'recent_players' => 0,
            'last_activity' => null
        );
        
        // Total players
        $stats['total_players'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$players_table} WHERE server_id = %d",
            $server_id
        ));
        
        // Recent players (last 7 days)
        $recent_cutoff = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $stats['recent_players'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$players_table} WHERE server_id = %d AND last_seen > %s",
            $server_id,
            $recent_cutoff
        ));
        
        // Last activity
        $stats['last_activity'] = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(last_seen) FROM {$players_table} WHERE server_id = %d",
            $server_id
        ));
        
        return $stats;
    }
}
