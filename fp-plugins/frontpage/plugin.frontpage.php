<?php
/*
Plugin Name: Front Page
Version: 1.0
Plugin URI: http://flatpress.org
Description: Set a default category to display on the front page
Author: NoWhereMan
Author URI: http://www.flatpress.org
*/

function plugin_frontpage_init() {

    global $fp_params;

    if (defined('ADMIN_PANEL')) return;
    $cat = plugin_getoptions('frontpage','defcat');
    $excat = plugin_getoptions('frontpage','excat');
   
    if (!isset($fp_params['cat'])) {
    	$fp_params['cat']=$cat;
    }
   
	if ($excat) {
		$cats = entry_categories_get('rels');
		$rels = $cats[ $excat ];
	    if (!isset($fp_params['not']) && !in_array($fp_params['cat'], $rels)) {
    		$fp_params['not']=$excat;
	    }
	}

}
 
add_action('init', 'plugin_frontpage_init');

if (class_exists('AdminPanelAction')){

	class admin_plugin_frontpage extends AdminPanelAction { 
		
		var $langres = 'plugin:frontpage';
		
		function setup() {
			$this->smarty->assign('admin_resource', "plugin:frontpage/admin.plugin.frontpage");
		}
		
		function main() {
			$category = 
			// a bit of (horrible) magic: list_categories checks for a 'categories' array
			// in the template variables
			$this->smarty->assign('categories', plugin_getoptions('frontpage', 'defcat') );
			$this->smarty->assign('exclude_categories', plugin_getoptions('frontpage', 'excat') );
		}
		
		function onsubmit() {

			if (isset($_POST['def-cats'])){
				plugin_addoption('frontpage', 'defcat', (int)$_POST['def-cats']);
			} 
			
			if (isset($_POST['ex-cats'])){
				plugin_addoption('frontpage', 'excat', (int)$_POST['ex-cats']);
			} 

			//print_r($_POST);print_r(plugin_getoptions('frontpage'));exit();
			
			$this->smarty->assign('success', 1);
			plugin_saveoptions('frontpage');
			
			return 2;
		}
		
	}

	admin_addpanelaction('plugin', 'frontpage', true);

}
