<?php
/*
 * Plugin Name: Download Counter
 * Plugin URI: https://www.flatpress.org
 * Description: Adds a download counter BBCode with URL link. Requires the activation of the BBCode plugin.
 * Author: Igor Kromin
 * Version: 1.3.1
 * Author URI: https://www.igorkromin.net/
 * Changelog:	Error-Pages in HTML5
 * 		        Instruction added
 * Change-Date:	21.11.2022 by Fraenkiman
 */

require_once '../../../defaults.php';
require_once '../../../fp-includes/core/core.fileio.php';

$downloadFile = urldecode($_GET["x"]);

// make sure the files outside of the download directory are not accessed
if (startsWith($downloadFile, '..') || startsWith($downloadFile, '/') || substr_count($downloadFile, '..') > 0) {
    error_403($downloadFile);
    die;
}

// files are assumed to be in fp-content/attachs directory
$download = '../../../'. ATTACHS_DIR . $downloadFile;

if (file_exists($download)) {    
    
    // if the file cannot be read show 403 error
    if (!is_readable($download)) {
        error_403($downloadFile);
        die;
    }
    
    $f = $download . '.dlctr'; // counter file is in the same location as the download file

    // read the contents of the counter file
    $v = io_load_file($f);
    if ($v === false) {
        $v = 0;
    }
    
    $v++; // increment download counter
    
    // update the counter and write to file
    // io_write_file was not working for some reason, so using this code now
    $file = @fopen($f, "w");
    if ($file) {
            if (flock($file, LOCK_EX))
            {
                fwrite($file, $v);
                flock($file, LOCK_UN);
                fclose($file);
                @chmod($filename, FILE_PERMISSIONS);
            }
    }

    // set some response headers to indicate that a file is being sent
    header("Content-type: application/force-download");
    header('Content-disposition: attachment; filename=' . basename($downloadFile));
    header("Content-Transfer-Encoding: Binary");
    header("Content-length: " . filesize($download));
    
    readfile($download); // send file to browser
}
else {
   error_404($downloadFile);
   die;
}

function startsWith($haystack, $needle)
{
    return !strncmp($haystack, $needle, strlen($needle));
}

function error_403($downloadFile) {
    header('HTTP/1.0 403 Forbidden');
    header('Content-type: text/html; charset=utf-8');
    echo '<!DOCTYPE HTML>';
    echo '<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">';
    echo '<head><title>Error 403 (Forbidden)</title></head>';
    echo '<body>';
    echo '<h1>Error 403</h1>';
    echo '<p><strong>The requested file cannot be sent.<br>';
    echo 'Datei: <span style="color:#FF0000">' . $downloadFile . '</span>';
    echo '</strong></p>';
    echo '<p><small><span style="color:#9c9c9c"><a href="../../../contact.php">Report</a> error, <a href="../../../search.php">search</a> in blog or back to <a href="../../../?">home page</a> .</span></small></p>';
    echo '</body>';
    echo '</html>';
}

function error_404($downloadFile) {
    header('HTTP/1.0 404 Not Found');
    header('Content-type: text/html; charset=utf-8');
    echo '<!DOCTYPE HTML>';
    echo '<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">';
    echo '<head><title>Error 404 (Not Found)</title></head>';
    echo '<body>';
    echo '<h1>Error 404</h1>';
    echo '<p><strong>The requested file could not be found.<br>';
    echo 'Datei: <span style="color:#FF0000">' . $downloadFile . '</span>';
    echo '</strong></p>';
    echo '<p><small><span style="color:#9c9c9c"><a href="../../../contact.php">Report</a> error, <a href="../../../search.php">search</a> in blog or back to <a href="../../../?">home page</a> .</span></small></p>';
    echo '</body>';
    echo '</html>';
}

?>
