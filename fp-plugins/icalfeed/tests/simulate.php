<?php
/**
 * CLI simulation for the iCalFeed parser/expander.
 *
 * Usage:
 *   php simulate.php
 */

require_once __DIR__ . '/../inc/icalfeed.lib.php';

$icsFile = __DIR__ . '/sample.ics';
$ics = @file_get_contents($icsFile);
if (!is_string($ics) || $ics === '') {
	fwrite(STDERR, "Failed to read sample.ics\n");
	exit(1);
}

$parseErr = null;
// Use a typical site offset (seconds) as the fallback for floating times.
$defaultOffset = 3600;
$events = icalfeed_parse_ics($ics, $defaultOffset, $parseErr);

if (!is_array($events) || count($events) < 5) {
	fwrite(STDERR, "Parse failed or too few events. error=" . (string) $parseErr . "\n");
	exit(1);
}

echo "Parsed VEVENTs: " . count($events) . "\n";

$from = (int) gmmktime(0, 0, 0, 3, 20, 2026);
$to   = (int) gmmktime(0, 0, 0, 4, 20, 2026);

$occ = icalfeed_expand_occurrences($events, $from, $to, 200);
if (!is_array($occ) || count($occ) < 4) {
	fwrite(STDERR, "Expansion failed or too few occurrences.\n");
	exit(1);
}

echo "Expanded occurrences (window): " . count($occ) . "\n";

echo "First 12 occurrences:\n";
for ($i = 0; $i < min(12, count($occ)); $i++) {
	$e = $occ [$i];
	$uid = isset($e ['uid']) ? (string) $e ['uid'] : '';
	$sum = isset($e ['summary']) ? (string) $e ['summary'] : '';
	$st = isset($e ['start_ts']) && is_int($e ['start_ts']) ? $e ['start_ts'] : 0;
	$en = isset($e ['end_ts']) && is_int($e ['end_ts']) ? $e ['end_ts'] : 0;
	$ad = !empty($e ['all_day']);
	echo sprintf(
		"%2d) %s  %s  %s  %s\n",
		$i + 1,
		gmdate('Y-m-d H:i', $st),
		gmdate('Y-m-d H:i', $en),
		$ad ? '[ALLDAY]' : '        ',
		$uid . '  ' . $sum
	);
}

// Basic assertions (sanity): the folded SUMMARY was unfolded
$foundFolded = false;
foreach ($events as $ev) {
	if (isset($ev ['uid']) && (string) $ev ['uid'] === 'ev2@example.com') {
		if (isset($ev ['summary']) && strpos((string) $ev ['summary'], "folded across lines") !== false) {
			$foundFolded = true;
		}
		break;
	}
}
if (!$foundFolded) {
	fwrite(STDERR, "Folded SUMMARY was not unfolded as expected.\n");
	exit(1);
}


// DST sanity: Europe/Berlin recurring event should stay at 09:00 local time across the DST switch
$tz = new DateTimeZone('Europe/Berlin');
$dst = array();
foreach ($occ as $e) {
	if (isset($e ['uid']) && (string) $e ['uid'] === 'dst1@example.com') {
		$dst [] = $e;
	}
}
if (count($dst) < 4) {
	fwrite(STDERR, "DST test event occurrences missing.\n");
	exit(1);
}
foreach ($dst as $dste) {
	$st = isset($dste ['start_ts']) && is_int($dste ['start_ts']) ? $dste ['start_ts'] : 0;
	$dt = (new DateTimeImmutable('@' . (string) $st))->setTimezone($tz);
	if ($dt->format('H:i') !== '09:00') {
		fwrite(STDERR, "DST local time shifted unexpectedly: " . $dt->format('c') . "\n");
		exit(1);
	}
}
echo "OK\n";
