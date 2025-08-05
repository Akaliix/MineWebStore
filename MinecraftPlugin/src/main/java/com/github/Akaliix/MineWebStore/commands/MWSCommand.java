package com.github.Akaliix.MineWebStore.commands;

import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import com.github.Akaliix.MineWebStore.models.PendingCommand;
import org.bukkit.command.Command;
import org.bukkit.command.CommandExecutor;
import org.bukkit.command.CommandSender;
import net.kyori.adventure.text.Component;
import net.kyori.adventure.text.format.NamedTextColor;

import java.util.List;
import java.util.Map;
import java.util.stream.Collectors;

public class MWSCommand implements CommandExecutor {
    
    private final MineWebStorePlugin plugin;
    
    public MWSCommand(MineWebStorePlugin plugin) {
        this.plugin = plugin;
    }
    
    @Override
    public boolean onCommand(CommandSender sender, Command command, String label, String[] args) {
        if (!sender.hasPermission("mws.admin")) {
            sender.sendMessage(Component.text("You don't have permission to use this command!").color(NamedTextColor.RED));
            return true;
        }
        
        if (args.length == 0) {
            sendHelpMessage(sender);
            return true;
        }
        
        switch (args[0].toLowerCase()) {
            case "reload":
                handleReload(sender);
                break;
            case "status":
                handleStatus(sender);
                break;
            case "test":
                handleTest(sender);
                break;
            case "pending":
                handlePending(sender, args);
                break;
            default:
                sendHelpMessage(sender);
                break;
        }
        
        return true;
    }
    
    private void sendHelpMessage(CommandSender sender) {
        sender.sendMessage(Component.text("=== MineWebStore Commands ===").color(NamedTextColor.GOLD));
        sender.sendMessage(Component.text("/mws reload").color(NamedTextColor.YELLOW)
            .append(Component.text(" - Reload the plugin configuration").color(NamedTextColor.WHITE)));
        sender.sendMessage(Component.text("/mws status").color(NamedTextColor.YELLOW)
            .append(Component.text(" - Show plugin status").color(NamedTextColor.WHITE)));
        sender.sendMessage(Component.text("/mws test").color(NamedTextColor.YELLOW)
            .append(Component.text(" - Test WordPress connection").color(NamedTextColor.WHITE)));
        sender.sendMessage(Component.text("/mws pending [player]").color(NamedTextColor.YELLOW)
            .append(Component.text(" - Show pending commands").color(NamedTextColor.WHITE)));
        sender.sendMessage(Component.text("/mws pending <player> execute").color(NamedTextColor.YELLOW)
            .append(Component.text(" - Execute queued commands").color(NamedTextColor.WHITE)));
    }
    
    private void handleReload(CommandSender sender) {
        sender.sendMessage(Component.text("Reloading MineWebStore configuration...").color(NamedTextColor.YELLOW));
        
        try {
            plugin.reloadPluginConfig();
            sender.sendMessage(Component.text("Configuration reloaded successfully!").color(NamedTextColor.GREEN));
        } catch (Exception e) {
            sender.sendMessage(Component.text("Error reloading configuration: " + e.getMessage()).color(NamedTextColor.RED));
            plugin.getLogger().severe("Error reloading configuration: " + e.getMessage());
        }
    }
    
    private void handleStatus(CommandSender sender) {
        sender.sendMessage(Component.text("=== MineWebStore Status ===").color(NamedTextColor.GOLD));
        
        // Server registration status
        boolean isRegistered = plugin.getServerRegistrationManager().isRegistered();
        sender.sendMessage(Component.text("Server Registration: ").color(NamedTextColor.YELLOW)
            .append(Component.text(isRegistered ? "✓ Registered" : "✗ Not Registered")
                .color(isRegistered ? NamedTextColor.GREEN : NamedTextColor.RED)));
        
        // Server name
        String serverName = plugin.getServerRegistrationManager().getServerName();
        sender.sendMessage(Component.text("Server Name: ").color(NamedTextColor.YELLOW)
            .append(Component.text(serverName).color(NamedTextColor.WHITE)));
        
        // WordPress API key status
        String apiKey = plugin.getWordPressAPI().getServerSpecificKey();
        sender.sendMessage(Component.text("WordPress API Key: ").color(NamedTextColor.YELLOW)
            .append(Component.text(apiKey != null ? "✓ Available" : "✗ Missing")
                .color(apiKey != null ? NamedTextColor.GREEN : NamedTextColor.RED)));
        
        // Debug mode
        boolean debugEnabled = plugin.isDebugEnabled();
        sender.sendMessage(Component.text("Debug Mode: ").color(NamedTextColor.YELLOW)
            .append(Component.text(debugEnabled ? "Enabled" : "Disabled")
                .color(debugEnabled ? NamedTextColor.GREEN : NamedTextColor.GRAY)));
        
        // Online players count
        int playerCount = plugin.getServer().getOnlinePlayers().size();
        sender.sendMessage(Component.text("Online Players: ").color(NamedTextColor.YELLOW)
            .append(Component.text(String.valueOf(playerCount)).color(NamedTextColor.WHITE)));
        
        // Processing commands count (from CommandManager)
        int processingCount = plugin.getCommandManager().getProcessingCommandsCount();
        sender.sendMessage(Component.text("Processing Commands: ").color(NamedTextColor.YELLOW)
            .append(Component.text(String.valueOf(processingCount)).color(NamedTextColor.WHITE)));
        
        // Queued commands count (from PlayerCacheManager)
        int queuedCount = plugin.getPlayerCacheManager().getQueuedCommandsCount();
        sender.sendMessage(Component.text("Queued Commands: ").color(NamedTextColor.YELLOW)
            .append(Component.text(String.valueOf(queuedCount)).color(NamedTextColor.WHITE)));
    }
    
    private void handleTest(CommandSender sender) {
        sender.sendMessage(Component.text("Testing WordPress connection...").color(NamedTextColor.YELLOW));
        
        // This will be implemented as an async task
        plugin.getServer().getScheduler().runTaskAsynchronously(plugin, () -> {
            try {
                // Try to sync current player history as a test
                plugin.getPlayerCacheManager().syncPlayerHistoryToWordPress();
                
                sender.sendMessage(Component.text("WordPress connection test completed! Check console for details.").color(NamedTextColor.GREEN));
            } catch (Exception e) {
                sender.sendMessage(Component.text("WordPress connection test failed: " + e.getMessage()).color(NamedTextColor.RED));
                plugin.getLogger().severe("WordPress connection test failed: " + e.getMessage());
            }
        });
    }
    
    private void handlePending(CommandSender sender, String[] args) {
        if (args.length > 1) {
            // Show commands for specific player
            String playerName = args[1].toLowerCase();
            String originalPlayerName = args[1];
            
            sender.sendMessage(Component.text("=== Commands for " + originalPlayerName + " ===").color(NamedTextColor.GOLD));
            
            // Show processing commands (from CommandManager)
            List<PendingCommand> processingCommands = plugin.getCommandManager().getProcessingCommands();
            List<PendingCommand> playerProcessingCommands = processingCommands.stream()
                .filter(cmd -> cmd.getPlayerName().equalsIgnoreCase(playerName))
                .toList();
            
            sender.sendMessage(Component.text("Processing Commands: ").color(NamedTextColor.YELLOW)
                .append(Component.text(String.valueOf(playerProcessingCommands.size())).color(NamedTextColor.WHITE)));
                
            if (!playerProcessingCommands.isEmpty()) {
                for (int i = 0; i < Math.min(playerProcessingCommands.size(), 5); i++) {
                    PendingCommand cmd = playerProcessingCommands.get(i);
                    sender.sendMessage(Component.text("  P" + (i + 1) + ". ").color(NamedTextColor.BLUE)
                        .append(Component.text(cmd.getCommand()).color(NamedTextColor.WHITE)));
                }
            }
            
            // Show queued commands (from PlayerCacheManager)
            List<PendingCommand> queuedCommands = plugin.getPlayerCacheManager().getQueuedCommandsForPlayer(originalPlayerName);
            sender.sendMessage(Component.text("Queued Commands: ").color(NamedTextColor.YELLOW)
                .append(Component.text(String.valueOf(queuedCommands.size())).color(NamedTextColor.WHITE)));
                
            if (!queuedCommands.isEmpty()) {
                for (int i = 0; i < Math.min(queuedCommands.size(), 5); i++) {
                    PendingCommand cmd = queuedCommands.get(i);
                    sender.sendMessage(Component.text("  Q" + (i + 1) + ". ").color(NamedTextColor.GREEN)
                        .append(Component.text(cmd.getCommand()).color(NamedTextColor.WHITE)));
                }
                
                // Add option to manually execute queued commands
                sender.sendMessage(Component.text("To execute queued commands, run: ").color(NamedTextColor.GRAY)
                    .append(Component.text("/mws pending " + originalPlayerName + " execute").color(NamedTextColor.YELLOW)));
            }
            
            // Check for execute parameter
            if (args.length > 2 && args[2].equalsIgnoreCase("execute")) {
                if (!queuedCommands.isEmpty()) {
                    sender.sendMessage(Component.text("Executing " + queuedCommands.size() + " queued commands for " + originalPlayerName + "...")
                        .color(NamedTextColor.YELLOW));
                    plugin.getPlayerCacheManager().executeQueuedCommandsForPlayer(originalPlayerName);
                    sender.sendMessage(Component.text("Queued commands executed!").color(NamedTextColor.GREEN));
                } else {
                    sender.sendMessage(Component.text("No queued commands to execute for " + originalPlayerName).color(NamedTextColor.GRAY));
                }
            }
            
        } else {
            // Show summary of all commands
            List<PendingCommand> processingCommands = plugin.getCommandManager().getProcessingCommands();
            int queuedCount = plugin.getPlayerCacheManager().getQueuedCommandsCount();
                
            sender.sendMessage(Component.text("=== All Commands Summary ===").color(NamedTextColor.GOLD));
            
            sender.sendMessage(Component.text("Processing Commands: ").color(NamedTextColor.YELLOW)
                .append(Component.text(String.valueOf(processingCommands.size())).color(NamedTextColor.WHITE)));
                
            sender.sendMessage(Component.text("Queued Commands: ").color(NamedTextColor.YELLOW)
                .append(Component.text(String.valueOf(queuedCount)).color(NamedTextColor.WHITE)));
            
            if (!processingCommands.isEmpty()) {
                // Group processing commands by player
                Map<String, Long> commandsByPlayer = processingCommands.stream()
                    .collect(Collectors.groupingBy(PendingCommand::getPlayerName, Collectors.counting()));
                
                sender.sendMessage(Component.text("Processing by player:").color(NamedTextColor.YELLOW));
                for (Map.Entry<String, Long> entry : commandsByPlayer.entrySet()) {
                    String playerName = entry.getKey();
                    long count = entry.getValue();
                    
                    sender.sendMessage(Component.text("  " + playerName + ": ").color(NamedTextColor.GRAY)
                        .append(Component.text(count + " commands").color(NamedTextColor.WHITE)));
                }
            }
            
            sender.sendMessage(Component.text("Use '/mws pending <player>' to see specific player's commands").color(NamedTextColor.GRAY));
        }
    }
}
