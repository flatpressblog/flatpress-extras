<?php
/**
 * FlatPress Burnout Benchmark
 *
 * CLI tool. Generates many HTTP requests against a FlatPress instance and measures times.
 * Uses curl_multi for parallelism; falls back to sequential if cURL is missing.
 *
 * Usage CLI:
 *   php burnout.php --url="https://example.com/" --requests=500 --concurrency=8 --paths="/,/index.php,/?x=cat:general" --warmup=40 --cache-bust=0
 * Usage Web:
 *   https://blog.example.com/burnout.php
 *
 * Output:
 *   - Summary on STDOUT
 *   - CSV under fp-content/cache/bench-YYYYmmdd-HHMMSS.csv (or /tmp fallback)
 */
@ignore_user_abort(true); // continue if client disconnects
if (function_exists('set_time_limit')) {
	@set_time_limit(0);
}
@ini_set('max_execution_time', '0');

// --- Arguments ---
$longopts  = array(
	"url:",           // Base URL, e.g., https://example.com/
	"requests::",     // Number (default 500)
	"concurrency::",  // Concurrency (default 8)
	"paths::",        // Comma-separated paths, used in rotation. Default: "/"
	"warmup::",       // Total warmup requests (default 40)
	"header::",       // Optional header, can be used multiple times: --header="Cookie: a=b" (cURL only)
	"cache-bust::",   // 1 = Add query string, default 0
	"timeout::"       // Seconds per request, default 240
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
		echo $s . PHP_EOL;
	}
}

function fp_discover_baseurl() {
	// Browser: derive from request
	if (PHP_SAPI !== 'cli') {
		if (!empty($_GET ['url'])) {
			return rtrim((string)$_GET ['url'], '/');
		}
		$host = $_SERVER ['HTTP_X_FORWARDED_HOST'] ?? $_SERVER ['HTTP_HOST'] ?? $_SERVER ['SERVER_NAME'] ?? null;
		$proto = $_SERVER ['HTTP_X_FORWARDED_PROTO'] ?? ((!empty($_SERVER ['HTTPS']) && $_SERVER ['HTTPS'] !== 'off') ? 'https' : 'http');
		if ($host) {
			$basePath = rtrim(str_replace('\\','/', dirname($_SERVER ['SCRIPT_NAME'] ?? '/')), '/');
			return rtrim($proto . '://' . $host . $basePath, '/');
		}
		return null;
	}

	// CLI: 1) explizit
	$u = getenv('FP_BASEURL');
	if ($u) return rtrim((string)$u, '/');

	// CLI: 2) PARSE literal from defaults.php (do not execute)
	$files = [__DIR__ . '/defaults.php'];
	foreach ($files as $f) {
		if (@is_readable($f)) {
			$src = @file_get_contents($f);
			if ($src !== false && preg_match("/define\\s*\\(\\s*['\\\"]BLOG_BASEURL['\\\"]\\s*,\\s*['\\\"]([^'\\\"]+)['\\\"]\\s*\\)\\s*;/i", $src, $m)) {
				return rtrim((string)$m [1], '/');
			}
			// Dynamic HTTP_HOST variant detected? -> heuristic formation
			if (preg_match("/BLOG_BASEURL.*HTTP_HOST/i", $src)) {
				$host = getenv('FP_HOST') ?: gethostname() ?: 'localhost';
				$path = '/' . trim(basename(__DIR__), '/');
				if ($path === '/.' || $path === '/') {
					$path = '/';
				}
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
$requests = isset($args ["requests"]) ? max(1, (int)$args ["requests"]) : 200; // default 500
$concurrency = isset($args ["concurrency"]) ? max(1, (int)$args ["concurrency"]) : 8; // defaut 8
$warmup = isset($args ["warmup"]) ? max(0, (int)$args ["warmup"]) : 40; // default 40
$timeout = isset($args ["timeout"]) ? max(1, (int)$args ["timeout"]) : 240; // default 240
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
		"/index.php/?x=feed:rss2",
		"/index.php/?x=feed:atom",
		"/admin.php",
		"/blog.php",
		"/comments.php",
		"/contact.php",
		"/login.php",
		"/search.php?q=flatpress&stype=full",
		//"/search.php?q=caching&stype=full",
		//"/search.php?q=performance&stype=full",
		//"/search.php?q=category&stype=full",
		//"/search.php?q=comment&stype=full",
		//"/search.php?q=entry&stype=full",
		//"/search.php?q=smarty",
		"/static.php?page=about",
		//"/static.php?page=check-your-email",
		//"/static.php?page=invalid-email",
		//"/static.php?page=invalid-token",
		//"/static.php?page=legal-notice",
		//"/static.php?page=privacy-policy",
		//"/static.php?page=subscription-confirmed",
		//"/static.php?page=throttle-limit",
		//"/static.php?page=unsubscribe-success",
		//"/static.php?page=check-your-email",
		"/?random"
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
$csvDir = __DIR__ . "/fp-content/cache";
if (!is_dir($csvDir)) {
	@mkdir($csvDir, DIR_PERMISSIONS, true);
}
if (!is_dir($csvDir) || !is_writable($csvDir)) {
	$csvDir = sys_get_temp_dir();
}
$csvFile = $csvDir . "/bench-" . date("Ymd-His") . ".csv";

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
		foreach ($ps as $p) {
			$r [$p] = NAN;
		}
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
			$r [$p] = $xs [$lo] * (1-$w) + $xs [$hi] * $w;
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
				if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
					$code = (int)$m [1];
					break;
				}
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
	foreach ($headers as $h) {
		$setHeaders [] = $h;
	}

	// seed initial
	$seed = min($concurrency, $n);
	for ($k = 0; $k < $seed; $k++) {
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
		// Wait for activity
		curl_multi_select($mh, 0.1);

		// Harvest completed
		while ($info = curl_multi_info_read($mh)) {
			/** @var resource $ch */
			$ch = $info ['handle'];
			$content = curl_multi_getcontent($ch); // discard
			$ci = curl_getinfo($ch);
			// Prefer microsecond-resolution if available
			$tt = isset($ci ['total_time']) ? (float)$ci ['total_time'] : NAN;
			if (defined('CURLINFO_TOTAL_TIME_T')) {
				$tt_us = curl_getinfo($ch, CURLINFO_TOTAL_TIME_T);
				if (is_int($tt_us) && $tt_us > 0) {
					$tt = $tt_us / 1000000.0;
				}
			}
			$results [] = array(
				"url" => $ci ['url'],
				"http_code" => (int)$ci ['http_code'],
				"total_time" => $tt,
				"size_download" => (int)$ci ['size_download'],
				"namelookup_time" => (float)$ci ['namelookup_time'],
				"connect_time" => (float)$ci ['connect_time'],
				"appconnect_time" => isset($ci ['appconnect_time']) ? (float)$ci ['appconnect_time'] : NAN,
				"starttransfer_time" => (float)$ci ['starttransfer_time'],
			);
			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
			unset($handles [(int)$ch]);

			// Add next if any
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
for ($i=0; $i < $warmup; $i++) {
	$p = $paths [$i % count($paths)];
	$warm [] = build_url($baseUrl, $p, $cacheBust);
}
if ($warmup > 0) {
	if (function_exists('curl_multi_init')) {
		//stderr("Warmup: " . $warmup);
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
	fputcsv($fp, array("idx", "url", "http_code", "t_total_ms", "t_dns_ms", "t_connect_ms", "t_tls_ms", "t_ttfb_ms", "bytes"), ",", "\"", "\\");
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
$perc = percentiles($dur, array(50, 90, 95, 99));
$min_v = (count($dur) > 0) ? min($dur) : NAN;
$max_v = (count($dur) > 0) ? max($dur) : NAN;

$fmt_ms = function($s) {
	return is_nan($s) ? "NaN" : number_format($s * 1000, 1, ',', '.');
};
$fmt_rps = function($rps){
	return is_nan($rps) ? "NaN" : number_format($rps, 2, ',', '.');
};

echo "Destination:             {$baseUrl}\n";
echo "Requests:                {$requests}\n";
echo "Concurrency:             {$concurrency}\n";
echo "Warmup:                  {$warmup}\n";
echo "Cache-Bust:              " . ($cacheBust? "1" : "0") . "\n";
echo "Timeout:                 {$timeout}s\n";
// SAPI/Limit Diagnose
$sapi = PHP_SAPI;
$mx   = ini_get('max_execution_time');
$fcgi = getenv('FCGI_ROLE') ?: '';
echo "SAPI:                    {$sapi}" . ($fcgi ? " ({$fcgi})" : "") . "\n";
echo "PHP max_execution_time:  " . ($mx === false ? "unknown" : $mx) . "\n";
if ($mx !== false && $mx !== '0') {
	echo "Hinweis: PHP-Limit nicht '0'. Server-/SAPI setzt evtl. Grenzen.\n";
}
echo "Path(s):                 ".implode(", ", $paths) . "\n";
echo "----\n";
echo "OK/Error:                {$ok}/{$err}\n";
echo "Total time:              " . number_format($wall, 3, ',', '.') . " s\n";
echo "Req/s:                   " . $fmt_rps($req_s) . "\n";
echo "Average:                 " . $fmt_ms($mean) . " ms\n";
echo "p50 | p90 | p95 | p99:   " . $fmt_ms($perc [50]) . " | " . $fmt_ms($perc [90]) . " | " . $fmt_ms($perc [95]) . " | " . $fmt_ms($perc [99]) . " ms\n";
echo "min | max:               " . $fmt_ms($min_v) . " | " . $fmt_ms($max_v) . " ms\n";
echo "Transfer:                " . number_format($bytes/1024/1024, 2, ',', '.') . " MiB\n";
echo "----\n";
echo "CSV:                     {$csvFile}\n";

// --- HTML-Report ---
// Daten für JS
$dur_ms = array();
foreach ($dur as $_s) { $dur_ms[] = round($_s * 1000.0, 1); }
$summary = array(
	'destination'   => (string)$baseUrl,
	'requests'      => (int)$requests,
	'concurrency'   => (int)$concurrency,
	'warmup'        => (int)$warmup,
	'cache_bust'    => $cacheBust ? 1 : 0,
	'timeout_s'     => (int)$timeout,
	'paths'         => array_values($paths),
	'ok'            => (int)$ok,
	'err'           => (int)$err,
	'total_time_s'  => (float)$wall,
	'req_per_s'     => (float)$req_s,
	'mean_ms'       => is_nan($mean) ? null : $mean * 1000,
	'p50_ms'        => isset($perc [50]) ? $perc [50] * 1000 : null,
	'p90_ms'        => isset($perc [90]) ? $perc [90] * 1000 : null,
	'p95_ms'        => isset($perc [95]) ? $perc [95] * 1000 : null,
	'p99_ms'        => isset($perc [99]) ? $perc [99] * 1000 : null,
	'min_ms'        => is_nan($min_v) ? null : $min_v * 1000,
	'max_ms'        => is_nan($max_v) ? null : $max_v * 1000,
	'transfer_mib'  => (float)($bytes / 1024 / 1024)
);
$js = json_encode(array('summary'=>$summary,'latencies_ms'=>$dur_ms), JSON_UNESCAPED_SLASHES);
$htmlFile = preg_replace('/\\.csv$/i', '-report.html', $csvFile);
$doc = <<<HTML
<!doctype html>
<html lang="en"><head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>FlatPress Burnout Report</title>
	<style>
		*{box-sizing:border-box} body{
			font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial, sans-serif; margin: 0; padding: 24px; background: #f6f7f9; color: #111
		}
		.wrap{max-width:1100px;margin:0 auto}
		.card{
			background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.06); padding: 16px; margin-bottom: 16px
		}
		h1{font-size: 20px; margin:0 0 8px 0}
		h2{font-size: 16px; margin:0 0 8px 0}
		table{width: 100%; border-collapse: collapse; font-size: 14px}
		th,td{border-top: 1px solid #e5e7eb; padding:8px 6px; text-align: left; vertical-align: top}
		.grid{display: grid; grid-template-columns: 1fr; gap: 16px}
		@media (min-width:900px){
			grid{grid-template-columns: 1fr 1fr}
		}
		canvas{
			width: 100%; height: 360px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; display: block
		}
		.mono{font-family: ui-monospace, Menlo, Consolas, monospace}
		.muted{color:#6b7280}
	</style>
</head><body><div class="wrap">
	<div class="card">
		<h1>FlatPress Burnout Report</h1>
		<div class="muted">Destination: <span id="dest"></span> • generated: <span id="ts"></span></div>
	</div>
	<div class="card">
		<h2>Summary</h2>
		<table id="summary"></table>
	</div>
	<div class="grid">
		<div class="card">
			<h2>Percentiles &amp; min/max (ms)</h2>
			<div style="margin: 4px 0 8px 0; font-size: 13px">
				<label><input id="zeroBars" type="checkbox"> Zero-based</label>
			</div>
			<canvas id="cBars"></canvas>
		</div>
		<div class="card">
			<h2>Latency per request (ms)</h2>
			<div style="margin: 4px 0 8px 0; font-size: 13px">
				<label><input id="zeroLine" type="checkbox"> Zero-based</label>
			</div>
			<canvas id="cLine"></canvas>
		</div>
	</div>
	<div id="tip" style="position: fixed; pointer-events: none; opacity:.95; background: #111; color: #fff; font: 12px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; padding: 6px 8px; border-radius: 6px; display: none"></div>
	<div class="card"><div class="muted mono">Source: {$csvFile}</div></div>
</div>
<script>
const DATA = $js;
// Formatierer
const de = n => (n==null||!isFinite(n))? '–' : new Intl.NumberFormat('en-US',{maximumFractionDigits:1}).format(n);
const ts = new Date().toLocaleString('en-US');
document.getElementById('dest').textContent = DATA.summary.destination;
document.getElementById('ts').textContent = ts;

// Persistence for options
const persistGet = k => {
	try {
		return JSON.parse(localStorage.getItem(k));
	} catch(e){
		return null;
	}
};
const persistSet = (k,v) => {
	try {
		localStorage.setItem(k, JSON.stringify(v));
	} catch(e){}
};
(['zeroBars','zeroLine']).forEach(id => {
	const el = document.getElementById(id);
	const v = persistGet('burnout:'+id);
	if (typeof v === 'boolean') el.checked = v;
});

// Summary table
const S = DATA.summary, T = [
 ['Requests', S.requests], ['Concurrency', S.concurrency], ['Total time (s)', de(S.total_time_s)],
 ['Req/s', de(S.req_per_s)], ['Average (ms)', de(S.mean_ms)],
 ['p50 (ms)', de(S.p50_ms)], ['p90 (ms)', de(S.p90_ms)], ['p95 (ms)', de(S.p95_ms)], ['p99 (ms)', de(S.p99_ms)],
 ['min (ms)', de(S.min_ms)], ['max (ms)', de(S.max_ms)], ['Transfer (MiB)', de(S.transfer_mib)],
 ['OK/Error', S.ok + '/' + S.err], ['Paths', (S.paths||[]).join(', ')]
];
const tbl = document.getElementById('summary');
tbl.innerHTML = '<tr><th>Metric</th><th>Value</th></tr>' + T.map(r => '<tr><td>'+r[0]+'</td><td class="mono">'+r[1]+'</td></tr>').join('');

// ----- Canvas utilities -----
const PALETTE = ['#2563eb','#16a34a','#f59e0b','#ef4444','#64748b','#0ea5e9'];
function prepCanvas(canvas){
	const dpr = window.devicePixelRatio || 1;
	const rect = canvas.getBoundingClientRect();
	canvas.width  = Math.round(rect.width * dpr);
	canvas.height = Math.round(rect.height * dpr);
	const ctx = canvas.getContext('2d');
	ctx.setTransform(dpr,0,0,dpr,0,0);
	ctx.font = '13px system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif';
	ctx.fillStyle = '#111'; ctx.strokeStyle = '#111'; ctx.lineWidth = 1;
	return {ctx, width: rect.width, height: rect.height};
}

const map = (v,a,b,c,d)=> c + (v-a)*(d-c)/(b-a);
// Scales for any range
function niceNum(range, round){
	const exp = Math.floor(Math.log10(range));
	const frac = range / Math.pow(10, exp);
	let nice;
	if (round){
		if (frac < 1.5) nice = 1;
		else if (frac < 3) nice = 2;
		else if (frac < 7) nice = 5;
		else nice = 10;
	} else {
		if (frac <= 1) nice = 1;
		else if (frac <= 2) nice = 2;
		else if (frac <= 5) nice = 5;
		else nice = 10;
	}
	return nice * Math.pow(10, exp);
}
function niceTicksMinMax(minVal, maxVal, steps=5){
	if(!isFinite(minVal) || !isFinite(maxVal) || maxVal<=minVal){
		return {ticks:[0,1,2,3,4,5], min:0, max:5};
	}
	const range = niceNum(maxVal-minVal, false);
	const step  = niceNum(range/steps, true);
	const niceMin = Math.floor(minVal/step)*step;
	const niceMax = Math.ceil (maxVal/step)*step;
	const ticks = [];
	for(let v=niceMin; v<=niceMax+1e-9; v+=step) ticks.push(v);
	return {ticks, min:niceMin, max:niceMax};
}
// Ticks, rounded to 1·2·5·10 scheme
function niceTicks(maxVal, steps=5){
	if(!isFinite(maxVal) || maxVal<=0) {
		return Array.from({length:steps+1},(_,i)=>i);
	}
	const exp = Math.floor(Math.log10(maxVal));
	const base = Math.pow(10, exp);
	const candidates = [1,2,5,10].map(m=>m*base);
	let span = candidates[0];
	for(const c of candidates){
		if(maxVal/steps <= c) {
			span = c; break;
		}
	}
	const top = span * Math.ceil(maxVal/span);
	return Array.from({length:steps+1},(_,i)=> i*top/steps);
}
// Axes with dynamic inner edges and clamping
function axes(ctx, box, yTicks, unit){
	const {x0,y0,x1,y1} = box;
	ctx.beginPath();
	ctx.moveTo(x0,y0);
	ctx.lineTo(x0,y1);
	ctx.lineTo(x1,y1);
	ctx.stroke();
	const fmt = v => new Intl.NumberFormat('en-US',{maximumFractionDigits:1}).format(v);
	const topPad = 10, bottomPad = 10;
	ctx.textAlign='right';
	ctx.textBaseline='middle';
	const yMin = yTicks[0], yMax = yTicks[yTicks.length-1];
	for(const t of yTicks){
		let yy = map(t, yMin, yMax, y1, y0);
		yy = Math.max(y0+topPad, Math.min(y1-bottomPad, yy));
		ctx.fillText(fmt(t), x0-8, yy);
		ctx.save();
		ctx.globalAlpha=0.08;
		ctx.beginPath();
		ctx.moveTo(x0,yy);
		ctx.lineTo(x1,yy);
		ctx.stroke();
		ctx.restore();
	}
	if(unit){
		ctx.save(); ctx.translate(x0-38,(y0+y1)/2);
		ctx.rotate(-Math.PI/2);
		ctx.textAlign='center';
		ctx.textBaseline='bottom';
		ctx.fillText(unit, 0, 0);
		ctx.restore();
	}
}
function measureMaxWidth(ctx, labels){
	let m=0; for(const s of labels){
		m = Math.max(m, ctx.measureText(s).width);
	} return m;
}

// ----- Bar chart -----
function drawBars(canvasId, labels, values){
	const c = document.getElementById(canvasId);
	const {ctx, width:w, height:h} = prepCanvas(c);
	ctx.clearRect(0,0,w,h);
	// Determine range: tight with margin, optionally zero-based
	const vMinData = Math.min(...values.filter(Number.isFinite));
	const vMaxData = Math.max(...values.filter(Number.isFinite));
	const zero = document.getElementById('zeroBars').checked;
	const span = (vMaxData-vMinData) || Math.max(1, vMaxData*0.1);
	let minDomain = zero ? 0 : Math.max(0, vMinData - span*0.05);
	let maxDomain = vMaxData + span*0.05;
	if (maxDomain<=minDomain) {
		maxDomain = minDomain + Math.max(1, minDomain*0.1);
	}
	const steps = Math.max(3, Math.min(6, Math.round(h/90)));
	const nt = niceTicksMinMax(minDomain, maxDomain, steps);
	const ticks = nt.ticks;
	// Space on the left dynamically adjusted to tick text width
	const tickLabels = ticks.map(v => new Intl.NumberFormat('en-US',{maximumFractionDigits:1}).format(v));
	const leftPad = 16 + measureMaxWidth(ctx, tickLabels);
	// Measure legend and value label height
	const valH = (ctx.measureText('0123456789').actualBoundingBoxAscent || 12);
	const legendH = 18;
	// Select top padding so that value labels are not truncated
	const topPad = 14 + legendH + valH + 6;
	const rightPad = 12, bottomPad = 28;
	const x0 = leftPad+22, y0 = topPad, x1 = w-rightPad, y1 = h-bottomPad;
	axes(ctx, {x0,y0,x1,y1}, ticks, 'ms');
	const n = labels.length, bw = (x1-x0)/(n*1.6);
	// Beams
	const barRects = []; // für Tooltip
	for(let i=0;i<n;i++){
		const v = values[i];
		const x = x0 + (i+0.5)*(x1-x0)/n;
		const y = map(v, ticks[0], ticks[ticks.length-1], y1, y0);
		ctx.fillStyle = PALETTE[i % PALETTE.length];
		const rx = x-bw/2, ry = y, rh = Math.max(0, y1-y);
		ctx.fillRect(rx, ry, bw, rh);
		barRects.push({x:rx, y:ry, w:bw, h:rh, label:labels[i], value:v, cx:x});
		// Value label
		ctx.fillStyle = '#111';
		ctx.textAlign='center';
		ctx.textBaseline='bottom';
		const txt = new Intl.NumberFormat('en-US',{maximumFractionDigits:1}).format(v);
		let ty = y - 4;
		if (ty - valH < y0) {
			ty = y0 + 2; // falls sehr nah am Rand, Text innerhalb zeichnen
		}
		ctx.fillText(txt, x, ty);
		// X-Label
		ctx.textBaseline='top';
		ctx.fillText(labels[i], x, y1+6);
	}
	// Legend
	const Lx = x0, Ly = (y0 - legendH + 4);
	let lx = Lx;
	ctx.textAlign='left';
	ctx.textBaseline='middle';
	labels.forEach((lab,i)=>{
		ctx.fillStyle=PALETTE[i%PALETTE.length];
		ctx.fillRect(lx, Ly-5, 12, 12);
		ctx.fillStyle='#111';
		ctx.fillText(' '+lab, lx+16, Ly+1);
		lx += ctx.measureText(' '+lab).width + 36;
	});
	// Tooltip interaction
	const tip = document.getElementById('tip');
	c.onmousemove = e => {
		const r = c.getBoundingClientRect();
		const px = e.clientX - r.left; const py = e.clientY - r.top;
		const hit = barRects.find(b => px>=b.x && px<=b.x+b.w && py>=b.y && py<=b.y+b.h);
		if(hit){
			tip.style.display='block';
			tip.textContent = hit.label+': '+de(hit.value)+' ms';
			tip.style.left=(e.clientX+12)+'px';
			tip.style.top=(e.clientY+12)+'px';
		} else {
			tip.style.display='none';
		}
	};
	c.onmouseleave = ()=> tip.style.display='none';
}

// ----- Line chart with points -----
function drawLine(canvasId, ys){
	const c = document.getElementById(canvasId);
	const {ctx, width:w, height:h} = prepCanvas(c);
	ctx.clearRect(0,0,w,h);
	const n = ys.length;
	const yMinData = Math.min(...ys.filter(Number.isFinite));
	const yMaxData = Math.max(...ys.filter(Number.isFinite)) || 1;
	const zero = document.getElementById('zeroLine').checked;
	const span = (yMaxData-yMinData) || Math.max(1, yMaxData*0.1);
	let minDomain = zero ? 0 : Math.max(0, yMinData - span*0.05);
	let maxDomain = yMaxData + span*0.05;
	if (maxDomain<=minDomain) {
		maxDomain = minDomain + Math.max(1, minDomain*0.1);
	}
	const steps = Math.max(3, Math.min(6, Math.round(h/90)));
	const nt = niceTicksMinMax(minDomain, maxDomain, steps);
	const ticks = nt.ticks;
	const tickLabels = ticks.map(v => new Intl.NumberFormat('en-US',{maximumFractionDigits:1}).format(v));
	const leftPad = 16 + measureMaxWidth(ctx, tickLabels);
	const topPad = 18, rightPad = 12, bottomPad = 28;
	const x0 = leftPad+22, y0 = topPad, x1 = w-rightPad, y1 = h-bottomPad;
	axes(ctx, {x0,y0,x1,y1}, ticks, 'ms');
	ctx.beginPath();
	const pts = [];
	for(let i=0;i<n;i++){
		const x = map(i, 0, n-1, x0, x1);
		const y = map(ys[i], ticks[0], ticks[ticks.length-1], y1, y0);
		pts.push({x,y,v:ys[i], i});
		if(i===0) {
			ctx.moveTo(x,y);
		} else {
			ctx.lineTo(x,y);
		}
	}
	ctx.stroke();
	// Points
	for(const p of pts){
		ctx.beginPath();
		ctx.arc(p.x,p.y,2.5,0,Math.PI*2);
		ctx.fill();
	}
	// Tooltip
	const tip = document.getElementById('tip');
	c.onmousemove = e => {
		const r = c.getBoundingClientRect();
		const px = e.clientX - r.left;
		if(!pts.length){
			tip.style.display='none';
			return;
		}
		let best = pts[0], dmin = Math.abs(px-best.x);
		for(const p of pts){
			const d = Math.abs(px-p.x);
			if(d<dmin){
				dmin=d; best=p;
			}
		}
		tip.style.display='block';
		tip.textContent = 'Req '+(best.i+1)+': '+de(best.v)+' ms';
		tip.style.left=(e.clientX+12)+'px';
		tip.style.top=(e.clientY+12)+'px';
	};
	c.onmouseleave = ()=> tip.style.display='none';
}

// Render + Resize
function render(){
	const S = DATA.summary;
	drawBars('cBars', ['p50','p90','p95','p99','min','max'],
		[S.p50_ms,S.p90_ms,S.p95_ms,S.p99_ms,S.min_ms,S.max_ms]);
	drawLine('cLine', DATA.latencies_ms);
}
window.addEventListener('resize', render, {passive:true});
document.getElementById('zeroBars').addEventListener('change', e => {
	persistSet('burnout:zeroBars', e.target.checked); render();
});
document.getElementById('zeroLine').addEventListener('change', e => {
	persistSet('burnout:zeroLine', e.target.checked); render();
});
render();
</script>
</body></html>
HTML;
@file_put_contents($htmlFile, $doc);
echo "HTML:                    {$htmlFile}\n";

exit(0);
?>
