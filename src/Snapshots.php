<?php
declare(strict_types=1);

// Reads the dated files each stage writes, pivoted for easy lookups.
final class Snapshots
{
    /** @return string[] dates with a file in data/<kind>/, oldest first */
    public static function dates(string $kind = 'snapshots'): array
    {
        $dates = array_map(fn($f) => basename($f, '.json'), glob(DATA_DIR . "/{$kind}/????-??-??.json") ?: []);
        sort($dates);
        return $dates;
    }

    /** @return array<string, array{name:string, category:string, metrics:array<string, float>}> */
    public static function load(string $date): array
    {
        $items = [];
        foreach (Files::read(DATA_DIR . "/snapshots/{$date}.json", []) as $r) {
            $items[$r['item_id']] ??= ['name' => $r['item_name'], 'category' => $r['category'], 'metrics' => []];
            $items[$r['item_id']]['metrics'][$r['metric']] = (float) $r['value'];
        }
        return $items;
    }

    /** @return array<string, array> score rows keyed by item_id */
    public static function scores(string $date): array
    {
        $rows = Files::read(DATA_DIR . "/scores/{$date}.json", []);
        return array_column($rows, null, 'item_id');
    }

    public static function shift(string $date, int $days): string
    {
        return gmdate('Y-m-d', strtotime($date) + $days * 86400);
    }
}
