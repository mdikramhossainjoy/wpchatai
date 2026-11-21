=== WPChat — Autonomous AI WordPress Copilot ===
Contributors: mdikramhossainjoy
Author: MD Ikram Hossain Joy
Tags: ai, gemini, copilot, chat, admin, automation, woocommerce, full control
Requires at least: 6.2
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage, configure, and build your entire WordPress site via conversational AI powered by Google Gemini with strict human-in-the-loop approval gates.

== Description ==

WPChat is an autonomous AI Copilot integrated directly into WordPress admin that works as a real human administrator with full control over the site.
Powered by Google Gemini and native Tool Calling, WPChat can manage plugins, themes, content, WooCommerce, SEO, performance cache, navigation menus, comments, site settings, and arbitrary administrative PHP code execution.

= Key Features =
* **Full Administrative Control**: AI works as a real administrator capable of installing plugins from WordPress.org, switching themes, configuring menus, moderating comments, optimizing databases, purging cache, and running arbitrary PHP code.
* **Universal PHP Execution**: `wp_execute_php` allows executing sandboxed administrative code with output buffering and runtime metrics.
* **Full Plugin Management**: `wp_manage_plugins` allows searching WordPress.org, installing plugins directly, activating, deactivating, and deleting plugins.
* **Theme & Appearance Engine**: `wp_manage_themes` manages themes, permalinks, homepage configuration, site title, tagline, and site icons.
* **Navigation Menus**: `wp_manage_menus` creates menus, adds items (pages, custom links), and assigns theme locations.
* **WooCommerce Specialist**: `wp_manage_woocommerce` manages products, stock, coupons, orders, and store analytics.
* **100% Live AJAX Context**: Dynamically checks WooCommerce, plugins, and site state via AJAX without needing a page refresh.
* **Three Approval Modes**:
  * **Always Ask**: Requires explicit administrator approval before any write or modification occurs.
  * **Copilot (Default)**: Automatically runs low-risk writes while prompting confirmation for high-risk actions.
  * **Turbo**: Zero-friction autonomous execution paired with automatic DB snapshots and instant 1-click Undo.
* **Strict Security Guardrails**: Hard blocks on server security salts and passwords. Nonce verification and capability checks (`manage_options`) on all endpoints.
* **Clean Design System**: Sleek monochrome interface, occlusion-safe slide-out drawer, bottom-right global toast architecture, and live connection pulse.

== Installation ==

1. Upload `wpchat.zip` through the WordPress plugins screen or unzip to `/wp-content/plugins/wpchat`.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Open WPChat settings and provide your free Google Gemini API Key from Google AI Studio.
4. Access the assistant from any admin screen via the floating launcher button in the bottom right corner.
