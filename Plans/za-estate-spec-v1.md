---
type: spec
workstream: SA pack — Estate module completion
version: v1
date: 2026-07-06
status: draft (autonomous loop)
parent: Plans/SA_Research_and_Mapping.md §10 (v1-essential Estate scope)
---

# SA Estate Module — Spec

## 1. Problem

The July audit flagged SA Estate as the biggest SA-pack gap. Reality after
inspection is narrower and better than the audit implied: the **tax logic is
already built and config-seeded** —
- `ZaTaxEngine::calculateEstateDuty` (s4A abatement R3.5m, portability, tiered
  20%/25% rates above R30m),
- `ZaTaxEngine::calculateDonationsTax` (SARS: R100k annual exemption, 20%/25%),
- `ZaEstateEngine::{calculateEstateTax, calculateCgtOnDeath, getExemptions,
  getReliefs, calculateExecutorFees}`.

What's missing is everything AROUND the engine:
- No `ZaEstateController` and no `/api/za/estate/*` routes — the engine is
  unreachable from the frontend.
- No **donations register** persistence — `calculateDonationsTax` needs the
  user's cumulative lifetime donations (since 2018-03-01) as input; nothing
  records donations.
- No ZA Estate frontend components.

## 2. Goal

A South African user can see their estate-duty position (estate duty, CGT on
death, executor fees, exemptions/reliefs) computed from their SA financial
position, and can record lifetime donations that feed both a running
donations-tax figure and the estate-duty aggregation.

## 3. Scope (v1)

**In:**
1. `ZaEstateController` exposing the existing engines: an estate-duty projection
   endpoint (from the user's SA assets/liabilities) + an exemptions/reliefs
   reference endpoint.
2. A **donations register**: `ZaDonation` model + `za_donations` table + CRUD
   endpoints, with a cumulative-donations aggregation feeding
   `calculateDonationsTax`.
3. `/api/za/estate/*` routes registered in the ZA pack routes file.
4. A ZA Estate Vue view + components mirroring the other ZA modules.
5. Feature + unit tests; no new tax math (the engines are already tested).

**Out (later slices / flagged):**
- Full will-builder / LPA equivalents (GB has these; SA v1 doesn't need them).
- SA estate personas (workstream: personas, separate).
- FAIS/POPIA compliance surfaces (separate SA workstream).

## 4. Constraints

- **No new tax logic.** The controller and services only orchestrate the
  existing `ZaTaxEngine`/`ZaEstateEngine`. Any change to the tax math is out of
  scope and would require tax-compliance review.
- **Int-minor money throughout** (ADR-005) — the engines already use `_minor`.
- **Pack isolation**: all code in `Fynla\Packs\Za\*`; routes in the ZA pack
  routes file; zero `App\`/core-pack-crossing imports (PackIsolationTest).
- **Canonical patterns**: mirror `ZaProtectionController` / `ZaSavingsController`
  (constructor-injected engine, `Sanctum::actingAs` auth, Form Requests,
  `user_id` server-side scoping, ZA resource transformers).

## 5. Acceptance criteria

| # | Criterion | Verification |
|---|-----------|--------------|
| 1 | `GET /api/za/estate/summary` returns estate duty + CGT-on-death + executor fees + net estate for the authenticated SA user | Feature test |
| 2 | `GET /api/za/estate/exemptions` returns the exemptions + reliefs reference | Feature test |
| 3 | Donations CRUD (`GET/POST/PUT/DELETE /api/za/estate/donations`) scoped to the user; cumulative feeds donations-tax | Feature test + IDOR test |
| 4 | Estate routes are pack-registered and auth-guarded (ApiAuthCoverageTest stays green) | arch + auth-coverage |
| 5 | Pack isolation + strict_types + no-float-money all green | architecture suite |
| 6 | ZA Estate view renders for a ZA context without console errors | (deferred — no SA persona/E2E yet; unit + Feature suffice for v1) |

## 6. Slices

- **Slice 1**: `ZaEstateController` + estate-summary & exemptions endpoints +
  routes + Feature tests (read-only, engine-wiring only). LOW risk.
- **Slice 2**: `ZaDonation` model + migration + donations CRUD + cumulative
  aggregation into donations-tax. MED (persistence + one aggregation).
- **Slice 3**: ZA Estate Vue view + components. MED (frontend, no SA persona to
  E2E against yet — manual/unit only).

## 7. Assumptions (CSJ may veto)

- **A1**: SA Estate v1 does NOT need a will-builder/LPA (GB-specific richness).
  Estate-duty visibility + donations register is the SARS-relevant core.
- **A2**: The estate-summary endpoint derives the gross estate from the user's
  existing SA holdings/accounts via the same aggregation the other ZA modules
  use; where an SA net-worth aggregator doesn't exist yet, slice 1 accepts an
  explicit request payload (gross/liabilities/spouse-transfer) and slice 2+
  wires the aggregation. Keeps slice 1 shippable.
