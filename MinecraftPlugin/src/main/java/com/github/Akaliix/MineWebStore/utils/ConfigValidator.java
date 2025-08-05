package com.github.Akaliix.MineWebStore.utils;

import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import org.bukkit.configuration.file.FileConfiguration;

import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;

public class ConfigValidator {
    
    private final MineWebStorePlugin plugin;
    private final FileConfiguration config;
    
    public ConfigValidator(MineWebStorePlugin plugin) {
        this.plugin = plugin;
        this.config = plugin.getConfig();
    }
    
    public boolean validateConfiguration() {
        boolean isValid = true;
        
        plugin.getLogger().info("=== Configuration Validation ===");
        
        // Check WordPress settings
        String wpBaseUrl = config.getString("wordpress.base_url", "");
        String wpSecretKey = config.getString("wordpress.secret_key", "");
        
        if (wpBaseUrl.isEmpty() || wpBaseUrl.equals("https://yourdomain.com")) {
            plugin.getLogger().severe("❌ WordPress base_url not configured!");
            isValid = false;
        } else {
            plugin.getLogger().info("✅ WordPress URL: " + wpBaseUrl);
        }
        
        if (wpSecretKey.isEmpty() || wpSecretKey.equals("your-secret-key-here")) {
            plugin.getLogger().severe("❌ WordPress secret_key not configured!");
            isValid = false;
        } else {
            plugin.getLogger().info("✅ WordPress secret key configured");
        }
        
        // Check server settings
        String serverName = config.getString("server.name", "");
        if (serverName.isEmpty()) {
            plugin.getLogger().severe("❌ Server name not configured!");
            isValid = false;
        } else {
            plugin.getLogger().info("✅ Server name: " + serverName);
        }
        
        // Test connectivity if basic config is valid
        if (isValid) {
            testConnectivity(wpBaseUrl);
        }
        
        plugin.getLogger().info("=== End Configuration Validation ===");
        
        if (!isValid) {
            plugin.getLogger().severe("Configuration validation failed! Please check your config.yml file.");
            plugin.getLogger().severe("See TROUBLESHOOTING_SCRIPT.md for detailed setup instructions.");
        }
        
        return isValid;
    }
    
    private void testConnectivity(String baseUrl) {
        plugin.getLogger().info("=== Connectivity Tests ===");
        
        // Test WordPress registration endpoint
        testWordPressEndpoint(baseUrl);
    }
    
    private void testWordPressEndpoint(String baseUrl) {
        try {
            String endpoint = baseUrl + "/wp-json/mcapi/v1/status";
            URL url = URI.create(endpoint).toURL();
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            connection.setRequestMethod("GET");
            connection.setConnectTimeout(5000);
            connection.setReadTimeout(5000);

            int responseCode = connection.getResponseCode();

            if (responseCode == 200) {
                plugin.getLogger().info("✅ WordPress MineWebStore plugin is active and working");
            } else if (responseCode == 404) {
                plugin.getLogger().warning("⚠️ WordPress MineWebStore plugin not found - install the plugin from WordpressPlugin/ folder");
            } else {
                plugin.getLogger().info("ℹ️ WordPress plugin endpoint responded with code: " + responseCode);
            }

        } catch (Exception e) {
            plugin.getLogger().warning("❌ Cannot connect to WordPress plugin: " + e.getMessage());
        }
    }
    
    public void printConfigurationHelp() {
        plugin.getLogger().info("=== Configuration Help ===");
        plugin.getLogger().info("1. WordPress Setup:");
        plugin.getLogger().info("   - Install the WordPress plugin from WordpressPlugin/ folder");
        plugin.getLogger().info("   - Go to WordPress Admin > Minecraft");
        plugin.getLogger().info("   - Copy the Secret Key to config.yml");
        plugin.getLogger().info("");
        plugin.getLogger().info("2. Server Configuration:");
        plugin.getLogger().info("   - Set a unique server name in config.yml");
        plugin.getLogger().info("   - Commands are now managed automatically by WordPress");
        plugin.getLogger().info("");
        plugin.getLogger().info("3. For detailed troubleshooting, see:");
        plugin.getLogger().info("   - NEW_ARCHITECTURE.md for the updated system overview");
        plugin.getLogger().info("   - Check WordPress Admin > Minecraft > Command Management");
        plugin.getLogger().info("=== End Configuration Help ===");
    }
}
