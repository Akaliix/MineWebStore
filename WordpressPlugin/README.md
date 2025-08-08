# MineWebStore - WordPress Plugin

[![WordPress Version](https://img.shields.io/badge/WordPress-6.0+-blue.svg)](https://wordpress.org/)
[![WooCommerce Version](https://img.shields.io/badge/WooCommerce-8.0+-purple.svg)](https://woocommerce.com/)
[![PHP Version](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

The WordPress/WooCommerce component of the MineWebStore system. This plugin enables automatic Minecraft command execution when WooCommerce products are purchased.

## 🚀 Features

### E-commerce Integration
- **WooCommerce Products**: Add Minecraft commands to any WooCommerce product
- **Product Variations**: Support for product variations with individual command sets
- **Checkout Integration**: Custom Minecraft username field during checkout
- **Order Management**: Comprehensive order tracking and status management
- **HPOS Compatibility**: Full support for High-Performance Order Storage

### Server Management
- **Multi-Server Support**: Manage multiple Minecraft servers from one WordPress site
- **Server Registration**: Automatic server registration
- **Status Monitoring**: Real-time server status and health monitoring
- **API Management**: Secure REST API for Minecraft server communication

### Command System
- **Flexible Commands**: Support for any Minecraft server command
- **Player Placeholders**: Use `%player%` placeholder for dynamic usernames
- **Execution Modes**: Choose between "online-only" and "always-run" execution
- **Command Delays**: Configure delays between command execution
- **Bulk Operations**: Manage multiple commands and orders efficiently

## 📋 Requirements

- **WordPress**: 6.0 or higher (tested up to 6.8)
- **WooCommerce**: 8.0 or higher (tested up to 10.0)
- **PHP**: 8.0 or higher (tested with 8.2)
- **MySQL**: 8.0 or higher
- **HPOS**: Compatible with High-Performance Order Storage

## 📦 Installation

### From WordPress Plugin Directory
1. Go to **Plugins → Add New** in your WordPress admin
2. Search for "MineWebStore"
3. Click **Install Now** and then **Activate**
4. Navigate to **Minecraft** in your admin menu to configure

### Manual Installation
1. Download the plugin from the WordPress Plugin Directory
2. Upload to `/wp-content/plugins/minewebstore/` or upload plugin as .zip format in "add plugins" page
3. Activate through the WordPress plugins panel
4. Navigate to **Minecraft** in your admin menu to configure

## ⚙️ Configuration

### Initial Setup
1. **Generate Secret Key**: Go to Minecraft → Settings and generate a secure secret key
2. **Add Servers**: Register your Minecraft servers with their connection details
3. **Configure Checkout**: Customize the checkout field text and validation messages
4. **Test Connection**: Use the built-in test tools to verify connectivity

### Product Configuration
1. Edit any WooCommerce product
2. Scroll to the **Minecraft** section
3. Select the target Minecraft server
4. Add commands using the `%player%` placeholder
5. Configure execution modes and delays
6. Save the product

### Example Product Setup
```
Product: VIP Rank
Server: Survival Server
Commands:
  - lp user %player% group add vip
  - give %player% diamond 64
  - tell %player% Welcome to VIP!
Execution Mode: Online Only
Command Delay: 1 second
```

## 🎮 Customer Experience

### Checkout Process
1. Customer adds Minecraft products to cart
2. Checkout form displays Minecraft username field
3. System validates username against server player database
4. Order is placed and payment processed
5. Commands are queued for execution on the Minecraft server

### Player Validation
- Real-time username validation during checkout
- Checks against actual server player databases
- Prevents orders with invalid or non-existent usernames
- Customizable error messages for better user experience

## 🔧 Admin Features

### Order Management
- Custom order statuses for Minecraft processing
- Detailed command execution tracking

### Command History
- Complete log of executed commands
- Status tracking (pending, read, executed, failed)
- Player and server association
- Execution timestamps and results

### Server Administration
- Add/remove Minecraft servers
- View registered server details
- Regenerate API keys

## 📡 API Documentation

### REST API Endpoints

#### Server Registration
```
POST /wp-json/mcapi/v1/register
Headers: X-Secret-Key: your-secret-key
```

#### Get Pending Commands
```
GET /wp-json/mcapi/v1/commands?server_name=YourServer
Headers: X-Secret-Key: your-secret-key
```

#### Mark Commands as Read
```
POST /wp-json/mcapi/v1/commands/read
Headers: X-Secret-Key: your-secret-key
```

#### Update Command Status
```
PUT /wp-json/mcapi/v1/commands/{id}
Headers: X-Secret-Key: your-secret-key
```

### Security
- All endpoints require secret key authentication
- HTTPS encryption recommended

## 🛠️ Customization

Customize all customer-facing text:
- Checkout field labels
- Validation error messages
- Order confirmation text

## 🔍 Troubleshooting

### Common Issues

#### Commands Not Executing
- Verify Minecraft plugin is installed and configured
- Check secret key matches between WordPress and Minecraft
- Ensure server has internet connectivity
- Review WordPress and server logs

#### Player Validation Failing
- Confirm players have joined the server at least once
- Check server name configuration
- Verify API endpoints are accessible
- Test with known valid usernames

## 🔒 Security

### Best Practices
- Use strong, unique secret keys
- Enable HTTPS for all communications
- Regularly update WordPress and plugins
- Monitor API access logs
- Implement IP filtering if needed

## 📈 Performance

### Optimization Features
- Efficient database queries
- Background command processing
- Caching mechanisms
- Minimal resource usage

## 🔄 Updates

### Update Process
1. Updates are delivered through WordPress admin
2. Automatic background updates available
3. Manual updates via plugin upload
4. Version notifications in admin dashboard

### Backup Recommendations
Before updating:
- Backup your WordPress database
- Export plugin settings
- Test on staging environment
- Review changelog for breaking changes


## 🤝 Contributing

We welcome contributions to improve the WordPress plugin:

### Ways to Contribute
- Report bugs and issues
- Suggest new features
- Submit code improvements
- Improve documentation

### Development Setup
```bash
# Clone the repository
git clone https://github.com/Akaliix/MineWebStore.git

# Navigate to WordPress plugin
cd MineWebStore/WordpressPlugin

# Set up development environment
# (Follow WordPress plugin development guidelines)
```

## 📄 Support

### Getting Help
- **Issues**: Report on [GitHub](https://github.com/Akaliix/MineWebStore/issues)
- **Discord**: Join our community server [Community Discord](https://discord.gg/cG8XdnXMPE)

---

**Part of the MineWebStore System**

This WordPress plugin works in conjunction with our Minecraft server plugin to provide a complete e-commerce solution for Minecraft servers.

For complete setup instructions and Minecraft plugin information, see the [main project README](../README.md).
