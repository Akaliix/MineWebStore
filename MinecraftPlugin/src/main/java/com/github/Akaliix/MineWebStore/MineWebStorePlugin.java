package com.github.Akaliix.MineWebStore;

import com.github.Akaliix.MineWebStore.api.WordPressAPI;
import com.github.Akaliix.MineWebStore.commands.MWSCommand;
import com.github.Akaliix.MineWebStore.listeners.PlayerListener;
import com.github.Akaliix.MineWebStore.managers.CommandManager;
import com.github.Akaliix.MineWebStore.managers.PlayerCacheManager;
import com.github.Akaliix.MineWebStore.managers.PlayerHistoryManager;
import com.github.Akaliix.MineWebStore.managers.ServerRegistrationManager;
import com.github.Akaliix.MineWebStore.utils.ConfigValidator;
import org.bukkit.plugin.java.JavaPlugin;
import org.bukkit.scheduler.BukkitRunnable;

import java.util.logging.Level;

public class MineWebStorePlugin extends JavaPlugin {
    
    private WordPressAPI wordPressAPI;
    private ServerRegistrationManager serverRegistrationManager;
    private PlayerCacheManager playerCacheManager;
    private PlayerHistoryManager playerHistoryManager;
    private CommandManager commandManager;
    private ConfigValidator configValidator;
    private boolean debugEnabled;
    
    @Override
    public void onEnable() {
        // Save default config
        saveDefaultConfig();
        
        // Initialize debug mode
        debugEnabled = getConfig().getBoolean("debug.enabled", false);
        
        // Validate configuration
        configValidator = new ConfigValidator(this);
        if (!configValidator.validateConfiguration()) {
            getLogger().severe("Plugin startup failed due to configuration errors!");
            configValidator.printConfigurationHelp();
            getServer().getPluginManager().disablePlugin(this);
            return;
        }
        
        // Initialize API clients
        initializeAPIs();
        
        // Initialize managers
        initializeManagers();
        
        // Register event listeners
        getServer().getPluginManager().registerEvents(new PlayerListener(playerCacheManager, playerHistoryManager), this);
        
        // Register commands
        getCommand("mws").setExecutor(new MWSCommand(this));
        
        // Start server registration
        registerServer();
        
        getLogger().info("MineWebStore plugin has been enabled!");
    }
    
    @Override
    public void onDisable() {
        // Save queued commands before shutdown
        if (playerCacheManager != null) {
            playerCacheManager.shutdown();
        }
        
        // Cancel all tasks
        getServer().getScheduler().cancelTasks(this);
        
        getLogger().info("MineWebStore plugin has been disabled!");
    }
    
    private void initializeAPIs() {
        String baseUrl = getConfig().getString("wordpress.base_url");
        String secretKey = getConfig().getString("wordpress.secret_key");
        
        wordPressAPI = new WordPressAPI(baseUrl, secretKey, debugEnabled, this);
    }
    
    private void initializeManagers() {
        String serverName = getConfig().getString("server.name");
        
        serverRegistrationManager = new ServerRegistrationManager(wordPressAPI, serverName, this);
        playerHistoryManager = new PlayerHistoryManager(this);
        playerCacheManager = new PlayerCacheManager(wordPressAPI, serverName, this, playerHistoryManager);
        commandManager = new CommandManager(wordPressAPI, serverName, this);
    }
    
    private void registerServer() {
        new BukkitRunnable() {
            @Override
            public void run() {
                if (serverRegistrationManager.registerServer()) {
                    debug("Server registered successfully!");
                    // Start command polling only after successful registration
                    startCommandPollingTask();
                } else {
                    getLogger().warning("Failed to register server with WordPress!");
                    getLogger().warning("Command polling will not start until server registration succeeds.");
                    // Retry registration after 30 seconds
                    new BukkitRunnable() {
                        @Override
                        public void run() {
                            registerServer();
                        }
                    }.runTaskLaterAsynchronously(MineWebStorePlugin.this, 30 * 20L);
                }
            }
        }.runTaskAsynchronously(this);
    }
    
    private void startCommandPollingTask() {
        int pollInterval = getConfig().getInt("server.poll_interval", 10);
        
        new BukkitRunnable() {
            @Override
            public void run() {
                try {
                    commandManager.processCommands();
                } catch (Exception e) {
                    getLogger().log(Level.SEVERE, "Error processing commands: ", e);
                }
            }
        }.runTaskTimerAsynchronously(this, 0L, pollInterval * 20L); // Convert seconds to ticks
    }
    
    public void reloadPluginConfig() {
        reloadConfig();
        debugEnabled = getConfig().getBoolean("debug.enabled", false);
        
        // Reinitialize APIs and managers
        initializeAPIs();
        initializeManagers();
        
        getLogger().info("Configuration reloaded!");
    }
    
    public void debug(String message) {
        if (debugEnabled) {
            getLogger().info("[DEBUG] " + message);
        }
    }
    
    // Getters
    public WordPressAPI getWordPressAPI() { return wordPressAPI; }
    public ServerRegistrationManager getServerRegistrationManager() { return serverRegistrationManager; }
    public PlayerCacheManager getPlayerCacheManager() { return playerCacheManager; }
    public PlayerHistoryManager getPlayerHistoryManager() { return playerHistoryManager; }
    public CommandManager getCommandManager() { return commandManager; }
    public boolean isDebugEnabled() { return debugEnabled; }
}
