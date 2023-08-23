<?php
/*
 * Plugin Name: Download Counter
 * Plugin URI: https://www.flatpress.org
 * Description: Adds a download counter bbcode with URL link to an <a href="./admin.php?p=uploader&action=default" title="Upload file/s">uploaded file</a>. <a href="./fp-plugins/downloadctr/doc_downloadctr.txt" title="Instruction" target="_blank">[Instruction]</a><br>Requires the activation of the BBCode plugin.
 * Author: Igor Kromin
 * Version: 1.3.2
 * Author URI: https://www.igorkromin.net/
 * Changelog: 	Fixed: The plugin crashes when the BBCode plugin is not enabled. Thanks to Arvid for the hint
 * Change-Date:	14.12.2022, by Fraenkiman
 * Changelog:	Error-Pages in HTML5
 * 		Instruction added
 * Change-Date:	21.11.2022, by Fraenkiman
 */

// this tells FlatPress to load the new tags at the very beginning
add_filter('init', 'plugin_bbcode_downloadctr_tag');
 
// define function. In this case we're creating an "inc" tag
 
function plugin_bbcode_downloadctr_tag() {
	if (!function_exists('plugin_bbcode_init')) {				// check if BBCode Plugin is enabled
		return;
	}
	$bbcode = plugin_bbcode_init(); 					// import the "global" bbcode object into current function
										// this way 
										// a) parsing is done only once, and by the official plugin
										// b) you create only ONE object, and therefore computation is quicker
	$bbcode->addCode (
                'download',                             			// tag name: this will go between square brackets
                'callback_replace_single',              			// type of action: we'll use a callback function
                'plugin_colorsin_downloadctr',      				// name of the callback function
                array('usecontent_param' => array ('default', 'name', 'size')), // supported parameters: "default" is [inc=valore]
                'inline',                               			// type of the tag, inline or block, etc
                array('listitem', 'block', 'inline'),  				// type of elements in which you can use this tag
                array());                        				// type of elements where this tag CAN'T go (in this case, none, so it can go everywhere)

	$bbcode->setCodeFlag ('download', 'closetag', BBCODE_CLOSETAG_FORBIDDEN); 	// a closing tag is forbidden (no [/tag])
}

function plugin_colorsin_calc($file) {

	$f = $file . '.dlctr'; 							// counter file is in the same location as the download file
	$v = io_load_file($f);
        
	if ($v === false) {
		$v = 0;
	}
        elseif ($v < 0) {
		// file was locked. Do not increase download.
		// actually on file locks system should hang, so
		// this should never happen
		$v = 0;
	}
	
	return $v;
}

function plugin_colorsin_downloadctr($action, $attr, $content, $params, $node_object) {

	if ($action == 'validate') {
		return true;
	}
        
        // files to download are assumed to be in the fp-content/attachs directory
        $file = $attr ['default'];
        $download = ATTACHS_DIR . $file;
        
        // set the name to the provided name or just use the file as the name
        if (isset($attr ['name'])) {
            $name = $attr ['name'];
        }
        else {
            $name = $attr ['default'];
        }
	
        // calculate the download count
        $ctr = plugin_colorsin_calc($download);
        $dl = ($ctr == 1) ? "Download" : "Downloads";
        
        // if the size attribute is set, display the size
        if (isset($attr ['size'])) {
            
            $fsize = filesize($download);
            
            // if there is an error, set file size to -1
            if ($fsize === FALSE) {
                $fsize = -1;
            }
            
            switch ($attr ['size']) {
                case "k":
                case "K":
                    $fs = round($fsize / 1024, 2) . "&nbsp;kB"; break;
                case "m":
                case "M":
                    $fs = round($fsize / (1024 * 1024), 2) . "&nbsp;MB"; break;
                case "g":
                case "G":
                    $fs = round($fsize / (1024 * 1024 * 1024), 2) . "&nbsp;GB"; break;
                case "t":
                case "T":
                    $fs = round($fsize / (1024 * 1024 * 1024 * 1024), 2) . "&nbsp;TB"; break;
                case "b":
                case "B":
                    $fs = $fsize . "&nbsp;B"; break;
            }
            
            $size = " [" . $fs . "]";
        }
        else {
            $size = "";
        }
        
	return '<!-- beginn of downloadctr -->
<a href="' . BLOG_BASEURL . PLUGINS_DIR . 'downloadctr/res/download.php?x=' . urlencode($file) . '">' . $name . '</a>' . $size . ' (' . $ctr . ' '. $dl . ')
<!-- end of downloadctr -->';
}

?>
