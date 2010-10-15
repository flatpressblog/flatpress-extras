<?php
/*
Plugin Name: Ping
Plugin URI: http://www.nowhereland.it/
Description: Send ping pings when posting stuff
Author: NoWhereMan
Version: 0.703
Author URI: http://www.nowhereland.it/
*/ 

define('PLUGIN_PING_DATA', CACHE_DIR . 'ping.dat');

function plugin_ping_form($string) {

	$lang = lang_load('plugin:ping');
	$l =& $lang['plugin']['ping'];
	
	$form = '<fieldset><legend>'.$l['pingback'].'</legend>'.
	'<p><input id="plugin-ping-ping" name="plugin-ping-tb" class="maxsize" type="text" value="autodiscovery" /></p>'.
	'<p>'. $l['hint']. '</p></fieldset>';

	$out = '';
	if (SYSTEM_VER < '0.814') {
		// oh noes I brokez my output
		$out = "</p>$form<p>";
	} else {
		$out = "$form";
	}

	echo $out;
}

function plugin_ping_autodiscovery($text) {
	$match=array();
	$total_matched = preg_match_all('@https?://(?:[-\w\.]+)+(?::\d+)?(?:/(?:[\w/_\.]*(?:\?\S+)?)?)?@', $text, $match);
	return $total_matched? $match[0] : array();
	
}

function plugin_ping_add_pending($id, $content) {
	
	if (!isset($_POST['plugin-ping-tb'])) return;
	$tb = trim($_POST['plugin-ping-tb']);
	$urls = array();
	if ($tb=='autodiscovery') {
		$urls = plugin_ping_autodiscovery($content['content']);
	} else {
		// normalize whitespace
		$tb = preg_replace('/[\s,]+/', ' ', $tb);
		$urls = explode(' ', $tb);
	}

	if ($urls)
		file_put_contents(PLUGIN_PING_DATA, serialize($urls));

}

function plugin_ping_send() {

	if (!file_exists(PLUGIN_PING_DATA)) return;
	$tburls = array();
	$_tburls = file_get_contents(PLUGIN_PING_DATA);
	unlink(PLUGIN_PING_DATA);
	if ($_tburls) $tburls = unserialize($_tburls);

	foreach ($tburls as $url) {
		// ping
		echo("PINGBACK TO $url\n");

	}

	echo("DONE");
}

if (defined('MOD_ADMIN_PANEL')) 
	add_action('wp_footer', 'plugin_ping_send');
add_action('publish_post', 'plugin_ping_add_pending', 10, 2);
add_filter('simple_edit_form', 'plugin_ping_form', 1);


