<?php

// Frontend strings
$lang ['plugin'] ['icalfeed'] = array(
	'subject' => 'iCalFeed',
	'widget_title' => 'iCalFeed',
	'tag_title_default' => 'iCalFeed',

	'labels' => array(
		'no_events' => 'No upcoming appointments.',
		'free' => 'Free',
		'busy' => 'Busy',
		'all_day' => 'All day'
	),

	'errors' => array(
		'no_urls' => 'No calendar feed URL configured.',
		'fetch_failed' => 'Could not fetch calendar feed.',
		'parse_failed' => 'Could not parse calendar feed.',
		'partial' => 'Some feeds could not be loaded; showing partial results.',
		'generic' => 'Calendar feed error.'
	)
);

// Admin strings
$lang ['admin'] ['plugin'] ['submenu'] ['icalfeed'] = 'iCalFeed';

$lang ['admin'] ['plugin'] ['icalfeed'] = array(
	'head' => 'iCal/ICS calendar feed',
	'desc1' => 'Displays upcoming appointments or busy-times from one or more iCalendar (ICS) feeds (e.g. a public Google Calendar ICS URL).',
	'desc2' => 'Privacy tip: set <strong>Privacy</strong> to <em>Busy only</em> to avoid exposing titles.',

	'feed_urls_label' => 'Feed URL(s)',
	'feed_urls_help' => 'One URL per line. Supported: http(s) and webcal:// (converted to https).',

	'ssl_verify_label' => 'TLS/SSL verification',
	'ssl_verify_checkbox' => 'Verify server certificates (recommended)',
	'ssl_verify_help' => 'Disable only if your host lacks a CA bundle or the feed uses a self-signed certificate. Disabling reduces security.',

	'cache_ttl_label' => 'Cache TTL (seconds)',
	'days_ahead_label' => 'Days ahead',

	'display_timezone_label' => 'Display timezone (optional)',
	'display_timezone_help' => 'IANA timezone name, e.g. Europe/Berlin. If empty, uses the event TZID when available; otherwise the FlatPress time offset is used.',
	'limit_label' => 'Max items',

	'mode_label' => 'Display mode',
	'mode_list' => 'Upcoming appointments (list)',
	'mode_busy' => 'Busy times (availability)',

	'privacy_label' => 'Privacy',
	'privacy_details' => 'Show details (title/location)',
	'privacy_busy' => 'Busy only',

	'show_location_label' => 'Show location (only if details are enabled)',

	'save' => 'Save settings',
	'clear_cache' => 'Clear cache now',

	'tag_usage_head' => 'Usage',
	'tag_usage' => '[icalfeed] or [icalfeed url="https://.../basic.ics" days="14" limit="10" privacy="busy" mode="busy" tz="Europe/Berlin"]',

	'msgs' => array(
		1 => 'Settings saved successfully.',
		2 => 'Cache cleared.',
		-1 => 'No valid feed URL configured.'
	)
);

?>
