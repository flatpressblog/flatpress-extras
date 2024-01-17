<?php
/*
 * Plugin Name: MoreOnBlog 
 * Plugin URI: https://frank-web.dedyn.io
 * Type: Block
 * Description: The widget displays links to the other random posts on the blog. <a href="./fp-plugins/moreonblog/doc_moreonblog.txt" title="Instructions" target="_blank">[Instructions]</a>
 * Author: Igor Kromin
 * Version: 1.0.2
 * Author URI: http://www.igorkromin.net/
 * 
 * Changelog:
  * v1.0.2		Only show the widget when a single post is displayed, by Fraenkiman
 * Change-Date:	16.01.2024
 * v1.0.1		Added stylesheet and multilanguage support, by Fraenkiman
 * Change-Date:	20.02.2023
 * v1.0.0		Thanks for the template to Igor Kromin
 * Change-Date:	07.10.2014
 */

add_action('wp_head', 'plugin_moreonblog_head');
function plugin_moreonblog_head() {
	$pdir = plugin_geturl('moreonblog');
	echo '
	    <!-- start of MoreOnBlog -->
		<link rel="stylesheet" type="text/css" href="' . $pdir . 'res/moreonblog.css">
	    <!-- end of MoreOnBlog -->';
}

function plugin_moreonblog_widget() {
	global $fpdb, $fp_config, $lang;
	lang_load('plugin:moreonblog');
	$content = '';
	$q =& $fpdb->getQuery();
	if (($q && $q->single) || isset($fp_params ['entry'])) {
		$content = zzzzzx();
	}
	$widget = array();
	if (empty($content)) {
		return;
	} else {
		$widget ['subject'] = $lang ['plugin'] ['moreonblog'] ['other_posts'] . $fp_config ['general'] ['title'];
	}
	$widget ['content'] = $content;
	return $widget;
}

function zzzzzx() {
    $q = new FPDB_Query(array ('start' => 0, 'count' => -1, 'fullparse' => true), null);
    $entry = array();
   
    while ($q->hasMore()) {
        list ($id, $e) = $q->getEntry();
        $subj = $e ["subject"];
        $loc =  get_permalink($id); 
        array_push($entry, array ($subj, $loc));
    }
    $idx = (range(0, sizeof($entry)-1));
    shuffle($idx);
    $content = '<div class="moreonblog_outer">';
    // Note: There is a known issue with this plugin that the plugin does not work if there are less than 4 posts in a blog. There may be a simple solution.
    for ($i = 0; $i < 4; $i++) {
        $v = $idx [$i];
        $content = $content . '<div class="moreonblog_inner"><a href="' . $entry [$v] [1] . '">' . $entry [$v] [0] . '</a></div>';
    }
    $content = $content . '</div>';
        
    return $content;
}
register_widget('moreonblog', 'MoreOnBlog', 'plugin_moreonblog_widget');
?>
