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

	add_action('shutdown', 'plugin_twitter_shutdown');
	return 1;		
}

function plugin_twitter_get($count=1) {
	$tconf = plugin_getoptions('twitter');
	$username=$tconf['userid']; // set user name
	$format='json'; // set format
	$url = "http://api.twitter.com/1/statuses/user_timeline/{$username}.{$format}?count={$count}";

	$tweet_category=$tconf['category'];

	$data = @file_get_contents($url);

	if (!$data) return null;
	$tweet=json_decode($data); // get tweets and decode them into a variable

	if ($tweet[0]->id == @file_get_contents(PLUGIN_TWITTER_LAST)) return null;

	$date = strtotime($tweet[0]->created_at);

	file_put_contents(PLUGIN_TWITTER_LAST, $tweet[0]->id);
	@unlink(PLUGIN_TWITTER_LOCK);
	file_put_contents(PLUGIN_TWITTER_LOCK .'.tmp', time());
	rename(PLUGIN_TWITTER_LOCK .'.tmp', PLUGIN_TWITTER_LOCK);

	$content = preg_replace('{https?://\S*}', '[url]$0[/url]', $tweet[0]->text);

	return $entry = array(
		'subject' => $tweet[0]->id,
		'content' => $content,
		'date' => $date,
		'categories' => array($tweet_category),
		'author' => $tweet[0]->user->screen_name
	);
}

function plugin_twitter_getdelayed($count=1) {
	$last_access = @file_get_contents(PLUGIN_TWITTER_LOCK);
	$tconf = plugin_getoptions('twitter');
	if (time() - $last_access < $tconf['check_freq']) return;
	//echo time() - $last_access;
	return plugin_twitter_get($count);
}

function plugin_twitter_updatenow() {
	$e = plugin_twitter_get(1);
	if (!$e) return false;
	entry_save($e);
	return true;

}

function plugin_twitter_shutdown() {
	$e = plugin_twitter_getdelayed(1);
	if (!$e) return;
	
	entry_save($e);

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

			if ($_POST['check_now']) { $this->smarty->assign('success', plugin_twitter_updatenow()? 2:3); return 2; }
			
			
			plugin_addoption('twitter', 'userid', @$_POST['userid']);
			plugin_addoption('twitter', 'check_freq', (int)$_POST['check_freq']);
			plugin_addoption('twitter', 'category', (int)$_POST['category']);
			plugin_saveoptions('twitter');

			$this->smarty->assign('success', 1);

			return 2;
		}
		
	}

	admin_addpanelaction('plugin', 'twitter', true);

}

//plugin_twitter_shutdown();

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



