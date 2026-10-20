<?php
declare(strict_types=1);

// Holding page until a week of snapshots exists. Shows only counts from the latest snapshot.

// Included by index.php until launch_days of data exist.
require_once __DIR__ . '/../src/bootstrap.php';

$latest = glob(DATA_DIR . '/snapshots/*.json') ?: [];
sort($latest);
$first = $latest ? basename($latest[0], '.json') : null;
$repos = 0;
if ($latest) {
    $ids = [];
    foreach (Files::read(end($latest), []) as $row) {
        $ids[$row['item_id']] = true;
    }
    $repos = count($ids);
}
$days = count($latest);
$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$minStars = (int) config('product.min_stars');
$includeDays = (int) config('product.include_days');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Rising Repos</title>
  <meta name="description" content="New open-source projects on GitHub, ranked by how fast they're gaining traction.">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <main class="hold">
    <p class="eyebrow">RankPortfolio</p>
    <h1>Rising Repos</h1>
    <p class="lede">New open-source projects on GitHub, ranked by how fast they're gaining traction. For developers and tech teams deciding what's worth watching.</p>

    <div class="status">
      <span class="dot" aria-hidden="true"></span>
      <span>Collecting data. Rankings launch once a full week is in.</span>
    </div>

    <?php if ($repos): ?>
    <dl class="facts">
      <div><dt>Repos tracked</dt><dd><?= number_format($repos) ?></dd></div>
      <div><dt>Days collected</dt><dd><?= $days ?> of 7</dd></div>
      <div><dt>Since</dt><dd><?= $e(gmdate('j M Y', strtotime($first))) ?></dd></div>
    </dl>
    <?php endif; ?>

    <p class="scope">Public repos created in the last <?= $includeDays ?> days with <?= $minStars ?>+ stars. Repos are ranked, never people.</p>
  </main>
  <footer class="foot">
    Data: <a href="https://docs.github.com/rest">GitHub API</a>. Not affiliated with GitHub.
    · <a href="https://github.com/leonmsync-droid/rp-github">Source</a>
  </footer>
</body>
</html>
