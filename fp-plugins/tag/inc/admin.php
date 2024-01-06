<?php

/**
 * This class removes the cache files for related entries.
 */
class tag_relted_remover extends fs_filelister {

	/**
	 * Constructor: it calls the constructor of parent class
	 * and it sets the directory to list.
	 */
	function __construct() {
		parent::__construct(CACHE_DIR);
	}

	/**
	 * This function checks the files and deletes them.
	 *
	 * @param string $directory:
	 *        	The directory of the file to check
	 * @param string $file:
	 *        	The file name
	 * @return integer: See fs_filelister class
	 */
	function _checkFile($directory, $file) {
		$f = $directory . $file;
		if (fnmatch('tag-related*.tmp', $file)) {
			@unlink($f);
		}
		return 0;
	}

}

/**
 * This class manages all actions in the Admin Panel
 * that are useful for the tag plugin.
 */
class plugin_tag_admin {

	# See plugin_tag
	var $use_rewrite = false;

	# The tag database, null for now
	var $tagdb = null;

	# The tag's entry utilites, null for now
	var $entry = null;

	/**
	 * This is the content before that tags are stripped
	 * by the simple form functions.
	 *
	 * @var string
	 */
	var $simplecontent = '';

	/**
	 * plugin_tag_admin
	 *
	 * It manages hooks.
	 *
	 * @param
	 *        	object &$tagdb: the tag database object
	 * @param
	 *        	object &$entry: the tag entry object
	 */
	function __construct(&$tagdb, &$entry) {
		$this->tagdb = &$tagdb;
		$this->entry = &$entry;
		add_filter('publish_post', array(
			&$this,
			'entry_save'
		), 5, 2);
		add_filter('delete_post', array(
			&$this,
			'entry_delete'
		));
		add_filter('wp_footer', array(
			&$this,
			'purge_cache'
		));

		if (!defined('MOD_ADMIN_PANEL') && version_compare(SYSTEM_VER, '0.1010', '>=') == 1) {
			return;
		}

		add_filter('simple_edit_form', array(
			&$this,
			'simple'
		));
		add_filter('admin_entry_write_onsave', array(
			&$this,
			'simpleadd'
		));
		add_filter('admin_entry_write_onsavecontinue', array(
			&$this,
			'simpleadd'
		));
		add_filter('admin_entry_write_main', array(
			&$this,
			'simpleremove'
		));
		add_filter('admin_entry_write_onsubmit', array(
			&$this,
			'simpleremove'
		));
		add_action('wp_head', array(
			&$this,
			'simplestyle'
		), 5);
		add_action('init', array(
			&$this,
			'simpleajax'
		));
	}

	/**
	 * entry_save
	 *
	 * Function called on entry_save hook.
	 * It updates the tags database
	 *
	 * @param string $id:
	 *        	the entry id
	 * @param array $array:
	 *        	the entry array
	 * @returns boolean true (always)
	 */
	function entry_save($id, $array) {
		$toremove = array();

		# Get the tags of the entry
		$this->entry->tag_list($array ['content']);
		$tags = $this->entry->tags;

		# If the entry already exists...
		if (entry_exists($id)) {
			# Get old tags
			$entry = entry_parse($id);
			$this->entry->tag_list($entry ['content']);
			$old_tags = $this->entry->tags;
			# Get tags to remove
			$toremove = array_values(array_diff($old_tags, $tags));
			# Don't do anything with tags that there were already
			$tags = array_values(array_diff($tags, $old_tags));
		}

		# Add tags
		for($i = 0; $i < count($tags); $i++) {
			$file = $this->tagdb->tagfile($tags [$i]);
			$this->tagdb->open_file($file);
			$this->tagdb->files [$file] [$tags [$i]] [] = $id;
		}

		# Remove old tags
		for($i = 0; $i < count($toremove); $i++) {
			$file = $this->tagdb->tagfile($toremove [$i]);
			$f = $this->tagdb->open_file($file);

			if (!isset($f [$toremove [$i]])) {
				continue;
			}

			$k = array_search($id, $f [$toremove [$i]]);
			if (isset($f [$toremove [$i]] [$k])) {
				unset($f [$toremove [$i]] [$k]);
			}

			# If tag hasn't entries, remove it
			$f [$toremove [$i]] = array_values($f [$toremove [$i]]);
			if (!count($f [$toremove [$i]])) {
				unset($f [$toremove [$i]]);
			}

			$this->tagdb->files [$file] = $f;
		}

		# # CRITICAL!! It saves the work
		$this->tagdb->save_all();

		# If we use rewrite, we have to rebuild cache.
		if ($this->use_rewrite) {
			$this->tagdb->rewriteCache(true);
		}

		# We don't make immediately the cache of widgets:
		# maybe the tag widget is not used anymore.
		if (file_exists(CACHE_DIR . 'tag-widget.tmp')) {
			@unlink(CACHE_DIR . 'tag-widget.tmp');
		}
		$remover = new tag_relted_remover();
		$remover->getList();

		# Clean the cache of ajax
		if (file_exists(CACHE_DIR . 'tag-ajax.tmp')) {
			@unlink(CACHE_DIR . 'tag-ajax.tmp');
		}

		return true;
	}

	/**
	 * entry_delete
	 *
	 * This function is called by hook entry_delete.
	 * It updates the tag database
	 *
	 * @param string $id:
	 *        	The id of the entry that is being deleted.
	 * @returns boolean true (always)
	 */
	function entry_delete($id) {
		# D'oh! We need to find in the database because entry doesn't exist anymore!
		# So ugly function, a linear parse of all tags! Oh my...
		$lister = new tag_lister();
		$list = $lister->_list;
		if (!count($list)) {
			# There is no tag, let's stop here
			return true;
		}

		# Very similar to entry_save
		foreach ($list as $file) {
			$f = $this->tagdb->open_file($file);
			if (!count($f)) {
				continue;
			}
			foreach ($f as $tag => $entries) {
				$k = array_keys($entries, $id);
				if (@isset($entries [$k [0]])) {
					unset($this->tagdb->files [$file] [$tag] [$k [0]]);
				}
				if (count($this->tagdb->files [$file] [$tag]) == 0) {
					unset($this->tagdb->files [$file] [$tag]);
				}
			}
		}

		$this->tagdb->save_all();

		if ($this->use_rewrite) {
			$this->tagdb->rewriteCache(true);
		}

		# See at lines 127-128
		if (file_exists(CACHE_DIR . 'tag-widget.tmp')) {
			@unlink(CACHE_DIR . 'tag-widget.tmp');
		}
		$remover = new tag_relted_remover();
		$remover->getList();

		return true;
	}

	/**
	 * purge_cache
	 *
	 * This function is called on hook wp_footer.
	 * It remake the cache of the tags when you delete
	 * the cache of templates.
	 */
	function purge_cache() {
		if (PLUGIN_TAG_NOCACHE) {
			return false;
		}

		if (empty($_GET ['do'])) {
			return false;
		}

		# Just if we are in mantain panel
		if (ADMIN_PANEL == 'maintain' && $_GET ['do'] == 'purgetplcache') {
			fs_delete_recursive(PLUGIN_TAG_DIR);
			if (!is_dir(PLUGIN_TAG_DIR)) {
				@fs_mkdir(PLUGIN_TAG_DIR);
			}
			# Rebuild cache
			$this->tagdb->makeCache($this->entry);
			return true;
		}

		return false;
	}

	/**
	 * This function is the callback for simple_edit_form hook.
	 */
	function simple() {
		global $lang, $smarty;
		if (!isset($lang ['admin'] ['plugin'] ['tag'])) {
			$lang = lang_load('plugin:tag');
		}

		// Get the simple tags
		if (!empty($this->simplebody)) {
			$entry = array(
				'entry' => $this->simplebody
			);
		} else {
			$entry = $smarty->getTemplateVars('post');
		}
		if (empty($this->simplebody)) {
				$entry = array(
				'content' => 'content'
			);
			$entry = $entry ['content'];
			$entry = $this->entry->tag_list($entry);
		}

		$tags = $this->entry->tags;
		if (!empty($_POST ['taginput'])) {
			$tags = array_merge((array) $tags, explode(',', $_POST ['taginput']));
		}
		$tags = array_map('trim', $tags);
		$tags = array_unique($tags);
		$tagsimple = implode(', ', $tags);

		$smarty->assign('taglang', $lang ['admin'] ['plugin'] ['tag']);
		$smarty->assign('tags_simple', $tagsimple);
		$smarty->display('plugin:tag/tagsimple');

		return true;
	}

	/**
	 * This function prints the stylesheet of the template
	 */
	function simplestyle() {
		if (ADMIN_PANEL == 'entry') {
			if (ADMIN_PANEL_ACTION == 'write') {
				$h = plugin_geturl('tag') . 'res/admin.css';
				echo '<link rel="stylesheet" type="text/css" href="' . $h . "\">\n";
			}
		}
	}

	/**
	 * This function reads the tags to the textarea.
	 */
	function simpleadd() {
		if (empty($_POST ['taginput'])) {
			return;
		}

		$tags = $_POST ['taginput'];
		if (substr($tags, -1) == ',') {
			$tags = substr($tags, 0, -1);
		}
		$tags = explode(',', $tags);
		$tags = array_filter($tags);

		$cont = $_POST ['content']; //
		$cont = $this->entry->tag_list($cont); //
		$tags = array_merge($tags, $this->entry->tags);
		$tags = array_map('trim', $tags);
		$tags = array_unique($tags);

		$tags = implode(', ', $tags);
		$cont = trim($cont);
		$cont .= "\n[tag]{$tags}[/tag]";

		$_POST ['content'] = $cont;
	}

	/**
	 * This function removes [tag] from the content.
	 *
	 * @param string $content:
	 *        	The entry content
	 * @return string: $content modified
	 */
	function simpleremove() {
		global $smarty;

		$post = $smarty->getTemplateVars('post');
		$content = @$post ['content'];

		if (empty($content)) {
			return;
		}

		$this->simplebody = $content;
		$content = preg_replace('/\[tag\](.*)\[\/tag\]/im', '', $content);

		$post ['content'] = $content;
		$smarty->assign('post', $post);
	}

	/**
	 * This function handles the ajax function of the plugin.
	 */
	function simpleajax() {
		if (@$_GET ['ajaxtag'] != 'list') {
			return;
		}

		if (empty($_GET ['tag'])) {
			die();
		}

		// Make the list
		if (file_exists($f = CACHE_DIR . 'tag-ajax.tmp') && (filemtime($f) - time()) < 3600) {
			include $f;
		} else {
			$lister = new tag_lister();
			$tags = array_keys($lister->makeTagList());
			natcasesort($tags);
			system_save($f, array(
				'tags' => $tags
			));
		}

		$suggs = array();
		$tag = strtolower($_GET ['tag']);
		$tagl = array_map('strtolower', $tags);
		foreach ($tagl as $key => $val) {
			if ($tag == substr($val, 0, strlen($tag))) {
				$tmp = wp_specialchars($tags [$key]);
				$suggs [] = '<b>' . substr($tmp, 0, strlen($tag)) . '</b>' . substr($tmp, strlen($tag));
			}
		}

		if (count($suggs) > 10) {
			// Only ten suggestions
			$suggs = array_slice($suggs, 0, 10);
		}

		$sugghtml = '<ul>';
		foreach ($suggs as $sugg) {
			$sugghtml .= "<li>{$sugg}</li>\n";
		}
		$sugghtml .= '</ul>';

		if (count($suggs) == 0) {
			$sugghtml = '';
		}
		die ($sugghtml);
	}

}
?>
