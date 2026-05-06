---
type: spec
date: 2026-05-06
status: authoritative
supersedes:
  - Plans/Implementation_Plan_v2.md
  - May/May5Updates/architecture-spec-realigned-2026-05-05.md
  - May/May5Updates/architecture-plan-realigned-2026-05-05.md
re_affirms: Plans/multi_country_architecture.md (v1.1)
companion_plan: Plans/architecture-plan-v3.md
---

# Architecture Spec v3 — UK as a Pack (Re-affirmation)

This spec retires the v2 detour. It re-affirms `Plans/multi_country_architecture.md` v1.1 as the authoritative architectural contract. The contract has not changed; only the migration *technique* has. UK code is moving into `packs/country-gb/` — properly this time, without compatibility aliases.

## 1. Why this spec exists

Two prior plans drifted from the original:

1. `Implementation_Plan_v2.md` (16 April 2026) declared "UK code stays in `app/` untouched" after a botched relocation attempt produced 600+ test failures. The failures were caused by a *technique* — compatibility aliases via Eloquent inheritance (`App\Models\X extends Fynla\Packs\GB\Models\X`) — not by the relocation itself. Eloquent rejects parent/child instance interchangeability; that pattern was never going to work. The v2 plan abandoned the destination instead of fixing the technique.

2. `May/May5Updates/architecture-spec-realigned-2026-05-05.md` and its companion plan (RA-1 → RA-6) re-affirmed the v2 retreat: UK in `app/`, SA in `packs/`, with cosmetic frontend cleanup to make the asymmetry less visible.

This spec retires both. The original architecture is right. The retreat does not scale.

## 2. Why the v2 retreat fails at scale

| Concern | At 2 countries | At 5 countries | At 50 countries |
|---|---|---|---|
| UK as core+monolith, all others as packs | Tolerable | Awkward | Untenable |
| Cross-border bilateral relationships (N×(N-1)/2) | 1 pair | 10 pairs | 1,225 pairs |
| Each cross-border pair must special-case "UK lives in `\App\`" | Manageable | Painful | Architectural debt that compounds per country |
| "Adding a country requires zero core changes" — the original promise | Already broken (sidebar lives in core constants) | Worse — every new country edits core | Architecture has lost its meaning |

The original plan's `multi_country_architecture.md` § 17 mandated an Architecture test suite asserting *"Core must not reference any Pack namespace; no Pack may reference another Pack."* With UK in `app/`, that fence cannot be erected: either it excludes UK (the rule is fictional) or country packs cannot depend on UK logic (cross-border becomes impossible).

## 3. The contract (re-affirmed verbatim from `multi_country_architecture.md` v1.1)

- **Core has no knowledge of any specific country.** No tax rate, no product type, no pension rule, no currency assumption. (§ 4)
- **Packs never reference each other.** Cross-country logic lives in a separate, optional cross-border pack that depends on both. (§ 4)
- **A pack is self-contained:** models, migrations, seeders, services, controllers, requests, resources, policies, tests, Vue components, stores, routes, translation strings. (§ 4)
- **Adding a country does not edit core code.** If adding Ireland requires changes to core, the core is wrong and must be generalised. (§ 4)
- **Each pack exposes `navigation()`** returning its sidebar items. The sidebar is the concatenation of navs from all active packs plus (if eligible) the global nav. (§ 8.3)
- **Routes for inactive packs are not registered** — not lazy-loaded-and-hidden, not registered. A UK-only user's bundle includes core + `packs/gb` only. (§ 8.2)
- **The 12 Country Pack contracts** (`CountryPack`, `TaxEngine`, `RetirementEngine`, `InvestmentEngine`, `ProtectionEngine`, `EstateEngine`, `ExchangeControl`, `IdentityValidator`, `Localisation`, `BankingValidator`, `PaymentProcessor`, `LifeTableProvider` — plus the 13th, `SavingsEngine`, added in WS 1.2a) are the binding interfaces every pack must satisfy. (§ 7)

These principles apply symmetrically to UK, SA, and every future country. UK is not exempt.

## 4. What lives where (target state)

```
fynla/
  core/app/Core/                    Country-agnostic infrastructure (already exists)
    Contracts/                      13 contracts every pack implements
    Money/, TaxYear/, Jurisdiction/
    Registry/                       PackRegistry, PackManifest
    Http/Middleware/                ActiveJurisdictionMiddleware, EnsurePackEnabled
    Models/                         Jurisdiction, UserJurisdiction (only)
    Services/                       FxService, CurrencyService, GeoLocationService
    Observers/                      JurisdictionDetectionObserver
    Providers/                      CoreServiceProvider

  packs/
    country-gb/                     UK pack (NEW — receives relocated UK code)
      composer.json
      src/
        Providers/GbPackServiceProvider.php
        Tax/                        GbTaxEngine + IHT/CGT/NRB/RNRB calculators
        Retirement/                 SIPP / WP DC / DB / LSA / UFPLS / Annual Allowance
        Investment/                 ISA / GIA / VCT / EIS
        Protection/                 Life / CIC / Income Protection
        Estate/                     IHT / NRB / RNRB / Trusts
        Savings/                    ISA / GIA / fixed savings
        Goals/                      UK goal defaults
        Validation/                 NinoValidator, GbBankingValidator
        Localisation/               GbLocalisation
        Billing/                    GbPaymentProcessor (Stripe UK)
        LifeTables/                 GbLifeTableProvider (ONS)
        Models/                     IsaContribution, LtaRecord, UfplsEvent, etc.
        Http/Controllers/Api/
        Http/Requests/
        Http/Resources/
        Observers/                  UK-specific observers
        Constants/                  TaxDefaults, EstateDefaults, ValidationLimits
      database/migrations/          UK-specific tables (renamed gb_* in a follow-up; not blocking)
      database/seeders/             TaxConfigurationSeeder, ActuarialLifeTablesSeeder, etc.
      routes/api.php                /api/gb/* (or unprefixed, see § 6.1)
      resources/js/                 UK Vue components, views, stores, services
      tests/

    country-za/                     SA pack (already exists, unchanged)
    cross-border/                   Phase 3 — depends on country-gb + country-za + future packs
    _template/                      Pack scaffold for new countries

  app/                              Becomes thin / optional. Reserved for cross-pack glue
                                    only if proven necessary; ideally empty after Phase 1.
```

## 5. What stays in core (and why)

Only genuinely country-agnostic code:

| Stays in core | Why |
|---|---|
| `User`, `Household`, `HouseholdMember`, `Goal`, `LifeEvent` | These exist regardless of jurisdiction |
| `CoordinatingAgent` | Orchestrates calls across active packs via container resolution |
| `Auditable`, `StructuredLogging`, `HasJointOwnership`, `CalculatesOwnershipShare` traits | Generic behaviours |
| `FormatsCurrency` trait | Generalised to consult the active `Localisation` binding |
| Auth, MFA, biometric, session management | Country-agnostic |
| Subscription primitives | The `PaymentProcessor` adapter lives in the pack |
| Audit log, observers framework | Generic |
| Money, FX, TaxYear value objects | Already in core |
| Design system, layouts, shared UI primitives | Already shared |
| Capacitor iOS shell, push notifications, background jobs | Country-agnostic |

Everything financial-product-specific moves to a pack.

## 6. Routing (target)

### 6.1 Backend

Two viable shapes; the plan picks one and the other is closed:

**Option X — Country-prefixed URLs** (matches `multi_country_architecture.md § 6.5`):
- `/api/core/*` for auth, household, goals, billing
- `/api/gb/*` for UK pack
- `/api/za/*` for SA pack
- `/api/global/*` for cross-border
- Pro: clean, unambiguous, identical for every pack
- Con: existing UK clients hit `/api/protection/*` etc. — relocation requires either a redirect layer or a one-shot client update

**Option Y — Unprefixed URLs with jurisdiction middleware** (matches the May 5 spec):
- `/api/savings/*` resolves to the user's active pack via container
- Pro: no client-side URL changes
- Con: requires a routing layer that picks the right pack at request time

**Decision required:** plan defaults to **Option X with a 12-month redirect layer**. UK clients see no break (all `/api/protection/*` requests 301 → `/api/gb/protection/*` on the server). The redirect layer drops once mobile + web clients have shipped with the new URLs.

### 6.2 Frontend

Per `multi_country_architecture.md § 8.2`:
- A user's auth response includes `active_jurisdictions: [...]`
- The router conditionally registers routes from each active pack: `if active_jurisdictions.includes('gb') router.addRoute(gbRoutes)`
- Routes for inactive packs are **not** registered. A SA-only user's router has zero UK routes.
- Pack JS bundles are dynamic-imported. A SA-only user never downloads UK pack JS.

URL paths within a pack are pack-owned. GB pack registers `/savings`, `/protection`, `/retirement` etc. ZA pack registers the same paths. For a single-jurisdiction user only one pack's set is ever live, so there is no collision. For a dual-jurisdiction user, **the user's country of residence dictates which pack forms the base (default) experience**; the residence pack owns the route registration for shared URLs. Other-country assets and liabilities load into the residence pack's views as needed (e.g. a UK resident with a SA TFSA sees the TFSA surfaced within the UK Savings view, with jurisdiction labelling on the asset itself). No toggle, no manual jurisdiction switch — residence is the implicit context, and foreign holdings are surfaced inline.

The current `/za/savings` URLs are dropped during the SA frontend pack relocation — they are an artefact of the bolt-on era.

## 7. Data model

Per `multi_country_architecture.md § 9` and `Implementation_Plan_v2.md § 3.5`:

- Core tables unprefixed: `users`, `households`, `goals`, `audit_logs`, `jurisdictions`, `user_jurisdictions`, `tax_years`, `fx_rates` (cross-border only).
- Pack-owned tables prefixed with the country code. SA pack already does this (`za_*`). UK pack tables get the `gb_` prefix at relocation OR keep their existing names with a deferred prefix migration. **Decision required:** plan defaults to **deferred prefix migration** — relocate without renaming, then rename in a follow-up migration once Phase 1 is stable. Renaming 50+ UK tables is high-risk and can be sequenced separately.
- Money columns are integer minor units (`amount_minor BIGINT`) with sibling `currency_code CHAR(3)`. UK migration to this shape is partial; complete it during pack relocation.
- Asset entities carry `country_code CHAR(2)` so the `JurisdictionDetectionObserver` can wire location → jurisdiction activation.

## 8. Frontend state (target)

```
resources/js/
  core/
    components/                     Layout, Sidebar shell, design-system primitives
    views/                          Dashboard, Auth, Settings, Onboarding
    store/modules/                  jurisdiction (active/primary), auth, household, goals
    services/                       api.js, tokenStorage, geo
    mixins/                         currencyMixin (consults Localisation binding)
    layouts/                        AppLayout, PublicLayout, MobileLayout
    router/index.js                 Router factory — composes routes from active packs
    constants/designSystem.js       Shared

  packs/
    gb/
      index.js                      Exports { routes, navigation, store, components }
      routes.js                     UK route definitions
      navigation.js                 GB pack's sidebar manifest
      store/                        UK Vuex modules (savings, investment, retirement, …)
      components/                   UK Vue components
      views/                        UK page-level views
      services/                     UK API service wrappers
      i18n/en-GB.json
    za/
      …                              SA equivalents (relocated from /components/ZA, /views/ZA)
    cross-border/                   Phase 3
```

Sidebar composition (replaces `MODULES_BY_JURISDICTION` constant in core):

```js
// core/store/modules/jurisdiction.js
getters: {
  sidebarSections: (state, getters, rootState) => {
    const sections = [];
    for (const code of state.active) {
      const pack = rootState[`pack_${code}`];           // registered on pack import
      sections.push(...pack.navigation());              // each pack provides its items
    }
    if (state.crossBorder) sections.push(...crossBorderPack.navigation());
    return mergeBySectionKey(sections);                 // shared section keys collapse
  }
}
```

Each pack's `navigation()` returns items grouped by shared section keys (`cashManagement`, `finances`, `family`, `planning`). The sidebar shape is country-agnostic; the items come from packs. Adding country #3 means shipping `packs/country-ie/resources/js/navigation.js` — no edit to core.

## 9. Migration strategy (the lesson from the failed attempt)

**Banned:** compatibility aliases. Never write `class App\Models\X extends Fynla\Packs\GB\Models\X`. Eloquent's `static::query()` instantiates the concrete class, breaking type interchangeability. This is the technique that failed in April.

**Required:** direct relocation. For each UK file:
1. Move the file to its target location in `packs/country-gb/src/...`.
2. Change its namespace declaration from `App\…` to `Fynla\Packs\Gb\…`.
3. Update **every** `use App\…` and `\App\…` reference in the codebase to the new namespace, in the same commit.
4. Run the test suite. Fix any breakage (relationship references, polymorphic types, route bindings).
5. Move on to the next file or batch.

This is mechanical refactoring at scale, not architectural redesign. Tools that help: Rector with namespace-rename rules, PHPStorm's "Move Class" refactor, or scripted `sed` across known-clean boundaries.

**Cadence:** one module at a time, not 293 files at once. Estate first, then Investment, then Retirement, etc. Each module ships as its own commit on the relocation branch. Pest stays green at every step. This is the discipline `multi_country_architecture.md § 15.2` originally specified — the April execution violated it.

## 10. Acceptance for Phase 1 (UK relocation)

The relocation is done when:

- [ ] `app/` is empty of UK financial-module code (Models, Services, Agents, Controllers, Requests, Resources, Observers, Constants relating to Tax/Estate/Retirement/Investment/Protection/Savings).
- [ ] `packs/country-gb/` is a working Composer package implementing all 13 contracts via `GbPackServiceProvider`.
- [ ] `packs/country-gb/resources/js/` contains the relocated UK frontend, dynamic-imported on auth.
- [ ] All 940+ existing Pest tests pass.
- [ ] Architecture tests assert: no `App\` namespace reference inside `packs/country-gb/`; no cross-pack reference (`packs/country-gb/` doesn't import from `packs/country-za/` or vice versa); core has no `Fynla\Packs\…` reference.
- [ ] The sidebar reads from per-pack `navigation()` providers; `MODULES_BY_JURISDICTION` constant removed from core.
- [ ] A UK-only test user's browser network tab shows no SA pack JS chunks.
- [ ] A SA-only test user's browser network tab shows no UK pack JS chunks.
- [ ] `composer.json` autoload resolves UK code from `packs/country-gb/` only — not from `app/`.

## 11. Out of scope for this spec

- Cross-border pack (Phase 3 — adds DTA, QROPS, residency, worldwide estate, FX)
- Dual-user UX (Phase 2 — adds `<CountryView>`, residence-based pack default with foreign-asset surfacing inside residence-pack views, pack-scoped vue-i18n)
- Geo-registration verification (deferred to Phase 2)
- Any new SA workstreams (1.6b Estate, 1.7 personas, 1.8 FAIS/POPIA — paused; resume after relocation lands)

## 12. Glossary (per `multi_country_architecture.md` Appendix C)

| Term | Meaning |
|---|---|
| Core | Country-agnostic application code shared by every deployment |
| Country Pack (or Pack) | A Composer package + Vue feature bundle containing one country's tax, product, and localisation logic |
| Active Jurisdictions | The subset of jurisdictions currently enabled for a specific user |
| Contract | A PHP interface in `Fynla\Core\Contracts` that every Pack must satisfy |
| Cross-Border Pack | Optional Pack containing residency, DTA, worldwide-estate logic; activates when ≥ 2 active jurisdictions |
| Compatibility alias | The banned pattern (`App\X extends Fynla\Packs\GB\X`) that broke the April relocation. Direct relocation is the only sanctioned migration technique. |
