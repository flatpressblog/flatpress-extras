<?php
/*
Plugin Name: QuickGallery 
Version: 1.0
Plugin URI: http://wiki.flatpress.org
Description: Quick gallery plugin
Author: NoWhereMan
Author URI: http://www.flatpress.org
*/
 
// this will tell FlatPress to load the new tags at the very beginning 

add_filter('init', 'plugin_quickgallery_tags');
 
// here you define a function. In this case we're creating an acronym tag

function plugin_quickgallery_tags() {
        $bbcode = plugin_bbcode_init(); //import the "global" bbcode object into current function
                                         // this way 
                                         // a) parsing is done only once, and by the official plugin
                                         // b) you create only ONE object, and therefore computation is quicker
        $bbcode->addCode (
                    'gallery',  // tag name: this will go between square brackets
                    'callback_replace_single', // type of action: we'll use a callback function
                    'plugin_quickgallery_gallery', // name of the callback function
                    array('usecontent_param' => array ('default')), // supported parameters: "default" is [acronym=valore]
                    'inline', // type of the tag, inline or block, etc
                    array ('listitem', 'block', 'inline', 'link'), // type of elements in which you can use this tag
                    array ()  // type of elements where this tag CAN'T go (in this case, none, so it can go everywhere)
        );
 
        $bbcode->setCodeFlag ('acronym', 'closetag', BBCODE_CLOSETAG_FORBIDDEN); // a closing tag is forbidden (no [/tag])

}
 
// $content is the text between the two tags, i.e. [tag]CONTAINED TEXT[/tag] $content='CONTAINED TEXT'
// $attributes is an associative array where keys are the tag properties. default is the [tagname=value] property
 
function plugin_quickgallery_gallery($action, $attr, $content, $params, $node_object) { 
    if ($action == 'validate') {
        // not used for now
        return true;
    }

    global $lightbox_rel;

    $dir = $attr['default'];

    if (substr($dir, -1)!='/') $dir .= '/';

    $d = substr_replace ($dir, IMAGES_DIR, 0, 7 );

    $fs = new fs_filelister($d);

    $l = $fs->getlist();
    
    natsort($l);

    $imgattr = $attr;

    $lightbox_rel = sanitize_title($dir);

    $str = '<div class="img-gallery '.$lightbox_rel.'">';

    foreach ($l as $f) {
        $imgattr['default'] = $dir . $f; 
        $str .= do_bbcode_img($action, $imgattr, $content, $params, $node_object);
    }

    $lightbox_rel = null;

    return $str . '</div>';
}
 
