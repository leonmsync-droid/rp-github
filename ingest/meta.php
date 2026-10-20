<?php
declare(strict_types=1);

// Display details per repo (description, link, licence, topics) from a day's raw search results.
// Kept apart from the numeric snapshot. No owner details beyond the repo's own name.
//   php ingest/meta.php [--date=YYYY-MM-DD]

require __DIR__ . '/../src/bootstrap.php';

$opts = getopt('', ['date:']);
$date = $opts['date'] ?? today();
$rawDir = DATA_DIR . "/raw/{$date}";
$files = Files::read("{$rawDir}/manifest.json", ['files' => []])['files'];

$meta = [];
foreach ($files as $f) {
    $data = Files::read("{$rawDir}/{$f['file']}");
    $repos = str_starts_with($f['file'], 'search_') ? ($data['items'] ?? []) : ($data ? [$data] : []);
    foreach ($repos as $r) {
        $meta['repo-' . $r['id']] = [
            'name' => $r['full_name'],
            'url' => $r['html_url'],
            'description' => mb_strimwidth(trim((string) $r['description']), 0, 220, '…'),
            'language' => $r['language'] ?? null,
            'license' => $r['license']['spdx_id'] ?? null,
            'topics' => array_slice($r['topics'] ?? [], 0, 6),
            'created_at' => $r['created_at'],
            'stars' => $r['stargazers_count'],
        ];
    }
    unset($data, $repos);
}
Files::write(DATA_DIR . '/state/meta.json', $meta);
logline(count($meta) . ' repos in meta.json');
