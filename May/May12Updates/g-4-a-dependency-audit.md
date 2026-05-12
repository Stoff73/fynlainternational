---
type: audit
gauntlet_gate: G-4-a
date: 2026-05-12
session: 5
status: PASS
branch: refactor/uk-pack-relocation
---

# G-4-a — Dependency CVE Scan

Pure-read security audit of PHP (composer) and JS (npm) dependencies. Patches applied where in-constraint and non-breaking. Risk-acceptance documented for the rest.

## Composer

**Pre-scan:** 1 vulnerable package, 4 advisories.
**Post-scan:** clean (`No security vulnerability advisories found.`).

| Package | Old | New | Advisories | Action | Notes |
|---|---|---|---|---|---|
| `phpoffice/phpspreadsheet` | 5.6.0 | 5.7.0 | CVE-2026-40902 (high DoS, unbounded row in XLSX), CVE-2026-40863 (high DoS, unbounded row in SpreadsheetML), CVE-2026-40296 (med XSS in HTML writer), CVE-2026-35453 (med XSS in HTML writer) | **Patched** — `composer update phpoffice/phpspreadsheet --with-dependencies`. In-constraint (`^5.3`). | Both XSS advisories are HTML-writer only; Fynla doesn't use the HTML writer (grep verified zero call sites). DoS advisories apply to `IOFactory::load()` which IS used by `ExcelParserService` for user uploads via `POST /api/documents/upload`. |

**Verification:** `./vendor/bin/pest tests/Unit/Services/Documents/` 16/16 passed, `./vendor/bin/pest tests/Feature/Documents/ExcelUploadTest.php` 5/5 passed.

## NPM (production deps only)

**Pre-scan:** 3 moderate (`@capgo/capacitor-native-biometric`, `postcss`, `vite`).
**Post-scan:** 2 moderate remaining (`@capgo/capacitor-native-biometric`, `vite`) — both semver-major fixes, both risk-accepted with documented follow-up workstreams.

| Package | Installed | Advisory | CVSS | Fix | Action | Reasoning |
|---|---|---|---|---|---|---|
| `postcss` | 8.5.6 → 8.5.14 | GHSA-qx2v-qp2m-jg93 — XSS via unescaped `</style>` in CSS Stringify output | 6.1 | 8.5.10+ (in-range) | **Patched** — `npm update postcss`. | Constraint `^8.5.6` allows fix. No code change required. PostCSS Stringify is used at build-time by Tailwind; user-controlled CSS not present in pipeline, so practical exposure was nil. Patched anyway for hygiene. |
| `vite` | 5.4.21 | GHSA-4w7w-66w2-5vf9 — Path traversal in optimized deps `.map` handling | n/a (dev-server scoped) | 8.0.12 (semver-major) | **Risk-accept**, follow-up workstream | Vite is build-time only; the vulnerable code path is `vite dev` server, never exposed publicly. Local dev runs on `localhost:5173`, never on a routable interface. Production deploys serve only the static `public/build/` output (Apache/SiteGround). Fix requires Vite 5→8 multi-major bump (`@vitejs/plugin-vue`, `laravel-vite-plugin`, `vitest`, build scripts all change). Tracked as a routine maintenance workstream, not a security blocker. |
| `@capgo/capacitor-native-biometric` | 6.0.4 | GHSA-vx5f-vmr6-32wf — Authentication Bypass (CWE-287) | n/a (vector incomplete) | 8.4.5 (semver-major) | **Risk-accept**, formal upgrade workstream required | **Auth-critical.** Used for Face ID login on iOS. Semver-major upgrade (6→8) needs: (1) review of Capacitor 5→6/7 API surface drift, (2) regression test on iOS device, (3) confirm `attemptBiometricLogin()` flow in `app.js`, `BiometricPrompt.vue`, `SettingsList.vue` still works, (4) verify Keychain token format unchanged. Don't bump in-session without a dedicated test plan — biometric breakage is a production user-visible failure and prod is frozen anyway (`feedback_prod_deploy_freeze.md`). Risk while deferred: bypass requires a local attacker with code-execution on the device or a malicious app exploiting the older binding; remote exploitation not credible. |

**Full audit (with dev deps):** 16 vulns / 4 moderate / 12 high. All in `devDependencies` — only run at build time on developer machines, never in production runtime. Not in scope for G-4-a per plan (`npm audit --production`). To be revisited in G-4-b OWASP walk-through if any leak into the runtime bundle.

## Follow-up workstreams (not blocking G-4-a exit)

1. **Vite 5→8 upgrade** — multi-major. Scope: bump `vite`, `@vitejs/plugin-vue`, `laravel-vite-plugin`, `vitest`, update `vite.config.js` per CLAUDE.md mobile rules (transformAssetUrls, rollupOptions, conditional PWA), rebuild + redeploy. Effort: 0.5–1 day. **Open `triage-backlog.md` entry** as E-5.
2. **@capgo/capacitor-native-biometric 6→8 upgrade** — auth-critical. Scope: review API drift, test on iOS device, verify Face ID end-to-end. Effort: 0.5 day including QA. **Open `triage-backlog.md` entry** as B-4 (sev-2 — auth-bypass theoretical but local-attacker only).

Both will be picked up after G-1-c (logic fixtures) and the rest of G-4 close, or earlier if CSJ wants the biometric one prioritised.

## Files touched this gate

- `composer.json` — unchanged (constraint `^5.3` already allowed 5.7.0)
- `composer.lock` — `phpoffice/phpspreadsheet` 5.6.0 → 5.7.0
- `package.json` — unchanged (constraint `^8.5.6` already allowed 8.5.14)
- `package-lock.json` — `postcss` 8.5.6 → 8.5.14 (+ transitive dedupes)
- `Plans/test-gauntlet-plan-v1.md` — G-4-a marked PASS
- `May/May12Updates/g-4-a-dependency-audit.md` — this file
- `May/May12Updates/triage-backlog.md` — to add E-5 (vite upgrade) and B-4 (biometric upgrade)

## Exit criteria

- [x] `composer audit` returns clean
- [x] `npm audit --omit=dev` returns only moderate-or-below items with documented risk-accept
- [x] All patches verified against existing test suites (no regressions)
- [x] Risk-accepted items have planned follow-up workstreams logged in triage backlog
- [x] G-4-a tracker line in `Plans/test-gauntlet-plan-v1.md` updated
