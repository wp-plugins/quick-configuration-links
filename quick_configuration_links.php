<?php
/*
Plugin Name: Quick Configuration Links
Plugin URI: http://w-shadow.com/blog/2008/10/15/quick-configuration-links-for-all-plugins-a-wordpress-hack/
Description: Attempts to automagically add a "Settings" link to every active plugin on this page.
Version: 1.2
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
				
				if (!isset($this->plugin_configuration_pages[$detected])) {
					$this->plugin_configuration_pages[$detected] = array($topmenu, $subitem);
				}
			}
		}
		
	}
	
	function find_by_reflection(){
		global $submenu, $wp_filter;
		
		$items = $submenu;
		//Move the "Settings" menu to the start of the array 
		unset($items['options-general.php']);
		$items['options-general.php'] = $submenu['options-general.php'];
		$items = array_reverse($items);
		
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
						//Is there already a page set for this plugin? 
						//if (!isset($this->plugin_configuration_pages[$dir])) {
							//First encounter. Save the menu info. 
							$this->plugin_configuration_pages[$dir][] = array($topmenu, $subitem);
						//}
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
				if (is_string($handler)){
					//It's a function, plain and simple
					$func = new ReflectionFunction($handler);
					$filename = $func->getFileName();
					unset($func);
					
				} else if (is_object($handler[0])){
					//It's an object's method. Get the filename from the class definition.
					$class = new ReflectionClass(get_class($handler[0]));
					$filename = $class->getFileName();
					unset($class);
					
				} else if (is_string($handler[0])){
					//It's a static method call; get the filename from the class definition.
					$class = new ReflectionClass($handler[0]);
					$filename = $class->getFileName();
					unset($class);
				}
				
				if (!$filename) continue;
				//Get the plugin's directory name 
				$dir = $this->plugin_dirname($filename);
				//Is there already a page set for this plugin? 
				//if (!isset($this->plugin_configuration_pages[$dir])) {
					//First encounter. Save the menu info. 
					$this->plugin_configuration_pages[$dir][] = array($topmenu, $subitem);
				//}
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
		//Do nothing if there's already some kind of custom link there.
		if ( (count($links) > 2) || has_filter('plugin_action_links_'.$file) ) return $links; 
		
		//I identify plugins by their directory
		$plugin = $this->plugin_dirname($file);
		//Lets see if I have a config page detected...
		if (isset($this->plugin_configuration_pages[$plugin])){
			$conf = $this->plugin_configuration_pages[$plugin];
			//Check privileges
			if (current_user_can($conf[1][1])) {
				//Add the "Settings" link
				$menu_hook = get_plugin_page_hook($conf[0], $conf[1][2]);
				if ( file_exists(WP_PLUGIN_DIR . "/{$conf[1][2]}") || !empty($menu_hook))
					$links[] = "<a href='admin.php?page={$conf[1][2]}'>". __('Settings') ."</a>";
				else
					$links[] = "<a href='".$conf[0].'?page='.$conf[1][2]."'>" . __('Settings') . "</a>";
				
				//$links[] = "<a href='".$conf[0].'?page='.$conf[1][2]."'>" . __('Settings') . "</a>";
			}
		}
		return $links;
	}
} //class

$plugin_settings_link = new PluginConfigurationLink;

} //is_admin
?>