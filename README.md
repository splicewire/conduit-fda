# splicewire/conduit-fda

A Saloon HTTP connector for the openFDA drug-shortage API. This package supplies
the one piece `splicewire/laravel-knowledge-engine`'s `OpenFdaShortageSource`
and `StatusIngestor` never had: a real fetch step, exercised only by test
doubles until now.

Modeled directly on the sibling package `splicewire/conduit-ecfr` — same shape
(a Saloon `Connector` + `Request` per endpoint, a plain manually-run Artisan
command, a plain `Illuminate\Support\ServiceProvider`), same convention (no
scheduling, no change-feed listener).

## Contents

- `Splicewire\Fda\Connectors\FdaConnector` — base URL `https://api.fda.gov`,
  rate-limited to openFDA's unauthenticated default (~40 requests/minute).
- `Splicewire\Fda\Requests\GetDrugShortages` — `GET /drug/drugshortages.json`,
  with optional `search`, `limit`, `skip` query params.
- `Splicewire\Fda\Commands\SyncShortages` — `fda:sync-shortages
  {--search=} {--limit=} {--skip=}`. Fetches via `FdaConnector`, hands the raw
  payload to `OpenFdaShortageSource::parse()` (via `StatusIngestor`), and
  upserts the resulting `ConceptStatus` rows into `concept_statuses`.

## Why `SyncShortages` doesn't use `laravel-satellite-knowledge`

`StatusIngestor::pull()` only fetches and parses — it returns `ConceptStatus`
DTOs and never persists (by design: an `*-engine` package may not require
`illuminate/database`). The Eloquent side, in
`splicewire/laravel-satellite-knowledge`, only offers `ConceptStatus::toData()`
(model → DTO); there is no DTO → model hydrator anywhere in the family.

Rather than add a dependency on that satellite package from this conduit —
which would break the `conduit-ecfr` convention that a conduit stays
persistence-agnostic and depends only on its transport plus the parsing spine
— `SyncShortages` persists directly via the query builder against the
documented `concept_statuses` table shape. A host that already depends on
`laravel-satellite-knowledge` (e.g. `splicewire/tower`) can freely swap this
for its own Eloquent-model persistence one tier up.

## Manually run, not scheduled

Like `conduit-ecfr`'s `ecfr:sync`, `fda:sync-shortages` is not scheduled
anywhere and no change-feed listener is built here — openFDA gives no reliable
push signal for shortage changes, so this is re-run by hand (or wired up by a
host) whenever fresh data is wanted.

## Testing

```
composer test
```

Uses Saloon's `MockClient` — no real network calls in the test suite.
