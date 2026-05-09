<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Tax;

/**
 * UK Tax and National Insurance Calculator.
 *
 * Uses active tax year rates from TaxConfigService.
 *
 * R-14a-Tax-v: relocated from app/Services/ → packs/country-gb/src/Tax/.
 * Internal arithmetic is int-minor (pence). Public input signatures take pence
 * (`int $xMinor`) — the 14 caller sites convert at the boundary via
 * `(int) round($pounds * 100)`. Output array keys continue to expose
 * float-pounds values because the cross-pack consumers (ResolvesIncome trait,
 * UserProfileService detailed-tax view, CoverageGapAnalyzer, PersonalAccountsService)
 * read pounds today; the full pence-shape contract migration lands when those
 * peers migrate.
 */
class UKTaxCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate detailed net income with per-income-type breakdowns.
     * Uses stack-order allocation: employment uses PA first, other income taxed at remaining band position.
     *
     * @param  int  $employmentIncomeMinor  Employment income (PAYE), in pence
     * @param  int  $selfEmploymentIncomeMinor  Self-employment income, in pence
     * @param  int  $rentalIncomeMinor  Rental income (property), in pence
     * @param  int  $pensionIncomeMinor  Pension income (DB/state), in pence
     * @param  int  $trustIncomeMinor  Trust income (gross amount), in pence
     * @param  int  $interestIncomeMinor  Interest income (savings), in pence
     * @param  int  $dividendIncomeMinor  Dividend income, in pence
     * @param  string|null  $trustType  Type of trust: 'discretionary', 'interest_in_possession', 'bare', etc.
     * @param  int  $pensionContributionsMinor  Employee pension contributions (deducted before tax), in pence
     * @param  int  $section24CreditMinor  Section 24 tax credit, in pence
     * @return array Detailed breakdown per income type with tax bands and NI (output keys in float-pounds)
     */
    public function calculateDetailedNetIncome(
        int $employmentIncomeMinor = 0,
        int $selfEmploymentIncomeMinor = 0,
        int $rentalIncomeMinor = 0,
        int $pensionIncomeMinor = 0,
        int $trustIncomeMinor = 0,
        int $interestIncomeMinor = 0,
        int $dividendIncomeMinor = 0,
        ?string $trustType = null,
        int $pensionContributionsMinor = 0,
        int $section24CreditMinor = 0
    ): array {
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        // Apply Personal Allowance taper for incomes above £100,000
        // Must be done BEFORE creating TaxBandTracker so band thresholds are correct
        $totalIncomePreReliefMinor = $employmentIncomeMinor + $selfEmploymentIncomeMinor + $rentalIncomeMinor
            + $pensionIncomeMinor + $trustIncomeMinor + $interestIncomeMinor + $dividendIncomeMinor;
        $taxableIncomePreReliefMinor = $totalIncomePreReliefMinor - $pensionContributionsMinor;

        $taperThresholdMinor = self::poundsToMinor((float) ($incomeTaxConfig['personal_allowance_taper_threshold'] ?? 100000));
        $fullPAMinor = self::poundsToMinor((float) $incomeTaxConfig['personal_allowance']);
        if ($taxableIncomePreReliefMinor > $taperThresholdMinor) {
            $excessMinor = $taxableIncomePreReliefMinor - $taperThresholdMinor;
            // Round-down to whole pounds to mirror the legacy `floor($excess / 2)` rule on pounds.
            $reductionMinor = intdiv(intdiv($excessMinor, 2), 100) * 100;
            $taperedPAMinor = max(0, $fullPAMinor - $reductionMinor);
            $incomeTaxConfig['personal_allowance'] = self::minorToPounds($taperedPAMinor);
        }

        $tracker = new TaxBandTracker($incomeTaxConfig);

        $incomeBreakdowns = [];
        $totalGrossMinor = 0;
        $totalTaxMinor = 0;
        $totalNIMinor = 0;

        // Combined "Earned Income" card - employment, self-employment, rental, pension income
        // These all use the same tax bands (20%/40%/45%)
        $hasEarnedIncome = $employmentIncomeMinor > 0 || $selfEmploymentIncomeMinor > 0
            || $rentalIncomeMinor > 0 || $pensionIncomeMinor > 0;

        if ($hasEarnedIncome) {
            // Calculate taxable employment income (after pension contributions)
            $taxableEmploymentIncomeMinor = max(0, $employmentIncomeMinor - $pensionContributionsMinor);

            // Total taxable earned income for tax calculation
            $totalTaxableEarnedIncomeMinor = $taxableEmploymentIncomeMinor + $selfEmploymentIncomeMinor
                + $rentalIncomeMinor + $pensionIncomeMinor;

            // Calculate tax on combined earned income (TaxBandTracker takes pence)
            $taxAllocation = $tracker->allocateIncome($totalTaxableEarnedIncomeMinor);

            // Calculate NI separately for employment and self-employment
            $class1NI = $employmentIncomeMinor > 0 ? $this->calculateClass1NIDetailed($employmentIncomeMinor) : null;
            $class4NI = $selfEmploymentIncomeMinor > 0 ? $this->calculateClass4NIDetailed($selfEmploymentIncomeMinor) : null;

            $totalNIPounds = ($class1NI['total_ni'] ?? 0.0) + ($class4NI['total_ni'] ?? 0.0);
            $totalNIComponentMinor = self::poundsToMinor((float) $totalNIPounds);

            // Build income components for display (float-pounds for the frontend)
            $incomeComponents = [];

            if ($employmentIncomeMinor > 0) {
                $incomeComponents[] = [
                    'label' => 'Employment Income',
                    'amount' => round(self::minorToPounds($employmentIncomeMinor), 2),
                ];

                if ($pensionContributionsMinor > 0) {
                    $incomeComponents[] = [
                        'label' => 'Pension Contributions',
                        'amount' => round(-self::minorToPounds($pensionContributionsMinor), 2),
                        'is_deduction' => true,
                    ];
                }
            }

            if ($selfEmploymentIncomeMinor > 0) {
                $incomeComponents[] = [
                    'label' => 'Self-Employment Income',
                    'amount' => round(self::minorToPounds($selfEmploymentIncomeMinor), 2),
                ];
            }

            if ($rentalIncomeMinor > 0) {
                $incomeComponents[] = [
                    'label' => 'Rental Income',
                    'amount' => round(self::minorToPounds($rentalIncomeMinor), 2),
                ];
            }

            if ($pensionIncomeMinor > 0) {
                $incomeComponents[] = [
                    'label' => 'Pension Income',
                    'amount' => round(self::minorToPounds($pensionIncomeMinor), 2),
                ];
            }

            // Build NI breakdown combining both classes
            $niBreakdown = null;
            if ($class1NI || $class4NI) {
                $niBreakdown = [
                    'class_1' => $class1NI,
                    'class_4' => $class4NI,
                    'total_ni' => round((float) $totalNIPounds, 2),
                ];
            }

            // Gross earned income (before pension deduction for display)
            $grossEarnedIncomeMinor = $employmentIncomeMinor + $selfEmploymentIncomeMinor
                + $rentalIncomeMinor + $pensionIncomeMinor;
            $grossEarnedIncomePounds = self::minorToPounds($grossEarnedIncomeMinor);
            $totalEarnedIncomeTaxPounds = (float) ($taxAllocation['total_income_tax'] ?? 0.0);

            $incomeBreakdowns[] = [
                'income_type' => 'earned',
                'income_type_label' => 'Earned Income',
                'gross_amount' => round($grossEarnedIncomePounds, 2),
                'income_components' => $incomeComponents,
                'taxable_income' => round(self::minorToPounds($totalTaxableEarnedIncomeMinor), 2),
                'tax_breakdown' => $taxAllocation,
                'ni_breakdown' => $niBreakdown,
                'total_deductions' => round($totalEarnedIncomeTaxPounds + $totalNIPounds, 2),
                'net_income' => round($grossEarnedIncomePounds - $totalEarnedIncomeTaxPounds - $totalNIPounds, 2),
            ];

            $totalGrossMinor += $grossEarnedIncomeMinor;
            $totalTaxMinor += self::poundsToMinor($totalEarnedIncomeTaxPounds);
            $totalNIMinor += $totalNIComponentMinor;
        }

        // Interest income (uses same bands but has PSA - keep separate for clarity)
        if ($interestIncomeMinor > 0) {
            $interestBreakdown = $this->calculateInterestTaxDetailed($interestIncomeMinor, $tracker);
            $interestTaxPounds = (float) ($interestBreakdown['total_income_tax'] ?? 0.0);
            $interestIncomePounds = self::minorToPounds($interestIncomeMinor);

            $incomeBreakdowns[] = [
                'income_type' => 'interest',
                'income_type_label' => 'Interest Income',
                'gross_amount' => round($interestIncomePounds, 2),
                'tax_breakdown' => $interestBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($interestTaxPounds, 2),
                'net_income' => round($interestIncomePounds - $interestTaxPounds, 2),
            ];

            $totalGrossMinor += $interestIncomeMinor;
            $totalTaxMinor += self::poundsToMinor($interestTaxPounds);
        }

        // Dividend income (special rates: 8.75%/33.75%/39.35%)
        if ($dividendIncomeMinor > 0) {
            $dividendBreakdown = $this->calculateDividendTaxDetailed($dividendIncomeMinor, $tracker);
            $dividendTaxPounds = (float) ($dividendBreakdown['total_income_tax'] ?? 0.0);
            $dividendIncomePounds = self::minorToPounds($dividendIncomeMinor);

            $incomeBreakdowns[] = [
                'income_type' => 'dividend',
                'income_type_label' => 'Dividend Income',
                'gross_amount' => round($dividendIncomePounds, 2),
                'tax_breakdown' => $dividendBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($dividendTaxPounds, 2),
                'net_income' => round($dividendIncomePounds - $dividendTaxPounds, 2),
            ];

            $totalGrossMinor += $dividendIncomeMinor;
            $totalTaxMinor += self::poundsToMinor($dividendTaxPounds);
        }

        // Trust income (special taxation based on trust type)
        if ($trustIncomeMinor > 0) {
            $trustTaxBreakdown = $this->calculateTrustIncomeTax($trustIncomeMinor, $trustType, $tracker);
            $trustTaxPounds = (float) ($trustTaxBreakdown['total_income_tax'] ?? 0.0);
            $trustIncomePounds = self::minorToPounds($trustIncomeMinor);

            $incomeBreakdowns[] = [
                'income_type' => 'trust',
                'income_type_label' => 'Trust Income',
                'gross_amount' => round($trustIncomePounds, 2),
                'tax_breakdown' => $trustTaxBreakdown,
                'ni_breakdown' => null,
                'total_deductions' => round($trustTaxPounds, 2),
                'net_income' => round($trustIncomePounds - $trustTaxPounds, 2),
            ];

            $totalGrossMinor += $trustIncomeMinor;
            $totalTaxMinor += self::poundsToMinor($trustTaxPounds);
        }

        // Apply Section 24 tax credit (reduces tax bill, not income)
        $appliedSection24CreditMinor = min($section24CreditMinor, $totalTaxMinor);
        $totalTaxAfterCreditMinor = $totalTaxMinor - $appliedSection24CreditMinor;

        $totalDeductionsMinor = $totalTaxAfterCreditMinor + $totalNIMinor;
        $netIncomeMinor = $totalGrossMinor - $totalDeductionsMinor;

        $totalGrossPounds = self::minorToPounds($totalGrossMinor);
        $totalDeductionsPounds = self::minorToPounds($totalDeductionsMinor);
        $netIncomePounds = self::minorToPounds($netIncomeMinor);

        return [
            'income_breakdowns' => $incomeBreakdowns,
            'section_24' => $section24CreditMinor > 0 ? [
                'annual_credit' => round(self::minorToPounds($section24CreditMinor), 2),
                'applied_credit' => round(self::minorToPounds($appliedSection24CreditMinor), 2),
            ] : null,
            'summary' => [
                'total_gross_income' => round($totalGrossPounds, 2),
                'total_income_tax_before_credits' => round(self::minorToPounds($totalTaxMinor), 2),
                'section_24_credit' => round(self::minorToPounds($appliedSection24CreditMinor), 2),
                'total_income_tax' => round(self::minorToPounds($totalTaxAfterCreditMinor), 2),
                'total_national_insurance' => round(self::minorToPounds($totalNIMinor), 2),
                'total_deductions' => round($totalDeductionsPounds, 2),
                'net_income' => round($netIncomePounds, 2),
                'effective_tax_rate' => $totalGrossMinor > 0 ? round(($totalDeductionsMinor / $totalGrossMinor) * 100, 2) : 0,
                'monthly_net_income' => round($netIncomePounds / 12, 2),
            ],
            'tax_year' => $this->taxConfig->getTaxYear(),
        ];
    }

    /**
     * Calculate Class 1 NI with detailed breakdown.
     *
     * Output keys in float-pounds (frontend display contract).
     */
    private function calculateClass1NIDetailed(int $employmentIncomeMinor): array
    {
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class1Employee = $niConfig['class_1']['employee'];

        $primaryThresholdMinor = self::poundsToMinor((float) $class1Employee['primary_threshold']);
        $upperEarningsLimitMinor = self::poundsToMinor((float) $class1Employee['upper_earnings_limit']);
        $mainRate = (float) $class1Employee['main_rate'];
        $additionalRate = (float) $class1Employee['additional_rate'];

        $breakdown = [
            'class' => 'Class 1',
            'main_rate' => ['earnings' => 0.0, 'contribution' => 0.0, 'rate' => $mainRate],
            'additional_rate' => ['earnings' => 0.0, 'contribution' => 0.0, 'rate' => $additionalRate],
            'total_ni' => 0.0,
        ];

        if ($employmentIncomeMinor <= $primaryThresholdMinor) {
            return $breakdown;
        }

        $mainContributionMinor = 0;
        $additionalContributionMinor = 0;

        // Main rate: earnings between primary threshold and upper earnings limit
        if ($employmentIncomeMinor > $primaryThresholdMinor) {
            $mainRateEarningsMinor = min(
                $employmentIncomeMinor - $primaryThresholdMinor,
                $upperEarningsLimitMinor - $primaryThresholdMinor
            );
            $mainContributionMinor = (int) round($mainRateEarningsMinor * $mainRate);
            $breakdown['main_rate']['earnings'] = round(self::minorToPounds($mainRateEarningsMinor), 2);
            $breakdown['main_rate']['contribution'] = round(self::minorToPounds($mainContributionMinor), 2);
        }

        // Additional rate: earnings above upper earnings limit
        if ($employmentIncomeMinor > $upperEarningsLimitMinor) {
            $additionalRateEarningsMinor = $employmentIncomeMinor - $upperEarningsLimitMinor;
            $additionalContributionMinor = (int) round($additionalRateEarningsMinor * $additionalRate);
            $breakdown['additional_rate']['earnings'] = round(self::minorToPounds($additionalRateEarningsMinor), 2);
            $breakdown['additional_rate']['contribution'] = round(self::minorToPounds($additionalContributionMinor), 2);
        }

        $breakdown['total_ni'] = round(self::minorToPounds($mainContributionMinor + $additionalContributionMinor), 2);

        return $breakdown;
    }

    /**
     * Calculate Class 4 NI with detailed breakdown.
     *
     * Output keys in float-pounds (frontend display contract).
     */
    private function calculateClass4NIDetailed(int $selfEmploymentIncomeMinor): array
    {
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class4 = $niConfig['class_4'];

        $lowerProfitsLimitMinor = self::poundsToMinor((float) $class4['lower_profits_limit']);
        $upperProfitsLimitMinor = self::poundsToMinor((float) $class4['upper_profits_limit']);
        $mainRate = (float) $class4['main_rate'];
        $additionalRate = (float) $class4['additional_rate'];

        $breakdown = [
            'class' => 'Class 4',
            'main_rate' => ['earnings' => 0.0, 'contribution' => 0.0, 'rate' => $mainRate],
            'additional_rate' => ['earnings' => 0.0, 'contribution' => 0.0, 'rate' => $additionalRate],
            'total_ni' => 0.0,
        ];

        if ($selfEmploymentIncomeMinor <= $lowerProfitsLimitMinor) {
            return $breakdown;
        }

        $mainContributionMinor = 0;
        $additionalContributionMinor = 0;

        // Main rate
        if ($selfEmploymentIncomeMinor > $lowerProfitsLimitMinor) {
            $mainRateEarningsMinor = min(
                $selfEmploymentIncomeMinor - $lowerProfitsLimitMinor,
                $upperProfitsLimitMinor - $lowerProfitsLimitMinor
            );
            $mainContributionMinor = (int) round($mainRateEarningsMinor * $mainRate);
            $breakdown['main_rate']['earnings'] = round(self::minorToPounds($mainRateEarningsMinor), 2);
            $breakdown['main_rate']['contribution'] = round(self::minorToPounds($mainContributionMinor), 2);
        }

        // Additional rate
        if ($selfEmploymentIncomeMinor > $upperProfitsLimitMinor) {
            $additionalRateEarningsMinor = $selfEmploymentIncomeMinor - $upperProfitsLimitMinor;
            $additionalContributionMinor = (int) round($additionalRateEarningsMinor * $additionalRate);
            $breakdown['additional_rate']['earnings'] = round(self::minorToPounds($additionalRateEarningsMinor), 2);
            $breakdown['additional_rate']['contribution'] = round(self::minorToPounds($additionalContributionMinor), 2);
        }

        $breakdown['total_ni'] = round(self::minorToPounds($mainContributionMinor + $additionalContributionMinor), 2);

        return $breakdown;
    }

    /**
     * Calculate interest tax with PSA consideration.
     *
     * Output is the float-pounds-shaped TaxBandTracker::allocateIncome contract
     * with two extra keys (PSA + taxable_after_psa, in pounds).
     */
    private function calculateInterestTaxDetailed(int $interestIncomeMinor, TaxBandTracker $tracker): array
    {
        $bandPosition = $tracker->getCurrentBandPosition();

        // Determine PSA based on current band position (from TaxConfigService)
        $psaConfig = $this->taxConfig->getPersonalSavingsAllowance();
        $psaPounds = match ($bandPosition) {
            'personal_allowance', 'basic' => (int) ($psaConfig['basic'] ?? 1000),
            'higher' => (int) ($psaConfig['higher'] ?? 500),
            default => 0,
        };
        $psaMinor = self::poundsToMinor((float) $psaPounds);

        $taxableInterestMinor = max(0, $interestIncomeMinor - $psaMinor);
        $taxAllocation = $tracker->allocateIncome($taxableInterestMinor);

        // Add PSA info to breakdown (in pounds, matching the band-tracker output convention)
        $taxAllocation['personal_savings_allowance'] = $psaPounds;
        $taxAllocation['taxable_after_psa'] = self::minorToPounds($taxableInterestMinor);

        return $taxAllocation;
    }

    /**
     * Calculate dividend tax with allowance and special rates.
     *
     * Output keys in float-pounds (frontend display contract).
     */
    private function calculateDividendTaxDetailed(int $dividendIncomeMinor, TaxBandTracker $tracker): array
    {
        $dividendTax = $this->taxConfig->getDividendTax();

        $allowanceMinor = self::poundsToMinor((float) $dividendTax['allowance']);
        $basicRate = (float) $dividendTax['basic_rate'];
        $higherRate = (float) $dividendTax['higher_rate'];
        $additionalRate = (float) $dividendTax['additional_rate'];

        $taxableDividendsMinor = max(0, $dividendIncomeMinor - $allowanceMinor);

        $breakdown = [
            'dividend_allowance' => self::minorToPounds($allowanceMinor),
            'taxable_after_allowance' => self::minorToPounds($taxableDividendsMinor),
            'basic_rate' => ['taxable' => 0.0, 'tax' => 0.0, 'rate' => $basicRate],
            'higher_rate' => ['taxable' => 0.0, 'tax' => 0.0, 'rate' => $higherRate],
            'additional_rate' => ['taxable' => 0.0, 'tax' => 0.0, 'rate' => $additionalRate],
            'total_income_tax' => 0.0,
        ];

        if ($taxableDividendsMinor <= 0) {
            return $breakdown;
        }

        // Allocate dividends to remaining bands (band-tracker getters return pounds)
        $remainingMinor = $taxableDividendsMinor;
        $basicTaxMinor = 0;
        $higherTaxMinor = 0;
        $additionalTaxMinor = 0;

        // Basic rate band
        $basicAvailableMinor = self::poundsToMinor((float) $tracker->getRemainingBasicBand());
        if ($basicAvailableMinor > 0 && $remainingMinor > 0) {
            $basicUsedMinor = min($remainingMinor, $basicAvailableMinor);
            $basicTaxMinor = (int) round($basicUsedMinor * $basicRate);
            $breakdown['basic_rate']['taxable'] = self::minorToPounds($basicUsedMinor);
            $breakdown['basic_rate']['tax'] = round(self::minorToPounds($basicTaxMinor), 2);
            $remainingMinor -= $basicUsedMinor;
        }

        // Higher rate band
        $higherAvailableMinor = self::poundsToMinor((float) $tracker->getRemainingHigherBand());
        if ($higherAvailableMinor > 0 && $remainingMinor > 0) {
            $higherUsedMinor = min($remainingMinor, $higherAvailableMinor);
            $higherTaxMinor = (int) round($higherUsedMinor * $higherRate);
            $breakdown['higher_rate']['taxable'] = self::minorToPounds($higherUsedMinor);
            $breakdown['higher_rate']['tax'] = round(self::minorToPounds($higherTaxMinor), 2);
            $remainingMinor -= $higherUsedMinor;
        }

        // Additional rate
        if ($remainingMinor > 0) {
            $additionalTaxMinor = (int) round($remainingMinor * $additionalRate);
            $breakdown['additional_rate']['taxable'] = self::minorToPounds($remainingMinor);
            $breakdown['additional_rate']['tax'] = round(self::minorToPounds($additionalTaxMinor), 2);
        }

        $breakdown['total_income_tax'] = round(self::minorToPounds($basicTaxMinor + $higherTaxMinor + $additionalTaxMinor), 2);

        return $breakdown;
    }

    /**
     * Calculate trust income tax based on trust type.
     *
     * Trust taxation rules:
     * - Discretionary/Accumulation trusts: Trust pays 45% at source (39.35% for dividends)
     * - Interest in Possession trusts: Trust pays 20% at source (8.75% for dividends)
     * - Bare trusts: Beneficiary pays at their marginal rate (not handled here)
     *
     * For most trusts, the TRUST pays tax at source and the beneficiary receives
     * income net of this tax. The beneficiary may be able to reclaim tax if their
     * marginal rate is lower than the trust rate.
     *
     * Output keys in float-pounds (frontend display contract).
     */
    private function calculateTrustIncomeTax(int $trustIncomeMinor, ?string $trustType, TaxBandTracker $tracker): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();

        // Default to discretionary rate from config; fall back to additional income rate band
        $incomeTaxBands = $this->taxConfig->getIncomeTax();
        $additionalRateFallback = (float) ($incomeTaxBands['bands'][2]['rate'] ?? 0.45);
        $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
        $trustTypeLabel = 'Discretionary Trust';
        $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';

        $basicRateFallback = (float) ($incomeTaxBands['bands'][0]['rate'] ?? 0.20);

        switch ($trustType) {
            case 'discretionary':
            case 'accumulation_maintenance':
                $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
                $trustTypeLabel = $trustType === 'discretionary' ? 'Discretionary Trust' : 'Accumulation & Maintenance Trust';
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
                break;

            case 'interest_in_possession':
                $taxRate = (float) ($trustsConfig['income_tax']['interest_in_possession']['standard_rate'] ?? $basicRateFallback);
                $trustTypeLabel = 'Interest in Possession Trust';
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
                break;

            case 'bare':
                // Bare trusts - beneficiary pays at their marginal rate
                $taxRate = 0;
                $trustTypeLabel = 'Bare Trust';
                $taxDescription = 'Taxed as beneficiary\'s income';
                break;

            case 'settlor_interested':
                // Settlor-interested trusts - settlor pays at their marginal rate
                $taxRate = 0;
                $trustTypeLabel = 'Settlor-Interested Trust';
                $taxDescription = 'Taxed as settlor\'s income';
                break;

            case 'life_insurance':
            case 'loan':
            case 'discounted_gift':
                // These don't typically generate regular income
                $taxRate = 0;
                $trustTypeLabel = ucwords(str_replace('_', ' ', $trustType ?? 'Trust'));
                $taxDescription = 'No regular income tax applies';
                break;

            default:
                // Default to discretionary rates for unknown types
                $taxRate = (float) ($trustsConfig['income_tax']['discretionary']['standard_rate'] ?? $additionalRateFallback);
                $taxDescription = 'Tax paid by trust at '.number_format($taxRate * 100, 0).'%';
        }

        $taxPaidByTrustMinor = (int) round($trustIncomeMinor * $taxRate);
        $taxPaidByTrustPounds = round(self::minorToPounds($taxPaidByTrustMinor), 2);

        // Calculate personalized reclaim based on beneficiary's marginal rate
        $beneficiaryMarginalRate = $this->getBeneficiaryMarginalRate($tracker);
        $beneficiaryMarginalRateLabel = $this->getMarginalRateLabel($beneficiaryMarginalRate);
        $taxAtMarginalRateMinor = (int) round($trustIncomeMinor * $beneficiaryMarginalRate);
        $taxAtMarginalRatePounds = round(self::minorToPounds($taxAtMarginalRateMinor), 2);

        $reclaimInfo = null;
        if ($taxRate > 0) {
            $differenceMinor = $taxPaidByTrustMinor - $taxAtMarginalRateMinor;
            $differencePounds = self::minorToPounds($differenceMinor);
            if ($differenceMinor > 0) {
                // Can reclaim
                $reclaimInfo = [
                    'type' => 'reclaim',
                    'amount' => round($differencePounds, 2),
                    'message' => 'You can reclaim £'.number_format($differencePounds, 0)." as you are a {$beneficiaryMarginalRateLabel} taxpayer (".round($beneficiaryMarginalRate * 100).'%) but the trust paid '.round($taxRate * 100).'% tax.',
                ];
            } elseif ($differenceMinor < 0) {
                // Owes additional tax
                $absPounds = abs($differencePounds);
                $reclaimInfo = [
                    'type' => 'owe',
                    'amount' => round($absPounds, 2),
                    'message' => 'You owe an additional £'.number_format($absPounds, 0)." as you are a {$beneficiaryMarginalRateLabel} taxpayer (".round($beneficiaryMarginalRate * 100).'%) but the trust only paid '.round($taxRate * 100).'% tax.',
                ];
            } else {
                // No difference
                $reclaimInfo = [
                    'type' => 'none',
                    'amount' => 0,
                    'message' => "No additional tax due - trust rate matches your {$beneficiaryMarginalRateLabel} rate.",
                ];
            }
        }

        return [
            'trust_type' => $trustType,
            'trust_type_label' => $trustTypeLabel,
            'tax_rate' => $taxRate,
            'tax_description' => $taxDescription,
            'tax_paid_by_trust' => $taxPaidByTrustPounds,
            'total_income_tax' => $taxPaidByTrustPounds,
            'net_to_beneficiary' => round(self::minorToPounds($trustIncomeMinor - $taxPaidByTrustMinor), 2),
            'beneficiary_marginal_rate' => $beneficiaryMarginalRate,
            'beneficiary_marginal_rate_label' => $beneficiaryMarginalRateLabel,
            'tax_at_marginal_rate' => $taxAtMarginalRatePounds,
            'reclaim_info' => $reclaimInfo,
        ];
    }

    /**
     * Get the beneficiary's marginal tax rate based on current band position
     */
    private function getBeneficiaryMarginalRate(TaxBandTracker $tracker): float
    {
        $bandPosition = $tracker->getCurrentBandPosition();

        return match ($bandPosition) {
            'personal_allowance' => 0.0,
            'basic' => 0.20,
            'higher' => 0.40,
            'additional' => 0.45,
            default => 0.20,
        };
    }

    /**
     * Get a human-readable label for the marginal rate
     */
    private function getMarginalRateLabel(float $rate): string
    {
        return match (true) {
            $rate === 0.0 => 'non',
            $rate <= 0.20 => 'basic rate',
            $rate <= 0.40 => 'higher rate',
            default => 'additional rate',
        };
    }

    /**
     * Calculate net income after income tax and National Insurance.
     *
     * @param  int  $employmentIncomeMinor  Employment income (PAYE), in pence
     * @param  int  $selfEmploymentIncomeMinor  Self-employment income, in pence
     * @param  int  $rentalIncomeMinor  Rental income (property), in pence
     * @param  int  $dividendIncomeMinor  Dividend income, in pence
     * @param  int  $interestIncomeMinor  Interest income (savings), in pence
     * @param  int  $otherIncomeMinor  Other taxable income, in pence
     * @return array Net income breakdown with tax and NI details (output keys in float-pounds)
     */
    public function calculateNetIncome(
        int $employmentIncomeMinor = 0,
        int $selfEmploymentIncomeMinor = 0,
        int $rentalIncomeMinor = 0,
        int $dividendIncomeMinor = 0,
        int $interestIncomeMinor = 0,
        int $otherIncomeMinor = 0
    ): array {
        $grossIncomeMinor = $employmentIncomeMinor + $selfEmploymentIncomeMinor + $rentalIncomeMinor
            + $dividendIncomeMinor + $interestIncomeMinor + $otherIncomeMinor;

        // Calculate Income Tax (including interest and dividend income)
        $nonDividendNonInterestIncomeMinor = $employmentIncomeMinor + $selfEmploymentIncomeMinor
            + $rentalIncomeMinor + $otherIncomeMinor;
        $incomeTaxMinor = $this->calculateIncomeTax($nonDividendNonInterestIncomeMinor, $interestIncomeMinor, $dividendIncomeMinor);

        // Calculate National Insurance
        $class1NIMinor = $this->calculateClass1NI($employmentIncomeMinor); // Employees
        $class4NIMinor = $this->calculateClass4NI($selfEmploymentIncomeMinor); // Self-employed
        $totalNIMinor = $class1NIMinor + $class4NIMinor;

        $totalDeductionsMinor = $incomeTaxMinor + $totalNIMinor;
        $netIncomeMinor = $grossIncomeMinor - $totalDeductionsMinor;

        return [
            'gross_income' => round(self::minorToPounds($grossIncomeMinor), 2),
            'income_tax' => round(self::minorToPounds($incomeTaxMinor), 2),
            'national_insurance' => round(self::minorToPounds($totalNIMinor), 2),
            'total_deductions' => round(self::minorToPounds($totalDeductionsMinor), 2),
            'net_income' => round(self::minorToPounds($netIncomeMinor), 2),
            'effective_tax_rate' => $grossIncomeMinor > 0 ? round(($totalDeductionsMinor / $grossIncomeMinor) * 100, 2) : 0,
            'breakdown' => [
                'employment_income' => round(self::minorToPounds($employmentIncomeMinor), 2),
                'self_employment_income' => round(self::minorToPounds($selfEmploymentIncomeMinor), 2),
                'rental_income' => round(self::minorToPounds($rentalIncomeMinor), 2),
                'dividend_income' => round(self::minorToPounds($dividendIncomeMinor), 2),
                'interest_income' => round(self::minorToPounds($interestIncomeMinor), 2),
                'other_income' => round(self::minorToPounds($otherIncomeMinor), 2),
                'class_1_ni' => round(self::minorToPounds($class1NIMinor), 2),
                'class_4_ni' => round(self::minorToPounds($class4NIMinor), 2),
            ],
        ];
    }

    /**
     * Calculate UK Income Tax using active tax year rates from TaxConfigService.
     * Supports:
     * - Income tax bands (basic, higher, additional)
     * - Personal allowance
     * - Personal Savings Allowance (£1,000 basic rate, £500 higher rate, £0 additional rate)
     * - Dividend allowance and dividend-specific rates
     *
     * @return int Total tax due, in pence
     */
    private function calculateIncomeTax(int $nonDividendNonInterestIncomeMinor, int $interestIncomeMinor, int $dividendIncomeMinor): int
    {
        // Get tax configuration from service
        $incomeTax = $this->taxConfig->getIncomeTax();
        $dividendTax = $this->taxConfig->getDividendTax();

        $personalAllowanceMinor = self::poundsToMinor((float) $incomeTax['personal_allowance']);
        $dividendAllowanceMinor = self::poundsToMinor((float) $dividendTax['allowance']);

        // Apply Personal Allowance taper for incomes above £100,000
        $totalIncomePreMinor = $nonDividendNonInterestIncomeMinor + $interestIncomeMinor + $dividendIncomeMinor;
        $taperThresholdMinor = self::poundsToMinor((float) ($incomeTax['personal_allowance_taper_threshold'] ?? 100000));
        if ($totalIncomePreMinor > $taperThresholdMinor) {
            $excessMinor = $totalIncomePreMinor - $taperThresholdMinor;
            // Round-down to whole pounds to mirror the legacy `floor($excess / 2)` rule on pounds.
            $reductionMinor = intdiv(intdiv($excessMinor, 2), 100) * 100;
            $personalAllowanceMinor = max(0, $personalAllowanceMinor - $reductionMinor);
        }

        // Get income tax bands (stored as array in seeder)
        $bands = $incomeTax['bands'];

        // Calculate absolute thresholds (in pence)
        // Basic rate band ends at personal_allowance + band max
        $basicRateLimitMinor = $personalAllowanceMinor + self::poundsToMinor((float) $bands[0]['max']); // £12,570 + £37,700 = £50,270
        // Higher rate band ends at personal_allowance + band max
        $higherRateLimitMinor = $personalAllowanceMinor + self::poundsToMinor((float) $bands[1]['max']); // £12,570 + £150,000 = £162,570 (for historical)

        // Tax rates are stored as decimals (0.20 for 20%)
        $basicRate = (float) $bands[0]['rate'];
        $higherRate = (float) $bands[1]['rate'];
        $additionalRate = (float) $bands[2]['rate'];

        // Dividend tax rates (stored as decimals)
        $basicDividendRate = (float) $dividendTax['basic_rate'];           // 0.0875 (8.75%)
        $higherDividendRate = (float) $dividendTax['higher_rate'];         // 0.3375 (33.75%)
        $additionalDividendRate = (float) $dividendTax['additional_rate']; // 0.3935 (39.35%)

        $taxMinor = 0;

        // Total income to determine tax bands
        $totalIncomeMinor = $nonDividendNonInterestIncomeMinor + $interestIncomeMinor + $dividendIncomeMinor;

        // Step 1: Calculate tax on non-dividend, non-interest income (employment, self-employment, rental, other)
        if ($nonDividendNonInterestIncomeMinor > $personalAllowanceMinor) {
            $taxableIncomeMinor = $nonDividendNonInterestIncomeMinor - $personalAllowanceMinor;

            // Basic rate
            if ($taxableIncomeMinor > 0) {
                $basicRateTaxableMinor = min($taxableIncomeMinor, $basicRateLimitMinor - $personalAllowanceMinor);
                $taxMinor += (int) round($basicRateTaxableMinor * $basicRate);
            }

            // Higher rate
            if ($taxableIncomeMinor > ($basicRateLimitMinor - $personalAllowanceMinor)) {
                $higherRateTaxableMinor = min(
                    $taxableIncomeMinor - ($basicRateLimitMinor - $personalAllowanceMinor),
                    $higherRateLimitMinor - $basicRateLimitMinor
                );
                $taxMinor += (int) round($higherRateTaxableMinor * $higherRate);
            }

            // Additional rate
            if ($taxableIncomeMinor > ($higherRateLimitMinor - $personalAllowanceMinor)) {
                $additionalRateTaxableMinor = $taxableIncomeMinor - ($higherRateLimitMinor - $personalAllowanceMinor);
                $taxMinor += (int) round($additionalRateTaxableMinor * $additionalRate);
            }
        }

        // Step 2: Calculate tax on interest income with Personal Savings Allowance
        // PSA rates sourced from TaxConfigService (basic: £1,000, higher: £500, additional: £0)
        if ($interestIncomeMinor > 0) {
            // Determine PSA based on total income band
            $psaBand = match (true) {
                $totalIncomeMinor <= $basicRateLimitMinor => 'basic',
                $totalIncomeMinor <= $higherRateLimitMinor => 'higher',
                default => 'additional',
            };
            $personalSavingsAllowanceMinor = self::poundsToMinor((float) $this->taxConfig->getPersonalSavingsAllowance($psaBand));

            $taxableInterestMinor = max(0, $interestIncomeMinor - $personalSavingsAllowanceMinor);

            if ($taxableInterestMinor > 0) {
                // Interest is taxed at standard income tax rates based on total income
                $incomeBeforeInterestMinor = $nonDividendNonInterestIncomeMinor;

                // Tax interest at appropriate rate(s)
                if ($incomeBeforeInterestMinor + $taxableInterestMinor <= $basicRateLimitMinor) {
                    // All interest in basic rate band
                    $taxMinor += (int) round($taxableInterestMinor * $basicRate);
                } elseif ($incomeBeforeInterestMinor >= $basicRateLimitMinor && $incomeBeforeInterestMinor + $taxableInterestMinor <= $higherRateLimitMinor) {
                    // All interest in higher rate band
                    $taxMinor += (int) round($taxableInterestMinor * $higherRate);
                } elseif ($incomeBeforeInterestMinor >= $higherRateLimitMinor) {
                    // All interest in additional rate band
                    $taxMinor += (int) round($taxableInterestMinor * $additionalRate);
                } else {
                    // Interest spans multiple bands
                    $remainingMinor = $taxableInterestMinor;

                    // Basic rate portion
                    if ($incomeBeforeInterestMinor < $basicRateLimitMinor) {
                        $basicPortionMinor = min($remainingMinor, $basicRateLimitMinor - $incomeBeforeInterestMinor);
                        $taxMinor += (int) round($basicPortionMinor * $basicRate);
                        $remainingMinor -= $basicPortionMinor;
                        $incomeBeforeInterestMinor += $basicPortionMinor;
                    }

                    // Higher rate portion
                    if ($remainingMinor > 0 && $incomeBeforeInterestMinor < $higherRateLimitMinor) {
                        $higherPortionMinor = min($remainingMinor, $higherRateLimitMinor - $incomeBeforeInterestMinor);
                        $taxMinor += (int) round($higherPortionMinor * $higherRate);
                        $remainingMinor -= $higherPortionMinor;
                        $incomeBeforeInterestMinor += $higherPortionMinor;
                    }

                    // Additional rate portion
                    if ($remainingMinor > 0) {
                        $taxMinor += (int) round($remainingMinor * $additionalRate);
                    }
                }
            }
        }

        // Step 3: Calculate dividend tax
        if ($dividendIncomeMinor > $dividendAllowanceMinor) {
            $taxableDividendsMinor = $dividendIncomeMinor - $dividendAllowanceMinor;
            $incomeBeforeDividendsMinor = $nonDividendNonInterestIncomeMinor + $interestIncomeMinor;

            // Determine dividend tax rate based on total income band
            if ($totalIncomeMinor <= $basicRateLimitMinor) {
                // Basic rate dividend tax
                $taxMinor += (int) round($taxableDividendsMinor * $basicDividendRate);
            } elseif ($totalIncomeMinor <= $higherRateLimitMinor) {
                // Dividends may span basic and higher rate
                $basicRateDividendsMinor = max(0, $basicRateLimitMinor - $incomeBeforeDividendsMinor);
                $higherRateDividendsMinor = $taxableDividendsMinor - $basicRateDividendsMinor;

                $taxMinor += (int) round($basicRateDividendsMinor * $basicDividendRate);
                $taxMinor += (int) round(max(0, $higherRateDividendsMinor) * $higherDividendRate);
            } else {
                // Dividends may span all three bands
                $basicRateDividendsMinor = max(0, $basicRateLimitMinor - $incomeBeforeDividendsMinor);
                $higherRateDividendsMinor = max(0, min($taxableDividendsMinor - $basicRateDividendsMinor, $higherRateLimitMinor - max($incomeBeforeDividendsMinor, $basicRateLimitMinor)));
                $additionalRateDividendsMinor = $taxableDividendsMinor - $basicRateDividendsMinor - $higherRateDividendsMinor;

                $taxMinor += (int) round($basicRateDividendsMinor * $basicDividendRate);
                $taxMinor += (int) round($higherRateDividendsMinor * $higherDividendRate);
                $taxMinor += (int) round(max(0, $additionalRateDividendsMinor) * $additionalDividendRate);
            }
        }

        return $taxMinor;
    }

    /**
     * Calculate Class 1 National Insurance (Employees).
     * Uses active tax year rates from TaxConfigService.
     *
     * @return int NI due, in pence
     */
    private function calculateClass1NI(int $employmentIncomeMinor): int
    {
        // Get National Insurance configuration
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class1Employee = $niConfig['class_1']['employee'];

        $primaryThresholdMinor = self::poundsToMinor((float) $class1Employee['primary_threshold']);
        $upperEarningsLimitMinor = self::poundsToMinor((float) $class1Employee['upper_earnings_limit']);
        $mainRate = (float) $class1Employee['main_rate'];
        $additionalRate = (float) $class1Employee['additional_rate'];

        if ($employmentIncomeMinor <= $primaryThresholdMinor) {
            return 0;
        }

        $niMinor = 0;

        // Main rate
        if ($employmentIncomeMinor > $primaryThresholdMinor) {
            $mainRateEarningsMinor = min(
                $employmentIncomeMinor - $primaryThresholdMinor,
                $upperEarningsLimitMinor - $primaryThresholdMinor
            );
            $niMinor += (int) round($mainRateEarningsMinor * $mainRate);
        }

        // Additional rate
        if ($employmentIncomeMinor > $upperEarningsLimitMinor) {
            $additionalRateEarningsMinor = $employmentIncomeMinor - $upperEarningsLimitMinor;
            $niMinor += (int) round($additionalRateEarningsMinor * $additionalRate);
        }

        return $niMinor;
    }

    /**
     * Calculate Class 4 National Insurance (Self-Employed).
     * Uses active tax year rates from TaxConfigService.
     *
     * @return int NI due, in pence
     */
    private function calculateClass4NI(int $selfEmploymentIncomeMinor): int
    {
        // Get National Insurance configuration
        $niConfig = $this->taxConfig->getNationalInsurance();
        $class4 = $niConfig['class_4'];

        $lowerProfitsLimitMinor = self::poundsToMinor((float) $class4['lower_profits_limit']);
        $upperProfitsLimitMinor = self::poundsToMinor((float) $class4['upper_profits_limit']);
        $mainRate = (float) $class4['main_rate'];
        $additionalRate = (float) $class4['additional_rate'];

        if ($selfEmploymentIncomeMinor <= $lowerProfitsLimitMinor) {
            return 0;
        }

        $niMinor = 0;

        // Main rate
        if ($selfEmploymentIncomeMinor > $lowerProfitsLimitMinor) {
            $mainRateEarningsMinor = min(
                $selfEmploymentIncomeMinor - $lowerProfitsLimitMinor,
                $upperProfitsLimitMinor - $lowerProfitsLimitMinor
            );
            $niMinor += (int) round($mainRateEarningsMinor * $mainRate);
        }

        // Additional rate
        if ($selfEmploymentIncomeMinor > $upperProfitsLimitMinor) {
            $additionalRateEarningsMinor = $selfEmploymentIncomeMinor - $upperProfitsLimitMinor;
            $niMinor += (int) round($additionalRateEarningsMinor * $additionalRate);
        }

        return $niMinor;
    }

    private static function poundsToMinor(float $pounds): int
    {
        return (int) round($pounds * 100);
    }

    private static function minorToPounds(int $minor): float
    {
        return $minor / 100;
    }
}
