<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Protection\CoverageGapRequest;
use App\Http\Requests\Za\Protection\StoreZaBeneficiariesRequest;
use App\Http\Requests\Za\Protection\StoreZaProtectionPolicyRequest;
use App\Http\Requests\Za\Protection\UpdateZaProtectionPolicyRequest;
use App\Http\Resources\Za\Protection\ZaCoverageGapResource;
use App\Http\Resources\Za\Protection\ZaProtectionBeneficiaryResource;
use App\Http\Resources\Za\Protection\ZaProtectionPolicyResource;
use App\Models\FamilyMember;
use App\Models\Mortgage;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA protection domain (WS 1.5b).
 *
 * Thin proxy: every method either resolves a pack binding and delegates,
 * or performs straight CRUD over pack models. No business logic here.
 */
class ZaProtectionController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->get();

        $byType = $policies->groupBy('product_type')->map(fn ($group) => [
            'count' => $group->count(),
            'total_cover_minor' => (int) $group->sum('cover_amount_minor'),
            'total_premium_minor' => (int) $group->sum(fn ($p) => $this->monthlyPremium($p)),
        ])->all();

        $totalMonthly = $policies->sum(fn ($p) => $this->monthlyPremium($p));

        return response()->json([
            'success' => true,
            'message' => 'Protection dashboard payload.',
            'data' => [
                'policy_count' => $policies->count(),
                'total_monthly_premium_minor' => (int) $totalMonthly,
                'total_monthly_premium_major' => round($totalMonthly / 100, 2),
                'policies_by_type' => $byType,
            ],
        ]);
    }

    public function listPolicies(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->with('beneficiaries')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->orderBy('product_type')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ZaProtectionPolicyResource::collection($policies),
        ]);
    }

    public function storePolicy(StoreZaProtectionPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $beneficiaries = $data['beneficiaries'] ?? [];
        unset($data['beneficiaries']);
        $data['user_id'] = $request->user()->id;

        $policy = DB::transaction(function () use ($data, $beneficiaries) {
            $policy = ZaProtectionPolicy::create($data);
            foreach ($beneficiaries as $b) {
                $b['policy_id'] = $policy->id;
                ZaProtectionBeneficiary::create($b);
            }
            return $policy->load('beneficiaries');
        });

        return response()->json([
            'success' => true,
            'message' => 'Policy created.',
            'data' => new ZaProtectionPolicyResource($policy),
        ], Response::HTTP_CREATED);
    }

    public function showPolicy(Request $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);

        return response()->json([
            'success' => true,
            'data' => new ZaProtectionPolicyResource($policy->load('beneficiaries')),
        ]);
    }

    public function updatePolicy(UpdateZaProtectionPolicyRequest $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);
        $policy->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Policy updated.',
            'data' => new ZaProtectionPolicyResource($policy->fresh('beneficiaries')),
        ]);
    }

    public function deletePolicy(Request $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Policy deleted.',
        ]);
    }

    public function policyTypes(): JsonResponse
    {
        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');

        return response()->json([
            'success' => true,
            'data' => $engine->getAvailablePolicyTypes(),
        ]);
    }

    public function taxTreatment(string $type): JsonResponse
    {
        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');

        try {
            $data = $engine->getPolicyTaxTreatment($type);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function coverageGap(CoverageGapRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->get()
            ->map(fn ($p) => [
                'product_type' => $p->product_type,
                'cover_amount_minor' => (int) $p->cover_amount_minor,
            ])
            ->all();

        // Annual income from User columns (app/Traits/ResolvesIncome.php pattern).
        // Engine expects MINOR units (R-cents), so multiply by 100.
        $user = $request->user();
        $annualIncomeMajor = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
        $annualIncome = (int) round($annualIncomeMajor * 100);

        $outstandingDebts = $this->outstandingDebts($userId);
        $dependants = FamilyMember::query()
            ->where('user_id', $userId)
            ->where('is_dependent', true)
            ->count();

        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');
        $gap = $engine->calculateAggregateCoverageGap($policies, [
            'annual_income' => $annualIncome,
            'outstanding_debts' => $outstandingDebts,
            'dependants' => $dependants,
        ]);

        return response()->json([
            'success' => true,
            'data' => (new ZaCoverageGapResource($gap))->toArray($request),
            'meta' => [
                'inputs' => [
                    'annual_income' => $annualIncome,
                    'outstanding_debts' => $outstandingDebts,
                    'dependants' => $dependants,
                ],
            ],
        ]);
    }

    public function listBeneficiaries(Request $request, int $policyId): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $policyId);

        return response()->json([
            'success' => true,
            'data' => ZaProtectionBeneficiaryResource::collection($policy->beneficiaries),
        ]);
    }

    public function storeBeneficiaries(StoreZaBeneficiariesRequest $request, int $policyId): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $policyId);

        $beneficiaries = $request->validated()['beneficiaries'];

        DB::transaction(function () use ($policy, $beneficiaries) {
            $policy->beneficiaries()->delete();
            foreach ($beneficiaries as $b) {
                $b['policy_id'] = $policy->id;
                ZaProtectionBeneficiary::create($b);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Beneficiaries replaced.',
            'data' => ZaProtectionBeneficiaryResource::collection($policy->fresh('beneficiaries')->beneficiaries),
        ]);
    }

    private function findUserPolicy(Request $request, int $id): ZaProtectionPolicy
    {
        $userId = $request->user()->id;

        $policy = ZaProtectionPolicy::query()
            ->where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->first();

        if (! $policy) {
            abort(Response::HTTP_NOT_FOUND, 'Policy not found.');
        }

        return $policy;
    }

    private function monthlyPremium(ZaProtectionPolicy $p): int
    {
        return match ($p->premium_frequency) {
            'monthly' => (int) $p->premium_amount_minor,
            'quarterly' => (int) round($p->premium_amount_minor / 3),
            'annual' => (int) round($p->premium_amount_minor / 12),
            default => 0,
        };
    }

    private function outstandingDebts(int $userId): int
    {
        // Sum outstanding mortgage balances for the user via Eloquent
        // (architecture test blocks DB facade in controllers). The
        // `mortgages.outstanding_balance` column is decimal(15,2) in
        // MAJOR units; engine expects MINOR units, so convert.
        $mortgagesMajor = (float) Mortgage::query()
            ->where('user_id', $userId)
            ->sum('outstanding_balance');

        return (int) round($mortgagesMajor * 100);
    }
}
