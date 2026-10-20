<?php
// Copy to config.php (git-ignored, outside the web root) and fill in.

return [
    'github' => [
        // Fine-grained personal access token: public repositories, read-only, no extra permissions.
        // Leave empty to run unauthenticated (60 requests/hour, 10 searches/minute).
        'token' => '',
    ],
    // GitHub asks for a User-Agent that identifies the project.
    'user_agent' => 'rp-github (https://github.com/leonmsync-droid/rp-github)',
];
