<?php
/*
 * Plugin Name: Markdown
 * Version: 1.2.1
 * Plugin URI: https://github.com/flatpressblog/flatpress-extras/tree/master/fp-plugins/markdown
 * Description: Provides <a href="http://daringfireball.net/projects/markdown/">markdown</a> markup in posts
 * Author: Vasily Polovnyov
 * Author URI: https://github.com/vast
 */

// Openning and closing smart double-quotes.
// define( 'SMARTYPANTS_SMART_DOUBLEQUOTE_OPEN', "&#171;" );
// define( 'SMARTYPANTS_SMART_DOUBLEQUOTE_CLOSE', "&#187;" );
define('XML_HTMLSAX3', plugin_getdir('markdown') . '/inc/');

require_once plugin_getdir('markdown') . '/inc/MarkdownExtra.inc.php';
require_once plugin_getdir('markdown') . '/inc/SmartyPantsTypographer.inc.php';

// require plugin_getdir('markdown') . '/inc/safehtml.php';
function pl_markdown($text) {
	$my_html = \Michelf\MarkdownExtra::defaultTransform($text);
	$my_html = \Michelf\SmartyPantsTypographer::defaultTransform($my_html);
	return $my_html;
}

function pl_markdown_comment($text) {
	$my_html = \Michelf\Markdown::defaultTransform($text);
	$my_html = \Michelf\SmartyPants::defaultTransform($my_html);
	return $my_html;
}

// markdown will do it, so remove them
remove_filter('the_content', 'wpautop');
remove_filter('the_content_rss', 'wpautop');
remove_filter('the_excerpt', 'wpautop');
remove_filter('comment_text', 'wpautop');
remove_filter('comment_text', 'make_clickable');

// remove wptexturize -- this work is already done by smartypants
remove_filter('the_content', 'wptexturize');
remove_filter('the_content_rss', 'wptexturize');
remove_filter('the_excerpt', 'wptexturize');

add_filter('the_content', 'pl_markdown', 1);
add_filter('the_content_rss', 'pl_markdown', 1);

add_filter('comment_text', 'pl_markdown_comment');
