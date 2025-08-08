<?php
/**
 * Admin interface for MineWebStore
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_regenerate_secret_key', array($this, 'ajax_regenerate_secret_key'));
        add_action('wp_ajax_remove_server', array($this, 'ajax_remove_server'));
        add_action('wp_ajax_get_server_players', array($this, 'ajax_get_server_players'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Minecraft Integration', 'minewebstore'),
            __('Minecraft', 'minewebstore'),
            'manage_options',
            'minewebstore', // Main menu slug, not the text domain
            array($this, 'admin_page'),
            'dashicons-games',
            30
        );
    }
    
    public function admin_init() {
        register_setting('mws_settings', 'mws_secret_key', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        
        // Register text customization settings
        register_setting('mws_settings', 'mws_field_label', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mws_settings', 'mws_field_placeholder', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mws_settings', 'mws_field_title', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mws_settings', 'mws_validation_messages', array(
            'sanitize_callback' => array($this, 'sanitize_validation_messages')
        ));
        register_setting('mws_settings', 'mws_checkout_error_messages', array(
            'sanitize_callback' => array($this, 'sanitize_checkout_error_messages')
        ));
        register_setting('mws_settings', 'mws_order_section_title', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
        register_setting('mws_settings', 'mws_order_label', array(
            'sanitize_callback' => 'sanitize_text_field'
        ));
    }
    
    /**
     * Sanitize validation messages array
     */
    public function sanitize_validation_messages($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_keys = array(
            'validating', 'invalid', 'error', 'characters', 'minimum', 'maximum',
            'player_found_on', 'format_error', 'wait_validation'
        );
        
        foreach ($allowed_keys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize checkout error messages array
     */
    public function sanitize_checkout_error_messages($input) {
        if (!is_array($input)) {
            return array();
        }
        
        $sanitized = array();
        $allowed_keys = array('required', 'format', 'not_found');
        
        foreach ($allowed_keys as $key) {
            if (isset($input[$key])) {
                $sanitized[$key] = sanitize_text_field($input[$key]);
            }
        }
        
        return $sanitized;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_minewebstore') {
            return;
        }
        
        wp_enqueue_script(
            'mws-admin',
            MWS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            MWS_VERSION,
            true
        );
        
        wp_enqueue_style(
            'mws-admin',
            MWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MWS_VERSION
        );
        
        wp_localize_script('mws-admin', 'mws_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mws_admin_nonce')
        ));
    }
    
    public function admin_page() {
        $secret_key = get_option('mws_secret_key');
        $servers = MWS_Server_Manager::get_all_servers();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MineWebStore Settings', 'minewebstore'); ?></h1>
            <p class="description"><?php esc_html_e('Configure your MineWebStore settings and manage server connections.', 'minewebstore'); ?></p>
            
            <div class="mws-admin-container">
                <div class="mws-section">
                    <h2><?php esc_html_e('Secret Key', 'minewebstore'); ?></h2>
                    <p><?php esc_html_e('Use this secret key in your Minecraft server configuration to connect to this WordPress site. Keep this key secure and do not share it publicly.', 'minewebstore'); ?></p>
                    
                    <div class="secret-key-container">
                        <div class="secret-key-field">
                            <label for="secret-key-input"><?php esc_html_e('Your Secret Key:', 'minewebstore'); ?></label>
                            <div class="secret-key-input-group">
                                <input type="text" id="secret-key-input" class="regular-text code" value="<?php echo esc_attr($secret_key); ?>" readonly>
                                <button type="button" class="button button-secondary copy-key" data-key="<?php echo esc_attr($secret_key); ?>">
                                    <?php esc_html_e('Copy', 'minewebstore'); ?>
                                </button>
                                <button type="button" class="button button-secondary regenerate-key">
                                    <?php esc_html_e('Regenerate', 'minewebstore'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="secret-key-info">
                            <h4><?php esc_html_e('How to use:', 'minewebstore'); ?></h4>
                            <ol>
                                <li><?php esc_html_e('Copy the secret key above', 'minewebstore'); ?></li>
                                <li><?php esc_html_e('Open your Minecraft server\'s config.yml file', 'minewebstore'); ?></li>
                                <li><?php esc_html_e('Paste the key in the "secret_key" field', 'minewebstore'); ?></li>
                                <li><?php esc_html_e('Restart your Minecraft server', 'minewebstore'); ?></li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <div class="mws-section">
                    <h2><?php esc_html_e('Registered Servers', 'minewebstore'); ?></h2>
                    <?php if (empty($servers)): ?>
                        <p><?php esc_html_e('No servers registered yet.', 'minewebstore'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Server Name', 'minewebstore'); ?></th>
                                    <th><?php esc_html_e('Status', 'minewebstore'); ?></th>
                                    <th><?php esc_html_e('Last Seen', 'minewebstore'); ?></th>
                                    <th><?php esc_html_e('Players', 'minewebstore'); ?></th>
                                    <th><?php esc_html_e('Actions', 'minewebstore'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servers as $server): ?>
                                    <tr>
                                        <td><?php echo esc_html($server->server_name); ?></td>
                                        <td>
                                            <span class="status-<?php echo esc_attr($server->status); ?>">
                                                <?php echo esc_html(ucfirst($server->status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($server->last_seen))); ?></td>
                                        <td><?php echo esc_html(MWS_Player_Cache::get_player_count($server->id)); ?></td>
                                        <td>
                                            <button type="button" class="button button-small view-players" data-server-id="<?php echo esc_attr($server->id); ?>">
                                                <?php esc_html_e('View Players', 'minewebstore'); ?>
                                            </button>
                                            <button type="button" class="button button-small button-link-delete remove-server" data-server-id="<?php echo esc_attr($server->id); ?>">
                                                <?php esc_html_e('Remove', 'minewebstore'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
                <div class="mws-section">
                    <h2><?php esc_html_e('API Endpoints', 'minewebstore'); ?></h2>
                    <p><?php esc_html_e('These endpoints are used by Minecraft servers:', 'minewebstore'); ?></p>
                    <ul>
                        <li><code><?php echo esc_html(home_url('/wp-json/mcapi/v1/register')); ?></code> - Server registration</li>
                        <li><code><?php echo esc_html(home_url('/wp-json/mcapi/v1/players')); ?></code> - Player synchronization</li>
                        <li><code><?php echo esc_html(home_url('/wp-json/mcapi/v1/commands')); ?></code> - Get pending commands</li>
                        <li><code><?php echo esc_html(home_url('/wp-json/mcapi/v1/commands/read')); ?></code> - Mark commands as read</li>
                        <li><code><?php echo esc_html(home_url('/wp-json/mcapi/v1/commands/{id}')); ?></code> - Update command status</li>
                    </ul>
                </div>

                <div class="mws-section">
                    <h2><?php esc_html_e('Command Management', 'minewebstore'); ?></h2>
                    <p><?php esc_html_e('Monitor and manage Minecraft commands generated from WooCommerce orders.', 'minewebstore'); ?></p>
                    
                    <?php $this->render_commands_table(); ?>
                </div>
                
                <div class="mws-section">
                    <h2><?php esc_html_e('Text Customization', 'minewebstore'); ?></h2>
                    <p><?php esc_html_e('Customize the text displayed to customers on the checkout page and order details.', 'minewebstore'); ?></p>
                    
                    <form method="post" action="options.php">
                        <?php 
                        settings_fields('mws_settings');
                        
                        // Get current settings
                        $field_label = get_option('mws_field_label', __('Minecraft Player Name', 'minewebstore'));
                        $field_placeholder = get_option('mws_field_placeholder', __('Enter your Minecraft username', 'minewebstore'));
                        $field_title = get_option('mws_field_title', __('Minecraft username must be 3-16 characters, alphanumeric and underscores only', 'minewebstore'));
                        $validation_messages = get_option('mws_validation_messages', array(
                            'validating' => __('Validating player...', 'minewebstore'),
                            'invalid' => __('Player not found on any required servers.', 'minewebstore'),
                            'error' => __('Error validating player. Please try again.', 'minewebstore')
                        ));
                        $order_section_title = get_option('mws_order_section_title', __('Minecraft Information', 'minewebstore'));
                        $order_label = get_option('mws_order_label', __('Player Name:', 'minewebstore'));
                        $checkout_error_messages = get_option('mws_checkout_error_messages', array(
                            'required' => __('Minecraft Player Name is required for your purchase.', 'minewebstore'),
                            'format' => __('Minecraft Player Name must be 3-16 characters, alphanumeric and underscores only.', 'minewebstore'),
                            'not_found' => __('The specified Minecraft player was not found on any required servers. Please ensure you have joined the server before making this purchase.', 'minewebstore')
                        ));
                        ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Field Label', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_field_label" value="<?php echo esc_attr($field_label); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('The label shown above the Minecraft player name field.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Field Placeholder', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_field_placeholder" value="<?php echo esc_attr($field_placeholder); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('The placeholder text shown inside the input field.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Field Title (Tooltip)', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_field_title" value="<?php echo esc_attr($field_title); ?>" class="large-text" />
                                    <p class="description"><?php esc_html_e('The tooltip text shown when hovering over the field or on validation errors.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Validation Message - Checking', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[validating]" value="<?php echo esc_attr($validation_messages['validating']); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Message shown while checking if the player exists on the server.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Validation Message - Not Found', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[invalid]" value="<?php echo esc_attr($validation_messages['invalid']); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Message shown when the player is not found on any required servers.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Validation Message - Error', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[error]" value="<?php echo esc_attr($validation_messages['error']); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Message shown when there\'s an error during validation.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Character Counter - Characters', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[characters]" value="<?php echo esc_attr($validation_messages['characters'] ?? __('characters', 'minewebstore')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Text shown after the character count (e.g., "5/16 characters").', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Character Counter - Minimum', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[minimum]" value="<?php echo esc_attr($validation_messages['minimum'] ?? __('minimum', 'minewebstore')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Text shown for minimum character requirement (e.g., "minimum 3").', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Character Counter - Maximum', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[maximum]" value="<?php echo esc_attr($validation_messages['maximum'] ?? __('maximum', 'minewebstore')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Text shown for maximum character requirement (e.g., "maximum 16").', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Player Found Message', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[player_found_on]" value="<?php echo esc_attr($validation_messages['player_found_on'] ?? __('Player found on:', 'minewebstore')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Text shown before server names when player is found (e.g., "Player found on: Server-1").', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Format Error Message', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[format_error]" value="<?php echo esc_attr($validation_messages['format_error'] ?? __('Invalid player name format. Must be 3-16 characters, letters, numbers, and underscores only.', 'minewebstore')); ?>" class="large-text" />
                                    <p class="description"><?php esc_html_e('Message shown when player name format is invalid during real-time validation.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Wait Validation Message', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_validation_messages[wait_validation]" value="<?php echo esc_attr($validation_messages['wait_validation'] ?? __('Please wait for player name validation to complete.', 'minewebstore')); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('Alert message shown when user tries to submit checkout while validation is in progress.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Checkout Error - Required Field', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_checkout_error_messages[required]" value="<?php echo esc_attr($checkout_error_messages['required']); ?>" class="large-text" />
                                    <p class="description"><?php esc_html_e('Error message when the player name field is empty during checkout.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Checkout Error - Invalid Format', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_checkout_error_messages[format]" value="<?php echo esc_attr($checkout_error_messages['format']); ?>" class="large-text" />
                                    <p class="description"><?php esc_html_e('Error message when the player name format is invalid during checkout.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Checkout Error - Player Not Found', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_checkout_error_messages[not_found]" value="<?php echo esc_attr($checkout_error_messages['not_found']); ?>" class="large-text" />
                                    <p class="description"><?php esc_html_e('Error message when the player is not found on required servers during checkout.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Order Section Title', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_order_section_title" value="<?php echo esc_attr($order_section_title); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('The title shown in order details for the Minecraft section.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Order Player Label', 'minewebstore'); ?></th>
                                <td>
                                    <input type="text" name="mws_order_label" value="<?php echo esc_attr($order_label); ?>" class="regular-text" />
                                    <p class="description"><?php esc_html_e('The label shown before the player name in order details.', 'minewebstore'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button(__('Save Text Settings', 'minewebstore')); ?>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Player List Modal -->
        <div id="player-list-modal" class="mws-modal" style="display: none;">
            <div class="mws-modal-content">
                <span class="mws-modal-close">&times;</span>
                <h3><?php esc_html_e('Players on Server', 'minewebstore'); ?></h3>
                <div id="player-list-content"></div>
            </div>
        </div>
        <?php
    }
    
    public function ajax_regenerate_secret_key() {
        check_ajax_referer('mws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $new_key = wp_generate_password(32, false);
        update_option('mws_secret_key', $new_key);
        
        // Deactivate all existing servers (they'll need to re-register)
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'mws_servers',
            array('status' => 'inactive'),
            array('status' => 'active'),
            array('%s'),
            array('%s')
        );
        
        wp_send_json_success(array('new_key' => $new_key));
    }
    
    public function ajax_remove_server() {
        check_ajax_referer('mws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $server_id = intval($_POST['server_id'] ?? 0);
        
        if (!$server_id) {
            wp_send_json_error(array('message' => 'Invalid server ID'));
        }
        
        $result = MWS_Server_Manager::delete_server($server_id);
        
        if ($result !== false) {
            wp_send_json_success();
        } else {
            wp_send_json_error(array('message' => 'Failed to remove server'));
        }
    }
    
    public function ajax_get_server_players() {
        check_ajax_referer('mws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        $server_id = intval($_POST['server_id'] ?? 0);
        
        if (!$server_id) {
            wp_send_json_error(array('message' => 'Invalid server ID'));
        }
        
        $players = MWS_Player_Cache::get_players($server_id, 100);
        
        wp_send_json_success(array('players' => $players));
    }

    private function render_commands_table() {
        // Note: This method processes GET parameters for filtering and pagination.
        // These are read-only operations within an admin page that already requires
        // 'manage_options' capability.
        
        // Get pagination parameters with proper sanitization
        // Using filter_input to avoid nonce verification warnings for read-only pagination
        $current_page = filter_input(INPUT_GET, 'commands_page', FILTER_VALIDATE_INT, array('options' => array('default' => 1, 'min_range' => 1)));
        $filter_status = filter_input(INPUT_GET, 'filter_status', FILTER_SANITIZE_STRING, array('options' => array('default' => '')));
        
        // Validate filter_status against allowed values
        $allowed_statuses = array('', 'pending', 'read', 'executed', 'failed');
        if (!in_array($filter_status, $allowed_statuses, true)) {
            $filter_status = '';
        }
        
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;

        // Get commands and count
        $commands = MWS_Pending_Commands::get_commands_for_admin($per_page, $offset, $filter_status);
        $total_commands = MWS_Pending_Commands::get_commands_count($filter_status);
        $total_pages = ceil($total_commands / $per_page);

        // Status counts for filter tabs
        $status_counts = array(
            'all' => MWS_Pending_Commands::get_commands_count(),
            'pending' => MWS_Pending_Commands::get_commands_count('pending'),
            'read' => MWS_Pending_Commands::get_commands_count('read'),
            'executed' => MWS_Pending_Commands::get_commands_count('executed'),
            'failed' => MWS_Pending_Commands::get_commands_count('failed'),
        );

        ?>
        <div class="commands-management">
            <!-- Status Filter Tabs -->
            <h3 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(add_query_arg(array('filter_status' => '', 'commands_page' => 1))); ?>" 
                   class="nav-tab <?php echo empty($filter_status) ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('All', 'minewebstore'); ?> (<?php echo esc_html($status_counts['all']); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('filter_status' => 'pending', 'commands_page' => 1))); ?>" 
                   class="nav-tab <?php echo $filter_status === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Pending', 'minewebstore'); ?> (<?php echo esc_html($status_counts['pending']); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('filter_status' => 'read', 'commands_page' => 1))); ?>" 
                   class="nav-tab <?php echo $filter_status === 'read' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Read', 'minewebstore'); ?> (<?php echo esc_html($status_counts['read']); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('filter_status' => 'executed', 'commands_page' => 1))); ?>" 
                   class="nav-tab <?php echo $filter_status === 'executed' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Executed', 'minewebstore'); ?> (<?php echo esc_html($status_counts['executed']); ?>)
                </a>
                <a href="<?php echo esc_url(add_query_arg(array('filter_status' => 'failed', 'commands_page' => 1))); ?>" 
                   class="nav-tab <?php echo $filter_status === 'failed' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Failed', 'minewebstore'); ?> (<?php echo esc_html($status_counts['failed']); ?>)
                </a>
            </h3>

            <?php if (empty($commands)): ?>
                <p><?php esc_html_e('No commands found.', 'minewebstore'); ?></p>
            <?php else: ?>
                <!-- Commands Table -->
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Order', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Product', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Player', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Command', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Status', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Created', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Read At', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Executed At', 'minewebstore'); ?></th>
                            <th><?php esc_html_e('Message', 'minewebstore'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($commands as $command): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(admin_url('post.php?post=' . $command->order_id . '&action=edit')); ?>">
                                        #<?php echo esc_html($command->order_id); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($command->product_name ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($command->player_name); ?></td>
                                <td>
                                    <code><?php echo esc_html(strlen($command->command_text) > 50 ? substr($command->command_text, 0, 50) . '...' : $command->command_text); ?></code>
                                    <?php if (strlen($command->command_text) > 50): ?>
                                        <span class="full-command" style="display:none;"><?php echo esc_html($command->command_text); ?></span>
                                        <button type="button" class="button-link toggle-command"><?php esc_html_e('Show full', 'minewebstore'); ?></button>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="command-status status-<?php echo esc_attr($command->status); ?>">
                                        <?php echo esc_html(ucfirst($command->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($command->created_at))); ?></td>
                                <td>
                                    <?php if ($command->read_at && $command->read_at !== '0000-00-00 00:00:00'): ?>
                                        <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($command->read_at))); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($command->executed_at && $command->executed_at !== '0000-00-00 00:00:00'): ?>
                                        <?php echo esc_html(date_i18n('Y-m-d H:i', strtotime($command->executed_at))); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($command->execution_message): ?>
                                        <span class="execution-message"><?php echo esc_html($command->execution_message); ?></span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php 
                                // translators: %s is the number of pending commands
                                printf(esc_html__('%s items', 'minewebstore'), esc_html($total_commands)); ?>
                            </span>
                            <?php
                            $pagination_args = array(
                                'base' => add_query_arg('commands_page', '%#%'),
                                'format' => '',
                                'current' => $current_page,
                                'total' => $total_pages,
                                'prev_text' => '‹',
                                'next_text' => '›',
                            );
                            echo wp_kses(paginate_links($pagination_args), array(
                                'a' => array(
                                    'href' => array(),
                                    'class' => array()
                                ),
                                'span' => array(
                                    'class' => array(),
                                    'aria-current' => array()
                                )
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <style>
        .command-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #ffecb3; color: #f57f17; }
        .status-read { background: #e1f5fe; color: #0277bd; }
        .status-executed { background: #e8f5e8; color: #2e7d32; }
        .status-failed { background: #ffebee; color: #c62828; }
        .full-command { display: none; }
        .toggle-command { text-decoration: none; }
        .execution-message { font-style: italic; }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.toggle-command').click(function() {
                var $this = $(this);
                var $fullCommand = $this.siblings('.full-command');
                var $code = $this.closest('td').find('code');
                
                if ($fullCommand.is(':visible')) {
                    $fullCommand.hide();
                    $code.html($code.data('short'));
                    $this.text('<?php esc_html_e('Show full', 'minewebstore'); ?>');
                } else {
                    if (!$code.data('short')) {
                        $code.data('short', $code.html());
                    }
                    $fullCommand.show();
                    $code.html($fullCommand.text());
                    $this.text('<?php esc_html_e('Show less', 'minewebstore'); ?>');
                }
            });
        });
        </script>
        <?php
    }
}
