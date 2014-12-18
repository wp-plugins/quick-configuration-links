=== Quick Configuration Links ===
Contributors: whiteshadow
Donate link: http://w-shadow.com/
Tags: configuration, admin, plugins, settings, usability, menu
Requires at least: 2.5
Tested up to: 4.2-alpha.
Stable tag: 1.4.4

Automagically adds a "Settings" link to every active plugin on the "Plugins" page.

== Description ==

This plugin will add a "Settings" to every active plugin listed on the "Plugins" page (right next to the "Deactivate" and "Edit" links). This makes it easy to acccess plugin configuration without hunting through the entire dashboard menu. 

The plugin finds the right page to link to by automatically scanning through the WordPress menu structure to detect configuration-related plugin pages. Lab trials shown success rate of over 95%.

Additional notes : 

* Works best with PHP 5+
* *Quick Configuration Links* will automatically skip plugins that already add their own custom link(s) to their "Plugins" page listing.
* If a plugin has only one menu entry, the "Settings" link will always point to that page - even it has nothing to do with configuration. This is a feature.

== Installation ==

Install it just like any other plugin : 

1. Download the .zip archive and extract it.
2. Upload the `quick-configuration-links` directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

You may need to refresh the page before the "Settings" will appear.

== Changelog ==

= 1.4.5 =
* Tested up to WP 4.2-alpha.
* Fixed a URL generation bug that caused the plugin to display non-functioning "Settings" links for plugins that place their settings page under a custom top level menu (e.g. "My Plugin -> Settings").
