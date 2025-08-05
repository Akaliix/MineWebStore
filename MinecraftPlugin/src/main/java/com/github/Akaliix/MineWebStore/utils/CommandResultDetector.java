package com.github.Akaliix.MineWebStore.utils;

import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;

/**
 * Utility to capture and analyze command execution results
 */
public class CommandResultDetector {
    
    private final MineWebStorePlugin plugin;
    
    public CommandResultDetector(MineWebStorePlugin plugin) {
        this.plugin = plugin;
    }
    
    /**
     * Execute a command with result detection
     */
    public CommandResult executeCommand(String command) {
        return executeCommand(command, null);
    }
    
    /**
     * Execute a command with result detection and player online check
     */
    public CommandResult executeCommand(String command, String playerName) {
        boolean bukkitResult = false;
        Exception commandException = null;
        
        // Check if player is online before command execution (for player-specific commands)
        if (playerName != null && !playerName.isEmpty()) {
            Player player = Bukkit.getPlayer(playerName);
            if (player == null || !player.isOnline()) {
                return new CommandResult(false, "Player '" + playerName + "' is not online - command not executed");
            }
        }
        
        try {
            // Execute the command
            bukkitResult = Bukkit.dispatchCommand(Bukkit.getConsoleSender(), command);
            
        } catch (Exception e) {
            commandException = e;
            plugin.debug("Command execution exception: " + e.getMessage());
        }
        
        // If there was an exception, it's definitely a failure
        if (commandException != null) {
            return new CommandResult(false, "Command execution failed: " + commandException.getMessage());
        }
        
        // Check if player is still online after command execution (for player-specific commands)
        if (playerName != null && !playerName.isEmpty()) {
            Player player = Bukkit.getPlayer(playerName);
            if (player == null || !player.isOnline()) {
                return new CommandResult(false, "Player '" + playerName + "' is no longer online - command may not have completed due to player leaving");
            }
        }
        
        // Return result based on Bukkit's response
        if (bukkitResult) {
            return new CommandResult(true, "Command executed successfully");
        } else {
            return new CommandResult(false, "Command execution returned false - likely failed");
        }
    }
    
    /**
     * Result of command execution
     */
    public static class CommandResult {
        private final boolean success;
        private final String message;
        
        public CommandResult(boolean success, String message) {
            this.success = success;
            this.message = message;
        }
        
        public boolean isSuccess() {
            return success;
        }
        
        public String getMessage() {
            return message;
        }
    }
}