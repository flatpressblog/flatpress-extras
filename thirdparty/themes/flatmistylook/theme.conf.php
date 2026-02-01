<?php
/*  
Theme Name: FlatMistyLoook
Theme URI: http://wpthemes.info/misty-look/
Description: Based on the Wordpress MistyLook 3.8RC Theme from http://wpthemes.info/misty-look/'. 
Version: 0.705
Author: sneakatron
Author URI: http://wpthemes.info/misty-look/
*/


	$theme['name'] = 'FlatMistyLook';
	$theme['author'] = 'sneakatron';
	$theme['www'] = 'http://wpthemes.info/misty-look/';
	$theme['description'] = 'Based on the Wordpress MistyLook 3.8RC Theme from http://wpthemes.info/misty-look/'.
							'Mobile 0.705';
	
	
	$theme['version'] = '0.705';
		
	$theme['style_def'] = 'style.css';
	$theme['style_admin'] = 'admin.css';
	
	$theme['default_style'] = 'flatmistylook';
	
	
	
	// Other theme settings
	
		// overrides default tabmenu
		// and panel layout
	remove_filter('admin_head', 'admin_head_action');
	
		// register widgetsets
	register_widgetset('right');
	register_widgetset('left'); 
	
?>
