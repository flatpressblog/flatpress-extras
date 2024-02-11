<?php
/*
 * Plugin Name: SyntaxHighlighter-NG
 * Version: 1.0.3-fp
 * Plugin URI: https://github.com/flatpressblog/flatpress-extras/tree/master/fp-plugins
 * Description: <a href="https://git.la10cy.net/DeltaLima/flatpress-plugin-syntaxhighlighter-ng/">SyntaxHighlighter-NG</a> (a fork from DeltaLima 2023) using now <a href="https://prismjs.com">prism.js</a> <a href="./fp-plugins/syntaxhighlighter/doc_syntaxhighlighter.txt" title="Instructions" target="_blank">[Instructions]</a>
 * Author: 2005 NoWhereMan,<br> 2023 DeltaLima
 * Author URI: https://flatpress.org
 * License: MIT
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the “Software”), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 */


function plugin_syntaxhighlighter_add($lang = null) {
	static $languages = array ();
	
	$pdir = plugin_geturl('syntaxhighlighter');

	// create array containing the used languages
		$languages[] = "{$lang}";
	// remove unique
		$languages = array_unique($languages);

	return $languages;
}


function plugin_syntaxhighlighter_head($theme) {

	global $fp_config;
	$style = $fp_config ['general'] ['style'];

	$config = include ('config.php');
	$theme = $config ['theme'];
	$pdir = plugin_geturl('syntaxhighlighter');

	echo '
	<!-- start of prism.js header -->';
	
	// use FlatPress-Leggero $style when selected
	$search = array ('flatmaas-rev', 'leggero', 'leggero-v2');
	$result = array_search($style, $search);

	if ($result !== false) {
		echo '
	<link rel="stylesheet" type="text/css" href="' . $pdir . 'res/prism-' . $style . '.css">
	<link rel="stylesheet" type="text/css" href="' . $pdir . 'res/ubuntu-mono.css.php">';
		} else {
	// if another FlatPress $style is selected, then use $theme from $config
			echo '
	<link rel="stylesheet" type="text/css" href="' . $pdir . 'res/prism-' . $theme . '.css">';
		}	
	echo '
	<link rel="stylesheet" type="text/css" href="' . $pdir . 'res/prism.plugins.css">
	<!-- end of prism.js header -->
	';
}

add_action('wp_head', 'plugin_syntaxhighlighter_head');


function plugin_syntaxhighlighter_foot() {

	global $fp_config;
	$style = $fp_config ['general'] ['style'];

	$config = include ('config.php');
	// convert the returned array into a json one, to have an easier time
	// giving it to the javascript below
	$used_languages = json_encode(plugin_syntaxhighlighter_add());
	
	$pdir = plugin_geturl('syntaxhighlighter');
	// javascript part
	
	echo '
	<!-- start of prism.js footer -->
	<script src="' . $pdir . 'res/prism.' . $config ['size'] . '.js"></script>
	<script src="' . $pdir . 'res/syntaxhighlighter-ng.js"></script>
	<script>
		/**
		 * call wrap_pre_tags() from syntaxhighlighter-ng.js
		 */
		var used_languages = ' . $used_languages . ';';
	// show line numbers when Leggero $style is selected
	$search = array ('flatmaas-rev', 'leggero', 'leggero-v2');
	$result = array_search($style, $search);

	if ($result !== false) {
		echo '
		var enable_line_numbers = true;';
		} else {
	// if another FlatPress $style is selected, then use $theme from $config
			echo '
		var enable_line_numbers = ' . $config ['line-numbers'] . ';';
		}	
	echo '
		wrap_pre_tags(used_languages, enable_line_numbers);
	</script>
	<!-- end of prism.js footer -->
';
}

add_action('wp_footer', 'plugin_syntaxhighlighter_foot');

?>
