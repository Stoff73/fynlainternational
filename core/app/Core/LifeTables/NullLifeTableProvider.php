<?php

declare(strict_types=1);

namespace Fynla\Core\LifeTables;

use Fynla\Core\Contracts\LifeTableProvider;

/**
 * Sentinel LifeTableProvider used while a pack does not yet supply
 * a real one. Returns a conservative 80.0 year life expectancy and
 * a 0.0 survival probability for any (currentAge, targetAge) pair.
 *
 * Packs MUST replace this with a real provider before any
 * retirement projection is shown to the user — a Null provider
 * makes Monte-Carlo decumulation projections meaningless.
 */
final class NullLifeTableProvider implements LifeTableProvider
{
    public function getLifeExpectancy(string $dateOfBirth, string $gender): float
    {
        return 80.0;
    }

    public function getSurvivalProbability(int $currentAge, int $targetAge, string $gender): float
    {
        return 0.0;
    }
}
