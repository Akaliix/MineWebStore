<?php
/**
 * Product fields for Minecraft commands
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MWS_Product_Fields {
    
    public function __construct() {
        // Add product fields
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        
        // Add variation fields for variable products
        add_action('woocommerce_variation_options_pricing', array($this, 'add_variation_fields'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_fields'), 10, 2);
    }
    
    public function add_product_fields() {
        global $woocommerce, $post;
        
        echo '<div class="options_group mws-product-fields">';
        
        // Server selection
        $servers = MWS_Server_Manager::get_active_servers();
        $server_options = array('' => __('Select server...', 'minewebstore'));
        
        foreach ($servers as $server) {
            $server_options[$server->id] = $server->server_name;
        }
        
        woocommerce_wp_select(array(
            'id' => '_mc_server_id',
            'label' => __('Minecraft Server', 'minewebstore'),
            'description' => __('Select which Minecraft server should execute the commands', 'minewebstore'),
            'desc_tip' => true,
            'options' => $server_options
        ));
        
        // Commands field with dynamic add/remove functionality
        echo '<div class="mc-commands-container">';
        echo '<label for="_mc_commands">' . esc_html(__('Minecraft Commands', 'minewebstore')) . '</label>';
        echo '<p class="description">' . esc_html(__('Enter Minecraft commands to execute when this product is purchased. Use %player% placeholder for the player name.', 'minewebstore')) . '</p>';
        
        $existing_commands = get_post_meta($post->ID, '_mc_commands', true);
        $existing_run_modes = get_post_meta($post->ID, '_mc_commands_run_modes', true);
        $commands_array = array_filter(array_map('trim', explode("\n", $existing_commands)));
        $run_modes_array = !empty($existing_run_modes) ? explode("\n", $existing_run_modes) : array();
        
        if (empty($commands_array)) {
            $commands_array = array(''); // Start with one empty command
            $run_modes_array = array('online'); // Default to 'online'
        }
        
        // Ensure run_modes_array has the same length as commands_array
        while (count($run_modes_array) < count($commands_array)) {
            $run_modes_array[] = 'online';
        }
        
        echo '<div id="mc-commands-list">';
        foreach ($commands_array as $index => $command) {
            $run_mode = isset($run_modes_array[$index]) ? $run_modes_array[$index] : 'always';
            echo '<div class="mc-command-row" data-index="' . esc_attr($index) . '">';
            echo '<input type="text" name="_mc_commands[]" value="' . esc_attr($command) . '" placeholder="give %player% diamond 1" class="mc-command-input" />';
            echo '<select name="_mc_commands_run_modes[]" class="mc-run-mode-select">';
            echo '<option value="always"' . selected($run_mode, 'always', false) . '>' . esc_html(__('Always Run', 'minewebstore')) . '</option>';
            echo '<option value="online"' . selected($run_mode, 'online', false) . '>' . esc_html(__('Run When Player Online', 'minewebstore')) . '</option>';
            echo '</select>';
            echo '<button type="button" class="button mc-remove-command" title="' . esc_attr(__('Remove command', 'minewebstore')) . '">−</button>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '<button type="button" id="mc-add-command" class="button">' . esc_html(__('+ Add Command', 'minewebstore')) . '</button>';
        
        echo '</div>';
        
        // Command delay field
        woocommerce_wp_text_input(array(
            'id' => '_mc_command_delay',
            'label' => __('Command Delay (seconds)', 'minewebstore'),
            'description' => __('Delay between each command execution in seconds. Default: 0', 'minewebstore'),
            'desc_tip' => true,
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '0',
                'step' => '0.1'
            ),
            'value' => get_post_meta($post->ID, '_mc_command_delay', true) ?: '0'
        ));
        
        echo '</div>';
        
        // Add some JavaScript for better UX
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Handle server selection
            $('#_mc_server_id').change(function() {
                var serverId = $(this).val();
                var $commandsContainer = $('#mc-commands-list');
                
                if (serverId && $commandsContainer.find('.mc-command-input').first().val() === '') {
                    // Populate with example commands
                    $commandsContainer.find('.mc-command-input').first().val('give %player% diamond 1');
                }
            });
            
            // Add command functionality
            $('#mc-add-command').click(function() {
                var index = $('#mc-commands-list .mc-command-row').length;
                var newRow = '<div class="mc-command-row" data-index="' + index + '">' +
                    '<input type="text" name="_mc_commands[]" value="" placeholder="give %player% diamond 1" class="mc-command-input" />' +
                    '<select name="_mc_commands_run_modes[]" class="mc-run-mode-select">' +
                    '<option value="always"><?php echo esc_attr(__('Always Run', 'minewebstore')); ?></option>' +
                    '<option value="online" selected><?php echo esc_attr(__('Run When Player Online', 'minewebstore')); ?></option>' +
                    '</select>' +
                    '<button type="button" class="button mc-remove-command" title="<?php echo esc_attr(__('Remove command', 'minewebstore')); ?>">−</button>' +
                    '</div>';
                $('#mc-commands-list').append(newRow);
            });
            
            // Remove command functionality
            $(document).on('click', '.mc-remove-command', function() {
                if ($('#mc-commands-list .mc-command-row').length > 1) {
                    $(this).closest('.mc-command-row').remove();
                }
            });
        });
        </script>
        <style>
        .mc-commands-container {
            margin-bottom: 12px;
        }
        .mc-command-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .mc-command-input {
            flex: 1;
            margin-right: 8px;
            min-width: 300px;
        }
        .mc-run-mode-select {
            margin-right: 8px;
            min-width: 150px;
        }
        .mc-remove-command {
            background: #dc3232;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 3px;
            cursor: pointer;
        }
        .mc-remove-command:hover {
            background: #a02323;
        }
        #mc-add-command {
            margin-top: 8px;
        }
        .mws-product-fields {
            border: 1px solid #ddd;
            padding: 12px;
            background: #f9f9f9;
        }
        </style>
        <?php
    }
    
    public function save_product_fields($post_id) {
        // Verify nonce for security
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $server_id = sanitize_text_field(wp_unslash($_POST['_mc_server_id'] ?? ''));
        
        // Handle command structure (array of commands)
        $commands = '';
        $run_modes = '';
        if (isset($_POST['_mc_commands']) && is_array($_POST['_mc_commands'])) {
            $commands_array = array_filter(array_map('trim', array_map('sanitize_text_field', wp_unslash($_POST['_mc_commands']))));
            $commands = implode("\n", $commands_array);
            
            // Handle run modes
            if (isset($_POST['_mc_commands_run_modes']) && is_array($_POST['_mc_commands_run_modes'])) {
                $run_modes_array = array_slice(array_map('sanitize_text_field', wp_unslash($_POST['_mc_commands_run_modes'])), 0, count($commands_array));
                // Ensure all run modes are valid
                $run_modes_array = array_map(function($mode) {
                    return in_array($mode, array('always', 'online')) ? $mode : 'always';
                }, $run_modes_array);
                $run_modes = implode("\n", $run_modes_array);
            }
        }
        
        $command_delay = floatval(sanitize_text_field(wp_unslash($_POST['_mc_command_delay'] ?? '0')));
        
        update_post_meta($post_id, '_mc_server_id', $server_id);
        update_post_meta($post_id, '_mc_commands', $commands);
        update_post_meta($post_id, '_mc_commands_run_modes', $run_modes);
        update_post_meta($post_id, '_mc_command_delay', $command_delay);
    }
    
    public function add_variation_fields($loop, $variation_data, $variation) {
        echo '<div class="mws-variation-fields">';
        
        // Server selection for variation
        $servers = MWS_Server_Manager::get_active_servers();
        $server_options = array('' => __('Use parent product setting', 'minewebstore'));
        
        foreach ($servers as $server) {
            $server_options[$server->id] = $server->server_name;
        }
        
        woocommerce_wp_select(array(
            'id' => '_mc_server_id_' . $variation->ID,
            'name' => '_mc_server_id[' . $variation->ID . ']',
            'label' => __('Minecraft Server', 'minewebstore'),
            'description' => __('Override server for this variation', 'minewebstore'),
            'desc_tip' => true,
            'value' => get_post_meta($variation->ID, '_mc_server_id', true),
            'options' => $server_options,
            'wrapper_class' => 'form-row form-row-full'
        ));
        
        // Commands for variation
        woocommerce_wp_textarea_input(array(
            'id' => '_mc_commands_' . $variation->ID,
            'name' => '_mc_commands[' . $variation->ID . ']',
            'label' => __('Minecraft Commands', 'minewebstore'),
            'placeholder' => "give %player% diamond 1\ntp %player% 0 100 0",
            'description' => __('Commands specific to this variation. Leave empty to use parent product commands.', 'minewebstore'),
            'desc_tip' => true,
            'value' => get_post_meta($variation->ID, '_mc_commands', true),
            'rows' => 3,
            'wrapper_class' => 'form-row form-row-full'
        ));
        
        echo '</div>';
    }
    
    public function save_variation_fields($variation_id, $index) {
        // Verify nonce for security - WooCommerce uses this nonce for variation saves
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        // Check if this is a variation save (WooCommerce sets this)
        if (!isset($_POST['variable_post_id'])) {
            return;
        }

        if (isset($_POST['_mc_server_id'][$variation_id])) {
            update_post_meta($variation_id, '_mc_server_id', sanitize_text_field(wp_unslash($_POST['_mc_server_id'][$variation_id])));
        }
        
        if (isset($_POST['_mc_commands'][$variation_id])) {
            update_post_meta($variation_id, '_mc_commands', sanitize_textarea_field(wp_unslash($_POST['_mc_commands'][$variation_id])));
        }
    }
    
    /**
     * Get Minecraft configuration for a product or variation
     */
    public static function get_minecraft_config($product_id, $variation_id = null) {
        $config = array(
            'server_id' => '',
            'commands' => '',
            'commands_run_modes' => '',
            'command_delay' => 0
        );
        
        // Get variation-specific settings first
        if ($variation_id) {
            $var_server_id = get_post_meta($variation_id, '_mc_server_id', true);
            $var_commands = get_post_meta($variation_id, '_mc_commands', true);
            
            if ($var_server_id) {
                $config['server_id'] = $var_server_id;
            }
            if ($var_commands) {
                $config['commands'] = $var_commands;
                // For variations, use default 'online' run mode
                $commands_count = count(array_filter(explode("\n", $var_commands)));
                $config['commands_run_modes'] = implode("\n", array_fill(0, $commands_count, 'online'));
            }
        }
        
        // Fall back to product settings if variation doesn't override
        if (!$config['server_id']) {
            $config['server_id'] = get_post_meta($product_id, '_mc_server_id', true);
        }
        if (!$config['commands']) {
            $config['commands'] = get_post_meta($product_id, '_mc_commands', true);
            $config['commands_run_modes'] = get_post_meta($product_id, '_mc_commands_run_modes', true);
        }
        
        // Always get these from parent product
        $config['command_delay'] = floatval(get_post_meta($product_id, '_mc_command_delay', true) ?: 0);
        
        return $config;
    }
}
