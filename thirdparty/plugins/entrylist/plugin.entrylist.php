<?php
/**
 * Plugin Name: Entry List
 * Version: 1.0.2
 * Plugin URI: http://www.vdfn.altervista.org/redirect/plugin_entrylist.html
 * Description: This plugin add the [entrylist] tag to make the list of the entries. It needs BBCode.
 * Author: Piero VDFN
 * Author URI: http://www.vdfn.altervista.org/
 */

// Turn off all error reporting
error_reporting(0);

/**
 * If a page is often visited it's better making a cache.
 */
define('ENTRYLIST_CACHEFILE', CACHE_DIR.'plugin_entrylist_tag.txt');

/**
 * This class is the main class of the entrylist plugin.
 *
 * @see The description of the plugin. 
 */
class plugin_entrylist {

	/**
	 * This is the year of the list.
	 *
	 * @var integer
	 */
	var $year = 0;

	/**
	 * This is the month of the list.
	 *
	 * @var integer
	 */
	var $month = 0;

	/**
	 * This is the day of the list.
	 *
	 * @var integer
	 */
	var $day = 0;

	/**
	 * This is the format of the year.
	 * It must be compatible with date_format.
	 *
	 * @var string
	 */
	var $fyear = '%Y';

	/**
	 * This is the format of the month.
	 * It must be compatible with date_format.
	 *
	 * @var string
	 */
	var $fmonth = '%B';

	/**
	 * This is the format of the day.
	 * It must be compatible with date_format.
	 *
	 * @var string
	 */
	var $fday = '%d';

	/**
	 * This function is the constructor.
	 */
	function __construct() {
		add_filter('bbcode_init', array(&$this, 'hook'));
		add_filter('publish_post', array(&$this, 'delete'), 10, 1);
		add_filter('delete_post', array(&$this, 'delete'), 10, 1);
	}

	/**
	 * Delete cache. It's used as callback.
	 *
	 * @return boolean: True
	 */
	function delete() {
		if(file_exists(ENTRYLIST_CACHEFILE)) {
			@unlink(ENTRYLIST_CACHEFILE);
		}

		return true;
	}

	/**
	 * This function get the list of entries.
	 *
	 * @return array: The list
	 */
	function getEntriesList() {
		if(file_exists(ENTRYLIST_CACHEFILE)) {
			include ENTRYLIST_CACHEFILE;
			return $list;
		}

		// Make the list
		$list = array();

		$query = new FPDB_Query(array('count' => -1, 'fullparse' => false), null);
		while($query->hasMore()) {
			list($id, $entry) = $query->getEntry();
			$date = date_from_id($id);
			$list [$date ['y']] [$date ['m']] [$date ['d']] [$id] = $entry ['subject'];
		}

		system_save(ENTRYLIST_CACHEFILE, array('list' => $list));
		return $list;
	}

	/**
	 * This function lists the entries.
	 *
	 * @param array $list: The list
	 * @param boolean $link: Make the link?
	 * @param string $sort: The sort function
	 * @return string: The list in HTML
	 */
	function listEntries($list, $link = true, $sort = 'ksort') {
		global $post; // PrettyURLs wants it

		if(!is_array($list)) {
			return '';
		}

		if(is_callable($sort)) {
			call_user_func_array($sort, array(&$list));
		}

		$return = '';

		// What are we listing?
		switch(0) {
			case $this->year:
				$what = 'year';
				$mod = &$this->year;
				$m = '01';
				$d = '01';
				break;
			case $this->month:
				$what = 'month';
				$mod = &$this->month;
				$m = &$mod;
				$d = '01';
				break;
			case $this->day:
				$what = 'day';
				$mod = &$this->day;
				$m = $this->month;
				$d = &$mod;
				break;
		}

		$format = 'f' . $what;

		// Make the list or just output?
		$ul = !empty($this->$format) || empty($what);

		foreach($list as $id => $subject) {
			$mod = $id;

			$strtotime = '20' . $this->year . "/" . $m . "/" . $d;

			if($ul) {
				$return .= '<li>';
				$date = theme_date_format(strtotime($strtotime), $this->$format);
				$return .= "\n<p>" . $date . "</p>\n";
			}

			if(is_array($subject)) {
				// Recurse
				$add = $this->listEntries($subject, $link, $sort);

				// Hack to do only a list
				if($what == 'month' && empty($this->fday)) {
					$add = str_replace("</ul>\n<ul class=\"entrylist\">", '', $add);
				}

				$return .= $add;
			} else {
				// Is the entry
				if($link) {
					// Make the link
					$oldpost = $post;
					$post = array('subject' => $subject);
					$href = get_permalink($id);
					$post = $oldpost;

					// Add the link
					$return .= '<a href="' . $href . '">'.wp_specialchars($subject) . '</a>';
				} else {
					$return.=wp_specialchars($subject);
				}
			}

			if($ul) {
				$return .= '</li>';
			}

			$mod = 0;
			$return .= "\n";
		}

		if(empty($return)) {
			return '';
		}

		if($ul) {
			$return = "<ul class=\"entrylist\">\n" . $return . "</ul>";
		}

		$return = str_replace("\n\n", "\n", $return);

		return $return;
	}

	/**
	 * This function manages the entrylist tag.
	 *
	 * @param string $action: The action
	 * @param array $attributes: The attributes
	 * @param string $content: The content of the tag
	 * @params array $params: The parameters
	 * @param mixed $node_object: Not known
	 * @return string: The replacement
	 */
	function tag($action, $attributes, $content, $params, $node_object) {
		if($action == 'validate') {
			return true;
		}

		$list = (array)$this->getEntriesList();

		// Just an year or a month or a day
		if(isset($attributes ['y'])) {
			$y = $attributes ['y'];
			$list = isset($list [$y]) ? (array) $list [$y] : [];
			$this->year = $y;

			if(isset($attributes ['m'])) {
				$m = $attributes ['m'];
				if(strlen($m) == 1) {
					$m = '0'.$m;
				}
				$list = (array)@$list [$m];
				$this->month = $m;

				if(isset($attributes ['d'])) {
					$d = $attributes ['d'];
					if(strlen($d) == 1) {
						$d = '0'.$d;
					}
					$list = (array)@$list [$d];
					$this->day = $d;
				}

			}

		}

		// If list is empty
		if(!empty($attributes ['noentries']) && !count($list)) {
			return $attributes ['noentries'];
		} elseif(!count($list)) {
			return 'There aren\'t entries.';
		}

		// Format of the date... date_format compatible
		if(isset($attributes ['yformat'])) {
			$this->fyear = $attributes ['yformat'];
		}
		if(isset($attributes ['mformat'])) {
			$this->fmonth = $attributes ['mformat'];
		}
		if(isset($attributes ['dformat'])) {
			$this->fday = $attributes ['dformat'];
		}

		// Make the link?
		$link = true;
		if(isset($attributes ['link'])) {
			$link = !($attributes ['link'] == 'false' || $attributes ['link'] == 'off');
		}

		// Sort callback for the list
		$sort = 'ksort';
		if(@$attributes ['sort'] == 'desc') {
			$sort = 'krsort';
		}

		// Finally make the list
		$return = $this->listEntries($list, $link, $sort);
		return $return;
	}

	/**
	 * This function adds the entrylist tag to the BBCode plugin.
	 *
	 * @param object $bbcode: The bbcode instance
	 * @return object: $bbcode modified
	 */
	function hook($bbcode) {
		$bbcode->addCode(
			'entrylist', 
			'callback_replace_single', 
			array(&$this, 'tag'), 
			array(
				'usecontent_param' => array('default')
			),
			'list',
			array('block', 'listitem'),
			array()
		);
		return $bbcode;
	}

}

new plugin_entrylist();
?>
