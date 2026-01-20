# iCalFeed (FlatPress plugin)

Author: Frank Hochmuth @Fraenkiman

Display **upcoming appointments** or **busy-times (availability)** from one or more **iCalendar/ICS feeds**.

Typical use cases:
- show your next meetings on a static page
- show "busy" slots (without revealing details)
- combine multiple calendars (work/personal) via multiple feed URLs

## Requirements

- FlatPress 1.5+ (tested on FlatPress 1.5 RC2)
- PHP 7.2 to 8.5
- Smarty 5.x
- For the `[icalfeed]` tag: the **BBCode** plugin must be enabled

## Install

1. Copy the folder `fp-plugins/icalfeed/` to your FlatPress installation.
2. Enable **iCalFeed** in the plugin manager.
3. Configure feed URL(s) under **Admin → Plugin → Calendar feed**.

## Privacy and security notes

- Google Calendar: you usually need a **public** ICS link (or a private-but-unguessable secret ICS link). Do **not** publish sensitive calendars without using privacy mode.
- Use **Privacy = Busy only** to avoid showing titles/locations.
- Keep **TLS/SSL verification enabled**. Disable it only if your host is missing CA certificates or you use a self-signed feed.

## Usage

### Widget

Add the widget **iCalFeed** to a sidebar via the widget manager.

### BBCode tag

Basic:

```
[icalfeed]
```

Override settings per page:

```
[icalfeed url="https://example.com/calendar.ics" days="14" limit="10" mode="list" privacy="busy" tz="Europe/Berlin"]
```

Options:

- `url` – single feed URL
- `urls` – multiple URLs separated by `|`, comma, or newlines
- `days` – days ahead (integer)
- `limit` – max items (integer)
- `mode` – `list` (appointments) or `busy` (availability list)
- `privacy` – `busy` (hide titles/locations) or `details`
- `location` – `1`/`true` to show location (only if `privacy=details`)
- `tz` / `timezone` – force display timezone (IANA name like `Europe/Berlin`)

## Caching

The plugin caches:
- the fetched ICS content (file cache + APCu if available)
- the parsed/expanded event list

Adjust cache TTL in the admin panel.

## Limitations

- This is a **read-only** display (no write-back to Google).
- Complex RRULEs are supported best for DAILY/WEEKLY/MONTHLY with a TZID. Some exotic rules are not expanded.
- For private feeds requiring OAuth, you need an intermediate service that exposes an ICS URL.

## License
- The iCalFeed-Plugin code follows the FlatPress project license.