package com.github.Akaliix.MineWebStore.models;

public class PendingCommand {
    private final int id;
    private final int orderId;
    private final int productId;
    private final String playerName;
    private final String command;
    private final String runMode;
    private final String createdAt;
    
    public PendingCommand(int id, int orderId, int productId, String playerName, String command, String runMode, String createdAt) {
        this.id = id;
        this.orderId = orderId;
        this.productId = productId;
        this.playerName = playerName;
        this.command = command;
        this.runMode = runMode != null ? runMode : "online";
        this.createdAt = createdAt;
    }
    
    public int getId() {
        return id;
    }
    
    public int getOrderId() {
        return orderId;
    }
    
    public int getProductId() {
        return productId;
    }
    
    public String getPlayerName() {
        return playerName;
    }
    
    public String getCommand() {
        return command;
    }
    
    public String getRunMode() {
        return runMode;
    }
    
    public String getCreatedAt() {
        return createdAt;
    }
    
    public boolean shouldRunWhenPlayerOnline() {
        return "online".equals(runMode);
    }
    
    public boolean shouldRunAlways() {
        return "always".equals(runMode);
    }
    
    @Override
    public String toString() {
        return "PendingCommand{" +
                "id=" + id +
                ", orderId=" + orderId +
                ", productId=" + productId +
                ", playerName='" + playerName + '\'' +
                ", command='" + command + '\'' +
                ", runMode='" + runMode + '\'' +
                ", createdAt='" + createdAt + '\'' +
                '}';
    }
}
