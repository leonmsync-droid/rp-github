<?php
declare(strict_types=1);

// Method: the formula, current weights, inclusion rules, limits, sources and licences.

require __DIR__ . '/../src/bootstrap.php';

$p = config('product');
$weights = Files::read(APP_ROOT . '/score/weights.json')['metrics'];
$e = [View::class, 'e'];

View::header('Method · Rising Repos', 'method');
?>
  <main class="narrow prose">
    <h1 class="title">How the score works</h1>
    <p class="lede">Rising Repos ranks new public GitHub projects by how fast they're gaining traction, so developers and tech teams can see what's worth watching.</p>

    <h2>Which repos are included</h2>
    <ul>
      <li>Public repos created in the last <?= (int) $p['include_days'] ?> days with at least <?= (int) $p['min_stars'] ?> stars. Forks and archived repos are left out.</li>
      <li>Once included, a repo stays tracked until it's <?= (int) $p['track_days'] ?> days old.</li>
      <li>Repos with <?= (int) $p['suspect_min_stars'] ?>+ stars but <?= (int) $p['suspect_max_forks'] ?> or fewer forks are held back. That pattern often means bought or automated stars.</li>
      <li>Data is collected once a day, at 06:00 UTC.</li>
    </ul>

    <h2>The score</h2>
    <p>Each metric is turned into a <strong>percentile</strong>: the share of tracked repos this one beats, from 0 to 100. Percentiles stop one huge repo from squashing everyone else. The score is the weighted sum:</p>
    <p class="formula">Score = Σ weight × percentile, with the weights adding up to 1</p>
    <div class="box">
      <table class="table">
        <thead><tr><th>Metric</th><th>Weight</th><th>Better when</th></tr></thead>
        <tbody>
          <?php foreach ($weights as $m): ?>
          <tr><td><?= $e($m['label']) ?></td><td><?= (int) round($m['weight'] * 100) ?>%</td><td><?= $m['direction'] === 'lower_better' ? 'Lower' : 'Higher' ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p>If a repo is missing a metric (the 7-day gains need a week of data, for example), that metric is left out and the other weights are scaled up to still add up to 1. These repos are marked “partial”. Ties go to the repo stronger on the highest-weighted metric, then alphabetically.</p>
    <p>The weight sliders on the leaderboard re-rank everything with your own weights. Arrows and sparklines always use the defaults above.</p>

    <h2>Change over time</h2>
    <ul>
      <li><strong>Rank change:</strong> yesterday's rank minus today's, so a positive number means rising.</li>
      <li><strong>Score change:</strong> points gained or lost over 7 and 28 days. Points, not percentages, so small scores don't swing wildly.</li>
      <li><strong>New:</strong> a repo with fewer than 2 days of data.</li>
      <li><strong>Biggest movers:</strong> the 5 biggest daily rank gains within the current top <?= Board::MOVERS_WITHIN ?>. Only risers are shown.</li>
    </ul>

    <h2>Limits</h2>
    <ul>
      <li>Stars measure attention, not quality or safety. Check a project yourself before using it.</li>
      <li>Stars can be bought or automated. The fork check catches the obvious cases, not all of them.</li>
      <li>GitHub search returns at most 1,000 results per query, so searches are split by creation date. A day with more than 1,000 qualifying repos would be cut short; none has come close so far.</li>
      <li>Contributor counts are all-time. For repos under 180 days old that's close to recent activity.</li>
    </ul>

    <h2>Sources and licence</h2>
    <ul>
      <li>Data from the <a href="https://docs.github.com/rest">GitHub REST API</a>, used under GitHub's Terms of Service and Acceptable Use Policies. Not affiliated with or endorsed by GitHub.</li>
      <li>Repos are ranked, never people. Owner names appear only as part of the repo's own name, and no profile data is collected or shown.</li>
      <li>Language colours from <a href="https://github.com/github-linguist/linguist">Linguist</a> (MIT).</li>
      <li>Source code: <a href="https://github.com/leonmsync-droid/rp-github">github.com/leonmsync-droid/rp-github</a>.</li>
    </ul>
  </main>
<?php View::footer(); ?>
