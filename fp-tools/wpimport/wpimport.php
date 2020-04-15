<?php
/**
* This is a script that imports content from Wordpress to FlatPress.
* It was written by Edoardo Vacchi at 2012/07/27.
* Please notice that categories are not imported as of WP3.0; if you want to improve the following code, feel free.  
* [[nowhereman@flashmail.com|Edoardo Vacchi]] 2012-07-27 07:09
* 
* =====================================================================================
* Arvid Zimmermann, 2020-04-15: 
* THIS SCRIPT IS CONSIDERED DEPRECATED AND IS UNTESTED WITH CURRENT WP AND FP VERSIONS!
* In its current state, it's more a reminder and codebase for a new wpimport script.
* =====================================================================================
*/
 
 
    require_once('defaults.php');
    require_once(INCLUDES_DIR.'includes.php');
 
	// first time execute with debug = true;
	define('DEBUG', true);
	define('ISO_TO_UTF', true);
	define('SKIP_CATEGORIES', true);

    if (function_exists('system_init')) {
    	system_init();
    } else {
    	plugin_loadall();
    }
 
      header('Content-Type: text/plain; charset=utf-8');
 
    function toutf($str) {
		return ISO_TO_UTF?  mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1') :$str;
    }
    
    function cat_tree($arr, $subtree, $level) {
    	$string = '';
    	$pad = str_repeat('--', $level);
    	foreach($subtree as $v) {
    		$string .= "{$pad}{$v[1]}:{$v[0]}\n";
    		if (isset($arr[$v[0]]))
    			$string .= cat_tree($arr, $arr[$v[0]], $level+1);
    	}
    	return $string;
    }
 
    error_reporting(E_ALL);
 
    $WP_PREFIX = 'wp_';
 
    mysql_connect('localhost', 'root', '') or die('Cannot connect to DB');
    mysql_select_db('wordpress');
 	

 	if (!SKIP_CATEGORIES) {
 	
 	$cats = mysql_query('SELECT * FROM wp_categories ORDER BY category_parent')
 	 	or die(mysql_error());


 	$cat_arr = array();
	while (false !== ($cat = mysql_fetch_assoc($cats))) {
		$cat_arr[ $cat['category_parent'] ][] = array($cat['cat_ID'], $cat['cat_name']);
	}
	
	echo "\n----8<------- categories --------\n";
	$string = cat_tree($cat_arr, $cat_arr[0], 0);
	
	if ($cat_arr && !DEBUG) {
		io_write_file(CONTENT_DIR . 'categories.txt', $string);
		entry_categories_encode();
		
	}
	
	echo $string;
	echo "\n----8<---------------------------\n";
	} // SKIP CATEGORIES

	$q = mysql_query('SELECT wp_posts.*, wp_users.user_login FROM wp_posts JOIN wp_users ON post_author = wp_users.ID')
 	 	or die(mysql_error());
 
	while(false !== ( $arr = mysql_fetch_assoc( $q ) ) ) {
		$cats = array();
		
 	 	if (! SKIP_CATEGORIES ) {
		$ccq = mysql_query('SELECT category_id FROM wp_post2cat WHERE post_id = '.$arr['ID'])
 	 	    or die(mysql_error());

		while (false !== ($cc = mysql_fetch_assoc($ccq))) $cats[] = $cc['category_id'];
                }
		$entry = array(
			'subject' 	=> toutf($arr['post_title']),
			'content' 	=> toutf($arr['post_content']),
			'date' 		=> strtotime($arr['post_date_gmt']),
			'author'	=> $arr['user_login']
		);
		if ($cats)
			$entry['categories'] = $cats;

		echo "SAVE: {$entry['subject']} ({$arr['ID']} => {$entry['date']}) \n";
		$id = null;
		if (!DEBUG) { 
			if ($arr['post_status']=='static') {
				$id = static_save($entry, sanitize_title($arr['post_name']));
			} else {
				$id = entry_save($entry);
			}
		}
 
		if (!$id && !DEBUG)
			trigger_error("FAIL: could not save '{$entry['subject']} ({$carr['comment_ID']} => {$comment['date']}) \n", E_USER_ERROR);
 
		if ($arr['post_status']=='static') {
			echo "\tCOMM in static not yet supported: skipping...\n";
			continue;
		}
 
		$cq = mysql_query('SELECT * FROM wp_comments WHERE comment_post_ID = '.$arr['ID'])
   	 	      or die(mysql_error());

		while (false !== ( $carr = mysql_fetch_assoc($cq) ) ) {
			$comment = array(
				'name'	=> toutf($carr['comment_author']),
				'email' => $carr['comment_author_email'],
				'content' => toutf($carr['comment_content']),
				'date'	=> strtotime($carr['comment_date_gmt']),
			);
			if ($carr['comment_author_url'])
				$comment['url'] = $carr['comment_author_url'];
			if ($carr['user_id']) /* non sono sicuro questa mia interpretazione sia corretta */
				$comment['loggedin'] = true;
 
			echo "\tCOMM SAVE: {$comment['name']} ({$carr['comment_ID']} => {$comment['date']}) \n";
			
			if (!DEBUG) {
				$comment = array_change_key_case($comment, CASE_UPPER);
				$comment_dir = bdb_idtofile($id,BDB_COMMENT);
				$id = bdb_idfromtime(BDB_COMMENT, $comment['DATE']);
				$f = $comment_dir . $id . EXT;
				$str = utils_kimplode($comment);
				io_write_file($f, $str);
			}
 
		}
 
	}
	mysql_close();
	echo "\nDONE\nIf no errors were encountered, set DEBUG to false and restart.\n"
