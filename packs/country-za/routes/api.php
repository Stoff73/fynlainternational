<?php

declare(strict_types=1);

use Fynla\Packs\Za\Http\Controllers\ZaEstateController;
use Fynla\Packs\Za\Http\Controllers\ZaExchangeControlController;
use Fynla\Packs\Za\Http\Controllers\ZaInvestmentController;
use Fynla\Packs\Za\Http\Controllers\ZaProtectionController;
use Fynla\Packs\Za\Http\Controllers\ZaRetirementController;
use Fynla\Packs\Za\Http\Controllers\ZaSavingsController;
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
            Route::get('dashboard', [ZaSavingsController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('contributions', [ZaSavingsController::class, 'listContributions'])
                ->name('contributions.index');
            Route::post('contributions', [ZaSavingsController::class, 'storeContribution'])
                ->name('contributions.store');
            Route::post('emergency-fund/assess', [ZaSavingsController::class, 'assessEmergencyFund'])
                ->name('emergency-fund.assess');
            Route::get('accounts', [ZaSavingsController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [ZaSavingsController::class, 'storeAccount'])
                ->name('accounts.store');
        });

        // WS 1.3c — Investment
        Route::prefix('investments')->as('investments.')->group(function () {
            Route::get('dashboard', [ZaInvestmentController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('accounts', [ZaInvestmentController::class, 'listAccounts'])
                ->name('accounts.index');
            Route::post('accounts', [ZaInvestmentController::class, 'storeAccount'])
                ->name('accounts.store');
            Route::get('holdings', [ZaInvestmentController::class, 'listHoldings'])
                ->name('holdings.index');
            Route::get('holdings/{holdingId}/lots', [ZaInvestmentController::class, 'listLots'])
                ->whereNumber('holdingId')
                ->name('holdings.lots');
            Route::post('holdings/purchase', [ZaInvestmentController::class, 'storePurchase'])
                ->name('holdings.purchase');
            Route::post('holdings/disposal', [ZaInvestmentController::class, 'recordDisposal'])
                ->name('holdings.disposal');
            Route::post('cgt/calculate', [ZaInvestmentController::class, 'calculateCgt'])
                ->name('cgt.calculate');
        });

        // WS 1.3c — Exchange Control
        Route::prefix('exchange-control')->as('exchange-control.')->group(function () {
            Route::get('dashboard', [ZaExchangeControlController::class, 'dashboard'])
                ->name('dashboard');
            Route::get('transfers', [ZaExchangeControlController::class, 'listTransfers'])
                ->name('transfers.index');
            Route::post('transfers', [ZaExchangeControlController::class, 'storeTransfer'])
                ->name('transfers.store');
            Route::post('check-approval', [ZaExchangeControlController::class, 'checkApproval'])
                ->name('check-approval');
        });

        // WS 1.4d — Retirement
        Route::prefix('retirement')->as('retirement.')->group(function () {
            Route::get('dashboard', [ZaRetirementController::class, 'dashboard'])->name('dashboard');

            Route::get('funds', [ZaRetirementController::class, 'listFunds'])->name('funds.index');
            Route::post('funds', [ZaRetirementController::class, 'storeFund'])->name('funds.store');
            Route::get('funds/{fundId}/buckets', [ZaRetirementController::class, 'showBuckets'])->name('funds.buckets');

            Route::post('contributions', [ZaRetirementController::class, 'storeContribution'])->name('contributions.store');

            Route::post('savings-pot/simulate', [ZaRetirementController::class, 'simulateSavingsPotWithdrawal'])->name('savings-pot.simulate');
            Route::post('savings-pot/withdraw', [ZaRetirementController::class, 'withdrawSavingsPot'])->name('savings-pot.withdraw');

            Route::post('tax-relief/calculate', [ZaRetirementController::class, 'calculateTaxRelief'])->name('tax-relief.calculate');

            Route::prefix('annuities')->as('annuities.')->group(function () {
                Route::post('living/quote', [ZaRetirementController::class, 'quoteLivingAnnuity'])->name('living.quote');
                Route::post('life/quote', [ZaRetirementController::class, 'quoteLifeAnnuity'])->name('life.quote');
                Route::post('compulsory-apportion', [ZaRetirementController::class, 'apportionCompulsory'])->name('compulsory-apportion');
            });

            Route::prefix('reg28')->as('reg28.')->group(function () {
                Route::match(['get', 'post'], 'check', [ZaRetirementController::class, 'checkReg28'])->name('check');
                Route::get('snapshots', [ZaRetirementController::class, 'listReg28Snapshots'])->name('snapshots.index');
                Route::post('snapshots', [ZaRetirementController::class, 'storeReg28Snapshot'])->name('snapshots.store');
            });
        });

        // WS 1.5b — Protection
        Route::prefix('protection')->as('protection.')->group(function () {
            Route::get('dashboard', [ZaProtectionController::class, 'dashboard'])->name('dashboard');

            Route::get('policies', [ZaProtectionController::class, 'listPolicies'])->name('policies.index');
            Route::post('policies', [ZaProtectionController::class, 'storePolicy'])->name('policies.store');
            Route::get('policies/{id}', [ZaProtectionController::class, 'showPolicy'])->whereNumber('id')->name('policies.show');
            Route::put('policies/{id}', [ZaProtectionController::class, 'updatePolicy'])->whereNumber('id')->name('policies.update');
            Route::delete('policies/{id}', [ZaProtectionController::class, 'deletePolicy'])->whereNumber('id')->name('policies.destroy');

            Route::get('policy-types', [ZaProtectionController::class, 'policyTypes'])->name('policy-types');
            Route::get('tax-treatment/{type}', [ZaProtectionController::class, 'taxTreatment'])->name('tax-treatment');

            Route::get('coverage-gap', [ZaProtectionController::class, 'coverageGap'])->name('coverage-gap');

            Route::get('beneficiaries/{policyId}', [ZaProtectionController::class, 'listBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.index');
            Route::post('beneficiaries/{policyId}', [ZaProtectionController::class, 'storeBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.store');
        });

        // SA Estate v1 slice 1 — estate-duty / CGT-on-death / exemptions.
        Route::prefix('estate')->as('estate.')->group(function () {
            Route::post('summary', [ZaEstateController::class, 'summary'])->name('summary');
            Route::get('exemptions', [ZaEstateController::class, 'exemptions'])->name('exemptions');
            Route::post('cgt-on-death', [ZaEstateController::class, 'cgtOnDeath'])->name('cgt-on-death');

            // Donations register (slice 2) — feeds SARS donations tax.
            Route::get('donations', [ZaEstateController::class, 'donations'])->name('donations.index');
            Route::post('donations', [ZaEstateController::class, 'storeDonation'])->name('donations.store');
            Route::delete('donations/{id}', [ZaEstateController::class, 'deleteDonation'])->whereNumber('id')->name('donations.destroy');
            Route::get('donations-tax', [ZaEstateController::class, 'donationsTax'])->name('donations-tax');
        });
    });
