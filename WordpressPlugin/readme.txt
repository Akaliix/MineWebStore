=== MineWebStore ===
Contributors: akaliix
Donate link: https://buymeacoffee.com/akaliix
Tags: woocommerce, minecraft, ecommerce, shop
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Integrates WooCommerce with Minecraft servers to execute commands when products are purchased. HPOS compatible.

== Description ==

MineWebStore connects your WooCommerce store to your Minecraft servers so that purchasing a product can trigger server commands automatically.

Key capabilities include:

= E-commerce Integration =
* Add Minecraft commands to any WooCommerce product (supports variations)
* Custom Minecraft username field at checkout with real-time validation
* Order tracking with custom statuses
* Fully compatible with WooCommerce High-Performance Order Storage (HPOS)

= Server Management =
* Manage multiple Minecraft servers from one WordPress site
* Automatic server registration and status monitoring
* Secure REST API for server communication

= Command System =
* Flexible commands with %player% placeholder
* Execution modes: online-only or always-run
* Optional delays between commands
* Bulk processing and history tracking

= Requirements =
* WordPress 6.0+
* WooCommerce 8.0+
* PHP 8.0+
* MySQL 8.0+

= Security =
* All API requests are authenticated using a server-specific secret key
* HTTPS recommended

= REST API =
The plugin exposes secure endpoints used by the companion Minecraft server plugin:

Server Registration

```
POST /wp-json/mcapi/v1/register
Headers: X-Secret-Key: your-secret-key
```

Get Pending Commands

```
GET /wp-json/mcapi/v1/commands?server_name=YourServer
Headers: X-Secret-Key: your-secret-key
```

Mark Commands as Read

```
POST /wp-json/mcapi/v1/commands/read
Headers: X-Secret-Key: your-secret-key
```

Update Command Status

```
PUT /wp-json/mcapi/v1/commands/{id}
Headers: X-Secret-Key: your-secret-key
```

Works together with the companion Minecraft plugin included in the MineWebStore project.

== Installation ==

= From WordPress Admin =
1. Go to Plugins → Add New
2. Search for "MineWebStore"
3. Click Install Now and Activate
4. Go to Minecraft in the admin menu to configure

= Manual Installation =
1. Download the plugin zip
2. Upload to /wp-content/plugins/minewebstore/ or upload the zip in Plugins → Add New → Upload Plugin
3. Activate the plugin
4. Go to Minecraft in the admin menu to configure

= Initial Configuration =
1. Generate a secret key under Minecraft → Settings
2. Add your Minecraft server(s)
3. Configure checkout text and validation messages if needed
4. Test the connection using the built-in tools

= Product Setup Example =
Product: VIP Rank

Server: Survival Server

Commands:
* lp user %player% group add vip
* give %player% diamond 64
* tell %player% Welcome to VIP!

Execution Mode: Online Only

Command Delay: 1 second

== Frequently Asked Questions ==

= Does this plugin support HPOS? =
Yes. The plugin declares compatibility with WooCommerce High-Performance Order Storage (HPOS).

= How do I generate the secret key? =
Navigate to Minecraft → Settings and click Generate Secret Key. Use this key in your server-side integration.

= How does player validation work? =
At checkout, the player name can be validated against the server's player cache to reduce errors. Ensure players have joined your server at least once.

= Commands are not executing — what should I check? =
* Ensure the companion Minecraft plugin is installed and configured
* Verify the secret key matches on both WordPress and the server
* Confirm the server has internet connectivity
* Check WordPress and server logs for errors

= Can I use multiple servers? =
Yes. You can register multiple servers and target commands to a specific server per product.

== Screenshots ==
1. Admin dashboard: Minecraft settings and overview
2. Admin: Server list and status monitor
3. Product editor: Minecraft commands panel
4. Checkout: Minecraft username field (valid)
5. Checkout: Minecraft username field (error state)
6. Minecraft server logs showing executed commands

== Changelog ==

= 1.0.0 =
* Initial public release
* WooCommerce product commands with %player% placeholder
* Checkout player name field with validation
* Multi-server support with registration and status
* REST API for pending commands and status updates
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release with WooCommerce integration, REST API, and HPOS support.