# ADR-001: Single Codebase with Country Packs

**Status:** Accepted
**Date:** 2026-04-15

## Context

Fynla needs to expand beyond the UK to South Africa and potentially other countries. Three options were evaluated:

1. **Fork per country** -- clone the repo for each new jurisdiction, diverge independently.
2. **Microservices per country** -- separate deployable services per jurisdiction behind an API gateway.
3. **Single codebase with modular packs** -- one monorepo with a country-agnostic core and pluggable country-specific packs.

Forking leads to duplicated bug fixes, divergent feature sets, and an exponential maintenance burden as countries are added. Microservices introduce network overhead, distributed-system complexity, and operational cost disproportionate to the current team size. A single codebase with modular packs keeps all shared logic in one place while cleanly isolating jurisdiction-specific behaviour.

## Decision

Single monorepo with a country-agnostic core and country-specific packs. The existing UK logic becomes one such pack (`country-gb`); South Africa becomes a second (`country-za`). Future countries arrive as additional packs without forking or deploying new services.

The repository structure follows:

```
packs/
  country-gb/       # UK-specific logic (tax, regulation, products)
  country-za/       # South Africa-specific logic
  cross-border/     # Cross-jurisdiction calculations (DTA, QROPS, worldwide estate)
app/                # Country-agnostic core (auth, users, households, goals, coordination)
```

## Consequences

- **Positive:** Shared infrastructure updates (auth, billing, design system, CI) benefit all countries simultaneously.
- **Positive:** A single test suite covers core + all packs, catching cross-cutting regressions early.
- **Positive:** New countries are additive -- they do not touch existing pack code.
- **Negative:** Core must be rigorously country-agnostic. Any UK assumption that leaks into core becomes a blocker for every future pack.
- **Negative:** Requires discipline to prevent cross-pack coupling. Architecture tests must enforce pack boundaries (see ADR-003).
- **Negative:** Monorepo tooling (selective CI, pack-scoped migrations) needs upfront investment.
