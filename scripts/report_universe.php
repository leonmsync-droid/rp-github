<?php
declare(strict_types=1);

// Checkpoint: what's in a day's snapshot, before any scoring.
//   php scripts/report_universe.php [YYYY-MM-DD]

require __DIR__ . '/../src/bootstrap.php';

$date = $argv[1] ?? today();
$rows = Files::read(DATA_DIR . "/snapshots/{$date}.json", []);
$items = [];
foreach ($rows as $r) {
    $items[$r['item_id']]['name'] = $r['item_name'];
    $items[$r['item_id']]['category'] = $r['category'];
    $items[$r['item_id']][$r['metric']] = $r['value'];
}
$n = count($items);
echo "Snapshot {$date}: {$n} repos, " . count($rows) . " rows\n\n";

$count = function (callable $key) use ($items): array {
    $out = [];
    foreach ($items as $i) {
        $k = $key($i);
        $out[$k] = ($out[$k] ?? 0) + 1;
    }
    return $out;
};
$show = function (string $title, array $counts, int $limit = 12) use ($n): void {
    echo "{$title}\n";
    foreach (array_slice($counts, 0, $limit, true) as $k => $v) {
        printf("  %-24s %5d  %4.1f%%\n", $k, $v, 100 * $v / $n);
    }
    echo "\n";
};

$langs = $count(fn($i) => $i['category']);
arsort($langs);
$show('Language', $langs);

$band = fn(float $v, array $edges, string $unit): string => (function () use ($v, $edges, $unit) {
    $prev = 0;
    foreach ($edges as $e) {
        if ($v < $e) {
            return "{$prev}–{$e} {$unit}";
        }
        $prev = $e;
    }
    return "{$prev}+ {$unit}";
})();
$show('Stars', $count(fn($i) => $band($i['stars_total'], [200, 500, 1000, 5000], 'stars')));
$show('Age', $count(fn($i) => $band($i['age_days'], [7, 30, 60, 90], 'days')));
$show('Last push', $count(fn($i) => $band($i['days_since_last_push'], [1, 7, 30], 'days ago')));

// Possible fake-star pattern: many stars but almost no forks.
$suspect = array_filter($items, fn($i) => $i['stars_total'] >= 300 && $i['forks_total'] <= 2);
echo 'Many stars, almost no forks (300+ stars, 2 or fewer forks): ' . count($suspect) . "\n\n";

uasort($items, fn($a, $b) => $b['stars_per_day_since_creation'] <=> $a['stars_per_day_since_creation']);
echo "Fastest by stars per day since creation\n";
foreach (array_slice($items, 0, 15) as $i) {
    printf("  %-44s %-12s %8.1f/day  %6d stars  %4.0f days old\n", mb_strimwidth($i['name'], 0, 44, '…'), mb_strimwidth($i['category'], 0, 12), $i['stars_per_day_since_creation'], $i['stars_total'], $i['age_days']);
}
