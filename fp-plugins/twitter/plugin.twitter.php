<?php
/*
Plugin Name: Twitter
Plugin URI: http://www.nowhereland.it/
Description: Twitter plugin, edit to config
Author: NoWhereMan
Version: 1.0
Author URI: http://www.nowhereland.it/
*/
//define('CACHE_DIR','./');
define('PLUGIN_TWITTER_LOCK', CACHE_DIR.'twitter.lock');
define('PLUGIN_TWITTER_LAST', CACHE_DIR.'twitter.last');
//define('PLUGIN_TWITTER_AUTHOR', true);
//define('PLUGIN_TWITTER_LOCK', CACHE_DIR.'twitter.lock');

function plugin_twitter_setup() {
	if (!plugin_getoptions('twitter')) {
		return -1;
	}

	return 1;		

}

function plugin_twitter_registerhooks() {
	global $smarty;
	$_o=plugin_twitter_object::get_instance();
	add_action('shutdown', array(&$_o, 'shutdown'));
	$smarty->register_modifier('istweet', array(&$_o, 'istweet'));
	$smarty->assign('plugin_twitter_userid', $_o->tconf['userid']);
}
plugin_twitter_registerhooks();



class plugin_twitter_object {

var $tconf;

function get_instance() {
	static $o;
	if ($o==null) $o=new plugin_twitter_object;
	return $o;
}

function plugin_twitter_object() {
	__construct();
}

function __construct() {
	$this->tconf = plugin_getoptions('twitter');
}

function istweet($category_array) {
	return in_array($this->tconf['category'], $category_array);
}



function urlcallback($matches) {
	
	$u = $matches[0];
	$replace="";
	
	// it is an image
	if (preg_match('/\.(jpg|png|gif)$/',$u)) {
		$replace = "\n\n[img=$u]\n\n";
	} else {
		$replace = "[url]{$u}[/url]";
	}
	
	return $replace;
}

function txttransforms($content) {

	$tconf = $this->tconf;

	$urlmatch = '{\bhttps?://\S+\b}';
	$imgmatch = '{\bhttps?://\S+\.(jpg|gif|png)\b}';


	// avoid double matching imgs/urls
	if ($tconf['include_imgs'] && $tconf['linkify_urls']) {
		$content = preg_replace_callback($urlmatch, array(&$this, 'urlcallback'), $content);
	} else {
		if ($tconf['include_imgs']) {
			$content = preg_replace($imgmatch, "\n\n[img=\$0]\n\n", $content);
		} else {
			$content = preg_replace($urlmatch, "[url]\$0[/url]", $content);
		}
	}
	
	if ($tconf['linkify_replies'])
		$content = preg_replace('{@(\S+)}', '[url=http://twitter.com/$1]$0[/url]', $content);
	
	if ($tconf['linkify_tags'])
		$content = preg_replace('{#(\S+)}', '[url=http://twitter.com/search?q=%23$1]$0[/url]', $content);
			
	//if ($tconf['oembed'])	
	
	return $content;

}


// from http://nadeausoftware.com/articles/2007/06/php_tip_how_get_web_page_using_curl
// code under OSI BSD
/**
 * Get a web file (HTML, XHTML, XML, image, etc.) from a URL.  Return an
 * array containing the HTTP server response header fields and content.
 */
function get_web_page( $url )
{
    $options = array(
        CURLOPT_RETURNTRANSFER => true,     // return web page
        CURLOPT_HEADER         => false,    // don't return headers
        CURLOPT_FOLLOWLOCATION => true,     // follow redirects
        CURLOPT_ENCODING       => "",       // handle all encodings
        CURLOPT_USERAGENT      => "spider", // who am i
        CURLOPT_AUTOREFERER    => true,     // set referer on redirect
        CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
        CURLOPT_TIMEOUT        => 120,      // timeout on response
        CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
    );

    $ch      = curl_init( $url );
    curl_setopt_array( $ch, $options );
    $content = curl_exec( $ch );
    $err     = curl_errno( $ch );
    $errmsg  = curl_error( $ch );
    $header  = curl_getinfo( $ch );
    curl_close( $ch );

    $header['errno']   = $err;
    $header['errmsg']  = $errmsg;
    $header['content'] = $content;
    return $header;
}


function get($count=1) {
$count=2;
	$tconf=$this->tconf;
	$username=$tconf['userid']; // set user name
	$format='json'; // set format
	$url = "http://api.twitter.com/1/statuses/user_timeline/{$username}.{$format}?count={$count}";
	$tweet_category=$tconf['category'];
	$replies = $tconf['replies'];

	$webdata = $this->get_web_page($url); 
	$data =  $webdata['content']; // file_get_contents($url);

	if (!$data) return null;
	$tweet=json_decode($data); // get tweets and decode them into a variable

	if (!$tweet) return null;

	if ($tweet[0]->id == @file_get_contents(PLUGIN_TWITTER_LAST)) return null;

	if ($replies && $tweet[0]->text[0]=='@') return null; // it is a reply
	
	

	$date = strtotime($tweet[0]->created_at);

	file_put_contents(PLUGIN_TWITTER_LAST, $tweet[0]->id);

	$content = $this->txttransforms($tweet[0]->text);
	

	return $entry = array(
		'subject' => $tweet[0]->id,
		'content' => $content,
		'date' => $date,
		'categories' => array($tweet_category),
		'author' => $tweet[0]->user->screen_name
	);
}

function getdelayed($count=2) {
	$tconf = $this->tconf;
	$last_access = @file_get_contents(PLUGIN_TWITTER_LOCK);
	if (time() - $last_access < 60*$tconf['check_freq']) return;
	
	@unlink(PLUGIN_TWITTER_LOCK);
	file_put_contents(PLUGIN_TWITTER_LOCK .'.tmp', time());
	rename(PLUGIN_TWITTER_LOCK .'.tmp', PLUGIN_TWITTER_LOCK);

	//echo time() - $last_access;
	return $this->get($count);
}

function updatenow() {
	$e = $this->get(2);
	if (!$e) return false;
	entry_save($e);
	return true;

}

function shutdown() {
	$e = $this->getdelayed(1);
	if (!$e) return;
	
	entry_save($e);

}

}

if (class_exists('AdminPanelAction')){

	class admin_plugin_twitter extends AdminPanelAction { 
		
		var $langres = 'plugin:twitter';
		
		function setup() {
			$this->smarty->assign('admin_resource', "plugin:twitter/admin.plugin.twitter");
			$this->smarty->assign('categories_all', entry_categories_get('defs'));
		}
		
		function main() {
			$twitterconf = plugin_getoptions('twitter');
			$this->smarty->assign('twitterconf', $twitterconf);
		}
		
		function onsubmit() {
			global $fp_config;

			$u = trim(@$_POST['userid']);
			if (!$u) { $this->smarty->assign('success', -2); return 2; }

			if (@$_POST['check_now']) {
				$_o = plugin_twitter_object::get_instance();
				$this->smarty->assign('success', $_o->updatenow()? 2:3); 
				return 2; 
			}
			
			
			plugin_addoption('twitter', 'userid', @$_POST['userid']);
			plugin_addoption('twitter', 'check_freq', (int)$_POST['check_freq']);
			plugin_addoption('twitter', 'category', (int)$_POST['category']);
			plugin_addoption('twitter', 'replies', (bool)@$_POST['replies']);
			
			plugin_addoption('twitter','linkify_replies', (bool)@ $_POST['linkify_replies']);
			plugin_addoption('twitter','linkify_tags', (bool) @$_POST['linkify_tags']);
			plugin_addoption('twitter','linkify_urls', (bool) @$_POST['linkify_urls']);
			plugin_addoption('twitter','include_imgs', (bool) @$_POST['include_imgs']);

			plugin_saveoptions('twitter');

			$this->smarty->assign('success', 1);

			return 2;
		}
		
	}

	admin_addpanelaction('plugin', 'twitter', true);

}



// (probably it will be removed/moved from here)
// credits for this function (which I assume to be public domain)
// http://www.php.net/manual/en/function.time.php#85481
 function distanceOfTimeInWords($fromTime, $toTime = -1, $showLessThanAMinute = false) {
 	$toTime = $toTime<0? time() : $toTime;
    $distanceInSeconds = round(abs($toTime - $fromTime));
    $distanceInMinutes = round($distanceInSeconds / 60);
        
        if ( $distanceInMinutes <= 1 ) {
            if ( !$showLessThanAMinute ) {
                return ($distanceInMinutes == 0) ? 'less than a minute' : '1 minute';
            } else {
                if ( $distanceInSeconds < 5 ) {
                    return 'less than 5 seconds';
                }
                if ( $distanceInSeconds < 10 ) {
                    return 'less than 10 seconds';
                }
                if ( $distanceInSeconds < 20 ) {
                    return 'less than 20 seconds';
                }
                if ( $distanceInSeconds < 40 ) {
                    return 'about half a minute';
                }
                if ( $distanceInSeconds < 60 ) {
                    return 'less than a minute';
                }
                
                return '1 minute';
            }
        }
        if ( $distanceInMinutes < 45 ) {
            return $distanceInMinutes . ' minutes';
        }
        if ( $distanceInMinutes < 90 ) {
            return 'about 1 hour';
        }
        if ( $distanceInMinutes < 1440 ) {
            return 'about ' . round(floatval($distanceInMinutes) / 60.0) . ' hours';
        }
        if ( $distanceInMinutes < 2880 ) {
            return '1 day';
        }
        if ( $distanceInMinutes < 43200 ) {
            return 'about ' . round(floatval($distanceInMinutes) / 1440) . ' days';
        }
        if ( $distanceInMinutes < 86400 ) {
            return 'about 1 month';
        }
        if ( $distanceInMinutes < 525600 ) {
            return round(floatval($distanceInMinutes) / 43200) . ' months';
        }
        if ( $distanceInMinutes < 1051199 ) {
            return 'about 1 year';
        }
        
        return 'over ' . round(floatval($distanceInMinutes) / 525600) . ' years';
}



