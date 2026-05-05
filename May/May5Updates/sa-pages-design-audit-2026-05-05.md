---
type: design-audit
date: 2026-05-05
session: 1
scope: 5 SA module pages (Savings, Investment, Exchange Control, Retirement, Protection)
reference: fynlaDesignGuide.md v1.3.0 + UK module pages (canonical pattern)
status: AUDIT ONLY — no code changed
---

# SA Pages — Design-System Compliance Audit

This report compares the 5 SA module pages against `fynlaDesignGuide.md` v1.3.0 and the UK module-page conventions, after WS 1.2b → 1.5b shipped them across 18–21 April. Audit is screenshot-based + source-read; no code was changed.

Side-by-side screenshots in this folder:
- `audit-uk-protection.png` — canonical UK reference
- `audit-za-savings.png`, `audit-za-investments.png`, `audit-za-retirement.png`, `audit-za-exchange-control.png`, `audit-za-protection.png` — the 5 SA pages

## Headline finding

**Earlier I claimed all SA pages were missing chrome. That was wrong.** 4 of 5 SA pages do use `AppLayout` and render with full sidebar / top nav / footer. **Only `/za/protection` is bare** — its view file (`resources/js/views/ZA/ZaProtectionDashboard.vue`) wraps in a plain `<div>` instead of `<AppLayout>`. That's a regression from the WS 1.2b/1.3c/1.4d pattern, introduced by WS 1.5b on 21 April.

The remaining defects across all 5 pages are real but smaller-scope:

| Severity | Count |
|---|---|
| Critical (broken page) | 1 — `/za/protection` missing AppLayout |
| Major (deviates from canonical UK module pattern, breaks visual coherence) | 6 |
| Minor (palette / typography / micro-copy) | 8 |
| **Total defects** | **15** |

A full rework PR can fix every page and align them with the UK pattern. Estimate: 1 day's work, no backend changes, ~12 files touched.

---

## Per-page summary

### Page-level chrome matrix

| Page | `AppLayout` wrapper | Container width | `module-gradient` bg | `ModuleStatusBar` | `ProfileCompletenessAlert` | `ModuleLifeEvents` |
|---|---|---|---|---|---|---|
| `/protection` (UK reference) | ✓ | `max-w-7xl` | ✓ | ✓ | ✓ | ✓ |
| `/za/savings` | ✓ | **`max-w-6xl`** ⚠ | ❌ | ❌ | ❌ | ❌ |
| `/za/investments` | ✓ | **`max-w-6xl`** ⚠ | ❌ | ❌ | ❌ | ❌ |
| `/za/exchange-control` | ✓ | **`max-w-6xl`** ⚠ | ❌ | ❌ | ❌ | ❌ |
| `/za/retirement` | ✓ | `max-w-7xl` | ❌ | ❌ | ❌ | ❌ |
| **`/za/protection`** | **❌** | **none (no wrapper)** | ❌ | ❌ | ❌ | ❌ |

UK module pages all use the same shared chrome stack. The SA pages skipped all of it. This is what makes the SA pages feel "different" — even when AppLayout is present, the *module-level* chrome is absent.

---

## Defects by severity

### CRITICAL — 1

#### C1. `/za/protection` has no `AppLayout` wrapper
**File:** `resources/js/views/ZA/ZaProtectionDashboard.vue:1-21`
**Current code:**
```vue
<template>
  <div class="za-protection-dashboard">
    <header class="mb-6">
      <h1 class="text-3xl font-black text-horizon-500">Protection</h1>
      ...
```
**Should be:**
```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      ...
```
**Impact:** the entire page renders with no sidebar, no top nav, no trial banner, no user dropdown, no footer, no eggshell page background. Every other authenticated page in the app has this chrome. **This is the biggest visual offender** and the page that triggered the user complaint.
**Fix:** wrap the existing `<div>` in `<AppLayout>` and import it. ~5 lines of code.

### MAJOR — 6

#### M1. SA pages don't use `module-gradient` background
**Files:** all 5 SA dashboards.
**UK pattern (`Protection/ProtectionDashboard.vue:3`):** `<div class="protection-dashboard module-gradient py-2 sm:py-6">`
**SA pages:** plain `<div>` with no background pattern.
**Impact:** UK pages have a subtle module-coloured gradient that brands each module. SA pages render flat eggshell. Visual hierarchy diverges.
**Fix:** add `module-gradient` (and pick a CSS variable / token for the gradient stops per `fynlaDesignGuide.md` § 4 "Gradients & Patterns").

#### M2. SA pages skip `ModuleStatusBar`, `ProfileCompletenessAlert`, `ModuleLifeEvents`
**Files:** all 5 SA dashboards.
**UK pattern:** every UK module dashboard imports these from `@/components/Shared/`. They render: a category pill at the top ("Family" / "Cash" / etc.), a profile-completeness banner ("7 of 9 items complete · What powers this view"), and a module-relevant life-events section.
**SA pages:** none present. Just bare H1 + body.
**Impact:** users get a more abrupt, less-contextual page. Profile completeness and life events are key drivers of the "what should I do next" UX and are missing on the SA side.
**Fix:** add these three shared components to each SA dashboard (or create SA equivalents if the data shapes differ — `ProfileCompletenessAlert` should work as-is since it reads from the same auth user).

#### M3. Container widths inconsistent — 3 of 5 SA pages use `max-w-6xl` instead of `max-w-7xl`
**Files:** `ZaSavingsDashboard.vue:3`, `ZaInvestmentDashboard.vue:3`, `ZaExchangeControlDashboard.vue:3` use `max-w-6xl`. `ZaRetirementDashboard.vue:3` uses `max-w-7xl` (correct). `ZaProtectionDashboard.vue` has no container (broken — see C1).
**UK pattern:** all UK module pages use `max-w-7xl mx-auto`.
**Impact:** SA pages render narrower than UK pages — content shifts left when you navigate between modules and the sidebar's relative position changes.
**Fix:** change all SA dashboards to `max-w-7xl mx-auto`.

#### M4. `/za/protection` tab strip is hand-rolled
**File:** `ZaProtectionDashboard.vue:7-15` and `components/ZA/Retirement/ZaRetirementTabs.vue` — both hand-roll their tab UI rather than reuse a shared `Tabs` component (none exists in the codebase, so this is a "design-system gap" finding rather than a "wrong import" finding).
**UK pattern:** UK module dashboards use module-local tab components like `Investment/DiversificationTab.vue` etc., but the *strip* itself is also hand-rolled inline. So this is not an SA-specific bug — but it's an opportunity to extract a shared `Tabs.vue` and consume it from both UK and SA pages.
**Impact:** any future tab-style change has to be hand-edited across at least 6 places. Minor coherence risk.
**Fix:** extract `resources/js/components/Shared/Tabs.vue` and refactor all 6 consumers. Out of scope for the SA rework if you want to keep blast radius small.

#### M5. SA pages don't render policies / accounts / funds inside a card
**Files:** `ZaPoliciesTable.vue:17-39`, `ZaInvestmentAccountsList.vue` likely similar.
**UK pattern (`Protection/ProtectionDashboard.vue:60-65`):**
```vue
<div class="bg-white rounded-lg border border-light-gray p-6">
  <CurrentSituation @add-policy="..." />
</div>
```
**SA pattern:** the table renders directly on the page background, no card.
**Impact:** harder to scan, less visual grouping, and the design guide specifies card patterns (§ 6.1) for primary content blocks.
**Fix:** wrap each SA list/section in `bg-white rounded-lg border border-light-gray p-6` (or extract a `Card.vue` shared component while we're at it).

#### M6. SA Protection uses `confirm()` (native browser dialog) for delete confirmation
**File:** `ZaPoliciesTable.vue:76` — `if (!confirm(\`Delete ${p.provider} ${this.typeLabel(p.product_type)}?\`)) return;`
**UK pattern:** all destructive UK actions use the in-app modal pattern (`SpouseSuccessModal`, custom delete confirmation modals, etc.). Native `confirm()` is jarring on web and shows the localhost URL on iOS/macOS.
**Impact:** breaks the visual continuity of the app, and is an iOS-app accessibility concern.
**Fix:** replace `confirm()` with a `ConfirmModal` component (one likely exists somewhere — needs a quick grep — otherwise add a shared `ConfirmModal.vue`).

### MINOR — 8

#### m1. Typography weight: SA pages use `font-black` (900); some UK pages use `font-bold` (700)
Per design guide § "Type Scale": **H1 weight = 900 (Black)**. SA pages get this right (`text-3xl font-black`). The UK Protection page uses `text-xl sm:text-2xl lg:text-3xl font-bold` — that's responsive but **wrong on weight**. So this defect is on the UK side, not SA. Leaving it noted because a future "responsive H1" refactor needs to keep weight at 900.

#### m2. H1 size: SA uses `text-3xl` (30px); design guide spec is 36px
Per § "Type Scale" H1 = 2.25rem (36px) = Tailwind `text-4xl`. SA pages use `text-3xl` (1.875rem = 30px). Off by one Tailwind step. UK pages also use `text-3xl` at the largest breakpoint. Both should be `text-4xl`.

#### m3. ZA Exchange Control H1 uses `text-horizon-700` instead of `text-horizon-500`
**File:** `ZaExchangeControlDashboard.vue:5` — `<h1 class="text-3xl font-black text-horizon-700">`
**Spec (`fynlaDesignGuide.md` § "Text Colors"):** Primary text (headings) → `text-horizon-500` (#1F2A44).
**Other 4 SA pages:** correctly use `text-horizon-500`.
**Impact:** Exchange Control's H1 is slightly darker (#020617 vs #1F2A44) — barely visible to a casual user but breaks token consistency.
**Fix:** swap `text-horizon-700` → `text-horizon-500`.

#### m4. ZA Protection sub-heading uses `text-horizon-300`; the rest use `text-sm text-horizon-500` or similar
**File:** `ZaProtectionDashboard.vue:5` — `<p class="text-horizon-300 mt-2">Policies, coverage gap, and beneficiaries.</p>`
**ZA Exchange Control (correct):** `<p class="text-sm text-horizon-500 mt-1">Calendar year ...`
**Impact:** Protection's sub-heading is muted (horizon-300 = grey) while Exchange Control's is full-strength horizon-500 + smaller. Two different sub-heading patterns across the SA section.
**Fix:** pick one — recommend the `text-sm text-horizon-500` pattern matching Exchange Control. Or formalise a `<PageSubheading>` shared component.

#### m5. ZA Protection tab strip uses `border-savannah-100` for inactive border
**File:** `ZaProtectionDashboard.vue:7` — `<div class="tabs border-b border-savannah-100 mb-6">`
**Spec § "Border Colors":** Default → `border-horizon-200`. Hover → `border-horizon-300` or `border-raspberry-300`. Focus → `border-raspberry-600`.
**Impact:** `savannah-100` is a sand-tinted off-white, not in the formal border palette. Off-spec.
**Fix:** swap to `border-horizon-200`.

#### m6. ZA Retirement labels have full names + acronyms in "(RA), (PF), (PvF)" parentheses pattern
**File:** rendered text on `/za/retirement`: "Retirement Annuity (RA), Pension Fund (PF), Provident Fund (PvF), and Preservation Fund accounts."
**CLAUDE.md Rule 10:** "Other SA acronyms (RA, PF, PvF, ...) must still be spelled out on first use."
**Verdict:** technically compliant — the full name precedes the acronym. But the parenthetical introduction sets up the rest of the page to use bare "RA" / "PF" / "PvF" later, which is what Rule 10 wants to avoid in user-facing UI. Spot-check the rest of the retirement components for bare-acronym usage.
**Fix:** audit `components/ZA/Retirement/*` for any later `RA` / `PF` / `PvF` / `Reg 28` standalone usage and spell them out OR move the acronyms to a glossary/help-tooltip rather than parenthetical introduction. Same audit for `SDA`, `FIA`, `AIT`, `SARS`, `SARB` on Exchange Control.

#### m7. ZA Investments "Capital Gains Tax — what-if" uses em-dash style consistent with UK; ZA Retirement also uses em-dash; ZA Exchange Control uses em-dash
Consistent within SA. UK pages tend to use ":" in headings. Either is fine; just note the SA section made a stylistic choice. Decide if you want to align SA with UK ":" pattern.

#### m8. ZA Investments "Calculate" CTA renders disabled (raspberry-300 background) by default
**Visible in `audit-za-investments.png`:** the "Calculate" button under "Capital Gains Tax — what-if" is greyed out — disabled state, presumably because no realised-gain has been entered.
**Spec § "Primary Palette":** raspberry-300 (#F472B6) is listed as **"Disabled states"** — so this *is* the correct token. Compliant. Leaving noted because the previous audit pass might have flagged it as a bug.

---

## Per-page deep dive

### `/za/savings` — Tax-Free Savings
**Verdict:** mostly aligned, looks polished.

What works:
- `AppLayout` chrome ✓
- Two-card "Annual / Lifetime allowance" layout matches UK's two-card patterns
- Raspberry "Record contribution" / "Assess adequacy" CTAs ✓
- ZAR formatting "R 46 000,00" with NBSP grouping ✓
- en-ZA decimal comma ✓
- Card backgrounds match `bg-white rounded-lg border` ✓

Defects: M2 (no shared chrome stack), M3 (`max-w-6xl` instead of `max-w-7xl`), m2.

### `/za/investments` — Investments
**Verdict:** aligned, polished, but acronyms-heavy.

What works:
- AppLayout, cards, raspberry "Add account" CTA ✓
- Three-up wrapper-type cards (TFSA / Discretionary / Endowment) — good info hierarchy ✓
- Form inputs styled per UK pattern (border, rounded, labels above) ✓
- Disabled "Calculate" button uses raspberry-300 (correct disabled token, see m8)
- Footer help text "No South African investment accounts yet. Click 'Add account' above to record one." — friendly, on-brand

Defects: M2, M3, m2, m6 (audit `SDA` / `FIA` / etc. usage downstream).

### `/za/exchange-control` — Exchange Control
**Verdict:** aligned, with two minor token slips.

What works:
- AppLayout, two-up SDA/FIA gauge cards, raspberry "Record transfer" CTA ✓
- Acronyms spelled out on first use: "Single Discretionary Allowance (SDA)", "Foreign Investment Allowance (FIA)", "South African Revenue Service Approval for International Transfer (AIT)", "South African Reserve Bank (SARB)" ✓ (Rule 10 compliant)

Defects: M2, M3, m2, **m3 (H1 uses `text-horizon-700` not `text-horizon-500`)**.

### `/za/retirement` — Retirement
**Verdict:** the most polished SA page; aligned.

What works:
- AppLayout, `max-w-7xl` (the only SA page that gets the width right) ✓
- Three-up summary card "Total balance / Section 11F annual cap / Funds recorded" ✓
- Tab strip "Accumulation / Decumulation / Compliance" with raspberry underline indicator ✓
- Multiple white cards: "Retirement funds", "Section 11F tax relief — what-if", "Savings-Pot withdrawal simulator" ✓
- "Add fund", "Calculate relief", "Simulate" CTAs all present ✓
- "Section 11F" reference is regulatory — not an acronym needing expansion ✓

Defects: M2, m2, m6 (audit downstream `RA` / `PF` / `PvF` usage), m7 (em-dash pattern — consistency choice).

### `/za/protection` — Protection
**Verdict:** broken. Everything from C1, M1–M6 hits this page.

What works:
- ZAR formatting in the table ✓
- Raspberry "Add policy" CTA ✓
- Tab strip with raspberry underline (despite m5 border-token slip) ✓

Defects: **C1**, M1, M2, M3, M5, **M6 (native `confirm()` dialog)**, m2, m4 (sub-heading uses `text-horizon-300`), m5 (`border-savannah-100`).

The bug found in the smoke (`is_dutiable` mutator, "Why this number?" raw cents narrative, Policies-tab count not refreshing) are functional and out of scope for *this* design audit — captured separately in `playwright-task-21-2026-05-05.md`.

---

## Recommendations

If you want to ship a single rework PR fixing this end-to-end:

1. **Wrap `/za/protection` in `AppLayout`** (fixes C1) — 5 lines, immediate visible impact.
2. **Standardise container width to `max-w-7xl mx-auto`** across all 5 SA dashboards (fixes M3) — 4 one-line edits.
3. **Add the shared chrome stack** (`module-gradient` bg, `ModuleStatusBar`, `ProfileCompletenessAlert`, optional `ModuleLifeEvents`) to all 5 SA dashboards (fixes M1 + M2) — biggest visual lift, ~5 components × 5 pages = 25 imports + appropriate layout adjustments.
4. **Wrap each SA list/section in a `bg-white rounded-lg border border-light-gray p-6` card** (fixes M5) — pure JSX edits, no logic.
5. **Replace `confirm()` with a shared `ConfirmModal`** in `ZaPoliciesTable.vue` (fixes M6) — also worth doing in any other SA component that does the same.
6. **Token sweep** — `text-horizon-700` → `text-horizon-500` on Exchange Control H1, `border-savannah-100` → `border-horizon-200` on ZA Protection tab border, `text-horizon-300` → `text-sm text-horizon-500` on ZA Protection sub-heading (fixes m3, m4, m5).
7. **Acronym sweep** — grep `components/ZA/Retirement/*` for bare `RA`, `PF`, `PvF`, `MPAA` (etc.) and spell them out on first use (m6 follow-up).
8. **(Out of scope)** Extract shared `Tabs.vue` and `ConfirmModal.vue` components — these are pre-existing design-system gaps, not SA-specific.

Steps 1–6 land in one PR, no backend changes, ~12 files touched. Step 7 is a separate spot-fix sweep across Retirement components. Step 8 is its own small refactor PR.

## What this report does NOT cover

- Functional behaviour (covered separately in `playwright-task-21-2026-05-05.md`).
- The internal styling of the policy / account / fund **modals** (PolicyFormModal, ZaInvestmentForm, ZaRetirementFundForm, ZaTransferModal). They render in dialog overlays and weren't visible in the screenshot run. Worth a follow-up audit pass — likely candidate for `DialogContainer` follow-up PR mentioned in CSJTODO.
- Mobile (Capacitor) views under `resources/js/mobile/` — different layout system.
- The `/za/savings` "Emergency fund" assessment form — only briefly visible in the screenshot. Worth a closer look during the rework PR.
- Empty-state quality across SA pages. UK Protection has a distinctive violet "No Protection Coverage" empty-state with bullet list and CTA. SA pages have a single line of grey text. Worth elevating during the rework.
