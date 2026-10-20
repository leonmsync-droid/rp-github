<?php
declare(strict_types=1);

// Small HTML helpers shared by the pages. Everything printed goes through e().
final class View
{
    // Language colours from GitHub Linguist (MIT), so dots match what developers expect.
    private const LANG_COLOURS = [
        'Python' => '#3572A5', 'TypeScript' => '#3178c6', 'JavaScript' => '#f1e05a', 'Rust' => '#dea584',
        'C++' => '#f34b7d', 'HTML' => '#e34c26', 'Go' => '#00ADD8', 'Swift' => '#F05138', 'C' => '#555555',
        'Kotlin' => '#A97BFF', 'Shell' => '#89e051', 'Java' => '#b07219', 'C#' => '#178600', 'Ruby' => '#701516',
        'PHP' => '#4F5D95', 'Dart' => '#00B4AB', 'Lua' => '#000080', 'Vue' => '#41b883', 'CSS' => '#663399',
        'Jupyter Notebook' => '#DA5B0B', 'Zig' => '#ec915c', 'Svelte' => '#ff3e00', 'MDX' => '#fcb32c',
        'Objective-C' => '#438eff', 'Scala' => '#c22d40', 'Elixir' => '#6e4a7e', 'Haskell' => '#5e5086',
        'Nix' => '#7e7eff', 'Astro' => '#ff5a03', 'Lean' => '#7a7a7a', 'TeX' => '#3D6117', 'Dockerfile' => '#384d54',
    ];

    public static function e(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    public static function num(float|int|null $n, int $decimals = 0): string
    {
        return $n === null ? '–' : number_format((float) $n, $decimals);
    }

    public static function compact(float|int|null $n): string
    {
        if ($n === null) {
            return '–';
        }
        if (abs($n) >= 1e6) {
            return rtrim(rtrim(number_format($n / 1e6, 1), '0'), '.') . 'M';
        }
        if (abs($n) >= 1e3) {
            return rtrim(rtrim(number_format($n / 1e3, 1), '0'), '.') . 'K';
        }
        return number_format((float) $n, $n == (int) $n ? 0 : 1);
    }

    public static function date(string $iso, string $format = 'j M Y'): string
    {
        return gmdate($format, strtotime($iso));
    }

    public static function langDot(string $lang): string
    {
        $colour = self::LANG_COLOURS[$lang] ?? '#8b949e';
        return '<svg class="lang-dot" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><circle cx="5" cy="5" r="5" fill="' . $colour . '"/></svg>';
    }

    public static function langColours(): array
    {
        return self::LANG_COLOURS;
    }

    // Owner in muted text, repo name bold; repos are the subject, not people.
    public static function repoName(string $full): string
    {
        [$owner, $repo] = array_pad(explode('/', $full, 2), 2, '');
        return '<span class="owner">' . self::e($owner) . '/</span><span class="repo">' . self::e($repo) . '</span>';
    }

    // Arrow and colour together, never colour alone.
    public static function change(?int $change, bool $isNew): string
    {
        if ($isNew) {
            return '<span class="pill pill-new">New</span>';
        }
        if ($change === null || $change === 0) {
            return '<span class="change flat" title="No change since yesterday"><span aria-hidden="true">–</span><span class="sr">No change</span></span>';
        }
        $up = $change > 0;
        $label = ($up ? 'Up ' : 'Down ') . abs($change) . ' since yesterday';
        return '<span class="change ' . ($up ? 'up' : 'down') . '" title="' . $label . '">'
            . self::icon($up ? 'arrow-up' : 'arrow-down', 12) . '<span aria-hidden="true">' . abs($change) . '</span><span class="sr">' . $label . '</span></span>';
    }

    public static function sparkline(array $values, int $w = 88, int $h = 24): string
    {
        $points = array_values(array_filter($values, fn($v) => $v !== null));
        if (count($points) < 2) {
            return '<span class="spark-empty" title="History builds up daily">–</span>';
        }
        $min = min($points);
        $max = max($points);
        $range = max(1, $max - $min);
        $step = $w / (count($values) - 1);
        $path = '';
        foreach ($values as $i => $v) {
            if ($v === null) {
                continue;
            }
            $x = round($i * $step, 1);
            $y = round($h - 2 - ($v - $min) / $range * ($h - 4), 1);
            $path .= ($path === '' ? 'M' : 'L') . "{$x} {$y}";
        }
        $rising = end($points) >= $points[0];
        return '<svg class="spark ' . ($rising ? 'up' : 'down') . '" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><path d="' . $path . '"/></svg>';
    }

    // Octicons-style line icons, drawn here (no GitHub marks).
    public static function icon(string $name, int $size = 16): string
    {
        $paths = [
            'search' => '<circle cx="7" cy="7" r="4.75"/><path d="M10.5 10.5 14 14"/>',
            'arrow-up' => '<path d="M8 13V3M3.5 7.5 8 3l4.5 4.5"/>',
            'arrow-down' => '<path d="M8 3v10M3.5 8.5 8 13l4.5-4.5"/>',
            'trend' => '<path d="M1.5 11.5 6 7l3 3 5.5-5.5"/><path d="M10.5 4.5h4v4"/>',
            'book' => '<path d="M2 3.5C2 2.7 2.7 2 3.5 2H8v11H3.5A1.5 1.5 0 0 0 2 14.5Z"/><path d="M14 3.5c0-.8-.7-1.5-1.5-1.5H8v11h4.5c.8 0 1.5.7 1.5 1.5Z"/>',
            'code' => '<path d="m5.5 4.5-3.5 3.5 3.5 3.5M10.5 4.5 14 8l-3.5 3.5"/>',
            'download' => '<path d="M8 2v8M4.5 6.5 8 10l3.5-3.5M2.5 13.5h11"/>',
            'sun' => '<circle cx="8" cy="8" r="3"/><path d="M8 1v1.5M8 13.5V15M1 8h1.5M13.5 8H15M3 3l1 1M12 12l1 1M3 13l1-1M12 4l1-1"/>',
            'globe' => '<circle cx="8" cy="8" r="6.25"/><path d="M1.75 8h12.5M8 1.75c1.8 1.7 2.7 3.8 2.7 6.25S9.8 12.55 8 14.25C6.2 12.55 5.3 10.45 5.3 8S6.2 3.45 8 1.75Z"/>',
            'sliders' => '<path d="M2 4h7M12 4h2M2 12h2M7 12h7"/><circle cx="10.5" cy="4" r="1.5"/><circle cx="5.5" cy="12" r="1.5"/>',
            'shield' => '<path d="M8 1.75 13.25 3.5v4c0 3.2-2.2 5.6-5.25 6.75C4.95 13.1 2.75 10.7 2.75 7.5v-4Z"/>',
            'external' => '<path d="M9.5 2.5h4v4M13.5 2.5 7.5 8.5M12 9.5v3.25c0 .4-.35.75-.75.75h-8c-.4 0-.75-.35-.75-.75v-8c0-.4.35-.75.75-.75H6.5"/>',
            'star' => '<path d="m8 1.75 1.9 3.9 4.3.6-3.1 3 .75 4.25L8 11.5l-3.85 2 .75-4.25-3.1-3 4.3-.6Z"/>',
            'fork' => '<circle cx="4.5" cy="3" r="1.5"/><circle cx="11.5" cy="3" r="1.5"/><circle cx="8" cy="13" r="1.5"/><path d="M4.5 4.5v1c0 1.1.9 2 2 2h3c1.1 0 2-.9 2-2v-1M8 7.5v4"/>',
        ];
        return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }

    // One leaderboard row; also returned by api/board.php so live updates match the first render.
    public static function row(array $i, bool $custom): string
    {
        $pos = $i['position'] ?? $i['rank'];
        $desc = $i['description'] !== '' ? '<p class="desc">' . self::e($i['description']) . '</p>' : '';
        return '<li class="row">'
            . '<span class="pos">' . $pos . '</span>'
            . '<div class="what">'
            . '<a class="name" href="repo.php?id=' . self::e($i['id']) . '">' . self::repoName($i['name']) . '</a>'
            . $desc
            . '<p class="meta">' . self::langDot($i['language']) . self::e($i['language'])
            . '<span>' . self::icon('star', 12) . self::compact($i['metrics']['stars_total'] ?? null) . '</span>'
            . '<span>' . self::compact($i['metrics']['stars_per_day_since_creation'] ?? null) . ' stars/day</span>'
            . ($i['complete'] ? '' : '<span class="muted-note" title="Some metrics need more days of data">partial</span>')
            . '</p></div>'
            . '<div class="nums">'
            . '<span class="score" title="Score out of 100' . ($custom ? ', with your weights' : '') . '">' . self::num($i['score'], 1) . '</span>'
            . self::change($i['rank_change'], $i['is_new'])
            . self::sparkline($i['spark'])
            . '</div></li>';
    }

    public static function header(string $title, string $active = 'home', string $q = ''): void
    {
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= self::e($title) ?></title>
  <meta name="description" content="New open-source projects on GitHub, ranked by how fast they're gaining traction.">
  <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="assets/style.css">
  <script src="assets/app.js" defer></script>
</head>
<body>
  <header class="topbar">
    <a class="brand" href="./" aria-label="Rising Repos home">
      <img src="assets/favicon.svg" width="32" height="32" alt="">
      <span>Rising Repos</span>
    </a>
    <form class="search" action="./" method="get" role="search">
      <?= self::icon('search') ?>
      <input type="search" name="q" value="<?= self::e($q) ?>" placeholder="Type / to search repos" aria-label="Search repos" id="search" autocomplete="off">
    </form>
    <nav class="top-actions" aria-label="Site">
      <a class="icon-btn<?= $active === 'method' ? ' active' : '' ?>" href="method.php" title="How the score works"><?= self::icon('book') ?><span class="sr">Method</span></a>
      <a class="icon-btn" href="https://github.com/leonmsync-droid/rp-github" title="Source code"><?= self::icon('code') ?><span class="sr">Source code</span></a>
      <button class="icon-btn" type="button" id="theme" title="Switch light or dark"><?= self::icon('sun') ?><span class="sr">Switch theme</span></button>
    </nav>
  </header>
<?php
    }

    public static function footer(): void
    {
        ?>
  <footer class="foot">
    <span>Data: <a href="https://docs.github.com/rest">GitHub REST API</a>. Not affiliated with or endorsed by GitHub.</span>
    <span>Repos are ranked, never people. Part of RankPortfolio · <a href="method.php">Method</a> · <a href="https://github.com/leonmsync-droid/rp-github">Source</a></span>
  </footer>
</body>
</html>
<?php
    }
}
