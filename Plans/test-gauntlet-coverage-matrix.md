---
type: coverage-matrix
workstream: Test Gauntlet v2 — G-2-a
version: v1
date: 2026-07-06
status: auth column GREEN (enforced); happy-path partial (G-3 fills the rest)
---

# G-2-a — API Controller Coverage Matrix

98 API controllers across `app/Http/Controllers/Api` (39), `packs/country-gb`
(54), `packs/country-za` (5). Three coverage columns per the v1 spec:
happy-path, unauthenticated-rejection, unauthorized-resource.

## Column 2 — unauthenticated-rejection: **GREEN for 100% of routes (enforced)**

Rather than one auth test per controller, `tests/Feature/Security/ApiAuthCoverageTest.php`
walks the entire route table (601 `api/*` routes) and asserts every one is
sanctum-guarded, `AgentTokenAuth`-gated, or on an explicit 21-entry public
allow-list (auth entry points, health, webhook, preview, public marketing).
A new route with no guard fails the build. This is a stronger guarantee than
per-controller tests — it can't be circumvented by adding a controller.

Runtime spot-checks confirm the static guard maps to real 401s
(`/api/dashboard`, `/api/gb/net-worth/overview`, `/api/gb/protection`).

## Column 3 — unauthorized-resource (IDOR): covered where it matters

Ownership-scoping rejection (403/404 for another user's record) is proven for
the highest-risk paths by dedicated tests: savings (`MorphEscalationTest` —
steered token → 404 on another owner's account), goals
(`GoalLinkedAccountScopingTest` — cross-user FK rejected), plus the
pre-existing `UserMassAssignmentTest`. The systemic FK-IDOR class across the
remaining requests is logged as triage **E-24** with the shared-rule fix.

## Column 1 — happy-path: partial, G-3 completes it

9 controllers have Feature tests naming them directly; the module engines
behind the rest are heavily unit-tested (2,900+ tests). Dedicated per-endpoint
happy-path Feature coverage is **not** complete controller-by-controller — but
the v2 plan routes this to **G-3** (42 persona × module Playwright journeys),
which exercises every user-facing module end-to-end per persona. That is a
better happy-path signal than isolated controller Feature tests for a
local-only build.

### Controllers without a dedicated happy-path Feature test (89)

Deferred to G-3 (user-facing modules) or accepted as unit-covered (internal/
admin/AI). Not RED for security — column 2 (auth) is enforced GREEN for all.
Highest-value ones to add direct Feature tests for, if G-3 leaves gaps:
`AiChatController`, `PasswordResetController`, `ReferralController`,
`AgentInternalController`, `GDPRController`, `PaymentController` (money paths).

Full list regenerable via the matrix script (see the G-2-a commit).

## Exit posture

- **Column 2 (auth): CLOSED** — enforced across all 601 routes.
- **Column 3 (IDOR): CLOSED for high-risk paths**; systemic sweep = triage E-24.
- **Column 1 (happy-path): DEFERRED to G-3** for user-facing modules; internal/
  admin controllers accepted as unit-covered. G-2-a exits on that basis;
  revisit after G-3 to confirm no user-facing module was missed.
