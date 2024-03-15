<?php 
/*
 * Plugin Name: CKEditor
 * Version: 1.0.1
 * Plugin URI: https://flatpress.org
 * Description: Use a CKEditor in entries and static pages. Requires BBcode plugin to <a href="./admin.php?p=plugin&action=bbcode" title="BBcode panel">allow inline HTML</a>. <a href="./fp-plugins/ckeditor/doc_ckeditor.txt" title="Instructions" target="_blank">[Instructions]</a>
 * Author: Fraenkiman
 * Author URI: https://frank-web.dedyn.io
 * Proof of Concept: Francisco Arocas @Franah (CDN-version), 20024 Marcus @DeltaLima (locally hosted version)
 * Licenze: GPLv2, LGPL and MPL (LICENSE.md)
 */
function plugin_ckeditor_head() {
	$plugin_dir = plugin_geturl('ckeditor');
	echo '
		<!-- BOF ckeditor toolbar -->
		<link rel="stylesheet" type="text/css" href="' . $plugin_dir . 'res/ckeditor.css">
		<script src="' . $plugin_dir . 'res/ckeditor.js"></script>
		<script src="' . $plugin_dir . 'res/adapters/jquery.js"></script>
		<!-- EOF ckeditor toolbar -->';
}

function load_ckeditor() {
	echo '
		<!-- BOF CKEditor Script-->
		<script>
		/**
		 * Init CKEditor as native jQuery integration
		 */
		$(document).ready(function() {
			$(\'#content\').ckeditor();
		});

		function setValueSave() {
			$(\'#content\').val($(\'input#save\').val());
		}

		function setValueSavecontinue() {
			$(\'#content\').val($(\'input#savecontinue\').val());
		}
		</script>
		<!-- EOF CKEditor Script -->';
}

add_action('wp_head', 'plugin_ckeditor_head', 10);
add_action('simple_edit_form', 'load_ckeditor', 10);
?>
