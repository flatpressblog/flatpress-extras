<?php
declare(strict_types=1);

/**
 * Bulk-Generator für FlatPress
 * CLI: php gen-bulk.php <entries> <comments_per_entry> [seed] [spread_days]
 * Web: gen-bulk.php?n=1000&k=5&seed=1234&spread=30
 */

@set_time_limit(0);
@ignore_user_abort(true);

// Determine root
$root = realpath(__DIR__);
if (!$root || !is_file($root . '/defaults.php')) {
	header('Content-Type: text/plain; charset=utf-8');
	echo "FlatPress root not found.\n";
	exit(2);
}
chdir($root);

// Load base
require_once $root . '/defaults.php';

// Load configuration (fill $fp_config)
$__fp_default = [];
$__fp_user = [];
if (defined('CONFIG_DEFAULT') && is_file(CONFIG_DEFAULT)) {
	require CONFIG_DEFAULT; $__fp_default = isset($fp_config) && is_array($fp_config) ? $fp_config : [];
}
if (defined('CONFIG_FILE') && is_file(CONFIG_FILE)) {
	require CONFIG_FILE; $__fp_user = isset($fp_config) && is_array($fp_config) ? $fp_config : [];
}
$fp_config = array_replace_recursive($__fp_default, $__fp_user);

// Load core
$incCandidates = [
	INCLUDES_DIR . 'includes.php',
	$root . '/fp-includes/core/includes.php',
	$root . '/fp-includes/includes.php',
];
$incFound = false;
foreach ($incCandidates as $cand) {
	if (is_file($cand)) {
		require_once $cand;
		$incFound = true;
		break;
	}
}
if (!$incFound) {
	header('Content-Type: text/plain; charset=utf-8'); echo "Core/includes.php not found.\n"; exit(3);
}

// Ensure PrettyURLs plugin (for CLI/Web)
if (!isset($GLOBALS['plugin_prettyurls'])) {
	$p = $root . '/fp-plugins/prettyurls/plugin.prettyurls.php';
	if (is_file($p)) {
		require_once $p;
	}
}

// Read parameters
[$N, $K, $SEED, $SPREAD] = read_params();
mt_srand($SEED);

// Statistics
$createdEntries = 0;
$createdComments = 0;
$failEntries = 0;
$failComments = 0;
$errors = [];
$now = time();

for ($i=0; $i < $N; $i++) {
	$ts = $now - (mt_rand(0, $SPREAD) * 86400);

	$entry = [
		'date' => $ts,
		'subject' => sprintf("Entry %06d", $i + 1),
		'content' => "Beginning: " . rndText(20, 40) . "\n\n[more]\n\n" . rndText(100, 220),
		'categories' => [],
		'comments' => 'yes',
		'author' => 'bulkgen',
		'format' => 'bbcode',
	];

	$eid = entry_save($entry);
	if (!is_string($eid) || !preg_match('/^entry[0-9]{6}-[0-9]{6}$/', $eid)) {
		$failEntries++;
		$errors[] = "Entry $i failed: " . var_export($eid, true);
		continue;
	}
	$createdEntries++;

	// UPDATE PrettyURLs index: primarily via plugin, otherwise fallback file
	$slug = function_exists('sanitize_title') ? sanitize_title($entry['subject']) : $entry['subject'];
	if (isset($GLOBALS['plugin_prettyurls']) && is_object($GLOBALS['plugin_prettyurls'])) {
		// Plugin internal: sets y/m/d/md5(slug) - entryID and saves cache
		@$GLOBALS['plugin_prettyurls']->cache_add($eid, ['subject' => $entry['subject']]);
	} else {
		// Fallback: Maintain index file directly (only if plugin is not loaded)
		fp_prettyurls_index_add($slug, $eid, $ts);
	}

	// Create comments
	$lastCid = null;
	for ($j = 0; $j < $K; $j++) {
		$c = [
			'name' => (string)"User$j",
			'email' => (string)"user$j@example.test",
			'url' => (string)'',
			'ip' => (string)'127.0.0.1',
			'content' => (string)("Kommentar $j zu $eid\n\n" . rndText(12, 40)),
		];
		$cid = comment_save($eid, $c);
		if (!is_string($cid)) {
			$failComments++;
			$errors[] = "Comment j=$j to $eid failed";
		} else {
			$createdComments++;
			$lastCid = $cid;
		}
	}

	// Output sample link for manual verification
	if ($lastCid) {
		$y = date('Y', $ts);
		$m = date('m', $ts);
		$d = date('d', $ts);
		$base = rtrim(BLOG_BASEURL, '/');
		$pretty = plugin_getoptions('prettyurls', 'mode'); // 0=rewrites, 1=index.php/, 2=?u=/
		if ($pretty === 1) {
			$url = $base . '/index.php/' . $y . '/' . $m . '/' . $d . '/' . $slug . '/comments/#' . $lastCid;
		} elseif ($pretty === 2) {
			$url = $base . '/?u=/' . $y . '/' . $m . '/' . $d . '/' . $slug . '/comments/#' . $lastCid;
		} else {
			$url = $base . '/' . $y . '/' . $m . '/' . $d . '/' . $slug . '/comments/#' . $lastCid;
		}
		out($url);
	}

	if ((($i + 1) %100) === 0) {
		out(sprintf(".. %d/%d Entries, %d Comments", $i+1, $N, $createdComments));
		flush_now();
	}
}

// Rough verification: Number of entries
$totalFiles = count(glob(CONTENT_DIR . '??' . DIRECTORY_SEPARATOR . '??' . DIRECTORY_SEPARATOR . 'entry*.txt', GLOB_NOSORT) ?: []);

out("");
out("SEED=$SEED SPREAD_DAYS=$SPREAD");
out("Generated: entries=$createdEntries comments=$createdComments");
out("Error:  entries=$failEntries comments=$failComments");
out("Total number of entry files in the content: $totalFiles");

if ($errors) {
	@file_put_contents($root . DIRECTORY_SEPARATOR . 'gen-bulk.log', implode(PHP_EOL, $errors) . PHP_EOL, FILE_APPEND);
	out("Details: gen-bulk.log");
}

exit($failEntries > 0 ? 1 : 0);

/* ===== Helpers ===== */

function read_params(): array {
	if (PHP_SAPI === 'cli') {
		global $argv;
		$N = (int)($argv[1] ?? 0);
		$K = (int)($argv[2] ?? 0);
		$SEED = isset($argv[3]) ? (int)$argv[3] : 1;
		$SPREAD = (int)($argv[4] ?? 0);
	} else {
		header('Content-Type: text/plain; charset=utf-8');
		$N = (int)($_GET['n'] ?? 0);
		$K = (int)($_GET['k'] ?? 0);
		$SEED = isset($_GET['seed']) ? (int)$_GET['seed'] : 1;
		$SPREAD = (int)($_GET['spread'] ?? 0);
	}
	return [max(0,$N), max(0,$K), $SEED, max(0,$SPREAD)];
}
function rndText(int $min=30, int $max=120): string {
	$w = ["FlatPress", "Performance", "Caching", "Smarty", "APCu", "Plugin", "Template", "Entry", "Comment", "Search", "Category", "Index", "Parser", "Request", "PHP","Theme", "Widget", "Tag", "RSS", "BBCode"];
	$n = mt_rand($min, $max);
	$o = [];
	for($i = 0; $i < $n; $i++) {
		$o[] = $w [mt_rand(0, count($w)-1)];
	}
	return implode(' ', $o);
}
function out(string $msg, bool $isError = false, ?int $exitCode = null): void {
	echo $msg . PHP_EOL;
	if ($exitCode !== null){
		exit($exitCode);
	}
}
function flush_now(): void {
	if (PHP_SAPI !== 'cli') {
		@ob_flush();
		@flush();
	}
}

/**
 * PrettyURLs index fallback: Only if plugin is not loaded
 */
function fp_prettyurls_index_add(string $slug, string $entryId, int $ts): void {
	if (!defined('CACHE_DIR')) {
		return;
	}
	$yymm = date('ym', $ts); // z.B. "2510"
	$dd = date('d', $ts); // z.B. "02"
	$file = CACHE_DIR . '%%prettyurls-index.tmp' . $yymm;
	$map = [];
	if (@is_file($file)) {
		$raw = @file_get_contents($file);
		$tmp = @unserialize($raw);
		if (is_array($tmp)) {
			$map = $tmp;
		}
	}
	if (!isset($map[$dd]) || !is_array($map[$dd])) {
		$map[$dd] = [];
	}
	$map[$dd][md5($slug)] = $entryId;
	if (function_exists('io_write_file')) {
		io_write_file($file, serialize($map));
	} else {
		@file_put_contents($file, serialize($map), LOCK_EX);
	}
}
