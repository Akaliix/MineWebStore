package com.github.Akaliix.MineWebStore.managers;

import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;
import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import org.bukkit.entity.Player;

import java.io.*;
import java.lang.reflect.Type;
import java.security.MessageDigest;
import java.util.*;
import java.util.concurrent.ConcurrentHashMap;
import java.util.logging.Level;

/**
 * Manages the history of all players who have ever joined the server
 * Stores UUID and name pairs for persistent tracking
 */
public class PlayerHistoryManager {
    
    private final MineWebStorePlugin plugin;
    private final File dataFile;
    private final ConcurrentHashMap<String, String> playerHistory; // UUID -> Name
    private final Gson gson;
    private String lastPlayerHash = null;
    
    public PlayerHistoryManager(MineWebStorePlugin plugin) {
        this.plugin = plugin;
        this.gson = new Gson();
        this.playerHistory = new ConcurrentHashMap<>();
        
        // Create data directory if it doesn't exist
        File dataFolder = plugin.getDataFolder();
        if (!dataFolder.exists()) {
            dataFolder.mkdirs();
        }
        
        this.dataFile = new File(dataFolder, "player_history.json");
        loadPlayerHistory();
    }
    
    /**
     * Load player history from file
     */
    private void loadPlayerHistory() {
        if (!dataFile.exists()) {
            plugin.debug("Player history file doesn't exist, starting with empty history");
            return;
        }
        
        try (FileReader reader = new FileReader(dataFile)) {
            Type type = new TypeToken<Map<String, String>>(){}.getType();
            Map<String, String> loadedData = gson.fromJson(reader, type);
            
            if (loadedData != null) {
                playerHistory.putAll(loadedData);
                plugin.debug("Loaded " + playerHistory.size() + " players from history file");
            }
        } catch (Exception e) {
            plugin.getLogger().log(Level.WARNING, "Error loading player history: ", e);
        }
    }
    
    /**
     * Save player history to file
     */
    private void savePlayerHistory() {
        try (FileWriter writer = new FileWriter(dataFile)) {
            gson.toJson(playerHistory, writer);
            plugin.debug("Saved " + playerHistory.size() + " players to history file");
        } catch (Exception e) {
            plugin.getLogger().log(Level.SEVERE, "Error saving player history: ", e);
        }
    }
    
    /**
     * Add a player to the history if they're new
     * @param player The player who joined
     * @return true if this is a new player, false if they've joined before
     */
    public boolean addPlayerIfNew(Player player) {
        String uuid = player.getUniqueId().toString();
        String name = player.getName();
        
        boolean isNewPlayer = !playerHistory.containsKey(uuid);
        
        if (isNewPlayer) {
            playerHistory.put(uuid, name);
            savePlayerHistory();
            plugin.debug("New player added to history: " + name + " (" + uuid + ")");
        } else {
            // Update name if it changed (unlikely but possible)
            String oldName = playerHistory.get(uuid);
            if (!oldName.equals(name)) {
                playerHistory.put(uuid, name);
                savePlayerHistory();
                plugin.debug("Updated player name in history: " + oldName + " -> " + name + " (" + uuid + ")");
            }
        }
        
        return isNewPlayer;
    }
    
    /**
     * Check if a player has ever joined the server
     * @param playerName The player name to check
     * @return true if the player has joined before
     */
    public boolean hasPlayerJoined(String playerName) {
        return playerHistory.containsValue(playerName);
    }
    
    /**
     * Check if a player UUID has ever joined the server
     * @param uuid The player UUID to check
     * @return true if the player has joined before
     */
    public boolean hasPlayerJoined(UUID uuid) {
        return playerHistory.containsKey(uuid.toString());
    }
    
    /**
     * Get all player names that have ever joined
     * @return List of all player names
     */
    public List<String> getAllPlayerNames() {
        return new ArrayList<>(playerHistory.values());
    }
    
    /**
     * Get all player UUIDs that have ever joined
     * @return Set of all player UUIDs as strings
     */
    public Set<String> getAllPlayerUUIDs() {
        return new HashSet<>(playerHistory.keySet());
    }
    
    /**
     * Get the total number of unique players who have ever joined
     * @return Total player count
     */
    public int getTotalPlayerCount() {
        return playerHistory.size();
    }
    
    /**
     * Calculate hash of all players for sync verification
     * @return SHA-256 hash of all player data
     */
    public String calculatePlayerHash() {
        try {
            // Create a sorted list of UUID:Name pairs for consistent hashing
            List<String> sortedEntries = new ArrayList<>();
            for (Map.Entry<String, String> entry : playerHistory.entrySet()) {
                sortedEntries.add(entry.getKey() + ":" + entry.getValue());
            }
            Collections.sort(sortedEntries);
            
            String combined = String.join(",", sortedEntries);
            
            MessageDigest md = MessageDigest.getInstance("SHA-256");
            byte[] hash = md.digest(combined.getBytes());
            
            StringBuilder hexString = new StringBuilder();
            for (byte b : hash) {
                String hex = Integer.toHexString(0xff & b);
                if (hex.length() == 1) {
                    hexString.append('0');
                }
                hexString.append(hex);
            }
            
            return hexString.toString();
        } catch (Exception e) {
            plugin.getLogger().log(Level.WARNING, "Error calculating player hash: ", e);
            return String.valueOf(playerHistory.hashCode());
        }
    }
    
    /**
     * Get the last calculated hash
     * @return Last hash value
     */
    public String getLastPlayerHash() {
        return lastPlayerHash;
    }
    
    /**
     * Set the last calculated hash
     * @param hash The hash value
     */
    public void setLastPlayerHash(String hash) {
        this.lastPlayerHash = hash;
    }
    
    /**
     * Get player name by UUID
     * @param uuid The player UUID
     * @return Player name or null if not found
     */
    public String getPlayerName(String uuid) {
        return playerHistory.get(uuid);
    }
    
    /**
     * Get all player data as a map
     * @return Map of UUID -> Name
     */
    public Map<String, String> getAllPlayerData() {
        return new HashMap<>(playerHistory);
    }
}
