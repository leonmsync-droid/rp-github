<?php
declare(strict_types=1);

// LOCAL DESIGN AID ONLY. Builds 27 invented earlier days from today's real snapshot in data-demo/,
// so movers, arrows and sparklines can be designed before real history exists.
// Never deploy or publish data-demo/. Use with: RP_DATA_DIR=$PWD/data-demo php -S ...
//   php scripts/demo_history.php

require __DIR__ . '/../src/bootstrap.php';

$real = APP_ROOT . '/data';
$demo = APP_ROOT . '/data-demo';
$date = Snapshots::dates()[count(Snapshots::dates()) - 1];
$today = Files::read("{$real}/snapshots/{$date}.json", []);
@mkdir("{$demo}/snapshots", 0775, true);
@mkdir("{$demo}/state", 0775, true);
copy("{$real}/state/meta.json", "{$demo}/state/meta.json");
copy("{$real}/snapshots/{$date}.json", "{$demo}/snapshots/{$date}.json");

$items = [];
foreach ($today as $r) {
    $items[$r['item_id']]['row'] = $r;
    $items[$r['item_id']]['m'][$r['metric']] = $r['value'];
}
mt_srand(42);
foreach ($items as &$it) {
    $it['trend'] = mt_rand(40, 160) / 100;   // >1: gaining faster recently
}
unset($it);

for ($back = 1; $back <= 27; $back++) {
    $d = Snapshots::shift($date, -$back);
    $rows = [];
    foreach ($items as $id => $it) {
        $m = $it['m'];
        $age = $m['age_days'] - $back;
        if ($age < 1) {
            continue;
        }
        $recent = $m['stars_per_day_since_creation'] * $it['trend'];
        $stars = max(0, (int) round($m['stars_total'] - $recent * $back * (mt_rand(85, 115) / 100)));
        $forks = (int) round($m['forks_total'] * ($m['stars_total'] ? $stars / $m['stars_total'] : 1));
        $values = [
            'stars_total' => $stars,
            'forks_total' => $forks,
            'stars_per_day_since_creation' => round($stars / $age, 2),
            'days_since_last_push' => round(mt_rand(0, 300) / 10, 1),
            'age_days' => round($age, 1),
        ];
        if (isset($m['contributors_total'])) {
            $values['contributors_total'] = $m['contributors_total'];
        }
        foreach ($values as $metric => $v) {
            $rows[] = ['date' => $d, 'metric' => $metric, 'value' => $v] + $it['row'];
        }
    }
    Files::write("{$demo}/snapshots/{$d}.json", $rows);
}
echo "Demo snapshots written for 27 days before {$date}\n";
