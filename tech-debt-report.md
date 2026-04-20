# Tech Debt Report — WS 1.4d SA Retirement Frontend (2026-04-20 session 2)

**Files analysed:** 39 (controller, 8 form requests, 4 resources, middleware, agent patch, migration, router, Vuex module, axios service, 14 Vue components + view, 21 Pest tests across 3 files, 2 planning docs)
**Issues found:** 4 (0 critical, 3 warnings, 1 suggestion)
**Severity breakdown:** 0 critical, 3 warning, 1 suggestion
**Bill of health:** Clean on hex colors, banned palette tokens, hardcoded SARS calculation values, Vue anti-patterns (`v-if`+`v-for`, missing `:key`), strict_types coverage, debug leftovers, console.log leftovers, Vuex response-shape consistency, button types. All PHP has `declare(strict_types=1)`. All controller methods have return type hints. All Vuex actions correctly destructure `{ data }` from service responses.

---

## Critical Issues

None. 🟢

---

## Warnings

### W1 — Modal accessibility gap (ZaRetirementFundForm, ZaContributionModal)

- **Files:** `resources/js/components/ZA/Retirement/ZaRetirementFundForm.vue`, `resources/js/components/ZA/Retirement/ZaContributionModal.vue`
- **Category:** Convention Violations (accessibility)
- **What's wrong:** Both modals lack `role="dialog"`, `aria-modal="true"`, Escape-to-close keyboard handler, and focus-trap logic. Keyboard-only users can tab out of the modal into background content; screen readers don't announce the modal as a dialog.
- **Suggested fix:** Add the ARIA attributes to the modal root div and wire up a `@keydown.esc="$emit('close')"` handler. Focus trap requires cycling focus within the dialog on Tab/Shift+Tab — the existing WS 1.3c modals have the same gap (not a regression this session introduced), so a shared `DialogContainer` wrapper is the long-term fix. Check `resources/js/components/ZA/Investment/ZaInvestmentForm.vue` for the established pattern.
- **Severity:** Warning — doesn't block sighted-mouse users but fails WCAG 2.1 AA focus management.

### W2 — Conversion logic duplicated across 14 components (Math.round × 100)

- **Files:** 9 components with 15 occurrences of `Math.round((this.form.X || 0) * 100)` — `ZaCompulsoryAnnuitisationCard:95-97`, `ZaSavingsPotWithdrawalCard:116-117`, `ZaSection11fReliefCalculator`, `ZaContributionModal`, `ZaRetirementFundForm`, `ZaLivingAnnuitySlider`, `ZaLifeAnnuityQuote`, `ZaReg28AllocationForm`.
- **Category:** Duplicate Code (cross-file)
- **What's wrong:** `resources/js/utils/zaCurrency.js:34` already exports `toMinorZAR(valueMajor)` for exactly this conversion. None of the new components use it; they inline the `Math.round(x * 100)` pattern. If the conversion ever needs to handle edge cases (NaN, negative, locale decimal separator), we'd have to patch 15 sites.
- **Suggested fix:** Replace every `Math.round((this.form.X || 0) * 100)` with `toMinorZAR(this.form.X || 0)`. Import from `@/utils/zaCurrency`, or add to the `zaCurrencyMixin` if not already there.
- **Severity:** Warning — inconsistency, not a bug.

### W3 — Hardcoded SARS values in user-facing prose (not calculations)

- **Files:**
  - `resources/js/components/ZA/Retirement/ZaCompulsoryAnnuitisationCard.vue:7` — prose "R165 000 de-minimis threshold"
  - `resources/js/components/ZA/Retirement/ZaLivingAnnuitySlider.vue:6,41,42` — prose "2.5% and 17.5% of capital", slider min/max labels
- **Category:** Convention Violations (CLAUDE.md Rule 3: no hardcoded tax values)
- **What's wrong:** The backend returns `de_minimis_threshold_minor` in the compulsory-apportion response (already used correctly in the result card badge). The slider band comes from `annuity.living.drawdown_min_bps` / `max_bps` config. The descriptive prose duplicates those values as string literals. If SARS changes the threshold or Regulation 39 amends the band, these strings become stale without any test or usage signalling the drift.
- **Suggested fix:** Drop the specific values from descriptive copy, e.g. "If the commutable total is under the de-minimis threshold (shown below), full commutation is allowed." Slider copy can say "between the minimum and maximum drawdown rates".
- **Severity:** Warning — graceful drift, but rule-adjacent.

---

## Suggestions

### S1 — ZaRetirementController approaching soft file-length limit (445 lines)

- **File:** `app/Http/Controllers/Api/Za/ZaRetirementController.php` (445 lines)
- **Category:** Complexity & Maintainability
- **What's wrong:** Single file hosts 13 endpoint methods for 4 loosely-coupled concerns (fund CRUD, contributions, savings-pot, annuities, Reg 28). Not over the 500-line soft limit, but any v1.1 additions (Section 10C tracker threading, Reg 28 look-through) will push it over. The WS 1.3c precedent was to split Investment vs Exchange Control; retirement kept them together because the user-facing surface is one tabbed page — the right v1 call.
- **Suggested fix:** None now. Revisit if v1.1 adds >50 more lines — extract `ZaReg28Controller` into its own file keeping the same route prefix.
- **Severity:** Suggestion — keep an eye on it.

---

## Summary

- **Top 3 impactful:** W1 (accessibility — ships to real users), W2 (easy win for consistency), W3 (tax value drift risk).
- **Nothing blocking commit or merge.** All tests green (2,747 passing), all Playwright flows verified, no security or correctness issues. Auto-fix was NOT applied per instructions.
- **Handover note:** Defer W1 into a dedicated accessibility pass across ALL existing ZA modals (Savings, Investment, ExCon, Retirement). W2 + W3 can ride along with the next ZA frontend workstream (WS 1.5b Protection) since all components there will need the same conversion util and rule-compliant copy.

---
*Generated by tech-debt-session skill · 2026-04-20 session 2 wrap*
