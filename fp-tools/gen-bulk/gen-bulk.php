<?php
declare(strict_types=1);

/**
 * gen-bulk.php — FlatPress Bulk Generator
 * CLI: php gen-bulk.php <entries> <comments_per_entry> [seed] [spread_days]
 * Web: gen-bulk.php?n=3000&k=10&seed=1234&spread=1080
 */

@ignore_user_abort(true); // continue if client disconnects
if (function_exists('set_time_limit')) {
	@set_time_limit(0);
}
@ini_set('max_execution_time', '0'); // 0 = unlimited (PHP)

/**
 * Bootstrap
 */
$root = realpath(__DIR__);
if (!$root || !is_file($root . '/defaults.php')) {
	header('Content-Type: text/plain; charset=utf-8');
	echo "FlatPress root not found.\n";
	exit(2);
}
chdir($root);
require_once $root . '/defaults.php';

/**
 * Load $fp_config
 */
$__fp_default = [];
$__fp_user = [];
if (defined('CONFIG_DEFAULT') && is_file(CONFIG_DEFAULT)) {
	require CONFIG_DEFAULT;
	$__fp_default = isset($fp_config) && is_array($fp_config) ? $fp_config : [];
}
if (defined('CONFIG_FILE') && is_file(CONFIG_FILE)) {
	require CONFIG_FILE;
	$__fp_user = isset($fp_config) && is_array($fp_config) ? $fp_config : [];
}
$fp_config = array_replace_recursive($__fp_default, $__fp_user);

/**
 * Core
 */
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
	header('Content-Type: text/plain; charset=utf-8');
	echo "Core/includes.php not found.\n";
	exit(3);
}

/**
 * PrettyURLs plugin (optional)
 */
if (!isset($GLOBALS ['plugin_prettyurls'])) {
	$p = $root . '/fp-plugins/prettyurls/plugin.prettyurls.php';
	if (is_file($p)) {
		require_once $p;
	}
}

/**
 * Params
 */
[$N, $K, $SEED, $SPREAD] = read_params();
mt_srand($SEED);

/**
 * Helpers
 */
function out(string $msg, ?int $exitCode = null): void {
	echo $msg . PHP_EOL;
	if ($exitCode !== null) {
		exit($exitCode);
	}
}
function flush_now(): void {
	if (PHP_SAPI !== 'cli'){
		@ob_flush();
		@flush();
	}
}
function rndText(int $min = 30, int $max = 120): string {
	$w = ["FlatPress", "Performance", "Caching", "Smarty", "APCu", "Plugin", "Template", "Entry", "Comment", "Search", "Category", "Index", "Parser", "Request", "PHP", "Theme", "Widget", "Tag", "RSS", "BBCode"];
	$n = mt_rand($min,$max);
	$o = [];
	for($i = 0; $i < $n; $i++){
		$o [] = $w [mt_rand(0,count($w) -1)];
	}
	return implode(' ', $o);
}

/**
 * unique entry timestamp - avoids ID collisions (second resolution)
 */
function unique_entry_ts(int $now, int $spreadDays): int {
	$span = $spreadDays > 0 ? ($spreadDays * 86400 + 86399) : 0;
	$ts = $now - ($span > 0 ? mt_rand(0, $span) : 0);
	// bump until free
	do {
		$id = bdb_idfromtime(BDB_ENTRY, $ts);
		$f = bdb_idtofile($id, BDB_ENTRY) . EXT;
		if (!file_exists($f)) {
			return $ts;
		}
		$ts++;
	}
	while (true);
}

/**
 * unique comment timestamp under an entry
 */
function unique_comment_ts(string $entryId, int $baseTs): int {
	$dir = bdb_idtofile($entryId, BDB_COMMENT);
	$ts = $baseTs;
	while (true) {
		$cid = bdb_idfromtime(BDB_COMMENT, $ts);
		$f = $dir . $cid . EXT;
		if (!file_exists($f)) {
			return $ts;
		}
		$ts++;
	}
}

/**
 * PrettyURLs index fallback when plugin object not present
 */
function fp_prettyurls_index_add(string $slug, string $entryId, int $ts): void {
	if (!defined('CACHE_DIR')) {
		return;
	}
	$yymm = date('ym', $ts);
	$dd = date('d', $ts);
	$file = CACHE_DIR . '%%prettyurls-index.tmp' . $yymm;
	$map = [];
	if (@is_file($file)) {
		$raw = @file_get_contents($file);
		$tmp = @unserialize($raw);
		if (is_array($tmp)) {
			$map = $tmp;
		}
	}
	if (!isset($map [$dd]) || !is_array($map [$dd])) {
		$map [$dd] = [];
	}
	$map [$dd] [md5($slug)] = $entryId;
	if (function_exists('io_write_file')) {
		io_write_file($file, serialize($map));
	} else {
		@file_put_contents($file, serialize($map), LOCK_EX);
	}
}

/**
 * Prepares categories and subcategories for the bulk generator.
 *
 * - Creates a default category structure in categories.txt if necessary.
 * - Ensures that the category cache (categories_encoded.dat) is set up.
 * - Returns a list of [main category ID, subcategory ID] pairs.
 */
function bulkgen_prepare_categories(): array {
	if (!defined('CONTENT_DIR')) {
		return [];
	}

	$catFile = CONTENT_DIR . 'categories.txt';
	$raw = '';

	// Import existing categories (if available)
	if (function_exists('io_load_file') && @file_exists($catFile)) {
		$raw = (string) io_load_file($catFile);
	} elseif (@file_exists($catFile)) {
		$raw = (string) @file_get_contents($catFile);
	}

	// If nothing exists: Create default tree
	if (trim($raw) === '') {
		$defaultLines = [
			'General :1',
			'News :2',
			'--Announcements :5',
			'--Events :3',
			'----Miscellaneous :6',
			'Technology :4',
			'Other :7',
		];

		$raw = implode("\n", $defaultLines) . "\n";

		if (function_exists('io_write_file')) {
			io_write_file($catFile, $raw);
		} else {
			@file_put_contents($catFile, $raw, LOCK_EX);
		}
	}

	// Ensure that FlatPress builds/updates the category list and encoded cache
	if (function_exists('entry_categories_encode')) {
		// builds fp-content/content/categories_encoded.dat from $raw
		@entry_categories_encode($raw);
	}
	if (function_exists('entry_categories_list')) {
		// warms the flat [id => parent] cache
		@entry_categories_list();
	}

	// Evaluate categories.txt according to (category, subcategory) pairs
	$lines = explode("\n", $raw);
	$parentsByDepth = [];
	$meta = [];

	foreach ($lines as $line) {
		$line = rtrim($line);
		if ($line === '') {
			continue;
		}

		// Format: [- -]Name :ID
		if (!preg_match('/^(-*)(.*?):\s*(\d+)\s*$/u', $line, $m)) {
			continue;
		}

		$depth = strlen($m [1]);
		$label = trim($m [2]);
		$id = (int) $m [3];

		$parent = null;
		if ($depth > 0) {
			$parent = $parentsByDepth [$depth - 1] ?? null;
		}

		$parentsByDepth [$depth] = $id;
		$meta [$id] = [
			'label' => $label,
			'parent' => $parent,
			'depth' => $depth,
		];
	}

	$pairs = [];

	foreach ($meta as $id => $info) {
		if ($info ['parent'] === null) {
			continue; // pure main category, no subcategory
		}

		// Run up to the top category
		$top = $id;
		$seen = [];
		$p = $info ['parent'];
		while ($p !== null && $p > 0 && !in_array($p, $seen, true)) {
			$seen[] = $p;
			$top = $p;
			$p = $meta [$p] ['parent'] ?? null;
		}

		if ($top !== $id) {
			// Subcategory (lower than level 0)
			$pairs [] = [$top, $id];
		} elseif ($info ['parent'] !== null) {
			// Direkt unter einer Hauptkategorie
			$pairs [] = [$info ['parent'], $id];
		}
	}

	// Fallback: if there are only flat categories without hierarchy
	if (!$pairs && $meta) {
		foreach (array_keys($meta) as $id) {
			$pairs [] = [$id, $id];
		}
	}

	return $pairs;
}

/**
 * Generation
 */
$bulkCategoryPairs = [];
if (defined('CONTENT_DIR')) {
	$bulkCategoryPairs = bulkgen_prepare_categories();
}

$createdEntries = 0;
$failEntries = 0;
$createdComments = 0;
$failComments = 0;
$now = time();
$madeIds = []; // list of created entry IDs for verification

for ($i = 0; $i < $N; $i++) {
	// unique timestamp per entry
	$ts = unique_entry_ts($now, $SPREAD);

	$entry = [
		'date' => $ts,
		'subject' => sprintf("Entry %06d", $i + 1),
		'content' => "Beginning: " . rndText(20, 40) . "\n\n[more]\n\n" . rndText(100, 220),
		'categories' => [],
		'comments' => 'yes',
		'author' => 'bulkgen',
		'format' => 'bbcode',
	];

	// Assign exactly one category + one subcategory to each entry
	if (!empty($bulkCategoryPairs)) {
		$pair = $bulkCategoryPairs[array_rand($bulkCategoryPairs)];
		if (is_array($pair) && count($pair) === 2) {
			$entry['categories'] = [
				(int) $pair [0], // Main category
				(int) $pair [1], // Subcategory
			];
		}
	}

	$eid = entry_save($entry);
	if (!is_string($eid) || !preg_match('/^entry[0-9]{6}-[0-9]{6}$/', $eid)) {
		$failEntries++;
		continue;
	}
	$createdEntries++;
	$madeIds [] = $eid;

	// PrettyURLs
	$slug = function_exists('sanitize_title') ? sanitize_title($entry ['subject']) : $entry ['subject'];
	if (isset($GLOBALS ['plugin_prettyurls']) && is_object($GLOBALS ['plugin_prettyurls'])) {
		@$GLOBALS ['plugin_prettyurls']->cache_add($eid, ['subject' => $entry ['subject']]);
	} else {
		fp_prettyurls_index_add($slug, $eid, $ts);
	}

	// comments with unique timestamps
	$lastCid = null;
	$ctsBase = $ts + 1; // start after post time
	for ($j = 0; $j < $K; $j++) {
		$cts = unique_comment_ts($eid, $ctsBase + $j);
		$c = [
			'name' => (string)"User$j",
			'email' => (string)"user$j@example.test",
			'url' => (string)'',
			'ip' => (string)'127.0.0.1',
			'content' => (string)("Comment $j to $eid\n\n" . rndText(12, 40)),
			'date' => $cts,
		];
		$cid = comment_save($eid, $c);
		if (!is_string($cid)) {
			$failComments++;
		} else {
			$createdComments++;
			$lastCid = $cid;
		}
	}

	if ((($i + 1) %100) === 0) {
		out(sprintf(".. %d/%d entries, %d comments", $i+1, $N, $createdComments));
		flush_now();
	}
}

/**
 * Verification
 */
// count files we actually created (unique IDs)
$totalEntryFiles = 0;
foreach ($madeIds as $id) {
	// CORRECT: entry_exists() returns the path or false
	$f = entry_exists($id);
	if ($f && is_file($f)) {
		$totalEntryFiles++;
	}
}

// count comments under those entries
$totalCommentFiles = 0;
foreach ($madeIds as $id) {
	$dir = rtrim(bdb_idtofile($id, BDB_COMMENT), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	if (is_dir($dir)) {
		$files = @glob($dir . 'comment*.txt', GLOB_NOSORT);
		$totalCommentFiles += $files ? count($files) : 0;
	}
}

/**
 * Report
 */
out("");
out("Requested: entries=$N comments_per_entry=$K => expected_comments=" . ($N * $K));
out("Created:   entries=$createdEntries (files=$totalEntryFiles) comments=$createdComments (files=$totalCommentFiles)");
out("Failures:  entries=$failEntries comments=$failComments");
if ($createdEntries !== $N || $createdComments !== $N * $K || $totalEntryFiles !== $N || $totalCommentFiles !== $N * $K) {
	out("STATUS: MISMATCH", 1);
}
out("STATUS: OK", 0);

/**
 * Param reader
 */
function read_params(): array {
	if (PHP_SAPI === 'cli') {
		global $argv;
		$N = (int)($argv [1] ?? 0);
		$K = (int)($argv [2] ?? 0);
		$SEED = isset($argv [3]) ? (int)$argv [3] : 1;
		$SPREAD = (int)($argv [4] ?? 0);
	} else {
		header('Content-Type: text/plain; charset=utf-8');
		$N = (int)($_GET ['n'] ?? 0);
		$K = (int)($_GET ['k'] ?? 0);
		$SEED = isset($_GET ['seed']) ? (int)$_GET ['seed'] : 1;
		$SPREAD = (int)($_GET ['spread'] ?? 0);
	}
	return [max(0, $N), max(0, $K), $SEED, max(0, $SPREAD)];
}
?>
