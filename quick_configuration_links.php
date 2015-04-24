<?php
/*
Plugin Name: Quick Configuration Links
Plugin URI: http://w-shadow.com/blog/2008/10/15/quick-configuration-links-for-all-plugins-a-wordpress-hack/
Description: Attempts to automagically add a "Settings" link to every active plugin on this page.
Version: 1.4.6
Author: Janis Elsts
Author URI: http://w-shadow.com/blog/
*/

/*
Created by Janis Elsts (email : whiteshadow@w-shadow.com) 
It's GPL.
*/

if (is_admin()) {

class PluginConfigurationLink {
	var $plugin_configuration_pages;
	
  /**
   * PluginSettingsLink::PluginSettingsLink()
   * Class constructor. Initializes variables and installs hooks.
   *
   * @return void
   */
	function PluginConfigurationLink(){
		$this->plugin_configuration_pages = array();
		//Set up the detector hook.
		//It should run after all the other hooks, so it gets a late priority.
		add_action('admin_head', array($this, 'find_plugin_pages'), 9001);
		//Set up the hook that adds the links.
		add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 9001, 2);
	}

  /**
   * PluginSettingsLink::find_plugin_pages()
   * The hook function that tries to find plugins' configuration pages.
   * Uses $submenu.
   *
   * @return void
   */
	function find_plugin_pages(){
		//Used the appropriate method depending on if introspection is available.
		//This will populate the plugin_configuration_pages array.
		if (class_exists('Reflection')) 
			$this->find_by_reflection();
		else 
			$this->find_by_filename(); //warning : not reliable
		
		//Some plugins may have multiple menu entries. Try to guess the correct one by checking
		//for strings commonly found in configuration-related entries.
		$common = array('setting', 'option', 'conf');
		foreach ($this->plugin_configuration_pages as $plugin => $pages){
			if (count($pages) == 1) {
				//There's only one choice, so use that.
				$this->plugin_configuration_pages[$plugin] = $pages[0];
				continue; 
			}
			$best = $pages[0]; //In most cases the first entry will be the right one
			foreach ($pages as $page){
				foreach ($common as $word){
					if ( (stripos($page[1][0], $word) !== false) || (isset($page[1][3]) && (stripos($page[1][3], $word) !== false)) ) {
						$best = $page; 
						break 2; //Exit both loops
					}
				}
			}
			$this->plugin_configuration_pages[$plugin] = $best;
		}
		
	}
	
	function find_by_filename(){
		global $submenu;
		
		$exceptions = array('options-general.php', 'options-writing.php', 'options-reading.php', 
			'options-discussion.php', 'options-media.php', 'options-privacy.php', 
			'options-permalink.php', 'options-misc.php', 'import.php', 
			'export.php', 'users.php', 'profile.php', 
			'themes.php', 'widgets.php', 'theme-editor.php', 
			'functions.php', 'edit-comments.php', 'page-new.php', 
			'edit-pages.php', 'link-add.php', 'upload.php', 
			'post-new.php', 'edit.php', 'edit-tags.php', 
			'categories.php', 'index.php', 'media-new.php', 
			'edit-link-categories.php', 'plugins.php', '', );
		
		$items = $submenu;
		//Move the "Settings" menu to the start of the array 
		unset($items['options-general.php']);
		$items['options-general.php'] = $submenu['options-general.php'];
		$items = array_reverse($items);
		
		//Check all menus for plugin pages
		foreach ($items as $topmenu => $item){
			foreach ($item as $subitem){
				$detected = $subitem[2];
				if (in_array($detected, $exceptions)) continue;
				$detected = $this->plugin_dirname($detected);
				
				$this->plugin_configuration_pages[$detected][] = array($topmenu, $subitem);
			}
		}
		
	}
	
	function find_by_reflection(){
		global $submenu, $wp_filter;
		
		$items = $submenu;
		if ( isset($submenu['options-general.php']) ) {
			//Move the "Settings" menu to the start of the array 
			unset($items['options-general.php']);
			$items['options-general.php'] = $submenu['options-general.php'];
			$items = array_reverse($items);
		}
		if ( empty($items) ) {
			return;
		} 
		
		//Check all menus for plugin pages
		foreach ($items as $topmenu => $item){
			if (!is_array($item)) continue; //skip menus with no submenus
			foreach ($item as $subitem){
				//Get the page hook name 
				$hook = get_plugin_page_hook($subitem[2], $topmenu);
				if (!$hook) {
					//This might be a strange, possibly outdated plugin that uses the old approach -
					//directly calling a plugin file instead of a hook.
					if (defined('WP_PLUGIN_DIR') && file_exists(WP_PLUGIN_DIR . "/$subitem[2]")){
						$dir = $this->plugin_dirname($subitem[2]);
						//Save the menu info. 
						$this->plugin_configuration_pages[$dir][] = array($topmenu, $subitem);
					}
					continue;
				};
				//Get the handler(s) (there should only ever be one handler for this type of hook).
				$hook_data = $wp_filter[$hook];
				
				//Get the first batch of handlers
				$handler_info = array_shift($hook_data);
				//Take the first handler
				$handler_info = array_shift($handler_info);
				$handler = $handler_info['function'];
				
				$filename = '';
				
				//What is the nature of the hook? Function, class method, or what?
				if (is_string($handler) && function_exists($handler)){
					//It's a function, plain and simple
					$func = new ReflectionFunction($handler);
					$filename = $func->getFileName();
					unset($func);
					
				} else if (is_object($handler[0])){
					//It's an object's method. Get the filename from the class definition.
					$class = new ReflectionClass(get_class($handler[0]));
					$filename = $class->getFileName();
					unset($class);
					
				} else if (is_string($handler[0]) && class_exists($handler[0]) ){
					//It's a static method call; get the filename from the class definition.
					$class = new ReflectionClass($handler[0]);
					$filename = $class->getFileName();
					unset($class);
				}
				
				if (!$filename) continue;
				//Get the plugin's directory name 
				$dir = $this->plugin_dirname($filename);
				//Save the menu info. 
				$this->plugin_configuration_pages[$dir][] = array($topmenu, $subitem);
				
			} //inner loop
		}//outer loop
		
	}
	
  /**
   * PluginSettingsLink::plugin_dirname()
   * Returns the name of the directory of the plugin identified by $file. If the path
   * contains no directories returns the filename without the .php extension. 
   *
   * @param string $file
   * @return string
   */
	function plugin_dirname($file){
		$dir = plugin_basename($file);
		$dir = preg_replace('/\/.*/', '', $dir);
		//$dir = explode('/', str_replace('\\', '/', $file));
		$dir = str_replace('.php', '', $dir);
		return $dir;
	}
	
  /**
   * PluginSettingsLink::plugin_action_links()
   * Adds the "Settings" link to the plugin's action links, if possible.
   *
   * @param array $links
   * @param string $file
   * @return array
   */
	function plugin_action_links($links, $file){
		//Do nothing if the plugin intends to add its own link.
		if ( has_filter('plugin_action_links_'.$file) ) return $links;
		//Also do nothing if there's already a custom link there
		$native_actions = array('deactivate', 'edit', 'activate');
		foreach($links as $id => $link){
			if ( !in_array($id, $native_actions) || !is_string($id) ){
				return $links;
			}
		}
		
		//I identify plugins by their directory
		$plugin = $this->plugin_dirname($file);
		//Lets see if I have a config page detected...
		if (isset($this->plugin_configuration_pages[$plugin])){
			$conf = $this->plugin_configuration_pages[$plugin];
			//Check privileges
			if (current_user_can($conf[1][1])) {
				//Add the "Settings" link
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_attr(PclMenuUrlGenerator::get_menu_url($conf[0], $conf[1])),
					__('Settings')
				);
			}
		}
		return $links;
	}

} //class


/**
 * Generating admin menu URLs is surprisingly difficult. There are many edge cases.
 * This implementation was borrowed from my Admin Menu Editor plugin.
 */
abstract class PclMenuUrlGenerator {
	/**
	 * @var array A partial list of files in /wp-admin/. Correct as of WP 3.8-RC1, 2013.12.04.
	 * When trying to determine if a menu links to one of the default WP admin pages, it's faster
	 * to check this list than to hit the disk.
	 */
	private static $known_wp_admin_files = array(
		'customize.php' => true, 'edit-comments.php' => true, 'edit-tags.php' => true, 'edit.php' => true,
		'export.php' => true, 'import.php' => true, 'index.php' => true, 'link-add.php' => true,
		'link-manager.php' => true, 'media-new.php' => true, 'nav-menus.php' => true, 'options-discussion.php' => true,
		'options-general.php' => true, 'options-media.php' => true, 'options-permalink.php' => true,
		'options-reading.php' => true, 'options-writing.php' => true, 'plugin-editor.php' => true,
		'plugin-install.php' => true, 'plugins.php' => true, 'post-new.php' => true, 'profile.php' => true,
		'theme-editor.php' => true, 'themes.php' => true, 'tools.php' => true, 'update-core.php' => true,
		'upload.php' => true, 'user-new.php' => true, 'users.php' => true, 'widgets.php' => true,
	);

	/**
	 * Get the URL for a plugin menu item.
	 *
	 * @param array $parent_file Parent menu slug or file name.
	 * @param array $item Submenu item.
	 * @return string
	 */
	public static function get_menu_url($parent_file, $item) {
		$menu_url = $item[2];
		$parent_url = !empty($parent_file) ? $parent_file : 'admin.php';

		//Workaround for WooCommerce 2.1.12: For some reason, it uses "&amp;" instead of a plain "&" to separate
		//query parameters. We need a plain URL, not a HTML-entity-encoded one.
		//It is theoretically possible that another plugin might want to use a literal "&amp;", but its very unlikely.
		$menu_url = str_replace('&amp;', '&', $menu_url);

		if ( strpos($menu_url, '://') !== false ) {
			return $menu_url;
		}

		if ( self::is_hook_or_plugin_page($menu_url, $parent_url) ) {
			$base_file = self::is_wp_admin_file($parent_url) ? $parent_url : 'admin.php';
			$url = add_query_arg(array('page' => $menu_url), $base_file);
		} else {
			$url = $menu_url;
		}
		return $url;
	}

	private static function is_hook_or_plugin_page($page_url, $parent_page_url = '') {
		if ( empty($parent_page_url) ) {
			$parent_page_url = 'admin.php';
		}
		$pageFile = self::remove_query_from($page_url);

		//Files in /wp-admin are part of WP core so they're not plugin pages.
		if ( self::is_wp_admin_file($pageFile) ) {
			return false;
		}

		$hasHook = (get_plugin_page_hook($page_url, $parent_page_url) !== null);
		if ( $hasHook ) {
			return true;
		}

		$allowPathConcatenation = self::is_safe_to_append($pageFile);

		$pluginFileExists = $allowPathConcatenation
			&& ($page_url != 'index.php')
			&& is_file(WP_PLUGIN_DIR . '/' . $pageFile);
		if ( $pluginFileExists ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a file exists inside the /wp-admin subdirectory.
	 *
	 * @param string $filename
	 * @return bool
	 */
	private static function is_wp_admin_file($filename) {
		//Check our hard-coded list of admin pages first. It's measurably faster than
		//hitting the disk with is_file().
		if ( isset(self::$known_wp_admin_files[$filename]) ) {
			return self::$known_wp_admin_files[$filename];
		}

		//Now actually check the filesystem.
		$adminFileExists = self::is_safe_to_append($filename)
			&& is_file(ABSPATH . 'wp-admin/' . $filename);

		//Cache the result for later. We can generally expect more than one call per top level menu URL.
		self::$known_wp_admin_files[$filename] = $adminFileExists;

		return $adminFileExists;
	}

	/**
	 * Verify that it's safe to append a given filename to another path.
	 *
	 * If we blindly append an absolute path to another path, we can get something like "C:\a\b/wp-admin/C:\c\d.php".
	 * PHP 5.2.5 has a known bug where calling file_exists() on that kind of an invalid filename will cause
	 * a timeout and a crash in some configurations. See: https://bugs.php.net/bug.php?id=44412
	 *
	 * @param string $filename
	 * @return bool
	 */
	private static function is_safe_to_append($filename) {
		return (substr($filename, 1, 1) !== ':'); //Reject "C:\whatever" and similar.
	}

	public static function remove_query_from($url) {
		$pos = strpos($url, '?');
		if ( $pos !== false ) {
			return substr($url, 0, $pos);
		}
		return $url;
	}
}

$plugin_settings_link = new PluginConfigurationLink;

} //is_admin