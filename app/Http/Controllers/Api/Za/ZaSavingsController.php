<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Savings\EmergencyFundAssessmentRequest;
use App\Http\Requests\Za\Savings\StoreTfsaContributionRequest;
use App\Http\Requests\Za\Savings\StoreZaSavingsAccountRequest;
use App\Http\Resources\Za\TfsaContributionResource;
use App\Http\Resources\Za\ZaSavingsAccountResource;
use App\Models\SavingsAccount;
use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Core\Money\Currency;
use Fynla\Core\Money\Money;
use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HTTP adapter over the ZA pack's savings domain (WS 1.2b).
 *
 * Thin proxy: every method resolves a pack.za.* container binding and
 * delegates. No business logic here. The pack owns the calculations;
 * the app owns HTTP + auth + validation.
 *
 * Internal arithmetic in dashboard() uses Money VO per ADR-005; wire
 * format stays integer minor units (ADR-005 doesn't require JSON
 * exposure of the VO).
 */
class ZaSavingsController extends Controller
{
    public function __construct(
        private readonly ZaTfsaContributionTracker $tfsaTracker,
        private readonly ZaEmergencyFundCalculator $emergencyFund,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        /** @var SavingsEngine $engine */
        $engine = app('pack.za.savings');

        $zar = Currency::ZAR();
        $annualCap = new Money($engine->getAnnualContributionCap($taxYear), $zar);
        $lifetimeCap = new Money($engine->getLifetimeContributionCap($taxYear) ?? 0, $zar);
        $annualUsed = new Money($this->tfsaTracker->sumForTaxYear($user->id, null, $taxYear), $zar);
        $lifetimeUsed = new Money($this->tfsaTracker->sumLifetime($user->id, null), $zar);

        $annualRemaining = $annualCap->minus($annualUsed);
        if ($annualRemaining->isNegative()) {
            $annualRemaining = new Money(0, $zar);
        }
        $lifetimeRemaining = $lifetimeCap->minus($lifetimeUsed);
        if ($lifetimeRemaining->isNegative()) {
            $lifetimeRemaining = new Money(0, $zar);
        }

        $contributions = ZaTfsaContribution::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->orderByDesc('contribution_date')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'tax_year' => $taxYear,
                'tfsa' => [
                    'annual_cap_minor' => $annualCap->minor,
                    'lifetime_cap_minor' => $lifetimeCap->minor,
                    'annual_used_minor' => $annualUsed->minor,
                    'lifetime_used_minor' => $lifetimeUsed->minor,
                    'annual_remaining_minor' => $annualRemaining->minor,
                    'lifetime_remaining_minor' => $lifetimeRemaining->minor,
                ],
                'contributions' => TfsaContributionResource::collection($contributions),
            ],
        ]);
    }

    public function listContributions(Request $request): JsonResponse
    {
        $user = $request->user();
        $taxYear = (string) $request->query('tax_year', $this->currentZaTaxYear());

        $contributions = ZaTfsaContribution::query()
            ->where('user_id', $user->id)
            ->where('tax_year', $taxYear)
            ->orderByDesc('contribution_date')
            ->get();

        return response()->json([
            'data' => TfsaContributionResource::collection($contributions),
        ]);
    }

    public function storeContribution(StoreTfsaContributionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $taxYear = $data['tax_year'];
        $beneficiaryId = $data['beneficiary_id'] ?? null;

        /** @var SavingsEngine $engine */
        $engine = app('pack.za.savings');

        $annualPrior = $this->tfsaTracker->sumForTaxYear($user->id, $beneficiaryId, $taxYear);
        $lifetimePrior = $this->tfsaTracker->sumLifetime($user->id, $beneficiaryId);

        $penaltyAssessment = $engine->calculateTaxFreeWrapperPenalty(
            contributionMinor: $data['amount_minor'],
            annualPriorMinor: $annualPrior,
            lifetimePriorMinor: $lifetimePrior,
            taxYear: $taxYear,
        );

        $id = $this->tfsaTracker->record(
            userId: $user->id,
            beneficiaryId: $beneficiaryId,
            savingsAccountId: $data['savings_account_id'] ?? null,
            taxYear: $taxYear,
            amountMinor: $data['amount_minor'],
            contributionDate: $data['contribution_date'],
            sourceType: $data['source_type'] ?? 'contribution',
            notes: $data['notes'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $id,
                'tax_year' => $taxYear,
                'amount_minor' => $data['amount_minor'],
                'penalty_minor' => $penaltyAssessment['penalty_minor'],
                'excess_minor' => $penaltyAssessment['excess_minor'],
                'breached_cap' => $penaltyAssessment['breached_cap'],
                'annual_remaining_minor' => $penaltyAssessment['annual_remaining_minor'],
                'lifetime_remaining_minor' => $penaltyAssessment['lifetime_remaining_minor'],
            ],
        ], 201);
    }

    public function assessEmergencyFund(EmergencyFundAssessmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $assessment = $this->emergencyFund->assess(
            currentBalanceMinor: $data['current_balance_minor'],
            essentialMonthlyExpenditureMinor: $data['essential_monthly_expenditure_minor'],
            incomeStability: $data['income_stability'],
            householdIncomeEarners: $data['household_income_earners'],
            uifEligible: $data['uif_eligible'],
        );

        return response()->json(['data' => $assessment]);
    }

    public function listAccounts(Request $request): JsonResponse
    {
        $user = $request->user();

        $accounts = SavingsAccount::query()
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->orderBy('institution')
            ->get();

        return response()->json([
            'data' => ZaSavingsAccountResource::collection($accounts),
        ]);
    }

    public function storeAccount(StoreZaSavingsAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['country_code'] = 'ZA';
        $data['ownership_type'] = $data['ownership_type'] ?? 'individual';
        $data['currency'] = 'ZAR';

        // Note: joint_owner_id deliberately not accepted. ZA savings are
        // individual-only in v1; SA family/spouse model ships in WS 1.7.
        $account = SavingsAccount::create($data);

        return response()->json([
            'data' => new ZaSavingsAccountResource($account),
        ], 201);
    }

    /**
     * SA tax year runs 1 March to 28/29 February. Label format '2026/27'.
     */
    private function currentZaTaxYear(): string
    {
        $now = now();
        $startYear = $now->month >= 3 ? $now->year : $now->year - 1;

        return sprintf('%d/%02d', $startYear, ($startYear + 1) % 100);
    }
}
