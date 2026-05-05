---
type: playwright-smoke
date: 2026-05-05
session: 1
workstream: WS 1.5b SA Protection
task: 21 — Playwright smoke
test_user: za-protection-test@example.com / password
servers: Laravel :8001, Vite :5173 (5174 was held by sibling fynla repo)
---

# WS 1.5b Task 21 — SA Protection Playwright smoke

3 scenarios run end-to-end as `za-protection-test@example.com` (R 480 000 income, 2 dependants, R 800 000 mortgage seeded — actual fixture inflated to 4 dependants + R 1.6M mortgages because TestUsersSeeder ran twice during DB recovery — see findings § F4 below).

## Result

**All 3 scenarios PASSED at the user-journey level.** Four real defects surfaced and are noted for follow-up.

| # | Scenario | Result |
|---|---|---|
| 1 | Add life policy + spouse beneficiary end-to-end | ✓ PASS |
| 2 | Coverage-gap missing-inputs deep-links | ✓ PASS |
| 3 | Estate-duty beneficiary warning badge | ✓ PASS (for `estate` type — see F2) |

---

## Scenario 1 — Add life policy + spouse beneficiary

Steps actually clicked / filled / submitted in Playwright:

1. Navigated to `/za/protection`. Empty state visible: "No policies yet. Click Add policy to start."
2. Clicked **Add policy** → modal opened with all 6 ASISA-canonical SA cover types in the Policy type combobox (Life cover, Whole-of-life, Dread disease — Severity-tiered SCIDEP A/B/C/D, Lump-sum disability, Income protection, Funeral cover).
3. Filled: Provider=`Old Mutual`, Policy number=`OM-LIFE-001`, Cover amount=`2000000`, Premium=`850`, Premium frequency=`Monthly` (default), Start date=`2026-05-05` (default).
4. Clicked **Save** → policy appeared in table with formatting `R 2 000 000,00` / `R 850,00 / monthly` (correct en-ZA NBSP-grouping currency display).
5. Switched to **Beneficiaries** tab → URL `?tab=beneficiaries`. Policy panel rendered with cover header "Old Mutual — Life cover · Cover R 2 000 000,00".
6. Clicked **+ Add beneficiary** → row appeared with Type combobox defaulting to `Spouse` and Identity-number cell **disabled** (correct — spouse doesn't need ID).
7. Filled Name=`Thandi Test`, Relationship=`Wife`, Allocation %=`100`. Clicked **Save beneficiaries**. Footer row showed `Sum: 100.00`.

Verified DB state via tinker:
```
policy id=25 provider=Old Mutual cover_minor=200000000
beneficiary type=spouse name=Thandi Test relationship=Wife allocation_percentage=100.00 is_dutiable=0
```

`is_dutiable=0` correct because spouse beneficiaries are deductible under Estate Duty Act s4(q).

**UX issue (F3):** after saving beneficiaries, the Policies-tab beneficiary count column still showed `0` until the page was reloaded. The Policies tab doesn't refetch when the user switches back from Beneficiaries.

## Scenario 2 — Coverage-gap missing-inputs deep-links

Initial pass with full data:

1. Navigated to `?tab=coverage-gap`. All 4 categories rendered with calculated shortfalls:
   - Life cover: Recommended R 6 400 000,00 / Existing R 2 000 000,00 / Shortfall R 4 400 000,00
   - Income protection: Recommended R 360 000,00 / Existing R 0,00
   - Dread disease: Recommended R 960 000,00
   - Funeral cover: Recommended R 150 000,00
   - "Inputs used: annual income R 480 000,00, outstanding debts R 1 600 000,00, dependants 4."
2. Clicked **Why this number?** disclosure on Life cover. Expanded text: *"Capitalise 4 dependants at 10× annual income plus outstanding debts. Existing cover **200000000** applied."* — see F1.

Then triggered the missing-inputs branch:

3. Cleared `users.annual_employment_income` via tinker.
4. Reloaded `?tab=coverage-gap`. 3 of 4 categories now showed empty-state **"We need more data to compute this gap."** with `→ Add your annual income` deep-link → href `/income`.
5. Funeral cover (income-independent) still calculated R 150 000,00 — correct.
6. Clicked the deep-link. Browser landed on `/income`. Income link works.
7. Restored income to R 480 000.

## Scenario 3 — Estate-duty beneficiary warning badge

1. Beneficiaries tab. Changed the existing beneficiary's Type combobox from `Spouse` → `Nominated individual`. Identity-number field became enabled (correct — nominees need ID). Filled `8501015800089`. Saved.
2. DB check: `type=nominated_individual is_dutiable=0` — **wrong, see F2.**
3. Changed Type to `Estate`. Saved.
4. DB check: `type=estate is_dutiable=1` ✓ (mutator handles `estate` correctly).
5. Reloaded the Beneficiaries tab. **Warning badge rendered on the policy panel header**: *"Estate nomination — dutiable under s3(3)(a)(ii)"* ✓.

The badge surfaces correctly when `is_dutiable` is set.

---

## Findings (4 real defects)

### F1 — "Why this number?" narrative renders raw minor-cents

**File:** likely `packs/country-za/src/Services/Protection/CoverageGapEngine*.php` or whichever builds the rationale string.
**Symptom:** "Existing cover **200000000** applied." instead of "Existing cover R 2 000 000,00 applied."
**Severity:** Low — cosmetic, but undermines trust in the figure.
**Fix sketch:** route the existing-cover value through the same en-ZA currency formatter used elsewhere on the page.

### F2 — `is_dutiable` mutator incomplete

**File:** `packs/country-za/src/Models/ZaProtectionBeneficiary.php:49-53`
**Current behaviour:**
```php
public function setBeneficiaryTypeAttribute(string $value): void
{
    $this->attributes['beneficiary_type'] = $value;
    $this->attributes['is_dutiable'] = ($value === 'estate');
}
```
**Defect:** under SA Estate Duty Act s3(3)(a)(iA), policy proceeds paid to a nominated beneficiary OTHER than spouse are deemed property of the deceased estate. Currently:
- `estate` → dutiable ✓
- `spouse` → not dutiable ✓ (deductible under s4(q))
- `nominated_individual` → **should be dutiable** but mutator marks not dutiable ❌
- `testamentary_trust` → **should be dutiable** but mutator marks not dutiable ❌
- `inter_vivos_trust` → debatable; usually dutiable unless trust funded the policy ❌

**Severity:** Medium — affects WS 1.6 Estate calculations that consume this flag.
**Fix sketch:** flip the boolean to a whitelist: `is_dutiable = !in_array($value, ['spouse', 'inter_vivos_trust'], true)` (or similar after a tax-rule decision on inter-vivos).

### F3 — Beneficiary count on Policies tab doesn't auto-refresh

After saving beneficiaries on `?tab=beneficiaries`, returning to `?tab=policies` shows the stale count column (`0`) until full page reload.
**Severity:** Low UX papercut.
**Fix sketch:** dispatch `policies/fetchAll` (or equivalent) on tab-switch, or invalidate the cached count after a beneficiaries save.

### F4 — `TestUsersSeeder` is not idempotent for `FamilyMember` / `Mortgage` rows

`TestUsersSeeder.php:170,173-174` use `Factory->for($user)->create()` for dependants, properties, and mortgages. Re-running the seeder duplicates these rows because there's no `updateOrCreate` keying. Discovered when the test user ended up with 4 dependants + R 1.6M of mortgage debt (vs the 2 + R 800k advertised).
**Severity:** Low for tests, but produces misleading dashboard numbers when devs re-seed manually.
**Fix sketch:** wrap the related-record creation in a `firstOrCreate` keyed on `(user_id, role/type)` or pull the test user out of TestUsersSeeder into a separate idempotent fixture that runs once.

---

## Side effects of running this smoke

Two real seeder bugs were fixed *during* this Playwright run because they blocked the smoke. Both are committed in the next commit (`fix(seeders)` after `fix(tech-debt)`):

1. **`JurisdictionSeeder` was orphan-namespaced.** Lived at `core/database/seeders/JurisdictionSeeder.php` under namespace `Database\Seeders\` — but `composer.json` autoload only points `Database\Seeders\` at `database/seeders/`, so the seeder was unreachable. Result: GB jurisdiction was never seeded after a Pest `RefreshDatabase` wipe. Moved file to `database/seeders/JurisdictionSeeder.php`, removed orphan, registered in `DatabaseSeeder` PHASE 1 + `seedRequiredDataOnly` (before any seeder that references `jurisdiction_id`).
2. **`./dev.sh` killed any process on `:8001` / `:5174` indiscriminately** and pinned Vite to 5174 via `strictPort: true`. When the sibling UK `fynla` repo's Vite was on 5174, ours died silently and the wrong JS bundle was served against our Laravel API (UK Vuex modules with no `jurisdiction` / `zaProtection` modules — the SA sidebar never appeared). Rewrote `dev.sh` with a `pick_port()` function that:
   - Checks the listening PID's `cwd` via `lsof -p $PID -d cwd -Fn`
   - Kills only if cwd is inside this repo (ours, stale)
   - Falls back to the sibling-preferred port (8000 / 5173) otherwise
   - Errors out cleanly if both ports are held by sibling work
   - Exports `VITE_PORT` so `vite.config.js` (now reads `parseInt(process.env.VITE_PORT) || 5174`) honours the chosen port.

Outcome: International prefers 8001/5174, UK prefers 8000/5173, but either repo can run on either pair without stomping the other. The "100s of vite processes" risk that was flagged in the brief is now structurally prevented.

---

## Files changed in this smoke session

- `database/seeders/JurisdictionSeeder.php` — new, replaces orphan
- `core/database/seeders/JurisdictionSeeder.php` — deleted (orphan)
- `database/seeders/DatabaseSeeder.php` — register `JurisdictionSeeder` in PHASE 1 + `seedRequiredDataOnly` (already had `ZaTaxConfigurationSeeder` from earlier this session)
- `dev.sh` — `pick_port()` + port-fallback logic
- `vite.config.js` — `port` now reads `VITE_PORT` env var (default 5174)
- `May/May5Updates/playwright-task-21-2026-05-05.md` — this report

No `app/`, `packs/`, or `resources/js/` source code was changed during the smoke. The 4 findings (F1–F4) are recorded for follow-up rather than fixed in-scope.

## Next session

- Decide whether to ship the 4 findings now or roll into WS 1.6 Estate (which consumes `is_dutiable`).
- Resume Phase 1 SA frontend queue: WS 1.6b SA Estate, WS 1.7 SA personas + onboarding, WS 1.8 Localisation/FAIS/POPIA copy.
- Decide on the deferred DialogContainer follow-up PR (5 ZA modals).
