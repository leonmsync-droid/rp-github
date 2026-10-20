<?php
// Which repos are in the Rising Repos universe. No secrets here.

return [
    'min_stars' => 100,       // a repo needs this many stars to be included
    'include_days' => 90,     // ...and must have been created within this many days
    'track_days' => 180,      // once included, it stays tracked until this age
    'raw_keep_days' => 90,    // raw API responses older than this are pruned
    'search_cap' => 1000,     // GitHub search returns at most this many results per query
];
