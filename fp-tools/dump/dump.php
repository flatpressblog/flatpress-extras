<?php

    header('Content-Type: text/plain');

    require_once('defaults.php');
    require_once(INCLUDES_DIR.'includes.php');
    
    class dump extends fs_filelister {
        
        var $_directory = CONTENT_DIR;
	     function _checkFile($d,$f) {
		
            $p="$d/$f";
			
			echo substr(sprintf('%o', fileperms($p)), -4), "\t", $p ;
			
            if (is_dir($p)) {
				echo "\tIS_DIR: entering\n\n";
				return 1;
            } else {
				$s = io_load_file($p);
				
				if ((strpos( $p, '.gz' ) !== false )  ) {
					echo " [ FOUND GZ ] ";
					$s = gzinflate(substr($s, 10 ));
				}
				
				
				if ((strpos($f, 'entry') || strpos($f, 'entry')) && (strpos($s, 'VERSION|') === false)) {
					echo " [ VERY OLD FORMAT ] ";
				}
				
				if (strpos($s, 'relatedlink')!==false)
					echo " [ relatedlink INVALID KEY ] ";
				if (strpos($s, '||')!==false) {
					echo " [ BLANK KEY, dumping ] \n";
					echo wordwrap($s, 80);
					echo "\n";
				}

			
			}
			
			echo "\n";
        
        }
    
    }

	echo "======= FlatPress db dump =======\n\n" ;
	echo "Listing...\n";
	
	
    $o = new dump;
    echo "\n\nFINISHED";

?>