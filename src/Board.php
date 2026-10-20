<?php
declare(strict_types=1);

// Everything the pages need for the latest score date, built once per date and cached in storage/.
// Re-weighting, filtering and search run on this cached board.
final class Board
{
    public const PAGE = 50;
    public const SPARK_DAYS = 28;
    public const MOVERS = 5;
    public const MOVERS_WITHIN = 100;   // movers are drawn from the current top 100

    public static function latest(): ?array
    {
        $dates = Snapshots::dates('scores');
        if (!$dates) {
            return null;
        }
        $date = end($dates);
        $cache = APP_ROOT . '/storage/board-' . md5(DATA_DIR) . "-{$date}.json";
        $source = DATA_DIR . "/scores/{$date}.json";
        if (is_file($cache) && filemtime($cache) >= filemtime($source)) {
            return Files::read($cache);
        }
        $board = self::build($date, $dates);
        Files::write($cache, $board);
        foreach (glob(APP_ROOT . '/storage/board-*.json') as $old) {
            if ($old !== $cache && filemtime($old) < time() - 86400) {
                unlink($old);
            }
        }
        return $board;
    }

    private static function build(string $date, array $dates): array
    {
        $weights = Files::read(APP_ROOT . '/score/weights.json')['metrics'];
        $base = Files::read(DATA_DIR . "/scores/base/{$date}.json");
        $scores = Snapshots::scores($date);
        $meta = Files::read(DATA_DIR . '/state/meta.json', []);
        $snapshot = Snapshots::load($date);
        // Same 7-day gains the score uses, for display.
        foreach (Snapshots::load(Snapshots::shift($date, -7)) as $id => $before) {
            if (isset($snapshot[$id])) {
                $snapshot[$id]['metrics']['stars_gained_7d'] = $snapshot[$id]['metrics']['stars_total'] - $before['metrics']['stars_total'];
                $snapshot[$id]['metrics']['forks_gained_7d'] = $snapshot[$id]['metrics']['forks_total'] - $before['metrics']['forks_total'];
            }
        }

        $history = [];
        $sparkDates = array_slice(array_values(array_filter($dates, fn($d) => $d <= $date)), -self::SPARK_DAYS);
        foreach ($sparkDates as $i => $d) {
            foreach (Snapshots::scores($d) as $id => $s) {
                $history[$id][$i] = $s['score'];
            }
        }

        $items = [];
        $languages = [];
        foreach ($base['rows'] as $r) {
            $id = $r['item_id'];
            $m = $meta[$id] ?? [];
            $s = $scores[$id] ?? [];
            $lang = $m['language'] ?? 'Other';
            $languages[$lang] = ($languages[$lang] ?? 0) + 1;
            $spark = [];
            foreach (array_keys($sparkDates) as $i) {
                $spark[] = $history[$id][$i] ?? null;
            }
            $items[] = [
                'id' => $id,
                'name' => $r['item_name'],
                'url' => $m['url'] ?? 'https://github.com/' . $r['item_name'],
                'description' => $m['description'] ?? '',
                'language' => $lang,
                'license' => $m['license'] ?? null,
                'topics' => $m['topics'] ?? [],
                'created_at' => $m['created_at'] ?? null,
                'score' => $r['score'],
                'rank' => $r['rank'],
                'rank_change' => $s['rank_change'] ?? null,
                'score_change_7d' => $s['score_change_7d'] ?? null,
                'score_change_28d' => $s['score_change_28d'] ?? null,
                'is_new' => $s['is_new'] ?? true,
                'complete' => $r['complete'],
                'pct' => array_map(fn($c) => $c['pct'], $r['contributions']),
                'metrics' => $snapshot[$id]['metrics'] ?? [],
                'spark' => $spark,
            ];
        }
        arsort($languages);

        $movers = array_values(array_filter($items, fn($i) => $i['rank'] <= self::MOVERS_WITHIN && ($i['rank_change'] ?? 0) > 0));
        usort($movers, fn($a, $b) => [$b['rank_change'], $a['rank']] <=> [$a['rank_change'], $b['rank']]);

        $available = [];
        foreach ($items as $i) {
            $available += array_flip(array_keys($i['pct']));
        }

        return [
            'date' => $date,
            'days' => count(Snapshots::dates()),
            'spark_dates' => $sparkDates,
            'weights' => $weights,
            'available' => array_keys($available),
            'held_back' => count($base['held_back']),
            'languages' => $languages,
            'movers' => array_map(fn($m) => $m['id'], array_slice($movers, 0, self::MOVERS)),
            'items' => $items,
        ];
    }

    /**
     * Filters and optionally re-weights the board. $weights maps metric => 0–100 slider value.
     * @return array{total:int, rows:array, custom:bool}
     */
    public static function query(array $board, ?string $lang, string $q, array $weights = [], int $limit = self::PAGE): array
    {
        $items = $board['items'];
        if ($lang !== null && $lang !== '') {
            $items = array_filter($items, fn($i) => $i['language'] === $lang);
        }
        $q = trim($q);
        if ($q !== '') {
            $items = array_filter($items, fn($i) => stripos($i['name'], $q) !== false || stripos($i['description'], $q) !== false);
        }

        $custom = false;
        $defaults = array_column($board['weights'], 'weight', 'metric');
        $use = [];
        foreach ($defaults as $metric => $w) {
            $use[$metric] = isset($weights[$metric]) ? max(0, min(100, (float) $weights[$metric])) / 100 : $w;
            if (abs($use[$metric] - $w) > 0.001) {
                $custom = true;
            }
        }
        if ($custom) {
            foreach ($items as &$i) {
                $total = $sum = 0.0;
                foreach ($use as $metric => $w) {
                    if ($w > 0 && isset($i['pct'][$metric])) {
                        $sum += $w * $i['pct'][$metric];
                        $total += $w;
                    }
                }
                $i['score'] = $total > 0 ? round($sum / $total, 1) : 0.0;
            }
            unset($i);
            usort($items, fn($a, $b) => [$b['score'], $a['rank']] <=> [$a['score'], $b['rank']]);
        }
        $items = array_values($items);
        foreach ($items as $n => &$i) {
            $i['position'] = $n + 1;
        }
        unset($i);
        return ['total' => count($items), 'rows' => array_slice($items, 0, $limit), 'custom' => $custom];
    }

    public static function find(array $board, string $id): ?array
    {
        foreach ($board['items'] as $i) {
            if ($i['id'] === $id) {
                return $i;
            }
        }
        return null;
    }
}
