# ADR-005: Money as an Integer Value Object

**Status:** Accepted
**Date:** 2026-04-15

## Context

The current codebase uses `DECIMAL` columns and PHP floats for money. This works for a single-currency application but breaks down with multi-currency support:

- **Float arithmetic introduces rounding errors.** `0.1 + 0.2 !== 0.3` in IEEE 754. In a finance app, accumulated rounding errors across projections, tax calculations, and aggregations produce incorrect results.
- **No explicit currency tracking.** A column named `total_coverage` could be GBP or ZAR. Without a currency field, multi-currency aggregation is guesswork.
- **Implicit conversions.** Mixing GBP and ZAR values without explicit conversion is a category error that the type system should prevent.

## Decision

Money is an integer value object with an explicit currency. Floats for money are banned from all new code.

```php
final class Money
{
    public function __construct(
        public readonly int $amount,      // Minor units (pence, cents)
        public readonly Currency $currency // ISO 4217 value object
    ) {}
}
```

**Rules:**

1. All new money fields use `BIGINT` columns storing minor units, paired with a `currency` column (ISO 4217 code).
2. Existing `DECIMAL` columns are migrated incrementally via a dual-read/dual-write pattern: add a shadow `BIGINT` column, write to both, read from the new column, then drop the old column.
3. An architecture test bans float arithmetic (`*`, `/`, `+`, `-`) on variables matching money-related naming patterns in new code.
4. The `Money` class provides arithmetic methods (`add`, `subtract`, `multiply`, `allocate`) that enforce same-currency constraints.
5. Currency conversion is explicit via a `CurrencyConverter` service, never implicit.

## Consequences

- **Positive:** Eliminates rounding errors. Integer arithmetic is exact.
- **Positive:** Forces explicit currency handling. You cannot accidentally add GBP to ZAR.
- **Positive:** The value object pattern makes money a first-class concept in the domain model.
- **Negative:** Requires migration of all existing money columns. This is invasive but can be done incrementally via shadow columns.
- **Negative:** Display formatting must convert minor units back to major units. The existing `currencyMixin` needs updating.
- **Negative:** Third-party integrations that return floats need conversion at the boundary.
- This is a finance app -- precision is non-negotiable.
