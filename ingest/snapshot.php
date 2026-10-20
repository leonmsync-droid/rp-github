<?php
declare(strict_types=1);

// Turns a day's raw responses into the standard snapshot rows (data/snapshots/YYYY-MM-DD.json).
// Past snapshots are never edited; --replace only rebuilds the given day's file.
//   php ingest/snapshot.php [--date=YYYY-MM-DD] [--replace]

require __DIR__ . '/../src/bootstrap.php';

$opts = getopt('', ['date:', 'replace']);
$date = $opts['date'] ?? today();
$rawDir = DATA_DIR . "/raw/{$date}";
$files = array_merge(
    Files::read("{$rawDir}/manifest.json", ['files' => []])['files'],
    Files::read("{$rawDir}/manifest_contributors.json", ['files' => []])['files'],
);
if (!$files) {
    logline("No raw data for {$date}");
    exit(1);
}

// Keeps only the fields the metrics need; full search pages are large.
$slim = fn(array $r): array => array_intersect_key($r, array_flip(['id', 'full_name', 'language', 'created_at', 'pushed_at', 'stargazers_count', 'forks_count']));
$repos = [];         // id => [repo, fetched_at]
$contributors = [];  // id => [count|null, fetched_at]
foreach ($files as $f) {
    $data = Files::read("{$rawDir}/{$f['file']}");
    if ($data === null) {
        continue;
    }
    if (str_starts_with($f['file'], 'search_')) {
        foreach ($data['items'] ?? [] as $repo) {
            $repos[$repo['id']] = [$slim($repo), $f['fetched_at']];
        }
    } elseif (str_starts_with($f['file'], 'repo_')) {
        $repos[$data['id']] = [$slim($data), $f['fetched_at']];
    } elseif (preg_match('/^contributors_(\d+)\.json$/', $f['file'], $m)) {
        $count = null;
        if ($data['status'] === 204) {
            $count = 0;
        } elseif ($data['status'] === 200) {
            $count = preg_match('/[?&]page=(\d+)>; rel="last"/', (string) $data['link'], $last)
                ? (int) $last[1]
                : count($data['body'] ?? []);
        }
        $contributors[(int) $m[1]] = [$count, $f['fetched_at']];
    }
}

$rows = [];
$row = fn(array $repo, string $metric, $value, string $unit, string $fetchedAt): array => [
    'item_id' => 'repo-' . $repo['id'],
    'item_name' => $repo['full_name'],
    'category' => $repo['language'] ?? 'Other',
    'metric' => $metric,
    'value' => $value,
    'unit' => $unit,
    'date' => $date,
    'source' => 'github',
    'fetched_at' => $fetchedAt,
];
foreach ($repos as $id => [$repo, $at]) {
    $now = strtotime($at);
    $ageDays = max(1.0, ($now - strtotime($repo['created_at'])) / 86400);
    $rows[] = $row($repo, 'stars_total', $repo['stargazers_count'], 'stars', $at);
    $rows[] = $row($repo, 'forks_total', $repo['forks_count'], 'forks', $at);
    $rows[] = $row($repo, 'stars_per_day_since_creation', round($repo['stargazers_count'] / $ageDays, 2), 'stars per day', $at);
    $rows[] = $row($repo, 'days_since_last_push', round(max(0, $now - strtotime($repo['pushed_at'])) / 86400, 1), 'days', $at);
    $rows[] = $row($repo, 'age_days', round($ageDays, 1), 'days', $at);
    if (isset($contributors[$id]) && $contributors[$id][0] !== null) {
        $rows[] = $row($repo, 'contributors_total', $contributors[$id][0], 'contributors', $contributors[$id][1]);
    }
}

Files::writeOnce(DATA_DIR . "/snapshots/{$date}.json", $rows, isset($opts['replace']) && $date === today());
logline(count($repos) . ' repos, ' . count($rows) . " rows written to snapshots/{$date}.json (" . count(array_filter($contributors, fn($c) => $c[0] !== null)) . ' with contributor counts)');
