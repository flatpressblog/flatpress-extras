<?php
/**
 * Plugin Name: Entry List
 * Version: 1.0.4
 * Plugin URI: https://www.pierov.org/2012/10/17/plugin-entrylist-v101-flatpress/
 * Description: This plugin add the [entrylist] tag to make the list of the entries. It needs BBCode.
 * Author: Piero VDFN
 * Author URI: https://www.pierov.org/
 */

// Turn off all error reporting
//@error_reporting(0);

/**
 * If a page is often visited it's better making a cache.
 */
define('ENTRYLIST_CACHEFILE', CACHE_DIR . 'plugin_entrylist_tag.txt');

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
	 * This is the format of the entry time (always without seconds).
	 * It must be compatible with date_format.
	 *
	 * @var string
	 */
	var $ftime = '%H:%M';

	/**
	 * This function is the constructor.
	 */
	function __construct() {
		add_filter('bbcode_init', array(&$this, 'hook'));
		add_filter('publish_post', array(&$this, 'delete'), 10, 1);
		add_filter('delete_post', array(&$this, 'delete'), 10, 1);
	}

	/**
	 * Reset state for each [entrylist] tag call.
	 * Prevents values from a previous tag from affecting subsequent tags.
	 */
	function resetState() {
		$this->year = 0;
		$this->month = 0;
		$this->day = 0;

		$this->initDefaultFormatsFromConfig();
	}

	/**
	 * Initialize default heading formats from the admin-configured locale settings.
	 * - Day headings: use locale['dateformatshort'] as-is
	 * - Month/year headings: derived from locale['dateformatshort'] (keeps ordering + literals like "年", "月", "日")
	 *
	 * Tag attributes yformat/mformat/dformat can still override these defaults per usage.
	 */
	function initDefaultFormatsFromConfig() {
		global $fp_config;

		$datefmt = '%Y-%m-%d';
		if (isset($fp_config ['locale'] ['dateformatshort']) && is_string($fp_config ['locale'] ['dateformatshort'])) {
			$tmp = trim($fp_config ['locale'] ['dateformatshort']);
			if ($tmp !== '') {
				$datefmt = $tmp;
			}
		}

		// Full day heading format
		$this->fday = $datefmt;

		// Derive month + year headings from the chosen format
		$this->fmonth = $this->deriveFormatKeeping($datefmt, array('Y','y','G','g','m','b','B','h'));
		if ($this->fmonth === '') {
			$this->fmonth = '%B %Y';
		}

		$this->fyear = $this->deriveFormatKeeping($datefmt, array('Y','y','G','g'));
		if ($this->fyear === '') {
			$this->fyear = '%Y';
		}

		// Default entry time format
		$timefmt = '%H:%M';
		if (isset($fp_config ['locale'] ['timeformat']) && is_string($fp_config ['locale'] ['timeformat'])) {
			$tmp = trim($fp_config ['locale'] ['timeformat']);
			if ($tmp !== '') {
				$timefmt = $tmp;
			}
		}
		$this->ftime = $this->normalizeTimeFormat($timefmt);
	}

	/**
	 * Normalize a time format string.
	 * Accepts strftime-compatible formats (as used by theme_date_format/date_strformat).
	 * - Expands common composite tokens (%T, %r, %X).
	 * - Removes %S / %ES / %OS including directly attached suffix literals.
	 * - Removes separators immediately before the seconds token (e.g. ":" in "%H:%M:%S").
	 *
	 * @param string $format
	 * @return string
	 */
	function normalizeTimeFormat($format) {
		$format = trim((string)$format);
		if ($format === '') {
			return '%H:%M';
		}

		// Expand composite tokens that may include seconds
		$format = str_replace('%T', '%H:%M:%S', $format); // %T = %H:%M:%S
		$format = str_replace('%r', '%I:%M:%S %p', $format); // %r = %I:%M:%S %p
		// %X is locale-dependent and often includes seconds (MEDIUM); force a safe baseline
		$format = str_replace('%X', '%H:%M:%S', $format);

		$out = '';
		$len = strlen($format);

		for ($i = 0; $i < $len; $i++) {
			$ch = $format[$i];
			if ($ch !== '%') {
				$out .= $ch;
				continue;
			}

			// Lone '%' at end
			if ($i + 1 >= $len) {
				$out .= '%';
				break;
			}

			$c1 = $format[$i + 1];
			$token = '%' . $c1;
			$tokenChar = $c1;
			$consume = 2;

			// POSIX modifiers %E? / %O?
			if (($c1 === 'E' || $c1 === 'O') && ($i + 2) < $len) {
				$token .= $format [$i + 2];
				$tokenChar = $format [$i + 2];
				$consume = 3;
			}

			// Advance to end of token
			$i += ($consume - 1);

			$remove = ($tokenChar === 'S'); // Seconds token (also when prefixed by %E/%O)
			if ($remove) {
				// Remove typical separators right before the seconds token (e.g. ":" in "%H:%M:%S")
				$out = rtrim($out, " \t\n\r\0\x0B:.,;/\\|-");
			} else {
				$out .= $token;
			}

			// Copy/skip literal suffix directly attached to the token (no whitespace, no '%')
			$j = $i + 1;
			while ($j < $len) {
				$b = $format [$j];
				if ($b === '%' || $b === ' ' || $b === "\t" || $b === "\n" || $b === "\r" || $b === "\0" || $b === "\x0B") {
					break;
				}
				if (!$remove) {
					$out .= $b;
				}
				$j++;
			}
			$i = $j - 1;
		}

		$out = $this->cleanupDerivedFormat($out);
		if ($out === '') {
			$out = '%H:%M';
		}
		return $out;
	}

	/**
	 * Normalize year parameter.
	 * Accepts: 1..99, 01..99 or 2001..2099. Returns two digits (01..99) or null.
	 */
	function normalizeYear($y) {
		$y = trim((string)$y);
		if ($y === '') {
			return null;
		}

		// 4-digit year (2001..2099)
		if (preg_match('/^\d{4}$/', $y)) {
			$y4 = (int)$y;
			if ($y4 < 2001 || $y4 > 2099) {
				return null;
			}
			$y = substr($y, 2, 2);
		}

		// 1-2 digit year (1..99)
		if (preg_match('/^\d{1,2}$/', $y)) {
			$y = str_pad($y, 2, '0', STR_PAD_LEFT);
			if ($y === '00') {
				return null;
			}
			return $y;
		}

		return null;
	}

	/**
	 * Normalize month parameter. Accepts 1..12 or 01..12. Returns two digits or null.
	 */
	function normalizeMonth($m) {
		$m = trim((string)$m);
		if (!preg_match('/^\d{1,2}$/', $m)) {
			return null;
		}
		$mi = (int)$m;
		if ($mi < 1 || $mi > 12) {
			return null;
		}
		return str_pad((string)$mi, 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Normalize day parameter. Accepts 1..31 or 01..31. Returns two digits or null.
	 */
	function normalizeDay($d) {
		$d = trim((string)$d);
		if (!preg_match('/^\d{1,2}$/', $d)) {
			return null;
		}
		$di = (int)$d;
		if ($di < 1 || $di > 31) {
			return null;
		}
		return str_pad((string)$di, 2, '0', STR_PAD_LEFT);
	}

	/**
	 * Build a timestamp for headings (year/month/day list items).
	 * Returns an int timestamp or false.
	 */
	function makeHeadingTimestamp($year2, $month2, $day2) {
		$year2 = (string)$year2;
		$month2 = (string)$month2;
		$day2 = (string)$day2;

		if (!preg_match('/^\d{2}$/', $year2) || !preg_match('/^\d{2}$/', $month2) || !preg_match('/^\d{2}$/', $day2)) {
			return false;
		}

		$year4 = 2000 + (int)$year2;
		$m = (int)$month2;
		$d = (int)$day2;

		if (!checkdate($m, $d, $year4)) {
			return false;
		}

		return mktime(0, 0, 0, $m, $d, $year4);
	}

	/**
	 * Derive a strftime-compatible format string by keeping only selected token chars.
	 * Also removes token-attached suffix literals when the token itself is removed (e.g. "%d." or "%d日").
	 *
	 * @param string $format Original format string (strftime style)
	 * @param array $keepTokenChars List of token characters to keep (e.g. array('Y','m','B'))
	 * @return string Derived + cleaned format string
	 */
	function deriveFormatKeeping($format, $keepTokenChars) {
		$format = (string)$format;
		$out = '';
		$len = strlen($format);

		for ($i = 0; $i < $len; $i++) {
			$ch = $format [$i];

			if ($ch !== '%') {
				$out .= $ch;
				continue;
			}

			// Lone '%' at end
			if ($i + 1 >= $len) {
				$out .= '%';
				break;
			}

			$c1 = $format[$i + 1];
			$token = '%' . $c1;
			$tokenChar = $c1;
			$consume = 2;

			// POSIX modifiers %E? / %O?
			if (($c1 === 'E' || $c1 === 'O') && ($i + 2) < $len) {
				$token .= $format [$i + 2];
				$tokenChar = $format [$i + 2];
				$consume = 3;
			}

			// Advance to end of token
			$i += ($consume - 1);

			$keep = ($tokenChar === '%') || in_array($tokenChar, $keepTokenChars, true);
			if ($keep) {
				$out .= $token;
			}

			// Copy/skip literal suffix directly attached to the token (no whitespace, no '%')
			$j = $i + 1;
			while ($j < $len) {
				$b = $format[$j];
				if ($b === '%' || $b === ' ' || $b === "\t" || $b === "\n" || $b === "\r" || $b === "\0" || $b === "\x0B") {
					break;
				}
				if ($keep) {
					$out .= $b;
				}
				$j++;
			}
			$i = $j - 1;
		}

		return $this->cleanupDerivedFormat($out);
	}

	/**
	 * Cleanup derived format strings by removing leftover punctuation/separators and empty brackets.
	 */
	function cleanupDerivedFormat($format) {
		$format = (string)$format;

		// Collapse whitespace
		$format = preg_replace('/\s+/u', ' ', $format);

		// Remove empty brackets (common with formats like "... (%A)")
		$format = preg_replace('/\(\s*\)/u', '', $format);
		$format = preg_replace('/\[\s*\]/u', '', $format);
		$format = preg_replace('/\{\s*\}/u', '', $format);

		$format = trim($format);

		// Trim leading/trailing punctuation and separators (also lone brackets)
		$format = trim($format, " \t\n\r\0\x0B,.;:/\\|()[]{}<>-");
		$format = trim($format);

		return $format;
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
		$what = '';
		$m = $this->month;
		$d = $this->day;

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
		$ul = ((!empty($what) && property_exists($this, $format) && !empty($this->$format)) || empty($what));

		foreach($list as $id => $subject) {
			$mod = $id;

			$timestamp = $this->makeHeadingTimestamp($this->year, $m, $d);

			if ($timestamp === false) {
				$timestamp = strtotime('20' . $this->year . "/" . $m . "/" . $d);
			}

			if ($ul) {
				$return .= '<li>';
				if (!empty($what) && property_exists($this, $format) && !empty($this->$format)) {
					$date = theme_date_format($timestamp, $this->$format);
					$return .= "\n<p><strong>" . $date . "</strong></p>\n";
				}
			}

			if (is_array($subject)) {
				// Recurse
				$add = $this->listEntries($subject, $link, $sort);

				// Hack to do only a list
				if($what == 'month' && empty($this->fday)) {
					$add = str_replace("</ul>\n<ul class=\"entrylist\">", '', $add);
				}

				$return .= $add;
			} else {
				// Prepend entry time
				if (!empty($this->ftime) && is_string($id)) {
					$edate = date_from_id($id);
					if (is_array($edate) && isset($edate ['time'])) {
						$etime = theme_date_format($edate ['time'], $this->ftime);
						if ($etime !== '') {
							$return .= '<span class="entrylist-time">' . wp_specialchars($etime) . '</span> ';
						}
					}
				}
				// Is the entry
				if ($link) {
					// Make the link
					$oldpost = $post;
					$post = array('subject' => $subject);
					$href = get_permalink($id);
					$post = $oldpost;

					// Add the link
					$return .= '<a href="' . $href . '">' . wp_specialchars($subject) . '</a>';
				} else {
					$return .= wp_specialchars($subject);
				}
			}

			if ($ul) {
				$return .= '</li>';
			}

			$mod = 0;
			$return .= "\n";
		}

		if (empty($return)) {
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
		if ($action == 'validate') {
			return true;
		}

		$this->resetState();

		$list = (array)$this->getEntriesList();

		// Optional: override entry time format via [entrylist=+FORMAT]
		if (isset($attributes ['default']) && is_string($attributes ['default'])) {
			$tmp = trim($attributes ['default']);
			if ($tmp !== '' && $tmp [0] === '+') {
				$this->ftime = $this->normalizeTimeFormat(substr($tmp, 1));
			}
		}

		// Just an year or a month or a day
		if (isset($attributes ['y'])) {
			$y = $this->normalizeYear($attributes ['y']);

			if ($y === null) {
				$list = [];
			} else {
				$list = isset($list [$y]) ? (array)$list [$y] : [];
				$this->year = $y;

				if (isset($attributes ['m'])) {
					$m = $this->normalizeMonth($attributes ['m']);

					if ($m === null) {
						$list = [];
					} else {
						$list = isset($list [$m]) ? (array)$list [$m] : [];
						$this->month = $m;

						if (isset($attributes ['d'])) {
							$d = $this->normalizeDay($attributes ['d']);

							if ($d === null) {
								$list = [];
							} else {
								$year4 = 2000 + (int)$this->year;

								if (!checkdate((int)$this->month, (int)$d, $year4)) {
									$list = [];
								} else {
									$list = isset($list [$d]) ? (array)$list [$d] : [];
									$this->day = $d;
								}
							}
						}
					}
				}
			}
		}


		// If list is empty
		if (!empty($attributes ['noentries']) && !count($list)) {
			return $attributes ['noentries'];
		} elseif(!count($list)) {
			return 'There aren\'t entries.';
		}

		// Format of the date... date_format compatible
		if (isset($attributes ['yformat'])) {
			$this->fyear = $attributes ['yformat'];
		}
		if (isset($attributes ['mformat'])) {
			$this->fmonth = $attributes ['mformat'];
		}
		if (isset($attributes ['dformat'])) {
			$this->fday = $attributes ['dformat'];
		}

		// Make the link?
		$link = true;
		if (isset($attributes ['link'])) {
			$link = !($attributes ['link'] == 'false' || $attributes ['link'] == 'off');
		}

		// Sort callback for the list
		$sort = 'ksort';
		if (@$attributes ['sort'] == 'desc') {
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
