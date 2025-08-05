package com.github.Akaliix.MineWebStore.managers;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import com.github.Akaliix.MineWebStore.api.WordPressAPI;
import com.github.Akaliix.MineWebStore.models.PendingCommand;
import com.github.Akaliix.MineWebStore.utils.CommandResultDetector;
import org.bukkit.Bukkit;
import org.bukkit.entity.Player;
import org.bukkit.scheduler.BukkitRunnable;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

public class CommandManager {
    
    private final WordPressAPI wordPressAPI;
    private final String serverName;
    private final MineWebStorePlugin plugin;
    private final Map<Integer, PendingCommand> processingCommands;
    private final CommandResultDetector commandDetector;
    
    public CommandManager(WordPressAPI wordPressAPI, String serverName, MineWebStorePlugin plugin) {
        this.wordPressAPI = wordPressAPI;
        this.serverName = serverName;
        this.plugin = plugin;
        this.processingCommands = new HashMap<>();
        this.commandDetector = new CommandResultDetector(plugin);
    }
    
    public void processCommands() {
        try {
            List<PendingCommand> commands = fetchPendingCommands();
            if (commands.isEmpty()) {
                plugin.debug("No pending commands found");
                return;
            }
            
            plugin.debug("Found " + commands.size() + " pending commands");
            
            // Mark commands as read and execute them
            if (markCommandsAsRead(commands)) {
                executeCommands(commands);
            }
            
        } catch (Exception e) {
            plugin.debug("Error processing commands: " + e.getMessage());
        }
    }
    
    private List<PendingCommand> fetchPendingCommands() {
        try {
            String response = wordPressAPI.getPendingCommands(serverName);
            if (response == null) {
                return new ArrayList<>();
            }
            
            JsonObject responseObj = JsonParser.parseString(response).getAsJsonObject();
            if (!responseObj.get("success").getAsBoolean()) {
                plugin.debug("Failed to get pending commands: " + response);
                return new ArrayList<>();
            }
            
            JsonArray commandsArray = responseObj.getAsJsonArray("commands");
            return parseCommands(commandsArray);
            
        } catch (Exception e) {
            plugin.debug("Error fetching pending commands: " + e.getMessage());
            return new ArrayList<>();
        }
    }
    
    private List<PendingCommand> parseCommands(JsonArray commandsArray) {
        List<PendingCommand> commands = new ArrayList<>();
        
        for (JsonElement element : commandsArray) {
            JsonObject commandObj = element.getAsJsonObject();
            
            String runMode = "online"; // default
            if (commandObj.has("run_mode")) {
                runMode = commandObj.get("run_mode").getAsString();
            }
            
            PendingCommand command = new PendingCommand(
                commandObj.get("id").getAsInt(),
                commandObj.get("order_id").getAsInt(),
                commandObj.get("product_id").getAsInt(),
                commandObj.get("player_name").getAsString(),
                commandObj.get("command").getAsString(),
                runMode,
                commandObj.get("created_at").getAsString()
            );
            
            commands.add(command);
        }
        
        return commands;
    }
    
    private boolean markCommandsAsRead(List<PendingCommand> commands) {
        try {
            List<Integer> commandIds = new ArrayList<>();
            for (PendingCommand command : commands) {
                commandIds.add(command.getId());
                processingCommands.put(command.getId(), command);
            }
            
            String response = wordPressAPI.markCommandsAsRead(serverName, commandIds);
            if (response == null) {
                return false;
            }
            
            JsonObject responseObj = JsonParser.parseString(response).getAsJsonObject();
            boolean success = responseObj.get("success").getAsBoolean();
            
            if (success) {
                plugin.debug("Marked " + commandIds.size() + " commands as read");
            }
            
            return success;
            
        } catch (Exception e) {
            plugin.debug("Error marking commands as read: " + e.getMessage());
            return false;
        }
    }
    
    private void executeCommands(List<PendingCommand> commands) {
        for (PendingCommand command : commands) {
            if (command.shouldRunAlways()) {
                executeCommand(command);
            } else if (command.shouldRunWhenPlayerOnline()) {
                handleOnlineCommand(command);
            }
        }
    }
    
    private void handleOnlineCommand(PendingCommand command) {
        Player player = Bukkit.getPlayerExact(command.getPlayerName());
        if (isPlayerOnline(player)) {
            executeCommand(command);
        } else {
            queueCommandForLater(command);
        }
    }
    
    private boolean isPlayerOnline(Player player) {
        return player != null && player.isOnline();
    }
    
    private void queueCommandForLater(PendingCommand command) {
        plugin.getPlayerCacheManager().queueCommandForPlayer(command);
        plugin.debug("Queued command for offline player " + command.getPlayerName() + ": " + command.getCommand());
    }
    
    private void executeCommand(PendingCommand command) {
        new BukkitRunnable() {
            @Override
            public void run() {
                CommandExecutionResult result = performCommandExecution(command);
                updateCommandStatus(command.getId(), result.isSuccess(), result.getMessage());
            }
        }.runTask(plugin);
    }
    
    private CommandExecutionResult performCommandExecution(PendingCommand command) {
        try {
            logCommandExecution(command);
            
            CommandResultDetector.CommandResult result = executeCommandWithDetector(command);
            
            boolean success = result.isSuccess();
            String message = result.getMessage();
            
            logExecutionResult(command, success, message);
            
            return new CommandExecutionResult(success, message);
            
        } catch (Exception e) {
            String errorMessage = "Command execution failed: " + e.getMessage();
            plugin.debug("Command execution failed: " + command.getCommand() + " - " + e.getMessage());
            return new CommandExecutionResult(false, errorMessage);
        }
    }
    
    private void logCommandExecution(PendingCommand command) {
        Player player = Bukkit.getPlayerExact(command.getPlayerName());
        boolean playerOnline = isPlayerOnline(player);
        
        plugin.debug("Executing " + command.getRunMode() + " command for player " + command.getPlayerName() + 
                   " (online: " + playerOnline + "): " + command.getCommand());
    }
    
    private CommandResultDetector.CommandResult executeCommandWithDetector(PendingCommand command) {
        String commandText = command.getCommand();
        
        if (command.shouldRunWhenPlayerOnline()) {
            return commandDetector.executeCommand(commandText, command.getPlayerName());
        } else {
            return commandDetector.executeCommand(commandText);
        }
    }
    
    private void logExecutionResult(PendingCommand command, boolean success, String message) {
        if (success) {
            plugin.debug("Command executed successfully: " + command.getCommand());
        } else {
            plugin.debug("Command execution failed: " + command.getCommand() + " - " + message);
        }
    }
    
    private void updateCommandStatus(int commandId, boolean success, String message) {
        new BukkitRunnable() {
            @Override
            public void run() {
                try {
                    performStatusUpdate(commandId, success, message);
                } catch (Exception e) {
                    plugin.debug("Error updating command status: " + e.getMessage());
                } finally {
                    processingCommands.remove(commandId);
                }
            }
        }.runTaskAsynchronously(plugin);
    }
    
    private void performStatusUpdate(int commandId, boolean success, String message) {
        String status = success ? "executed" : "failed";
        String response = wordPressAPI.updateCommandStatus(serverName, commandId, status, message);
        
        if (response != null) {
            handleStatusUpdateResponse(commandId, status, response);
        } else {
            plugin.debug("No response when updating command status for command " + commandId);
        }
    }
    
    private void handleStatusUpdateResponse(int commandId, String status, String response) {
        JsonObject responseObj = JsonParser.parseString(response).getAsJsonObject();
        if (responseObj.get("success").getAsBoolean()) {
            plugin.debug("Updated command " + commandId + " status to " + status);
        } else {
            plugin.debug("Failed to update command status: " + response);
        }
    }
    
    public int getProcessingCommandsCount() {
        return processingCommands.size();
    }
    
    public List<PendingCommand> getProcessingCommands() {
        return new ArrayList<>(processingCommands.values());
    }
    
    /**
     * Result wrapper for command execution
     */
    private static class CommandExecutionResult {
        private final boolean success;
        private final String message;
        
        public CommandExecutionResult(boolean success, String message) {
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
