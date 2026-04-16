# ADR-002: Composer Path Repositories for Pack Loading

**Status:** Accepted
**Date:** 2026-04-15

## Context

Country packs need to be treated as separate packages with their own autoloading, service providers, and dependency declarations, while remaining in the same monorepo for development convenience. Three options were evaluated:

1. **nwidart/laravel-modules** -- a popular Laravel package for modular architecture. Adds its own conventions, directory structures, and CLI tooling.
2. **Private Packagist** -- host packs as private Composer packages on a registry. Requires infrastructure, versioning discipline, and a publish step for every change.
3. **Composer path repositories** -- declare packs as local path repositories in the root `composer.json`. Composer symlinks them into `vendor/`, so edits take effect immediately.

nwidart/laravel-modules imposes opinionated conventions that conflict with Fynla's existing structure and adds a framework-level dependency. Private Packagist is the right long-term choice for distributing packs to client deployments but adds unnecessary friction during active development. Composer path repositories give us package isolation with zero overhead.

## Decision

Use Composer path repositories with symlink. Each pack lives under `packs/` and has its own `composer.json`. The root `composer.json` declares them as path repositories:

```json
{
  "repositories": [
    { "type": "path", "url": "packs/country-gb" },
    { "type": "path", "url": "packs/country-za" },
    { "type": "path", "url": "packs/cross-border" }
  ]
}
```

An edit inside `packs/country-gb/src/` takes effect immediately with no `composer update` loop. Each pack registers its own Laravel service provider via `extra.laravel.providers` in its `composer.json`.

## Consequences

- **Positive:** Simple, zero-infrastructure dependency management. No registry to maintain.
- **Positive:** Immediate feedback during development -- edit a pack file, refresh the browser.
- **Positive:** Each pack has its own `composer.json`, PSR-4 autoloading, and service provider, establishing clean boundaries.
- **Positive:** Scales to private Packagist later when packs need independent versioning or distribution to separate deployments.
- **Negative:** Path repositories are symlinked, so `vendor/` contains symlinks rather than copied files. Some deployment tools may need configuration to follow symlinks.
- **Negative:** No independent versioning of packs during development. All packs move at the monorepo's pace.
