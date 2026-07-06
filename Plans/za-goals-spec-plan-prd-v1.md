---
type: spec+plan+prd
workstream: SA pack — Goals module completion
version: v1
date: 2026-07-06
status: ACTIVE
parent: Plans/SA_Research_and_Mapping.md §11
note: Small feature (2 read-only endpoints wiring existing services) — combined
      spec/plan/PRD is proportionate per the workflow rule.
---

# SA Goals — Spec / Plan / PRD

## Spec

**Problem.** SA Goals has two built, config-seeded services —
`ZaGoalsDefaults` (bond deposit/term, tuition public/private, severance tax-free
threshold) and `ZaSeveranceBenefitCalculator` (retrenchment lump-sum tax via the
SARS retirement table) — but no controller, routes, or UI, so neither is
reachable. SA goals themselves reuse the core Goal model; SA's contribution is
SA-appropriate defaults + the severance life-event calculation.

**Goal.** An SA user's goal-creation flow can seed SA-appropriate defaults
(bond, tuition) and an SA user facing retrenchment can see the tax on a
severance lump sum.

**Scope (v1, in):**
1. `ZaGoalsController::defaults` — bond + tuition + severance-threshold defaults
   for the active/queried tax year.
2. `ZaGoalsController::severanceBenefit` — retrenchment lump-sum tax from a
   severance amount + prior cumulative lump sums.
3. `/api/za/goals/{defaults,severance-benefit}` routes.
4. Feature tests.

**Out:** SA goal persistence (uses core Goal), full goals UI (SA goals surface
through the existing goals module; a dedicated SA goals dashboard is not v1),
SA life-event UI (deferred with personas).

**Constraints.** No new tax math (services own it). Pack isolation, strict_types,
int-minor. Mirror `ZaEstateController` (thin proxy).

**Acceptance:**
| # | Criterion | Verify |
|---|-----------|--------|
| 1 | `GET /api/za/goals/defaults` returns bond + tuition + severance threshold | Feature |
| 2 | `POST /api/za/goals/severance-benefit` returns tax-free/taxable/net for a severance amount | Feature |
| 3 | Both auth-guarded; unauth → 401 | Feature + auth-coverage |
| 4 | Isolation + strict-types + no-float green | architecture |

## Plan

Single slice (read-only wiring, no persistence):
- `ZaGoalsController` (inject `ZaGoalsDefaults` + `ZaSeveranceBenefitCalculator`).
- `defaults(Request)`: tax_year query → the three defaults arrays.
- `severanceBenefit(Request)`: validate `severance_amount_minor` +
  `prior_cumulative_lump_sum_minor` (bounded) → calculator result.
- Routes under the existing za group.
- Feature tests (defaults, severance, auth, validation).
- Ritual: strict_types + pack ns → routes → dump-autoload → pest (arch + new)
  → pint → commit+push.

## PRD

**Overview.** Wire the SA goals defaults + severance calculator to the API.
**Users.** SA users setting goals / facing retrenchment.
**FRs.** FR1 defaults endpoint; FR2 severance-benefit endpoint; FR3
auth-guarded, pack-registered.
**NFRs.** Isolation/strict-types/int-minor enforced by the architecture suite.
**Out of scope.** Persistence, dedicated UI, SA life-event surfaces.
**Success.** Acceptance table green; architecture + auth-coverage stay green.
**Open Q.** Q1: does the goals UI consume `/za/goals/defaults` to seed SA goal
creation, or is that a later frontend task? Default: endpoint now, UI wiring
with the SA personas workstream.
