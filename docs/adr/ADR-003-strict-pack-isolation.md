# ADR-003: Strict Pack Isolation

**Status:** Accepted
**Date:** 2026-04-15

## Context

Users may have financial interests in multiple countries -- for example, a UK expat living in South Africa with pensions in both jurisdictions. Cross-border calculations (Double Taxation Agreements, QROPS transfers, worldwide estate aggregation) need to reference both jurisdictions' rules.

The naive approach is to let `country-za` import classes from `country-gb` directly. This creates tight coupling: changes to UK tax logic could break SA calculations, pack release order becomes constrained, and the dependency graph becomes a web rather than a tree.

## Decision

No pack may import from another pack's namespace. The dependency rules are:

```
country-gb  -->  core  <--  country-za
                  ^
                  |
             cross-border  -->  country-gb (interface only)
                            -->  country-za (interface only)
```

- `country-gb` depends only on `core`.
- `country-za` depends only on `core`.
- `cross-border` depends on `core`, `country-gb`, and `country-za`. It is the only pack permitted to reference multiple jurisdictions.
- No pack may import from another country pack directly.

This is enforced by architecture tests in CI that scan `use` statements and `composer.json` `require` blocks.

## Consequences

- **Positive:** Clean dependency graph. Each country pack can be developed, tested, and reasoned about independently.
- **Positive:** The `cross-border` pack can be loaded only when a user has two or more active jurisdictions, keeping the common case simple.
- **Positive:** Prevents subtle coupling where a change in UK pension logic silently breaks SA retirement calculations.
- **Negative:** Cross-border features require a dedicated pack rather than quick imports, adding development overhead for multi-jurisdiction features.
- **Negative:** Shared abstractions (e.g., a common pension interface) must live in core, requiring careful interface design upfront.
