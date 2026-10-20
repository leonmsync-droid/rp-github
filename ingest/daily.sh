#!/bin/sh
# Daily ingest: search, contributor counts, then the dated snapshot. Stops at the first failure.
#   PHP=/usr/local/bin/php sh ingest/daily.sh
set -e
cd "$(dirname "$0")/.."
PHP=${PHP:-php}
"$PHP" ingest/fetch.php
"$PHP" ingest/contributors.php
"$PHP" ingest/snapshot.php
