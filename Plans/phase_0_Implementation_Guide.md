Fynla
Phase 0 Implementation Guide
Extracting the core, adopting composer path-repository packs, preparing for South Africa
Prepared for: Chris Slater-Jones
Date: 15 April 2026
Version: 1.0
Companion to: Fynla SA Research & Mapping (v1.0), Fynla Multi-Country Architecture (v1.0)
 
1. Executive summary
Phase 0 is the preparatory workstream that converts today's monolithic Fynla codebase into a core + country pack structure without changing any user-visible behaviour. By the end of Phase 0, the UK application still runs exactly as it does today — same URLs, same DB, same design system, same iOS app — but the code is reshaped so that (a) a Fynla\Core namespace exists with no country-specific knowledge, (b) a packs/country-gb composer package holds all UK-specific code and is loaded via a composer path repository, and (c) the application is ready to accept a second pack (packs/country-za) without further structural change.
Phase 0 is a 12-week programme. It is entirely internal; nothing ships to users except a silent cutover when the repackaged UK deploy goes live. The acceptance gate is simple: all 940+ Pest tests still pass, the full UK journey in Playwright still passes, and a synthetic packs/country-xx-smoke pack can be loaded alongside country-gb without either pack breaking.
This guide is written to be executed step by step. Every section lists concrete files to create, commands to run, and acceptance criteria. Every decision point that was resolved earlier in the architecture conversation is recorded in §2 so that future engineers can see why the choice was made and under what conditions it should be revisited.
 
2. Decisions recorded (ADR summary)
Each row here is an architectural decision already made. A more formal ADR should be committed under docs/adr/ in the repo as the first PR of Phase 0.
ADR	Decision	Status
ADR-001	Single codebase, core + country packs (not separate forks per country)	Accepted
ADR-002	Packs are composer packages loaded via path repositories, starting day one (not nwidart/laravel-modules, not private Packagist yet)	Accepted
ADR-003	Strict pack isolation. Packs never import each other. Cross-country logic lives in a separate cross-border pack that depends on both.	Accepted
ADR-004	Jurisdictional scoping is mandatory. A user has 0..n active jurisdictions; the router, navigation and middleware all respect this.	Accepted
ADR-005	Money is an integer value object with an explicit currency. Floats for money are banned from all new code in core and packs.	Accepted
ADR-006	Tax year is a semantic interval (jurisdiction + label + start/end dates). No code may assume 6 April or 1 March.	Accepted
ADR-007	Per-pack database table prefix (gb_, za_, cb_). Core tables are unprefixed. No cross-pack foreign keys; references go via a core table (e.g. users, goals).	Accepted
ADR-008	Frontend feature bundles are lazy-loaded per active pack. A UK-only user never downloads the SA bundle.	Accepted
ADR-009	Disclaimers, legal copy and compliance text are pack-owned strings. Core holds no FCA/FSCA/FAIS wording.	Accepted
ADR-010	Design system stays in core. Packs consume tokens from @fynla/core-ui; they do not define colours, fonts or spacing.	Accepted
 
3. Prerequisites & success criteria
3.1 Prerequisites
•	Current main branch is green: 940+ Pest tests pass, Playwright UK journeys pass, fynla.org deploy is healthy.
•	A full logical backup of the production DB is available and restorable to a staging instance.
•	The csjones.co/fynla staging environment is matching main and is writable (we will use it heavily).
•	A feature branch feature/csj/phase-0 is cut from main and protected by CODEOWNERS for @Stoff73 review.
•	docs/adr/ is created and ADR-001..010 above are committed as separate markdown files.
3.2 Success criteria (Phase 0 exit gate)
•	All 940+ Pest tests pass under the repackaged layout.
•	New architecture test suite passes: no core file imports Fynla\Packs\*; no pack imports another pack; no pack references raw currency strings outside its own namespace.
•	Playwright UK journey for each preview persona completes end-to-end against the repackaged build.
•	A synthetic pack packs/country-xx-smoke can be added to the repositories block and registered without touching core code. It exposes a health endpoint GET /api/xx/health returning 200 and no core routes break.
•	composer update in root refreshes pack versions without error; composer validate passes for core and every pack.
•	CI matrix runs core-only, gb-only, and gb+smoke builds; all three green.
•	The UK production deploy on fynla.org is cutover, is serving real users, and post-deploy error budget for 72 hours is within the normal baseline.
 
4. Target directory structure
4.1 Before (today)
fynla/
  app/                  ← mixed: core + UK-specific side by side
    Agents/             ← ProtectionAgent, SavingsAgent, ... (UK logic)
    Services/           ← 214 services, mixed UK + cross-cutting
    Http/Controllers/   ← 94 controllers, UK routes
    Models/             ← 94 models, UK schema assumptions
  resources/js/         ← Vue: 443 components, 31 Vuex modules (UK)
  database/migrations/  ← all UK tables, unprefixed
  tests/                ← Pest suites
  deploy/fynla-org/     ← prod build
  deploy/csjones-fynla/ ← dev build
4.2 After Phase 0
fynla/
  composer.json         ← root; declares path repositories
  core/                 ← Laravel app skeleton (now ‘Fynla Core’)
    app/Core/           ← Fynla\Core\* — no UK code
    app/Http/           ← core controllers only (auth, health, core settings)
    resources/js/core/  ← shell, layouts, design system, router, auth
    database/migrations/
                        ← core-only tables (users, households, jurisdictions,
                          user_jurisdictions, tax_years, goals, audit_log)
    tests/              ← core + architecture suites
  packs/
    _template/          ← scaffold pack: copy to bootstrap a new country
    country-gb/         ← UK pack (composer pkg fynla/pack-country-gb)
      composer.json
      src/              ← Fynla\Packs\GB\*
      resources/js/     ← GB feature bundle (lazy-loaded)
      database/migrations/
                        ← gb_* tables only
      routes/api.php    ← /api/gb/*
      tests/            ← Pest suite scoped to the pack
  frontend/
    package.json        ← workspace root
    packages/core-ui/   ← design tokens, primitives
    packages/core-app/  ← shell, router, auth, store root
    packages/pack-gb/   ← GB feature bundle
  deploy/
    fynla-org/          ← prod build, FYNLA_ACTIVE_PACKS=country-gb
    csjones-fynla/      ← dev build
  docs/
    adr/                ← architectural decision records
    contracts/          ← Country Pack contract spec
The top level still looks like a single Laravel application from the deployer's point of view — artisan commands still run from the repo root; the build scripts under deploy/ are unchanged in spirit. What changes is where the code physically lives and which namespace it sits under.
 
5. Workstream A — monorepo and pack scaffolding (week 1–2)
5.1 Root composer.json
Create a root composer.json that declares path repositories for every pack. This is the mechanism that turns packs/country-gb into a real composer package without us publishing to Packagist.
{
  "name": "fynla/fynla",
  "type": "project",
  "license": "proprietary",
  "repositories": [
    { "type": "path", "url": "packs/country-gb", "options": { "symlink": true } },
    { "type": "path", "url": "packs/_template",  "options": { "symlink": true } }
  ],
  "require": {
    "php": "^8.2",
    "laravel/framework": "^10.0",
    "fynla/pack-country-gb": "*@dev"
  },
  "autoload": {
    "psr-4": {
      "Fynla\\Core\\": "core/app/Core/",
      "App\\":            "core/app/"
    }
  },
  "extra": {
    "laravel": {
      "dont-discover": []
    }
  }
}
Symlinked path repositories mean an edit inside packs/country-gb/src takes effect immediately; no composer update loop. In Phase 0 we only symlink; in the scaling playbook we cover cutting over to a private composer registry.
5.2 Pack template (packs/_template)
Create packs/_template as the canonical scaffold. A new country is bootstrapped with cp -r packs/_template packs/country-xx and a name-replace pass.
packs/_template/
  composer.json
  README.md
  src/
    Providers/CountryPackServiceProvider.php
    Contracts/                  ← empty; pack implements Core contracts
    Tax/TaxEngine.php           ← stub impl of Fynla\Core\Contracts\TaxEngine
    Retirement/RetirementEngine.php
    Investment/InvestmentEngine.php
    Protection/ProtectionEngine.php
    Estate/EstateEngine.php
    ExchangeControl/NoopExchangeControl.php
    Identity/IdentityValidator.php
    Localisation/Localisation.php
    Banking/BankingValidator.php
    Http/
      Controllers/HealthController.php  ← GET /api/{cc}/health
      Middleware/
    Support/PackManifest.php    ← describes navigation items, routes, currency
  resources/js/
    index.ts                    ← feature bundle entry (registers Vue routes/components)
    components/
    store/
  routes/api.php
  database/migrations/
  database/seeders/
  tests/
    Architecture/PackIsolationTest.php
    Feature/HealthTest.php
5.3 Pack composer.json (template)
{
  "name": "fynla/pack-country-xx",
  "type": "library",
  "description": "Fynla country pack — <Country>",
  "license": "proprietary",
  "require": {
    "php": "^8.2",
    "fynla/core": "*@dev"
  },
  "autoload": {
    "psr-4": {
      "Fynla\\Packs\\XX\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "Fynla\\Packs\\XX\\Providers\\CountryPackServiceProvider"
      ]
    },
    "fynla": {
      "pack": {
        "code": "xx",
        "currency": "XXX",
        "locale": "en-XX",
        "tableprefix": "xx_"
      }
    }
  }
}
5.4 CountryPackServiceProvider (template)
<?php
namespace Fynla\Packs\XX\Providers;

use Illuminate\Support\ServiceProvider;
use Fynla\Core\Contracts\TaxEngine;
use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\XX\Support\PackManifest;

class CountryPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind("pack.xx.tax",        \Fynla\Packs\XX\Tax\TaxEngine::class);
        $this->app->bind("pack.xx.retirement", \Fynla\Packs\XX\Retirement\RetirementEngine::class);
        // …
    }

    public function boot(PackRegistry $registry): void
    {
        $registry->register(PackManifest::describe());

        $this->loadRoutesFrom(__DIR__."/../../routes/api.php");
        $this->loadMigrationsFrom(__DIR__."/../../database/migrations");
        $this->loadTranslationsFrom(__DIR__."/../../resources/lang", "xx");
    }
}
5.5 Acceptance for Workstream A
•	composer install succeeds at the repo root.
•	composer validate passes in root, packs/_template, and packs/country-gb (even though country-gb is empty at this stage — just the scaffold).
•	php artisan route:list shows the template pack's /api/xx/health route when the template is enabled via FYNLA_ACTIVE_PACKS=_template.
•	Architecture test: no file under core/ references Fynla\Packs\.
 
6. Workstream B — core contracts & pack registry (week 2–4)
This workstream creates the contracts that every pack must satisfy. Pack implementations are thin; the contracts are the real product.
6.1 Namespace layout in core
core/app/Core/
  Contracts/
    CountryPack.php
    TaxEngine.php
    RetirementEngine.php
    InvestmentEngine.php
    ProtectionEngine.php
    EstateEngine.php
    ExchangeControl.php
    IdentityValidator.php
    BankingValidator.php
    Localisation.php
    PaymentProcessor.php
    LifeTableProvider.php
  Registry/
    PackRegistry.php        ← in-memory catalogue of registered packs
    PackManifest.php        ← value object describing one pack
  Money/
    Money.php               ← int minor units + Currency
    Currency.php
  TaxYear/
    TaxYear.php             ← jurisdiction + label + start/end
    TaxYearResolver.php
  Jurisdiction/
    Jurisdiction.php
    ActiveJurisdictions.php ← resolves from auth user
  Http/
    Middleware/ActiveJurisdictionMiddleware.php
    Middleware/EnsurePackEnabled.php
6.2 CountryPack contract (core)
<?php
namespace Fynla\Core\Contracts;

use Fynla\Core\Registry\PackManifest;

interface CountryPack
{
    public function code(): string;             // 'gb', 'za'
    public function manifest(): PackManifest;   // nav, currency, locale, routes
    public function taxEngine(): TaxEngine;
    public function retirementEngine(): RetirementEngine;
    public function investmentEngine(): InvestmentEngine;
    public function protectionEngine(): ProtectionEngine;
    public function estateEngine(): EstateEngine;
    public function exchangeControl(): ExchangeControl;   // may be no-op
    public function identityValidator(): IdentityValidator;
    public function banking(): BankingValidator;
    public function localisation(): Localisation;
}
6.3 PackRegistry & ActiveJurisdictionMiddleware
PackRegistry is a singleton keyed by country code. During boot, each CountryPackServiceProvider calls PackRegistry::register(). ActiveJurisdictionMiddleware reads FYNLA_ACTIVE_PACKS (for installation-level enabling) and the authenticated user's user_jurisdictions (for user-level entitlement), then rejects any request targeting /api/{cc}/* where the user has no entitlement to {cc}.
Route::middleware(['auth:sanctum', 'active.jurisdiction:{cc}'])
    ->prefix('api/{cc}')
    ->group(function () {
        // routes loaded by each pack
    });
6.4 Acceptance for Workstream B
•	All 12 core contracts exist with PHPDoc that references the appropriate domain concepts (no UK-specific terms in signatures).
•	PackRegistry unit tests cover: register, duplicate-prevention, listEnabled, byCountryCode.
•	ActiveJurisdictionMiddleware feature test covers: user without entitlement gets 403, user with entitlement passes through, installation not enabling the pack returns 404.
•	Architecture test: every interface under Fynla\Core\Contracts\ has at least one implementation under Fynla\Packs\* (once the GB pack lands in Workstream E).
 
7. Workstream C — Money value object & currency migration (week 3–5)
The single most invasive change in Phase 0 is replacing free-floating decimals with a Money value object. We execute this as a dual-read / dual-write / backfill / flip migration so we never break prod.
7.1 Money contract
<?php
namespace Fynla\Core\Money;

final class Money
{
    public function __construct(
        public readonly int $minor,
        public readonly Currency $currency,
    ) {}

    public static function ofMajor(string $decimal, Currency $c): self {/* … */}
    public function plus(Money $other): self {/* same-ccy guard */}
    public function minus(Money $other): self {/* same-ccy guard */}
    public function times(float|int $x): self {/* banker's rounding */}
    public function isZero(): bool { return $this->minor === 0; }
    public function format(Localisation $l): string {/* … */}
}
7.2 DB migration pattern (per money column)
For every DECIMAL money column in today's schema, we add two shadow columns, dual-write through a trait, backfill, then flip reads. The old column is kept for one release and then dropped.
// Step 1 — add shadow columns (deployed, no behaviour change)
Schema::table('accounts', function (Blueprint $t) {
    $t->bigInteger('balance_minor')->nullable();
    $t->char('balance_ccy', 3)->nullable();
});

// Step 2 — dual-write in the model via HasMoney trait
// Step 3 — backfill job reads decimal, writes (minor, ccy)
// Step 4 — flip reads: accessor returns Money from (minor, ccy)
// Step 5 — in the next release, drop the decimal column
7.3 Scope of the migration
•	Identify all DECIMAL columns that represent money (use a one-off artisan command that greps migrations + a manual review pass).
•	For each: create the shadow-column migration, update the model with the HasMoney trait, write a backfill job.
•	Run backfill on staging; verify checksums: sum(balance) in GBP equals sum(balance_minor)/100 where balance_ccy = 'GBP'.
•	Only after staging is verified do we run backfill on prod.
7.4 Guardrails
•	Architecture test: no new file may use float for a money-named variable or column (regex scan on PR).
•	Linter rule in Vue: no Number() on an amount field; must go through the Money helper.
•	Legacy compatibility: core/app/Core/Money/LegacyDecimalAccessor.php provides a bridge for the handful of places (e.g. export CSVs) where a decimal string is still the contract.
7.5 Acceptance for Workstream C
•	All money columns have shadow (_minor, _ccy) columns populated on staging and prod.
•	All domain services read and write through Money; no float arithmetic on amounts in the codebase (proven by the architecture test).
•	Pest tests for Money cover: construction, addition, subtraction, multiplication with banker's rounding, currency mismatch exception, formatting through Localisation.
 
8. Workstream D — jurisdictional data model (week 4–5)
8.1 New core tables
Table	Columns (abbrev.)	Notes
jurisdictions	id, code, name, currency, locale, active	Seeded with GB on Phase 0; ZA added in Phase 2
user_jurisdictions	user_id, jurisdiction_id, primary, activated_at	Composite unique (user_id, jurisdiction_id)
tax_years	id, jurisdiction_id, label, starts_on, ends_on	E.g. (GB, '2025/26', 2025-04-06, 2026-04-05)
8.2 Backfill of existing users
•	Create jurisdictions row for GB (code 'GB', currency 'GBP', locale 'en-GB').
•	For every existing user, insert a user_jurisdictions row (user_id, GB, primary=true, activated_at=created_at).
•	Seed tax_years for GB from 2020/21 up to 2026/27.
•	After backfill, a CHECK constraint ensures every non-deleted user has at least one user_jurisdictions row.
8.3 Route resolution
Existing routes move from /api/protection/* etc. to /api/gb/protection/*. A compatibility layer (to be deleted at end of Phase 1) transparently rewrites old URLs to the new shape for 60 days, logging each hit to detect any mobile client still on the old base URL.
8.4 Acceptance for Workstream D
•	Every existing user has exactly one user_jurisdictions row pointing at GB.
•	/api/gb/protection/policies returns identical payloads to today's /api/protection/policies (equality test in Pest).
•	TaxYearResolver returns the correct tax year for today's date in the GB jurisdiction.
 
9. Workstream E — repackage UK into country-gb (week 5–9)
This is the largest piece of lifting. Every UK-specific file gets moved into packs/country-gb/src under the Fynla\Packs\GB namespace. A temporary compatibility alias is kept in core/app until the cutover PR.
9.1 Move map
From (today)	To (Phase 0 target)	Notes
app/Agents/*	packs/country-gb/src/Agents/*	Rename Fynla\Packs\GB\Agents\*
app/Services/Protection/*	packs/country-gb/src/Protection/*	Ditto
app/Services/Retirement/*	packs/country-gb/src/Retirement/*	Split out TaxConfigService — see §9.4
app/Services/Tax*	packs/country-gb/src/Tax/*	Implements Fynla\Core\Contracts\TaxEngine
app/Http/Controllers/Api/Protection/*	packs/country-gb/src/Http/Controllers/Protection/*	Route prefix becomes /api/gb/protection
app/Models/{Policy,Pension,Investment,...}	packs/country-gb/src/Models/*	Adds gb_ table prefix
database/migrations/2024_*_uk_*.php	packs/country-gb/database/migrations/*	Rename tables with gb_ prefix in a paired migration
resources/js/components/{Protection,Savings,Investment,Retirement,Estate}/*	frontend/packages/pack-gb/src/components/*	Lazy-loaded bundle
resources/js/store/modules/{protection,savings,investment,retirement,estate}.js	frontend/packages/pack-gb/src/store/*	Namespaced 'gb/protection', etc.
9.2 Mechanical move discipline
•	One module at a time: Protection first (smallest), then Savings, Investment, Retirement, Estate.
•	Per-module PR: move files, update namespace, update controller routes, run Pest, run Playwright for that module only, merge.
•	Never combine a move with a behaviour change. Pure relocation PRs only.
•	The cutover of URLs (/api/gb/*) is a separate, atomic PR at the end of the workstream.
9.3 TaxConfigService & seeders
•	TaxConfigService moves to packs/country-gb/src/Tax/TaxConfigService.php. Its interface becomes Fynla\Core\Contracts\TaxEngine.
•	TaxConfigurationSeeder becomes the GB tax seeder. It runs only when country-gb is enabled.
•	Core seeds jurisdictions, but not brackets.
9.4 Design system & shared UI
•	frontend/packages/core-ui owns the fynlaDesignGuide.md v1.2.0 tokens and primitives.
•	Pack-GB imports only from @fynla/core-ui. Architecture test on frontend forbids pack bundles from declaring their own colour or font tokens.
9.5 Acceptance for Workstream E
•	All 940+ Pest tests pass after every module move.
•	Full UK Playwright journey passes for every preview persona.
•	No file under core/app references Fynla\Packs\GB (architecture test).
•	Bundle analyser shows the GB frontend bundle is loaded on demand, not in the initial shell chunk.
 
10. Workstream F — testing, CI and CODEOWNERS (week 8–11)
10.1 New Pest suites
•	tests/Architecture/CoreIndependenceTest.php — scans core/app for any symbol starting with Fynla\Packs\; fails if found.
•	tests/Architecture/PackIsolationTest.php — scans packs/country-gb/src for Fynla\Packs\ZA (or any other pack namespace); fails if found.
•	tests/Architecture/NoFloatMoneyTest.php — ensures no float arithmetic on Money.
•	tests/Architecture/NoHardcodedLegalCopyTest.php — greps core for 'FCA', 'FSCA', 'HMRC', 'SARS' strings; fails if found.
•	tests/Contract/CountryPackConformanceTest.php — shared scenario pack that every CountryPack implementation must pass (run against country-gb now, country-za later).
10.2 CI matrix
# .github/workflows/ci.yml (abbreviated)
strategy:
  matrix:
    target:
      - { name: 'core-only',   packs: '' }
      - { name: 'gb-only',     packs: 'country-gb' }
      - { name: 'gb+smoke',    packs: 'country-gb,country-xx-smoke' }
env:
  FYNLA_ACTIVE_PACKS: ${{ matrix.target.packs }}
steps:
  - composer install --no-interaction --no-progress
  - composer validate --strict --no-check-publish
  - ./vendor/bin/pest --parallel
  - cd frontend && pnpm install && pnpm -r build
  - pnpm --filter pack-gb test
10.3 CODEOWNERS
# .github/CODEOWNERS
*                        @Stoff73
/core/**                 @Stoff73
/packs/country-gb/**     @Stoff73
/frontend/packages/core-ui/**   @Stoff73
/docs/adr/**             @Stoff73
In Phase 0 the Core owner and the GB-pack owner are the same person. The scaling playbook covers how this splits when a country team arrives.
10.4 Acceptance for Workstream F
•	All four architecture tests pass on main.
•	CI matrix green for core-only, gb-only, and gb+smoke.
•	CODEOWNERS enforced on every PR.
 
11. Workstream G — deploy, cutover and rollback (week 11–12)
11.1 Deploy plan
•	Rehearse twice on csjones.co/fynla. Each rehearsal resets staging to a snapshot of prod taken the morning of the rehearsal.
•	Rehearsal checklist: composer install, php artisan migrate --force, php artisan db:seed --force, php artisan cache:clear && config:clear && view:clear && route:clear && optimize, smoke Playwright, monitor laravel.log for 30 minutes.
•	Production window: midweek morning (Tue/Wed), 30-minute maintenance banner, feature flag set to read-only for 10 minutes before cut.
11.2 Cutover sequence (production)
•	Build locally: ./deploy/fynla-org/build.sh (updated to set FYNLA_ACTIVE_PACKS=country-gb).
•	Upload public/build/ and changed PHP files to ~/www/fynla.org/public_html/.
•	SSH in: php artisan migrate --force (only shadow-column and jurisdictions migrations run — already rehearsed); then cache clears.
•	Flip the .htaccess (if touched). Smoke.
•	Monitor laravel.log and the APM dashboard for 72 hours.
11.3 Rollback plan
•	Branch tag pre-phase0 on main is the known-good reference.
•	If cutover fails before migrations: revert the deployed files; no DB touch; fynla.org is back within minutes.
•	If cutover fails after jurisdictions backfill: roll forward, not back. The backfill is idempotent and additive; removing it is harder than fixing forward.
•	If cutover fails after the Money shadow-column migration: keep shadow columns; revert code that flips reads; continue to write dually. Old reads still work.
•	Never drop the old decimal columns in Phase 0. Their removal is a Phase 1 PR, after 30 days of successful Money-only reads.
 
12. 12-week timeline
Week	Workstream focus	Exit
1	A — monorepo scaffold, root composer.json, _template pack, docs/adr/ seeded	composer install clean; ADRs committed
2	A — packs/country-gb empty scaffold + synthetic country-xx-smoke pack	route:list shows both packs' /api/{cc}/health endpoints
3	B — Core\Contracts\* interfaces, PackRegistry, ActiveJurisdictionMiddleware	Contracts merged; architecture tests in place
4	B + C — Middleware tests green; Money value object + HasMoney trait	Money Pest suite green; audit of all decimal columns
5	C + D — Shadow-column migrations; jurisdictions + user_jurisdictions + tax_years	Backfill dry run green on staging
6	D + E — GB backfill on prod; move Protection module into country-gb	Protection module passing all tests under Fynla\Packs\GB
7	E — move Savings and Investment modules	Savings + Investment tests green
8	E — move Retirement and Estate modules	Retirement + Estate tests green
9	E — move remaining services, observers, traits; design system to core-ui	No core/ file references Fynla\Packs\GB
10	F — CI matrix, architecture tests, CODEOWNERS; Playwright UK journeys	CI matrix all green
11	G — Staging rehearsal #1, rehearsal #2, bug fixes from rehearsals	Two successful rehearsals
12	G — Production cutover + 72h monitoring	Phase 0 exit gate passed
 
13. Risk register
Risk	Likelihood	Impact	Mitigation
Mass move introduces subtle namespace conflicts	M	H	One module per PR; Pest runs on each; no behaviour changes in move PRs
Mobile app breaks on /api/gb/* URL shape	M	H	60-day URL rewrite compatibility layer; explicit iOS build after Workstream E; App Store release gated
Money migration off-by-one on rounding	L	H	Checksum assertion post-backfill (sum of majors equals sum of minors / 100 within tolerance 0)
Architecture tests flag known-acceptable exceptions	M	L	Exceptions list with explicit allow-listing and ADR reference; ADR revisited quarterly
Developer resistance to pack discipline	M	M	Architecture tests are enforced in CI, not a style guide; PR template requires pack designation
Staging differs from prod and rehearsal misleads	L	H	Snapshot prod into staging the morning of each rehearsal; rehearse twice; maintenance window booked for cutover
 
14. Explicitly out of scope for Phase 0
•	Any South Africa code. Not a line. SA begins in Phase 2 per the Multi-Country Architecture document.
•	The cross-border pack. That lands in Phase 3 once two country packs exist.
•	Private composer registry / Packagist. Path repositories are sufficient for this phase; the scaling playbook covers when to graduate.
•	Splitting the repo into multiple repos. Single monorepo is retained through Phase 0 — and, the scaling playbook argues, well beyond it.
•	Dropping the old DECIMAL money columns. Shadow-column dual-write stays for 30 days after Phase 0 exit; dropping them is a Phase 1 PR.
•	Mobile API version bump. The URL rewrite compatibility layer covers the Phase 0 window; a versioned /api/v2/* scheme is a Phase 1+ conversation.
 
15. Acceptance checklist (to be ticked at cutover + 72h)
•	[ ] 940+ Pest tests pass on main
•	[ ] Architecture suite green (CoreIndependence, PackIsolation, NoFloatMoney, NoHardcodedLegalCopy)
•	[ ] Contract conformance suite green against country-gb
•	[ ] Full UK Playwright journey passes for every preview persona
•	[ ] gb+smoke CI target green
•	[ ] Production deploy green; laravel.log baseline within normal bounds for 72 hours
•	[ ] iOS build passes with new URL shape; TestFlight build validated
•	[ ] 10 ADRs committed under docs/adr/
•	[ ] CODEOWNERS covers /core/**, /packs/country-gb/**, /frontend/packages/core-ui/**, /docs/adr/**
•	[ ] Rollback runbook reviewed and signed off by @Stoff73
 
16. Appendix A — full root composer.json (reference)
{
  "name": "fynla/fynla",
  "type": "project",
  "description": "Fynla — personal financial planning (core + country packs)",
  "license": "proprietary",
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": [
    { "type": "path", "url": "packs/country-gb",        "options": { "symlink": true } },
    { "type": "path", "url": "packs/country-xx-smoke",  "options": { "symlink": true } },
    { "type": "path", "url": "packs/_template",         "options": { "symlink": true } }
  ],
  "require": {
    "php": "^8.2",
    "laravel/framework": "^10.0",
    "laravel/sanctum": "^3.0",
    "fynla/pack-country-gb": "*@dev"
  },
  "require-dev": {
    "pestphp/pest": "^2.0",
    "laravel/pint": "^1.0",
    "fynla/pack-country-xx-smoke": "*@dev"
  },
  "autoload": {
    "psr-4": {
      "Fynla\\Core\\": "core/app/Core/",
      "App\\":            "core/app/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Tests\\": "tests/"
    }
  },
  "scripts": {
    "post-autoload-dump": [
      "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
      "@php artisan package:discover --ansi"
    ]
  },
  "config": {
    "sort-packages": true,
    "allow-plugins": { "pestphp/pest-plugin": true }
  }
}
 
17. Appendix B — full pack composer.json (country-gb)
{
  "name": "fynla/pack-country-gb",
  "type": "library",
  "description": "Fynla UK country pack (Protection, Savings, Investment, Retirement, Estate)",
  "license": "proprietary",
  "require": {
    "php": "^8.2",
    "illuminate/support": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "Fynla\\Packs\\GB\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Fynla\\Packs\\GB\\Tests\\": "tests/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "Fynla\\Packs\\GB\\Providers\\CountryPackServiceProvider"
      ]
    },
    "fynla": {
      "pack": {
        "code": "gb",
        "currency": "GBP",
        "locale": "en-GB",
        "tableprefix": "gb_",
        "taxYearStart": "04-06",
        "regulator": "FCA"
      }
    }
  }
}
 
18. Appendix C — CountryPackServiceProvider skeleton (country-gb)
<?php
declare(strict_types=1);

namespace Fynla\Packs\GB\Providers;

use Illuminate\Support\ServiceProvider;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Core\Registry\PackManifest;

use Fynla\Core\Contracts\TaxEngine;
use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Core\Contracts\ProtectionEngine;
use Fynla\Core\Contracts\EstateEngine;
use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Core\Contracts\IdentityValidator;
use Fynla\Core\Contracts\BankingValidator;
use Fynla\Core\Contracts\Localisation;

use Fynla\Packs\GB\Tax\TaxConfigService;
use Fynla\Packs\GB\Retirement\UKRetirementEngine;
use Fynla\Packs\GB\Investment\UKInvestmentEngine;
use Fynla\Packs\GB\Protection\UKProtectionEngine;
use Fynla\Packs\GB\Estate\UKEstateEngine;
use Fynla\Packs\GB\ExchangeControl\NoopExchangeControl;
use Fynla\Packs\GB\Identity\UKIdentityValidator;
use Fynla\Packs\GB\Banking\UKBankingValidator;
use Fynla\Packs\GB\Localisation\UKLocalisation;

final class CountryPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TaxEngine::class,         TaxConfigService::class);
        $this->app->bind(RetirementEngine::class,  UKRetirementEngine::class);
        $this->app->bind(InvestmentEngine::class,  UKInvestmentEngine::class);
        $this->app->bind(ProtectionEngine::class,  UKProtectionEngine::class);
        $this->app->bind(EstateEngine::class,      UKEstateEngine::class);
        $this->app->bind(ExchangeControl::class,   NoopExchangeControl::class);
        $this->app->bind(IdentityValidator::class, UKIdentityValidator::class);
        $this->app->bind(BankingValidator::class,  UKBankingValidator::class);
        $this->app->bind(Localisation::class,      UKLocalisation::class);
    }

    public function boot(PackRegistry $registry): void
    {
        $registry->register(new PackManifest(
            code: 'gb',
            name: 'United Kingdom',
            currency: 'GBP',
            locale: 'en-GB',
            regulator: 'FCA',
            modules: ['protection','savings','investment','retirement','estate','goals'],
        ));

        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'gb');
    }
}
 
19. Appendix D — architecture test examples
// tests/Architecture/CoreIndependenceTest.php
arch('core does not know about packs')
    ->expect('Fynla\\Core')
    ->not->toUse('Fynla\\Packs');

// tests/Architecture/PackIsolationTest.php
arch('GB pack does not reference any other pack')
    ->expect('Fynla\\Packs\\GB')
    ->not->toUse(['Fynla\\Packs\\ZA', 'Fynla\\Packs\\CrossBorder']);

// tests/Architecture/NoFloatMoneyTest.php
arch('money math is integer')
    ->expect('Fynla\\Core\\Money\\Money')
    ->toOnlyUse(['Fynla\\Core\\Money\\Currency', 'InvalidArgumentException']);

// tests/Architecture/NoHardcodedLegalCopyTest.php
test('core contains no regulator strings', function () {
    $hits = shell_exec("grep -rnE '\\b(FCA|FSCA|HMRC|SARS|PRA|FAIS|SARB)\\b' core/app || true");
    expect(trim((string) $hits))->toBe('');
});
 
20. Closing note
Phase 0 is the only phase that is pure refactor. Every subsequent phase — repackaging the UK bundle for the iOS app in Phase 1, standing up the SA pack in Phase 2, delivering the cross-border layer in Phase 3 — assumes the scaffolding described here. Skipping any part of Phase 0 is load-bearing debt: it will be paid, with interest, during the first real country addition.
On the other hand, once Phase 0 is done, adding South Africa becomes a self-contained body of work that touches zero core code. That is the prize, and it is worth the 12 weeks this guide describes.
