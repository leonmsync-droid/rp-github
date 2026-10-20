<?php
declare(strict_types=1);

// Adds change over time to a day's scores and writes the standard score rows (data/scores/YYYY-MM-DD.json).
// rank_change = previous day's rank − today's (positive = rising); score changes are in points.
//   php momentum/momentum.php [--date=YYYY-MM-DD]

require __DIR__ . '/../src/bootstrap.php';

$opts = getopt('', ['date:']);
$date = $opts['date'] ?? today();
$base = Files::read(DATA_DIR . "/scores/base/{$date}.json");
if (!$base) {
    logline("No base scores for {$date}; run score/score.php first");
    exit(1);
}

$yesterday = Snapshots::scores(Snapshots::shift($date, -1));
$week = Snapshots::scores(Snapshots::shift($date, -7));
$month = Snapshots::scores(Snapshots::shift($date, -28));

// An item is new until it has at least 2 snapshots.
$seenBefore = [];
foreach (Snapshots::dates() as $d) {
    if ($d < $date) {
        foreach (Snapshots::scores($d) as $id => $_) {
            $seenBefore[$id] = true;
        }
    }
}
$snapshotDates = array_filter(Snapshots::dates(), fn($d) => $d < $date);
$previousSnapshot = $snapshotDates ? Snapshots::load(end($snapshotDates)) : [];

$rows = [];
foreach ($base['rows'] as $r) {
    $id = $r['item_id'];
    $rows[] = [
        'item_id' => $id,
        'date' => $date,
        'score' => $r['score'],
        'rank' => $r['rank'],
        'rank_change' => isset($yesterday[$id]) ? $yesterday[$id]['rank'] - $r['rank'] : null,
        'score_change_7d' => isset($week[$id]) ? round($r['score'] - $week[$id]['score'], 1) : null,
        'score_change_28d' => isset($month[$id]) ? round($r['score'] - $month[$id]['score'], 1) : null,
        'is_new' => !isset($previousSnapshot[$id]) && !isset($seenBefore[$id]),
    ];
}

Files::write(DATA_DIR . "/scores/{$date}.json", $rows);
$risers = array_filter($rows, fn($r) => ($r['rank_change'] ?? 0) > 0);
logline(count($rows) . " score rows for {$date}, " . count($risers) . ' rising');
