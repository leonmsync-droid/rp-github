# rp-github: Rising Repos

Part of RankPortfolio. Ranks new public GitHub repositories (created in the last 90 days, 100+ stars) by how fast they're gaining traction. Free and public. Follows [`RP_CONVENTIONS.md`](RP_CONVENTIONS.md).

Status: collecting data. The leaderboard switches on once 7 daily snapshots exist; until then the site shows a holding page.

## Pipeline

| Stage | Files | What it does |
|---|---|---|
| Ingest | `ingest/fetch.php`, `ingest/contributors.php` | GitHub search (split by creation date to stay under the 1,000-result cap), plus one small call per repo for its contributor count. Raw responses saved gzipped in `data/raw/YYYY-MM-DD/`, kept 90 days |
| Snapshot | `ingest/snapshot.php` | Raw → standard long-format rows in `data/snapshots/YYYY-MM-DD.json`; past snapshots are never edited |
| Details | `ingest/meta.php` | Description, link, licence and topics per repo, kept apart from the numbers |
| Score | `score/score.php`, `score/weights.json` | Percentile per metric, weighted into a 0–100 score; possible fake stars held back |
| Momentum | `momentum/momentum.php` | Rank change, 7- and 28-day score change, New tags → `data/scores/YYYY-MM-DD.json` |
| Display | `display/` | Leaderboard, repo pages and method (the web root) |
| Check | `scripts/report_universe.php` | What's in a day's snapshot |

`scripts/demo_history.php` builds invented history in `data-demo/` for local design work only (`RP_DATA_DIR=$PWD/data-demo RP_PREVIEW=1 php -S 127.0.0.1:8082 -t display`). It is git-ignored and never deployed.

Repos stay tracked until they're 180 days old. Settings are in `config/product.php`.

## Setup

1. `cp config.example.php config.php` and add a GitHub fine-grained token (public repositories, read-only, no extra permissions). `config.php` is git-ignored and sits outside the web root.
2. Run once: `sh ingest/daily.sh` (about 1.5 hours, mostly contributor counts at 5,000 API calls/hour).
3. Daily cron (UTC): `0 6 * * * PHP=/usr/local/bin/php /bin/sh /home/USER/rp-github/ingest/daily.sh >> /home/USER/rp-github/data/state/daily.log 2>&1`

## Data and licence

Data from the [GitHub REST API](https://docs.github.com/rest), used under GitHub's Terms of Service and Acceptable Use Policies. Repos are ranked; individual people are not, and no profile data is published.
