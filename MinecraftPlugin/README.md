# MineWebStore - Minecraft Plugin

[![Minecraft Version](https://img.shields.io/badge/Minecraft-1.18.x--1.21.x-green.svg)](https://minecraft.net/)
[![Java Version](https://img.shields.io/badge/Java-17--21-orange.svg)](https://adoptium.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

The Minecraft server component of the MineWebStore Integration system. This plugin connects your Minecraft server to a WordPress/WooCommerce website, automatically executing commands when products are purchased.

## 🚀 Features

### Core Functionality
- **WordPress Integration**: Seamless connection to WordPress via REST API
- **Command Processing**: Automatic execution of commands from WooCommerce orders
- **Player Management**: Real-time player data synchronization with WordPress
- **Queue System**: Command queuing for offline players with automatic execution on join
- **Server Registration**: Automatic server registration with WordPress backend

### Advanced Features
- **Command Modes**: Support for "online-only" and "always-run" execution modes
- **Status Reporting**: Real-time command status updates (pending, read, executed, failed)
- **Error Handling**: Error detection and reporting
- **Debug System**: Logging and debugging capabilities
- **Configuration Validation**: Automatic validation of plugin configuration

## 📋 Requirements

- **Minecraft Server**: 1.18.x - 1.21.x
- **Server Software**: Paper (recommended), Spigot, or Bukkit
- **Java**: 17 - 21
- **Network**: Outbound HTTP/S access to your WordPress site

## 📦 Installation

### Step 1: Download
Download the latest `minewebstore-1.0.0.jar` from the [Releases](https://github.com/Akaliix/MineWebStore/releases) page.

### Step 2: Install
1. Stop your Minecraft server
2. Place the `minewebstore-1.0.0.jar` file in your server's `plugins/` folder
3. Start your Minecraft server

### Step 3: Configure
1. Stop the server after first run
2. Edit `plugins/MineWebStore/config.yml` with your WordPress details
3. Start the server again

## ⚙️ Configuration

### Basic Configuration

Edit `plugins/MineWebStore/config.yml`:

```yaml
# WordPress API Configuration
wordpress:
  # Your WordPress site URL (without trailing slash)
  base_url: "https://yourdomain.com"
  # WordPress plugin secret key (from WP admin settings)
  secret_key: "your-secret-key-here"

# Server Configuration
server:
  # Unique name for this server (must match WP admin settings)
  name: "Survival-1"
  # How often to poll for new commands (in seconds)
  poll_interval: 10

# Debug Configuration
debug:
  enabled: false
  log_api_calls: false
```

### Configuration Options

#### WordPress Settings
- **base_url**: Your WordPress site URL (e.g., `https://yourdomain.com`)
- **secret_key**: Secret key from WordPress admin (Minecraft → Settings)

#### Server Settings
- **name**: Unique identifier for this server (must match WordPress configuration)
- **poll_interval**: How often to check for new commands (recommended: 5-30 seconds)

#### Debug Settings
- **enabled**: Enable debug logging to console
- **log_api_calls**: Log all API requests and responses (for troubleshooting)

## 🎮 Commands

### Admin Commands
All commands require the `mws.admin` permission (default: OP).

#### `/mws status`
Display plugin status and statistics:
- WordPress connection status
- Pending commands count
- Queued commands count
- Last successful sync time

#### `/mws reload`
Reload the plugin configuration:
- Reloads `config.yml`
- Reinitializes API connections
- Restarts command polling

#### `/mws test`
Test WordPress connectivity:
- Validates API endpoints
- Tests authentication
- Reports connection status

### Command Examples
```
/mws status
/mws reload
/mws test
```

## 🔧 Permissions

### Default Permissions
- **mws.admin**: Access to all MineWebStore commands (default: OP)

### Custom Permission Setup
Add to your permissions plugin:
```yaml
groups:
  admin:
    permissions:
      - mws.admin
  moderator:
    permissions:
      - mws.admin
```

## 📊 Monitoring

### Server Logs
Monitor your server console for MineWebStore activity:

```
[INFO] [MineWebStore] Plugin enabled successfully!
[INFO] [MineWebStore] Server registered with WordPress
[INFO] [MineWebStore] Found 3 pending commands
[INFO] [MineWebStore] Executed command: give PlayerName diamond 64
```

### Debug Logging
Enable debug mode for detailed logging:
```yaml
debug:
  enabled: true
  log_api_calls: true
```

### Status Monitoring
Use `/mws status` to check:
- Connection health
- Command processing statistics
- Player synchronization status

## 🔍 Troubleshooting

### Common Issues

#### Connection Failed
**Problem**: Plugin can't connect to WordPress
**Solutions**:
- Verify `base_url` in config.yml
- Check `secret_key` matches WordPress settings
- Ensure server has internet access
- Verify WordPress site is accessible via HTTP/S

#### Server Registration Failed
**Problem**: Server won't register with WordPress
**Solutions**:
- Verify secret key is correct
- Check WordPress plugin is activated
- Ensure API endpoints are accessible
- Check server name doesn't conflict with existing servers

### Debug Steps

1. **Enable Debug Mode**:
   ```yaml
   debug:
     enabled: true
     log_api_calls: true
   ```

2. **Check Logs**: Monitor server console for error messages

3. **Test Connection**: Use `/mws test` command

4. **Verify Configuration**: Double-check all config.yml settings

5. **WordPress Side**: Check WordPress admin for server status

## 🏗️ Development

### Building from Source

#### Prerequisites
- Java 17 or higher
- Maven 3.6 or higher
- Git

#### Build Steps
```bash
git clone https://github.com/Akaliix/MineWebStore.git
cd MineWebStore/MinecraftPlugin
mvn clean package
```

The compiled JAR will be in the `target/` directory.

### Development Setup
```bash
# Clone repository
git clone https://github.com/Akaliix/MineWebStore.git

# Navigate to Minecraft plugin
cd MineWebStore/MinecraftPlugin

# Build plugin
mvn clean package

# Copy to test server
cp target/minewebstore-1.0.0.jar /path/to/server/plugins/
```

## 📡 API Integration

### How It Works
1. Plugin polls WordPress for pending commands every `poll_interval` seconds
2. Commands are fetched via REST API using secure authentication
3. Commands are executed on the Minecraft server
4. Status updates are sent back to WordPress
5. Player data is synchronized in real-time

### API Endpoints Used
- `GET /wp-json/mcapi/v1/commands` - Fetch pending commands
- `POST /wp-json/mcapi/v1/commands/read` - Mark commands as read
- `PUT /wp-json/mcapi/v1/commands/{id}` - Update command status
- `POST /wp-json/mcapi/v1/players` - Sync player data
- `POST /wp-json/mcapi/v1/register` - Register server

## 🔒 Security

### Authentication
- All API requests use secret key authentication
- Secret keys should be unique and randomly generated
- Never share or commit secret keys to version control

### Best Practices
- Use HTTPS for all WordPress communication
- Regularly rotate secret keys
- Monitor API access logs
- Restrict network access to trusted sources only

## 📄 Support

### Getting Help
- **GitHub Issues**: [Report bugs or request features](https://github.com/Akaliix/MineWebStore/issues)
- **Documentation**: Check the [main README](../README.md)
- **Discord**: Join our community server [Community Discord](https://discord.gg/cG8XdnXMPE)

## 🔄 Version History

...

## 🤝 Contributing

We welcome contributions to improve the Minecraft plugin:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Development Guidelines
- Follow Java coding standards
- Add appropriate comments
- Test with multiple Minecraft versions
- Update documentation as needed

---

**Part of the MineWebStore Integration System**

For complete setup instructions and WordPress plugin information, see the [main project README](../README.md).
