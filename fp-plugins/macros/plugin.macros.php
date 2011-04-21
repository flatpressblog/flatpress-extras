<?php
/*
Plugin Name: Macros
Plugin URI: http://www.flatpress.org/
Description: Defines some macros which are automatically expanded
Author: NoWhereMan
Version: 1.1
Author URI: http://www.nowhereland.it/
*/ 

global $__plugin_macros;

define('PLUGIN_MACROS_DELIM', '$');
	
$__plugin_macros = array(
	'blog'		=> BLOG_BASEURL,
	'content'	=> BLOG_BASEURL . FP_CONTENT,
	'images'	=> BLOG_BASEURL . IMAGES_DIR,
	'attachs'	=> BLOG_BASEURL . ATTACHS_DIR
);




function plugin_macros_expand($text) {
	global $__plugin_macros;
	foreach($__plugin_macros as $_macro => $_value) {
		$text = str_replace(
			PLUGIN_MACROS_DELIM.$_macro.PLUGIN_MACROS_DELIM, 
			$_value, 
			$text
		);
	}

	return $text;

}

add_filter('the_content', 'plugin_macros_expand', 100);

