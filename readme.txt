=== Quick Configuration Links ===
Contributors: whiteshadow
Donate link: http://w-shadow.com/
Tags: configuration, admin, plugins, settings, usability, menu
Requires at least: 2.5
Tested up to: 3.2
Stable tag: 1.4.1

Automagically adds a "Settings" link to every active plugin on the "Plugins" page.

== Description ==

This plugins makes it easy to acccess every plugin's configuration page without hunting through the entire dashboard menu. It automatically scans the menu structure to find configuration-related entries created by other plugins. Then it adds an appropriate "Settings" link to each active plugin on the "Plugins" page (right next to the "Deactivate" and "Edit" links). Lab trials shown success rate over 95%

Additional notes : 

* For best results, PHP 5 or later is recommended.
* If a plugin has no menu entries, no "Settings" link will be shown for it.
* On the other hand, if a plugin has only one menu entry the link will always point to that page, even it has nothing to do with configuration. It's a feature ;)
* "Quick Configuration Links" will skip plugins that already add custom action link(s) on their own.

== Installation ==

Install it just like any other plugin : 

1. Download the .zip archive and extract it.
2. Upload the `quick-configuration-links` directory to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

You may need to refresh the page before the "Settings" will appear.