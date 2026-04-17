<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TaxConfiguration;
use Fynla\Core\Contracts\TaxEngine;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Tax Configuration Service
 *
 * Provides centralized access to the active UK tax configuration.
 * Request-scoped singleton - loads active config once per request and caches in memory.
 *
 * Implements the Fynla\Core\Contracts\TaxEngine contract. The UK-specific lookup
 * methods (getIncomeTax(), getInheritanceTax(), etc.) are the primary UK surface;
 * the TaxEngine contract methods at the bottom of this class provide the
 * cross-jurisdiction surface the PackRegistry routes through.
 *
 * SA-only contract methods (calculateLumpSumTax, calculateRetirementDeduction,
 * calculateDividendsWithholdingTax, calculateMedicalCredits) are deliberate stubs
 * here — the UK tax system has no equivalent of SARS lump-sum tables, Section 11F
 * carry-forward, local vs foreign dividend withholding, or medical scheme tax
 * credits. Stubs return 0 / ['not_applicable' => true] so the contract is satisfied
 * without side effects.
 *
 * Usage:
 *   $taxConfig = app(TaxConfigService::class);
 *   $personalAllowance = $taxConfig->get('income_tax.personal_allowance');
 *   $incomeTax = $taxConfig->getIncomeTax();
 */
class TaxConfigService implements TaxEngine
{
    /**
     * Cached active tax configuration (request-scoped)
     */
    private ?array $config = null;

    /**
     * Active tax configuration model
     */
    private ?TaxConfiguration $taxConfigModel = null;

    /**
     * Get the full active tax configuration
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getAll(): array
    {
        return $this->loadActiveConfig();
    }

    /**
     * Get a specific tax configuration value using dot notation
     *
     * @param  string  $key  Dot notation key (e.g., 'income_tax.personal_allowance')
     * @param  mixed  $default  Default value if key doesn't exist
     *
     * @throws RuntimeException if no active tax year found
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $config = $this->loadActiveConfig();

        return Arr::get($config, $key, $default);
    }

    /**
     * Check if a configuration key exists
     *
     * @param  string  $key  Dot notation key
     */
    public function has(string $key): bool
    {
        $config = $this->loadActiveConfig();

        return Arr::has($config, $key);
    }

    /**
     * Get the active tax year string
     *
     * @return string e.g., '2025/26'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getTaxYear(): string
    {
        return $this->get('tax_year', '');
    }

    /**
     * Get the effective from date
     *
     * @return string e.g., '2025-04-06'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getEffectiveFrom(): string
    {
        return $this->get('effective_from', '');
    }

    /**
     * Get the effective to date
     *
     * @return string e.g., '2026-04-05'
     *
     * @throws RuntimeException if no active tax year found
     */
    public function getEffectiveTo(): string
    {
        return $this->get('effective_to', '');
    }

    /**
     * Check if a date falls within the current tax year
     *
     * @param  \Carbon\Carbon|string  $date
     */
    public function isInCurrentTaxYear($date): bool
    {
        $effectiveFrom = $this->getEffectiveFrom();
        $effectiveTo = $this->getEffectiveTo();

        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->isBetween($effectiveFrom, $effectiveTo, true);
    }

    // =========================================================================
    // Module-Specific Helper Methods
    // =========================================================================

    /**
     * Get Income Tax configuration
     *
     * @return array Contains personal_allowance, bands, etc.
     */
    public function getIncomeTax(): array
    {
        return $this->get('income_tax', []);
    }

    /**
     * Get National Insurance configuration
     *
     * @return array Contains class_1, class_2, class_4 rates
     */
    public function getNationalInsurance(): array
    {
        return $this->get('national_insurance', []);
    }

    /**
     * Get ISA allowances configuration
     *
     * @return array Contains annual_allowance, lifetime_isa, junior_isa
     */
    public function getISAAllowances(): array
    {
        return $this->get('isa', []);
    }

    /**
     * Get Pension allowances configuration
     *
     * @return array Contains annual_allowance, MPAA, tapered_allowance, state_pension
     */
    public function getPensionAllowances(): array
    {
        return $this->get('pension', []);
    }

    /**
     * Get Inheritance Tax configuration
     *
     * @return array Contains NRB, RNRB, rates, PETs, CLTs
     */
    public function getInheritanceTax(): array
    {
        return $this->get('inheritance_tax', []);
    }

    /**
     * Get Capital Gains Tax configuration
     *
     * @return array Contains annual_exempt_amount, rates
     */
    public function getCapitalGainsTax(): array
    {
        return $this->get('capital_gains_tax', []);
    }

    /**
     * Get Dividend Tax configuration
     *
     * @return array Contains allowance, rates
     */
    public function getDividendTax(): array
    {
        return $this->get('dividend_tax', []);
    }

    /**
     * Get Stamp Duty Land Tax configuration
     *
     * @return array Contains residential and non_residential bands
     */
    public function getStampDuty(): array
    {
        return $this->get('stamp_duty', []);
    }

    /**
     * Get Gifting Exemptions configuration
     *
     * @return array Contains annual_exemption, small_gifts, wedding_gifts, etc.
     */
    public function getGiftingExemptions(): array
    {
        return $this->get('gifting_exemptions', []);
    }

    /**
     * Get Trusts configuration
     *
     * @return array Contains entry_charge, exit_charge, periodic_charge
     */
    public function getTrusts(): array
    {
        return $this->get('trusts', []);
    }

    /**
     * Get PET (Potentially Exempt Transfer) rules
     *
     * @return array Contains years_to_exemption, taper_relief, failed_pet_rules
     */
    public function getPETRules(): array
    {
        return $this->get('inheritance_tax.potentially_exempt_transfers', []);
    }

    /**
     * Get CLT (Chargeable Lifetime Transfer) rules
     *
     * @return array Contains lookback_period, lifetime_rate, death_rate, taper_relief
     */
    public function getCLTRules(): array
    {
        return $this->get('inheritance_tax.chargeable_lifetime_transfers', []);
    }

    /**
     * Get the 14-year rule configuration
     *
     * @return array Contains lookback periods and calculation steps
     */
    public function getFourteenYearRule(): array
    {
        return $this->get('inheritance_tax.fourteen_year_rule', []);
    }

    /**
     * Get Trust IHT charges configuration
     *
     * @return array Contains entry, periodic, and exit charge rules
     */
    public function getTrustCharges(): array
    {
        return $this->get('inheritance_tax.trust_charges', []);
    }

    /**
     * Get taper relief rates for PETs/CLTs
     *
     * @param  string  $type  'pet' or 'clt'
     * @return array Taper relief schedule
     */
    public function getTaperRelief(string $type = 'pet'): array
    {
        if ($type === 'clt') {
            return $this->get('inheritance_tax.chargeable_lifetime_transfers.taper_relief', []);
        }

        return $this->get('inheritance_tax.potentially_exempt_transfers.taper_relief', []);
    }

    /**
     * Get the tax rate for a gift based on years survived
     *
     * @param  int|float  $yearsSurvived  Years between gift and death
     * @param  string  $type  'pet' or 'clt'
     * @return float Tax rate (0.0 to 0.40)
     */
    public function getGiftTaxRate(int|float $yearsSurvived, string $type = 'pet'): float
    {
        $taperRelief = $this->getTaperRelief($type);

        foreach ($taperRelief as $band) {
            $minYears = $band['min_years'] ?? 0;
            $maxYears = $band['max_years'] ?? PHP_INT_MAX;

            if ($yearsSurvived >= $minYears && $yearsSurvived < $maxYears) {
                return $band['tax_rate'] ?? 0.40;
            }
        }

        // Default to full rate if no band matches
        return $this->get('inheritance_tax.standard_rate', 0.40);
    }

    /**
     * Get Business Relief configuration
     *
     * @return array Contains rates, min_ownership_years, excluded_businesses
     */
    public function getBusinessRelief(): array
    {
        return $this->get('inheritance_tax.business_relief', []);
    }

    /**
     * Get Agricultural Relief configuration
     *
     * @return array Contains rates, ownership requirements, caps
     */
    public function getAgriculturalRelief(): array
    {
        return $this->get('inheritance_tax.agricultural_relief', []);
    }

    /**
     * Get Quick Succession Relief configuration
     *
     * @return array Contains relief rates by years
     */
    public function getQuickSuccessionRelief(): array
    {
        return $this->get('inheritance_tax.quick_succession_relief', []);
    }

    /**
     * Get Normal Expenditure from Income exemption rules
     *
     * @return array Contains conditions and evidence requirements
     */
    public function getNormalExpenditureFromIncome(): array
    {
        return $this->get('gifting_exemptions.normal_expenditure_from_income', []);
    }

    /**
     * Get Investment/Financial Planning Assumptions
     *
     * @return array Contains investment_growth, inflation, salary_growth, growth_by_risk
     */
    public function getAssumptions(): array
    {
        return $this->get('assumptions', []);
    }

    /**
     * Get Personal Savings Allowance by tax band
     *
     * @param  string|null  $taxBand  'basic', 'higher', or 'additional'. Null returns all bands.
     * @return int|array Returns the PSA amount for the band, or all bands if null
     */
    public function getPersonalSavingsAllowance(?string $taxBand = null): int|array
    {
        $psa = $this->get('income_tax.personal_savings_allowance', [
            'basic' => 1000,
            'higher' => 500,
            'additional' => 0,
        ]);

        if ($taxBand === null) {
            return $psa;
        }

        return $psa[$taxBand] ?? 0;
    }

    /**
     * Get the Blind Person's Allowance for the active tax year.
     */
    public function getBlindPersonsAllowance(): float
    {
        return (float) ($this->get('income_tax.blind_persons_allowance') ?? 2870);
    }

    /**
     * Get Savings-specific configuration (FSCS, Premium Bonds, etc.)
     *
     * @param  string|null  $key  Optional dot-notation sub-key (e.g., 'fscs_deposit_protection')
     * @return mixed Full savings config array, or specific value if key provided
     */
    public function getSavingsConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('savings', []);
        }

        return $this->get("savings.{$key}");
    }

    /**
     * Get Protection module configuration
     *
     * @return array Contains income_multipliers, affordability, premium_factors, etc.
     */
    public function getProtectionConfig(): array
    {
        return $this->get('protection', []);
    }

    /**
     * Get Retirement module configuration
     *
     * @return array Contains withdrawal_rates, target_income_percent, annuity_rate_estimates, etc.
     */
    public function getRetirementConfig(): array
    {
        return $this->get('retirement', []);
    }

    /**
     * Get Investment module configuration
     *
     * @return array Contains fee_benchmarks, waterfall limits, venture_capital, safety thresholds
     */
    public function getInvestmentConfig(): array
    {
        return $this->get('investment', []);
    }

    /**
     * Get Estate planning configuration
     *
     * @return array Contains onboarding_estimates, insurance_premium_estimates
     */
    public function getEstateConfig(): array
    {
        return $this->get('estate', []);
    }

    /**
     * Get Benefits configuration (SSP, ESA, UC, PIP, bereavement)
     *
     * @param  string|null  $key  Optional sub-key (e.g., 'ssp', 'universal_credit')
     * @return mixed Full benefits config or specific benefit section
     */
    public function getBenefits(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->get('benefits', []);
        }

        return $this->get("benefits.{$key}", []);
    }

    /**
     * Get Domicile rules
     *
     * @return array Contains uk_domiciled, non_uk_domiciled rules
     */
    public function getDomicile(): array
    {
        return $this->get('domicile', []);
    }

    // =========================================================================
    // Private Methods
    // =========================================================================

    /**
     * Load active tax configuration (with request-scoped caching)
     *
     * @throws RuntimeException if no active tax year found
     */
    private function loadActiveConfig(): array
    {
        // Return cached config if already loaded
        if ($this->config !== null) {
            return $this->config;
        }

        // Load active tax configuration from database
        $this->taxConfigModel = TaxConfiguration::where('is_active', true)->first();

        if (! $this->taxConfigModel) {
            throw new RuntimeException(
                'No active tax configuration found. Please run TaxConfigurationSeeder or activate a tax year.'
            );
        }

        // Cache the config_data array for this request
        $this->config = $this->taxConfigModel->config_data;

        // Log which tax year is being used (helpful for debugging)
        logger()->debug('Tax Configuration Service loaded', [
            'tax_year' => $this->config['tax_year'] ?? 'unknown',
            'effective_from' => $this->config['effective_from'] ?? 'unknown',
        ]);

        return $this->config;
    }

    /**
     * Clear cached configuration (mainly for testing)
     */
    public function clearCache(): void
    {
        $this->config = null;
        $this->taxConfigModel = null;
    }

    /**
     * Get the underlying TaxConfiguration model (if needed for relationships)
     */
    public function getModel(): ?TaxConfiguration
    {
        if ($this->taxConfigModel === null) {
            $this->loadActiveConfig();
        }

        return $this->taxConfigModel;
    }

    /**
     * Get property ownership information including joint ownership types and leasehold reform
     *
     * @return array Contains joint_ownership_types, leasehold_reform, tenure_types
     */
    public function getPropertyOwnership(): array
    {
        return $this->get('property_ownership', []);
    }

    /**
     * Get joint ownership type information
     *
     * @param  string|null  $type  Optional specific type ('joint_tenancy' or 'tenants_in_common')
     */
    public function getJointOwnershipType(?string $type = null): ?array
    {
        $types = $this->get('property_ownership.joint_ownership_types', []);

        if ($type !== null) {
            return $types[$type] ?? null;
        }

        return $types;
    }

    /**
     * Get leasehold reform information
     *
     * @return array Contains ground_rent_abolished_date, valuation_thresholds, etc.
     */
    public function getLeaseholdReform(): array
    {
        return $this->get('property_ownership.leasehold_reform', []);
    }

    /**
     * Check if a leasehold property is approaching problematic remaining lease term
     *
     * @return array Returns warnings and thresholds
     */
    public function getLeaseholdValuationWarnings(int $remainingYears): array
    {
        $reform = $this->getLeaseholdReform();
        $thresholds = $reform['valuation_thresholds'] ?? ['difficult_to_mortgage' => 80, 'significant_value_loss' => 60];

        $warnings = [];

        if ($remainingYears < $thresholds['difficult_to_mortgage']) {
            $warnings[] = [
                'level' => 'warning',
                'message' => 'Properties with less than '.$thresholds['difficult_to_mortgage'].' years remaining may be difficult to mortgage',
            ];
        }

        if ($remainingYears < $thresholds['significant_value_loss']) {
            $warnings[] = [
                'level' => 'danger',
                'message' => 'Properties with less than '.$thresholds['significant_value_loss'].' years remaining may significantly lose value',
            ];
        }

        return [
            'has_warnings' => count($warnings) > 0,
            'warnings' => $warnings,
            'thresholds' => $thresholds,
            'remaining_years' => $remainingYears,
        ];
    }

    /**
     * Check if joint tenancy has survivorship rights (for IHT calculations)
     */
    public function hasSurvivorshipRights(string $jointOwnershipType): bool
    {
        $typeInfo = $this->getJointOwnershipType($jointOwnershipType);

        return $typeInfo['survivorship'] ?? false;
    }

    /**
     * Check if joint ownership type allows will override
     */
    public function allowsWillOverride(string $jointOwnershipType): bool
    {
        $typeInfo = $this->getJointOwnershipType($jointOwnershipType);

        return $typeInfo['will_override'] ?? false;
    }

    /**
     * Get Child Benefit configuration
     *
     * @return array Contains weekly/annual rates and HICBC thresholds
     */
    public function getChildBenefit(): array
    {
        return $this->get('benefits.child_benefit', [
            'eldest_child_weekly' => 26.05,
            'additional_child_weekly' => 17.25,
            'eldest_child_annual' => 1354.60,
            'additional_child_annual' => 897.00,
            'high_income_charge_threshold' => 60000,
            'high_income_full_clawback' => 80000,
            'clawback_increment' => 200,
        ]);
    }

    /**
     * Get Tax-Free Childcare configuration.
     *
     * @return array Contains top-up rates, limits, income thresholds, and warnings
     */
    public function getTaxFreeChildcare(): array
    {
        return $this->get('benefits.tax_free_childcare', [
            'government_top_up_rate' => 0.25,
            'max_government_contribution' => 2000,
            'max_disabled_contribution' => 4000,
            'child_age_limit' => 11,
            'max_income_threshold' => 100000,
        ]);
    }

    /**
     * Get Early Years Funding configuration.
     *
     * @return array Contains funded hours entitlements, age ranges, income thresholds, and warnings
     */
    public function getEarlyYearsFunding(): array
    {
        return $this->get('benefits.early_years_funding', [
            'universal_15hrs' => ['hours_per_week' => 15, 'weeks_per_year' => 38, 'income_test' => false],
            'working_parents_30hrs' => ['hours_per_week' => 30, 'weeks_per_year' => 38, 'max_income_threshold' => 100000],
        ]);
    }

    // ------------------------------------------------------------------
    // TaxEngine contract surface
    // ------------------------------------------------------------------
    //
    // The methods below satisfy Fynla\Core\Contracts\TaxEngine so this
    // service can be bound as pack.gb.tax via the GbPackServiceProvider
    // (Phase 0 Workstream 0.2). UK-relevant calculations are stubs for now
    // — full implementations land when the UK engine graduates into a
    // Composer pack post-Phase 2 per Implementation_Plan_v2.md § 5.1.
    // SA-only methods return ['not_applicable' => true] / 0.
    //

    /**
     * {@inheritDoc}
     */
    public function calculateIncomeTax(int $grossMinor, string $taxYear): array
    {
        // UK income tax calculation is handled by UKTaxCalculator / module
        // services today. A stub here preserves the contract until the UK
        // engine graduates to a pack.
        return [
            'tax_due' => 0,
            'effective_rate' => 0.0,
            'marginal_rate' => 0.0,
            'breakdown' => [],
            'not_applicable' => true,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function calculateCGT(int $gainMinor, string $taxYear, array $options = []): array
    {
        return [
            'tax_due' => 0,
            'exemption_used' => 0,
            'taxable_gain' => 0,
            'breakdown' => [],
            'not_applicable' => true,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * UK has no cumulative lump-sum table analogous to SARS. UK pension
     * lump-sum tax is handled via income-tax bracketing on the non-PCLS
     * portion in the Retirement module services.
     */
    public function calculateLumpSumTax(
        int $amountMinor,
        string $taxYear,
        int $priorCumulativeMinor,
        string $tableType
    ): array {
        return [
            'tax_due_minor' => 0,
            'cumulative_tax_minor' => 0,
            'prior_tax_minor' => 0,
            'table_applied' => $tableType,
            'not_applicable' => true,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * UK has no carry-forward mechanism analogous to Section 11F. Annual
     * Allowance unused amounts are handled by the Retirement module
     * services against the 3-year AA carry-forward rules.
     */
    public function calculateRetirementDeduction(
        int $grossMinor,
        string $taxYear,
        int $carryForwardMinor
    ): array {
        return [
            'deductible_minor' => 0,
            'carry_forward_minor' => 0,
            'cap_applied_minor' => 0,
            'not_applicable' => true,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * UK does not withhold tax on dividends at source (the dividend
     * allowance and marginal-rate bracketing are applied via self-assessment).
     */
    public function calculateDividendsWithholdingTax(
        int $amountMinor,
        string $taxYear,
        string $source
    ): int {
        return 0;
    }

    /**
     * {@inheritDoc}
     *
     * UK has no medical-scheme tax credit analogous to SARS.
     */
    public function calculateMedicalCredits(
        int $mainPlusFirstDependant,
        int $additionalDependants,
        string $taxYear
    ): int {
        return 0;
    }

    /**
     * {@inheritDoc}
     *
     * UK personal allowance is a flat amount; the $age parameter is ignored.
     */
    public function getPersonalAllowance(string $taxYear, ?int $age = null): int
    {
        $allowance = $this->get('income_tax.personal_allowance', 0);

        // Stored in whole pounds per the UK JSON config; convert to pence
        // (minor units) to satisfy the contract.
        return (int) round(((float) $allowance) * 100);
    }

    /**
     * {@inheritDoc}
     *
     * Returns the UK income-tax bands from the active configuration,
     * converted to minor units.
     */
    public function getTaxBrackets(string $taxYear): array
    {
        $bands = $this->get('income_tax.bands', []);

        if (! is_array($bands)) {
            return [];
        }

        $brackets = [];
        foreach ($bands as $name => $band) {
            if (! is_array($band)) {
                continue;
            }

            $lower = isset($band['from']) ? (int) round(((float) $band['from']) * 100) : 0;
            $upper = isset($band['to']) && $band['to'] !== null
                ? (int) round(((float) $band['to']) * 100)
                : null;
            $rate = isset($band['rate']) ? (float) $band['rate'] : 0.0;

            $brackets[] = [
                'name' => is_string($name) ? $name : (string) ($band['name'] ?? ''),
                'lower' => $lower,
                'upper' => $upper,
                'rate' => $rate,
            ];
        }

        return $brackets;
    }

    /**
     * {@inheritDoc}
     *
     * Returns key UK annual exemptions in minor units.
     */
    public function getAnnualExemptions(string $taxYear): array
    {
        $pence = static fn (mixed $v): int => (int) round(((float) $v) * 100);

        $cgt = $this->getCapitalGainsTax();
        $iht = $this->getInheritanceTax();
        $isa = $this->getISAAllowances();
        $div = $this->getDividendTax();

        return [
            'cgt_annual_exemption' => $pence($cgt['annual_exemption'] ?? 0),
            'iht_nil_rate_band' => $pence($iht['nil_rate_band'] ?? 0),
            'iht_residence_nil_rate_band' => $pence($iht['residence_nil_rate_band'] ?? 0),
            'isa_annual_allowance' => $pence($isa['annual_allowance'] ?? 0),
            'dividend_allowance' => $pence($div['allowance'] ?? 0),
        ];
    }
}
