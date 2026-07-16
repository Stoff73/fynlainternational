---
type: spec
date: 2026-07-16
workstream: WS1 — Jurisdiction Lifecycle
program: International Layer (WS1–WS6)
status: draft (awaiting user review)
branch: fix/reconciliation-fixes
companion_evidence: July/July15Updates/reconciliation-2026-07-15.md, findings.md
---

# WS1 — Jurisdiction Lifecycle

## Program context

The "international layer" is being built as six sequenced workstreams toward one
end-to-end definition of done:

> A user registers, picks South Africa, and lands in an app that is correct for
> them — SA-only sidebar items, an SA dashboard showing their real TFSA/RA/policy
> data, everything in ZAR with SA dates and the 1-March tax year. A GB user is
> completely unaffected.

Build order (each stands on the ones before it): **WS1 Jurisdiction lifecycle** →
WS2 query-layer un-Null → WS3 frontend localisation → WS4 SA experience surfacing
→ WS5 ZA payment → WS6 R-13 pack-relocation + bundle isolation.

This spec covers **WS1 only**. Its job: every user reliably has a correct
jurisdiction from the moment the account exists, and the backend enforces pack
access by it. Nothing downstream can key off jurisdiction until this is true.

## Problem (from the reconciliation)

- `AuthController::verifyCode` creates the `User` (line ~511) with **no**
  `user_jurisdictions` row. `GeoLocationService` is orphaned (no call sites, no
  MaxMind data source). New users get `active_jurisdictions: []`.
- The app survives this only by accident: `SideMenu.vue` computes
  `zaOnly = hasZa && !hasGb`, which is false for an empty list, so a row-less user
  renders the GB sidebar by default.
- `ActiveJurisdictionMiddleware` keys off a `{cc}` route param that **no route
  has**, so it passes every request through. Its entitlement check is a Phase-0
  `FYNLA_ACTIVE_PACKS` env stub with a `TODO: Workstream D — replace with
  user_jurisdictions table check`. Result: any authenticated user can call any
  pack's API (`/api/za/*`, `/api/gb/*`) and get 200.
- Only 5 of 37 local users have jurisdiction rows; all 6 GB preview personas,
  their spouses, `chris@fynla.org`, and every factory user have none. Spouse
  creation and preview seeding also mint row-less users.
- `UserJurisdiction::$fillable` lacks `deactivated_at`/`auto_detected`; the
  cross-border observer sidesteps it via `DB::table`. No reader filters
  `deactivated_at`, so soft-deactivation is write-only.

## Decisions (confirmed with user, 2026-07-16)

- Jurisdiction is set by **explicit country selection at signup** (not geo-IP).
  Product-doc "geo-registration" can layer on later as an auto-suggest.
- Supported countries in v1: **GB, ZA**.

## Design

### Component 1 — Capture country at signup

- Add a `country_code` column to `pending_registrations` (migration; `CHAR(2)`,
  nullable for back-compat, no default).
- Add `country_code` to `PendingRegistration::$fillable`.
- `RegisterRequest`: add `'country_code' => ['required', Rule::in(['GB','ZA'])]`.
  (British-spelling label "Country of residence" in the frontend.)
- `AuthController::register` already does `PendingRegistration::createOrUpdate($data)`
  — `country_code` flows through with no controller change once validated + fillable.
- Frontend: add a country-of-residence `<select>` (GB, ZA) to the registration
  form, sent as `country_code`. This is the one small frontend touch WS1 needs;
  the SPA's broader localisation/sidebar work is WS3/WS4.

### Component 2 — `AssignPrimaryJurisdiction` service (core)

New `core/app/Core/Jurisdiction/AssignPrimaryJurisdiction.php` — the single writer
of a user's primary jurisdiction, so no path mints a row-less user.

```
(new AssignPrimaryJurisdiction)->assign(User $user, string $countryCode): UserJurisdiction
```

- Resolves `Jurisdiction::findByCode($countryCode)`; throws a clear domain
  exception if the jurisdiction isn't seeded.
- Idempotent: if the user already has a primary row, update it; else insert
  (`is_primary = true`, `activated_at = now()`). Never creates duplicates.
- Called from:
  - `AuthController::verifyCode` after `User::create`, using
    `$pending->country_code` (fallback `'GB'` if null — legacy pending rows).
  - Spouse creation (`FamilyMembersController`, `PreviewController` spouse path):
    inherit the creating user's primary jurisdiction.
  - Preview seeders: UK personas → `GB`, Thabo Nkosi → `ZA` (the ZA seeder
    already sets this; route it through the service for consistency).

### Component 3 — Backfill command

`php artisan jurisdictions:backfill` — assigns `GB` primary to every user with no
jurisdiction row, via `AssignPrimaryJurisdiction`. Idempotent (safe to re-run).
Reports counts. Fixes the existing row-less population so Component 5's
enforcement can't lock anyone out. GB is the correct default: the row-less
population is all UK-era users/personas.

### Component 4 — `UserJurisdiction` model correctness

- Add `deactivated_at`, `auto_detected` to `$fillable` (+ `deactivated_at`
  datetime cast, `auto_detected` boolean cast).
- `User::jurisdictions()` and `Jurisdiction::users()`: scope out
  `whereNull('deactivated_at')` so a soft-deactivated jurisdiction stops being
  reported as active (honors what the cross-border observer already writes).

### Component 5 — Real enforcement in `ActiveJurisdictionMiddleware`

- Derive the pack code from the request path instead of the absent `{cc}` param:
  `api/za/*` → `ZA`, `api/gb/*` → `GB`. Core routes (`api/auth`, `api/user`, …)
  match neither → pass through unchanged.
- Replace the `FYNLA_ACTIVE_PACKS` entitlement stub with the intended
  `user_jurisdictions` check: authenticated user must have a **non-deactivated**
  row for the derived code, else `403 JURISDICTION_NOT_AUTHORISED`. Unauthenticated
  requests are unaffected (auth middleware handles them).
- Keep the installation-level `PackRegistry::isEnabled` check (404 if the pack
  isn't installed at all).
- `FYNLA_ACTIVE_PACKS` remains the installation-level "which packs boot" gate;
  `user_jurisdictions` is the per-user gate. The two no longer overlap in meaning.
- Ensure the middleware is applied to both pack route groups (GB mounts at
  `prefix('api/gb')`, ZA inside `prefix('api')` as `api/za/*`).

**Sequencing safety:** Components 2 + 3 must be merged and the backfill run before
Component 5 is enabled, or row-less users get 403s. The plan will land 5 last and
its test seeds jurisdictions explicitly.

**LegacyApiRewrite interaction (documented, not changed here):**
`LegacyApiRewrite` rewrites `/api/savings` → `/api/gb/savings` for everyone. After
enforcement, a ZA user hitting a legacy unprefixed URL is rewritten to GB and
correctly 403'd — they must call `/api/za/*`. Making the SA frontend call the
right pack URLs is WS4; WS1 only makes the enforcement correct.

## Data flow

```
signup form (country_code)
  → RegisterRequest (validated GB|ZA)
  → PendingRegistration.country_code
  → verifyCode → User::create
  → AssignPrimaryJurisdiction->assign(user, country_code)
  → user_jurisdictions (is_primary, activated_at)
  → GET /api/auth/user reads user->jurisdictions (existing code)
  → active_jurisdictions / primary_jurisdiction in session
  → sidebar + ActiveJurisdictionMiddleware key off it
```

## Error handling

- Unknown/absent country at signup → 422 validation error.
- Jurisdiction code not seeded when assigning → clear domain exception (surfaces
  a seeding problem rather than a silent null).
- Row-less legacy pending registration → `AssignPrimaryJurisdiction` defaults to
  `GB` (documented), never throws for a missing `country_code`.

## Testing (Pest)

- SA registrant (verifyCode with `country_code = ZA`) → exactly one primary
  `user_jurisdictions` row for ZA; session returns `active_jurisdictions: ['za']`.
- GB registrant → GB primary.
- `AssignPrimaryJurisdiction` is idempotent (second call updates, no duplicate).
- `jurisdictions:backfill` assigns GB to a row-less user, skips users who already
  have a row.
- Enforcement: a GB-only user calling a ZA endpoint → 403; ZA-only user calling a
  GB endpoint → 403; each calling their own pack → passes; core routes
  (`/api/user`) → pass for both.
- Spouse creation yields a jurisdiction row inheriting the creator's.
- Deactivated jurisdiction is excluded from `active_jurisdictions`.
- Regression: the full existing suite stays green (enforcement must not 403
  existing feature tests — they seed users; those users need a jurisdiction, so
  the test harness/factory is updated to assign one, or the relevant tests act on
  core/own-pack routes).

## Acceptance criteria

- [ ] Registering with SA selected yields a ZA-primary user whose session reports `za`.
- [ ] No creation path (signup, spouse, preview seed, factory-in-tests) leaves a user without a jurisdiction.
- [ ] `jurisdictions:backfill` brings the existing population to 100% coverage.
- [ ] A user cannot successfully call a pack API for a jurisdiction they don't hold (403).
- [ ] A GB-only user's experience and API access are unchanged.
- [ ] Soft-deactivated jurisdictions are not reported as active.
- [ ] Full Pest suite green; Larastan clean.

## Out of scope for WS1

- Geo-IP auto-detection (explicit selection chosen).
- SA dashboard/Net Worth data (WS2 — repos still Null after WS1).
- ZAR/date/tax-year localisation (WS3).
- Sidebar module hiding + SA-aware dashboards (WS4).
- ZA payment (WS5), frontend pack-relocation/bundle isolation (WS6).

## Risks

- **Enabling enforcement is a behavior change.** Mitigated by sequencing (2+3
  before 5) and the backfill. A GB user with a GB row is unaffected.
- **Existing tests create bare users.** The enforcement tests + any feature test
  that hits a pack route need those users to hold the jurisdiction; addressed by a
  factory/helper update in the plan, kept minimal.
- **Legacy pending registrations** lack `country_code` → default GB (documented).
