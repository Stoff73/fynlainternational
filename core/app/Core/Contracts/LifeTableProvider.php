<?php

declare(strict_types=1);

namespace Fynla\Core\Contracts;

/**
 * Actuarial life tables contract for a jurisdiction.
 *
 * Provides life expectancy and survival probability calculations
 * using the jurisdiction's official or recognised actuarial tables.
 */
interface LifeTableProvider
{
    /**
     * Get the expected remaining lifespan for an individual.
     *
     * @param string $dateOfBirth ISO 8601 date (YYYY-MM-DD)
     * @param string $gender      "male" or "female"
     *
     * @return float Life expectancy in years (e.g. 82.4)
     */
    public function getLifeExpectancy(string $dateOfBirth, string $gender): float;

    /**
     * Calculate the probability of surviving from a current age to a target age.
     *
     * @param int    $currentAge Current age in whole years
     * @param int    $targetAge  Target age in whole years
     * @param string $gender     "male" or "female"
     *
     * @return float Survival probability as a decimal between 0.0 and 1.0
     */
    public function getSurvivalProbability(int $currentAge, int $targetAge, string $gender): float;
}
