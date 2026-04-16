# ADR-007: Per-Pack Database Table Prefix

**Status:** Accepted
**Date:** 2026-04-15

## Context

With multiple country packs contributing database tables, clear ownership is essential. Without a naming convention:

- Two packs could independently create a `life_insurance_policies` table with incompatible schemas.
- Cross-pack foreign keys create hidden coupling that breaks pack isolation (ADR-003).
- Developers cannot tell at a glance whether a table belongs to core, UK, SA, or cross-border logic.
- Pack-specific migrations could conflict if tables share names.

## Decision

Per-pack database table prefix. Core tables are unprefixed. No cross-pack foreign keys.

| Owner        | Prefix | Examples                                           |
|--------------|--------|----------------------------------------------------|
| Core         | (none) | `users`, `households`, `jurisdictions`, `tax_years` |
| country-gb   | `gb_`  | `gb_life_insurance_policies`, `gb_isa_accounts`    |
| country-za   | `za_`  | `za_retirement_annuities`, `za_tax_free_savings`   |
| cross-border | `cb_`  | `cb_dta_claims`, `cb_qrops_transfers`              |

**Rules:**

1. Core tables hold jurisdiction-agnostic data: users, households, jurisdictions, user_jurisdictions, tax_years, goals, audit_log.
2. Pack tables use a prefix matching their country code or pack identifier.
3. Pack tables may only have foreign keys to core tables (e.g., `gb_isa_accounts.user_id` references `users.id`).
4. No cross-pack foreign keys. A `za_` table must never reference a `gb_` table directly. Cross-pack data relationships go through core tables.
5. Existing UK tables (currently unprefixed) require a rename migration: create `gb_`-prefixed table, migrate data, drop the old table.

## Consequences

- **Positive:** Clear table ownership visible in any database tool or query log.
- **Positive:** Packs can be developed independently without migration filename or table name conflicts.
- **Positive:** Prevents accidental cross-pack JOINs -- if you see `gb_` joined to `za_`, something is wrong.
- **Negative:** Existing UK tables require a rename migration. This is a significant one-time effort (paired add-new, migrate data, drop-old for each table).
- **Negative:** Table names are longer (`gb_life_insurance_policies` vs `life_insurance_policies`).
- **Negative:** Eloquent model `$table` properties must be set explicitly for prefixed tables.
