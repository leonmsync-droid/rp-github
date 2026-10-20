# RankPortfolio Build Conventions

Oct 20, 2026 · @Leon

## Purpose

Every RankPortfolio product is built standalone but follows the same structure, data shape, scoring rules and page layout, so the shared RP Engine can be extracted at the end with minimal refactoring.

- Give this brief to Claude Code at the start of every build (save it in each repo as `RP_CONVENTIONS.md`; prompts are in the last section).
- When a build has to break a convention, record why in that build's log.
- CS2 already exists, so it is the reference build: audit it against this brief first, and where CS2's approach is better, update the brief rather than the code.

## Products and build order

Five products, built in this order; CS2 is already built and sets the pattern for the other three.

| # | Product | Repo | Audience | Data source | Refresh |
| --- | --- | --- | --- | --- | --- |
| 1 | CS2 Tier-2 Sponsor Rankings (built) | `rp-cs2` | Sponsors and brands | Existing CS2 sources | Weekly |
| 2 | GitHub Rankings (Rising Repos, then Health Score) | `rp-github` | Developers and tech teams | GitHub API | Daily |
| 3 | Clean Power Momentum | `rp-cleanpower` | Global public, energy sector | Ember | Twice monthly |
| 4 | Attention Index | `rp-attention` | Everyone; also feeds 1 to 3 | Wikimedia Pageviews API | Daily |
| 5 | Chess Opening Meta Index | `rp-chess` | Chess players and coaches | Lichess open database | Monthly |

Final project: extract the RP Engine from what products 1 to 4 share and rebuild them on it, then launch product 5 (Chess Opening Meta Index) on the engine in a day to prove it scales.

## Folder structure

Every repo uses the same top-level folders, so each stage of the pipeline can later be lifted into the engine as a module.

```
rp-<product>/
  ingest/        # fetch from the API on a schedule
  data/
    raw/         # untouched API responses, one file per fetch, dated
    snapshots/   # normalised rows (see Data shape), one file per date
    scores/      # scored and ranked output, one file per date
  score/
    weights.json # metrics, weights and direction
    score.*      # scoring formula only, no UI code
  momentum/      # change-over-time and movers calculations
  display/       # pages and components
  export/        # open dataset (CSV and JSON) + weekly summary text
  docs/
    METHOD.md    # how the score works, in plain words
    LOG.md       # after-build log
  RP_CONVENTIONS.md
  README.md
```

- Each folder only talks to the next one through files in `data/`; no stage reaches into another stage's code.
- Product-specific logic lives in `ingest/` and `score/weights.json`; everything else should look almost identical across repos.

## Data shape

All snapshots use one long format: one row per item, per metric, per date. This single shape is what lets one engine rank teams, repos, countries and pages.

**Snapshot row** (`data/snapshots/YYYY-MM-DD.json`)

| Field | Type | Example | Notes |
| --- | --- | --- | --- |
| `item_id` | string | `team-liquid-academy` | Stable slug; never changes once issued |
| `item_name` | string | Liquid Academy | Display name |
| `category` | string | Tier-2 | Optional grouping for filters |
| `metric` | string | `peak_viewers` | snake\_case metric key |
| `value` | number | 48200 | Raw value, not normalised |
| `unit` | string | viewers | Shown in tooltips and the method section |
| `date` | string | 2026-10-20 | ISO date the value applies to (UTC) |
| `source` | string | twitch | Matches a row in the licence table |
| `fetched_at` | string | 2026-10-20T06:00:00Z | ISO timestamp of the fetch |

**Score row** (`data/scores/YYYY-MM-DD.json`)

| Field | Type | Example | Notes |
| --- | --- | --- | --- |
| `item_id` | string | `team-liquid-academy` | Same slug as the snapshot |
| `date` | string | 2026-10-20 | Score date |
| `score` | number | 72.4 | 0 to 100 |
| `rank` | integer | 3 | 1 = top |
| `rank_change` | integer | 2 | Previous rank minus current rank; positive = rising |
| `score_change_7d` | number | 5.1 | Points change over 7 days |
| `score_change_28d` | number | 11.8 | Points change over 28 days |
| `is_new` | boolean | false | True when fewer than 2 snapshots exist |

- Never overwrite or edit a past snapshot; corrections go in a new dated file.
- Raw API responses stay in `data/raw/` so any snapshot can be rebuilt.

## Scoring and momentum rules

Every score is a weighted sum of percentile-normalised metrics on a 0 to 100 scale, and momentum is always measured in score points over 7 and 28 days.

**Weights** live in `score/weights.json`, never in code:

```json
{
  "metrics": [
    { "metric": "peak_viewers", "weight": 0.5, "direction": "higher_better" },
    { "metric": "matches_won", "weight": 0.3, "direction": "higher_better" },
    { "metric": "avg_cost_per_view", "weight": 0.2, "direction": "lower_better" }
  ]
}
```

**Score formula**, where pct is the item's percentile rank (0 to 100) for that metric on that date, flipped for lower\_better metrics:

```latex
\text{Score}_i = \sum_{m} w_m \cdot \text{pct}_m(i), \qquad \sum_{m} w_m = 1
```

- Use percentile rank, not min-max, so one outlier cannot squash everyone else.
- Weights must sum to 1; the build fails if they don't.
- Momentum = score points change: today's score minus the score 7 days ago, and 28 days ago. Points, not percentages, so small scores don't produce huge swings.
- `rank_change` = previous period's rank minus current rank (positive = rising).
- An item needs at least 2 snapshots before it shows momentum; until then it is tagged New.
- Biggest movers = top 5 by `rank_change`. Show risers by default; show fallers only where it can't embarrass a volunteer or small team.
- Ties are broken by the highest-weighted metric, then alphabetically.

## Page layout and UI conventions

Every product page has the same seven sections in the same order, so the four read as one family.

1. **Header**: product name, one line on who it's for and the decision it helps, last-updated date.
2. **Leaderboard**: rank, item, score, rank-change arrow, 28-day sparkline.
3. **Biggest movers**: top 5 risers (fallers only where appropriate).
4. **Filters and weights**: category filter, plus sliders that re-weight the score live in the browser.
5. **Item detail**: click an item to see its score history and each metric's contribution.
6. **Method**: the formula, current weights, data sources and licences, in plain words (from `docs/METHOD.md`).
7. **Footer**: source attribution and a link to download the open dataset.

- Rising and falling use an arrow icon as well as colour (green and red), never colour alone.
- Works at phone width; supports light and dark mode.
- Same component names across repos (`Leaderboard`, `Movers`, `WeightSliders`, `ItemDetail`, `MethodPanel`, `Attribution`).
- Deploy through Claude Code to your own hosting, one subdomain or path per product.

## Data licence and attribution rules

No product goes live until its row below is filled in and the credit appears in its footer and `docs/METHOD.md`.

| Product | Source | Licence / terms | Attribution | Notes |
| --- | --- | --- | --- | --- |
| CS2 | Existing CS2 sources | To confirm per source | To confirm | Twitch viewership history kept as an experiment; check terms before any commercial use |
| GitHub | GitHub API | GitHub Terms of Service and Acceptable Use | Credit GitHub as the data source | Rank repos and organisations, never individual people; respect API rate limits |
| Clean Power | [Ember electricity data](https://ember-energy.org/electricity-data-explorer/) | CC BY 4.0 | Credit Ember | Commercial use allowed with attribution |
| Attention Index | Wikimedia Pageviews API | Wikimedia Terms of Use; content notice CC BY-SA 3.0 | Credit the Wikimedia Foundation | Send a descriptive User-Agent header on every request |
| Chess | [Lichess open database](https://database.lichess.org/) | CC0 (public domain) | Not required; credit Lichess as good practice | Aggregate by opening and rating band only, never profile individual players; leave out junior rankings; monthly files are very large, so start with one month. Do not use Chess.com data (its terms forbid storing data offline) |

- Any new source gets its own row before it is used.
- If a licence blocks commercial use, the product stays free and non-commercial.

## Bringing the existing CS2 project in line

CS2 is audited, not rewritten: Claude Code reports every difference first, and you decide per item whether to retrofit the code or update this brief.

- [ ] Audit: compare the CS2 repo against every section of this brief and list the differences
- [ ] Folders: map current folders to the standard structure; rename, or note why not
- [ ] Data: convert stored data to the snapshot schema, or add an adapter in `ingest/` that outputs it
- [ ] Scoring: move the formula into `score/` and the weights into `weights.json`
- [ ] Momentum: add 7-day and 28-day score change and `rank_change` if missing
- [ ] Page: align sections to the seven-part layout
- [ ] Docs: write `docs/METHOD.md` and the first `docs/LOG.md` entry
- [ ] Licence: fill in the CS2 row of the licence table for each source
- [ ] Brief: fold anything CS2 does better back into this document

Keep changes small: an adapter that outputs the standard shape is fine; a full rewrite is not needed.

## After-build log template

Fill this in `docs/LOG.md` at the end of each build (about 10 minutes); together the four logs become the RP Engine spec and the final case study.

```markdown
# Build log: rp-<product>
Date:

## Copied from the previous build
-

## Changed or adapted
-

## Unique to this product
-

## Conventions broken, and why
-

## Engine candidates (parts that should become shared)
-

## What I'd do differently next time
-
```

## Prompts for Claude Code

Use the audit prompt once on CS2, then the build prompt at the start of each new product, with `RP_CONVENTIONS.md` (this doc, exported as Markdown) in the repo root.

**CS2 audit prompt**

```
This is my existing CS2 Tier-2 sponsor rankings project, part of my RankPortfolio.
Read RP_CONVENTIONS.md first. Audit this repo against every section of it:
folder structure, snapshot and score schemas, weights.json, scoring and momentum
rules, page layout, licence table and docs.
For each difference, report what it is now, what the convention says, and
recommend either "retrofit the code" or "update the conventions" with a reason.
Do not change any code until I approve the list.
```

**New product build prompt**

```
You're building rp-<product>, part of my RankPortfolio.
Read RP_CONVENTIONS.md first and follow it exactly: folder structure, snapshot
and score schemas, weights in score/weights.json, scoring and momentum rules,
seven-section page layout, licence and attribution rules.

Product: <name>
Audience and the decision it helps: <who decides what>
Data source and licence: <API, licence, attribution text>
Metrics, weights and direction: <metric: weight, higher_better / lower_better>
Refresh schedule: <daily / weekly / twice monthly>

Before writing code, list any conventions this product can't follow and why.
When finished, fill in docs/LOG.md using the template, then deploy to my own hosting.
```

## Sources

- [Ember, Electricity Data Explorer](https://ember-energy.org/electricity-data-explorer/)
