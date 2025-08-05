package com.github.Akaliix.MineWebStore.listeners;

import com.github.Akaliix.MineWebStore.managers.PlayerCacheManager;
import com.github.Akaliix.MineWebStore.managers.PlayerHistoryManager;
import org.bukkit.entity.Player;
import org.bukkit.event.EventHandler;
import org.bukkit.event.Listener;
import org.bukkit.event.player.PlayerJoinEvent;
import org.bukkit.event.player.PlayerQuitEvent;

public class PlayerListener implements Listener {
    
    private final PlayerCacheManager playerCacheManager;
    private final PlayerHistoryManager playerHistoryManager;
    
    public PlayerListener(PlayerCacheManager playerCacheManager, PlayerHistoryManager playerHistoryManager) {
        this.playerCacheManager = playerCacheManager;
        this.playerHistoryManager = playerHistoryManager;
    }
    
    @EventHandler
    public void onPlayerJoin(PlayerJoinEvent event) {
        Player player = event.getPlayer();
        
        // Add player to history and check if they're new
        boolean isNewPlayer = playerHistoryManager.addPlayerIfNew(player);
        
        if (isNewPlayer) {
            // Only sync WordPress for new players
            playerCacheManager.onNewPlayerJoin(player);
        } else {
            // For existing players, just execute any queued commands
            playerCacheManager.executeQueuedCommandsForPlayer(player.getName());
        }
        
        // Always update online players list (for Java-side use)
        playerCacheManager.updateOnlinePlayersList();
    }
    
    @EventHandler
    public void onPlayerQuit(PlayerQuitEvent event) {
        // Update online players list when someone leaves
        playerCacheManager.updateOnlinePlayersList();
    }
}
