# G-4-b Slice 4 — Form Request Deep-Validation Sweep

**Date:** 2026-07-06 · **Gauntlet:** v2 (local-only) · **Method:** sample-of-10, risk-biased, read-only audit + adversarial verification of the one HIGH. Closes workstream G-4-b.

## Result: **PASS** (after in-session fix of the single HIGH)

Slice-4 raw result was FAIL (1 HIGH per the slice-3 rubric: ≥1 HIGH = FAIL). The HIGH was verified end-to-end and **fixed in-session** with 3 regression tests; the layer now exits green. Tally: **1 HIGH (fixed) · 8 MEDIUM · 9 LOW · 5 INFO** across the sample.

## HIGH — S4-H1 (FIXED)

**Cross-user savings-deposit leak via unscoped `linked_savings_account_id`.**
`StoreGoalRequest.php:34` / `UpdateGoalRequest.php:35` validated the FK only as `exists:savings_accounts,id` — unscoped. `GoalsController` never re-scoped it (verified: zero references), and `TracksGoalContributions::resolveLinkedGoals` (`packs/country-gb/src/Traits/TracksGoalContributions.php:107-110`) resolves goals by account id with **no owner filter**. Exploit: attacker links their goal to a victim's account id → on the victim's next deposit, the observer writes a `GoalContribution` equal to the victim's balance delta onto the attacker's goal → attacker sees the victim's deposit amount + date. Precondition (victim account has no pivot-linked goals) holds for the common case.

**Verification:** all three chain links confirmed by grep + code read; a failing Feature test reproduced the leak (attacker POST succeeded pre-fix).

**Fix:** both requests now scope the FK with `Rule::exists('savings_accounts','id')->where(...)` to `user_id = caller OR joint_owner_id = caller`. Regression tests in `tests/Feature/Security/GoalLinkedAccountScopingTest.php` (attacker rejected 422; owner + joint-owner accepted 201). Suite 2,985 green.

## MEDIUM / LOW — logged to triage (E-24..E-31)

Systemic, not fixed under this gate (extend existing E-16..E-20):
- **E-24 (MED):** unscoped FK IDOR class — `trust_id` (StorePropertyRequest:26 → injects attacker asset into victim's trust aggregation; `TrustAssetAggregatorService` filters by trust_id only) and `joint_owner_id` across Property/Goal/LifeEvent/Mortgage requests (validated `exists:users,id`, no spouse/consent check — contradicts the SpousePermission request→accept design). Recommend a shared caller-scoped rule for both.
- **E-25 (MED):** currency fields missing `max:` — 7/10 sampled requests have ≥1 unbounded `numeric|min:0` money field (property values, gift value, pension fund value, DC contributions). `ValidationLimits::currencyRules()` exists but is barely used. Highest priority: fields feeding IHT/AA/tax calcs.
- **E-26 (LOW):** unbounded text (`notes => nullable|string` with no `max:`) — DoS class, constants `MAX_NOTES_LENGTH` exist but unenforced.
- **E-27 (LOW):** ZA `ownership_type` accepts `joint` with no `joint_owner_id` field (data-model inconsistency vs GB joint pattern).
- **E-28 (LOW):** ZA exchange-control `amount_minor` / `transfer_date` unbounded (mitigated by ledger throwing 422, but request layer should cap).
- **G-4-c-i (LOW):** nonsense `tokenable_type` 500s instead of ideal 401 (robustness only — needs DB write to trigger, fails closed with no data leak; from the G-4-c morph test).

## What's clean (verified)
- **Mass-assignment:** no request leaked `user_id`/`is_admin`/verified flags — controllers set `user_id` server-side.
- **Enums:** zero canonical-enum violations; no `sole` anywhere in the sample.
- **Two reference-quality requests:** `StoreActionDefinitionRequest` (real `PermissionService` gate + double route gating), `StoreDCPensionRequest` (genuine ownership check in `authorize()`). `StoreHoldingRequest`'s controller closes its FK IDOR with `->where('user_id',…)->firstOrFail()` — the pattern the rest should adopt.

## Systemic recommendation
`authorize(): return true` is near-universal (8/10), acceptable only because controllers compensate — so any controller that forgets `where('user_id')` has zero request-layer backstop (exactly how S4-H1 arose). The durable fix for the FK-IDOR class is a shared caller-scoped validation rule object, applied wherever a request accepts a linkable/ownable FK. Tracked as E-24.
