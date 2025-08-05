package com.github.Akaliix.MineWebStore.api;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import com.github.Akaliix.MineWebStore.MineWebStorePlugin;
import com.github.Akaliix.MineWebStore.utils.HttpClient;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.List;

public class WordPressAPI {
    
    private final String baseUrl;
    private final String secretKey;
    private final HttpClient httpClient;
    private final Gson gson;
    private final MineWebStorePlugin plugin;
    private String serverSpecificKey;
    
    public WordPressAPI(String baseUrl, String secretKey, boolean debugEnabled, MineWebStorePlugin plugin) {
        this.baseUrl = baseUrl.endsWith("/") ? baseUrl : baseUrl + "/";
        this.secretKey = secretKey;
        this.httpClient = new HttpClient(plugin, debugEnabled);
        this.gson = new Gson();
        this.plugin = plugin;
    }
    
    public boolean registerServer(String serverName) {
        try {
            String endpoint = baseUrl + "wp-json/mcapi/v1/register";
            
            JsonObject requestData = new JsonObject();
            requestData.addProperty("secret_key", secretKey);
            requestData.addProperty("server_name", serverName);
            
            String response = httpClient.sendPostRequest(endpoint, requestData.toString(), null);
            
            if (response != null) {
                JsonObject responseJson = JsonParser.parseString(response).getAsJsonObject();
                
                if (responseJson.has("success") && responseJson.get("success").getAsBoolean()) {
                    if (responseJson.has("server_key")) {
                        serverSpecificKey = responseJson.get("server_key").getAsString();
                        plugin.debug("Server registered successfully with key: " + serverSpecificKey);
                        return true;
                    }
                } else {
                    String message = responseJson.has("message") ? 
                        responseJson.get("message").getAsString() : "Unknown error";
                    plugin.debug("Server registration failed: " + message);
                }
            }
        } catch (Exception e) {
            plugin.debug("Error registering server: " + e.getMessage());
        }
        
        return false;
    }
    
    public boolean syncPlayerList(String serverName, List<String> players, String playerHash) {
        if (serverSpecificKey == null) {
            plugin.debug("Cannot sync players - server not registered!");
            return false;
        }
        
        try {
            String endpoint = baseUrl + "wp-json/mcapi/v1/players";
            
            JsonObject requestData = new JsonObject();
            requestData.addProperty("server_name", serverName);
            requestData.add("players", gson.toJsonTree(players));
            
            if (playerHash != null) {
                requestData.addProperty("player_hash", playerHash);
            }
            
            String response = httpClient.sendPostRequest(endpoint, requestData.toString(), serverSpecificKey);
            
            if (response != null) {
                JsonObject responseJson = JsonParser.parseString(response).getAsJsonObject();
                
                if (responseJson.has("success") && responseJson.get("success").getAsBoolean()) {
                    plugin.debug("Player history synced successfully");
                    return true;
                } else {
                    String message = responseJson.has("message") ? 
                        responseJson.get("message").getAsString() : "Unknown error";
                    plugin.debug("Player sync failed: " + message);
                }
            }
        } catch (Exception e) {
            plugin.debug("Error syncing player list: " + e.getMessage());
        }
        
        return false;
    }
    
    public String getPendingCommands(String serverName) {
        try {
            String endpoint = baseUrl + "wp-json/mcapi/v1/commands?server_name=" + 
                URLEncoder.encode(serverName, StandardCharsets.UTF_8);
            
            plugin.debug("Got pending commands response");
            return httpClient.sendGetRequest(endpoint, serverSpecificKey);
            
        } catch (Exception e) {
            plugin.debug("Error getting pending commands: " + e.getMessage());
        }
        
        return null;
    }
    
    public String markCommandsAsRead(String serverName, List<Integer> commandIds) {
        try {
            String endpoint = baseUrl + "wp-json/mcapi/v1/commands/read";
            
            JsonObject requestData = new JsonObject();
            requestData.addProperty("server_name", serverName);
            requestData.add("command_ids", gson.toJsonTree(commandIds));
            
            plugin.debug("Marked commands as read: " + commandIds.size());
            return httpClient.sendPostRequest(endpoint, requestData.toString(), serverSpecificKey);
            
        } catch (Exception e) {
            plugin.debug("Error marking commands as read: " + e.getMessage());
        }
        
        return null;
    }
    
    public String updateCommandStatus(String serverName, int commandId, String status, String message) {
        if (serverSpecificKey == null) {
            plugin.debug("Server not yet registered, cannot update command status");
            return null;
        }
        
        try {
            String endpoint = baseUrl + "wp-json/mcapi/v1/commands/" + commandId;
            
            JsonObject requestData = new JsonObject();
            requestData.addProperty("server_name", serverName);
            requestData.addProperty("status", status);
            if (message != null) {
                requestData.addProperty("message", message);
            }
            
            String response = httpClient.sendPutRequest(endpoint, requestData.toString(), serverSpecificKey);
            
            if (response != null) {
                plugin.debug("Updated command " + commandId + " status to " + status);
                return response;
            }
        } catch (Exception e) {
            plugin.debug("Error updating command status: " + e.getMessage());
        }
        
        return null;
    }
    
    public String getServerSpecificKey() {
        return serverSpecificKey;
    }
}
