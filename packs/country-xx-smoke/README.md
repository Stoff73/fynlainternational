# country-xx-smoke

Synthetic smoke-test pack for Fynla's multi-pack architecture. This is **not** a real country pack. It exists solely for CI to verify that a second pack can be loaded alongside `country-gb` without conflicts.

## What it does

- Registers a single health endpoint: `GET /api/xx/health`
- Returns `{"status":"ok","pack":"xx-smoke","version":"0.0.1","purpose":"ci-smoke-test"}`
- Includes Pest tests for endpoint health and multi-pack coexistence

## Usage

Load the service provider (auto-discovered via `composer.json` extra) and run:

```bash
./vendor/bin/pest packs/country-xx-smoke/tests/
```
