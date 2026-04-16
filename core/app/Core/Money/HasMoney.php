<?php

declare(strict_types=1);

namespace Fynla\Core\Money;

/**
 * Eloquent trait for dual-read/dual-write money column migration.
 *
 * Models using this trait declare which columns are money columns via
 * a $moneyColumns property. During migration, the trait writes to both
 * the legacy decimal column and the shadow (minor, ccy) columns.
 *
 * Usage:
 *   protected array $moneyColumns = [
 *       'current_balance' => 'GBP',  // column_name => default_currency
 *       'premium_amount' => 'GBP',
 *   ];
 *
 * This creates shadow columns: current_balance_minor (bigint), current_balance_ccy (char 3)
 *
 * Read via: $model->getMoneyAttribute('current_balance') returns Money
 * Write via: $model->setMoneyAttribute('current_balance', $money) writes both
 */
trait HasMoney
{
    /**
     * Get a money column as a Money value object.
     *
     * Reads from shadow columns (_minor, _ccy) if populated.
     * Falls back to legacy decimal column with default currency.
     */
    public function getMoneyAttribute(string $column): Money
    {
        $minorColumn = $column . '_minor';
        $ccyColumn = $column . '_ccy';

        // Prefer shadow columns if populated
        if ($this->getAttribute($minorColumn) !== null && $this->getAttribute($ccyColumn) !== null) {
            return new Money(
                (int) $this->getAttribute($minorColumn),
                Currency::from($this->getAttribute($ccyColumn)),
            );
        }

        // Fall back to legacy decimal column
        $decimal = $this->getAttribute($column);
        $defaultCurrency = $this->getDefaultCurrencyForColumn($column);

        if ($decimal === null) {
            return new Money(0, $defaultCurrency);
        }

        return Money::ofMajor((string) $decimal, $defaultCurrency);
    }

    /**
     * Set a money column, writing to both legacy decimal and shadow columns.
     */
    public function setMoneyAttribute(string $column, Money $money): void
    {
        $minorColumn = $column . '_minor';
        $ccyColumn = $column . '_ccy';

        // Write to shadow columns
        $this->setAttribute($minorColumn, $money->minor);
        $this->setAttribute($ccyColumn, $money->currency->code);

        // Dual-write: also write back to legacy decimal for backward compatibility
        $divisor = 10 ** $money->currency->minorUnits;
        $this->setAttribute($column, round($money->minor / $divisor, $money->currency->minorUnits));
    }

    /**
     * Get the default currency for a money column.
     * Uses the $moneyColumns property defined on the model.
     */
    protected function getDefaultCurrencyForColumn(string $column): Currency
    {
        $moneyColumns = $this->moneyColumns ?? [];
        $code = $moneyColumns[$column] ?? 'GBP';  // Default to GBP for Phase 0

        return Currency::from($code);
    }

    /**
     * Boot the HasMoney trait — register the saving event to dual-write.
     */
    public static function bootHasMoney(): void
    {
        static::saving(function ($model) {
            if (!property_exists($model, 'moneyColumns') || empty($model->moneyColumns)) {
                return;
            }

            foreach ($model->moneyColumns as $column => $defaultCurrency) {
                $minorColumn = $column . '_minor';
                $ccyColumn = $column . '_ccy';

                // If legacy column was changed but shadow wasn't, sync shadow from legacy
                if ($model->isDirty($column) && !$model->isDirty($minorColumn)) {
                    $decimal = $model->getAttribute($column);
                    if ($decimal !== null) {
                        $currency = Currency::from($model->getAttribute($ccyColumn) ?? $defaultCurrency);
                        $money = Money::ofMajor((string) $decimal, $currency);
                        $model->setAttribute($minorColumn, $money->minor);
                        $model->setAttribute($ccyColumn, $money->currency->code);
                    }
                }

                // If shadow was changed but legacy wasn't, sync legacy from shadow
                if ($model->isDirty($minorColumn) && !$model->isDirty($column)) {
                    $minor = $model->getAttribute($minorColumn);
                    $ccy = $model->getAttribute($ccyColumn) ?? $defaultCurrency;
                    if ($minor !== null) {
                        $currency = Currency::from($ccy);
                        $divisor = 10 ** $currency->minorUnits;
                        $model->setAttribute($column, round($minor / $divisor, $currency->minorUnits));
                    }
                }
            }
        });
    }

    /**
     * Get all money column names defined on this model.
     */
    public function getMoneyColumns(): array
    {
        return $this->moneyColumns ?? [];
    }

    /**
     * Check if all shadow columns for money fields are populated.
     */
    public function hasMoneyColumnsBackfilled(): bool
    {
        foreach ($this->getMoneyColumns() as $column => $defaultCurrency) {
            if ($this->getAttribute($column . '_minor') === null) {
                return false;
            }
        }
        return true;
    }
}
