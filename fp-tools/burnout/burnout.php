<?php
/**
 * FlatPress Burnout Benchmark
 *
 * CLI tool. Generates many HTTP requests against a FlatPress instance and measures times.
 * Uses curl_multi for parallelism; falls back to sequential if cURL is missing.
 *
 * Usage:
 *   php burnout.php --url=“https://example.com/” --requests=500 --concurrency=20 --paths=“/,/index.php,/?x=cat:general” --warmup=10 --cache-bust=0
 *
 * Output:
 *   - Summary on STDOUT
 *   - CSV under fp-content/cache/benchmarks/bench-YYYYmmdd-HHMMSS.csv (or /tmp fallback)
 */

// --- Arguments ---
$longopts  = array(
	"url:",           // Base URL, e.g., https://example.com/
	"requests::",     // Number (default 500)
	"concurrency::",  // Concurrency (default 20)
	"paths::",        // Comma-separated paths, used in rotation. Default: “/”
	"warmup::",       // Total warmup requests (default 10)
	"header::",       // Optional header, can be used multiple times: --header=“Cookie: a=b” (cURL only)
	"cache-bust::",   // 1 = Add query string, default 0
	"timeout::"       // Seconds per request, default 30
);
$args = getopt("", $longopts);

$IS_CLI = (PHP_SAPI === 'cli');
if (!$IS_CLI && !headers_sent()) {
	if (PHP_SAPI !== 'cli') {
		header('Content-Type: text/plain; charset=UTF-8');
	}
}

function stderr($s){
	if (defined('STDERR')) {
		@fwrite(STDERR, $s.PHP_EOL);
	} else {
		// Web-Fallback: log + echo
		@error_log($s);
		echo $s.PHP_EOL;
	}
}

function fp_discover_baseurl() {
	// Browser: derive from request
	if (PHP_SAPI !== 'cli') {
		if (!empty($_GET ['url'])) {
			return rtrim((string)$_GET ['url'], '/');
		}
		$host = $_SERVER ['HTTP_X_FORWARDED_HOST'] ?? $_SERVER ['HTTP_HOST'] ?? $_SERVER ['SERVER_NAME'] ?? null;
		$proto = $_SERVER ['HTTP_X_FORWARDED_PROTO']
					 ?? ((!empty($_SERVER ['HTTPS']) && $_SERVER ['HTTPS'] !== 'off') ? 'https' : 'http');
		if ($host) {
			$basePath = rtrim(str_replace('\\','/', dirname($_SERVER ['SCRIPT_NAME'] ?? '/')), '/');
			return rtrim($proto . '://' . $host . $basePath, '/');
		}
		return null;
	}

	// CLI: 1) explizit
	$u = getenv('FP_BASEURL');
	if ($u) return rtrim((string)$u, '/');

	// CLI: 2) PARSE literal from defaults.php/config.php (do not execute)
	$files = [__DIR__.'/defaults.php', __DIR__.'/config.php'];
	foreach ($files as $f) {
		if (@is_readable($f)) {
			$src = @file_get_contents($f);
			if ($src !== false && preg_match("/define\\s*\\(\\s*['\\\"]BLOG_BASEURL['\\\"]\\s*,\\s*['\\\"]([^'\\\"]+)['\\\"]\\s*\\)\\s*;/i", $src, $m)) {
				return rtrim((string)$m [1], '/');
			}
			// Dynamic HTTP_HOST variant detected? -> heuristic formation
			if (preg_match("/BLOG_BASEURL.*HTTP_HOST/i", $src)) {
				$host = getenv('FP_HOST') ?: gethostname() ?: 'localhost';
				$path = '/'.trim(basename(__DIR__), '/');
				if ($path === '/.' || $path === '/') $path = '/';
				return 'http://' . $host . rtrim($path, '/');
			}
		}
	}

	// CLI: 3) Heuristics as a last resort
	$host = getenv('FP_HOST') ?: gethostname() ?: 'localhost';
	$path = '/'.trim(basename(__DIR__), '/');
	if ($path === '/.' || $path === '/') {
		$path = '/';
	}
	return 'http://' . $host . rtrim($path, '/');
}

$baseUrlArg = isset($args ["url"]) ? $args ["url"] : null;
$baseUrl = $baseUrlArg ? rtrim($baseUrlArg, "/") : fp_discover_baseurl();
if (!$baseUrl) {
	stderr("Error: Base URL not found. Either set --url or make BLOG_BASEURL available in defaults.php.");
	exit(2);
}
$requests = isset($args ["requests"]) ? max(1, (int)$args ["requests"]) : 500;
$concurrency = isset($args ["concurrency"]) ? max(1, (int)$args ["concurrency"]) : 20;
$warmup = isset($args ["warmup"]) ? max(0, (int)$args ["warmup"]) : 10;
$timeout = isset($args ["timeout"]) ? max(1, (int)$args ["timeout"]) : 30;
$cacheBust = isset($args ["cache-bust"]) ? (int)$args ["cache-bust"] === 1 : false;

$paths = array();
if (isset($args ["paths"]) && trim((string)$args ["paths"]) !== "") {
	$pathsArg = $args ["paths"];
	foreach (explode(",", $pathsArg) as $p) {
		$p = trim($p);
		if ($p === "") {
			continue;
		}
		// Allow absolute and query-only. Normalize.
		if ($p [0] !== "/") {
			$p = "/" . $p;
		}
		$paths [] = $p;
	}
} else {
	// Specification: rotating standard paths
	$paths = array(
		"/index.php",
		"/admin.php",
		"/blog.php",
		"/comments.php",
		"/contact.php",
		"/login.php",
		"/search.php",
		"/static.php"
	);
}$headers = array();
if (isset($args ["header"])) {
	if (is_array($args ["header"])) {
		$headers = array_values(array_map('strval', $args ["header"]));
	} else {
		$headers [] = (string)$args ["header"];
	}
}

// --- Target CSV path ---
$csvDir = __DIR__ . "/fp-content/cache/benchmarks";
if (!is_dir($csvDir)) {
	@mkdir($csvDir, 0775, true);
}
if (!is_dir($csvDir) || !is_writable($csvDir)) {
	$csvDir = sys_get_temp_dir();
}
$csvFile = $csvDir . "/bench-".date("Ymd-His").".csv";

// --- Support functions ---
function build_url($baseUrl, $path, $cacheBust) {
	$url = $baseUrl . $path;
	if ($cacheBust) {
		$sep = (strpos($url, '?') === false) ? '?' : '&';
		$url .= $sep . "_bench=" . microtime(true) . mt_rand(1000,9999);
	}
	return $url;
}

function percentiles(array $xs, array $ps) {
	sort($xs);
	$n = count($xs);
	$r = array();
	if ($n === 0) {
		foreach ($ps as $p) { $r [$p] = NAN; }
		return $r;
	}
	foreach ($ps as $p) {
		$rank = ($p/100.0) * ($n - 1);
		$lo = (int)floor($rank);
		$hi = (int)ceil($rank);
		if ($lo === $hi) {
			$r [$p] = $xs [$lo];
		} else {
			$w = $rank - $lo;
			$r [$p] = $xs [$lo]*(1-$w) + $xs [$hi]*$w;
		}
	}
	return $r;
}

// --- Warmup ---
function do_sequential($urls, $timeout, $headers){
	$out = array();
	foreach ($urls as $i => $u) {
		$t0 = microtime(true);
		$contextOpts = array(
			"http" => array(
				"method" => "GET",
				"timeout" => $timeout,
				"ignore_errors" => true,
				"header" => ""
			)
		);
		if (!empty($headers)) {
			$contextOpts ["http"] ["header"] = implode("\r\n", $headers);
		}
		$ctx = stream_context_create($contextOpts);
		$data = @file_get_contents($u, false, $ctx);
		$t1 = microtime(true);
		$code = 0;
		if (isset($http_response_header) && is_array($http_response_header)) {
			foreach ($http_response_header as $h) {
				if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m [1]; break; }
			}
		}
		$out [] = array(
			"url" => $u,
			"http_code" => $code,
			"total_time" => ($t1 - $t0),
			"size_download" => ($data !== false ? strlen($data) : 0),
			"namelookup_time" => NAN,
			"connect_time" => NAN,
			"appconnect_time" => NAN,
			"starttransfer_time" => NAN,
		);
	}
	return $out;
}

function do_multi($urls, $concurrency, $timeout, $headers){
	$mh = curl_multi_init();
	$handles = array();
	$results = array();
	$i = 0;
	$n = count($urls);

	$setHeaders = array();
	foreach ($headers as $h) { $setHeaders [] = $h; }

	// seed initial
	$seed = min($concurrency, $n);
	for ($k=0; $k<$seed; $k++) {
		$ch = curl_init();
		$u = $urls [$i++];
		curl_setopt_array($ch, array(
			CURLOPT_URL => $u,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_HEADER => false,
			CURLOPT_NOBODY => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		));
		if (!empty($setHeaders)) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $setHeaders);
		}
		curl_multi_add_handle($mh, $ch);
		$handles [(int)$ch] = $ch;
	}

	do {
		$status = curl_multi_exec($mh, $active);
		if ($status > CURLM_OK) {
			break;
		}
		// wait for activity
		curl_multi_select($mh, 1.0);

		// harvest completed
		while ($info = curl_multi_info_read($mh)) {
			/** @var resource $ch */
			$ch = $info ['handle'];
			$content = curl_multi_getcontent($ch); // discard
			$ci = curl_getinfo($ch);
			$results [] = array(
				"url" => $ci ['url'],
				"http_code" => (int)$ci ['http_code'],
				"total_time" => (float)$ci ['total_time'],
				"size_download" => (int)$ci ['size_download'],
				"namelookup_time" => (float)$ci ['namelookup_time'],
				"connect_time" => (float)$ci ['connect_time'],
				"appconnect_time" => isset($ci ['appconnect_time']) ? (float)$ci ['appconnect_time'] : NAN,
				"starttransfer_time" => (float)$ci ['starttransfer_time'],
			);
			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
			unset($handles [(int)$ch]);

			// add next if any
			if ($i < $n) {
				$ch2 = curl_init();
				$u2 = $urls [$i++];
				curl_setopt_array($ch2, array(
					CURLOPT_URL => $u2,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 5,
					CURLOPT_CONNECTTIMEOUT => $timeout,
					CURLOPT_TIMEOUT => $timeout,
					CURLOPT_HEADER => false,
					CURLOPT_NOBODY => false,
					CURLOPT_SSL_VERIFYPEER => true,
					CURLOPT_SSL_VERIFYHOST => 2,
				));
				if (!empty($setHeaders)) {
					curl_setopt($ch2, CURLOPT_HTTPHEADER, $setHeaders);
				}
				curl_multi_add_handle($mh, $ch2);
				$handles [(int)$ch2] = $ch2;
			}
		}
	} while ($active);

	curl_multi_close($mh);
	return $results;
}

// --- Prepare warmup URLs ---
$warm = array();
for ($i=0; $i<$warmup; $i++) {
	$p = $paths [$i % count($paths)];
	$warm [] = build_url($baseUrl, $p, $cacheBust);
}
if ($warmup > 0) {
	if (function_exists('curl_multi_init')) {
		//stderr("Warmup: ".$warmup);
		do_multi($warm, min($concurrency, max(1,$warmup)), $timeout, $headers);
	} else {
		stderr("Warmup (sequenziell): " . $warmup);
		do_sequential($warm, $timeout, $headers);
	}
}

// --- Main run ---
$urls = array();
for ($i = 0; $i < $requests; $i++) {
	$p = $paths [$i % count($paths)];
	$urls [] = build_url($baseUrl, $p, $cacheBust);
}

$start = microtime(true);
if (function_exists('curl_multi_init')) {
	$rows = do_multi($urls, $concurrency, $timeout, $headers);
} else {
	stderr("Note: cURL is missing. Fallback to sequential file_get_contents().");
	$rows = do_sequential($urls, $timeout, $headers);
}
$end = microtime(true);
$wall = $end - $start;

// --- Write CSV ---
$fp = @fopen($csvFile, "wb");
if ($fp) {
	fputcsv($fp, array("idx","url","http_code","t_total_ms","t_dns_ms","t_connect_ms","t_tls_ms","t_ttfb_ms","bytes"), ",", "\"", "\\");
	foreach ($rows as $i => $r) {
		fputcsv($fp, array(
			$i + 1,
			$r ["url"],
			$r ["http_code"],
			(int)round($r ["total_time"] * 1000),
			is_finite($r ["namelookup_time"]) ? (int)round($r ["namelookup_time"] * 1000) : "",
			is_finite($r ["connect_time"]) ? (int)round($r ["connect_time"] * 1000) : "",
			is_finite($r ["appconnect_time"]) ? (int)round($r ["appconnect_time"] * 1000) : "",
			is_finite($r ["starttransfer_time"]) ? (int)round($r ["starttransfer_time"] * 1000) : "",
			$r ["size_download"]
		), ",", "\"", "\\");
	}
	fclose($fp);
}

// --- Evaluation ---
$dur = array();
$ok = 0;
$err = 0;
$bytes = 0;
foreach ($rows as $r) {
	$dur [] = $r ["total_time"];
	$bytes += (int)$r ["size_download"];
	if ($r ["http_code"] >= 200 && $r ["http_code"] < 400) {
		$ok++;
	} else {
		$err++;
	}
}

$req_s = ($wall > 0) ? ($requests / $wall) : NAN;
$mean = (count($dur) > 0) ? array_sum($dur)/count($dur) : NAN;
$perc = percentiles($dur, array(50,90,95,99));

$fmt_ms = function($s) {
	return is_nan($s) ? "NaN" : number_format($s*1000, 1, ',', '.');
};
$fmt_rps = function($x){
	return is_nan($x) ? "NaN" : number_format($x, 1, ',', '.');
};

echo "Destination: {$baseUrl}\n";
echo "Requests:    {$requests}\n";
echo "Concurrency: {$concurrency}\n";
echo "Warmup:      {$warmup}\n";
echo "Cache-Bust:  " . ($cacheBust? "1":"0") . "\n";
echo "Timeout:     {$timeout}s\n";
echo "Path(s):     ".implode(", ", $paths) . "\n";
echo "----\n";
echo "OK/Error:    {$ok}/{$err}\n";
echo "Total time:  " . number_format($wall, 3, ',', '.') . " s\n";
echo "Req/s:       " . $fmt_rps($req_s) . "\n";
echo "Average:     " . $fmt_ms($mean) . " ms\n";
echo "p50 | p90 | p95 | p99: " . $fmt_ms($perc [50]) . " | " . $fmt_ms($perc [90]) . " | " . $fmt_ms($perc [95]) . " | " . $fmt_ms($perc [99]) . " ms\n";
echo "Transfer:    " . number_format($bytes/1024/1024, 2, ',', '.') . " MiB\n";
echo "CSV:         {$csvFile}\n";

exit(0);
?>
