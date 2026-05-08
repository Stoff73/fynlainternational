<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Estate;

use Fynla\Packs\Gb\Constants\TaxDefaults;
use Fynla\Packs\Gb\Models\ActuarialLifeTable;
use Fynla\Packs\Gb\Models\Estate\Trust;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Trust analysis service for IHT planning.
 *
 * Money values are passed and returned in minor units (pence) per ADR-005.
 * The service reads its config and DB-backed money fields from `TaxConfigService`
 * and `Trust` model attributes (which still expose pounds) and converts at
 * the read site via `poundsToMinor`.
 */
class TrustService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    public function calculateNextPeriodicChargeDate(Trust $trust): ?Carbon
    {
        if (! $trust->isRelevantPropertyTrust()) {
            return null;
        }

        $creationDate = Carbon::parse($trust->trust_creation_date);
        $lastChargeDate = $trust->last_periodic_charge_date
            ? Carbon::parse($trust->last_periodic_charge_date)
            : null;

        if ($lastChargeDate) {
            return $lastChargeDate->copy()->addYears(10);
        }

        return $creationDate->copy()->addYears(10);
    }

    /**
     * Calculate periodic charge for a relevant property trust (10-year anniversary).
     * Charge is up to 6% of trust value, based on cumulative transfers.
     */
    public function calculatePeriodicCharge(Trust $trust): array
    {
        if (! $trust->isRelevantPropertyTrust()) {
            return [
                'charge_amount_minor' => 0,
                'effective_rate' => 0,
                'next_charge_date' => null,
            ];
        }

        $trustsConfig = $this->taxConfig->getTrusts();
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrbMinor = self::poundsToMinor($ihtConfig['nil_rate_band']);

        $maxRate = (float) ($trustsConfig['periodic_charges']['max_rate'] ?? 0.06);

        $trustValueMinor = self::poundsToMinor($trust->current_value);
        $excessOverNrbMinor = max(0, $trustValueMinor - $nrbMinor);

        $effectiveRate = $trustValueMinor > 0
            ? min($maxRate, ($excessOverNrbMinor / $trustValueMinor) * 0.06)
            : 0.0;

        $chargeAmountMinor = (int) round($trustValueMinor * $effectiveRate);

        return [
            'charge_amount_minor' => $chargeAmountMinor,
            'effective_rate' => round($effectiveRate, 4),
            'trust_value_minor' => $trustValueMinor,
            'nrb_minor' => $nrbMinor,
            'excess_over_nrb_minor' => $excessOverNrbMinor,
            'next_charge_date' => $this->calculateNextPeriodicChargeDate($trust)?->format('Y-m-d'),
        ];
    }

    /**
     * Calculate the IHT value of all trusts for a user. The returned amount
     * is in pence and should be included in estate value calculations.
     */
    public function calculateTotalIHTValue(Collection $trusts): int
    {
        return $trusts->reduce(
            fn (int $carry, $trust) => $carry + self::poundsToMinor($trust->getIHTValue()),
            0
        );
    }

    /**
     * Analyze trust efficiency for IHT planning.
     */
    public function analyzeTrustEfficiency(Trust $trust): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypeConfig = $trustsConfig['types'][$trust->trust_type] ?? null;

        $valueInEstateMinor = self::poundsToMinor($trust->getIHTValue());
        $currentValueMinor = self::poundsToMinor($trust->current_value);
        $initialValueMinor = self::poundsToMinor($trust->initial_value);
        $valueOutsideEstateMinor = $currentValueMinor - $valueInEstateMinor;
        $efficiencyPercent = $currentValueMinor > 0
            ? ($valueOutsideEstateMinor / $currentValueMinor) * 100
            : 0;

        $growthMinor = $currentValueMinor - $initialValueMinor;
        $growthRate = $initialValueMinor > 0
            ? (($currentValueMinor - $initialValueMinor) / $initialValueMinor) * 100
            : 0;

        $yearsActive = Carbon::parse($trust->trust_creation_date)->diffInYears(Carbon::now());

        $taxRates = $this->getTrustTaxRates($trust->trust_type);

        return [
            'trust_id' => $trust->id,
            'trust_name' => $trust->trust_name,
            'trust_type' => $trust->trust_type,
            'trust_type_name' => $trustTypeConfig['name'] ?? ucwords(str_replace('_', ' ', $trust->trust_type)),
            'trust_type_description' => $trustTypeConfig['description'] ?? null,
            'initial_value_minor' => $initialValueMinor,
            'current_value_minor' => $currentValueMinor,
            'growth_minor' => $growthMinor,
            'growth_rate_percent' => round($growthRate, 2),
            'value_in_estate_minor' => $valueInEstateMinor,
            'value_outside_estate_minor' => $valueOutsideEstateMinor,
            'iht_efficiency_percent' => round($efficiencyPercent, 2),
            'years_active' => $yearsActive,
            'is_relevant_property_trust' => $trust->isRelevantPropertyTrust(),
            'tax_rates' => $taxRates,
            'key_features' => $trustTypeConfig['key_features'] ?? [],
            'suitable_for' => $trustTypeConfig['suitable_for'] ?? [],
            'periodic_charge_info' => $trust->isRelevantPropertyTrust()
                ? $this->calculatePeriodicCharge($trust)
                : null,
        ];
    }

    /**
     * Get income and CGT tax rates for a specific trust type. Money-named
     * fields (`*_exempt_amount`, `tax_free_allowance`) are returned in pence.
     */
    public function getTrustTaxRates(string $trustType): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypeConfig = $trustsConfig['types'][$trustType] ?? null;

        $discretionaryConfig = $trustsConfig['income_tax']['discretionary'] ?? [];
        $incomeTaxRates = [
            'standard_rate' => (float) ($discretionaryConfig['standard_rate'] ?? $this->taxConfig->getIncomeTax()['additional_rate'] ?? 0.45),
            'dividend_rate' => (float) ($discretionaryConfig['dividend_rate'] ?? TaxDefaults::DIVIDEND_ADDITIONAL_RATE),
        ];

        $incomeTaxTreatment = $trustTypeConfig['income_tax_treatment'] ?? 'trust_discretionary';

        if ($incomeTaxTreatment === 'trust_iip' || $trustType === 'interest_in_possession') {
            $basicRate = (float) ($this->taxConfig->getIncomeTax()['bands'][0]['rate'] ?? 0.20);
            $incomeTaxRates = $trustsConfig['income_tax']['interest_in_possession'] ?? [
                'standard_rate' => $basicRate,
                'dividend_rate' => 0.0875,
            ];
        } elseif ($incomeTaxTreatment === 'beneficiary' || $trustType === 'bare') {
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'Taxed as beneficiary\'s income using their personal rates',
            ];
        } elseif ($incomeTaxTreatment === 'settlor' || $trustType === 'settlor_interested') {
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'Taxed as settlor\'s income using their personal rates',
            ];
        } elseif ($incomeTaxTreatment === 'none' || $trustType === 'life_insurance') {
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'No regular income - policy proceeds on death',
            ];
        } else {
            $incomeTaxRates = $trustsConfig['income_tax']['discretionary'] ?? [
                'standard_rate' => $this->taxConfig->getIncomeTax()['additional_rate'] ?? 0.45,
                'dividend_rate' => TaxDefaults::DIVIDEND_ADDITIONAL_RATE,
            ];
        }

        $cgtConfig = $trustsConfig['capital_gains_tax'] ?? [];
        $cgtTreatment = $trustTypeConfig['cgt_treatment'] ?? 'trust';

        $cgtRates = match ($cgtTreatment) {
            'beneficiary' => [
                'rate' => null,
                'annual_exempt_amount_minor' => null,
                'note' => 'Uses beneficiary\'s CGT allowance and rates',
            ],
            'settlor' => [
                'rate' => null,
                'annual_exempt_amount_minor' => null,
                'note' => 'Uses settlor\'s CGT allowance and rates',
            ],
            'none' => [
                'rate' => null,
                'annual_exempt_amount_minor' => null,
                'note' => 'No CGT on life policy proceeds',
            ],
            default => [
                'rate' => $cgtConfig['rate'] ?? 0.24,
                'annual_exempt_amount_minor' => self::poundsToMinor($cgtConfig['annual_exempt_amount'] ?? 1500),
                'vulnerable_beneficiary_exempt_amount_minor' => self::poundsToMinor($cgtConfig['vulnerable_beneficiary_exempt_amount'] ?? 3000),
            ],
        };

        return [
            'income_tax' => $incomeTaxRates,
            'capital_gains_tax' => $cgtRates,
            'tax_free_allowance_minor' => self::poundsToMinor($trustsConfig['income_tax']['tax_free_allowance'] ?? 500),
            'iht_treatment' => $trustTypeConfig['iht_treatment'] ?? 'unknown',
        ];
    }

    /**
     * Get trust recommendations based on user's estate and circumstances.
     */
    public function getTrustRecommendations(int $estateValueMinor, int $ihtLiabilityMinor, array $circumstances = []): array
    {
        $recommendations = [];
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypes = $trustsConfig['types'] ?? [];

        $defaultDescriptions = [
            'life_insurance' => 'Life insurance policy written in trust to pay IHT liability',
            'discounted_gift' => 'Gift assets to trust while retaining income rights',
            'loan' => 'Loan assets to trust, freezing estate value',
            'bare' => 'Simple trust giving child absolute entitlement at age 18',
            'discretionary' => 'Flexible trust allowing trustees to decide distributions',
        ];

        // £50,000 in pence
        if ($ihtLiabilityMinor > 5_000_000) {
            $ihtLiabilityPounds = intdiv($ihtLiabilityMinor, 100);
            $recommendations[] = [
                'trust_type' => 'life_insurance',
                'priority' => 'high',
                'reason' => 'Cover IHT liability of £'.number_format($ihtLiabilityPounds, 0).' without depleting estate assets',
                'description' => $trustTypes['life_insurance']['description'] ?? $defaultDescriptions['life_insurance'],
                'benefits' => [
                    'Policy proceeds paid outside estate',
                    'Provides liquid funds to pay IHT',
                    'Beneficiaries inherit estate intact',
                ],
            ];

            // £1,000,000 in pence
            if ($estateValueMinor > 100_000_000) {
                $recommendations[] = [
                    'trust_type' => 'discounted_gift',
                    'priority' => 'high',
                    'reason' => 'Reduce estate value while retaining income',
                    'description' => $trustTypes['discounted_gift']['description'] ?? $defaultDescriptions['discounted_gift'],
                    'benefits' => [
                        'Immediate IHT reduction (30-60% discount typical)',
                        'Retain regular income stream',
                        'Growth outside estate from day one',
                    ],
                ];
            }

            $recommendations[] = [
                'trust_type' => 'loan',
                'priority' => 'medium',
                'reason' => 'Freeze estate value while maintaining access',
                'description' => $trustTypes['loan']['description'] ?? $defaultDescriptions['loan'],
                'benefits' => [
                    'No 7-year wait for original loan',
                    'Growth accrues outside estate immediately',
                    'Can repay loan if need capital',
                ],
            ];
        }

        if ($circumstances['has_children'] ?? false) {
            $recommendations[] = [
                'trust_type' => 'bare',
                'priority' => 'medium',
                'reason' => 'Pass assets to children with certainty',
                'description' => $trustTypes['bare']['description'] ?? $defaultDescriptions['bare'],
                'benefits' => [
                    'Simple and low-cost',
                    'PET treatment (7-year rule)',
                    'Child entitled at age 18',
                ],
            ];
        }

        if ($circumstances['needs_flexibility'] ?? false) {
            $recommendations[] = [
                'trust_type' => 'discretionary',
                'priority' => 'medium',
                'reason' => 'Maximum flexibility for trustees',
                'description' => $trustTypes['discretionary']['description'] ?? $defaultDescriptions['discretionary'],
                'benefits' => [
                    'Trustees decide distributions',
                    'Protect vulnerable beneficiaries',
                    'Assets outside settlor estate',
                ],
                'drawbacks' => [
                    'Periodic charges every 10 years (up to 6%)',
                    'Entry charge (20% over NRB)',
                    'Exit charges on distributions',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate discounted gift trust discount based on age, income, and gender.
     * Uses actuarial tables when gender is provided for more accurate estimates.
     */
    public function estimateDiscountedGiftDiscount(int $age, int $giftValueMinor, int $annualIncomeMinor, ?string $gender = null): array
    {
        if ($gender) {
            $actuarialExpectancy = ActuarialLifeTable::where('gender', $gender)
                ->where('age', '<=', $age)
                ->where('table_year', '2020-2022')
                ->orderBy('age', 'desc')
                ->value('life_expectancy_years');

            $lifeExpectancy = $actuarialExpectancy
                ? max(5, (int) ceil((float) $actuarialExpectancy))
                : max(5, 90 - $age);
        } else {
            $lifeExpectancy = max(5, 90 - $age);
        }

        $totalExpectedIncomeMinor = $annualIncomeMinor * $lifeExpectancy;

        // Discount is the lesser of (60% of gift value) or (80% of total expected income).
        $discountMinor = (int) round((float) min(
            $giftValueMinor * 0.60,
            $totalExpectedIncomeMinor * 0.8
        ));

        $giftedValueMinor = $giftValueMinor - $discountMinor;
        $discountPercent = $giftValueMinor > 0
            ? ($discountMinor / $giftValueMinor) * 100
            : 0;

        return [
            'gift_value_minor' => $giftValueMinor,
            'discount_amount_minor' => $discountMinor,
            'discount_percent' => round($discountPercent, 2),
            'gifted_value_minor' => $giftedValueMinor,
            'retained_value_minor' => $discountMinor,
            'annual_income_minor' => $annualIncomeMinor,
            'estimated_life_expectancy' => $lifeExpectancy,
        ];
    }

    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        if ($pounds === null) {
            return 0;
        }

        return (int) round((float) $pounds * 100);
    }
}
