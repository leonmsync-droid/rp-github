<?php
declare(strict_types=1);

// Scores one day's snapshot: percentile rank per metric, weighted sum on a 0–100 scale.
// Output: data/scores/base/YYYY-MM-DD.json (score, rank and each metric's percentile and contribution).
// Momentum fields are added by momentum/momentum.php.
//   php score/score.php [--date=YYYY-MM-DD]

require __DIR__ . '/../src/bootstrap.php';

$opts = getopt('', ['date:']);
$date = $opts['date'] ?? today();
$p = config('product');
$weights = Files::read(APP_ROOT . '/score/weights.json')['metrics'];

$sum = array_sum(array_column($weights, 'weight'));
if (abs($sum - 1.0) > 1e-9) {
    fwrite(STDERR, "score/weights.json weights add up to {$sum}, not 1\n");
    exit(1);
}

$items = Snapshots::load($date);
if (!$items) {
    logline("No snapshot for {$date}");
    exit(1);
}

// 7-day gains come from the snapshot 7 days earlier; until one exists the metric is left out.
$weekAgo = Snapshots::load(Snapshots::shift($date, -7));
foreach ($items as $id => &$item) {
    $before = $weekAgo[$id]['metrics'] ?? null;
    if ($before) {
        $item['metrics']['stars_gained_7d'] = $item['metrics']['stars_total'] - $before['stars_total'];
        $item['metrics']['forks_gained_7d'] = $item['metrics']['forks_total'] - $before['forks_total'];
    }
}
unset($item);

// Possible fake stars are held back, not scored.
$held = [];
foreach ($items as $id => $item) {
    if ($item['metrics']['stars_total'] >= $p['suspect_min_stars'] && $item['metrics']['forks_total'] <= $p['suspect_max_forks']) {
        $held[] = $id;
        unset($items[$id]);
    }
}

// Percentile rank (0–100, ties share the average rank), flipped for lower_better.
$pct = [];
foreach ($weights as $w) {
    $values = [];
    foreach ($items as $id => $item) {
        if (isset($item['metrics'][$w['metric']])) {
            $values[$id] = $item['metrics'][$w['metric']];
        }
    }
    $n = count($values);
    if ($n < 2) {
        continue;
    }
    asort($values);
    $ids = array_keys($values);
    $vals = array_values($values);
    for ($i = 0; $i < $n;) {
        $j = $i;
        while ($j + 1 < $n && $vals[$j + 1] === $vals[$i]) {
            $j++;
        }
        $rank = ($i + $j) / 2;
        for ($k = $i; $k <= $j; $k++) {
            $v = 100 * $rank / ($n - 1);
            $pct[$ids[$k]][$w['metric']] = $w['direction'] === 'lower_better' ? 100 - $v : $v;
        }
        $i = $j + 1;
    }
}

// A metric an item lacks (a new repo has no 7-day gain yet) is dropped and the other weights scaled up to sum to 1.
$rows = [];
foreach ($items as $id => $item) {
    $used = array_filter($weights, fn($w) => isset($pct[$id][$w['metric']]));
    $usedWeight = array_sum(array_column($used, 'weight'));
    $score = 0.0;
    $parts = [];
    foreach ($used as $w) {
        $weight = $w['weight'] / $usedWeight;
        $parts[$w['metric']] = ['pct' => round($pct[$id][$w['metric']], 1), 'weight' => round($weight, 4), 'points' => round($weight * $pct[$id][$w['metric']], 2)];
        $score += $weight * $pct[$id][$w['metric']];
    }
    $rows[] = [
        'item_id' => $id,
        'item_name' => $item['name'],
        'date' => $date,
        'score' => round($score, 1),
        'complete' => count($used) === count($weights),
        'contributions' => $parts,
    ];
}

// Ties: highest-weighted metric first, then name.
$top = $weights;
usort($top, fn($a, $b) => $b['weight'] <=> $a['weight']);
usort($rows, function ($a, $b) use ($top) {
    if ($a['score'] !== $b['score']) {
        return $b['score'] <=> $a['score'];
    }
    foreach ($top as $w) {
        $c = ($b['contributions'][$w['metric']]['pct'] ?? -1) <=> ($a['contributions'][$w['metric']]['pct'] ?? -1);
        if ($c !== 0) {
            return $c;
        }
    }
    return strcasecmp($a['item_name'], $b['item_name']);
});
foreach ($rows as $i => &$row) {
    $row['rank'] = $i + 1;
}
unset($row);

Files::write(DATA_DIR . "/scores/base/{$date}.json", ['date' => $date, 'held_back' => $held, 'rows' => $rows]);
$missing = array_diff(array_column($weights, 'metric'), array_keys(array_merge(...array_values($pct ?: [[]]))));
logline(count($rows) . ' repos scored, ' . count($held) . ' held back' . ($missing ? '; not yet available: ' . implode(', ', $missing) : ''));
