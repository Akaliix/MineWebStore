package com.github.Akaliix.MineWebStore.utils;

import com.github.Akaliix.MineWebStore.MineWebStorePlugin;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;

/**
 * Utility class for HTTP requests
 */
public class HttpClient {
    
    private final MineWebStorePlugin plugin;
    private final boolean debugEnabled;
    
    public HttpClient(MineWebStorePlugin plugin, boolean debugEnabled) {
        this.plugin = plugin;
        this.debugEnabled = debugEnabled;
    }
    
    public String sendGetRequest(String endpoint, String authToken) {
        try {
            URL url = URI.create(endpoint).toURL();
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            
            connection.setRequestMethod("GET");
            connection.setRequestProperty("Accept", "application/json");
            
            if (authToken != null) {
                connection.setRequestProperty("Authorization", "Bearer " + authToken);
            }
            
            debugLog("GET " + endpoint);
            
            return readResponse(connection);
            
        } catch (Exception e) {
            plugin.debug("Error sending GET request to " + endpoint + ": " + e.getMessage());
            return null;
        }
    }
    
    public String sendPostRequest(String endpoint, String jsonData, String authToken) {
        return sendJsonRequest(endpoint, "POST", jsonData, authToken);
    }
    
    public String sendPutRequest(String endpoint, String jsonData, String authToken) {
        return sendJsonRequest(endpoint, "PUT", jsonData, authToken);
    }
    
    private String sendJsonRequest(String endpoint, String method, String jsonData, String authToken) {
        try {
            URL url = URI.create(endpoint).toURL();
            HttpURLConnection connection = (HttpURLConnection) url.openConnection();
            
            connection.setRequestMethod(method);
            connection.setRequestProperty("Content-Type", "application/json");
            connection.setRequestProperty("Accept", "application/json");
            connection.setDoOutput(true);
            
            if (authToken != null) {
                connection.setRequestProperty("Authorization", "Bearer " + authToken);
            }
            
            debugLog(method + " " + endpoint);
            debugLog("Request: " + jsonData);
            
            // Send request
            try (OutputStream os = connection.getOutputStream()) {
                byte[] input = jsonData.getBytes(StandardCharsets.UTF_8);
                os.write(input, 0, input.length);
            }
            
            return readResponse(connection);
            
        } catch (Exception e) {
            plugin.debug("Error sending " + method + " request to " + endpoint + ": " + e.getMessage());
            return null;
        }
    }
    
    private String readResponse(HttpURLConnection connection) throws Exception {
        int responseCode = connection.getResponseCode();
        StringBuilder response = new StringBuilder();
        
        try (BufferedReader br = new BufferedReader(new InputStreamReader(
                responseCode >= 200 && responseCode < 300 ? 
                    connection.getInputStream() : connection.getErrorStream(), 
                StandardCharsets.UTF_8))) {
            
            String responseLine;
            while ((responseLine = br.readLine()) != null) {
                response.append(responseLine.trim());
            }
        }
        
        debugLog("Response Code: " + responseCode);
        debugLog("Response: " + response.toString());
        
        if (responseCode >= 200 && responseCode < 300) {
            return response.toString();
        } else {
            plugin.debug("HTTP Error " + responseCode + ": " + response.toString());
            return null;
        }
    }
    
    private void debugLog(String message) {
        if (debugEnabled) {
            plugin.debug("[WP-API] " + message);
        }
    }
}
