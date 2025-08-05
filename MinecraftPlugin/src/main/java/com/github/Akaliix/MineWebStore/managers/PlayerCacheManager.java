package com.github.Akaliix.MineWebStore.managers;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.reflect.TypeToken;
import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import com.github.Akaliix.MineWebStore.api.WordPressAPI;
import com.github.Akaliix.MineWebStore.models.PendingCommand;
import com.github.Akaliix.MineWebStore.utils.CommandResultDetector;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.scheduler.BukkitRunnable;

import java.io.File;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.lang.reflect.Type;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.logging.Level;

public class PlayerCacheManager {
    
    private final WordPressAPI wordPressAPI;
    private final String serverName;
    private final MineWebStorePlugin plugin;
    private final PlayerHistoryManager playerHistoryManager;
    private final CommandResultDetector commandDetector;
    private final Map<String, List<PendingCommand>> queuedCommands;
    private final File queuedCommandsFile;
    private final Gson gson;
    
    private String lastPlayerHash = null;
    private List<String> onlinePlayersList = new ArrayList<>();
    
    public PlayerCacheManager(WordPressAPI wordPressAPI, String serverName, MineWebStorePlugin plugin, PlayerHistoryManager playerHistoryManager) {
        this.wordPressAPI = wordPressAPI;
        this.serverName = serverName;
        this.plugin = plugin;
        this.playerHistoryManager = playerHistoryManager;
        this.commandDetector = new CommandResultDetector(plugin);
        this.queuedCommands = new HashMap<>();
        this.queuedCommandsFile = new File(plugin.getDataFolder(), "queued_commands.json");
        this.gson = new GsonBuilder().setPrettyPrinting().create();
        
        // Load queued commands from file on startup
        loadQueuedCommandsFromFile();
    }
    
    public void syncPlayerHistoryToWordPress() {
        new BukkitRunnable() {
            @Override
            public void run() {
                performPlayerHistorySync();
            }
        }.runTaskAsynchronously(plugin);
    }
    
    private void performPlayerHistorySync() {
        try {
            List<String> allPlayerNames = playerHistoryManager.getAllPlayerNames();
            String currentHash = playerHistoryManager.calculatePlayerHash();
            
            if (shouldSyncPlayerHistory(currentHash)) {
                syncPlayerListToWordPress(allPlayerNames, currentHash);
            }
        } catch (Exception e) {
            plugin.getLogger().log(Level.SEVERE, "Error syncing player history: ", e);
        }
    }
    
    private boolean shouldSyncPlayerHistory(String currentHash) {
        return lastPlayerHash == null || !lastPlayerHash.equals(currentHash);
    }
    
    private void syncPlayerListToWordPress(List<String> allPlayerNames, String currentHash) {
        boolean success = wordPressAPI.syncPlayerList(serverName, allPlayerNames, lastPlayerHash);
        
        if (success) {
            updatePlayerHashAfterSync(currentHash, allPlayerNames.size());
        } else {
            plugin.getLogger().warning("Failed to sync player history with WordPress!");
        }
    }
    
    private void updatePlayerHashAfterSync(String currentHash, int playerCount) {
        lastPlayerHash = currentHash;
        playerHistoryManager.setLastPlayerHash(currentHash);
        plugin.debug("Player history synced to WordPress successfully (" + playerCount + " total players ever joined)");
    }
    
    public void updateOnlinePlayersList() {
        List<String> currentOnlinePlayers = getCurrentOnlinePlayers();
        onlinePlayersList = currentOnlinePlayers;
        plugin.debug("Updated online players list: " + onlinePlayersList.size() + " players online");
    }
    
    private List<String> getCurrentOnlinePlayers() {
        List<String> currentOnlinePlayers = new ArrayList<>();
        for (Player player : plugin.getServer().getOnlinePlayers()) {
            currentOnlinePlayers.add(player.getName());
        }
        return currentOnlinePlayers;
    }
    
    public void onNewPlayerJoin(Player player) {
        plugin.debug("New player joined: " + player.getName() + " - syncing player history to WordPress");
        syncPlayerHistoryToWordPress();
        executeQueuedCommandsForPlayer(player.getName());
    }
    
    /**
     * Queue a command for a player to be executed when they join the server
     * Commands are immediately saved to persistent storage to prevent data loss
     * @param command The command to queue for the player
     */
    public void queueCommandForPlayer(PendingCommand command) {
        String playerName = command.getPlayerName();
        queuedCommands.computeIfAbsent(playerName, k -> new ArrayList<>()).add(command);
        
        int queueSize = queuedCommands.get(playerName).size();
        plugin.getLogger().info("Cached command for offline player " + playerName + ": " + command.getCommand() + " (total queued: " + queueSize + ")");
        
        // Save to file immediately to prevent data loss
        saveQueuedCommandsToFile();
    }
    
    /**
     * Execute all queued commands for a specific player (case-insensitive lookup)
     * This method is called when a player joins the server
     */
    public void executeQueuedCommandsForPlayer(String playerName) {
        // Find commands using case-insensitive lookup to handle username case variations
        String actualKey = findPlayerKeyIgnoreCase(playerName);
        List<PendingCommand> commandsForPlayer = actualKey != null ? queuedCommands.get(actualKey) : null;
        
        if (hasNoQueuedCommands(commandsForPlayer)) {
            return; // No commands to execute
        }
        
        plugin.getLogger().info("Executing " + commandsForPlayer.size() + " queued commands for player " + playerName);
        
        // Execute all commands for this player
        for (PendingCommand command : commandsForPlayer) {
            plugin.getLogger().info("Executing command: " + command.getCommand());
            executeQueuedCommand(command, playerName);
        }
        
        // Remove commands from queue and save to persistent storage
        clearPlayerCommandsAndSave(actualKey);
    }
    
    /**
     * Clear all commands for a player and save to file
     */
    private void clearPlayerCommandsAndSave(String playerKey) {
        if (playerKey != null) {
            queuedCommands.remove(playerKey);
            saveQueuedCommandsToFile();
        }
    }
    
    private boolean hasNoQueuedCommands(List<PendingCommand> commandsForPlayer) {
        return commandsForPlayer == null || commandsForPlayer.isEmpty();
    }
    
    /**
     * Find the actual key in the queuedCommands map that matches the player name (case-insensitive)
     * This handles cases where the stored username case differs from the joining player's username case
     * @param playerName The player name to search for
     * @return The actual key found in the map, or null if no match
     */
    private String findPlayerKeyIgnoreCase(String playerName) {
        for (String key : queuedCommands.keySet()) {
            if (key.equalsIgnoreCase(playerName)) {
                return key;
            }
        }
        return null;
    }
    
    private void executeQueuedCommand(PendingCommand command, String playerName) {
        new BukkitRunnable() {
            @Override
            public void run() {
                QueuedCommandResult result = performQueuedCommandExecution(command, playerName);
                if (result != null) {
                    updateCommandStatus(command.getId(), result.isSuccess(), result.getMessage());
                }
            }
        }.runTask(plugin);
    }
    
    private QueuedCommandResult performQueuedCommandExecution(PendingCommand command, String playerName) {
        try {
            if (!isPlayerStillOnline(playerName)) {
                plugin.debug("Player " + playerName + " went offline before executing queued command: " + command.getCommand());
                return null; // Skip status update if player went offline
            }
            
            return executeQueuedCommandWithDetector(command, playerName);
            
        } catch (Exception e) {
            String errorMessage = "Queued command execution failed: " + e.getMessage();
            plugin.debug("Queued command execution failed: " + command.getCommand() + " - " + e.getMessage());
            return new QueuedCommandResult(false, errorMessage);
        }
    }
    
    private boolean isPlayerStillOnline(String playerName) {
        Player player = Bukkit.getPlayerExact(playerName);
        return player != null && player.isOnline();
    }
    
    private QueuedCommandResult executeQueuedCommandWithDetector(PendingCommand command, String playerName) {
        plugin.debug("Executing queued command for player " + playerName + ": " + command.getCommand());
        
        // For queued commands, always check player status since they are "online" commands
        CommandResultDetector.CommandResult result = commandDetector.executeCommand(command.getCommand(), playerName);
        
        boolean success = result.isSuccess();
        String message = result.getMessage();
        
        logQueuedCommandResult(command.getCommand(), success, message);
        
        return new QueuedCommandResult(success, message);
    }
    
    private void logQueuedCommandResult(String commandText, boolean success, String message) {
        if (success) {
            plugin.debug("Queued command executed successfully: " + commandText);
        } else {
            plugin.debug("Queued command execution failed: " + commandText + " - " + message);
        }
    }
    
    /**
     * Load queued commands from persistent storage on server startup
     */
    private void loadQueuedCommandsFromFile() {
        if (!queuedCommandsFile.exists()) {
            plugin.debug("No queued commands file found - starting with empty queue");
            return;
        }
        
        try (FileReader reader = new FileReader(queuedCommandsFile)) {
            Type type = new TypeToken<Map<String, List<PendingCommand>>>(){}.getType();
            Map<String, List<PendingCommand>> loadedCommands = gson.fromJson(reader, type);
            
            if (loadedCommands != null) {
                queuedCommands.clear();
                queuedCommands.putAll(loadedCommands);
                
                int totalCommands = loadedCommands.values().stream()
                    .mapToInt(List::size)
                    .sum();
                
                plugin.getLogger().info("Loaded " + totalCommands + " queued commands for " + 
                    loadedCommands.size() + " players from persistent storage");
            }
            
        } catch (IOException e) {
            plugin.getLogger().warning("Failed to load queued commands from file: " + e.getMessage());
        } catch (Exception e) {
            plugin.getLogger().warning("Error parsing queued commands file: " + e.getMessage());
            // Backup corrupted file
            backupCorruptedFile();
        }
    }
    
    /**
     * Save queued commands to persistent storage
     */
    private void saveQueuedCommandsToFile() {
        try {
            // Ensure the plugin data folder exists
            if (!plugin.getDataFolder().exists()) {
                plugin.getDataFolder().mkdirs();
            }
            
            try (FileWriter writer = new FileWriter(queuedCommandsFile)) {
                gson.toJson(queuedCommands, writer);
                plugin.debug("Saved queued commands to persistent storage");
            }
            
        } catch (IOException e) {
            plugin.getLogger().warning("Failed to save queued commands to file: " + e.getMessage());
        }
    }
    
    /**
     * Backup corrupted file for debugging
     */
    private void backupCorruptedFile() {
        try {
            File backupFile = new File(plugin.getDataFolder(), "queued_commands_corrupted_" + System.currentTimeMillis() + ".json");
            if (queuedCommandsFile.renameTo(backupFile)) {
                plugin.getLogger().warning("Corrupted queued commands file backed up to: " + backupFile.getName());
            }
        } catch (Exception e) {
            plugin.getLogger().warning("Failed to backup corrupted file: " + e.getMessage());
        }
    }
    
    /**
     * Get count of queued commands (for status reporting)
     */
    public int getQueuedCommandsCount() {
        return queuedCommands.values().stream()
            .mapToInt(List::size)
            .sum();
    }
    
    /**
     * Get queued commands for a specific player (for debugging)
     */
    public List<PendingCommand> getQueuedCommandsForPlayer(String playerName) {
        // Use case-insensitive lookup
        String actualKey = findPlayerKeyIgnoreCase(playerName);
        List<PendingCommand> commands = actualKey != null ? queuedCommands.get(actualKey) : null;
        return commands != null ? new ArrayList<>(commands) : new ArrayList<>();
    }
    
    /**
     * Cleanup method to save queued commands before server shutdown
     */
    public void shutdown() {
        saveQueuedCommandsToFile();
        plugin.getLogger().info("Saved queued commands before server shutdown");
    }
    
    private void updateCommandStatus(int commandId, boolean success, String message) {
        new BukkitRunnable() {
            @Override
            public void run() {
                performQueuedCommandStatusUpdate(commandId, success, message);
            }
        }.runTaskAsynchronously(plugin);
    }
    
    private void performQueuedCommandStatusUpdate(int commandId, boolean success, String message) {
        try {
            String status = success ? "executed" : "failed";
            String response = wordPressAPI.updateCommandStatus(serverName, commandId, status, message);
            
            logStatusUpdateResult(commandId, status, response);
            
        } catch (Exception e) {
            plugin.debug("Error updating queued command status: " + e.getMessage());
        }
    }
    
    private void logStatusUpdateResult(int commandId, String status, String response) {
        if (response != null) {
            plugin.debug("Updated queued command " + commandId + " status to " + status);
        } else {
            plugin.debug("No response when updating queued command status for command " + commandId);
        }
    }
    
    // Getters for accessing online players data
    public List<String> getOnlinePlayersList() {
        return new ArrayList<>(onlinePlayersList);
    }
    
    public boolean isPlayerOnline(String playerName) {
        return onlinePlayersList.contains(playerName);
    }
    
    public int getOnlinePlayerCount() {
        return onlinePlayersList.size();
    }
    
    /**
     * Result wrapper for queued command execution
     */
    private static class QueuedCommandResult {
        private final boolean success;
        private final String message;
        
        public QueuedCommandResult(boolean success, String message) {
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
