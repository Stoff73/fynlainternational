<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Savings;

use Fynla\Packs\Za\Models\ZaTfsaContribution;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Thin persistence for TFSA contribution events.
 *
 * Mirrors the pattern of ZaSection11fTracker / ZaSection10cTracker —
 * append-only, no business rules. Caps / penalty logic lives in
 * ZaSavingsEngine.
 *
 * The beneficiary_id parameter disambiguates adult self-owned TFSAs
 * (beneficiary_id = null) from minor TFSAs (beneficiary_id = family
 * member id). Both use the same adult's user_id for authority, but caps
 * are tracked independently against each (user_id, beneficiary_id) pair.
 */
class ZaTfsaContributionTracker
{
    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    public function record(
        int $userId,
        ?int $beneficiaryId,
        ?int $savingsAccountId,
        string $taxYear,
        int $amountMinor,
        string $contributionDate,
        string $sourceType = 'contribution',
        ?string $notes = null,
    ): int {
        $row = ZaTfsaContribution::create([
            'user_id' => $userId,
            'beneficiary_id' => $beneficiaryId,
            'savings_account_id' => $savingsAccountId,
            'tax_year' => $taxYear,
            'amount_minor' => $amountMinor,
            'amount_ccy' => 'ZAR',
            'source_type' => $sourceType,
            'contribution_date' => $contributionDate,
            'notes' => $notes,
        ]);

        return (int) $row->id;
    }

    public function sumForTaxYear(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        return (int) $this->scopeBeneficiary(
            ZaTfsaContribution::query()
                ->where('user_id', $userId)
                ->where('tax_year', $taxYear),
            $beneficiaryId,
        )->sum('amount_minor');
    }

    public function sumLifetime(int $userId, ?int $beneficiaryId): int
    {
        return (int) $this->scopeBeneficiary(
            ZaTfsaContribution::query()->where('user_id', $userId),
            $beneficiaryId,
        )->sum('amount_minor');
    }

    public function remainingAnnualAllowance(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.annual_limit_minor', 0);

        return max(0, $cap - $this->sumForTaxYear($userId, $beneficiaryId, $taxYear));
    }

    public function remainingLifetimeAllowance(int $userId, ?int $beneficiaryId, string $taxYear): int
    {
        $cap = (int) $this->config->get($taxYear, 'tfsa.lifetime_limit_minor', 0);

        return max(0, $cap - $this->sumLifetime($userId, $beneficiaryId));
    }

    private function scopeBeneficiary(Builder $query, ?int $beneficiaryId): Builder
    {
        return $beneficiaryId === null
            ? $query->whereNull('beneficiary_id')
            : $query->where('beneficiary_id', $beneficiaryId);
    }
}
