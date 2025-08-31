# FlatPress Smarty 5 Smoketest (`fp_smarty_smoketest.php`)

Concise guide for running, reading, and fixing results.

---

## Purpose

Quick integration check for FlatPress 1.5 with Smarty 5. Verifies FP–Smarty plugin registration, core Smarty features, cache/compile paths, and frequent misconfigurations.

## Requirements

- PHP 7.2–8.4  
- FlatPress ≥ 1.5  
- Smarty ≥ 5.5.1  
- Writable: `fp-content/compile`, `fp-content/cache`

## Install

Copy `fp_smarty_smoketest.php` to the FlatPress root directory.

## Run

**Browser:**  
```
http(s)://<host>/fp_smarty_smoketest.php
```

**CLI:**  
```
php fp_smarty_smoketest.php > smoketest.html
```
Output is HTML. The top shows group totals. Below are grouped tables. A “Show only failures” checkbox hides successful rows.

## What it tests

1. **Bootstrap**
   - Loads `defaults.php` and, if needed, `INCLUDES_DIR/includes.php`.
   - Calls `system_init()` when required.
   - Sets and validates `compileDir` and `cacheDir`.
2. **Plugin discovery**
   - Scans `fp-includes/fp-smartyplugins/*.php`.
   - Compares “expected” vs “loaded” via `function_exists()` / `class_exists()`.
3. **Removed language constructs**
   - `{php}`, `{include_php}`, `{insert}` must be blocked.
4. **DefaultExtension / modifiers**
   - Built‑in modifiers (e.g., `substr`) work via `Smarty\Extension\DefaultExtension`.
   - Unknown modifier fails as expected.
   - Report lists built‑in modifiers detected from `DefaultExtension`.
5. **Default plugin handler**
   - Shows status “active”/“inactive”. The informational note is shown only if `default_handler.msg === 'active'`.
6. **Cache/compile roundtrip**
   - Uses `setCaching()`, `isCached()`, `clearCache()`.
   - Confirms identical output with and without cache.
7. **Filter order**
   - Ensures `pre` → `post` → `output` sequence.
8. **`mbstring`**
   - Presence and correct UTF‑8 length behavior.
9. **By‑ref modifiers**
   - Registration by reference is rejected as required by Smarty 5.
10. **Resources**
    - `string:` and `eval:` render correctly.
11. **Template inheritance**
    - `{extends}` against `fp-interface/themes/default/maintemplate.tpl`. If missing, the test is skipped.
12. **Duplicate registration**
    - Second `registerPlugin()` call throws the expected error.
13. **Warning suppression**
    - `muteUndefinedOrNullWarnings()` exists and is effective.

## Output structure

- **Totals per group:** “Expected / Loaded / Missing”.  
- **Detail tables:** name, source, status, message.  
- **Filter:** “Show only failures” checkbox.  
- **Extra section:** list of built‑in modifiers discovered from `DefaultExtension`.

## Common findings and fixes

| Finding | Likely cause | Fix |
|---|---|---|
| Plugins “missing > 0” | `includes.php`/`system_init()` not executed or wrong scan path | Ensure `includes.php` runs and `fp_register_fp_plugins()` is active; verify `FP_SMARTYPLUGINS_DIR` and file naming (`function.*.php`, `modifier.*.php`, `prefilter.*.php`, …) |
| Cache test failed | `cacheDir`/`compileDir` not writable | Create directories and set permissions |
| “removed_language FAILED” | Old Smarty or wrong security policy | Upgrade to Smarty ≥ 5.5.1 and review security policy |
| `{extends}` skipped | `maintemplate.tpl` missing | Provide `fp-interface/themes/default/maintemplate.tpl` |
| Default handler “inactive” | No handler registered | Not a bug. Ensure all needed plugins are explicitly registered |

## Compatibility

- PHP 7.2–8.4.  
- Smarty 5.5.1 or newer.  
- FlatPress 1.5 directory layout as referenced above.

## Limitations

- No network or SMTP checks.  
- No theme‑specific coverage beyond a minimal `{extends}` smoke.  
- Report is read‑only and does not change configuration.

## Troubleshooting checklist

1. `fp-content/compile` and `fp-content/cache` exist and are writable.  
2. `includes.php` is actually loaded; `system_init()` ran.  
3. Smarty version is ≥ 5.5.1.  
4. `maintemplate.tpl` present if the `{extends}` test is needed.  
5. Verify plugin filenames and naming conventions.

## License

This document can be used in the project wiki/repo. The Smoketest code follows the FlatPress project license.
