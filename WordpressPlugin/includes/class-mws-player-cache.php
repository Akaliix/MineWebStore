<?php
/**
 * Player cache management
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Player_Cache {
    
    public static function update_players($server_id, $players) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Clear existing players for this server
            $wpdb->delete(
                $table_name,
                array('server_id' => $server_id),
                array('%d')
            );
            
            // Insert all players who have ever joined
            if (!empty($players)) {
                $values = array();
                $placeholder_strings = array();
                
                foreach ($players as $player) {
                    $values[] = $server_id;
                    $values[] = $player;
                    $values[] = current_time('mysql');
                    $placeholder_strings[] = '(%d, %s, %s)';
                }
                
                $placeholders_sql = implode(', ', $placeholder_strings);
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO `{$table_name}` (server_id, player_name, last_seen) VALUES {$placeholders_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $values
                ));
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return true;
        } catch (Exception $e) {
            // Rollback on error
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    
    public static function get_players($server_id, $limit = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        if ($limit) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT player_name, last_seen FROM `{$table_name}` WHERE server_id = %d ORDER BY player_name ASC LIMIT %d",
                $server_id,
                $limit
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT player_name, last_seen FROM `{$table_name}` WHERE server_id = %d ORDER BY player_name ASC",
                $server_id
            ));
        }
    }
    
    public static function get_player_count($server_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE server_id = %d",
            $server_id
        ));
    }
    
    public static function has_player_joined($server_id, $player_name) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE server_id = %d AND player_name = %s",
            $server_id,
            $player_name
        ));
        
        return $result > 0;
    }
    
    public static function get_player_hash($server_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        $players = $wpdb->get_col($wpdb->prepare(
            "SELECT player_name FROM `{$table_name}` WHERE server_id = %d ORDER BY player_name ASC",
            $server_id
        ));
        
        return md5(serialize($players));
    }
    
    public static function search_players($query, $server_id = null, $limit = 20) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        $where_conditions = array();
        $where_values = array();
        
        // Add search query
        if (!empty($query)) {
            $where_conditions[] = "player_name LIKE %s";
            $where_values[] = '%' . $wpdb->esc_like($query) . '%';
        }
        
        // Add server filter
        if ($server_id) {
            $where_conditions[] = "server_id = %d";
            $where_values[] = $server_id;
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $base_sql = "SELECT DISTINCT player_name, server_id FROM `{$table_name}` {$where_clause} ORDER BY player_name ASC";
        
        if ($limit) {
            $base_sql .= " LIMIT %d";
            $where_values[] = $limit;
        }
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($base_sql, $where_values)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        } else {
            return $wpdb->get_results($base_sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
    }
    
    public static function get_all_players_for_servers($server_ids) {
        global $wpdb;
        
        if (empty($server_ids)) {
            return array();
        }
        
        $table_name = $wpdb->prefix . 'mws_players';
        $placeholders = implode(',', array_fill(0, count($server_ids), '%d'));
        
        return $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT player_name FROM `{$table_name}` WHERE server_id IN ({$placeholders}) ORDER BY player_name ASC",
            ...$server_ids
        ));
    }
    
    public static function cleanup_old_players($days = 30) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM `{$table_name}` WHERE last_seen < %s",
            $cutoff_date
        ));
    }
    
    public static function get_player_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'mws_players';
        
        $stats = array(
            'total_players' => 0,
            'total_unique_players' => 0,
            'players_last_24h' => 0,
            'players_last_7d' => 0
        );
        
        // Total player records
        $stats['total_players'] = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        
        // Unique players across all servers
        $stats['total_unique_players'] = $wpdb->get_var("SELECT COUNT(DISTINCT player_name) FROM `{$table_name}`");
        
        // Players seen in last 24 hours
        $last_24h = gmdate('Y-m-d H:i:s', strtotime('-24 hours'));
        $stats['players_last_24h'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT player_name) FROM `{$table_name}` WHERE last_seen > %s",
            $last_24h
        ));
        
        // Players seen in last 7 days
        $last_7d = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        $stats['players_last_7d'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT player_name) FROM `{$table_name}` WHERE last_seen > %s",
            $last_7d
        ));
        
        return $stats;
    }
}
