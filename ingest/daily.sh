#!/bin/sh
# Daily pipeline: search, contributor counts, snapshot, display details, score, momentum. Stops at the first failure.
#   PHP=/usr/local/bin/php sh ingest/daily.sh
set -e
cd "$(dirname "$0")/.."
PHP=${PHP:-php}
"$PHP" ingest/fetch.php
"$PHP" ingest/contributors.php
"$PHP" ingest/snapshot.php
"$PHP" ingest/meta.php
"$PHP" score/score.php
"$PHP" momentum/momentum.php
