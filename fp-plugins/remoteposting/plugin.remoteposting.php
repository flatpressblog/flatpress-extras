<?php
/*
Plugin Name: RemotePosting
Plugin URI: http://www.flatpress.org/
Description: Implements some methods to allow remote posting (using Windows Live Writer, Scribefire and such)
Author: NoWhereMan
Version: 1.4
Author URI: http://www.nowhereland.it/
*/ 
 
function plugin_remoteposting_head() {
	$url = plugin_geturl('remoteposting');
	echo '<link rel="EditURI" type="application/rsd+xml" title="RSD" href="'.BLOG_BASEURL.'?xmlrpc&amp;rsd" />
	<link rel="wlwmanifest" type="application/wlwmanifest+xml" href="'.$url.'res/wlwmanifest.xml" /> ';
	
}

function plugin_remoteposting_backport() {
	function entry_categories_list() {
		if (!$string = io_load_file(CONTENT_DIR . 'categories.txt'))
			return false;

			$lines = explode("\n", trim($string));
			$idstack = array(0);
			$indentstack=array();


			// $categories = array(0=>null);
			$lastindent = 0;
			$lastid = 0;
			$parent = 0;

			$NEST = 0;

			foreach ($lines as $v) {

				$vt = trim($v);

				if (!$vt) continue;

				$text='';
				$indent = utils_countdashes($vt, $text);
					
				$val = explode(':', $text);
				
				$id     = trim($val[1]);
				$label  = trim($val[0]);

				// echo "PARSE: $id:$label\n";
				if ($indent > $lastindent) {
					// echo "INDENT ($indent, $id, $lastid)\n";
					$parent = $lastid;
					array_push($indentstack, $lastindent);
					array_push($idstack, $lastid);
					$lastindent = $indent;
					$NEST++;
				} elseif ($indent < $lastindent) {
					// echo "DEDENT ($indent)\n";
					do {
						$dedent = array_pop($indentstack);
						array_pop($idstack);
						$NEST--;
					} while ($dedent > $indent);
					if ($dedent < $indent) return false; //trigger_error("failed parsing ($dedent<$indent)", E_USER_ERROR);
					$parent = end($idstack);
					$lastindent = $indent;
					$lastid = $id;
				}

					$lastid = $id;
					// echo "NEST: $NEST\n";
				

				$categories[ $id ] = $parent;
			
			}

			return $categories;

	}

}


function plugin_remoteposting_handle() {
	global $fp_config, $fp_params;
	$url = plugin_getdir('remoteposting');

	if ( ! defined('MOD_INDEX') ) return;

	if ( isset($_GET['xmlrpc']) ) {
	
		if (SYSTEM_VER <= '0.813')
			plugin_remoteposting_backport();
		
		include $url.'inc/class-IXR.php';
		include $url.'inc/xmlrpc.php';	
		exit();
	}
	
}
 
add_action('wp_head', 'plugin_remoteposting_head');
add_action('init', 'plugin_remoteposting_handle');


 
?>
