<?php
declare(strict_types=1);

// Expanded leaderboard row: a short summary of one repo, loaded when the row is opened.
// Returns an HTML fragment; the full picture stays on repo.php.

require __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');

$board = Board::latest();
$launched = count(Snapshots::dates()) >= (int) config('product.launch_days') || getenv('RP_PREVIEW');
$id = isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '';
$item = $board && $launched && preg_match('/^repo-\d+$/', $id) ? Board::find($board, $id) : null;
if (!$item) {
    http_response_code(404);
    exit;
}

$e = [View::class, 'e'];
$m = $item['metrics'];
$verdict = View::verdict($board, $item);
?>
<div class="preview-body">
  <?php if ($verdict): ?><p class="preview-verdict"><?= $e($verdict) ?></p><?php endif; ?>
  <dl class="preview-stats">
    <div><dt>Stars</dt><dd><?= View::compact($m['stars_total'] ?? null) ?><small><?= isset($m['stars_gained_7d']) ? '+' . View::compact($m['stars_gained_7d']) . ' this week' : 'weekly gain from day 8' ?></small></dd></div>
    <div><dt>Forks</dt><dd><?= View::compact($m['forks_total'] ?? null) ?><small><?= isset($m['forks_gained_7d']) ? '+' . View::compact($m['forks_gained_7d']) . ' this week' : '&nbsp;' ?></small></dd></div>
    <div><dt>Last push</dt><dd><?= isset($m['days_since_last_push']) ? $e(View::metricValue('days_since_last_push', $m['days_since_last_push'])) : '–' ?><small><?= isset($m['days_since_last_push']) && $m['days_since_last_push'] >= 1 ? 'ago' : '&nbsp;' ?></small></dd></div>
    <div><dt>Created</dt><dd><?= $item['created_at'] ? $e(View::date($item['created_at'], 'j M')) : '–' ?><small><?= isset($m['age_days']) ? View::num($m['age_days'], 0) . ' days old' : '&nbsp;' ?></small></dd></div>
  </dl>
  <ul class="preview-parts" aria-label="How it compares with other tracked repos">
    <?php foreach ($board['weights'] as $w): $has = isset($item['pct'][$w['metric']]); ?>
    <li class="<?= $has ? '' : 'missing' ?>">
      <span><?= $e($w['label']) ?></span>
      <svg class="bar" viewBox="0 0 100 6" preserveAspectRatio="none" aria-hidden="true"><rect class="bar-bg" width="100" height="6" rx="3"/><?php if ($has): ?><rect class="bar-fill" width="<?= max(1, $item['pct'][$w['metric']]) ?>" height="6" rx="3"/><?php endif; ?></svg>
      <b><?= $has ? 'top ' . (100 - View::beats($item['pct'][$w['metric']])) . '%' : 'soon' ?></b>
    </li>
    <?php endforeach; ?>
  </ul>
  <p class="preview-actions">
    <a class="btn btn-primary" href="repo.php?id=<?= $e($item['id']) ?>">Full details</a>
    <a class="btn" href="<?= $e($item['url']) ?>" rel="noopener">GitHub <?= View::icon('external', 14) ?></a>
  </p>
</div>
