<?php

	include 'defaults.php';
	include INCLUDES_DIR . 'includes.php';

	
	// customize this
	define('CUTE_DIR', '../../cutenews/data/');

	// don't need to customize these
	define('CUTE_NEWS', CUTE_DIR . 'news.txt');
	define('CUTE_COMMENTS', CUTE_DIR . 'comments.txt');
	define('CUTE_CATEGORIES', CUTE_DIR . 'category.db.php');

	system_init();

	header('Content-Type: text/plain');


	if (!user_loggedin()) die('Please login to FP first!');

	
	if (!file_exists(CUTE_NEWS)) die('Cannot find CuteNews file');
	



function import_entries($file = CUTE_NEWS) {
	$fpver = system_ver();
	$f = fopen($file, 'r');

	echo "\nNow importing ENTRIES [$file] \n\n";
	while(false!== ( $line = fgets($f) ) ) {
		$contents = explode('|', $line);

		$textcontent = $contents[3];

		// if long post exists
		if (trim($contents[4])) {
			// concat excerpt and long posts, and put a [more] tag
			$textcontent .= "\n\n[more]\n\n" . $contents[4];
		} 
	
		$entry = array(
			'date'	=>$contents[0],
			'author'=>$contents[1], // assume author-id is the same
			'subject'	=>$contents[2],
			'content'=>$textcontent,
			'version'=>$fpver,
		);
		if ($line[6]) {
			$entry['categories'] = explode(',',$contents[6]);
		}

		$id = entry_save($entry);

		echo "{$entry['date']} ---> $id\n";
		
	}
	fclose($f);
	echo "\nDONE\n\n";
}


function import_comments($file = CUTE_COMMENTS) {
	$fpver = system_ver();
	echo "\nNow importing COMMENTS [$file]\n\n";

	$f = fopen($file, 'r');

	while(false!== ( $line = fgets($f) ) ) {
		
		$comment_list = explode('|>|', $line);
		
		// no comments: skip
		if (!trim($comment_list[1])) continue;

		$comments = explode('||', $comment_list[1]);


		foreach($comments as $cc) {
			if (!trim($cc)) continue;
			$comm = explode('|', $cc);
		echo "---{$comm[0]}--";
			$content = array(
				'date'	=>$comm[0],
				'name'	=>$comm[1], // assume author-id is the same
				'ip-address'=>$comm[3],
				'content'=>$comm[4],
				'version'=>$fpver,
			);
		
			if ($comm[2]!='none')
			$content['email'] = $comm[2];

			$entry_time = $comment_list[0];
			$entry_id = bdb_idfromtime('entry', $entry_time);
			$id = __comment_save($entry_id, $content);

			echo "$entry_id :: {$content['date']} ---> $id\n";

		}
		
	}

		
	fclose($f);
	
	echo "\n\nDONE\n\n";

}

function import_categories() {
	
	echo "Importing categories...";
	$categories = '';
	$f = fopen(CUTE_CATEGORIES, 'r');
	while(false!== ( $line = fgets($f) ) ) {
		if (!trim($line)) continue;
		list($id, $title) = explode('|', $line);

		$categories .= "$title :$id\n";
	}
	fclose($f);
	io_write_file(CONTENT_DIR.'categories.txt', trim($categories));
	entry_categories_encode();

	echo " DONE\n\n";

}



// stupid me there is no date override for comments!
if (SYSTEM_VER <= '0.813') {
	function __comment_save($id, $comment) {
		
		comment_clean($comment);
		
		$comment = array_change_key_case($comment, CASE_UPPER);
		
		$comment_dir = bdb_idtofile($id,BDB_COMMENT);
		
		if(!isset($comment['DATE'])) 
			$comment['DATE'] = date_time();

		$id = bdb_idfromtime(BDB_COMMENT, $comment['DATE']);
		$f = $comment_dir . $id . EXT;
		$str = utils_kimplode($comment);
		if (io_write_file($f, $str))
			return $id;
		
		
		return false;
	}
} else {
	function __comment_save($id, $comment) {
		return comment_save($id,$comment);
	}
}
		
	



class import_entries_class extends fs_filelister {
	function import_entries_class() {
		import_entries(CUTE_NEWS);
		parent::fs_filelister(CUTE_DIR.'archives/');
	}
	function _checkFile($directory, $file) {
		$f = "$directory/$file";
		if (!is_dir($f)) {
			if (false !== strpos($file, 'news')) {
				import_entries($f);
			}
		}
		return 0;
	}
}

class import_comments_class extends fs_filelister {
	function import_comments_class() {
		import_comments(CUTE_COMMENTS);
		parent::fs_filelister(CUTE_DIR.'archives/');
	}
	function _checkFile($directory, $file) {
		$f = "$directory/$file";
		if (!is_dir($f)) {
			if (false !== strpos($file, 'comments')) {
				import_comments($f);
			}
		}
		return 0;
	}
}


import_categories();
new import_entries_class();
new import_comments_class();

echo "PROCESS COMPLETE. Enjoy FlatPress!";