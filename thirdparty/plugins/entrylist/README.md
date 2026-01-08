# Entrylist

Author: [Pier Angelo Vendrame @Piero VDFN](https://www.pierov.org/)

## Description

This plugin adds the BBCode tag `entrylist`. It requires the **BBCode** plugin and allows you to list blog entries grouped by their **entry date** (year → month → day → entries).

> Tip: The plugin does not include a widget. However, you can create a static page and then use the BlockParser plugin to place it as a widget in the menu column.

## Requirements

- FlatPress CMS
- BBCode plugin enabled
- PHP 7.2–8.5

## Installation

1. Copy the plugin folder to:
   - `fp-plugins/entrylist/`
2. Enable the plugin in the FlatPress admin panel.
3. Ensure the **BBCode** plugin is enabled (the tag is registered via BBCode).

## Usage

Basic syntax:

```text
[entrylist <options>]
```

You can filter by year/month/day:

- `y` filters by year
- `m` filters by month (**requires `y`**)
- `d` filters by day (**requires `y` and `m`**)

If no filter is given, the plugin outputs a hierarchical archive list (years → months → days → entries).

## Parameters

| Parameter   | Values                  | Description                                                                                                                                |
|-------------|-------------------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `y`         | `YY` or `YYYY`          | List entries for a year. `YY` means 2000–2099 (e.g. `23` = 2023). `YYYY` is accepted for convenience in the range 2001–2099 (e.g. `2025`). |
| `m`         | `MM` or `M`             | List entries for a month. Requires `y`. Values 1–12 (`1` will be treated as `01`).                                                         |
| `d`         | `DD` or `D`             | List entries for a day. Requires `y` and `m`. Values 1–31 (`2` will be treated as `02`). Invalid dates (e.g. 31/02) return “no entries”.   |
| `yformat`   | date format             | Output format for year headings (strftime-style tokens). Overrides default.                                                                |
| `mformat`   | date format             | Output format for month headings (strftime-style tokens). Overrides default.                                                               |
| `dformat`   | date format             | Output format for day headings (strftime-style tokens). Overrides default.                                                                 |
| `link`      | `on/off` (also `false`) | Whether entry titles should link to the entry. Default: `on`. Use `off` or `false` to disable.                                             |
| `sort`      | `asc/desc`              | Sorting order. Default: `asc`. Applies to years/months/days and also to entries within a day (by entry id / time).                         |
| `noentries` | text                    | If the resulting list is empty, output this text instead.                                                                                  |
| `+FORMAT`   | time format             | Output format for time (strftime-style tokens). Override the entry time (e.g. `[entrylist=+%H:%M y=23]`).                                  |

## Date & time formats (important)

FlatPress allows admins to configure the site short date format under:

**Administration → Configuration → International settings**

### Default behaviour (1.0.4)

- Day headings use the configured admin short date format (`locale.dateformatshort`) **as-is**.
- Month and year headings are automatically derived from that admin date format.
- You can override these defaults per tag with `yformat/mformat/dformat`.
- The default time format comes from the admin setting `locale.timeformat`.
- You can override the entry time format for a single `[entrylist]` tag.

This means the Entrylist headings follow the admin-configured formats for many locales (e.g. German, English, Spanish, French, Italian, Greek, Dutch, Danish, Czech, Japanese, Portuguese/Brazil, Russian, Turkish, …), including formats with literal characters like `年/月/日`.

## Examples

Entrylist for the whole year 2023:

```text
[entrylist y=23]
```

Same year, using a 4-digit year:

```text
[entrylist y=2023]
```

If there is no entry in the whole year 2024, output “none at home”:

```text
[entrylist y=24 noentries="none at home"]
```

Entrylist only for January 2023:

```text
[entrylist m=01 y=23]
```

Entrylist only for day 12 of January 2023:

```text
[entrylist d=12 m=01 y=23]
```

Same day, but without links to the posts:

```text
[entrylist d=12 m=01 y=23 link=off]
```

Descending order:

```text
[entrylist y=23 sort=desc]
```

Override heading formats:

```text
[entrylist y=23 yformat="%Y" mformat="%B %Y" dformat="%A, %e. %B %Y"]
```

Override the entry time format (per tag):

```text
[entrylist=+%H:%M y=23]
```

Overwrite heading and entry time (1.0.3 like):
```text
[entrylist=+%H:%M y=23 yformat="%Y" mformat="%B" dformat="%d."]
```

## Notes

- The list is built from FlatPress entries and grouped by their entry date (the date stored in the entry id).
- The plugin uses a cache file (`plugin_entrylist_tag.txt` in FlatPress' cache directory) and automatically invalidates it when entries are published or deleted.
- Multiple `[entrylist]` tags on one page are supported (state is reset per tag).
- The entry time is wrapped in a span you can style via CSS:

  ```html
  <span class="entrylist-time">14:30</span>
  ```

## License

GNU GPLv2

## Download

The [FlatPress wiki](https://wiki.flatpress.org/res:plugins:entrylist) historically listed these downloads:

- `entrylist1_0_2.zip`

[Pieros Blog](https://www.pierov.org/2012/10/17/plugin-entrylist-v101-flatpress/) has listed the following downloads in the past:

- `entrylist_v1.0.1.tar.gz`

## Changelog

- **2026-01-05: Version 1.0.4**
  - Respects the admin-configured short date and time format
  - Accepts 4-digit years (2001–2099) in addition to YY
  - Per-tag state reset: multiple `[entrylist]` tags on one page no longer affect each other

- **2025-02-26: Version 1.0.2**
  - Compatibility with PHP 8.0+ established

## Support

Please ask for help on the [FlatPress forum](https://forum.flatpress.org/viewtopic.php?t=848).
