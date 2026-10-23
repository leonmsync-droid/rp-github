<?php
declare(strict_types=1);

// Item detail: one repo's score history and what each metric contributes.

require __DIR__ . '/../src/bootstrap.php';

$board = Board::latest();
$id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$item = $board && preg_match('/^repo-\d+$/', $id) ? Board::find($board, $id) : null;
if (!$item || (count(Snapshots::dates()) < (int) config('product.launch_days') && !getenv('RP_PREVIEW'))) {
    http_response_code(404);
    View::header('Not found · Rising Repos');
    echo '<main class="narrow"><h1 class="title">Repo not found</h1><p class="lede">It may be older than 180 days, below 100 stars, or held back. <a href="./">Back to the leaderboard</a></p></main>';
    View::footer();
    exit;
}

$e = [View::class, 'e'];
$metrics = $item['metrics'];
$labels = array_column($board['weights'], 'label', 'metric');
$verdict = View::verdict($board, $item);

// Score history chart (default weights), drawn as SVG.
$hist = $item['spark'];
$dates = $board['spark_dates'];
$W = 640; $H = 180; $pad = 28;
$pts = $tips = [];
foreach ($hist as $i => $v) {
    if ($v !== null) {
        $x = $pad + $i * ($W - 2 * $pad) / max(1, count($hist) - 1);
        $y = $H - $pad - $v / 100 * ($H - 2 * $pad);
        $pts[] = [round($x, 1), round($y, 1)];
        $tips[] = [round($x, 1), round($y, 1), View::date($dates[$i], 'j M'), round($v, 1)]; // for the hover box
    }
}
$line = $pts ? 'M' . implode('L', array_map(fn($p) => "{$p[0]} {$p[1]}", $pts)) : '';
$area = $pts ? $line . "L{$pts[count($pts) - 1][0]} " . ($H - $pad) . "L{$pts[0][0]} " . ($H - $pad) . 'Z' : '';

View::header($item['name'] . ' · Rising Repos');
?>
  <main class="detail">
    <nav class="crumbs" aria-label="Breadcrumb"><a href="./">Leaderboard</a><span aria-hidden="true">/</span><span><?= $e($item['name']) ?></span></nav>

    <header class="detail-head">
      <div>
        <h1 class="title repo-title"><?= View::repoName($item['name']) ?></h1>
        <?php if ($item['description'] !== ''): ?><p class="lede"><?= $e($item['description']) ?></p><?php endif; ?>
        <p class="chips">
          <span class="chip"><?= View::langDot($item['language']) ?><?= $e($item['language']) ?></span>
          <?php if ($item['license']): ?><span class="chip"><?= $e($item['license']) ?></span><?php endif; ?>
          <?php if ($item['created_at']): ?><span class="chip">Created <?= $e(View::date($item['created_at'])) ?></span><?php endif; ?>
          <?php foreach ($item['topics'] as $t): ?><span class="chip chip-topic"><?= $e($t) ?></span><?php endforeach; ?>
        </p>
      </div>
      <a class="btn" href="<?= $e($item['url']) ?>" rel="noopener">View on GitHub <?= View::icon('external', 14) ?></a>
    </header>

    <?php if ($verdict): ?><p class="verdict"><?= $e($verdict) ?></p><?php endif; ?>

    <div class="stats">
      <div class="stat"><span>Score</span><b><?= View::num($item['score'], 1) ?></b><small>out of 100</small></div>
      <div class="stat"><span>Rank</span><b>#<?= (int) $item['rank'] ?></b><small><?= View::change($item['rank_change'], $item['is_new']) ?></small></div>
      <div class="stat"><span>Stars</span><b><?= View::compact($metrics['stars_total'] ?? null) ?></b><small><?= isset($metrics['stars_gained_7d']) ? '+' . View::compact($metrics['stars_gained_7d']) . ' this week' : 'Weekly gain from day 8' ?></small></div>
      <div class="stat"><span>Score change</span><b><?= $item['score_change_7d'] === null ? '–' : sprintf('%+.1f', $item['score_change_7d']) ?></b><small>points, 7 days</small></div>
    </div>

    <div class="detail-grid">
      <section class="box">
        <div class="box-head"><h2>Score history</h2><span class="box-sub">Last <?= count($dates) ?> days · default weights</span></div>
        <div class="chart">
          <?php if (count($pts) >= 2): ?>
          <svg id="history" data-points="<?= $e(json_encode($tips)) ?>" data-w="<?= $W ?>" data-h="<?= $H ?>" data-top="<?= $pad ?>" data-bottom="<?= $H - $pad ?>" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Score over the last <?= count($dates) ?> days, from <?= View::num($pts ? $hist[array_key_first(array_filter($hist, fn($v) => $v !== null))] : 0, 1) ?> to <?= View::num($item['score'], 1) ?>">
            <?php foreach ([0, 50, 100] as $g): $gy = $H - $pad - $g / 100 * ($H - 2 * $pad); ?>
            <line class="grid" x1="<?= $pad ?>" x2="<?= $W - $pad ?>" y1="<?= $gy ?>" y2="<?= $gy ?>"/>
            <text class="axis" x="<?= $pad - 6 ?>" y="<?= $gy + 4 ?>" text-anchor="end"><?= $g ?></text>
            <?php endforeach; ?>
            <path class="area" d="<?= $area ?>"/>
            <path class="line" d="<?= $line ?>"/>
            <circle class="dot" cx="<?= $pts[count($pts) - 1][0] ?>" cy="<?= $pts[count($pts) - 1][1] ?>" r="4"/>
            <line class="guide" x1="0" x2="0" y1="<?= $pad ?>" y2="<?= $H - $pad ?>"/>
            <circle class="dot hover-dot" cx="0" cy="0" r="4"/>
            <text class="axis" x="<?= $pad ?>" y="<?= $H - 6 ?>"><?= $e(View::date($dates[0], 'j M')) ?></text>
            <text class="axis" x="<?= $W - $pad ?>" y="<?= $H - 6 ?>" text-anchor="end"><?= $e(View::date(end($dates), 'j M')) ?></text>
          </svg>
          <div class="chart-tip" id="history-tip" aria-hidden="true"></div>
          <?php else: ?>
          <p class="empty">History builds up from the second day of data.</p>
          <?php endif; ?>
        </div>
      </section>

      <section class="box">
        <div class="box-head"><h2>What makes up the score</h2></div>
        <ul class="parts">
          <?php foreach ($board['weights'] as $m):
              $metric = $m['metric'];
              $has = isset($item['pct'][$metric]); ?>
          <li class="<?= $has ? '' : 'missing' ?>">
            <div class="part-head">
              <span><?= $e($labels[$metric]) ?></span>
              <span class="part-value"><?= isset($metrics[$metric]) ? $e(View::metricValue($metric, $metrics[$metric])) : 'Not yet available' ?></span>
            </div>
            <svg class="bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect class="bar-bg" width="100" height="6" rx="3"/><?php if ($has): ?><rect class="bar-fill" width="<?= max(1, $item['pct'][$metric]) ?>" height="6" rx="3"/><?php endif; ?></svg>
            <small><?= $has ? 'Better than ' . View::beats($item['pct'][$metric]) . '% of tracked repos · weight ' . (int) round($m['weight'] * 100) . '%' : 'Weight ' . (int) round($m['weight'] * 100) . '% · shared across the other metrics for now' ?></small>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>
    </div>
  </main>
<?php View::footer(); ?>
