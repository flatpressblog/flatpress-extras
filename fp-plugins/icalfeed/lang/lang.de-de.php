<?php

// Frontend strings
$lang ['plugin'] ['icalfeed'] = array(
	'subject' => 'iCalFeed',
	'widget_title' => 'iCalFeed',
	'tag_title_default' => 'iCalFeed',

	'labels' => array(
		'no_events' => 'Keine anstehenden Termine.',
		'free' => 'Frei',
		'busy' => 'Belegt',
		'all_day' => 'Ganztägig'
	),

	'errors' => array(
		'no_urls' => 'Keine Kalender-Feed-URL konfiguriert.',
		'fetch_failed' => 'Kalender-Feed konnte nicht geladen werden.',
		'parse_failed' => 'Kalender-Feed konnte nicht gelesen werden.',
		'partial' => 'Einige Feeds konnten nicht geladen werden; es werden Teilergebnisse angezeigt.',
		'generic' => 'Kalender-Feed-Fehler.'
	)
);

// Admin strings
$lang ['admin'] ['plugin'] ['submenu'] ['icalfeed'] = 'iCalFeed';

$lang ['admin'] ['plugin'] ['icalfeed'] = array(
	'head' => 'iCal/ICS-Kalender-Feed',
	'desc1' => 'Zeigt kommende Termine oder Belegt-Zeiten aus einem oder mehreren iCalendar-(ICS)-Feeds an (z. B. eine öffentliche Google-Kalender-ICS-URL).',
	'desc2' => 'Datenschutz-Tipp: Stelle <strong>Privatsphäre</strong> auf <em>Nur belegt</em>, um keine Titel/Orte zu veröffentlichen.',

	'feed_urls_label' => 'Feed-URL(s)',
	'feed_urls_help' => 'Eine URL pro Zeile. Unterstützt: http(s) und webcal:// (wird zu https konvertiert).',

	'ssl_verify_label' => 'TLS/SSL-Prüfung',
	'ssl_verify_checkbox' => 'Zertifikate prüfen (empfohlen)',
	'ssl_verify_help' => 'Nur deaktivieren, wenn dein Hoster kein CA-Bundle hat oder die Feed-URL ein selbstsigniertes Zertifikat nutzt. Deaktivieren reduziert die Sicherheit.',

	'cache_ttl_label' => 'Cache-TTL (Sekunden)',
	'days_ahead_label' => 'Tage im Voraus',

	'display_timezone_label' => 'Anzeige-Zeitzone (optional)',
	'display_timezone_help' => 'IANA-Zeitzone, z. B. Europe/Berlin. Wenn leer, wird die TZID des Ereignisses verwendet (falls vorhanden); sonst der FlatPress-Timeoffset.',
	'limit_label' => 'Max. Einträge',

	'mode_label' => 'Anzeigemodus',
	'mode_list' => 'Kommende Termine (Liste)',
	'mode_busy' => 'Belegt-Zeiten (Verfügbarkeit)',

	'privacy_label' => 'Privatsphäre',
	'privacy_details' => 'Details anzeigen (Titel/Ort)',
	'privacy_busy' => 'Nur belegt',

	'show_location_label' => 'Ort anzeigen (nur wenn Details aktiv sind)',

	'save' => 'Einstellungen speichern',
	'clear_cache' => 'Cache jetzt leeren',

	'tag_usage_head' => 'Verwendung',
	'tag_usage' => '[icalfeed] oder [icalfeed url="https://.../basic.ics" days="14" limit="10" privacy="busy" mode="busy" tz="Europe/Berlin"]',

	'msgs' => array(
		1 => 'Einstellungen erfolgreich gespeichert.',
		2 => 'Cache geleert.',
		-1 => 'Keine gültige Feed-URL konfiguriert.'
	)
);

?>
