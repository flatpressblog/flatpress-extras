<?php
/*
Plugin Name: ajaxadmin
Version: 1.0
Plugin URI: http://www.nowhereland.it
Description: add AJAX functionalities to some panels of the admin
Author: NoWhereMan (e.vacchi)
Author URI: http://www.nowhereland.it
*/

/*
 * Code goes here
 *
 */

add_action('admin_entry_write_head', array('plugin_ajaxadmin','entry_preview'));

class plugin_ajaxadmin {
	function entry_preview() { 
		$dir=plugin_getdir('ajaxadmin');
		echo '<script type="text/javascript" src="'.$dir.'res/admin.entry.write.js"></script>';
	}

}

