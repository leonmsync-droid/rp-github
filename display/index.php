<?php
declare(strict_types=1);

// Leaderboard: header, filters and weights, leaderboard, biggest movers, method summary.

require __DIR__ . '/../src/bootstrap.php';

$launchDays = (int) config('product.launch_days');
if (count(Snapshots::dates()) < $launchDays && !getenv('RP_PREVIEW')) {
    require __DIR__ . '/holding.php';
    exit;
}

$board = Board::latest();
if (!$board) {
    require __DIR__ . '/holding.php';
    exit;
}

$lang = isset($_GET['lang']) && is_string($_GET['lang']) && isset($board['languages'][$_GET['lang']]) ? $_GET['lang'] : null;
$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr($_GET['q'], 0, 80) : '';
$w = isset($_GET['w']) && is_array($_GET['w']) ? $_GET['w'] : [];
$result = Board::query($board, $lang, $q, $w);
$byId = array_column($board['items'], null, 'id');
$e = [View::class, 'e'];
$days = $board['days'];
$link = fn(?string $l): string => './' . ($l !== null ? '?lang=' . rawurlencode($l) : '');

View::header('Rising Repos', 'home', $q);
?>
  <div class="layout">
    <aside class="side" aria-label="Filters">
      <details class="side-block" open>
        <summary><span>Languages</span></summary>
        <ul class="nav-list">
          <li><a class="<?= $lang === null ? 'active' : '' ?>" href="<?= $link(null) ?>"><?= View::icon('globe') ?><span class="label">All languages</span><span class="count"><?= View::num(count($board['items'])) ?></span></a></li>
          <?php foreach (array_slice($board['languages'], 0, 14, true) as $name => $n): ?>
          <li><a class="<?= $lang === $name ? 'active' : '' ?>" href="<?= $e($link((string) $name)) ?>"><?= View::langDot((string) $name) ?><span class="label"><?= $e((string) $name) ?></span><span class="count"><?= View::num($n) ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </details>

      <details class="side-block" open>
        <summary><span>Weights</span></summary>
        <form class="weights" id="weights" action="./" method="get">
          <?php if ($lang !== null): ?><input type="hidden" name="lang" value="<?= $e($lang) ?>"><?php endif; ?>
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= $e($q) ?>"><?php endif; ?>
          <?php foreach ($board['weights'] as $m):
              $value = isset($w[$m['metric']]) ? (int) $w[$m['metric']] : (int) round($m['weight'] * 100);
              $ready = in_array($m['metric'], $board['available'], true); ?>
          <label class="weight<?= $ready ? '' : ' waiting' ?>">
            <span class="weight-head"><span><?= $e($m['label']) ?></span><output data-for="<?= $e($m['metric']) ?>"><?= $value ?></output></span>
            <input type="range" min="0" max="100" step="5" name="w[<?= $e($m['metric']) ?>]" value="<?= $value ?>" data-default="<?= (int) round($m['weight'] * 100) ?>">
            <?php if (!$ready): ?><small>Not available yet</small><?php endif; ?>
          </label>
          <?php endforeach; ?>
          <div class="weight-actions">
            <button class="btn" type="submit" id="apply">Apply</button>
            <a class="btn btn-quiet" href="<?= $e($link($lang)) ?>" id="reset">Reset</a>
          </div>
          <p class="hint">Re-weight the score live. Arrows and sparklines always use the default weights.</p>
        </form>
      </details>
    </aside>

    <main class="main" id="main">
      <div class="status-row">
        <?php if ($days < $launchDays): ?>
        <span class="pill pill-preview">Preview</span>
        <span class="status-text">Day <?= $days ?> of <?= $launchDays ?>: 7-day gains switch on after a full week</span>
        <?php else: ?>
        <span class="pill pill-live">Live</span>
        <span class="status-text">Updated daily</span>
        <?php endif; ?>
      </div>

      <h1 class="title"><?= View::icon('trend', 28) ?>Rising repos</h1>
      <p class="lede">New open-source projects on GitHub, ranked by how fast they're gaining traction. Public repos created in the last <?= (int) config('product.include_days') ?> days with <?= (int) config('product.min_stars') ?>+ stars. Updated <?= $e(View::date($board['date'])) ?>.</p>

      <section class="box" aria-labelledby="board-title">
        <div class="box-head">
          <h2 id="board-title">Leaderboard</h2>
          <span class="box-sub" id="summary"><?= View::num($result['total']) ?> repos<?= $lang ? ' in ' . $e($lang) : '' ?><?= $q !== '' ? ' matching “' . $e($q) . '”' : '' ?><?= $result['custom'] ? ' · your weights' : '' ?></span>
        </div>
        <ol class="rows" id="rows">
          <?php foreach ($result['rows'] as $item) { echo View::row($item, $result['custom']); } ?>
        </ol>
        <?php if (!$result['rows']): ?><p class="empty">No repos match. Try a different search or language.</p><?php endif; ?>
        <div class="box-foot" id="foot">Showing the top <?= min(Board::PAGE, $result['total']) ?> of <?= View::num($result['total']) ?></div>
      </section>
    </main>

    <aside class="rail" aria-label="Highlights">
      <section class="card">
        <h2 class="card-title">Biggest movers</h2>
        <?php if ($board['movers']): ?>
        <ol class="timeline">
          <?php foreach ($board['movers'] as $id): $m = $byId[$id]; ?>
          <li>
            <span class="when up"><?= View::icon('arrow-up', 12) ?>Up <?= (int) $m['rank_change'] ?> places, now #<?= (int) $m['rank'] ?></span>
            <a href="repo.php?id=<?= $e($id) ?>"><?= View::repoName($m['name']) ?></a>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php else: ?>
        <p class="card-text">Movers appear from the second day of data.</p>
        <?php endif; ?>
      </section>

      <section class="card">
        <h2 class="card-title">How the score works</h2>
        <ul class="weight-list">
          <?php foreach ($board['weights'] as $m): ?>
          <li><span><?= $e($m['label']) ?></span><b><?= (int) round($m['weight'] * 100) ?>%</b></li>
          <?php endforeach; ?>
        </ul>
        <p class="card-text">Each metric is a percentile against every tracked repo, then weighted into a score out of 100. <?= (int) $board['held_back'] ?> repos with many stars but almost no forks are held back as possible fake stars.</p>
        <a class="btn btn-block" href="method.php">Read the method</a>
      </section>
    </aside>
  </div>
<?php View::footer(); ?>
