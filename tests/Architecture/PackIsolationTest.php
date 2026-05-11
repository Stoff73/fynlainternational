<?php

declare(strict_types=1);

describe('Pack Isolation', function () {
    it('country-gb does not reference other pack namespaces', function () {
        $packDir = base_path('packs/country-gb/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-gb/src directory not found');
        }

        $violations = [];
        // Pack namespaces are Pascal-case (Fynla\Packs\Gb\…, Fynla\Packs\Za\…),
        // mirroring composer.json autoload entries. Negative lookahead matches
        // the actual namespace casing, not the ISO country code.
        $otherPackPattern = '/Fynla\\\\Packs\\\\(?!Gb\\\\)/';  // Match any pack except Gb

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            if (preg_match($otherPackPattern, $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'GB pack must not reference other pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-gb does not import the App namespace (outside provider wiring)', function () {
        $packDir = base_path('packs/country-gb/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-gb/src directory not found');
        }

        // R-2: provider wiring (Providers/) may still reference \App\Services\…
        // while UK code moves in across R-3 → R-9. R-15 ratchets the exemption
        // away.
        //
        // R-3: Constants/ and Traits/ have App\Models\* and App\Services\*
        // imports that don't move until R-4 (Models) and R-5 (Tax services).
        //
        // R-4: Models/ relationships still reference User / Household / Goal /
        // LifeEvent et al. that are deferred in App\Models\ (heavy cross-pack
        // refs would trip CoreIndependenceTest if moved to core today).
        //
        // Each import is allow-listed below. Subsequent workstreams shrink
        // the allow-list; R-15 closes the exemption.
        $exemptDirs = [
            $packDir . DIRECTORY_SEPARATOR . 'Providers' . DIRECTORY_SEPARATOR,
            $packDir . DIRECTORY_SEPARATOR . 'Constants' . DIRECTORY_SEPARATOR,
            $packDir . DIRECTORY_SEPARATOR . 'Traits' . DIRECTORY_SEPARATOR,
            $packDir . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR,
            // R-5: Estate/Tax services still import App\Services\* peers
            // (Investment, Retirement, Goals, Risk, Settings, Cache, Shared,
            // UserProfile) that relocate in R-6/R-7. Pinned by allow-list.
            $packDir . DIRECTORY_SEPARATOR . 'Estate' . DIRECTORY_SEPARATOR,
            $packDir . DIRECTORY_SEPARATOR . 'Tax' . DIRECTORY_SEPARATOR,
            // R-6a: Retirement clean services moved into the GB pack still
            // collaborate with the 8 deferred App\Services\Retirement\* peers
            // (R-14a) and with App\Services\Investment\* peers (R-6b),
            // App\Services\Settings\AssumptionsService (R-7), and
            // App\Services\UserProfile\UserProfileService (R-7). Pinned by
            // allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Retirement' . DIRECTORY_SEPARATOR,
            // R-6b: Investment services move in 4 sub-commits. Top-level
            // (R-6b-i) imports the 19 deferred App\Services\Investment\*
            // R-14a peers, plus App\Services\Investment\Rebalancing\*
            // (R-6b-iii target), App\Services\Investment\Utilities\* (R-6b-iv
            // target), App\Jobs\RunMonteCarloSimulation,
            // Fynla\Packs\Gb\Plans\PlanConfigService, and
            // App\Services\Shared\MonteCarloEngine. Pinned by allow-list.
            $packDir . DIRECTORY_SEPARATOR . 'Investment' . DIRECTORY_SEPARATOR,
            // R-6c: Protection clean services moved into the GB pack. The
            // 3 R-14a deferred peers (ComprehensiveProtectionPlanService,
            // CoverageGapAnalyzer, ProtectionActionDefinitionService) stay
            // in app/Services/Protection/ pending int-minor money refactor.
            // Pack code still imports them via cross-boundary use; pinned
            // by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Protection' . DIRECTORY_SEPARATOR,
            // R-6d: Savings clean services moved into the GB pack. ISATracker
            // is the sole R-14a deferral (?float $amount signature). Pack
            // RateComparator imports App\Services\Savings\ISATracker across
            // the boundary; pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Savings' . DIRECTORY_SEPARATOR,
            // R-7a: Goals clean services moved into the GB pack. The 3
            // R-14a deferrals (GoalAssignmentService, GoalProgressService,
            // LifeEventAllocationService) stay in app/Services/Goals/.
            // Pack GoalStrategyService imports two of them across the
            // boundary; pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Goals' . DIRECTORY_SEPARATOR,
            // R-7b: Plans clean services moved into the GB pack. The 4
            // R-14a deferrals (BasePlanService, DistributionAccount,
            // InvestmentPlanService, RetirementPlanService) stay in
            // app/Services/Plans/. Pack Plans services extend BasePlanService
            // and reference App\Agents\* (R-8 deferral) across the boundary;
            // pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Plans' . DIRECTORY_SEPARATOR,
            // R-7c: Coordination clean services moved into the GB pack. The
            // 3 R-14a deferrals (CashFlowCoordinator, CrossModuleStrategyService,
            // HouseholdPlanningService) stay in app/Services/Coordination/.
            // Pack RecommendationsAggregatorService imports
            // App\Services\Investment\PortfolioAnalyzer (R-14a) across the
            // boundary; pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Coordination' . DIRECTORY_SEPARATOR,
            // R-8: 7 module agents (Coordinating + 6 module agents) moved
            // into the GB pack. They extend App\Agents\BaseAgent (still in
            // app/Agents pending follow-up) and import deferred R-14a peers
            // (Coordination/Protection/AI services) across the boundary;
            // pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Agents' . DIRECTORY_SEPARATOR,
            // R-9a: 18 UK Resources moved into the GB pack. Pack resources
            // for UK joint-ownable models (Property, Mortgage, Investment,
            // Savings, Chattel, BusinessInterest) reference
            // App\Http\Resources\UserResource for the user / joint_owner
            // relationships; pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR,
            // R-9b: 6 UK module observers moved into the GB pack. Risk
            // observers extend App\Observers\RiskRecalculationObserver
            // (generic base, stays in app/Observers/); pinned by allow-list.
            $packDir . DIRECTORY_SEPARATOR . 'Observers' . DIRECTORY_SEPARATOR,
            // R-9d: UK module controllers begin moving into the GB pack
            // (Savings first). Controllers extend App\Http\Controllers\Controller
            // (Laravel base controller, stays in core) and use
            // App\Http\Traits\SanitizedErrorResponse (cross-cutting trait,
            // stays in core); pinned by allow-list below.
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR,
            // R-9e: 50 module-folder Requests relocated in R-9c had no App\
            // imports, so the requests directory wasn't exempted at the time.
            // The flat StoreProtectionActionDefinitionRequest moved in R-9e
            // imports App\Services\Auth\PermissionService for admin-permission
            // gating. Exempt the directory (and pin the import via allow-list
            // below) rather than refactor PermissionService into core for the
            // sake of one request.
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Requests' . DIRECTORY_SEPARATOR,
        ];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = $file->getPathname();
            $isExempt = false;
            foreach ($exemptDirs as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $isExempt = true;
                    break;
                }
            }
            if ($isExempt) continue;
            $contents = file_get_contents($path);

            if (preg_match('/(?:^|[\s(;])(use\s+)?\\\\?App\\\\/m', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $path);
            }
        }

        expect($violations)->toBeEmpty(
            'GB pack must not import any App\\ namespace (outside src/Providers/). Violations: ' . implode(', ', $violations)
        );
    });

    it('country-gb Constants/Traits/Models/Estate/Tax/Retirement/Investment/Protection/Savings/Goals/Plans/Coordination/Agents/Http/Observers only import allow-listed App\\ namespaces (R-6/R-7/R-8/R-9 ratchet)', function () {
        $packDir = base_path('packs/country-gb/src');
        $targetDirs = [
            $packDir . DIRECTORY_SEPARATOR . 'Constants',
            $packDir . DIRECTORY_SEPARATOR . 'Traits',
            $packDir . DIRECTORY_SEPARATOR . 'Models',
            $packDir . DIRECTORY_SEPARATOR . 'Estate',
            $packDir . DIRECTORY_SEPARATOR . 'Tax',
            $packDir . DIRECTORY_SEPARATOR . 'Retirement',
            $packDir . DIRECTORY_SEPARATOR . 'Investment',
            $packDir . DIRECTORY_SEPARATOR . 'Protection',
            $packDir . DIRECTORY_SEPARATOR . 'Savings',
            $packDir . DIRECTORY_SEPARATOR . 'Goals',
            $packDir . DIRECTORY_SEPARATOR . 'Plans',
            $packDir . DIRECTORY_SEPARATOR . 'Coordination',
            $packDir . DIRECTORY_SEPARATOR . 'Agents',
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Resources',
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers',
            $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Requests',
            $packDir . DIRECTORY_SEPARATOR . 'Observers',
        ];

        // The R-3/R-4 relocations tolerate a narrow allow-list of App\
        // imports inside the GB Constants/Traits/Models. Anything outside
        // this list is a leak and should fail the build. R-5 (TaxConfigService)
        // and the deferred User/Household/Goal/LifeEvent relocation shrink
        // the allow-list; R-15 reduces it to empty.
        $allowed = [
            // App\Models\* — R-14b CLOSED for all 6 deferred core models.
            // R-14b-iv closed GoalContribution + LifeEvent + LifeEventAllocation
            // (3 clean models). R-14b-v closed Goal (FK relations are
            // PackAssetResolver-backed accessors). R-14b-vi closed Household
            // (6 unused pack hasMany relations dropped, householdAssets
            // accessor routes through PackAssetRepository). R-14b-vii closed
            // User (pack-asset hasMany / hasOne relations resolve their
            // Eloquent target class via PackUserRelationProvider, keeping
            // the relation API intact while removing pack literals from
            // core). The R-14b-deferred section is now empty.
            // App\Agents\BaseAgent — abstract parent of all module agents.
            // Stays in app/Agents/ as a generic orchestrator base (no UK-only
            // logic except a TaxDefaults cache-TTL constant). The 7 relocated
            // GB agents extend it across the boundary.
            'App\\Agents\\BaseAgent',
            // App\Agents\TaxOptimisationAgent — implements the
            // TaxOptimisationEngine contract; bound by GbPackServiceProvider
            // as `pack.gb.tax_optimisation`. Stays in app/Agents/ awaiting
            // its own R-8 follow-up agent relocation; its TaxOptimisationService
            // dependency now lives in pack (R-14a-Tax-iii). Pack CoordinatingAgent
            // injects it across the boundary.
            'App\\Agents\\TaxOptimisationAgent',
            // R-9-final-i: Goal-shaped Requests/Resources wrap the deferred
            // App\Models\Goal (one of the 6 R-14b core models). They stay in
            // app/Http/Requests/Goals and app/Http/Resources/ until the Goal
            // relocation in R-14b sub-batch vi; pack GoalsController imports
            // them across the boundary.
            'App\\Http\\Requests\\Goals\\StoreGoalRequest',
            'App\\Http\\Requests\\Goals\\UpdateGoalRequest',
            'App\\Http\\Resources\\GoalContributionResource',
            'App\\Http\\Resources\\GoalResource',
            // R-9-final-ii: LifeEvent-shaped Requests wrap the deferred
            // App\Models\LifeEvent (one of the 6 R-14b core models). They
            // stay in app/Http/Requests/ until the LifeEvent relocation in
            // R-14b sub-batch vi; pack LifeEventController imports them
            // across the boundary.
            'App\\Http\\Requests\\StoreLifeEventRequest',
            'App\\Http\\Requests\\UpdateLifeEventRequest',
            // R-9-final-v: Property-shaped flat Requests. Property model
            // already lives in the pack (Fynla\Packs\Gb\Models\Property),
            // but the flat StorePropertyRequest / UpdatePropertyRequest
            // (and the App\Services\Property\* services they collaborate
            // with) reference App\Models\User. They stay in
            // app/Http/Requests/ + app/Services/Property/ until the Property
            // services relocation workstream (post-R-14b User relocation).
            'App\\Http\\Requests\\StorePropertyRequest',
            'App\\Http\\Requests\\UpdatePropertyRequest',
            'App\\Services\\Property\\MortgageService',
            'App\\Services\\Property\\PropertyService',
            'App\\Services\\Property\\PropertyTaxService',
            // R-9-final-vi: Mortgage-shaped flat Requests. Mortgage model
            // already lives in the pack (Fynla\Packs\Gb\Models\Mortgage);
            // the flat Store/Update requests sit in app/Http/Requests/ and
            // reference App\Models\User. Relocates with the Property
            // services workstream.
            'App\\Http\\Requests\\StoreMortgageRequest',
            'App\\Http\\Requests\\UpdateMortgageRequest',
            // R-9-final-vii: BusinessInterestService straddles the boundary
            // (BusinessInterest model already in pack, service references
            // App\Models\User). Relocates with a future Business services
            // workstream.
            'App\\Services\\Business\\BusinessInterestService',
            // R-9-final-viii: ChattelCGTService straddles the boundary
            // (Chattel model already in pack, service references the still-
            // in-core TaxConfigService consumption pattern via
            // App\Services\Tax helpers). Relocates with a future Chattel
            // services workstream.
            'App\\Services\\Chattel\\ChattelCGTService',
            // R-9d: pack controllers extend the Laravel base controller and
            // use the cross-cutting SanitizedErrorResponse trait. Both stay
            // in core as framework / shared infrastructure.
            'App\\Http\\Controllers\\Controller',
            'App\\Http\\Traits\\SanitizedErrorResponse',
            // R-9e: StoreProtectionActionDefinitionRequest gates admin
            // creation of protection action definitions via the cross-cutting
            // PermissionService (used by every admin-permission check across
            // packs). Stays in core as shared auth infrastructure.
            'App\\Services\\Auth\\PermissionService',
            // App\Observers\RiskRecalculationObserver — generic base class
            // (debounced job dispatch). Stays in app/Observers/ as a non-UK
            // helper; the 4 UK risk observers (DCPension, InvestmentAccount,
            // Property, SavingsAccount) extend it across the boundary.
            'App\\Observers\\RiskRecalculationObserver',
            // App\Services\* — relocated in R-5/R-6.
            'App\\Services\\AI\\AiToolDefinitions', // R-8: CoordinatingAgent imports
            'App\\Services\\AI\\KycGateChecker',
            'App\\Services\\AI\\QueryClassifier',
            'App\\Services\\AI\\SystemPromptBuilder',
            'App\\Services\\AI\\XaiClient',
            'App\\Services\\AI\\XaiToolDefinitions',
            'App\\Services\\PrerequisiteGateService',
            // App\Services\* — relocated in R-6/R-7.
            'App\\Services\\Cache\\CacheInvalidationService',
            // R-14a deferred Coordination services — float-money signatures
            // keep these in app/Services/Coordination/ until the int-minor
            // money refactor. Pack CoordinatingAgent imports both across
            // the boundary.
            'App\\Services\\Coordination\\CashFlowCoordinator', // R-14a
            'App\\Services\\Coordination\\CrossModuleStrategyService', // R-14a
            'App\\Services\\Coordination\\HouseholdPlanningService', // R-14a
            // R-14a deferred Goals services — float-money signatures (ADR-005)
            // keep these in app/Services/Goals/ until the int-minor money
            // refactor lands. Pack GoalStrategyService imports
            // GoalAssignmentService + GoalProgressService across the boundary.
            'App\\Services\\Goals\\GoalAssignmentService', // R-14a
            'App\\Services\\Goals\\GoalProgressService', // R-14a
            'App\\Services\\Goals\\LifeEventAllocationService', // R-14a
            // App\Services\NetWorth\NetWorthService — used by pack
            // GoalsProjectionService. Stays in app/Services/NetWorth/ until
            // a follow-up workstream relocates the NetWorth module.
            'App\\Services\\NetWorth\\NetWorthService',
            // R-14a deferred Investment services — float-money signatures
            // (ADR-005) keep these in app/Services/Investment/ until the
            // int-minor money refactor. Pack code that collaborates with
            // them imports across the boundary.
            'App\\Services\\Investment\\ContributionOptimizer', // R-14a
            'App\\Services\\Investment\\DividendTaxCalculator', // R-14a
            'App\\Services\\Investment\\FeeAnalyzer', // R-14a
            'App\\Services\\Investment\\InvestmentProjectionService', // R-14a
            'App\\Services\\Investment\\PortfolioAnalyzer', // R-14a
            'App\\Services\\Investment\\TaxEfficiencyCalculator', // R-14a
            'App\\Services\\Investment\\AssetLocation\\AssetLocationOptimizer', // R-14a
            'App\\Services\\Investment\\Fees\\OCFImpactCalculator', // R-14a
            'App\\Services\\Investment\\Fees\\PlatformComparator', // R-14a
            'App\\Services\\Investment\\Goals\\GoalProbabilityCalculator', // R-14a
            'App\\Services\\Investment\\Goals\\GoalProgressAnalyzer', // R-14a
            'App\\Services\\Investment\\Goals\\ShortfallAnalyzer', // R-14a
            'App\\Services\\Investment\\ModelPortfolio\\AssetAllocationOptimizer', // R-14a
            'App\\Services\\Investment\\Performance\\PerformanceAttributionAnalyzer', // R-14a
            'App\\Services\\Investment\\Recommendation\\LifeEventAssessmentService', // R-14a
            'App\\Services\\Investment\\Recommendation\\UserContextBuilder', // R-14a
            'App\\Services\\Investment\\Tax\\BedAndISACalculator', // R-14a
            'App\\Services\\Investment\\Tax\\ISAAllowanceOptimizer', // R-14a
            'App\\Services\\Investment\\Tax\\TaxOptimizationAnalyzer', // R-14a
            // R-6b complete: all 37 clean Investment services have relocated
            // (R-6b-i top-level, R-6b-ii Analytics + AssetLocation,
            // R-6b-iii ModelPortfolio + Performance + Rebalancing,
            // R-6b-iv Recommendation + Tax + Utilities). The 19 remaining
            // App\Services\Investment\* entries above are R-14a deferrals
            // pinned by the int-minor money refactor.
            // App\Jobs\* — Job dispatched by ScenarioService when running
            // Monte Carlo simulations. Stays in app/Jobs after R-6b.
            'App\\Jobs\\RunMonteCarloSimulation',
            // R-14a deferred Plans services — BasePlanService and concrete
            // plan-money services keep float-money signatures (ADR-005). Pack
            // EstatePlanService / GoalPlanService / ProtectionPlanService /
            // SavingsPlanService extend BasePlanService; WhatIfCalculator
            // imports InvestmentPlanService + RetirementPlanService;
            // GoalPlanService instantiates DistributionAccount.
            'App\\Services\\Plans\\BasePlanService', // R-14a
            'App\\Services\\Plans\\DistributionAccount', // R-14a
            'App\\Services\\Plans\\InvestmentPlanService', // R-14a
            'App\\Services\\Plans\\RetirementPlanService', // R-14a
            // R-14a deferred Protection services — pack ProtectionPlanService
            // imports ComprehensiveProtectionPlanService and
            // ProtectionActionDefinitionService across the boundary; pack
            // ProtectionAgent (R-8) imports CoverageGapAnalyzer.
            'App\\Services\\Protection\\ComprehensiveProtectionPlanService', // R-14a
            'App\\Services\\Protection\\CoverageGapAnalyzer', // R-14a
            'App\\Services\\Protection\\ProtectionActionDefinitionService', // R-14a
            'App\\Services\\Property\\PropertyCalculationService',
            // R-14a deferred Retirement services — float-money signatures
            // (ADR-005) keep these in app/Services/Retirement/ until the
            // int-minor money refactor lands. RetirementActionDefinitionService
            // imports DecumulationPlanner / PensionContributionOptimizer /
            // SalarySacrificeAnalyzer; AnnualAllowanceChecker collaborates
            // across pack boundaries as before.
            'App\\Services\\Retirement\\AnnualAllowanceChecker', // R-14a
            'App\\Services\\Retirement\\DecumulationPlanner', // R-14a
            'App\\Services\\Retirement\\PensionContributionOptimizer', // R-14a
            'App\\Services\\Retirement\\PensionProjector', // R-14a
            'App\\Services\\Retirement\\RetirementIncomeService', // R-14a
            'App\\Services\\Retirement\\RetirementProjectionService', // R-14a
            'App\\Services\\Retirement\\RetirementStrategyService', // R-14a
            'App\\Services\\Retirement\\SalarySacrificeAnalyzer', // R-14a
            // R-14a deferred Savings service — ISATracker has ?float $amount
            // signature on updateISAUsage. Pack RateComparator imports it
            // across the boundary; relocates with the int-minor money refactor.
            'App\\Services\\Savings\\ISATracker', // R-14a
            // R-9j deferrals — WhatIfScenarioService and LetterToSpouseService
            // already collaborate with the GB pack (importing pack agents and
            // models respectively) but live in app/Services/ awaiting a
            // dedicated WhatIf / UserProfile relocation workstream. Pack
            // WhatIfScenarioController + LetterToSpouseController import them
            // across the boundary; relocates with the int-minor money refactor.
            'App\\Services\\WhatIf\\WhatIfScenarioService', // R-14a
            'App\\Services\\UserProfile\\LetterToSpouseService', // R-14a
            'App\\Services\\Risk\\RiskPreferenceService',
            'App\\Services\\Settings\\AssumptionsService',
            'App\\Services\\Shared\\CrossModuleAssetAggregator',
            // App\Services\Shared\MonteCarloEngine — used by MonteCarloSimulator
            // (relocated in R-6b-i). Shared module relocates in R-7.
            'App\\Services\\Shared\\MonteCarloEngine',
            'App\\Services\\UserProfile\\ProfileCompletenessChecker',
            // R-7 target — UserProfileService relocates with the
            // UserProfile module. RequiredCapitalCalculator imports it
            // for income-source resolution.
            'App\\Services\\UserProfile\\UserProfileService',
            // R-14a-Traits CLOSED: FormatsCurrency + CalculatesOCF both
            // relocated to Fynla\Packs\Gb\Traits in R-14a-Traits-i and -ii.
            // The v3-plan-original 14-target list is now empty.
            // App\Services\Estate\* — these stay in app/Services/Estate
            // pending the int-minor money refactor (ADR-005). Pack code that
            // collaborates with them imports across the boundary until
            // they relocate.
            // R-9h surfaced two App\Services\Trust\* peers used by pack
            // TrustController + WillController. IHTPeriodicChargeCalculator
            // has float-money signatures (calculateExitCharge / calculateEntryCharge)
            // — R-14a deferral. TrustAssetAggregatorService is clean but the
            // Trust sub-module hasn't had a relocation workstream yet; relocates
            // alongside the Estate float-money services in R-14a.
            'App\\Services\\Trust\\IHTPeriodicChargeCalculator', // R-14a
            'App\\Services\\Trust\\TrustAssetAggregatorService', // R-14a (sub-module, relocates with Estate)
            // Same deferral for Tax-side float-money services.
        ];

        $violations = [];
        foreach ($targetDirs as $dir) {
            if (! is_dir($dir)) continue;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;
                $contents = file_get_contents($file->getPathname());

                preg_match_all('/^use\s+(\\\\?App\\\\[A-Za-z0-9_\\\\]+);/m', $contents, $matches);
                foreach ($matches[1] as $import) {
                    $normalised = ltrim($import, '\\');
                    if (! in_array($normalised, $allowed, true)) {
                        $violations[] = str_replace(base_path() . '/', '', $file->getPathname()) . ' uses ' . $normalised;
                    }
                }
            }
        }

        expect($violations)->toBeEmpty(
            'GB pack Constants/Traits may only import allow-listed App\\ classes. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-xx-smoke does not reference other pack namespaces', function () {
        $packDir = base_path('packs/country-xx-smoke/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-xx-smoke/src directory not found');
        }

        $violations = [];
        $otherPackPattern = '/Fynla\\\\Packs\\\\(?!XXSmoke\\\\)/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            if (preg_match($otherPackPattern, $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'Smoke pack must not reference other pack namespaces. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-za does not import the App namespace (outside Http adapters)', function () {
        $packDir = base_path('packs/country-za/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-za/src directory not found');
        }

        // R-0a: SA HTTP adapters (controllers under src/Http/) are exempt from
        // the strict App\ ban while the cross-pack read layer is still
        // mediated by direct App\Models\* imports. R-15 ratchets this — the
        // core query layer (or relocated cross-pack models) closes the gap
        // and this exemption is removed.
        $exemptPrefix = $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR;

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            if (str_starts_with($file->getPathname(), $exemptPrefix)) continue;
            $contents = file_get_contents($file->getPathname());

            // Look for `use App\`, `App\\...::class`, or a leading backslash App\ reference.
            if (preg_match('/(?:^|[\s(;])(use\s+)?\\\\?App\\\\/m', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'ZA pack must not import any App\\ namespace (outside src/Http/). Violations: ' . implode(', ', $violations)
        );
    });

    it('country-za HTTP adapters only import allow-listed App\\ namespaces (R-15 ratchet)', function () {
        $httpDir = base_path('packs/country-za/src/Http');

        if (!is_dir($httpDir)) {
            $this->markTestSkipped('packs/country-za/src/Http directory not found');
        }

        // The R-0a relocation tolerates a narrow allow-list of cross-pack
        // imports inside the SA Http adapters. Anything outside this list
        // is a leak and should fail the build. R-15 reduces this list to
        // empty once a core-mediated asset query layer is in place.
        $allowed = [
            // App\ — only the base controller stays in the legacy namespace.
            'App\\Http\\Controllers\\Controller',
            // Cross-pack — UK models the SA Http layer reads for joint
            // assets and aggregate calculations.
            'Fynla\\Packs\\Gb\\Models\\DCPension',
            'Fynla\\Packs\\Gb\\Models\\Investment\\Holding',
            'Fynla\\Packs\\Gb\\Models\\Investment\\InvestmentAccount',
            'Fynla\\Packs\\Gb\\Models\\Mortgage',
            'Fynla\\Packs\\Gb\\Models\\SavingsAccount',
            // Core models — relocated in R-4a.
            'Fynla\\Core\\Models\\FamilyMember',
        ];

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($httpDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            // Allow-list applies to App\ and Fynla\Packs\Gb\ — these are
            // the legacy and relocated cross-pack imports. Fynla\Core\
            // imports (contracts, Money VO) are core infrastructure that
            // every pack legitimately uses, so they're not allow-listed.
            preg_match_all('/^use\s+(\\\\?(?:App|Fynla\\\\Packs\\\\Gb)\\\\[A-Za-z0-9_\\\\]+);/m', $contents, $matches);
            foreach ($matches[1] as $import) {
                $normalised = ltrim($import, '\\');
                if (! in_array($normalised, $allowed, true)) {
                    $violations[] = str_replace(base_path() . '/', '', $file->getPathname()) . ' uses ' . $normalised;
                }
            }
        }

        expect($violations)->toBeEmpty(
            'ZA pack Http adapters may only import allow-listed cross-namespace classes. Violations: ' . implode(', ', $violations)
        );
    });

    it('country-za does not reference other pack namespaces (outside Http adapters)', function () {
        $packDir = base_path('packs/country-za/src');

        if (!is_dir($packDir)) {
            $this->markTestSkipped('packs/country-za/src directory not found');
        }

        // R-4: SA Http adapters previously imported App\Models\Property
        // etc. for cross-pack reads (R-0a allow-list). After R-4 those
        // models live under Fynla\Packs\Gb\Models\, so the imports are
        // now cross-pack rather than App\. Same exemption shape as the
        // App\ ban — ratchets to empty in R-15 once the core-mediated
        // query layer abstracts asset lookup.
        $exemptPrefix = $packDir . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR;

        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($packDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            if (str_starts_with($file->getPathname(), $exemptPrefix)) continue;
            $contents = file_get_contents($file->getPathname());

            // Any Fynla\Packs\ reference that isn't the Za namespace is a leak.
            if (preg_match('/Fynla\\\\Packs\\\\(?!Za\\\\)/', $contents)) {
                $violations[] = str_replace(base_path() . '/', '', $file->getPathname());
            }
        }

        expect($violations)->toBeEmpty(
            'ZA pack must not reference other pack namespaces (outside src/Http/). Violations: ' . implode(', ', $violations)
        );
    });

    it('core/ does not contain SA-specific logic (outside docblocks)', function () {
        $coreDir = base_path('core/app/Core');

        if (!is_dir($coreDir)) {
            $this->markTestSkipped('core/app/Core directory not found');
        }

        // PRD § 8 intent: no SA-specific HARDCODED logic in core. Documentation
        // mentions of SARS / Section 11F as example context are acceptable —
        // they help future pack authors understand the contract. Actual
        // forbidden patterns: hardcoded SA amounts (R99,000, R40,000 exclusion,
        // etc.) and direct ZA rate constants appearing in executable statements.
        $forbiddenInCode = ['R99,000', 'R40,000', 'R3.5m', 'R350,000'];
        $violations = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($coreDir)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;

            // Strip PHPDoc blocks and // line comments before checking.
            $contents = file_get_contents($file->getPathname());
            $codeOnly = preg_replace('#/\*.*?\*/#s', '', $contents) ?? $contents;
            $codeOnly = preg_replace('#//[^\n]*#', '', $codeOnly) ?? $codeOnly;

            foreach ($forbiddenInCode as $literal) {
                if (str_contains($codeOnly, $literal)) {
                    $violations[] = str_replace(base_path() . '/', '', $file->getPathname()) . ' contains code literal "' . $literal . '"';
                }
            }
        }

        expect($violations)->toBeEmpty(
            'core/ must not hardcode SA-specific values. Violations: ' . implode('; ', $violations)
        );
    });

    it('ZaTaxEngine implements the core TaxEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Tax\ZaTaxEngine::class)) {
            $this->markTestSkipped('ZaTaxEngine not loaded');
        }

        expect(class_implements(\Fynla\Packs\Za\Tax\ZaTaxEngine::class))
            ->toContain(\Fynla\Core\Contracts\TaxEngine::class);
    });

    it('UkSavingsEngine implements the core SavingsEngine contract', function () {
        expect(class_implements(\Fynla\Packs\Gb\Savings\UkSavingsEngine::class))
            ->toContain(\Fynla\Core\Contracts\SavingsEngine::class);
    });

    it('ZaSavingsEngine implements the core SavingsEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class)) {
            $this->markTestSkipped('ZaSavingsEngine not yet loaded (WS 1.2a in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Savings\ZaSavingsEngine::class))
            ->toContain(\Fynla\Core\Contracts\SavingsEngine::class);
    });

    it('UkInvestmentEngine implements the core InvestmentEngine contract', function () {
        expect(class_implements(\Fynla\Packs\Gb\Investment\UkInvestmentEngine::class))
            ->toContain(\Fynla\Core\Contracts\InvestmentEngine::class);
    });

    it('ZaInvestmentEngine implements the core InvestmentEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class)) {
            $this->markTestSkipped('ZaInvestmentEngine not yet loaded (WS 1.3a in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Investment\ZaInvestmentEngine::class))
            ->toContain(\Fynla\Core\Contracts\InvestmentEngine::class);
    });

    it('UkExchangeControl implements the core ExchangeControl contract', function () {
        expect(class_implements(\App\Services\ExchangeControl\UkExchangeControl::class))
            ->toContain(\Fynla\Core\Contracts\ExchangeControl::class);
    });

    it('TaxOptimisationAgent implements the core TaxOptimisationEngine contract', function () {
        expect(class_implements(\App\Agents\TaxOptimisationAgent::class))
            ->toContain(\Fynla\Core\Contracts\TaxOptimisationEngine::class);
    });

    it('pack.gb.tax_optimisation resolves to the UK tax-optimisation agent', function () {
        $resolved = app('pack.gb.tax_optimisation');
        expect($resolved)->toBeInstanceOf(\App\Agents\TaxOptimisationAgent::class);
        expect($resolved)->toBeInstanceOf(\Fynla\Core\Contracts\TaxOptimisationEngine::class);
    });

    it('ZaExchangeControl implements the core ExchangeControl contract', function () {
        if (! class_exists(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class)) {
            $this->markTestSkipped('ZaExchangeControl not yet loaded (WS 1.3b in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class))
            ->toContain(\Fynla\Core\Contracts\ExchangeControl::class);
    });

    it('UkRetirementEngine implements the core RetirementEngine contract', function () {
        expect(class_implements(\Fynla\Packs\Gb\Retirement\UkRetirementEngine::class))
            ->toContain(\Fynla\Core\Contracts\RetirementEngine::class);
    });

    it('ZaRetirementEngine implements the core RetirementEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class)) {
            $this->markTestSkipped('ZaRetirementEngine not yet loaded (WS 1.4a in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Retirement\ZaRetirementEngine::class))
            ->toContain(\Fynla\Core\Contracts\RetirementEngine::class);
    });

    it('UkProtectionEngine implements the core ProtectionEngine contract', function () {
        expect(class_implements(\Fynla\Packs\Gb\Protection\UkProtectionEngine::class))
            ->toContain(\Fynla\Core\Contracts\ProtectionEngine::class);
    });

    it('ZaProtectionEngine implements the core ProtectionEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Protection\ZaProtectionEngine::class)) {
            $this->markTestSkipped('ZaProtectionEngine not yet loaded (WS 1.5 in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Protection\ZaProtectionEngine::class))
            ->toContain(\Fynla\Core\Contracts\ProtectionEngine::class);
    });

    it('UkEstateEngine implements the core EstateEngine contract', function () {
        expect(class_implements(\Fynla\Packs\Gb\Estate\UkEstateEngine::class))
            ->toContain(\Fynla\Core\Contracts\EstateEngine::class);
    });

    it('ZaEstateEngine implements the core EstateEngine contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Estate\ZaEstateEngine::class)) {
            $this->markTestSkipped('ZaEstateEngine not yet loaded (WS 1.6 in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Estate\ZaEstateEngine::class))
            ->toContain(\Fynla\Core\Contracts\EstateEngine::class);
    });

    it('ZaLocalisation implements the core Localisation contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Localisation\ZaLocalisation::class)) {
            $this->markTestSkipped('ZaLocalisation not yet loaded (WS 1.8 in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Localisation\ZaLocalisation::class))
            ->toContain(\Fynla\Core\Contracts\Localisation::class);
    });

    it('ZaIdValidator implements the core IdentityValidator contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Identity\ZaIdValidator::class)) {
            $this->markTestSkipped('ZaIdValidator not yet loaded (WS 1.8 in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Identity\ZaIdValidator::class))
            ->toContain(\Fynla\Core\Contracts\IdentityValidator::class);
    });

    it('ZaBankingValidator implements the core BankingValidator contract', function () {
        if (! class_exists(\Fynla\Packs\Za\Banking\ZaBankingValidator::class)) {
            $this->markTestSkipped('ZaBankingValidator not yet loaded (WS 1.8 in progress)');
        }

        expect(class_implements(\Fynla\Packs\Za\Banking\ZaBankingValidator::class))
            ->toContain(\Fynla\Core\Contracts\BankingValidator::class);
    });
});
