<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

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
