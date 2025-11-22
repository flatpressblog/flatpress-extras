# FlatPress Burnout Benchmark

Self contained CLI benchmark to stress a FlatPress site with many requests and measure timings.

## Requirements
- cURL extension recommended. If missing, falls back to sequential `file_get_contents`.

## Install
Place `burnout.php` anywhere. For FlatPress, you can drop it into your FlatPress root or e.g. `fp-admin/tools/` (CLI only).

## Usage
```bash
php burnout.php --url="https://example.com/flatpress"   --requests=200 --concurrency=8   --paths="/,/index.php,/?x=cat:general,/?x=tag:news,/?paged=1"   --warmup=10 --cache-bust=0 --timeout=240
```

### Parameters
- `--url` (required): Base URL of your FlatPress site, without trailing slash.
- `--requests` default `200`: Total number of requests.
- `--concurrency` default `8`: Parallel connections when cURL is available.
- `--paths` default `"/"`: Comma separated list of paths to rotate over. Each must start with `/`.
- `--warmup` default `10`: Warmup requests before measuring.
- `--cache-bust` `0|1`: Append a changing `?_bench=...` query to bypass caches.
- `--header` repeatable: Extra HTTP headers (cURL only), e.g. `--header="Cookie: PHPSESSID=..."`.
- `--timeout` default `240`: Per request timeout in seconds.

### Output
- Summary to STDOUT: OK/error counts, wall time, requests/sec, mean, p50/p90/p95/p99 latencies, transfer volume.
- CSV under `fp-content/cache/benchmarks/bench-YYYYmmdd-HHMMSS.csv` or `/tmp` when not writable.
  Columns: index, url, http_code, total_ms, dns_ms, connect_ms, tls_ms, ttfb_ms, bytes.

### Examples
Minimal:
```bash
php burnout.php --url="https://blog.example.org"
```

Mixed endpoints with cache busting:
```bash
php burnout.php --url="https://blog.example.org"   --paths="/,/?x=entry:welcome,/?x=cat:general,/static/about"   --requests=800 --concurrency=32 --cache-bust=1
```

Admin (if you want to include authenticated hits using an existing cookie):
```bash
php burnout.php --url="https://blog.example.org"   --paths="/admin.php?p=entry,/admin.php?p=widgets"   --header="Cookie: PHPSESSID=your_session_id"   --requests=100 --concurrency=10 --cache-bust=0
```

### Notes
- This measures full HTTP responses. It does **not** run FlatPress in process, so it includes web server and TLS overhead which is realistic.
- On shared hosting without cURL, the fallback is sequential and slower by design.
- To simulate anonymous cache behavior, leave `--cache-bust=0`. To stress dynamic paths, set `--cache-bust=1`.
- CSV can be opened in a spreadsheet to plot latency distributions.
