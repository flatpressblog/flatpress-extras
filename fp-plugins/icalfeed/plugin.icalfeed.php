<?php
/**
 * Plugin Name: iCalFeed
 * Version: 1.0.0
 * Plugin URI: https://frank-web.dedyn.io
 * Author: Fraenkiman
 * Author URI: https://frank-web.dedyn.io
 * Description: Displays upcoming appointments or busy-times from one or more iCalendar (ICS) feeds. Requires BBCode for the [icalfeed] tag.
 */

if (!defined('ABS_PATH')) {
	// FlatPress defines ABS_PATH in defaults.php; keep plugin silent if loaded outside.
	// (No fatal errors for tests or file scanners.)
}

require_once __DIR__ . '/inc/icalfeed.lib.php';

/**
 * Default plugin options.
 * @return array<string,mixed>
 */
function icalfeed_default_options() {
	return array(
		'feed_urls' => '',
		'cache_ttl' => 900,
		'days_ahead' => 14,
		'limit' => 10,
		'mode' => 'list', // list|busy
		'privacy' => 'busy', // busy|details
		'show_location' => false
	);
}

/**
 * Get plugin options merged with defaults.
 * @return array<string,mixed>
 */
function icalfeed_get_options() {
	$defaults = icalfeed_default_options();
	$opt = function_exists('plugin_getoptions') ? plugin_getoptions('icalfeed') : null;
	if (!is_array($opt)) {
		$opt = array();
	}
	foreach ($defaults as $k => $v) {
		if (!array_key_exists($k, $opt)) {
			$opt [$k] = $v;
		}
	}
	return $opt;
}

/**
 * Sanitize an integer option.
 * @param mixed $v
 * @param int $min
 * @param int $max
 * @param int $fallback
 * @return int
 */
function icalfeed_sanitize_int($v, $min, $max, $fallback) {
	if (!is_numeric($v)) {
		return (int)$fallback;
	}
	$n = (int)$v;
	if ($n < (int)$min) {
		return (int)$min;
	}
	if ($n > (int)$max) {
		return (int)$max;
	}
	return $n;
}

/**
 * Sanitize enum.
 * @param mixed $v
 * @param array<int,string> $allowed
 * @param string $fallback
 * @return string
 */
function icalfeed_sanitize_enum($v, $allowed, $fallback) {
	if (!is_string($v)) {
		return $fallback;
	}
	$v = strtolower(trim($v));
	return in_array($v, $allowed, true) ? $v : $fallback;
}

/**
 * Current locale timeoffset in seconds (may be float in config).
 * Uses UTC internally and adds a fixed offset for display, matching FlatPress' timeoffset behavior.
 * @return int
 */
function icalfeed_timeoffset_seconds() {
	global $fp_config;
	$off = 0.0;
	if (isset($fp_config ['locale']) && is_array($fp_config ['locale']) && isset($fp_config ['locale'] ['timeoffset']) && is_numeric($fp_config ['locale'] ['timeoffset'])) {
		$off = (float)$fp_config ['locale'] ['timeoffset'];
	}
	return (int)round($off * 3600.0);
}

/**
 * Normalize a feed URL (supports webcal:// -> https://) and validate.
 * @param string $url
 * @return string|null
 */
function icalfeed_normalize_url($url) {
	if (!is_string($url)) {
		return null;
	}
	$url = trim($url);
	if ($url === '') {
		return null;
	}
	if (stripos($url, 'webcal://') === 0) {
		$url = 'https://' . substr($url, 9);
	}
	$parts = @parse_url($url);
	if (!is_array($parts) || !isset($parts ['scheme']) || !is_string($parts ['scheme'])) {
		return null;
	}
	$scheme = strtolower($parts ['scheme']);
	if ($scheme !== 'http' && $scheme !== 'https') {
		return null;
	}
	return $url;
}

/**
 * Normalize multiple URLs (one per line).
 * @param mixed $raw
 * @return array<int,string>
 */
function icalfeed_normalize_urls($raw) {
	$urls = array();
	if (is_string($raw)) {
		$lines = preg_split('/\r\n|\r|\n/', $raw);
		if (is_array($lines)) {
			foreach ($lines as $line) {
				$n = icalfeed_normalize_url((string)$line);
				if ($n !== null) {
					$urls [] = $n;
				}
			}
		}
	} elseif (is_array($raw)) {
		foreach ($raw as $u) {
			$n = icalfeed_normalize_url((string)$u);
			if ($n !== null) {
				$urls [] = $n;
			}
		}
	}
	// De-dup
	$urls = array_values(array_unique($urls));
	return $urls;
}

/**
 * Check whether APCu is available and enabled for this SAPI.
 * @return bool
 */
function icalfeed_apcu_available() {
	if (!function_exists('apcu_fetch') || !function_exists('apcu_store')) {
		return false;
	}
	if (PHP_SAPI === 'cli') {
		$cli = ini_get('apc.enable_cli');
		return ($cli !== false && (string)$cli !== '' && (string)$cli !== '0');
	}
	$enabled = ini_get('apc.enabled');
	if ($enabled === false) {
		// Some SAPIs may not expose this ini setting; assume enabled if extension loaded.
		return true;
	}
	return ((string)$enabled !== '0');
}

/**
 * APCu generation token (changes when cache cleared).
 * @return int
 */
function icalfeed_cache_gen() {
	if (!icalfeed_apcu_available()) {
		return 1;
	}
	$hit = false;
	$val = apcu_fetch('fp:icalfeed:gen', $hit);
	if ($hit && is_numeric($val)) {
		return (int)$val;
	}
	apcu_store('fp:icalfeed:gen', 1, 0);
	return 1;
}

/**
 * Bump APCu generation token.
 * @return void
 */
function icalfeed_cache_bump() {
	if (!icalfeed_apcu_available()) {
		return;
	}
	$success = false;
	$val = apcu_inc('fp:icalfeed:gen', 1, $success);
	if (!$success || !is_numeric($val)) {
		apcu_store('fp:icalfeed:gen', 1, 0);
	}
}

/**
 * Build cache file path.
 * @param string $key
 * @return string
 */
function icalfeed_cache_file($key) {
	$dir = defined('CACHE_DIR') ? CACHE_DIR : sys_get_temp_dir() . '/';
	return rtrim($dir, '/\\') . '/icalfeed-' . sha1($key) . '.json';
}

/**
 * Get cached data.
 * @param string $key
 * @param int $ttl
 * @return array|null
 */
function icalfeed_cache_get($key, $ttl) {
	$ttl = (int)$ttl;
	if ($ttl < 0) {
		$ttl = 0;
	}

	// APCu (fast)
	if (icalfeed_apcu_available()) {
		$apcuKey = 'fp:icalfeed:' . icalfeed_cache_gen() . ':' . sha1($key);
		$hit = false;
		$val = apcu_fetch($apcuKey, $hit);
		if ($hit && is_array($val)) {
			return $val;
		}
	}

	// File cache
	if ($ttl === 0) {
		return null;
	}
	$file = icalfeed_cache_file($key);
	if (!is_file($file)) {
		return null;
	}
	$mtime = @filemtime($file);
	if (!$mtime) {
		return null;
	}
	if (time() - (int)$mtime > $ttl) {
		return null;
	}
	$json = @file_get_contents($file);
	if (!is_string($json) || $json === '') {
		return null;
	}
	$data = json_decode($json, true);
	if (!is_array($data)) {
		return null;
	}
	// Promote to APCu
	if (icalfeed_apcu_available()) {
		$apcuKey = 'fp:icalfeed:' . icalfeed_cache_gen() . ':' . sha1($key);
		apcu_store($apcuKey, $data, $ttl);
	}
	return $data;
}

/**
 * Set cached data.
 * @param string $key
 * @param array $data
 * @param int $ttl
 * @return void
 */
function icalfeed_cache_set($key, $data, $ttl) {
	$ttl = (int)$ttl;
	if ($ttl < 0) {
		$ttl = 0;
	}

	if (icalfeed_apcu_available()) {
		$apcuKey = 'fp:icalfeed:' . icalfeed_cache_gen() . ':' . sha1($key);
		apcu_store($apcuKey, $data, $ttl);
	}

	if ($ttl <= 0) {
		return;
	}

	$file = icalfeed_cache_file($key);
	$json = json_encode($data);
	if (is_string($json) && $json !== '') {
		@file_put_contents($file, $json, LOCK_EX);
	}
}

/**
 * Clear file cache entries.
 * @return void
 */
function icalfeed_clear_cache() {
	$dir = defined('CACHE_DIR') ? CACHE_DIR : null;
	if ($dir && is_dir($dir)) {
		$files = @glob(rtrim($dir, '/\\') . '/icalfeed-*.json');
		if (is_array($files)) {
			foreach ($files as $f) {
				@unlink($f);
			}
		}
	}
	icalfeed_cache_bump();
}

/**
 * Fetch and expand events for given URLs.
 * @param array<int,string> $urls
 * @param int $daysAhead
 * @param int $limit
 * @param string|null $error
 * @param bool $partial
 * @return array<int,array<string,mixed>>
 */
function icalfeed_get_events($urls, $daysAhead, $limit, &$error = null, &$partial = false) {
	$error = null;
	$partial = false;
	if (!is_array($urls) || count($urls) === 0) {
		$error = 'no_urls';
		return array();
	}

	$opt = icalfeed_get_options();
	$ttl = isset($opt ['cache_ttl']) ? (int)$opt ['cache_ttl'] : 900;
	if ($ttl < 0) {
		$ttl = 0;
	}

	$daysAhead = icalfeed_sanitize_int($daysAhead, 1, 365, 14);
	$limit = icalfeed_sanitize_int($limit, 1, 200, 10);

	$cacheKey = 'urls=' . implode("\n", $urls) . '|days=' . $daysAhead . '|limit=' . $limit . '|v=1';
	$cached = icalfeed_cache_get($cacheKey, $ttl);
	if (is_array($cached) && isset($cached ['events']) && is_array($cached ['events'])) {
		return $cached ['events'];
	}

	$rawEvents = array();
	$errors = 0;
	$parseErrors = 0;
	foreach ($urls as $url) {
		$code = null;
		$err = null;
		$body = icalfeed_http_get($url, 12, $code, $err);
		if (!is_string($body) || $body === '') {
			$errors++;
			continue;
		}
		$perr = null;
		$events = icalfeed_parse_ics($body, icalfeed_timeoffset_seconds(), $perr);
		if ($perr !== null) {
			$parseErrors++;
		}
		if (is_array($events) && count($events) > 0) {
			$rawEvents = array_merge($rawEvents, $events);
		}
	}

	if ($errors > 0 || $parseErrors > 0) {
		$partial = (count($rawEvents) > 0);
	}
	if (count($rawEvents) === 0) {
		$error = ($parseErrors > 0) ? 'parse_failed' : 'fetch_failed';
		return array();
	}

	$nowUtc = time();
	$windowStart = $nowUtc - 86400; // include ongoing/just-started events
	$windowEnd = $nowUtc + ($daysAhead * 86400);

	$occ = icalfeed_expand_occurrences($rawEvents, $windowStart, $windowEnd, $limit);

	// Convert to view model (with local offset)
	$off = icalfeed_timeoffset_seconds();
	$view = array();
	$seen = array();
	foreach ($occ as $o) {
		$uid = isset($o ['uid']) ? (string)$o ['uid'] : '';
		$start = isset($o ['start_ts']) ? (int)$o ['start_ts'] : 0;
		$k = $uid . ':' . $start;
		if (isset($seen [$k])) {
			continue;
		}
		$seen [$k] = true;

		$end = isset($o ['end_ts']) ? (int)$o ['end_ts'] : $start;
		$allDay = !empty($o ['all_day']);

		$view [] = array(
			'uid' => $uid,
			'all_day' => $allDay ? true : false,
			'start_utc' => $start,
			'end_utc' => $end,
			'start_local' => $start + $off,
			'end_local' => $end + $off,
			'summary' => isset($o ['summary']) ? (string)$o ['summary'] : '',
			'location' => isset($o ['location']) ? (string)$o ['location'] : ''
		);
	}

	icalfeed_cache_set($cacheKey, array('events' => $view), $ttl);
	return $view;
}

/**
 * Render for widget or tag.
 * @param array<string,mixed> $overrides
 * @param string $template
 * @return string
 */
function icalfeed_render($overrides, $template) {
	global $smarty;
	if (!isset($smarty)) {
		return '';
	}

	$lang = lang_load('plugin:icalfeed');
	$opt = icalfeed_get_options();

	$urlsRaw = isset($overrides ['feed_urls']) ? $overrides ['feed_urls'] : (isset($opt ['feed_urls']) ? $opt ['feed_urls'] : '');
	$urls = icalfeed_normalize_urls($urlsRaw);

	$days = isset($overrides ['days_ahead']) ? $overrides ['days_ahead'] : (isset($opt ['days_ahead']) ? $opt ['days_ahead'] : 14);
	$limit = isset($overrides ['limit']) ? $overrides ['limit'] : (isset($opt ['limit']) ? $opt ['limit'] : 10);

	$mode = isset($overrides ['mode']) ? $overrides ['mode'] : (isset($opt ['mode']) ? $opt ['mode'] : 'list');
	$mode = icalfeed_sanitize_enum($mode, array('list', 'busy'), 'list');

	$privacy = isset($overrides ['privacy']) ? $overrides ['privacy'] : (isset($opt ['privacy']) ? $opt ['privacy'] : 'busy');
	$privacy = icalfeed_sanitize_enum($privacy, array('busy', 'details'), 'busy');

	$showLocation = isset($overrides ['show_location']) ? (bool)$overrides ['show_location'] : (!empty($opt ['show_location']));

	$title = isset($overrides ['title']) && is_string($overrides ['title']) ? trim($overrides ['title']) : '';
	if ($title === '' && isset($overrides ['with_default_title']) && $overrides ['with_default_title']) {
		$title = $lang ['plugin'] ['icalfeed'] ['tag_title_default'];
	}

	$error = null;
	$partial = false;
	$events = icalfeed_get_events($urls, (int)$days, (int)$limit, $error, $partial);

	$smarty->assign('icalfeed_events', $events);
	$smarty->assign('icalfeed_error', $error);
	$smarty->assign('icalfeed_partial', $partial);
	$smarty->assign('icalfeed_mode', $mode);
	$smarty->assign('icalfeed_privacy', $privacy);
	$smarty->assign('icalfeed_show_location', $showLocation ? true : false);

	$pl = (isset($lang ['plugin'] ['icalfeed']) && is_array($lang ['plugin'] ['icalfeed'])) ? $lang ['plugin'] ['icalfeed'] : array();
	$labels = (isset($pl ['labels']) && is_array($pl ['labels'])) ? $pl ['labels'] : array();
	$errors = (isset($pl ['errors']) && is_array($pl ['errors'])) ? $pl ['errors'] : array();

	$icalLang = array(
		'subject' => isset($pl ['subject']) ? (string)$pl ['subject'] : 'iCalFeed',
		'labels' => array(
			'no_events' => isset($labels ['no_events']) ? (string)$labels ['no_events'] : (isset($pl ['no_events']) ? (string)$pl ['no_events'] : (isset($pl ['no_upcoming']) ? (string)$pl ['no_upcoming'] : 'No upcoming appointments.')),
			'free' => isset($labels ['free']) ? (string)$labels ['free'] : (isset($pl ['free']) ? (string)$pl ['free'] : 'Free'),
			'busy' => isset($labels ['busy']) ? (string)$labels ['busy'] : (isset($pl ['busy']) ? (string)$pl ['busy'] : 'Busy'),
			'all_day' => isset($labels ['all_day']) ? (string)$labels ['all_day'] : (isset($pl ['all_day']) ? (string)$pl ['all_day'] : 'All day')
		),
		'errors' => array(
			'no_urls' => isset($errors ['no_urls']) ? (string)$errors ['no_urls'] : (isset($pl ['error_missing_url']) ? (string)$pl ['error_missing_url'] : 'No calendar feed URL configured.'),
			'fetch_failed' => isset($errors ['fetch_failed']) ? (string)$errors ['fetch_failed'] : (isset($pl ['error_fetch_failed']) ? (string)$pl ['error_fetch_failed'] : 'Could not fetch calendar feed.'),
			'parse_failed' => isset($errors ['parse_failed']) ? (string)$errors ['parse_failed'] : (isset($pl ['error_parse_failed']) ? (string)$pl ['error_parse_failed'] : 'Could not parse calendar feed.'),
			'partial' => isset($errors ['partial']) ? (string)$errors ['partial'] : (isset($pl ['error_partial']) ? (string)$pl ['error_partial'] : 'Some feeds could not be loaded; showing partial results.'),
			'generic' => isset($errors ['generic']) ? (string)$errors ['generic'] : (isset($pl ['error_generic']) ? (string)$pl ['error_generic'] : 'Calendar feed error.')
		)
	);
	$smarty->assign('icalfeed_lang', $icalLang);
	$smarty->assign('icalfeed_title', $title);

	return $smarty->fetch($template);
}

/**
 * CSS include.
 */
function plugin_icalfeed_head() {
	$pdir = plugin_geturl('icalfeed');
	$raw = $pdir . 'res/icalfeed.css';
	if (function_exists('utils_asset_ver')) {
		$href = utils_asset_ver($raw, defined('SYSTEM_VER') ? SYSTEM_VER : null);
	} else {
		$href = $raw . ((strpos($raw, '?') === false) ? '?' : '&') . 'v=' . (defined('SYSTEM_VER') ? rawurlencode(SYSTEM_VER) : time());
	}
	echo '
		<!-- BOF iCalFeed Stylesheet -->
		<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">
		<!-- EOF iCalFeed Stylesheet -->
	';
}

/**
 * Widget.
 */
function plugin_icalfeed_widget() {
	$lang = lang_load('plugin:icalfeed');
	$entry = array();
	$entry ['subject'] = $lang ['plugin'] ['icalfeed'] ['widget_title'];
	$entry ['content'] = icalfeed_render(array(), 'plugin:icalfeed/widget');
	return $entry;
}

// Register widget + CSS
if (function_exists('register_widget')) {
	register_widget('icalfeed', 'iCalFeed', 'plugin_icalfeed_widget');
}
if (function_exists('add_action')) {
	add_action('wp_head', 'plugin_icalfeed_head', 1);
}

/**
 * BBCode tag: [icalfeed]
 */
class plugin_icalfeed {
	function __construct() {
		if (function_exists('add_filter')) {
			add_filter('bbcode_init', array(&$this, 'hook'));
		}
	}

	function hook($bbcode) {
		// If BBCode plugin isn't available, just skip.
		if (!is_object($bbcode) || !method_exists($bbcode, 'addCode')) {
			return $bbcode;
		}
		$bbcode->addCode(
			'icalfeed',
			'callback_replace_single',
			array(&$this, 'tag'),
			array('usecontent_param' => array('default')),
			'list',
			array('block', 'listitem'),
			array()
		);
		return $bbcode;
	}

	/**
	 * @param string $action
	 * @param array<string,string> $attributes
	 * @param string $content
	 * @param array<string,mixed> $params
	 * @param mixed $node_object
	 * @return string|bool
	 */
	function tag($action, $attributes, $content, $params, $node_object) {
		if ($action === 'validate') {
			return true;
		}

		$overrides = array(
			'with_default_title' => true
		);

		// URL via [icalfeed=URL] or [icalfeed url="..."]
		if (isset($attributes ['default']) && is_string($attributes ['default']) && trim($attributes ['default']) !== '') {
			$overrides ['feed_urls'] = trim($attributes ['default']);
		}
		if (isset($attributes ['url']) && is_string($attributes ['url']) && trim($attributes ['url']) !== '') {
			$overrides ['feed_urls'] = trim($attributes ['url']);
		}
		if (isset($attributes ['urls']) && is_string($attributes ['urls']) && trim($attributes ['urls']) !== '') {
			// Support separators: |, comma, newline
			$raw = str_replace(array('|', ','), "\n", (string)$attributes ['urls']);
			$overrides ['feed_urls'] = $raw;
		}

		if (isset($attributes ['days'])) {
			$overrides ['days_ahead'] = $attributes ['days'];
		}
		if (isset($attributes ['limit'])) {
			$overrides ['limit'] = $attributes ['limit'];
		}
		if (isset($attributes ['mode'])) {
			$overrides ['mode'] = $attributes ['mode'];
		}
		if (isset($attributes ['privacy'])) {
			$overrides ['privacy'] = $attributes ['privacy'];
		}
		if (isset($attributes ['title']) && is_string($attributes ['title'])) {
			$overrides ['title'] = (string)$attributes ['title'];
			$overrides ['with_default_title'] = false;
		}
		if (isset($attributes ['location'])) {
			$overrides ['show_location'] = ((string)$attributes ['location'] === '1' || strtolower((string)$attributes ['location']) === 'true');
		}

		$html = icalfeed_render($overrides, 'plugin:icalfeed/tag');
		if (!is_string($html) || $html === '') {
			return '';
		}
		// Ensure block-level separation for Markdown/autop.
		return "\n\n" . $html . "\n\n";
	}
}

new plugin_icalfeed();

/**
 * Admin panel.
 */
if (class_exists('AdminPanelAction')) {
	class admin_plugin_icalfeed extends AdminPanelAction {
		var $lang = 'plugin:icalfeed';

		function setup() {
			// Load language
			lang_load('plugin:icalfeed');
			$this->smarty->assign('admin_resource', 'plugin:icalfeed/admin.plugin.icalfeed');
		}

		function main() {
			if ($_SERVER ['REQUEST_METHOD'] === 'POST') {
				$this->onsubmit();
			}
			$this->assign_config_to_template();
		}

		function assign_config_to_template() {
			$opt = icalfeed_get_options();
			$this->smarty->assign('feed_urls', isset($opt ['feed_urls']) ? (string)$opt ['feed_urls'] : '');
			$this->smarty->assign('cache_ttl', isset($opt ['cache_ttl']) ? (int)$opt ['cache_ttl'] : 900);
			$this->smarty->assign('days_ahead', isset($opt ['days_ahead']) ? (int)$opt ['days_ahead'] : 14);
			$this->smarty->assign('limit', isset($opt ['limit']) ? (int)$opt ['limit'] : 10);
			$this->smarty->assign('mode', isset($opt ['mode']) ? (string)$opt ['mode'] : 'list');
			$this->smarty->assign('privacy', isset($opt ['privacy']) ? (string)$opt ['privacy'] : 'busy');
			$this->smarty->assign('show_location', !empty($opt ['show_location']));
		}

		function onsubmit($data = null) {
			// Clear cache?
			if (isset($_POST ['clear_cache'])) {
				icalfeed_clear_cache();
				$this->smarty->assign('success', 2);
				$this->assign_config_to_template();
				return 0;
			}

			$feed_urls = isset($_POST ['feed_urls']) && is_string($_POST ['feed_urls']) ? trim($_POST ['feed_urls']) : '';
			$urls = icalfeed_normalize_urls($feed_urls);
			if (count($urls) === 0) {
				$this->smarty->assign('success', -1);
				$this->assign_config_to_template();
				return 0;
			}

			$cache_ttl = icalfeed_sanitize_int(isset($_POST ['cache_ttl']) ? $_POST ['cache_ttl'] : 900, 0, 86400, 900);
			$days_ahead = icalfeed_sanitize_int(isset($_POST ['days_ahead']) ? $_POST ['days_ahead'] : 14, 1, 365, 14);
			$limit = icalfeed_sanitize_int(isset($_POST ['limit']) ? $_POST ['limit'] : 10, 1, 200, 10);
			$mode = icalfeed_sanitize_enum(isset($_POST ['mode']) ? $_POST ['mode'] : 'list', array('list', 'busy'), 'list');
			$privacy = icalfeed_sanitize_enum(isset($_POST ['privacy']) ? $_POST ['privacy'] : 'busy', array('busy', 'details'), 'busy');
			$show_location = isset($_POST ['show_location']) ? true : false;

			plugin_addoption('icalfeed', 'feed_urls', $feed_urls);
			plugin_addoption('icalfeed', 'cache_ttl', $cache_ttl);
			plugin_addoption('icalfeed', 'days_ahead', $days_ahead);
			plugin_addoption('icalfeed', 'limit', $limit);
			plugin_addoption('icalfeed', 'mode', $mode);
			plugin_addoption('icalfeed', 'privacy', $privacy);
			plugin_addoption('icalfeed', 'show_location', $show_location);
			plugin_saveoptions('icalfeed');

			$this->smarty->assign('success', 1);
			$this->assign_config_to_template();
			return 0;
		}
	}

	admin_addpanelaction('plugin', 'icalfeed', true);
}

?>
