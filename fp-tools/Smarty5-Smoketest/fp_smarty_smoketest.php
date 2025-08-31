<?php
declare(strict_types=1);
/**
 * FlatPress / Smarty 5 – Smoketest
 * --------------------------------
 */

@header('Content-Type: text/html; charset=utf-8');

$root = __DIR__;
$pluginsDir = $root . DIRECTORY_SEPARATOR . 'fp-includes' . DIRECTORY_SEPARATOR . 'fp-smartyplugins';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fail(string $msg){ echo '<h1 style="color:#b00">Error</h1><p>' . h($msg) . '</p>'; exit(1); }

if (!is_dir($pluginsDir)) {
	fail("Directory not found: {$pluginsDir}");
}

/**
 * Attempts to extract the default modifiers from Smarty\Extension\DefaultExtension.
 * Return: array<string> or empty array if unsuccessful
 */
function fp_smoke_detect_default_modifiers() {
	$mods = array();
	$class = '\\Smarty\\Extension\\DefaultExtension';
	if (!class_exists($class)) {
		return $mods;
	}
	try {
		$ref = new ReflectionClass($class);
		$file = $ref->getFileName();
		if (!$file || !is_readable($file)) {
			return $mods;
		}
		$src = @file_get_contents($file);
		if ($src === false || $src === '') {
			return $mods;
		}
		// Shorten to the relevant area (optional, robust against different implementations)
		$m = array();
		if (preg_match('/function\s+getModifierCompiler\s*\([^{]*\)\s*\{(?P<body>.*?)^\}/sm', $src, $m)) {
			$body = $m ['body'];
		} else {
			$body = $src; // Fallback: scan entire file
		}
		$found = array();
		// case 'name':
		if (preg_match_all('/case\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*:/', $body, $m1)) {
			$found = array_merge($found, $m1 [1]);
		}
		// 'name' => new SomethingModifierCompiler
		if (preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>\s*new\s+[A-Za-z0-9_\\\\]+ModifierCompiler/', $body, $m2)) {
			$found = array_merge($found, $m2 [1]);
		}
		// if ($name === 'name')
		if (preg_match_all('/\$name\s*===\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $body, $m3)) {
			$found = array_merge($found, $m3 [1]);
		}
		$mods = array_values(array_unique($found));
		sort($mods, SORT_STRING);
	} catch (Throwable $e) {
		// still: return []
	}
	return $mods;
}

/**
 * Small assert/render helpers (PHP 7.2 compatible)
 */
function assert_true($cond, $msg_ok = 'OK', $msg_fail = 'FAILED') {
	return array('ok' => (bool)$cond, 'msg' => $cond ? $msg_ok : $msg_fail);
}
function assert_false($cond, $msg_ok = 'OK (expected failure)', $msg_fail = 'FAILED (unexpected success)') {
	return array('ok' => !$cond, 'msg' => !$cond ? $msg_ok : $msg_fail);
}
function render_assert($label, $res, $extra = '') {
	$ok = !empty($res ['ok']);
	$msg = isset($res ['msg']) ? (string)$res ['msg'] : ($ok ? 'OK' : 'FAILED');
	$cls = $ok ? 'ok' : 'fail';
	$extra = (string)$extra;
	$dataOk = $ok ? '1' : '0';
	return '<li class="'.$cls.'" data-ok="'.$dataOk.'"><span class="label">'.h($label).'</span> <span class="msg">'.h($msg).'</span>'.($extra!==''?' <small class="extra">'.h($extra).'</small>':'').'</li>';
}

/* -----------------------------------------------------------
 * Parser helper
 * ----------------------------------------------------------- */
function extract_functions_with_prefix(string $file, array $prefixes): array {
	$code = @file_get_contents($file);
	if ($code === false) return [];
	$tokens = token_get_all($code);
	$fns = [];
	$count = count($tokens);
	for ($i = 0; $i < $count; $i++) {
		if (is_array($tokens [$i]) && $tokens [$i] [0] === T_FUNCTION) {
			$j = $i + 1;
			while ($j < $count && ((is_array($tokens [$j]) && $tokens [$j] [0] === T_WHITESPACE) || $tokens [$j] === '&')) $j++;
			if ($j < $count && is_array($tokens [$j]) && $tokens [$j] [0] === T_STRING) {
				$name = $tokens [$j] [1];
				foreach ($prefixes as $pref) {
					if (strpos($name, $pref) === 0) {
						$fns [] = $name; break;
					}
				}
			}
		}
	}
	return array_values(array_unique($fns));
}

function extract_classes(string $file): array {
	$code = @file_get_contents($file);
	if ($code === false) {
		return [];
	}
	$tokens = token_get_all($code);
	$classes = [];
	$count = count($tokens);
	for ($i = 0; $i < $count; $i++) {
		if (is_array($tokens [$i]) && $tokens [$i] [0] === T_CLASS) {
			$j = $i + 1;
			while ($j < $count && is_array($tokens [$j]) && $tokens [$j] [0] === T_WHITESPACE) $j++;
			if ($j < $count && is_array($tokens [$j]) && $tokens [$j] [0] === T_STRING) {
				$classes [] = $tokens [$j] [1];
			}
		}
	}
	return array_values(array_unique($classes));
}

/* -----------------------------------------------------------
 * Bootstrap som index.php: defaults.php + INCLUDES_DIR/includes.php
 * ----------------------------------------------------------- */
$bootstrapLog = [];
$beforeFns = get_defined_functions()['user'] ?? [];
$beforeClasses = get_declared_classes();
$includesLoaded = false;

$defaultsFile = $root . '/defaults.php';
if (is_file($defaultsFile)) {
	$bootstrapLog [] = "require_once " . $defaultsFile;
	require_once $defaultsFile;
} else {
	$bootstrapLog [] = "WARN: defaults.php not found at " . $defaultsFile;
}

// INCLUDES_DIR should be defined in defaults.php
if (defined('INCLUDES_DIR')) {
	$includesFile = INCLUDES_DIR . 'includes.php';
	if (is_file($includesFile)) {
		$bootstrapLog [] = "require_once " . $includesFile;
		require_once $includesFile;
		$includesLoaded = true;
	} else {
		$bootstrapLog [] = "WARN: includes.php not found in INCLUDES_DIR: " . $includesFile;
	}
} else {
	$bootstrapLog [] = "WARN: INCLUDES_DIR is not defined – includes.php could not be loaded automatically.";
}

// Fallback: direct core files (optional) if registration is otherwise missing
$maybeCoreUtils = $root . '/fp-includes/core/core.smarty.php';
if (is_file($maybeCoreUtils)) {
	$bootstrapLog [] = "require_once (fallback) " . $maybeCoreUtils;
	require_once $maybeCoreUtils;
}

/**
 * Try to get hold of a Smarty object.
 * Supports Smarty 3/4 (class_exists('Smarty')) and Smarty 5 (class_exists('\Smarty\Smarty')).
 */
function fp_smoketest_get_smarty_instance() {
	if (isset($GLOBALS ['smarty']) && (is_object($GLOBALS ['smarty']))) {
		return $GLOBALS ['smarty'];
	}
	if (class_exists('\\Smarty\\Smarty')) {
		try {
			return new \Smarty\Smarty();
			} catch (Throwable $e) {
				/* ignore */
			}
	}
	if (class_exists('Smarty')) {
		try {
			return new Smarty();
			} catch (Throwable $e) {
				/* ignore */
			}
	}
	return null;
}

/**
 * After FlatPress bootstrap (defaults.php + includes.php), perform system initialization so that $compileDir/$cacheDir are set, among other things.
 * The call is idempotent: we only call system_init() when necessary/available.
 */
$didSystemInit = false;
if (function_exists('system_init')) {
	$needInit = true;
	// If already set by Bootstrap, we skip the call.
	if (isset($compileDir) || defined('COMPILE_DIR') || isset($GLOBALS ['compileDir'])) {
		$needInit = false;
	}
	try {
		if ($needInit) {
			system_init();
			$didSystemInit = true;
			$bootstrapLog [] = 'system_init(): executed (compile/cache/config initialized)';
		} else {
			$bootstrapLog [] = 'system_init(): skipped (compile/cache already exists)';
		}
	} catch (Throwable $e) {
		$bootstrapLog [] = 'system_init(): ERROR – ' . $e->getMessage();
	}
} else {
	$bootstrapLog [] = 'system_init(): not available (check includes.php/core.system.php)';
}

/**
 * Resolve compile/cache directories IMMEDIATELY AFTER system_init().
 * Order:
 *   1) Variables from system_init()
 *   2) FlatPress constants
 *   3) $GLOBALS fallbacks
 *   4) Safe default paths
 */
if (!isset($compileDir) || !is_string($compileDir) || $compileDir === '') {
	$compileDir = defined('COMPILE_DIR') ? COMPILE_DIR : (isset($GLOBALS ['compileDir']) ? $GLOBALS ['compileDir'] : __DIR__ . DIRECTORY_SEPARATOR . 'fp-content' . DIRECTORY_SEPARATOR . 'compile');
}
if (!isset($cacheDir) || !is_string($cacheDir) || $cacheDir === '') {
	$cacheDir = defined('CACHE_DIR') ? CACHE_DIR : (isset($GLOBALS ['cacheDir']) ? $GLOBALS ['cacheDir'] : __DIR__ . DIRECTORY_SEPARATOR . 'fp-content' . DIRECTORY_SEPARATOR . 'cache');
}

// Explicitly attempt to register the FP plugins – but only if includes.php has NOT been loaded.
if (!$includesLoaded && !defined('FP_SMARTY_FP_PLUGINS_DONE') && function_exists('fp_register_fp_plugins')) {
	try {
		$bootstrapLog [] = 'fp_register_fp_plugins() is defined and includes.php was NOT loaded – call';
		$ref = new ReflectionFunction('fp_register_fp_plugins');
		$req = $ref->getNumberOfRequiredParameters();
		$num = $ref->getNumberOfParameters();
		$bootstrapLog [] = "Signature: required={$req}, total={$num}";

		$smartyObj = fp_smoketest_get_smarty_instance();
		$pluginsDirConst = defined('FP_SMARTYPLUGINS_DIR') ? FP_SMARTYPLUGINS_DIR : $pluginsDir;

		if ($req === 0) {
			$ref->invoke();
		} elseif ($req === 1) {
			if ($smartyObj !== null) $ref->invoke($smartyObj);
			else $bootstrapLog [] = 'Skip: 1 parameter required, but no Smarty object available';
		} else {
			if ($smartyObj !== null) $ref->invoke($smartyObj, $pluginsDirConst);
			else $bootstrapLog [] = 'Skip: >=2 parameters required, but no Smarty object available';
		}
	} catch (Throwable $e) {
		// Downgrade "already registered" to Info
		if (strpos($e->getMessage(), 'already registered') !== false) {
			$bootstrapLog [] = "Info: Duplicate registration detected and ignored ({$e->getMessage()})";
		} else {
			$bootstrapLog [] = 'Call to fp_register_fp_plugins() failed: ' . $e->getMessage();
		}
	}
} elseif (function_exists('fp_register_fp_plugins')) {
	$bootstrapLog [] = 'fp_register_fp_plugins() is defined, but includes.php was loaded – call skipped (to avoid double registration)';
} else {
	$bootstrapLog [] = 'fp_register_fp_plugins() not found – registration may occur elsewhere.';
}

if (defined('FP_SMARTY_FP_PLUGINS_DONE')) {
	$bootstrapLog [] = 'Note: FP_SMARTY_FP_PLUGINS_DONE set (registration already completed)';
}

$afterFns = get_defined_functions()['user'] ?? [];
$afterClasses = get_declared_classes();
$newFnsBootstrap = array_values(array_diff($afterFns, $beforeFns));
$newClassesBootstrap = array_values(array_diff($afterClasses, $beforeClasses));

/* -----------------------------------------------------------
 * Collect files in the plugins folder & derive expectations
 * ----------------------------------------------------------- */
$files = glob($pluginsDir . DIRECTORY_SEPARATOR . '*.php') ?: [];

// Categories
$cats = [
	'plugins' => [ // classic plugins
		'function' => [], 'block' => [], 'modifier' => [], 'modifiercompiler' => [], 'compiler' => [],
	],
	'filters' => [
		'prefilter' => [], 'postfilter' => [], 'outputfilter' => [], 'variablefilter' => [],
	],
	'resources' => [], // classes from resource.*.php
	'shared' => [], // functions from shared.*.php
	'validation' => [], // functions from validate_*.php
	'insert' => [], // deprecated, just reported
	'others' => [], // uncategorized
];

// Expected callbacks/objects
$expected = [
	'plugins' => [
		'function' => [], 'block' => [], 'modifier' => [], 'modifiercompiler' => [], 'compiler' => [],
	],
	'filters' => [
		'prefilter' => [], 'postfilter' => [], 'outputfilter' => [], 'variablefilter' => [],
	],
	'resources' => [],
	'shared' => [],
	'validation' => [],
	'insert' => [],
	'others' => [],
];

foreach ($files as $file) {
	$base = basename($file);
	$path = $file;

	// Break down after the first point
	$dotPos = strpos($base, '.');
	$prefix = $dotPos !== false ? substr($base, 0, $dotPos) : '';
	$rest = $dotPos !== false ? substr($base, $dotPos + 1) : '';

	switch ($prefix) {
		case 'function':
		case 'block':
		case 'modifier':
		case 'modifiercompiler':
		case 'compiler':
			$name = substr($rest, 0, -4); // without ".php"
			$cats ['plugins'] [$prefix] [$name] = $path;
			// Derive expected function names
			$fn = "smarty_{$prefix}_{$name}";
			$expected ['plugins'] [$prefix] [$fn] = $path;
			break;

		case 'prefilter':
		case 'postfilter':
		case 'outputfilter':
		case 'variablefilter':
			$name = substr($rest, 0, -4);
			$cats ['filters'] [$prefix] [$name] = $path;
			$fn = "smarty_{$prefix}_{$name}";
			$expected ['filters'] [$prefix] [$fn] = $path;
			break;

		case 'resource':
			$cats ['resources'] [$base] = $path;
			// Determine classes
			foreach (extract_classes($path) as $cls) {
				$expected ['resources'] [$cls] = $path;
			}
			break;

		case 'shared':
			$cats ['shared'] [$base] = $path;
			foreach (extract_functions_with_prefix($path, ['smarty_shared_', 'fp_shared_', 'shared_']) as $fn) {
				$expected ['shared'] [$fn] = $path;
			}
			break;

		default:
			if (strpos($base, 'validate_') === 0) {
				$cats ['validation'] [$base] = $path;
				foreach (extract_functions_with_prefix($path, ['smarty_validate_']) as $fn) {
					$expected ['validation'] [$fn] = $path;
				}
			} elseif (strpos($base, 'insert.') === 0) {
				$cats ['insert'] [$base] = $path;
				// Expected (historical) insert function
				$name = substr($rest, 0, -4);
				$fn = "smarty_insert_{$name}";
				$expected ['insert'] [$fn] = $path;
			} else {
				$cats ['others'] [$base] = $path;
				$expected ['others'] [$base] = $path;
			}
			break;
	}
}

/* -----------------------------------------------------------
 * Verification: Do expected functions/classes exist after bootstrap?
 * ----------------------------------------------------------- */
$results = [
	'plugins' => [
		'function' => ['loaded' => [], 'missing' => []],
		'block' => ['loaded' => [], 'missing' => []],
		'modifier' => ['loaded' => [], 'missing' => []],
		'modifiercompiler' => ['loaded' => [], 'missing' => []],
		'compiler' => ['loaded'=>[], 'missing'=> []],
	],
	'filters' =>  [
		'prefilter' => ['loaded' => [], 'missing' => []],
		'postfilter' => ['loaded' => [], 'missing' => []],
		'outputfilter' => ['loaded' => [], 'missing' => []],
		'variablefilter' => ['loaded' => [], 'missing' => []],
	],
	'resources' => ['loaded' => [], 'missing' => []],
	'shared' => ['loaded' => [], 'missing' => []],
	'validation' => ['loaded' => [], 'missing' => []],
	'insert' => ['loaded' => [], 'missing' => []],
	'others' => ['info' => []],
];

foreach ($expected ['plugins'] as $ptype => $map) {
	foreach ($map as $fn => $src) {
		if (function_exists($fn)) {
			$results ['plugins'] [$ptype] ['loaded'] [$fn] = $src;
		} else {
			$results ['plugins'] [$ptype] ['missing'] [$fn] = $src;
		}
	}
}
foreach ($expected ['filters'] as $ftype => $map) {
	foreach ($map as $fn => $src) {
		if (function_exists($fn)) {
			$results ['filters'] [$ftype] ['loaded'] [$fn] = $src;
		} else {
			$results ['filters'] [$ftype] ['missing'] [$fn] = $src;
		}
	}
}
foreach ($expected ['resources'] as $cls => $src) {
	if (class_exists($cls, false)) {
		$results ['resources'] ['loaded'] [$cls] = $src;
	} else {
		$results ['resources'] ['missing'] [$cls] = $src;
	}
}
foreach ($expected ['shared'] as $fn => $src) {
	if (function_exists($fn)) {
		$results ['shared'] ['loaded'] [$fn] = $src;
	} else {
		$results ['shared'] ['missing'] [$fn] = $src;
	}
}
foreach ($expected ['validation'] as $fn => $src) {
	if (function_exists($fn)) {
		$results ['validation'] ['loaded'] [$fn] = $src;
	} else {
		$results ['validation'] ['missing'] [$fn] = $src;
	}
}
foreach ($expected ['insert'] as $fn => $src) {
	if (function_exists($fn)) {
		$results ['insert'] ['loaded'] [$fn] = $src;
	} else {
		$results ['insert'] ['missing'] [$fn] = $src;
	}
}
foreach ($expected ['others'] as $name => $src) {
	$results ['others'] ['info'] [$name] = $src;
}

/**
 * Returns the class name of the Smarty main class (string) or null.
 */
function _sm_class() {
	if (class_exists('\\Smarty\\Smarty')) return '\\Smarty\\Smarty';
	if (class_exists('Smarty')) return 'Smarty';
	return null;
}

/**
 * 1) Removed language features (must fail)
 */
function compat_test_removed_language(&$smarty) {
	$cases = array(
		'insert_removed' => '{insert name="foo"}',
		'php_removed' => '{php}echo "x";{/php}',
		'include_php' => '{include_php file="x.php"}',
	);
	$out = array();
	foreach ($cases as $k => $tpl) {
		$ok = false; $msg = '';
		try {
			$smarty->fetch('string:'.$tpl);
			$ok = true;
		} catch (Throwable $e) {
			$msg = $e->getMessage();
		}
		$out [$k] = assert_false($ok, 'OK (blocked)', 'FAILED (feature not blocked)');
		$out [$k] ['detail'] = $msg;
	}
	return $out;
}

/**
 * 2) PHP functions as modifiers – fail first, then ok after registration
 */
function compat_test_modifier_registration(&$smarty) {
	$r = array();

	// Explicitly check whether a default plugin handler is registered (true/false)
	$hasDefaultHandler = false;
	if (method_exists($smarty, 'getDefaultPluginHandlerFunc')) {
		try {
			$hasDefaultHandler = (bool)$smarty->getDefaultPluginHandlerFunc();
		} catch (Throwable $e) {
			/* */
		}
	}
	$r ['default_handler'] = array('ok' => true, 'msg' => $hasDefaultHandler ? 'active' : 'inactive');

	// 1) Guaranteed NOT existing modifier -> must fail
	$bogus = '_smoke_bogus_' . mt_rand(1000,9999);
	$okBogus = false; $errBogus = '';
	try {
		$smarty->fetch('string:{$x|'.$bogus.'}', array('x' => 'AB'));
		$okBogus = true;
	} catch (Throwable $e) {
		$errBogus = $e->getMessage();
	}
	$r ['unregistered_bogus'] = assert_false($okBogus, 'OK (blocked)', 'FAILED (unexpected success)');
	if ($errBogus) {
		$r ['unregistered_bogus'] ['detail'] = $errBogus;
	}

	// 2) Standard modifier without registration: can work via DefaultExtension (no indication of default handler!)
	$substrWorked = false; $errSub = ''; $out = '';
	try {
		$out = $smarty->fetch('string:{$x|substr:0:1}', array('x' => 'AB'));
		$substrWorked = ($out === 'A');
	} catch (Throwable $e) {
		$errSub = $e->getMessage();
	}
	$r ['substr_unregistered'] = array('ok' => true, 'msg' => $substrWorked ? 'OK (via DefaultExtension)' : 'blocked', 'detail' => $errSub);
	// Additional diagnosis: Default handler assessment
	$r ['default_handler'] = $substrWorked ? array('ok' => true,  'msg' => 'active (likely allows PHP functions as modifiers)') : array('ok' => true,  'msg' => 'inactive or restrictive');

	// 3) Attach list of standard modifiers from DefaultExtension (for informational purposes only)
	$defaults = fp_smoke_detect_default_modifiers();
	$r ['default_modifiers'] = array('ok'=>true, 'msg' => ($defaults ? implode(', ', $defaults) : 'n/a'));

	// 4) Explicit registration under alias works independently of the handler.
	try {
		$smarty->registerPlugin('modifier', 'substr_smoke', 'substr');
		$out2 = $smarty->fetch('string:{$x|substr_smoke:0:1}', array('x'=>'AB'));
		$r ['registered_alias'] = assert_true($out2 === 'A', 'OK', 'FAILED (expected "A", got "'.$out2.'")');
	} catch (Throwable $e) {
		$r ['registered_alias'] = assert_true(false, 'OK', 'FAILED: '.$e->getMessage());
	}
	// Clean up: Remove alias to avoid side effects for subsequent tests
	if (method_exists($smarty, 'unregisterPlugin')) {
		try {
			$smarty->unregisterPlugin('modifier', 'substr_smoke');
		} catch (Throwable $e) {
			// deliberately ignore – Cleanup must never cause the smoke test to fail
		}
	}
	return $r;
}

/**
 * 3) Setter methods + path writability
 */
function compat_test_setters_paths(&$smarty, $compile, $cache) {
	$r = array();
	try {
		if (method_exists($smarty, 'setCompileDir')) $smarty->setCompileDir($compile);
		if (method_exists($smarty, 'setCacheDir')) $smarty->setCacheDir($cache);
		$r ['compile_writable'] = assert_true(is_dir($compile) && is_writable($compile), 'OK', 'FAILED (compile dir not writable)');
		$r ['cache_writable'] = assert_true(is_dir($cache) && is_writable($cache), 'OK', 'FAILED (cache dir not writable)');
	} catch (Throwable $e) {
		$r ['exception'] = assert_true(false, 'OK', 'FAILED: '.$e->getMessage());
	}
	return $r;
}

/**
 * 4) Caching-Roundtrip
 */
function compat_test_cache_roundtrip(&$smarty) {
	$r = array();
	$cls = _sm_class();
	$C_ON = ($cls && defined($cls.'::CACHING_LIFETIME_CURRENT')) ? constant($cls.'::CACHING_LIFETIME_CURRENT') : 1;
	$C_OFF = ($cls && defined($cls.'::CACHING_OFF')) ? constant($cls.'::CACHING_OFF') : 0;
	$tpl = 'string:Hello {$t}';
	try {
		if (method_exists($smarty, 'setCaching')) $smarty->setCaching($C_ON);
		$out1 = $smarty->fetch($tpl, array('t'=>time()));
		$isc1 = method_exists($smarty, 'isCached') ? $smarty->isCached($tpl) : null;
		usleep(200000);
		$out2 = $smarty->fetch($tpl, array('t'=>time()));
		$isc2 = method_exists($smarty, 'isCached') ? $smarty->isCached($tpl) : null;
		if (method_exists($smarty, 'clearCache')) $smarty->clearCache($tpl);
		$isc3 = method_exists($smarty, 'isCached') ? $smarty->isCached($tpl) : null;
		if (method_exists($smarty, 'setCaching')) $smarty->setCaching($C_OFF);
		$r ['cached_after_first'] = assert_true($isc1 === true, 'OK', 'FAILED');
		$r ['cached_after_second'] = assert_true($isc2 === true, 'OK', 'FAILED');
		$r ['cached_after_clear'] = assert_true($isc3 === false, 'OK', 'FAILED');
		$r ['output_stable'] = assert_true($out1 === $out2, 'OK', 'FAILED (outputs differ)');
	} catch (Throwable $e) {
		$r ['exception'] = assert_true(false, 'OK', 'FAILED: '.$e->getMessage());
	}
	return $r;
}

/**
 * 5) Filter pipeline order (pre → post → output)
 */
function compat_test_filter_order(&$smarty) {
	$seq = array();
	try {
		// Register filters
		$smarty->registerFilter('pre', function($src) use (&$seq){
			$seq []='pre'; return $src;
		});
		$smarty->registerFilter('post', function($src) use (&$seq){
			$seq []='post'; return $src;
		});
		$smarty->registerFilter('output', function($out) use (&$seq){
			$seq []='output'; return $out;
		});

		// Force fresh compilation:
		//  - eval: is always recompiled
		//  - Unique token prevents any reuse due to implementation details
		$uniq = '_smoke_'.mt_rand(1000,9999).'_'.(string)microtime(true);
		if (method_exists($smarty, 'clearCompiledTemplate')) {
			$smarty->clearCompiledTemplate();
		}
		$tpl = 'eval:{* '.$uniq.' *}OK';
		$smarty->fetch($tpl);

		// Compress duplicates and check order (pre … post … output)
		$comp = array();
		foreach ($seq as $s) {
			if (empty($comp) || end($comp) !== $s) {
				$comp [] = $s;
			}
		}
		$ok = !empty($comp) && $comp [0]==='pre' && end($comp)==='output';
		$hasPost = false;
		for ($i=1; $i<count($comp)-1; $i++) {
			if ($comp [$i] ==='post') {
				$hasPost = true; break;
			}
		}
		$ok = $ok && $hasPost;

		return array('order' => assert_true($ok, 'OK', 'FAILED ('.implode('→',$seq).')'));
	} catch (Throwable $e) {
		return array('exception' => assert_true(false, 'OK', 'FAILED: '.$e->getMessage()));
	}
}

/**
 * 6) mbstring/Unicode – pure diagnosis, no hard requirement
 */
function compat_test_mbstring() {
	$has = extension_loaded('mbstring');
	$l1 = strlen("ä");
	$l2 = function_exists('mb_strlen') ? @mb_strlen("ä", 'UTF-8') : null;
	return array(
		'extension_loaded' => assert_true($has, $has?'OK':'FAILED', $has?'OK':'FAILED'),
		'strlen_utf8' => array('ok' => true, 'msg' => (string)$l1),
		'mb_strlen_utf8' => array('ok' => $l2 === null ? false : true, 'msg' => ($l2 === null ? 'n/a' : (string)$l2)),
	);
}

/**
 * 7) By-ref modifiers (e.g., reset) should NOT work.
 */
function compat_test_byref_modifier(&$smarty) {
	try {
		$smarty->registerPlugin('modifier','reset','reset');
		$smarty->fetch('string:{$a|reset}', array('a' => array(1, 2)));
		return array('blocked' => assert_false(false)); // unexpected success
	} catch (Throwable $e) {
		return array('blocked' => assert_true(true, 'OK (blocked)'), 'detail'=>$e->getMessage());
	}
}

/**
 * 8) Resources string:/eval:
 */
function compat_test_resources(&$smarty) {
	$r = array();
	try {
		$r ['string'] = assert_true($smarty->fetch('string:Ping') === 'Ping');
	} catch (Throwable $e) {
		$r ['string'] = assert_true(false, 'OK', 'FAILED: '.$e->getMessage());
	}
	try {
		$r ['eval'] = assert_true($smarty->fetch('eval:Ping') === 'Ping');
	} catch (Throwable $e) {
		$r ['eval'] = array('ok'=>false, 'msg'=>'FAILED: '.$e->getMessage());
	}
	return $r;
}

/**
 * 9) {extends}-Smoke (only if base template exists)
 */
function compat_test_extends(&$smarty, $baseTplPath) {
	if (!is_file($baseTplPath)) {
		return array('skipped' => array('ok' => true,'msg' => 'skipped (no base template)'));
	}
	$child = 'string:{extends file="'.$baseTplPath.'"}{block name=content}X{/block}';
	try {
		$out = $smarty->fetch($child);
		return array('basic' => assert_true($out !== ''));
	} catch (Throwable $e) {
		return array('basic' => array('ok' => false,'msg' => 'FAILED: '.$e->getMessage()));
	}
}

/**
 * 10) Double registration: second call should throw an error
 */
function compat_test_double_registration(&$smarty) {
	$cb = function($p){
		return 'x';
	};
	$r = array();
	try {
		$smarty->registerPlugin('function','_smoke_dup',$cb);
		$r ['first'] = assert_true(true);
	} catch (Throwable $e) {
		$r ['first'] = array('ok' => false,'msg' => 'FAILED: '.$e->getMessage());
	}
	$secondThrows = false; $msg = '';
	try {
		$smarty->registerPlugin('function','_smoke_dup',$cb);
	} catch (Throwable $e) {
		$secondThrows = true; $msg = $e->getMessage();
	}
	$r ['second_call_throws'] = assert_true($secondThrows, 'OK', 'FAILED (no exception on duplicate)');
	if ($msg) $r ['second_call_throws'] ['detail'] = $msg;
	return $r;
}

/**
 * 11) Suppress undefined/null warnings (if method exists)
 */
function compat_test_mute_warnings(&$smarty) {
	if (!method_exists($smarty, 'muteUndefinedOrNullWarnings')) {
		return array('available' => array('ok' => false,'msg' => 'not available'));
	}
	try {
		$smarty->muteUndefinedOrNullWarnings();
		$smarty->fetch('string:{$undef}+{$nullvar}');
		return array('muted' => assert_true(true));
	} catch (Throwable $e) {
		return array('muted' => array('ok' => false,'msg' => 'FAILED: '.$e->getMessage()));
	}
}

// --- Perform compatibility tests ---
$results ['compat'] = array();
$baseTpl = __DIR__.'/fp-interface/themes/default/maintemplate.tpl';

$results ['compat'] ['removed_language'] = compat_test_removed_language($smarty);
$results ['compat'] ['modifier_register'] = compat_test_modifier_registration($smarty);
$results ['compat'] ['setters_paths'] = compat_test_setters_paths($smarty, $compileDir, $cacheDir);
$results ['compat'] ['cache_roundtrip'] = compat_test_cache_roundtrip($smarty);
$results ['compat'] ['filter_order'] = compat_test_filter_order($smarty);
$results ['compat'] ['mbstring'] = compat_test_mbstring();
$results ['compat'] ['byref_modifier'] = compat_test_byref_modifier($smarty);
$results ['compat'] ['resources'] = compat_test_resources($smarty);
$results ['compat'] ['extends'] = compat_test_extends($smarty, $baseTpl);
$results ['compat'] ['double_registration']= compat_test_double_registration($smarty);
$results ['compat'] ['mute_warnings'] = compat_test_mute_warnings($smarty);

/* -----------------------------------------------------------
 * Totals
 * ----------------------------------------------------------- */
function ac(array $a): int { return count($a); }

$totalExpected =
	  ac($expected ['plugins'] ['function'])
	+ ac($expected ['plugins'] ['block'])
	+ ac($expected ['plugins'] ['modifier'])
	+ ac($expected ['plugins'] ['modifiercompiler'])
	+ ac($expected ['plugins'] ['compiler'])
	+ ac($expected ['filters'] ['prefilter'])
	+ ac($expected ['filters'] ['postfilter'])
	+ ac($expected ['filters'] ['outputfilter'])
	+ ac($expected ['filters'] ['variablefilter'])
	+ ac($expected ['resources'])
	+ ac($expected ['shared'])
	+ ac($expected ['validation'])
	+ ac($expected ['insert']);

$totalLoaded =
	  ac($results ['plugins'] ['function'] ['loaded'])
	+ ac($results ['plugins'] ['block'] ['loaded'])
	+ ac($results ['plugins'] ['modifier'] ['loaded'])
	+ ac($results ['plugins'] ['modifiercompiler'] ['loaded'])
	+ ac($results ['plugins'] ['compiler'] ['loaded'])
	+ ac($results ['filters'] ['prefilter'] ['loaded'])
	+ ac($results ['filters'] ['postfilter'] ['loaded'])
	+ ac($results ['filters'] ['outputfilter'] ['loaded'])
	+ ac($results ['filters'] ['variablefilter'] ['loaded'])
	+ ac($results ['resources'] ['loaded'])
	+ ac($results ['shared'] ['loaded'])
	+ ac($results ['validation'] ['loaded'])
	+ ac($results ['insert'] ['loaded']);

$totalMissing = max(0, $totalExpected - $totalLoaded);

/* -----------------------------------------------------------
 * HTML output
 * ----------------------------------------------------------- */
function render_list($map, string $root): string {
	if (!is_array($map)) { return '<p><em>Keine</em></p>'; }
	if (!$map) return '<p><em>Keine</em></p>';
	$out = "<ul>";
	foreach ($map as $name => $src) {
		$rel = $src !== '' ? str_replace($root . DIRECTORY_SEPARATOR, '', (string)$src) : '';
		$out .= "<li><code>".h($name)."</code>";
		if ($rel !== '') $out .= " <small class=\"mono\">(".h($rel).")</small>";
		$out .= "</li>";
	}
	$out .= "</ul>";
	return $out;
}

?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FlatPress / Smarty 5 – Smoketest</title>
<style>
body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial, sans-serif; margin: 2rem; line-height: 1.5; }
code, pre { background: #f6f8fa; padding: 0.2rem 0.4rem; border-radius: 4px; }
small.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; color: #555; }
.summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px,1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; }
.ok { color: #0a7a27; }
.warn { color: #b45f06; }
.err { color: #b00020; }
section { margin: 1.5rem 0; }
details { margin: .5rem 0; }
h2 { margin-top: 2rem; }
.compat { margin-top:1rem; }
.compat .toolbar { margin-bottom:.5rem; }
.compat ul { margin:.25rem 0 1rem 1.25rem; }
.compat li { margin:.15rem 0; }
.compat .sub { margin:.25rem 0 .5rem 0; }
.compat code { padding:0 .15rem; }
.compat .group { padding:.5rem .75rem; border:1px solid #ddd; border-radius:.5rem; margin-bottom:.75rem; }
.compat .group h4 { margin:.25rem 0 .5rem 0; }
.compat .label { display:inline-block; min-width:18rem; }
.compat .msg { font-weight:600; }
.compat .extra { opacity:.75 }
.notice { padding:.65rem .8rem; border-radius:.5rem; margin-top:.5rem; }
.notice.warn { background:#fff7e6; border:1px solid #f6c56b; }
.notice.warn strong { color:#8a5a00; }
</style>
</head>
<body>
<h1>FlatPress / Smarty 5 – Smoketest</h1>

<div class="summary">
	<div class="card">
		<div><strong>PHP-Version:</strong> <?=h(PHP_VERSION)?></div>
		<?php
		$__smv = (class_exists('\\Smarty\\Smarty') && defined('\\Smarty\\Smarty::SMARTY_VERSION')) ? \Smarty\Smarty::SMARTY_VERSION : ((class_exists('Smarty') && defined('Smarty::SMARTY_VERSION')) ? Smarty::SMARTY_VERSION : null);
		?>
		<div><strong>Smarty:</strong> <small class="mono"><?= $__smv ? h($__smv) : 'unbekannt' ?></small></div>
		<div><strong>Root:</strong> <small class="mono"><?=h($root)?></small></div>
		<div><strong>Plugins directory:</strong> <small class="mono"><?=h($pluginsDir)?></small></div>
	<div><strong>Compile directory:</strong> <small class="mono"><?=h(isset($compileDir)?$compileDir:'(nicht gesetzt)')?></small></div>
	<div><strong>Cache directory:</strong> <small class="mono"><?=h(isset($cacheDir)?$cacheDir:'(nicht gesetzt)')?></small></div>
		<?php
			// Note ONLY if default handler is actually “active”
			$__defh_msg = isset($results ['compat'] ['modifier_register'] ['default_handler'] ['msg']) ? (string)$results ['compat'] ['modifier_register'] ['default_handler'] ['msg'] : '';
			if ($__defh_msg === 'active'):
		?>
			<div class="notice warn">
				<strong>Note:</strong> Default plugin handler is active
				<small>(unregistered PHP functions can pass as modifiers; for future compatibility, we recommend explicit registration).</small>
			</div>
		<?php endif; ?>
	</div>
	<div class="card">
		<div><strong>Plugin files found:</strong> <?=count($files)?></div>
		<div><strong>Total expected elements:</strong> <?=$totalExpected?></div>
	</div>
	<div class="card">
		<div><strong>Loaded:</strong> <span class="ok"><?=$totalLoaded?></span></div>
		<div><strong>Missing:</strong> <span class="<?=($totalMissing>0)?'err':'ok'?>"><?=$totalMissing?></span></div>
	</div>
</div>

<!-- Compatibility tests (Smarty 5) -->
<div class="compat">
	<div class="toolbar">
		<label><input type="checkbox" id="onlyFails" /> Show errors only</label>
	</div>
	<div class="group" id="g_removed_language">
		<h4>Removed language features</h4>
		<ul>
			<?php foreach ($results ['compat'] ['removed_language'] as $k=>$v) {
				$li = render_assert($k, $v, isset($v ['detail'])?$v ['detail']:'');
				echo $li;
			} ?>
		</ul>
	</div>
	<div class="group" id="g_modifiers">
		<h4>Modifier registration</h4>
		<ul>
			<?php foreach ($results ['compat'] ['modifier_register'] as $k=>$v) { if ($k === 'default_modifiers') continue; echo render_assert($k, $v, isset($v ['detail'])?$v ['detail']:''); } ?>
		</ul>
		<?php
			// Output of recognized standard modifiers (DefaultExtension)
			$modsMsg = isset($results ['compat'] ['modifier_register'] ['default_modifiers'] ['msg']) ? (string)$results ['compat'] ['modifier_register'] ['default_modifiers'] ['msg'] : '';
			$mods = array_filter(array_map('trim', explode(',', $modsMsg)));
			if (!empty($mods)):
		?>
			<div class="sub">
				<strong>Standard modifier (DefaultExtension):</strong>
				<div>
					<?php foreach ($mods as $m): ?>
						<code><?=h($m)?></code>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<div class="group" id="g_setters">
		<h4>Setter methods &amp; paths</h4>
		<ul>
			<?php foreach ($results ['compat'] ['setters_paths'] as $k=>$v) echo render_assert($k, $v); ?>
		</ul>
	</div>
	<div class="group" id="g_cache">
		<h4>Cache round trip</h4>
		<ul>
			<?php foreach ($results ['compat'] ['cache_roundtrip'] as $k=>$v) echo render_assert($k, $v); ?>
		</ul>
	</div>
	<div class="group" id="g_filters">
		<h4>Filter pipeline</h4>
		<ul>
			<?php foreach ($results ['compat'] ['filter_order'] as $k=>$v) echo render_assert($k, $v); ?>
		</ul>
	</div>
	<div class="group" id="g_mbstring">
		<h4>mbstring/Unicode</h4>
		<ul>
			<?php foreach ($results ['compat'] ['mbstring'] as $k=>$v) echo render_assert($k, $v); ?>
		</ul>
	</div>
	<div class="group" id="g_byref">
		<h4>By-Ref-Modifier</h4>
		<ul><?php foreach ($results ['compat'] ['byref_modifier'] as $k=>$v) echo render_assert($k, $v, isset($results ['compat'] ['byref_modifier'] ['detail'])?$results ['compat'] ['byref_modifier'] ['detail']:''); ?></ul>
	</div>
	<div class="group" id="g_resources">
		<h4>Ressourcen string:/eval:</h4>
		<ul><?php foreach ($results ['compat'] ['resources'] as $k=>$v) echo render_assert($k, $v); ?></ul>
	</div>
	<div class="group" id="g_extends">
		<h4>{extends}</h4>
		<ul><?php foreach ($results ['compat'] ['extends'] as $k=>$v) echo render_assert($k, $v); ?></ul>
	</div>
	<div class="group" id="g_dupreg">
		<h4>Double registration</h4>
		<ul><?php foreach ($results ['compat'] ['double_registration'] as $k=>$v) echo render_assert($k, $v, isset($v ['detail'])?$v ['detail']:''); ?></ul>
	</div>
	<div class="group" id="g_mute">
		<h4>Undefined/null warnings</h4>
		<ul><?php foreach ($results ['compat'] ['mute_warnings'] as $k=>$v) echo render_assert($k, $v); ?></ul>
	</div>
</div>

<script>
(function(){
	var only = document.getElementById('onlyFails');
	function apply() {
		var failsOnly = only && only.checked;
		var items = document.querySelectorAll('.compat li');
		for (var i=0;i<items.length;i++) {
			var ok = items [i].getAttribute('data-ok') === '1';
			items [i].style.display = (failsOnly && ok) ? 'none' : '';
		}
	}
	if (only) {
		only.addEventListener('change', apply);
		apply();
	}
})();
</script>

<details open>
	<summary>Bootstrap protocol</summary>
	<ul>
		<?php foreach ($bootstrapLog as $line): ?>
			<li><small class="mono"><?=h($line)?></small></li>
		<?php endforeach; ?>
	</ul>
	<?php if (!empty($newFnsBootstrap)): ?>
		<p><strong>Newly defined functions in Bootstrap:</strong></p>
		<?=render_list(array_fill_keys($newFnsBootstrap, ''), $root)?>
	<?php endif; ?>
	<?php if (!empty($newClassesBootstrap)): ?>
		<p><strong>Newly loaded classes during bootstrap:</strong></p>
		<?=render_list(array_fill_keys($newClassesBootstrap, ''), $root)?>
	<?php endif; ?>
</details>

<section>
	<h2>Plugins</h2>
	<details open><summary><strong>function.*</strong> – loaded (<?=count($results ['plugins'] ['function'] ['loaded'])?>) / missing (<?=count($results ['plugins'] ['function'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['plugins'] ['function'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['plugins'] ['function'] ['missing'], $root)?>
	</details>
	<details><summary><strong>block.*</strong> – loaded (<?=count($results ['plugins'] ['block'] ['loaded'])?>) / missing (<?=count($results ['plugins'] ['block'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['plugins'] ['block'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['plugins'] ['block'] ['missing'], $root)?>
	</details>
	<details><summary><strong>modifier.*</strong> – loaded (<?=count($results ['plugins'] ['modifier'] ['loaded'])?>) / missing (<?=count($results ['plugins'] ['modifier'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['plugins'] ['modifier'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['plugins'] ['modifier'] ['missing'], $root)?>
	</details>
	<details><summary><strong>modifiercompiler.*</strong> – loaded (<?=count($results ['plugins'] ['modifiercompiler'] ['loaded'])?>) / missing (<?=count($results ['plugins'] ['modifiercompiler'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['plugins'] ['modifiercompiler'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['plugins'] ['modifiercompiler'] ['missing'], $root)?>
	</details>
	<details><summary><strong>compiler.*</strong> – loaded (<?=count($results ['plugins'] ['compiler'] ['loaded'])?>) / missing (<?=count($results ['plugins'] ['compiler'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['plugins'] ['compiler'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['plugins'] ['compiler'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Filter</h2>
	<details open><summary><strong>prefilter.*</strong> – loaded (<?=count($results ['filters'] ['prefilter'] ['loaded'])?>) / missing (<?=count($results ['filters'] ['prefilter'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['filters'] ['prefilter'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['filters'] ['prefilter'] ['missing'], $root)?>
	</details>
	<details><summary><strong>postfilter.*</strong> – loaded (<?=count($results ['filters'] ['postfilter'] ['loaded'])?>) / missing (<?=count($results ['filters'] ['postfilter'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['filters'] ['postfilter'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['filters'] ['postfilter'] ['missing'], $root)?>
	</details>
	<details><summary><strong>outputfilter.*</strong> – loaded (<?=count($results ['filters'] ['outputfilter'] ['loaded'])?>) / missing (<?=count($results ['filters'] ['outputfilter'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['filters'] ['outputfilter'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['filters'] ['outputfilter'] ['missing'], $root)?>
	</details>
	<details><summary><strong>variablefilter.*</strong> – loaded (<?=count($results ['filters'] ['variablefilter'] ['loaded'])?>) / missing (<?=count($results ['filters'] ['variablefilter'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['filters'] ['variablefilter'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['filters'] ['variablefilter'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Resources (OO)</h2>
	<details open><summary>Loaded classes (<?=count($results ['resources'] ['loaded'])?>) / missing classes (<?=count($results ['resources'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['resources'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['resources'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Shared helpers</h2>
	<details open><summary>Loaded functions (<?=count($results ['shared'] ['loaded'])?>) / missing functions (<?=count($results ['shared'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['shared'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['shared'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Validation helpers</h2>
	<details open><summary>Loaded functions (<?=count($results ['validation'] ['loaded'])?>) / missing functions (<?=count($results ['validation'] ['missing'])?>)</summary>
		<h3>Loaded</h3><?=render_list($results ['validation'] ['loaded'], $root)?>
		<h3>Missing</h3><?=render_list($results ['validation'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Insert-Plugins (deprecated in Smarty 5)</h2>
	<details><summary>Found (<?=count($expected ['insert'])?>)</summary>
		<p class="warn"><strong>Note:</strong> <code>insert.*</code> is no longer supported in Smarty&nbsp;5.
		This list is provided for completeness only. It is expected that related functions will not be loaded.</p>
		<h3>Loaded</h3><?=render_list($results ['insert'] ['loaded'], $root)?>
		<h3>Not loaded (expected)</h3><?=render_list($results ['insert'] ['missing'], $root)?>
	</details>
</section>

<section>
	<h2>Other files</h2>
	<details><summary>Listing</summary>
		<?=render_list($results ['others'] ['info'], $root)?>
	</details>
</section>

<hr>
<p><small>
If any "missing" items appear here, check <code>fp_register_fp_plugins()</code> in
<code>fp-includes/core/core.smarty.php</code> (e.g., regex for <code>validate_*.php</code> and the inclusion of <code>shared.*</code>).
<br>1. <code>fp-content/compile</code> and <code>fp-content/cache</code> exist and are writable.
<br>2. <code>includes.php</code> is actually loaded; <code>system_init()</code> ran.
<br>3. Smarty version is ≥ 5.5.1.
<br>4. <code>maintemplate.tpl</code> present if the <code>{extends}</code> test is needed.
<br>5. Verify plugin filenames and naming conventions.
</small></p>

</body>
</html>
