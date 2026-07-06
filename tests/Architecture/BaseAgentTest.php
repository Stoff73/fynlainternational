<?php

declare(strict_types=1);

arch('BaseAgent is abstract')
    ->expect('Fynla\Core\Agents\BaseAgent')
    ->toBeAbstract();

arch('BaseAgent has required abstract methods')
    ->expect('Fynla\Core\Agents\BaseAgent')
    ->toHaveMethod('analyze')
    ->toHaveMethod('generateRecommendations')
    ->toHaveMethod('buildScenarios');

arch('Agent classes are in the Agents namespace')
    ->expect('Fynla\Packs\Gb\Agents')
    ->toBeClasses();
