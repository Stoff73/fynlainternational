---
type: plan
workstream: SA pack — Estate module completion
version: v1
date: 2026-07-06
status: ACTIVE — slice tracker
spec: Plans/za-estate-spec-v1.md
---

# SA Estate Plan — Slice Tracker

Engines already exist (`ZaEstateEngine` bound `pack.za.estate`; `ZaTaxEngine`
has `calculateEstateDuty` + `calculateDonationsTax`, config-seeded). This is
wiring + one persistence model. Mirror `ZaSavingsController`/`ZaProtectionController`.

| Slice | Scope | Risk | Status |
|-------|-------|------|--------|
| 1 | `ZaEstateController` (summary + exemptions + cgt-on-death) + routes + Feature tests | LOW | **DONE 2026-07-06** — 3 endpoints wired to ZaEstateEngine, `/api/za/estate/*` pack-registered + auth-guarded, EstateSummaryRequest bounds inputs, 6 Feature tests (duty/spousal/CGT/auth/validation). Arch + ZA suites 422 green. |
| 2 | `ZaDonation` model + `za_donations` migration + donations CRUD + cumulative→donations-tax | MED | TODO |
| 3 | ZA Estate Vue view + components | MED (no SA persona to E2E; unit/manual) | TODO |

## Per-slice ritual
git mv/create → strict_types + pack namespace → routes in packs/country-za/routes/api.php
→ composer dump-autoload → pest (arch + new Feature) green → pint → commit+push.

## Slice 1 detail
- `packs/country-za/src/Http/Controllers/ZaEstateController.php`:
  - `summary(Request)`: accepts gross_estate/liabilities/spouse_transfer/exempt_transfers
    (int minor) + optional predeceased-spouse flags; delegates to
    `ZaEstateEngine::calculateEstateTax`; returns the engine array.
  - `exemptions(Request)`: `getExemptions($taxYear)` + `getReliefs()`.
  - `cgtOnDeath(Request)`: delegates to `calculateCgtOnDeath`.
- Routes: `/api/za/estate/{summary,exemptions,cgt-on-death}` under the existing
  `auth:sanctum + active.jurisdiction + pack.enabled:za` group.
- Form Request `EstateSummaryRequest` validating the int-minor inputs (bounded).
- Feature test: authenticated summary + exemptions + unauth 401.

## Slice 2 detail
- `ZaDonation` model (user_id, amount_minor, donation_date, recipient, notes),
  `za_donations` table, `StoreZaDonationRequest` (caller-scoped, bounded).
- `donations()` CRUD + `donationsTax()` that sums the user's donations since
  2018-03-01 and calls `ZaTaxEngine::calculateDonationsTax`.

## Slice 3 detail
- `packs/country-za/resources/js/components/Estate/*` + a view; mirror the ZA
  Savings components. Deferred E2E until SA personas exist.
