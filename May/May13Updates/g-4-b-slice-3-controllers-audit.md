---
type: audit
gate: G-4-b slice 3
scope: controllers + form requests
date: 2026-05-13
sessions:
  - session 2026-05-13-1+2 (audit phase, 3 HIGH + 1 MED + 5 LOW identified)
  - session 2026-05-13-3 (HIGH fixes applied + tests added + MED/LOW logged to triage)
status: ✅ PASS — all 3 HIGH closed, MED/LOW logged as E-16..E-23
prev_slices:
  - slice 1 (auth + interceptor) — closed 2026-05-12
  - slice 2 (Revolut webhook + payments) — closed 2026-05-13
---

# G-4-b Slice 3 — Controllers + Form Requests Audit (initial scan)

## Universe

| Surface | Count |
|---|---|
| Controllers (excluding tests/vendor) | 101 |
| Form Requests | 104 |
| Routes in `routes/api.php` | 599 lines, 299 Route::* declarations |
| Routes in `packs/country-gb/routes/api.php` | 833 lines |
| Write routes (post/put/patch/delete) lacking `mfa.verified` | 328 |

Slice 3 cannot exhaustively review all 101 controllers. Per the slice plan, the approach is **sample-driven on the top-10 highest-sensitivity controllers** (Estate, Investment, Retirement, Documents, auth-adjacent) plus **pattern-based grep across the rest** for known anti-patterns from slices 1+2.

## Sensitivity ranking (top 10 candidates)

| # | Controller | Reason | Audited |
|---|---|---|---|
| 1 | `AdminController` | User CRUD, backups, AI provider, discount codes, role management | ✅ initial |
| 2 | `AdvisorController` | Multi-tenant impersonation of any user; assignments | ✅ initial |
| 3 | `DocumentController` | File upload, PII extraction, identity docs | ✅ initial |
| 4 | `GDPRController` | Data export, account erasure (destructive, irreversible) | ✅ initial |
| 5 | `packs/country-gb/Http/Controllers/Estate/IHTController` | IHT financial calculations; gift accounting | ✅ initial |
| 6 | `packs/country-gb/Http/Controllers/Estate/WillController` + `WillDocumentController` | Legal document generation, beneficiaries | pending |
| 7 | `packs/country-gb/Http/Controllers/Estate/GiftingController` + `LpaController` + `TrustController` | Estate transfer rails (IHT 7-year rule, LPAs, trusts) | ✅ Gifting initial; LPA + Trust + Will pending |
| 8 | `AiChatController` + `AiAuditController` | Prompt-injection surface; user data fed to external LLMs | ✅ AiChat initial; AiAudit skipped (admin read-only) |
| 9 | `SpousePermissionController` + `FamilyMembersController` | Cross-account data sharing | ✅ initial |
| 10 | `ReferralController` | Referral fraud / abuse paths | ✅ initial |

## Pattern-based triage results

### Pattern A — IDOR (`findOrFail($id)` without user scoping)

Two raw `User::findOrFail($id)` matches found in non-admin controllers:

- `AdvisorController.php:107` (`clientModuleStatus`) — **NOT a finding.** Preceded by `$advisor->advisorClients()->where('client_id', $id)->firstOrFail()` which gates access. Defence-in-depth.
- `AdvisorController.php:123` (`enterClient`) — **NOT a finding.** Same gating pattern.

Admin uses of `User::findOrFail($id)` are by-design (admins legitimately see all users).

### Pattern B — Path traversal in `Storage::` / `file_put_contents` / `file_get_contents`

All file-system touches are bounded:
- `AdminController.php:332, 459` — temp my.cnf for mysqldump, owner is admin only, deleted immediately after use. ✅
- `PaymentController.php:945, 965, 975` — invoice PDF, scoped to invoice owned by current user. ✅
- `PreviewController.php:388, 389, 397` — preview persona's own storage, read-only seed data. ✅

No traversal candidates.

### Pattern C — Inline `$request->validate()` vs FormRequest

66 controllers use inline `$request->validate(...)`. Most are simple GETs/PATCH with small rule sets; not inherently a finding. Will spot-check the privileged ones (AdminController, GDPRController) for completeness.

## Findings so far

### H-1 (HIGH) — Admin write endpoints lack `mfa.verified` middleware

**Evidence:** `routes/api.php` lines 326–388.

```php
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
    // ... 30+ admin endpoints, including ALL write ops:
    Route::post('/users', ...)              // user create
    Route::put('/users/{id}', ...)          // user update (inc. password reset!)
    Route::delete('/users/{id}', ...)       // user delete
    Route::post('/ai-provider', ...)        // global AI provider switch
    Route::post('/backup/restore', ...)     // database restore (effectively replaces DB)
    Route::post('/discount-codes', ...)     // money-relevant: discount code issuance
    Route::put('/discount-codes/{id}', ...)
    Route::delete('/discount-codes/{id}', ...)
    Route::patch('/discount-codes/{id}/toggle', ...)
});
```

None of the write endpoints inside the admin group are wrapped in `mfa.verified`. An attacker who hijacks an admin session token (XSS, cookie theft, stolen device, leaked dev environment, etc.) can:

- Create new admin accounts immediately
- Reset any user's password
- Restore an arbitrary backup file (effectively replacing the entire production DB)
- Issue 100%-off discount codes for arbitrary plans
- Pivot the AI provider (could route prompts to a malicious or different model)

Same risk class as slice 2 H-4 (payment write endpoints lacking MFA), but the blast radius is significantly larger because admin endpoints can directly compromise other users' data and the application's integrity.

**Recommended fix shape** (mirrors slice 2 H-4):

Split the admin route group into read-only and write groups:

```php
// Read-only admin endpoints (no mfa.verified)
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
    Route::get('/dashboard', ...);
    Route::get('/roles', ...);
    Route::get('/users', ...);
    Route::get('users/{id}/module-status', ...);
    Route::get('/subscriptions/stats', ...);
    Route::get('/ai-provider', ...);
    Route::prefix('ai-audit')->group(function () { /* GETs */ });
    Route::middleware(['permission:admin.backup'])->get('/backup/list', ...);
    Route::get('/user-metrics/...', ...);
    Route::get('/discount-codes', ...);
});

// Write admin endpoints (require mfa.verified in addition to admin permission)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'mfa.verified'])->prefix('admin')->group(function () {
    Route::middleware('permission:users.edit')->group(function () {
        Route::post('/users', ...);
        Route::put('/users/{id}', ...);
    });
    Route::delete('/users/{id}', ...)->middleware('permission:users.delete');
    Route::post('/ai-provider', ...);
    Route::middleware(['permission:admin.backup', 'throttle:3,1'])->group(function () {
        Route::post('/backup/create', ...);
        Route::post('/backup/restore', ...);
        Route::delete('/backup/delete', ...);
    });
    Route::post('/discount-codes', ...);
    Route::put('/discount-codes/{id}', ...);
    Route::delete('/discount-codes/{id}', ...);
    Route::patch('/discount-codes/{id}/toggle', ...);
});
```

**Compatibility note:** Admins without MFA enabled get a 403 with a clear "MFA required for admin write operations" error message — guides them to enable MFA in Settings > Security before performing writes. Read endpoints (dashboard, listing) keep working.

**Tests to add (similar to slice 2):**
- `AdminWriteEndpointsRequireMfaTest`: enumerate every admin write endpoint, assert 403 for non-MFA-verified admin and 200/201 for MFA-verified admin.

### H-2 (HIGH) — Legacy GDPR erasure routes bypass multi-step verification

**Evidence:** `routes/api.php` lines 108–112; `app/Http/Controllers/Api/GDPRController.php:184` (`requestErasure`), `:249` (`confirmErasure`), `:283` (`cancelErasure`).

```php
// Lines 102-106: new hardened 3-step flow (correct)
Route::post('/erasure/initiate', ...);          // generates session token, requires 2FA OR email code
Route::post('/erasure/verify', ...);            // verifies 2FA TOTP or email code, 3-attempt lockout
Route::post('/erasure/execute', ...);           // requires session-verified + confirmation phrase
Route::post('/erasure/resend-code', ...);

// Lines 108-112: legacy endpoints "deprecated, kept for backwards compatibility"
Route::post('/erasure', ...);                   // ⚠️ creates pending request with only confirm:true
Route::get('/erasure/status', ...);
Route::post('/erasure/{id}/confirm', ...);      // ⚠️ executes immediate deletion
Route::post('/erasure/{id}/cancel', ...);
```

The legacy POST `/erasure` accepts only `{confirm: true, reason: string}` and creates a pending erasure request. The legacy POST `/erasure/{id}/confirm` then triggers `processErasure()` (line 272) with no further verification — no MFA, no email code, no confirmation phrase, no password recheck. Attacker with a stolen session token can:

```bash
# Step 1: create pending request
curl -X POST https://.../api/auth/gdpr/erasure \
  -H "Authorization: Bearer $STOLEN_TOKEN" \
  -d '{"confirm": true}'
# returns request_id

# Step 2: confirm — deletes the entire account
curl -X POST https://.../api/auth/gdpr/erasure/{request_id}/confirm \
  -H "Authorization: Bearer $STOLEN_TOKEN"
# account gone
```

Frontend usage check (`resources/js/services/privacyService.js`): the frontend exclusively calls the new 3-step flow (`/initiate`, `/verify`, `/resend-code`, `/execute`). The legacy routes are unreferenced from JS — they exist purely as an API-level attack surface.

**Recommended fix:** Delete the four legacy route registrations (lines 109–112). Keep the controller methods (`requestErasure`, `confirmErasure`, `cancelErasure`, `getErasureStatus`) intact for now — they may be invoked by admin/support tooling in future, but without route exposure they are inert. Add a deprecation marker docblock to each method noting they should only be called from internal/admin flows, never re-exposed publicly.

**Test to add:** `LegacyGdprErasureRoutesAreUnroutableTest` — assert that `POST /api/auth/gdpr/erasure` and `POST /api/auth/gdpr/erasure/{id}/confirm` return 404, pinning the fix.

### H-3 (HIGH) — FamilyMembersController auto-links spouse accounts without consent

**Evidence:** `app/Http/Controllers/Api/FamilyMembersController.php:183` (`handleSpouseCreation`), specifically the unlinked-existing-spouse branch at lines 272–391.

When user A adds user B as a spouse by email, and B already has an account but no `spouse_id` set, the controller will:

1. **Auto-link both users inside a transaction (lines 273–366)** — without any input from B:
   ```php
   $currentUser->spouse_id = $spouseUser->id;
   $spouseUser->spouse_id  = $currentUser->id;
   $spouseUser->marital_status = 'married';
   if (isset($data['annual_income']) && $data['annual_income'] > 0) {
       $spouseUser->annual_employment_income = $data['annual_income'];  // OVERWRITES B's stated income
   }
   if (! $spouseUser->address_line_1 && $currentUser->address_line_1) {
       $spouseUser->address_line_1 = $currentUser->address_line_1;       // OVERWRITES B's address with A's
       // ... line_2 / city / county / postcode
   }
   $spouseUser->save();
   ```

2. **Auto-accept bidirectional `SpousePermission` records (lines 304–324):**
   ```php
   SpousePermission::updateOrCreate(
       ['user_id' => $currentUser->id, 'spouse_id' => $spouseUser->id],
       ['status' => 'accepted', 'responded_at' => now()],
   );
   SpousePermission::updateOrCreate(
       ['user_id' => $spouseUser->id, 'spouse_id' => $currentUser->id],
       ['status' => 'accepted', 'responded_at' => now()],
   );
   ```

3. **Mail a "spouse account linked" notification AFTER the fact** (line 378) — the link has already happened, A already has data-sharing access.

**Exploit:** Logged-in attacker A learns target B's email (B's account already exists and is unlinked). A submits:
```http
POST /api/user/family-members
{
  "relationship": "spouse",
  "email": "victim@example.com",
  "first_name": "Victim",
  "last_name": "Lastname",
  "annual_income": 1000000,
  "date_of_birth": "1990-01-01",
  ...
}
```

Result:
- B's `spouse_id` is now set to A.
- B's `marital_status` is now `married`.
- B's `annual_employment_income` is overwritten with A's submission.
- B's address (if blank) is overwritten with A's address.
- Bidirectional `SpousePermission`s are auto-accepted.
- A now has full data-sharing read access to B's financial data via every endpoint that checks `$user->hasAcceptedSpousePermission()` (Estate IHT, gifting strategy, trust strategy, joint account view, etc. — most module roll-ups).
- B receives an email after the fact saying "you've been linked".

There is **no consent step** for B before any of these field overwrites or permission grants happen. This directly contradicts the design of `SpousePermissionController`, which has a proper `request → accept/reject` flow specifically to gate cross-account sharing.

**Recommended fix** (single approach, two changes):

1. **`handleSpouseCreation` for existing accounts must NOT auto-link.** Create only the family-member record on A's side and set `linked_user_id = B.id`, but leave `spouse_id`, `marital_status`, `annual_employment_income`, address fields, and `SpousePermission` records untouched until B explicitly accepts a permission request.

2. **Reuse the existing `SpousePermissionController::request` flow** instead of creating bypass linkage in this controller. After the family member record is created, fire a `SpousePermission` row with `status = 'pending'` and notify B via email with a deep link to accept/reject in their own account.

3. **B's accept action** (already in `SpousePermissionController::accept`) is the only path that should set B's `spouse_id` and create the bidirectional accepted permission records. A reciprocal `FamilyMember` row on B's side should be created at accept time too (currently created at link time in FamilyMembersController:350).

This brings the family-add path in line with the request/accept design that already exists in `SpousePermissionController`. The new-spouse-creation branch (line 395+ — spouse doesn't have an account yet) can stay roughly as-is because B *cannot* exist to consent — but it should:
- Require B to verify their email before the account is "real" (set a flag like `requires_email_verification`)
- Hold off auto-accepting `SpousePermission` until verification completes

**Tests to add:**
- `FamilyMembersControllerCannotAutoLinkExistingUserTest` — assert that adding an existing user as spouse leaves their `spouse_id`, `marital_status`, `annual_employment_income`, address fields untouched.
- `FamilyMembersControllerCreatesPendingSpousePermissionTest` — assert a `pending` SpousePermission row is created on add; `accepted` requires explicit accept call.
- `FamilyMembersControllerCannotOverwriteSpouseIncomeTest` — pin the income-tampering hole closed.

### M-/L- candidates

- **M-1** `TrustController::createTrust/updateTrust` — `initial_value`, `current_value`, `discount_amount`, `loan_amount`, `sum_assured`, `annual_premium`, `retained_income_annual`, `loan_interest_rate` all accept `numeric|min:0` with **no `max:`**. Currency overflow → service-layer crashes / fake high IHT views. Should use `ValidationLimits::currencyRules()` / `percentageRules()`.
- **L-1** `IHTController::storeOrUpdateIHTProfile` — same `home_value` / `nrb_transferred_from_spouse` overflow issue.
- **L-2** `GiftingController::calculateDiscountedGiftDiscount` + `TrustController::calculateDiscountedGiftDiscount` — same `gift_value|min:1` / `annual_income|min:0` overflow.
- **L-3** `TrustController::createTrust/updateTrust` — `beneficiaries`, `trustees`, `purpose`, `notes` accept `nullable|string` with **no `max:`**. 100 MB string DoS candidate.
- **L-4** `TrustController` uses inline `$request->validate()` — convention drift (most pack controllers use FormRequest classes). Cosmetic; aligns with refactor backlog.
- **L-5** `FamilyMembersController::index` + `handleSpouseCreation` — `Log::info()` lines include user names + emails. Mild PII in INFO-level logs. Should be moved to `Log::debug()` or have email obscured.
- **M-2 candidate** `ReferralController::sendInvitation` — `throttle:10,1` allows 10 invites/minute = 14,400/day. If `ReferralService::sendInvitation` has no per-target dedup, this is a spam vector against arbitrary email addresses. Verification deferred (service-level review, G-4-c).

### Pending — full sweep

The top-10 sensitivity sample is now complete. Remaining sweep (lower priority):

- Cross-cutting pattern check: 66 controllers with inline `$request->validate()` — most are low-risk module CRUD; a sample of 10 would suffice for confidence.
- 104 Form Requests — spot check 10 random ones for `authorize(): true` (default) without a complementary policy check in the controller. Most controllers do `where('user_id', $user->id)` scoping which is sufficient.

## Next steps in this audit (carry-over to next working session)

1. Read GDPRController (especially `executeErasure` and `requestExport` — destructive/exfiltration paths).
2. Read all 7 packs/country-gb/Estate controllers (financial integrity + beneficiary-tampering risk).
3. Read AiChatController + AiAuditController (prompt injection, conversation data isolation).
4. Read SpousePermissionController + FamilyMembersController (cross-account access).
5. Read ReferralController (fraud paths).
6. Sample 5 Form Requests at random from `packs/country-gb/src/Http/Requests/` — verify `authorize()` not just `return true`.
7. Compile final HIGH + MEDIUM list and apply HIGH fixes inline before commit.

## Status

- Inventory ✅
- Pattern triage ✅
- Top-10 ranking ✅
- **10/10 top-sensitivity controllers audited** (Admin, Advisor, Document, GDPR, IHT, Gifting, Will, WillDocument, LPA, Trust, AiChat, SpousePermission, FamilyMembers, Referral) — **3 HIGH identified** (H-1 admin write, H-2 legacy GDPR erasure, H-3 spouse auto-link)
- **HIGH fixes — all closed (2026-05-13 session 3):**
  - **H-1** ✅ — `routes/api.php` admin group split into read (no `mfa.verified`) + write (with `mfa.verified`). 17 admin write endpoints now MFA-gated. 39 new pinning tests in `tests/Feature/Admin/AdminWriteEndpointsRequireMfaTest.php`.
  - **H-2** ✅ — 4 legacy GDPR erasure routes deleted (`/erasure`, `/erasure/status`, `/erasure/{id}/confirm`, `/erasure/{id}/cancel`). Controller methods retained with `@deprecated` docblocks for inert admin tooling use only. 8 new pinning tests in `tests/Feature/GDPR/LegacyGdprErasureRoutesAreUnroutableTest.php`.
  - **H-3** ✅ — `FamilyMembersController::handleSpouseCreation` (existing-account branch) rewired: no longer auto-links `spouse_id`, no longer auto-sets `marital_status`, no longer overwrites `annual_employment_income` / address, no longer auto-accepts bidirectional `SpousePermission`. Now creates a pending `SpousePermission` row + sends a new `SpouseDataSharingRequest` email to the invitee. `SpousePermissionController::accept` extended to finalise the linkage atomically (sets spouse_id on both, creates reciprocal accepted permission + reciprocal `FamilyMember`). 13 new pinning tests in `tests/Feature/Api/FamilyMembersControllerSpouseConsentTest.php`. 2 new files: `app/Mail/SpouseDataSharingRequest.php` + `resources/views/emails/spouse-data-sharing-request.blade.php`.
- **MEDIUM/LOW logged to triage backlog** (`May/May12Updates/triage-backlog.md`):
  - E-16 (was M-1) — TrustController currency rules
  - E-17 (L-1) — IHTController profile overflow
  - E-18 (L-2) — Gifting/Trust DGT overflow
  - E-19 (L-3) — Trust text fields no `max:`
  - E-20 (L-4) — TrustController inline validate convention drift
  - E-21 (L-5) — FamilyMembers PII in INFO logs
  - E-22 (M-2 candidate) — Referral throttle 10/min spam vector
  - E-23 (cosmetic) — SpouseAccountLinked subject copy review
- Pest serial baseline post-fixes: **2975 passing** (+60 new tests for slice 3, 0 regressions, 1 skipped — pre-existing).
