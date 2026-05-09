<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Tax;

use Fynla\Packs\Gb\Constants\TaxDefaults;
use Fynla\Packs\Gb\Models\Investment\InvestmentAccount;
use Fynla\Packs\Gb\Models\SavingsAccount;
use Fynla\Packs\Gb\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use Fynla\Packs\Gb\Traits\FormatsCurrency;
use Fynla\Core\Traits\StructuredLogging;

/**
 * Evaluates tax action definitions against user data
 * to produce configurable, database-driven tax optimisation recommendations.
 *
 * Mirrors InvestmentActionDefinitionService — each trigger condition
 * maps to one private evaluator method that checks the condition
 * and returns zero or more recommendations.
 *
 * R-14a-Tax-i: relocated from app/Services/Tax/ → packs/country-gb/src/Tax/.
 * Internal helpers and local money state are int-minor (pence). The
 * `estimated_impact` recommendation key remains float-pounds because it is
 * the shared cross-pack action shape consumed by frontend, BasePlanService,
 * and the other *ActionDefinitionService siblings (Estate, Savings, etc.) —
 * touching that key here would break the contract one service ahead of the
 * coordinated migration. Template-rendered `£` display strings render from
 * pence at print time.
 */
class TaxActionDefinitionService
{
    use FormatsCurrency;
    use StructuredLogging;

    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly AnnualAllowanceChecker $allowanceChecker
    ) {}

    /**
     * Evaluate all enabled tax action definitions against a user's data.
     *
     * @return array{recommendations: array, total_count: int, high_priority_count: int}
     */
    public function evaluateActions(User $user): array
    {
        $definitions = TaxActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        foreach ($definitions as $definition) {
            $results = $this->evaluateTrigger($definition, $user, $priority);

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => in_array($r['impact'] ?? '', ['Critical', 'High'], true))),
        ];
    }

    // =========================================================================
    // Trigger dispatch
    // =========================================================================

    /**
     * Dispatch a single trigger to the appropriate evaluator.
     *
     * @return array List of recommendations (may be empty)
     */
    private function evaluateTrigger(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'isa_not_maxed' => $this->evaluateIsaNotMaxed($definition, $user, $priority),
            'pension_carry_forward_available' => $this->evaluatePensionCarryForward($definition, $user, $priority),
            'spousal_transfer_beneficial' => $this->evaluateSpousalTransfer($definition, $user, $priority),
            'cgt_allowance_unused' => $this->evaluateCgtAllowanceUnused($definition, $user, $priority),
            'high_dividend_in_gia' => $this->evaluateHighDividendInGia($definition, $user, $priority),
            default => [],
        };
    }

    // =========================================================================
    // Evaluators (5)
    // =========================================================================

    /**
     * ISA not maxed: triggers when total ISA subscriptions are below the annual allowance.
     */
    private function evaluateIsaNotMaxed(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowanceMinor = self::poundsToMinor($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        // Investment ISAs
        $investmentISASubscribedMinor = self::poundsToMinor(
            InvestmentAccount::where('user_id', $user->id)
                ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
                ->sum('isa_subscription_current_year')
        );

        // Cash ISAs from savings
        $cashISASubscribedMinor = self::poundsToMinor(
            SavingsAccount::where('user_id', $user->id)
                ->where('account_type', 'isa')
                ->sum('isa_subscription_amount')
        );

        $totalUsedMinor = $investmentISASubscribedMinor + $cashISASubscribedMinor;
        $remainingMinor = $isaAllowanceMinor - $totalUsedMinor;

        if ($remainingMinor <= 0) {
            return [];
        }

        $vars = [
            'isa_used' => '£'.number_format($totalUsedMinor / 100, 0),
            'isa_allowance' => '£'.number_format($isaAllowanceMinor / 100, 0),
            'isa_remaining' => '£'.number_format($remainingMinor / 100, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * Pension carry forward: triggers when unused Annual Allowance is available from prior years.
     */
    private function evaluatePensionCarryForward(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $taxYear = $this->taxConfig->getTaxYear();
        $allowanceResult = $this->allowanceChecker->checkAnnualAllowance($user->id, $taxYear);

        $carryForwardMinor = self::poundsToMinor($allowanceResult['carry_forward_available'] ?? 0);

        if ($carryForwardMinor <= 0) {
            return [];
        }

        $grossIncomeMinor = self::poundsToMinor($user->annual_employment_income ?? 0);
        $taxRate = $this->determineMarginalRate($grossIncomeMinor);

        $vars = [
            'carry_forward' => '£'.number_format($carryForwardMinor / 100, 0),
            'tax_rate' => (string) $taxRate,
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        // Cross-pack action contract: estimated_impact is float pounds.
        $rec['estimated_impact'] = round(($carryForwardMinor * $taxRate / 100) / 100, 2);

        return [$rec];
    }

    /**
     * Spousal transfer: triggers when married user and spouse are in different tax bands.
     */
    private function evaluateSpousalTransfer(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        if ($user->marital_status !== 'married' || ! $user->spouse_id) {
            return [];
        }

        $spouse = User::find($user->spouse_id);
        if (! $spouse) {
            return [];
        }

        $userIncomeMinor = self::poundsToMinor($user->annual_employment_income ?? 0);
        $spouseIncomeMinor = self::poundsToMinor($spouse->annual_employment_income ?? 0);

        $userBand = $this->determineTaxBand($userIncomeMinor);
        $spouseBand = $this->determineTaxBand($spouseIncomeMinor);

        if ($userBand === $spouseBand) {
            return [];
        }

        // Estimate potential saving from income shifting
        $higherRate = $this->determineMarginalRate(max($userIncomeMinor, $spouseIncomeMinor));
        $lowerRate = $this->determineMarginalRate(min($userIncomeMinor, $spouseIncomeMinor));
        $rateDifferenceBps = $higherRate - $lowerRate; // percentage-point difference

        // Conservative estimate: shift 4% of GIA value as investment income
        $giaValueMinor = self::poundsToMinor(
            InvestmentAccount::where('user_id', $user->id)
                ->where('account_type', 'gia')
                ->sum('current_value')
        );
        $estimatedInvestmentIncomeMinor = (int) round($giaValueMinor * 0.04);
        $potentialSavingMinor = (int) round($estimatedInvestmentIncomeMinor * $rateDifferenceBps / 100);

        if ($potentialSavingMinor <= 0) {
            $potentialSavingMinor = 20000; // £200 conservative fallback
        }

        $vars = [
            'user_band' => $userIncomeMinor >= $spouseIncomeMinor ? $userBand : $spouseBand,
            'spouse_band' => $userIncomeMinor >= $spouseIncomeMinor ? $spouseBand : $userBand,
            'potential_saving' => '£'.number_format($potentialSavingMinor / 100, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        // Cross-pack action contract: estimated_impact is float pounds.
        $rec['estimated_impact'] = round($potentialSavingMinor / 100, 2);

        return [$rec];
    }

    /**
     * CGT allowance unused: triggers when user has GIA holdings with unrealised gains
     * and has not used the CGT annual exemption.
     */
    private function evaluateCgtAllowanceUnused(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $annualExemptMinor = self::poundsToMinor($cgtConfig['annual_exempt_amount'] ?? TaxDefaults::CGT_ANNUAL_EXEMPT);

        $giaAccounts = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->with('holdings')
            ->get();

        if ($giaAccounts->isEmpty()) {
            return [];
        }

        $unrealisedGainsMinor = 0;
        $totalGiaValueMinor = 0;

        foreach ($giaAccounts as $account) {
            $totalGiaValueMinor += self::poundsToMinor($account->current_value);
            foreach ($account->holdings as $holding) {
                if ($holding->cost_basis && $holding->current_value) {
                    $gainLossMinor = self::poundsToMinor($holding->current_value) - self::poundsToMinor($holding->cost_basis);
                    if ($gainLossMinor > 0) {
                        $unrealisedGainsMinor += $gainLossMinor;
                    }
                }
            }
        }

        if ($unrealisedGainsMinor <= 0) {
            return [];
        }

        $vars = [
            'gia_value' => '£'.number_format($totalGiaValueMinor / 100, 0),
            'cgt_exemption' => '£'.number_format($annualExemptMinor / 100, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    /**
     * High dividend in GIA: triggers when GIA holdings exceed threshold
     * and could benefit from ISA sheltering for dividends.
     */
    private function evaluateHighDividendInGia(
        TaxActionDefinition $definition,
        User $user,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $minGiaValueMinor = self::poundsToMinor($config['min_gia_value'] ?? 10000);

        $giaValueMinor = self::poundsToMinor(
            InvestmentAccount::where('user_id', $user->id)
                ->where('account_type', 'gia')
                ->sum('current_value')
        );

        if ($giaValueMinor < $minGiaValueMinor) {
            return [];
        }

        // Check if user has ISA remaining capacity
        $isaConfig = $this->taxConfig->getISAAllowances();
        $isaAllowanceMinor = self::poundsToMinor($isaConfig['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE);

        $investmentISASubscribedMinor = self::poundsToMinor(
            InvestmentAccount::where('user_id', $user->id)
                ->whereIn('account_type', ['isa', 'stocks_shares_isa'])
                ->sum('isa_subscription_current_year')
        );
        $cashISASubscribedMinor = self::poundsToMinor(
            SavingsAccount::where('user_id', $user->id)
                ->where('account_type', 'isa')
                ->sum('isa_subscription_amount')
        );

        $isaRemainingMinor = $isaAllowanceMinor - ($investmentISASubscribedMinor + $cashISASubscribedMinor);
        if ($isaRemainingMinor <= 0) {
            return [];
        }

        $dividendTaxConfig = $this->taxConfig->getDividendTax();
        $dividendAllowanceMinor = self::poundsToMinor($dividendTaxConfig['allowance'] ?? 500);

        $vars = [
            'gia_value' => '£'.number_format($giaValueMinor / 100, 0),
            'dividend_allowance' => '£'.number_format($dividendAllowanceMinor / 100, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        TaxActionDefinition $definition,
        array $vars,
        int $priority
    ): array {
        return [
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'definition_key' => $definition->key,
        ];
    }

    /**
     * Determine the marginal tax rate for a given gross income in pence.
     */
    private function determineMarginalRate(int $grossIncomeMinor): int
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowanceMinor = self::poundsToMinor($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimitMinor = $personalAllowanceMinor + self::poundsToMinor($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThresholdMinor = self::poundsToMinor($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncomeMinor <= $personalAllowanceMinor) {
            return 0;
        }

        if ($grossIncomeMinor <= $basicRateLimitMinor) {
            return 20;
        }

        if ($grossIncomeMinor <= $additionalThresholdMinor) {
            return 40;
        }

        return 45;
    }

    /**
     * Determine the tax band name for a given gross income in pence.
     */
    private function determineTaxBand(int $grossIncomeMinor): string
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowanceMinor = self::poundsToMinor($incomeTax['personal_allowance'] ?? 12570);
        $basicRateLimitMinor = $personalAllowanceMinor + self::poundsToMinor($incomeTax['bands'][0]['max'] ?? 37700);
        $additionalThresholdMinor = self::poundsToMinor($incomeTax['additional_rate_threshold'] ?? 125140);

        if ($grossIncomeMinor <= $personalAllowanceMinor) {
            return 'non-taxpayer';
        }

        if ($grossIncomeMinor <= $basicRateLimitMinor) {
            return 'basic rate';
        }

        if ($grossIncomeMinor <= $additionalThresholdMinor) {
            return 'higher rate';
        }

        return 'additional rate';
    }

    /**
     * Convert a pounds value (int / float / numeric string / null) to pence.
     */
    private static function poundsToMinor(int|float|string|null $pounds): int
    {
        return (int) round(((float) ($pounds ?? 0)) * 100);
    }
}
