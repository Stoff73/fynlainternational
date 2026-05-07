<?php

declare(strict_types=1);

arch('ProtectionAgent extends BaseAgent')
    ->expect('Fynla\Packs\Gb\Agents\ProtectionAgent')
    ->toExtend('App\Agents\BaseAgent');

arch('Protection services are in correct namespace')
    ->expect('App\Services\Protection')
    ->toBeClasses()
    ->not->toBeAbstract();

arch('Protection models have user relationship')
    ->expect('Fynla\Packs\Gb\Models\ProtectionProfile')
    ->toHaveMethod('user')
    ->and('Fynla\Packs\Gb\Models\LifeInsurancePolicy')
    ->toHaveMethod('user')
    ->and('Fynla\Packs\Gb\Models\CriticalIllnessPolicy')
    ->toHaveMethod('user')
    ->and('Fynla\Packs\Gb\Models\IncomeProtectionPolicy')
    ->toHaveMethod('user')
    ->and('Fynla\Packs\Gb\Models\DisabilityPolicy')
    ->toHaveMethod('user')
    ->and('Fynla\Packs\Gb\Models\SicknessIllnessPolicy')
    ->toHaveMethod('user');

arch('Protection form requests extend FormRequest')
    ->expect('App\Http\Requests\Protection')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('Protection controllers are in correct namespace')
    ->expect('App\Http\Controllers\Api\ProtectionController')
    ->toBeClass();

arch('strict types declared in Protection files')
    ->expect('App\Services\Protection')
    ->toUseStrictTypes()
    ->and('Fynla\Packs\Gb\Agents\ProtectionAgent')
    ->toUseStrictTypes();
