<?php
/**
 * API endpoints for Minecraft server integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    public function register_routes() {
        // Health check endpoint
        register_rest_route('mcapi/v1', '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_status'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('mcapi/v1', '/register', array(
            'methods' => 'POST',
            'callback' => array($this, 'register_server'),
            'permission_callback' => '__return_true',
            'args' => array(
                'secret_key' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'server_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
        
        register_rest_route('mcapi/v1', '/players', array(
            'methods' => 'POST',
            'callback' => array($this, 'sync_players'),
            'permission_callback' => array($this, 'check_server_auth'),
            'args' => array(
                'server_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'player_hash' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'players' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            ),
        ));

        // Endpoint to get pending commands for Minecraft server
        register_rest_route('mcapi/v1', '/commands', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_pending_commands'),
            'permission_callback' => array($this, 'check_server_auth'),
            'args' => array(
                'server_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 50,
                    'sanitize_callback' => 'absint',
                ),
            ),
        ));

        // Endpoint to mark commands as read
        register_rest_route('mcapi/v1', '/commands/read', array(
            'methods' => 'POST',
            'callback' => array($this, 'mark_commands_read'),
            'permission_callback' => array($this, 'check_server_auth'),
            'args' => array(
                'server_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'command_ids' => array(
                    'required' => true,
                    'type' => 'array',
                    'items' => array(
                        'type' => 'integer',
                        'sanitize_callback' => 'absint',
                    ),
                ),
            ),
        ));

        // Endpoint to update command execution status
        register_rest_route('mcapi/v1', '/commands/(?P<id>\d+)', array(
            'methods' => 'PUT',
            'callback' => array($this, 'update_command_status'),
            'permission_callback' => array($this, 'check_server_auth'),
            'args' => array(
                'server_name' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'status' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('executed', 'failed'),
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'message' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ));
    }
    
    public function get_status($request) {
        global $wpdb;
        
        // Check if database tables exist
        $table_name = $wpdb->prefix . 'mws_servers';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name;
        
        // Check if secret key is configured
        $secret_configured = !empty(get_option('mws_secret_key'));
        
        return array(
            'success' => true,
            'plugin' => 'MWS API',
            'version' => '1.0.0',
            'status' => 'active',
            'database_ready' => $table_exists,
            'secret_configured' => $secret_configured,
            'timestamp' => current_time('mysql')
        );
    }
    
    public function register_server($request) {
        $secret_key = $request->get_param('secret_key');
        $server_name = $request->get_param('server_name');
        
        // Verify secret key
        $stored_secret = get_option('mws_secret_key');
        if (!$stored_secret) {
            return new WP_Error('no_secret_configured', __('No secret key configured', 'minewebstore'), array('status' => 500));
        }
        
        if ($secret_key !== $stored_secret) {
            return new WP_Error('invalid_secret', __('Invalid secret key', 'minewebstore'), array('status' => 401));
        }
        
        // Validate server name
        if (empty($server_name) || strlen($server_name) > 50) {
            return new WP_Error('invalid_server_name', __('Server name must be between 1-50 characters', 'minewebstore'), array('status' => 400));
        }
        
        // Check if database tables exist
        global $wpdb;
        $table_name = $wpdb->prefix . 'mws_servers';
        
        // Verify table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return new WP_Error('database_error', __('Database tables not initialized', 'minewebstore'), array('status' => 500));
        }
        
        // Generate server-specific key
        $server_key = wp_generate_password(32, false);
        
        // Register or update server
        $result = MWS_Server_Manager::register_server($server_name, $server_key);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        return array(
            'success' => true,
            'server_key' => $server_key,
            // translators: %s is the server name that was registered
            'message' => sprintf(__('Server "%s" registered successfully', 'minewebstore'), $server_name)
        );
    }
    
    public function sync_players($request) {
        $server_name = $request->get_param('server_name');
        $player_hash = $request->get_param('player_hash');
        $players = $request->get_param('players');
        
        // Get server ID
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        if (!$server) {
            return new WP_Error('server_not_found', __('Server not found', 'minewebstore'), array('status' => 404));
        }
        
        // Update server last seen
        MWS_Server_Manager::update_last_seen($server->id);
        
        // Validate players array
        if (!is_array($players)) {
            return new WP_Error('invalid_players', __('Players must be an array', 'minewebstore'), array('status' => 400));
        }
        
        // Filter valid Minecraft usernames (3-16 chars, alphanumeric + underscore)
        $valid_players = array_filter($players, function($player) {
            return preg_match('/^[a-zA-Z0-9_]{3,16}$/', $player);
        });
        
        // Handle player history (all players who have ever joined)
        $current_hash = MWS_Player_Cache::get_player_hash($server->id);
        $new_hash = md5(serialize(sort($valid_players)));
        
        $needs_update = false;
        if ($player_hash) {
            // Server provided a hash, check if it matches our current hash
            if ($player_hash !== $current_hash) {
                $needs_update = true;
            }
        } else {
            // No hash provided, always update
            $needs_update = true;
        }
        
        if ($needs_update) {
            MWS_Player_Cache::update_players($server->id, $valid_players);
        }
        
        return array(
            'success' => true,
            'hash' => $new_hash,
            'players_count' => count($valid_players),
            'updated' => $needs_update
        );
    }
    
    public function check_server_auth($request) {
        $server_name = $request->get_param('server_name');
        
        if (!$server_name) {
            return false;
        }
        
        // Check if server exists and is active
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        
        if (!$server || $server->status !== 'active') {
            return false;
        }
        
        // Check authorization header for server key
        $auth_header = $request->get_header('authorization');
        if (!$auth_header) {
            return false;
        }
        
        $auth_parts = explode(' ', $auth_header);
        if (count($auth_parts) !== 2 || $auth_parts[0] !== 'Bearer') {
            return false;
        }
        
        $provided_key = $auth_parts[1];
        
        return $provided_key === $server->server_key;
    }

    public function get_pending_commands($request) {
        $server_name = $request->get_param('server_name');
        $limit = $request->get_param('limit') ?: 50;

        // Get server to verify it exists
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        if (!$server) {
            return new WP_Error('server_not_found', __('Server not found', 'minewebstore'), array('status' => 404));
        }

        // Update server last seen
        MWS_Server_Manager::update_last_seen($server->id);

        // Get pending commands
        $commands = MWS_Pending_Commands::get_pending_commands($server_name, $limit);

        // Format commands for response
        $formatted_commands = array();
        foreach ($commands as $command) {
            $formatted_commands[] = array(
                'id' => (int) $command->id,
                'order_id' => (int) $command->order_id,
                'product_id' => (int) $command->product_id,
                'player_name' => $command->player_name,
                'command' => $command->command_text,
                'run_mode' => $command->run_mode,
                'created_at' => $command->created_at,
            );
        }

        return array(
            'success' => true,
            'commands' => $formatted_commands,
            'count' => count($formatted_commands),
            'server_name' => $server_name,
        );
    }

    public function mark_commands_read($request) {
        $server_name = $request->get_param('server_name');
        $command_ids = $request->get_param('command_ids');

        // Get server to verify it exists
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        if (!$server) {
            return new WP_Error('server_not_found', __('Server not found', 'minewebstore'), array('status' => 404));
        }

        // Validate command IDs
        if (!is_array($command_ids) || empty($command_ids)) {
            return new WP_Error('invalid_command_ids', __('Command IDs must be a non-empty array', 'minewebstore'), array('status' => 400));
        }

        // Update server last seen
        MWS_Server_Manager::update_last_seen($server->id);

        // Mark commands as read
        $updated = MWS_Pending_Commands::mark_commands_as_read($command_ids, $server_name);

        return array(
            'success' => true,
            'updated_count' => $updated,
            'command_ids' => $command_ids,
        );
    }

    public function update_command_status($request) {
        $command_id = (int) $request->get_param('id');
        $server_name = $request->get_param('server_name');
        $status = $request->get_param('status');
        $message = $request->get_param('message');

        // Get server to verify it exists
        $server = MWS_Server_Manager::get_server_by_name($server_name);
        if (!$server) {
            return new WP_Error('server_not_found', __('Server not found', 'minewebstore'), array('status' => 404));
        }

        // Validate status
        if (!in_array($status, array('executed', 'failed'))) {
            return new WP_Error('invalid_status', __('Status must be either "executed" or "failed"', 'minewebstore'), array('status' => 400));
        }

        // Update server last seen
        MWS_Server_Manager::update_last_seen($server->id);

        // Update command status
        $updated = MWS_Pending_Commands::update_command_status($command_id, $status, $message, $server_name);

        if ($updated === false) {
            return new WP_Error('update_failed', __('Failed to update command status', 'minewebstore'), array('status' => 500));
        }

        return array(
            'success' => true,
            'command_id' => $command_id,
            'status' => $status,
            'message' => $message,
        );
    }
}
