<?php
declare(strict_types=1);

// Daily: finds rising repos with GitHub search and saves the untouched responses to data/raw/YYYY-MM-DD/.
//   php ingest/fetch.php           fetch today's raw data
//   php ingest/fetch.php --count   only count repos per creation window (no files written)

require __DIR__ . '/../src/bootstrap.php';

$countOnly = in_array('--count', $argv, true);
$p = config('product');
$gh = new GitHub((string) config('github.token'), (string) config('user_agent'));
$date = today();
$rawDir = DATA_DIR . "/raw/{$date}";
$statePath = DATA_DIR . '/state/tracked.json';

logline('GitHub ' . ($gh->authenticated() ? 'authenticated' : 'unauthenticated (slow)'));

$query = fn(string $from, string $to): string => "created:{$from}..{$to} stars:>={$p['min_stars']} fork:false archived:false";

// Splits the creation range until each window fits under GitHub's 1,000-result search cap.
$windows = [];
$split = function (string $from, string $to) use (&$split, &$windows, $gh, $query, $p): void {
    $total = (int) $gh->search($query($from, $to), 1, 1)['body']['total_count'];
    if ($total > $p['search_cap'] && $from !== $to) {
        $days = (int) ((strtotime($to) - strtotime($from)) / 86400);
        $mid = gmdate('Y-m-d', strtotime($from) + intdiv($days, 2) * 86400);
        $split($from, $mid);
        $split(gmdate('Y-m-d', strtotime($mid) + 86400), $to);
        return;
    }
    if ($total > $p['search_cap']) {
        logline("Warning: {$from} alone has {$total} repos; only the top {$p['search_cap']} by stars are reachable");
    }
    $windows[] = ['from' => $from, 'to' => $to, 'total' => $total];
};
$split(gmdate('Y-m-d', time() - $p['include_days'] * 86400), $date);

$total = array_sum(array_column($windows, 'total'));
logline(count($windows) . " creation windows, {$total} repos with {$p['min_stars']}+ stars created in the last {$p['include_days']} days");
if ($countOnly) {
    foreach ($windows as $w) {
        echo "{$w['from']} to {$w['to']}: {$w['total']}\n";
    }
    echo "Total: {$total}\n";
    exit(0);
}

$manifest = ['date' => $date, 'files' => []];
$save = function (string $name, string $raw) use ($rawDir, &$manifest): void {
    if (!is_dir($rawDir)) {
        mkdir($rawDir, 0775, true);
    }
    // Gzipped but otherwise untouched; Files::read opens .gz transparently.
    $name .= '.gz';
    file_put_contents("{$rawDir}/{$name}", gzencode($raw, 6));
    $manifest['files'][] = ['file' => $name, 'fetched_at' => gmdate('Y-m-d\TH:i:s\Z')];
};

// 1. Search: every repo in the inclusion window.
$seen = [];
foreach ($windows as $w) {
    $pages = min(intdiv($p['search_cap'], 100), (int) ceil($w['total'] / 100));
    for ($page = 1; $page <= $pages; $page++) {
        $res = $gh->search($query($w['from'], $w['to']), $page);
        if (!empty($res['body']['incomplete_results'])) {
            logline("Warning: GitHub returned incomplete results for {$w['from']}..{$w['to']} page {$page}");
        }
        $save("search_{$w['from']}_{$w['to']}_p{$page}.json", $res['raw']);
        foreach ($res['body']['items'] ?? [] as $repo) {
            $seen[$repo['id']] = ['full_name' => $repo['full_name'], 'created_at' => $repo['created_at']];
        }
        unset($res);
    }
}
logline(count($seen) . ' repos found by search');

// 2. Tracked list: included repos stay tracked until track_days old, even after leaving the search window.
$tracked = Files::read($statePath, []);
foreach ($seen as $id => $repo) {
    $tracked[$id] ??= ['first_seen' => $date];
    $tracked[$id]['full_name'] = $repo['full_name'];
    $tracked[$id]['created_at'] = $repo['created_at'];
}
$cutoff = time() - $p['track_days'] * 86400;
$refreshed = $dropped = 0;
foreach ($tracked as $id => $t) {
    if (strtotime($t['created_at']) < $cutoff) {
        unset($tracked[$id]);
        continue;
    }
    if (isset($seen[$id])) {
        continue;
    }
    try {
        $res = $gh->get("/repositories/{$id}");
        $save("repo_{$id}.json", $res['raw']);
        $tracked[$id]['full_name'] = $res['body']['full_name'];
        $refreshed++;
    } catch (RuntimeException $e) {
        if (str_contains($e->getMessage(), 'GitHub 404') || str_contains($e->getMessage(), 'GitHub 451')) {
            unset($tracked[$id]);
            $dropped++;
            continue;
        }
        throw $e;
    }
}
Files::write($statePath, $tracked);
Files::write("{$rawDir}/manifest.json", $manifest);
logline("Tracked: " . count($tracked) . " ({$refreshed} refreshed individually, {$dropped} gone)");

// 3. Prune raw days past the retention limit.
foreach (glob(DATA_DIR . '/raw/*', GLOB_ONLYDIR) as $dir) {
    if (strtotime(basename($dir)) < time() - $p['raw_keep_days'] * 86400) {
        array_map('unlink', glob("{$dir}/*"));
        rmdir($dir);
    }
}
