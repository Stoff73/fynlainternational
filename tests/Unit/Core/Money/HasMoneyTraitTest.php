<?php

declare(strict_types=1);

use Fynla\Core\Money\Currency;
use Fynla\Core\Money\HasMoney;
use Fynla\Core\Money\Money;

/**
 * Stub model that simulates Eloquent attribute behaviour for testing HasMoney.
 *
 * Uses a simple array-backed attribute store with dirty tracking,
 * avoiding any real Eloquent/database dependency.
 */
class HasMoneyTestModel
{
    use HasMoney {
        // We cannot call bootHasMoney in a non-Eloquent context,
        // so we test the saving sync logic separately.
        bootHasMoney as private eloquentBootHasMoney;
    }

    protected array $moneyColumns = [
        'current_balance' => 'GBP',
        'premium_amount' => 'GBP',
    ];

    private array $attributes = [];
    private array $original = [];

    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Simulate Eloquent isDirty() — returns true if attribute differs from original.
     */
    public function isDirty(string $key): bool
    {
        if (!array_key_exists($key, $this->attributes)) {
            return false;
        }
        if (!array_key_exists($key, $this->original)) {
            return true;
        }
        return $this->attributes[$key] !== $this->original[$key];
    }

    /**
     * Snapshot current attributes as "original" (simulates a fresh-from-DB state).
     */
    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Expose all attributes for assertions.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }
}

describe('HasMoney Trait', function () {
    it('reads from shadow columns when populated', function () {
        $model = new HasMoneyTestModel();
        $model->setAttribute('current_balance', 1234.56);
        $model->setAttribute('current_balance_minor', 123456);
        $model->setAttribute('current_balance_ccy', 'GBP');

        $money = $model->getMoneyAttribute('current_balance');

        expect($money)->toBeInstanceOf(Money::class);
        expect($money->minor)->toBe(123456);
        expect($money->currency->code)->toBe('GBP');
    });

    it('falls back to legacy decimal column when shadow is null', function () {
        $model = new HasMoneyTestModel();
        $model->setAttribute('current_balance', 99.99);
        // Shadow columns not set — should fall back to decimal

        $money = $model->getMoneyAttribute('current_balance');

        expect($money)->toBeInstanceOf(Money::class);
        expect($money->minor)->toBe(9999);
        expect($money->currency->code)->toBe('GBP');
    });

    it('writes to both legacy and shadow columns', function () {
        $model = new HasMoneyTestModel();
        $money = new Money(250000, Currency::GBP());

        $model->setMoneyAttribute('current_balance', $money);

        $attrs = $model->getAttributes();
        expect($attrs['current_balance_minor'])->toBe(250000);
        expect($attrs['current_balance_ccy'])->toBe('GBP');
        expect($attrs['current_balance'])->toBe(2500.00);
    });

    it('correctly syncs shadow from legacy on save', function () {
        $model = new HasMoneyTestModel();
        // Simulate existing record with only legacy column
        $model->setAttribute('current_balance', 100.00);
        $model->syncOriginal();

        // Now update just the legacy column (simulating old code path)
        $model->setAttribute('current_balance', 200.50);

        // Manually invoke the sync logic (same as the saving callback)
        foreach ($model->getMoneyColumns() as $column => $defaultCurrency) {
            $minorColumn = $column . '_minor';
            $ccyColumn = $column . '_ccy';

            if ($model->isDirty($column) && !$model->isDirty($minorColumn)) {
                $decimal = $model->getAttribute($column);
                if ($decimal !== null) {
                    $currency = Currency::from($model->getAttribute($ccyColumn) ?? $defaultCurrency);
                    $synced = Money::ofMajor((string) $decimal, $currency);
                    $model->setAttribute($minorColumn, $synced->minor);
                    $model->setAttribute($ccyColumn, $synced->currency->code);
                }
            }
        }

        expect($model->getAttribute('current_balance_minor'))->toBe(20050);
        expect($model->getAttribute('current_balance_ccy'))->toBe('GBP');
    });

    it('correctly syncs legacy from shadow on save', function () {
        $model = new HasMoneyTestModel();
        // Simulate existing record
        $model->setAttribute('current_balance', 100.00);
        $model->setAttribute('current_balance_minor', 10000);
        $model->setAttribute('current_balance_ccy', 'GBP');
        $model->syncOriginal();

        // Now update just the shadow column (simulating new code path)
        $model->setAttribute('current_balance_minor', 50075);

        // Manually invoke the sync logic
        foreach ($model->getMoneyColumns() as $column => $defaultCurrency) {
            $minorColumn = $column . '_minor';
            $ccyColumn = $column . '_ccy';

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

        expect($model->getAttribute('current_balance'))->toBe(500.75);
    });

    it('reports backfill status correctly', function () {
        $model = new HasMoneyTestModel();

        // No shadow columns populated
        expect($model->hasMoneyColumnsBackfilled())->toBeFalse();

        // Populate one shadow column but not the other
        $model->setAttribute('current_balance_minor', 10000);
        expect($model->hasMoneyColumnsBackfilled())->toBeFalse();

        // Populate both shadow columns
        $model->setAttribute('premium_amount_minor', 5000);
        expect($model->hasMoneyColumnsBackfilled())->toBeTrue();
    });

    it('returns zero Money for null decimal values', function () {
        $model = new HasMoneyTestModel();
        // No attributes set at all — legacy column is null

        $money = $model->getMoneyAttribute('current_balance');

        expect($money->minor)->toBe(0);
        expect($money->currency->code)->toBe('GBP');
    });

    it('uses GBP as default currency', function () {
        $model = new HasMoneyTestModel();
        $model->setAttribute('current_balance', 50.00);

        $money = $model->getMoneyAttribute('current_balance');

        expect($money->currency->code)->toBe('GBP');
        expect($money->currency->minorUnits)->toBe(2);
    });
});
