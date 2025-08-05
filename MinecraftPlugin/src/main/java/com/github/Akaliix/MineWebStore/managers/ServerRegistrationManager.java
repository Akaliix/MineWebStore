package com.github.Akaliix.MineWebStore.managers;

import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import com.github.Akaliix.MineWebStore.api.WordPressAPI;

public class ServerRegistrationManager {
    
    private final WordPressAPI wordPressAPI;
    private final String serverName;
    private final MineWebStorePlugin plugin;
    private boolean isRegistered = false;
    
    public ServerRegistrationManager(WordPressAPI wordPressAPI, String serverName, MineWebStorePlugin plugin) {
        this.wordPressAPI = wordPressAPI;
        this.serverName = serverName;
        this.plugin = plugin;
    }
    
    public boolean registerServer() {
        try {
            boolean success = wordPressAPI.registerServer(serverName);
            
            if (success) {
                isRegistered = true;
                plugin.getLogger().info("Server '" + serverName + "' registered successfully with WordPress!");
                return true;
            } else {
                plugin.getLogger().warning("Failed to register server '" + serverName + "' with WordPress!");
                return false;
            }
        } catch (Exception e) {
            plugin.getLogger().severe("Error during server registration: " + e.getMessage());
            return false;
        }
    }
    
    public boolean isRegistered() {
        return isRegistered;
    }
    
    public String getServerName() {
        return serverName;
    }
}
