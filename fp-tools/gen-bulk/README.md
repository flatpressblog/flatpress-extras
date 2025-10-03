# gen-bulk.php — FlatPress Bulk Content Generator

Generate many FlatPress posts and comments for load testing, demos, or development. The script keeps PrettyURLs in sync so comment links are immediately resolvable.

---

## Requirements
- FlatPress checked out and accessible (script lives in FlatPress root beside `defaults.php`).
- PHP 7.2–8.4.
- Write permissions for `fp-content/` and `fp-content/cache/`.
- Optional: **prettyurls** plugin enabled. The script falls back to a file-based index if the plugin is not loaded.

---

## Installation
1. Copy `gen-bulk.php` to the FlatPress root directory.  
2. Commit to a dev branch only. Remove after use in production environments.

---

## Usage

### CLI
```bash
# Syntax
php gen-bulk.php <entries> <comments_per_entry> [seed] [spread_days]

# Example: 1,000 posts, 5 comments each, deterministic seed, 30 days date spread
php gen-bulk.php 1000 5 1234 30
```

### Web
```
http(s)://<host>/<path-to-flatpress>/gen-bulk.php?n=1000&k=5&seed=1234&spread=30
```

### Parameters
- `entries` / `n` — number of posts to create.  
- `comments_per_entry` / `k` — number of comments per post.  
- `seed` — RNG seed for reproducible content.  
- `spread_days` / `spread` — date spread in days backward from “now”; controls the year/month/day paths under `fp-content/content/YY/MM/`.

---

## What the script does
- Loads `defaults.php` and `settings.conf.php`, merges `$fp_config`.
- Uses only core APIs: `entry_save()` and `comment_save()`.
- Creates realistic date paths in `fp-content/content/YY/MM/...`.
- Updates the PrettyURLs index so comment links work immediately:
  - Preferred: calls `plugin_prettyurls->cache_add()` if the plugin is loaded.
  - Fallback: writes `fp-content/cache/%%prettyurls-index.tmpYYMM` (hash map of `md5(slug) → entryID`).
- Prints a **verifiable** comment URL for each created post, adapted to the PrettyURLs mode:
  - Mode 0 (rewrites): `/YYYY/MM/DD/<slug>/comments/#comment…`
  - Mode 1 (`index.php/`): `/index.php/YYYY/MM/DD/<slug>/comments/#comment…`
  - Mode 2 (`?u=`): `/?u=/YYYY/MM/DD/<slug>/comments/#comment…`
- Logs errors to `gen-bulk.log`. Exits with non‑zero code if any post creation fails.

---

## Verification
1. Open the URLs printed by the script.  
2. The post page must load and jump to the comment anchor `#comment…`.  
3. Optional: Inspect `fp-content/cache/%%prettyurls-index.tmpYYMM` and verify a day bucket (`DD`) with `md5(slug) → entry…` mappings for the affected dates.

---

## Examples

### Minimal
```bash
php gen-bulk.php 10 0
```

### With comments and date spread
```bash
php gen-bulk.php 200 3 42 14
```

### Web variant
```
http://localhost/flatpress/gen-bulk.php?n=50&k=2&seed=7&spread=5
```

---

## Troubleshooting
- **Comments not reachable**  
  - Check that the PrettyURLs plugin is enabled, or that `%%prettyurls-index.tmpYYMM` was written.  
  - Ensure write permissions on `fp-content/cache/`.  
  - Verify `BLOG_BASEURL` and PrettyURLs mode in FlatPress settings.  
  - On Windows without rewrite support, Mode 1 uses `index.php/...`. The script prints the proper format for all modes.

- **No new files**  
  - Check file permissions under `fp-content/`.  
  - Review the PHP error log and `gen-bulk.log`.

- **Slow on very large batches**  
  - Generate in smaller batches.  
  - Consider disabling APCu for CLI runs unless benchmarking.

---

## Security
`gen-bulk.php` is a developer tool. Do not leave it accessible in a public environment. Remove it or protect it via server ACLs after use.

---

## Compatibility
- PHP 7.2–8.4.  
- Smarty 5 untouched.  
- Works with or without the PrettyURLs plugin (fallback index writer included).

---

## License
The FlatPress Bulk Content Generator code follows the FlatPress project license.
