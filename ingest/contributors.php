<?php
declare(strict_types=1);

// Daily, after fetch.php: one small call per tracked repo to count its contributors.
// Asks for one contributor per page, so the "last page" number in the Link header is the count.
// Resumable: repos already saved today are skipped.
//   php ingest/contributors.php

require __DIR__ . '/../src/bootstrap.php';

$gh = new GitHub((string) config('github.token'), (string) config('user_agent'));
$date = today();
$rawDir = DATA_DIR . "/raw/{$date}";
$manifestPath = "{$rawDir}/manifest_contributors.json";
$tracked = Files::read(DATA_DIR . '/state/tracked.json', []);
if (!$tracked || !is_file("{$rawDir}/manifest.json")) {
    logline('Run ingest/fetch.php first');
    exit(1);
}

$manifest = Files::read($manifestPath, ['date' => $date, 'files' => []]);
$done = array_flip(array_column($manifest['files'], 'file'));
$todo = array_filter(array_keys($tracked), fn($id) => !isset($done["contributors_{$id}.json"]));
logline(count($todo) . ' of ' . count($tracked) . ' repos still to count');

$n = 0;
foreach ($todo as $id) {
    $name = "contributors_{$id}.json";
    try {
        $res = $gh->get("/repos/{$tracked[$id]['full_name']}/contributors", ['per_page' => 1, 'anon' => 'false']);
        $saved = ['link' => $res['headers']['link'] ?? null, 'status' => $res['status'], 'body' => $res['body']];
    } catch (RuntimeException $e) {
        // 404: renamed or deleted since the search; 403: history too large to list. Recorded as unknown.
        if (!preg_match('/GitHub (403|404|451)/', $e->getMessage(), $m)) {
            throw $e;
        }
        $saved = ['link' => null, 'status' => (int) $m[1], 'body' => null];
    }
    file_put_contents("{$rawDir}/{$name}", json_encode($saved, JSON_UNESCAPED_SLASHES));
    $manifest['files'][] = ['file' => $name, 'fetched_at' => gmdate('Y-m-d\TH:i:s\Z')];
    if (++$n % 200 === 0) {
        Files::write($manifestPath, $manifest);
        logline("{$n} counted");
    }
}
Files::write($manifestPath, $manifest);
logline("Done: {$n} counted");
