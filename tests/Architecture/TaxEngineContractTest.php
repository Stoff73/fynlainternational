<?php

declare(strict_types=1);

use App\Services\TaxConfigService;
use Fynla\Core\Contracts\TaxEngine;

describe('TaxEngine contract implementation', function () {
    it('TaxConfigService implements the TaxEngine contract', function () {
        expect(class_implements(TaxConfigService::class))
            ->toContain(TaxEngine::class);
    });

    it('TaxEngine contract exposes the nine expected methods', function () {
        $reflection = new ReflectionClass(TaxEngine::class);
        $methodNames = array_map(
            fn (ReflectionMethod $m): string => $m->getName(),
            $reflection->getMethods()
        );

        expect($methodNames)->toContain(
            'calculateIncomeTax',
            'calculateCGT',
            'calculateLumpSumTax',
            'calculateRetirementDeduction',
            'calculateDividendsWithholdingTax',
            'calculateMedicalCredits',
            'getPersonalAllowance',
            'getTaxBrackets',
            'getAnnualExemptions',
        );
    });

    it('calculateCGT accepts an options parameter with default empty array', function () {
        $method = new ReflectionMethod(TaxEngine::class, 'calculateCGT');
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(3);
        expect($parameters[2]->getName())->toBe('options');
        expect($parameters[2]->isDefaultValueAvailable())->toBeTrue();
        expect($parameters[2]->getDefaultValue())->toBe([]);
    });

    it('getPersonalAllowance accepts an optional age parameter', function () {
        $method = new ReflectionMethod(TaxEngine::class, 'getPersonalAllowance');
        $parameters = $method->getParameters();

        expect($parameters)->toHaveCount(2);
        expect($parameters[1]->getName())->toBe('age');
        expect($parameters[1]->allowsNull())->toBeTrue();
        expect($parameters[1]->isDefaultValueAvailable())->toBeTrue();
        expect($parameters[1]->getDefaultValue())->toBeNull();
    });

    it('TaxConfigService stubs return not_applicable for SA-only methods', function () {
        $service = new TaxConfigService;

        expect($service->calculateLumpSumTax(100_00, '2025/26', 0, 'retirement'))
            ->toHaveKey('not_applicable', true);

        expect($service->calculateRetirementDeduction(100_00, '2025/26', 0))
            ->toHaveKey('not_applicable', true);

        expect($service->calculateDividendsWithholdingTax(100_00, '2025/26', 'local'))
            ->toBe(0);

        expect($service->calculateMedicalCredits(2, 0, '2025/26'))
            ->toBe(0);
    });
});
