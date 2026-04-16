<?php

declare(strict_types=1);

arch('BaseAgent is abstract')
    ->expect('App\Agents\BaseAgent')
    ->toBeAbstract();

arch('BaseAgent has required abstract methods')
    ->expect('App\Agents\BaseAgent')
    ->toHaveMethod('analyze')
    ->toHaveMethod('generateRecommendations')
    ->toHaveMethod('buildScenarios');

arch('Agent classes are in the Agents namespace')
    ->expect('App\Agents')
    ->toBeClasses();
