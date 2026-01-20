<?php
/**
 * CLI simulation for rendering via Smarty templates.
 *
 * This starts a local PHP built-in webserver to serve sample.ics,
 * then boots only the minimum FlatPress core needed for Smarty rendering
 * (defaults.php + includes.php) and calls icalfeed_render() with an HTTP URL.
 *
 * Usage:
 *   php simulate_render.php
 */

$root = realpath(__DIR__ . '/../../..');
if (!is_string($root) || $root === '') {
	fwrite(STDERR, "Cannot locate FlatPress root.\n");
	exit(1);
}

// Provide minimal web-server vars for defaults.php when running via CLI.
if (!isset($_SERVER ['HTTP_HOST'])) {
	$_SERVER ['HTTP_HOST'] = '127.0.0.1';
}
if (!isset($_SERVER ['SCRIPT_NAME'])) {
	$_SERVER ['SCRIPT_NAME'] = '/index.php';
}
if (!isset($_SERVER ['SERVER_PORT'])) {
	$_SERVER ['SERVER_PORT'] = '80';
}
if (!isset($_SERVER ['HTTPS'])) {
	$_SERVER ['HTTPS'] = 'off';
}
if (!isset($_SERVER ['REQUEST_METHOD'])) {
	$_SERVER ['REQUEST_METHOD'] = 'GET';
}
if (!isset($_SERVER ['REMOTE_ADDR'])) {
	$_SERVER ['REMOTE_ADDR'] = '127.0.0.1';
}
if (!isset($_SERVER ['HTTP_USER_AGENT'])) {
	$_SERVER ['HTTP_USER_AGENT'] = 'CLI';
}

// Start a local webserver to serve sample.ics (plugin only accepts http(s) URLs).
$port = 8011;
$docroot = __DIR__;
$cmd = escapeshellcmd(PHP_BINARY) . ' -S 127.0.0.1:' . (int) $port . ' -t ' . escapeshellarg($docroot);
$descriptors = array(
	0 => array('pipe', 'r'),
	1 => array('file', '/dev/null', 'a'),
	2 => array('file', '/dev/null', 'a'),
);
$proc = @proc_open($cmd, $descriptors, $pipes);
if (!is_resource($proc)) {
	fwrite(STDERR, "Failed to start built-in webserver.\n");
	exit(1);
}
register_shutdown_function(function () use (&$proc) {
	if (is_resource($proc)) {
		@proc_terminate($proc);
		@proc_close($proc);
	}
});

usleep(250000);

// Boot FlatPress (minimal)
chdir($root);

// The Smarty plugin index cache stores absolute paths; if a FlatPress tree is moved/extracted
// elsewhere, this file can point to stale locations. Remove it so the index is rebuilt.
$idx = $root . '/fp-content/cache/smarty_plugins.index.php';
if (is_file($idx)) {
	@unlink($idx);
}

require_once $root . '/defaults.php';
require_once $root . '/fp-includes/core/includes.php';

// Minimal environment for template variables
$GLOBALS ['fp_config'] = config_load();
$GLOBALS ['lang'] = lang_load();
set_locale();

/** @var array $fp_config */
$fp_config = $GLOBALS ['fp_config'];
/** @var \Smarty\Smarty $smarty */
global $smarty;

// Configure Smarty similarly to system_init (needed for consistent compilation)
$smarty->setCompileDir(COMPILE_DIR);
$smarty->setCacheDir(CACHE_DIR);
$smarty->setCaching(false);

// Make config available to templates (used for date/time formatting)

// Make language array available to templates (some templates still use {$lang...})
$smarty->assign('lang', $GLOBALS ['lang']);
$smarty->assign('fp_config', $fp_config);

// Load plugin code (registers widget + tag renderer)
require_once $root . '/fp-plugins/icalfeed/plugin.icalfeed.php';

$url = 'http://127.0.0.1:' . (int) $port . '/sample.ics';
$html = icalfeed_render(
	array(
		'feed_urls' => $url,
		'days_ahead' => 20,
		'limit' => 20,
		'mode' => 'list',
		'privacy' => 'details',
		'show_location' => true,
		'with_default_title' => true,
	),
	'plugin:icalfeed/list'
);

if (!is_string($html) || trim($html) === '') {
	fwrite(STDERR, "Render failed or returned empty output.\n");
	exit(1);
}

if (strpos($html, 'class="icalfeed"') === false) {
	fwrite(STDERR, "Rendered HTML did not contain expected container.\n");
	exit(1);
}
if (strpos($html, 'One-off UTC Meeting') === false) {
	fwrite(STDERR, "Rendered HTML did not contain expected summary.\n");
	exit(1);
}

echo "Rendered HTML (first 40 lines):\n\n";
$lines = preg_split('/\r\n|\r|\n/', $html);
if (is_array($lines)) {
	for ($i = 0; $i < min(40, count($lines)); $i++) {
		echo $lines [$i] . "\n";
	}
}

echo "\nOK\n";
