<?php
// Which repos are in the Rising Repos universe. No secrets here.

return [
    'min_stars' => 100,       // a repo needs this many stars to be included
    'include_days' => 90,     // ...and must have been created within this many days
    'track_days' => 180,      // once included, it stays tracked until this age
    'raw_keep_days' => 90,    // raw API responses older than this are pruned
    'search_cap' => 1000,     // GitHub search returns at most this many results per query
    // Held back from ranking as a possible fake-star pattern: many stars, almost no forks.
    'suspect_min_stars' => 300,
    'suspect_max_forks' => 2,
    'launch_days' => 1,        // the holding page shows until this many daily snapshots exist
    'preview_days' => 7,       // "Preview" pill until a full week (and the 7-day gains) exists
];
