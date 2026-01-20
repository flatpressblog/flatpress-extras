<?php
/**
 * iCalFeed library (ICS fetch + parse + basic recurrence expansion).
 *
 * Designed for FlatPress (PHP 7.2+), but kept framework-agnostic.
 *
 * Output timestamps are UTC.
 *
 * Supported fields:
 * - DTSTART, DTEND, DURATION, UID, SUMMARY, LOCATION, RRULE, EXDATE, RECURRENCE-ID
 *
 * Supported RRULE subset:
 * - FREQ=DAILY|WEEKLY|MONTHLY
 * - INTERVAL, UNTIL, COUNT
 * - BYDAY (weekly + monthly), BYMONTHDAY (monthly)
 */

/**
 * Fetch a URL via cURL (preferred) or stream context.
 * Returns the response body string on success; null on failure.
 *
 * @param string $url
 * @param int $timeoutSeconds
 * @param int|null $httpCode
 * @param string|null $error
 * @return string|null
 */
function icalfeed_http_get($url, $timeoutSeconds = 10, &$httpCode = null, &$error = null, $sslVerify = true) {
	$timeoutSeconds = (int) $timeoutSeconds;
	if ($timeoutSeconds <= 0) {
		$timeoutSeconds = 10;
	}

	$httpCode = null;
	$error = null;
	$sslVerify = (bool) $sslVerify;

	// Prefer cURL (most reliable on shared hosts)
	if (function_exists('curl_init')) {
		$ch = curl_init();
		if ($ch === false) {
			$error = 'curl_init_failed';
			return null;
		}

		$headers = array(
			'Accept: text/calendar, text/plain, */*',
			'User-Agent: FlatPress iCalFeed'
		);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $sslVerify ? true : false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $sslVerify ? 2 : 0);

		$body = curl_exec($ch);
		if ($body === false) {
			$error = 'curl_error:' . (string) curl_error($ch);
			if (!is_php85_plus()) {
				curl_close($ch);
			}
			return null;
		}

		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$httpCode = is_numeric($code) ? (int) $code : null;
		if (!is_php85_plus()) {
			curl_close($ch);
		}

		if ($httpCode !== null && $httpCode >= 400) {
			$error = 'http_' . (string) $httpCode;
			return null;
		}

		return is_string($body) ? $body : null;
	}

	// Fallback: allow_url_fopen
	if (!ini_get('allow_url_fopen')) {
		$error = 'allow_url_fopen_disabled';
		return null;
	}

	$ctx = stream_context_create(array(
		'http' => array(
			'timeout' => $timeoutSeconds,
			'header' => "Accept: text/calendar, text/plain, */*\r\nUser-Agent: FlatPress iCalFeed\r\n"
		),
		'ssl' => array(
			'verify_peer' => $sslVerify ? true : false,
			'verify_peer_name' => $sslVerify ? true : false
		)
	));

	$body = @file_get_contents($url, false, $ctx);
	if ($body === false) {
		$error = 'stream_failed';
		return null;
	}

	// Try to extract HTTP status code
	$code = null;
	if (isset($http_response_header) && is_array($http_response_header)) {
		foreach ($http_response_header as $hdr) {
			if (preg_match('~^HTTP/\S+\s+(\d{3})~', (string) $hdr, $m)) {
				$code = (int) $m [1];
				break;
			}
		}
	}
	$httpCode = $code;
	if ($httpCode !== null && $httpCode >= 400) {
		$error = 'http_' . (string) $httpCode;
		return null;
	}

	return is_string($body) ? $body : null;
}

/**
 * RFC5545 unfolding: lines that begin with a single space or tab continue the previous line.
 * @param string $ics
 * @return array<int,string>
 */
function icalfeed_unfold_lines($ics) {
	$ics = str_replace("\r\n", "\n", (string) $ics);
	$ics = str_replace("\r", "\n", $ics);
	$raw = explode("\n", $ics);

	$lines = array();
	$cur = '';
	foreach ($raw as $line) {
		$line = (string) $line;
		if ($line === '') {
			if ($cur !== '') {
				$lines [] = $cur;
				$cur = '';
			}
			continue;
		}
		$first = $line [0];
		if (($first === ' ' || $first === "\t") && $cur !== '') {
			$cur .= substr($line, 1);
		} else {
			if ($cur !== '') {
				$lines [] = $cur;
			}
			$cur = $line;
		}
	}
	if ($cur !== '') {
		$lines [] = $cur;
	}
	return $lines;
}

/**
 * Parse a content line: NAME(;PARAM=VAL...):VALUE
 * @param string $line
 * @return array{0:string,1:array<string,string>,2:string}|null
 */
function icalfeed_parse_contentline($line) {
	$line = (string) $line;
	$pos = strpos($line, ':');
	if ($pos === false) {
		return null;
	}
	$left = substr($line, 0, $pos);
	$value = substr($line, $pos + 1);

	$parts = explode(';', $left);
	$name = strtoupper(trim((string) array_shift($parts)));
	$params = array();
	foreach ($parts as $p) {
		$p = (string) $p;
		$eq = strpos($p, '=');
		if ($eq === false) {
			continue;
		}
		$k = strtoupper(trim(substr($p, 0, $eq)));
		$v = trim(substr($p, $eq + 1));
		// Remove optional quotes
		if ($v !== '' && $v [0] === '"' && substr($v, -1) === '"') {
			$v = substr($v, 1, -1);
		}
		$params [$k] = $v;
	}

	return array($name, $params, (string) $value);
}

/**
 * Unescape iCalendar text values.
 * RFC5545: \\n, \\N, \\;, \\,, \\\\.
 * @param string $s
 * @return string
 */
function icalfeed_unescape_text($s) {
	$s = (string) $s;
	// Normalize line breaks
	$s = str_replace(array('\\n', '\\N'), "\n", $s);
	$s = str_replace(array('\\,', '\\;'), array(',', ';'), $s);
	$s = str_replace('\\\\', '\\', $s);
	return $s;
}

/**
 * Parse DTSTART/DTEND/EXDATE/RECURRENCE-ID.
 * Returns a UTC timestamp.
 *
 * If no TZID and no trailing Z are provided, the value is treated as "floating".
 * For floating values we interpret them as site-local time (fixed timeoffset) and convert to UTC by subtracting $defaultOffsetSeconds.
 *
 * @param string $value
 * @param array<string,string> $params
 * @param int $defaultOffsetSeconds
 * @param bool $isAllDay
 * @return int|null
 */
function icalfeed_parse_datetime($value, array $params, $defaultOffsetSeconds, &$isAllDay) {
	$value = trim((string) $value);
	$defaultOffsetSeconds = (int) $defaultOffsetSeconds;
	$isAllDay = false;

	// All-day
	if (($params ['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
		if (!preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
			return null;
		}
		$y = (int) $m [1];
		$mo = (int) $m [2];
		$d = (int) $m [3];
		$isAllDay = true;
		$ts = @gmmktime(0, 0, 0, $mo, $d, $y);
		if (!is_int($ts) || $ts <= 0) {
			return null;
		}
		// convert local midnight to UTC
		return $ts - $defaultOffsetSeconds;
	}

	$hasZ = (substr($value, -1) === 'Z');
	$raw = $hasZ ? substr($value, 0, -1) : $value;
	if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?$/', $raw, $m)) {
		return null;
	}
	$y = (int) $m [1];
	$mo = (int) $m [2];
	$d = (int) $m [3];
	$h = (int) $m [4];
	$mi = (int) $m [5];
	$s = isset($m [6]) && $m [6] !== '' ? (int) $m [6] : 0;

	$tzid = isset($params ['TZID']) ? (string) $params ['TZID'] : '';
	if ($hasZ) {
		$ts = @gmmktime($h, $mi, $s, $mo, $d, $y);
		return (is_int($ts) && $ts > 0) ? $ts : null;
	}

	if ($tzid !== '') {
		try {
			$tz = new DateTimeZone($tzid);
			$dt = new DateTimeImmutable('now', $tz);
			$dt = $dt->setDate($y, $mo, $d)->setTime($h, $mi, $s);
			$utc = $dt->setTimezone(new DateTimeZone('UTC'));
			return (int) $utc->format('U');
		} catch (Exception $e) {
			// Fall through to floating handling
		}
	}

	// Floating (no TZID, no Z): interpret as site-local (fixed offset)
	$ts = @gmmktime($h, $mi, $s, $mo, $d, $y);
	if (!is_int($ts) || $ts <= 0) {
		return null;
	}
	return $ts - $defaultOffsetSeconds;
}

/**
 * Parse an RRULE string into an associative array.
 * @param string $raw
 * @return array<string,mixed>
 */
function icalfeed_parse_rrule($raw) {
	$out = array();
	$raw = trim((string) $raw);
	if ($raw === '') {
		return $out;
	}
	$parts = explode(';', $raw);
	foreach ($parts as $p) {
		$p = (string) $p;
		$eq = strpos($p, '=');
		if ($eq === false) {
			continue;
		}
		$k = strtoupper(trim(substr($p, 0, $eq)));
		$v = trim(substr($p, $eq + 1));
		if ($k === '') {
			continue;
		}
		if (in_array($k, array('BYDAY', 'BYMONTHDAY'), true)) {
			$items = array();
			foreach (explode(',', $v) as $it) {
				$it = strtoupper(trim((string) $it));
				if ($it !== '') {
					$items [] = $it;
				}
			}
			$out [$k] = $items;
			continue;
		}
		if (in_array($k, array('COUNT', 'INTERVAL'), true)) {
			$out [$k] = (int) $v;
			continue;
		}
		$out [$k] = strtoupper($v);
	}
	return $out;
}

/**
 * Parse a DURATION value (very small subset: PnDTnHnMnS).
 * Returns seconds or null.
 * @param string $raw
 * @return int|null
 */
function icalfeed_parse_duration_seconds($raw) {
	$raw = strtoupper(trim((string) $raw));
	if ($raw === '') {
		return null;
	}
	if (!preg_match('/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $raw, $m)) {
		return null;
	}
	$days = isset($m [1]) && $m [1] !== '' ? (int) $m [1] : 0;
	$h = isset($m [2]) && $m [2] !== '' ? (int) $m [2] : 0;
	$mi = isset($m [3]) && $m [3] !== '' ? (int) $m [3] : 0;
	$s = isset($m [4]) && $m [4] !== '' ? (int) $m [4] : 0;
	return $days * 86400 + $h * 3600 + $mi * 60 + $s;
}

/**
 * Parse an ICS document into raw events.
 *
 * @param string $ics
 * @param int $defaultOffsetSeconds
 * @param string|null $error
 * @return array<int,array<string,mixed>>
 */
function icalfeed_parse_ics($ics, $defaultOffsetSeconds = 0, &$error = null) {
	$defaultOffsetSeconds = (int) $defaultOffsetSeconds;
	$error = null;

	$lines = icalfeed_unfold_lines((string) $ics);
	$events = array();

	$inEvent = false;
	$ev = array();

	foreach ($lines as $line) {
		$line = trim((string) $line);
		if ($line === '') {
			continue;
		}

		if ($line === 'BEGIN:VEVENT') {
			$inEvent = true;
			$ev = array('uid' => '', 'start_ts' => null, 'end_ts' => null, 'all_day' => false, 'summary' => '', 'location' => '', 'rrule_raw' => '', 'rrule' => array(), 'exdates' => array(), 'recurrence_id_ts' => null, 'tzid' => '', 'default_offset' => $defaultOffsetSeconds);
			continue;
		}
		if ($line === 'END:VEVENT') {
			if ($inEvent) {
				// Basic validation
				if (!isset($ev ['start_ts']) || !is_int($ev ['start_ts'])) {
					$inEvent = false;
					$ev = array();
					continue;
				}

				if (!isset($ev ['uid']) || (string) $ev ['uid'] === '') {
					$basis = (string) $ev ['start_ts'] . '|' . (string) ($ev ['summary'] ?? '') . '|' . (string) ($ev ['location'] ?? '');
					$ev ['uid'] = 'fp-' . sha1($basis);
				}

				// End handling
				if (!isset($ev ['end_ts']) || !is_int($ev ['end_ts'])) {
					$ev ['end_ts'] = $ev ['start_ts'] + (!empty($ev ['all_day']) ? 86400 : 3600);
				}
				if ($ev ['end_ts'] < $ev ['start_ts']) {
					$ev ['end_ts'] = $ev ['start_ts'];
				}
				$ev ['duration'] = (int) max(0, $ev ['end_ts'] - $ev ['start_ts']);

				// Normalize exdates
				if (isset($ev ['exdates']) && is_array($ev ['exdates'])) {
					$uniq = array();
					foreach ($ev ['exdates'] as $x) {
						if (is_int($x)) {
							$uniq [(string) $x] = $x;
						}
					}
					$ev ['exdates'] = array_values($uniq);
				}

				$events [] = $ev;
			}
			$inEvent = false;
			$ev = array();
			continue;
		}

		if (!$inEvent) {
			continue;
		}

		$parsed = icalfeed_parse_contentline($line);
		if (!$parsed) {
			continue;
		}
		list($name, $params, $value) = $parsed;

		switch ($name) {
			case 'UID':
				$ev ['uid'] = trim((string) $value);
				break;

			case 'SUMMARY':
				$ev ['summary'] = icalfeed_unescape_text((string) $value);
				break;

			case 'LOCATION':
				$ev ['location'] = icalfeed_unescape_text((string) $value);
				break;

			case 'DTSTART':
				$allDay = false;
				if (isset($params ['TZID']) && is_string($params ['TZID']) && (string) $params ['TZID'] !== '') {
					$candTz = (string) $params ['TZID'];
					try {
						new DateTimeZone($candTz);
						$ev ['tzid'] = $candTz;
					} catch (Exception $e) {
						// ignore invalid TZID
					}
				}
				$ts = icalfeed_parse_datetime($value, $params, $defaultOffsetSeconds, $allDay);
				if (is_int($ts)) {
					$ev ['start_ts'] = $ts;
					$ev ['all_day'] = $allDay;
				}
				break;

			case 'DTEND':
				$allDay2 = false;
				$ts2 = icalfeed_parse_datetime($value, $params, $defaultOffsetSeconds, $allDay2);
				if (is_int($ts2)) {
					$ev ['end_ts'] = $ts2;
				}
				break;

			case 'DURATION':
				// Only used if DTEND missing
				if (!isset($ev ['end_ts']) || !is_int($ev ['end_ts'])) {
					$dur = icalfeed_parse_duration_seconds($value);
					if (is_int($dur) && isset($ev ['start_ts']) && is_int($ev ['start_ts'])) {
						$ev ['end_ts'] = $ev ['start_ts'] + $dur;
					}
				}
				break;

			case 'RRULE':
				$ev ['rrule_raw'] = (string) $value;
				$ev ['rrule'] = icalfeed_parse_rrule((string) $value);
				break;

			case 'EXDATE':
				// Can contain comma-separated list
				$vals = explode(',', (string) $value);
				foreach ($vals as $v) {
					$ad = false;
					$xt = icalfeed_parse_datetime(trim((string) $v), $params, $defaultOffsetSeconds, $ad);
					if (is_int($xt)) {
						$ev ['exdates'] [] = $xt;
					}
				}
				break;

			case 'RECURRENCE-ID':
				$ad3 = false;
				$rid = icalfeed_parse_datetime($value, $params, $defaultOffsetSeconds, $ad3);
				if (is_int($rid)) {
					$ev ['recurrence_id_ts'] = $rid;
				}
				break;
		}
	}

	return $events;
}

/**
 * Expand events (including RRULE) into occurrences.
 *
 * @param array<int,array<string,mixed>> $events
 * @param int $fromTs Inclusive lower bound (UTC)
 * @param int $toTs Exclusive upper bound (UTC)
 * @param int $limit Max items (0=no limit)
 * @return array<int,array<string,mixed>>
 */
function icalfeed_expand_events(array $events, $fromTs, $toTs, $limit = 0) {
	$fromTs = (int) $fromTs;
	$toTs = (int) $toTs;
	$limit = (int) $limit;
	if ($toTs <= $fromTs) {
		return array();
	}

	// Collect override keys (UID#RECURRENCE-ID) so we can skip the original occurrence.
	$override = array();
	foreach ($events as $e) {
		if (isset($e ['uid'], $e ['recurrence_id_ts']) && is_int($e ['recurrence_id_ts'])) {
			$override [(string) $e ['uid'] . '#' . (string) $e ['recurrence_id_ts']] = true;
		}
	}

	$out = array();

	foreach ($events as $e) {
		if (!isset($e ['start_ts'], $e ['end_ts']) || !is_int($e ['start_ts']) || !is_int($e ['end_ts'])) {
			continue;
		}

		$uid = isset($e ['uid']) ? (string) $e ['uid'] : '';
		$duration = isset($e ['duration']) && is_int($e ['duration']) ? (int) $e ['duration'] : (int) max(0, $e ['end_ts'] - $e ['start_ts']);
		$allDay = !empty($e ['all_day']);

		$exset = array();
		if (isset($e ['exdates']) && is_array($e ['exdates'])) {
			foreach ($e ['exdates'] as $x) {
				if (is_int($x)) {
					$exset [(string) $x] = true;
				}
			}
		}

		// Overrides are standalone events.
		if (isset($e ['recurrence_id_ts']) && is_int($e ['recurrence_id_ts'])) {
			$st = $e ['start_ts'];
			$en = $e ['end_ts'];
			if ($st < $toTs && $en > $fromTs) {
				$out [] = $e;
			}
			continue;
		}

		$rr = isset($e ['rrule']) && is_array($e ['rrule']) ? $e ['rrule'] : array();
		$freq = isset($rr ['FREQ']) ? (string) $rr ['FREQ'] : '';
		$tzid = isset($e ['tzid']) ? (string) $e ['tzid'] : '';

		if ($freq === '') {
			// Non-recurring
			if ($e ['start_ts'] < $toTs && $e ['end_ts'] > $fromTs) {
				$out [] = $e;
			}
			continue;
		}

		$interval = isset($rr ['INTERVAL']) && (int) $rr ['INTERVAL'] > 0 ? (int) $rr ['INTERVAL'] : 1;
		$countMax = isset($rr ['COUNT']) && (int) $rr ['COUNT'] > 0 ? (int) $rr ['COUNT'] : 0;
		$defaultOffset = isset($e ['default_offset']) ? (int) $e ['default_offset'] : 0;
		$untilTs = null;
		if (isset($rr ['UNTIL']) && is_string($rr ['UNTIL']) && $rr ['UNTIL'] !== '') {
			$untilTs = icalfeed_parse_until_utc((string) $rr ['UNTIL'], $tzid, $defaultOffset);
		}

		// We only need to expand in a bounded window. Use a small lookback for overlap cases.
		$windowFrom = $fromTs - max(0, $duration);
		$windowTo = $toTs;

		$occStarts = null;
		if ($tzid !== '' && ($freq === 'DAILY' || $freq === 'WEEKLY' || $freq === 'MONTHLY')) {
			$occStarts = icalfeed_expand_rrule_tz($e, $rr, $windowFrom, $windowTo, $interval, $countMax, $untilTs);
		}

		if ($occStarts === null) {
			$occStarts = array();

			if ($freq === 'DAILY') {
			$step = 86400 * $interval;
			$start = (int) $e ['start_ts'];
			$k = 0;
			if ($windowFrom > $start && $step > 0) {
				$k = (int) floor(($windowFrom - $start) / $step);
				if ($k > 0) {
					$k = max(0, $k - 1);
				}
			}
			$cur = $start + $k * $step;
			$n = 0;
			while (true) {
				if ($countMax > 0 && $n >= $countMax) {
					break;
				}
				if ($untilTs !== null && $cur > $untilTs) {
					break;
				}
				if ($cur >= $windowTo) {
					break;
				}
				$occStarts [] = $cur;
				$cur += $step;
				$n++;
			}
		} elseif ($freq === 'WEEKLY') {
			$week = 7 * 86400;
			$step = $week * $interval;
			$start = (int) $e ['start_ts'];
			$tod = $start % 86400;
			$weekdayOfStart = (int) gmdate('N', $start); // 1..7
			$weekStart = $start - ($weekdayOfStart - 1) * 86400 - $tod; // Monday 00:00 UTC

			$byday = array();
			if (isset($rr ['BYDAY']) && is_array($rr ['BYDAY']) && count($rr ['BYDAY']) > 0) {
				$byday = $rr ['BYDAY'];
			} else {
				$byday = array(strtoupper((string) gmdate('D', $start))); // e.g. MON
			}
			$map = array('1' => 1, 'MO' => 1, 'MON' => 1, 'TU' => 2, 'TUE' => 2, 'TUEs' => 2, 'WE' => 3, 'WED' => 3, 'TH' => 4, 'THU' => 4, 'FR' => 5, 'FRI' => 5, 'SA' => 6, 'SAT' => 6, 'SU' => 7, 'SUN' => 7);

			$weekIndex = 0;
			if ($windowFrom > $start && $step > 0) {
				$weekIndex = (int) floor(($windowFrom - $start) / $step);
				if ($weekIndex > 0) {
					$weekIndex = max(0, $weekIndex - 1);
				}
			}
			$curWeekStart = $weekStart + $weekIndex * $step;

			$n = 0;
			while (true) {
				if ($countMax > 0 && $n >= $countMax) {
					break;
				}
				if ($curWeekStart >= $windowTo) {
					break;
				}

				foreach ($byday as $dcode) {
					$dcode = strtoupper((string) $dcode);
					$wd = null;
					// Strip ordinals like 1MO, -1SU (weekly ordinals are ignored)
					if (preg_match('/^[+-]?\d*(MO|TU|WE|TH|FR|SA|SU)$/', $dcode, $m)) {
						$wd = $map [$m [1]] ?? null;
					} elseif (isset($map [$dcode])) {
						$wd = $map [$dcode];
					}
					if (!is_int($wd) || $wd < 1 || $wd > 7) {
						continue;
					}
					$occ = $curWeekStart + ($wd - 1) * 86400 + $tod;
					if ($occ < (int) $e ['start_ts']) {
						continue;
					}
					if ($untilTs !== null && $occ > $untilTs) {
						continue;
					}
					if ($occ >= $windowTo) {
						continue;
					}
					$occStarts [] = $occ;
					$n++;
					if ($countMax > 0 && $n >= $countMax) {
						break 2;
					}
				}

				$curWeekStart += $step;
			}
		} elseif ($freq === 'MONTHLY') {
			$start = (int) $e ['start_ts'];
			$tod = $start % 86400;

			$startY = (int) gmdate('Y', $start);
			$startM = (int) gmdate('n', $start);

			$fromY = (int) gmdate('Y', $windowFrom);
			$fromM = (int) gmdate('n', $windowFrom);

			$startIndex = $startY * 12 + ($startM - 1);
			$fromIndex = $fromY * 12 + ($fromM - 1);
			$diff = $fromIndex - $startIndex;
			$k = 0;
			if ($diff > 0 && $interval > 0) {
				$k = (int) floor($diff / $interval);
				if ($k > 0) {
					$k = max(0, $k - 1);
				}
			}

			$idx = $startIndex + $k * $interval;
			$n = 0;

			$byMonthDay = array();
			if (isset($rr ['BYMONTHDAY']) && is_array($rr ['BYMONTHDAY']) && count($rr ['BYMONTHDAY']) > 0) {
				foreach ($rr ['BYMONTHDAY'] as $md) {
					$md = (int) $md;
					if ($md !== 0) {
						$byMonthDay [] = $md;
					}
				}
			}

			$byDay = array();
			if (isset($rr ['BYDAY']) && is_array($rr ['BYDAY']) && count($rr ['BYDAY']) > 0) {
				$byDay = $rr ['BYDAY'];
			}

			while (true) {
				$y = (int) floor($idx / 12);
				$mo = (int) ($idx % 12) + 1;
				$monthStartTs = @gmmktime(0, 0, 0, $mo, 1, $y);
				if (!is_int($monthStartTs) || $monthStartTs <= 0) {
					break;
				}

				if ($monthStartTs >= $windowTo) {
					break;
				}

				$daysInMonth = (int) gmdate('t', $monthStartTs);

				if (count($byMonthDay) > 0) {
					foreach ($byMonthDay as $md) {
						$day = $md;
						if ($md < 0) {
							$day = $daysInMonth + 1 + $md; // -1 => last day
						}
						if ($day < 1 || $day > $daysInMonth) {
							continue;
						}
						$occ = @gmmktime((int) floor($tod / 3600), (int) floor(($tod % 3600) / 60), (int) ($tod % 60), $mo, $day, $y);
						if (!is_int($occ) || $occ <= 0) {
							continue;
						}
						if ($occ < (int) $e ['start_ts']) {
							continue;
						}
						if ($untilTs !== null && $occ > $untilTs) {
							continue;
						}
						if ($occ >= $windowTo) {
							continue;
						}
						$occStarts [] = $occ;
						$n++;
						if ($countMax > 0 && $n >= $countMax) {
							break 2;
						}
					}
				} elseif (count($byDay) > 0) {
					// Monthly BYDAY: supports ordinals (e.g. 1MO, -1SU). If no ordinal, includes all such weekdays.
					$map2 = array('MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7);
					foreach ($byDay as $spec) {
						$spec = strtoupper((string) $spec);
						if (!preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $spec, $m)) {
							continue;
						}
						$ord = isset($m [1]) && $m [1] !== '' ? (int) $m [1] : 0;
						$wd = $map2 [$m [2]];

						if ($ord !== 0) {
							$day = icalfeed_nth_weekday_of_month($y, $mo, $wd, $ord);
							if ($day !== null) {
								$occ = @gmmktime((int) floor($tod / 3600), (int) floor(($tod % 3600) / 60), (int) ($tod % 60), $mo, $day, $y);
								if (is_int($occ) && $occ >= (int) $e ['start_ts'] && $occ < $windowTo && ($untilTs === null || $occ <= $untilTs)) {
									$occStarts [] = $occ;
									$n++;
									if ($countMax > 0 && $n >= $countMax) {
										break 3;
									}
								}
							}
						} else {
							// No ordinal: include all matching weekdays in this month
							for ($day = 1; $day <= $daysInMonth; $day++) {
								$tsd = @gmmktime(0, 0, 0, $mo, $day, $y);
								if (!is_int($tsd) || (int) gmdate('N', $tsd) !== $wd) {
									continue;
								}
								$occ = @gmmktime((int) floor($tod / 3600), (int) floor(($tod % 3600) / 60), (int) ($tod % 60), $mo, $day, $y);
								if (!is_int($occ)) {
									continue;
								}
								if ($occ < (int) $e ['start_ts']) {
									continue;
								}
								if ($untilTs !== null && $occ > $untilTs) {
									continue;
								}
								if ($occ >= $windowTo) {
									continue;
								}
								$occStarts [] = $occ;
								$n++;
								if ($countMax > 0 && $n >= $countMax) {
									break 4;
								}
							}
						}
					}
				} else {
					// Default monthly: same day-of-month as DTSTART
					$dom = (int) gmdate('j', $start);
					if ($dom <= $daysInMonth) {
						$occ = @gmmktime((int) floor($tod / 3600), (int) floor(($tod % 3600) / 60), (int) ($tod % 60), $mo, $dom, $y);
						if (is_int($occ) && $occ >= (int) $e ['start_ts'] && $occ < $windowTo && ($untilTs === null || $occ <= $untilTs)) {
							$occStarts [] = $occ;
							$n++;
						}
					}
				}

				$idx += $interval;
				if ($countMax > 0 && $n >= $countMax) {
					break;
				}
				// Safety cap (prevents accidental infinite loops on malformed rules)
				if ($idx - $startIndex > 2400) { // 200 years
					break;
				}
			}
		}
		}

		// Turn starts into occurrences
		foreach ($occStarts as $st) {
			$st = (int) $st;
			if (isset($exset [(string) $st])) {
				continue;
			}
			if ($uid !== '' && isset($override [$uid . '#' . (string) $st])) {
				continue;
			}
			$en = $st + $duration;
			if ($st < $toTs && $en > $fromTs) {
				$occ = $e;
				$occ ['start_ts'] = $st;
				$occ ['end_ts'] = $en;
				$occ ['duration'] = $duration;
				$occ ['all_day'] = $allDay;
				$out [] = $occ;
			}
		}
	}

	// Sort by start time
	usort($out, 'icalfeed_event_sort');

	if ($limit > 0 && count($out) > $limit) {
		$out = array_slice($out, 0, $limit);
	}

	return $out;
}

/**
 * Compatibility alias.
 * @param array<int,array<string,mixed>> $events
 * @param int $fromTs
 * @param int $toTs
 * @param int $limit
 * @return array<int,array<string,mixed>>
 */
function icalfeed_expand_occurrences(array $events, $fromTs, $toTs, $limit = 0) {
	return icalfeed_expand_events($events, $fromTs, $toTs, $limit);
}

/**
 * Sort callback.
 * @param array<string,mixed> $a
 * @param array<string,mixed> $b
 * @return int
 */
function icalfeed_event_sort($a, $b) {
	$sa = isset($a ['start_ts']) && is_int($a ['start_ts']) ? $a ['start_ts'] : 0;
	$sb = isset($b ['start_ts']) && is_int($b ['start_ts']) ? $b ['start_ts'] : 0;
	if ($sa === $sb) {
		$ua = isset($a ['uid']) ? (string) $a ['uid'] : '';
		$ub = isset($b ['uid']) ? (string) $b ['uid'] : '';
		return strcmp($ua, $ub);
	}
	return ($sa < $sb) ? -1 : 1;
}

/**
 * Returns the day-of-month for the n-th weekday of a month.
 * Example: (2026, 1, 1=Mon, 1) => first Monday.
 * Example: (2026, 1, 7=Sun, -1) => last Sunday.
 *
 * @param int $year
 * @param int $month 1..12
 * @param int $weekday 1..7 (Mon..Sun)
 * @param int $ordinal
 * @return int|null
 */
function icalfeed_nth_weekday_of_month($year, $month, $weekday, $ordinal) {
	$year = (int) $year;
	$month = (int) $month;
	$weekday = (int) $weekday;
	$ordinal = (int) $ordinal;
	if ($weekday < 1 || $weekday > 7 || $ordinal === 0) {
		return null;
	}
	$monthStart = @gmmktime(0, 0, 0, $month, 1, $year);
	if (!is_int($monthStart) || $monthStart <= 0) {
		return null;
	}
	$daysInMonth = (int) gmdate('t', $monthStart);

	if ($ordinal > 0) {
		// First day-of-month weekday
		$firstW = (int) gmdate('N', $monthStart);
		$delta = ($weekday - $firstW + 7) % 7;
		$day = 1 + $delta + ($ordinal - 1) * 7;
		return ($day >= 1 && $day <= $daysInMonth) ? $day : null;
	}

	// ordinal < 0: count from end
	$lastTs = @gmmktime(0, 0, 0, $month, $daysInMonth, $year);
	if (!is_int($lastTs) || $lastTs <= 0) {
		return null;
	}
	$lastW = (int) gmdate('N', $lastTs);
	$delta = ($lastW - $weekday + 7) % 7;
	$lastDay = $daysInMonth - $delta;
	$day = $lastDay + ($ordinal + 1) * 7; // e.g. -1 => lastDay, -2 => lastDay-7
	return ($day >= 1 && $day <= $daysInMonth) ? $day : null;
}

/**
 * Parse RRULE UNTIL into a UTC timestamp.
 * If the value is a DATE-TIME without trailing Z and a valid TZID is provided,
 * interpret the value in that timezone.
 *
 * @param string $raw
 * @param string $tzid
 * @param int $defaultOffsetSeconds
 * @return int|null
 */
function icalfeed_parse_until_utc($raw, $tzid, $defaultOffsetSeconds) {
	$raw = trim((string) $raw);
	$tzid = (string) $tzid;
	$defaultOffsetSeconds = (int) $defaultOffsetSeconds;
	if ($raw === '') {
		return null;
	}

	// Use existing parser if UTC form
	if (substr($raw, -1) === 'Z') {
		$ad = false;
		$ts = icalfeed_parse_datetime($raw, array(), $defaultOffsetSeconds, $ad);
		return is_int($ts) ? $ts : null;
	}

	// All-day UNTIL (DATE) => treat as local midnight converted to UTC
	if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $raw, $m)) {
		$y = (int) $m [1];
		$mo = (int) $m [2];
		$d = (int) $m [3];

		if ($tzid !== '') {
			try {
				$tz = new DateTimeZone($tzid);
				$dt = new DateTimeImmutable('now', $tz);
				$dt = $dt->setDate($y, $mo, $d)->setTime(0, 0, 0);
				$utc = $dt->setTimezone(new DateTimeZone('UTC'));
				return (int) $utc->format('U');
			} catch (Exception $e) {
				// fall through
			}
		}

		$ts = @gmmktime(0, 0, 0, $mo, $d, $y);
		if (!is_int($ts) || $ts <= 0) {
			return null;
		}
		return $ts - $defaultOffsetSeconds;
	}

	// If we have a TZID, interpret UNTIL in that timezone.
	if ($tzid !== '') {
		try {
			$tz = new DateTimeZone($tzid);
			if (preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?$/', $raw, $m)) {
				$y = (int) $m [1];
				$mo = (int) $m [2];
				$d = (int) $m [3];
				$h = (int) $m [4];
				$mi = (int) $m [5];
				$s = isset($m [6]) && $m [6] !== '' ? (int) $m [6] : 0;
				$dt = new DateTimeImmutable('now', $tz);
				$dt = $dt->setDate($y, $mo, $d)->setTime($h, $mi, $s);
				$utc = $dt->setTimezone(new DateTimeZone('UTC'));
				return (int) $utc->format('U');
			}
		} catch (Exception $e) {
			// fall through
		}
	}

	// Fallback: treat as floating
	$ad = false;
	$ts = icalfeed_parse_datetime($raw, array(), $defaultOffsetSeconds, $ad);
	return is_int($ts) ? $ts : null;
}

/**
 * Expand RRULE using timezone-aware DateTime for TZID events.
 * Returns an array of UTC timestamps (starts). Returns null to indicate fallback.
 *
 * @param array<string,mixed> $event
 * @param array<string,mixed> $rr
 * @param int $windowFrom
 * @param int $windowTo
 * @param int $interval
 * @param int $countMax
 * @param int|null $untilTs
 * @return array<int,int>|null
 */
function icalfeed_expand_rrule_tz(array $event, array $rr, $windowFrom, $windowTo, $interval, $countMax, $untilTs) {
	$tzid = isset($event ['tzid']) ? (string) $event ['tzid'] : '';
	if ($tzid === '') {
		return null;
	}
	try {
		$tz = new DateTimeZone($tzid);
	} catch (Exception $e) {
		return null;
	}

	if (!isset($event ['start_ts']) || !is_int($event ['start_ts'])) {
		return null;
	}
	$startUtc = (int) $event ['start_ts'];
	$duration = isset($event ['duration']) && is_int($event ['duration']) ? (int) $event ['duration'] : 0;
	$freq = isset($rr ['FREQ']) ? (string) $rr ['FREQ'] : '';
	$interval = (int) $interval;
	if ($interval <= 0) {
		$interval = 1;
	}
	$countMax = (int) $countMax;
	$windowFrom = (int) $windowFrom;
	$windowTo = (int) $windowTo;

	$utcTz = new DateTimeZone('UTC');
	$dt0 = (new DateTimeImmutable('@' . (string) $startUtc))->setTimezone($tz);
	$h = (int) $dt0->format('H');
	$mi = (int) $dt0->format('i');
	$s = (int) $dt0->format('s');

	$occStarts = array();
	$safety = 0;

	if ($freq === 'DAILY') {
		// Approximate skip in UTC days, then adjust by stepping locally.
		$k = 0;
		if ($windowFrom > $startUtc) {
			$diffDays = (int) floor(((int) $windowFrom - $startUtc) / 86400);
			$k = (int) max(0, floor($diffDays / $interval) - 2);
		}
		$n = $k;
		if ($k > 0) {
			$dt = $dt0->add(new DateInterval('P' . (string) ($k * $interval) . 'D'));
		} else {
			$dt = $dt0;
		}

		while (true) {
			if ($countMax > 0 && $n >= $countMax) {
				break;
			}
			$occUtc = (int) $dt->setTimezone($utcTz)->format('U');
			if ($untilTs !== null && $occUtc > (int) $untilTs) {
				break;
			}
			if ($occUtc >= $windowTo) {
				break;
			}
			$occEnd = $occUtc + $duration;
			if ($occUtc < $windowTo && $occEnd > $windowFrom) {
				$occStarts [] = $occUtc;
			}
			$dt = $dt->add(new DateInterval('P' . (string) $interval . 'D'));
			$n++;
			$safety++;
			if ($safety > 20000) {
				break;
			}
		}

		return $occStarts;
	}

	if ($freq === 'WEEKLY') {
		$byday = array();
		if (isset($rr ['BYDAY']) && is_array($rr ['BYDAY']) && count($rr ['BYDAY']) > 0) {
			$byday = $rr ['BYDAY'];
		} else {
			// default: weekday of DTSTART
			$mapd = array(1 => 'MO', 2 => 'TU', 3 => 'WE', 4 => 'TH', 5 => 'FR', 6 => 'SA', 7 => 'SU');
			$byday = array($mapd [(int) $dt0->format('N')] ?? 'MO');
		}
		$map = array('MO' => 1,'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7, 'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6,'SUN' => 7);
		$weekStart0 = $dt0->modify('monday this week')->setTime(0,0,0);

		// For unbounded rules, jump closer to windowFrom.
		$curWeekStart = $weekStart0;
		if ($countMax <= 0 && $windowFrom > $startUtc) {
			$diffWeeks = (int) floor(((int) $windowFrom - $startUtc) / (7*86400));
			$wk = (int) max(0, floor($diffWeeks / $interval) - 2);
			if ($wk > 0) {
				$curWeekStart = $weekStart0->add(new DateInterval('P' . (string) ($wk * $interval) . 'W'));
			}
		}

		$n = 0;
		while (true) {
			// Stop if even the week start (00:00) is beyond window
			$weekStartUtc = (int) $curWeekStart->setTimezone($utcTz)->format('U');
			if ($weekStartUtc >= $windowTo + 7*86400) {
				break;
			}

			foreach ($byday as $dcode) {
				$dcode = strtoupper((string) $dcode);
				if (!preg_match('/^[+-]?\d*(MO|TU|WE|TH|FR|SA|SU)$/', $dcode, $m)) {
					continue;
				}
				$wd = $map [$m [1]] ?? null;
				if (!is_int($wd)) {
					continue;
				}
				$dtOcc = $curWeekStart->modify('+' . (string) ($wd - 1) . ' days')->setTime($h, $mi, $s);
				if ($dtOcc < $dt0) {
					continue;
				}
				$occUtc = (int) $dtOcc->setTimezone($utcTz)->format('U');
				if ($untilTs !== null && $occUtc > (int) $untilTs) {
					return $occStarts;
				}
				if ($occUtc >= $windowTo) {
					continue;
				}
				$occEnd = $occUtc + $duration;
				if ($occUtc < $windowTo && $occEnd > $windowFrom) {
					$occStarts [] = $occUtc;
				}
				$n++;
				if ($countMax > 0 && $n >= $countMax) {
					return $occStarts;
				}
				$safety++;
				if ($safety > 20000) {
					return $occStarts;
				}
			}

			$curWeekStart = $curWeekStart->add(new DateInterval('P' . (string) $interval . 'W'));
		}

		return $occStarts;
	}

	if ($freq === 'MONTHLY') {
		$byMonthDay = array();
		if (isset($rr ['BYMONTHDAY']) && is_array($rr ['BYMONTHDAY']) && count($rr ['BYMONTHDAY']) > 0) {
			foreach ($rr ['BYMONTHDAY'] as $md) {
				$md = (int) $md;
				if ($md != 0) {
					$byMonthDay [] = $md;
				}
			}
		}
		$byDay = array();
		if (isset($rr ['BYDAY']) && is_array($rr ['BYDAY']) && count($rr ['BYDAY']) > 0) {
			$byDay = $rr ['BYDAY'];
		}

		// Jump months for unbounded rules
		$cur = $dt0->modify('first day of this month')->setTime(0,0,0);
		if ($countMax <= 0 && $windowFrom > $startUtc) {
			$dtWf = (new DateTimeImmutable('@' . (string) $windowFrom))->setTimezone($tz);
			$months0 = ((int) $dt0->format('Y')) * 12 + ((int) $dt0->format('n') - 1);
			$monthsW = ((int) $dtWf->format('Y')) * 12 + ((int) $dtWf->format('n') - 1);
			$diffM = $monthsW - $months0;
			$k = (int) max(0, floor($diffM / $interval) - 2);
			if ($k > 0) {
				$cur = $cur->add(new DateInterval('P' . (string) ($k * $interval) . 'M'));
			}
		}

		$n = 0;
		$map2 = array('MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7);

		while (true) {
			$monthStartUtc = (int) $cur->setTimezone($utcTz)->format('U');
			if ($monthStartUtc >= $windowTo + 32*86400) {
				break;
			}

			$daysInMonth = (int) $cur->format('t');
			$y = (int) $cur->format('Y');
			$mo = (int) $cur->format('n');

			if (count($byMonthDay) > 0) {
				for ($i = 0; $i < count($byMonthDay); $i++) {
					$md = (int) $byMonthDay [$i];
					$day = $md;
					if ($md < 0) {
						$day = $daysInMonth + 1 + $md;
					}
					if ($day < 1 || $day > $daysInMonth) {
						continue;
					}
					$dtOcc = $cur->setDate($y, $mo, $day)->setTime($h, $mi, $s);
					if ($dtOcc < $dt0) {
						continue;
					}
					$occUtc = (int) $dtOcc->setTimezone($utcTz)->format('U');
					if ($untilTs !== null && $occUtc > (int) $untilTs) {
						return $occStarts;
					}
					if ($occUtc >= $windowTo) {
						continue;
					}
					$occEnd = $occUtc + $duration;
					if ($occUtc < $windowTo && $occEnd > $windowFrom) {
						$occStarts [] = $occUtc;
					}
					$n++;
					if ($countMax > 0 && $n >= $countMax) {
						return $occStarts;
					}
					$safety++;
					if ($safety > 20000) {
						return $occStarts;
					}
				}
			} elseif (count($byDay) > 0) {
				for ($speci = 0; $speci < count($byDay); $speci++) {
					$spec = strtoupper((string) $byDay [$speci]);
					if (!preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $spec, $m)) {
						continue;
					}
					$ord = isset($m [1]) && $m [1] != '' ? (int) $m [1] : 0;
					$wd = $map2 [$m [2]];
					if ($ord != 0) {
						$day = icalfeed_nth_weekday_of_month($y, $mo, $wd, $ord);
						if ($day === null) {
							continue;
						}
						$dtOcc = $cur->setDate($y, $mo, (int) $day)->setTime($h, $mi, $s);
						if ($dtOcc < $dt0) {
							continue;
						}
						$occUtc = (int) $dtOcc->setTimezone($utcTz)->format('U');
						if ($untilTs !== null && $occUtc > (int) $untilTs) {
							return $occStarts;
						}
						if ($occUtc >= $windowTo) {
							continue;
						}
						$occEnd = $occUtc + $duration;
						if ($occUtc < $windowTo && $occEnd > $windowFrom) {
							$occStarts [] = $occUtc;
						}
						$n++;
						if ($countMax > 0 && $n >= $countMax) {
							return $occStarts;
						}
						$safety++;
						if ($safety > 20000) {
							return $occStarts;
						}
					} else {
						// All matching weekdays
						for ($day = 1; $day <= $daysInMonth; $day++) {
							$dtDay = $cur->setDate($y, $mo, $day)->setTime(0,0,0);
							if ((int) $dtDay->format('N') !== $wd) {
								continue;
							}
							$dtOcc = $cur->setDate($y, $mo, $day)->setTime($h, $mi, $s);
							if ($dtOcc < $dt0) {
								continue;
							}
							$occUtc = (int) $dtOcc->setTimezone($utcTz)->format('U');
							if ($untilTs !== null && $occUtc > (int) $untilTs) {
								return $occStarts;
							}
							if ($occUtc >= $windowTo) {
								continue;
							}
							$occEnd = $occUtc + $duration;
							if ($occUtc < $windowTo && $occEnd > $windowFrom) {
								$occStarts [] = $occUtc;
							}
							$n++;
							if ($countMax > 0 && $n >= $countMax) {
								return $occStarts;
							}
							$safety++;
							if ($safety > 20000) {
								return $occStarts;
							}
						}
					}
				}
			} else {
				$dom = (int) $dt0->format('j');
				if ($dom >= 1 && $dom <= $daysInMonth) {
					$dtOcc = $cur->setDate($y, $mo, $dom)->setTime($h, $mi, $s);
					if ($dtOcc >= $dt0) {
						$occUtc = (int) $dtOcc->setTimezone($utcTz)->format('U');
						if ($untilTs !== null && $occUtc > (int) $untilTs) {
							return $occStarts;
						}
						if ($occUtc < $windowTo) {
							$occEnd = $occUtc + $duration;
							if ($occEnd > $windowFrom) {
								$occStarts [] = $occUtc;
							}
						}
						$n++;
						if ($countMax > 0 && $n >= $countMax) {
							return $occStarts;
						}
					}
				}
			}

			$cur = $cur->add(new DateInterval('P' . (string) $interval . 'M'));
			$safety++;
			if ($safety > 2400) { // safety
				break;
			}
		}

		return $occStarts;
	}

	return null;
}
?>
