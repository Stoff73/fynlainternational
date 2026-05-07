<?php

use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\BusinessInterestController;
use App\Http\Controllers\Api\ChattelController;
use App\Http\Controllers\Api\ContactFormController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\FamilyMembersController;
use App\Http\Controllers\Api\GDPRController;
use App\Http\Controllers\Api\GoalsController;
use App\Http\Controllers\Api\HolisticPlanningController;
use App\Http\Controllers\Api\HouseholdController;
use App\Http\Controllers\Api\InfoGuideController;
use App\Http\Controllers\Api\JourneyController;
use App\Http\Controllers\Api\LetterToSpouseController;
use App\Http\Controllers\Api\LifeStageController;
use App\Http\Controllers\Api\MFAController;
use App\Http\Controllers\Api\MortgageController;
use App\Http\Controllers\Api\NetWorthController;
use App\Http\Controllers\Api\OccupationController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PersonalAccountsController;
use App\Http\Controllers\Api\PostcodeLookupController;
use App\Http\Controllers\Api\PreviewController;
use App\Http\Controllers\Api\ProfileCompletenessController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\RecommendationsController;
use App\Http\Controllers\Api\RiskPreferenceController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\Settings\AssumptionsController;
use App\Http\Controllers\Api\SpousePermissionController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\WhatIfScenarioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Contact form (public, rate-limited)
Route::post('/contact', [ContactFormController::class, 'submit'])->middleware('throttle:3,5');

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/verify-code', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('/resend-code', [AuthController::class, 'resendCode'])->middleware('throttle:5,1');

    // Beacon logout - accepts token in body for browser/tab close handling
    // No auth middleware since sendBeacon cannot set Authorization header
    Route::post('/logout-beacon', [AuthController::class, 'logoutBeacon'])->middleware('throttle:10,1');

    // MFA verification during login (no auth required - user is partially authenticated)
    Route::post('/mfa/verify', [MFAController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/mfa/recovery', [MFAController::class, 'useRecoveryCode'])->middleware('throttle:5,1');

    // Password reset routes (no auth required)
    Route::prefix('password-reset')->group(function () {
        Route::post('/request', [PasswordResetController::class, 'request'])->middleware('throttle:3,1');
        Route::post('/verify-email', [PasswordResetController::class, 'verifyEmail'])->middleware('throttle:10,1');
        Route::post('/resend-code', [PasswordResetController::class, 'resendCode'])->middleware('throttle:3,1');
        Route::post('/verify-mfa', [PasswordResetController::class, 'verifyMfa'])->middleware('throttle:10,1');
        Route::post('/mfa-recovery', [PasswordResetController::class, 'useMfaRecovery'])->middleware('throttle:3,1');
        Route::post('/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:3,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user'])->middleware('throttle:60,1');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:5,1');

        // MFA management (requires full authentication)
        Route::prefix('mfa')->group(function () {
            Route::get('/status', [MFAController::class, 'status']);
            Route::post('/setup', [MFAController::class, 'setup']);
            Route::post('/verify-setup', [MFAController::class, 'verifySetup']);
            Route::post('/disable', [MFAController::class, 'disable']);
            Route::post('/recovery-codes', [MFAController::class, 'regenerateRecoveryCodes']);
        });

        // Session management
        Route::prefix('sessions')->group(function () {
            Route::get('/', [SessionController::class, 'index']);
            Route::delete('/{id}', [SessionController::class, 'destroy']);
            Route::delete('/others/all', [SessionController::class, 'destroyOthers']);
        });

        // GDPR & Privacy routes
        Route::prefix('gdpr')->group(function () {
            // Consent management
            Route::get('/consents', [GDPRController::class, 'getConsents']);
            Route::put('/consents', [GDPRController::class, 'updateConsents']);
            Route::get('/consents/history', [GDPRController::class, 'getConsentHistory']);

            // Data export (right to portability) - rate limited to 3/hour
            Route::post('/export', [GDPRController::class, 'requestExport'])->middleware('throttle:export');
            Route::get('/export/status', [GDPRController::class, 'getExportStatus']);
            Route::get('/export/{id}/download', [GDPRController::class, 'downloadExport']);

            // Data erasure (right to be forgotten) - self-service immediate deletion
            Route::post('/erasure/initiate', [GDPRController::class, 'initiateErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/verify', [GDPRController::class, 'verifyErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/execute', [GDPRController::class, 'executeErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/resend-code', [GDPRController::class, 'resendDeletionCode'])->middleware('throttle:sensitive');

            // Legacy erasure endpoints (deprecated, kept for backwards compatibility)
            Route::post('/erasure', [GDPRController::class, 'requestErasure'])->middleware('throttle:sensitive');
            Route::get('/erasure/status', [GDPRController::class, 'getErasureStatus']);
            Route::post('/erasure/{id}/confirm', [GDPRController::class, 'confirmErasure'])->middleware('throttle:sensitive');
            Route::post('/erasure/{id}/cancel', [GDPRController::class, 'cancelErasure']);
        });
    });
});

// Preview Mode routes (allows unauthenticated preview access)
Route::prefix('preview')->group(function () {
    // Public routes - no auth required (rate limited)
    Route::get('/personas', [PreviewController::class, 'getPersonas']);
    Route::post('/login/{personaId}', [PreviewController::class, 'login'])->middleware('throttle:3,1');

    // Authenticated preview routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/switch/{personaId}', [PreviewController::class, 'switch'])->middleware('throttle:20,1');
        Route::post('/exit', [PreviewController::class, 'exit']);
    });
});

// Onboarding routes
Route::middleware('auth:sanctum')->prefix('onboarding')->group(function () {
    Route::get('/status', [OnboardingController::class, 'getOnboardingStatus']);
    Route::post('/focus-area', [OnboardingController::class, 'setFocusArea']);
    Route::get('/steps', [OnboardingController::class, 'getSteps']);
    Route::get('/step/{step}', [OnboardingController::class, 'getStepData']);
    Route::post('/step', [OnboardingController::class, 'saveStepProgress']);
    Route::post('/skip-step', [OnboardingController::class, 'skipStep']);
    Route::get('/skip-reason/{step}', [OnboardingController::class, 'getSkipReason']);
    Route::post('/skip-to-dashboard', [OnboardingController::class, 'skipToDashboard']);
    Route::post('/complete', [OnboardingController::class, 'completeOnboarding']);
    Route::post('/complete-quick', [OnboardingController::class, 'completeQuickOnboarding']);
    Route::post('/restart', [OnboardingController::class, 'restartOnboarding']);
});

// Journey onboarding routes
Route::middleware('auth:sanctum')->prefix('journeys')->group(function () {
    Route::get('/selections', [JourneyController::class, 'getSelections']);
    Route::post('/selections', [JourneyController::class, 'saveSelections']);
    Route::get('/preview', [JourneyController::class, 'preview']);
    Route::get('/dashboard-prompts', [JourneyController::class, 'getDashboardPrompts']);
    Route::post('/dismiss-prompt', [JourneyController::class, 'dismissPrompt']);
    Route::get('/{journey}/steps', [JourneyController::class, 'getSteps']);
    Route::post('/{journey}/start', [JourneyController::class, 'startJourney']);
    Route::post('/{journey}/complete', [JourneyController::class, 'completeJourney']);
});

// Life Stage routes
Route::middleware('auth:sanctum')->prefix('life-stage')->group(function () {
    Route::get('/progress', [LifeStageController::class, 'progress']);
    Route::get('/completeness', [LifeStageController::class, 'completeness']);
    Route::post('/set', [LifeStageController::class, 'setStage']);
    Route::post('/complete-step', [LifeStageController::class, 'completeStep']);
});

// User Profile routes (Phase 2)
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    // Profile endpoints
    Route::get('/profile', [UserProfileController::class, 'getProfile']);
    Route::put('/profile/personal', [UserProfileController::class, 'updatePersonalInfo']);
    Route::put('/profile/income-occupation', [UserProfileController::class, 'updateIncomeOccupation']);
    Route::put('/profile/expenditure', [UserProfileController::class, 'updateExpenditure']);
    Route::put('/profile/domicile', [UserProfileController::class, 'updateDomicileInfo']);
    Route::get('/profile/completeness', [ProfileCompletenessController::class, 'check']);
    Route::get('/financial-commitments', [UserProfileController::class, 'getFinancialCommitments']);
    Route::get('/spouse/financial-commitments', [UserProfileController::class, 'getSpouseFinancialCommitments']);
    Route::put('/dashboard-widget-order', [UserProfileController::class, 'updateDashboardWidgetOrder']);

    // Letter to Spouse
    Route::get('/letter-to-spouse', [LetterToSpouseController::class, 'show']);
    Route::get('/letter-to-spouse/exists', [LetterToSpouseController::class, 'exists']);
    Route::get('/letter-to-spouse/spouse', [LetterToSpouseController::class, 'showSpouse']);
    Route::put('/letter-to-spouse', [LetterToSpouseController::class, 'update'])->middleware('feature:standard');

    // Family Members CRUD
    Route::prefix('family-members')->group(function () {
        Route::get('/', [FamilyMembersController::class, 'index']);
        Route::post('/', [FamilyMembersController::class, 'store'])->middleware('feature:family');
        Route::get('/{id}', [FamilyMembersController::class, 'show']);
        Route::put('/{id}', [FamilyMembersController::class, 'update'])->middleware('feature:family');
        Route::delete('/{id}', [FamilyMembersController::class, 'destroy'])->middleware('feature:family');
    });

    // Personal Accounts (P&L, Cashflow, Balance Sheet)
    Route::prefix('personal-accounts')->group(function () {
        Route::get('/', [PersonalAccountsController::class, 'index']);
        Route::post('/calculate', [PersonalAccountsController::class, 'calculate']);
        Route::post('/line-item', [PersonalAccountsController::class, 'storeLineItem']);
        Route::put('/line-item/{id}', [PersonalAccountsController::class, 'updateLineItem']);
        Route::delete('/line-item/{id}', [PersonalAccountsController::class, 'deleteLineItem']);
    });

    // Preview/Guidance routes
    Route::post('/seed-persona-data', [PreviewController::class, 'seedPersonaData']);
    Route::get('/guidance-status', [PreviewController::class, 'getGuidanceStatus']);
    Route::post('/guidance-status', [PreviewController::class, 'updateGuidanceStatus']);
});

// Information Guide routes
Route::middleware('auth:sanctum')->prefix('info-guide')->group(function () {
    Route::get('/requirements', [InfoGuideController::class, 'getRequirements']);
    Route::get('/preference', [InfoGuideController::class, 'getPreference']);
    Route::put('/preference', [InfoGuideController::class, 'updatePreference']);
});

// Spouse Permission routes
Route::middleware('auth:sanctum')->prefix('spouse-permission')->group(function () {
    Route::get('/status', [SpousePermissionController::class, 'status']);
    Route::post('/request', [SpousePermissionController::class, 'request']);
    Route::post('/accept', [SpousePermissionController::class, 'accept']);
    Route::post('/reject', [SpousePermissionController::class, 'reject']);
    Route::delete('/revoke', [SpousePermissionController::class, 'revoke']);
});

// Spouse data access routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users/{userId}', [UserProfileController::class, 'getUserById']);
    Route::put('/users/{userId}/expenditure', [UserProfileController::class, 'updateSpouseExpenditure']);
});

// Net Worth routes (Phase 3)
Route::middleware('auth:sanctum')->prefix('net-worth')->group(function () {
    Route::get('/overview', [NetWorthController::class, 'getOverview']);
    Route::get('/breakdown', [NetWorthController::class, 'getBreakdown']);
    Route::get('/assets-summary', [NetWorthController::class, 'getAssetsSummary']);
    Route::get('/assets-summary-detailed', [NetWorthController::class, 'getAssetsSummaryWithDetails']);
    Route::get('/joint-assets', [NetWorthController::class, 'getJointAssets']);
    Route::post('/refresh', [NetWorthController::class, 'refresh']);
});

// Joint Account Logs routes
Route::middleware('auth:sanctum')->prefix('joint-account-logs')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\JointAccountLogController::class, 'index']);
});

// Property routes (Phase 4)
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

    // Mortgages for a property
    Route::prefix('{propertyId}/mortgages')->group(function () {
        Route::get('/', [MortgageController::class, 'index']);
        Route::post('/', [MortgageController::class, 'store']);
        Route::put('/{mortgageId}', [MortgageController::class, 'update']);
        Route::delete('/{mortgageId}', [MortgageController::class, 'destroy']);
    });
});

// Mortgage routes (Phase 4)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('mortgages')->group(function () {
    Route::get('/{id}', [MortgageController::class, 'show']);
    Route::put('/{id}', [MortgageController::class, 'update']);
    Route::delete('/{id}', [MortgageController::class, 'destroy']);
    Route::get('/{id}/amortization-schedule', [MortgageController::class, 'amortizationSchedule']);
    Route::post('/calculate-payment', [MortgageController::class, 'calculatePayment']);
});

// Business Interest routes
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('business-interests')->group(function () {
    Route::get('/', [BusinessInterestController::class, 'index']);
    Route::post('/', [BusinessInterestController::class, 'store']);
    Route::get('/{id}', [BusinessInterestController::class, 'show']);
    Route::put('/{id}', [BusinessInterestController::class, 'update']);
    Route::delete('/{id}', [BusinessInterestController::class, 'destroy']);
    Route::get('/{id}/tax-deadlines', [BusinessInterestController::class, 'taxDeadlines']);
    Route::get('/{id}/exit-calculation', [BusinessInterestController::class, 'exitCalculation']);
});

// Chattel routes (personal property / chattels & valuables)
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('chattels')->group(function () {
    Route::get('/', [ChattelController::class, 'index']);
    Route::post('/', [ChattelController::class, 'store']);
    Route::get('/{id}', [ChattelController::class, 'show']);
    Route::put('/{id}', [ChattelController::class, 'update']);
    Route::delete('/{id}', [ChattelController::class, 'destroy']);
    Route::post('/{id}/calculate-cgt', [ChattelController::class, 'calculateCGT']);
});

// Dashboard routes (aggregated data from all modules)
Route::middleware('auth:sanctum')->prefix('dashboard')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/alerts', [DashboardController::class, 'alerts']);
    Route::post('/alerts/{id}/dismiss', [DashboardController::class, 'dismissAlert']);
    Route::post('/invalidate-cache', [DashboardController::class, 'invalidateCache']);
});

// Protection module routes — relocated to packs/country-gb/routes/api.php in R-9e.

// Savings module routes — relocated to packs/country-gb/routes/api.php in R-9d.

// Goals module routes (unified goals-based planning)
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

// Life Events routes (future occurrences impacting net worth)
Route::middleware('auth:sanctum')->prefix('life-events')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\LifeEventController::class, 'index']);
    Route::get('/types', [\App\Http\Controllers\Api\LifeEventController::class, 'getEventTypes']);
    Route::get('/by-age', [\App\Http\Controllers\Api\LifeEventController::class, 'getByAge']);
    Route::post('/', [\App\Http\Controllers\Api\LifeEventController::class, 'store']);
    Route::get('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'show']);
    Route::put('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'update']);
    Route::delete('/{id}', [\App\Http\Controllers\Api\LifeEventController::class, 'destroy']);
    Route::post('/{id}/complete', [\App\Http\Controllers\Api\LifeEventController::class, 'markCompleted']);
});

// Investment module routes — relocated to packs/country-gb/routes/api.php in R-9f.

// Estate module routes (Liabilities, read-only + IHT, Estate Planning write ops) — relocated to packs/country-gb/routes/api.php in R-9h.

// Retirement module routes — relocated to packs/country-gb/routes/api.php in R-9g.

// Plans routes (comprehensive cross-module plans)
Route::middleware('auth:sanctum')->prefix('plans')->group(function () {
    // Plan system
    Route::get('/statuses', [\App\Http\Controllers\Api\Plans\PlanController::class, 'statuses']);
    Route::get('/goal/{goalId}', [\App\Http\Controllers\Api\Plans\PlanController::class, 'generateGoalPlan']);
    Route::post('/goal/{goalId}/recalculate', [\App\Http\Controllers\Api\Plans\PlanController::class, 'recalculateGoalPlan']);
    Route::get('/{type}', [\App\Http\Controllers\Api\Plans\PlanController::class, 'generate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::post('/{type}/recalculate', [\App\Http\Controllers\Api\Plans\PlanController::class, 'recalculate'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::delete('/{type}/clear-cache', [\App\Http\Controllers\Api\Plans\PlanController::class, 'clearCache'])
        ->where('type', 'investment|protection|retirement|estate');
    Route::put('/{type}/funding-source', [\App\Http\Controllers\Api\Plans\PlanController::class, 'updateFundingSource'])
        ->where('type', 'investment|protection|retirement|estate');
});

// Household coordination routes (spousal planning)
Route::middleware('auth:sanctum')->prefix('household')->group(function () {
    Route::get('/net-worth', [HouseholdController::class, 'getNetWorth']);
    Route::get('/optimisations', [HouseholdController::class, 'getOptimisations']);
    Route::get('/death-scenario', [HouseholdController::class, 'getDeathScenario']);
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

// Tax Product Information + Tax Optimisation routes — relocated to packs/country-gb/routes/api.php in R-9i.

// Payment routes (public)
Route::prefix('payment')->group(function () {
    Route::get('/plans', [\App\Http\Controllers\Api\PaymentController::class, 'plans']);
});

// Payment routes (authenticated)
Route::middleware('auth:sanctum')->prefix('payment')->group(function () {
    Route::get('/trial-status', [\App\Http\Controllers\Api\PaymentController::class, 'trialStatus']);
    Route::get('/billing-history', [\App\Http\Controllers\Api\PaymentController::class, 'billingHistory']);
    Route::post('/create-order', [\App\Http\Controllers\Api\PaymentController::class, 'createOrder'])->middleware('throttle:10,1');
    Route::post('/confirm', [\App\Http\Controllers\Api\PaymentController::class, 'confirmPayment'])->middleware('throttle:10,1');
    Route::post('/upgrade', [\App\Http\Controllers\Api\PaymentController::class, 'upgradeSubscription'])->middleware('throttle:10,1');
    Route::post('/cancel-subscription', [\App\Http\Controllers\Api\PaymentController::class, 'cancelSubscription'])->middleware('throttle:1,1');
    Route::post('/delete-all-data', [\App\Http\Controllers\Api\PaymentController::class, 'deleteAllData'])->middleware('throttle:1,5');
    Route::post('/validate-discount', [\App\Http\Controllers\Api\PaymentController::class, 'validateDiscountCode'])->middleware('throttle:20,1');
    Route::get('/invoices/{invoice}/download', [\App\Http\Controllers\Api\PaymentController::class, 'downloadInvoice'])->middleware('throttle:10,1');
});

// Referral
Route::middleware('auth:sanctum')->prefix('referral')->group(function () {
    Route::get('/code', [\App\Http\Controllers\Api\ReferralController::class, 'getMyCode']);
    Route::post('/invite', [\App\Http\Controllers\Api\ReferralController::class, 'sendInvitation'])->middleware('throttle:10,1');
    Route::get('/list', [\App\Http\Controllers\Api\ReferralController::class, 'myReferrals']);
});

// Revolut webhook (no auth:sanctum — verified by HMAC signature)
Route::post('/webhooks/revolut', [\App\Http\Controllers\Api\WebhookController::class, 'handleRevolut'])->middleware('throttle:60,1');

// User Settings routes
Route::middleware('auth:sanctum')->prefix('settings')->group(function () {
    // Planning Assumptions
    Route::get('/assumptions', [AssumptionsController::class, 'index']);
    Route::put('/assumptions/{type}', [AssumptionsController::class, 'update']);
});

// Admin Panel routes (RBAC-protected)
Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\Api\AdminController::class, 'dashboard']);

    // Roles list (for user management dropdowns)
    Route::get('/roles', [\App\Http\Controllers\Api\AdminController::class, 'getRoles']);

    // User management - view (support + admin via admin.access)
    Route::get('/users', [\App\Http\Controllers\Api\AdminController::class, 'getUsers']);

    // User management - create/update (requires users.edit)
    Route::middleware('permission:users.edit')->group(function () {
        Route::post('/users', [\App\Http\Controllers\Api\AdminController::class, 'createUser']);
        Route::put('/users/{id}', [\App\Http\Controllers\Api\AdminController::class, 'updateUser']);
    });

    // User management - delete (requires users.delete)
    Route::delete('/users/{id}', [\App\Http\Controllers\Api\AdminController::class, 'deleteUser'])
        ->middleware('permission:users.delete');

    // User module status tracking
    Route::get('users/{id}/module-status', [\App\Http\Controllers\Api\AdminController::class, 'moduleStatus']);

    // Subscription stats
    Route::get('/subscriptions/stats', [\App\Http\Controllers\Api\AdminController::class, 'getSubscriptionStats']);

    // AI provider management
    Route::get('/ai-provider', [\App\Http\Controllers\Api\AdminController::class, 'getAiProvider']);
    Route::post('/ai-provider', [\App\Http\Controllers\Api\AdminController::class, 'setAiProvider']);

    // AI Audit trail
    Route::prefix('ai-audit')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\AiAuditController::class, 'users']);
        Route::get('/users/{userId}/conversations', [\App\Http\Controllers\Api\AiAuditController::class, 'conversations']);
        Route::get('/conversations/{conversationId}/messages', [\App\Http\Controllers\Api\AiAuditController::class, 'messages']);
    });

    // Database backup - list (read-only, no rate limit)
    Route::middleware(['permission:admin.backup'])->group(function () {
        Route::get('/backup/list', [\App\Http\Controllers\Api\AdminController::class, 'listBackups']);
    });

    // Database backup - write operations (rate limited: 3 per minute)
    Route::middleware(['permission:admin.backup', 'throttle:3,1'])->group(function () {
        Route::post('/backup/create', [\App\Http\Controllers\Api\AdminController::class, 'createBackup']);
        Route::post('/backup/restore', [\App\Http\Controllers\Api\AdminController::class, 'restoreBackup']);
        Route::delete('/backup/delete', [\App\Http\Controllers\Api\AdminController::class, 'deleteBackup']);
    });

    // User Metrics
    Route::get('/user-metrics/snapshot', [\App\Http\Controllers\Api\UserMetricsController::class, 'snapshot']);
    Route::get('/user-metrics/trials', [\App\Http\Controllers\Api\UserMetricsController::class, 'trials']);
    Route::get('/user-metrics/plans', [\App\Http\Controllers\Api\UserMetricsController::class, 'plans']);
    Route::get('/user-metrics/activity', [\App\Http\Controllers\Api\UserMetricsController::class, 'activity']);
    Route::get('/user-metrics/engagement', [\App\Http\Controllers\Api\UserMetricsController::class, 'engagement']);

    // Discount Code Management
    Route::get('/discount-codes', [\App\Http\Controllers\Api\AdminController::class, 'listDiscountCodes']);
    Route::post('/discount-codes', [\App\Http\Controllers\Api\AdminController::class, 'createDiscountCode']);
    Route::put('/discount-codes/{id}', [\App\Http\Controllers\Api\AdminController::class, 'updateDiscountCode']);
    Route::delete('/discount-codes/{id}', [\App\Http\Controllers\Api\AdminController::class, 'deleteDiscountCode']);
    Route::patch('/discount-codes/{id}/toggle', [\App\Http\Controllers\Api\AdminController::class, 'toggleDiscountCode']);
});

// Retirement Action Definitions (admin-configurable plan actions) — relocated to packs/country-gb/routes/api.php in R-9g.

// Investment Action Definitions (admin-configurable plan actions) — relocated to packs/country-gb/routes/api.php in R-9f.

// Protection Action Definitions (admin-configurable plan actions) — relocated to packs/country-gb/routes/api.php in R-9e.

// Generic action definition routes (for Decision Matrix)
Route::middleware(['auth:sanctum', 'permission:admin.access', 'throttle:30,1'])
    ->prefix('admin/action-definitions/{module}')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'show']);
        Route::post('/', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'store']);
        Route::patch('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'destroy']);
        Route::patch('/{id}/toggle', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'toggleEnabled']);
    });

Route::middleware(['auth:sanctum', 'permission:admin.access'])
    ->get('admin/decision-matrix/{module}', [\App\Http\Controllers\Api\ActionDefinitionController::class, 'decisionMatrix']);

// Tax Year + Tax Settings routes — relocated to packs/country-gb/routes/api.php in R-9i.

// Document Upload & AI Extraction routes (rate limited for security)
Route::middleware(['auth:sanctum', 'throttle:30,1'])->prefix('documents')->group(function () {
    Route::get('/', [DocumentController::class, 'index']);
    Route::get('/types', [DocumentController::class, 'types']);
    Route::post('/upload', [DocumentController::class, 'upload'])->middleware('throttle:10,1');
    Route::post('/upload-only', [DocumentController::class, 'uploadOnly'])->middleware('throttle:10,1');
    Route::get('/{id}', [DocumentController::class, 'show']);
    Route::get('/{id}/extraction', [DocumentController::class, 'getExtraction']);
    Route::post('/{id}/confirm', [DocumentController::class, 'confirm']);
    Route::post('/{id}/confirm-excel', [DocumentController::class, 'confirmExcel']);
    Route::post('/{id}/reprocess', [DocumentController::class, 'reprocess'])->middleware('throttle:5,1');
    Route::delete('/{id}', [DocumentController::class, 'destroy']);
});

// Postcode Lookup routes (UK address lookup via GetAddress.io)
Route::middleware(['auth:sanctum', 'throttle:30,1'])
    ->get('/postcode-lookup/{postcode}', [PostcodeLookupController::class, 'lookup']);

// Occupation search (SOC 2020)
Route::middleware('auth:sanctum')
    ->get('/occupations/search', [OccupationController::class, 'search']);

// What-If Scenarios
Route::middleware(['auth:sanctum', 'feature:standard'])->prefix('what-if-scenarios')->group(function () {
    Route::get('/', [WhatIfScenarioController::class, 'index']);
    Route::get('/count', [WhatIfScenarioController::class, 'count']);
    Route::get('/{id}', [WhatIfScenarioController::class, 'show']);
    Route::post('/', [WhatIfScenarioController::class, 'store']);
    Route::put('/{id}', [WhatIfScenarioController::class, 'update']);
    Route::delete('/{id}', [WhatIfScenarioController::class, 'destroy']);
});

// AI Chat routes
Route::middleware(['auth:sanctum', 'throttle:20,1'])->prefix('ai-chat')->group(function () {
    Route::get('/token-usage', [AiChatController::class, 'tokenUsage']);
    Route::get('/conversations', [AiChatController::class, 'index']);
    Route::post('/conversations', [AiChatController::class, 'create']);
    Route::get('/conversations/{id}', [AiChatController::class, 'show']);
    Route::delete('/conversations/{id}', [AiChatController::class, 'destroy']);
    Route::post('/conversations/{id}/messages', [AiChatController::class, 'sendMessage']);
});

// Internal Agent API routes (Python Agent SDK sidecar callbacks)
Route::prefix('internal/agent')->middleware('agent.token')->group(function () {
    Route::get('/analysis/{module}', [\App\Http\Controllers\Api\AgentInternalController::class, 'moduleAnalysis']);
    Route::get('/tax/{topic}', [\App\Http\Controllers\Api\AgentInternalController::class, 'taxInformation']);
    Route::post('/scenario', [\App\Http\Controllers\Api\AgentInternalController::class, 'scenario']);
    Route::post('/prerequisite-check', [\App\Http\Controllers\Api\AgentInternalController::class, 'prerequisiteCheck']);
    Route::get('/user-context/{userId}', [\App\Http\Controllers\Api\AgentInternalController::class, 'userContext']);
    Route::get('/recommendations', [\App\Http\Controllers\Api\AgentInternalController::class, 'recommendations']);
});

// ===========================
// Advisor Routes
// ===========================
Route::middleware(['auth:sanctum', 'advisor'])
    ->prefix('advisor')
    ->controller(\App\Http\Controllers\Api\AdvisorController::class)
    ->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('clients', 'clients');
        Route::get('clients/{id}', 'clientDetail');
        Route::get('clients/{id}/modules', 'clientModuleStatus');
        Route::post('clients/{id}/enter', 'enterClient');
        Route::post('exit', 'exitClient');
        Route::get('activities', 'activities');
        Route::post('activities', 'storeActivity');
        Route::put('activities/{id}', 'updateActivity');
        Route::get('reviews-due', 'reviewsDue');
        Route::get('reports', 'reports');
    });

// Bug Report route (works for both authenticated and guest users)
Route::post('/bug-report', [BugReportController::class, 'store'])
    ->middleware('throttle:bug-reports');

/*
 |-----------------------------------------------------------------------
 | ZA Pack Routes (WS 1.2b)
 |-----------------------------------------------------------------------
 |
 | All SA-specific endpoints are grouped under /api/za/*. The
 | active.jurisdiction middleware validates pack registration and (when
 | authenticated) user entitlement against FYNLA_ACTIVE_PACKS. The
 | pack.enabled:za middleware is a belt-and-braces check that the pack
 | has booted — useful for routes that don't have {cc} in the URL.
 |
 | Contracts resolved via pack.za.* container bindings registered in
 | packs/country-za/src/Providers/ZaPackServiceProvider.php.
 |
 | TODO(WS-D): /api/za/* currently has installation-level gating only
 | (pack.enabled:za). active.jurisdiction is a no-op without {cc} in the
 | URL (ActiveJurisdictionMiddleware L42-46). When user_jurisdictions
 | becomes a row-based check, refactor this group to /api/{cc=za}/* so
 | per-user entitlement enforces. See architect audit §2 (2026-04-18).
 */
Route::middleware(['auth:sanctum', 'active.jurisdiction', 'pack.enabled:za'])
    ->prefix('za')
    ->as('za.')
    ->group(function () {
        Route::prefix('savings')->as('savings.')->group(function () {
            Route::get('dashboard', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('contributions', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'listContributions'])
                ->name('contributions.index');
            Route::post('contributions', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'storeContribution'])
                ->name('contributions.store');
            Route::post('emergency-fund/assess', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'assessEmergencyFund'])
                ->name('emergency-fund.assess');
            Route::get('accounts', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [\Fynla\Packs\Za\Http\Controllers\ZaSavingsController::class, 'storeAccount'])
                ->name('accounts.store');
        });

        // WS 1.3c — Investment
        Route::prefix('investments')->as('investments.')->group(function () {
            Route::get('dashboard', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('accounts', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'storeAccount'])
                ->name('accounts.store');
            Route::get('holdings', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'listHoldings'])
                ->name('holdings.index');
            Route::get('holdings/{holdingId}/lots', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'listLots'])
                ->whereNumber('holdingId')
                ->name('holdings.lots');
            Route::post('holdings/purchase', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'storePurchase'])
                ->name('holdings.purchase');
            Route::post('holdings/disposal', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'recordDisposal'])
                ->name('holdings.disposal');
            Route::post('cgt/calculate', [\Fynla\Packs\Za\Http\Controllers\ZaInvestmentController::class, 'calculateCgt'])
                ->name('cgt.calculate');
        });

        // WS 1.3c — Exchange Control
        Route::prefix('exchange-control')->as('exchange-control.')->group(function () {
            Route::get('dashboard', [\Fynla\Packs\Za\Http\Controllers\ZaExchangeControlController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('transfers', [\Fynla\Packs\Za\Http\Controllers\ZaExchangeControlController::class, 'listTransfers'])
                ->name('transfers.index');
            Route::post('transfers', [\Fynla\Packs\Za\Http\Controllers\ZaExchangeControlController::class, 'storeTransfer'])
                ->name('transfers.store');
            Route::post('check-approval', [\Fynla\Packs\Za\Http\Controllers\ZaExchangeControlController::class, 'checkApproval'])
                ->name('check-approval');
        });

        // WS 1.4d — Retirement
        Route::prefix('retirement')->as('retirement.')->group(function () {
            Route::get('dashboard', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'dashboard'])->name('dashboard');

            Route::get('funds', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'listFunds'])->name('funds.index');
            Route::post('funds', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'storeFund'])->name('funds.store');
            Route::get('funds/{fundId}/buckets', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'showBuckets'])->name('funds.buckets');

            Route::post('contributions', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'storeContribution'])->name('contributions.store');

            Route::post('savings-pot/simulate', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'simulateSavingsPotWithdrawal'])->name('savings-pot.simulate');
            Route::post('savings-pot/withdraw', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'withdrawSavingsPot'])->name('savings-pot.withdraw');

            Route::post('tax-relief/calculate', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'calculateTaxRelief'])->name('tax-relief.calculate');

            Route::prefix('annuities')->as('annuities.')->group(function () {
                Route::post('living/quote', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'quoteLivingAnnuity'])->name('living.quote');
                Route::post('life/quote', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'quoteLifeAnnuity'])->name('life.quote');
                Route::post('compulsory-apportion', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'apportionCompulsory'])->name('compulsory-apportion');
            });

            Route::prefix('reg28')->as('reg28.')->group(function () {
                Route::match(['get', 'post'], 'check', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'checkReg28'])->name('check');
                Route::get('snapshots', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'listReg28Snapshots'])->name('snapshots.index');
                Route::post('snapshots', [\Fynla\Packs\Za\Http\Controllers\ZaRetirementController::class, 'storeReg28Snapshot'])->name('snapshots.store');
            });
        });

        // WS 1.5b — Protection
        Route::prefix('protection')->as('protection.')->group(function () {
            Route::get('dashboard', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'dashboard'])->name('dashboard');

            Route::get('policies', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'listPolicies'])->name('policies.index');
            Route::post('policies', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'storePolicy'])->name('policies.store');
            Route::get('policies/{id}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'showPolicy'])->whereNumber('id')->name('policies.show');
            Route::put('policies/{id}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'updatePolicy'])->whereNumber('id')->name('policies.update');
            Route::delete('policies/{id}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'deletePolicy'])->whereNumber('id')->name('policies.destroy');

            Route::get('policy-types', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'policyTypes'])->name('policy-types');
            Route::get('tax-treatment/{type}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'taxTreatment'])->name('tax-treatment');

            Route::get('coverage-gap', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'coverageGap'])->name('coverage-gap');

            Route::get('beneficiaries/{policyId}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'listBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.index');
            Route::post('beneficiaries/{policyId}', [\Fynla\Packs\Za\Http\Controllers\ZaProtectionController::class, 'storeBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.store');
        });
    });
