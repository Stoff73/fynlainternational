<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| GB Pack API Routes
|--------------------------------------------------------------------------
|
| UK module routes mounted by GbPackServiceProvider::boot() under the same
| /api prefix and api middleware group as routes/api.php. Per the R-9
| URL-strategy decision, GB routes are mounted WITHOUT a /api/gb/ prefix
| so URL paths stay identical and feature tests keep passing. The Option X
| prefix + redirect layer ships in R-14.
|
*/

use App\Http\Controllers\Api\RiskPreferenceController;
use Fynla\Packs\Gb\Http\Controllers\Estate\GiftingController;
use Fynla\Packs\Gb\Http\Controllers\Estate\IHTController;
use Fynla\Packs\Gb\Http\Controllers\Estate\LetterValidationController;
use Fynla\Packs\Gb\Http\Controllers\Estate\LifePolicyController;
use Fynla\Packs\Gb\Http\Controllers\Estate\LpaController;
use Fynla\Packs\Gb\Http\Controllers\Estate\TrustController;
use Fynla\Packs\Gb\Http\Controllers\Estate\WillController;
use Fynla\Packs\Gb\Http\Controllers\Estate\WillDocumentController;
use Fynla\Packs\Gb\Http\Controllers\EstateController;
use Fynla\Packs\Gb\Http\Controllers\GoalsController;
use Fynla\Packs\Gb\Http\Controllers\HolisticPlanningController;
use Fynla\Packs\Gb\Http\Controllers\HouseholdController;
use Fynla\Packs\Gb\Http\Controllers\IncomeDefinitionsController;
use Fynla\Packs\Gb\Http\Controllers\InvestmentActionDefinitionController;
use Fynla\Packs\Gb\Http\Controllers\InvestmentController;
use Fynla\Packs\Gb\Http\Controllers\Investment\AssetLocationController;
use Fynla\Packs\Gb\Http\Controllers\Investment\ContributionOptimizerController;
use Fynla\Packs\Gb\Http\Controllers\Investment\EfficientFrontierController;
use Fynla\Packs\Gb\Http\Controllers\Investment\FeeImpactController;
use Fynla\Packs\Gb\Http\Controllers\Investment\GoalProgressController;
use Fynla\Packs\Gb\Http\Controllers\Investment\InvestmentScenarioController;
use Fynla\Packs\Gb\Http\Controllers\Investment\ModelPortfolioController;
use Fynla\Packs\Gb\Http\Controllers\Investment\PerformanceAttributionController;
use Fynla\Packs\Gb\Http\Controllers\Investment\PortfolioStrategyController;
use Fynla\Packs\Gb\Http\Controllers\Investment\RebalancingActionsController;
use Fynla\Packs\Gb\Http\Controllers\Investment\RebalancingCalculationController;
use Fynla\Packs\Gb\Http\Controllers\Investment\RebalancingStrategiesController;
use Fynla\Packs\Gb\Http\Controllers\Investment\TaxOptimizationController;
use Fynla\Packs\Gb\Http\Controllers\InvestmentProjectionController;
use Fynla\Packs\Gb\Http\Controllers\LetterToSpouseController;
use Fynla\Packs\Gb\Http\Controllers\LifeEventController;
use Fynla\Packs\Gb\Http\Controllers\MortgageController;
use Fynla\Packs\Gb\Http\Controllers\Plans\PlanController;
use Fynla\Packs\Gb\Http\Controllers\PortfolioOptimizationController;
use Fynla\Packs\Gb\Http\Controllers\PropertyController;
use Fynla\Packs\Gb\Http\Controllers\ProtectionActionDefinitionController;
use Fynla\Packs\Gb\Http\Controllers\ProtectionController;
use Fynla\Packs\Gb\Http\Controllers\RecommendationsController;
use Fynla\Packs\Gb\Http\Controllers\Retirement\DCPensionHoldingsController;
use Fynla\Packs\Gb\Http\Controllers\Retirement\DecumulationController;
use Fynla\Packs\Gb\Http\Controllers\RetirementActionDefinitionController;
use Fynla\Packs\Gb\Http\Controllers\RetirementController;
use Fynla\Packs\Gb\Http\Controllers\SavingsController;
use Fynla\Packs\Gb\Http\Controllers\Tax\TaxOptimisationController;
use Fynla\Packs\Gb\Http\Controllers\TaxProductInfoController;
use Fynla\Packs\Gb\Http\Controllers\TaxSettingsController;
use Fynla\Packs\Gb\Http\Controllers\TaxYearController;
use Fynla\Packs\Gb\Http\Controllers\WhatIfScenarioController;

// Savings module routes
Route::middleware('auth:sanctum')->prefix('savings')->group(function () {
    // Main savings data and analysis
    Route::get('/', [SavingsController::class, 'index']);
    Route::post('/analyze', [SavingsController::class, 'analyze']);
    Route::get('/recommendations', [SavingsController::class, 'recommendations']);
    Route::post('/scenarios', [SavingsController::class, 'scenarios']);

    // ISA allowance tracking
    Route::get('/isa-allowance/{taxYear}', [SavingsController::class, 'isaAllowance'])->where('taxYear', '.*');

    // Savings accounts
    Route::prefix('accounts')->group(function () {
        Route::post('/', [SavingsController::class, 'storeAccount']);
        Route::get('/{id}', [SavingsController::class, 'showAccount']);
        Route::put('/{id}', [SavingsController::class, 'updateAccount']);
        Route::delete('/{id}', [SavingsController::class, 'destroyAccount']);
        Route::patch('/{id}/toggle-retirement', [SavingsController::class, 'toggleRetirementInclusion']);
    });

    // Legacy savings goals - DEPRECATED since v0.7.0
    // Goals are now managed via unified Goals module: /api/goals?module=savings
    // See GoalsController for the unified API. Legacy routes removed in v0.8.1.
});

// Protection module routes
Route::middleware('auth:sanctum')->prefix('protection')->group(function () {
    // Main protection data and analysis
    Route::get('/', [ProtectionController::class, 'index']);
    Route::post('/analyze', [ProtectionController::class, 'analyze']);
    Route::get('/recommendations', [ProtectionController::class, 'recommendations']);
    Route::post('/scenarios', [ProtectionController::class, 'scenarios']);

    // Protection profile
    Route::post('/profile', [ProtectionController::class, 'storeProfile']);
    Route::patch('/profile/has-no-policies', [ProtectionController::class, 'updateHasNoPolicies']);

    // Life insurance policies
    Route::prefix('policies/life')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeLifePolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateLifePolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyLifePolicy']);
    });

    // Critical illness policies
    Route::prefix('policies/critical-illness')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeCriticalIllnessPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateCriticalIllnessPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyCriticalIllnessPolicy']);
    });

    // Income protection policies
    Route::prefix('policies/income-protection')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeIncomeProtectionPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateIncomeProtectionPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyIncomeProtectionPolicy']);
    });

    // Disability policies
    Route::prefix('policies/disability')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeDisabilityPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateDisabilityPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroyDisabilityPolicy']);
    });

    // Sickness/Illness policies
    Route::prefix('policies/sickness-illness')->group(function () {
        Route::post('/', [ProtectionController::class, 'storeSicknessIllnessPolicy']);
        Route::put('/{id}', [ProtectionController::class, 'updateSicknessIllnessPolicy']);
        Route::delete('/{id}', [ProtectionController::class, 'destroySicknessIllnessPolicy']);
    });
});

// Protection Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/protection-actions')->group(function () {
    Route::get('/', [ProtectionActionDefinitionController::class, 'index']);
    Route::get('/{id}', [ProtectionActionDefinitionController::class, 'show']);
    Route::post('/', [ProtectionActionDefinitionController::class, 'store']);
    Route::put('/{id}', [ProtectionActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [ProtectionActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [ProtectionActionDefinitionController::class, 'toggleEnabled']);
});

// Investment module routes
Route::middleware('auth:sanctum')->prefix('investment')->group(function () {
    // Main investment data and analysis
    Route::get('/', [InvestmentController::class, 'index']);
    Route::post('/analyze', [InvestmentController::class, 'analyze']);
    Route::get('/recommendations', [InvestmentController::class, 'recommendations']);
    Route::post('/scenarios', [InvestmentController::class, 'scenarios']);

    // Portfolio Strategy (aggregated recommendations)
    Route::get('/portfolio-strategy', [PortfolioStrategyController::class, 'index']);
    Route::get('/portfolio-strategy/account/{accountId}', [PortfolioStrategyController::class, 'forAccount']);

    // Monte Carlo simulation
    Route::post('/monte-carlo', [InvestmentController::class, 'startMonteCarlo'])->middleware('throttle:10,1');
    Route::get('/monte-carlo/{jobId}', [InvestmentController::class, 'getMonteCarloResults']);

    // Portfolio projections (Performance tab)
    Route::post('/projections', [InvestmentProjectionController::class, 'getProjections']);

    // Investment accounts
    Route::prefix('accounts')->group(function () {
        Route::post('/', [InvestmentController::class, 'storeAccount']);
        Route::put('/{id}', [InvestmentController::class, 'updateAccount']);
        Route::delete('/{id}', [InvestmentController::class, 'destroyAccount']);
        Route::get('/{id}/projections', [InvestmentController::class, 'getAccountProjections']);
        Route::get('/{id}/rebalancing', [RebalancingCalculationController::class, 'getAccountRebalancing']);
        Route::patch('/{id}/rebalancing-threshold', [RebalancingCalculationController::class, 'updateRebalancingThreshold']);
        Route::get('/{id}/diversification', [InvestmentController::class, 'getAccountDiversification']);
        Route::patch('/{id}/toggle-retirement', [InvestmentController::class, 'toggleRetirementInclusion']);
    });

    // Holdings
    Route::prefix('holdings')->group(function () {
        Route::post('/', [InvestmentController::class, 'storeHolding']);
        Route::put('/{id}', [InvestmentController::class, 'updateHolding']);
        Route::delete('/{id}', [InvestmentController::class, 'destroyHolding']);
    });

    // Legacy investment goals - DEPRECATED since v0.7.0
    // Goals are now managed via unified Goals module: /api/goals?module=investment
    // See GoalsController for the unified API. Legacy routes removed in v0.8.1.

    // Risk profile
    Route::post('/risk-profile', [InvestmentController::class, 'storeOrUpdateRiskProfile']);

    // Portfolio Optimization & Modern Portfolio Theory
    Route::prefix('optimization')->middleware('throttle:10,1')->group(function () {
        // Efficient frontier calculation
        Route::post('/efficient-frontier', [PortfolioOptimizationController::class, 'calculateEfficientFrontier']);
        Route::get('/current-position', [PortfolioOptimizationController::class, 'getCurrentPosition']);

        // Correlation analysis
        Route::get('/correlation-matrix', [PortfolioOptimizationController::class, 'getCorrelationMatrix']);

        // Optimization strategies
        Route::post('/minimize-variance', [PortfolioOptimizationController::class, 'optimizeMinimumVariance']);
        Route::post('/maximize-sharpe', [PortfolioOptimizationController::class, 'optimizeMaximumSharpe']);
        Route::post('/target-return', [PortfolioOptimizationController::class, 'optimizeTargetReturn']);
        Route::post('/risk-parity', [PortfolioOptimizationController::class, 'optimizeRiskParity']);

        // Cache management
        Route::delete('/clear-cache', [PortfolioOptimizationController::class, 'clearCache']);
    });

    // Portfolio Rebalancing with CGT Optimization
    Route::prefix('rebalancing')->group(function () {
        // Calculate rebalancing actions
        Route::post('/calculate', [RebalancingCalculationController::class, 'calculateRebalancing']);
        Route::post('/from-optimization', [RebalancingCalculationController::class, 'calculateFromOptimization']);

        // CGT-aware rebalancing
        Route::post('/compare-cgt', [RebalancingCalculationController::class, 'compareCGTStrategies']);
        Route::post('/within-cgt-allowance', [RebalancingCalculationController::class, 'rebalanceWithinCGTAllowance']);

        // Drift analysis (Phase 3.4)
        Route::post('/analyze-drift', [RebalancingCalculationController::class, 'analyzeDrift']);

        // Rebalancing strategies (Phase 3.4)
        Route::post('/evaluate-strategies', [RebalancingStrategiesController::class, 'evaluateStrategies']);
        Route::post('/threshold-strategy', [RebalancingStrategiesController::class, 'evaluateThresholdStrategy']);
        Route::post('/calendar-strategy', [RebalancingStrategiesController::class, 'evaluateCalendarStrategy']);
        Route::post('/opportunistic-strategy', [RebalancingStrategiesController::class, 'evaluateOpportunisticStrategy']);
        Route::post('/recommend-frequency', [RebalancingStrategiesController::class, 'recommendFrequency']);

        // Manage rebalancing actions
        Route::get('/actions', [RebalancingActionsController::class, 'getRebalancingActions']);
        Route::post('/save', [RebalancingActionsController::class, 'saveRebalancingActions']);
        Route::put('/actions/{id}', [RebalancingActionsController::class, 'updateRebalancingAction']);
        Route::delete('/actions/{id}', [RebalancingActionsController::class, 'deleteRebalancingAction']);
    });

    // Contribution Planning & Optimization (Phase 2.1)
    Route::prefix('contribution')->group(function () {
        // Optimize contribution strategy
        Route::post('/optimize', [ContributionOptimizerController::class, 'optimize']);

        // Affordability analysis
        Route::post('/affordability', [ContributionOptimizerController::class, 'affordability']);

        // Lump sum vs DCA comparison
        Route::post('/lump-sum-vs-dca', [ContributionOptimizerController::class, 'lumpSumVsDCA']);
    });

    // Tax Optimization Strategies
    Route::prefix('tax-optimization')->group(function () {
        // Comprehensive tax analysis
        Route::get('/analyze', [TaxOptimizationController::class, 'analyzeTaxPosition']);

        // ISA optimization
        Route::get('/isa-strategy', [TaxOptimizationController::class, 'getISAStrategy']);

        // CGT loss harvesting
        Route::get('/cgt-harvesting', [TaxOptimizationController::class, 'getCGTHarvestingOpportunities']);

        // Bed and ISA transfers
        Route::get('/bed-and-isa', [TaxOptimizationController::class, 'getBedAndISAOpportunities']);

        // Tax efficiency scoring
        Route::get('/efficiency-score', [TaxOptimizationController::class, 'getTaxEfficiencyScore']);

        // Recommendations
        Route::get('/recommendations', [TaxOptimizationController::class, 'getRecommendations']);

        // Savings calculator
        Route::post('/calculate-savings', [TaxOptimizationController::class, 'calculatePotentialSavings']);

        // Cache management
        Route::delete('/clear-cache', [TaxOptimizationController::class, 'clearCache']);
    });

    // Asset Location Optimization
    Route::prefix('asset-location')->group(function () {
        // Comprehensive analysis
        Route::get('/analyze', [AssetLocationController::class, 'analyzeAssetLocation']);

        // Placement recommendations
        Route::get('/recommendations', [AssetLocationController::class, 'getRecommendations']);

        // Tax drag calculation
        Route::get('/tax-drag', [AssetLocationController::class, 'calculateTaxDrag']);

        // Optimization score
        Route::get('/optimization-score', [AssetLocationController::class, 'getOptimizationScore']);

        // Compare account types
        Route::post('/compare-accounts', [AssetLocationController::class, 'compareAccountTypes']);

        // Cache management
        Route::delete('/clear-cache', [AssetLocationController::class, 'clearCache']);
    });

    // Performance Attribution & Benchmarking
    Route::prefix('performance')->group(function () {
        // Performance attribution analysis
        Route::get('/analyze', [PerformanceAttributionController::class, 'analyzePerformance']);

        // Benchmark comparison
        Route::get('/benchmark', [PerformanceAttributionController::class, 'compareWithBenchmark']);

        // Multi-benchmark comparison
        Route::get('/multi-benchmark', [PerformanceAttributionController::class, 'compareWithMultipleBenchmarks']);

        // Risk metrics
        Route::get('/risk-metrics', [PerformanceAttributionController::class, 'getRiskMetrics']);

        // Cache management
        Route::delete('/clear-cache', [PerformanceAttributionController::class, 'clearCache']);
    });

    // Goal Progress & Tracking
    Route::prefix('goals')->group(function () {
        // Progress analysis
        Route::get('/{goalId}/progress', [GoalProgressController::class, 'analyzeGoalProgress']);
        Route::get('/progress/all', [GoalProgressController::class, 'analyzeAllGoals']);

        // Shortfall analysis
        Route::get('/{goalId}/shortfall', [GoalProgressController::class, 'analyzeShortfall']);

        // What-if scenarios
        Route::post('/{goalId}/what-if', [GoalProgressController::class, 'generateWhatIfScenarios']);

        // Probability calculations
        Route::post('/calculate-probability', [GoalProgressController::class, 'calculateProbability']);
        Route::post('/required-contribution', [GoalProgressController::class, 'calculateRequiredContribution']);

        // Glide path recommendations
        Route::get('/glide-path', [GoalProgressController::class, 'getGlidePath']);

        // Cache management
        Route::delete('/clear-cache', [GoalProgressController::class, 'clearCache']);
    });

    // Fee Impact Analysis
    Route::prefix('fees')->group(function () {
        // Portfolio fee analysis
        Route::get('/analyze', [FeeImpactController::class, 'analyzePortfolioFees']);
        Route::get('/holdings', [FeeImpactController::class, 'analyzeHoldingFees']);

        // OCF impact
        Route::post('/ocf-impact', [FeeImpactController::class, 'calculateOCFImpact']);
        Route::get('/active-vs-passive', [FeeImpactController::class, 'compareActiveVsPassive']);
        Route::get('/alternatives/{holdingId}', [FeeImpactController::class, 'findAlternatives']);

        // Platform comparison
        Route::get('/compare-platforms', [FeeImpactController::class, 'comparePlatforms']);
        Route::post('/compare-specific', [FeeImpactController::class, 'compareSpecificPlatforms']);

        // Cache management
        Route::delete('/clear-cache', [FeeImpactController::class, 'clearCache']);
    });

    // Risk Preference (Self-select 5-level system)
    Route::prefix('risk')->group(function () {
        // Get all available risk levels with descriptions
        Route::get('/levels', [RiskPreferenceController::class, 'getLevels']);

        // User's main risk profile
        Route::get('/profile', [RiskPreferenceController::class, 'getProfile']);
        Route::post('/profile', [RiskPreferenceController::class, 'setProfile']);

        // Recalculate risk profile from financial factors
        Route::post('/recalculate', [RiskPreferenceController::class, 'recalculate']);

        // Allowed levels for product override (main level +/- 1)
        Route::get('/allowed-levels', [RiskPreferenceController::class, 'getAllowedLevels']);

        // Validate a product risk level
        Route::post('/validate-product-level', [RiskPreferenceController::class, 'validateProductLevel']);

        // Get configuration for a specific risk level
        Route::get('/config/{level}', [RiskPreferenceController::class, 'getRiskConfig']);
    });

    // Model Portfolio Builder
    Route::prefix('model-portfolio')->group(function () {
        // Model portfolios
        Route::get('/{riskLevel}', [ModelPortfolioController::class, 'getModelPortfolio']);
        Route::get('/all', [ModelPortfolioController::class, 'getAllPortfolios']);
        Route::post('/compare', [ModelPortfolioController::class, 'compareWithModel']);

        // Asset allocation optimization
        Route::get('/optimize-by-age', [ModelPortfolioController::class, 'optimizeByAge']);
        Route::post('/optimize-by-horizon', [ModelPortfolioController::class, 'optimizeByTimeHorizon']);
        Route::get('/glide-path', [ModelPortfolioController::class, 'getGlidePath']);

        // Fund recommendations
        Route::post('/funds', [ModelPortfolioController::class, 'getFundRecommendations']);
    });

    // Efficient Frontier / Modern Portfolio Theory (Phase 3.3)
    Route::prefix('efficient-frontier')->group(function () {
        // Calculate efficient frontier
        Route::post('/calculate', [EfficientFrontierController::class, 'calculateEfficientFrontier']);
        Route::get('/default', [EfficientFrontierController::class, 'calculateWithDefaults']);

        // Find optimal portfolios
        Route::post('/optimal-by-return', [EfficientFrontierController::class, 'findOptimalByReturn']);
        Route::post('/optimal-by-risk', [EfficientFrontierController::class, 'findOptimalByRisk']);

        // Portfolio analysis
        Route::post('/compare', [EfficientFrontierController::class, 'compareWithFrontier']);
        Route::post('/statistics', [EfficientFrontierController::class, 'calculateStatistics']);
        Route::get('/analyze-current', [EfficientFrontierController::class, 'analyzeCurrentPortfolio']);

        // Default assumptions
        Route::get('/default-assumptions', [EfficientFrontierController::class, 'getDefaultAssumptions']);
    });

    // Investment Scenarios (Phase 1.3)
    Route::prefix('scenarios')->group(function () {
        // Templates
        Route::get('/templates', [InvestmentScenarioController::class, 'templates']);

        // CRUD operations
        Route::get('/', [InvestmentScenarioController::class, 'index']);
        Route::post('/', [InvestmentScenarioController::class, 'store']);
        Route::get('/{id}', [InvestmentScenarioController::class, 'show']);
        Route::put('/{id}', [InvestmentScenarioController::class, 'update']);
        Route::delete('/{id}', [InvestmentScenarioController::class, 'destroy']);

        // Scenario operations
        Route::post('/{id}/run', [InvestmentScenarioController::class, 'run']);
        Route::get('/{id}/results', [InvestmentScenarioController::class, 'results']);
        Route::post('/compare', [InvestmentScenarioController::class, 'compare']);

        // Save/bookmark operations
        Route::post('/{id}/save', [InvestmentScenarioController::class, 'save']);
        Route::post('/{id}/unsave', [InvestmentScenarioController::class, 'unsave']);
    });
});


// Investment Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/investment-actions')->group(function () {
    Route::get('/', [InvestmentActionDefinitionController::class, 'index']);
    Route::get('/{id}', [InvestmentActionDefinitionController::class, 'show']);
    Route::post('/', [InvestmentActionDefinitionController::class, 'store']);
    Route::put('/{id}', [InvestmentActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [InvestmentActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [InvestmentActionDefinitionController::class, 'toggleEnabled']);
});


// Retirement module routes
Route::middleware('auth:sanctum')->prefix('retirement')->group(function () {
    // Main retirement data and analysis
    Route::get('/', [RetirementController::class, 'index']);
    Route::get('/projections', [RetirementController::class, 'getProjections']);
    Route::get('/required-capital', [RetirementController::class, 'getRequiredCapital']);
    Route::get('/dc-pensions/{id}/projections', [RetirementController::class, 'getDCPensionProjection']);
    Route::post('/analyze', [RetirementController::class, 'analyze']);
    Route::get('/recommendations', [RetirementController::class, 'recommendations']);
    Route::post('/scenarios', [RetirementController::class, 'scenarios']);

    // DC Pension Portfolio Analysis (advanced analytics)
    Route::get('/portfolio-analysis', [RetirementController::class, 'analyzeDCPensionPortfolio']);
    Route::get('/portfolio-analysis/{dcPensionId}', [RetirementController::class, 'analyzeDCPensionPortfolio']);

    // Annual allowance checking
    Route::get('/annual-allowance/{taxYear}', [RetirementController::class, 'checkAnnualAllowance'])->where('taxYear', '.*');

    // Retirement strategies
    Route::get('/strategies', [RetirementController::class, 'getStrategies']);
    Route::get('/strategies/impact', [RetirementController::class, 'calculateStrategyImpact']);

    // Retirement income (tax-optimized drawdown)
    Route::get('/income', [RetirementController::class, 'getRetirementIncome']);
    Route::post('/income/calculate', [RetirementController::class, 'calculateRetirementIncome']);
    Route::get('/income/accounts', [RetirementController::class, 'getIncomeAccounts']);

    // Decumulation analysis (drawdown strategies)
    Route::get('/decumulation-analysis', [DecumulationController::class, 'analysis']);

    // DC pensions
    Route::prefix('pensions/dc')->group(function () {
        Route::post('/', [RetirementController::class, 'storeDCPension']);
        Route::put('/{id}', [RetirementController::class, 'updateDCPension']);
        Route::delete('/{id}', [RetirementController::class, 'destroyDCPension']);

        // DC Pension Holdings (for portfolio optimization)
        Route::get('/{dcPensionId}/holdings', [DCPensionHoldingsController::class, 'index']);
        Route::post('/{dcPensionId}/holdings', [DCPensionHoldingsController::class, 'store']);
        Route::put('/{dcPensionId}/holdings/{holdingId}', [DCPensionHoldingsController::class, 'update']);
        Route::delete('/{dcPensionId}/holdings/{holdingId}', [DCPensionHoldingsController::class, 'destroy']);
        Route::post('/{dcPensionId}/holdings/bulk-update', [DCPensionHoldingsController::class, 'bulkUpdate']);
        Route::get('/{id}/diversification', [RetirementController::class, 'getDCPensionDiversification']);
    });

    // DB pensions
    Route::prefix('pensions/db')->group(function () {
        Route::post('/', [RetirementController::class, 'storeDBPension']);
        Route::put('/{id}', [RetirementController::class, 'updateDBPension']);
        Route::delete('/{id}', [RetirementController::class, 'destroyDBPension']);
    });

    // State pension
    Route::post('/state-pension', [RetirementController::class, 'updateStatePension']);
});


// Retirement Action Definitions (admin-configurable plan actions)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])->prefix('admin/retirement-actions')->group(function () {
    Route::get('/', [RetirementActionDefinitionController::class, 'index']);
    Route::get('/{id}', [RetirementActionDefinitionController::class, 'show']);
    Route::post('/', [RetirementActionDefinitionController::class, 'store']);
    Route::put('/{id}', [RetirementActionDefinitionController::class, 'update']);
    Route::delete('/{id}', [RetirementActionDefinitionController::class, 'destroy']);
    Route::patch('/{id}/toggle', [RetirementActionDefinitionController::class, 'toggleEnabled']);
});


// Estate Liabilities (standard tier — part of Finances/Net Worth, not estate-only)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('estate/liabilities')->group(function () {
    Route::post('/', [EstateController::class, 'storeLiability']);
    Route::put('/{id}', [EstateController::class, 'updateLiability']);
    Route::delete('/{id}', [EstateController::class, 'destroyLiability']);
});

// Estate read-only + IHT calculations (all tiers — used by dashboard)
Route::middleware(['auth:sanctum'])->prefix('estate')->group(function () {
    Route::get('/', [EstateController::class, 'index']);
    Route::post('/calculate-iht', [IHTController::class, 'calculateIHT']);
    Route::get('/net-worth', [EstateController::class, 'getNetWorth']);
    Route::get('/cash-flow', [EstateController::class, 'getCashFlow']);
});

// Estate Planning write operations (pro tier)
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('estate')->group(function () {

    // IHT Profile
    Route::post('/profile', [IHTController::class, 'storeOrUpdateIHTProfile']);

    // Assets
    Route::prefix('assets')->group(function () {
        Route::post('/', [EstateController::class, 'storeAsset']);
        Route::put('/{id}', [EstateController::class, 'updateAsset']);
        Route::delete('/{id}', [EstateController::class, 'destroyAsset']);
    });

    // Gifts (CRUD in EstateController, Strategy in GiftingController)
    Route::prefix('gifts')->group(function () {
        Route::get('/planned-strategy', [GiftingController::class, 'getPlannedGiftingStrategy']);
        Route::get('/personalized-strategy', [GiftingController::class, 'getPersonalizedGiftingStrategy']);
        Route::get('/trust-strategy', [GiftingController::class, 'getPersonalizedTrustStrategy']);
        Route::post('/', [EstateController::class, 'storeGift']);
        Route::put('/{id}', [EstateController::class, 'updateGift']);
        Route::delete('/{id}', [EstateController::class, 'destroyGift']);
    });

    // Life Policy Strategy
    Route::get('/life-policy-strategy', [LifePolicyController::class, 'getLifePolicyStrategy']);

    // Trusts
    Route::prefix('trusts')->group(function () {
        Route::get('/', [TrustController::class, 'getTrusts']);
        Route::post('/', [TrustController::class, 'createTrust']);
        Route::put('/{id}', [TrustController::class, 'updateTrust']);
        Route::delete('/{id}', [TrustController::class, 'deleteTrust']);
        Route::get('/{id}/analyze', [TrustController::class, 'analyzeTrust']);
        Route::get('/{id}/assets', [TrustController::class, 'getTrustAssets']);
        Route::post('/{id}/calculate-iht-impact', [TrustController::class, 'calculateTrustIHTImpact']);
    });

    // Trust planning and tax returns
    Route::get('/trust-recommendations', [TrustController::class, 'getTrustRecommendations']);
    Route::get('/trusts/upcoming-tax-returns', [TrustController::class, 'getUpcomingTaxReturns']);

    // Will Builder
    Route::prefix('will-builder')->group(function () {
        Route::get('/pre-populate', [WillDocumentController::class, 'prePopulate']);
        Route::get('/', [WillDocumentController::class, 'index']);
        Route::post('/', [WillDocumentController::class, 'store']);
        Route::get('/{id}', [WillDocumentController::class, 'show']);
        Route::put('/{id}', [WillDocumentController::class, 'update']);
        Route::post('/{id}/complete', [WillDocumentController::class, 'complete']);
        Route::post('/{id}/mirror', [WillDocumentController::class, 'generateMirror']);
        Route::get('/{id}/validate', [WillDocumentController::class, 'validateDocument']);
        Route::delete('/{id}', [WillDocumentController::class, 'destroy']);
    });

    // Will and Bequests
    Route::get('/will', [WillController::class, 'getWill']);
    Route::post('/will', [WillController::class, 'storeOrUpdateWill']);
    Route::post('/calculate-intestacy', [WillController::class, 'calculateIntestacy']);
    Route::prefix('bequests')->group(function () {
        Route::get('/', [WillController::class, 'getBequests']);
        Route::post('/', [WillController::class, 'storeBequest']);
        Route::put('/{id}', [WillController::class, 'updateBequest']);
        Route::delete('/{id}', [WillController::class, 'deleteBequest']);
    });
    Route::post('/calculate-discount', [GiftingController::class, 'calculateDiscountedGiftDiscount']);

    // Letter to Spouse cross-validation
    Route::get('/letter-validation', [LetterValidationController::class, 'checkConsistency']);

    // Lasting Powers of Attorney
    Route::prefix('lpa')->group(function () {
        Route::get('/', [LpaController::class, 'index']);
        Route::post('/', [LpaController::class, 'store']);
        Route::get('/donor-defaults', [LpaController::class, 'donorDefaults']);
        Route::post('/upload', [LpaController::class, 'upload']);
        Route::get('/{id}', [LpaController::class, 'show'])->where('id', '[0-9]+');
        Route::put('/{id}', [LpaController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/{id}', [LpaController::class, 'destroy'])->where('id', '[0-9]+');
        Route::get('/{id}/compliance', [LpaController::class, 'compliance'])->where('id', '[0-9]+');
        Route::post('/{id}/register', [LpaController::class, 'markRegistered'])->where('id', '[0-9]+');
    });
});


// Tax Product Information routes (Tax status for products)
Route::middleware('auth:sanctum')->prefix('tax-info')->group(function () {
    Route::get('/investment/{accountType}', [TaxProductInfoController::class, 'getInvestmentTaxInfo']);
    Route::get('/savings/{accountType}', [TaxProductInfoController::class, 'getSavingsTaxInfo']);
    Route::get('/summary', [TaxProductInfoController::class, 'getTaxSummary']);
});

// Tax Optimisation routes (cross-module tax strategies)
Route::middleware('auth:sanctum')->prefix('tax')->group(function () {
    Route::get('/optimisation-analysis', [TaxOptimisationController::class, 'getAnalysis']);
    Route::get('/strategies', [TaxOptimisationController::class, 'getStrategies']);
    Route::get('/income-definitions', [IncomeDefinitionsController::class, 'show']);
});

// Lightweight active tax year endpoint — any authenticated user can read this.
// Returns just the tax year label and effective dates so the frontend knows
// which year to display and calculate allowances against. No sensitive admin
// config is exposed here (that stays behind permission:admin.tax_config below).
Route::middleware('auth:sanctum')->get('tax-year/current', [TaxYearController::class, 'current']);

// Tax Settings routes (requires tax config permission)
Route::middleware(['auth:sanctum', 'permission:admin.tax_config'])->prefix('tax-settings')->group(function () {
    Route::get('/current', [TaxSettingsController::class, 'getCurrent']);
    Route::get('/all', [TaxSettingsController::class, 'getAll']);
    Route::get('/calculations', [TaxSettingsController::class, 'getCalculations']);
    Route::post('/create', [TaxSettingsController::class, 'create']);
    Route::put('/{id}', [TaxSettingsController::class, 'update']);
    Route::post('/{id}/activate', [TaxSettingsController::class, 'setActive']);
    Route::post('/{id}/duplicate', [TaxSettingsController::class, 'duplicate']);
    Route::delete('/{id}', [TaxSettingsController::class, 'delete']);
});


// Plans routes (comprehensive cross-module plans)
Route::middleware('auth:sanctum')->prefix('plans')->group(function () {
    Route::get('/statuses', [PlanController::class, 'statuses']);
    Route::get('/goal/{goalId}', [PlanController::class, 'generateGoalPlan']);
    Route::post('/goal/{goalId}/recalculate', [PlanController::class, 'recalculateGoalPlan']);
    Route::get('/{type}', [PlanController::class, 'generate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::post('/{type}/recalculate', [PlanController::class, 'recalculate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::delete('/{type}/clear-cache', [PlanController::class, 'clearCache'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::put('/{type}/funding-source', [PlanController::class, 'updateFundingSource'])
        ->where('type', 'investment|protection|retirement|estate');
});


// Holistic Planning routes (coordinating agent)
Route::middleware(['auth:sanctum', 'feature:pro'])->prefix('holistic')->group(function () {
    // Main holistic analysis and plan
    Route::post('/analyze', [HolisticPlanningController::class, 'analyze']);
    Route::post('/plan', [HolisticPlanningController::class, 'plan']);
    Route::get('/recommendations', [HolisticPlanningController::class, 'recommendations']);
    Route::get('/cash-flow-analysis', [HolisticPlanningController::class, 'cashFlowAnalysis']);

    // Recommendation tracking
    Route::post('/recommendations/{id}/mark-done', [HolisticPlanningController::class, 'markRecommendationDone']);
    Route::post('/recommendations/{id}/in-progress', [HolisticPlanningController::class, 'markRecommendationInProgress']);
    Route::post('/recommendations/{id}/dismiss', [HolisticPlanningController::class, 'dismissRecommendation']);
    Route::get('/recommendations/completed', [HolisticPlanningController::class, 'completedRecommendations']);
    Route::patch('/recommendations/{id}/notes', [HolisticPlanningController::class, 'updateRecommendationNotes']);
});


// Unified Recommendations routes (Phase 5)
Route::middleware('auth:sanctum')->prefix('recommendations')->group(function () {
    // Main recommendations endpoints
    Route::get('/', [RecommendationsController::class, 'index']);
    Route::get('/summary', [RecommendationsController::class, 'summary']);
    Route::get('/top', [RecommendationsController::class, 'top']);
    Route::get('/completed', [RecommendationsController::class, 'completed']);

    // Recommendation tracking actions
    Route::post('/{id}/mark-done', [RecommendationsController::class, 'markDone']);
    Route::post('/{id}/in-progress', [RecommendationsController::class, 'markInProgress']);
    Route::post('/{id}/dismiss', [RecommendationsController::class, 'dismiss']);
    Route::patch('/{id}/notes', [RecommendationsController::class, 'updateNotes']);
});


// What-If Scenarios
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('what-if-scenarios')->group(function () {
    Route::get('/', [WhatIfScenarioController::class, 'index']);
    Route::get('/count', [WhatIfScenarioController::class, 'count']);
    Route::get('/{id}', [WhatIfScenarioController::class, 'show']);
    Route::post('/', [WhatIfScenarioController::class, 'store']);
    Route::put('/{id}', [WhatIfScenarioController::class, 'update']);
    Route::delete('/{id}', [WhatIfScenarioController::class, 'destroy']);
});


// Letter to Spouse (lives under /api auth group, no shared prefix)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/letter-to-spouse', [LetterToSpouseController::class, 'show']);
    Route::get('/letter-to-spouse/exists', [LetterToSpouseController::class, 'exists']);
    Route::get('/letter-to-spouse/spouse', [LetterToSpouseController::class, 'showSpouse']);
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update'])->middleware('feature:standard');
});

// Property routes (Phase 4) — R-9-final-v
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('properties')->group(function () {
    // Property CRUD
    Route::get('/', [PropertyController::class, 'index']);
    Route::post('/', [PropertyController::class, 'store']);
    Route::get('/{id}', [PropertyController::class, 'show']);
    Route::put('/{id}', [PropertyController::class, 'update']);
    Route::delete('/{id}', [PropertyController::class, 'destroy']);

    // Tax calculations
    Route::post('/calculate-sdlt', [PropertyController::class, 'calculateSDLT']);
    Route::post('/{id}/calculate-cgt', [PropertyController::class, 'calculateCGT']);
    Route::post('/{id}/rental-income-tax', [PropertyController::class, 'calculateRentalIncomeTax']);

    // Mortgages for a property — R-9-final-vi
    Route::prefix('{propertyId}/mortgages')->group(function () {
        Route::get('/', [MortgageController::class, 'index']);
        Route::post('/', [MortgageController::class, 'store']);
        Route::put('/{mortgageId}', [MortgageController::class, 'update']);
        Route::delete('/{mortgageId}', [MortgageController::class, 'destroy']);
    });
});

// Mortgage routes (Phase 4) — R-9-final-vi
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('mortgages')->group(function () {
    Route::get('/{id}', [MortgageController::class, 'show']);
    Route::put('/{id}', [MortgageController::class, 'update']);
    Route::delete('/{id}', [MortgageController::class, 'destroy']);
    Route::get('/{id}/amortization-schedule', [MortgageController::class, 'amortizationSchedule']);
    Route::post('/calculate-payment', [MortgageController::class, 'calculatePayment']);
});

// Household coordination routes (spousal planning) — R-9-final-iv
Route::middleware('auth:sanctum')->prefix('household')->group(function () {
    Route::get('/net-worth', [HouseholdController::class, 'getNetWorth']);
    Route::get('/optimisations', [HouseholdController::class, 'getOptimisations']);
    Route::get('/death-scenario', [HouseholdController::class, 'getDeathScenario']);
});

// Life Events routes (future occurrences impacting net worth) — R-9-final-ii
Route::middleware('auth:sanctum')->prefix('life-events')->group(function () {
    Route::get('/', [LifeEventController::class, 'index']);
    Route::get('/types', [LifeEventController::class, 'getEventTypes']);
    Route::get('/by-age', [LifeEventController::class, 'getByAge']);
    Route::post('/', [LifeEventController::class, 'store']);
    Route::get('/{id}', [LifeEventController::class, 'show']);
    Route::put('/{id}', [LifeEventController::class, 'update']);
    Route::delete('/{id}', [LifeEventController::class, 'destroy']);
    Route::post('/{id}/complete', [LifeEventController::class, 'markCompleted']);
});

// Goals module routes (unified goals-based planning) — R-9-final-i
Route::middleware('auth:sanctum')->prefix('goals')->group(function () {
    // Main goals data and analysis
    Route::get('/', [GoalsController::class, 'index']);
    Route::get('/analysis', [GoalsController::class, 'analysis']);
    Route::get('/dashboard-overview', [GoalsController::class, 'dashboardOverview']);

    // Projection (net worth chart with events)
    Route::get('/projection', [GoalsController::class, 'getProjection']);
    Route::get('/household-summary', [GoalsController::class, 'getHouseholdSummary']);
    Route::get('/financial-forecast', [GoalsController::class, 'getFinancialForecast']);

    // Reference data
    Route::get('/types', [GoalsController::class, 'getGoalTypes']);
    Route::get('/risk-levels', [GoalsController::class, 'getRiskLevels']);

    // Property cost calculator
    Route::post('/calculate-property-costs', [GoalsController::class, 'calculatePropertyCosts']);

    // Goal CRUD
    Route::post('/', [GoalsController::class, 'store']);
    Route::get('/{id}', [GoalsController::class, 'show']);
    Route::put('/{id}', [GoalsController::class, 'update']);
    Route::delete('/{id}', [GoalsController::class, 'destroy']);

    // Goal-specific operations
    Route::post('/{id}/contribution', [GoalsController::class, 'recordContribution']);
    Route::get('/{id}/projections', [GoalsController::class, 'getProjections']);
    Route::get('/{id}/scenarios', [GoalsController::class, 'getScenarios']);
    Route::get('/{id}/contributions', [GoalsController::class, 'getContributionHistory']);

    // Goal dependencies
    Route::get('/{id}/dependencies', [GoalsController::class, 'getDependencies']);
    Route::post('/{id}/dependencies', [GoalsController::class, 'addDependency']);
    Route::delete('/{id}/dependencies/{dependsOnId}', [GoalsController::class, 'removeDependency']);
});
