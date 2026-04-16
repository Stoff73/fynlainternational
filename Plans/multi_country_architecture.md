Fynla
Multi-Country Architecture
Core + Country Packs — adding South Africa (and beyond)
Prepared for: Chris Slater-Jones
Date: 15 April 2026
Version: 1.1
Companion to: Fynla SA — Research & Mapping (v1.1), Fynla Phase 0 Implementation Guide (v1.0)
 
Change log
Version	Date	Changes
1.0	15 April 2026	Initial multi-country architecture. Composer path-repository packs recommended but listed as an ‘open question’.
1.1	15 April 2026	Packaging decided: composer path-repository packs from day one (ADR-002). Frontend lazy chunks decided (ADR-008). §19 open questions resolved and re-scoped; still-open questions re-listed. Cross-references to the new Phase 0 Implementation Guide and Scaling Playbook added.
 
1. Executive summary
The previous document treated Fynla SA as a forked, standalone product. This document replaces that assumption. Fynla is now a single codebase that is factored into a country-agnostic core plus country-specific packs. The UK logic that currently ships as “Fynla” is one such pack; South Africa is added as a second pack; future countries (Ireland, Australia, UAE, New Zealand…) arrive as additional packs without touching the core.
The design is governed by three non-negotiable product principles:
•	A user who only lives and holds assets in the UK must experience Fynla exactly as it exists today. No TFSA, no Two-Pot, no exchange control, no mention of other jurisdictions — not in the navigation, not in copy, not in calculators.
•	A user who only lives and holds assets in South Africa must experience a pure SA product. No ISA, no SIPP, no IHT — only SARS / FSCA / SARB concepts.
•	A user with footprints in more than one jurisdiction gets the cross-border layer — DTA, residency, QROPS, worldwide estate — as an additive capability on top of both single-country experiences.
The architectural equivalent is a strict separation between the Fynla core (identity, household, goals, UI, design system, audit, preview, mobile shell, billing) and country packs (tax engine, retirement, protection, estate, investment, exchange control, localisation, identity validation). A user’s “jurisdictional context” determines which packs are active for them, and therefore which modules, routes, navigation items, currencies, disclaimers, and rule-sets they see. Country packs register themselves via Laravel service providers; the Vue frontend loads the corresponding feature bundles lazily per the authenticated user’s active jurisdictions.
 
2. Contents
1. Executive summary
2. Contents
3. Product experience principles
4. Architectural principles
5. The Jurisdictional Context model
6. Backend architecture (Laravel): core + country packs
7. Country Pack contract — interfaces every pack must satisfy
8. Frontend architecture (Vue): dynamic, jurisdiction-scoped UI
9. Data model for multi-country and multi-jurisdiction users
10. Country Pack anatomy — concrete file-level specification
11. Experience walk-throughs: UK-only / SA-only / dual user
12. Domain, branding & SEO strategy
13. Mobile app strategy
14. Billing, payments & currency
15. Migration plan: UK Fynla → Core + UK Pack → + SA Pack
16. Code-level move map
17. Testing strategy
18. Extending to country #3 — proving the design
19. Risks, open questions & out of scope
20. Appendix A — interface stubs (PHP)
21. Appendix B — file tree diagram
22. Appendix C — glossary
 
3. Product experience principles
Every architectural decision in this document is traceable back to one of three commitments:
3.1 UK-only users see a UK-only product
A user whose active jurisdiction is GB sees the same app they see today. The navigation reads Protection, Savings, Investment, Retirement, Estate Planning, Goals & Life Events, and Coordination. Values are in GBP. The tax year is 2025/26 on 6 April logic. Disclaimers are FCA-framed. The word “TFSA” never appears in the app; neither does Two-Pot or SDA. Even the support / FAQ copy is UK-only. The fynla.org (or fynla.co.uk) domain serves this experience exclusively.
3.2 SA-only users see a SA-only product
A user whose active jurisdiction is ZA sees the module set described in the companion research document. Values are in ZAR. The tax year is 2026/27 on 1 March logic. Disclaimers are FAIS-framed. ISA, SIPP, IHT, Annual Allowance do not appear. The fynla.co.za domain serves this experience exclusively.
3.3 Dual-jurisdiction users are opted into the cross-border layer
When a user adds a second jurisdiction to their profile, the cross-border layer activates. They retain full access to both country experiences — a SA-UK client can still add a UK workplace pension the same way a UK-only client would — plus a new Cross-Border section appears in the sidebar with residency tracking, DTA classification, worldwide estate, QROPS modelling, etc. Without the second jurisdiction, this section does not exist.
3.4 No country context → no financial modules
A brand-new user whose jurisdiction has not yet been set sees an onboarding flow that establishes one. They do not see ‘empty UK’ or ‘empty SA’ modules by accident. The country pack is chosen explicitly and can be changed later (with a migration warning).
 
4. Architectural principles
•	Core has no knowledge of any specific country. It contains no tax rate, no product type, no pension rule, no currency assumption. Everything country-specific lives in a pack.
•	Packs never reference each other. The SA pack does not import from the UK pack; cross-country logic lives in a separate, optional cross-border pack that depends on both.
•	A pack is self-contained: models, migrations, seeders, services, controllers, requests, resources, policies, tests, Vue components, stores, routes, translation strings.
•	Registration is explicit. Packs register themselves via a CountryPackServiceProvider. Core discovers enabled packs at boot and mounts them.
•	User-visible features are jurisdiction-scoped. Middleware rejects requests for a pack the user does not have. The navigation and router only expose enabled modules.
•	Adding a country does not edit core code. If adding Ireland requires changes to core, the core is wrong and must be generalised.
•	Money is never a float. Core provides a Money type (integer minor units + currency). Every pack operates in its own currency. Cross-currency conversion is explicit.
•	Tax year abstraction. Core represents a tax year as a semantic interval (e.g. ‘2025/26 GB’, ‘2026/27 ZA’). No code assumes 6 April or 1 March.
•	Date, number and name formatting go through a Localisation contract — never directly through PHP intl or JS Intl at call sites.
•	Disclaimers, legal copy and compliance text are pack-owned. Core never writes ‘FCA’ or ‘FSCA’ — the pack injects the right wording.
 
5. The Jurisdictional Context model
A user has zero or more active jurisdictions. In v1 the supported cardinalities are:
# active	Meaning	Effective product
0	New user, onboarding not yet complete	Onboarding wizard only
1 (GB)	UK-only user	Pure UK Fynla
1 (ZA)	SA-only user	Pure SA Fynla
2 (GB + ZA)	Dual-jurisdiction user	Both country experiences + cross-border section
n > 2	Reserved for future country additions	All enabled experiences + cross-border
Each active jurisdiction has three sub-states that matter to the engines below it:
•	Tax residency — binary per tax year (derived from an SRT / physical-presence timeline maintained in the cross-border module, or declared by the user in single-jurisdiction mode).
•	Asset footprint — set of accounts / policies / properties held under that country’s regime.
•	Income footprint — set of income sources attached to that jurisdiction.
A single-jurisdiction user’s tax residency is trivially ‘true’ for their one country. The SRT / physical-presence engine only spins up when a second jurisdiction is added. This is how we preserve the ‘looks like a national app’ promise while still letting users upgrade.
 
6. Backend architecture (Laravel): core + country packs
6.1 Monorepo layout
fynla/
  core/                  ← country-agnostic Laravel app
  packs/
    country-gb/          ← UK pack (package, namespaced Fynla\Packs\GB)
    country-za/          ← SA pack (package, namespaced Fynla\Packs\ZA)
    cross-border/        ← optional, depends on >= 2 country packs
  frontend/              ← Vue monorepo: core + per-pack feature bundles
  deploy/                ← per-market deployment configs
Packs are composer packages under packs/country-xx with their own composer.json. They are loaded via path repositories in the root composer.json so the entire tree lives in one git repo but packs are independently namespaced and testable. The frontend mirrors this with pnpm workspaces or npm workspaces.
6.2 Core responsibilities
•	Authentication, email verification, 2FA, biometric login, session management
•	User, Household, Household Member, joint ownership primitives
•	Goals & Life Events framework (generic — country packs supply default goal shapes)
•	Coordination agent (orchestrates calls across active packs)
•	Audit log, structured logging, observers framework
•	Money type, Currency service, FX rates table (populated only when cross-border is active)
•	Design system, shared UI primitives, layouts
•	Preview-mode scaffolding, feature flags, role-based access
•	Capacitor iOS shell, push notifications, background jobs
•	Billing primitives and subscription engine (payment processor adapter lives in the pack)
6.3 A country pack’s responsibilities
•	All tax engine logic (brackets, rebates, credits, CGT, death tax, dividend / interest treatment)
•	All retirement products and rules (contribution limits, decumulation, regulation monitoring)
•	All protection products and coverage calculators
•	All investment wrapper products and their CGT / income treatment
•	All estate-planning rules (death tax, probate / administration costs, trust types)
•	Exchange control / capital movement rules (no-op on GB; live on ZA)
•	Currency and locale defaults, date formats, spelling, validation of identifiers (NI number, SA ID, passport)
•	Per-country copy, terminology, compliance disclaimers, legal notices
•	Default goal templates (retirement age norms, tertiary-education cost bands, property deposits)
•	Pack-specific seeders (tax tables, preview personas, life tables)
•	Payment processor adapter (Stripe vs Paystack vs Payfast)
6.4 Service provider registration
Each pack ships a CountryPackServiceProvider that registers bindings for the Country Pack Contract (see §7), plus its routes, migrations, seeders, policies and commands. The provider is auto-discovered via Laravel’s package discovery; an environment variable (FYNLA_ACTIVE_PACKS) controls which packs boot in a given environment. A developer running the UK-only build sets FYNLA_ACTIVE_PACKS=gb; a staging environment previewing both sets it to gb,za.
6.5 Routing
Core owns /api/core/* (auth, household, goals, coordination, billing). Each pack owns /api/{cc}/* where {cc} is its ISO country code (gb, za). A middleware ActiveJurisdictionMiddleware rejects any request to /api/{cc}/* if the authenticated user does not have that jurisdiction active — important for multi-tenant safety: a UK-only user cannot accidentally hit a SA endpoint even if they discover the URL. Cross-border routes sit under /api/global/* and require ≥ 2 active jurisdictions.
 
7. Country Pack contract — interfaces every pack must satisfy
Core defines a set of PHP interfaces in the namespace Fynla\Core\Contracts. Every pack must provide a concrete binding for each. Where a capability does not exist in a country (e.g. there is no UK exchange-control regime), the pack binds a NullImplementation that is safe, explicit and throws on unsupported operations — never silently returns zero.
Contract	What it supplies	UK binding	SA binding
CountryPack	Metadata (code, name, currency, locale, tax-year convention, enabled modules)	GbPack	ZaPack
TaxEngine	Compute income tax, CGT, death tax; derive rebates, thresholds, allowances	GbTaxEngine	ZaTaxEngine
RetirementEngine	Product catalogue, contribution limits, decumulation rules, allowance monitors	GbRetirementEngine (SIPP, WP DC, DB, LSA)	ZaRetirementEngine (RA, PF, PvF, Two-Pot, Reg 28)
InvestmentEngine	Wrapper types, taxable-event classifier, CGT tracker	GbInvestmentEngine (ISA, GIA, VCT, EIS)	ZaInvestmentEngine (TFSA, Discretionary, Endowment, Offshore)
ProtectionEngine	Product catalogue, coverage calculator, tax treatment	GbProtectionEngine	ZaProtectionEngine
EstateEngine	Dutiable-estate computation, admin costs, relief rules	GbEstateEngine (IHT, NRB, RNRB)	ZaEstateEngine (Estate Duty, R3.5m abatement)
ExchangeControl	Allowance tracking, transfer workflow	NullExchangeControl (always unlimited, never writes ledger)	ZaExchangeControl (SDA, FIA, AIT)
IdentityValidator	National ID / NI number validation, DOB derivation	GbNinoValidator	ZaIdValidator (13-digit with Luhn)
Localisation	Currency / date formatting, spelling locale, module disclaimers	GbLocalisation	ZaLocalisation
BankingValidator	Bank / account / branch-code validation	GbBankingValidator (sort-code + 8-digit)	ZaBankingValidator (universal branch code)
PaymentProcessor	Billing adapter	GbPaymentProcessor (Stripe UK)	ZaPaymentProcessor (Paystack/Payfast)
LifeTableProvider	Mortality / longevity data for retirement projections	GbLifeTableProvider (ONS)	ZaLifeTableProvider (ASSA / Stats SA)
See Appendix A for illustrative method signatures.
 
8. Frontend architecture (Vue): dynamic, jurisdiction-scoped UI
8.1 Feature bundles
The frontend is reorganised into three tiers: core, per-pack feature bundles, and the cross-border bundle. Each feature bundle is an independently-loadable module exposing views, route definitions, Vuex store modules, and a navigation manifest.
frontend/
  core/                           ← shared UI, layouts, router shell, auth, design system
  packs/gb/                       ← UK feature bundle (views, stores, components, i18n)
  packs/za/                       ← SA feature bundle
  packs/global/                   ← cross-border feature bundle
8.2 Router composition
At login, the frontend receives the user’s active jurisdictions and capabilities from /api/core/session. The router composes its route tree from core + enabled packs. Routes for inactive packs are not registered at all — not lazy-loaded-and-hidden, not registered. A UK-only user’s bundle download includes core + packs/gb only; the SA JavaScript is never shipped to them.
8.3 Navigation manifest
Each pack exposes a navigation() function returning a list of sidebar items scoped to that pack. The sidebar is a concatenation of navs from all active packs plus (if eligible) the global nav. The core layout simply renders the concatenated list. This is how ‘TFSA’ stays invisible to a UK-only user: the pack that owns the TFSA nav item is never loaded.
8.4 Component resolution for shared concepts
Some concepts exist in both countries but with different shapes — e.g. ‘retirement account form’. Rather than a single polymorphic component with branches, each pack provides its own: GbSipFormView.vue, ZaRaFormView.vue. A core helper &lt;CountryView name=“retirementAccountForm” /&gt; resolves the correct pack component based on the currently-focused jurisdiction.
8.5 Currency and date components
&lt;Money :value /&gt; and &lt;DateString :date /&gt; consult the Localisation binding for the currently active jurisdiction. A dual user viewing their SA retirement account sees ZAR and DD MMM YYYY; toggling to their UK workplace pension switches to GBP and DD/MM/YYYY without a page reload.
8.6 Translations and copy
vue-i18n scopes are per-pack (e.g. fynla.gb.protection.*, fynla.za.protection.*) so identical keys can carry materially different copy across countries. Core uses fynla.core.* and never owns user-facing product copy.
 
9. Data model for multi-country and multi-jurisdiction users
9.1 Jurisdictions & user scope
•	jurisdictions (id, code, name, currency, default_locale, active_from, active_until) — seeded GB and ZA
•	user_jurisdictions (user_id, jurisdiction_id, role, activated_at, deactivated_at, primary_flag) — pivot; a user’s primary jurisdiction drives default currency and landing page
•	Every country-scoped entity (retirement_accounts, protection_policies, investment_accounts, tax_records, properties, etc.) carries a nullable jurisdiction_id FK — nullable because core-owned entities (goals, household members) are country-agnostic
9.2 Money and currency
•	All money columns become integer minor units (amount_minor BIGINT) with a sibling currency_code column (CHAR(3))
•	Core ships a cast (MoneyCast) mapping DB columns to a Money value object
•	FX rates table (fx_rates: base_ccy, quote_ccy, rate_date, spot_rate, monthly_avg_rate) — only populated when cross-border pack is active
9.3 Pack-owned tables
Each pack ships its own migrations. Table names are prefixed with the country code to avoid collisions when both packs are enabled:
gb_tax_configurations, gb_nrb_trackers, gb_isa_contributions, gb_lta_records…
za_tax_configurations, za_retirement_fund_buckets, za_exchange_control_ledger, za_tfsa_contributions, za_donations_register…
Core migrations remain prefix-less (users, households, goals, audit_logs, fx_rates). Laravel’s package discovery handles per-pack migration paths.
9.4 Tax year abstraction
•	tax_years table owned by core: (id, jurisdiction_id, label, starts_on, ends_on)
•	Seeders in the UK pack insert ‘2024/25’ 6 Apr – 5 Apr, ‘2025/26’, ‘2026/27’…
•	Seeders in the SA pack insert ‘2025/26’ 1 Mar – 28/29 Feb, ‘2026/27’, ‘2027/28’…
•	Tax-related records reference a tax_year_id so queries never assume a particular fiscal calendar
9.5 Cross-border-only tables
•	residency_timeline (user_id, jurisdiction_id, period_start, period_end, residency_status, determination_method)
•	srt_days / physical_presence_days (travel logs)
•	treaty_classifications (entity_type, entity_id, dta_article, primary_jurisdiction, credit_method)
•	pension_transfer_cases (source_account, target_scheme, scheme_jurisdiction, qrops_flag, ots_charge_due)
These tables exist only in the cross-border migration set. They are not created on a single-jurisdiction deployment.
 
10. Country Pack anatomy — concrete file-level specification
A country pack is a full Laravel package with the following shape:
packs/country-za/
  composer.json
  src/
    ZaPackServiceProvider.php
    ZaPack.php                          (implements CountryPack)
    Tax/
      ZaTaxEngine.php                   (implements TaxEngine)
      ZaTaxConfigService.php
      CgtInclusion.php, Section11F.php, Section10C.php…
    Retirement/
      ZaRetirementEngine.php            (implements RetirementEngine)
      Products/{Ra,PensionFund,ProvidentFund,PreservationFund,LivingAnnuity,LifeAnnuity}.php
      TwoPotTracker.php, Reg28Monitor.php
    Investment/
      ZaInvestmentEngine.php            (implements InvestmentEngine)
      Wrappers/{Tfsa,Discretionary,Endowment,Offshore}.php
    Protection/
      ZaProtectionEngine.php            (implements ProtectionEngine)
    Estate/
      ZaEstateEngine.php                (implements EstateEngine)
      EstateDutyCalculator.php, DonationsTaxCalculator.php
    ExchangeControl/
      ZaExchangeControl.php             (implements ExchangeControl)
      SdaTracker.php, FiaTracker.php, AitWorkflow.php
    Validation/
      ZaIdValidator.php, ZaBankingValidator.php
    Localisation/
      ZaLocalisation.php
    Billing/
      ZaPaymentProcessor.php            (implements PaymentProcessor)
    Http/
      Controllers/Api/*.php
      Requests/*.php
      Resources/*.php
    Models/
      RetirementFund.php, TfsaAccount.php, ExchangeControlEntry.php…
    routes/api.php                      (mounted at /api/za/*)
  database/
    migrations/*.php
    seeders/{ZaTaxConfigurationSeeder,ZaPreviewUserSeeder,ZaProductReferenceSeeder}.php
  frontend/
    src/
      index.ts                          (exports feature bundle metadata)
      views/{Retirement,Savings,…}.vue
      stores/{retirement,tfsa,exchangeControl…}.ts
      components/…
      i18n/{en-ZA.json,af-ZA.json}
      routes.ts
      navigation.ts
  tests/
    Unit/, Feature/, Architecture/
The UK pack has the same shape, with SIPP/ISA/IHT specifics in place of RA/TFSA/Estate Duty. When Ireland joins, it ships a country-ie pack with exactly the same shape — no core changes required.
 
11. Experience walk-throughs
11.1 UK-only user
Ella registers at fynla.co.uk. The signup form asks her country of residence; she selects United Kingdom. Server side, user_jurisdictions gets one row (GB, primary). The frontend session response says: active_packs = [‘gb’], cross_border = false. Her router loads core + packs/gb. The sidebar shows Dashboard, Protection, Savings, Investment, Retirement, Estate Planning, Goals. She sees GBP, 2025/26 tax year, IHT calculators, SIPP forms, ISA wrappers. ‘TFSA’ is not a string anywhere in her bundle. The disclaimer on every calculator reads ‘Fynla provides information, not FCA-regulated advice’. She is indistinguishable from a user of today’s Fynla.
11.2 SA-only user
Thabo registers at fynla.co.za. He selects South Africa. user_jurisdictions = [ZA, primary]. Session: active_packs = [‘za’], cross_border = false. He downloads core + packs/za. His sidebar shows Dashboard, Protection, Savings, Investment, Retirement (RA / PF / Two-Pot / Living Annuity), Estate Planning, Exchange Control, Goals. ZAR, 2026/27, SARS tables, FSCA-framed disclaimer. ‘ISA’ and ‘SIPP’ never appear. He is indistinguishable from a user of a dedicated SA app.
11.3 Dual-jurisdiction user (Brit in SA)
Sophie has lived in the UK her whole career and moved to Cape Town in 2025. She registers at fynla.co.za (or fynla.co.uk — either works), selects South Africa as primary, and during onboarding clicks ‘I also have assets or income in another country’. She adds United Kingdom. user_jurisdictions = [ZA primary, GB]. Session: active_packs = [‘za’, ‘gb’], cross_border = true. Her router loads core + packs/za + packs/gb + packs/global. Her sidebar now shows: the full SA module set, the full UK module set (under a distinct ‘United Kingdom’ header), plus a Cross-Border section containing Residency (SRT / physical-presence tracker), Treaty Reliefs, Worldwide Estate, and Pension Transfers (QROPS). She enters her UK SIPP under the UK section; it’s tagged jurisdiction_id = GB and does not appear in SA Reg 28 checks. She enters her SA RA under the SA section; exchange-control tracking is ZA-only and never includes her UK investments. Cross-border reports surface the combined worldwide picture.
11.4 Adding a country to an existing user
If Ella later moves to SA she can add ZA to her profile from Settings → Jurisdictions. A confirmation modal warns her that (a) SA-specific modules will appear, (b) cross-border features will activate, and (c) if she ticks ‘primary jurisdiction’, her landing page, default currency and billing currency will switch. After confirmation, the frontend refetches the session; the app reloads with the new module set live. Her UK data is untouched.
11.5 Removing a jurisdiction
Removing a jurisdiction from a user with historic data in it is a soft-delete: the user_jurisdictions row is marked deactivated_at. Existing records are preserved (for audit and historic tax queries) but new writes to that jurisdiction are blocked. This handles ‘I’ve emigrated from SA’ as a reversible state change rather than a destructive operation.
 
12. Domain, branding & SEO strategy
12.1 Domains
•	fynla.co.uk (or the current fynla.org) — UK marketing site + app for UK users
•	fynla.co.za — SA marketing site + app for SA users
•	Both domains point at the same Fynla core; server-side, the request’s Host header determines the ‘default pack’ shown to unauthenticated visitors (marketing pages, signup flow)
•	Once authenticated, the user’s active jurisdictions drive the experience regardless of which domain they arrived on — a SA user who lands on fynla.co.uk is still shown the ZAR app once logged in, but deep-links respect the domain they were issued from (avoids cross-domain cookie friction)
•	Optional future: country sub-domains (gb.fynla.com, za.fynla.com) if the marketing branding diverges materially
12.2 Marketing, legal, T&Cs
Each country pack owns its own marketing copy, privacy policy, T&Cs, and compliance framing. The UK public site references the FCA / ICO / UK GDPR; the SA public site references the FSCA / Information Regulator / POPIA. The CMS that powers marketing is pack-scoped: UK marketing editors can’t accidentally publish SA copy and vice versa.
12.3 SEO
•	Separate sitemaps per TLD; canonical URLs per-country
•	hreflang tags link UK and SA variants of the same-concept pages where they genuinely correspond; do not hreflang across countries for pages that don’t have an equivalent
•	Structured data (Product, Organization) localised per jurisdiction
 
13. Mobile app strategy
Two iOS apps on the App Store: Fynla (UK store territory) and Fynla (ZA store territory). Both are built from the same Capacitor codebase; the build pipeline differs only in (a) bundle identifier, (b) default-pack flag baked into the bundle, (c) app icon and splash variants, (d) Face ID prompt copy (localised), (e) in-app purchase product IDs for the country’s store. Cross-border features unlock server-side based on the authenticated user’s jurisdictions, so a SA-purchased app that belongs to a user who also has UK jurisdiction gets the cross-border section without needing a separate app. Android follows the same model (Play Store regional listings).
•	deploy/mobile/build-ios-gb.sh — builds UK bundle
•	deploy/mobile/build-ios-za.sh — builds SA bundle
•	Shared Capacitor core; pack-specific assets injected by the build script
 
14. Billing, payments & currency
•	Subscription plans are jurisdiction-scoped. UK plans priced in GBP through Stripe UK; SA plans priced in ZAR through Paystack (or Payfast)
•	A dual user has a single subscription in their primary jurisdiction’s currency; cross-border is a feature of the plan, not a separate SKU (rationale: cross-border is the product’s differentiator, not a bolt-on)
•	Tax handling: UK VAT (20%) on UK subscriptions where applicable; SA VAT (15%) on SA subscriptions. The PaymentProcessor binding handles this per-pack
•	Changing primary jurisdiction triggers a plan migration at the next billing cycle (we do not prorate across currencies mid-cycle)
 
15. Migration plan: UK Fynla → Core + UK Pack → + SA Pack
15.1 Phase 0 — extract Core (4 weeks)
•	Create /packs/country-gb as an empty package skeleton. Add path repository to root composer.json
•	Define the full set of contracts in Fynla\Core\Contracts
•	Introduce Money value object + MoneyCast; migrate all monetary columns to amount_minor + currency_code over a sequence of zero-downtime migrations (dual-read, dual-write, backfill, flip reader, drop old)
•	Introduce jurisdictions, user_jurisdictions, tax_years (core tables). Seed GB only
•	Add ActiveJurisdictionMiddleware; initially a no-op that always allows GB for every user
•	No functional change visible to users; app still works identically to today
15.2 Phase 1 — repackage UK logic into country-gb pack (6 weeks)
•	Move UK-specific services (IHT, ISA, SIPP, Annual Allowance, UFPLS, LSA/LSDBA, NRB/RNRB) into packs/country-gb/src/*
•	Move TaxConfigService, TaxConfigurationSeeder, TaxDefaults, EstateDefaults into the UK pack
•	Move UK-specific Vue components/views/stores into frontend/packs/gb/
•	Implement GbPack, GbTaxEngine, GbRetirementEngine, GbInvestmentEngine, GbProtectionEngine, GbEstateEngine
•	NullExchangeControl provided by core (acceptable for countries with no regime)
•	Rewire Agents (ProtectionAgent, RetirementAgent, etc.) to resolve per-jurisdiction engines via the container
•	Feature flag: FYNLA_ACTIVE_PACKS=gb; CI runs the full UK regression suite
•	Deploy; UK users experience no change
15.3 Phase 2 — build country-za pack (10 weeks)
•	Scaffold packs/country-za using packs/country-gb as a template
•	Implement ZaTaxEngine / RetirementEngine / InvestmentEngine / ProtectionEngine / EstateEngine per the research doc
•	Implement ZaExchangeControl (SDA/FIA ledger, AIT workflow stub)
•	Add ZA tables via pack migrations (za_tax_configurations, za_retirement_fund_buckets, za_exchange_control_ledger, za_tfsa_contributions…)
•	Build Vue feature bundle with full SA module set and en-ZA i18n
•	Seed ZA preview personas (young_professional_sa, peak_earners_sa, pre_retiree_sa, etc.)
•	Integrate ZA payment processor
•	Enable at fynla.co.za; closed beta with SA-only users; FAIS/POPIA disclaimers active
15.4 Phase 3 — cross-border pack (6 weeks)
•	Scaffold packs/cross-border; depends on both country-gb and country-za
•	Build Residency & Domicile Engine (SRT + SA physical-presence), DTA Treaty Mapper (UK-SA DTA as the first treaty)
•	Build Worldwide Estate view, Pension Transfer module (QROPS), FX view
•	Enable opt-in ‘I have assets in another country’ flow; activates the pack for that user
15.5 Phase 4 — country #3 onwards (8–10 weeks each)
•	The core + contracts are now stable. New countries are scaffolded, filled in, certified, and released independently
•	The cross-border pack grows by adding treaty mappers for each new bilateral pair (UK-IE, ZA-IE, AU-UK…). Add-another-country is additive; no core change
 
16. Code-level move map
High-level summary of which current Fynla files land where.
Current location	Target location	Transform
app/Services/Tax/*, app/Services/Estate/*, app/Services/Retirement/*, app/Services/Investment/*, app/Services/Protection/*	packs/country-gb/src/{Tax,Estate,Retirement,Investment,Protection}/*	Move verbatim; implement relevant Contract
app/Agents/*	Split: generic orchestration → core/Agents; UK logic → packs/country-gb/src/Agents	Refactor to resolve pack engines from container
app/Traits/{Auditable,StructuredLogging,FormatsCurrency…}	core/Traits/*	Keep; generalise FormatsCurrency to use Localisation contract
app/Constants/{TaxDefaults,EstateDefaults,ValidationLimits}	packs/country-gb/src/Constants/*	Move; values become pack-owned
app/Http/Controllers/Api/*	Split: core controllers stay; UK-specific move to packs/country-gb/src/Http/Controllers/Api/*	Route prefix becomes /api/gb/*
app/Http/Requests/*	Same split as controllers	
app/Observers/*	Split: generic → core/Observers; UK-specific → packs/country-gb/src/Observers	
app/Models/* (UK-specific: IsaContribution, LtaRecord, UfplsEvent…)	packs/country-gb/src/Models/*	Move; table names prefixed gb_
app/Models/* (generic: User, Household, Goal, LifeEvent)	core/Models/*	Keep
database/migrations/* (UK-specific)	packs/country-gb/database/migrations/*	Move; rename tables with gb_ prefix in a dedicated rename migration
database/seeders/{TaxConfigurationSeeder, TaxProductReferenceSeeder, PreviewUserSeeder, ActuarialLifeTablesSeeder}	packs/country-gb/database/seeders/*	Move
resources/js/components/{Protection,Savings,Investment,Retirement,Estate}/*	frontend/packs/gb/src/views/* & components/*	Move; import paths rewritten
resources/js/components/{Dashboard,Auth,Settings,Layout}/*	frontend/core/src/*	Keep
resources/js/store/modules/*	Split as above	
resources/js/mixins/{currencyMixin,previewModeMixin}	frontend/core/src/mixins/*	Generalise currencyMixin to use Localisation per active jurisdiction
resources/js/constants/{taxConfig,goalIcons,designSystem…}	Split: generic → core; UK-specific (taxConfig) → packs/gb	
fynlaDesignGuide.md	docs/design/fynlaDesignGuide.md	Shared — the design system is global
deploy/fynla-org/*, deploy/csjones-fynla/*	deploy/gb/* (renamed)	Duplicate structure added for deploy/za/*
ios/*	ios/gb/*, ios/za/*	Two bundles sharing a Capacitor core; build script selects default pack
 
17. Testing strategy
•	Core has its own Pest suite covering generic behaviours (auth, household, goals, coordination, Money type, middleware)
•	Each pack has its own Pest suite. packs/country-gb/tests continues to house the current 940+ Fynla tests once relocated
•	An Architecture suite enforces the rules: ‘Core must not reference any Pack namespace’, ‘No Pack may reference another Pack’, ‘Every Contract has a binding in every Pack’
•	A Contract Conformance suite: for every Contract, run a shared set of property-based tests against every registered Pack binding. Ensures, e.g., every TaxEngine returns a non-negative liability for a zero-income user, every RetirementEngine’s contribution limit is positive
•	Integration tests (cross-border) spin up with FYNLA_ACTIVE_PACKS=gb,za and assert the dual-user flows
•	Browser / Playwright suites run three distinct profiles: UK-only, SA-only, dual. Every module has to pass its single-country suite and not appear in the other country’s navigation when disabled
 
18. Extending to country #3 — proving the design
A useful sanity check for the architecture is ‘what does it take to add Ireland?’ Answer, end-to-end:
•	Scaffold packs/country-ie from the template
•	Implement IePack with code=IE, currency=EUR, locale=en-IE, tax year = calendar year
•	Implement IeTaxEngine (PAYE bands, USC, PRSI), IeRetirementEngine (PRSAs, Occupational, ARFs), IeInvestmentEngine, IeProtectionEngine, IeEstateEngine (CAT, group thresholds A/B/C)
•	NullExchangeControl
•	Build Vue feature bundle; en-IE i18n
•	Seed Irish tax tables and preview personas
•	Cross-border pack gains UK-IE and SA-IE treaty mappers
•	Core and the existing GB / ZA packs get no changes
A second country should take meaningfully less engineer-time than the first (estimate: 10 weeks vs 16 weeks for SA). A third should be faster still (~8 weeks) as the pack template matures.
 
19. Risks, open questions & out of scope
19.1 Risks
•	The Phase 0 ‘extract core’ refactor is invasive. The biggest risk is the Money / currency migration; it touches almost every model. Dual-write / backfill / flip is the safe path, but it’s weeks of careful work
•	Over-generalisation: designing contracts that accommodate every future country is impossible. Expect to revise the TaxEngine / RetirementEngine signatures after country #3 — treat them as v1 interfaces, not forever interfaces
•	Under-generalisation: it’s easy to leave UK-isms in core by accident (‘tax year starts in April’, ‘NI number’ validators). The Architecture test suite is the fence that catches these
•	Translation / copy drift: a single string edited in fynla.gb.protection.adequacy isn’t reflected in fynla.za.protection.adequacy automatically. Content ops becomes per-pack; bake that into the editorial workflow
•	Database size: once multiple packs are live the schema has many more tables. Migrations framework must handle per-pack migrate/rollback; dev ergonomics (artisan migrate:pack gb, artisan db:seed:pack za) become important
19.2 Decisions taken (v1.1)
Items listed as ‘open questions’ in v1.0 are now decided. They are recorded here with reasoning, and reproduced as ADRs in docs/adr/ per the Phase 0 guide.
•	Packs are composer packages loaded via path repositories (ADR-002). Not nwidart/laravel-modules. Rationale: first-class PHP namespacing, no magic autoloader, standard tooling, a clean graduation path to a private composer registry when the team grows (see the Scaling Playbook). Starting with path repositories on day one avoids a painful later migration.
•	Frontend build is a single Vite config with dynamic imports; packs are lazy-loaded chunks (ADR-008). A UK-only user never downloads the SA bundle.
•	Public marketing sites ship from the same Laravel app initially (two public facades). This is re-evaluated once per-market marketing ops justify splitting.
•	Cross-Border DTA data lives in packs/cross-border/data/treaties/*.json, one file per bilateral treaty, versioned with semver-aligned metadata so a treaty protocol change is a bump, not a silent edit.
•	Cross-border pricing remains a product decision — not architecturally blocking.
19.3 Still-open questions
•	How do we surface ‘a pack is temporarily unavailable’ (e.g. a Phase 0-era shadow migration still running)? Proposed: core-level feature flag per pack code, rendered as a maintenance card; final form deferred to Phase 2.
•	When does the single repository graduate to a private composer registry? See the Scaling Playbook for the graduation criteria (team size, independent release cadence, partner packs). No calendar trigger.
•	Mobile: single bundle with conditional packs vs two App Store listings (UK / SA). Both are supportable; decision deferred to the Phase 1 mobile refresh.
19.4 Out of scope for this document
•	The actual SA tax/retirement/estate rules — those live in the SA research document (v1.1)
•	Phase 0 execution detail — see the Phase 0 Implementation Guide
•	Team scaling and release cadence — see the Scaling Playbook
•	UK-SA DTA article-by-article mapping — deferred to the cross-border specification
•	Regulatory authorisation questions (FCA / FSCA permissions) — legal track, not engineering
 
20. Appendix A — interface stubs (PHP)
Illustrative signatures only — see core/Contracts/* in the codebase for the authoritative definitions.
namespace Fynla\Core\Contracts;

interface CountryPack {
    public function code(): string;              // 'GB', 'ZA'
    public function name(): string;              // 'United Kingdom'
    public function currency(): string;          // 'GBP'
    public function defaultLocale(): string;     // 'en-GB'
    public function taxYearStart(): string;      // MM-DD, e.g. '04-06'
    public function modules(): array;            // enabled module keys
    public function engines(): array;            // FQN map: Contract => implementation
}

interface TaxEngine {
    public function brackets(TaxYear $ty): Collection;
    public function rebates(User $u, TaxYear $ty): Money;
    public function computeIncomeTax(Money $taxable, User $u, TaxYear $ty): Money;
    public function computeCapitalGainsTax(Money $gain, User $u, TaxYear $ty): Money;
    public function computeDeathTax(Estate $estate): Money;
    public function deductions(User $u, TaxYear $ty): Collection;
}

interface RetirementEngine {
    public function productTypes(): array;
    public function contributionLimits(User $u, TaxYear $ty): ContributionLimits;
    public function decumulation(RetirementAccount $a): DecumulationRules;
    public function allowanceStatus(User $u, TaxYear $ty): AllowanceStatus;
}

interface ExchangeControl {
    public function allowances(): array;                         // [] for GB, [SDA, FIA] for ZA
    public function recordTransfer(ExchangeTransfer $t): void;   // no-op for GB
    public function remainingHeadroom(User $u, int $calYr): array;
}

interface Localisation {
    public function formatMoney(Money $m): string;
    public function formatDate(Carbon $d, string $style = 'long'): string;
    public function disclaimer(string $moduleKey): string;
    public function spellingLocale(): string;                    // 'en-GB'
}
 
21. Appendix B — file tree diagram
fynla/
  composer.json                 (path repositories for all packs)
  package.json                  (workspace root for frontend)
  core/
    app/
      Agents/CoordinatingAgent.php
      Contracts/{CountryPack,TaxEngine,RetirementEngine,…}.php
      Http/Middleware/ActiveJurisdictionMiddleware.php
      Models/{User,Household,Goal,LifeEvent,Jurisdiction}.php
      Services/{PackRegistry,FxService,CurrencyService}.php
      Support/Money.php
    database/migrations/  (core tables)
    config/{fynla.php, countries.php}
  packs/
    country-gb/                 (composer package)
    country-za/                 (composer package)
    cross-border/               (composer package; depends on country-gb + country-za)
  frontend/
    core/                       (router shell, layout, auth, design system)
    packs/
      gb/                       (views, stores, routes, i18n)
      za/
      global/
  deploy/
    gb/{build.sh,…}
    za/{build.sh,…}
    mobile/{build-ios-gb.sh,build-ios-za.sh,…}
  docs/
    architecture.md             (this document)
    design/fynlaDesignGuide.md
    countries/{gb,za}/README.md
 
22. Appendix C — glossary
Term	Meaning
Core	Country-agnostic application code shared by every deployment
Country Pack (or Pack)	A composer package + Vue feature bundle containing one country’s tax, product and localisation logic
Jurisdiction	A country in which Fynla is licensed / compliant (GB, ZA, later IE, AU…)
Active Jurisdictions	The subset of jurisdictions currently enabled for a specific user
Primary Jurisdiction	A user’s default jurisdiction — drives currency, landing page, billing
Contract	A PHP interface in Fynla\Core\Contracts that every Pack must satisfy
Cross-Border Pack	Optional Pack that contains residency, DTA and worldwide-estate logic; activates when ≥ 2 Active Jurisdictions
Pack Registry	Core service that discovers and introspects installed Packs
ActiveJurisdictionMiddleware	Rejects API requests for a Pack the authenticated user has not activated
Null implementation	A safe ‘does nothing’ binding for Contracts that a country has no equivalent for (e.g. ExchangeControl on GB)
