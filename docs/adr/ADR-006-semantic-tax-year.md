# ADR-006: Semantic Tax Year

**Status:** Accepted
**Date:** 2026-04-15

## Context

The current codebase contains hardcoded assumptions about tax year boundaries:

- UK tax year runs 6 April to 5 April.
- South Africa's tax year runs 1 March to 28/29 February.
- SA exchange control allowances reset on 1 January (calendar year).
- Australia's tax year runs 1 July to 30 June.

Hardcoding `April 6` anywhere in core logic means that core is silently UK-specific. Adding South Africa would require either duplicating the logic with different dates or refactoring to support arbitrary tax year boundaries.

## Decision

Tax year is a semantic interval: a combination of jurisdiction, label, start date, and end date. No code outside a pack may hardcode tax year boundaries.

**Database:** A `tax_years` table stores:

| Column          | Type     | Description                          |
|-----------------|----------|--------------------------------------|
| id              | BIGINT   | Primary key                          |
| jurisdiction_id | BIGINT   | FK to jurisdictions table            |
| label           | VARCHAR  | e.g., "2025/26", "2026"             |
| starts_on       | DATE     | First day of the tax year            |
| ends_on         | DATE     | Last day of the tax year             |
| calendar_type   | VARCHAR  | "tax_year", "calendar_year", custom  |

**Service:** A `TaxYearResolver` determines the active tax year for a given date and jurisdiction:

```php
$taxYear = $resolver->active($jurisdictionId, Carbon::today());
// Returns: TaxYear { label: "2025/26", starts_on: "2025-04-06", ends_on: "2026-04-05" }
```

**Enforcement:** Each pack seeds its own tax years. Core code uses `TaxYearResolver` exclusively. Architecture tests grep core for month/day literals associated with tax year boundaries and fail if found.

## Consequences

- **Positive:** Core code is date-assumption-free. Works for any jurisdiction's fiscal calendar.
- **Positive:** Each pack seeds its own tax years, making the boundaries explicit and auditable.
- **Positive:** Supports jurisdictions with non-standard fiscal years (Australia July-June, SA March-February) without core changes.
- **Positive:** Handles edge cases like SA's dual calendar (tax year vs exchange control year) via the `calendar_type` field.
- **Negative:** Existing UK tax year logic throughout the codebase must be audited and refactored to use `TaxYearResolver`.
- **Negative:** Slightly more complex than a hardcoded date -- requires a database lookup (cacheable).
