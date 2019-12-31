<?php
  
    require_once('defaults.php');
    require_once(INCLUDES_DIR.'includes.php');
    
    if (function_exists('system_init')) {
    	system_init();
    } else {
    	plugin_loadall();
    }
    
      header('Content-Type: text/plain; charset=utf-8');

    
    function toutf($str) {
        // comment this one and uncomment the following line if you're using SPB!
    	return $str;

        //return mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
    }

    error_reporting(E_ALL);

    $WP_PREFIX = 'wp_';
    
    $POSTID = 10;
    $COMMENTID = 10;
    
    $SQL_STRING = '';
    $q = new FPDB_Query(array('start'=>0, 'count'=>-1, 'fullparse'=>true), null);
    while($q->hasMore()) {
    
        list($id, $e) = $q->getEntry();
        
        $obj = new comment_indexer($id);
        $clist = $obj->getList();
        $ccount = count($clist);
        $date = date("Y-m-d H:i:s", $e['date']);

        
        $SQL_STRING = "INSERT INTO {$WP_PREFIX}posts (id, post_author, post_date, post_date_gmt, post_content, post_title, post_name, comment_count) ";
        $SQL_STRING .= "VALUES ({$POSTID}, 1, '{$date}', '{$date}', '" . addslashes(toutf(apply_filters('the_content', $e['content']))) . "', '" . 
                            addslashes(toutf($e['subject'])) ."', '";
        $SQL_STRING .= sanitize_title($e['subject']) ."', {$ccount}); \n";
        
        echo $SQL_STRING;
    
        foreach ($clist as $cid)  {
            $c = comment_parse($id, $cid);
            $cdate = date("Y-m-d H:i:s", $c['date']);
            $SQL_STRING = "INSERT INTO {$WP_PREFIX}comments (comment_id, comment_post_id, comment_content, comment_author, comment_date, comment_date_gmt) ";
            $SQL_STRING .= "VALUES ($COMMENTID, $POSTID, '" . /* in questo particolare caso */ addslashes(toutf(apply_filters('the_content', $c['content']))). "', '";
            $SQL_STRING .= addslashes(toutf($c['name'])) . "', '{$cdate}', '{$cdate}' ); \n";
        
            echo $SQL_STRING;
            
            $COMMENTID++;

        }
        
        
        $POSTID++;
    
    }

?>    