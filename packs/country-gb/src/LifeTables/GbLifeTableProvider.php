<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\LifeTables;

use DateTimeImmutable;
use Fynla\Core\Contracts\LifeTableProvider;
use Fynla\Packs\Gb\Models\ActuarialLifeTable;

/**
 * UK life-table provider backed by the ONS National Life Tables 2020-2022,
 * loaded into the actuarial_life_tables table by ActuarialLifeTablesSeeder.
 *
 * The seeded data has rows at 5-year age intervals (0, 1, 5, 10, …, 100).
 * Both lookups linearly interpolate between the surrounding rows when no
 * exact age match exists, mirroring the convention already in use by
 * FutureValueCalculator and TrustService.
 */
class GbLifeTableProvider implements LifeTableProvider
{
    private const TABLE_YEAR = '2020-2022';

    public function getLifeExpectancy(string $dateOfBirth, string $gender): float
    {
        $age = $this->ageFromDateOfBirth($dateOfBirth);
        $gender = $this->normaliseGender($gender);

        return $this->interpolatedLifeExpectancy($age, $gender);
    }

    public function getSurvivalProbability(int $currentAge, int $targetAge, string $gender): float
    {
        if ($targetAge <= $currentAge) {
            return 1.0;
        }

        $gender = $this->normaliseGender($gender);

        $survival = 1.0;
        for ($age = $currentAge; $age < $targetAge; $age++) {
            $probDeath = $this->interpolatedProbabilityOfDeath($age, $gender);
            $survival *= max(0.0, 1.0 - $probDeath);

            if ($survival <= 0.0) {
                return 0.0;
            }
        }

        return $survival;
    }

    private function interpolatedLifeExpectancy(int $age, string $gender): float
    {
        $exact = ActuarialLifeTable::query()
            ->where('age', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->value('life_expectancy_years');

        if ($exact !== null) {
            return (float) $exact;
        }

        $lower = ActuarialLifeTable::query()
            ->where('age', '<', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->orderBy('age', 'desc')
            ->first();

        $upper = ActuarialLifeTable::query()
            ->where('age', '>', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->orderBy('age', 'asc')
            ->first();

        if (! $lower && $upper) {
            return (float) $upper->life_expectancy_years + ($upper->age - $age);
        }

        if ($lower && ! $upper) {
            return max(1.0, (float) $lower->life_expectancy_years - ($age - $lower->age));
        }

        if ($lower && $upper) {
            $lowerLE = (float) $lower->life_expectancy_years;
            $upperLE = (float) $upper->life_expectancy_years;
            $fraction = ($age - $lower->age) / ($upper->age - $lower->age);

            return $lowerLE + ($upperLE - $lowerLE) * $fraction;
        }

        // Fallback when the table has no rows for this gender (defensive).
        return 80.0;
    }

    private function interpolatedProbabilityOfDeath(int $age, string $gender): float
    {
        $exact = ActuarialLifeTable::query()
            ->where('age', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->value('probability_of_death');

        if ($exact !== null) {
            return (float) $exact;
        }

        $lower = ActuarialLifeTable::query()
            ->where('age', '<', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->orderBy('age', 'desc')
            ->first();

        $upper = ActuarialLifeTable::query()
            ->where('age', '>', $age)
            ->where('gender', $gender)
            ->where('table_year', self::TABLE_YEAR)
            ->orderBy('age', 'asc')
            ->first();

        if ($lower && $upper) {
            $lowerPD = (float) $lower->probability_of_death;
            $upperPD = (float) $upper->probability_of_death;
            $fraction = ($age - $lower->age) / ($upper->age - $lower->age);

            return $lowerPD + ($upperPD - $lowerPD) * $fraction;
        }

        if ($lower) {
            return (float) $lower->probability_of_death;
        }

        if ($upper) {
            return (float) $upper->probability_of_death;
        }

        return 0.0;
    }

    private function ageFromDateOfBirth(string $dateOfBirth): int
    {
        $dob = new DateTimeImmutable($dateOfBirth);
        $now = new DateTimeImmutable('now');

        return $now->diff($dob)->y;
    }

    private function normaliseGender(string $gender): string
    {
        $gender = strtolower(trim($gender));

        return in_array($gender, ['male', 'female'], true) ? $gender : 'male';
    }
}
